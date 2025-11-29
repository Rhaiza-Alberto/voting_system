<?php
require_once 'config.php';
require_once 'email_helper.php';
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
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email)) {
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
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                // Update with new password (NO full_name column)
                $updateStmt = $conn->prepare("UPDATE users SET student_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, password = ? WHERE id = ?");
                $updateStmt->bind_param("ssssssi", $studentId, $firstName, $lastName, $middleName, $email, $hashedPassword, $userId);
            } else {
                // Update without changing password (NO full_name column)
                $updateStmt = $conn->prepare("UPDATE users SET student_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ? WHERE id = ?");
                $updateStmt->bind_param("sssssi", $studentId, $firstName, $lastName, $middleName, $email, $userId);
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
                // Insert new student (NO full_name column)
                $insertStmt = $conn->prepare("INSERT INTO users (student_id, first_name, last_name, middle_name, email, password, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
                $insertStmt->bind_param("ssssss", $studentId, $firstName, $lastName, $middleName, $emailAddress, $hashedPassword);
                
                if ($insertStmt->execute()) {
                    // Send welcome email with credentials
                    $studentFullName = formatStudentName($firstName, $middleName, $lastName);
                    $emailSent = $email->sendWelcomeEmail($emailAddress, $studentFullName, $studentId, $password);
                    
                    if ($emailSent) {
                        $message = ' Student added successfully! Welcome email sent to ' . htmlspecialchars($emailAddress);
                        $messageType = 'success';
                    } else {
                        $message = ' Student added successfully!  However, the welcome email could not be sent. Please inform the student manually.';
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
// Handle delete student
if (isset($_GET['delete'])) {
    $userId = $_GET['delete'];
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $deleteStmt->bind_param("i", $userId);
    
    if ($deleteStmt->execute()) {
        $message = 'Student removed successfully!';
        $messageType = 'success';
    } else {
        $message = 'Failed to remove student.';
        $messageType = 'error';
    }
    $deleteStmt->close();
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

// Get all students with computed full_name (3NF compliant)
$studentsQuery = "SELECT 
                    id, 
                    student_id, 
                    first_name, 
                    middle_name, 
                    last_name,
                    TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS full_name,
                    email, 
                    created_at
                  FROM users 
                  WHERE role = 'student' 
                  ORDER BY last_name, first_name";
$students = $conn->query($studentsQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
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

        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        label .required {
            color: #e53e3e;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
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
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
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
        
        .info-banner {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-banner strong {
            color: #1e40af;
        }
        
        .edit-highlight {
            background: #fef3c7 !important;
            border-left: 4px solid #f59e0b;
        }
        
        .password-note {
            color: #666;
            font-size: 0.9em;
            font-style: italic;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 8px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1> Manage Students</h1>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($editMode && $editStudent): ?>
        <!-- Edit Student Form -->
        <div class="card">
            <h2> Edit Student</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="user_id" value="<?php echo $editStudent['id']; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student ID <span class="required">*</span></label>
                        <input type="text" id="student_id" name="student_id" 
                               value="<?php echo htmlspecialchars($editStudent['student_id']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($editStudent['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" 
                               value="<?php echo htmlspecialchars($editStudent['middle_name'] ?? ''); ?>"
                               placeholder="Optional">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($editStudent['last_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($editStudent['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Leave blank to keep current">
                        <p class="password-note">Leave blank to keep the current password</p>
                    </div>
                </div>
                
                <button type="submit" name="edit_student" class="btn btn-primary">
                     Save Changes
                </button>
                <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
        <?php else: ?>
        <!-- Add New Student Form -->
        <div class="card">
            <h2>Add New Student</h2>
            
            <div class="info-banner">
                <strong> Automatic Welcome Email:</strong> When you add a student, they will automatically receive a welcome email with their login credentials at the email address you provide below.
            </div>

            <div class="info-banner">
                <strong> Security Note:</strong> The student will receive their temporary password via email. Please advise them to change it after first login for security.
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_id">Student ID <span class="required">*</span></label>
                        <input type="text" id="student_id" name="student_id" 
                               placeholder="e.g., 2024001" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" 
                               placeholder="e.g., John" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" 
                               placeholder="Optional">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" 
                               placeholder="e.g., Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" 
                               placeholder="e.g., john@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" 
                               placeholder="Create password" required>
                    </div>
                </div>
                
                <button type="submit" name="add_student" class="btn btn-primary">
                     Add Student
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2> Registered Students</h2>
            
            <?php if ($students->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students->fetch_assoc()): ?>
                            <tr class="<?php echo ($editMode && $editStudent && $student['id'] == $editStudent['id']) ? 'edit-highlight' : ''; ?>">
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?php echo $student['id']; ?>" class="btn btn-warning">
                                             Edit
                                        </a>
                                        <a href="?delete=<?php echo $student['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm(' Remove this student?\n\nThis will also remove:\n- Their votes\n- Their candidacies\n\nThis action cannot be undone!')">
                                             Remove
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No students registered yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>