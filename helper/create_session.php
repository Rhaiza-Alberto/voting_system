<?php
require_once 'config.php';
requireAdmin();

$error = '';
$success = '';
$conn = getDBConnection();

// Check old candidates
$oldCandidatesCount = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch_assoc()['count'];

// Handle cleanup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_candidates'])) {
    $activeSessionCheck = "SELECT COUNT(*) as count FROM voting_sessions WHERE status IN ('active', 'pending', 'paused')";
    $activeCount = $conn->query($activeSessionCheck)->fetch_assoc()['count'];
    
    if ($activeCount > 0) {
        $error = 'Cannot cleanup candidates while there are active sessions!';
    } else {
        if ($conn->query("DELETE FROM candidates")) {
            $success = 'Cleaned up ' . $oldCandidatesCount . ' old candidates!';
            $oldCandidatesCount = 0;
        } else {
            $error = 'Failed to cleanup candidates.';
        }
    }
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $sessionName = trim($_POST['session_name']);
    $description = trim($_POST['description']);
    $groupId = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    
    if (empty($sessionName)) {
        $error = 'Session name is required';
    } else {
        $checkQuery = "SELECT id FROM voting_sessions WHERE status IN ('active', 'paused')";
        $result = $conn->query($checkQuery);
        
        if ($result->num_rows > 0) {
            $error = 'There is already an active or paused session. Complete or lock it first.';
        } else {
            $stmt = $conn->prepare("INSERT INTO voting_sessions (session_name, description, group_id, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->bind_param("ssissi", $sessionName, $description, $groupId, $startDate, $endDate, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $sessionId = $stmt->insert_id;
                $success = 'Voting session created successfully!';
            } else {
                $error = 'Failed to create session.';
            }
            $stmt->close();
        }
    }
}

// Get all groups
$groupsQuery = "SELECT sg.*, COUNT(sgm.user_id) as member_count 
                FROM student_groups sg 
                LEFT JOIN student_group_members sgm ON sg.id = sgm.group_id 
                GROUP BY sg.id 
                ORDER BY sg.group_name";
$groups = $conn->query($groupsQuery);

// Get all positions
$positionsQuery = "SELECT * FROM positions ORDER BY position_order";
$positions = $conn->query($positionsQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Voting Session</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">➕</div>
                <div class="brand-text">
                    <h1>Create Voting Session</h1>
                    <p>Set up a new election</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($error): ?>
            <div class="alert alert-danger fade-in"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success fade-in">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <a href="manage_candidates.php" class="btn-modern btn-primary">→ Nominate Candidates</a>
                    <a href="manage_session.php" class="btn-modern btn-success">→ Manage Session</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($oldCandidatesCount > 0): ?>
        <div class="alert alert-warning fade-in">
            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">⚠️ Old Candidates Detected!</h3>
            <p>There are <strong><?php echo $oldCandidatesCount; ?> candidates</strong> from previous sessions. Clean them up before creating a new session.</p>
            <form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('Cleanup old candidates? Vote records will be preserved.');">
                <button type="submit" name="cleanup_candidates" class="btn-modern btn-warning">
                    🧹 Cleanup Old Candidates
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">New Voting Session</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                    <strong>💡 Key Features:</strong>
                    <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                        <li>Create multiple sessions for different student groups</li>
                        <li>Sequential voting - one position at a time</li>
                        <li>Only one active session allowed at a time</li>
                        <li>Automatic vote preservation and audit trails</li>
                    </ul>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Session Name *</label>
                        <input type="text" name="session_name" class="form-input" 
                               placeholder="e.g., Grade 10-A Student Council 2025" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3"
                                  placeholder="Optional: Brief description of this voting session"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Student Group (Optional)</label>
                        <select name="group_id" class="form-select">
                            <option value="">All Students</option>
                            <?php 
                            $groups->data_seek(0);
                            while ($group = $groups->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo htmlspecialchars($group['group_name']); ?> 
                                    (<?php echo $group['member_count']; ?> students)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                            Select a specific group or leave as "All Students" to include everyone
                        </p>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Start Date (Optional)</label>
                            <input type="date" name="start_date" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date (Optional)</label>
                            <input type="date" name="end_date" class="form-input">
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>📋 Next Steps After Creation:</strong>
                        <ol style="margin-top: 0.5rem; margin-left: 1.5rem;">
                            <li>Nominate candidates for each position</li>
                            <li>Open voting for the first position</li>
                            <li>Close position and determine winner</li>
                            <li>Repeat for remaining positions</li>
                            <li>Lock session when complete</li>
                        </ol>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" name="create_session" class="btn-modern btn-primary">
                            ✅ Create Session
                        </button>
                        <a href="admin_dashboard.php" class="btn-modern btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Card -->
        <div class="modern-card" style="margin-top: 2rem;">
            <div class="card-header">
                <h2 class="card-title">Available Positions</h2>
            </div>
            <div class="card-body">
                <?php if ($positions->num_rows > 0): ?>
                    <div class="grid-3">
                        <?php 
                        $positions->data_seek(0);
                        while ($pos = $positions->fetch_assoc()): 
                        ?>
                        <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <p style="font-weight: 600; color: #111827;">
                                        <?php echo htmlspecialchars($pos['position_name']); ?>
                                    </p>
                                    <p style="font-size: 0.875rem; color: #6b7280;">
                                        Priority #<?php echo $pos['position_order']; ?>
                                    </p>
                                </div>
                                <span style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                    Active
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No positions created yet. 
                        <a href="manage_positions.php" style="color: #10b981; font-weight: 600;">Create positions first →</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
