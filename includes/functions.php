<?php
/**
 * Common Utility Functions
 * TESDA-BCAT Grade Management System
 */

/**
 * Detect if the current device is a mobile device
 * @return bool
 */
function isMobileDevice()
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone', 'BlackBerry'];
    
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput($data)
{
    if ($data === null || $data === '') {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'F d, Y')
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'F d, Y h:i A')
{
    if (empty($datetime)) {
        return '';
    }
    return date($format, strtotime($datetime));
}

/**
 * Get academic year options
 * @param int $yearsBefore
 * @param int $yearsAfter
 * @return array
 */
function getAcademicYears($yearsBefore = 5, $yearsAfter = 2)
{
    $currentYear = date('Y');
    $years = [];

    for ($i = -$yearsBefore; $i <= $yearsAfter; $i++) {
        $year = $currentYear + $i;
        $nextYear = $year + 1;
        $years[] = "$year-$nextYear";
    }

    return $years;
}

/**
 * Get semester options
 * @return array
 */
function getSemesters()
{
    return ['1st', '2nd', 'Summer'];
}

/**
 * Calculate GWA (General Weighted Average)
 * Formula: Σ (Grade * Units) / Σ (Units)
 * Note: CHED scale is 1.00 to 5.00 (lower is better)
 * @param int $studentId
 * @return float
 */
function calculateGWA($studentId)
{
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT 
            SUM(g.grade * s.units) as weighted_sum,
            SUM(s.units) as total_units
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
        JOIN subjects s ON cur.subject_id = s.subject_id
        WHERE g.student_id = ? 
        AND g.status = 'approved' 
        AND g.grade IS NOT NULL
    ");

    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && $row['total_units'] > 0) {
        return round($row['weighted_sum'] / $row['total_units'], 2);
    }

    return 0.00;
}

/**
 * Backwards compatibility alias for calculateGWA
 */
function calculateGPA($studentId)
{
    return calculateGWA($studentId);
}

/**
 * Get grade remark based on score (CHED Scale 1.00-5.00)
 * @param float $grade
 * @param float $passingGrade
 * @return string
 */
function getGradeRemark($grade, $passingGrade = null)
{
    if ($grade === null || $grade == 0) {
        return 'INC';
    }

    $gradingSystem = getSetting('grading_system', 'Numeric');
    
    if ($passingGrade === null) {
        // Fallback defaults based on type
        $passingGrade = ($gradingSystem === 'Numeric') ? 75.00 : 3.00;
    }

    if ($gradingSystem === 'Numeric') {
        // In a Numeric system, higher is better (e.g. 75-100)
        if ($grade >= $passingGrade) {
            return 'Passed';
        }
        return 'Failed';
    } else {
        // In a CHED system, lower is better (e.g. 1.0-5.0)
        if ($grade <= 1.25)
            return 'Excellent';
        if ($grade <= 1.75)
            return 'Very Good';
        if ($grade <= 2.25)
            return 'Good';
        if ($grade <= 2.75)
            return 'Satisfactory';
        if ($grade <= $passingGrade)
            return 'Passed';
        if ($grade < 5.00)
            return 'Conditional';

        return 'Failed';
    }
}

/**
 * Check if student has any academic backlog (failure, incomplete, or dropped subjects)
 * Disqualifies from Latin Honors.
 * @param int $studentId
 * @return bool
 */
function hasAcademicBacklog($studentId)
{
    $conn = getDBConnection();

    // Check for failing grades ( > 3.00 in CHED 1.0-5.0 scale), INCs, or explicitly 'Dropped' remarks
    // 5.00 is failing. 4.0 is often conditional/incomplete.
    $stmt = $conn->prepare("
        SELECT COUNT(*) as backlog_count 
        FROM grades 
        WHERE student_id = ? 
        AND (
            remarks IN ('Failed', 'INC', 'Conditional', 'Dropped', 'Incomplete') 
            OR (grade > 3.00 AND grade <= 5.00)
        )
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $gradeBacklog = ($res['backlog_count'] ?? 0) > 0;
    $stmt->close();

    if ($gradeBacklog)
        return true;

    // Check for dropped enrollments (status in enrollment table)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as dropped_count 
        FROM enrollments 
        WHERE student_id = ? AND status = 'dropped'
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $droppedBacklog = ($res['dropped_count'] ?? 0) > 0;
    $stmt->close();

    return $droppedBacklog;
}

/**
 * Get Latin Honors based on GWA and backlog status
 * @param float $gwa
 * @param bool $hasBacklog
 * @return string|null
 */
function getLatinHonors($gwa, $hasBacklog = false)
{
    if ($gwa <= 0 || $hasBacklog)
        return null;

    if ($gwa <= 1.25)
        return 'Summa Cum Laude';
    if ($gwa <= 1.50)
        return 'Magna Cum Laude';
    if ($gwa <= 1.75)
        return 'Cum Laude';
    if ($gwa <= 2.00)
        return 'With Honor';

    return null;
}

/**
 * Generate pagination HTML
 * @param int $currentPage
 * @param int $totalPages
 * @param string $baseUrl
 * @return string
 */
function generatePagination($currentPage, $totalPages, $baseUrl)
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous button
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $prevPage = max(1, $currentPage - 1);
    $html .= "<li class='page-item $prevDisabled'><a class='page-link' href='{$baseUrl}page=$prevPage'>Previous</a></li>";

    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}page=1'>1</a></li>";
        if ($startPage > 2) {
            $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html .= "<li class='page-item $active'><a class='page-link' href='{$baseUrl}page=$i'>$i</a></li>";
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}page=$totalPages'>$totalPages</a></li>";
    }

    // Next button
    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $nextPage = min($totalPages, $currentPage + 1);
    $html .= "<li class='page-item $nextDisabled'><a class='page-link' href='{$baseUrl}page=$nextPage'>Next</a></li>";

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Display success message
 * @param string $message
 * @return string
 */
function showSuccess($message)
{
    return "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <i class='fas fa-check-circle'></i> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Display error message
 * @param string $message
 * @return string
 */
function showError($message)
{
    return "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <i class='fas fa-exclamation-circle'></i> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Display info message
 * @param string $message
 * @return string
 */
function showInfo($message)
{
    return "<div class='alert alert-info alert-dismissible fade show' role='alert'>
                <i class='fas fa-info-circle'></i> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Display warning message
 * @param string $message
 * @return string
 */
function showWarning($message)
{
    return "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                <i class='fas fa-exclamation-triangle'></i> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Redirect with message
 * @param string $url
 * @param string $message
 * @param string $type
 */
function redirectWithMessage($url, $message, $type = 'success')
{
    startSession();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Get and clear flash message
 * @return string
 */
function getFlashMessage()
{
    startSession();

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        switch ($type) {
            case 'success':
                return showSuccess($message);
            case 'danger':
            case 'error':
                return showError($message);
            case 'warning':
                return showWarning($message);
            default:
                return showInfo($message);
        }
    }

    return '';
}

/**
 * Upload file
 * @param array $file $_FILES array element
 * @param string $targetDir
 * @param array $allowedTypes
 * @return array [success, message, filename]
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'])
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'File upload error', null];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $maxMB = round(UPLOAD_MAX_SIZE / (1024 * 1024), 2);
        $fileMB = round($file['size'] / (1024 * 1024), 2);
        return [false, "File size ($fileMB MB) exceeds maximum allowed size ($maxMB MB)", null];
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedTypes)) {
        $allowedStr = strtoupper(implode(', ', $allowedTypes));
        return [false, "File type $fileExt not allowed. Supported: $allowedStr", null];
    }

    // MIME type validation
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
$rawMimeType = finfo_file($finfo, $file['tmp_name']);
// No need to call finfo_close() anymore

    // Normalize MIME type (remove charset info like "; charset=binary")
    $mimeType = strtolower(explode(';', $rawMimeType)[0]);

    $allowedMimeTypes = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png', 'image/x-png'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream']
    ];

    $expectedMimes = $allowedMimeTypes[$fileExt] ?? null;
    if ($expectedMimes && !in_array($mimeType, $expectedMimes)) {
        // Special case: check for HEIC to provide helpful message
        if ($mimeType === 'image/heic' || $mimeType === 'image/heif') {
            return [false, 'iPhones use HEIC by default which is not web-ready. Please convert to JPG first.', null];
        }
        return [false, "Invalid file content ($mimeType) for the given extension ($fileExt).", null];
    }

    $filename = uniqid() . '_' . time() . '.' . $fileExt;
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [true, 'File uploaded successfully', $filename];
    }

    return [false, 'Failed to upload file', null];
}

/**
 * Export array to CSV
 * @param array $data
 * @param string $filename
 */
function exportToCSV($data, $filename)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Output headers
        fputcsv($output, array_keys($data[0]));

        // Output data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit();
}

/**
 * Get system setting value
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getSetting($key, $default = null)
{
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['setting_value'];
    }

    $stmt->close();
    return $default;
}

/**
 * Update system setting
 * @param string $key
 * @param mixed $value
 * @param int $userId
 * @return bool
 */
function updateSetting($key, $value, $userId)
{
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        UPDATE system_settings 
        SET setting_value = ?, updated_by = ? 
        WHERE setting_key = ?
    ");

    $stmt->bind_param("sis", $value, $userId, $key);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

/**
 * Generate next ID/No for students or instructors
 * @param string $type 'student' or 'instructor'
 * @return string
 */
function generateNextID($type)
{
    $prefixKey = $type . '_id_prefix';
    $counterKey = $type . '_id_counter';

    $prefix = getSetting($prefixKey, strtoupper(substr($type, 0, 3)) . '-');
    $counter = intval(getSetting($counterKey, 1));

    // Format: PREFIX + 5-digit padded counter
    $id = $prefix . str_pad($counter, 5, '0', STR_PAD_LEFT);

    // Increment counter in DB
    $conn = getDBConnection();
    $nextCounter = $counter + 1;
    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("is", $nextCounter, $counterKey);
    $stmt->execute();
    $stmt->close();

    return $id;
}

/**
 * Parse a schedule string (e.g., "MW 8:00-9:30") into a computer-readable format
 * @param string $schedule
 * @return array|null Parsed data [days => [0, 2], start => 480, end => 570] or null if invalid
 */
function parseSchedule($schedule)
{
    if (empty($schedule) || strtoupper($schedule) === 'TBA') {
        return null;
    }

    // Standardize format: MWF 8:00AM-10:00AM -> MWF 8:00AM-10:00AM
    // Support formats: "MW 8:00-9:30", "TTh 1:00PM-2:30PM", "M 9:00 - 12:00"
    $schedule = strtoupper(trim($schedule));

    // Split by space to separate days and time
    $parts = preg_split('/\s+/', $schedule, 2);
    if (count($parts) < 2)
        return null;

    $dayPart = $parts[0];
    $timePart = $parts[1];

    // 1. Parse Days
    $days = [];
    $dayMap = [
        'M' => 0, 'T' => 1, 'W' => 2, 'TH' => 3, 'F' => 4, 'S' => 5, 'SU' => 6
    ];

    // Handle "TH" specifically because it's 2 chars
    $tempDays = str_replace('TH', 'H', $dayPart); // Temporary replacement
    for ($i = 0; $i < strlen($tempDays); $i++) {
        $char = $tempDays[$i];
        if ($char === 'H') {
            $days[] = 3; // Thursday
        }
        elseif (isset($dayMap[$char])) {
            $days[] = $dayMap[$char];
        }
    }

    if (empty($days))
        return null;

    // 2. Parse Time Range (8:00-9:30, 8:00AM-10:00AM)
    $timeParts = explode('-', str_replace(' ', '', $timePart));
    if (count($timeParts) < 2)
        return null;

    $convert = function ($timeStr) {
        $timeStr = trim($timeStr);
        $hasAM = strpos($timeStr, 'AM') !== false;
        $hasPM = strpos($timeStr, 'PM') !== false;

        $cleanTime = str_replace(['AM', 'PM'], '', $timeStr);
        $tParts = explode(':', $cleanTime);
        $hour = intval($tParts[0]);
        $min = isset($tParts[1]) ? intval($tParts[1]) : 0;

        if ($hasPM && $hour < 12)
            $hour += 12;
        if ($hasAM && $hour == 12)
            $hour = 0;

        return ($hour * 60) + $min;
    };

    $start = $convert($timeParts[0]);
    $end = $convert($timeParts[1]);

    // Simple heuristic for mixed AM/PM (e.g. 11:00-1:00)
    // If end < start, assume afternoon jump
    if ($end < $start && $end < 720) { // If end is before noon and less than start
        $end += 720; // Add 12 hours
    }

    return ['days' => $days, 'start' => $start, 'end' => $end];
}

/**
 * Check if two parsed schedules overlap
 */
function isScheduleOverlapping($schedA, $schedB)
{
    if (!$schedA || !$schedB)
        return false;

    // Check if any days match
    $commonDays = array_intersect($schedA['days'], $schedB['days']);
    if (empty($commonDays))
        return false;

    // Check time overlap on those days
    // A starts before B ends AND A ends after B starts
    return ($schedA['start'] < $schedB['end'] && $schedA['end'] > $schedB['start']);
}

/**
 * Check for student schedule conflicts
 */
function checkStudentScheduleConflict($studentId, $sectionId, $semester, $schoolYear)
{
    $conn = getDBConnection();

    // Get target section details
    $targetStmt = $conn->prepare("SELECT schedule, curriculum_id FROM class_sections WHERE section_id = ?");
    $targetStmt->bind_param("i", $sectionId);
    $targetStmt->execute();
    $target = $targetStmt->get_result()->fetch_assoc();
    $targetStmt->close();

    if (!$target || empty($target['schedule']) || $target['schedule'] === 'TBA') {
        return false;
    }

    $targetParsed = parseSchedule($target['schedule']);
    if (!$targetParsed)
        return false;

    // Get all current active enrollments for this student/semester
    $stmt = $conn->prepare("
        SELECT cs.section_name, cs.schedule, s.subject_id, s.subject_name 
        FROM enrollments e
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
        JOIN subjects s ON cur.subject_id = s.subject_id
        WHERE e.student_id = ? 
          AND cs.semester = ? 
          AND cs.school_year = ? 
          AND cs.status = 'active'
          AND e.status != 'dropped'
    ");
    $stmt->bind_param("iss", $studentId, $semester, $schoolYear);
    $stmt->execute();
    $enrollments = $stmt->get_result();

    while ($e = $enrollments->fetch_assoc()) {
        $existingParsed = parseSchedule($e['schedule']);
        if ($existingParsed && isScheduleOverlapping($targetParsed, $existingParsed)) {
            $stmt->close();
            return [
                'type' => 'Schedule Conflict',
                'msg' => "Conflicts with '{$e['course_code']} - {$e['course_name']}' which is scheduled at '{$e['schedule']}'.",
                'existing' => $e
            ];
        }
    }
    $stmt->close();
    return false;
}

/**
 * Get recommended sections for a subject that don't conflict with student schedule
 */
function getScheduleRecommendations($courseId, $studentId, $semester, $schoolYear)
{
    $conn = getDBConnection();

    // Get all other active sections for this subject
    $stmt = $conn->prepare("
        SELECT cs.*, 
               (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id AND status != 'dropped') as enrolled_count
        FROM class_sections cs
        WHERE cs.curriculum_id = ? 
          AND cs.semester = ? 
          AND cs.school_year = ? 
          AND cs.status = 'active'
    ");
    $stmt->bind_param("iss", $courseId, $semester, $schoolYear);
    $stmt->execute();
    $sections = $stmt->get_result();

    $recommendations = [];
    while ($sec = $sections->fetch_assoc()) {
        // Check capacity
        if ($sec['enrolled_count'] >= ($sec['max_students'] ?? 40))
            continue;

        // Check conflict for this specific section
        if (!checkStudentScheduleConflict($studentId, $sec['section_id'], $semester, $schoolYear)) {
            $recommendations[] = $sec;
        }
    }

    $stmt->close();
    return $recommendations;
}

/**
 * Display recommendations as an alert string
 */
function showRecommendations($recs)
{
    if (empty($recs))
        return "No other vacant sections found for this subject that fit the student's schedule.";

    $html = "Recommended Vacant Sections:<br><ul class='mb-0'>";
    foreach ($recs as $r) {
        $html .= "<li><strong>{$r['section_name']}</strong>: {$r['schedule']} (Room: {$r['room']})</li>";
    }
    $html .= "</ul>";
    return $html;
}

/**
 * Check for section schedule conflicts (Instructor or Room)
 * Enhanced to use intelligent overlap detection.
 */
function checkSectionConflict($instructorId, $room, $schedule, $semester, $schoolYear, $excludeSectionId = null)
{
    if (empty($schedule) || strtoupper($schedule) === 'TBA') {
        return false;
    }

    $targetParsed = parseSchedule($schedule);
    if (!$targetParsed)
        return false;

    $conn = getDBConnection();

    // Check Instructor Conflict
    $sql = "SELECT cs.*, s.subject_id, s.subject_name 
            FROM class_sections cs 
            JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
            JOIN subjects s ON cur.subject_id = s.subject_id
            WHERE cs.instructor_id = ? 
              AND cs.semester = ? 
              AND cs.school_year = ? 
              AND cs.status = 'active'";

    if ($excludeSectionId) {
        $sql .= " AND cs.section_id != ?";
    }

    $stmt = $conn->prepare($sql);
    if ($excludeSectionId) {
        $stmt->bind_param("issi", $instructorId, $semester, $schoolYear, $excludeSectionId);
    }
    else {
        $stmt->bind_param("iss", $instructorId, $semester, $schoolYear);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($conflict = $res->fetch_assoc()) {
        $existingParsed = parseSchedule($conflict['schedule']);
        if ($existingParsed && isScheduleOverlapping($targetParsed, $existingParsed)) {
            $stmt->close();
            return ['type' => 'Instructor', 'msg' => "Instructor already has a class '{$conflict['subject_id']}' at '{$conflict['schedule']}'.", 'data' => $conflict];
        }
    }
    $stmt->close();

    // Check Room Conflict (ignore TBA)
    if (!empty($room) && strtoupper($room) !== 'TBA') {
        $sql = "SELECT cs.*, s.subject_id, s.subject_name 
                FROM class_sections cs 
                JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
                JOIN subjects s ON cur.subject_id = s.subject_id
                WHERE cs.room = ? 
                  AND cs.semester = ? 
                  AND cs.school_year = ?
                  AND cs.status = 'active'";

        if ($excludeSectionId) {
            $sql .= " AND cs.section_id != ?";
        }

        $stmt = $conn->prepare($sql);
        if ($excludeSectionId) {
            $stmt->bind_param("sssi", $room, $semester, $schoolYear, $excludeSectionId);
        }
        else {
            $stmt->bind_param("sss", $room, $semester, $schoolYear);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        while ($conflict = $res->fetch_assoc()) {
            $existingParsed = parseSchedule($conflict['schedule']);
            if ($existingParsed && isScheduleOverlapping($targetParsed, $existingParsed)) {
                $stmt->close();
                return ['type' => 'Room', 'msg' => "Room is already occupied by '{$conflict['subject_id']}' at '{$conflict['schedule']}'.", 'data' => $conflict];
            }
        }
        $stmt->close();
    }

    return false;
}

/**
 * Check if an IP address is rate limited and get remaining time.
 * @param string $ipAddress The IP address of the user.
 * @param int $maxAttempts The maximum number of allowed attempts.
 * @param int $lockoutTime The lockout duration in minutes.
 * @return array|bool Array with 'minutes' and 'seconds' if locked out, false otherwise.
 */
function getLockoutState($ipAddress, $maxAttempts = 5, $lockoutTime = 15) {
    if (empty($ipAddress)) return false;
    
    $conn = getDBConnection();
    // Check records within the lockout period and get remaining time via MySQL to avoid timezone issues
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as attempts,
            TIMESTAMPDIFF(MINUTE, NOW(), MAX(attempt_time) + INTERVAL ? MINUTE) as remaining_minutes,
            TIMESTAMPDIFF(SECOND, NOW(), MAX(attempt_time) + INTERVAL ? MINUTE) as remaining_seconds
        FROM login_attempts 
        WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("iisi", $lockoutTime, $lockoutTime, $ipAddress, $lockoutTime);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['attempts'] >= $maxAttempts) {
        $secs = max(0, $result['remaining_seconds']);
        $mins = floor($secs / 60);
        $rem_secs = $secs % 60;
        return ['minutes' => $mins, 'seconds' => $rem_secs];
    }
    
    return false;
}

/**
 * Log a failed login attempt for an IP address.
 * @param string $ipAddress The IP address of the user.
 */
function logFailedAttempt($ipAddress) {
    if (empty($ipAddress)) return;
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
    $stmt->bind_param("s", $ipAddress);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clear failed login records for an IP address after a successful login.
 * @param string $ipAddress The IP address of the user.
 */
function clearLoginAttempts($ipAddress) {
    if (empty($ipAddress)) return;
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ipAddress);
    $stmt->execute();
    $stmt->close();
}
/**
 * Return a human-readable relative time string (e.g., "5 mins ago")
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime)
{
    if (empty($datetime)) return "Never";
    
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return "Just now";
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        86400 => 'day',
        3600 => 'hour',
        60 => 'min'
    ];
    
    foreach ($intervals as $secs => $label) {
        $count = floor($diff / $secs);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    
    return "Recently";
}
/**
 * Safe Database Translator
 * Maps legacy "Course" requirements to the new "Curriculum/Subject" schema.
 */
function getDepartmentGradesQuery($deptId, $limit = null) {
    $conn = getDBConnection();
    $limitSql = $limit ? "LIMIT " . (int)$limit : "";
    
    $sql = "
        SELECT 
            g.*, 
            s.first_name, s.last_name, s.student_no,
            subj.subject_id AS course_code, 
            subj.subject_name AS course_name,
            subj.units,
            i.first_name AS inst_first, i.last_name AS inst_last
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN students s ON e.student_id = s.student_id
        JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
        JOIN subjects subj ON cur.subject_id = subj.subject_id
        JOIN instructors i ON cs.instructor_id = i.instructor_id
        WHERE cur.dept_id = ?
        ORDER BY g.updated_at DESC
        $limitSql
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $deptId);
    $stmt->execute();
    return $stmt->get_result();
}
?>
