<?php
require_once '../config.php';
require_once '../helper/email_helper.php';
require_once '../notifications/notification_helper.php';
requireLogin();

$conn = getDBConnection();
$email = new EmailHelper();
$notif = new NotificationHelper();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$debugInfo = [];

// Get active session with validation
$sessionQuery = "SELECT * FROM voting_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

if (!$activeSession) {
    $conn->close();
    header('Location: student_dashboard.php');
    exit();
}

$sessionId = $activeSession['id'];
$currentPositionId = $activeSession['current_position_id'];

// Check if user is eligible to vote in this session
if ($activeSession['group_id']) {
    $eligibilityQuery = "SELECT COUNT(*) as count FROM student_group_members 
                         WHERE group_id = ? AND user_id = ?";
    $stmt = $conn->prepare($eligibilityQuery);
    $stmt->bind_param("ii", $activeSession['group_id'], $userId);
    $stmt->execute();
    $isEligible = $stmt->get_result()->fetch_assoc()['count'] > 0;
    $stmt->close();
    
    if (!$isEligible) {
        $conn->close();
        header('Location: student_dashboard.php?error=not_eligible');
        exit();
    }
}

// Debug info
$debugInfo[] = "Session ID: " . $sessionId;
$debugInfo[] = "User ID: " . $userId;
$debugInfo[] = "Current Position ID: " . ($currentPositionId ? $currentPositionId : "NONE OPEN");

// Get user information using helper function
$userQuery = "SELECT first_name, middle_name, last_name, student_id FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Compute full name safely
$userInfo['full_name'] = formatStudentName(
    $userInfo['first_name'],
    $userInfo['middle_name'],
    $userInfo['last_name']
);

// Check if user is a candidate
$userCandidacyQuery = "SELECT p.position_name, c.status 
                      FROM candidates c 
                      JOIN positions p ON c.position_id = p.id 
                      WHERE c.user_id = ?";
$stmt = $conn->prepare($userCandidacyQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userCandidacies = $stmt->get_result();
$isCandidate = ($userCandidacies->num_rows > 0);
$stmt->close();

// Initialize variables
$noPositionOpen = !$currentPositionId;
$hasVoted = false;
$currentPosition = null;
$candidates = null;

if (!$noPositionOpen) {
    // Get the current position details
    $positionQuery = "SELECT * FROM positions WHERE id = ?";
    $stmt = $conn->prepare($positionQuery);
    $stmt->bind_param("i", $currentPositionId);
    $stmt->execute();
    $currentPosition = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$currentPosition) {
        $noPositionOpen = true;
        $debugInfo[] = "Error: Position ID " . $currentPositionId . " not found";
    } else {
        $debugInfo[] = "Open Position: " . $currentPosition['position_name'];
        
        // Check if user has already voted
        $voteCheckQuery = "SELECT id, voted_at FROM votes 
                          WHERE session_id = ? AND voter_id = ? AND position_id = ? 
                          AND deleted_at IS NULL";
        $stmt = $conn->prepare($voteCheckQuery);
        $stmt->bind_param("iii", $sessionId, $userId, $currentPositionId);
        $stmt->execute();
        $existingVote = $stmt->get_result()->fetch_assoc();
        $hasVoted = ($existingVote !== null);
        $stmt->close();
        
        $debugInfo[] = "Vote Status: " . ($hasVoted ? "Already voted at " . $existingVote['voted_at'] : "NOT voted yet");
        
        // Get candidates
        $candidatesQuery = "SELECT c.id, c.user_id, c.status, 
                        u.first_name, u.middle_name, u.last_name, u.student_id 
                        FROM candidates c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.position_id = ? 
                        AND c.deleted_at IS NULL 
                        AND u.deleted_at IS NULL
                        ORDER BY u.last_name, u.first_name";
        $stmt = $conn->prepare($candidatesQuery);
        $stmt->bind_param("i", $currentPositionId);
        $stmt->execute();
        $candidates = $stmt->get_result();
        $stmt->close();
        
        $debugInfo[] = "Available Candidates: " . $candidates->num_rows;
    }
}

// Handle vote submission with transaction safety
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id']) && isset($_POST['position_id'])) {
    $candidateId = intval($_POST['candidate_id']);
    $positionId = intval($_POST['position_id']);
    
    $debugInfo[] = "=== VOTE SUBMISSION ATTEMPT ===";
    $debugInfo[] = "Candidate ID: " . $candidateId;
    $debugInfo[] = "Position ID: " . $positionId;
    
    try {
        // Use the safe recordVote function
        recordVote($sessionId, $userId, $candidateId, $positionId, $conn);
        
        // VOTE SUCCESSFUL - SEND NOTIFICATIONS
        
        // Get voter details
        $voterQuery = "SELECT first_name, middle_name, last_name, email FROM users WHERE id = ?";
        $stmt = $conn->prepare($voterQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $voterData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $voterFullName = formatStudentName(
            $voterData['first_name'],
            $voterData['middle_name'],
            $voterData['last_name']
        );
        
        // Get position details
        $positionQuery = "SELECT position_name FROM positions WHERE id = ?";
        $stmt = $conn->prepare($positionQuery);
        $stmt->bind_param("i", $positionId);
        $stmt->execute();
        $position = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Send confirmation email
        $email->sendVotingConfirmationEmail(
            $voterData['email'],
            $voterFullName,
            $position['position_name']
        );
        
        // Notify all admins
        $adminQuery = "SELECT id FROM users WHERE role = 'admin'";
        $admins = $conn->query($adminQuery);
        
        while ($admin = $admins->fetch_assoc()) {
            $notif->notifyAdminNewVote(
                $admin['id'],
                $voterFullName,
                $position['position_name']
            );
        }
        
        // Check milestones
        $totalStudentsQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'student' AND deleted_at IS NULL";
        $totalStudents = $conn->query($totalStudentsQuery)->fetch_assoc()['count'];
        
        $votersQuery = "SELECT COUNT(DISTINCT voter_id) as count FROM votes 
                       WHERE session_id = ? AND position_id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($votersQuery);
        $stmt->bind_param("ii", $sessionId, $positionId);
        $stmt->execute();
        $votersCount = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        if ($totalStudents > 0) {
            $turnoutPercentage = ($votersCount / $totalStudents) * 100;
            
            // Get session name
            $sessionQuery = "SELECT session_name FROM voting_sessions WHERE id = ?";
            $stmt = $conn->prepare($sessionQuery);
            $stmt->bind_param("i", $sessionId);
            $stmt->execute();
            $session = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Check milestone notifications
            $milestones = [25, 50, 75, 100];
            foreach ($milestones as $milestone) {
                $previousTurnout = (($votersCount - 1) / $totalStudents) * 100;
                if ($turnoutPercentage >= $milestone && $previousTurnout < $milestone) {
                    $admins->data_seek(0);
                    while ($admin = $admins->fetch_assoc()) {
                        $notif->notifyAdminMilestone(
                            $admin['id'],
                            $session['session_name'],
                            $milestone,
                            $position['position_name']
                        );
                        
                        if ($milestone == 100) {
                            $notif->notifyAdminFullTurnout(
                                $admin['id'],
                                $session['session_name'],
                                $position['position_name']
                            );
                        }
                    }
                }
            }
        }
        
        $message = 'Your vote has been recorded successfully!';
        $messageType = 'success';
        $hasVoted = true;
        
        // Log the vote
        error_log("VOTE: User=" . $userId . " (" . $userInfo['student_id'] . "), Position=" . $positionId . ", Candidate=" . $candidateId);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        $debugInfo[] = "Error: " . $e->getMessage();
    }
}

// Get total votes for current position
$totalVotes = 0;
if (!$noPositionOpen && $currentPosition) {
    $voteCountQuery = "SELECT COUNT(*) as total FROM votes 
                      WHERE session_id = ? AND position_id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($voteCountQuery);
    $stmt->bind_param("ii", $sessionId, $currentPositionId);
    $stmt->execute();
    $totalVotes = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Cast Your Vote - VoteSystem Pro</title>
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
            font-size: 0.875rem;
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

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
            font-size: 1.125rem;
            padding: 1rem 2rem;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }
        
        .modern-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        /* Session Header */
        .session-header-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
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
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .session-subtitle {
            font-size: 1rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        /* Message Alerts */
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
            animation: fadeIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #ef4444;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #f59e0b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #3b82f6;
        }

        .alert-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .alert ul {
            margin: 0.75rem 0 0 1.25rem;
            line-height: 1.6;
        }

        .alert ul li {
            margin: 0.5rem 0;
        }
        
        /* Modern Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .card-header h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .card-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            font-size: 0.875rem;
            opacity: 0.95;
            flex-wrap: wrap;
        }

        .meta-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }
        
        /* Candidate Items */
        .candidate-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .candidate-item:hover {
            border-color: #10b981;
            background: white;
            transform: translateX(5px);
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }

        .candidate-item input[type="radio"] {
            width: 24px;
            height: 24px;
            margin-right: 1.5rem;
            cursor: pointer;
            accent-color: #10b981;
        }

        .candidate-info {
            flex: 1;
        }

        .candidate-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .candidate-id {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .you-badge {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* Success State */
        .success-state {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .success-state h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .success-state p {
            font-size: 1.125rem;
            line-height: 1.6;
            opacity: 0.95;
            margin: 0.75rem 0;
        }

        .success-state-meta {
            font-size: 1rem;
            opacity: 0.9;
            margin-top: 1.5rem;
        }

        .success-state .btn-secondary {
            margin-top: 1.5rem;
            background: white;
            color: #10b981;
        }

        .success-state .btn-secondary:hover {
            background: #f0fdf4;
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .empty-state h3 {
            color: #10b981;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 1rem;
            line-height: 1.6;
            margin: 0.75rem 0;
        }

        .empty-state p strong {
            color: #1f2937;
        }

        .empty-state-meta {
            font-size: 0.875rem;
            color: #9ca3af;
            margin-top: 1.5rem;
        }

        .no-candidates {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .no-candidates p:first-child {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        
        /* Debug Info */
        .debug-info {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 2rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }

        .debug-info h4 {
            color: #10b981;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .debug-info ul {
            list-style: none;
            color: #4a5568;
        }

        .debug-info ul li {
            padding: 0.375rem 0;
        }

        .debug-info p {
            margin-top: 1rem;
            color: #6b7280;
            font-size: 0.875rem;
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

            .card-header h3 {
                font-size: 1.5rem;
            }

            .candidate-name {
                font-size: 1.125rem;
            }

            .candidate-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .candidate-item input[type="radio"] {
                margin-bottom: 1rem;
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
                    <p>Cast Your Vote</p>
                </div>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userInfo['full_name'], 0, 1)); ?>
                    </div>
                    <span style="font-weight: 500; color: #1f2937;">
                        <?php echo htmlspecialchars($userInfo['full_name']); ?>
                    </span>
                    <span style="color: #6b7280; font-size: 0.875rem;">
                        (<?php echo htmlspecialchars($userInfo['student_id']); ?>)
                    </span>
                </div>
                <a href="student_dashboard.php" class="btn-modern btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Session Header -->
        <div class="session-header-card fade-in">
            <h2 class="session-title"><?php echo htmlspecialchars($activeSession['session_name']); ?></h2>
            <p class="session-subtitle">Sequential Voting - One Position at a Time</p>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Candidacy Status -->
        <?php if ($isCandidate): ?>
            <div class="alert alert-warning fade-in">
                <div class="alert-title">Your Candidacy Status</div>
                <ul>
                    <?php
                    $userCandidacies->data_seek(0);
                    while ($candidacy = $userCandidacies->fetch_assoc()):
                    ?>
                        <li><?php echo htmlspecialchars($candidacy['position_name']); ?> - <strong><?php echo ucfirst($candidacy['status']); ?></strong></li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- No Position Open State -->
        <?php if ($noPositionOpen): ?>
            <div class="empty-state fade-in">
                <h3>No Position Open for Voting</h3>
                <p><strong>What this means:</strong> The administrator hasn't opened a position for voting yet, or closed the previous position and hasn't opened the next one.</p>
                <p style="margin-top: 1rem;"><strong>What to do:</strong> Please wait. The page will auto-refresh. When a position opens, you'll be able to vote!</p>
                <p class="empty-state-meta">This page auto-refreshes every 15 seconds</p>
            </div>
            
            <div class="alert alert-info fade-in" style="animation-delay: 0.1s;">
                <div class="alert-title">How Sequential Voting Works</div>
                <ul>
                    <li>Admin opens ONE position at a time (e.g., President)</li>
                    <li>ALL students can vote for that position</li>
                    <li>When voting is complete, admin closes that position</li>
                    <li>Then admin opens the NEXT position (e.g., Vice President)</li>
                    <li>This continues until all positions are filled</li>
                </ul>
            </div>
        
        <!-- Already Voted State -->
        <?php elseif ($hasVoted): ?>
            <div class="success-state fade-in">
                <h3>Vote Recorded!</h3>
                <p>Thank you for voting for <strong><?php echo htmlspecialchars($currentPosition['position_name']); ?></strong></p>
                <p>Your vote has been securely recorded and counted.</p>
                <p class="success-state-meta"><?php echo $totalVotes; ?> total votes cast so far</p>
                <a href="../students/student_results.php" class="btn-modern btn-secondary">
                    View Results
                </a>
            </div>
            
            <div class="alert alert-info fade-in" style="animation-delay: 0.1s;">
                <div class="alert-title">What happens next?</div>
                <p style="margin: 0.5rem 0 0 0;">The admin will close this position when enough votes are collected, determine the winner, then open the next position. Check back later!</p>
            </div>
        
        <!-- Voting Form -->
        <?php else: ?>
            <div class="modern-card fade-in">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($currentPosition['position_name']); ?></h3>
                    <div class="card-meta">
                        <span class="meta-badge">Priority #<?php echo $currentPosition['position_order']; ?></span>
                        <span class="meta-badge"><?php echo $totalVotes; ?> votes cast</span>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if ($candidates && $candidates->num_rows > 0): ?>
                        <form method="POST" action="" id="voteForm">
                            <input type="hidden" name="position_id" value="<?php echo $currentPositionId; ?>">
                            
                            <?php while ($candidate = $candidates->fetch_assoc()): ?>
                                <label class="candidate-item">
                                    <input type="radio" name="candidate_id" value="<?php echo $candidate['id']; ?>" required>
                                    <div class="candidate-info">
                                        <?php $candidateName = formatStudentName($candidate['first_name'], $candidate['middle_name'], $candidate['last_name']); ?>
                                        <div class="candidate-name">
                                            <?php echo htmlspecialchars($candidateName); ?>
                                            <?php if ($candidate['user_id'] == $userId): ?>
                                                <span class="you-badge">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="candidate-id">Student ID: <?php echo htmlspecialchars($candidate['student_id']); ?></div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                            
                            <button type="submit" class="btn-modern btn-primary" onclick="return confirm('Confirm your vote?\n\nYou can only vote ONCE for this position!\n\nClick OK to submit.')">
                                Submit My Vote
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="no-candidates">
                            <p>No candidates nominated yet</p>
                            <p>The administrator needs to nominate candidates for this position first.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Debug Info -->
        <?php if (count($debugInfo) > 0 && (isset($_GET['debug']) || $messageType === 'error')): ?>
            <div class="debug-info">
                <h4>Debug Information</h4>
                <ul>
                    <?php foreach ($debugInfo as $info): ?>
                        <li><?php echo htmlspecialchars($info); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>If you see errors, screenshot this and show your administrator.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-refresh every 15 seconds
        setTimeout(function() { 
            if (document.visibilityState === 'visible') {
                location.reload(); 
            }
        }, 15000);
        
        <?php if (!$hasVoted && !$noPositionOpen && $candidates && $candidates->num_rows > 0): ?>
        // Warn before leaving if form not submitted
        window.addEventListener('beforeunload', function (e) {
            var form = document.getElementById('voteForm');
            if (form && !form.hasAttribute('data-submitted')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Mark form as submitted
        document.getElementById('voteForm').addEventListener('submit', function() {
            this.setAttribute('data-submitted', 'true');
        });
        <?php endif; ?>
    </script>
</body>
</html> 