<?php
/**
 * Public Document Verification Portal
 * TESDA-BCAT Grade Management System
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

$isVerified = false;
$document = null;
$errorMsg = '';

// 1. Handle GET (QR Code / Direct Link with Hash)
$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$cid = isset($_GET['cid']) ? intval($_GET['cid']) : 0;
$vHash = isset($_GET['v']) ? $_GET['v'] : '';

if ($tid > 0 && !empty($vHash)) {
    $expectedHash = hash('sha256', 'BCAT_TRANSCRIPT_' . $tid);
    if (hash_equals($expectedHash, $vHash)) {
        $stmt = $conn->prepare("
            SELECT t.*, s.first_name, s.last_name, s.student_no, p.program_name, d.title_diploma_program as dept_name, 'Transcript of Records' as doc_type
            FROM transcripts t
            JOIN students s ON t.student_id = s.student_id
            LEFT JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE t.transcript_id = ?
        ");
        $stmt->bind_param("i", $tid);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($document) $isVerified = ($document['status'] === 'official');
    }
} elseif ($cid > 0 && !empty($vHash)) {
    $expectedHash = hash('sha256', 'BCAT_COR_' . $cid);
    if (hash_equals($expectedHash, $vHash)) {
        $stmt = $conn->prepare("
            SELECT c.*, s.first_name, s.last_name, s.student_no, p.program_name, d.title_diploma_program as dept_name, 'Certificate of Registration' as doc_type
            FROM cors c
            JOIN students s ON c.student_id = s.student_id
            LEFT JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE c.cor_id = ?
        ");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($document) $isVerified = true;
        $tid = $cid; 
    }
}

// 2. Handle POST (Manual Search)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ref_id'])) {
    $refId = strtoupper(sanitizeInput($_POST['ref_id']));
    $studentNo = sanitizeInput($_POST['student_no']);
    
    if (str_starts_with($refId, 'TOR-')) {
        $id = intval(substr($refId, 4));
        $stmt = $conn->prepare("
            SELECT t.*, s.first_name, s.last_name, s.student_no, p.program_name, d.title_diploma_program as dept_name, 'Transcript of Records' as doc_type
            FROM transcripts t
            JOIN students s ON t.student_id = s.student_id
            LEFT JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE t.transcript_id = ? AND s.student_no = ?
        ");
        $stmt->bind_param("is", $id, $studentNo);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($document) {
            $isVerified = ($document['status'] === 'official');
            $tid = $id;
        }
    } elseif (str_starts_with($refId, 'COR-')) {
        $id = intval(substr($refId, 4));
        $stmt = $conn->prepare("
            SELECT c.*, s.first_name, s.last_name, s.student_no, p.program_name, d.title_diploma_program as dept_name, 'Certificate of Registration' as doc_type
            FROM cors c
            JOIN students s ON c.student_id = s.student_id
            LEFT JOIN programs p ON s.program_id = p.program_id
            LEFT JOIN departments d ON s.dept_id = d.dept_id
            WHERE c.cor_id = ? AND s.student_no = ?
        ");
        $stmt->bind_param("is", $id, $studentNo);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($document) {
            $isVerified = true;
            $tid = $id;
        }
    }
    
    if (!$document) {
        $errorMsg = "No record found for the provided Reference ID and Student Number.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification - TESDA-BCAT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .verify-card { max-width: 500px; margin: 80px auto; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .gradient-navy { background: linear-gradient(135deg, #002366 0%, #001a4d 100%); }
        .success-accent { color: #1a8754; }
        .failed-accent { color: #dc3545; }
        .navbar { background: #0038A8 !important; }
        .btn-primary { background: #0038A8 !important; border: none; }
        .btn-primary:hover { background: #002e8a !important; }
        .bg-primary { background: #0038A8 !important; }
        .text-primary { color: #0038A8 !important; }

        /* ──── ELEGANT SCROLLBARS ──── */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.4);
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.7);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card verify-card overflow-hidden">
        <div class="card-header gradient-navy p-5 text-center text-white border-0">
            <div class="mb-3">
                <i class="fas fa-shield-alt fa-4x opacity-75"></i>
            </div>
            <h4 class="fw-bold mb-0">Official Document Verification</h4>
            <p class="small opacity-75 mt-2">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</p>
        </div>
        
        <div class="card-body p-5">
            <?php if ($isVerified && $document): ?>
                <div class="text-center mb-5">
                    <div class="d-inline-block p-4 rounded-circle bg-success bg-opacity-10 mb-3">
                        <i class="fas fa-check-circle fa-4x text-success"></i>
                    </div>
                    <h3 class="fw-bold text-success">VERIFIED AUTHENTIC</h3>
                    <p class="text-muted">This document is a legitimate record issued by BCAT Registrar.</p>
                </div>
                
                <div class="bg-light p-4 rounded-4 border">
                    <label class="text-muted small text-uppercase fw-bold mb-1">Student Name</label>
                    <h5 class="fw-bold mb-3"><?php echo htmlspecialchars(strtoupper($document['last_name'] . ', ' . $document['first_name'])); ?></h5>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Student No</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($document['student_no']); ?></div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Document Type</label>
                            <div class="badge bg-primary"><?php echo htmlspecialchars($document['doc_type']); ?></div>
                        </div>
                    </div>
                    
                    <label class="text-muted small text-uppercase fw-bold mb-1">Program</label>
                    <div class="fw-bold mb-3"><?php echo htmlspecialchars($document['program_name'] ?? 'N/A'); ?></div>
                    
                    <label class="text-muted small text-uppercase fw-bold mb-1">Date Issued</label>
                    <div class="fw-bold"><?php echo date('F d, Y', strtotime($document['date_generated'])); ?></div>
                </div>
                
                <div class="mt-5 text-center">
                    <div class="text-muted small mb-3">Verification ID: <?php echo str_pad($tid, 8, '0', STR_PAD_LEFT); ?></div>
                    <a href="verify.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-search me-1"></i> Verify Another Document
                    </a>
                </div>

            <?php else: ?>
                <?php if ($errorMsg): ?>
                <div class="alert alert-danger px-4 py-3 border-0 shadow-sm mb-4" style="border-radius: 1rem;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fa-2x opacity-75"></i>
                        <div>
                            <div class="fw-bold">Verification Failed</div>
                            <div class="small opacity-75">No record found. Please ensure the Reference ID and Student Number match the printed document exactly.</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-file-contract fa-3x text-primary opacity-25"></i>
                    </div>
                    <h5 class="fw-bold">Manual Verification</h5>
                    <p class="text-muted small">Enter the document details as printed on the official record.</p>
                </div>

                <form method="POST" action="verify.php">
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase">Reference ID</label>
                        <div class="input-group border rounded-3 p-1 shadow-sm bg-white">
                            <span class="input-group-text bg-transparent border-0 text-primary">
                                <i class="fas fa-hashtag"></i>
                            </span>
                            <input type="text" name="ref_id" class="form-control border-0 bg-transparent" placeholder="e.g. TOR-00000001" value="<?php echo isset($_POST['ref_id']) ? htmlspecialchars($_POST['ref_id']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase">Student ID / Number</label>
                        <div class="input-group border rounded-3 p-1 shadow-sm bg-white">
                            <span class="input-group-text bg-transparent border-0 text-primary">
                                <i class="fas fa-id-card"></i>
                            </span>
                            <input type="text" name="student_no" class="form-control border-0 bg-transparent" placeholder="e.g. STU-00000" required>
                        </div>
                        <div class="form-text small px-2">Example format: STU-XXXXX or 2024-XXXXX</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm">
                        <i class="fas fa-search me-2"></i> Verify Authenticity
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="index.php" class="text-muted text-decoration-none small">
                            <i class="fas fa-arrow-left me-1"></i> Return to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="card-footer bg-white p-4 text-center border-0 opacity-50 small">
            &copy; <?php echo date('Y'); ?> Balicuatro College of Arts and Trades. All Rights Reserved.
        </div>
    </div>
</div>

</body>
</html>
