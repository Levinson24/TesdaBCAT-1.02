<?php
/**
 * Excel Export Functions
 * TESDA-BCAT Grade Management System
 * Requires: composer require phpoffice/phpspreadsheet
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Export student transcript to Excel
 * @param int $studentId
 * @return string Filename of generated Excel file
 */
function exportTranscriptToExcel($studentId)
{
    // Note: This requires PHPSpreadsheet to be installed via Composer
    // For a simpler implementation without dependencies, see the alternative function below

    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback to simple CSV export
        return exportTranscriptToCSV($studentId);
    }

    $conn = getDBConnection();

    // Get student information
    $stmt = $conn->prepare("
        SELECT s.*, u.username
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.student_id = ?
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        return false;
    }

    // Get student grades
    $stmt = $conn->prepare("
        SELECT 
            c.course_code,
            c.course_name,
            c.units,
            cs.semester,
            cs.school_year,
            g.midterm,
            g.final,
            g.grade,
            g.remarks
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.course_id
        WHERE g.student_id = ? AND g.status = 'approved'
        ORDER BY cs.school_year, cs.semester, c.course_code
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Create spreadsheet using PHPSpreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header
    $sheet->setCellValue('A1', 'Republic of the Philippines');
    $sheet->setCellValue('A2', 'Technical Education and Skills Development Authority');
    $sheet->setCellValue('A3', getSetting('school_region', 'Region VIII'));
    $sheet->setCellValue('A4', getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES'));
    $sheet->setCellValue('A5', getSetting('school_address', 'Allen, Northern Samar'));
    $sheet->setCellValue('A7', 'OFFICIAL TRANSCRIPT OF RECORDS');

    // Student Information
    $row = 9;
    $fullName = $student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '');
    $sheet->setCellValue('A' . $row, 'Name:');
    $sheet->setCellValue('D' . $row, strtoupper($fullName));

    $row++;
    $sheet->setCellValue('A' . $row, 'Student No:');
    $sheet->setCellValue('D' . $row, $student['student_no']);

    $row++;
    $sheet->setCellValue('A' . $row, 'Address:');
    $sheet->setCellValue('D' . $row, $student['address']);

    $row++;
    $sheet->setCellValue('A' . $row, 'Course:');
    $sheet->setCellValue('D' . $row, $student['course']);

    // Grades table header
    $row += 2;
    $headerRow = $row;
    $headers = ['School Year', 'Semester', 'Course Code', 'Subject Description', 'Units', 'Midterm', 'Final', 'Grade', 'Remarks'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    // Grades data
    $row++;
    foreach ($grades as $grade) {
        $sheet->setCellValue('A' . $row, $grade['school_year']);
        $sheet->setCellValue('B' . $row, $grade['semester']);
        $sheet->setCellValue('C' . $row, $grade['course_code']);
        $sheet->setCellValue('D' . $row, $grade['course_name']);
        $sheet->setCellValue('E' . $row, $grade['units']);
        $sheet->setCellValue('F' . $row, $grade['midterm']);
        $sheet->setCellValue('G' . $row, $grade['final']);
        $sheet->setCellValue('H' . $row, $grade['grade']);
        $sheet->setCellValue('I' . $row, $grade['remarks']);
        $row++;
    }

    // Summary
    $row += 1;
    $gwa = calculateGWA($studentId);
    $honors = getLatinHonors($gwa);

    $sheet->setCellValue('G' . $row, 'GWA:');
    $sheet->setCellValue('H' . $row, number_format($gwa, 2));
    $sheet->getStyle('G' . $row . ':H' . $row)->getFont()->setBold(true);

    if ($honors) {
        $row++;
        $sheet->setCellValue('G' . $row, 'Honors:');
        $sheet->setCellValue('H' . $row, $honors);
        $sheet->getStyle('G' . $row . ':H' . $row)->getFont()->setBold(true);
    }

    // Legend
    $row += 2;
    $sheet->setCellValue('A' . $row, 'CHED GRADING SYSTEM:');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
    $sheet->setCellValue('A' . $row, '1.00-1.25: Excellent');
    $sheet->setCellValue('D' . $row, '2.50-2.75: Satisfactory');
    $row++;
    $sheet->setCellValue('A' . $row, '1.50-1.75: Very Good');
    $sheet->setCellValue('D' . $row, '3.00: Passing');
    $row++;
    $sheet->setCellValue('A' . $row, '2.00-2.25: Good');
    $sheet->setCellValue('D' . $row, '5.00: Failure');

    // Styling
    $sheet->getStyle('A1:A7')->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF2c3e50');
    $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->getFont()->getColor()->setARGB('FFFFFFFF');

    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Save file
    $filename = 'Transcript_' . $student['student_no'] . '_' . date('Ymd_His') . '.xlsx';
    $filepath = __DIR__ . '/../exports/' . $filename;

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filepath);

    return $filename;
}

/**
 * Export student transcript to CSV (simple alternative without dependencies)
 * @param int $studentId
 * @return string Filename of generated CSV file
 */
function exportTranscriptToCSV($studentId)
{
    $conn = getDBConnection();

    // Get student information
    $stmt = $conn->prepare("
        SELECT s.*, u.username
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.student_id = ?
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        return false;
    }

    // Get student grades
    $stmt = $conn->prepare("
        SELECT 
            c.course_code,
            c.course_name,
            c.units,
            cs.semester,
            cs.school_year,
            g.midterm,
            g.final,
            g.grade,
            g.remarks
        FROM grades g
        JOIN enrollments e ON g.enrollment_id = e.enrollment_id
        JOIN class_sections cs ON e.section_id = cs.section_id
        JOIN courses c ON cs.course_id = c.course_id
        WHERE g.student_id = ? AND g.status = 'approved'
        ORDER BY cs.school_year, cs.semester, c.course_code
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Create CSV file
    $filename = 'Transcript_' . $student['student_no'] . '_' . date('Ymd_His') . '.csv';
    $filepath = __DIR__ . '/../exports/' . $filename;

    $file = fopen($filepath, 'w');

    // Header
    fputcsv($file, ['Republic of the Philippines']);
    fputcsv($file, ['Technical Education and Skills Development Authority']);
    fputcsv($file, [getSetting('school_region', 'Region VIII')]);
    fputcsv($file, [getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES')]);
    fputcsv($file, [getSetting('school_address', 'Allen, Northern Samar')]);
    fputcsv($file, []);
    fputcsv($file, ['OFFICIAL TRANSCRIPT OF RECORDS']);
    fputcsv($file, []);

    // Student Information
    $fullName = $student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '');
    fputcsv($file, ['Name:', strtoupper($fullName)]);
    fputcsv($file, ['Student No:', $student['student_no']]);
    fputcsv($file, ['Address:', $student['address']]);
    fputcsv($file, ['Course:', $student['course']]);
    fputcsv($file, []);

    // Grades table
    foreach ($grades as $grade) {
        fputcsv($file, [
            $grade['school_year'],
            $grade['semester'],
            $grade['course_code'],
            $grade['course_name'],
            $grade['units'],
            $grade['midterm'],
            $grade['final'],
            $grade['grade'],
            $grade['remarks']
        ]);
    }

    fputcsv($file, []);
    $gwa = calculateGWA($studentId);
    $honors = getLatinHonors($gwa);
    fputcsv($file, ['', '', '', '', '', '', 'GWA:', number_format($gwa, 2)]);
    if ($honors) {
        fputcsv($file, ['', '', '', '', '', '', 'Honors:', $honors]);
    }

    fputcsv($file, []);
    fputcsv($file, ['CHED GRADING SYSTEM:']);
    fputcsv($file, ['1.00-1.25: Excellent', '2.50-2.75: Satisfactory']);
    fputcsv($file, ['1.50-1.75: Very Good', '3.00: Passing']);
    fputcsv($file, ['2.00-2.25: Good', '5.00: Failure']);

    fclose($file);

    return $filename;
}

/**
 * Export grades for a class section
 * @param int $sectionId
 * @return string Filename
 */
function exportClassGradesToCSV($sectionId)
{
    $conn = getDBConnection();

    // Get section information
    $stmt = $conn->prepare("
        SELECT cs.*, c.course_code, c.course_name,
               CONCAT(i.first_name, ' ', i.last_name) as instructor_name
        FROM class_sections cs
        JOIN courses c ON cs.course_id = c.course_id
        JOIN instructors i ON cs.instructor_id = i.instructor_id
        WHERE cs.section_id = ?
    ");
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get grades
    $stmt = $conn->prepare("
        SELECT 
            s.student_no,
            CONCAT(s.last_name, ', ', s.first_name, ' ', COALESCE(s.middle_name, '')) as student_name,
            g.midterm,
            g.final,
            g.grade,
            g.remarks,
            g.status
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
        WHERE e.section_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Create CSV
    $filename = 'Grades_' . $section['course_code'] . '_' . $section['section_name'] . '_' . date('Ymd_His') . '.csv';
    $filepath = __DIR__ . '/../exports/' . $filename;

    $file = fopen($filepath, 'w');

    // Header
    fputcsv($file, [getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES')]);
    fputcsv($file, ['Class Grade Sheet']);
    fputcsv($file, []);
    fputcsv($file, ['Course:', $section['course_code'] . ' - ' . $section['course_name']]);
    fputcsv($file, ['Section:', $section['section_name']]);
    fputcsv($file, ['Instructor:', $section['instructor_name']]);
    fputcsv($file, ['School Year:', $section['school_year']]);
    fputcsv($file, ['Semester:', $section['semester']]);
    fputcsv($file, []);

    // Grades table
    fputcsv($file, ['Student No', 'Student Name', 'Midterm', 'Final', 'Grade', 'Remarks', 'Status']);

    foreach ($grades as $grade) {
        fputcsv($file, [
            $grade['student_no'],
            $grade['student_name'],
            $grade['midterm'] ?? '',
            $grade['final'] ?? '',
            $grade['grade'] ?? '',
            $grade['remarks'] ?? '',
            $grade['status'] ?? 'Not Graded'
        ]);
    }

    fclose($file);

    return $filename;
}
?>
