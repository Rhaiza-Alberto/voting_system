<?php
require_once '../config.php';
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

// Handle quick position add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_position'])) {
    $positionName = trim($_POST['position_name']);
    $positionOrder = intval($_POST['position_order']);
    
    if (!empty($positionName) && $positionOrder > 0) {
        $stmt = $conn->prepare("INSERT INTO positions (position_name, position_order) VALUES (?, ?)");
        $stmt->bind_param("si", $positionName, $positionOrder);
        if ($stmt->execute()) {
            $success = 'Position "' . htmlspecialchars($positionName) . '" added successfully!';
        } else {
            $error = 'Failed to add position. Order number might already exist.';
        }
        $stmt->close();
    } else {
        $error = 'Please provide valid position name and order.';
    }
}

// Handle quick position edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_edit_position'])) {
    $positionId = intval($_POST['position_id']);
    $positionName = trim($_POST['edit_position_name']);
    $positionOrder = intval($_POST['edit_position_order']);
    
    if (!empty($positionName) && $positionOrder > 0) {
        $stmt = $conn->prepare("UPDATE positions SET position_name = ?, position_order = ? WHERE id = ?");
        $stmt->bind_param("sii", $positionName, $positionOrder, $positionId);
        if ($stmt->execute()) {
            $success = 'Position updated successfully!';
        } else {
            $error = 'Failed to update position.';
        }
        $stmt->close();
    }
}

// Handle quick position delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_delete_position'])) {
    $positionId = intval($_POST['position_id']);
    
    // Check if position has candidates
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE position_id = ?");
    $checkStmt->bind_param("i", $positionId);
    $checkStmt->execute();
    $count = $checkStmt->get_result()->fetch_assoc()['count'];
    $checkStmt->close();
    
    if ($count > 0) {
        $error = 'Cannot delete position with existing candidates. Remove candidates first.';
    } else {
        $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
        $stmt->bind_param("i", $positionId);
        if ($stmt->execute()) {
            $success = 'Position deleted successfully!';
        } else {
            $error = 'Failed to delete position.';
        }
        $stmt->close();
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

// Get next available order number
$nextOrderQuery = "SELECT COALESCE(MAX(position_order), 0) + 1 as next_order FROM positions";
$nextOrder = $conn->query($nextOrderQuery)->fetch_assoc()['next_order'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Voting Session - VoteSystem Pro</title>
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

        .alert-danger {
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

        .alert h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .alert ul {
            margin-top: 0.5rem;
            margin-left: 1.5rem;
        }

        .alert li {
            margin: 0.25rem 0;
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

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-textarea {
            resize: vertical;
            font-family: inherit;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
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

        .btn-success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        /* Quick Add Section */
        .quick-add-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #d1fae5;
        }

        .quick-add-section h3 {
            color: #1f2937;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .quick-add-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        /* Position Card */
        .position-card {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .position-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.15);
            transform: translateY(-2px);
        }
        
        .position-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .position-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
            font-size: 1.125rem;
        }

        .position-order {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #d1fae5;
            color: #065f46;
        }
        
        .position-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .btn-icon {
            flex: 1;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .modal-header h3 {
            font-size: 1.5rem;
            color: #111827;
            font-weight: 700;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .btn-close:hover {
            background: #f3f4f6;
            color: #111827;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
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

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .grid-3 {
                grid-template-columns: 1fr;
            }

            .quick-add-form {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 1.5rem;
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
                    <p>Create Voting Session</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($error): ?>
            <div class="alert alert-danger fade-in"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success fade-in">
                <?php echo htmlspecialchars($success); ?>
                <?php if (isset($_POST['create_session'])): ?>
                <div style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="../admin/manage_candidates.php" class="btn-modern btn-primary">Nominate Candidates</a>
                    <a href="../admin/manage_session.php" class="btn-modern btn-success">Manage Session</a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($oldCandidatesCount > 0): ?>
        <div class="alert alert-warning fade-in">
            <h3>Old Candidates Detected</h3>
            <p>There are <strong><?php echo $oldCandidatesCount; ?> candidates</strong> from previous sessions. Clean them up before creating a new session.</p>
            <form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('Cleanup old candidates? Vote records will be preserved.');">
                <button type="submit" name="cleanup_candidates" class="btn-modern btn-warning">
                    Cleanup Old Candidates
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Session Creation Form -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">New Voting Session</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info" style="margin-bottom: 2rem;">
                    <strong>Key Features</strong>
                    <ul>
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
                        <strong>Next Steps After Creation</strong>
                        <ol style="margin-top: 0.5rem; margin-left: 1.5rem;">
                            <li>Nominate candidates for each position</li>
                            <li>Open voting for the first position</li>
                            <li>Close position and determine winner</li>
                            <li>Repeat for remaining positions</li>
                            <li>Lock session when complete</li>
                        </ol>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                        <button type="submit" name="create_session" class="btn-modern btn-primary">
                            Create Session
                        </button>
                        <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Add Position Section -->
        <div class="quick-add-section fade-in" style="animation-delay: 0.1s;">
            <h3>Quick Add Position</h3>
            <form method="POST" class="quick-add-form">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="position_name" class="form-label">Position Name</label>
                    <input type="text" id="position_name" name="position_name" class="form-input" 
                           placeholder="e.g., President" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="position_order" class="form-label">Priority Order</label>
                    <input type="number" id="position_order" name="position_order" class="form-input" 
                           value="<?php echo $nextOrder; ?>" min="1" required>
                </div>
                <button type="submit" name="quick_add_position" class="btn-modern btn-primary" 
                        style="white-space: nowrap; align-self: end;">
                    Add Position
                </button>
            </form>
        </div>

        <!-- Available Positions -->
        <div class="modern-card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h2 class="card-title">Available Positions</h2>
                <a href="manage_positions.php" class="btn-modern btn-secondary">
                    Advanced Management
                </a>
            </div>
            <div class="card-body">
                <?php if ($positions->num_rows > 0): ?>
                    <div class="grid-3">
                        <?php 
                        $positions->data_seek(0);
                        while ($pos = $positions->fetch_assoc()): 
                        ?>
                        <div class="position-card">
                            <div class="position-header">
                                <div>
                                    <p class="position-title">
                                        <?php echo htmlspecialchars($pos['position_name']); ?>
                                    </p>
                                    <p class="position-order">
                                        Priority #<?php echo $pos['position_order']; ?>
                                    </p>
                                </div>
                                <span class="status-badge">
                                    Active
                                </span>
                            </div>
                            <div class="position-actions">
                                <button onclick="openEditModal(<?php echo $pos['id']; ?>, '<?php echo htmlspecialchars($pos['position_name'], ENT_QUOTES); ?>', <?php echo $pos['position_order']; ?>)" 
                                        class="btn-icon btn-edit">
                                    Edit
                                </button>
                                <form method="POST" style="flex: 1; display: inline;" 
                                      onsubmit="return confirm('Delete <?php echo htmlspecialchars($pos['position_name']); ?>?\n\nThis will remove all candidates and votes for this position.\n\nThis cannot be undone!');">
                                    <input type="hidden" name="position_id" value="<?php echo $pos['id']; ?>">
                                    <button type="submit" name="quick_delete_position" class="btn-icon btn-delete" style="width: 100%;">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3 class="empty-title">No positions created yet</h3>
                        <p class="empty-description">Use the quick add form above to create your first position!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Position Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Position</h3>
                <button type="button" class="btn-close" onclick="closeEditModal()">×</button>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_position_id" name="position_id">
                <div class="form-group">
                    <label class="form-label">Position Name</label>
                    <input type="text" id="edit_position_name" name="edit_position_name" 
                           class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="edit_position_order" class="form-label">Position Order</label>
                    <input type="number" id="edit_position_order" name="edit_position_order" 
                           class="form-input" min="1" required>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="quick_edit_position" class="btn-modern btn-primary">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn-modern btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, order) {
            document.getElementById('edit_position_id').value = id;
            document.getElementById('edit_position_name').value = name;
            document.getElementById('edit_position_order').value = order;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>