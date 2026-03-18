<?php
/**
 * Public Document Verification Portal
 * TESDA-BCAT Grade Management System
 */
require_once 'includes/functions.php';
$conn = getDBConnection();

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$vHash = isset($_GET['v']) ? $_GET['v'] : '';

$isVerified = false;
$document = null;

if ($tid > 0 && !empty($vHash)) {
    // Verify TOR hash
    $expectedHash = hash('sha256', 'BCAT_TRANSCRIPT_' . $tid);
    
    if (hash_equals($expectedHash, $vHash)) {
        // Fetch document details
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
        
        if ($document) {
            $isVerified = ($document['status'] === 'official');
        }
    }
} elseif ($cid = (isset($_GET['cid']) ? intval($_GET['cid']) : 0)) {
    // Verify COR hash
    $expectedHash = hash('sha256', 'BCAT_COR_' . $cid);
    
    if (hash_equals($expectedHash, $vHash)) {
        // Fetch COR details
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
        
        if ($document) {
            $isVerified = true; // CORs are official by generation
        }
        $tid = $cid; // Set tid to cid for display consistency
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
        .gradient-navy { background: linear-gradient(135deg, #1a3a5c 0%, #0d1b2a 100%); }
        .success-accent { color: #1a8754; }
        .failed-accent { color: #dc3545; }
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
                    <div class="fw-bold mb-3"><?php echo htmlspecialchars($document['program_name']); ?></div>
                    
                    <label class="text-muted small text-uppercase fw-bold mb-1">Date Issued</label>
                    <div class="fw-bold"><?php echo date('F d, Y', strtotime($document['date_generated'])); ?></div>
                </div>
                
                <div class="mt-5 text-center">
                    <small class="text-muted">Verification ID: <?php echo str_pad($tid, 8, '0', STR_PAD_LEFT); ?></small>
                </div>

            <?php else: ?>
                <div class="text-center">
                    <div class="d-inline-block p-4 rounded-circle bg-danger bg-opacity-10 mb-3">
                        <i class="fas fa-times-circle fa-4x text-danger"></i>
                    </div>
                    <h3 class="fw-bold text-danger">INVALID DOCUMENT</h3>
                    <p class="text-muted">Either the verification link is broken or the document has not been registered in our system.</p>
                    <a href="index.php" class="btn btn-primary rounded-pill px-4 mt-3">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer bg-white p-4 text-center border-0 opacity-50 small">
            &copy; <?php echo date('Y'); ?> Balicuatro College of Arts and Trades. All Rights Reserved.
        </div>
    </div>
</div>

</body>
</html>
