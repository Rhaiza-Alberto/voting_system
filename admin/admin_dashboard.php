<?php
require_once '../config.php';  // Fixed: Go up one directory
requireAdmin();

$conn = getDBConnection();

// Get statistics
$totalSessions = $conn->query("SELECT COUNT(*) as count FROM voting_sessions")->fetch_assoc()['count'];
$activeSessions = $conn->query("SELECT COUNT(*) as count FROM voting_sessions WHERE status IN ('active', 'pending', 'paused')")->fetch_assoc()['count'];
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
$totalVotes = $conn->query("SELECT COUNT(*) as count FROM votes")->fetch_assoc()['count'];

// Get recent sessions with group info
$recentSessionsQuery = "SELECT vs.*, 
                        sg.group_name,
                        (SELECT COUNT(*) FROM votes WHERE session_id = vs.id) as total_votes,
                        (SELECT COUNT(DISTINCT voter_id) FROM votes WHERE session_id = vs.id) as unique_voters,
                        (SELECT COUNT(*) FROM positions) as total_positions,
                        (SELECT COUNT(DISTINCT position_id) FROM candidates WHERE status IN ('elected', 'lost')) as completed_positions
                        FROM voting_sessions vs
                        LEFT JOIN student_groups sg ON vs.group_id = sg.id
                        ORDER BY vs.id DESC
                        LIMIT 5";
$recentSessions = $conn->query($recentSessionsQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VoteSystem Pro</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Modern Navbar -->
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">🗳️</div>
                <div class="brand-text">
                    <h1>VoteSystem Pro</h1>
                    <p>Admin Dashboard</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: rgba(255,255,255,0.9);">
                    Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <?php 
                // Check if notification widget exists
                if (file_exists('../notification_widget.php')) {
                    include '../notification_widget.php'; 
                }
                ?>
                <a href="../logout.php" class="btn-modern btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Sessions</h3>
                        <div class="stat-number"><?php echo $totalSessions; ?></div>
                        <p style="color: #10b981; font-size: 0.875rem; margin-top: 0.5rem;">
                            <?php echo $activeSessions; ?> active
                        </p>
                    </div>
                    <div class="stat-icon blue">📅</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $totalStudents; ?></div>
                    </div>
                    <div class="stat-icon green">👥</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Votes</h3>
                        <div class="stat-number"><?php echo $totalVotes; ?></div>
                    </div>
                    <div class="stat-icon purple">🗳️</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Active Sessions</h3>
                        <div class="stat-number"><?php echo $activeSessions; ?></div>
                    </div>
                    <div class="stat-icon orange">⚡</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="grid-3">
                    <a href="../helper/create_session.php" class="btn-modern btn-primary">
                        ➕ Create New Session
                    </a>
                    <a href="manage_students.php" class="btn-modern btn-success">
                        👥 Manage Students
                    </a>
                    <a href="manage_positions.php" class="btn-modern btn-warning">
                        🏆 Manage Positions
                    </a>
                    <a href="manage_groups.php" class="btn-modern btn-secondary">
                        📁 Student Groups
                    </a>
                    <a href="../views/view_results.php" class="btn-modern btn-primary">
                        📊 View Results
                    </a>
                    <a href="audit_logs.php" class="btn-modern btn-secondary">
                        📜 Audit Logs
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="modern-card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title">Recent Sessions</h2>
                    <a href="../helper/all_sessions.php" class="btn-modern btn-secondary">View All →</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($recentSessions->num_rows > 0): ?>
                    <?php while ($session = $recentSessions->fetch_assoc()): 
                        $progress = 0;
                        if ($session['total_positions'] > 0) {
                            $progress = ($session['completed_positions'] / $session['total_positions']) * 100;
                        }
                    ?>
                    <div class="session-card fade-in">
                        <div class="session-header">
                            <div>
                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                    <h3 class="session-title"><?php echo htmlspecialchars($session['session_name']); ?></h3>
                                    <span class="status-badge badge-<?php echo $session['status']; ?>">
                                        <?php echo strtoupper($session['status']); ?>
                                    </span>
                                </div>
                                <p class="session-meta">
                                    <?php if ($session['group_name']): ?>
                                        Group: <?php echo htmlspecialchars($session['group_name']); ?> • 
                                    <?php endif; ?>
                                    Created: <?php echo date('M d, Y', strtotime($session['created_at'])); ?>
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="manage_session.php?id=<?php echo $session['id']; ?>" class="btn-modern btn-primary">
                                    Manage
                                </a>
                                <a href="../views/view_results.php?session_id=<?php echo $session['id']; ?>" class="btn-modern btn-secondary">
                                    Results
                                </a>
                            </div>
                        </div>

                        <div class="grid-4" style="margin-bottom: 1rem;">
                            <div style="background: #dbeafe; padding: 1rem; border-radius: 8px;">
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Votes Cast</p>
                                <p style="font-size: 1.5rem; font-weight: 700; color: #3b82f6;">
                                    <?php echo $session['total_votes']; ?>
                                </p>
                            </div>
                            <div style="background: #d1fae5; padding: 1rem; border-radius: 8px;">
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Voters</p>
                                <p style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                    <?php echo $session['unique_voters']; ?>
                                </p>
                            </div>
                            <div style="background: #e9d5ff; padding: 1rem; border-radius: 8px;">
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Positions</p>
                                <p style="font-size: 1.5rem; font-weight: 700; color: #a855f7;">
                                    <?php echo $session['total_positions']; ?>
                                </p>
                            </div>
                            <div style="background: #fed7aa; padding: 1rem; border-radius: 8px;">
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">Completed</p>
                                <p style="font-size: 1.5rem; font-weight: 700; color: #f97316;">
                                    <?php echo $session['completed_positions']; ?>
                                </p>
                            </div>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="color: #6b7280; font-size: 0.875rem;">Progress</span>
                                <span style="font-weight: 600;"><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <p style="font-size: 3rem; margin-bottom: 1rem;">📋</p>
                        <p>No sessions yet. Create your first session to get started!</p>
                        <a href="../helper/create_session.php" class="btn-modern btn-primary" style="margin-top: 1rem;">
                            Create Session
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($activeSessions > 0): ?>
    <script>
        // Auto-refresh every 30 seconds if there are active sessions
        setTimeout(() => location.reload(), 30000);
    </script>
    <?php endif; ?>
</body>
</html>