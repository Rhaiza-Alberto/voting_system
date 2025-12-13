<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'classroom_voting');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Helper function to format student name (computed from name parts)
function formatStudentName($firstName, $middleName, $lastName) {
    $nameParts = array_filter([
        trim($firstName),
        trim($middleName),
        trim($lastName)
    ]);
    
    if (empty($nameParts)) {
        return 'No Name';
    }
    
    // Format: First Name Middle Name Last Name
    return implode(' ', $nameParts);
}

// Helper function to get user's full name
function getUserFullName($userId, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConn = true;
    }
    
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $fullName = formatStudentName($user['first_name'], $user['middle_name'], $user['last_name']);
    } else {
        $fullName = 'Unknown User';
    }
    
    $stmt->close();
    if ($closeConn) {
        $conn->close();
    }
    
    return $fullName;
}

// Helper function to get vote count for a winner (computed)
function getWinnerVoteCount($sessionId, $positionId, $userId, $conn = null) {
    $closeConn = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConn = true;
    }
    
    $query = "SELECT COUNT(*) as vote_count 
              FROM votes v
              JOIN candidates c ON v.candidate_id = c.id
              WHERE v.session_id = ? 
              AND v.position_id = ? 
              AND c.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $sessionId, $positionId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $voteCount = $result['vote_count'];
    
    $stmt->close();
    if ($closeConn) {
        $conn->close();
    }
    
    return $voteCount;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: students/student_dashboard.php');
        exit();
    }
}

// Get current user info (with computed full name)
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT id, student_id, first_name, middle_name, last_name, email, role, created_at 
                           FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result(); 
    $user = $result->fetch_assoc();
    
    // Add computed full name
    if ($user) {
        $user['full_name'] = formatStudentName($user['first_name'], $user['middle_name'], $user['last_name']);
    }
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Update session variables with full name (for display purposes)
function updateSessionUserInfo() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            $_SESSION['full_name'] = $user['full_name'];
        }
    }
}

/**
 * Soft delete a record from any table
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @param int $deletedBy User ID who is deleting
 * @return bool Success status
 */
function softDelete($conn, $table, $id, $deletedBy) {
    $allowedTables = ['users', 'candidates', 'voting_sessions', 'votes', 'winners', 
                      'positions', 'student_groups', 'student_group_members', 
                      'notifications', 'vote_logs', 'email_verification_logs'];
    
    if (!in_array($table, $allowedTables)) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE $table SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $deletedBy, $id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Restore a soft-deleted record
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool Success status
 */
function restoreRecord($conn, $table, $id) {
    $allowedTables = ['users', 'candidates', 'voting_sessions', 'votes', 'winners', 
                      'positions', 'student_groups', 'student_group_members', 
                      'notifications', 'vote_logs', 'email_verification_logs'];
    
    if (!in_array($table, $allowedTables)) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE $table SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Check if a record is soft deleted
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @return bool True if deleted, false otherwise
 */
function isDeleted($conn, $table, $id) {
    $stmt = $conn->prepare("SELECT deleted_at FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return ($result && $result['deleted_at'] !== null);
}

?>