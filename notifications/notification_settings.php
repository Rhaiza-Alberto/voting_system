<?php
require_once 'config.php';
require_once 'notification_helper.php';
requireAdmin();

$notif = new NotificationHelper();
$message = '';
$messageType = '';

// Handle cleanup
if (isset($_GET['cleanup'])) {
    $notif->cleanupOldNotifications();
    $message = 'Old notifications cleaned up successfully!';
    $messageType = 'success';
}

// Get notification statistics
$conn = getDBConnection();
$totalNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$unreadNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
$oldNotifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get notification breakdown by type
$typeQuery = "SELECT type, COUNT(*) as count FROM notifications GROUP BY type";
$typeBreakdown = $conn->query($typeQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings</title>
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">⚙️</div>
                <div class="brand-text">
                    <h1>Notification Settings</h1>
                    <p>Manage notification system</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <?php include 'notification_widget_modern.php'; ?>
                <a href="admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Notifications</h3>
                        <div class="stat-number"><?php echo $totalNotifs; ?></div>
                    </div>
                    <div class="stat-icon blue">🔔</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Unread</h3>
                        <div class="stat-number"><?php echo $unreadNotifs; ?></div>
                    </div>
                    <div class="stat-icon orange">📬</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Old (30+ days)</h3>
                        <div class="stat-number"><?php echo $oldNotifs; ?></div>
                    </div>
                    <div class="stat-icon purple">🗑️</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>System Status</h3>
                        <div class="stat-number" style="font-size: 1.5rem; color: #10b981;">✓ Active</div>
                    </div>
                    <div class="stat-icon green">✅</div>
                </div>
            </div>
        </div>

        <!-- Notification Breakdown -->
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">📊 Notification Breakdown</h2>
            </div>
            <div class="card-body">
                <div class="grid-3">
                    <?php 
                    $typeBreakdown->data_seek(0);
                    while ($type = $typeBreakdown->fetch_assoc()): 
                    ?>
                    <div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                    <?php echo ucfirst($type['type']); ?>
                                </p>
                                <p style="font-size: 2rem; font-weight: 700; color: #111827;">
                                    <?php echo $type['count']; ?>
                                </p>
                            </div>
                            <span style="font-size: 2rem;">
                                <?php 
                                if ($type['type'] === 'vote') echo '🗳️';
                                elseif ($type['type'] === 'milestone') echo '🎯';
                                else echo '📢';
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">🔧 Maintenance Actions</h2>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1.5rem; border-radius: 0.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #92400e; margin-bottom: 0.5rem;">
                            🗑️ Cleanup Old Notifications
                        </h3>
                        <p style="color: #78350f; margin-bottom: 1rem;">
                            Remove notifications older than 30 days to keep the database clean.
                        </p>
                        <p style="color: #78350f; font-weight: 600; margin-bottom: 1rem;">
                            <?php echo $oldNotifs; ?> old notification(s) will be deleted
                        </p>
                        <?php if ($oldNotifs > 0): ?>
                            <a href="?cleanup=1" 
                               onclick="return confirm('Delete <?php echo $oldNotifs; ?> old notifications?')"
                               class="btn-modern btn-warning">
                                🗑️ Cleanup Now
                            </a>
                        <?php else: ?>
                            <button class="btn-modern btn-secondary" disabled>
                                No old notifications
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 1.5rem; border-radius: 0.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #1e40af; margin-bottom: 0.5rem;">
                            📧 Test Email System
                        </h3>
                        <p style="color: #1e3a8a; margin-bottom: 1rem;">
                            Send a test email to verify your SMTP configuration is working.
                        </p>
                        <a href="test_email.php" class="btn-modern btn-primary">
                            📧 Test Email
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Info -->
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">ℹ️ Notification Features</h2>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 1rem;">
                            🔔 In-App Notifications
                        </h3>
                        <ul style="color: #6b7280; line-height: 2; margin-left: 1.5rem;">
                            <li>Real-time bell icon alerts</li>
                            <li>Dropdown preview of recent notifications</li>
                            <li>Mark as read functionality</li>
                            <li>Filter by type (Vote, Milestone, System)</li>
                            <li>Auto-refresh when active</li>
                        </ul>
                    </div>

                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 1rem;">
                            📧 Email Notifications
                        </h3>
                        <ul style="color: #6b7280; line-height: 2; margin-left: 1.5rem;">
                            <li>Welcome emails for new students</li>
                            <li>Vote confirmation emails</li>
                            <li>Professional HTML templates</li>
                            <li>Gmail SMTP support</li>
                            <li>Automatic sending on events</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>