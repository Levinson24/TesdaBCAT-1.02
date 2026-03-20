<?php
/**
 * Admin Dashboard
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Dashboard';
require_once '../includes/header.php';

requireRole('admin');

// Get statistics
$conn = getDBConnection();

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$totalUsers = $result->fetch_assoc()['total'];

// Total students
$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
$totalStudents = $result->fetch_assoc()['total'];

// Total instructors
$result = $conn->query("SELECT COUNT(*) as total FROM instructors WHERE status = 'active'");
$totalInstructors = $result->fetch_assoc()['total'];

// Total courses
$result = $conn->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
$totalCourses = $result->fetch_assoc()['total'];

// Total Colleges
$result = $conn->query("SELECT COUNT(*) as total FROM colleges WHERE status = 'active'");
$totalColleges = $result->fetch_assoc()['total'];

// Total Programs
$result = $conn->query("SELECT COUNT(*) as total FROM programs WHERE status = 'active'");
$totalPrograms = $result->fetch_assoc()['total'];

// Pending grades
$result = $conn->query("SELECT COUNT(*) as total FROM grades WHERE status = 'submitted'");
$pendingGrades = $result->fetch_assoc()['total'];

// Recent activities
$recentActivities = $conn->query("
    SELECT a.*, u.username 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 10
");

$currentUser = getUserProfile(getCurrentUserId(), 'admin');
?>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card premium-card overflow-hidden shadow-lg border-0" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-lg-3 gradient-navy d-flex flex-column align-items-center justify-content-center p-5 text-white position-relative overflow-hidden">
                        <!-- Decorative circle -->
                        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                        <div class="user-avatar bg-white p-3 mb-3 shadow-lg" style="width: 130px; height: 130px; border-radius: 3rem; display: flex; align-items: center; justify-content: center;">
                            <img src="../BCAT logo 2024.png" alt="BCAT Logo" class="img-fluid">
                        </div>
                        <h5 class="fw-bold mb-0">TESDA-BCAT</h5>
                        <p class="small opacity-75 mb-0">Grade Management System</p>
                    </div>
                    <div class="col-lg-9 p-5">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h1 class="display-5 fw-800 mb-2" style="letter-spacing: -0.03em; color: var(--primary-indigo);">Welcome, <?php echo htmlspecialchars($currentUser['username'] ?? 'Admin'); ?>!</h1>
                                <p class="text-muted lead mb-0">You have full control over the system's academic and administrative operations.</p>
                            </div>
                            <div class="text-end d-none d-md-block">
                                <span class="badge glass-effect text-primary px-4 py-3 rounded-pill fw-bold" style="font-size: 0.85rem;">
                                    <i class="fas fa-certificate me-2 text-warning"></i> MASTER ADMINISTRATOR
                                </span>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                                        <i class="fas fa-shield-alt fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">Security Level</div>
                                        <div class="fw-bold text-dark">Maximum Access</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-success bg-opacity-10 p-2 rounded-3 text-success">
                                        <i class="fas fa-microchip fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">System Status</div>
                                        <div class="fw-bold text-success">Optimal Performance</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-info bg-opacity-10 p-2 rounded-3 text-info">
                                        <i class="fas fa-calendar-check fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">Active Semester</div>
                                        <div class="fw-bold text-dark"><?php echo getSetting('current_semester', '1st'); ?> Sem, <?php echo getSetting('academic_year', '2024-2025'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Stats Grid -->
    <div class="responsive-grid mb-5">
        <!-- Users Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Total Users</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalUsers); ?></h3>
        </div>

        <!-- Students Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Total Students</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalStudents); ?></h3>
        </div>

        <!-- Instructors Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Faculty Members</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalInstructors); ?></h3>
        </div>

        <!-- Colleges Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-university"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Colleges</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalColleges); ?></h3>
        </div>

        <!-- Programs Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Diploma Programs</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalPrograms); ?></h3>
        </div>

        <!-- Courses Card -->
        <div class="premium-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-card-icon-v2 bg-secondary bg-opacity-10 text-secondary">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <h6 class="text-muted fw-semibold mb-1">Total Subjects</h6>
            <h3 class="fw-bold mb-0"><?php echo number_format($totalCourses); ?></h3>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-5">
        <div class="col-lg-6 mb-4">
            <div class="card premium-card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fas fa-chart-pie me-2 text-accent-indigo"></i> Enrollment by Diploma Program
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div style="height: 300px; position: relative;">
                        <canvas id="programChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card premium-card h-100 shadow-sm">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fas fa-chart-bar me-2 text-accent-indigo"></i> Student Distribution by Year Level
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div style="height: 300px; position: relative;">
                        <canvas id="yearLevelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card premium-card h-100 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fas fa-history me-2 text-accent-indigo"></i> Recent System Activities
                    </h5>
                    <a href="audit_logs.php" class="btn btn-link btn-sm text-decoration-none fw-bold">View Full Audit Log</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-mobile-card align-middle overflow-hidden mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 border-0">User Account</th>
                                <th class="border-0">Action Type</th>
                                <th class="border-0">Update Details</th>
                                <th class="border-0 text-end pe-4">Date & Time</th>
                            </tr>
                        </thead>

                        <tbody id="activity-feed">
                            <?php if ($recentActivities->num_rows > 0): ?>
                                <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4" data-label="User Account">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-pill" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <span class="fw-semibold d-block"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></span>
                                                <span class="text-muted d-block" style="font-size: 0.65rem;"><i class="fas fa-desktop me-1"></i> <?php echo htmlspecialchars($activity['ip_address'] ?? '0.0.0.0'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Action Type">
                                        <?php
                                            $badgeClass = 'bg-info bg-opacity-10 text-info';
                                            $icon = 'fa-info-circle';
                                            
                                            $action = $activity['action'];
                                            if (stripos($action, 'create') !== false) {
                                                $badgeClass = 'bg-success bg-opacity-10 text-success';
                                                $icon = 'fa-plus-circle';
                                            } elseif (stripos($action, 'delete') !== false) {
                                                $badgeClass = 'bg-danger bg-opacity-10 text-danger';
                                                $icon = 'fa-trash-alt';
                                            } elseif (stripos($action, 'print') !== false || stripos($action, 'view') !== false) {
                                                $badgeClass = 'bg-primary bg-opacity-10 text-primary';
                                                $icon = 'fa-file-alt';
                                            } elseif (stripos($action, 'update') !== false) {
                                                $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                                                $icon = 'fa-edit';
                                            }
                                        ?>
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?> px-3 mt-1">
                                            <i class="fas <?php echo $icon; ?> me-1 small"></i> <?php echo htmlspecialchars($activity['action']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Update Details">
                                        <span class="text-muted small"><?php echo htmlspecialchars($activity['new_values'] ?? '-'); ?></span>
                                    </td>
                                    <td class="text-end pe-4" data-label="Date & Time">
                                        <div class="fw-semibold small"><?php echo formatDateTime($activity['created_at']); ?></div>
                                    </td>
                                </tr>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No recent activities found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="row g-4">
            <div class="col-12">
                <div class="card premium-card border-0 shadow-sm" style="background: linear-gradient(135deg, #fff 0%, #fffbf2 100%); border-left: 5px solid #f39c12 !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="stat-card-icon-v2 bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <span class="badge bg-warning text-white rounded-pill">ACTION REQUIRED</span>
                        </div>
                        <h6 class="text-muted small fw-bold text-uppercase mb-1">Pending Grade Approvals</h6>
                        <h2 class="display-6 fw-800 text-dark mb-4"><?php echo number_format($pendingGrades); ?> <span class="fs-6 fw-normal text-muted">records</span></h2>
                        
                        <?php if ($pendingGrades > 0): ?>
                        <a href="grades.php?filter=submitted" class="btn btn-warning shadow-sm w-100 py-3 rounded-4 fw-bold">
                            <i class="fas fa-tasks me-2"></i> Review Submissions
                        </a>
                        <?php
else: ?>
                        <button class="btn btn-outline-secondary w-100 py-3 rounded-4 disabled border-dashed">
                            <i class="fas fa-check-circle me-2"></i> All Grades Processed
                        </button>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card premium-card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 px-4 pt-4 pb-0">
                        <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-server me-2"></i> Infrastructure Info</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="list-group list-group-flush border-0">
                            <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <span class="text-muted small"><i class="fas fa-code-branch me-2"></i> System Version</span>
                                <span class="badge bg-light text-dark border fw-bold"><?php echo APP_VERSION; ?></span>
                            </div>
                            <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <span class="text-muted small"><i class="fas fa-database me-2"></i> Database Instance</span>
                                <span class="fw-semibold small"><?php echo DB_NAME; ?></span>
                            </div>
                            <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <span class="text-muted small"><i class="fas fa-clock me-2"></i> PHP Runtime</span>
                                <span class="fw-semibold small"><?php echo phpversion(); ?></span>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-primary text-white rounded-4 text-center">
                            <div class="small fw-bold"><?php echo getSetting('academic_year', '2024-2025'); ?> | <?php echo getSetting('current_semester', '1st'); ?> Semester</div>
                            <div class="opacity-75" style="font-size: 0.65rem;">Last synced: <?php echo date('M d, Y h:i A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let isUpdatingActivity = false;
function updateActivityFeed() {
    if (isUpdatingActivity) return;
    isUpdatingActivity = true;

    $.ajax({
        url: 'ajax/get_recent_activities.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            const feed = document.getElementById('activity-feed');
            if (!feed) return;

            if (data.length === 0) {
                feed.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">No recent activities found.</td></tr>';
                return;
            }

            let html = '';
            data.forEach(activity => {
                html += `<tr>
                    <td class="ps-4" data-label="User Account">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-pill" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <span class="fw-semibold d-block">${activity.username}</span>
                                <span class="text-muted d-block" style="font-size: 0.65rem;"><i class="fas fa-desktop me-1"></i> ${activity.ip_address || '0.0.0.0'}</span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Action Type">
                        <span class="badge rounded-pill ${activity.badgeClass} px-3 mt-1">
                            <i class="fas ${activity.icon} me-1 small"></i> ${activity.action}
                        </span>
                    </td>
                    <td data-label="Update Details">
                        <span class="text-muted small">${activity.details}</span>
                    </td>
                    <td class="text-end pe-4" data-label="Date & Time">
                        <div class="fw-semibold small">${activity.datetime}</div>
                    </td>
                </tr>`;
            });
            feed.innerHTML = html;
        },
        error: function(xhr, status, error) {
            console.error('Failed to update activity feed:', error);
        },
        complete: function() {
            isUpdatingActivity = false;
        }
    });
}


// Start polling every 10 seconds
setInterval(updateActivityFeed, 10000);

// Dashboard Charts Logic
function initDashboardCharts() {
    $.ajax({
        url: 'ajax/get_dashboard_charts.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            // 1. Program Chart (Doughnut)
            const programCtx = document.getElementById('programChart').getContext('2d');
            new Chart(programCtx, {
                type: 'doughnut',
                data: {
                    labels: data.programs.map(p => p.label),
                    datasets: [{
                        data: data.programs.map(p => p.value),
                        backgroundColor: [
                            '#0038A8', '#0047D1', '#0055FF', '#3377FF', '#6699FF', '#99BBFF'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 11, family: 'Inter' }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });

            // 2. Year Level Chart (Bar)
            const yearLevelCtx = document.getElementById('yearLevelChart').getContext('2d');
            new Chart(yearLevelCtx, {
                type: 'bar',
                data: {
                    labels: data.yearLevels.map(y => y.label),
                    datasets: [{
                        label: 'Active Students',
                        data: data.yearLevels.map(y => y.value),
                        backgroundColor: 'rgba(0, 56, 168, 0.8)',
                        borderRadius: 8,
                        barThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { display: false },
                            ticks: { font: { family: 'Inter' } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter' } }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        },
        error: function(xhr, status, error) {
            console.error('Failed to load chart data:', error);
        }
    });
}

// Initialize charts on load
document.addEventListener('DOMContentLoaded', initDashboardCharts);
</script>

<?php require_once '../includes/footer.php'; ?>
