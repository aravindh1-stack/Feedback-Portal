<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

// Get filter parameters
$department = $_GET['department'] ?? '';
$year = $_GET['year'] ?? '';
$semester = $_GET['semester'] ?? '';

// Initialize data
$stats = [];
$grade_distribution = [];
$subject_details = [];

if (!empty($department) && !empty($year) && !empty($semester)) {
    try {
        // Get overall statistics
        $stats_query = "SELECT 
            (SELECT COUNT(DISTINCT s.id) FROM students s WHERE s.department = ? AND s.year = ? AND s.semester = ?) as class_strength,
            (SELECT COUNT(DISTINCT f.id) FROM feedback_forms f WHERE f.department = ? AND f.year = ? AND f.semester = ?) as forms_submitted,
            COALESCE(AVG(fr.rating), 0) as avg_rating,
            COUNT(fr.id) as total_responses
            FROM feedback_responses fr
            JOIN feedback_forms f ON fr.form_id = f.id
            WHERE f.department = ? AND f.year = ? AND f.semester = ?";
        
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("sssssssss", $department, $year, $semester, $department, $year, $semester, $department, $year, $semester);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Get grade distribution
        $rating_query = "SELECT 
            fr.rating,
            COUNT(*) as count
            FROM feedback_responses fr
            JOIN feedback_forms f ON fr.form_id = f.id
            WHERE f.department = ? AND f.year = ? AND f.semester = ?
            GROUP BY fr.rating";
        
        $stmt = $conn->prepare($rating_query);
        $stmt->bind_param("sss", $department, $year, $semester);
        $stmt->execute();
        $rating_result = $stmt->get_result();
        
        $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total_ratings = 0;
        
        while ($row = $rating_result->fetch_assoc()) {
            $rating_counts[$row['rating']] = $row['count'];
            $total_ratings += $row['count'];
        }
        
        // Calculate grade distribution
        $grade_distribution = [
            ['grade' => 'A+', 'count' => $rating_counts[5], 'percentage' => $total_ratings > 0 ? round(($rating_counts[5] / $total_ratings) * 100, 1) : 0, 'performance' => 'Outstanding', 'description' => '91-100% - Exceptional teaching performance'],
            ['grade' => 'A', 'count' => $rating_counts[4], 'percentage' => $total_ratings > 0 ? round(($rating_counts[4] / $total_ratings) * 100, 1) : 0, 'performance' => 'Excellent', 'description' => '81-90% - Very good teaching effectiveness'],
            ['grade' => 'B+', 'count' => $rating_counts[3], 'percentage' => $total_ratings > 0 ? round(($rating_counts[3] / $total_ratings) * 100, 1) : 0, 'performance' => 'Very Good', 'description' => '71-80% - Good teaching performance'],
            ['grade' => 'B', 'count' => $rating_counts[2], 'percentage' => $total_ratings > 0 ? round(($rating_counts[2] / $total_ratings) * 100, 1) : 0, 'performance' => 'Good', 'description' => '61-70% - Satisfactory teaching methods'],
            ['grade' => 'C', 'count' => $rating_counts[1], 'percentage' => $total_ratings > 0 ? round(($rating_counts[1] / $total_ratings) * 100, 1) : 0, 'performance' => 'Average', 'description' => '51-60% - Needs some improvement'],
            ['grade' => 'D', 'count' => 0, 'percentage' => 0, 'performance' => 'Below Average', 'description' => 'Below 51% - Requires significant improvement']
        ];
        
        // Get subject-wise details
        $subject_query = "SELECT 
            f.department,
            f.year,
            f.semester,
            f.subject_code,
            COALESCE(fac.name, 'Not Assigned') as faculty_name,
            COALESCE(AVG(fr.rating), 0) as avg_rating,
            COUNT(fr.id) as response_count,
            CASE 
                WHEN COALESCE(AVG(fr.rating), 0) >= 4.5 THEN 'A+'
                WHEN COALESCE(AVG(fr.rating), 0) >= 4.0 THEN 'A'
                WHEN COALESCE(AVG(fr.rating), 0) >= 3.5 THEN 'B+'
                WHEN COALESCE(AVG(fr.rating), 0) >= 3.0 THEN 'B'
                WHEN COALESCE(AVG(fr.rating), 0) >= 2.5 THEN 'C'
                ELSE 'D'
            END as grade,
            CASE 
                WHEN COALESCE(AVG(fr.rating), 0) >= 4.0 THEN 'Very Good'
                WHEN COALESCE(AVG(fr.rating), 0) >= 3.5 THEN 'Good'
                WHEN COALESCE(AVG(fr.rating), 0) >= 3.0 THEN 'Satisfactory'
                WHEN COALESCE(AVG(fr.rating), 0) >= 2.5 THEN 'Needs Improvement'
                ELSE 'Poor'
            END as performance_status
            FROM feedback_forms f
            LEFT JOIN feedback_responses fr ON f.id = fr.form_id
            LEFT JOIN faculty fac ON fr.faculty_id = fac.id
            WHERE f.department = ? AND f.year = ? AND f.semester = ?
            GROUP BY f.id, f.subject_code, fac.name
            ORDER BY f.subject_code";
        
        $stmt = $conn->prepare($subject_query);
        $stmt->bind_param("sss", $department, $year, $semester);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        while ($row = $subject_result->fetch_assoc()) {
            $subject_details[] = $row;
        }
        
    } catch (Exception $e) {
        $stats = ['class_strength' => 0, 'forms_submitted' => 0, 'avg_rating' => 0, 'total_responses' => 0];
        $grade_distribution = [];
        $subject_details = [];
    }
}

// Calculate additional metrics
$percentage = $stats['class_strength'] > 0 ? round(($stats['total_responses'] / $stats['class_strength']) * 100, 1) : 0;
$overall_grade = $stats['avg_rating'] >= 4.0 ? 'B+' : ($stats['avg_rating'] >= 3.5 ? 'B' : 'C');
$performance_status = $stats['avg_rating'] >= 4.0 ? 'Very Good' : ($stats['avg_rating'] >= 3.5 ? 'Good' : 'Average');

// Calculate statistical analysis
$highest_rating = !empty($subject_details) ? max(array_column($subject_details, 'avg_rating')) : $stats['avg_rating'];
$lowest_rating = !empty($subject_details) ? min(array_column($subject_details, 'avg_rating')) : $stats['avg_rating'];
$range = $highest_rating - $lowest_rating;
$subjects_evaluated = count($subject_details);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Feedback Analytics Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.4;
        }
        
        .container {
            max-width: 210mm;
            margin: 20px auto;
            background: white;
            padding: 20mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            min-height: 297mm;
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="%23f39c12"/><text x="50" y="60" text-anchor="middle" font-size="40" fill="white">🎓</text></svg>') no-repeat center;
            background-size: contain;
        }
        
        .college-info {
            flex: 1;
            text-align: center;
        }
        
        .college-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .autonomous {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .download-section {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .download-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #545b62;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin: 25px 0 15px 0;
            color: #333;
        }
        
        .filters-section {
            margin-bottom: 25px;
        }
        
        .filters-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        th, td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .filters-table td {
            text-align: center;
            font-weight: bold;
            padding: 10px;
        }
        
        .performance-summary {
            margin-bottom: 25px;
        }
        
        .performance-summary td {
            font-weight: bold;
            padding: 10px;
        }
        
        .grade-a-plus { color: #28a745; }
        .grade-a { color: #28a745; }
        .grade-b-plus { color: #007bff; }
        .grade-b { color: #007bff; }
        .grade-c { color: #ffc107; }
        .grade-d { color: #dc3545; }
        
        @media print {
            body { background: white; }
            .container { 
                box-shadow: none; 
                margin: 0;
                padding: 15mm;
            }
            .download-section { display: none; }
        }
        
        @media screen {
            .no-print { display: block; }
        }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="download-section no-print">
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
        <button onclick="printReport()" class="download-btn">
            <i class="fas fa-download"></i>
            Download PDF
        </button>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo"></div>
            <div class="college-info">
                <div class="college-name">Sri Shanmugha College of Engineering and Technology</div>
                <div class="autonomous">(Autonomous)</div>
                <div class="report-title">Staff Feedback Analytics Report</div>
            </div>
        </div>

        <?php if (!empty($department) && !empty($year) && !empty($semester)): ?>
        
        <div class="filters-section">
            <div class="filters-title">Applied Filters</div>
            <table class="filters-table">
                <tr>
                    <td style="width: 33.33%;">Department: <?php echo htmlspecialchars($department); ?></td>
                    <td style="width: 33.33%;">Year: <?php echo htmlspecialchars($year); ?></td>
                    <td style="width: 33.33%;">Semester: <?php echo htmlspecialchars($semester); ?></td>
                </tr>
            </table>
        </div>

        <div class="section-title">Overall Performance Summary</div>
        <table class="performance-summary">
            <tr>
                <td style="width: 25%;">Class Strength: <?php echo $stats['class_strength'] ?? 0; ?></td>
                <td style="width: 25%;">Forms Submitted: <?php echo $stats['forms_submitted'] ?? 0; ?></td>
                <td style="width: 25%;">Department: <?php echo htmlspecialchars($department); ?></td>
                <td style="width: 25%;">Report Date: <?php echo date('d-M-Y'); ?></td>
            </tr>
            <tr>
                <td>Average Rating: <?php echo number_format($stats['avg_rating'] ?? 0, 2); ?>/5.0</td>
                <td>Overall Grade: <?php echo $overall_grade; ?></td>
                <td>Year: <?php echo htmlspecialchars($year); ?></td>
                <td>Semester: <?php echo htmlspecialchars($semester); ?></td>
            </tr>
            <tr>
                <td>Percentage: <?php echo $percentage; ?>%</td>
                <td colspan="2">Performance Status: <?php echo $performance_status; ?></td>
                <td></td>
            </tr>
        </table>

        <div class="section-title">Grade Distribution Analysis</div>
        <table>
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Count</th>
                    <th>Percentage</th>
                    <th>Performance</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade_distribution as $grade): ?>
                <tr>
                    <td><?php echo $grade['grade']; ?></td>
                    <td><?php echo $grade['count']; ?></td>
                    <td><?php echo $grade['percentage']; ?>%</td>
                    <td class="grade-<?php echo strtolower(str_replace('+', '-plus', $grade['grade'])); ?>"><?php echo $grade['performance']; ?></td>
                    <td><?php echo $grade['description']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-title">Statistical Analysis</div>
        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Additional Info</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Highest Average Rating</td>
                    <td><?php echo number_format($highest_rating, 2); ?></td>
                    <td>Lowest Average Rating</td>
                    <td><?php echo number_format($lowest_rating, 2); ?></td>
                    <td>Range: <?php echo number_format($range, 2); ?></td>
                </tr>
                <tr>
                    <td>Standard Deviation</td>
                    <td>0.00</td>
                    <td>Total Subjects Evaluated</td>
                    <td><?php echo $subjects_evaluated; ?></td>
                    <td>Variance: 0.00</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Subject-wise Performance Details</div>
        <table>
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Year</th>
                    <th>Sem</th>
                    <th>Subject Code</th>
                    <th>Faculty Name</th>
                    <th>Avg Rating</th>
                    <th>Grade</th>
                    <th>Performance Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($subject_details)): ?>
                    <?php foreach ($subject_details as $subject): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($subject['department']); ?></td>
                        <td><?php echo htmlspecialchars($subject['year']); ?></td>
                        <td><?php echo htmlspecialchars($subject['semester']); ?></td>
                        <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                        <td><?php echo htmlspecialchars($subject['faculty_name']); ?></td>
                        <td><?php echo number_format($subject['avg_rating'], 2); ?></td>
                        <td class="grade-<?php echo strtolower(str_replace('+', '-plus', $subject['grade'])); ?>"><?php echo $subject['grade']; ?></td>
                        <td><?php echo $subject['performance_status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No subject data available for the selected criteria</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php else: ?>
        
        <div style="text-align: center; padding: 50px;">
            <h3>Please select filters to generate the report</h3>
            <p>Use the following URL format:</p>
            <code>professional_report.php?department=ECE&year=2&semester=3</code>
        </div>
        
        <?php endif; ?>
    </div>

    <script>
        function printReport() {
            // Get current parameters
            const department = '<?php echo htmlspecialchars($department); ?>';
            const year = '<?php echo htmlspecialchars($year); ?>';
            const semester = '<?php echo htmlspecialchars($semester); ?>';
            
            // Create URL for PDF generation
            const pdfUrl = `download_pdf_report.php?department=${encodeURIComponent(department)}&year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}`;
            
            // Open PDF in new window for automatic download
            window.open(pdfUrl, '_blank');
        }
        
        // Optional: Auto-focus on print dialog when page loads with print parameter
        if (window.location.search.includes('print=true')) {
            setTimeout(function() {
                printReport();
            }, 500);
        }
    </script>
</body>
</html>
