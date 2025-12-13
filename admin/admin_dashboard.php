<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get statistics
$totalSessions = $conn->query("SELECT COUNT(*) as count FROM voting_sessions WHERE deleted_at IS NULL")->fetch_assoc()['count'];
$activeSessions = $conn->query("SELECT COUNT(*) as count FROM voting_sessions WHERE status IN ('active', 'pending', 'paused') AND deleted_at IS NULL")->fetch_assoc()['count'];
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND deleted_at IS NULL")->fetch_assoc()['count'];
$totalVotes = $conn->query("SELECT COUNT(*) as count FROM votes WHERE deleted_at IS NULL")->fetch_assoc()['count'];

// Get recent sessions with group info
$recentSessionsQuery = "SELECT vs.*, 
                        sg.group_name,
                        (SELECT COUNT(*) FROM votes WHERE session_id = vs.id AND deleted_at IS NULL) as total_votes,
                        (SELECT COUNT(DISTINCT voter_id) FROM votes WHERE session_id = vs.id AND deleted_at IS NULL) as unique_voters,
                        (SELECT COUNT(*) FROM positions WHERE deleted_at IS NULL) as total_positions,
                        (SELECT COUNT(DISTINCT position_id) FROM candidates WHERE status IN ('elected', 'lost') AND deleted_at IS NULL) as completed_positions
                        FROM voting_sessions vs
                        LEFT JOIN student_groups sg ON vs.group_id = sg.id
                        WHERE vs.deleted_at IS NULL
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

        .brand-icon {
            display: none;
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

        .user-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: #f0fdf4;
            border-radius: 50px;
            border: 2px solid #d1fae5;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .modern-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Enhanced Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.15), 0 10px 10px -5px rgba(16, 185, 129, 0.1);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }

        .stat-icon {
            display: none;
        }

        .stat-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: #d1fae5;
            color: #065f46;
            border-radius: 50px;
            font-weight: 500;
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

        /* Enhanced Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: white;
            border: 2px solid #d1fae5;
            border-radius: 12px;
            text-decoration: none;
            color: #1f2937;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            justify-content: center;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            border-color: #10b981;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
        }

        .action-icon {
            display: none;
        }

        /* Enhanced Session Cards */
        .session-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .session-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
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

        .session-meta {
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-box {
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
        }

        .metric-label {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.875rem;
            font-weight: 700;
        }

        .metric-box:nth-child(1) {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }

        .metric-box:nth-child(1) .metric-value {
            color: #065f46;
        }

        .metric-box:nth-child(2) {
            background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
        }

        .metric-box:nth-child(2) .metric-value {
            color: #047857;
        }

        .metric-box:nth-child(3) {
            background: linear-gradient(135deg, #6ee7b7 0%, #34d399 100%);
        }

        .metric-box:nth-child(3) .metric-value {
            color: #065f46;
        }

        .metric-box:nth-child(4) {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        }

        .metric-box:nth-child(4) .metric-value {
            color: #064e3b;
        }

        /* Enhanced Progress Bar */
        .progress-container {
            margin-top: 1.5rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .progress-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .progress-percentage {
            font-weight: 700;
            color: #1f2937;
            font-size: 1rem;
        }

        .progress-bar {
            height: 12px;
            background: #e5e7eb;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            border-radius: 50px;
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        /* Enhanced Buttons */
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

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-icon {
            display: none;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .session-header {
                flex-direction: column;
                gap: 1rem;
            }

            .metric-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
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
                    <p>Admin Dashboard</p>
                </div>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <span style="font-weight: 500; color: #1f2937;">
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </span>
                </div>
                <?php 
                if (file_exists('../notification_widget.php')) {
                    include '../notification_widget.php'; 
                }
                ?>
                <a href="../logout.php" class="btn-modern btn-secondary">
                    Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="modern-container">
        <!-- Enhanced Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card fade-in">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Active Sessions</h3>
                        <div class="stat-number"><?php echo $activeSessions; ?></div>
                        <div class="stat-meta">
                            <?php if ($activeSessions > 0): ?>
                                <span class="stat-badge" style="background: #d1fae5; color: #065f46;">
                                    Live now
                                </span>
                            <?php else: ?>
                                <span class="stat-badge" style="background: #f3f4f6; color: #6b7280;">
                                    No active sessions
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card fade-in" style="animation-delay: 0.1s;">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Sessions</h3>
                        <div class="stat-number"><?php echo $totalSessions; ?></div>
                        <div class="stat-meta">
                            <span style="color: #10b981; font-weight: 500;">All time</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card fade-in" style="animation-delay: 0.2s;">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $totalStudents; ?></div>
                        <div class="stat-meta">
                            <span style="color: #059669; font-weight: 500;">Registered</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stat-card fade-in" style="animation-delay: 0.3s;">
                <div class="stat-card-content">
                    <div class="stat-info">
                        <h3>Total Votes Cast</h3>
                        <div class="stat-number"><?php echo $totalVotes; ?></div>
                        <div class="stat-meta">
                            <span style="color: #34d399; font-weight: 500;">All time</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Quick Actions -->
        <div class="modern-card fade-in" style="animation-delay: 0.4s;">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="actions-grid">
                    <a href="../helper/create_session.php" class="action-btn">
                        <span>Create Session</span>
                    </a>
                    <a href="../students/manage_students.php" class="action-btn">
                        <span>Manage Students</span>
                    </a>
                    <a href="manage_groups.php" class="action-btn">
                        <span>Student Groups</span>
                    </a>
                    <a href="../views/view_results.php" class="action-btn">
                        <span>View Results</span>
                    </a>
                    <a href="audit_logs.php" class="action-btn">
                        <span>Audit Logs</span>
                    </a>
                    <a href="restore_deleted.php" class="action-btn">
                        <span>Restore Deleted Records</span>
                    </a>
                    <!--<a href="../helper/all_sessions.php" class="action-btn">
                        <span>All Sessions</span>
                    </a>-->
                </div>
            </div>
        </div>

        <!-- Enhanced Recent Sessions -->
        <div class="modern-card fade-in" style="animation-delay: 0.5s;">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title">Recent Sessions</h2>
                    <a href="../helper/all_sessions.php" class="btn-modern btn-secondary">
                        View All →
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($recentSessions->num_rows > 0): ?>
                    <?php $delay = 0; ?>
                    <?php while ($session = $recentSessions->fetch_assoc()): 
                        $progress = 0;
                        if ($session['total_positions'] > 0) {
                            $progress = ($session['completed_positions'] / $session['total_positions']) * 100;
                        }
                        $delay += 0.1;
                    ?>
                    <div class="session-card fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                        <div class="session-header">
                            <div>
                                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                    <h3 class="session-title"><?php echo htmlspecialchars($session['session_name']); ?></h3>
                                    <span class="status-badge badge-<?php echo $session['status']; ?>">
                                        <?php echo strtoupper($session['status']); ?>
                                    </span>
                                </div>
                                <p class="session-meta">
                                    <?php if ($session['group_name']): ?>
                                        <span>Group: <?php echo htmlspecialchars($session['group_name']); ?></span>
                                        <span>•</span>
                                    <?php endif; ?>
                                    <span>Created: <?php echo date('M d, Y', strtotime($session['created_at'])); ?></span>
                                </p>
                            </div>
                            <div class="btn-group">
                                <?php if ($session['status'] !== 'locked'): ?>
                                    <a href="manage_session.php?id=<?php echo $session['id']; ?>" class="btn-modern btn-primary">
                                        Manage
                                    </a>
                                <?php endif; ?>
                                <a href="../views/view_results.php?session_id=<?php echo $session['id']; ?>" class="btn-modern btn-secondary">
                                    Results
                                </a>
                            </div>
                        </div>

                        <div class="metric-grid">
                            <div class="metric-box">
                                <p class="metric-label">Votes Cast</p>
                                <p class="metric-value"><?php echo $session['total_votes']; ?></p>
                            </div>
                            <div class="metric-box">
                                <p class="metric-label">Voters</p>
                                <p class="metric-value"><?php echo $session['unique_voters']; ?></p>
                            </div>
                            <div class="metric-box">
                                <p class="metric-label">Positions</p>
                                <p class="metric-value"><?php echo $session['total_positions']; ?></p>
                            </div>
                            <div class="metric-box">
                                <p class="metric-label">Completed</p>
                                <p class="metric-value"><?php echo $session['completed_positions']; ?></p>
                            </div>
                        </div>

                        <div class="progress-container">
                            <div class="progress-header">
                                <span class="progress-label">Session Progress</span>
                                <span class="progress-percentage"><?php echo round($progress); ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3 class="empty-title">No sessions yet</h3>
                        <p class="empty-description">Create your first voting session to get started!</p>
                        <a href="../helper/create_session.php" class="btn-modern btn-primary">
                            Create Your First Session
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