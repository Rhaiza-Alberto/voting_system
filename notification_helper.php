<?php
/**
 * Notification Helper - In-System Bell Notifications
 * Simple database-based notification system for admins
 */

class NotificationHelper {
    private $conn;
    private $tableChecked = false;
    
    public function __construct() {
        $this->conn = getDBConnection();
        $this->ensureTableExists();
    }
    
    /**
     * Ensure notifications table exists (auto-setup)
     */
    private function ensureTableExists() {
        if ($this->tableChecked) {
            return;
        }
        
        try {
            // Check if table exists
            $result = $this->conn->query("SHOW TABLES LIKE 'notifications'");
            
            if ($result->num_rows == 0) {
                // Table doesn't exist, create it
                $this->setupNotificationsTable();
            }
            
            $this->tableChecked = true;
        } catch (Exception $e) {
            // Silent fail - table will be created on first use
        }
    }
    
    /**
     * Create notifications table if not exists
     */
    public function setupNotificationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('vote', 'milestone', 'system') DEFAULT 'system',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conn->query($sql);
    }
    
    /**
     * Send notification to specific user
     */
    private function sendNotification($userId, $title, $message, $type = 'system') {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $title, $message, $type);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Notify admin of new vote
     */
    public function notifyAdminNewVote($adminId, $voterName, $positionName) {
        $title = "New Vote Cast";
        $message = $voterName . " has voted for " . $positionName;
        return $this->sendNotification($adminId, $title, $message, 'vote');
    }
    
    /**
     * Notify admin of voting milestone
     */
    public function notifyAdminMilestone($adminId, $sessionName, $percentage, $positionName) {
        $title = "Milestone Reached: " . $percentage . "%";
        $message = "Voter turnout for " . $positionName . " has reached " . $percentage . "% in " . $sessionName;
        return $this->sendNotification($adminId, $title, $message, 'milestone');
    }
    
    /**
     * Notify admin when position voting completes (100% turnout)
     */
    public function notifyAdminFullTurnout($adminId, $sessionName, $positionName) {
        $title = "🎉 Full Turnout Achieved!";
        $message = "All students have voted for " . $positionName . " in " . $sessionName;
        return $this->sendNotification($adminId, $title, $message, 'milestone');
    }
    
    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'];
    }
    
    /**
     * Get recent notifications for user
     */
    public function getNotifications($userId, $limit = 10) {
        $stmt = $this->conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $notificationId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Delete old notifications (older than 30 days)
     */
    public function cleanupOldNotifications() {
        $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return $this->conn->query($sql);
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>