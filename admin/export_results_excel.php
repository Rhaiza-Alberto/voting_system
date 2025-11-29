<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();

// Get latest or specified session
$sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : null;

if ($sessionId) {
    $sessionQuery = "SELECT * FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($sessionQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $sessionQuery = "SELECT * FROM voting_sessions ORDER BY id DESC LIMIT 1";
    $session = $conn->query($sessionQuery)->fetch_assoc();
}

if (!$session) {
    die("No voting session found");
}

$sessionId = $session['id'];
$sessionName = $session['session_name'];

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Set headers for CSV download
$filename = "Election_Results_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sessionName) . "_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header
fputcsv($output, ['ELECTION RESULTS REPORT']);
fputcsv($output, ['Session:', $sessionName]);
fputcsv($output, ['Status:', strtoupper($session['status'])]);
fputcsv($output, ['Date:', date('F d, Y h:i A', strtotime($session['created_at']))]);
fputcsv($output, []);

// Get total votes for summary
$totalVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($totalVotesQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$totalVotes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get unique voters
$uniqueVotersQuery = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE session_id = ?";
$stmt = $conn->prepare($uniqueVotersQuery);
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$uniqueVoters = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Write summary
fputcsv($output, ['SUMMARY']);
fputcsv($output, ['Total Votes Cast:', $totalVotes]);
fputcsv($output, ['Students Who Voted:', $uniqueVoters]);
fputcsv($output, ['Total Positions:', $positions->num_rows]);
fputcsv($output, []);
fputcsv($output, []);

// Write results for each position
while ($position = $positions->fetch_assoc()) {
    $positionId = $position['id'];
    $positionName = $position['position_name'];
    
    // Get candidates and their votes - use snapshot data when candidate has been deleted
    $resultsQuery = "SELECT 
                     COALESCE(
                         NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)), ''),
                         MAX(v.snapshot_candidate_name)
                     ) AS full_name,
                     COALESCE(u.student_id, MAX(v.snapshot_candidate_student_id)) AS student_id,
                     COUNT(v.id) as vote_count,
                     (COUNT(v.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM votes WHERE session_id = ? AND position_id = ?), 0)) as percentage
                    FROM votes v
                    LEFT JOIN candidates c ON v.candidate_id = c.id
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE v.session_id = ? AND v.position_id = ?
                    GROUP BY v.candidate_id, c.user_id, u.first_name, u.middle_name, u.last_name, u.student_id
                    ORDER BY vote_count DESC, full_name";
    
    $stmt = $conn->prepare($resultsQuery);
    $stmt->bind_param("iiii", $sessionId, $positionId, $sessionId, $positionId);
    $stmt->execute();
    $results = $stmt->get_result();
    
    // Get total votes for this position
    $posVotesQuery = "SELECT COUNT(*) as total FROM votes WHERE session_id = ? AND position_id = ?";
    $posStmt = $conn->prepare($posVotesQuery);
    $posStmt->bind_param("ii", $sessionId, $positionId);
    $posStmt->execute();
    $positionTotalVotes = $posStmt->get_result()->fetch_assoc()['total'];
    $posStmt->close();
    
    // Write position header
    fputcsv($output, [strtoupper($positionName)]);
    fputcsv($output, ['Total Votes for this Position:', $positionTotalVotes]);
    fputcsv($output, []);
    
    // Write table headers
    fputcsv($output, ['Rank', 'Candidate Name', 'Student ID', 'Votes', 'Percentage', 'Status']);
    
    // Write candidates
    if ($results->num_rows > 0) {
        $rank = 1;
        $prevVotes = -1;
        $actualRank = 1;
        
        while ($result = $results->fetch_assoc()) {
            // Handle ties
            if ($result['vote_count'] != $prevVotes) {
                $actualRank = $rank;
            }
            
            $status = '';
            if ($actualRank == 1 && $result['vote_count'] > 0) {
                $status = 'WINNER';
            }
            
            $percentage = $positionTotalVotes > 0 ? number_format($result['percentage'], 2) . '%' : '0%';
            
            fputcsv($output, [
                $actualRank,
                $result['full_name'],
                $result['student_id'],
                $result['vote_count'],
                $percentage,
                $status
            ]);
            
            $prevVotes = $result['vote_count'];
            $rank++;
        }
    } else {
        fputcsv($output, ['No candidates nominated for this position']);
    }
    
    fputcsv($output, []);
    fputcsv($output, []);
    
    $stmt->close();
}

// Write footer
fputcsv($output, []);
fputcsv($output, ['Report Generated:', date('F d, Y h:i A')]);
fputcsv($output, ['Generated By:', $_SESSION['full_name']]);

fclose($output);
$conn->close();
exit();
?>