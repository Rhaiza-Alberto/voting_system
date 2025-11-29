<?php
/**
 * Modern Notification Bell Widget
 * Enhanced version with modern UI
 */

if (!isset($notificationHelper)) {
    require_once 'notification_helper.php';
    $notificationHelper = new NotificationHelper();
}

$unreadCount = $notificationHelper->getUnreadCount($_SESSION['user_id']);
$recentNotifications = $notificationHelper->getNotifications($_SESSION['user_id'], 5);
?>

<div class="notification-bell-container">
    <button class="notification-bell-button" onclick="window.location.href='notifications.php'">
        <span>🔔</span>
        <span>Notifications</span>
    </button>
    
    <?php if ($unreadCount > 0): ?>
        <span class="notification-count-badge">
            <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
        </span>
    <?php endif; ?>
    
    <div class="notification-dropdown">
        <div class="notification-dropdown-header">
            <h3>🔔 Notifications</h3>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-unread-count"><?php echo $unreadCount; ?> new</span>
                    <button class="mark-all-read-btn" onclick="window.location.href='notifications.php?mark_all_read=1'">
                        Mark all read
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="notification-dropdown-body">
            <?php if (count($recentNotifications) > 0): ?>
                <?php foreach ($recentNotifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                         onclick="window.location.href='notifications.php?mark_read=<?php echo $notif['id']; ?>'">
                        <div class="notification-item-header">
                            <span class="notification-icon">
                                <?php
                                if ($notif['type'] == 'vote') echo '🗳️';
                                elseif ($notif['type'] == 'milestone') echo '🎯';
                                else echo '📢';
                                ?>
                            </span>
                            <div class="notification-item-content">
                                <div class="notification-item-title">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                </div>
                                <div class="notification-item-message">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </div>
                                <div class="notification-item-time">
                                    <?php
                                    $time = strtotime($notif['created_at']);
                                    $diff = time() - $time;
                                    
                                    if ($diff < 60) echo "Just now";
                                    elseif ($diff < 3600) echo floor($diff / 60) . "m ago";
                                    elseif ($diff < 86400) echo floor($diff / 3600) . "h ago";
                                    else echo date('M d, Y', $time);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <div class="notification-empty-icon">🔕</div>
                    <p>No notifications yet</p>
                    <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                        You'll see updates here when students vote
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown-footer">
            <a href="notifications.php">View All Notifications →</a>
        </div>
    </div>
</div>