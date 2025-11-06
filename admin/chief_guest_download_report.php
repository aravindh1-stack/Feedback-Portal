 <?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

// Prefer Dompdf if available, else fall back to FPDF
$use_fpdf = false;
$dompdfAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($dompdfAutoload)) {
    require_once $dompdfAutoload;
}
if (!class_exists('Dompdf\\Dompdf')) {
    // Fallback to FPDF
    require_once __DIR__ . '/../fpdf186/fpdf.php';
    $use_fpdf = true;
}

use Dompdf\Dompdf;
use Dompdf\Options;

// Enhanced FPDF class for Chief Guest report
if ($use_fpdf && class_exists('FPDF')) {
    class EnhancedCGPDF extends FPDF {
        private $filter_info = '';
        function SetFilterInfo($info) { $this->filter_info = $info; }
        function Header() {
            $usable = $this->GetPageWidth() - $this->lMargin - $this->rMargin; // e.g., 210 - 10 - 10 = 190mm in portrait
            $logo = __DIR__ . '/../assets/images/college_logo.png';
            $logoW = 35; $logoH = 12; $gap = 5;
            if (file_exists($logo)) { $this->Image($logo, $this->lMargin, 8, $logoW, $logoH); }
            $this->SetFont('Arial','B',12);
            $this->SetXY($this->lMargin + $logoW + $gap, 8);
            $this->Cell($usable - ($logoW + $gap), 6, 'Sri Shanmugha College of Engineering and Technology', 0, 1, 'R');
            $this->SetFont('Arial','',10);
            $this->SetXY($this->lMargin + $logoW + $gap, 14);
            $this->Cell($usable - ($logoW + $gap), 5, '(Autonomous)', 0, 1, 'R');
            $this->Ln(6);
            $this->SetFont('Arial','B',14);
            $this->Cell(0, 8, 'Chief Guest Feedback Report', 0, 1, 'C');
            $this->Ln(2);
            if ($this->filter_info) {
                $this->SetFont('Arial','B',9);
                $this->SetFillColor(230,240,255);
                $this->Cell(0,6,'Applied Filters',0,1,'C');
                $this->Ln(1);
                $parts = explode(' | ', $this->filter_info);
                $col1 = isset($parts[0]) ? $parts[0] : '';
                $col2 = isset($parts[1]) ? $parts[1] : '';
                $col3 = isset($parts[2]) ? $parts[2] : '';
                $col4 = isset($parts[3]) ? $parts[3] : '';
                $this->SetFont('Arial','B',8);
                // 4 equal columns across usable width
                $w1 = floor(($usable) / 4); // integer mm
                $w2 = $w1; $w3 = $w1; $w4 = $usable - ($w1 * 3);
                $this->Cell($w1,6,$col1,1,0,'C',true);
                $this->Cell($w2,6,$col2,1,0,'C',true);
                $this->Cell($w3,6,$col3,1,0,'C',true);
                $this->Cell($w4,6,$col4,1,1,'C',true);
                $this->Ln(3);
            }
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10,'Generated on: '.date('d-M-Y H:i').' | Page '.$this->PageNo(),0,0,'C');
        }
        function OverallSummaryBox($summary, $extra = []) {
            $usable = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
            $this->SetFont('Arial','B',11);
            $this->Cell(0,8,'Overall Summary',0,1,'L');
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(240,248,255);
            // 4 equal cells across usable width
            $w = floor($usable / 4);
            $w4 = $usable - ($w * 3);
            $this->Cell($w,8,'Total Events: '.($summary['total_events']??0),1,0,'L',true);
            $this->Cell($w,8,'Total Students: '.($summary['total_students']??0),1,0,'L',true);
            $this->Cell($w,8,'Total Responses: '.($summary['total_responses']??0),1,0,'L',true);
            $this->Cell($w4,8,'Average Rating: '.number_format((float)($summary['average_rating']??0),2).'/5.0',1,1,'L',true);
            $this->Ln(4);
        }
        function GradeDistributionTable($gradeStats) {
            $usable = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
            $this->SetFont('Arial','B',11);
            $this->Cell(0,8,'Grade Distribution',0,1,'L');
            $this->SetFont('Arial','B',9);
            $this->SetFillColor(240,248,255);
            // Columns that fit exactly: 25 + 35 + 35 + (usable - 95)
            $wGrade = 25; $wCount = 35; $wPct = 35; $wDesc = $usable - ($wGrade + $wCount + $wPct);
            $this->Cell($wGrade,8,'Grade',1,0,'C',true);
            $this->Cell($wCount,8,'Count',1,0,'C',true);
            $this->Cell($wPct,8,'Percent',1,0,'C',true);
            $this->Cell($wDesc,8,'Description',1,1,'C',true);
            $defs = [
                'A+'=> '91-100% Outstanding',
                'A' => '81-90% Excellent',
                'B+'=> '71-80% Very Good',
                'B' => '61-70% Good',
                'C' => '51-60% Average',
                'D' => 'Below 51% Below Average',
            ];
            $this->SetFont('Arial','',8);
            $total = array_sum($gradeStats);
            foreach ($defs as $g=>$desc) {
                $cnt = $gradeStats[$g] ?? 0;
                $pct = $total>0 ? ($cnt/$total)*100 : 0;
                $this->Cell($wGrade,7,$g,1,0,'C',true);
                $this->Cell($wCount,7,(string)$cnt,1,0,'C',true);
                $this->Cell($wPct,7,number_format($pct,1).'%',1,0,'C',true);
                $this->Cell($wDesc,7,$desc,1,1,'L',true);
            }
            $this->Ln(4);
        }
        function DetailedResponsesTable($rows) {
            $usable = $this->GetPageWidth() - $this->lMargin - $this->rMargin; // 190mm on A4 portrait with 10mm margins
            $this->SetFont('Arial','B',11);
            $this->Cell(0,8,'Detailed Feedback Responses',0,1,'L');
            $this->SetFont('Arial','B',8);
            $this->SetFillColor(220,220,220);
            // Removed Date, Student ID, Year/Sem columns
            $headers = ['Event Name','Chief Guest','Type','Question','Rating','Dep','Subm'];
            // Column widths sum to usable width (190): 40+32+20+60+12+16+10 = 190
            $widths  = [40, 32, 20, 60, 12, 16, 10];
            foreach ($headers as $i=>$h) { $this->Cell($widths[$i],8,$h,1,0,'C',true); }
            $this->Ln();
            $this->SetFont('Arial','',8);
            if (empty($rows)) {
                $this->Cell(array_sum($widths), 12, 'No feedback records found matching the selected criteria.', 1, 1, 'C');
                return;
            }
            foreach ($rows as $row) {
                $submitted = !empty($row['submitted_at']) ? date('M d', strtotime($row['submitted_at'])) : 'N/A';
                $question  = $row['question'] ?? 'N/A';
                if (strlen($question) > 100) { $question = substr($question,0,97).'...'; }
                $cells = [
                    $row['event_name'] ?? '',
                    $row['chief_guest_name'] ?? 'N/A',
                    $row['event_type'] ?? 'N/A',
                    $question,
                    (string)($row['rating'] ?? ''),
                    $row['department'] ?? 'N/A',
                    $submitted,
                ];
                foreach ($cells as $i=>$val) {
                    $align = ($i===4)?'C':(($i===6)?'C':'L');
                    $this->Cell($widths[$i], 8, $val, 1, 0, $align);
                }
                $this->Ln();
            }
        }
    }
}

// Collect filters
$event_name = $_GET['event_name'] ?? '';
$chief_guest_name = $_GET['chief_guest_name'] ?? '';
$event_type = $_GET['event_type'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Build query for chief guest feedback responses
$sql = "SELECT 
    r.event_name,
    r.event_date,
    r.chief_guest_name,
    r.student_id,
    r.question,
    r.rating,
    r.department,
    r.academic_year,
    r.semester,
    r.submitted_at,
    r.event_type
FROM chief_guest_feedback_responses r";

$where = [];
if (!empty($event_name)) {
    $where[] = "r.event_name='" . $conn->real_escape_string($event_name) . "'";
}
if (!empty($chief_guest_name)) {
    $where[] = "r.chief_guest_name='" . $conn->real_escape_string($chief_guest_name) . "'";
}
if (!empty($event_type)) {
    $where[] = "r.event_type='" . $conn->real_escape_string($event_type) . "'";
}
if (!empty($from_date)) {
    $where[] = "r.event_date >= '" . $conn->real_escape_string($from_date) . "'";
}
if (!empty($to_date)) {
    $where[] = "r.event_date <= '" . $conn->real_escape_string($to_date) . "'";
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.event_date DESC, r.event_name, r.student_id';

$result = $conn->query($sql);
// Collect rows once for both HTML and FPDF rendering
$rows = [];
if ($result) {
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }
}

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT r.event_name) as total_events,
    COUNT(DISTINCT r.student_id) as total_students,
    COUNT(*) as total_responses,
    AVG(r.rating) as average_rating
FROM chief_guest_feedback_responses r";
if ($where) {
    $summary_sql .= ' WHERE ' . implode(' AND ', $where);
}

$summary_result = $conn->query($summary_sql);
$summary = [
    'total_events' => 0,
    'total_students' => 0,
    'total_responses' => 0,
    'average_rating' => 0,
];
if ($summary_result) {
    $row = $summary_result->fetch_assoc();
    if (is_array($row)) {
        $summary = $row;
    }
}

// Build HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Chief Guest Feedback Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .summary-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .summary-title {
            color: #1e293b;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 5px;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .stat-item {
            flex: 1;
            padding: 10px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        
        th, td {
            border: 1px solid #e2e8f0;
            padding: 6px 4px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background: #2563eb;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 11px;
        }
        
        tr:nth-child(even) {
            background: #f8fafc;
        }
        
        .rating-cell {
            text-align: center;
            font-weight: bold;
        }
        
        .rating-excellent { color: #059669; }
        .rating-good { color: #2563eb; }
        .rating-average { color: #d97706; }
        .rating-poor { color: #dc2626; }
        
        .no-data {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 40px;
        }
        
        .question-cell {
            max-width: 150px;
            word-wrap: break-word;
            font-size: 9px;
        }
        
        .footer {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Chief Guest Feedback Report</h1>
        <div class="subtitle">Generated on ' . date('F d, Y \a\t H:i:s') . '</div>';

// Add filter information
$filters = [];
if (!empty($event_name)) $filters[] = 'Event: ' . htmlspecialchars($event_name);
if (!empty($chief_guest_name)) $filters[] = 'Chief Guest: ' . htmlspecialchars($chief_guest_name);
if (!empty($event_type)) $filters[] = 'Type: ' . htmlspecialchars($event_type);
if (!empty($from_date) && !empty($to_date)) {
    $filters[] = 'Date Range: ' . htmlspecialchars($from_date) . ' to ' . htmlspecialchars($to_date);
}

if ($filters) {
    $html .= '<div class="subtitle">Filters Applied: ' . implode(' | ', $filters) . '</div>';
}

$html .= '
    </div>
    
    <div class="summary-section">
        <div class="summary-title">Report Summary</div>
        <div class="summary-stats">
            <div class="stat-item">
                <span class="stat-value">' . ($summary['total_events'] ?? 0) . '</span>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">' . ($summary['total_students'] ?? 0) . '</span>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">' . ($summary['total_responses'] ?? 0) . '</span>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-item">
                <span class="stat-value">' . number_format($summary['average_rating'] ?? 0, 1) . '/5.0</span>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
    </div>';

// Add detailed responses table
$html .= '
    <div class="summary-section">
        <div class="summary-title">Detailed Feedback Responses</div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:15%">Event Name</th>
                <th style="width:8%">Date</th>
                <th style="width:12%">Chief Guest</th>
                <th style="width:8%">Type</th>
                <th style="width:8%">Student ID</th>
                <th style="width:20%">Question</th>
                <th style="width:6%">Rating</th>
                <th style="width:10%">Department</th>
                <th style="width:8%">Year/Sem</th>
                <th style="width:8%">Submitted</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($rows)) {
    foreach ($rows as $row) {
        $rating = floatval($row['rating']);
        $rating_class = 'rating-poor';
        
        if($rating >= 4.5) {
            $rating_class = 'rating-excellent';
        } elseif($rating >= 3.5) {
            $rating_class = 'rating-good';
        } elseif($rating >= 2.5) {
            $rating_class = 'rating-average';
        }
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['event_name']) . '</td>';
        $html .= '<td>' . ($row['event_date'] ? date('M d, Y', strtotime($row['event_date'])) : 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['chief_guest_name'] ?? 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['event_type'] ?? 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['student_id']) . '</td>';
        $html .= '<td class="question-cell">' . htmlspecialchars(substr($row['question'] ?? 'N/A', 0, 60)) . '...</td>';
        $html .= '<td class="rating-cell ' . $rating_class . '">' . htmlspecialchars($row['rating']) . '/5</td>';
        $html .= '<td>' . htmlspecialchars($row['department'] ?? 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars(($row['academic_year'] ?? '') . '/' . ($row['semester'] ?? '')) . '</td>';
        $html .= '<td>' . ($row['submitted_at'] ? date('M d', strtotime($row['submitted_at'])) : 'N/A') . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="10" class="no-data">No feedback records found matching the selected criteria.</td></tr>';
}

$html .= '</tbody></table>';

$html .= '
    <div class="footer">
        <div>College Feedback Management System - Chief Guest Feedback Report</div>
        <div>This report contains confidential information. Please handle with appropriate care.</div>
    </div>
</body>
</html>';

// Generate filename
$filename = 'chief_guest_feedback_report';
if (!empty($event_name)) {
    $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $event_name);
}
$filename .= '_' . date('Y-m-d_H-i-s') . '.pdf';

if ($use_fpdf) {
    // Build filter info string using actual unique values from results when params are empty
    $filter_info_parts = [];
    // Event
    if (!empty($event_name)) {
        $display_event = $event_name;
    } else {
        $events = array_values(array_unique(array_filter(array_map(function($r){ return $r['event_name'] ?? ''; }, $rows))));
        $display_event = (count($events) === 1) ? $events[0] : 'All';
    }
    // Chief Guest
    if (!empty($chief_guest_name)) {
        $display_cg = $chief_guest_name;
    } else {
        $cgs = array_values(array_unique(array_filter(array_map(function($r){ return $r['chief_guest_name'] ?? ''; }, $rows))));
        $display_cg = (count($cgs) === 1) ? $cgs[0] : 'All';
    }
    // Event Type
    if (!empty($event_type)) {
        $display_type = $event_type;
    } else {
        $types = array_values(array_unique(array_filter(array_map(function($r){ return $r['event_type'] ?? ''; }, $rows))));
        $display_type = (count($types) === 1) ? $types[0] : 'All';
    }
    // Dates (use provided range if any, else compute from data)
    if (!empty($from_date) && !empty($to_date)) {
        $display_dates = $from_date . ' to ' . $to_date;
    } else {
        $dates = array_values(array_filter(array_map(function($r){ return $r['event_date'] ?? ''; }, $rows)));
        if (!empty($dates)) {
            $min = min(array_map('strtotime', $dates));
            $max = max(array_map('strtotime', $dates));
            if ($min === $max) {
                $display_dates = date('Y-m-d', $min);
            } else {
                $display_dates = date('Y-m-d', $min) . ' to ' . date('Y-m-d', $max);
            }
        } else {
            $display_dates = 'All';
        }
    }

    $filter_info_parts[] = 'Event: ' . $display_event;
    $filter_info_parts[] = 'Chief Guest: ' . $display_cg;
    $filter_info_parts[] = 'Type: ' . $display_type;
    $filter_info_parts[] = 'Dates: ' . $display_dates;
    $filter_info = implode(' | ', $filter_info_parts);

    // Compute grade distribution from rows
    $gradeStats = ['A+'=>0,'A'=>0,'B+'=>0,'B'=>0,'C'=>0,'D'=>0];
    foreach ($rows as $row) {
        $pct = min(100, max(0, ((float)$row['rating'] / 5) * 100));
        if ($pct >= 91) $g='A+';
        elseif ($pct >= 81) $g='A';
        elseif ($pct >= 71) $g='B+';
        elseif ($pct >= 61) $g='B';
        elseif ($pct >= 51) $g='C';
        else $g='D';
        $gradeStats[$g]++;
    }

    $pdf = new EnhancedCGPDF('P','mm','A4');
    $pdf->SetMargins(10,10,10);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->SetFilterInfo($filter_info);
    $pdf->AddPage();
    $pdf->OverallSummaryBox($summary);
    $pdf->GradeDistributionTable($gradeStats);
    $pdf->DetailedResponsesTable($rows);
    $pdf->Output('D', $filename);
    exit;
} else {
    // PDF Generation via Dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename, ["Attachment" => true]);
    exit;
}