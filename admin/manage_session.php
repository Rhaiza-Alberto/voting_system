<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';


// Handle session control actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Get active session (only pending, active, paused)
    $sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('pending', 'active', 'paused') ORDER BY id DESC LIMIT 1";
    $sessionResult = $conn->query($sessionQuery);
    $activeSession = $sessionResult->fetch_assoc();
    
    if ($activeSession) {
        $sessionId = $activeSession['id'];
        
        switch ($action) {
            case 'restart_voting':
                // Delete all votes for this session
                $stmt = $conn->prepare("DELETE FROM votes WHERE session_id = ?");
                $stmt->bind_param("i", $sessionId);
                $stmt->execute();
                $stmt->close();
                
                // Delete winners for this session
                $stmt = $conn->prepare("DELETE FROM winners WHERE session_id = ?");
                $stmt->bind_param("i", $sessionId);
                $stmt->execute();
                $stmt->close();
                
                // Reset all candidates to nominated status
                $conn->query("UPDATE candidates SET status = 'nominated'");
                
                // Reset session to pending status
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'pending', current_position_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $sessionId);
                if ($stmt->execute()) {
                    $message = ' Voting session restarted successfully! All votes have been cleared.';
                    $messageType = 'success';
                } else {
                    $message = ' Failed to restart session.';
                    $messageType = 'error';
                }
                $stmt->close();
                break;
            
            case 'open_position':
                $positionId = $_POST['position_id'];
                
                // Update session to active and set current position
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'active', current_position_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $positionId, $sessionId);
                if ($stmt->execute()) {
                    $message = ' Voting opened for this position!';
                    $messageType = 'success';
                } else {
                    $message = ' Failed to open position.';
                    $messageType = 'error';
                }
                $stmt->close();
                break;
                
            case 'close_position':
                // Close current position and mark winner
                $currentPositionId = $activeSession['current_position_id'];
                
                // Get all candidates with highest vote count to check for ties
                $topVotesQuery = "SELECT MAX(vote_count) as max_votes
                                 FROM (
                                     SELECT COUNT(v.id) as vote_count
                                     FROM candidates c
                                     LEFT JOIN votes v ON c.id = v.candidate_id AND v.session_id = ?
                                     WHERE c.position_id = ?
                                     GROUP BY c.id
                                 ) as vote_counts";
                $stmt = $conn->prepare($topVotesQuery);
                $stmt->bind_param("ii", $sessionId, $currentPositionId);
                $stmt->execute();
                $maxVotesResult = $stmt->get_result()->fetch_assoc();
                $maxVotes = $maxVotesResult['max_votes'] ?? 0;
                $stmt->close();
                
                // Get all candidates with the maximum votes (to detect ties)
                $winnersQuery = "SELECT c.id as candidate_id, c.user_id, 
                                TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) AS full_name, 
                                COUNT(v.id) as vote_count
                                FROM candidates c
                                JOIN users u ON c.user_id = u.id
                                LEFT JOIN votes v ON c.id = v.candidate_id AND v.session_id = ?
                                WHERE c.position_id = ?
                                GROUP BY c.id, c.user_id, u.first_name, u.middle_name, u.last_name
                                HAVING vote_count = ?
                                ORDER BY c.id";
                $stmt = $conn->prepare($winnersQuery);
                $stmt->bind_param("iii", $sessionId, $currentPositionId, $maxVotes);
                $stmt->execute();
                $topCandidates = $stmt->get_result();
                $stmt->close();
                
                // Check for tie
                if ($topCandidates->num_rows > 1 && $maxVotes > 0) {
                    // TIE DETECTED!
                    $tiedNames = [];
                    while ($tied = $topCandidates->fetch_assoc()) {
                        $tiedNames[] = $tied['full_name'];
                    }
                    
                    // Clear current position but don't mark any winners
                    $stmt = $conn->prepare("UPDATE voting_sessions SET current_position_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $sessionId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $message = ' TIE DETECTED! ' . $topCandidates->num_rows . ' candidates tied with ' . $maxVotes . ' votes each: ' . 
                               implode(', ', $tiedNames) . '. Use "Restart Position Voting" below to conduct a runoff vote.';
                    $messageType = 'warning';
                } elseif ($maxVotes > 0) {
                    // Clear winner - only one candidate has highest votes
                    $topCandidates->data_seek(0);
                    $winner = $topCandidates->fetch_assoc();
                    
                    // SAVE WINNER TO PERMANENT TABLE (3NF - no vote_count stored)
                    $saveWinnerStmt = $conn->prepare("INSERT INTO winners (session_id, position_id, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id = ?");
                    $saveWinnerStmt->bind_param("iiii", $sessionId, $currentPositionId, $winner['user_id'], $winner['user_id']);
                    $saveWinnerStmt->execute();
                    $saveWinnerStmt->close();
                    
                    // Get computed vote count for display message
                    $computedVoteCount = getWinnerVoteCount($sessionId, $currentPositionId, $winner['user_id'], $conn);
                    
                    // Mark winner as elected
                    $stmt = $conn->prepare("UPDATE candidates SET status = 'elected' WHERE id = ?");
                    $stmt->bind_param("i", $winner['candidate_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Mark others as lost
                    $stmt = $conn->prepare("UPDATE candidates SET status = 'lost' WHERE position_id = ? AND id != ?");
                    $stmt->bind_param("ii", $currentPositionId, $winner['candidate_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Mark winner's candidacies in lower positions as ineligible
                    $posQuery = "SELECT position_order FROM positions WHERE id = ?";
                    $stmt = $conn->prepare($posQuery);
                    $stmt->bind_param("i", $currentPositionId);
                    $stmt->execute();
                    $currentOrder = $stmt->get_result()->fetch_assoc()['position_order'];
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE candidates SET status = 'ineligible' 
                                           WHERE user_id = ? 
                                           AND position_id IN (
                                               SELECT id FROM positions WHERE position_order > ?
                                           )");
                    $stmt->bind_param("ii", $winner['user_id'], $currentOrder);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Clear current position
                    $stmt = $conn->prepare("UPDATE voting_sessions SET current_position_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $sessionId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $message = ' Position closed! Winner: ' . htmlspecialchars($winner['full_name']) . ' with ' . $computedVoteCount . ' votes. Result saved permanently!';
                    $messageType = 'success';
                } else {
                    // No votes cast
                    $stmt = $conn->prepare("UPDATE voting_sessions SET current_position_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $sessionId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $message = ' Position closed but no votes were cast.';
                    $messageType = 'warning';
                }
                break;
                
            case 'restart_position':
                // Restart voting for a specific position only
                $positionId = $_POST['position_id'];
                
                // Delete votes for this specific position
                $stmt = $conn->prepare("DELETE FROM votes WHERE session_id = ? AND position_id = ?");
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Delete winner record for this position
                $stmt = $conn->prepare("DELETE FROM winners WHERE session_id = ? AND position_id = ?");
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Reset candidates for this position to nominated status
                $stmt = $conn->prepare("UPDATE candidates SET status = 'nominated' WHERE position_id = ?");
                $stmt->bind_param("i", $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Get position name for message
                $posStmt = $conn->prepare("SELECT position_name FROM positions WHERE id = ?");
                $posStmt->bind_param("i", $positionId);
                $posStmt->execute();
                $posName = $posStmt->get_result()->fetch_assoc()['position_name'];
                $posStmt->close();
                
                $message = ' Voting restarted for ' . htmlspecialchars($posName) . '! All votes for this position have been cleared. You can now reopen voting for this position.';
                $messageType = 'success';
                break;
                
            case 'lock':
                // ========================================
                // CRITICAL FIX: PRESERVE VOTES!
                // ========================================
                // DO NOT DELETE CANDIDATES HERE!
                // Deleting candidates triggers CASCADE DELETE on votes
                // This destroys all vote history for audit logs
                
                // Just lock the session
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'locked', current_position_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $sessionId);
                $lockSuccess = $stmt->execute();
                $stmt->close();
                
                if ($lockSuccess) {
                    $message = ' Session locked successfully! All votes and winners are permanently saved in the database for audit logs. You can now create a new session.';
                    $messageType = 'success';
                } else {
                    $message = ' Failed to lock session.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get active session (only pending, active, paused - NOT locked)
$sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('pending', 'active', 'paused') ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

// Get group information if exists
$groupInfo = null;
if ($activeSession['group_id']) {
    $groupQuery = "SELECT sg.group_name, COUNT(sgm.user_id) as member_count
                   FROM student_groups sg
                   LEFT JOIN student_group_members sgm ON sg.id = sgm.group_id
                   WHERE sg.id = ?
                   GROUP BY sg.id";
    $stmt = $conn->prepare($groupQuery);
    $stmt->bind_param("i", $activeSession['group_id']);
    $stmt->execute();
    $groupInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// If no active session, redirect to dashboard
if (!$activeSession) {
    header('Location: admin_dashboard.php');
    exit();
}

$sessionId = $activeSession['id'];
$currentPositionId = $activeSession['current_position_id'];

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Get positions already completed
$completedQuery = "SELECT DISTINCT position_id FROM candidates WHERE status IN ('elected', 'lost')";
$completedResult = $conn->query($completedQuery);
$completedPositions = [];
while ($row = $completedResult->fetch_assoc()) {
    $completedPositions[] = $row['position_id'];
}

// Get vote statistics
$totalVotersQuery = "SELECT COUNT(DISTINCT voter_id) as count FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($totalVotersQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVoters = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];

// Get total votes cast
$totalVotesQuery = "SELECT COUNT(*) as count FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($totalVotesQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVotesCast = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Calculate progress metrics
$totalPositionsCount = $positions->num_rows;
$sessionProgress = 0;
$voterTurnout = 0;

if ($totalStudents > 0) {
    $voterTurnout = ($totalVoters / $totalStudents) * 100;
}

if ($totalPositionsCount > 0) {
    $sessionProgress = (count($completedPositions) / $totalPositionsCount) * 100;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Session</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5em;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        
        .message.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #10b981;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .session-status {
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            font-size: 0.95em;
            margin-bottom: 20px;
        }
        
        .status-pending {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-paused {
            background: #feebc8;
            color: #744210;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-box .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #10b981;
        }
        
        .stat-box .label {
            color: #718096;
            margin-top: 5px;
        }
        
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .current-position-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .position-list {
            display: grid;
            gap: 15px;
        }
        
        .position-item {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .position-item:hover {
            border-color: #10b981;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .position-item.active {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .position-item.completed {
            background: #f0fdf4;
            border-color: #10b981;
        }
        
        .position-item.tie-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            border-width: 3px;
        }
        
        .position-header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
        }
        
        .position-info {
            flex: 1;
        }
        
        .position-name {
            font-size: 1.2em;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .position-meta {
            color: #718096;
            font-size: 0.9em;
        }
        
        .position-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-priority {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-active {
            background: #667eea;
            color: white;
        }
        
        .badge-completed {
            background: #10b981;
            color: white;
        }
        
        .badge-tie {
            background: #f59e0b;
            color: white;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .nominees-section {
            background: #f7fafc;
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .nominees-header {
            font-size: 0.9em;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nominees-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .nominee-tag {
            background: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            color: #2d3748;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .nominee-tag.winner {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
            font-weight: 600;
        }
        
        .nominee-tag.ineligible {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
            opacity: 0.8;
        }
        
        .nominee-tag.tied {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
            font-weight: 600;
            border-width: 2px;
        }
        
        .no-nominees {
            color: #a0aec0;
            font-size: 0.85em;
            font-style: italic;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        
        .btn-warning:hover {
            background: #dd6b20;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        
        .btn-restart {
            background: #f59e0b;
            color: white;
        }
        
        .btn-restart:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #cbd5e0;
            color: #a0aec0;
            cursor: not-allowed;
            transform: none;
        }
        
        .control-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .position-header-section {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .control-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .nominees-list {
                flex-direction: column;
            }
        }
        
        /* Live Session Progress Card Styles */
        .progress-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .progress-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2d3748;
        }
        
        .progress-percentage {
            font-size: 1.5em;
            font-weight: bold;
            color: #10b981;
        }
        
        .progress-bar-container {
            width: 100%;
            height: 30px;
            background: #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.5s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .progress-bar-fill.low {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }
        
        .progress-bar-fill.medium {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .progress-bar-fill.high {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .progress-label {
            margin-top: 8px;
            font-size: 0.9em;
            color: #718096;
            text-align: center;
        }
        
        .mini-stat {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .mini-stat-icon {
            font-size: 1.5em;
        }
        
        .mini-stat-content {
            flex: 1;
        }
        
        .mini-stat-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #10b981;
        }
        
        .mini-stat-label {
            font-size: 0.85em;
            color: #718096;
        }
        
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Manage Session</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
    <h2><?php echo htmlspecialchars($activeSession['session_name']); ?></h2>
    
    <span class="session-status status-<?php echo $activeSession['status']; ?>">
        Status: <?php echo strtoupper($activeSession['status']); ?>
    </span>
    
    <?php if ($groupInfo): ?>
        <p style="margin-top: 1rem;">
            <strong>Student Group:</strong> <?php echo htmlspecialchars($groupInfo['group_name']); ?>
            (<?php echo $groupInfo['member_count']; ?> students)
        </p>
    <?php else: ?>
        <p style="margin-top: 1rem;">
            <strong>Eligible Voters:</strong> All Students
        </p>
    <?php endif; ?>
</div>

            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number"><?php echo $totalVoters; ?></div>
                    <div class="label">Total Voters</div>
                    <?php if ($totalStudents > 0): ?>
                    <div style="margin-top: 10px;">
                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                            <?php 
                            $voterPercentage = ($totalVoters / $totalStudents) * 100;
                            $voterClass = $voterPercentage >= 70 ? '#10b981' : ($voterPercentage >= 40 ? '#3b82f6' : '#f59e0b');
                            ?>
                            <div style="width: <?php echo min($voterPercentage, 100); ?>%; height: 100%; background: <?php echo $voterClass; ?>; transition: width 0.5s;"></div>
                        </div>
                        <div style="font-size: 0.75em; color: #718096; margin-top: 4px;">
                            <?php echo number_format($voterPercentage, 1); ?>% turnout
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-box">
                    <div class="number"><?php echo $totalStudents; ?></div>
                    <div class="label">Total Students</div>
                </div>
                
                <div class="stat-box">
                    <div class="number"><?php echo count($completedPositions); ?> / <?php echo $positions->num_rows; ?></div>
                    <div class="label">Positions Completed</div>
                    <?php if ($positions->num_rows > 0): ?>
                    <div style="margin-top: 10px;">
                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                            <?php 
                            $progressPercentage = (count($completedPositions) / $positions->num_rows) * 100;
                            $progressClass = $progressPercentage >= 70 ? '#10b981' : ($progressPercentage >= 40 ? '#3b82f6' : '#f59e0b');
                            ?>
                            <div style="width: <?php echo $progressPercentage; ?>%; height: 100%; background: <?php echo $progressClass; ?>; transition: width 0.5s;"></div>
                        </div>
                        <div style="font-size: 0.75em; color: #718096; margin-top: 4px;">
                            <?php echo number_format($progressPercentage, 1); ?>% complete
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Live Session Progress Card -->
        <div class="progress-card">
            <h3 style="color: #10b981; margin-bottom: 20px; display: flex; align-items: center;">
                <span class="live-indicator"></span> Live Session Progress
            </h3>
            
            <!-- Overall Session Progress -->
            <div style="margin-bottom: 25px;">
                <div class="progress-header">
                    <span class="progress-title"> Session Completion</span>
                    <span class="progress-percentage"><?php echo number_format($sessionProgress, 1); ?>%</span>
                </div>
                <div class="progress-bar-container">
                    <?php 
                    $progressClass = 'low';
                    if ($sessionProgress >= 70) $progressClass = 'high';
                    elseif ($sessionProgress >= 40) $progressClass = 'medium';
                    ?>
                    <div class="progress-bar-fill <?php echo $progressClass; ?>" style="width: <?php echo $sessionProgress; ?>%">
                        <?php if ($sessionProgress > 10): ?>
                            <?php echo count($completedPositions); ?> / <?php echo $totalPositionsCount; ?> positions
                        <?php endif; ?>
                    </div>
                </div>
                <div class="progress-label">
                    <?php echo count($completedPositions); ?> of <?php echo $totalPositionsCount; ?> positions completed
                </div>
            </div>
            
            <!-- Voter Turnout Progress -->
            <div style="margin-bottom: 25px;">
                <div class="progress-header">
                    <span class="progress-title"> Voter Turnout</span>
                    <span class="progress-percentage"><?php echo number_format($voterTurnout, 1); ?>%</span>
                </div>
                <div class="progress-bar-container">
                    <?php 
                    $turnoutClass = 'low';
                    if ($voterTurnout >= 70) $turnoutClass = 'high';
                    elseif ($voterTurnout >= 40) $turnoutClass = 'medium';
                    ?>
                    <div class="progress-bar-fill <?php echo $turnoutClass; ?>" style="width: <?php echo min($voterTurnout, 100); ?>%">
                        <?php if ($voterTurnout > 10): ?>
                            <?php echo $totalVoters; ?> / <?php echo $totalStudents; ?> students
                        <?php endif; ?>
                    </div>
                </div>
                <div class="progress-label">
                    <?php echo $totalVoters; ?> of <?php echo $totalStudents; ?> students have voted
                </div>
            </div>
            
            <!-- Mini Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="mini-stat">
                    <div class="mini-stat-content">
                        <div class="mini-stat-value"><?php echo $totalVoters; ?></div>
                        <div class="mini-stat-label">Students Voted</div>
                    </div>
                </div>
                
                <div class="mini-stat">
                
                    <div class="mini-stat-content">
                        <div class="mini-stat-value"><?php echo $totalVotesCast; ?></div>
                        <div class="mini-stat-label">Total Votes</div>
                    </div>
                </div>
                
                <div class="mini-stat">
                   
                    <div class="mini-stat-content">
                        <div class="mini-stat-value"><?php echo count($completedPositions); ?></div>
                        <div class="mini-stat-label">Completed Positions</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="info-banner">
            <strong> How Sequential Voting Works:</strong> Open voting for ONE position at a time. Students vote, then you close the position and determine the winner. The winner cannot win lower-priority positions. Continue with the next position.
        </div>
        
        <?php if ($currentPositionId): 
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT * FROM positions WHERE id = ?");
            $stmt->bind_param("i", $currentPositionId);
            $stmt->execute();
            $currentPosition = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Get vote count for current position
            $voteCountQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
            $stmt = $conn->prepare($voteCountQuery);
            $stmt->bind_param("ii", $sessionId, $currentPositionId);
            $stmt->execute();
            $currentVoteCount = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            $conn->close();
        ?>
        
        <div class="current-position-card">
            <h3> VOTING NOW OPEN FOR</h3>
            <p style="font-size: 2em; font-weight: bold; margin: 15px 0;">
                <?php echo htmlspecialchars($currentPosition['position_name']); ?>
            </p>
            <p><?php echo $currentVoteCount; ?> votes cast so far</p>
            
            <form method="POST" style="display: inline; margin-top: 15px;" onsubmit="return confirm('Close voting for this position and determine the winner?');">
                <input type="hidden" name="action" value="close_position">
                <button type="submit" class="btn btn-warning" style="font-size: 1.1em; padding: 12px 30px;">
                     Close This Position & Determine Winner
                </button>
            </form>
        </div>
        
        <?php endif; ?>
        
        <div class="card">
            <h2>Position Control</h2>
            
            <div class="position-list">
                <?php 
                $positions->data_seek(0);
                while ($position = $positions->fetch_assoc()): 
                    $positionId = $position['id'];
                    $isActive = ($currentPositionId == $positionId);
                    $isCompleted = in_array($positionId, $completedPositions);
                    
                    // Get vote count
                    $conn = getDBConnection();
                    $voteQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
                    $stmt = $conn->prepare($voteQuery);
                    $stmt->bind_param("ii", $sessionId, $positionId);
                    $stmt->execute();
                    $voteCount = $stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();
                    
                    // Get nominees for this position
                    $nomineesQuery = "SELECT c.id, c.status, u.first_name, u.middle_name, u.last_name 
                                     FROM candidates c
                                     JOIN users u ON c.user_id = u.id
                                     WHERE c.position_id = ?
                                     ORDER BY u.last_name, u.first_name";
                    $stmt = $conn->prepare($nomineesQuery);
                    $stmt->bind_param("i", $positionId);
                    $stmt->execute();
                    $nominees = $stmt->get_result();
                    $stmt->close();
                    
                    // Check if this position has a tie
                    $hasTie = false;
                    $tiedCandidates = [];
                    if ($voteCount > 0 && !$isCompleted && !$isActive) {
                        $tieCheckQuery = "SELECT c.id, 
                                         TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) AS full_name, 
                                         COUNT(v.id) as vote_count
                                         FROM candidates c
                                         JOIN users u ON c.user_id = u.id
                                         LEFT JOIN votes v ON c.id = v.candidate_id AND v.session_id = ?
                                         WHERE c.position_id = ?
                                         GROUP BY c.id, u.first_name, u.middle_name, u.last_name
                                         ORDER BY vote_count DESC";
                        $stmt = $conn->prepare($tieCheckQuery);
                        $stmt->bind_param("ii", $sessionId, $positionId);
                        $stmt->execute();
                        $voteResults = $stmt->get_result();
                        
                        $topVote = null;
                        $topCount = 0;
                        while ($row = $voteResults->fetch_assoc()) {
                            if ($topVote === null) {
                                $topVote = $row['vote_count'];
                                $topCount = 1;
                                $tiedCandidates[] = $row['full_name'];
                            } elseif ($row['vote_count'] == $topVote) {
                                $topCount++;
                                $tiedCandidates[] = $row['full_name'];
                            } else {
                                break;
                            }
                        }
                        
                        if ($topCount > 1 && $topVote > 0) {
                            $hasTie = true;
                        }
                        $stmt->close();
                    }
                    
                    // Get winner if completed (3NF - compute vote count)
                    $winnerName = null;
                    $winnerVoteCount = 0;
                    if ($isCompleted) {
                        $winnerQuery = "SELECT u.id as user_id, 
                                       TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) AS full_name 
                                       FROM candidates c 
                                       JOIN users u ON c.user_id = u.id 
                                       WHERE c.position_id = ? AND c.status = 'elected'";
                        $stmt = $conn->prepare($winnerQuery);
                        $stmt->bind_param("i", $positionId);
                        $stmt->execute();
                        $winnerResult = $stmt->get_result();
                        if ($winnerResult->num_rows > 0) {
                            $winnerData = $winnerResult->fetch_assoc();
                            $winnerName = $winnerData['full_name'];
                            // Compute vote count dynamically (3NF compliant)
                            $winnerVoteCount = getWinnerVoteCount($sessionId, $positionId, $winnerData['user_id']);
                        }
                        $stmt->close();
                    }
                    $conn->close();
                ?>
                
                <div class="position-item <?php echo $isActive ? 'active' : ($isCompleted ? 'completed' : ($hasTie ? 'tie-warning' : '')); ?>">
                    <div class="position-header-section">
                        <div class="position-info">
                            <div class="position-name">
                                <span class="position-badge badge-priority">Priority #<?php echo $position['position_order']; ?></span>
                                <?php echo htmlspecialchars($position['position_name']); ?>
                                
                                <?php if ($isActive): ?>
                                    <span class="position-badge badge-active"> VOTING NOW</span>
                                <?php elseif ($isCompleted): ?>
                                    <span class="position-badge badge-completed"> Completed</span>
                                <?php elseif ($hasTie): ?>
                                    <span class="position-badge badge-tie"> TIE DETECTED</span>
                                <?php endif; ?>
                            </div>
                            <div class="position-meta">
                                <?php echo $voteCount; ?> votes cast
                                <?php if ($winnerName): ?>
                                    | Winner: <strong><?php echo htmlspecialchars($winnerName); ?></strong> (<?php echo $winnerVoteCount; ?> votes)
                                <?php elseif ($hasTie): ?>
                                    | <strong style="color: #f59e0b;"><?php echo count($tiedCandidates); ?> candidates tied</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($hasTie): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Restart voting for this position?\n\nThis will:\n- Delete all <?php echo $voteCount; ?> votes for this position\n- Reset candidate statuses\n- Allow students to vote again\n\nContinue?');">
                                <input type="hidden" name="action" value="restart_position">
                                <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
                                <button type="submit" class="btn btn-warning">
                                     Restart Position Voting
                                </button>
                            </form>
                        <?php elseif (!$isCompleted && !$isActive && !$currentPositionId && $activeSession['status'] !== 'locked'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="open_position">
                                <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
                                <button type="submit" class="btn btn-success">
                                     Open Voting
                                </button>
                            </form>
                        <?php elseif ($isActive): ?>
                            <span style="color: #10b981; font-weight: 600;"> Currently Active</span>
                        <?php elseif ($isCompleted): ?>
                            <span style="color: #718096;"> Winner Determined</span>
                        <?php else: ?>
                            <button class="btn" disabled>Waiting...</button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="nominees-section">
                        <div class="nominees-header">
                            Nominees (<?php echo $nominees->num_rows; ?>):
                            <?php if ($hasTie): ?>
                                <span style="color: #f59e0b; font-weight: 700; margin-left: 10px;">
                                     Tied: <?php echo implode(', ', array_map('htmlspecialchars', $tiedCandidates)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="nominees-list">
                            <?php if ($nominees->num_rows > 0): ?>
                                <?php 
                                $nominees->data_seek(0);
                                while ($nominee = $nominees->fetch_assoc()): 
                                    $nomineeName = formatStudentName($nominee['first_name'], $nominee['middle_name'], $nominee['last_name']);
                                    $nomineeStatus = $nominee['status'];
                                    
                                    $showStatus = $isCompleted;
                                    $isTied = $hasTie && in_array($nomineeName, $tiedCandidates);
                                ?>
                                    <span class="nominee-tag <?php echo ($showStatus && $nomineeStatus === 'elected') ? 'winner' : (($showStatus && $nomineeStatus === 'ineligible') ? 'ineligible' : ($isTied ? 'tied' : '')); ?>">
                                        <?php if ($showStatus && $nomineeStatus === 'elected'): ?>
                                            
                                        <?php elseif ($showStatus && $nomineeStatus === 'ineligible'): ?>
                                            
                                        <?php elseif ($isTied): ?>
                                            
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($nomineeName); ?>
                                    </span>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <span class="no-nominees">No nominees yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="card">
            <h2>Session Controls</h2>
            
            <div class="control-buttons">
                <form method="POST" style="display: inline;" onsubmit="return confirm(' RESTART VOTING?\n\nThis will:\n- Delete ALL votes (<?php echo $totalVoters; ?> voters affected)\n- Reset all candidate statuses\n- Start voting from scratch\n\nThis action cannot be undone!\n\nAre you absolutely sure?');">
                    <input type="hidden" name="action" value="restart_voting">
                    <button type="submit" class="btn btn-restart" style="font-size: 1.1em; padding: 12px 30px;" <?php echo $currentPositionId ? 'disabled title="Close current position first"' : ''; ?>>
                         Restart Voting Session
                    </button>
                </form>
                
                <a href="view_results.php" class="btn btn-success" style="font-size: 1.1em; padding: 12px 30px;">
                     View Full Results
                </a>
                
                <form method="POST" style="display: inline;" onsubmit="return confirm(' LOCK SESSION?\n\nThis will:\n- End all voting permanently for this session\n- Save all votes and winners to the database\n- Preserve all data for audit logs\n\nYou can create a new session after locking.\n\nAre you sure?');">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-danger" style="font-size: 1.1em; padding: 12px 30px;" <?php echo $currentPositionId ? 'disabled' : ''; ?>>
                         Lock Session
                    </button>
                </form>
            </div>
            
            <?php if ($currentPositionId): ?>
                <p style="margin-top: 15px; color: #e53e3e;">
                     Close the current position before restarting or locking the session.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        <?php if ($currentPositionId): ?>
        // Auto-refresh every 10 seconds when voting is active
        let countdown = 10;
        
        setTimeout(function() {
            location.reload();
        }, 10000);
        
        // Add visual countdown indicator
        const indicator = document.createElement('div');
        indicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); font-size: 0.9em; z-index: 1000; display: flex; align-items: center; gap: 10px;';
        indicator.innerHTML = '<div style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 2s ease-in-out infinite;"></div> Auto-refresh in <span id="countdown" style="font-weight: bold;">10</span>s';
        document.body.appendChild(indicator);
        
        setInterval(function() {
            countdown--;
            const countdownEl = document.getElementById('countdown');
            if (countdownEl && countdown >= 0) {
                countdownEl.textContent = countdown;
            }
        }, 1000);
        
        // Add pulse animation
        const style = document.createElement('style');
        style.textContent = '@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }';
        document.head.appendChild(style);
        <?php else: ?>
        // No active voting, show manual refresh option
        const refreshBtn = document.createElement('div');
        refreshBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-size: 0.9em; z-index: 1000; cursor: pointer;';
        refreshBtn.innerHTML = ' Refresh Page';
        refreshBtn.onclick = function() { location.reload(); };
        document.body.appendChild(refreshBtn);
        <?php endif; ?>
    </script>
</body>
</html>