<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Debug mode - add ?debug=1 to URL to see diagnostics
$debugMode = isset($_GET['debug']);

// Handle session deletion
if (isset($_GET['delete_session'])) {
    $sessionId = $_GET['delete_session'];
    
    // Check if session can be deleted (not active/pending)
    $checkQuery = "SELECT status FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $sessionStatus = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($sessionStatus && in_array($sessionStatus['status'], ['active', 'pending', 'paused'])) {
        $message = ' Cannot delete an active, pending, or paused session! Lock the session first.';
        $messageType = 'error';
    } else {
        // Delete all votes for this session
        $stmt = $conn->prepare("DELETE FROM votes WHERE session_id = ?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $deletedVotes = $stmt->affected_rows;
        $stmt->close();
        
        // Delete winners for this session
        $stmt = $conn->prepare("DELETE FROM winners WHERE session_id = ?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $stmt->close();
        
        // Delete the session
        $stmt = $conn->prepare("DELETE FROM voting_sessions WHERE id = ?");
        $stmt->bind_param("i", $sessionId);
        if ($stmt->execute()) {
            $message = ' Session deleted successfully! Removed ' . $deletedVotes . ' vote records.';
            $messageType = 'success';
        } else {
            $message = ' Failed to delete session.';
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Get all voting sessions with vote counts
$sessionsQuery = "SELECT vs.id, vs.session_name, vs.status, vs.created_at,
                  (SELECT COUNT(*) FROM votes v WHERE v.session_id = vs.id) as total_votes,
                  (SELECT COUNT(DISTINCT v.voter_id) FROM votes v WHERE v.session_id = vs.id) as unique_voters
                  FROM voting_sessions vs 
                  ORDER BY vs.id DESC";

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
    <title>Audit Logs - Election History</title>
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
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
        
        .warning-banner {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .warning-banner strong {
            color: #92400e;
        }
        
        .success-banner {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .success-banner strong {
            color: #065f46;
        }
        
        .session-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            position: relative;
            transition: all 0.3s;
        }
        
        .session-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .session-card.active-session {
            border-left-color: #34d399;
            background: #ecfdf5;
        }
        
        .session-card.locked-session {
            border-left-color: #f56565;
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .session-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #2d3748;
        }
        
        .header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-locked {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-paused {
            background: #feebc8;
            color: #744210;
        }
        
        .session-meta {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .vote-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #10b981;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .vote-details {
            margin-top: 15px;
        }
        
        .vote-details summary {
            cursor: pointer;
            font-weight: 600;
            color: #10b981;
            padding: 10px;
            background: white;
            border-radius: 5px;
            user-select: none;
        }
        
        .vote-details summary:hover {
            background: #f7fafc;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: white;
            color: #10b981;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .results-table tr:last-child td {
            border-bottom: none;
        }
        
        .results-table tr.winner {
            background: #c6f6d5;
            font-weight: bold;
        }
        
        .deleted-indicator {
            color: #f59e0b;
            font-style: italic;
            font-size: 0.85em;
        }
        
        .preserved-indicator {
            color: #10b981;
            font-size: 0.85em;
            margin-left: 5px;
        }
        
        .snapshot-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            margin-left: 5px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
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
        }
        
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        
        .btn-disabled {
            background: #cbd5e0;
            color: #a0aec0;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #718096;
        }
        
        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .session-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .vote-stats {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Audit Logs - Election History</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2> Complete Voting Session History</h2>
            
            <div class="success-banner">
                <strong> Data Preservation System Active:</strong> All candidate names, voter information, and winner records are permanently preserved using snapshots. Even if users are deleted, their historical election data remains intact in audit logs forever.
            </div>
            
            <div class="info-banner">
                <strong> About Audit Logs:</strong> This page shows all voting sessions ever conducted. Vote data is permanently preserved with snapshot technology - candidate and voter names are saved when they participate, so historical records never lose information even when accounts are deleted.
            </div>
            
            <div class="warning-banner">
                <strong> Deletion Warning:</strong> Deleting a session will permanently remove all voting records and snapshot data. This action cannot be undone. Active, pending, or paused sessions cannot be deleted.
            </div>
            
            <?php if ($sessions->num_rows > 0): ?>
                <?php while ($session = $sessions->fetch_assoc()): 
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
                
                <div class="session-card <?php echo $isActiveSession ? 'active-session' : ($session['status'] == 'locked' ? 'locked-session' : ''); ?>">
                    <div class="session-header">
                        <div class="session-name">
                            <?php echo htmlspecialchars($session['session_name']); ?>
                        </div>
                        <div class="header-controls">
                            <span class="status-badge badge-<?php echo $session['status']; ?>">
                                <?php echo strtoupper($session['status']); ?>
                            </span>
                            <a href="view_results.php?session_id=<?php echo $sessionId; ?>" 
                               class="btn btn-primary">
                                 View Results
                            </a>
                            <?php if (!$isActiveSession): ?>
                                <a href="?delete_session=<?php echo $sessionId; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm(' Delete Session: <?php echo htmlspecialchars($session['session_name']); ?>?\n\nThis will permanently remove:\n- All vote records (<?php echo $totalVotes; ?> votes)\n- Session data\n- Winner records\n- Snapshot data\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')">
                                     Delete
                                </a>
                            <?php else: ?>
                                <button class="btn btn-disabled" disabled title="Cannot delete active session">
                                     Cannot Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="session-meta">
                        <strong> Created:</strong> <?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?>
                        <?php if ($isActiveSession): ?>
                            <span style="color: #10b981; font-weight: 600; margin-left: 15px;"> Currently Running</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($totalOrphanedVotes > 0): ?>
                        
                    <?php endif; ?>
                    
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
                    
                    <details class="vote-details" style="margin-top: 20px;">
                        <summary> View Detailed Breakdown</summary>
                        
                        <?php if ($winners->num_rows > 0): ?>
                            <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 10px;">
                                <h4 style="color: #10b981; margin-bottom: 10px;"> Election Winners:</h4>
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
                                                            <span class="snapshot-badge"></span>
                                                        <?php endif;
                                                    else: 
                                                    ?>
                                                        <span class="deleted-indicator"> Winner</span>
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
                        
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            <h4 style="color: #10b981; margin-bottom: 10px;"> Vote Breakdown by Position:</h4>
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
                                            <td><strong>#<?php echo $row['position_order']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                                            <td>
                                                <strong><?php echo $row['vote_count']; ?></strong> votes
                                                <?php if ($row['orphaned_votes'] > 0): ?>
                                                    <br>
                                                    <span class="deleted-indicator">
                                                        (<?php echo $row['orphaned_votes']; ?> from deleted candidates)
                                                    </span>
                                                    <span class="preserved-indicator"> Preserved</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $row['unique_voters']; ?> students</td>
                                            <td>
                                                <?php if ($row['orphaned_votes'] > 0): ?>
                                                    <span style="color: #10b981; font-size: 0.85em;"> Preserved</span>
                                                <?php else: ?>
                                                    <span style="color: #10b981; font-size: 0.85em;"> Complete</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php
                        // NEW: Show detailed vote information with snapshots
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
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            <h4 style="color: #10b981; margin-bottom: 10px;">Detailed Vote Log (Last 50):</h4>
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
                                                <small style="color: #718096;">ID: <?php echo htmlspecialchars($vote['voter_student_id']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($vote['candidate_name']); ?>
                                                <?php if ($vote['is_orphaned']): ?>
                                                    <span class="snapshot-badge"> Preserved</span>
                                                <?php endif; ?>
                                                <br>
                                                <small style="color: #718096;">ID: <?php echo htmlspecialchars($vote['candidate_student_id']); ?></small>
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
                <div class="empty-state">
                    <h3 style="color: #10b981; margin-bottom: 15px;">No Voting Sessions Yet</h3>
                    <p>There are no voting sessions recorded in the system.</p>
                    <p style="margin-top: 10px;">Create your first session to get started!</p>
                    <a href="create_session.php" class="btn btn-primary" style="margin-top: 20px; padding: 12px 30px;">
                         Create New Session
                    </a>
                </div>
            <?php endif; ?>
        </div>
    
    <?php $conn->close(); ?>
</body>
</html>