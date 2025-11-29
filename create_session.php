<?php
require_once 'config.php';
requireAdmin();

$error = '';
$success = '';
$warning = '';

// Check if there are old candidates from previous sessions
$conn = getDBConnection();
$oldCandidatesCount = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch_assoc()['count'];

// Handle cleanup of old candidates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_candidates'])) {
    // Make sure no active sessions exist
    $activeSessionCheck = "SELECT COUNT(*) as count FROM voting_sessions WHERE status IN ('active', 'pending', 'paused')";
    $activeCount = $conn->query($activeSessionCheck)->fetch_assoc()['count'];
    
    if ($activeCount > 0) {
        $error = 'Cannot cleanup candidates while there are active sessions! Complete or lock all sessions first.';
    } else {
        // Safe to delete candidates - all votes are in locked sessions
        if ($conn->query("DELETE FROM candidates")) {
            $success = ' Cleaned up ' . $oldCandidatesCount . ' old candidates! You can now create a new session with fresh candidates.';
            $oldCandidatesCount = 0;
        } else {
            $error = 'Failed to cleanup candidates: ' . $conn->error;
        }
    }
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $sessionName = trim($_POST['session_name']);
    
    if (empty($sessionName)) {
        $error = 'Session name is required';
    } else {
        // Check for existing active sessions
        $checkQuery = "SELECT id FROM voting_sessions WHERE status IN ('active', 'paused')";
        $result = $conn->query($checkQuery);
        
        if ($result->num_rows > 0) {
            $error = 'There is already an active or paused session. Please complete or lock it first.';
        } else {
            $stmt = $conn->prepare("INSERT INTO voting_sessions (session_name, status, created_by) VALUES (?, 'pending', ?)");
            $stmt->bind_param("si", $sessionName, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = 'Voting session created successfully!';
            } else {
                $error = 'Failed to create session. Please try again.';
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Voting Session</title>
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
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
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #a0aec0;
        }
        
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f56565;
        }
        
        .success {
            background: #c6f6d5;
            color: #22543d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }
        
        .warning {
            background: #fef3c7;
            color: #92400e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        
        .info-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box h4 {
            color: #065f46;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #065f46;
        }
        
        .cleanup-box {
            background: #fffbeb;
            border: 2px solid #f59e0b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .cleanup-box h3 {
            color: #92400e;
            margin-bottom: 15px;
        }
        
        .cleanup-box p {
            color: #78350f;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .stat-highlight {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #f59e0b;
        }
        
        .stat-label {
            color: #78350f;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Create Voting Session</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="error"> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br><br>
                <a href="manage_candidates.php" class="btn btn-primary"> Nominate Candidates</a>
                <a href="manage_session.php" class="btn btn-primary"> Manage Session</a>
            </div>
        <?php endif; ?>
        
        <?php if ($oldCandidatesCount > 0): ?>
        <div class="cleanup-box">
            <h3> Old Candidates Detected!</h3>
            <p>There are <strong><?php echo $oldCandidatesCount; ?> candidates</strong> from previous sessions in the system. It's recommended to clean them up before creating a new session.</p>
            
            <div class="stat-highlight">
                <div class="stat-number"><?php echo $oldCandidatesCount; ?></div>
                <div class="stat-label">Old Candidates</div>
            </div>
            
            <p><strong>What happens when you cleanup:</strong></p>
            <ul style="margin: 10px 0 15px 20px; color: #78350f;">
                <li>All old candidates will be removed</li>
                <li><strong>Vote records from locked sessions are PRESERVED</strong> in the database</li>
                <li>You can nominate fresh candidates for the new session</li>
                <li>Audit logs will still show all historical data</li>
            </ul>
            
            <form method="POST" onsubmit="return confirm(' Cleanup <?php echo $oldCandidatesCount; ?> old candidates?\n\nThis will delete old candidates but preserve all vote records from locked sessions.\n\nContinue?');">
                <button type="submit" name="cleanup_candidates" class="btn btn-warning">
                     Cleanup Old Candidates (<?php echo $oldCandidatesCount; ?>)
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>New Voting Session</h2>
            
            <div class="info-box">
                <h4> Before Creating a Session:</h4>
                <ul>
                    <li>Clean up old candidates from previous sessions (if any)</li>
                    <li>Make sure all previous sessions are locked</li>
                    <li>Only one active session can run at a time</li>
                    <li>You'll nominate candidates after creating the session</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="session_name">Session Name</label>
                    <input type="text" id="session_name" name="session_name" 
                           placeholder="e.g., Student Council Elections 2025" required>
                </div>
                
                <button type="submit" name="create_session" class="btn btn-primary">Create Session</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        
        <div class="card">
            <h2> Next Steps After Creation</h2>
            <ol style="color: #4a5568; line-height: 2; margin-left: 20px;">
                <li><strong>Nominate Candidates</strong> - Add candidates for each position</li>
                <li><strong>Open Position Voting</strong> - Start with the highest priority position</li>
                <li><strong>Students Vote</strong> - Students cast their votes</li>
                <li><strong>Close & Determine Winner</strong> - Lock in the winner</li>
                <li><strong>Repeat</strong> - Move to the next position</li>
                <li><strong>Lock Session</strong> - When all positions are complete, lock the session to preserve all data</li>
            </ol>
        </div>
    </div>
</body>
</html>