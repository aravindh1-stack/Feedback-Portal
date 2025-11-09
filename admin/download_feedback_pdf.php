<?php
// Entha session'il user login seithullara enbathaiyum, avarudaya role'aiyum ariya, session'ai thodanga.
session_start();

// User login seyyamal irunthalo allathu admin aaga illamalo irunthal, login pakkathirku thiruppi anuppa vendum.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Include database connection
require_once '../config/db.php';
require_once __DIR__ . '/../fpdf186/fpdf.php'; // Make sure this path is correct

// A custom PDF class to handle the specific header and footer
class FinalReportPDF extends FPDF
{
    // Page header
    public function Header()
    {
        // Unga server-la intha path correct-a irukka-nu check pannikonga
        $logoPath = __DIR__ . '/../assets/images/college_logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 50);
        }
        
        $this->SetY(14);
        $this->SetX(65); // 10mm (margin) + 50mm (logo width) + 5mm (padding)
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Sri Shanmugha College of Engineering and Technology', 0, 1, 'C');
        
        $this->SetX(65); // Align this text also
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, '(Autonomous)', 0, 1, 'C');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(192, 0, 0);
        $this->Cell(0, 10, 'Staff Feedback Analytics Report', 0, 1, 'C');
        $this->Ln(5); // Add more space after header
    }

    // Page footer
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $generatedText = 'Generated on: ' . date('d-M-Y H:i');
        $this->Cell(0, 10, $generatedText . ' | Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function toRoman(string $number): string
{
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI', '7' => 'VII', '8' => 'VIII'];
    return $map[$number] ?? $number; // Return the original number if not found in the map
}

function getGradeDetails(float $percentage): array
{
    if ($percentage >= 91) return ['grade' => 'A+', 'status' => 'Outstanding', 'desc' => '91-100% - Exceptional teaching performance'];
    if ($percentage >= 81) return ['grade' => 'A', 'status' => 'Excellent', 'desc' => '81-90% - Very good teaching effectiveness'];
    if ($percentage >= 71) return ['grade' => 'B+', 'status' => 'Very Good', 'desc' => '71-80% - Good teaching performance'];
    if ($percentage >= 61) return ['grade' => 'B', 'status' => 'Good', 'desc' => '61-70% - Satisfactory teaching methods'];
    if ($percentage >= 51) return ['grade' => 'C', 'status' => 'Average', 'desc' => '51-60% - Needs some improvement'];
    return ['grade' => 'D', 'status' => 'Below Average', 'desc' => 'Below 51% - Requires significant improvement'];
}

// =============================================================================
// DATABASE FUNCTIONS (MODIFIED FOR ACCURACY)
// =============================================================================

function fetchReportData(mysqli $conn, string $form_where_clause, array $form_params, string $form_types, string $student_where_clause, array $student_params, string $student_types): array
{
    // --- Summary Query ---
    
    // 1. Class Strength
    $class_strength_sql = "SELECT COUNT(DISTINCT s.id) AS class_strength FROM students s $student_where_clause";
    $stmt = $conn->prepare($class_strength_sql);
    if (!empty($student_params)) $stmt->bind_param($student_types, ...$student_params);
    $stmt->execute();
    $class_strength = $stmt->get_result()->fetch_assoc()['class_strength'] ?? 0;
    $stmt->close();

    // 2. Responses & Avg Rating (Neenga ketta maathiri maathiyachu - Changed as you asked)
    $responses_sql = "SELECT 
                        COALESCE(AVG(fr.rating), 0) AS average_rating, 
                        COUNT(DISTINCT fr.student_id) AS total_students_responded
                      FROM feedback_responses fr 
                      JOIN feedback_forms f ON fr.form_number = f.form_number
                      $form_where_clause";
    $stmt = $conn->prepare($responses_sql);
    if (!empty($form_params)) $stmt->bind_param($form_types, ...$form_params);
    $stmt->execute();
    $responses_summary = $stmt->get_result()->fetch_assoc() ?: ['average_rating' => 0, 'total_students_responded' => 0];
    $stmt->close();

    $summary = [
        'class_strength' => $class_strength,
        'average_rating' => $responses_summary['average_rating'],
        'total_students_responded' => $responses_summary['total_students_responded'] // Changed name
    ];
    
    // --- Details Query ---
    $details_sql = "SELECT 
                        f.subject_code,
                        COALESCE(fac.name, CONCAT('Faculty #', fr.faculty_id)) AS faculty_name,
                        AVG(fr.rating) AS avg_rating
                    FROM feedback_responses fr
                    JOIN feedback_forms f ON fr.form_number = f.form_number 
                        AND fr.subject_code = f.subject_code AND fr.faculty_id = f.faculty_id
                    LEFT JOIN faculty fac ON fr.faculty_id = fac.id
                    $form_where_clause
                    GROUP BY f.subject_code, fr.faculty_id, fac.name
                    ORDER BY f.subject_code";
    $stmt = $conn->prepare($details_sql);
    if (!empty($form_params)) $stmt->bind_param($form_types, ...$form_params);
    $stmt->execute();
    $details_result = $stmt->get_result();
    $stmt->close();
    
    $subjectDetails = [];
    $allRatings = [];
    while ($row = $details_result->fetch_assoc()) {
        $percentage = ($row['avg_rating'] / 5.0) * 100;
        $gradeDetails = getGradeDetails($percentage);
        $allRatings[] = $row['avg_rating'];
        $subjectDetails[] = [
            'subject_code' => $row['subject_code'], 'faculty_name' => $row['faculty_name'],
            'avg_rating' => round($row['avg_rating'], 2), 'grade' => $gradeDetails['grade'], 'status' => $gradeDetails['status'],
        ];
    }

    $gradeCounts = ['A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    foreach($subjectDetails as $subject) {
        if (isset($gradeCounts[$subject['grade']])) {
            $gradeCounts[$subject['grade']]++;
        }
    }

    $totalSubjects = count($subjectDetails);
    $std_dev = 0; $variance = 0;
    if ($totalSubjects > 1) {
        $mean = array_sum($allRatings) / $totalSubjects;
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $allRatings)) / $totalSubjects;
        $std_dev = sqrt($variance);
    }
    
    return [
        'summary' => $summary, 'subjectDetails' => $subjectDetails, 'gradeCounts' => $gradeCounts,
        'stats' => [
            'highest' => !empty($allRatings) ? max($allRatings) : 0, 'lowest' => !empty($allRatings) ? min($allRatings) : 0,
            'std_dev' => round($std_dev, 2), 'variance' => round($variance, 2), 'total_subjects' => $totalSubjects
        ]
    ];
}

// =============================================================================
// PDF DRAWING FUNCTIONS
// =============================================================================

function drawTitleBar(FPDF $pdf, string $title) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(190, 8, $title, 1, 1, 'C', true); // 190 total width
}

function drawSummarySection(FPDF $pdf, array $summary, array $filters) {
    drawTitleBar($pdf, 'Overall Performance Summary');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150);
    $cellWidth = 190 / 4;
    $cellHeight = 7;

    $percentage = isset($summary['average_rating']) ? ($summary['average_rating'] / 5.0) * 100 : 0;
    $gradeDetails = getGradeDetails($percentage);

    // Row 1
    $pdf->Cell($cellWidth, $cellHeight, 'Department: ' . (!empty($filters['department']) ? $filters['department'] : 'All'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Year: ' . (!empty($filters['year']) ? toRoman($filters['year']) : 'All'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Semester: ' . (!empty($filters['semester']) ? toRoman($filters['semester']) : 'All'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Report Date: ' . date('d-M-Y'), 1, 1, 'L');

    // Row 2
    $pdf->Cell($cellWidth, $cellHeight, 'Class Strength: ' . ($summary['class_strength'] ?? 'N/A'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Students Responded: ' . ($summary['total_students_responded'] ?? '0'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Avg Rating: ' . round($summary['average_rating'] ?? 0, 2) . '/5.0', 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Percentage: ' . round($percentage, 2) . '%', 1, 1, 'L');
    
    // Row 3
    $pdf->Cell($cellWidth, $cellHeight, 'Overall Grade: ' . $gradeDetails['grade'], 1, 0, 'L');
    $pdf->Cell($cellWidth * 2, $cellHeight, 'Performance Status: ' . $gradeDetails['status'], 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, '', 1, 1, 'L'); // Empty cell to fill the row
    
    $pdf->Ln(5);
}

function drawGradeDistributionSection(FPDF $pdf, array $gradeCounts, int $totalSubjects) {
    drawTitleBar($pdf, 'Grade Distribution Analysis');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $widths = [15, 20, 25, 30, 100]; // Total 190

    $pdf->Cell($widths[0], 7, 'Grade', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 7, 'Count', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 7, 'Percentage', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 7, 'Performance', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 7, 'Description', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $grades = ['A+', 'A', 'B+', 'B', 'C', 'D'];
    foreach($grades as $grade) {
        $details = getGradeDetails(($grade === 'A+' ? 95 : ($grade === 'A' ? 85 : ($grade === 'B+' ? 75 : ($grade === 'B' ? 65 : ($grade === 'C' ? 55 : 45))))));
        $count = $gradeCounts[$grade] ?? 0;
        $percentage = $totalSubjects > 0 ? round(($count / $totalSubjects) * 100, 2) : 0;
        
        $pdf->Cell($widths[0], 7, $grade, 1, 0, 'C');
        $pdf->Cell($widths[1], 7, $count, 1, 0, 'C');
        $pdf->Cell($widths[2], 7, $percentage . '%', 1, 0, 'C');
        $pdf->Cell($widths[3], 7, $details['status'], 1, 0, 'L');
        $pdf->Cell($widths[4], 7, $details['desc'], 1, 1, 'L');
    }
    $pdf->Ln(5);
}

function drawStatsSection(FPDF $pdf, array $stats) {
    drawTitleBar($pdf, 'Statistical Analysis');
    $pdf->SetFont('Arial', '', 9);
    $widths = [40, 25, 45, 25, 55]; // Total 190
    $range = $stats['highest'] - $stats['lowest'];

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($widths[0], 7, 'Metric', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 7, 'Value', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 7, 'Metric', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 7, 'Value', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 7, 'Additional Info', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell($widths[0], 7, 'Highest Average Rating', 1, 0, 'L');
    $pdf->Cell($widths[1], 7, number_format($stats['highest'], 2), 1, 0, 'C');
    $pdf->Cell($widths[2], 7, 'Lowest Average Rating', 1, 0, 'L');
    $pdf->Cell($widths[3], 7, number_format($stats['lowest'], 2), 1, 0, 'C');
    $pdf->Cell($widths[4], 7, 'Range: ' . number_format($range, 2), 1, 1, 'L');

    $pdf->Cell($widths[0], 7, 'Standard Deviation', 1, 0, 'L');
    $pdf->Cell($widths[1], 7, number_format($stats['std_dev'], 2), 1, 0, 'C');
    $pdf->Cell($widths[2], 7, 'Total Subjects Evaluated', 1, 0, 'L');
    $pdf->Cell($widths[3], 7, $stats['total_subjects'], 1, 0, 'C');
    $pdf->Cell($widths[4], 7, 'Variance: ' . number_format($stats['variance'], 2), 1, 1, 'L');
    $pdf->Ln(5);
}

function drawSubjectDetailsSection(FPDF $pdf, array $details) {
    drawTitleBar($pdf, 'Subject-wise Performance Details');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    // New widths: 35 + 90 + 20 + 15 + 30 = 190
    $widths = [35, 90, 20, 15, 30];

    $pdf->Cell($widths[0], 7, 'Subject Code', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 7, 'Faculty Name', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 7, 'Avg Rating', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 7, 'Grade', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 7, 'Performance Status', 1, 1, 'C', true);

    if (empty($details)) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(array_sum($widths), 10, 'No subject data available.', 1, 1, 'C');
        $pdf->Ln(5);
        return;
    }
    $pdf->SetFont('Arial', '', 9);
    foreach ($details as $row) {
        $pdf->Cell($widths[0], 7, $row['subject_code'], 1, 0, 'L');
        $pdf->Cell($widths[1], 7, $row['faculty_name'], 1, 0, 'L');
        $pdf->Cell($widths[2], 7, $row['avg_rating'], 1, 0, 'C');
        $pdf->Cell($widths[3], 7, $row['grade'], 1, 0, 'C');
        $pdf->Cell($widths[4], 7, $row['status'], 1, 1, 'L');
    }
}

// =============================================================================
// MAIN EXECUTION
// =============================================================================

try {
    // **PUTHU FILTER LOGIC**
    $filter_dept = $_GET['department'] ?? '';
    $filter_year = $_GET['year'] ?? '';
    $filter_sem = $_GET['semester'] ?? '';
    
    // Intha filters array PDF-la kaatrathukku mattum thaan
    $filters = [
        'department' => $filter_dept,
        'year'       => $filter_year,
        'semester'   => $filter_sem,
    ];

    // Build WHERE clause and params for JOINING on 'f' (feedback_forms)
    $form_where_conditions = [];
    $form_params = [];
    $form_types = '';
    if (!empty($filter_dept)) {
        $form_where_conditions[] = "f.department = ?";
        $form_params[] = $filter_dept;
        $form_types .= 's';
    }
    if (!empty($filter_year)) {
        $form_where_conditions[] = "f.year = ?";
        $form_params[] = $filter_year;
        $form_types .= 's';
    }
    if (!empty($filter_sem)) {
        $form_where_conditions[] = "f.semester = ?";
        $form_params[] = $filter_sem;
        $form_types .= 's';
    }
    $form_where_clause = !empty($form_where_conditions) ? 'WHERE ' . implode(' AND ', $form_where_conditions) : '';

    // Build WHERE clause and params for JOINING on 's' (students)
    $student_where_conditions = [];
    $student_params = [];
    $student_types = '';
    if (!empty($filter_dept)) {
        $student_where_conditions[] = "s.department = ?";
        $student_params[] = $filter_dept;
        $student_types .= 's';
    }
    if (!empty($filter_year)) {
        $student_where_conditions[] = "s.year = ?";
        $student_params[] = $filter_year;
        $student_types .= 's';
    }
    if (!empty($filter_sem)) {
        $student_where_conditions[] = "s.semester = ?";
        $student_params[] = $filter_sem;
        $student_types .= 's';
    }
    $student_where_clause = !empty($student_where_conditions) ? 'WHERE ' . implode(' AND ', $student_where_conditions) : '';
    
    // **PUTHU FUNCTION CALL**
    $reportData = fetchReportData(
        $conn, 
        $form_where_clause, $form_params, $form_types, 
        $student_where_clause, $student_params, $student_types
    );

    $pdf = new FinalReportPDF();
    $pdf->SetTitle('Staff Feedback Analytics Report');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    
    // The NEW summary section handles the filters
    drawSummarySection($pdf, $reportData['summary'], $filters);
    
    drawGradeDistributionSection($pdf, $reportData['gradeCounts'], $reportData['stats']['total_subjects']);
    
    drawStatsSection($pdf, $reportData['stats']);

    drawSubjectDetailsSection($pdf, $reportData['subjectDetails']);

    // === MAATHAM 3 (Center Alignment): HOD and Principal Signature Area ===
    $pdf->Ln(25); // Add space from the table above
    $pdf->Ln(10); // Space for the actual signature

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0);

    // (Left Signature Line - Centered within its 95mm cell)
    $pdf->Cell(95, 7, '_________________________', 0, 0, 'C');

    // (Right Signature Line - Centered within its 95mm cell)
    $pdf->Cell(95, 7, '_________________________', 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 10); // Bold for the title

    // (Left Signature Text - Centered within its 95mm cell)
    $pdf->Cell(95, 7, 'HOD Signature', 0, 0, 'C');

    // (Right Signature Text - Centered within its 95mm cell)
    $pdf->Cell(95, 7, 'Principal Signature', 0, 1, 'C');
    // === Maatham Mudinjadhu (Change End) ===


    $pdf->Output('D', 'Staff_Feedback_Report_' . date('Y-m-d') . '.pdf');

} catch (Exception $e) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo 'Error generating PDF: ' . $e->getMessage();
}