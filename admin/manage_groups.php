<?php
require_once '../config.php';
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
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Handle delete group
if (isset($_GET['delete'])) {
    $groupId = $_GET['delete'];
    $adminId = $_SESSION['user_id'];
    
    // Soft delete using stored procedure
    $stmt = $conn->prepare("CALL sp_soft_delete_group(?, ?)");
    $stmt->bind_param("ii", $groupId, $adminId);
    
    if ($stmt->execute()) {
        $message = 'Group soft deleted successfully!';
        $messageType = 'success';
    }
    $stmt->close();
}

// Get all groups with member counts
$groupsQuery = "SELECT sg.*, 
                COUNT(sgm.user_id) as member_count 
                FROM student_groups sg 
                LEFT JOIN student_group_members sgm ON sg.id = sgm.group_id AND sgm.deleted_at IS NULL
                WHERE sg.deleted_at IS NULL
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
    <title>Manage Student Groups - VoteSystem Pro</title>
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

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .grid-groups {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
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
            padding: 0.5rem 1rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        /* Group Card */
        .group-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.3s ease;
        }

        .group-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
            transform: translateY(-2px);
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            gap: 1rem;
        }

        .group-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .group-description {
            color: #6b7280;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .member-count-box {
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1.25rem;
            border: 2px solid #e5e7eb;
        }

        .member-count-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .member-count-value {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
            line-height: 1;
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

            .grid-groups {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1.5rem;
            }

            .group-card {
                padding: 1.5rem;
            }

            .group-header {
                flex-direction: column;
            }

            .btn-danger {
                width: 100%;
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
                    <p>Student Groups</p>
                </div>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Create Group Form -->
        <div class="modern-card fade-in">
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
                        Create Group
                    </button>
                </form>
            </div>
        </div>

        <!-- Groups List -->
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h2 class="card-title">Student Groups</h2>
            </div>
            <div class="card-body">
                <?php if ($groups->num_rows > 0): ?>
                    <div class="grid-groups">
                        <?php while ($group = $groups->fetch_assoc()): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <div class="group-info">
                                    <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <?php if ($group['description']): ?>
                                        <p class="group-description">
                                            <?php echo htmlspecialchars($group['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <a href="?delete=<?php echo $group['id']; ?>" 
                                   onclick="return confirm('Delete this group? Members will not be deleted.')"
                                   class="btn-modern btn-danger">
                                    Delete
                                </a>
                            </div>
                            
                            <div class="member-count-box">
                                <p class="member-count-label">Total Members</p>
                                <p class="member-count-value">
                                    <?php echo $group['member_count']; ?>
                                </p>
                            </div>
                            
                            <a href="manage_group_members.php?group_id=<?php echo $group['id']; ?>" 
                               class="btn-modern btn-primary" style="width: 100%;">
                                Manage Members
                            </a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3 class="empty-title">No groups yet</h3>
                        <p class="empty-description">Create your first group using the form above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>