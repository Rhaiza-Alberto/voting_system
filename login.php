<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? 'admin/admin_dashboard.php' : 'students/student_dashboard.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id']);
    $password = $_POST['password'];
    
    if (empty($studentId) || empty($password)) {
        $error = 'Please enter both Student ID and Password';
    } else {
        $conn = getDBConnection();
        
        // Get user with computed full name
        $stmt = $conn->prepare("SELECT id, student_id, first_name, middle_name, last_name, role, password 
                               FROM users WHERE student_id = ?");
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
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
            } else {
                $error = 'Invalid Student ID or Password';
            }
        } else {
            $error = 'Invalid Student ID or Password';
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
    <link rel="stylesheet" href="style.css">
    <title>Login - Voting System</title>
</head>
<body class="login-page">
    <div class="login-container">
        <h2>🗳️ Login</h2>
        <p class="subtitle">Welcome back! Please login to your account</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" required placeholder="Enter your Student ID">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your Password">
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>