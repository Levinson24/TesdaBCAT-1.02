<?php
/**
 * Instructor - Grade History Hub (v1.16)
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Grade History HUB';
require_once '../includes/header.php';
requireRole('instructor');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get instructor profile
$stmt = $conn->prepare("SELECT * FROM instructors WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$instructor) {
    echo showError('Instructor profile not found.');
    require_once '../includes/footer.php';
    exit();
}

$instructorId = $instructor['instructor_id'];

// Get all submitted grades for sections owned by this instructor
// Optimized SQL: Filter by section ownership for 100% visibility
$gradesQuery = "
    SELECT 
        g.*,
        s.student_no,
        CONCAT(s.last_name, ', ', s.first_name, ' ', COALESCE(s.middle_name, '')) as student_name,
        c.subject_id as course_code,
        c.subject_name as course_name,
        cs.section_name,
        cs.semester,
        cs.school_year
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    WHERE cs.instructor_id = ? 
    ORDER BY g.submitted_at DESC
";

$stmt = $conn->prepare($gradesQuery);
$stmt->bind_param("i", $instructorId);
$stmt->execute();
$gradesResult = $stmt->get_result();
$stmt->close();
?>

<!-- Premium Identity Hub Styling -->
<style>
    .registry-search-premium {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(0, 56, 168, 0.1);
        border-radius: 1.25rem;
        padding: 0.5rem 1.25rem;
        transition: all 0.3s ease;
    }
    .registry-search-premium:focus-within {
        box-shadow: 0 10px 25px rgba(0, 56, 168, 0.1);
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }
    .history-card-item {
        transition: all 0.2s ease;
    }
    .history-card-item:hover {
        background-color: rgba(0, 56, 168, 0.02) !important;
    }
    .grade-badge-premium {
        font-size: 0.9rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
    }
</style>

<div class="card premium-card border-0 shadow-sm rounded-4 mb-4 flex-grow-1 d-flex flex-column">
    <div class="card-header gradient-navy p-4 d-flex flex-wrap justify-content-between align-items-center rounded-top-4 gap-3">
        <div>
            <h5 class="mb-0 text-white fw-bold">
                <i class="fas fa-history me-3 text-warning shadow-sm"></i>Grade History HUB
            </h5>
            <p class="text-white opacity-75 mb-0 x-small mt-1 ls-1 text-uppercase fw-bold">Audit trail for all submitted student evaluations</p>
        </div>
        
        <div class="search-box-container flex-grow-1 border-0" style="max-width: 400px;">
            <div class="registry-search-premium d-flex align-items-center">
                <i class="fas fa-search text-primary me-3 opacity-50"></i>
                <input type="text" id="historySearch" class="form-control border-0 bg-transparent shadow-none p-0" placeholder="Filter by student, subject, or section..." onkeyup="filterHistory()">
            </div>
        </div>

        <button onclick="window.print()" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm border-0 text-primary">
            <i class="fas fa-print me-2"></i>Export View
        </button>
    </div>

    <div class="card-body p-0 flex-grow-1 d-flex flex-column">
        <div class="table-responsive flex-grow-1">
            <table class="table table-hover align-middle mb-0 premium-table" id="historyTable">
                <thead class="bg-light text-muted x-small text-uppercase ls-1">
                    <tr>
                        <th class="ps-4 py-3">Student Information</th>
                        <th class="py-3">Subject & Session</th>
                        <!-- Unified Grading -> removed midterm/final -->
                        <th class="py-3 text-center">Rating</th>
                        <th class="py-3 text-center">Remarks</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="pe-4 py-3 text-end">Submission Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($gradesResult->num_rows > 0): ?>
                        <?php while ($g = $gradesResult->fetch_assoc()): 
                            $gradeValue = $g['grade'] !== null ? number_format($g['grade'], 2) : '—';
                            $remarks = $g['remarks'] ?? 'Incomplete';
                            
                            // Dynamic Styling
                            $remarksClass = 'bg-secondary';
                            if ($remarks === 'Passed') $remarksClass = 'bg-success';
                            elseif ($remarks === 'Failed') $remarksClass = 'bg-danger';
                            elseif ($remarks === 'INC') $remarksClass = 'bg-warning';
                            elseif ($remarks === 'Incomplete') $remarksClass = 'bg-info bg-opacity-75';

                            $statusText = 'Graded';
                            $statusDot = '#22c55e';
                            if ($g['status'] === 'pending') {
                                $statusText = 'In Progress';
                                $statusDot = '#f59e0b';
                            }
                        ?>
                        <tr class="history-card-item">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-soft bg-primary bg-opacity-10 text-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 800;">
                                        <?php echo substr($g['student_name'] ?? 'S', 0, 1); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($g['student_name']); ?></div>
                                        <div class="x-small text-muted fw-bold ls-1"><?php echo htmlspecialchars($g['student_no']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($g['course_code']); ?></div>
                                <div class="x-small text-muted text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($g['section_name'] . ' · ' . $g['semester'] . ' ' . $g['school_year']); ?></div>
                            </td>
                            <!-- Unified Grading -> removed midterm/final -->
                            <td class="text-center">
                                <div class="d-flex justify-content-center">
                                    <div class="grade-badge-premium <?php echo $remarks === 'Passed' ? 'bg-success bg-opacity-10 text-success' : 'bg-light text-muted'; ?> border">
                                        <?php echo $gradeValue; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $remarksClass; ?> rounded-pill px-3 py-2 small fw-bold shadow-sm">
                                    <?php echo htmlspecialchars($remarks); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="status-pill status-active shadow-none border bg-light">
                                    <div class="status-dot" style="background: <?php echo $statusDot; ?>;"></div> <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="x-small fw-bold text-dark"><?php echo date('M d, Y', strtotime($g['submitted_at'])); ?></div>
                                <div class="text-muted" style="font-size: 0.65rem;"><?php echo date('h:i A', strtotime($g['submitted_at'])); ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="py-5">
                                    <i class="fas fa-folder-open fa-3x text-muted opacity-25 mb-3"></i>
                                    <h6 class="text-muted fw-bold">No Records Found</h6>
                                    <p class="text-muted small">You haven't submitted any grades for your active sections yet.</p>
                                    <a href="submit_grades.php" class="btn btn-primary rounded-pill px-4 mt-2">Go to Submission Hub</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterHistory() {
    const input = document.getElementById('historySearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('historyTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        if (row.cells.length > 1) {
            const text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
