<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper/verification_helper.php';

$verification = new VerificationHelper();
$message = '';
$messageType = '';
$verificationSuccess = false;
$userName = '';
$debugInfo = [];

// Debug: Log the token received
$debugInfo['token_received'] = isset($_GET['token']) ? 'YES' : 'NO';
$debugInfo['token_value'] = isset($_GET['token']) ? substr($_GET['token'], 0, 20) . '...' : 'NONE';
$debugInfo['token_length'] = isset($_GET['token']) ? strlen($_GET['token']) : 0;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $message = 'Invalid verification link. No token provided.';
    $messageType = 'error';
    $debugInfo['error'] = 'No token in URL';
} else {
    $token = trim($_GET['token']);
    $debugInfo['token_trimmed'] = substr($token, 0, 20) . '...';
    
    // Debug: Check if token exists in database
    $conn = getDBConnection();
    $checkStmt = $conn->prepare("SELECT id, student_id, email, email_verified, token_expires_at 
                                 FROM users WHERE verification_token = ?");
    $checkStmt->bind_param("s", $token);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    $debugInfo['token_found_in_db'] = $checkResult->num_rows > 0 ? 'YES' : 'NO';
    
    if ($checkResult->num_rows > 0) {
        $userData = $checkResult->fetch_assoc();
        $debugInfo['user_email'] = $userData['email'];
        $debugInfo['user_verified'] = $userData['email_verified'];
        $debugInfo['token_expires'] = $userData['token_expires_at'];
        $debugInfo['current_time'] = date('Y-m-d H:i:s');
        $debugInfo['is_expired'] = strtotime($userData['token_expires_at']) < time() ? 'YES' : 'NO';
    }
    
    $checkStmt->close();
    $conn->close();
    
    // Verify the token
    $result = $verification->verifyToken($token);
    
    $debugInfo['verification_result'] = $result['success'] ? 'SUCCESS' : 'FAILED';
    $debugInfo['verification_message'] = $result['message'];
    
    if ($result['success']) {
        $verificationSuccess = true;
        $userName = $result['user_name'];
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Email Verification Debug - WMSU Voting System</title>
    <style>
        .verification-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }
        .success-icon::before {
            content: "✓";
        }
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #f56565;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }
        .error-icon::before {
            content: "×";
        }
        .message {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
        }
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        .btn-container {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #059669;
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
        .debug-info {
            background: #f7fafc;
            border: 2px solid #3b82f6;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
            text-align: left;
        }
        .debug-info h3 {
            color: #3b82f6;
            margin-bottom: 15px;
        }
        .debug-item {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .debug-label {
            font-weight: bold;
            color: #1e40af;
        }
    </style>
</head>
<body class="login-page">
    <div class="verification-container">
        <?php if ($verificationSuccess): ?>
            <div class="success-icon"></div>
            <h2>Email Verified!</h2>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <p>Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong>!</p>
            <p>Your WMSU email has been successfully verified. You can now log in to participate in voting.</p>
            
            <div class="btn-container">
                <a href="login.php" class="btn">
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <div class="error-icon"></div>
            <h2>Verification Failed</h2>
            <div class="message error">
                <?php echo htmlspecialchars($message); ?>
            </div>
            
            <p>Your email could not be verified. This may be because:</p>
            <ul style="text-align: left; margin: 20px auto; max-width: 350px;">
                <li>The verification link has expired (valid for 24 hours)</li>
                <li>The link is invalid or has been tampered with</li>
                <li>Your email has already been verified</li>
            </ul>
            
            <div class="btn-container">
                <a href="register.php" class="btn">
                    Register Again
                </a>
                <a href="login.php" class="btn btn-secondary">
                    Try Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>