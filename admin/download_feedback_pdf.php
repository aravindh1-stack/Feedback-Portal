<?php
require_once '../config/db.php'; // Your database connection
require_once __DIR__ . '/../fpdf186/fpdf.php';

// A custom PDF class to handle the specific header and footer
class FinalReportPDF extends FPDF
{
    // Page header
    public function Header()
    {
        $this->Image(__DIR__ . '/../assets/images/college_logo.png', 10, 10, 50);
        
        $this->SetY(14);
        $this->SetX(65);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Sri Shanmugha College of Engineering and Technology', 0, 1, 'C');
        
        $this->SetX(65);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, '(Autonomous)', 0, 1, 'C');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(192, 0, 0);
        $this->Cell(0, 10, 'Staff Feedback Analytics Report', 0, 1, 'C');
        $this->Ln(2);
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

/**
 * NEW: Converts an integer to a Roman numeral.
 */
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
// DATABASE FUNCTIONS (IMPORTANT: ADAPT THESE QUERIES)
// =============================================================================

function fetchReportData(mysqli $conn, array $filters): array
{
    // --- IMPORTANT ---
    // This is a placeholder function. You must adapt the SQL queries below.
    
    // Query for summary
    $summary_sql = "SELECT 
        (SELECT COUNT(DISTINCT s.id) FROM students s WHERE s.department = ? AND s.year = ? AND s.semester = ?) as class_strength,
        COUNT(DISTINCT resp.student_id) as forms_submitted,
        AVG(resp.rating) as average_rating
        FROM feedback_responses resp JOIN students s ON resp.student_id = s.id
        WHERE s.department = ? AND s.year = ? AND s.semester = ?";
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ssisss", $filters['department'], $filters['year'], $filters['semester'], $filters['department'], $filters['year'], $filters['semester']);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Query for subject details
    $details_sql = "SELECT f.subject_code, fac.name as faculty_name, AVG(fr.rating) as avg_rating
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        JOIN students s ON fr.student_id = s.id
        JOIN faculty fac ON f.faculty_id = fac.id
        WHERE s.department = ? AND s.year = ? AND s.semester = ?
        GROUP BY f.subject_code, fac.name";
    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ssi", $filters['department'], $filters['year'], $filters['semester']);
    $stmt->execute();
    $details_result = $stmt->get_result();
    
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
    $pdf->Cell(0, 8, $title, 1, 1, 'C', true);
}

function drawSummarySection(FPDF $pdf, array $summary, array $filters) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150);
    $cellWidth = 190 / 4;
    $cellHeight = 7;

    $percentage = isset($summary['average_rating']) ? ($summary['average_rating'] / 5.0) * 100 : 0;
    $gradeDetails = getGradeDetails($percentage);

    $pdf->Cell($cellWidth, $cellHeight, 'Class Strength: ' . ($summary['class_strength'] ?? 'N/A'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Forms Submitted: ' . ($summary['forms_submitted'] ?? 'N/A'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Department: ' . $filters['department'], 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Report Date: ' . date('d-M-Y'), 1, 1, 'L');

    $pdf->Cell($cellWidth, $cellHeight, 'Average Rating: ' . round($summary['average_rating'] ?? 0, 2) . '/5.0', 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Overall Grade: ' . $gradeDetails['grade'], 1, 0, 'L');
    // MODIFICATION: Use toRoman() for year and semester
    $pdf->Cell($cellWidth, $cellHeight, 'Year: ' . toRoman($filters['year']), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Semester: ' . toRoman($filters['semester']), 1, 1, 'L');
    
    $pdf->Cell($cellWidth, $cellHeight, 'Percentage: ' . round($percentage, 2) . '%', 1, 0, 'L');
    $pdf->Cell($cellWidth * 2, $cellHeight, 'Performance Status: ' . $gradeDetails['status'], 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, '', 'TBR', 1, 'L');
    $pdf->Ln(5);
}

function drawGradeDistributionSection(FPDF $pdf, array $gradeCounts, int $totalSubjects) {
    drawTitleBar($pdf, 'Grade Distribution Analysis');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $widths = [15, 20, 25, 30, 100];

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
    $widths = [40, 25, 45, 25, 55];
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

// MODIFICATION: Removed Department, Year, Sem columns and redistributed widths
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
        $pdf->Cell(array_sum($widths), 10, 'No subject data available.', 1, 1, 'C');
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
    $filters = [
        'department' => $_GET['dept'] ?? 'ECE',
        'year'       => $_GET['year'] ?? '2',
        'semester'   => $_GET['sem'] ?? '3',
    ];

    $reportData = fetchReportData($conn, $filters);

    $pdf = new FinalReportPDF();
    $pdf->SetTitle('Staff Feedback Analytics Report');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    
    // MODIFICATION: Title changed from "Applied Filters"
    drawTitleBar($pdf, 'Academic Details');
    $pdf->SetFont('Arial','',9);
    // MODIFICATION: Use toRoman() for year and semester
    $pdf->Cell(190/3, 7, 'Department: '.$filters['department'], 1, 0, 'L');
    $pdf->Cell(190/3, 7, 'Year: '.toRoman($filters['year']), 1, 0, 'L');
    $pdf->Cell(190/3, 7, 'Semester: '.toRoman($filters['semester']), 1, 1, 'L');
    $pdf->Ln(5);
    
    drawTitleBar($pdf, 'Overall Performance Summary');
    drawSummarySection($pdf, $reportData['summary'], $filters);
    
    drawGradeDistributionSection($pdf, $reportData['gradeCounts'], $reportData['stats']['total_subjects']);
    
    drawStatsSection($pdf, $reportData['stats']);

    drawSubjectDetailsSection($pdf, $reportData['subjectDetails']);

    $pdf->Output('D', 'Staff_Feedback_Report_' . date('Y-m-d') . '.pdf');

} catch (Exception $e) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo 'Error generating PDF: ' . $e->getMessage();
}