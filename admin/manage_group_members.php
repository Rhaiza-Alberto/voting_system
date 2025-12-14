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
        $messageType = 'error';
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
    <title>Manage Group Members - <?php echo htmlspecialchars($group['group_name']); ?> - VoteSystem Pro</title>
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

        /* Alert Styles */
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #a7f3d0;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #bfdbfe;
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

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1.5rem;
            align-items: end;
        }

        /* Enhanced Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            white-space: nowrap;
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
            padding: 0.625rem 1.25rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }

        tr:hover {
            background: #f9fafb;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: #6b7280;
            font-size: 1rem;
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

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1.5rem;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                font-size: 0.875rem;
                min-width: 700px;
            }

            th, td {
                padding: 0.75rem 0.5rem;
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
                    <h1><?php echo htmlspecialchars($group['group_name']); ?></h1>
                    <p>Manage Group Members</p>
                </div>
            </div>
            <a href="manage_groups.php" class="btn-modern btn-secondary">Back to Groups</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Member Form -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">Add Member</h2>
            </div>
            <div class="card-body">
                <?php if ($availableStudents->num_rows > 0): ?>
                    <form method="POST">
                        <div class="grid-2">
                            <div class="form-group" style="margin-bottom: 0;">
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
                            <button type="submit" name="add_member" class="btn-modern btn-primary">
                                Add Member
                            </button>
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
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h2 class="card-title">Current Members (<?php echo $members->num_rows; ?>)</h2>
            </div>
            <div class="card-body">
                <?php if ($members->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Added</th>
                                    <th>Action</th>
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
                                           class="btn-modern btn-danger">
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3 class="empty-title">No members in this group yet</h3>
                        <p class="empty-description">Add students using the form above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>