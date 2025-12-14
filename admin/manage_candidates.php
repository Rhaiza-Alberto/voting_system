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
            
            $message = 'Cannot nominate ' . htmlspecialchars($studentName) . ' for ' . 
                       htmlspecialchars($targetPosition['position_name']) . '! They have already been elected as ' . 
                       htmlspecialchars($electedPosition['position_name']) . ' (higher priority position).';
            $messageType = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            nominateCandidate($conn, $userId, $positionId, $message, $messageType);
        }
    } else {
        $stmt->close();
        nominateCandidate($conn, $userId, $positionId, $message, $messageType);
    }
}

// Function to handle candidate nomination
function nominateCandidate($conn, $userId, $positionId, &$message, &$messageType) {
    // Check if already nominated
    $checkQuery = "SELECT id FROM candidates WHERE user_id = ? AND position_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $userId, $positionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = 'Student is already nominated for this position!';
        $messageType = 'warning';
    } else {
        // Insert candidate
        $insertStmt = $conn->prepare("INSERT INTO candidates (user_id, position_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $positionId);
        if ($insertStmt->execute()) {
            // Update snapshot
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
            
            $message = 'Candidate nominated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to nominate candidate!';
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
    
    // Get candidate info
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
        $message = 'Warning: This candidate has ' . $voteCount . ' vote(s). ';
    }
    
    // Soft delete the candidate
    $deleteStmt = $conn->prepare("CALL sp_soft_delete_candidate(?, ?)");
    $deleteStmt->bind_param("ii", $candidateId, $adminId);
    
    if ($deleteStmt->execute()) {
        if ($voteCount > 0) {
            $message .= 'Candidate ' . htmlspecialchars($candidateInfo['candidate_name']) . ' removed. ' . 
                      $voteCount . ' vote(s) will be orphaned.';
        } else {
            $message = 'Candidate removed successfully!';
        }
        $messageType = 'success';
    } else {
        $message = 'Failed to remove candidate!';
        $messageType = 'error';
    }
    $deleteStmt->close();
}

// Get all students
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

// Get all candidates
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates - VoteSystem Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        /* Enhanced Navbar */
        .modern-navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand-text h1 {
            font-size: 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-text p {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .modern-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #a7f3d0;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #bfdbfe;
        }

        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        /* Enhanced Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.5);
        }

        .btn-secondary {
            background: white;
            color: #1f2937;
            border: 2px solid #d1fae5;
        }

        .btn-secondary:hover {
            border-color: #10b981;
            background: #f0fdf4;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }

        tr:hover {
            background: #f9fafb;
        }

        tr.has-votes {
            background: #fef3c7;
        }

        tr.has-votes:hover {
            background: #fde68a;
        }

        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-nominated {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-elected {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-lost {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-ineligible {
            background: #e5e7eb;
            color: #6b7280;
        }

        .vote-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .vote-badge.has-votes {
            background: #fef3c7;
            color: #92400e;
        }

        /* Legend */
        .legend {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 2px solid #e5e7eb;
        }

        .legend-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .legend-item {
            display: inline-block;
            margin-right: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .legend-sample {
            background: #fef3c7;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            margin: 0 0.25rem;
        }

        /* Info Banner */
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }

        .info-banner strong {
            color: #1e40af;
            display: block;
            margin-bottom: 0.5rem;
        }

        .info-banner ul {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
            color: #1e40af;
        }

        .info-banner li {
            margin: 0.25rem 0;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-container {
                padding: 0 1rem;
            }

            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                font-size: 0.875rem;
                min-width: 900px;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-text">
                    <h1>VoteSystem Pro</h1>
                    <p>Manage Candidates</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Nominate Candidate Form -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">Nominate New Candidate</h2>
            </div>
            <div class="card-body">
                <div class="info-banner">
                    <strong>Important Rules</strong>
                    <ul>
                        <li>Students who have won a higher-priority position cannot be nominated for lower-priority positions</li>
                        <li>This ensures fair representation and prevents position conflicts</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Select Student</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Choose Student</option>
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
                        <label class="form-label">Select Position</label>
                        <select name="position_id" class="form-select" required>
                            <option value="">Choose Position</option>
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
                    
                    <button type="submit" name="nominate" class="btn-modern btn-primary">
                        Nominate Candidate
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Current Candidates -->
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h2 class="card-title">Current Candidates</h2>
            </div>
            <div class="card-body">
                <?php if ($candidates->num_rows > 0): ?>
                    <div class="legend">
                        <div class="legend-title">Legend</div>
                        <span class="legend-item">
                            <span class="legend-sample">Yellow Background</span> = Has votes (will be orphaned if removed)
                        </span>
                    </div>
                    
                    <div class="table-container">
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
                                            <span class="badge badge-<?php echo $candidate['status']; ?>">
                                                <?php echo ucfirst($candidate['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($candidate['vote_count'] > 0): ?>
                                                <span class="vote-badge has-votes">
                                                    <?php echo $candidate['vote_count']; ?> vote<?php echo $candidate['vote_count'] > 1 ? 's' : ''; ?>
                                                </span>
                                                <?php if ($candidate['sessions_count'] > 1): ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                                        (<?php echo $candidate['sessions_count']; ?> sessions)
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #9ca3af; font-size: 0.875rem;">No votes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($candidate['vote_count'] > 0): ?>
                                                <a href="?remove=<?php echo $candidate['id']; ?>" 
                                                   class="btn-modern btn-warning" 
                                                   onclick="return confirm('Remove candidate with <?php echo $candidate['vote_count']; ?> vote(s)?\n\n<?php echo htmlspecialchars($candidate['full_name']); ?> for <?php echo htmlspecialchars($candidate['position_name']); ?>\n\nTheir <?php echo $candidate['vote_count']; ?> vote(s) will become orphaned.\n\nContinue?')">
                                                    Remove (Has Votes)
                                                </a>
                                            <?php else: ?>
                                                <a href="?remove=<?php echo $candidate['id']; ?>" 
                                                   class="btn-modern btn-danger" 
                                                   onclick="return confirm('Remove this candidate?\n\n<?php echo htmlspecialchars($candidate['full_name']); ?> for <?php echo htmlspecialchars($candidate['position_name']); ?>\n\nThis candidate has no votes.\n\nContinue?')">
                                                    Remove
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 4rem 2rem;">
                        <p style="font-size: 1.125rem; color: #6b7280;">No candidates nominated yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>