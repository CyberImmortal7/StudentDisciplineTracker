<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is student
requireStudent();

// Get student's records
$records = [];
$stmt = $conn->prepare("
    SELECT i.*, ist.punishment, ist.details
    FROM incident_students ist
    JOIN incidents i ON ist.incident_id = i.id
    WHERE ist.student_id = ?
    ORDER BY i.date_reported DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Count records by status
$openRecords = 0;
$investigateRecords = 0;
$closedRecords = 0;

foreach ($records as $record) {
    switch ($record['status']) {
        case 'Open':
            $openRecords++;
            break;
        case 'Investigate':
            $investigateRecords++;
            break;
        case 'Closed':
            $closedRecords++;
            break;
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="dashboard-header mb-4">
    <h1 class="dashboard-title">Student Dashboard</h1>
    <p class="text-muted">Welcome, <?php echo $_SESSION['name']; ?>. View your disciplinary records below.</p>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-metrics">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="metric-value"><?php echo count($records); ?></div>
                <div class="metric-label">Total Records</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-metrics">
                <i class="fas fa-clock"></i>
                <div class="metric-value"><?php echo $openRecords; ?></div>
                <div class="metric-label">Open</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-metrics">
                <i class="fas fa-search"></i>
                <div class="metric-value"><?php echo $investigateRecords; ?></div>
                <div class="metric-label">Under Investigation</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-metrics">
                <i class="fas fa-check-circle"></i>
                <div class="metric-value"><?php echo $closedRecords; ?></div>
                <div class="metric-label">Closed</div>
            </div>
        </div>
    </div>
</div>

<!-- Records Table -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">My Disciplinary Records</h5>
        <div>
            <select id="status-filter" class="form-select form-select-sm" style="width: auto;">
                <option value="all">All Statuses</option>
                <option value="Open">Open</option>
                <option value="Investigate">Under Investigation</option>
                <option value="Closed">Closed</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($records) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="records-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date of Incident</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Judgment</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr class="record-row" data-status="<?php echo $record['status']; ?>">
                                <td><?php echo htmlspecialchars($record['type']); ?></td>
                                <td><?php echo formatDate($record['date_of_incidence']); ?></td>
                                <td><?php echo formatDate($record['date_reported']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($record['status']); ?>">
                                        <?php echo $record['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['punishment']): ?>
                                        <span class="badge <?php echo getPunishmentBadgeClass($record['punishment']); ?>">
                                            <?php echo $record['punishment']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['details']): ?>
                                        <?php echo htmlspecialchars($record['details']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 text-end">
                <p class="text-muted">Showing <span id="record-count"><?php echo count($records); ?></span> records</p>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                <h5>No Disciplinary Records</h5>
                <p class="text-muted">You don't have any disciplinary records at this time.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
