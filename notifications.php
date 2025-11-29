<?php
require_once 'config.php';
require_once 'notification_helper.php';
requireAdmin();

$notif = new NotificationHelper();
$userId = $_SESSION['user_id'];

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notif->markAsRead($_GET['mark_read']);
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $notif->markAllAsRead($userId);
    header('Location: notifications.php');
    exit();
}

$notifications = $notif->getNotifications($userId, 50);
$unreadCount = $notif->getUnreadCount($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5em;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .header-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
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
        }
        
        .notification-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .notification-item.unread {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }
        
        .notification-icon {
            font-size: 2em;
            width: 50px;
            text-align: center;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1.1em;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: #718096;
            margin-bottom: 8px;
        }
        
        .notification-time {
            font-size: 0.85em;
            color: #a0aec0;
        }
        
        .notification-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            background: white;
            padding: 60px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-actions {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Notifications</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="header-actions">
            <h2 style="color: #2d3748;">
                <?php echo $unreadCount; ?> Unread Notification<?php echo $unreadCount != 1 ? 's' : ''; ?>
            </h2>
            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-primary">✓ Mark All as Read</a>
            <?php endif; ?>
        </div>
        
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                    <div class="notification-icon">
                        <?php
                        if ($notification['type'] == 'vote') echo '🗳️';
                        elseif ($notification['type'] == 'milestone') echo '🎯';
                        else echo '🔔';
                        ?>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time">
                            <?php
                            $time = strtotime($notification['created_at']);
                            $diff = time() - $time;
                            
                            if ($diff < 60) {
                                echo "Just now";
                            } elseif ($diff < 3600) {
                                echo floor($diff / 60) . " minutes ago";
                            } elseif ($diff < 86400) {
                                echo floor($diff / 3600) . " hours ago";
                            } else {
                                echo date('M d, Y h:i A', $time);
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                            <a href="?mark_read=<?php echo $notification['id']; ?>" 
                               class="btn btn-primary" 
                               style="padding: 6px 12px; font-size: 0.85em;">
                                ✓ Mark Read
                            </a>
                        <?php else: ?>
                            <span style="color: #10b981; font-size: 0.9em;">✓ Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <h2 style="color: #10b981; margin-bottom: 10px;">No Notifications</h2>
                <p style="color: #718096;">You're all caught up! Notifications will appear here when students vote.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>