<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

// Get URL parameters
$form_id = $_GET['form_id'] ?? '';
$department = $_GET['department'] ?? '';
$year = $_GET['year'] ?? '';
$semester = $_GET['semester'] ?? '';

// Initialize data
$stats = [];
$grade_distribution = [];
$subject_details = [];

if (!empty($form_id) || (!empty($department) && !empty($year) && !empty($semester))) {
    try {
        // Get overall statistics - accurate data
        if (!empty($form_id)) {
            // Filter by specific form ID
            $stats_query = "SELECT 
                (SELECT COUNT(DISTINCT s.id) FROM students s 
                 JOIN feedback_forms f ON s.department = f.department AND s.year = f.year AND s.semester = f.semester 
                 WHERE f.id = ?) as class_strength,
                1 as forms_submitted,
                COALESCE(AVG(fr.rating), 0) as avg_rating,
                COUNT(fr.id) as total_responses
                FROM feedback_responses fr
                WHERE fr.form_id = ?";
            
            $stmt = $conn->prepare($stats_query);
            $stmt->bind_param("ii", $form_id, $form_id);
        } else {
            // Filter by department, year, semester
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
        }
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Get actual grade distribution based on real ratings
        $grade_distribution = [
            ['grade' => 'A+', 'count' => 0, 'percentage' => 0],
            ['grade' => 'A', 'count' => 0, 'percentage' => 0],
            ['grade' => 'B+', 'count' => 0, 'percentage' => 0],
            ['grade' => 'B', 'count' => 0, 'percentage' => 0],
            ['grade' => 'C', 'count' => 0, 'percentage' => 0],
            ['grade' => 'D', 'count' => 0, 'percentage' => 0]
        ];
        
        // Count ratings by grade
        if (!empty($form_id)) {
            // Filter by specific form ID
            $rating_query = "SELECT 
                fr.rating,
                COUNT(*) as count
                FROM feedback_responses fr
                WHERE fr.form_id = ?
                GROUP BY fr.rating";
            
            $stmt = $conn->prepare($rating_query);
            $stmt->bind_param("i", $form_id);
        } else {
            // Filter by department, year, semester
            $rating_query = "SELECT 
                fr.rating,
                COUNT(*) as count
                FROM feedback_responses fr
                JOIN feedback_forms f ON fr.form_id = f.id
                WHERE f.department = ? AND f.year = ? AND f.semester = ?
                GROUP BY fr.rating";
            
            $stmt = $conn->prepare($rating_query);
            $stmt->bind_param("sss", $department, $year, $semester);
        }
        $stmt->execute();
        $rating_result = $stmt->get_result();
        
        $total_ratings = 0;
        while ($row = $rating_result->fetch_assoc()) {
            $rating = $row['rating'];
            $count = $row['count'];
            $total_ratings += $count;
            
            // Map ratings to grades
            if ($rating == 5) {
                $grade_distribution[0]['count'] += $count; // A+
            } elseif ($rating == 4) {
                $grade_distribution[1]['count'] += $count; // A
            } elseif ($rating == 3) {
                $grade_distribution[2]['count'] += $count; // B+
            } elseif ($rating == 2) {
                $grade_distribution[3]['count'] += $count; // B
            } elseif ($rating == 1) {
                $grade_distribution[4]['count'] += $count; // C
            } else {
                $grade_distribution[5]['count'] += $count; // D
            }
        }
        
        // Calculate percentages
        foreach ($grade_distribution as &$grade) {
            $grade['percentage'] = $total_ratings > 0 ? round(($grade['count'] / $total_ratings) * 100, 1) : 0;
        }
        
        // Get subject-wise details with actual data
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Analytics Report</title>
    
    <!-- Modern UI Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            /* Modern UI Colors */
            --primary-blue: #4285f4;
            --primary-blue-dark: #3367d6;
            --success-green: #34a853;
            --warning-orange: #fbbc04;
            --error-red: #ea4335;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --background: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #dadce0;
            --hover-background: #f1f3f4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: var(--card-background);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .header-text h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .header-text p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-blue-dark);
        }
        
        .btn-secondary {
            background: var(--card-background);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--hover-background);
        }
        
        .card {
            background: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .card-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        th {
            background: var(--primary-blue);
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        th:first-child {
            border-radius: 6px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        tr:hover {
            background: var(--hover-background);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--card-background);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-blue);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-very-good {
            background: #e8f5e8;
            color: var(--success-green);
        }
        
        .status-good {
            background: #e3f2fd;
            color: var(--primary-blue);
        }
        
        .status-average {
            background: #fff8e1;
            color: #f57c00;
        }
        
        .highlight {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .no-data {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            padding: 40px;
        }
        
        @media print {
            .header-actions {
                display: none !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                background: white !important;
            }
            
            .header {
                background: white !important;
                color: black !important;
            }
            
            .card-header {
                background: #f0f0f0 !important;
                color: black !important;
            }
            
            .btn {
                display: none !important;
            }
            
            table {
                border: 1px solid #000 !important;
            }
            
            th {
                background: #f0f0f0 !important;
                color: black !important;
            }
            
            td {
                color: black !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <div class="header-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="header-text">
                    <h1>Feedback Analytics Report</h1>
                    <p>Detailed analysis of student feedback responses</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="view_feedback.php?view=forms&department=<?php echo urlencode($department); ?>&year=<?php echo urlencode($year); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button onclick="generatePDF()" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download PDF
                </button>
            </div>
        </div>
    
    <!-- Summary Statistics -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Summary Statistics</div>
            <div class="card-subtitle">Overview of feedback data for selected criteria</div>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['class_strength'] ?? 0; ?></div>
                    <div class="stat-label">Class Strength</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['forms_submitted'] ?? 0; ?></div>
                    <div class="stat-label">Forms Submitted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_responses'] ?? 0; ?></div>
                    <div class="stat-label">Total Responses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['avg_rating'] ?? 0, 2); ?>/5.0</div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Performance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="highlight"><?php echo htmlspecialchars($department); ?></td>
                            <td class="highlight"><?php echo htmlspecialchars($year); ?></td>
                            <td class="highlight"><?php echo htmlspecialchars($semester); ?></td>
                            <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $performance_status)); ?>"><?php echo $performance_status; ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detailed Feedback Responses -->
    <?php
    // Get detailed feedback responses like download_feedback_pdf.php
    $feedback_details = [];
    if (!empty($form_id)) {
        // Filter by specific form ID
        try {
            $feedback_query = "SELECT f.department, f.year, f.semester, 
                               COALESCE(fac.name, 'Not Assigned') as faculty_name, 
                               'Feedback Question' as question, 
                               s.name as student_name, s.sin_number, fr.rating
                               FROM feedback_responses fr
                               JOIN feedback_forms f ON fr.form_id = f.id
                               JOIN students s ON fr.student_id = s.id
                               LEFT JOIN faculty fac ON fr.faculty_id = fac.id
                               WHERE fr.form_id = ?
                               ORDER BY fac.name, s.name";
            
            $stmt = $conn->prepare($feedback_query);
            $stmt->bind_param("i", $form_id);
            $stmt->execute();
            $feedback_result = $stmt->get_result();
            while ($row = $feedback_result->fetch_assoc()) {
                $feedback_details[] = $row;
            }
        } catch (Exception $e) {
            $feedback_details = [];
        }
    } elseif (!empty($department) && !empty($year) && !empty($semester)) {
        // Filter by department, year, semester
        try {
            $feedback_query = "SELECT f.department, f.year, f.semester, 
                               COALESCE(fac.name, 'Not Assigned') as faculty_name, 
                               'Feedback Question' as question, 
                               s.name as student_name, s.sin_number, fr.rating
                               FROM feedback_responses fr
                               JOIN feedback_forms f ON fr.form_id = f.id
                               JOIN students s ON fr.student_id = s.id
                               LEFT JOIN faculty fac ON fr.faculty_id = fac.id
                               WHERE f.department = ? AND f.year = ? AND f.semester = ?
                               ORDER BY f.department, f.year, f.semester, fac.name, s.name";
            
            $stmt = $conn->prepare($feedback_query);
            $stmt->bind_param("sss", $department, $year, $semester);
            $stmt->execute();
            $feedback_result = $stmt->get_result();
            while ($row = $feedback_result->fetch_assoc()) {
                $feedback_details[] = $row;
            }
        } catch (Exception $e) {
            $feedback_details = [];
        }
    }
    ?>
    
        <div class="card">
            <div class="card-header">
                <div class="card-title">Detailed Feedback Responses</div>
                <div class="card-subtitle">Individual student feedback entries</div>
            </div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Faculty</th>
                            <th>Student</th>
                            <th>SIN Number</th>
                            <th>Question</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($feedback_details)): ?>
                            <?php foreach ($feedback_details as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['year']); ?></td>
                                    <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                    <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['sin_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['question']); ?></td>
                                    <td class="highlight"><?php echo htmlspecialchars($row['rating']); ?>/5</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-info-circle"></i><br>
                                    No feedback records found for the selected criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    
    </div> <!-- End container -->

    <script>
        
        function generatePDF() {
            // Get current parameters
            const department = '<?php echo htmlspecialchars($department); ?>';
            const year = '<?php echo htmlspecialchars($year); ?>';
            const semester = '<?php echo htmlspecialchars($semester); ?>';
            
            // Create URL for PDF generation
            const pdfUrl = `download_pdf_report.php?department=${encodeURIComponent(department)}&year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}`;
            
            // Open PDF in new window for automatic download
            window.open(pdfUrl, '_blank');
        }
    </script>
</body>
</html>
