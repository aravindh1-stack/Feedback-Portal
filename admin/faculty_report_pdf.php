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

// Role check: Admin-um, Faculty-um (avanga sontha report) paakkalam
if (!isset($_SESSION['role'])) {
    http_response_code(403);
    die('Access Denied: You must be logged in.');
}

require_once '../config/db.php';
require_once __DIR__ . '/../fpdf186/fpdf.php'; // Correct path to FPDF

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
        $logoPath = __DIR__ . '/../assets/images/college_logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 10, 50);
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
        $this->Ln(5);
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

// =============================================================================
// DATABASE FUNCTIONS
// =============================================================================

function fetchFacultyReportData(mysqli $conn, int $faculty_id, array $filters): array
{
    $where = [];
    $params = [$faculty_id];
    $types = 'i';

    // Build dynamic WHERE clause based on filters
    // Admin-a irundha mattum thaan department filter work aaganum,
    // Faculty-a irundha avanga sontha dept la irundhu mattum varum
    if ($_SESSION['role'] === 'admin' && !empty($filters['department'])) { 
        $where[] = 'f.department = ?'; 
        $params[] = $filters['department']; 
        $types .= 's'; 
    }
    if (!empty($filters['year'])) { $where[] = 'f.year = ?'; $params[] = $filters['year']; $types .= 's'; }
    if (!empty($filters['semester'])) { $where[] = 'f.semester = ?'; $params[] = $filters['semester']; $types .= 's'; }
    $where_sql = count($where) > 0 ? ' AND ' . implode(' AND ', $where) : '';

    // Summary query: Get stats ONLY for this faculty
    $summary_sql = "SELECT 
                        COUNT(DISTINCT fr.student_id) as students_responded, 
                        AVG(fr.rating) as average_rating 
                    FROM feedback_responses fr 
                    JOIN feedback_forms f ON fr.form_number = f.form_number 
                        AND fr.subject_code = f.subject_code 
                        AND fr.faculty_id = f.faculty_id
                    WHERE fr.faculty_id = ? $where_sql";
    
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Subject-wise details for this faculty
    $subject_sql = "SELECT 
                        f.subject_code, 
                        AVG(fr.rating) as avg_rating, 
                        COUNT(DISTINCT fr.student_id) as response_count
                    FROM feedback_responses fr 
                    JOIN feedback_forms f ON fr.form_number = f.form_number
                        AND fr.subject_code = f.subject_code
                        AND fr.faculty_id = f.faculty_id
                    WHERE fr.faculty_id = ? $where_sql 
                    GROUP BY f.subject_code";
                    
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
        $subjectDetails[] = [
            'subject_code' => $row['subject_code'], 
            'avg_rating' => round($row['avg_rating'], 2), 
            'grade' => $gradeDetails['grade'], 
            'status' => $gradeDetails['status'],
            'response_count' => $row['response_count']
        ];
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

// =============================================================================
// PDF DRAWING FUNCTIONS
// =============================================================================

function drawTitleBar(FPDF $pdf, string $title)
{
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(190, 8, $title, 1, 1, 'C', true); // 190 width
    $pdf->Ln(2);
}

function drawFacultyDetails(FPDF $pdf, string $faculty_name, string $faculty_dept, array $filters)
{
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(245, 245, 245);
    
    $pdf->Cell(40, 7, 'Faculty Name:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(150, 7, $faculty_name, 1, 1, 'L');

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, 'Faculty Dept:', 1, 0, 'L', true);
    $pdf->Cell(150, 7, $faculty_dept, 1, 1, 'L');
    
    // === ITHU THAAN MAATHAM (THIS IS THE CHANGE) ===
    // "Filtered Dept:" line-a remove pannitten
    
    $pdf->Cell(40, 7, 'Filtered Year:', 1, 0, 'L', true);
    $pdf->Cell(150, 7, $filters['year'] ? toRoman($filters['year']) : 'All Years', 1, 1, 'L');
    $pdf->Cell(40, 7, 'Filtered Sem:', 1, 0, 'L', true);
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

    $pdf->Cell($cellWidth, $cellHeight, 'Students Responded: ' . ($summary['students_responded'] ?? '0'), 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Total Subjects: ' . $totalSubjects, 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Average Rating: ' . round($summary['average_rating'] ?? 0, 2) . '/5.0', 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Report Date: ' . date('d-M-Y'), 1, 1, 'L');

    $pdf->Cell($cellWidth, $cellHeight, 'Overall Grade: ' . $gradeDetails['grade'], 1, 0, 'L');
    $pdf->Cell($cellWidth, $cellHeight, 'Percentage: ' . round($percentage, 2) . '%', 1, 0, 'L');
    $pdf->Cell($cellWidth * 2, $cellHeight, 'Status: ' . $gradeDetails['status'], 1, 1, 'L');
    $pdf->Ln(5);
}

function drawGradeDistributionSection(FPDF $pdf, array $gradeCounts, int $totalSubjects)
{
    drawTitleBar($pdf, 'Grade Distribution Analysis (Based on Subjects)');
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


function drawSubjectDetailsSection(FPDF $pdf, array $details)
{
    drawTitleBar($pdf, 'Subject-wise Performance Details');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $widths = [40, 30, 30, 30, 60]; // Total 190

    $pdf->Cell($widths[0], 7, 'Subject Code', 1, 0, 'C', true);
    $pdf->Cell($widths[1], 7, 'Responses', 1, 0, 'C', true);
    $pdf->Cell($widths[2], 7, 'Avg Rating', 1, 0, 'C', true);
    $pdf->Cell($widths[3], 7, 'Grade', 1, 0, 'C', true);
    $pdf->Cell($widths[4], 7, 'Performance Status', 1, 1, 'C', true);

    if (empty($details)) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(array_sum($widths), 10, 'No subject data available for the selected filters.', 1, 1, 'C');
        return;
    }
    $pdf->SetFont('Arial', '', 9);
    foreach ($details as $row) {
        $pdf->Cell($widths[0], 7, $row['subject_code'], 1, 0, 'L');
        $pdf->Cell($widths[1], 7, $row['response_count'], 1, 0, 'C');
        $pdf->Cell($widths[2], 7, $row['avg_rating'], 1, 0, 'C');
        $pdf->Cell($widths[3], 7, $row['grade'], 1, 0, 'C');
        $pdf->Cell($widths[4], 7, $row['status'], 1, 1, 'L');
    }
}

// =============================================================================
// MAIN EXECUTION
// =============================================================================

try {
    // Determine which faculty ID to use
    if ($_SESSION['role'] === 'admin' && isset($_GET['faculty_id'])) {
        // Admin is downloading for a specific faculty
        $faculty_id = intval($_GET['faculty_id']);
    } elseif ($_SESSION['role'] === 'faculty') {
        // Faculty is downloading their own report
        $faculty_id = $_SESSION['user']['id'];
    } else {
        throw new Exception("Faculty ID not specified.");
    }
    
    // Get Faculty Name & Dept
    $fac_stmt = $conn->prepare("SELECT name, department FROM faculty WHERE id = ?");
    $fac_stmt->bind_param("i", $faculty_id);
    $fac_stmt->execute();
    $fac_result = $fac_stmt->get_result()->fetch_assoc();
    $faculty_name = $fac_result['name'] ?? 'Unknown Faculty';
    $faculty_dept = $fac_result['department'] ?? 'N/A';
    $fac_stmt->close();


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
    $pdf->SetTitle('Faculty Feedback Analytics Report - ' . $faculty_name);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Draw content
    drawTitleBar($pdf, 'Academic & Faculty Details');
    drawFacultyDetails($pdf, $faculty_name, $faculty_dept, $filters);
    
    drawTitleBar($pdf, 'Overall Performance Summary');
    drawSummarySection($pdf, $reportData['summary'], $reportData['total_subjects']);

    drawGradeDistributionSection($pdf, $reportData['gradeCounts'], $reportData['total_subjects']);

    drawSubjectDetailsSection($pdf, $reportData['subjectDetails']);
    
    // === PUTHU MAATHAM: HOD and Principal Signature Area ===
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

    // Output PDF
    $pdf_filename = 'Faculty_Report_' . preg_replace('/[^a-z0-9]/i', '_', $faculty_name) . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output('D', $pdf_filename);

} catch (Exception $e) {
    header('Content-Type: text/plain');
    http_response_code(500);
    echo 'Error generating PDF: ' . $e->getMessage();
}