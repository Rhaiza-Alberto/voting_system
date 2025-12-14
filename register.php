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

// Function to validate student ID format
function isValidStudentIdFormat($studentId) {
    // Must be exactly 9 digits
    return preg_match('/^[0-9]{9}$/', $studentId);
}

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
    } elseif (!isValidStudentIdFormat($studentId)) {
        $message = 'Invalid Student ID format! Must be exactly 9 digits (e.g., 202403480)';
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
                        $message = 'Registration successful! Please check your WMSU email (' . htmlspecialchars($email) . ') for a verification link. The link will expire in 24 hours.';
                        $messageType = 'success';
                        $showResendOption = true;
                        $registeredEmail = $email;
                    } else {
                        $message = 'Registration successful, but we could not send the verification email. Please contact the administrator.';
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
    <title>Student Registration - WMSU Voting System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .register-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 650px;
            width: 100%;
            animation: fadeIn 0.5s ease;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            font-weight: 700;
        }
        
        h2 {
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            text-align: center;
        }
        
        .info-box {
            background: #dbeafe;
            border: 2px solid #93c5fd;
            border-left: 4px solid #3b82f6;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
        }
        
        .info-box-title {
            color: #1e40af;
            font-weight: 700;
            font-size: 0.9375rem;
            margin-bottom: 0.5rem;
        }
        
        .info-box-text {
            color: #1e3a8a;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .alert {
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            font-size: 0.9375rem;
        }
        
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }
        
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-color: #ef4444;
        }
        
        .alert.warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #f59e0b;
        }
        
        .resend-link {
            display: inline-block;
            margin-top: 0.75rem;
            padding: 0.625rem 1.25rem;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .resend-link:hover {
            background: #d97706;
            transform: translateY(-1px);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }
        
        .required {
            color: #ef4444;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-group input.invalid {
            border-color: #ef4444;
        }
        
        .form-group input.valid {
            border-color: #10b981;
        }
        
        .field-hint {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.375rem;
            line-height: 1.4;
        }
        
        .field-hint.error {
            color: #ef4444;
            font-weight: 500;
        }
        
        .field-hint.success {
            color: #10b981;
            font-weight: 500;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            margin-top: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }
        
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .login-link p {
            color: #6b7280;
            font-size: 0.9375rem;
        }
        
        .login-link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Student Registration</h2>
        <p class="subtitle">Register with your WMSU email to participate in voting</p>
        
        <div class="info-box">
            <div class="info-box-title">WMSU Email Required</div>
            <div class="info-box-text">
                You must use your official WMSU email address (@wmsu.edu.ph) to register. A verification link will be sent to your email.
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
                
                <?php if ($showResendOption && $registeredEmail): ?>
                    <a href="?resend=1&email=<?php echo urlencode($registeredEmail); ?>" 
                       class="resend-link">
                        Resend Verification Email
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registrationForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="student_id">Student ID <span class="required">*</span></label>
                    <input type="text" id="student_id" name="student_id" required 
                           placeholder="e.g.,202401234" 
                           maxlength="9"
                           pattern="[0-9]{9}"
                           inputmode="numeric"
                           value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>">
                    <p class="field-hint" id="studentIdHint">Must be exactly 9 digits (e.g., 202403480)</p>
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="e.g., Juan" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" 
                           placeholder="Optional" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="e.g., Dela Cruz" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group full-width">
                    <label for="email">WMSU Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required 
                           placeholder="e.g.,ae20241234@wmsu.edu.ph" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <p class="field-hint">Must be your official WMSU email (@wmsu.edu.ph)</p>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Create a password">
                    <p class="field-hint" id="passwordHint">Minimum 6 characters</p>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password">
                    <p class="field-hint" id="confirmPasswordHint"></p>
                </div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                Register & Send Verification Email
            </button>
        </form>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script>
        // Student ID validation
        const studentIdInput = document.getElementById('student_id');
        const studentIdHint = document.getElementById('studentIdHint');
        
        studentIdInput.addEventListener('input', function(e) {
            // Remove any non-numeric characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 9 characters
            if (this.value.length > 9) {
                this.value = this.value.slice(0, 9);
            }
            
            // Visual feedback
            if (this.value.length === 0) {
                this.classList.remove('valid', 'invalid');
                studentIdHint.classList.remove('error', 'success');
                studentIdHint.textContent = 'Must be exactly 9 digits (e.g., 202403480)';
            } else if (this.value.length === 9) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                studentIdHint.classList.remove('error');
                studentIdHint.classList.add('success');
                studentIdHint.textContent = 'Valid student ID format';
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
                studentIdHint.classList.remove('success');
                studentIdHint.classList.add('error');
                studentIdHint.textContent = `Please enter ${9 - this.value.length} more digit(s)`;
            }
        });
        
        // Password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordHint = document.getElementById('passwordHint');
        const confirmPasswordHint = document.getElementById('confirmPasswordHint');
        
        passwordInput.addEventListener('input', function() {
            if (this.value.length === 0) {
                this.classList.remove('valid', 'invalid');
                passwordHint.classList.remove('error', 'success');
                passwordHint.textContent = 'Minimum 6 characters';
            } else if (this.value.length >= 6) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                passwordHint.classList.remove('error');
                passwordHint.classList.add('success');
                passwordHint.textContent = ' Password meets requirements';
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
                passwordHint.classList.remove('success');
                passwordHint.classList.add('error');
                passwordHint.textContent = `Need ${6 - this.value.length} more character(s)`;
            }
            
            // Check password match if confirm field has value
            if (confirmPasswordInput.value) {
                checkPasswordMatch();
            }
        });
        
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            if (confirmPasswordInput.value.length === 0) {
                confirmPasswordInput.classList.remove('valid', 'invalid');
                confirmPasswordHint.classList.remove('error', 'success');
                confirmPasswordHint.textContent = '';
            } else if (passwordInput.value === confirmPasswordInput.value) {
                confirmPasswordInput.classList.remove('invalid');
                confirmPasswordInput.classList.add('valid');
                confirmPasswordHint.classList.remove('error');
                confirmPasswordHint.classList.add('success');
                confirmPasswordHint.textContent = 'Passwords match';
            } else {
                confirmPasswordInput.classList.remove('valid');
                confirmPasswordInput.classList.add('invalid');
                confirmPasswordHint.classList.remove('success');
                confirmPasswordHint.classList.add('error');
                confirmPasswordHint.textContent = ' Passwords do not match';
            }
        }
        
        // Form validation before submit
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const studentId = studentIdInput.value;
            
            // Final validation
            if (!/^[0-9]{9}$/.test(studentId)) {
                e.preventDefault();
                alert('Please enter a valid 9-digit Student ID (e.g., 202401234)');
                studentIdInput.focus();
                return false;
            }
            
            if (passwordInput.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                passwordInput.focus();
                return false;
            }
            
            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('Passwords do not match');
                confirmPasswordInput.focus();
                return false;
            }
        });
        
        // Prevent paste of non-numeric characters in student ID
        studentIdInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const numericOnly = pastedText.replace(/[^0-9]/g, '').slice(0, 9);
            this.value = numericOnly;
            this.dispatchEvent(new Event('input'));
        });
    </script>
</body>
</html>