<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is admin
requireAdmin();

// Get incident ID
if (!isset($_GET['id'])) {
    header("Location: incidents.php");
    exit;
}

$incident_id = (int)$_GET['id'];

// Get incident details
$stmt = $conn->prepare("
    SELECT i.*, u.name as reporter_name 
    FROM incidents i
    JOIN users u ON i.reporter_id = u.id
    WHERE i.id = ?
");
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: incidents.php");
    exit;
}

$incident = $result->fetch_assoc();

// Get students involved in this incident
$students = [];
$stmt = $conn->prepare("
    SELECT s.*, ist.punishment, ist.details
    FROM incident_students ist
    JOIN students s ON ist.student_id = s.id
    WHERE ist.incident_id = ?
");
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    
    if (in_array($newStatus, ['Open', 'Investigate', 'Closed'])) {
        $stmt = $conn->prepare("
            UPDATE incidents 
            SET status = ?, updated_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sii", $newStatus, $_SESSION['user_id'], $incident_id);
        
        if ($stmt->execute()) {
            // Update the incident object with new status
            $incident['status'] = $newStatus;
        }
    }
}

// Handle judgment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_judgment'])) {
    $student_id = (int)$_POST['student_id'];
    $punishment = $_POST['punishment'];
    $details = $_POST['details'];
    
    $stmt = $conn->prepare("
        UPDATE incident_students 
        SET punishment = ?, details = ?
        WHERE incident_id = ? AND student_id = ?
    ");
    $stmt->bind_param("ssii", $punishment, $details, $incident_id, $student_id);
    
    if ($stmt->execute()) {
        // Update the students array with new judgment
        foreach ($students as &$student) {
            if ($student['id'] == $student_id) {
                $student['punishment'] = $punishment;
                $student['details'] = $details;
                break;
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="dashboard-title">Incident Details</h1>
            <p class="text-muted">View and manage incident information</p>
        </div>
        <div>
            <a href="incidents.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Incidents
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Incident Information -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Incident Information</h5>
                <span class="badge <?php echo getStatusBadgeClass($incident['status']); ?>" id="status-badge">
                    <?php echo $incident['status']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="fw-bold">Incident Type:</label>
                    <div><?php echo htmlspecialchars($incident['type']); ?></div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Description:</label>
                    <div><?php echo nl2br(htmlspecialchars($incident['description'])); ?></div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Date of Incident:</label>
                        <div><?php echo formatDate($incident['date_of_incidence']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Date Reported:</label>
                        <div><?php echo formatDate($incident['date_reported']); ?></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-bold">Reported By:</label>
                    <div><?php echo htmlspecialchars($incident['reporter_name']); ?></div>
                </div>
                
                <?php if (!empty($incident['supporting_documents'])): ?>
                <div class="mb-3">
                    <label class="fw-bold">Supporting Documents:</label>
                    <div>
                        <?php 
                        $docs = explode(',', $incident['supporting_documents']);
                        foreach ($docs as $doc): 
                            $doc = trim($doc);
                            if (!empty($doc)):
                        ?>
                            <div class="mb-1">
                                <a href="<?php echo htmlspecialchars($doc); ?>" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-file-alt me-1"></i> <?php echo basename($doc); ?>
                                </a>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Update Status Form -->
                <form method="post" id="incident-status-form" data-incident-id="<?php echo $incident_id; ?>" class="mt-4">
                    <h6 class="mb-3">Update Incident Status</h6>
                    <div class="row">
                        <div class="col-md-8">
                            <select name="status" class="form-select">
                                <option value="Open" <?php echo $incident['status'] === 'Open' ? 'selected' : ''; ?>>Open</option>
                                <option value="Investigate" <?php echo $incident['status'] === 'Investigate' ? 'selected' : ''; ?>>Investigate</option>
                                <option value="Closed" <?php echo $incident['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="update_status" class="btn btn-primary w-100">Update Status</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Students Involved -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Students Involved (<?php echo count($students); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($students) > 0): ?>
                    <div class="accordion" id="studentsAccordion">
                        <?php foreach ($students as $index => $student): ?>
                            <div class="accordion-item mb-3 border">
                                <h2 class="accordion-header" id="heading-<?php echo $student['id']; ?>">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $student['id']; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $student['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                                <small class="ms-2 text-muted"><?php echo htmlspecialchars($student['student_number']); ?></small>
                                            </div>
                                            <span class="badge <?php echo getPunishmentBadgeClass($student['punishment'] ?? ''); ?>" id="punishment-badge-<?php echo $student['id']; ?>">
                                                <?php echo $student['punishment'] ?? 'No Judgment'; ?>
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $student['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $student['id']; ?>" data-bs-parent="#studentsAccordion">
                                    <div class="accordion-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="fw-bold">Email:</label>
                                                <div><?php echo htmlspecialchars($student['email']); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="fw-bold">Class:</label>
                                                <div><?php echo htmlspecialchars($student['class']); ?></div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Judgment Form -->
                                        <form method="post" class="judgment-form" data-student-id="<?php echo $student['id']; ?>" data-incident-id="<?php echo $incident_id; ?>">
                                            <h6 class="mb-3">Student Judgment</h6>
                                            
                                            <div class="mb-3">
                                                <label for="punishment-<?php echo $student['id']; ?>" class="form-label">Punishment</label>
                                                <select class="form-select" id="punishment-<?php echo $student['id']; ?>" name="punishment">
                                                    <option value="No Punishment" <?php echo ($student['punishment'] ?? '') === 'No Punishment' ? 'selected' : ''; ?>>No Punishment</option>
                                                    <option value="Suspension" <?php echo ($student['punishment'] ?? '') === 'Suspension' ? 'selected' : ''; ?>>Suspension</option>
                                                    <option value="Expulsion" <?php echo ($student['punishment'] ?? '') === 'Expulsion' ? 'selected' : ''; ?>>Expulsion</option>
                                                    <option value="Community Service" <?php echo ($student['punishment'] ?? '') === 'Community Service' ? 'selected' : ''; ?>>Community Service</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="details-<?php echo $student['id']; ?>" class="form-label">Details</label>
                                                <textarea class="form-control" id="details-<?php echo $student['id']; ?>" name="details" rows="3"><?php echo htmlspecialchars($student['details'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="update_judgment" value="1">
                                            
                                            <button type="submit" class="btn btn-primary">Save Judgment</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center">No students associated with this incident.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
