<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get departments for dropdown
$departments_query = "SELECT DISTINCT department FROM feedback_forms ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Get filter parameters
$selected_department = $_GET['department'] ?? '';
$selected_year = $_GET['year'] ?? '';

// Initialize variables
$forms_data = [];
$total_responses = 0;
$show_data = !empty($selected_department) && !empty($selected_year);

if ($show_data) {
    try {
        // Get all forms for selected department and year
        $forms_query = "SELECT 
            f.id,
            f.subject_code,
            f.subject_name,
            f.faculty_name,
            f.department,
            f.year,
            f.semester,
            f.created_at,
            COUNT(fr.id) as response_count,
            COUNT(DISTINCT fr.student_id) as unique_students
            FROM feedback_forms f
            LEFT JOIN feedback_responses fr ON f.id = fr.form_id
            WHERE f.department = ? AND f.year = ?
            GROUP BY f.id
            ORDER BY f.created_at DESC";
        
        $stmt = $conn->prepare($forms_query);
        $stmt->bind_param("ss", $selected_department, $selected_year);
        $stmt->execute();
        $forms_result = $stmt->get_result();
        
        while ($row = $forms_result->fetch_assoc()) {
            $forms_data[] = $row;
            $total_responses += $row['response_count'];
        }
        
    } catch (Exception $e) {
        $error_message = "Error fetching data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Reports - College Feedback System</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
            background: white;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .filter-btn:hover {
            background: #1d4ed8;
        }
        
        .forms-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .forms-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #e5e7eb;
            color: #374151;
        }
        
        .forms-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        
        .forms-table tr:hover {
            background: #f9fafb;
        }
        
        .response-badge {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .zero-responses {
            background: #ef4444;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 14px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .chart-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            font-style: italic;
        }
        
        .view-responses-btn {
            background: #059669;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .view-responses-btn:hover {
            background: #047857;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" style="text-decoration: none;">
                    <div class="sidebar-logo">
                        <i class="fas fa-graduation-cap"></i>
                        <div>
                            <div>College Feedback</div>
                            <div class="sidebar-subtitle">Admin Panel</div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="sidebar-nav">
                <nav class="sidebar-nav">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="manage_forms.php"><i class="fas fa-clipboard-list"></i> Manage Forms</a>
                    <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                    <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                    <a href="form_reports.php" class="active"><i class="fas fa-file-alt"></i> Form Reports</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-file-alt"></i> Form Reports</h1>
                <p>View all forms and responses by department and year</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="department">Department</label>
                        <select name="department" id="department" class="filter-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo ($selected_department == $dept) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="year">Year</label>
                        <select name="year" id="year" class="filter-select" required>
                            <option value="">Select Year</option>
                            <option value="1" <?php echo ($selected_year == '1') ? 'selected' : ''; ?>>Year 1</option>
                            <option value="2" <?php echo ($selected_year == '2') ? 'selected' : ''; ?>>Year 2</option>
                            <option value="3" <?php echo ($selected_year == '3') ? 'selected' : ''; ?>>Year 3</option>
                            <option value="4" <?php echo ($selected_year == '4') ? 'selected' : ''; ?>>Year 4</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Show Forms
                    </button>
                </form>
            </div>

            <?php if ($show_data): ?>
                <?php if (!empty($forms_data)): ?>
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo count($forms_data); ?></div>
                            <div class="stat-label">Total Forms</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_responses; ?></div>
                            <div class="stat-label">Total Responses</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php 
                                $unique_students = array_sum(array_column($forms_data, 'unique_students'));
                                echo $unique_students;
                                ?>
                            </div>
                            <div class="stat-label">Students Participated</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php 
                                $avg_responses = count($forms_data) > 0 ? round($total_responses / count($forms_data), 1) : 0;
                                echo $avg_responses;
                                ?>
                            </div>
                            <div class="stat-label">Avg Responses per Form</div>
                        </div>
                    </div>

                    <!-- Forms Table -->
                    <div class="card">
                        <div class="card-header">
                            <h2>All Forms - <?php echo htmlspecialchars($selected_department . ' Year ' . $selected_year); ?></h2>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="forms-table">
                                <thead>
                                    <tr>
                                        <th>Form ID</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Faculty</th>
                                        <th>Semester</th>
                                        <th>Created Date</th>
                                        <th>Created Time</th>
                                        <th>Total Responses</th>
                                        <th>Unique Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($forms_data as $form): ?>
                                        <tr>
                                            <td><strong>#<?php echo $form['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($form['subject_code']); ?></td>
                                            <td><?php echo htmlspecialchars($form['subject_name'] ?? $form['subject_code']); ?></td>
                                            <td><?php echo htmlspecialchars($form['faculty_name']); ?></td>
                                            <td>Sem <?php echo $form['semester']; ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($form['created_at'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($form['created_at'])); ?></td>
                                            <td>
                                                <span class="response-badge <?php echo $form['response_count'] == 0 ? 'zero-responses' : ''; ?>">
                                                    <?php echo $form['response_count']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $form['unique_students']; ?></td>
                                            <td>
                                                <a href="comprehensive_report.php?department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($form['semester']); ?>&subject_code=<?php echo urlencode($form['subject_code']); ?>" 
                                                   class="view-responses-btn" target="_blank">
                                                    <i class="fas fa-chart-bar"></i> View Report
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                        <!-- Responses per Form Chart -->
                        <div class="chart-container">
                            <div class="chart-title">Responses per Form</div>
                            <canvas id="responsesChart" style="height: 300px;"></canvas>
                        </div>

                        <!-- Semester Distribution Chart -->
                        <div class="chart-container">
                            <div class="chart-title">Forms by Semester</div>
                            <canvas id="semesterChart" style="height: 300px;"></canvas>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const formsData = <?php echo json_encode($forms_data); ?>;
                        
                        // Responses per Form Chart
                        const responsesCtx = document.getElementById('responsesChart').getContext('2d');
                        const formLabels = formsData.map(f => f.subject_code);
                        const responseValues = formsData.map(f => parseInt(f.response_count));
                        
                        new Chart(responsesCtx, {
                            type: 'bar',
                            data: {
                                labels: formLabels,
                                datasets: [{
                                    label: 'Total Responses',
                                    data: responseValues,
                                    backgroundColor: '#3b82f6',
                                    borderColor: '#1e40af',
                                    borderWidth: 1
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
                                        title: {
                                            display: true,
                                            text: 'Number of Responses'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Subject Code'
                                        }
                                    }
                                }
                            }
                        });
                        
                        // Semester Distribution Chart
                        const semesterCtx = document.getElementById('semesterChart').getContext('2d');
                        const semesterCounts = {};
                        formsData.forEach(f => {
                            const sem = 'Semester ' + f.semester;
                            semesterCounts[sem] = (semesterCounts[sem] || 0) + 1;
                        });
                        
                        new Chart(semesterCtx, {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(semesterCounts),
                                datasets: [{
                                    data: Object.values(semesterCounts),
                                    backgroundColor: [
                                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b',
                                        '#8b5cf6', '#06b6d4', '#f97316', '#84cc16'
                                    ]
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }
                        });
                    });
                    </script>

                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="no-data">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; color: #d1d5db;"></i>
                                <h3>No Forms Found</h3>
                                <p>No feedback forms found for <?php echo htmlspecialchars($selected_department . ' Year ' . $selected_year); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="no-data">
                            <i class="fas fa-filter" style="font-size: 48px; margin-bottom: 15px; color: #d1d5db;"></i>
                            <h3>Select Filters</h3>
                            <p>Please select a department and year to view forms and responses</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
