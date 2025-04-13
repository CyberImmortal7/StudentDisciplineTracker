<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is staff
requireStaff();

// Get incident ID
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$incident_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get incident details (only incidents reported by this staff member)
$stmt = $conn->prepare("
    SELECT i.* 
    FROM incidents i
    WHERE i.id = ? AND i.reporter_id = ?
");
$stmt->bind_param("ii", $incident_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Either incident doesn't exist or wasn't reported by this staff
    header("Location: dashboard.php");
    exit;
}

$incident = $result->fetch_assoc();

// Get students involved in this incident
$students = [];
$stmt = $conn->prepare("
SELECT s.*, incident_students_alias.punishment, incident_students_alias.details
FROM incident_students incident_students_alias
JOIN students s ON incident_students_alias.student_id = s.id
WHERE incident_students_alias.incident_id = ?
");
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="dashboard-title">Incident Details</h1>
            <p class="text-muted">View information about the reported incident</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
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
                <span class="badge <?php echo getStatusBadgeClass($incident['status']); ?>">
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
                
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> This incident is now under review by the administration.
                    <?php if ($incident['status'] === 'Open'): ?>
                        The status is currently "Open" and pending review.
                    <?php elseif ($incident['status'] === 'Investigate'): ?>
                        The incident is currently under investigation.
                    <?php elseif ($incident['status'] === 'Closed'): ?>
                        This incident has been reviewed and closed.
                    <?php endif; ?>
                </div>
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
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student Number</th>
                                    <th>Class</th>
                                    <th>Judgment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class']); ?></td>
                                        <td>
                                            <?php if ($student['punishment']): ?>
                                                <span class="badge <?php echo getPunishmentBadgeClass($student['punishment']); ?>">
                                                    <?php echo $student['punishment']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No students associated with this incident.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
