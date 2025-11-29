<?php
/**
 * One-time setup script to create notifications table
 * Run this once to set up the notification system
 */

require_once 'config.php';
require_once 'notification_helper.php';

// Must be admin to run setup
requireAdmin();

$notif = new NotificationHelper();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Notifications</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            padding: 40px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #10b981;
            margin-bottom: 20px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .btn {
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1> Notification System Setup</h1>";

try {
    $notif->setupNotificationsTable();
    echo "<div class='success'>
             <strong>Success!</strong> Notification system has been set up successfully.
            <br><br>
            The following features are now active:
            <ul style='margin: 10px 0 0 20px;'>
                <li>Bell icon notifications for admins</li>
                <li>Email confirmations for student votes</li>
                <li>Welcome emails for new students</li>
                <li>Milestone alerts for voter turnout</li>
            </ul>
          </div>";
    
    echo "<p><strong>Next steps:</strong></p>
          <ol style='margin-left: 20px;'>
              <li>Configure your mail server settings in php.ini (if not already done)</li>
              <li>Update the 'fromEmail' in email_helper.php with your actual email</li>
              <li>Update the login URL in email_helper.php getLoginUrl() function</li>
              <li>Add the bell icon widget to admin_dashboard.php navbar</li>
              <li>Test by adding a student or having a student vote</li>
          </ol>";
          
    echo "<a href='admin_dashboard.php' class='btn'>Go to Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div style='background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px;'>
             <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
}

echo "</div>
</body>
</html>";
?>