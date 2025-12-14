<?php
require_once '../config.php';
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
    <title>Student Dashboard - VoteSystem Pro</title>
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
            max-width: 1200px;
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

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: #f0fdf4;
            border-radius: 50px;
            border: 2px solid #d1fae5;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .modern-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }
        
        .welcome-card h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .welcome-card .student-id {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.15), 0 10px 10px -5px rgba(16, 185, 129, 0.1);
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }
        
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background: #e5e7eb;
            color: #4a5568;
        }
        
        .badge-voted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-nominated {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Enhanced Buttons */
        .btn-modern {
            padding: 0.875rem 1.75rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
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
        
        .btn-disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .info-section {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .eligibility-notice {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1.25rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .eligibility-notice-title {
            color: #92400e;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .eligibility-notice-text {
            color: #78350f;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .candidacy-list {
            list-style: none;
        }
        
        .candidacy-item {
            padding: 1.25rem;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-left: 4px solid #10b981;
            margin-bottom: 1rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .candidacy-item:hover {
            border-color: #10b981;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        
        .candidacy-item-title {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.125rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: #6b7280;
            font-size: 1rem;
            line-height: 1.6;
        }

        .vote-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .vote-info-box {
            padding: 1.25rem;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 12px;
            text-align: center;
        }

        .vote-info-label {
            color: #065f46;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .vote-info-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #065f46;
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
                text-align: center;
            }
            
            .welcome-card h2 {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .candidacy-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .vote-info-grid {
                grid-template-columns: 1fr;
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
                    <h1>VoteSystem Pro</h1>
                    <p>Student Portal</p>
                </div>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <span style="font-weight: 500; color: #1f2937;">
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </span>
                </div>
                <a href="../logout.php" class="btn-modern btn-secondary">
                    Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Welcome Card -->
        <div class="welcome-card fade-in">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
            <p class="student-id">Student ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
        </div>
        
        <!-- Voting Session Card -->
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h2 class="card-title">Voting Session</h2>
            </div>
            <div class="card-body">
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
                    
                    <span class="status-badge badge-active">Active Session</span>
                    
                    <div class="info-section">
                        <p class="info-label">Session Name</p>
                        <p class="info-value"><?php echo htmlspecialchars($activeSession['session_name']); ?></p>
                    </div>
                    
                    <?php if ($groupName): ?>
                        <div class="info-section">
                            <p class="info-label">Target Group</p>
                            <p class="info-value"><?php echo htmlspecialchars($groupName); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$isEligible): ?>
                        <div class="eligibility-notice">
                            <p class="eligibility-notice-title">
                                <span>Not Eligible</span>
                            </p>
                            <p class="eligibility-notice-text">
                                This voting session is for a specific student group. You are not a member of that group and cannot participate in this session.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if (count($votedPositions) > 0): ?>
                            <div class="vote-info-grid">
                                <div class="vote-info-box">
                                    <p class="vote-info-label">Positions Voted</p>
                                    <p class="vote-info-value"><?php echo count($votedPositions); ?></p>
                                </div>
                            </div>
                            <span class="status-badge badge-voted">You have cast your vote</span>
                        <?php endif; ?>
                        
                        <a href="vote.php" class="btn-modern btn-primary">Cast Your Vote</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="status-badge badge-inactive">No Active Session</span>
                        <p class="empty-title">No voting session available</p>
                        <p class="empty-description">There is currently no active voting session. Please check back later when a session becomes available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Candidacies Card -->
        <div class="modern-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h2 class="card-title">Your Candidacies</h2>
            </div>
            <div class="card-body">
                <?php if ($candidacies->num_rows > 0): ?>
                    <ul class="candidacy-list">
                        <?php while ($candidacy = $candidacies->fetch_assoc()): ?>
                            <li class="candidacy-item">
                                <span class="candidacy-item-title">
                                    <?php echo htmlspecialchars($candidacy['position_name']); ?>
                                </span>
                                <span class="status-badge badge-<?php echo $candidacy['status']; ?>">
                                    <?php echo ucfirst($candidacy['status']); ?>
                                </span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <p class="empty-title">No candidacies</p>
                        <p class="empty-description">You are not currently nominated for any position.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Results Card -->
        <div class="modern-card fade-in" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h2 class="card-title">Voting Results</h2>
            </div>
            <div class="card-body">
                <p class="empty-description" style="text-align: left; margin-bottom: 1.5rem;">
                    Check the current voting results and see who's leading in each position.
                </p>
                <a href="../students/student_results.php" class="btn-modern btn-primary">View Results</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>