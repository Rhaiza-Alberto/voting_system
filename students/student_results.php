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
    <title>Election Results - VoteSystem</title>
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

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Enhanced Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
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
        
        .modern-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        /* Session Header Card */
        .session-header-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .session-header-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .session-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .session-subtitle {
            font-size: 1rem;
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.75rem;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        /* Position Cards */
        .position-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .position-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.15), 0 10px 10px -5px rgba(16, 185, 129, 0.1);
        }
        
        .position-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.75rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .position-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .position-meta {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            opacity: 0.9;
        }
        
        .results-content {
            padding: 2rem;
        }
        
        .candidate-result {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .candidate-result:hover {
            border-color: #10b981;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        
        .candidate-result.winner {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-left: 5px solid #10b981;
            border-color: #10b981;
        }
        
        .candidate-info {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .candidate-rank {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            color: #6b7280;
            border: 2px solid #e5e7eb;
        }

        .candidate-result.winner .candidate-rank {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-color: #10b981;
        }
        
        .candidate-name {
            font-weight: 700;
            font-size: 1.125rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .vote-stats {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .vote-count {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
            min-width: 60px;
            text-align: center;
        }

        .vote-count-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .vote-bar-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 250px;
        }

        .vote-bar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .vote-percentage {
            font-weight: 700;
            color: #1f2937;
            font-size: 1rem;
        }
        
        .vote-bar {
            height: 32px;
            background: #e5e7eb;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        
        .vote-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            border-radius: 50px;
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }
        
        .winner-badge {
            background: #10b981;
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        /* Live indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #fee2e2;
            color: #991b1b;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-top: 1rem;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
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
        
        @media (max-width: 768px) {
            .modern-container {
                padding: 0 1rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .session-title {
                font-size: 1.5rem;
            }

            .candidate-result {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .candidate-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .vote-stats {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .vote-bar-container {
                width: 100%;
                min-width: auto;
            }

            .vote-count {
                font-size: 1.5rem;
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
                    <p>Election Results</p>
                </div>
            </div>
            <div class="navbar-actions">
                <a href="student_dashboard.php" class="btn-modern btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Session Header -->
        <div class="session-header-card fade-in">
            <h2 class="session-title"><?php echo htmlspecialchars($session['session_name']); ?></h2>
            <p class="session-subtitle">Live Election Results</p>
            <span class="status-badge badge-<?php echo $session['status']; ?>">
                <?php echo strtoupper($session['status']); ?>
            </span>
            <?php if ($session['status'] === 'active'): ?>
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    <span>Live Updates</span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php 
        $delay = 0;
        while ($position = $positions->fetch_assoc()): 
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
            
            $delay += 0.1;
        ?>
        
        <div class="position-card fade-in" style="animation-delay: <?php echo $delay; ?>s;">
            <div class="position-header">
                <h3><?php echo htmlspecialchars($position['position_name']); ?></h3>
                <p class="position-meta">Total Votes: <?php echo $totalVotes; ?></p>
            </div>
            
            <div class="results-content">
                <?php if ($results->num_rows > 0): 
                    $rank = 1;
                    while ($result = $results->fetch_assoc()): 
                        $percentage = $totalVotes > 0 ? ($result['vote_count'] / $totalVotes) * 100 : 0;
                        $isWinner = ($rank === 1 && $result['vote_count'] > 0);
                ?>
                    <div class="candidate-result <?php echo $isWinner ? 'winner' : ''; ?>">
                        <div class="candidate-info">
                            <div class="candidate-rank"><?php echo $rank; ?></div>
                            <div>
                                <?php $candidateName = formatStudentName($result['first_name'], $result['middle_name'], $result['last_name']); ?>
                                <div class="candidate-name">
                                    <?php echo htmlspecialchars($candidateName); ?>
                                    <?php if ($isWinner): ?>
                                        <span class="winner-badge">Leading</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vote-stats">
                            <div style="text-align: center;">
                                <div class="vote-count"><?php echo $result['vote_count']; ?></div>
                                <div class="vote-count-label">Votes</div>
                            </div>
                            <div class="vote-bar-container">
                                <div class="vote-bar-header">
                                    <span class="vote-percentage"><?php echo round($percentage, 1); ?>%</span>
                                </div>
                                <div class="vote-bar">
                                    <div class="vote-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                        $rank++;
                    endwhile; 
                else: ?>
                    <div class="empty-state">
                        <p class="empty-title">No votes yet</p>
                        <p>Votes will appear here once students start voting.</p>
                    </div>
                <?php endif; ?>
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
        // Auto-refresh every 15 seconds for live results
        setTimeout(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 15000);
        <?php endif; ?>
    </script>
</body>
</html>