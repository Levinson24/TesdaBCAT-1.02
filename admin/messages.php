<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$conn = getDBConnection();

// Handle Mark as Read / Archive / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['message_id']) && validateCSRFToken($_POST['csrf_token'])) {
    $msgId = (int)$_POST['message_id'];
    $act = $_POST['action'];

    if ($act === 'mark_read' || $act === 'archive') {
        $update = $conn->prepare("UPDATE admin_messages SET is_read = 1 WHERE message_id = ?");
        $update->bind_param("i", $msgId);
        $update->execute();
        $update->close();
    } elseif ($act === 'delete') {
        $delete = $conn->prepare("DELETE FROM admin_messages WHERE message_id = ?");
        $delete->bind_param("i", $msgId);
        $delete->execute();
        $delete->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: messages.php");
    exit();
}

$pageTitle = 'Support Messages';
require_once '../includes/header.php';

// Fetch messages
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$query = "SELECT * FROM admin_messages";
if ($statusFilter === 'unread') {
    $query .= " WHERE is_read = 0";
} elseif ($statusFilter === 'read') {
    $query .= " WHERE is_read = 1";
}
$query .= " ORDER BY created_at DESC";

// Fetch messages into an array so we can render table and modals separately
$messages = $conn->query($query);
$messageList = [];
while ($row = $messages->fetch_assoc()) {
    $messageList[] = $row;
}
?>

<div class="card premium-card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-inbox me-2"></i> Support Inbox</h4>
                <p class="text-muted mb-0 small mt-1">Manage user inquiries and system support requests.</p>
            </div>
            
            <div class="d-flex gap-2">
                <a href="messages.php" class="btn btn-sm px-3 py-2 <?php echo $statusFilter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill fw-bold">All</a>
                <a href="messages.php?status=unread" class="btn btn-sm px-3 py-2 <?php echo $statusFilter == 'unread' ? 'btn-warning' : 'btn-outline-warning'; ?> rounded-pill fw-bold">Unread</a>
                <a href="messages.php?status=read" class="btn btn-sm px-3 py-2 <?php echo $statusFilter == 'read' ? 'btn-success' : 'btn-outline-success'; ?> rounded-pill fw-bold">Resolved</a>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 border-0">Sender</th>
                        <th class="border-0">Role</th>
                        <th class="border-0">Message Snippet</th>
                        <th class="border-0">Date Received</th>
                        <th class="text-end pe-4 border-0">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($messageList)): ?>
                        <?php foreach ($messageList as $msg): ?>
                            <tr class="<?php echo empty($msg['is_read']) ? 'bg-primary bg-opacity-10 fw-bold' : ''; ?>">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 <?php echo empty($msg['is_read']) ? 'bg-primary text-white' : 'bg-light text-muted'; ?> d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="text-dark mb-0"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($msg['sender_email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3"><?php echo htmlspecialchars($msg['system_role']); ?></span>
                                </td>
                                <td style="max-width: 300px;">
                                    <div class="text-truncate" style="font-size: 0.9rem;" title="<?php echo htmlspecialchars($msg['message_body']); ?>">
                                        <?php echo htmlspecialchars($msg['message_body']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></span><br>
                                    <span class="small opacity-50"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-circle me-1" title="View Full Message" data-bs-toggle="modal" data-bs-target="#viewMessageModal<?php echo $msg['message_id']; ?>"><i class="fas fa-eye"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                <h5>No messages found</h5>
                                <p class="small mb-0">Your support inbox is clear based on the selected filter.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Render Modals separately at the bottom to avoid overflow/truncation issues
if (!empty($messageList)):
    foreach ($messageList as $msg): 
?>
<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal<?php echo $msg['message_id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.5rem; overflow: hidden; background: #fff !important;">
            <div class="modal-header border-0 gradient-navy text-white px-4 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-envelope-open-text me-2"></i> Message Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="avatar-sm me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 50px; height: 50px; font-size: 1.5rem;">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($msg['sender_name']); ?> <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($msg['system_role']); ?></span></h6>
                        <a href="mailto:<?php echo htmlspecialchars($msg['sender_email']); ?>" class="text-primary text-decoration-none small"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($msg['sender_email']); ?></a>
                    </div>
                </div>
                
                <div class="bg-light p-4 rounded-3 mb-4" style="font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($msg['message_body']); ?></div>
                
                <div class="d-flex justify-content-between border-top pt-3 mt-2">
                    <form method="POST" action="" class="w-100 d-flex gap-2 justify-content-between">
                        <?php csrfField(); ?>
                        <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                        
                        <?php if (empty($msg['is_read'])): ?>
                        <button type="submit" name="action" value="mark_read" class="btn btn-success fw-bold flex-grow-1" style="border-radius: 1rem;">
                            <i class="fas fa-check-circle me-2"></i> Mark as Resolved
                        </button>
                        <?php else: ?>
                            <a href="mailto:<?php echo htmlspecialchars($msg['sender_email']); ?>" class="btn btn-primary fw-bold flex-grow-1" style="border-radius: 1rem;">
                                <i class="fas fa-reply me-2"></i> Reply via Email
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger fw-bold" style="border-radius: 1rem;" onclick="return confirm('Are you sure you want to permanently delete this message?');">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
    endforeach;
endif;

require_once '../includes/footer.php'; 
?>
