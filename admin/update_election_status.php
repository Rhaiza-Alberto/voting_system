<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get the active or locked session
$sessionQuery = "SELECT * FROM voting_sessions WHERE status IN ('active', 'locked') ORDER BY id DESC LIMIT 1";
$sessionResult = $conn->query($sessionQuery);
$session = $sessionResult->fetch_assoc();

if (!$session) {
    die("No active session found");
}

$sessionId = $session['id'];

// Reset all candidates to 'nominated' status first
$conn->query("UPDATE candidates SET status = 'nominated'");

// Get all positions ordered by priority (lower number = higher priority)
$positionsQuery = "SELECT * FROM positions ORDER BY position_order ASC";
$positions = $conn->query($positionsQuery);

$electedUsers = []; // Track users who have already won positions

while ($position = $positions->fetch_assoc()) {
    $positionId = $position['id'];
    
    // Get the candidate with the most votes for this position
    // Exclude users who already won higher-priority positions
    $winnerQuery = "SELECT c.id as candidate_id, c.user_id, COUNT(v.id) as vote_count
                   FROM candidates c
                   LEFT JOIN votes v ON c.id = v.candidate_id AND v.session_id = ?
                   WHERE c.position_id = ?";
    
   
    if (!empty($electedUsers)) {
        $placeholders = implode(',', array_fill(0, count($electedUsers), '?'));
        $winnerQuery .= " AND c.user_id NOT IN ($placeholders)";
    }
    
    $winnerQuery .= " GROUP BY c.id, c.user_id
                     ORDER BY vote_count DESC, c.id ASC
                     LIMIT 1";
    
    $stmt = $conn->prepare($winnerQuery);
    
 
    $types = "ii";
    $params = [$sessionId, $positionId];
    
    foreach ($electedUsers as $userId) {
        $types .= "i";
        $params[] = $userId;
    }
    
    if (!empty($electedUsers)) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $sessionId, $positionId);
    }
    
    $stmt->execute();
    $winnerResult = $stmt->get_result();
    
    if ($winner = $winnerResult->fetch_assoc()) {
        if ($winner['vote_count'] > 0) {
         
            $updateStmt = $conn->prepare("UPDATE candidates SET status = 'elected' WHERE id = ?");
            $updateStmt->bind_param("i", $winner['candidate_id']);
            $updateStmt->execute();
            $updateStmt->close();
            
          
            $electedUsers[] = $winner['user_id'];
            
          
            $lostStmt = $conn->prepare("UPDATE candidates SET status = 'lost' WHERE position_id = ? AND id != ?");
            $lostStmt->bind_param("ii", $positionId, $winner['candidate_id']);
            $lostStmt->execute();
            $lostStmt->close();
            
   
            $ineligibleStmt = $conn->prepare("UPDATE candidates SET status = 'ineligible' 
                                             WHERE user_id = ? 
                                             AND position_id IN (
                                                 SELECT id FROM positions WHERE position_order > ?
                                             )");
            $ineligibleStmt->bind_param("ii", $winner['user_id'], $position['position_order']);
            $ineligibleStmt->execute();
            $ineligibleStmt->close();
        }
    }
    
    $stmt->close();
}

$conn->close();


header('Location: view_results.php?status=updated');
exit();
?>