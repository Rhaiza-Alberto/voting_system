<?php
require_once '../config.php';
requireLogin();

$conn = getDBConnection();

// Get latest or active session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('active', 'locked', 'completed') ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$session = $sessionResult->fetch_assoc();

if (!$session) {
    header('Location: student_dashboard.php');
    exit();
}

$sessionId = $session['id'];
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
        
        .session-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .session-header h2 {
            color: #10b981;
            margin-bottom: 10px;
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
        }
        
        .position-header h3 {
            font-size: 1.5em;
        }
        
        .results-content {
            padding: 20px;
        }
        
        .candidate-result {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
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
            margin-left: 10px;
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
        <a href="student_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="session-header">
            <h2><?php echo htmlspecialchars($session['session_name']); ?></h2>
            <p>Live Results</p>
        </div>
        
        <?php while ($position = $positions->fetch_assoc()): 
            $positionId = $position['id'];
            
            $resultsQuery = "SELECT c.id, u.first_name, u.middle_name, u.last_name, COUNT(v.id) as vote_count
               FROM candidates c
               JOIN users u ON c.user_id = u.id
               LEFT JOIN votes v ON c.id = v.candidate_id AND v.session_id = ?
               WHERE c.position_id = ?
               GROUP BY c.id, u.first_name, u.middle_name, u.last_name
               ORDER BY vote_count DESC, u.last_name, u.first_name";
            $stmt = $conn->prepare($resultsQuery);
            $stmt->bind_param("ii", $sessionId, $positionId);
            $stmt->execute();
            $results = $stmt->get_result();
            
            $totalVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
            $totalStmt = $conn->prepare($totalVotesQuery);
            $totalStmt->bind_param("ii", $sessionId, $positionId);
            $totalStmt->execute();
            $totalVotes = $totalStmt->get_result()->fetch_assoc()['total'];
        ?>
        
        <div class="position-card">
            <div class="position-header">
                <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
            </div>
            
            <div class="results-content">
                <?php if ($results->num_rows > 0): 
                    $isFirst = true;
                    while ($result = $results->fetch_assoc()): 
                        $percentage = $totalVotes > 0 ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                ?>
                    <div class="candidate-result <?php echo $isFirst && $result['vote_count'] > 0 ? 'winner' : ''; ?>">
                        <div class="candidate-info">
                            <?php $candidateName = formatStudentName($result['first_name'], $result['middle_name'], $result['last_name']); ?>
<div class="candidate-name">
    <?php echo htmlspecialchars($candidateName); ?>
                                <?php if ($isFirst && $result['vote_count'] > 0): ?>
                                    <span class="winner-badge"> Leading</span>
                                <?php endif; ?>
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
                        $isFirst = false;
                    endwhile; 
                endif; ?>
            </div>
        </div>
        
        <?php 
            $stmt->close();
            $totalStmt->close();
        endwhile; 
        
        $conn->close();
        ?>
    </div>
    
    <script>
        <?php if ($session['status'] === 'active'): ?>
        setTimeout(function() {
            location.reload();
        }, 15000);
        <?php endif; ?>
    </script>
</body>
</html>