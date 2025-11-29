<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Handle create group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $groupName = trim($_POST['group_name']);
    $description = trim($_POST['description']);
    
    if (!empty($groupName)) {
        $stmt = $conn->prepare("INSERT INTO student_groups (group_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $groupName, $description);
        if ($stmt->execute()) {
            $message = 'Group created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to create group.';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Handle delete group
if (isset($_GET['delete'])) {
    $groupId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM student_groups WHERE id = ?");
    $stmt->bind_param("i", $groupId);
    if ($stmt->execute()) {
        $message = 'Group deleted successfully!';
        $messageType = 'success';
    }
    $stmt->close();
}

// Get all groups with member counts
$groupsQuery = "SELECT sg.*, 
                COUNT(sgm.user_id) as member_count 
                FROM student_groups sg 
                LEFT JOIN student_group_members sgm ON sg.id = sgm.group_id 
                GROUP BY sg.id 
                ORDER BY sg.created_at DESC";
$groups = $conn->query($groupsQuery);

// Get all students for assignment
$studentsQuery = "SELECT id, student_id, first_name, middle_name, last_name 
                  FROM users WHERE role = 'student' 
                  ORDER BY last_name, first_name";
$students = $conn->query($studentsQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Student Groups</title>
    <link rel="stylesheet" href="assets/css/modern-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">📁</div>
                <div class="brand-text">
                    <h1>Student Groups</h1>
                    <p>Organize students into groups</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Create Group Form -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title">Create New Group</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Group Name *</label>
                            <input type="text" name="group_name" class="form-input" 
                                   placeholder="e.g., Grade 10-A" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-input" 
                                   placeholder="Optional description">
                        </div>
                    </div>
                    <button type="submit" name="create_group" class="btn-modern btn-primary">
                        ➕ Create Group
                    </button>
                </form>
            </div>
        </div>

        <!-- Groups List -->
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">Student Groups</h2>
            </div>
            <div class="card-body">
                <?php if ($groups->num_rows > 0): ?>
                    <div class="grid-2">
                        <?php while ($group = $groups->fetch_assoc()): ?>
                        <div class="session-card">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($group['group_name']); ?>
                                    </h3>
                                    <?php if ($group['description']): ?>
                                        <p style="color: #6b7280; font-size:0.875rem;">
                                            <?php echo htmlspecialchars($group['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <a href="?delete=<?php echo $group['id']; ?>" 
                                   onclick="return confirm('Delete this group? Members will not be deleted.')"
                                   class="btn-modern btn-danger" style="padding: 0.5rem 1rem;">
                                    🗑️
                                </a>
                            </div>
                            <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                <p style="color: #6b7280; font-size: 0.875rem;">Members</p>
                                <p style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                    <?php echo $group['member_count']; ?>
                                </p>
                            </div>
                            <a href="manage_group_members.php?group_id=<?php echo $group['id']; ?>" 
                               class="btn-modern btn-primary" style="width: 100%;">
                                👥 Manage Members
                            </a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <p style="font-size: 3rem; margin-bottom: 1rem;">📁</p>
                        <p>No groups yet. Create your first group above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>