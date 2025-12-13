<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Handle candidate nomination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nominate'])) {
    $userId = $_POST['user_id'];
    $positionId = $_POST['position_id'];
    
    // Get the position order for the position they're trying to nominate for
    $posOrderQuery = "SELECT position_order, position_name FROM positions WHERE id = ?";
    $stmt = $conn->prepare($posOrderQuery);
    $stmt->bind_param("i", $positionId);
    $stmt->execute();
    $targetPosition = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Check if student has already won a higher-priority position
    $electedCheckQuery = "SELECT p.position_order, p.position_name 
                          FROM candidates c
                          JOIN positions p ON c.position_id = p.id
                          WHERE c.user_id = ? AND c.status = 'elected'
                          ORDER BY p.position_order ASC
                          LIMIT 1";
    $stmt = $conn->prepare($electedCheckQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $electedResult = $stmt->get_result();
    
    if ($electedResult->num_rows > 0) {
        $electedPosition = $electedResult->fetch_assoc();
        
        // If they won a higher priority position (lower order number), prevent nomination
        if ($electedPosition['position_order'] < $targetPosition['position_order']) {
            // Get student name
            $studentQuery = "SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name 
                            FROM users WHERE id = ?";
            $stmtStudent = $conn->prepare($studentQuery);
            $stmtStudent->bind_param("i", $userId);
            $stmtStudent->execute();
            $studentName = $stmtStudent->get_result()->fetch_assoc()['full_name'];
            $stmtStudent->close();
            
            $message = ' Cannot nominate ' . htmlspecialchars($studentName) . ' for ' . 
                       htmlspecialchars($targetPosition['position_name']) . '! They have already been elected as ' . 
                       htmlspecialchars($electedPosition['position_name']) . ' (higher priority position).';
            $messageType = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            // They can be nominated for same or higher priority positions
            nominateCandidate($conn, $userId, $positionId, $message, $messageType);
        }
    } else {
        $stmt->close();
        // No elected position found, proceed with normal checks
        nominateCandidate($conn, $userId, $positionId, $message, $messageType);
    }
}

// Function to handle candidate nomination WITH SNAPSHOT SUPPORT
function nominateCandidate($conn, $userId, $positionId, &$message, &$messageType) {
    // Check if already nominated
    $checkQuery = "SELECT id FROM candidates WHERE user_id = ? AND position_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $userId, $positionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = ' Student is already nominated for this position!';
        $messageType = 'warning';
    } else {
        // Insert candidate - snapshot will be created automatically by trigger
        $insertStmt = $conn->prepare("INSERT INTO candidates (user_id, position_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $positionId);
        if ($insertStmt->execute()) {
            // Manually update snapshot if trigger isn't working
            $candidateId = $insertStmt->insert_id;
            $snapshotQuery = "UPDATE candidates c
                             JOIN users u ON c.user_id = u.id
                             SET c.snapshot_full_name = TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)),
                                 c.snapshot_student_id = u.student_id,
                                 c.snapshot_email = u.email
                             WHERE c.id = ? AND c.snapshot_full_name IS NULL";
            $snapshotStmt = $conn->prepare($snapshotQuery);
            $snapshotStmt->bind_param("i", $candidateId);
            $snapshotStmt->execute();
            $snapshotStmt->close();
            
            $message = ' Candidate nominated successfully!';
            $messageType = 'success';
        } else {
            $message = ' Failed to nominate candidate!';
            $messageType = 'error';
        }
        $insertStmt->close();
    }
    $stmt->close();
}

// Handle candidate removal
if (isset($_GET['remove'])) {
    $candidateId = $_GET['remove'];
    $adminId = $_SESSION['user_id'];
    
    // Get candidate info including snapshot before soft deletion
    $candidateInfoQuery = "SELECT 
                           COALESCE(c.snapshot_full_name, TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name))) as candidate_name,
                           p.position_name,
                           (SELECT COUNT(*) FROM votes WHERE candidate_id = c.id AND deleted_at IS NULL) as vote_count
                           FROM candidates c
                           LEFT JOIN users u ON c.user_id = u.id
                           JOIN positions p ON c.position_id = p.id
                           WHERE c.id = ?";
    $stmt = $conn->prepare($candidateInfoQuery);
    $stmt->bind_param("i", $candidateId);
    $stmt->execute();
    $candidateInfo = $stmt->get_result()->fetch_assoc();
    $voteCount = $candidateInfo['vote_count'];
    $stmt->close();
    
    if ($voteCount > 0) {
        $message = '⚠️ Warning: This candidate has ' . $voteCount . ' vote(s). ';
    }
    
    // Soft delete the candidate using stored procedure
    $deleteStmt = $conn->prepare("CALL sp_soft_delete_candidate(?, ?)");
    $deleteStmt->bind_param("ii", $candidateId, $adminId);
    
    if ($deleteStmt->execute()) {
        if ($voteCount > 0) {
            $message .= '✅ Candidate ' . htmlspecialchars($candidateInfo['candidate_name']) . ' soft deleted. ' . 
                      $voteCount . ' vote(s) preserved in audit logs.';
        } else {
            $message = '✅ Candidate soft deleted successfully!';
        }
        $messageType = 'success';
    } else {
        $message = '⛔ Failed to remove candidate!';
        $messageType = 'error';
    }
    $deleteStmt->close();
}

// Get all students with computed full_name (3NF compliant)
$studentsQuery = "SELECT 
                    id, 
                    student_id, 
                    first_name,
                    middle_name,
                    last_name,
                    TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name
                  FROM users 
                  WHERE role = 'student' 
                  ORDER BY last_name, first_name";
$students = $conn->query($studentsQuery);

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

// Get all candidates with snapshot data and vote counts
$candidatesQuery = "SELECT 
                        c.id,
                        c.user_id,
                        c.status,
                        c.deleted_at,
                        COALESCE(
                            c.snapshot_full_name,
                            TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)),
                            'Candidate (No Snapshot)'
                        ) AS full_name,
                        COALESCE(c.snapshot_student_id, u.student_id, 'N/A') as student_id,
                        p.position_name, 
                        p.position_order,
                        (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id AND v.deleted_at IS NULL) as vote_count,
                        (SELECT COUNT(DISTINCT v.session_id) FROM votes v WHERE v.candidate_id = c.id AND v.deleted_at IS NULL) as sessions_count,
                        CASE WHEN u.id IS NULL THEN 1 ELSE 0 END as user_deleted
                    FROM candidates c 
                    LEFT JOIN users u ON c.user_id = u.id 
                    JOIN positions p ON c.position_id = p.id 
                    WHERE c.deleted_at IS NULL
                    ORDER BY p.position_order, c.snapshot_full_name, u.last_name, u.first_name";

$candidates = $conn->query($candidatesQuery);

// Get count of orphaned votes
$orphanedVotesQuery = "SELECT COUNT(*) as count FROM votes WHERE candidate_id IS NULL";
$orphanedVotesResult = $conn->query($orphanedVotesQuery);
$orphanedVotesCount = $orphanedVotesResult->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Manage Candidates</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5em;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #10b981;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        
        .message.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-banner strong {
            color: #1e40af;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        select:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
            padding: 8px 16px;
            font-size: 0.9em;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            font-size: 0.9em;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f7fafc;
            color: #10b981;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f7fafc;
        }
        
        tr.has-votes {
            background: #fef3c7;
        }
        
        tr.has-votes:hover {
            background: #fde68a;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-nominated {
            background: #feebc8;
            color: #744210;
        }
        
        .badge-elected {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-lost {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-ineligible {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .vote-count-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .vote-count-badge.has-votes {
            background: #fef3c7;
            color: #92400e;
        }
        
        .warning-icon {
            color: #f59e0b;
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Manage Candidates</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($orphanedVotesCount > 0): ?>
            <div class="info-banner">
                <strong> Data Preservation:</strong>
                There are currently <strong><?php echo $orphanedVotesCount; ?> orphaned vote(s)</strong> in the system from deleted candidates. All data is preserved in audit logs.
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Nominate New Candidate</h2>
            
            <div class="info-banner">
                <strong> Automatic Data Preservation:</strong> When you nominate a candidate, their information is automatically saved for historical records.
            </div>
            
            <div class="info-banner">
                <strong> Important Rules:</strong>
                <ul style="margin-left: 20px; margin-top: 10px; color: #1e40af;">
                    <li>Students who have won a higher-priority position cannot be nominated for lower-priority positions</li>
                    <li>This ensures fair representation and prevents position conflicts</li>
                    <li>All historical data is preserved automatically</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">Select Student</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php
                        $students->data_seek(0);
                        while ($student = $students->fetch_assoc()):
                        ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="position_id">Select Position</label>
                    <select name="position_id" id="position_id" required>
                        <option value="">-- Choose Position --</option>
                        <?php
                        $positions->data_seek(0);
                        while ($position = $positions->fetch_assoc()):
                        ?>
                            <option value="<?php echo $position['id']; ?>">
                                Priority #<?php echo $position['position_order']; ?> - <?php echo htmlspecialchars($position['position_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" name="nominate" class="btn btn-primary"> Nominate Candidate</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Current Candidates</h2>
            
            <?php if ($candidates->num_rows > 0): ?>
                <div style="margin-bottom: 15px; padding: 12px; background: #f7fafc; border-radius: 8px;">
                    <strong style="color: #2d3748;">Legend:</strong>
                    <span style="margin-left: 15px; font-size: 0.9em; color: #718096;">
                        <span style="background: #fef3c7; padding: 4px 8px; border-radius: 4px; margin: 0 5px;">Yellow Background</span> = Has votes (will be orphaned if removed)
                    </span>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Position</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Votes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($candidate = $candidates->fetch_assoc()): ?>
                            <tr <?php echo ($candidate['vote_count'] > 0) ? 'class="has-votes"' : ''; ?>>
                                <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['position_name']); ?></td>
                                <td>#<?php echo $candidate['position_order']; ?></td>
                                <td>
                                    <span class="status-badge badge-<?php echo $candidate['status']; ?>">
                                        <?php echo ucfirst($candidate['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($candidate['vote_count'] > 0): ?>
                                        <span class="vote-count-badge has-votes">
                                            
                                            <?php echo $candidate['vote_count']; ?> vote<?php echo $candidate['vote_count'] > 1 ? 's' : ''; ?>
                                        </span>
                                        <?php if ($candidate['sessions_count'] > 1): ?>
                                            <br>
                                            <span style="font-size: 0.8em; color: #718096;">
                                                (<?php echo $candidate['sessions_count']; ?> sessions)
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #a0aec0; font-size: 0.9em;">No votes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($candidate['vote_count'] > 0): ?>
                                        <a href="?remove=<?php echo $candidate['id']; ?>" 
                                           class="btn btn-warning" 
                                           onclick="return confirm(' Remove candidate with <?php echo $candidate['vote_count']; ?> vote(s)?\n\n<?php echo htmlspecialchars($candidate['full_name']); ?> for <?php echo htmlspecialchars($candidate['position_name']); ?>\n\nTheir <?php echo $candidate['vote_count']; ?> vote(s) will become orphaned but preserved in audit logs.\n\nContinue?')">
                                             Remove (Has Votes)
                                        </a>
                                    <?php else: ?>
                                        <a href="?remove=<?php echo $candidate['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm(' Remove this candidate?\n\n<?php echo htmlspecialchars($candidate['full_name']); ?> for <?php echo htmlspecialchars($candidate['position_name']); ?>\n\nThis candidate has no votes.\n\nContinue?')">
                                             Remove
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No candidates nominated yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2> Understanding Vote Preservation</h2>
            <div style="color: #4a5568; line-height: 1.8;">
                <h3 style="color: #10b981; margin-bottom: 10px; font-size: 1.1em;">How the system handles candidate removal:</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Votes are NEVER deleted:</strong> When you remove a candidate, their votes remain in the database</li>
                    <li><strong>Orphaned votes:</strong> These votes have their candidate_id set to NULL but remain counted</li>
                    <li><strong>Historical data:</strong> Candidate information is preserved automatically</li>
                    <li><strong>Audit trail preserved:</strong> All historical data remains available in audit logs</li>
                    <li><strong>Safe for new sessions:</strong> You can safely cleanup old candidates when starting a new election</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>