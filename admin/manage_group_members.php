<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';

// Get group ID
if (!isset($_GET['group_id'])) {
    header('Location: manage_groups.php');
    exit();
}

$groupId = intval($_GET['group_id']);

// Get group info
$stmt = $conn->prepare("SELECT * FROM student_groups WHERE id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    header('Location: manage_groups.php');
    exit();
}

// Handle add member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $userId = intval($_POST['user_id']);
    
    $stmt = $conn->prepare("INSERT IGNORE INTO student_group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $groupId, $userId);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = 'Member added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Student is already a member of this group.';
            $messageType = 'warning';
        }
    } else {
        $message = 'Failed to add member.';
        $messageType = 'danger';
    }
    $stmt->close();
}

// Handle remove member
if (isset($_GET['remove'])) {
    $userId = intval($_GET['remove']);
    $stmt = $conn->prepare("DELETE FROM student_group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $groupId, $userId);
    if ($stmt->execute()) {
        $message = 'Member removed successfully!';
        $messageType = 'success';
    }
    $stmt->close();
}

// Get current members
$membersQuery = "SELECT u.id, u.student_id, u.first_name, u.middle_name, u.last_name, u.email, sgm.added_at
                 FROM student_group_members sgm
                 JOIN users u ON sgm.user_id = u.id
                 WHERE sgm.group_id = ?
                 ORDER BY u.last_name, u.first_name";
$stmt = $conn->prepare($membersQuery);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$members = $stmt->get_result();
$stmt->close();

// Get available students (not in this group)
$availableQuery = "SELECT u.id, u.student_id, u.first_name, u.middle_name, u.last_name
                   FROM users u
                   WHERE u.role = 'student'
                   AND u.id NOT IN (
                       SELECT user_id FROM student_group_members WHERE group_id = ?
                   )
                   ORDER BY u.last_name, u.first_name";
$stmt = $conn->prepare($availableQuery);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$availableStudents = $stmt->get_result();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Group Members - <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <div class="brand-icon">👥</div>
                <div class="brand-text">
                    <h1><?php echo htmlspecialchars($group['group_name']); ?></h1>
                    <p>Manage group members</p>
                </div>
            </div>
            <a href="manage_groups.php" class="btn-modern btn-secondary">← Back to Groups</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Member Form -->
        <div class="modern-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h2 class="card-title">Add Member</h2>
            </div>
            <div class="card-body">
                <?php if ($availableStudents->num_rows > 0): ?>
                    <form method="POST">
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Select Student</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Choose a student...</option>
                                    <?php while ($student = $availableStudents->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php 
                                            $name = formatStudentName($student['first_name'], $student['middle_name'], $student['last_name']);
                                            echo htmlspecialchars($student['student_id'] . ' - ' . $name); 
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="submit" name="add_member" class="btn-modern btn-primary">
                                    ➕ Add Member
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        All students are already members of this group.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Members -->
        <div class="modern-card">
            <div class="card-header">
                <h2 class="card-title">Current Members (<?php echo $members->num_rows; ?>)</h2>
            </div>
            <div class="card-body">
                <?php if ($members->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $members->data_seek(0);
                            while ($member = $members->fetch_assoc()): 
                                $name = formatStudentName($member['first_name'], $member['middle_name'], $member['last_name']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($member['added_at'])); ?></td>
                                <td>
                                    <a href="?group_id=<?php echo $groupId; ?>&remove=<?php echo $member['id']; ?>" 
                                       onclick="return confirm('Remove this member from the group?')"
                                       class="btn-modern btn-danger" style="padding: 0.5rem 1rem;">
                                        Remove
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #6b7280;">
                        <p style="font-size: 3rem; margin-bottom: 1rem;">👥</p>
                        <p>No members in this group yet. Add students above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>