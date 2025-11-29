<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();

// Get active session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('pending', 'active', 'paused') ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Get statistics
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$totalCandidates = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch_assoc()['count'];

// Get session progress metrics
$sessionProgress = 0;
$totalVoters = 0;
$voterTurnout = 0;
$completedPositions = 0;
$totalPositionsCount = $positions->num_rows;
$totalVotesCast = 0;

if ($activeSession) {
    $sessionId = $activeSession['id'];
    
    // Get total unique voters
    $votersQuery = "SELECT COUNT(DISTINCT voter_id) as count FROM votes WHERE session_id = ?";
    $stmt = $conn->prepare($votersQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $totalVoters = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Get total votes cast
    $votesQuery = "SELECT COUNT(*) as count FROM votes WHERE session_id = ?";
    $stmt = $conn->prepare($votesQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $totalVotesCast = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // Calculate voter turnout percentage
    if ($totalStudents > 0) {
        $voterTurnout = ($totalVoters / $totalStudents) * 100;
    }
    
    // Get completed positions
    $completedQuery = "SELECT COUNT(DISTINCT position_id) as count FROM candidates WHERE status IN ('elected', 'lost')";
    $completedPositions = $conn->query($completedQuery)->fetch_assoc()['count'];
    
    // Calculate session progress
    if ($totalPositionsCount > 0) {
        $sessionProgress = ($completedPositions / $totalPositionsCount) * 100;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 1.1em;
        }
        
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
            margin-top: 15px;
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
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
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
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #10b981;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-success {
            background: #34d399;
            color: white;
        }
        
        .btn-success:hover {
            background: #10b981;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 211, 153, 0.4);
        }
        
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        
        .btn-warning:hover {
            background: #dd6b20;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.4);
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4);
        }
        
        .session-status {
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
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
        
        .status-locked {
            background: #fed7d7;
            color: #742a2a;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .button-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
    <h1>Admin Dashboard</h1>
    <div class="user-info">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <?php include 'notification_widget.php'; ?>  <!-- Add this line -->
        <a href="logout.php">Logout</a>
    </div>
</div>

    <div class="container">
        <!-- Enhanced Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $totalStudents; ?></div>
                <div class="label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="number"><?php echo $totalCandidates; ?></div>
                <div class="label">Total Candidates</div>
            </div>
            
            <div class="stat-card">
                <div class="number"><?php echo $totalPositionsCount; ?></div>
                <div class="label">Positions</div>
            </div>
            
            <div class="stat-card">
                <div class="number"><?php echo $totalVotesCast; ?></div>
                <div class="label">Total Votes Cast</div>
            </div>
        </div>
        
        
        <?php if ($activeSession): ?>
        <div class="card">
            <h2>Current Voting Session</h2>
            <div class="session-status status-<?php echo $activeSession['status']; ?>">
                Status: <?php echo strtoupper($activeSession['status']); ?>
            </div>
            <p><strong>Session:</strong> <?php echo htmlspecialchars($activeSession['session_name']); ?></p>
            <div class="button-grid" style="margin-top: 20px;">
                <a href="manage_session.php" class="btn btn-primary">Manage Session</a>
                <a href="view_results.php" class="btn btn-success">View Results</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Quick Actions</h2>
            <div class="button-grid">
                <a href="create_session.php" class="btn btn-primary">Create New Session</a>
                <a href="manage_candidates.php" class="btn btn-success">Manage Candidates</a>
                <a href="manage_positions.php" class="btn btn-warning">Manage Positions</a>
                <a href="view_results.php" class="btn btn-primary">View All Results</a>
                <a href="manage_students.php" class="btn btn-success">Manage Students</a>
                <a href="password_reset.php" class="btn btn-warning">Reset Passwords</a>
                <a href="audit_logs.php" class="btn btn-primary">Audit Logs</a>
            </div>
        </div>
    </div>
    
    <!-- Auto-refresh for active sessions -->
    <script>
        <?php if ($activeSession && $activeSession['status'] === 'active'): ?>
        // Auto-refresh every 15 seconds when there's an active session
        setTimeout(function() {
            location.reload();
        }, 15000);
        
        // Add visual indicator that page will refresh
        let countdown = 15;
        const indicator = document.createElement('div');
        indicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: rgba(16, 185, 129, 0.95); color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); font-size: 0.9em; z-index: 1000;';
        indicator.innerHTML = ' Auto-refresh in <span id="countdown">15</span>s';
        document.body.appendChild(indicator);
        
        setInterval(function() {
            countdown--;
            const countdownEl = document.getElementById('countdown');
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>