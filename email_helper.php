<?php
/**
 * Email Helper - Email Notification System using PHPMailer
 * Supports Gmail SMTP
 * Manual PHPMailer loading (no Composer required)
 */

// Manual require for PHPMailer (no Composer)
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $fromEmail = "rhaizaalberto931@gmail.com";
    private $fromName = "Classroom Voting System";
    
    // Gmail SMTP Configuration
    private $smtpHost = "smtp.gmail.com";
    private $smtpPort = 587;
    private $smtpUsername = "rhaizaalberto931@gmail.com";
    private $smtpPassword = "zcdj efmp zshz iexw"; // ADD YOUR APP PASSWORD HERE
    
    /**
     * Get the login URL
     */
    private function getLoginUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
        
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir !== '/' && $scriptDir !== '') {
            $baseUrl .= $scriptDir;
        }
        
        // Add trailing slash if not present
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        
        return $baseUrl . 'login.php';
    }
    
    /**
     * Get the student dashboard URL
     */
    private function getDashboardUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
        
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir !== '/' && $scriptDir !== '') {
            $baseUrl .= $scriptDir;
        }
        
        // Add trailing slash if not present
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
        
        return $baseUrl . 'student_dashboard.php';
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     */
    private function configurePHPMailer($mail) {
        $mail->isSMTP();
        $mail->Host = $this->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtpUsername;
        $mail->Password = $this->smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $this->smtpPort;
        $mail->setFrom($this->fromEmail, $this->fromName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
    }
    
    /**
     * Send welcome email to newly registered student
     */
    public function sendWelcomeEmail($toEmail, $studentName, $studentId, $temporaryPassword) {
        try {
            $mail = new PHPMailer(true);
            $this->configurePHPMailer($mail);
            
            $mail->addAddress($toEmail, $studentName);
            $mail->Subject = "Welcome to Classroom Voting System";
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border-radius: 0 0 10px 10px; }
                    .welcome-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .credentials-box { background: white; border: 2px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 8px; }
                    .credential-item { margin: 10px 0; padding: 10px; background: #f7fafc; border-radius: 5px; }
                    .credential-label { font-weight: bold; color: #10b981; display: block; margin-bottom: 5px; }
                    .credential-value { font-size: 1.2em; color: #2d3748; font-family: monospace; }
                    .important-note { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .footer { text-align: center; padding: 20px; color: #718096; font-size: 0.9em; }
                    .button { background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1> Welcome to the Voting System!</h1>
                    </div>
                    <div class='content'>
                        <div class='welcome-box'>
                            <p style='margin: 0; color: #065f46; font-size: 1.1em;'><strong>Hello " . htmlspecialchars($studentName) . "!</strong></p>
                            <p style='margin: 10px 0 0 0; color: #065f46;'>Your student account has been successfully created by the administrator.</p>
                        </div>
                        
                        <p><strong>Account Details:</strong></p>
                        
                        <div class='credentials-box'>
                            <div class='credential-item'>
                                <span class='credential-label'> Student ID:</span>
                                <span class='credential-value'>" . htmlspecialchars($studentId) . "</span>
                            </div>
                            <div class='credential-item'>
                                <span class='credential-label'> Password:</span>
                                <span class='credential-value'>" . htmlspecialchars($temporaryPassword) . "</span>
                            </div>
                        </div>
                        
                        <div class='important-note'>
                            <p style='margin: 0; color: #92400e;'><strong> Important Security Notice:</strong></p>
                            <ul style='margin: 10px 0 0 20px; color: #92400e;'>
                                <li>This is your temporary password</li>
                                <li>Please change it after your first login</li>
                                <li>Keep your password secure and confidential</li>
                                <li>Never share your login credentials with anyone</li>
                            </ul>
                        </div>
                        
                        <div style='text-align: center;'>
                            <a href='" . $this->getLoginUrl() . "' class='button'> Login Now</a>
                        </div>
                        
                        <p style='margin-top: 30px;'><strong>What you can do:</strong></p>
                        <ul style='color: #4a5568; margin-left: 20px;'>
                            <li>Vote in active elections</li>
                            <li>View real-time election results</li>
                            <li>Check your candidacy status if nominated</li>
                            <li>Receive vote confirmations</li>
                        </ul>
                        
                        <p style='color: #718096; font-size: 0.9em; margin-top: 30px;'>If you have any questions or issues logging in, please contact your administrator.</p>
                    </div>
                    <div class='footer'>
                        <p>Classroom Voting System</p>
                        <p>This is an automated message. Please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send voting confirmation email to student
     */
    public function sendVotingConfirmationEmail($toEmail, $studentName, $positionName) {
        try {
            $mail = new PHPMailer(true);
            $this->configurePHPMailer($mail);
            
            $mail->addAddress($toEmail, $studentName);
            $mail->Subject = "Vote Confirmation - " . $positionName;
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border-radius: 0 0 10px 10px; }
                    .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    .footer { text-align: center; padding: 20px; color: #718096; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1> Vote Confirmed!</h1>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
                        
                        <div class='success-box'>
                            <p style='margin: 0; color: #065f46;'><strong>Your vote has been successfully recorded!</strong></p>
                        </div>
                        
                        <p><strong>Position:</strong> " . htmlspecialchars($positionName) . "</p>
                        <p><strong>Date & Time:</strong> " . date('F d, Y h:i A') . "</p>
                        
                        <p style='margin-top: 20px;'>Thank you for participating in the election. Your vote is confidential and has been securely stored.</p>
                        
                        <div style='text-align: center; margin-top: 30px;'>
                            <a href='" . $this->getDashboardUrl() . "' style='background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'> View Results Dashboard</a>
                        </div>
                        
                        <p style='color: #718096; font-size: 0.9em; margin-top: 30px;'>If you did not cast this vote, please contact the administrator immediately.</p>
                    </div>
                    <div class='footer'>
                        <p>Classroom Voting System</p>
                        <p>This is an automated message. Please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}
?>