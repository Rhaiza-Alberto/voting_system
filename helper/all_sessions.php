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
    <title>All Sessions - VoteSystem Pro</title>
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

        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-body {
            padding: 2rem;
        }

        /* Search and Filter Form */
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.875rem;
        }

        .form-input,
        .form-select {
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        /* Session Card */
        .session-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 0;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .session-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
        }

        .session-header {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .session-title {
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

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Stats Grid */
        .stats-grid {
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

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
            line-height: 1;
        }

        /* Progress Bar */
        .progress-container {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e5e7eb;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }

        .empty-description {
            color: #9ca3af;
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

            .filter-form {
                grid-template-columns: 1fr;
            }

            .session-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .btn-modern {
                flex: 1;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <p>All Voting Sessions</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <!-- Search and Filter Card -->
        <div class="modern-card fade-in">
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Search Sessions</label>
                        <input type="text" name="search" class="form-input" 
                               placeholder="Search by session name or group..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="locked" <?php echo $statusFilter === 'locked' ? 'selected' : ''; ?>>Locked</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-modern btn-primary">
                        Apply Filters
                    </button>
                </form>
            </div>
        </div>

        <!-- Sessions List -->
        <?php if ($sessions->num_rows > 0): ?>
            <?php 
            $delay = 0.1;
            while ($session = $sessions->fetch_assoc()): 
                $delay += 0.05;
                $progress = 0;
                if ($session['total_positions'] > 0) {
                    $progress = ($session['completed_positions'] / $session['total_positions']) * 100;
                }
            ?>
            <div class="session-card fade-in" style="animation-delay: <?php echo $delay; ?>s;">
                <div class="session-header">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h3 class="session-title"><?php echo htmlspecialchars($session['session_name']); ?></h3>
                            <span class="status-badge badge-<?php echo $session['status']; ?>">
                                <?php echo strtoupper($session['status']); ?>
                            </span>
                        </div>
                        <p class="session-meta">
                            <?php if ($session['group_name']): ?>
                                <strong>Group:</strong> <?php echo htmlspecialchars($session['group_name']); ?>
                                <span style="margin: 0 0.5rem;">•</span>
                            <?php endif; ?>
                            <strong>Created:</strong> <?php echo date('M d, Y', strtotime($session['created_at'])); ?>
                        </p>
                    </div>
                    <div class="header-actions">
                        <?php if ($session['status'] !== 'locked'): ?>
                            <a href="../admin/manage_session.php?id=<?php echo $session['id']; ?>" class="btn-modern btn-primary">
                                Manage
                            </a>
                        <?php endif; ?>
                        <a href="../views/view_results.php?session_id=<?php echo $session['id']; ?>" class="btn-modern btn-secondary">
                            Results
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">Votes Cast</div>
                        <div class="stat-number"><?php echo $session['total_votes']; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Voters</div>
                        <div class="stat-number"><?php echo $session['unique_voters']; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Positions</div>
                        <div class="stat-number"><?php echo $session['total_positions']; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Completed</div>
                        <div class="stat-number"><?php echo $session['completed_positions']; ?></div>
                    </div>
                </div>

                <!-- Progress Bar -->
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
            <div class="modern-card fade-in" style="animation-delay: 0.1s;">
                <div class="card-body empty-state">
                    <h3 class="empty-title">No Sessions Found</h3>
                    <p class="empty-description">
                        <?php if (!empty($searchTerm) || $statusFilter !== 'all'): ?>
                            No sessions match your search criteria.
                        <?php else: ?>
                            There are no voting sessions in the system yet.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($searchTerm) || $statusFilter !== 'all'): ?>
                        <a href="all_sessions.php" class="btn-modern btn-secondary">
                            Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="create_session.php" class="btn-modern btn-primary">
                            Create New Session
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>