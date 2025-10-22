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
$subject_code = $_GET['subject_code'] ?? '';

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
if (!empty($subject_code)) {
    $where_conditions[] = "f.subject_code = ?";
    $params[] = $subject_code;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get total students in the class based on filters
    $total_students_query = "SELECT COUNT(DISTINCT s.id) as total_students 
        FROM students s 
        JOIN feedback_forms f ON 1=1";
    
    $student_where_conditions = [];
    $student_params = [];
    
    if (!empty($department)) {
        $student_where_conditions[] = "s.department = ?";
        $student_params[] = $department;
        $student_where_conditions[] = "f.department = ?";
        $student_params[] = $department;
    }
    if (!empty($year)) {
        $student_where_conditions[] = "f.year = ?";
        $student_params[] = $year;
    }
    if (!empty($semester)) {
        $student_where_conditions[] = "f.semester = ?";
        $student_params[] = $semester;
    }
    
    if (!empty($student_where_conditions)) {
        $total_students_query .= " WHERE " . implode(' AND ', $student_where_conditions);
    }
    
    $stmt_total = $conn->prepare($total_students_query);
    if (!empty($student_params)) {
        $stmt_total->bind_param(str_repeat('s', count($student_params)), ...$student_params);
    }
    $stmt_total->execute();
    $total_students = $stmt_total->get_result()->fetch_assoc()['total_students'];

    // Get total forms submitted (responses count)
    $total_forms_query = "SELECT COUNT(fr.id) as total_forms
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        $where_clause";
    
    $stmt_forms = $conn->prepare($total_forms_query);
    if (!empty($params)) {
        $stmt_forms->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt_forms->execute();
    $total_forms_submitted = $stmt_forms->get_result()->fetch_assoc()['total_forms'];

    // Get students who participated in feedback
    $participated_query = "SELECT COUNT(DISTINCT fr.student_id) as participated_students
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        $where_clause";
    
    $stmt_part = $conn->prepare($participated_query);
    if (!empty($params)) {
        $stmt_part->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt_part->execute();
    $participated_students = $stmt_part->get_result()->fetch_assoc()['participated_students'];

    // Get detailed feedback analysis by question
    $detailed_analysis_query = "SELECT 
        fr.question,
        COUNT(CASE WHEN fr.rating = 5 THEN 1 END) as excellent_5,
        COUNT(CASE WHEN fr.rating = 4 THEN 1 END) as good_4,
        COUNT(CASE WHEN fr.rating = 3 THEN 1 END) as average_3,
        COUNT(CASE WHEN fr.rating = 2 THEN 1 END) as fair_2,
        COUNT(CASE WHEN fr.rating = 1 THEN 1 END) as need_improvement_1,
        AVG(fr.rating) as avg_rating,
        COUNT(fr.id) as total_responses
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        $where_clause
        GROUP BY fr.question
        ORDER BY fr.question";
    
    $stmt_detailed = $conn->prepare($detailed_analysis_query);
    if (!empty($params)) {
        $stmt_detailed->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt_detailed->execute();
    $detailed_analysis = $stmt_detailed->get_result();
    $questions_data = [];
    while ($row = $detailed_analysis->fetch_assoc()) {
        $questions_data[] = $row;
    }

    // Get subject information
    $subject_info_query = "SELECT DISTINCT f.subject_code, f.department, f.year, f.semester
        FROM feedback_forms f
        $where_clause
        LIMIT 1";
    
    $stmt_subject = $conn->prepare($subject_info_query);
    if (!empty($params)) {
        $stmt_subject->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt_subject->execute();
    $subject_info = $stmt_subject->get_result()->fetch_assoc();

} catch (Exception $e) {
    $total_students = 0;
    $participated_students = 0;
    $total_forms_submitted = 0;
    $questions_data = [];
    $subject_info = [];
}

// Calculate overall average
$overall_avg = 0;
if (!empty($questions_data)) {
    $total_avg = array_sum(array_column($questions_data, 'avg_rating'));
    $overall_avg = $total_avg / count($questions_data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Student Feedback Analysis Report</title>
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
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-left: 4px solid #2563eb;
            text-align: center;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
            font-size: 14px;
        }
        
        .info-value {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin-top: 5px;
        }
        
        .analysis-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        
        .analysis-table th {
            background: #2563eb;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 12px;
        }
        
        .analysis-table td {
            padding: 10px 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
        }
        
        .analysis-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .parameter-cell {
            text-align: left !important;
            font-weight: 500;
            max-width: 300px;
            padding-left: 12px !important;
        }
        
        .rating-cell {
            font-weight: bold;
            color: #1e40af;
        }
        
        .chart-section {
            margin: 40px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .chart-container {
            height: 400px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-title {
            text-align: center;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .summary-row {
            background: #e8f4fd !important;
            font-weight: bold;
            color: #1e40af;
        }
        
        .print-btn {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 10px;
        }
        
        .print-btn:hover {
            background: #1d4ed8;
        }
        
        @media print {
            body { background: white; }
            .print-btn { display: none; }
            .report-container { box-shadow: none; }
            .chart-section { page-break-inside: avoid; }
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
                    <h3>Department of <?php echo htmlspecialchars($subject_info['department'] ?? 'All Departments'); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="report-title">
            STUDENT FEEDBACK ANALYSIS
            <?php if (!empty($subject_info['subject_code'])): ?>
                <br><?php echo htmlspecialchars($subject_info['subject_code']); ?>
            <?php endif; ?>
        </div>
        
        <!-- Report Info -->
        <div class="report-info">
            <div class="info-box">
                <div class="info-label">Year/Semester</div>
                <div class="info-value">
                    <?php echo htmlspecialchars(($subject_info['year'] ?? 'All') . '/' . ($subject_info['semester'] ?? 'All')); ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Total Students in Class</div>
                <div class="info-value"><?php echo $total_students; ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Total Forms Submitted</div>
                <div class="info-value"><?php echo $total_forms_submitted; ?></div>
            </div>
        </div>
        
        <!-- Additional Stats -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="info-box">
                <div class="info-label">Students Participated</div>
                <div class="info-value"><?php echo $participated_students; ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Participation Rate</div>
                <div class="info-value">
                    <?php 
                    $participation_rate = $total_students > 0 ? round(($participated_students / $total_students) * 100, 1) : 0;
                    echo $participation_rate . '%';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content Layout - Exact Match to Reference -->
        <div style="display: flex; gap: 20px; margin: 20px 0;">
            
            <!-- Left Side - Questions List (Exact Match) -->
            <div style="flex: 0 0 350px;">
                <div style="text-align: center; margin-bottom: 15px;">
                    <strong style="color: #000; font-size: 14px;">Year/Semester: <?php echo htmlspecialchars(($subject_info['year'] ?? 'II') . '/' . ($subject_info['semester'] ?? 'III')); ?></strong>
                </div>
                <div style="text-align: right; margin-bottom: 10px; font-size: 14px; font-weight: bold;">
                    Total Number of Students Participated: <?php echo $participated_students; ?>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #000; padding: 8px; text-align: center; background: #f0f0f0; font-weight: bold;">Sl.No.</th>
                            <th style="border: 1px solid #000; padding: 8px; text-align: left; background: #f0f0f0; font-weight: bold;">Parameters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions_data as $index => $question): ?>
                            <tr>
                                <td style="border: 1px solid #000; padding: 6px; text-align: center; font-weight: bold;"><?php echo $index + 1; ?></td>
                                <td style="border: 1px solid #000; padding: 6px; font-size: 11px; line-height: 1.2;"><?php echo htmlspecialchars($question['question']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Right Side - Charts (Exact Match) -->
            <div style="flex: 1;">
                
                <!-- First Chart - Rating Analysis -->
                <div style="margin-bottom: 20px;">
                    <div style="text-align: center; font-weight: bold; color: #2563eb; font-size: 16px; margin-bottom: 15px;">
                        STUDENT FEEDBACK ANALYSIS
                    </div>
                    <div style="height: 300px; position: relative; border: 1px solid #ddd;">
                        <canvas id="ratingChart" style="width: 100%; height: 100%;"></canvas>
                    </div>
                    <div style="text-align: center; margin-top: 10px; font-weight: bold; color: #2563eb; font-size: 14px;">
                        Parameters
                    </div>
                </div>
                
                <!-- Second Chart - Distribution Analysis -->
                <div style="margin-bottom: 20px;">
                    <div style="text-align: center; font-weight: bold; color: #2563eb; font-size: 16px; margin-bottom: 15px;">
                        STUDENT FEEDBACK ANALYSIS
                    </div>
                    
                    <!-- Legend (Above Chart) -->
                    <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 10px; font-size: 12px;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: #1e40af;"></div>
                            <span>Excellent (5)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: #dc2626;"></div>
                            <span>Good (4)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: #16a34a;"></div>
                            <span>Average (3)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: #7c2d12;"></div>
                            <span>Fair (2)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="width: 12px; height: 12px; background: #06b6d4;"></div>
                            <span>Need Improvement (1)</span>
                        </div>
                    </div>
                    
                    <div style="height: 300px; position: relative; border: 1px solid #ddd;">
                        <canvas id="distributionChart" style="width: 100%; height: 100%;"></canvas>
                    </div>
                    <div style="text-align: center; margin-top: 10px; font-weight: bold; color: #2563eb; font-size: 14px;">
                        Parameters
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Detailed Analysis Table (Full Width) -->
        <table class="analysis-table">
            <thead>
                <tr>
                    <th>Sl.No.</th>
                    <th>Parameters</th>
                    <th>Excellent (5)</th>
                    <th>Good (4)</th>
                    <th>Average (3)</th>
                    <th>Fair (2)</th>
                    <th>Need<br>Improvement (1)</th>
                    <th>Student Rating<br>(1-5 scale)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions_data as $index => $question): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td class="parameter-cell"><?php echo htmlspecialchars($question['question']); ?></td>
                        <td><?php echo $question['excellent_5']; ?></td>
                        <td><?php echo $question['good_4']; ?></td>
                        <td><?php echo $question['average_3']; ?></td>
                        <td><?php echo $question['fair_2']; ?></td>
                        <td><?php echo $question['need_improvement_1']; ?></td>
                        <td class="rating-cell"><?php echo number_format($question['avg_rating'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (!empty($questions_data)): ?>
                <tr class="summary-row">
                    <td></td>
                    <td class="parameter-cell"><strong>Average</strong></td>
                    <td><strong><?php echo number_format(array_sum(array_column($questions_data, 'excellent_5')) / count($questions_data), 0); ?></strong></td>
                    <td><strong><?php echo number_format(array_sum(array_column($questions_data, 'good_4')) / count($questions_data), 0); ?></strong></td>
                    <td><strong><?php echo number_format(array_sum(array_column($questions_data, 'average_3')) / count($questions_data), 0); ?></strong></td>
                    <td><strong><?php echo number_format(array_sum(array_column($questions_data, 'fair_2')) / count($questions_data), 0); ?></strong></td>
                    <td><strong><?php echo number_format(array_sum(array_column($questions_data, 'need_improvement_1')) / count($questions_data), 0); ?></strong></td>
                    <td class="rating-cell"><strong><?php echo number_format($overall_avg, 2); ?></strong></td>
                </tr>
                <?php endif; ?>
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
        
        <!-- Action Buttons -->
        <div style="text-align: center; margin-top: 30px;">
            <button class="print-btn" onclick="window.print()">
                🖨️ Print Report
            </button>
            <a href="analytics.php" class="print-btn" style="text-decoration: none; display: inline-block;">
                ← Back to Analytics
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const questionsData = <?php echo json_encode($questions_data); ?>;
        
        if (questionsData.length > 0) {
            // Rating Chart - Exact Match to Reference
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            const ratingLabels = questionsData.map((q, i) => i + 1);
            const ratingValues = questionsData.map(q => parseFloat(q.avg_rating));
            
            new Chart(ratingCtx, {
                type: 'bar',
                data: {
                    labels: ratingLabels,
                    datasets: [{
                        label: 'Student Rating',
                        data: ratingValues,
                        backgroundColor: '#4472C4',
                        borderColor: '#2F5597',
                        borderWidth: 1,
                        barThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            ticks: { 
                                stepSize: 1.0,
                                font: { size: 12 }
                            },
                            title: {
                                display: true,
                                text: 'Student Rating',
                                font: { size: 12, weight: 'bold' }
                            },
                            grid: {
                                color: '#E0E0E0'
                            }
                        },
                        x: {
                            ticks: {
                                font: { size: 12 }
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    layout: {
                        padding: {
                            left: 10,
                            right: 10,
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            });
            
            // Distribution Chart - Exact Match to Reference
            const distCtx = document.getElementById('distributionChart').getContext('2d');
            
            new Chart(distCtx, {
                type: 'bar',
                data: {
                    labels: ratingLabels,
                    datasets: [
                        {
                            label: 'Excellent (5)',
                            data: questionsData.map(q => parseInt(q.excellent_5)),
                            backgroundColor: '#1e40af',
                            barThickness: 15
                        },
                        {
                            label: 'Good (4)',
                            data: questionsData.map(q => parseInt(q.good_4)),
                            backgroundColor: '#dc2626',
                            barThickness: 15
                        },
                        {
                            label: 'Average (3)',
                            data: questionsData.map(q => parseInt(q.average_3)),
                            backgroundColor: '#16a34a',
                            barThickness: 15
                        },
                        {
                            label: 'Fair (2)',
                            data: questionsData.map(q => parseInt(q.fair_2)),
                            backgroundColor: '#7c2d12',
                            barThickness: 15
                        },
                        {
                            label: 'Need Improvement (1)',
                            data: questionsData.map(q => parseInt(q.need_improvement_1)),
                            backgroundColor: '#06b6d4',
                            barThickness: 15
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: {
                                font: { size: 12 }
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            max: 30,
                            ticks: { 
                                stepSize: 10,
                                font: { size: 12 }
                            },
                            title: {
                                display: true,
                                text: 'No. of Students',
                                font: { size: 12, weight: 'bold' }
                            },
                            grid: {
                                color: '#E0E0E0'
                            }
                        }
                    },
                    layout: {
                        padding: {
                            left: 10,
                            right: 10,
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
