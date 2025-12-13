<?php
require_once '../config.php';
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
