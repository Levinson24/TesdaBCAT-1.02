<?php
/**
 * Admin - College Management
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitizeInput($_POST['college_name']);
        $code = strtoupper(sanitizeInput($_POST['college_code']));

        $stmt = $conn->prepare("INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $code);
        if ($stmt->execute()) {
            redirectWithMessage('colleges.php', 'College created successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'update') {
        $id = intval($_POST['college_id']);
        $name = sanitizeInput($_POST['college_name']);
        $code = strtoupper(sanitizeInput($_POST['college_code']));
        $status = sanitizeInput($_POST['status']);

        $stmt = $conn->prepare("UPDATE colleges SET college_name = ?, college_code = ?, status = ? WHERE college_id = ?");
        $stmt->bind_param("sssi", $name, $code, $status, $id);
        if ($stmt->execute()) {
            redirectWithMessage('colleges.php', 'College updated successfully', 'success');
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['college_id']);
        // Check if anything is using this college
        $check1 = $conn->query("SELECT COUNT(*) FROM departments WHERE college_id = $id")->fetch_row()[0];

        if ($check1 > 0) {
            redirectWithMessage('colleges.php', 'Cannot delete college: It is currently linked to diploma programs.', 'danger');
        }
        else {
            $conn->query("DELETE FROM colleges WHERE college_id = $id");
            redirectWithMessage('colleges.php', 'College deleted successfully', 'success');
        }
    }
}

$pageTitle = 'Manage Colleges';
require_once '../includes/header.php';

$colleges = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM departments WHERE college_id = c.college_id) as dept_count
    FROM colleges c
    ORDER BY c.college_name ASC
");
// === Premium Styles ===
?>


<div class="card premium-card mb-4 shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex flex-wrap justify-content-between align-items-center rounded-top gap-3">
        <h5 class="mb-0 text-white fw-bold ms-2 flex-grow-1">
            <i class="fas fa-university me-2 text-info"></i> Academic Portfolios (Colleges)
        </h5>
        
        <div class="search-box-container">
            <div class="input-group input-group-sm rounded-pill overflow-hidden border-0 shadow-sm" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(5px);">
                <span class="input-group-text bg-transparent border-0 text-white-50 ps-3">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="collegeSearchInput" class="form-control bg-transparent border-0 text-white placeholder-light" placeholder="Search College Name or Code..." onkeyup="filterColleges()" style="box-shadow: none;">
                <span class="input-group-text bg-transparent border-0 text-white-50 pe-3" id="searchCounter" style="font-size: 0.75rem; font-weight: 600;"></span>
            </div>
        </div>

        <button class="btn btn-light btn-sm rounded-pill px-4 shadow-sm fw-bold border-0 text-primary me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add College
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 colleges-table premium-table data-table">
                <thead>
                    <tr>
                        <th class="ps-4">INSTITUTION CODE</th>
                        <th>COLLEGE NAME</th>
                        <th class="text-center">PROGRAMS</th>
                        <th class="text-center">STATUS</th>
                        <th class="text-end pe-4">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $colleges->fetch_assoc()): ?>
                    <tr class="table-row-premium align-middle">
                        <td class="ps-4" data-label="Code"><strong><?php echo htmlspecialchars($c['college_code']); ?></strong></td>
                        <td data-label="College Name"><?php echo htmlspecialchars($c['college_name']); ?></td>
                        <td data-label="Diploma Programs"><span class="badge bg-info bg-opacity-10 text-info"><?php echo $c['dept_count']; ?></span></td>
                        <td data-label="Status">
                            <span class="status-pill <?php echo $c['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($c['status'] ?? 'active'); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4 py-3" data-label="Control Actions">
                            <div class="table-actions-v2">
                                <button class="btn-premium-edit" onclick='editCollege(<?php echo json_encode($c); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete this college?')">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="college_id" value="<?php echo $c['college_id']; ?>">
                                    <button type="submit" class="btn-premium-delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-university"></i>
                        <span>Add College</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-info-circle me-2"></i>Institution Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>College Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="college_code" class="form-control" placeholder="e.g. BCAT" required>
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="premium-input-group">
                                <label>College Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="college_name" class="form-control" placeholder="e.g. Balicuatro College of Arts and Trades" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-save me-2"></i>Save College</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="college_id" id="edit_id">
                <div class="modal-header modal-premium-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i>
                        <span>Edit College</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-section-divider" style="margin-top: 0;">
                        <span><i class="fas fa-sliders-h me-2"></i>Update Details</span>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>College Code</label>
                                <div class="input-wrapper">
                                    <input type="text" name="college_code" id="edit_code" class="form-control" required>
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>College Name</label>
                                <div class="input-wrapper">
                                    <input type="text" name="college_name" id="edit_name" class="form-control" required>
                                    <i class="fas fa-quote-left"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="premium-input-group">
                                <label>Status</label>
                                <div class="input-wrapper">
                                    <select name="status" id="edit_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-discard" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-create-profile"><i class="fas fa-sync me-2"></i>Update College</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCollege(c) {
    document.getElementById('edit_id').value = c.college_id;
    document.getElementById('edit_code').value = c.college_code;
    document.getElementById('edit_name').value = c.college_name;
    document.getElementById('edit_status').value = c.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
// Filter colleges locally
function filterColleges() {
    const input = document.getElementById('collegeSearchInput');
    const filter = input.value.toLowerCase().trim();
    const table = document.querySelector('.colleges-table');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');
    const counter = document.getElementById('searchCounter');
    let visibleCount = 0;

    for (let i = 1; i < tr.length; i++) {
        let rowMatch = false;
        const tds = tr[i].getElementsByTagName('td');
        for (let j = 0; j < tds.length; j++) {
            if (tds[j].textContent.toLowerCase().indexOf(filter) > -1) {
                rowMatch = true;
                break;
            }
        }
        
        if (rowMatch) {
            tr[i].style.display = "";
            visibleCount++;
        } else {
            tr[i].style.display = "none";
        }
    }

    if (filter === "") {
        counter.textContent = "";
    } else {
        counter.textContent = visibleCount + " found";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
