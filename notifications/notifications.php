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
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .notification-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
        }
        
        .notification-card-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .notification-card-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .notification-card-content {
            flex: 1;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
    </style>
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">🔔</div>
                <div class="brand-text">
                    <h1>Notifications</h1>
                    <p>Stay updated with system activities</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
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
                            ✓ Mark All as Read
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
                        🗳️ Votes
                    </a>
                    <a href="?filter=milestone" class="filter-tab <?php echo $filter === 'milestone' ? 'active' : ''; ?>">
                        🎯 Milestones
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
                            if ($notification['type'] == 'vote') echo '🗳️';
                            elseif ($notification['type'] == 'milestone') echo '🎯';
                            else echo '📢';
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
                        <div class="notification-card-actions">
                            <?php if (!$notification['is_read']): ?>
                                <a href="?mark_read=<?php echo $notification['id']; ?>&filter=<?php echo $filter; ?>" 
                                   class="btn-modern btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                    ✓ Mark Read
                                </a>
                            <?php else: ?>
                                <span style="color: #10b981; font-weight: 600; font-size: 0.875rem;">✓ Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="modern-card">
                <div class="card-body">
                    <div style="text-align: center; padding: 4rem 2rem; color: #6b7280;">
                        <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">
                            <?php 
                            if ($filter === 'unread') echo '✅';
                            elseif ($filter === 'vote') echo '🗳️';
                            elseif ($filter === 'milestone') echo '🎯';
                            else echo '🔕';
                            ?>
                        </div>
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