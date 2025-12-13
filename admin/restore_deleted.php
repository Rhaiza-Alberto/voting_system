<?php
require_once '../config.php';
requireAdmin();

$conn = getDBConnection();
$adminId = $_SESSION['user_id'];

// Handle restore action
if (isset($_GET['restore']) && isset($_GET['table'])) {
    $id = intval($_GET['restore']);
    $table = $_GET['table'];
    
    $allowedTables = ['users', 'candidates', 'voting_sessions', 'votes', 'winners', 'positions', 'student_groups'];
    
    if (in_array($table, $allowedTables)) {
        $stmt = $conn->prepare("UPDATE $table SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $successMessage = 'Record restored successfully!';
        } else {
            $errorMessage = 'Failed to restore record.';
        }
        $stmt->close();
    }
}

// Get deleted records by table
$tables = [
    'users' => 'Deleted Students',
    'voting_sessions' => 'Deleted Sessions',
    'candidates' => 'Deleted Candidates',
    'student_groups' => 'Deleted Student Groups'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Deleted Records - VoteSystem Pro</title>
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

        .navbar-brand h1 {
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        /* Enhanced Cards */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.15), 0 10px 10px -5px rgba(16, 185, 129, 0.1);
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

        /* Intro Card */
        .intro-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
            border: 2px solid #d1fae5;
        }

        .intro-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .intro-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            flex-shrink: 0;
        }

        .intro-text h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .intro-text p {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        thead {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f0fdf4;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 1rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn-modern {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
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

        /* Badge */
        .record-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .deleted-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Info Box */
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .info-box p {
            color: #1e40af;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        .info-box strong {
            font-weight: 600;
        }

        /* Stats Badge */
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f0fdf4;
            border: 2px solid #d1fae5;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #059669;
            margin-left: 1rem;
        }

        .stats-number {
            background: #10b981;
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
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

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
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

            .intro-content {
                flex-direction: column;
                text-align: center;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.75rem 0.875rem;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }

            .stats-badge {
                margin-left: 0;
                margin-top: 0.5rem;
            }

            .card-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="modern-navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <h1>VoteSystem Pro</h1>
            </div>
            <a href="admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="modern-container">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success fade-in"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger fade-in"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <!-- Intro Card -->
        <div class="modern-card intro-card fade-in">
            <div class="card-body">
                <div class="intro-content">
                    <div class="intro-text">
                        <h2>Restore Deleted Records</h2>
                        <p>Review and restore previously deleted records from the system. All data relationships are preserved during restoration.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box fade-in" style="animation-delay: 0.1s;">
            <p><strong>Note:</strong> Restoring records will make them active again in the system. Ensure you want to restore the record before confirming the action.</p>
        </div>
        
        <?php
        $delay = 0.2;
        foreach ($tables as $table => $title) {
            $delay += 0.1;
            
            // Count deleted records
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE deleted_at IS NOT NULL");
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $recordCount = $countResult['count'];
            $countStmt->close();
            
            echo '<div class="modern-card fade-in" style="animation-delay: ' . $delay . 's;">';
            echo '<div class="card-header">';
            echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">';
            echo '<h2 class="card-title">' . htmlspecialchars($title) . '</h2>';
            if ($recordCount > 0) {
                echo '<span class="stats-badge"><span class="stats-number">' . $recordCount . '</span> Records Found</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="card-body">';
            
            $stmt = $conn->prepare("CALL sp_get_deleted_records(?)");
            $stmt->bind_param("s", $table);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                echo '<div style="overflow-x: auto;">';
                echo '<table>';
                echo '<thead><tr>';
                echo '<th>ID</th>';
                echo '<th>Information</th>';
                echo '<th>Deleted At</th>';
                echo '<th>Action</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['id']) . '</strong></td>';
                    
                    // Display relevant info based on table
                    if ($table == 'users') {
                        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        echo '<td>';
                        echo '<div style="font-weight: 600; color: #1f2937;">' . htmlspecialchars($name ?: 'N/A') . '</div>';
                        echo '<div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">Student ID: ' . htmlspecialchars($row['student_id'] ?? 'N/A') . '</div>';
                        echo '</td>';
                    } elseif ($table == 'voting_sessions') {
                        echo '<td>';
                        echo '<div style="font-weight: 600; color: #1f2937;">' . htmlspecialchars($row['session_name'] ?? 'N/A') . '</div>';
                        if (isset($row['status'])) {
                            echo '<span class="record-badge">' . strtoupper($row['status']) . '</span>';
                        }
                        echo '</td>';
                    } elseif ($table == 'candidates') {
                        echo '<td>';
                        echo '<div style="font-weight: 600; color: #1f2937;">Candidate Record</div>';
                        echo '<div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">ID: ' . htmlspecialchars($row['id']) . '</div>';
                        echo '</td>';
                    } else {
                        echo '<td>';
                        echo '<div style="font-weight: 600; color: #1f2937;">' . htmlspecialchars($row['group_name'] ?? 'N/A') . '</div>';
                        echo '</td>';
                    }
                    
                    echo '<td>';
                    if (isset($row['deleted_at'])) {
                        echo '<div style="color: #6b7280;">' . date('M d, Y', strtotime($row['deleted_at'])) . '</div>';
                        echo '<div style="color: #9ca3af; font-size: 0.875rem;">' . date('h:i A', strtotime($row['deleted_at'])) . '</div>';
                    } else {
                        echo 'N/A';
                    }
                    echo '</td>';
                    
                    echo '<td>';
                    echo '<a href="?restore=' . $row['id'] . '&table=' . urlencode($table) . '" 
                              class="btn-modern btn-primary" 
                              onclick="return confirm(\'Are you sure you want to restore this record?\')">Restore</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                echo '</div>';
            } else {
                echo '<div class="empty-state">';
                echo '<p class="empty-text">No deleted records found</p>';
                echo '<p style="color: #9ca3af; font-size: 0.875rem; margin-top: 0.5rem;">All records in this category are active</p>';
                echo '</div>';
            }
            
            $stmt->close();
            echo '</div></div>';
        }
        
        $conn->close();
        ?>
    </div>
</body>
</html>