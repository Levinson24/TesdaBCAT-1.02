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

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-cog"></i> System Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php csrfField(); ?>
            <div class="table-responsive">
                <table class="table table-hover table-mobile-card align-middle mb-4">
                    <thead class="bg-light">
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
                                <small class="text-muted font-monospace"><?php echo htmlspecialchars($setting['setting_key']); ?></small>
                            </td>
                            <td data-label="Value">
                                <input type="text" 
                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                       class="form-control border-focus-primary" 
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

            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
