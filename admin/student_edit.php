<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
requireAdmin();

// Initialize variables
$id = '';
$student_number = '';
$name = '';
$email = '';
$class = '';
$password = '';
$confirm_password = '';
$isEdit = false;
$error = '';
$success = '';

// Check if this is an edit request
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $isEdit = true;
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $student = $result->fetch_assoc();
        $student_number = $student['student_number'];
        $name = $student['name'];
        $email = $student['email'];
        $class = $student['class'];
    } else {
        header("Location: students.php");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = sanitize($_POST['student_number'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $class = sanitize($_POST['class'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($student_number) || empty($name) || empty($email) || empty($class)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$isEdit && empty($password)) {
        $error = 'Please enter a password.';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if student number already exists
        $stmt = $conn->prepare("SELECT id FROM students WHERE student_number = ? AND id != ?");
        $stmt->bind_param("si", $student_number, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Student number already exists.';
        } else {
            if ($isEdit) {
                // Update existing student
                if (!empty($password)) {
                    // Update with new password
                    $stmt = $conn->prepare("
                        UPDATE students 
                        SET student_number = ?, name = ?, email = ?, class = ?, password = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("sssssi", $student_number, $name, $email, $class, $password, $id);
                } else {
                    // Update without changing password
                    $stmt = $conn->prepare("
                        UPDATE students 
                        SET student_number = ?, name = ?, email = ?, class = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssssi", $student_number, $name, $email, $class, $id);
                }
                
                if ($stmt->execute()) {
                    $success = 'Student updated successfully.';
                } else {
                    $error = 'Error updating student: ' . $conn->error;
                }
            } else {
                // Create new student
                $stmt = $conn->prepare("
                    INSERT INTO students (student_number, name, email, class, password)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssss", $student_number, $name, $email, $class, $password);
                
                if ($stmt->execute()) {
                    header("Location: students.php?success=record_created");
                    exit;
                } else {
                    $error = 'Error creating student: ' . $conn->error;
                }
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header mb-4">
    <h1 class="dashboard-title"><?php echo $isEdit ? 'Edit Student' : 'Add New Student'; ?></h1>
    <p class="text-muted"><?php echo $isEdit ? 'Update student information' : 'Create a new student record'; ?></p>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" class="needs-validation" novalidate>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="student_number" class="form-label">Student Number *</label>
                    <input type="text" class="form-control" id="student_number" name="student_number" value="<?php echo htmlspecialchars($student_number); ?>" required>
                    <div class="invalid-feedback">Please enter a student number.</div>
                </div>
                
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    <div class="invalid-feedback">Please enter the student's full name.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                
                <div class="col-md-6">
                    <label for="class" class="form-label">Class/Section *</label>
                    <input type="text" class="form-control" id="class" name="class" value="<?php echo htmlspecialchars($class); ?>" required>
                    <div class="invalid-feedback">Please enter the class or section.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label"><?php echo $isEdit ? 'Password (leave blank to keep current)' : 'Password *'; ?></label>
                    <input type="password" class="form-control" id="password" name="password" <?php echo $isEdit ? '' : 'required'; ?>>
                    <div class="invalid-feedback">Please enter a password.</div>
                </div>
                
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    <div class="invalid-feedback">Passwords do not match.</div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <a href="students.php" class="btn btn-secondary">Cancel</a>
                </div>
                <div class="col-md-6 text-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> <?php echo $isEdit ? 'Update Student' : 'Add Student'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
