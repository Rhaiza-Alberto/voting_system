<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Debug mode - add ?debug=1 to URL to see diagnostics
$debugMode = isset($_GET['debug']);

// Handle session deletion
if (isset($_GET['delete_session'])) {
    $sessionId = $_GET['delete_session'];
    $adminId = $_SESSION['user_id'];
    
    // Check if session can be deleted (not active/pending)
    $checkQuery = "SELECT status FROM voting_sessions WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $sessionStatus = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($sessionStatus && in_array($sessionStatus['status'], ['active', 'pending', 'paused'])) {
        $message = 'Cannot delete an active, pending, or paused session! Lock the session first.';
        $messageType = 'danger';
    } else {
        // Soft delete using stored procedure
        $stmt = $conn->prepare("CALL sp_soft_delete_session(?, ?)");
        $stmt->bind_param("ii", $sessionId, $adminId);
        
        if ($stmt->execute()) {
            $message = 'Session soft deleted successfully! All votes and data are preserved for audit logs.';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete session.';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all voting sessions with vote counts
$sessionsQuery = "SELECT vs.id, vs.session_name, vs.status, vs.created_at, vs.deleted_at,
                  (SELECT COUNT(*) FROM votes v WHERE v.session_id = vs.id AND v.deleted_at IS NULL) as total_votes,
                  (SELECT COUNT(DISTINCT v.voter_id) FROM votes v WHERE v.session_id = vs.id AND v.deleted_at IS NULL) as unique_voters
                  FROM voting_sessions vs 
                  ORDER BY vs.deleted_at IS NULL DESC, vs.id DESC";

if ($debugMode) {
    echo "<!-- DEBUG: Executing sessions query -->\n";
}

$sessions = $conn->query($sessionsQuery);

if (!$sessions) {
    die("Database query failed: " . $conn->error);
}

if ($debugMode) {
    echo "<!-- DEBUG: Found " . $sessions->num_rows . " sessions -->\n";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - VoteSystem Pro</title>
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
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

        /* Session Card */
        .session-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
        }

        .session-card.active-session {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .session-card.locked-session {
            border-color: #ef4444;
        }

        .session-header {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .session-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .session-meta {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-paused {
            background: #dbeafe;
            color: #1e40af;
        }

        .header-controls {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        /* Vote Stats Grid */
        .vote-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f9fafb;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            border-color: #10b981;
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
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
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.5);
        }

        /* Details Section */
        .vote-details {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .vote-details summary {
            cursor: pointer;
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
            padding: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .vote-details summary::-webkit-details-marker {
            display: none;
        }

        .vote-details summary::before {
            content: '▶';
            display: inline-block;
            transition: transform 0.3s ease;
            color: #10b981;
        }

        .vote-details[open] summary::before {
            transform: rotate(90deg);
        }

        .vote-details summary:hover {
            background: #f0fdf4;
            color: #10b981;
        }

        .details-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: #f9fafb;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .details-section h4 {
            color: #1f2937;
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #f0fdf4;
        }

        /* Badges */
        .snapshot-badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .preserved-indicator {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .deleted-indicator {
            color: #6b7280;
            font-style: italic;
            font-size: 0.875rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .empty-state p {
            color: #6b7280;
            margin-bottom: 0.5rem;
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

            .session-header {
                padding: 1rem 1.5rem;
            }

            .vote-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 1rem 1.5rem;
            }

            .header-controls {
                flex-direction: column;
                width: 100%;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.625rem 0.75rem;
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
                    <p>Audit Logs & Election History</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">Complete Voting Session History</h2>
            </div>
            
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Deletion Warning:</strong> Deleting a session will permanently remove all voting records and snapshot data. This action cannot be undone. Active, pending, or paused sessions cannot be deleted.
                </div>
            </div>
        </div>
        
        <?php if ($sessions->num_rows > 0): ?>
            <?php 
            $delay = 0.1;
            while ($session = $sessions->fetch_assoc()): 
                $delay += 0.05;
                $sessionId = $session['id'];
                $totalVotes = $session['total_votes'];
                $uniqueVoters = $session['unique_voters'];
                
                // Get vote breakdown by position with unique voters
                $breakdownQuery = "SELECT 
                                    p.position_name, 
                                    p.position_order, 
                                    COUNT(v.id) as vote_count,
                                    SUM(CASE WHEN v.candidate_id IS NULL THEN 1 ELSE 0 END) as orphaned_votes,
                                    COUNT(DISTINCT v.voter_id) as unique_voters
                                  FROM positions p
                                  LEFT JOIN votes v ON p.id = v.position_id AND v.session_id = ?
                                  GROUP BY p.id, p.position_name, p.position_order
                                  ORDER BY p.position_order";
                $stmt = $conn->prepare($breakdownQuery);
                $stmt->bind_param("i", $sessionId);
                $stmt->execute();
                $breakdown = $stmt->get_result();
                
                // Calculate total orphaned votes
                $totalOrphanedVotes = 0;
                $breakdown->data_seek(0);
                while ($row = $breakdown->fetch_assoc()) {
                    $totalOrphanedVotes += $row['orphaned_votes'];
                }
                $breakdown->data_seek(0);
                
                // Get winners with snapshot data
                $winnersQuery = "SELECT 
                                    p.position_name,
                                    p.position_order,
                                    w.user_id,
                                    COALESCE(
                                        w.snapshot_winner_name,
                                        TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)),
                                        'Winner (No Snapshot Available)'
                                    ) as winner_name,
                                    COALESCE(
                                        w.snapshot_student_id,
                                        u.student_id,
                                        'N/A'
                                    ) as student_id,
                                    (
                                        SELECT COUNT(*) 
                                        FROM votes v
                                        LEFT JOIN candidates c ON v.candidate_id = c.id 
                                        WHERE v.session_id = w.session_id 
                                        AND v.position_id = w.position_id 
                                        AND (
                                            c.user_id = w.user_id 
                                            OR (v.candidate_id IS NULL AND p.id = w.position_id)
                                        )
                                    ) as vote_count,
                                    CASE 
                                        WHEN u.id IS NULL THEN 1
                                        ELSE 0
                                    END as user_deleted
                                FROM winners w
                                JOIN positions p ON w.position_id = p.id
                                LEFT JOIN users u ON w.user_id = u.id
                                WHERE w.session_id = ?
                                ORDER BY p.position_order";
                $winnersStmt = $conn->prepare($winnersQuery);
                $winnersStmt->bind_param("i", $sessionId);
                $winnersStmt->execute();
                $winners = $winnersStmt->get_result();
                
                $isActiveSession = in_array($session['status'], ['active', 'pending', 'paused']);
            ?>
            
            <div class="session-card fade-in <?php echo $isActiveSession ? 'active-session' : ($session['status'] == 'locked' ? 'locked-session' : ''); ?>" style="animation-delay: <?php echo $delay; ?>s;">
                <div class="session-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h3 class="session-name"><?php echo htmlspecialchars($session['session_name']); ?></h3>
                            <span class="status-badge badge-<?php echo $session['status']; ?>">
                                <?php echo strtoupper($session['status']); ?>
                            </span>
                        </div>
                        <p class="session-meta">
                            <strong>Created:</strong> <?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?>
                            <?php if ($isActiveSession): ?>
                                <span style="color: #10b981; font-weight: 600; margin-left: 1rem;">Currently Running</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="header-controls">
                        <a href="../views/view_results.php?session_id=<?php echo $sessionId; ?>" 
                           class="btn-modern btn-primary">
                            View Results
                        </a>
                        <?php if (!$isActiveSession): ?>
                            <a href="?delete_session=<?php echo $sessionId; ?>" 
                               class="btn-modern btn-danger" 
                               onclick="return confirm('Delete Session: <?php echo htmlspecialchars($session['session_name']); ?>?\n\nThis will permanently remove:\n- All vote records (<?php echo $totalVotes; ?> votes)\n- Session data\n- Winner records\n- Snapshot data\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')">
                                Delete
                            </a>
                        <?php else: ?>
                            <button class="btn-modern btn-secondary" disabled style="cursor: not-allowed; opacity: 0.6;" title="Cannot delete active session">
                                Cannot Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="vote-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalVotes; ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $uniqueVoters; ?></div>
                        <div class="stat-label">Students Voted</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $breakdown->num_rows; ?></div>
                        <div class="stat-label">Positions</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $winners->num_rows; ?></div>
                        <div class="stat-label">Winners Elected</div>
                    </div>
                </div>
                
                <details class="vote-details">
                    <summary>View Detailed Breakdown</summary>
                    
                    <?php if ($winners->num_rows > 0): ?>
                        <div class="details-section">
                            <h4>Election Winners</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Position</th>
                                        <th>Winner</th>
                                        <th>Student ID</th>
                                        <th>Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $winners->data_seek(0);
                                    while ($winner = $winners->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($winner['position_name']); ?></strong></td>
                                            <td>
                                                <?php 
                                                if ($winner['winner_name'] && $winner['winner_name'] !== 'Winner (No Snapshot Available)'): 
                                                    echo htmlspecialchars($winner['winner_name']);
                                                    if ($winner['user_deleted']): ?>
                                                        <span class="snapshot-badge">Preserved</span>
                                                    <?php endif;
                                                else: 
                                                ?>
                                                    <span class="deleted-indicator">Winner</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($winner['student_id'] && $winner['student_id'] !== 'N/A'): 
                                                    echo htmlspecialchars($winner['student_id']);
                                                else:
                                                    echo '<span class="deleted-indicator">N/A</span>';
                                                endif; 
                                                ?>
                                            </td>
                                            <td><strong><?php echo $winner['vote_count']; ?></strong></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <div class="details-section">
                        <h4>Vote Breakdown by Position</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Position</th>
                                    <th>Votes Cast</th>
                                    <th>Voters</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $breakdown->data_seek(0);
                                while ($row = $breakdown->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><strong><?php echo $row['position_order']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                                        <td>
                                            <strong><?php echo $row['vote_count']; ?></strong> votes
                                            <?php if ($row['orphaned_votes'] > 0): ?>
                                                <br>
                                                <span class="deleted-indicator">
                                                    <?php echo $row['orphaned_votes']; ?> from deleted candidates
                                                </span>
                                                <span class="preserved-indicator">Preserved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['unique_voters']; ?> students</td>
                                        <td>
                                            <?php if ($row['orphaned_votes'] > 0): ?>
                                                <span class="preserved-indicator">Preserved</span>
                                            <?php else: ?>
                                                <span class="preserved-indicator">Complete</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php
                    // Show detailed vote information with snapshots
                    $detailedVotesQuery = "SELECT 
                        v.id as vote_id,
                        p.position_name,
                        COALESCE(v.snapshot_voter_name, 'Voter (Unknown)') as voter_name,
                        COALESCE(v.snapshot_voter_student_id, 'N/A') as voter_student_id,
                        CASE 
                            WHEN v.candidate_id IS NULL THEN 'Candidate Deleted'
                            ELSE COALESCE(v.snapshot_candidate_name, c.snapshot_full_name, 'Candidate (No Data)')
                        END as candidate_name,
                        CASE 
                            WHEN v.candidate_id IS NULL THEN 'N/A'
                            ELSE COALESCE(v.snapshot_candidate_student_id, c.snapshot_student_id, 'N/A')
                        END as candidate_student_id,
                        v.voted_at,
                        CASE 
                            WHEN v.candidate_id IS NULL THEN 1
                            ELSE 0
                        END as is_orphaned
                    FROM votes v
                    LEFT JOIN candidates c ON v.candidate_id = c.id
                    LEFT JOIN positions p ON v.position_id = p.id
                    WHERE v.session_id = ?
                    ORDER BY v.voted_at DESC
                    LIMIT 50";
                    
                    $detailedStmt = $conn->prepare($detailedVotesQuery);
                    $detailedStmt->bind_param("i", $sessionId);
                    $detailedStmt->execute();
                    $detailedVotes = $detailedStmt->get_result();
                    ?>
                    
                    <?php if ($detailedVotes->num_rows > 0): ?>
                    <div class="details-section">
                        <h4>Detailed Vote Log (Last 50)</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Voter</th>
                                    <th>Voted For</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($vote = $detailedVotes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vote['position_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($vote['voter_name']); ?>
                                            <br>
                                            <small style="color: #6b7280;">ID: <?php echo htmlspecialchars($vote['voter_student_id']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($vote['candidate_name']); ?>
                                            <?php if ($vote['is_orphaned']): ?>
                                                <span class="snapshot-badge">Preserved</span>
                                            <?php endif; ?>
                                            <br>
                                            <small style="color: #6b7280;">ID: <?php echo htmlspecialchars($vote['candidate_student_id']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y h:i A', strtotime($vote['voted_at'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    $detailedStmt->close();
                    endif; 
                    ?>
                </details>
            </div>
            
            <?php 
                $stmt->close();
                $winnersStmt->close();
            endwhile; 
            ?>
        <?php else: ?>
            <div class="modern-card fade-in">
                <div class="card-body empty-state">
                    <h3>No Voting Sessions Yet</h3>
                    <p style="margin-bottom: 0.5rem;">There are no voting sessions recorded in the system.</p>
                    <p style="color: #9ca3af;">Create your first session to get started!</p>
                    <a href="../helper/create_session.php" class="btn-modern btn-primary" style="margin-top: 1.5rem;">
                        Create New Session
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>