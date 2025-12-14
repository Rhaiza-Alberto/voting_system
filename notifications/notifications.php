<?php
require_once '../config.php';
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

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$notifications = $notif->getNotifications($userId, 100);
$unreadCount = $notif->getUnreadCount($userId);

// Apply filter
if ($filter === 'unread') {
    $notifications = array_filter($notifications, function($n) {
        return !$n['is_read'];
    });
} elseif ($filter === 'vote') {
    $notifications = array_filter($notifications, function($n) {
        return $n['type'] === 'vote';
    });
} elseif ($filter === 'milestone') {
    $notifications = array_filter($notifications, function($n) {
        return $n['type'] === 'milestone';
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - VoteSystem Pro</title>
    <link rel="stylesheet" href="../assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            padding-bottom: 2rem;
        }

        .modern-navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-text h1 {
            font-size: 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-text p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
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

        .modern-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-body {
            padding: 2rem;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
            position: relative;
        }
        
        .notification-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
        }

        .notification-card.unread::before {
            content: '';
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .notification-card-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .notification-card-icon {
            font-size: 1rem;
            flex-shrink: 0;
            font-weight: 700;
            color: #6b7280;
        }
        
        .notification-card-content {
            flex: 1;
            padding-right: 2rem;
        }
        
        .notification-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        
        .notification-card-message {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .notification-card-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #9ca3af;
        }
        
        .notification-card-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .notification-card:hover .notification-card-actions {
            opacity: 1;
        }

        .notification-card.unread .notification-card-actions {
            opacity: 1;
        }

        .btn-mark-read {
            padding: 0.5rem 1rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-mark-read:hover {
            background: #059669;
            transform: scale(1.05);
        }

        .read-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            color: #10b981;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            background: #d1fae5;
            border-radius: 6px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6b7280;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin-bottom: -2px;
        }
        
        .filter-tab:hover {
            color: #10b981;
            background: #f9fafb;
        }
        
        .filter-tab.active {
            color: #10b981;
            border-bottom-color: #10b981;
        }
        
        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .type-vote {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .type-milestone {
            background: #fef3c7;
            color: #92400e;
        }
        
        .type-system {
            background: #e9d5ff;
            color: #6b21a8;
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-text">
                    <h1>Notifications</h1>
                    <p>Stay updated with system activities</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <!-- Header Stats -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-body">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">
                            All Notifications
                        </h2>
                        <p style="color: #6b7280;">
                            <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount != 1 ? 's' : ''; ?>
                        </p>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <a href="?mark_all_read=1" class="btn-modern btn-primary">
                            Mark All as Read
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-body" style="padding-bottom: 0;">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        Unread (<?php echo $unreadCount; ?>)
                    </a>
                    <a href="?filter=vote" class="filter-tab <?php echo $filter === 'vote' ? 'active' : ''; ?>">
                        Votes
                    </a>
                    <a href="?filter=milestone" class="filter-tab <?php echo $filter === 'milestone' ? 'active' : ''; ?>">
                        Milestones
                    </a>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?> fade-in">
                    <div class="notification-card-header">
                        <span class="notification-card-icon">
                            <?php
                            if ($notification['type'] == 'vote') echo '[V]';
                            elseif ($notification['type'] == 'milestone') echo '[M]';
                            else echo '[S]';
                            ?>
                        </span>
                        <div class="notification-card-content">
                            <div class="notification-card-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-card-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-card-meta">
                                <span>
                                    <?php
                                    $time = strtotime($notification['created_at']);
                                    $diff = time() - $time;
                                    
                                    if ($diff < 60) {
                                        echo "Just now";
                                    } elseif ($diff < 3600) {
                                        $mins = floor($diff / 60);
                                        echo $mins . " minute" . ($mins != 1 ? 's' : '') . " ago";
                                    } elseif ($diff < 86400) {
                                        $hours = floor($diff / 3600);
                                        echo $hours . " hour" . ($hours != 1 ? 's' : '') . " ago";
                                    } else {
                                        echo date('M d, Y h:i A', $time);
                                    }
                                    ?>
                                </span>
                                <span>•</span>
                                <span class="type-badge type-<?php echo $notification['type']; ?>">
                                    <?php echo ucfirst($notification['type']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mark as Read button - Now in top right corner, appears on hover -->
                    <div class="notification-card-actions">
                        <?php if (!$notification['is_read']): ?>
                            <a href="?mark_read=<?php echo $notification['id']; ?>&filter=<?php echo $filter; ?>" 
                               class="btn-mark-read">
                                ✓ Mark Read
                            </a>
                        <?php else: ?>
                            <span class="read-indicator">✓ Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="modern-card">
                <div class="card-body">
                    <div style="text-align: center; padding: 4rem 2rem; color: #6b7280;">
                        <h3 style="font-size: 1.5rem; color: #111827; margin-bottom: 0.5rem;">
                            <?php 
                            if ($filter === 'unread') echo "You're All Caught Up!";
                            else echo "No " . ucfirst($filter) . " Notifications";
                            ?>
                        </h3>
                        <p>
                            <?php 
                            if ($filter === 'unread') {
                                echo "All notifications have been read.";
                            } else {
                                echo "Notifications will appear here when there's activity.";
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh every 30 seconds if there are unread notifications
        <?php if ($unreadCount > 0): ?>
        setTimeout(() => location.reload(), 30000);
        <?php endif; ?>
    </script>
</body>
</html>