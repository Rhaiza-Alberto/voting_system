<?php
/**
 * Notification Bell Widget
 * Include this in admin pages to show notification bell
 * Usage: include 'notification_widget.php';
 */

if (!isset($notificationHelper)) {
    require_once 'notification_helper.php';
    $notificationHelper = new NotificationHelper();
}

$unreadCount = $notificationHelper->getUnreadCount($_SESSION['user_id']);
?>

<style>
.notification-bell {
    position: relative;
    display: inline-block;
    margin-left: 15px;
    cursor: pointer;
}

.notification-bell-icon {
    font-size: 1.3em;
    color: white;
    font-weight: 600;
    transition: all 0.3s;
    padding: 6px 12px;
    background: rgba(255,255,255,0.15);
    border-radius: 6px;
}

.notification-bell:hover .notification-bell-icon {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 0.7em;
    font-weight: bold;
    padding: 3px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
    animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 10px;
}

.notification-bell:hover .notification-dropdown {
    display: block;
}

.notification-dropdown-header {
    padding: 15px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f7fafc;
    border-radius: 10px 10px 0 0;
}

.notification-dropdown-header h3 {
    color: #2d3748;
    font-size: 1em;
    margin: 0;
}

.notification-dropdown-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s;
}

.notification-dropdown-item:hover {
    background: #f7fafc;
}

.notification-dropdown-item.unread {
    background: #f0fdf4;
}

.notification-dropdown-title {
    font-weight: 600;
    color: #2d3748;
    font-size: 0.9em;
    margin-bottom: 3px;
}

.notification-dropdown-message {
    color: #718096;
    font-size: 0.85em;
    margin-bottom: 3px;
}

.notification-dropdown-time {
    color: #a0aec0;
    font-size: 0.75em;
}

.notification-dropdown-footer {
    padding: 12px;
    text-align: center;
    border-top: 2px solid #e2e8f0;
    background: #f7fafc;
    border-radius: 0 0 10px 10px;
}

.notification-dropdown-footer a {
    color: #10b981;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9em;
}

.notification-dropdown-footer a:hover {
    color: #059669;
}

.no-notifications {
    padding: 40px 20px;
    text-align: center;
    color: #a0aec0;
}
</style>

<a href="notifications.php" class="notification-bell">
    <span class="notification-bell-icon">Notifications</span>
    <?php if ($unreadCount > 0): ?>
        <span class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
    <?php endif; ?>
    
    <div class="notification-dropdown">
        <div class="notification-dropdown-header">
            <h3>Notifications</h3>
            <span style="color: #718096; font-size: 0.85em;"><?php echo $unreadCount; ?> new</span>
        </div>
        
        <?php
        $recentNotifications = $notificationHelper->getNotifications($_SESSION['user_id'], 5);
        
        if (count($recentNotifications) > 0):
            foreach ($recentNotifications as $notif):
        ?>
            <div class="notification-dropdown-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                <div class="notification-dropdown-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                <div class="notification-dropdown-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                <div class="notification-dropdown-time">
                    <?php
                    $time = strtotime($notif['created_at']);
                    $diff = time() - $time;
                    
                    if ($diff < 60) echo "Just now";
                    elseif ($diff < 3600) echo floor($diff / 60) . "m ago";
                    elseif ($diff < 86400) echo floor($diff / 3600) . "h ago";
                    else echo date('M d', $time);
                    ?>
                </div>
            </div>
        <?php
            endforeach;
        else:
        ?>
            <div class="no-notifications">
                No notifications yet
            </div>
        <?php endif; ?>
        
        <div class="notification-dropdown-footer">
            <a href="notifications.php">View All Notifications</a>
        </div>
    </div>
</a>