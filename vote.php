<?php
require_once 'config.php';
require_once 'email_helper.php';
require_once 'notification_helper.php';
requireLogin();

$conn = getDBConnection();
$email = new EmailHelper();
$notif = new NotificationHelper();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$debugInfo = [];

// Get active session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status = 'active' ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$activeSession = $sessionResult->fetch_assoc();

if (!$activeSession) {
    header('Location: student_dashboard.php');
    exit();
}

$sessionId = $activeSession['id'];
$currentPositionId = $activeSession['current_position_id'];

// Debug: Log voting attempt
$debugInfo[] = "Session ID: " . $sessionId;
$debugInfo[] = "User ID: " . $userId;
$debugInfo[] = "Current Position ID: " . ($currentPositionId ? $currentPositionId : "NONE OPEN");

// Get user information with computed full_name (3NF compliant) - FIXED LINE 31
$userQuery = "SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name, 
              student_id 
              FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$userInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if user is a candidate or elected in any position (for display purposes only - NOT to restrict voting)
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

// Check if there's a position currently open for voting
if (!$currentPositionId) {
    $noPositionOpen = true;
    $debugInfo[] = "Status: No position is currently open for voting";
} else {
    $noPositionOpen = false;
    
    // Get the current position details
    $positionQuery = "SELECT * FROM positions WHERE id = ?";
    $stmt = $conn->prepare($positionQuery);
    $stmt->bind_param("i", $currentPositionId);
    $stmt->execute();
    $currentPosition = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $debugInfo[] = "Open Position: " . $currentPosition['position_name'];
    
    // Check if user has already voted for this position
    $voteCheckQuery = "SELECT id, voted_at FROM votes WHERE session_id = ? AND voter_id = ? AND position_id = ?";
    $stmt = $conn->prepare($voteCheckQuery);
    $stmt->bind_param("iii", $sessionId, $userId, $currentPositionId);
    $stmt->execute();
    $existingVote = $stmt->get_result()->fetch_assoc();
    $hasVoted = ($existingVote !== null);
    $stmt->close();
    
    if ($hasVoted) {
        $debugInfo[] = "Vote Status: Already voted at " . $existingVote['voted_at'];
    } else {
        $debugInfo[] = "Vote Status: NOT voted yet - eligible to vote";
    }
    
    // Get candidates for current position with computed full_name (3NF compliant)
    $candidatesQuery = "SELECT c.id, c.user_id, c.status, 
                        u.first_name, u.middle_name, u.last_name, u.student_id 
                        FROM candidates c 
                        JOIN users u ON c.user_id = u.id 
                        WHERE c.position_id = ?
                        ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($candidatesQuery);
    $stmt->bind_param("i", $currentPositionId);
    $stmt->execute();
    $candidates = $stmt->get_result();
    $stmt->close();
    
    $debugInfo[] = "Available Candidates: " . $candidates->num_rows;
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id']) && isset($_POST['position_id'])) {
    $candidateId = $_POST['candidate_id'];
    $positionId = $_POST['position_id'];
    
    $debugInfo[] = "=== VOTE SUBMISSION ATTEMPT ===";
    $debugInfo[] = "Candidate ID: " . $candidateId;
    $debugInfo[] = "Position ID: " . $positionId;
    
    // Validate that this is the current position open for voting
    if ($positionId != $currentPositionId) {
        $message = ' Invalid position! This position is not currently open for voting. The admin may have closed it.';
        $messageType = 'error';
        $debugInfo[] = "Error: Position mismatch";
    } else {
        // CRITICAL CHECK: Verify user hasn't already voted for this position
        $voteCheckQuery = "SELECT id FROM votes WHERE session_id = ? AND voter_id = ? AND position_id = ?";
        $stmt = $conn->prepare($voteCheckQuery);
        $stmt->bind_param("iii", $sessionId, $userId, $positionId);
        $stmt->execute();
        $alreadyVoted = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($alreadyVoted) {
            $message = ' You have already voted for this position!';
            $messageType = 'warning';
            $debugInfo[] = "Error: Duplicate vote attempt blocked";
        } else {
            // Verify the candidate exists and is for the correct position
            $candidateCheckQuery = "SELECT id FROM candidates WHERE id = ? AND position_id = ?";
            $stmt = $conn->prepare($candidateCheckQuery);
            $stmt->bind_param("ii", $candidateId, $positionId);
            $stmt->execute();
            $candidateValid = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            
            if (!$candidateValid) {
                $message = ' Invalid candidate selection!';
                $messageType = 'error';
                $debugInfo[] = "Error: Invalid candidate";
            } else {
                // Insert the vote (3NF normalized - only foreign keys)
                $voteStmt = $conn->prepare("INSERT INTO votes (session_id, voter_id, candidate_id, position_id) VALUES (?, ?, ?, ?)");
                $voteStmt->bind_param("iiii", $sessionId, $userId, $candidateId, $positionId);
                
                if ($voteStmt->execute()) {
    // ✅ VOTE SUCCESSFUL - SEND NOTIFICATIONS
    
    // Get voter details
    $voterQuery = "SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name, email 
                   FROM users WHERE id = ?";
    $stmt = $conn->prepare($voterQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $voter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get position details
    $positionQuery = "SELECT position_name FROM positions WHERE id = ?";
    $stmt = $conn->prepare($positionQuery);
    $stmt->bind_param("i", $positionId);
    $stmt->execute();
    $position = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Send confirmation email
    $email->sendVotingConfirmationEmail(
        $voter['email'],
        $voter['full_name'],
        $position['position_name']
    );
    
    // Notify all admins
    $adminQuery = "SELECT id FROM users WHERE role = 'admin'";
    $admins = $conn->query($adminQuery);
    
    while ($admin = $admins->fetch_assoc()) {
        $notif->notifyAdminNewVote(
            $admin['id'],
            $voter['full_name'],
            $position['position_name']
        );
    }
    
    // Check milestones
    $totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
    
    $votersQuery = "SELECT COUNT(DISTINCT voter_id) as count FROM votes WHERE session_id = ? AND position_id = ?";
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
    
    $message = ' Your vote has been recorded successfully!';
    $messageType = 'success';
    $hasVoted = true;
    
    // Log the vote
    error_log("VOTE: User=" . $userId . " (" . $userInfo['student_id'] . "), Position=" . $positionId . ", Candidate=" . $candidateId);
} else {
    // VOTE FAILED
    $message = ' Failed to record your vote. Error: ' . $voteStmt->error;
    $messageType = 'error';
    $debugInfo[] = "Error: Database insert failed - " . $voteStmt->error;
}
$voteStmt->close();
            }
        }
    }
}

// Get total votes for current position
$totalVotes = 0;
if (!$noPositionOpen) {
    $voteCountQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
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
    <title>Cast Your Vote</title>
    <style>
        * {margin:0; padding:0; box-sizing:border-box;}
        body {font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:#f7fafc; min-height:100vh;}
        .navbar {background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:white; padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 4px rgba(0,0,0,0.1);}
        .navbar h1 {font-size:1.5em;}
        .navbar .user-info {display:flex; align-items:center; gap:15px; font-size:0.95em;}
        .navbar a {color:white; text-decoration:none; padding:8px 16px; background:rgba(255,255,255,0.2); border-radius:5px; transition:background 0.3s;}
        .navbar a:hover {background:rgba(255,255,255,0.3);}
        .container {max-width:1000px; margin:2rem auto; padding:0 2rem;}
        .session-info {background:white; padding:20px; border-radius:10px; box-shadow:0 2px 4px rgba(0,0,0,0.1); margin-bottom:20px; text-align:center;}
        .session-info h2 {color:#10b981; margin-bottom:5px;}
        .session-info p {color:#718096;}
        .message {padding:15px 20px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:600; font-size:1.05em;}
        .message.success {background:#d1fae5; color:#065f46; border-left:4px solid #10b981;}
        .message.error {background:#fed7d7; color:#c53030; border-left:4px solid #f56565;}
        .message.warning {background:#fef3c7; color:#92400e; border-left:4px solid #f59e0b;}
        .info-banner {background:#dbeafe; border-left:4px solid #3b82f6; padding:15px 20px; margin-bottom:20px; border-radius:5px;}
        .info-banner strong {color:#1e40af; display:block; margin-bottom:8px;}
        .info-banner ul {margin:10px 0 0 20px; color:#1e40af;}
        .info-banner ul li {margin:5px 0;}
        .success-banner {background:#d1fae5; border-left:4px solid #10b981; padding:15px 20px; margin-bottom:20px; border-radius:5px;}
        .success-banner strong {color:#065f46; display:block; margin-bottom:8px;}
        .candidate-status-info {background:#fef3c7; border-left:4px solid #f59e0b; padding:15px 20px; margin-bottom:20px; border-radius:5px;}
        .candidate-status-info strong {color:#92400e;}
        .candidate-status-info ul {margin:10px 0 0 20px; color:#744210;}
        .position-card {background:white; border-radius:10px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin-bottom:20px;}
        .position-header {background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:white; padding:25px; text-align:center;}
        .position-header h3 {font-size:2em; margin-bottom:10px;}
        .vote-count-badge {background:rgba(255,255,255,0.2); padding:8px 16px; border-radius:20px; display:inline-block; font-size:0.9em;}
        .candidates-list {padding:30px;}
        .candidate-item {display:flex; align-items:center; padding:20px; border:2px solid #e2e8f0; border-radius:10px; margin-bottom:15px; cursor:pointer; transition:all 0.3s;}
        .candidate-item:hover {border-color:#10b981; background:#f7fafc; transform:translateX(5px);}
        .candidate-item input[type="radio"] {width:24px; height:24px; margin-right:20px; cursor:pointer; accent-color:#10b981;}
        .candidate-info {flex:1;}
        .candidate-name {font-size:1.3em; font-weight:600; color:#2d3748; margin-bottom:5px;}
        .candidate-id {color:#718096; font-size:0.95em;}
        .vote-btn {width:100%; padding:18px; background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:white; border:none; border-radius:10px; font-size:1.2em; font-weight:600; cursor:pointer; transition:all 0.3s; margin-top:20px;}
        .vote-btn:hover {transform:translateY(-2px); box-shadow:0 10px 25px rgba(16,185,129,0.4);}
        .success-card {background:linear-gradient(135deg,#10b981 0%,#059669 100%); color:white; padding:40px; border-radius:10px; text-align:center; box-shadow:0 4px 6px rgba(0,0,0,0.1); margin-bottom:20px;}
        .success-card h3 {font-size:2em; margin-bottom:15px;}
        .success-card p {font-size:1.1em; margin:10px 0; opacity:0.95;}
        .empty-state {background:white; padding:60px 40px; border-radius:10px; text-align:center; box-shadow:0 2px 4px rgba(0,0,0,0.1);}
        .empty-state-icon {font-size:5em; margin-bottom:20px;}
        .empty-state h3 {color:#10b981; font-size:1.8em; margin-bottom:15px;}
        .empty-state p {color:#718096; font-size:1.1em; line-height:1.6; margin:10px 0;}
        .no-candidates {text-align:center; padding:40px; color:#718096;}
        .debug-info {background:#f7fafc; border:1px solid #e2e8f0; padding:15px; border-radius:5px; margin-top:20px; font-family:monospace; font-size:0.85em;}
        .debug-info h4 {color:#10b981; margin-bottom:10px;}
        .debug-info ul {list-style:none; color:#4a5568;}
        .debug-info ul li {padding:3px 0;}
        @media (max-width:768px) {
            .container {padding:1rem;}
            .navbar {flex-direction:column; gap:10px;}
            .position-header h3 {font-size:1.5em;}
            .candidate-name {font-size:1.1em;}
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Cast Your Vote</h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars($userInfo['full_name']); ?> (<?php echo htmlspecialchars($userInfo['student_id']); ?>)</span>
            <a href="student_dashboard.php">← Back</a>
        </div>
    </div>
    
    <div class="container">
        <div class="session-info">
            <h2><?php echo htmlspecialchars($activeSession['session_name']); ?></h2>
            <p>Sequential Voting - One Position at a Time</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($isCandidate): ?>
            <div class="candidate-status-info">
                <strong> Your Candidacy Status:</strong>
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
        
        <?php if ($noPositionOpen): ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <h3>No Position Open for Voting</h3>
                <p><strong>What this means:</strong> The administrator hasn't opened a position for voting yet, or closed the previous position and hasn't opened the next one.</p>
                <p style="margin-top:15px;"><strong>What to do:</strong> Please wait. The page will auto-refresh. When a position opens, you'll be able to vote!</p>
                <p style="margin-top:15px; font-size:0.95em; color:#a0aec0;">This page auto-refreshes every 15 seconds</p>
            </div>
            
            <div class="info-banner">
                <strong> How Sequential Voting Works:</strong>
                <ul>
                    <li>Admin opens ONE position at a time (e.g., President)</li>
                    <li>ALL students can vote for that position</li>
                    <li>When voting is complete, admin closes that position</li>
                    <li>Then admin opens the NEXT position (e.g., Vice President)</li>
                    <li>This continues until all positions are filled</li>
                </ul>
            </div>
        <?php elseif ($hasVoted): ?>
            <div class="success-card">
                <h3>Vote Recorded!</h3>
                <p>Thank you for voting for <strong><?php echo htmlspecialchars($currentPosition['position_name']); ?></strong></p>
                <p>Your vote has been securely recorded and counted.</p>
                <p style="font-size:0.95em; opacity:0.9; margin-top:20px;"> <?php echo $totalVotes; ?> total votes cast so far</p>
                <a href="student_results.php" style="background:white; color:#10b981; margin-top:20px; padding:12px 30px; text-decoration:none; display:inline-block; border-radius:8px; font-weight:600;">View Results</a>
            </div>
            
            <div class="info-banner">
                <strong>Note:</strong>
                <p style="margin:8px 0 0 0; color:#1e40af;">The admin will close this position when enough votes are collected, determine the winner, then open the next position. Check back later!</p>
            </div>
        <?php else: ?>
            
            <div class="position-card">
                <div class="position-header">
                    <h3> <?php echo htmlspecialchars($currentPosition['position_name']); ?></h3>
                    <span class="vote-count-badge">Priority #<?php echo $currentPosition['position_order']; ?> • <?php echo $totalVotes; ?> votes cast</span>
                </div>
                
                <div class="candidates-list">
                    <?php if ($candidates->num_rows > 0): ?>
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
                                                <span style="color:#10b981; font-size:0.8em;">(You)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="candidate-id">Student ID: <?php echo htmlspecialchars($candidate['student_id']); ?></div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                            
                            <button type="submit" class="vote-btn" onclick="return confirm(' Confirm your vote?\n\nYou can only vote ONCE for this position!\n\nClick OK to submit.')">✅ Submit My Vote</button>
                        </form>
                    <?php else: ?>
                        <div class="no-candidates">
                            <p style="font-size:1.2em;"> No candidates nominated yet</p>
                            <p style="margin-top:10px; color:#a0aec0;">The administrator needs to nominate candidates for this position first.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (count($debugInfo) > 0 && (isset($_GET['debug']) || $messageType === 'error')): ?>
            <div class="debug-info">
                <h4> Debug Information:</h4>
                <ul>
                    <?php foreach ($debugInfo as $info): ?>
                        <li><?php echo htmlspecialchars($info); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top:10px; color:#718096; font-size:0.9em;">If you see errors, screenshot this and show your administrator.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        setTimeout(function() { location.reload(); }, 15000);
        
        <?php if (!$hasVoted && !$noPositionOpen && $candidates->num_rows > 0): ?>
        window.addEventListener('beforeunload', function (e) {
            var form = document.getElementById('voteForm');
            if (form && !form.hasAttribute('data-submitted')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.getElementById('voteForm').addEventListener('submit', function() {
            this.setAttribute('data-submitted', 'true');
        });
        <?php endif; ?>
    </script>
</body>
</html>