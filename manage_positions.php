<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$messageType = '';
$editMode = false;
$editPosition = null;

// Handle edit position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_position'])) {
    $positionId = $_POST['position_id'];
    $positionName = trim($_POST['position_name']);
    $positionOrder = $_POST['position_order'];
    
    if (!empty($positionName) && !empty($positionOrder)) {
        $stmt = $conn->prepare("UPDATE positions SET position_name = ?, position_order = ? WHERE id = ?");
        $stmt->bind_param("sii", $positionName, $positionOrder, $positionId);
        if ($stmt->execute()) {
            $message = 'Position updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to update position.';
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Handle add position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    $positionName = trim($_POST['position_name']);
    $positionOrder = $_POST['position_order'];
    
    if (!empty($positionName) && !empty($positionOrder)) {
        $stmt = $conn->prepare("INSERT INTO positions (position_name, position_order) VALUES (?, ?)");
        $stmt->bind_param("si", $positionName, $positionOrder);
        if ($stmt->execute()) {
            $message = 'Position added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to add position.';
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Handle delete position
if (isset($_GET['delete'])) {
    $positionId = $_GET['delete'];
    $deleteStmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
    $deleteStmt->bind_param("i", $positionId);
    if ($deleteStmt->execute()) {
        $message = 'Position deleted successfully!';
        $messageType = 'success';
    } else {
        $message = 'Failed to delete position.';
        $messageType = 'error';
    }
    $deleteStmt->close();
}

// Check if we're in edit mode
if (isset($_GET['edit'])) {
    $editMode = true;
    $editId = $_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM positions WHERE id = ?");
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editPosition = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

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
    <title>Manage Positions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 1.5em;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #10b981;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
            padding: 8px 16px;
            font-size: 0.9em;
        }
        
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
            padding: 8px 16px;
            font-size: 0.9em;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
            padding: 8px 16px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #a0aec0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f7fafc;
            color: #10b981;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f7fafc;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .edit-highlight {
            background: #fef3c7 !important;
            border-left: 4px solid #f59e0b;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Manage Positions</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($editMode && $editPosition): ?>
        <!-- Edit Position Form -->
        <div class="card">
            <h2> Edit Position</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="position_id" value="<?php echo $editPosition['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="position_name">Position Name</label>
                        <input type="text" id="position_name" name="position_name" 
                               value="<?php echo htmlspecialchars($editPosition['position_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="position_order">Order</label>
                        <input type="number" id="position_order" name="position_order" 
                               value="<?php echo $editPosition['position_order']; ?>" min="1" required>
                    </div>
                    
                    <div>
                        <button type="submit" name="edit_position" class="btn btn-primary"> Save Changes</button>
                    </div>
                </div>
                
                <a href="manage_positions.php" class="btn btn-secondary" style="margin-top: 15px;">Cancel</a>
            </form>
        </div>
        <?php else: ?>
        <!-- Add New Position Form -->
        <div class="card">
            <h2> Add New Position</h2>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="position_name">Position Name</label>
                        <input type="text" id="position_name" name="position_name" 
                               placeholder="e.g., President" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="position_order">Order</label>
                        <input type="number" id="position_order" name="position_order" 
                               placeholder="1" min="1" required>
                    </div>
                    
                    <button type="submit" name="add_position" class="btn btn-primary">Add Position</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Current Positions</h2>
            
            <?php if ($positions->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Position Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($position = $positions->fetch_assoc()): ?>
                            <tr class="<?php echo ($editMode && $editPosition && $position['id'] == $editPosition['id']) ? 'edit-highlight' : ''; ?>">
                                <td><?php echo $position['position_order']; ?></td>
                                <td><?php echo htmlspecialchars($position['position_name']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $position['id']; ?>" class="btn btn-warning">
                                             Edit
                                        </a>
                                        <a href="?delete=<?php echo $position['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm(' Delete this position?\n\nThis will also remove:\n- All candidates for this position\n- Associated votes\n\nThis action cannot be undone!')">
                                             Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No positions created yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>