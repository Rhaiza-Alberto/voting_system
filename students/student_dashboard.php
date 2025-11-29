<?php
require_once '../config.php';  // Changed from 'config.php' to '../config.php'
requireLogin();

$conn = getDBConnection();
$userId = $_SESSION['user_id'];

// Get active session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

// Check if user is a candidate
$candidateQuery = "SELECT c.*, p.position_name FROM candidates c 
                   JOIN positions p ON c.position_id = p.id 
                   WHERE c.user_id = ?";
$stmt = $conn->prepare($candidateQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$candidacies = $stmt->get_result();

// Get voting status if there's an active session
$votedPositions = [];
if ($activeSession) {
    $voteQuery = "SELECT DISTINCT position_id FROM votes WHERE session_id = ? AND voter_id = ?";
    $stmt = $conn->prepare($voteQuery);
    $stmt->bind_param("ii", $activeSession['id'], $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $votedPositions[] = $row['position_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-card h2 {
            color: #10b981;
            margin-bottom: 10px;
            font-size: 2em;
        }
        
        .welcome-card .student-id {
            color: #666;
            font-size: 1.1em;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h3 {
            color: #10b981;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 1.5em;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .badge-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-inactive {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-voted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-nominated {
            background: #feebc8;
            color: #744210;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
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
        
        .btn-disabled {
            background: #cbd5e0;
            color: #718096;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .info-text {
            color: #666;
            font-size: 1em;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .candidacy-list {
            list-style: none;
        }
        
        .candidacy-item {
            padding: 15px;
            background: #f7fafc;
            border-left: 4px solid #10b981;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .candidacy-item strong {
            color: #10b981;
        }
        
        .icon-large {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
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
            
            .welcome-card h2 {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>🎓 Student Dashboard</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p class="student-id">Student ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
        </div>
        
        <div class="card">
            <h3>🗳️ Voting Session</h3>
            
            <?php if ($activeSession): ?>
                <?php
                // Check eligibility for group-specific sessions
                $isEligible = true;
                $groupName = null;
                
                if ($activeSession['group_id']) {
                    $eligibilityQuery = "SELECT sg.group_name 
                                        FROM student_group_members sgm
                                        JOIN student_groups sg ON sgm.group_id = sg.id
                                        WHERE sgm.group_id = ? AND sgm.user_id = ?";
                    $stmt = $conn->prepare($eligibilityQuery);
                    $stmt->bind_param("ii", $activeSession['group_id'], $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $groupName = $result->fetch_assoc()['group_name'];
                    } else {
                        $isEligible = false;
                    }
                    $stmt->close();
                }
                ?>
                
                <span class="status-badge badge-active">ACTIVE SESSION</span>
                <p class="info-text">
                    <strong>Session:</strong> <?php echo htmlspecialchars($activeSession['session_name']); ?>
                </p>
                
                <?php if ($groupName): ?>
                    <p class="info-text" style="margin-top: 0.5rem;">
                        <strong>For Group:</strong> <?php echo htmlspecialchars($groupName); ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!$isEligible): ?>
                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem;">
                        <p style="color: #92400e; font-weight: 600;">⚠️ Not Eligible</p>
                        <p style="color: #78350f; font-size: 0.9rem; margin-top: 0.5rem;">
                            This voting session is for a specific student group. You are not a member of that group.
                        </p>
                    </div>
                <?php else: ?>
                    <?php if (count($votedPositions) > 0): ?>
                        <span class="status-badge badge-voted">You have voted for <?php echo count($votedPositions); ?> position(s)</span>
                    <?php endif; ?>
                    
                    <a href="vote.php" class="btn btn-primary">Cast Your Vote</a>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="status-badge badge-inactive">NO ACTIVE SESSION</span>
                    <p class="info-text">There is currently no active voting session. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>🏆 Your Candidacies</h3>
            
            <?php if ($candidacies->num_rows > 0): ?>
                <ul class="candidacy-list">
                    <?php while ($candidacy = $candidacies->fetch_assoc()): ?>
                        <li class="candidacy-item">
                            <strong><?php echo htmlspecialchars($candidacy['position_name']); ?></strong>
                            <span class="status-badge badge-nominated" style="margin-left: 10px;">
                                <?php echo ucfirst($candidacy['status']); ?>
                            </span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <p class="info-text">You are not currently nominated for any position.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>📊 View Results</h3>
            <p class="info-text">Check the current voting results and see who's leading in each position.</p>
            <a href="student_results.php" class="btn btn-primary">View Results</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>