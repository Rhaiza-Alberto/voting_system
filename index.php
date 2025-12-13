<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/admin_dashboard.php');
    } else {
        header('Location: students/student_dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU Classroom Voting System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #10b981;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1em;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            flex-direction: column;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        .btn-secondary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        
        .icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .info-text {
            background: #dbeafe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 0.95em;
            color: #1e40af;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px;
            }
            
            h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🗳️</div>
        <h1>WMSU Voting System</h1>
        <p class="subtitle">Secure, Real-Time Student Elections</p>
        
        <div class="info-text">
            <strong>🎓 For WMSU Students:</strong> Register with your official WMSU email (@wmsu.edu.ph) 
            to participate in voting sessions.
        </div>
        
        <div class="button-group">
            <a href="login.php" class="btn btn-primary">🔑 Login to Vote</a>
            <a href="register.php" class="btn btn-secondary">📝 Register New Account</a>
        </div>
    </div>
</body>
</html>