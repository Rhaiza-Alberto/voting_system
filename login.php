<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/admin_dashboard.php' : 'students/student_dashboard.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']); // Can be student_id or email
    $password = $_POST['password'];
    
    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both Student ID/Email and Password';
    } else {
        $conn = getDBConnection();
        
        // Get user with computed full name - check both student_id and email
        $stmt = $conn->prepare("SELECT id, student_id, first_name, middle_name, last_name, 
                               email, role, password, email_verified, is_active 
                               FROM users WHERE student_id = ? OR email = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['is_active'] == 0) {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                }
                // Check if email is verified (only for self-registered students)
                elseif ($user['email_verified'] == 0 && $user['role'] !== 'admin') {
                    $error = 'Your email has not been verified yet. Please check your WMSU email (' . 
                             htmlspecialchars($user['email']) . ') for the verification link.';
                } else {
                    // Compute full name
                    $fullName = formatStudentName($user['first_name'], $user['middle_name'], $user['last_name']);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: admin/admin_dashboard.php');
                    } else {
                        header('Location: students/student_dashboard.php');
                    }
                    exit();
                }
            } else {
                $error = 'Invalid Student ID/Email or Password';
            }
        } else {
            $error = 'Invalid Student ID/Email or Password';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Login - WMSU Voting System</title>
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
        
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
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
            margin-bottom: 2rem;
            font-size: 1rem;
            text-align: center;
        }
        
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #ef4444;
            font-size: 0.9375rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
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
        
        .input-hint {
            color: #6b7280;
            font-size: 0.8125rem;
            margin-top: 0.375rem;
            display: block;
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
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .register-link p {
            color: #6b7280;
            font-size: 0.9375rem;
        }
        
        .register-link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
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
        
        @media (max-width: 600px) {
            .login-container {
                padding: 2rem;
            }
            
            h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Welcome Back</h2>
        <p class="subtitle">Please login to your account</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="identifier">Student ID or Email</label>
                <input type="text" id="identifier" name="identifier" required 
                       placeholder="Enter your Student ID or WMSU Email" autocomplete="username">
                <span class="input-hint">You can use either your Student ID or WMSU email address</span>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter your Password" autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register with WMSU Email</a></p>
        </div>
    </div>
</body>
</html>