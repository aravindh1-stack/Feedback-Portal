<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Role check: Ensure the user is a faculty member
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    die('Access Denied: You do not have permission to view this page.');
}

require_once '../config/db.php';
require_once '../fpdf186/fpdf.php'; // Correct path to FPDF

// Helper function to convert numbers to Roman numerals (for year/semester)
function toRoman(string $number): string
{
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI', '7' => 'VII', '8' => 'VIII'];
    return $map[$number] ?? $number;
}

// Helper function to determine grade based on percentage
function getGradeDetails(float $percentage): array
{
    if ($percentage >= 91) return ['grade' => 'A+', 'status' => 'Outstanding', 'desc' => '91-100% - Exceptional teaching performance'];
    if ($percentage >= 81) return ['grade' => 'A', 'status' => 'Excellent', 'desc' => '81-90% - Very good teaching effectiveness'];
    if ($percentage >= 71) return ['grade' => 'B+', 'status' => 'Very Good', 'desc' => '71-80% - Good teaching performance'];
    if ($percentage >= 61) return ['grade' => 'B', 'status' => 'Good', 'desc' => '61-70% - Satisfactory teaching methods'];
    if ($percentage >= 51) return ['grade' => 'C', 'status' => 'Average', 'desc' => '51-60% - Needs some improvement'];
    return ['grade' => 'D', 'status' => 'Below Average', 'desc' => 'Below 51% - Requires significant improvement'];
}

class FacultyReportPDF extends FPDF
{
    // Page header
    public function Header()
    {
        if (file_exists(__DIR__ . '/../assets/images/college_logo.png')) {
            $this->Image(__DIR__ . '/../assets/images/college_logo.png', 10, 10, 50);
        }
        
        $this->SetY(14);
        $this->SetX(65); // Move to the right of the logo
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Sri Shanmugha College of Engineering and Technology', 0, 1, 'C');
        
        $this->SetX(65);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, '(Autonomous)', 0, 1, 'C');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(192, 0, 0);
        $this->Cell(0, 10, 'Faculty Feedback Analytics Report', 0, 1, 'C');
        $this->Ln(2);
    }

    // Page footer
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Generated on: ' . date('d-M-Y H:i') . ' | Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Main script execution
try {
    $faculty_id = $_SESSION['user']['id'];
    $faculty_name = $_SESSION['user']['name'];

    // Get filters from URL
    $filters = [
        'department' => $_GET['department'] ?? '',
        'year'       => $_GET['year'] ?? '',
        'semester'   => $_GET['semester'] ?? '',
    ];

    // Fetch report data
    $reportData = fetchFacultyReportData($conn, $faculty_id, $filters);

    // Create PDF
    $pdf = new FacultyReportPDF();
    $pdf->AliasNbPages();
    $pdf->SetTitle('Faculty Feedback Analytics Report');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Draw content
    drawTitleBar($pdf, 'Academic & Faculty Details');
    drawFacultyDetails($pdf, $faculty_name, $filters);
    
    drawTitleBar($pdf, 'Overall Performance Summary');
    drawSummarySection($pdf, $reportData['summary'], $reportData['total_subjects']);

    drawGradeDistributionSection($pdf, $reportData['gradeCounts'], $reportData['total_subjects']);

    drawSubjectDetailsSection($pdf, $reportData['subjectDetails']);

    // Output PDF
    $pdf->Output('D', 'Faculty_Feedback_Report_' . date('Y-m-d') . '.pdf');

} catch (Exception $e) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo 'Error generating PDF: ' . $e->getMessage();
}

function fetchFacultyReportData(mysqli $conn, int $faculty_id, array $filters): array
{
    $where = [];
    $params = [$faculty_id];
    $types = 'i';

    if (!empty($filters['department'])) { $where[] = 's.department = ?'; $params[] = $filters['department']; $types .= 's'; }
    if (!empty($filters['year'])) { $where[] = 's.year = ?'; $params[] = $filters['year']; $types .= 's'; }
    if (!empty($filters['semester'])) { $where[] = 's.semester = ?'; $params[] = $filters['semester']; $types .= 's'; }
    $where_sql = count($where) > 0 ? ' AND ' . implode(' AND ', $where) : '';

    $summary_sql = "SELECT COUNT(DISTINCT s.id) as class_strength, COUNT(DISTINCT resp.student_id) as forms_submitted, AVG(resp.rating) as average_rating FROM feedback_responses resp JOIN students s ON resp.student_id = s.id WHERE resp.faculty_id = ? $where_sql";
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    $subject_sql = "SELECT f.subject_code, AVG(fr.rating) as avg_rating FROM feedback_responses fr JOIN feedback_forms f ON fr.form_id = f.id JOIN students s ON fr.student_id = s.id WHERE fr.faculty_id = ? $where_sql GROUP BY f.subject_code";
    $stmt = $conn->prepare($subject_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $details_result = $stmt->get_result();
    
    $subjectDetails = [];
    $allRatings = [];
    $gradeCounts = ['A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    while ($row = $details_result->fetch_assoc()) {
        $percentage = ($row['avg_rating'] / 5.0) * 100;
        $gradeDetails = getGradeDetails($percentage);
        $allRatings[] = $row['avg_rating'];
        $subjectDetails[] = ['subject_code' => $row['subject_code'], 'avg_rating' => round($row['avg_rating'], 2), 'grade' => $gradeDetails['grade'], 'status' => $gradeDetails['status']];
        if (isset($gradeCounts[$gradeDetails['grade']])) $gradeCounts[$gradeDetails['grade']]++;
    }

    $totalSubjects = count($subjectDetails);
    
    return [
        'summary' => $summary, 
        'subjectDetails' => $subjectDetails, 
        'gradeCounts' => $gradeCounts,
        'total_subjects' => $totalSubjects
    ];
}

function drawTitleBar(FPDF $pdf, string $title)
{
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, $title, 1, 1, 'C', true);
    $pdf->Ln(2);
}

function drawFacultyDetails(FPDF $pdf, string $faculty_name, array $filters)
{
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(40, 7, 'Faculty Name:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(150, 7, $faculty_name, 1, 1, 'L');

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, 'Department:', 1, 0, 'L', true);
    $pdf->Cell(150, 7, $filters['department'] ?: 'All Departments', 1, 1, 'L');
    $pdf->Cell(40, 7, 'Academic Year:', 1, 0, 'L', true);
    $pdf->Cell(150, 7, $filters['year'] ? toRoman($filters['year']) : 'All Years', 1, 1, 'L');
    $pdf->Cell(40, 7, 'Semester:', 1, 0, 'L', true);
    $pdf->Cell(150, 7, $filters['semester'] ? toRoman($filters['semester']) : 'All Semesters', 1, 1, 'L');
    $pdf->Ln(5);
}

function drawSummarySection(FPDF $pdf, array $summary, int $totalSubjects)
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(150);
    $cellWidth = 190 / 4;
    $cellHeight = 7;

    $percentage = isset($summary['average_rating']) ? ($summary['average_rating'] / 5.0) * 100 : 0;
    $gradeDetails = getGradeDetails($percentage);

    $pdf->Cell($cellWidth, $cellHeight, 'Class Strength: ' . ($summary['class_strength'] ?? 'N/A'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Forms Submitted: ' . ($summary['forms_submitted'] ?? 'N/A'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Total Subjects: ' . $totalSubjects, 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Report Date: ' . date('d-M-Y'), 1, 1, 'L');

    $pdf->Cell($cellWidth, $cellHeight, 'Average Rating: ' . round($summary['average_rating'] ?? 0, 2) . '/5.0', 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Overall Grade: ' . $gradeDetails['grade'], 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Percentage: ' . round($percentage, 2) . '%', 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Status: ' . $gradeDetails['status'], 1, 1, 'L');
    $pdf->Ln(5);
}

function drawGradeDistributionSection(FPDF $pdf, array $gradeCounts, int $totalSubjects)
{
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


function drawSubjectDetailsSection(FPDF $pdf, array $details)
{
    drawTitleBar($pdf, 'Subject-wise Performance Details');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $widths = [60, 40, 30, 60];

    $pdf->Cell($widths[0], 7, 'Subject Code', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 7, 'Avg Rating', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 7, 'Grade', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 7, 'Performance Status', 1, 1, 'C', true);

    if (empty($details)) {
        $pdf->Cell(array_sum($widths), 10, 'No subject data available for the selected filters.', 1, 1, 'C');
        return;
    }
    $pdf->SetFont('Arial', '', 9);
    foreach ($details as $row) {
        $pdf->Cell($widths[0], 7, $row['subject_code'], 1, 0, 'L');
        $pdf->Cell($widths[1], 7, $row['avg_rating'], 1, 0, 'C');
        $pdf->Cell($widths[2], 7, $row['grade'], 1, 0, 'C');
        $pdf->Cell($widths[3], 7, $row['status'], 1, 1, 'L');
    }
}
