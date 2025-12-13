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
     * Send verification email
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
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                             color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
                    .button { display: inline-block; padding: 15px 30px; background: #10b981; 
                             color: white; text-decoration: none; border-radius: 8px; 
                             font-weight: bold; margin: 20px 0; }
                    .footer { background: #e2e8f0; padding: 20px; text-align: center; 
                             font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
                    .warning { background: #fef3c7; border-left: 4px solid #f59e0b; 
                              padding: 15px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>WMSU Voting System</h1>
                        <p>Email Verification Required</p>
                    </div>
                    
                    <div class='content'>
                        <h2>Hello, " . htmlspecialchars($fullName) . "!</h2>
                        
                        <p>Thank you for registering with the WMSU Classroom Voting System. 
                        To complete your registration and activate your account, please verify 
                        your email address by clicking the button below:</p>
                        
                        <center>
                            <a href='" . htmlspecialchars($verificationLink) . "' class='button'>
                                Verify My Email Address
                            </a>
                        </center>
                        
                        <p>Or copy and paste this link into your browser:</p>
                        <p style='background: #fff; padding: 10px; border: 1px solid #ddd; 
                                  word-break: break-all; font-size: 12px;'>
                            " . htmlspecialchars($verificationLink) . "
                        </p>
                        
                        <div class='warning'>
                            <strong>Important:</strong> This verification link will expire in 24 hours. 
                            If you did not create an account, please ignore this email.
                        </div>
                        
                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Click the verification link above</li>
                            <li>Your account will be activated</li>
                            <li>You can then log in and participate in voting</li>
                        </ul>
                    </div>
                    
                    <div class='footer'>
                        <p>This is an automated message from WMSU Classroom Voting System.</p>
                        <p>If you need assistance, please contact your system administrator.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Verification email error: " . $e->getMessage());
            return false;
        }
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