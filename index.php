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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>WMSU Voting System</title>
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
        
        .container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.5s ease;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2.5rem;
            color: white;
            font-weight: 700;
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 2.5rem;
            font-size: 1.125rem;
            font-weight: 500;
        }
        
        .info-card {
            background: #f0fdf4;
            border: 2px solid #d1fae5;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .info-card-title {
            color: #065f46;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .info-card-text {
            color: #047857;
            font-size: 0.9375rem;
            line-height: 1.6;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            flex-direction: column;
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1.125rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }
        
        .btn-secondary {
            background: white;
            color: #1f2937;
            border: 2px solid #d1fae5;
        }
        
        .btn-secondary:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
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
            .container {
                padding: 2rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WMSU Voting System</h1>
        <p class="subtitle">Secure, Real-Time Student Elections</p>
        
        <div class="info-card">
            <div class="info-card-title">For WMSU Students</div>
            <div class="info-card-text">
                Register with your official WMSU email (@wmsu.edu.ph) to participate in voting sessions.
            </div>
        </div>
        
        <div class="button-group">
            <a href="login.php" class="btn btn-primary">Login to Vote</a>
            <a href="register.php" class="btn btn-secondary">Register New Account</a>
        </div>
    </div>
</body>
</html>