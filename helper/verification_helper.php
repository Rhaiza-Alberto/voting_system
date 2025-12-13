<?php
// helper/verification_helper.php
require_once __DIR__ . '/../config.php';

class VerificationHelper {
    
    /**
     * Generate a secure verification token
     */
    public function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate WMSU email address
     */
    public function isValidWMSUEmail($email) {
        $email = strtolower(trim($email));
        return preg_match('/@wmsu\.edu\.ph$/i', $email);
    }
    
    /**
     * Send verification email with modern design
     */
    public function sendVerificationEmail($email, $fullName, $token) {
        $verificationLink = $this->getVerificationLink($token);
        
        require_once __DIR__ . '/../PHPMailer/Exception.php';
        require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/SMTP.php';
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'rhaizaalberto931@gmail.com';
            $mail->Password = 'zcdj efmp zshz iexw';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('rhaizaalberto931@gmail.com', 'WMSU Voting System');
            $mail->addAddress($email, $fullName);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = "Verify Your WMSU Voting System Account";
            
            $mail->Body = $this->getEmailTemplate($fullName, $verificationLink);
            
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Verification email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate modern email template
     */
    private function getEmailTemplate($fullName, $verificationLink) {
        return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
        }
        
        .email-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .email-logo {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .email-header h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .email-header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.95;
            font-weight: 500;
        }
        
        .email-content {
            padding: 40px 30px;
            background: white;
        }
        
        .email-content h2 {
            margin: 0 0 20px 0;
            font-size: 24px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .email-content p {
            margin: 0 0 16px 0;
            line-height: 1.6;
            color: #4b5563;
            font-size: 15px;
        }
        
        .verify-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            margin: 30px 0;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .link-box {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            word-break: break-all;
            font-size: 13px;
            color: #6b7280;
            font-family: 'Courier New', monospace;
            margin: 20px 0;
        }
        
        .warning-box {
            background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }
        
        .warning-box strong {
            color: #92400e;
            font-size: 16px;
            display: block;
            margin-bottom: 8px;
        }
        
        .warning-box p {
            color: #78350f;
            margin: 0;
        }
        
        .steps-section {
            background: #f9fafb;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
        }
        
        .steps-section h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }
        
        .steps-list {
            margin: 0;
            padding-left: 20px;
            color: #4b5563;
        }
        
        .steps-list li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .email-footer {
            background: #1f2937;
            color: #9ca3af;
            padding: 30px;
            text-align: center;
            font-size: 13px;
        }
        
        .email-footer p {
            margin: 0 0 8px 0;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 30px 0;
        }
        
        .center {
            text-align: center;
        }
        
        .stats-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin: 10px 5px;
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <div class='email-logo'>🗳️</div>
            <h1>VoteSystem Pro</h1>
            <p>Email Verification Required</p>
        </div>
        
        <div class='email-content'>
            <h2>Hello, " . htmlspecialchars($fullName) . "! 👋</h2>
            
            <p>Thank you for registering with the <strong>WMSU Classroom Voting System</strong>. We're excited to have you join our democratic community!</p>
            
            <p>To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
            
            <div class='center'>
                <a href='" . htmlspecialchars($verificationLink) . "' class='verify-button'>✓ Verify My Email Address</a>
            </div>
            
            <div class='divider'></div>
            
            <p style='font-size: 14px; color: #6b7280;'><strong>Alternative method:</strong> Copy and paste this link into your browser:</p>
            <div class='link-box'>" . htmlspecialchars($verificationLink) . "</div>
            
            <div class='warning-box'>
                <strong>⏰ Important Notice</strong>
                <p>This verification link will expire in <strong>24 hours</strong>. If you did not create an account, please ignore this email.</p>
            </div>
            
            <div class='steps-section'>
                <h3>📋 What happens next?</h3>
                <ul class='steps-list'>
                    <li><strong>Step 1:</strong> Click the verification link above</li>
                    <li><strong>Step 2:</strong> Your account will be activated immediately</li>
                    <li><strong>Step 3:</strong> Log in and participate in voting sessions</li>
                    <li><strong>Step 4:</strong> Exercise your democratic right!</li>
                </ul>
            </div>
            
        </div>
        
        <div class='email-footer'>
            <p><strong>WMSU Classroom Voting System</strong></p>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>If you need assistance, please contact your system administrator.</p>
            <p style='margin-top: 15px; opacity: 0.7;'>© 2024 Western Mindanao State University. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
        ";
    }
    
    /**
     * Generate verification link
     */
    private function getVerificationLink($token) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        
        // Get the base directory
        $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $scriptPath = rtrim($scriptPath, '/');
        
        $baseUrl = $protocol . "://" . $host . $scriptPath;
        
        return $baseUrl . "/verify_email.php?token=" . urlencode($token);
    }
    
    /**
     * Store verification token in database
     */
    public function storeVerificationToken($userId, $token) {
        $conn = getDBConnection();
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // First, clear any existing tokens for this user
        $clearStmt = $conn->prepare("UPDATE users SET verification_token = NULL, 
                                     token_expires_at = NULL WHERE id = ?");
        $clearStmt->bind_param("i", $userId);
        $clearStmt->execute();
        $clearStmt->close();
        
        // Now set the new token
        $stmt = $conn->prepare("UPDATE users SET verification_token = ?, 
                               token_expires_at = ? WHERE id = ?");
        $stmt->bind_param("ssi", $token, $expiresAt, $userId);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return $result;
    }
    
    /**
     * Verify token and activate account
     */
    public function verifyToken($token) {
        $conn = getDBConnection();
        
        // Get the user with this token
        $stmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, 
                               token_expires_at, email_verified FROM users 
                               WHERE verification_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'Invalid verification link. The token does not exist.'];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Check if already verified
        if ($user['email_verified'] == 1) {
            $conn->close();
            return ['success' => false, 'message' => 'This email has already been verified. You can log in now.'];
        }
        
        // Check if token expired
        $expiresAt = strtotime($user['token_expires_at']);
        $now = time();
        
        if ($expiresAt < $now) {
            $conn->close();
            return ['success' => false, 'message' => 'Verification link has expired (valid for 24 hours). Please register again or request a new verification email.'];
        }
        
        // Mark as verified and clear token
        $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1, 
                                      verification_token = NULL, 
                                      token_expires_at = NULL 
                                      WHERE id = ?");
        $updateStmt->bind_param("i", $user['id']);
        $success = $updateStmt->execute();
        $updateStmt->close();
        
        // Log verification
        $this->logVerification($user['email'], $token, true);
        
        $conn->close();
        
        if ($success) {
            $fullName = formatStudentName($user['first_name'], $user['middle_name'], $user['last_name']);
            return [
                'success' => true, 
                'message' => 'Email verified successfully! You can now log in.',
                'user_name' => $fullName
            ];
        }
        
        return ['success' => false, 'message' => 'Verification failed. Please try again.'];
    }
    
    /**
     * Log verification attempt
     */
    private function logVerification($email, $token, $verified) {
        try {
            $conn = getDBConnection();
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $verifiedAt = $verified ? date('Y-m-d H:i:s') : null;
            
            // Check if table exists first
            $tableCheck = $conn->query("SHOW TABLES LIKE 'email_verification_logs'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO email_verification_logs 
                                       (email, token, ip_address, verified, verified_at) 
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $email, $token, $ipAddress, $verified, $verifiedAt);
                $stmt->execute();
                $stmt->close();
            }
            $conn->close();
        } catch (Exception $e) {
            error_log("Verification log error: " . $e->getMessage());
        }
    }
    
    /**
     * Resend verification email
     */
    public function resendVerification($email) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, email_verified 
                               FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'message' => 'Email address not found.'];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        // Check if already verified
        if ($user['email_verified'] == 1) {
            return ['success' => false, 'message' => 'This email is already verified. You can log in now.'];
        }
        
        // Generate new token
        $newToken = $this->generateToken();
        $this->storeVerificationToken($user['id'], $newToken);
        
        // Send email
        $fullName = formatStudentName($user['first_name'], $user['middle_name'], $user['last_name']);
        $emailSent = $this->sendVerificationEmail($email, $fullName, $newToken);
        
        if ($emailSent) {
            return ['success' => true, 'message' => 'Verification email resent successfully! Please check your inbox.'];
        }
        
        return ['success' => false, 'message' => 'Failed to send verification email. Please try again later.'];
    }
}