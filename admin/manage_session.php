<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Handle session control actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Get active session
    $sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('pending', 'active', 'paused') ORDER BY id DESC LIMIT 1";
    $sessionResult = $conn->query($sessionQuery);
    $activeSession = $sessionResult->fetch_assoc();
    
    if ($activeSession) {
        $sessionId = $activeSession['id'];
        
        switch ($action) {
            case 'restart_voting':
                // Delete all votes
                $stmt = $conn->prepare("DELETE FROM votes WHERE session_id = ?");
                $stmt->bind_param("i", $sessionId);
                $stmt->execute();
                $stmt->close();
                
                // Delete winners
                $stmt = $conn->prepare("DELETE FROM winners WHERE session_id = ?");
                $stmt->bind_param("i", $sessionId);
                $stmt->execute();
                $stmt->close();
                
                // Reset candidates
                $conn->query("UPDATE candidates SET status = 'nominated'");
                
                // Reset session
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'pending', current_position_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $sessionId);
                if ($stmt->execute()) {
                    $message = 'Voting session restarted successfully! All votes have been cleared.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to restart session.';
                    $messageType = 'error';
                }
                $stmt->close();
                break;
            
            case 'open_position':
                $positionId = $_POST['position_id'];
                
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'active', current_position_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $positionId, $sessionId);
                if ($stmt->execute()) {
                    $message = 'Voting opened for this position!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to open position.';
                    $messageType = 'error';
                }
                $stmt->close();
                break;
                
            case 'close_position':
                $currentPositionId = $activeSession['current_position_id'];
                
                // Get max votes
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
                
                // Get top candidates
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
                    $tiedNames = [];
                    while ($tied = $topCandidates->fetch_assoc()) {
                        $tiedNames[] = $tied['full_name'];
                    }
                    
                    $stmt = $conn->prepare("UPDATE voting_sessions SET current_position_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $sessionId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $message = 'TIE DETECTED! ' . $topCandidates->num_rows . ' candidates tied with ' . $maxVotes . ' votes each: ' . 
                               implode(', ', $tiedNames) . '. Use "Restart Position Voting" to conduct a runoff vote.';
                    $messageType = 'warning';
                } elseif ($maxVotes > 0) {
                    $topCandidates->data_seek(0);
                    $winner = $topCandidates->fetch_assoc();
                    
                    // Save winner
                    $saveWinnerStmt = $conn->prepare("INSERT INTO winners (session_id, position_id, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id = ?");
                    $saveWinnerStmt->bind_param("iiii", $sessionId, $currentPositionId, $winner['user_id'], $winner['user_id']);
                    $saveWinnerStmt->execute();
                    $saveWinnerStmt->close();
                    
                    $computedVoteCount = getWinnerVoteCount($sessionId, $currentPositionId, $winner['user_id'], $conn);
                    
                    // Mark winner
                    $stmt = $conn->prepare("UPDATE candidates SET status = 'elected' WHERE id = ?");
                    $stmt->bind_param("i", $winner['candidate_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Mark losers
                    $stmt = $conn->prepare("UPDATE candidates SET status = 'lost' WHERE position_id = ? AND id != ?");
                    $stmt->bind_param("ii", $currentPositionId, $winner['candidate_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Get position order
                    $posQuery = "SELECT position_order FROM positions WHERE id = ?";
                    $stmt = $conn->prepare($posQuery);
                    $stmt->bind_param("i", $currentPositionId);
                    $stmt->execute();
                    $currentOrder = $stmt->get_result()->fetch_assoc()['position_order'];
                    $stmt->close();
                    
                    // Mark ineligible
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
                    
                    $message = 'Position closed! Winner: ' . htmlspecialchars($winner['full_name']) . ' with ' . $computedVoteCount . ' votes.';
                    $messageType = 'success';
                } else {
                    $stmt = $conn->prepare("UPDATE voting_sessions SET current_position_id = NULL WHERE id = ?");
                    $stmt->bind_param("i", $sessionId);
                    $stmt->execute();
                    $stmt->close();
                    
                    $message = 'Position closed but no votes were cast.';
                    $messageType = 'warning';
                }
                break;
                
            case 'restart_position':
                $positionId = $_POST['position_id'];
                
                // Delete votes
                $stmt = $conn->prepare("DELETE FROM votes WHERE session_id = ? AND position_id = ?");
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Delete winner
                $stmt = $conn->prepare("DELETE FROM winners WHERE session_id = ? AND position_id = ?");
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Reset candidates
                $stmt = $conn->prepare("UPDATE candidates SET status = 'nominated' WHERE position_id = ?");
                $stmt->bind_param("i", $positionId);
                $stmt->execute();
                $stmt->close();
                
                // Get position name
                $posStmt = $conn->prepare("SELECT position_name FROM positions WHERE id = ?");
                $posStmt->bind_param("i", $positionId);
                $posStmt->execute();
                $posName = $posStmt->get_result()->fetch_assoc()['position_name'];
                $posStmt->close();
                
                $message = 'Voting restarted for ' . htmlspecialchars($posName) . '! All votes for this position cleared.';
                $messageType = 'success';
                break;
                
            case 'lock':
                $stmt = $conn->prepare("UPDATE voting_sessions SET status = 'locked', current_position_id = NULL, locked_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $sessionId);
                $lockSuccess = $stmt->execute();
                $stmt->close();
                if ($lockSuccess) {
                    $message = 'Session locked successfully! You can now create a new session.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to lock session.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get active session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('pending', 'active', 'paused') ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

// Get group info
$groupInfo = null;
if ($activeSession && $activeSession['group_id']) {
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

if (!$activeSession) {
    header('Location: admin_dashboard.php');
    exit();
}

$sessionId = $activeSession['id'];
$currentPositionId = $activeSession['current_position_id'];

// Get positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Get completed positions
$completedQuery = "SELECT DISTINCT position_id FROM candidates WHERE status IN ('elected', 'lost')";
$completedResult = $conn->query($completedQuery);
$completedPositions = [];
while ($row = $completedResult->fetch_assoc()) {
    $completedPositions[] = $row['position_id'];
}

// Get stats
$totalVotersQuery = "SELECT COUNT(DISTINCT voter_id) as count FROM votes WHERE session_id = ? AND deleted_at IS NULL";
$stmt = $conn->prepare($totalVotersQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVoters = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];

$totalVotesQuery = "SELECT COUNT(*) as count FROM votes WHERE session_id = ? AND deleted_at IS NULL";
$stmt = $conn->prepare($totalVotesQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVotesCast = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Calculate progress
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
    <title>Manage Session - VoteSystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        /* Enhanced Navbar */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-text h1 {
            font-size: 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-text p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .modern-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #a7f3d0;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #bfdbfe;
        }

        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .card-body {
            padding: 2rem;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background: #e5e7eb;
            color: #6b7280;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-paused {
            background: #fef3c7;
            color: #92400e;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-progress {
            margin-top: 1rem;
        }

        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        /* Active Position Card */
        .active-position-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.5);
        }

        .active-position-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .active-position-card .position-name {
            font-size: 2rem;
            font-weight: 700;
            margin: 1rem 0;
        }

        /* Position Items */
        .position-item {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .position-item:hover {
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }

        .position-item.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
        }

        .position-item.completed {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }

        .position-item.tie-warning {
            border-color: #f59e0b;
            border-width: 3px;
            background: #fffbeb;
        }

        .position-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
        }

        .position-info {
            flex: 1;
        }

        .position-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .position-meta {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .position-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }

        .badge-priority {
            background: #e5e7eb;
            color: #6b7280;
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

        /* Nominees Section */
        .nominees-section {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .nominees-header {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nominees-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .nominee-tag {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            color: #1f2937;
            border: 1px solid #e5e7eb;
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

        /* Enhanced Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }

        .btn-secondary {
            background: white;
            color: #1f2937;
            border: 2px solid #d1fae5;
        }

        .btn-secondary:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        .btn-modern:disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .control-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        /* Live Indicator */
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 0.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-container {
                padding: 0 1rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }

            .position-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .control-buttons {
                flex-direction: column;
            }

            .btn-modern {
                width: 100%;
            }

            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-text">
                    <h1>VoteSystem</h1>
                    <p>Manage Session</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Session Info Card -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title"><?php echo htmlspecialchars($activeSession['session_name']); ?></h2>
            </div>
            <div class="card-body">
                <span class="status-badge status-<?php echo $activeSession['status']; ?>">
                    Status: <?php echo strtoupper($activeSession['status']); ?>
                </span>
                
                <?php if ($groupInfo): ?>
                    <p style="margin-top: 1rem; color: #6b7280;">
                        <strong style="color: #1f2937;">Student Group:</strong> <?php echo htmlspecialchars($groupInfo['group_name']); ?>
                        (<?php echo $groupInfo['member_count']; ?> students)
                    </p>
                <?php else: ?>
                    <p style="margin-top: 1rem; color: #6b7280;">
                        <strong style="color: #1f2937;">Eligible Voters:</strong> All Students
                    </p>
                <?php endif; ?>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalVoters; ?></div>
                        <div class="stat-label">Total Voters</div>
                        <?php if ($totalStudents > 0): ?>
                        <div class="stat-progress">
                            <div class="progress-bar">
                                <?php 
                                $voterPercentage = ($totalVoters / $totalStudents) * 100;
                                ?>
                                <div class="progress-fill" style="width: <?php echo min($voterPercentage, 100); ?>%;"></div>
                            </div>
                            <div class="progress-text">
                                <?php echo number_format($voterPercentage, 1); ?>% turnout
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalStudents; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($completedPositions); ?> / <?php echo $positions->num_rows; ?></div>
                        <div class="stat-label">Positions Completed</div>
                        <?php if ($positions->num_rows > 0): ?>
                        <div class="stat-progress">
                            <div class="progress-bar">
                                <?php 
                                $progressPercentage = (count($completedPositions) / $positions->num_rows) * 100;
                                ?>
                                <div class="progress-fill" style="width: <?php echo $progressPercentage; ?>%;"></div>
                            </div>
                            <div class="progress-text">
                                <?php echo number_format($progressPercentage, 1); ?>% complete
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Info Banner -->
        <div class="alert alert-info fade-in" style="animation-delay: 0.1s;">
            <strong>How Sequential Voting Works:</strong> Open voting for ONE position at a time. Students vote, then you close the position and determine the winner. The winner cannot win lower-priority positions. Continue with the next position.
        </div>
        
        <?php if ($currentPositionId): 
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT * FROM positions WHERE id = ?");
            $stmt->bind_param("i", $currentPositionId);
            $stmt->execute();
            $currentPosition = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $voteCountQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
            $stmt = $conn->prepare($voteCountQuery);
            $stmt->bind_param("ii", $sessionId, $currentPositionId);
            $stmt->execute();
            $currentVoteCount = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();
            $conn->close();
        ?>
        
        <!-- Active Position Card -->
        <div class="active-position-card fade-in" style="animation-delay: 0.2s;">
            <h3><span class="live-indicator"></span>VOTING NOW OPEN FOR</h3>
            <div class="position-name">
                <?php echo htmlspecialchars($currentPosition['position_name']); ?>
            </div>
            <p style="opacity: 0.9;"><?php echo $currentVoteCount; ?> votes cast so far</p>
            
            <form method="POST" style="display: inline; margin-top: 1rem;" onsubmit="return confirm('Close voting for this position and determine the winner?');">
                <input type="hidden" name="action" value="close_position">
                <button type="submit" class="btn-modern btn-warning" style="font-size: 1.1em; padding: 1rem 2rem;">
                    Close This Position &amp; Determine Winner
                </button>
            </form>
        </div>
        
        <?php endif; ?>
        
        <!-- Position Control -->
        <div class="modern-card fade-in" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h2 class="card-title">Position Control</h2>
            </div>
            <div class="card-body">
                <div class="position-list">
                    <?php 
                    $positions->data_seek(0);
                    while ($position = $positions->fetch_assoc()): 
                        $positionId = $position['id'];
                        $isActive = ($currentPositionId == $positionId);
                        $isCompleted = in_array($positionId, $completedPositions);
                        
                        $conn = getDBConnection();
                        $voteQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
                        $stmt = $conn->prepare($voteQuery);
                        $stmt->bind_param("ii", $sessionId, $positionId);
                        $stmt->execute();
                        $voteCount = $stmt->get_result()->fetch_assoc()['total'];
                        $stmt->close();
                        
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
                                $winnerVoteCount = getWinnerVoteCount($sessionId, $positionId, $winnerData['user_id']);
                            }
                            $stmt->close();
                        }
                        $conn->close();
                    ?>
                    
                    <div class="position-item <?php echo $isActive ? 'active' : ($isCompleted ? 'completed' : ($hasTie ? 'tie-warning' : '')); ?>">
                        <div class="position-header">
                            <div class="position-info">
                                <div class="position-name">
                                    <span class="position-badge badge-priority">Priority #<?php echo $position['position_order']; ?></span>
                                    <?php echo htmlspecialchars($position['position_name']); ?>
                                    
                                    <?php if ($isActive): ?>
                                        <span class="position-badge badge-active">VOTING NOW</span>
                                    <?php elseif ($isCompleted): ?>
                                        <span class="position-badge badge-completed">Completed</span>
                                    <?php elseif ($hasTie): ?>
                                        <span class="position-badge badge-tie">TIE DETECTED</span>
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
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Restart voting for this position?\n\nThis will delete all <?php echo $voteCount; ?> votes and reset candidate statuses.\n\nContinue?');">
                                    <input type="hidden" name="action" value="restart_position">
                                    <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
                                    <button type="submit" class="btn-modern btn-warning">
                                        Restart Position Voting
                                    </button>
                                </form>
                            <?php elseif (!$isCompleted && !$isActive && !$currentPositionId && $activeSession['status'] !== 'locked'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="open_position">
                                    <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
                                    <button type="submit" class="btn-modern btn-primary">
                                        Open Voting
                                    </button>
                                </form>
                            <?php elseif ($isActive): ?>
                                <span style="color: #10b981; font-weight: 600;">Currently Active</span>
                            <?php elseif ($isCompleted): ?>
                                <span style="color: #6b7280;">Winner Determined</span>
                            <?php else: ?>
                                <button class="btn-modern" disabled>Waiting...</button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="nominees-section">
                            <div class="nominees-header">
                                Nominees (<?php echo $nominees->num_rows; ?>)
                                <?php if ($hasTie): ?>
                                    <span style="color: #f59e0b; font-weight: 700; margin-left: 0.5rem;">
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
                                            <?php echo htmlspecialchars($nomineeName); ?>
                                        </span>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.875rem; font-style: italic;">No nominees yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        
        <!-- Session Controls -->
        <div class="modern-card fade-in" style="animation-delay: 0.4s;">
            <div class="card-header">
                <h2 class="card-title">Session Controls</h2>
            </div>
            <div class="card-body">
                <div class="control-buttons">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('RESTART VOTING?\n\nThis will delete ALL votes (<?php echo $totalVoters; ?> voters affected) and reset all candidate statuses.\n\nThis action cannot be undone!\n\nAre you sure?');">
                        <input type="hidden" name="action" value="restart_voting">
                        <button type="submit" class="btn-modern btn-warning" <?php echo $currentPositionId ? 'disabled title="Close current position first"' : ''; ?>>
                            Restart Voting Session
                        </button>
                    </form>
                    
                    <a href="../views/view_results.php" class="btn-modern btn-primary">
                        View Full Results
                    </a>
                    
                    <form method="POST" style="display: inline;" onsubmit="return confirm('LOCK SESSION?\n\nThis will end all voting permanently and save all results.\n\nYou can create a new session after locking.\n\nAre you sure?');">
                        <input type="hidden" name="action" value="lock">
                        <button type="submit" class="btn-modern btn-danger" <?php echo $currentPositionId ? 'disabled' : ''; ?>>
                            Lock Session
                        </button>
                    </form>
                </div>
                
                <?php if ($currentPositionId): ?>
                    <p style="margin-top: 1rem; color: #ef4444; font-weight: 500;">
                        Close the current position before restarting or locking the session.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        <?php if ($currentPositionId): ?>
        // Auto-refresh every 10 seconds
        let countdown = 10;
        
        setTimeout(function() {
            location.reload();
        }, 10000);
        
        const indicator = document.createElement('div');
        indicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); font-size: 0.875rem; z-index: 1000; display: flex; align-items: center; gap: 10px;';
        indicator.innerHTML = '<div style="width: 8px; height: 8px; background: white; border-radius: 50%; animation: pulse 2s ease-in-out infinite;"></div> Auto-refresh in <span id="countdown" style="font-weight: 700;">10</span>s';
        document.body.appendChild(indicator);
        
        setInterval(function() {
            countdown--;
            const countdownEl = document.getElementById('countdown');
            if (countdownEl && countdown >= 0) {
                countdownEl.textContent = countdown;
            }
        }, 1000);
        <?php else: ?>
        const refreshBtn = document.createElement('div');
        refreshBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 20px; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4); font-size: 0.875rem; z-index: 1000; cursor: pointer; font-weight: 600;';
        refreshBtn.innerHTML = 'Refresh Page';
        refreshBtn.onclick = function() { location.reload(); };
        document.body.appendChild(refreshBtn);
        <?php endif; ?>
    </script>
</body>
</html>