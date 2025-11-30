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
    
    // Check if session can be deleted (not active/pending)
    $checkQuery = "SELECT status FROM voting_sessions WHERE id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $sessionStatus = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($sessionStatus && in_array($sessionStatus['status'], ['active', 'pending', 'paused'])) {
        $message = '❌ Cannot delete an active, pending, or paused session! Lock the session first.';
        $messageType = 'danger';
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
            $message = '✅ Session deleted successfully! Removed ' . $deletedVotes . ' vote records.';
            $messageType = 'success';
        } else {
            $message = '❌ Failed to delete session.';
            $messageType = 'danger';
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
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Audit Logs - Election History</title>
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">📜</div>
                <div class="brand-text">
                    <h1>Audit Logs</h1>
                    <p>Election History</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">📊 Complete Voting Session History</h2>
            </div>
            
            <div class="card-body">
                <div class="alert alert-success">
                    <strong>✅ Data Preservation System Active:</strong> All candidate names, voter information, and winner records are permanently preserved using snapshots. Even if users are deleted, their historical election data remains intact in audit logs forever.
                </div>
                
                <div class="alert alert-info">
                    <strong>ℹ️ About Audit Logs:</strong> This page shows all voting sessions ever conducted. Vote data is permanently preserved with snapshot technology - candidate and voter names are saved when they participate, so historical records never lose information even when accounts are deleted.
                </div>
                
                <div class="alert alert-warning">
                    <strong>⚠️ Deletion Warning:</strong> Deleting a session will permanently remove all voting records and snapshot data. This action cannot be undone. Active, pending, or paused sessions cannot be deleted.
                </div>
            </div>
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
            
            <div class="session-card fade-in <?php echo $isActiveSession ? 'active-session' : ($session['status'] == 'locked' ? 'locked-session' : ''); ?>">
                <div class="session-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h3 class="session-name"><?php echo htmlspecialchars($session['session_name']); ?></h3>
                            <span class="status-badge badge-<?php echo $session['status']; ?>">
                                <?php echo strtoupper($session['status']); ?>
                            </span>
                        </div>
                        <p class="session-meta">
                            <strong>📅 Created:</strong> <?php echo date('F d, Y h:i A', strtotime($session['created_at'])); ?>
                            <?php if ($isActiveSession): ?>
                                <span style="color: #10b981; font-weight: 600; margin-left: 1rem;">⚡ Currently Running</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="header-controls">
                        <a href="../views/view_results.php?session_id=<?php echo $sessionId; ?>" 
                           class="btn-modern btn-primary">
                            📊 View Results
                        </a>
                        <?php if (!$isActiveSession): ?>
                            <a href="?delete_session=<?php echo $sessionId; ?>" 
                               class="btn-modern btn-danger" 
                               onclick="return confirm('🗑️ Delete Session: <?php echo htmlspecialchars($session['session_name']); ?>?\n\nThis will permanently remove:\n- All vote records (<?php echo $totalVotes; ?> votes)\n- Session data\n- Winner records\n- Snapshot data\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')">
                                🗑️ Delete
                            </a>
                        <?php else: ?>
                            <button class="btn-modern btn-secondary" disabled style="cursor: not-allowed; opacity: 0.6;" title="Cannot delete active session">
                                🔒 Cannot Delete
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
                    <summary>📋 View Detailed Breakdown</summary>
                    
                    <?php if ($winners->num_rows > 0): ?>
                        <div class="details-section">
                            <h4>🏆 Election Winners:</h4>
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
                                                        <span class="snapshot-badge">💾 Preserved</span>
                                                    <?php endif;
                                                else: 
                                                ?>
                                                    <span class="deleted-indicator">🔒 Winner</span>
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
                        <h4>📊 Vote Breakdown by Position:</h4>
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
                                                <span class="preserved-indicator">✅ Preserved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $row['unique_voters']; ?> students</td>
                                        <td>
                                            <?php if ($row['orphaned_votes'] > 0): ?>
                                                <span style="color: #10b981; font-size: 0.875rem; font-weight: 600;">💾 Preserved</span>
                                            <?php else: ?>
                                                <span style="color: #10b981; font-size: 0.875rem; font-weight: 600;">✅ Complete</span>
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
                        <h4>📝 Detailed Vote Log (Last 50):</h4>
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
                                                <span class="snapshot-badge">💾 Preserved</span>
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
            <div class="modern-card">
                <div class="card-body empty-state">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">📊</div>
                    <h3>No Voting Sessions Yet</h3>
                    <p style="margin-bottom: 0.5rem;">There are no voting sessions recorded in the system.</p>
                    <p style="color: #9ca3af;">Create your first session to get started!</p>
                    <a href="create_session.php" class="btn-modern btn-primary" style="margin-top: 1.5rem;">
                        ➕ Create New Session
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>