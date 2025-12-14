<?php
require_once '../config.php';
require_once '../helper/email_helper.php';
requireAdmin();

$conn = getDBConnection();
$email = new EmailHelper();
$message = '';
$messageType = '';
$editMode = false;
$editStudent = null;

// Handle edit student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $userId = $_POST['user_id'];
    $studentId = trim($_POST['student_id']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $emailAddress = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($studentId) || empty($firstName) || empty($lastName) || empty($emailAddress)) {
        $message = 'Student ID, First Name, Last Name, and Email are required!';
        $messageType = 'error';
    } else {
        // Check if student ID already exists for a different user
        $checkQuery = "SELECT id FROM users WHERE student_id = ? AND id != ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("si", $studentId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Student ID already exists for another student!';
            $messageType = 'error';
        } else {
            // Update student with or without password change
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET student_id = ?, first_name = ?, 
                                             last_name = ?, middle_name = ?, email = ?, password = ? 
                                             WHERE id = ?");
                $updateStmt->bind_param("ssssssi", $studentId, $firstName, $lastName, 
                                       $middleName, $emailAddress, $hashedPassword, $userId);
            } else {
                $updateStmt = $conn->prepare("UPDATE users SET student_id = ?, first_name = ?, 
                                             last_name = ?, middle_name = ?, email = ? 
                                             WHERE id = ?");
                $updateStmt->bind_param("sssssi", $studentId, $firstName, $lastName, 
                                       $middleName, $emailAddress, $userId);
            }
            
            if ($updateStmt->execute()) {
                $message = 'Student updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to update student. Please try again.';
                $messageType = 'error';
            }
            $updateStmt->close();
        }
        $stmt->close();
    }
}

// Handle add new student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $studentId = trim($_POST['student_id']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $emailAddress = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($studentId) || empty($firstName) || empty($lastName) || empty($emailAddress) || empty($password)) {
        $message = 'Student ID, First Name, Last Name, Email, and Password are required!';
        $messageType = 'error';
    } else {
        // Check if student ID already exists
        $checkQuery = "SELECT id FROM users WHERE student_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Student ID already exists!';
            $messageType = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            
            // Check if email already exists
            $checkEmailQuery = "SELECT id FROM users WHERE email = ?";
            $emailStmt = $conn->prepare($checkEmailQuery);
            $emailStmt->bind_param("s", $emailAddress);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                $message = 'Email address already exists! Please use a different email.';
                $messageType = 'error';
                $emailStmt->close();
            } else {
                $emailStmt->close();
                
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new student - ADMIN ADDED = AUTO VERIFIED
                $insertStmt = $conn->prepare("INSERT INTO users 
                    (student_id, first_name, last_name, middle_name, email, password, 
                     role, email_verified, registration_method) 
                    VALUES (?, ?, ?, ?, ?, ?, 'student', 1, 'admin_added')");
                $insertStmt->bind_param("ssssss", $studentId, $firstName, $lastName, 
                                       $middleName, $emailAddress, $hashedPassword);
                
                if ($insertStmt->execute()) {
                    // Send welcome email with credentials
                    $studentFullName = formatStudentName($firstName, $middleName, $lastName);
                    $emailSent = $email->sendWelcomeEmail($emailAddress, $studentFullName, 
                                                         $studentId, $password);
                    
                    if ($emailSent) {
                        $message = 'Student added successfully! Welcome email sent to ' . 
                                  htmlspecialchars($emailAddress) . '. Account is pre-verified.';
                        $messageType = 'success';
                    } else {
                        $message = 'Student added successfully! However, the welcome email could not be sent. Account is pre-verified. Please inform the student manually.';
                        $messageType = 'warning';
                    }
                } else {
                    $message = 'Failed to add student. Please try again.';
                    $messageType = 'error';
                }
                $insertStmt->close();
            }
        }
    }
}

// Handle deactivate/reactivate student (soft delete)
if (isset($_GET['toggle_status'])) {
    $userId = $_GET['toggle_status'];
    $adminId = $_SESSION['user_id'];
    
    // Get current status
    $statusStmt = $conn->prepare("SELECT is_active, first_name, last_name FROM users WHERE id = ? AND role = 'student'");
    $statusStmt->bind_param("i", $userId);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    
    if ($statusResult->num_rows > 0) {
        $student = $statusResult->fetch_assoc();
        $currentStatus = $student['is_active'];
        $newStatus = $currentStatus ? 0 : 1;
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        
        if ($newStatus == 0) {
            // Deactivating - also set deleted_at
            $updateStmt = $conn->prepare("UPDATE users SET is_active = 0, deactivated_at = NOW(), deactivated_by = ?, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $updateStmt->bind_param("iii", $adminId, $adminId, $userId);
        } else {
            // Reactivating - clear both deactivated_at AND deleted_at
            $updateStmt = $conn->prepare("UPDATE users SET is_active = 1, deactivated_at = NULL, deactivated_by = NULL, deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $updateStmt->bind_param("i", $userId);
        }
        
        if ($updateStmt->execute()) {
            if ($newStatus == 0) {
                $message = 'Student "' . htmlspecialchars($studentName) . '" has been deactivated. They can no longer log in.';
                $messageType = 'success';
            } else {
                $message = 'Student "' . htmlspecialchars($studentName) . '" has been reactivated. They can now log in again.';
                $messageType = 'success';
            }
        } else {
            $message = 'Failed to update student status.';
            $messageType = 'error';
        }
        $updateStmt->close();
    }
    $statusStmt->close();
}

// Handle permanent delete (only for inactive students with no data)
if (isset($_GET['delete'])) {
    $userId = $_GET['delete'];
    
    // Check if student is inactive
    $checkInactiveStmt = $conn->prepare("SELECT is_active FROM users WHERE id = ? AND role = 'student'");
    $checkInactiveStmt->bind_param("i", $userId);
    $checkInactiveStmt->execute();
    $inactiveResult = $checkInactiveStmt->get_result();
    $userData = $inactiveResult->fetch_assoc();
    $checkInactiveStmt->close();
    
    if ($userData && $userData['is_active'] == 1) {
        $message = 'Cannot delete active student! Please deactivate the student first.';
        $messageType = 'error';
    } else {
        // Check if student has votes or candidacies
        $checkVotesStmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE voter_id = ?");
        $checkVotesStmt->bind_param("i", $userId);
        $checkVotesStmt->execute();
        $voteResult = $checkVotesStmt->get_result();
        $voteData = $voteResult->fetch_assoc();
        $checkVotesStmt->close();
        
        $checkCandidateStmt = $conn->prepare("SELECT COUNT(*) as candidate_count FROM candidates WHERE user_id = ?");
        $checkCandidateStmt->bind_param("i", $userId);
        $checkCandidateStmt->execute();
        $candidateResult = $checkCandidateStmt->get_result();
        $candidateData = $candidateResult->fetch_assoc();
        $checkCandidateStmt->close();
        
        if ($voteData['vote_count'] > 0 || $candidateData['candidate_count'] > 0) {
            $issues = [];
            if ($voteData['vote_count'] > 0) {
                $issues[] = $voteData['vote_count'] . ' vote(s)';
            }
            if ($candidateData['candidate_count'] > 0) {
                $issues[] = $candidateData['candidate_count'] . ' candidacy/candidacies';
            }
            
            $message = 'Cannot permanently delete! This student has historical data: ' . implode(' and ', $issues) . '. Keep them deactivated to preserve voting records.';
            $messageType = 'error';
        } else {
            // Safe to permanently delete - no votes or candidacies
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $deleteStmt->bind_param("i", $userId);
            
            if ($deleteStmt->execute()) {
                $message = 'Student permanently deleted from database.';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete student.';
                $messageType = 'error';
            }
            $deleteStmt->close();
        }
    }
}

// Check if we're in edit mode
if (isset($_GET['edit'])) {
    $editMode = true;
    $editId = $_GET['edit'];
    $editStmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $editStmt->bind_param("i", $editId);
    $editStmt->execute();
    $editStudent = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

// Get all students with computed full_name and verification status
$studentsQuery = "SELECT 
                    id, 
                    student_id, 
                    first_name, 
                    middle_name, 
                    last_name,
                    TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                    email,
                    email_verified,
                    registration_method,
                    is_active,
                    deactivated_at,
                    deleted_at,
                    created_at
                  FROM users 
                  WHERE role = 'student' AND deleted_at IS NULL
                  ORDER BY is_active DESC, last_name, first_name";
$students = $conn->query($studentsQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - VoteSystem Pro</title>
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

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #a7f3d0;
        }

        .alert.warning {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fde68a;
        }

        .alert.info {
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 0;
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

        .required {
            color: #ef4444;
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

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.4);
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

        .inactive-row {
            background: #fef3c7 !important;
            opacity: 0.8;
        }

        .inactive-row:hover {
            background: #fef3c7 !important;
        }

        /* Badge Styles */
        .badge {
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

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-method {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: nowrap;
            align-items: center;
        }

        .action-buttons .btn-modern {
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Info Banner */
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
        }

        .info-banner strong {
            color: #1e40af;
            display: block;
            margin-bottom: 0.5rem;
        }

        .info-banner a {
            color: #2563eb;
            text-decoration: underline;
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

            .form-grid {
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
                min-width: 900px;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
                min-width: 120px;
            }

            .action-buttons .btn-modern {
                width: 100%;
                justify-content: center;
                padding: 0.5rem 1rem;
                font-size: 0.813rem;
            }
        }

        @media (max-width: 1024px) {
            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.875rem 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons .btn-modern {
                width: 100%;
                justify-content: center;
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
                    <p>Manage Students</p>
                </div>
            </div>
            <a href="../admin/admin_dashboard.php" class="btn-modern btn-secondary">Back to Dashboard</a>
        </div>
    </nav>

    <div class="modern-container">
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?> fade-in">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($editMode && $editStudent): ?>
        <!-- Edit Student Form -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">Edit Student</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="user_id" value="<?php echo $editStudent['id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Student ID <span class="required">*</span></label>
                            <input type="text" name="student_id" class="form-input"
                                   value="<?php echo htmlspecialchars($editStudent['student_id']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-input"
                                   value="<?php echo htmlspecialchars($editStudent['first_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-input"
                                   value="<?php echo htmlspecialchars($editStudent['middle_name'] ?? ''); ?>"
                                   placeholder="Optional">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-input"
                                   value="<?php echo htmlspecialchars($editStudent['last_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-input"
                                   value="<?php echo htmlspecialchars($editStudent['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-input"
                                   placeholder="Leave blank to keep current">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <button type="submit" name="edit_student" class="btn-modern btn-primary">
                            Save Changes
                        </button>
                        <a href="manage_students.php" class="btn-modern btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Add New Student Form -->
        <div class="modern-card fade-in">
            <div class="card-header">
                <h2 class="card-title">Add New Student</h2>
            </div>
            <div class="card-body">
                <div class="info-banner">
                    <strong>Auto-Verification</strong>
                    Students added by admin are automatically verified and can login immediately. They will receive a welcome email with their credentials.
                </div>

                <div class="info-banner">
                    <strong>Alternative Registration</strong>
                    Students can also self-register at <a href="../register.php" target="_blank">register.php</a> using their WMSU email and will need to verify their email before logging in.
                </div>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Student ID <span class="required">*</span></label>
                            <input type="text" name="student_id" class="form-input"
                                   placeholder="e.g., 202401234" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-input"
                                   placeholder="e.g., John" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-input"
                                   placeholder="Optional">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-input"
                                   placeholder="e.g., Doe" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-input"
                                   placeholder="e.g., john@example.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <input type="password" name="password" class="form-input"
                                   placeholder="Create password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_student" class="btn-modern btn-primary">
                        Add Student (Auto-Verified)
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Students List -->
        <div class="modern-card fade-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h2 class="card-title">Registered Students</h2>
            </div>
            <div class="card-body">
                <?php if ($students->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Email Verified</th>
                                    <th>Registration</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($student = $students->fetch_assoc()): ?>
                                    <tr class="<?php echo $student['is_active'] ? '' : 'inactive-row'; ?>">
                                        <td>
                                            <?php if ($student['is_active']): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td>
                                            <?php if ($student['email_verified']): ?>
                                                <span class="badge badge-verified">Verified</span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-method">
                                                <?php echo $student['registration_method'] === 'admin_added' ? 'Admin' : 'Self'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($student['is_active']): ?>
                                                    <a href="?edit=<?php echo $student['id']; ?>" class="btn-modern btn-warning">
                                                        Edit
                                                    </a>
                                                    <a href="?toggle_status=<?php echo $student['id']; ?>" 
                                                       class="btn-modern btn-warning" 
                                                       onclick="return confirm('Deactivate this student?\n\nThey will NOT be deleted, just prevented from logging in.\n\nYou can reactivate them later.')">
                                                        Deactivate
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?toggle_status=<?php echo $student['id']; ?>" 
                                                       class="btn-modern btn-success" 
                                                       onclick="return confirm('Reactivate this student?\n\nThey will be able to log in again.')">
                                                        Reactivate
                                                    </a>
                                                    <a href="?delete=<?php echo $student['id']; ?>" 
                                                       class="btn-modern btn-danger" 
                                                       onclick="return confirm('PERMANENTLY DELETE?\n\nThis will remove the student from the database entirely.\n\nOnly works if they have no votes or candidacies.\n\nAre you sure?')">
                                                        Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #6b7280; padding: 3rem;">
                        No students registered yet. Add your first student using the form above.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>