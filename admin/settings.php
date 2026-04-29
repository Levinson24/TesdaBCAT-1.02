<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('settings.php', 'Invalid security token. Please try again.', 'danger');
    }
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        updateSetting($key, $value, getCurrentUserId());
    }
    redirectWithMessage('settings.php', 'Settings updated successfully', 'success');
}

$pageTitle = 'System Settings';
require_once '../includes/header.php';

$result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
?>

<style>
    .premium-card {
        border-radius: 1rem;
        transition: transform 0.2s;
    }
    .bg-dark-navy {
        background-color: #002366 !important;
    }
    .settings-table thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.1em;
        padding: 1rem;
        border-top: none;
    }
    .btn-create-profile {
        background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
        color: white;
        border: none;
        border-radius: 50px;
        padding: 0.6rem 2rem;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
        box-shadow: 0 4px 15px rgba(0, 56, 168, 0.2);
    }
    .btn-create-profile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 56, 168, 0.3);
        color: white;
    }
    .premium-input-group input, .premium-input-group select {
        padding-left: 2.8rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background: #f8fafc;
        transition: all 0.3s ease;
        padding-top: 0.6rem;
        padding-bottom: 0.6rem;
        font-size: 0.9rem;
    }
    .premium-input-group input:focus, .premium-input-group select:focus {
        border-color: #0038A8;
        box-shadow: 0 0 0 4px rgba(0, 56, 168, 0.1);
        background: #fff;
    }
</style>

<div class="card premium-card shadow-sm border-0">
    <div class="card-header gradient-navy p-3 d-flex justify-content-between align-items-center rounded-top">
        <h5 class="mb-0 text-white fw-bold ms-2">
            <i class="fas fa-cog me-2 text-info"></i> System Settings
        </h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php csrfField(); ?>
            <div class="table-responsive">
                <table class="table table-hover table-mobile-card align-middle mb-4 settings-table">
                    <thead>
                        <tr>
                            <th class="ps-4" width="30%">Setting</th>
                            <th width="40%">Value</th>
                            <th class="pe-4" width="30%">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($setting = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4" data-label="Setting">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars(str_replace('_', ' ', $setting['setting_key'])); ?></div>
                                <small class="text-muted font-monospace" style="font-size: 0.75rem;"><?php echo htmlspecialchars($setting['setting_key']); ?></small>
                            </td>
                            <td data-label="Value">
                                <input type="text" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       class="form-control bg-light border-0 py-2" style="border-radius: 0.5rem; font-size: 0.9rem;"
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                            </td>
                            <td class="pe-4" data-label="Description">
                                <small class="text-muted"><?php echo htmlspecialchars($setting['description']); ?></small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-3 pb-3">
                <button type="submit" class="btn btn-create-profile mt-3">
                    <i class="fas fa-save me-2"></i> Save Settings
                </button>
            </div>
        </form>

        <hr class="my-5">

        <div class="row">
            <div class="col-md-6 border-end">
                <div class="p-3">
                    <h5 class="text-dark mb-3 fw-bold"><i class="fas fa-database text-primary me-2"></i> Database Management</h5>
                    <p class="text-muted small">Generate a full backup of the system database. This will create a <code>.sql</code> file containing all tables and data which you can download directly to your computer.</p>
                    <a href="db_backup.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold mt-2">
                        <i class="fas fa-download me-2"></i> Download Database Backup
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3">
                    <h5 class="text-danger mb-3 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Danger Zone</h5>
                    <p class="text-muted small">Reset the system to a clean state or populate it with fresh curriculum data. <strong>Warning: This action is permanent and will delete all current student records and grades.</strong></p>
                    <a href="db_reset.php" class="btn btn-outline-danger rounded-pill px-4 fw-bold mt-2">
                        <i class="fas fa-sync-alt me-2"></i> Database Reset Utility
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
