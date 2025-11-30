<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get session ID from URL parameter or use latest
$selectedSessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

// Get all sessions for dropdown
$allSessionsQuery = "SELECT id, session_name, status, created_at FROM voting_sessions ORDER BY id DESC";
$allSessions = $conn->query($allSessionsQuery);

// Get the selected session or latest session
if ($selectedSessionId) {
    $sessionQuery = "SELECT * FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($sessionQuery);
    $stmt->bind_param("i", $selectedSessionId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $sessionQuery = "SELECT * FROM voting_sessions ORDER BY id DESC LIMIT 1";
    $sessionResult = $conn->query($sessionQuery);
    $session = $sessionResult->fetch_assoc();
}

$noSession = false;
if (!$session) {
    $noSession = true;
}

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Election Results</title>
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
        
        .session-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .session-selector label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .session-selector select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            background: white;
            cursor: pointer;
        }
        
        .session-selector select:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .session-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .session-header h2 {
            color: #10b981;
            margin-bottom: 10px;
        }
        
        .session-meta {
            color: #718096;
            font-size: 0.95em;
            margin-top: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-locked {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-pending {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-paused {
            background: #feebc8;
            color: #744210;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #34d399;
            color: white;
        }
        
        .btn-success:hover {
            background: #10b981;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .empty-state {
            background: white;
            padding: 60px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            color: #10b981;
            margin-bottom: 15px;
            font-size: 2em;
        }
        
        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
            font-size: 1.1em;
        }
        
        .position-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .position-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .position-header h3 {
            font-size: 1.5em;
        }
        
        .priority-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9em;
        }
        
        .results-content {
            padding: 20px;
        }
        
        .candidate-result {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.3s;
        }
        
        .candidate-result:hover {
            background: #f7fafc;
        }
        
        .candidate-result:last-child {
            border-bottom: none;
        }
        
        .candidate-result.winner {
            background: #c6f6d5;
            border-left: 5px solid #10b981;
        }
        
        .candidate-info {
            flex: 1;
        }
        
        .candidate-name {
            font-weight: 600;
            font-size: 1.1em;
            color: #2d3748;
        }
        
        .candidate-id {
            color: #718096;
            font-size: 0.9em;
        }
        
        .vote-stats {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .vote-count {
            font-size: 2em;
            font-weight: bold;
            color: #10b981;
        }
        
        .vote-bar {
            width: 200px;
            height: 30px;
            background: #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .vote-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .winner-badge {
            background: #10b981;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .no-votes {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        @media print {
            .navbar, .export-buttons, .session-selector, .no-print {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .vote-bar {
                width: 100px;
            }
            
            .vote-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Election Results</h1>
        <a href="../admin/admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($noSession): ?>
            <div class="empty-state">
               
                <h2>No Voting Sessions Yet</h2>
                <p>There are no voting sessions to display results for.</p>
                <p style="color: #a0aec0; font-size: 0.95em;">Create a voting session to get started!</p>
                <a href="create_session.php" class="btn btn-primary" style="margin-top: 20px; font-size: 1.1em; padding: 12px 30px;">
                     Create New Session
                </a>
            </div>
        <?php else: 
            $sessionId = $session['id'];
        ?>
        
        <!-- Session Selector -->
        <div class="session-selector">
            <label for="session-select"> Select Election Session to View:</label>
            <select id="session-select" onchange="window.location.href='view_results.php?session_id=' + this.value">
                <?php 
                $allSessions->data_seek(0);
                while ($sess = $allSessions->fetch_assoc()): 
                    $selected = ($sess['id'] == $sessionId) ? 'selected' : '';
                ?>
                    <option value="<?php echo $sess['id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($sess['session_name']); ?> 
                        (<?php echo strtoupper($sess['status']); ?>) - 
                        <?php echo date('M d, Y', strtotime($sess['created_at'])); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="session-header">
            <h2>
                <?php echo htmlspecialchars($session['session_name']); ?>
                <span class="status-badge badge-<?php echo $session['status']; ?>">
                    <?php echo strtoupper($session['status']); ?>
                </span>
            </h2>
            <div class="session-meta">
                <strong>Created:</strong> <?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?>
            </div>
        </div>
        
        <!-- Export Buttons -->
        <div class="export-buttons">
            <a href="../admin/export_results_excel.php?session_id=<?php echo $sessionId; ?>" class="btn btn-success">
                Export to Excel
            </a>
            <!--
            <a href="export_results_pdf.php?session_id=<?php echo $sessionId; ?>" class="btn btn-warning" target="_blank">
                 Export to PDF
            </a>
                -->
            <button onclick="window.print()" class="btn btn-primary">
                 Print Results
            </button>
        </div>
        
        <?php 
        // Track elected users for higher positions
        $electedUsers = [];
        
        if ($positions->num_rows === 0): ?>
            <div class="empty-state">
                <p> No positions created yet.</p>
            </div>
        <?php else:
            while ($position = $positions->fetch_assoc()): 
                $positionId = $position['id'];
                
                // First, check if there's a stored winner for this position in this session (3NF - no vote_count stored)
                $storedWinnerQuery = "SELECT w.user_id, 
                                      TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) AS full_name,
                                      u.student_id
                                      FROM winners w
                                      JOIN users u ON w.user_id = u.id
                                      WHERE w.session_id = ? AND w.position_id = ?";
                $winnerStmt = $conn->prepare($storedWinnerQuery);
                $winnerStmt->bind_param("ii", $sessionId, $positionId);
                $winnerStmt->execute();
                $storedWinner = $winnerStmt->get_result()->fetch_assoc();
                $winnerStmt->close();
                
                // If winner exists, compute their vote count dynamically (3NF compliant)
                if ($storedWinner) {
                    $storedWinner['vote_count'] = getWinnerVoteCount($sessionId, $positionId, $storedWinner['user_id'], $conn);
                }
                
                // Get all candidates who received votes for this position
                // Use snapshot data when candidate has been deleted (candidate_id is NULL or JOIN fails)
                $resultsQuery = "SELECT v.candidate_id, c.user_id,
                                COALESCE(
                                    NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)), ''),
                                    MAX(v.snapshot_candidate_name)
                                ) AS full_name,
                                COALESCE(u.student_id, MAX(v.snapshot_candidate_student_id)) AS student_id,
                                COUNT(v.id) as vote_count
                                FROM votes v
                                LEFT JOIN candidates c ON v.candidate_id = c.id
                                LEFT JOIN users u ON c.user_id = u.id
                                WHERE v.session_id = ? AND v.position_id = ?
                                GROUP BY v.candidate_id, c.user_id, u.first_name, u.middle_name, u.last_name, u.student_id
                                ORDER BY vote_count DESC, full_name";
                
                $stmt = $conn->prepare($resultsQuery);
                $stmt->bind_param("ii", $sessionId, $positionId);
                $stmt->execute();
                $results = $stmt->get_result();
                
                // Get total votes for percentage calculation
                $totalVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
                $totalStmt = $conn->prepare($totalVotesQuery);
                $totalStmt->bind_param("ii", $sessionId, $positionId);
                $totalStmt->execute();
                $totalVotes = $totalStmt->get_result()->fetch_assoc()['total'];
                
                // Determine the winner
                $winner = null;
                $tempResults = [];
                
                if ($storedWinner) {
                    // Use stored winner if available
                    $winner = $storedWinner;
                    $electedUsers[] = $storedWinner['user_id'];
                }
                
                // Collect all results
                while ($row = $results->fetch_assoc()) {
                    // If user data is missing (candidate was deleted), try to get from stored winner
                    if (!$row['full_name'] && $storedWinner && $row['candidate_id']) {
                        $row['full_name'] = $storedWinner['full_name'];
                        $row['student_id'] = $storedWinner['student_id'];
                        $row['user_id'] = $storedWinner['user_id'];
                    }
                    
                    $tempResults[] = $row;
                    
                    // Find winner from vote counts if not already set
                    if (!$winner && $row['full_name'] && $row['user_id'] && !in_array($row['user_id'], $electedUsers) && $row['vote_count'] > 0) {
                        $winner = $row;
                        if ($row['user_id']) {
                            $electedUsers[] = $row['user_id'];
                        }
                    }
                }
            ?>
            
            <div class="position-card">
                <div class="position-header">
                    <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
                    <span class="priority-badge">Priority #<?php echo $position['position_order']; ?></span>
                </div>
                
                <div class="results-content">
                    <?php if (count($tempResults) > 0 || $storedWinner): 
                        // If we have stored winner but no results, create a result entry
                        if (count($tempResults) == 0 && $storedWinner) {
                            $tempResults[] = $storedWinner;
                        }
                        
                        foreach ($tempResults as $result): 
                            $percentage = $totalVotes > 0 ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                            $isWinner = ($winner && isset($result['user_id']) && isset($winner['user_id']) && $winner['user_id'] === $result['user_id']);
                    ?>
                        <div class="candidate-result <?php echo $isWinner ? 'winner' : ''; ?>">
                            <div class="candidate-info">
                                <div class="candidate-name">
                                    <?php 
                                    if ($result['full_name']) {
                                        echo htmlspecialchars($result['full_name']); 
                                    } else {
                                        echo '<span style="color: #a0aec0;">Candidate (data removed)</span>';
                                    }
                                    ?>
                                    <?php if ($isWinner): ?>
                                        <span class="winner-badge"> WINNER</span>
                                    <?php endif; ?>
                                </div>
                                <div class="candidate-id">
                                    <?php 
                                    if (isset($result['student_id']) && $result['student_id']) {
                                        echo htmlspecialchars($result['student_id']); 
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="vote-stats">
                                <div class="vote-count"><?php echo $result['vote_count']; ?></div>
                                <div class="vote-bar">
                                    <div class="vote-bar-fill" style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <div class="no-votes">
                            No votes cast for this position.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
                $stmt->close();
                $totalStmt->close();
            endwhile;
        endif; 
        ?>
        
        <?php endif; ?>
    </div>
    
    <?php $conn->close(); ?>
    
    <script>
        // Auto-refresh only if viewing active session
        <?php if (!$noSession && $session['status'] === 'active'): ?>
        setTimeout(function() {
            location.reload();
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>