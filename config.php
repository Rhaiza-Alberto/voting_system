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
              AND c.user_id = ?
              AND v.deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $sessionId, $positionId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $voteCount = $result['vote_count'] ?? 0;
    
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

/**
 * Safely get computed full name from database
 * Prevents SQL injection in name concatenation
 * This is an alias for getUserFullName for compatibility
 */
function getFullName($userId, $conn = null) {
    return getUserFullName($userId, $conn);
}

/**
 * Validate that a session is in the expected state
 * Prevents race conditions during state transitions
 */
function validateSessionState($sessionId, $expectedStatus, $conn = null) {
    $closeConnection = false;
    
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConnection = true;
    }
    
    $query = "SELECT status FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($closeConnection) {
        $conn->close();
    }
    
    if (!$result) {
        return false;
    }
    
    if (is_array($expectedStatus)) {
        return in_array($result['status'], $expectedStatus);
    }
    
    return $result['status'] === $expectedStatus;
}

/**
 * Check if a position is currently open for voting
 * Prevents voting on closed positions
 */
function isPositionOpen($sessionId, $positionId, $conn = null) {
    $closeConnection = false;
    
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConnection = true;
    }
    
    $query = "SELECT current_position_id, status 
              FROM voting_sessions 
              WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($closeConnection) {
        $conn->close();
    }
    
    if (!$result) {
        return false;
    }
    
    return $result['current_position_id'] == $positionId;
}

/**
 * Prevent duplicate votes with transaction safety
 * Records a vote with full validation and race condition prevention
 * 
 * @param int $sessionId Voting session ID
 * @param int $voterId User ID of the voter
 * @param int $candidateId Candidate ID being voted for
 * @param int $positionId Position ID being voted for
 * @param mysqli $conn Optional database connection
 * @return bool Success status
 * @throws Exception If vote cannot be recorded
 */
function recordVote($sessionId, $voterId, $candidateId, $positionId, $conn = null) {
    $closeConnection = false;
    
    if ($conn === null) {
        $conn = getDBConnection();
        $closeConnection = true;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Lock the votes table to prevent race conditions
        $conn->query("LOCK TABLES votes WRITE, voting_sessions READ, candidates READ");
        
        // Verify position is still open
        if (!isPositionOpen($sessionId, $positionId, $conn)) {
            throw new Exception("Position is no longer open for voting");
        }
        
        // Check for duplicate vote
        $checkQuery = "SELECT id FROM votes 
                       WHERE session_id = ? 
                       AND voter_id = ? 
                       AND position_id = ? 
                       AND deleted_at IS NULL 
                       FOR UPDATE";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("iii", $sessionId, $voterId, $positionId);
        $stmt->execute();
        $existingVote = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($existingVote) {
            throw new Exception("You have already voted for this position");
        }
        
        // Verify candidate exists and is valid
        $candidateQuery = "SELECT id FROM candidates 
                          WHERE id = ? 
                          AND position_id = ? 
                          AND deleted_at IS NULL";
        $stmt = $conn->prepare($candidateQuery);
        $stmt->bind_param("ii", $candidateId, $positionId);
        $stmt->execute();
        $candidateValid = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if (!$candidateValid) {
            throw new Exception("Invalid candidate selection");
        }
        
        // Insert vote
        $insertQuery = "INSERT INTO votes (session_id, voter_id, candidate_id, position_id) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("iiii", $sessionId, $voterId, $candidateId, $positionId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record vote: " . $stmt->error);
        }
        $stmt->close();
        
        // Unlock tables
        $conn->query("UNLOCK TABLES");
        
        // Commit transaction
        $conn->commit();
        
        if ($closeConnection) {
            $conn->close();
        }
        
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->query("UNLOCK TABLES");
        $conn->rollback();
        
        if ($closeConnection) {
            $conn->close();
        }
        
        throw $e;
    }
}
?>