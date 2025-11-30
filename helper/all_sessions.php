<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();

// Get search and filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$query = "SELECT vs.*, 
          sg.group_name,
          (SELECT COUNT(*) FROM votes WHERE session_id = vs.id) as total_votes,
          (SELECT COUNT(DISTINCT voter_id) FROM votes WHERE session_id = vs.id) as unique_voters,
          (SELECT COUNT(*) FROM positions) as total_positions,
          (SELECT COUNT(DISTINCT position_id) FROM candidates WHERE status IN ('elected', 'lost')) as completed_positions
          FROM voting_sessions vs
          LEFT JOIN student_groups sg ON vs.group_id = sg.id
          WHERE 1=1";

if (!empty($searchTerm)) {
    $query .= " AND (vs.session_name LIKE ? OR sg.group_name LIKE ?)";
}

if ($statusFilter !== 'all') {
    $query .= " AND vs.status = ?";
}

$query .= " ORDER BY vs.id DESC";

$stmt = $conn->prepare($query);

if (!empty($searchTerm) && $statusFilter !== 'all') {
    $searchParam = "%$searchTerm%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $statusFilter);
} elseif (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
} elseif ($statusFilter !== 'all') {
    $stmt->bind_param("s", $statusFilter);
}

$stmt->execute();
$sessions = $stmt->get_result();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Sessions - Voting System</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">📋</div>
                <div class="brand-text">
                    <h1>All Voting Sessions</h1>
                    <p>Manage all elections</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <!-- Search and Filter Card -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-body">
                <form method="GET" class="grid-3">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" name="search" class="form-input" 
                               placeholder="🔍 Search sessions..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="locked" <?php echo $statusFilter === 'locked' ? 'selected' : ''; ?>>Locked</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-modern btn-primary">
                        🔍 Filter
                    </button>
                </form>
            </div>
        </div>

        <!-- Sessions List -->
        <?php if ($sessions->num_rows > 0): ?>
            <?php while ($session = $sessions->fetch_assoc()): 
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
                        <a href="../admin/manage_session.php?id=<?php echo $session['id']; ?>" class="btn-modern btn-primary">
                            Manage
                        </a>
                        <a href="../views/view_results.php?session_id=<?php echo $session['id']; ?>" class="btn-modern btn-secondary">
                            Results
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid-4" style="margin-bottom: 1rem;">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-info">
                                <h3>Votes Cast</h3>
                                <div class="stat-number"><?php echo $session['total_votes']; ?></div>
                            </div>
                            <div class="stat-icon blue">📊</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-info">
                                <h3>Voters</h3>
                                <div class="stat-number"><?php echo $session['unique_voters']; ?></div>
                            </div>
                            <div class="stat-icon green">👥</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-info">
                                <h3>Positions</h3>
                                <div class="stat-number"><?php echo $session['total_positions']; ?></div>
                            </div>
                            <div class="stat-icon purple">🎯</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-info">
                                <h3>Completed</h3>
                                <div class="stat-number"><?php echo $session['completed_positions']; ?></div>
                            </div>
                            <div class="stat-icon orange">✅</div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #6b7280; font-size: 0.875rem; font-weight: 500;">Progress</span>
                        <span style="font-weight: 600; color: #111827;"><?php echo round($progress); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="modern-card">
                <div class="card-body" style="text-align: center; padding: 4rem 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">📋</div>
                    <h3 style="color: #6b7280; font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">
                        No Sessions Found
                    </h3>
                    <p style="color: #9ca3af; margin-bottom: 1.5rem;">
                        No sessions match your search criteria.
                    </p>
                    <a href="all_sessions.php" class="btn-modern btn-secondary">
                        Clear Filters
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>