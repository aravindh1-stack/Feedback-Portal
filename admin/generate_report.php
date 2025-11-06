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
$format = $_GET['format'] ?? 'html';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($department)) {
    $where_conditions[] = "f.department = ?";
    $params[] = $department;
}
if (!empty($year)) {
    $where_conditions[] = "f.year = ?";
    $params[] = $year;
}
if (!empty($semester)) {
    $where_conditions[] = "f.semester = ?";
    $params[] = $semester;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get comprehensive report data
try {
    // Subject-wise analysis
    $subject_query = "SELECT 
        f.subject_code,
        f.subject_code as subject_name,
        f.department,
        f.year,
        f.semester,
        COUNT(fr.id) as total_responses,
        AVG(fr.rating) as avg_rating,
        COUNT(DISTINCT fr.student_id) as participating_students
        FROM feedback_forms f
        LEFT JOIN feedback_responses fr ON f.id = fr.form_id
        $where_clause
        GROUP BY f.id, f.subject_code, f.department, f.year, f.semester
        ORDER BY f.department, f.subject_code";
    
    $stmt = $conn->prepare($subject_query);
    if (!empty($params)) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $subject_result = $stmt->get_result();
    $subjects = [];
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = $row;
    }

    // Get total participating students
    $total_students_query = "SELECT COUNT(DISTINCT fr.student_id) as total_students
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        $where_clause";
    
    $stmt2 = $conn->prepare($total_students_query);
    if (!empty($params)) {
        $stmt2->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt2->execute();
    $total_students_result = $stmt2->get_result();
    $total_students = $total_students_result->fetch_assoc()['total_students'] ?? 0;

    // Get department info for header
    $dept_info = [
        'department' => $department ?: 'All Departments',
        'year' => $year ?: 'All Years',
        'semester' => $semester ?: 'All Semesters'
    ];

} catch (Exception $e) {
    $subjects = [];
    $total_students = 0;
    $dept_info = ['department' => 'Error', 'year' => '', 'semester' => ''];
}

// If PDF format requested, generate PDF
if ($format === 'pdf') {
    // For now, we'll create an HTML version that can be printed as PDF
    // You can integrate with libraries like DOMPDF or TCPDF later
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback Analysis Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }
        
        .college-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .logo-placeholder {
            width: 80px;
            height: 80px;
            background: #2563eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .college-info h1 {
            color: #1e40af;
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        
        .college-info h2 {
            color: #dc2626;
            margin: 5px 0;
            font-size: 18px;
        }
        
        .college-info h3 {
            color: #2563eb;
            margin: 5px 0;
            font-size: 16px;
        }
        
        .report-title {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-left: 4px solid #2563eb;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        
        .chart-section {
            margin: 40px 0;
        }
        
        .chart-container {
            height: 400px;
            margin: 20px 0;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        
        .subjects-table th {
            background: #2563eb;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        
        .subjects-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .subjects-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }
        
        .rating-excellent { background: #16a34a; }
        .rating-good { background: #2563eb; }
        .rating-average { background: #d97706; }
        .rating-poor { background: #dc2626; }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .print-btn {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 0;
        }
        
        .print-btn:hover {
            background: #1d4ed8;
        }
        
        @media print {
            body { background: white; }
            .print-btn { display: none; }
            .report-container { box-shadow: none; }
        }
        
        .parameters-section {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .parameters-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header -->
        <div class="header">
            <div class="college-logo">
                <div class="logo-placeholder">SRI</div>
                <div class="college-info">
                    <h1>SRI SHANMUGHA</h1>
                    <h2>COLLEGE OF ENGINEERING AND TECHNOLOGY</h2>
                    <h3>Department of <?php echo htmlspecialchars($dept_info['department']); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="report-title">
            STUDENTS FEEDBACK ANALYSIS - I
        </div>
        
        <!-- Report Info -->
        <div class="report-info">
            <div class="info-box">
                <div class="info-label">Year/Semester:</div>
                <div><?php echo htmlspecialchars($dept_info['year'] . ' / ' . $dept_info['semester']); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Total Number of Students Participated:</div>
                <div><strong><?php echo $total_students; ?></strong></div>
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($subjects); ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Students Participated</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_column($subjects, 'total_responses')); ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $avg_ratings = array_filter(array_column($subjects, 'avg_rating'));
                    echo !empty($avg_ratings) ? number_format(array_sum($avg_ratings) / count($avg_ratings), 1) : '0';
                    ?>
                </div>
                <div class="stat-label">Overall Rating</div>
            </div>
        </div>
        
        <!-- Chart Section -->
        <div class="chart-section">
            <h3 style="text-align: center; color: #1e40af; margin-bottom: 20px;">STUDENT FEEDBACK ANALYSIS</h3>
            <div class="chart-container">
                <canvas id="feedbackChart"></canvas>
            </div>
        </div>
        
        <!-- Parameters Section -->
        <div class="parameters-section">
            <div class="parameters-title">Parameters</div>
            <div style="text-align: center; font-weight: bold;">Subject-wise Analysis</div>
        </div>
        
        <!-- Subjects Table -->
        <table class="subjects-table">
            <thead>
                <tr>
                    <th>Sl.No.</th>
                    <th>Subject Code/Name</th>
                    <th>Responses</th>
                    <th>Students</th>
                    <th>Average Rating</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $index => $subject): ?>
                    <?php 
                    $rating = $subject['avg_rating'] ? round($subject['avg_rating'], 2) : 0;
                    $performance_class = '';
                    $performance_text = '';
                    
                    if ($rating >= 4.5) {
                        $performance_class = 'rating-excellent';
                        $performance_text = 'Excellent';
                    } elseif ($rating >= 3.5) {
                        $performance_class = 'rating-good';
                        $performance_text = 'Good';
                    } elseif ($rating >= 2.5) {
                        $performance_class = 'rating-average';
                        $performance_text = 'Average';
                    } else {
                        $performance_class = 'rating-poor';
                        $performance_text = 'Needs Improvement';
                    }
                    ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong><br>
                            <small><?php echo htmlspecialchars($subject['subject_name']); ?></small>
                        </td>
                        <td><?php echo $subject['total_responses']; ?></td>
                        <td><?php echo $subject['participating_students']; ?></td>
                        <td><strong><?php echo $rating; ?>/5.0</strong></td>
                        <td>
                            <span class="rating-badge <?php echo $performance_class; ?>">
                                <?php echo $performance_text; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Signature Section -->
        <div style="display: flex; justify-content: space-between; margin-top: 60px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <div style="text-align: center;">
                <div style="border-top: 1px solid #000; width: 200px; margin-top: 40px; padding-top: 5px;">
                    <strong>HoD</strong>
                </div>
            </div>
            <div style="text-align: center;">
                <div style="border-top: 1px solid #000; width: 200px; margin-top: 40px; padding-top: 5px;">
                    <strong>Principal</strong>
                </div>
            </div>
        </div>
        
        <!-- Print Button -->
        <div style="text-align: center; margin-top: 30px;">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="analytics.php" class="print-btn" style="text-decoration: none; margin-left: 10px;">
                Back to Analytics
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prepare chart data
        const subjects = <?php echo json_encode($subjects); ?>;
        const labels = subjects.map(s => s.subject_code);
        const ratings = subjects.map(s => parseFloat(s.avg_rating) || 0);
        
        // Create chart
        const ctx = document.getElementById('feedbackChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Student Rating',
                    data: ratings,
                    backgroundColor: [
                        '#3b82f6', '#60a5fa', '#93c5fd', '#dbeafe',
                        '#16a34a', '#4ade80', '#86efac', '#bbf7d0',
                        '#d97706', '#f59e0b', '#fbbf24', '#fde047'
                    ],
                    borderColor: '#1e40af',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Subject-wise Feedback Ratings',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: true,
                            text: 'Student Rating'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Parameters'
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>
