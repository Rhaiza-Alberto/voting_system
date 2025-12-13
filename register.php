<?php
// register.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper/verification_helper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/admin_dashboard.php' : 'students/student_dashboard.php'));
    exit();
}

$verification = new VerificationHelper();
$message = '';
$messageType = '';
$showResendOption = false;
$registeredEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id']);
    $firstName = trim($_POST['first_name']);
    $middleName = trim($_POST['middle_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $message = 'Student ID, First Name, Last Name, Email, and Password are required!';
        $messageType = 'error';
    } elseif (!$verification->isValidWMSUEmail($email)) {
        $message = 'Please use your official WMSU email address (@wmsu.edu.ph)';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match!';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long!';
        $messageType = 'error';
    } else {
        $conn = getDBConnection();
        
        // Check if student ID already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
        $checkStmt->bind_param("s", $studentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $message = 'Student ID already registered!';
            $messageType = 'error';
            $checkStmt->close();
        } else {
            $checkStmt->close();
            
            // Check if email already exists
            $emailStmt = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ?");
            $emailStmt->bind_param("s", $email);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                $existingUser = $emailResult->fetch_assoc();
                if ($existingUser['email_verified'] == 0) {
                    $message = 'This email is already registered but not verified. Check your email or click below to resend verification.';
                    $messageType = 'warning';
                    $showResendOption = true;
                    $registeredEmail = $email;
                } else {
                    $message = 'Email address already registered!';
                    $messageType = 'error';
                }
                $emailStmt->close();
            } else {
                $emailStmt->close();
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user (unverified)
                $insertStmt = $conn->prepare("INSERT INTO users 
                    (student_id, first_name, middle_name, last_name, email, password, 
                     role, email_verified, registration_method) 
                    VALUES (?, ?, ?, ?, ?, ?, 'student', 0, 'self_registered')");
                $insertStmt->bind_param("ssssss", $studentId, $firstName, $middleName, 
                                       $lastName, $email, $hashedPassword);
                
                if ($insertStmt->execute()) {
                    $userId = $insertStmt->insert_id;
                    $insertStmt->close();
                    
                    // Generate and send verification email
                    $token = $verification->generateToken();
                    $verification->storeVerificationToken($userId, $token);
                    
                    $fullName = formatStudentName($firstName, $middleName, $lastName);
                    $emailSent = $verification->sendVerificationEmail($email, $fullName, $token);
                    
                    if ($emailSent) {
                        $message = '✓ Registration successful! Please check your WMSU email (' . htmlspecialchars($email) . ') for a verification link. The link will expire in 24 hours.';
                        $messageType = 'success';
                        $showResendOption = true;
                        $registeredEmail = $email;
                    } else {
                        $message = '⚠ Registration successful, but we could not send the verification email. Please contact the administrator.';
                        $messageType = 'warning';
                    }
                } else {
                    $message = 'Registration failed. Please try again.';
                    $messageType = 'error';
                    $insertStmt->close();
                }
            }
        }
        
        $conn->close();
    }
}

// Handle resend verification
if (isset($_GET['resend']) && !empty($_GET['email'])) {
    $emailToResend = trim($_GET['email']);
    $result = $verification->resendVerification($emailToResend);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Student Registration - WMSU Voting System</title>
    <style>
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .info-box strong {
            color: #1e40af;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .password-requirements {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        .resend-link {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        .resend-link:hover {
            background: #d97706;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container" style="max-width: 600px;">
        <h2>🎓 Student Registration</h2>
        <p class="subtitle">Register with your WMSU email to participate in voting</p>
        
        <div class="info-box">
            <strong>📧 WMSU Email Required:</strong> You must use your official WMSU email address 
            (@wmsu.edu.ph) to register. A verification link will be sent to your email.
        </div>
        
        <?php if ($message): ?>
            <div class="<?php echo $messageType; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $message; ?>
                
                <?php if ($showResendOption && $registeredEmail): ?>
                    <a href="?resend=1&email=<?php echo urlencode($registeredEmail); ?>" 
                       class="resend-link">
                        📧 Resend Verification Email
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="student_id">Student ID <span style="color: #e53e3e;">*</span></label>
                    <input type="text" id="student_id" name="student_id" required 
                           placeholder="e.g., 2024001" value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name <span style="color: #e53e3e;">*</span></label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="e.g., Juan" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" 
                           placeholder="Optional" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color: #e53e3e;">*</span></label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="e.g., Dela Cruz" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group full-width">
                    <label for="email">WMSU Email Address <span style="color: #e53e3e;">*</span></label>
                    <input type="email" id="email" name="email" required 
                           placeholder="yourname@wmsu.edu.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <p class="password-requirements">Must be your official WMSU email (@wmsu.edu.ph)</p>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span style="color: #e53e3e;">*</span></label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Create a password">
                    <p class="password-requirements">Minimum 6 characters</p>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color: #e53e3e;">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password">
                </div>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">
                ✓ Register & Send Verification Email
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <p>Already have an account? <a href="login.php" style="color: #10b981; font-weight: 600;">Login here</a></p>
        </div>
    </div>
</body>
</html>