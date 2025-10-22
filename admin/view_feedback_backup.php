<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

// Get filter parameters
$selected_department = $_GET['department'] ?? '';
$selected_year = $_GET['year'] ?? '';
$selected_semester = $_GET['semester'] ?? '';
$view_type = $_GET['view'] ?? 'feedback'; // feedback, analytics, forms

// Get departments for dropdown
$departments_query = "SELECT DISTINCT department FROM feedback_forms ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

// Initialize data arrays
$feedback_data = [];
$analytics_data = [];
$forms_data = [];
$total_responses = 0;

// Build WHERE conditions
$where_conditions = [];
$params = [];
if (!empty($selected_department)) {
    $where_conditions[] = "f.department = ?";
    $params[] = $selected_department;
}
if (!empty($selected_year)) {
    $where_conditions[] = "f.year = ?";
    $params[] = $selected_year;
}
if (!empty($selected_semester)) {
    $where_conditions[] = "f.semester = ?";
    $params[] = $selected_semester;
}
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch data based on view type
if ($view_type === 'feedback') {
    // Original feedback view query
    $sql = "SELECT f.department, f.year, f.semester, fac.name as faculty_name, fr.question, s.name as student_name, s.sin_number, fr.rating
    FROM feedback_responses fr
    JOIN feedback_forms f ON fr.form_id = f.id
    JOIN students s ON fr.student_id = s.id
    JOIN faculty fac ON fr.faculty_id = fac.id";
    if ($where_clause) $sql .= ' ' . $where_clause;
    $sql .= ' ORDER BY f.department, f.year, f.semester, fac.name, s.name';
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
} elseif ($view_type === 'forms') {
    // Forms view query
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
        $where_clause
        GROUP BY f.id
        ORDER BY f.created_at DESC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($forms_query);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $forms_result = $stmt->get_result();
        while ($row = $forms_result->fetch_assoc()) {
            $forms_data[] = $row;
            $total_responses += $row['response_count'];
        }
    } else {
        $forms_result = $conn->query($forms_query);
        while ($row = $forms_result->fetch_assoc()) {
            $forms_data[] = $row;
            $total_responses += $row['response_count'];
        }
    }
} elseif ($view_type === 'analytics') {
    // Analytics view query
    $analytics_query = "SELECT 
        fr.question,
        AVG(fr.rating) as avg_rating,
        COUNT(fr.id) as response_count,
        SUM(CASE WHEN fr.rating = 5 THEN 1 ELSE 0 END) as excellent_5,
        SUM(CASE WHEN fr.rating = 4 THEN 1 ELSE 0 END) as good_4,
        SUM(CASE WHEN fr.rating = 3 THEN 1 ELSE 0 END) as average_3,
        SUM(CASE WHEN fr.rating = 2 THEN 1 ELSE 0 END) as fair_2,
        SUM(CASE WHEN fr.rating = 1 THEN 1 ELSE 0 END) as need_improvement_1
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_id = f.id
        $where_clause
        GROUP BY fr.question
        ORDER BY fr.question";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($analytics_query);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $analytics_result = $stmt->get_result();
        while ($row = $analytics_result->fetch_assoc()) {
            $analytics_data[] = $row;
        }
    } else {
        $analytics_result = $conn->query($analytics_query);
        while ($row = $analytics_result->fetch_assoc()) {
            $analytics_data[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* HEADER */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.95);
        }

        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 4rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--gray-900);
            text-decoration: none;
        }

        .logo-icon {
            width: 2rem;
            height: 2rem;
            background: var(--primary-color);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--gray-600);
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .nav-link:hover {
            background-color: var(--gray-100);
            color: var(--gray-900);
        }

        .nav-link.active {
            background-color: var(--primary-color);
            color: var(--white);
        }

        .mobile-menu-button {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            color: var(--gray-600);
            border-radius: 0.375rem;
            cursor: pointer;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }

        /* FILTER CARD */
        .filter-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .filter-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
            color: var(--gray-900);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* DATA CARD */
        .data-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .data-card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .download-btn {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .download-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        /* TABLE */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th {
            background: var(--gray-50);
            color: var(--gray-700);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid var(--gray-200);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-900);
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            text-align: center;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.students { background: var(--primary-color); }
        .stat-icon.responses { background: var(--success-color); }
        .stat-icon.departments { background: var(--warning-color); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* FOOTER */
        .footer {
            background: var(--white);
            color: var(--gray-600);
            font-size: 0.875rem;
            text-align: center;
            padding: 2rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            margin-top: auto;
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
        }

        .footer-text {
            margin-bottom: 0.5rem;
        }

        .footer-subtext {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
            }

            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--white);
                border-top: 1px solid var(--gray-200);
                flex-direction: column;
                padding: 1rem;
                gap: 0.25rem;
                box-shadow: var(--shadow-md);
            }

            .nav-menu.mobile-open {
                display: flex;
            }

            .mobile-menu-button {
                display: block;
            }

            .main-content {
                padding: 1rem 0.75rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .filter-card {
                padding: 1.5rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .data-card-header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                College Feedback Portal
            </a>
            
            <nav class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="../includes/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
    
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Feedback & Analytics</h1>
            <p class="page-subtitle">Review feedback, analyze data, and view form reports across departments and semesters.</p>
        </div>

        <!-- View Tabs -->
        $stats_sql = "SELECT 
            COUNT(DISTINCT s.id) as total_students,
            COUNT(fr.id) as total_responses,
            COUNT(DISTINCT f.department) as total_departments
            FROM feedback_responses fr
            JOIN students s ON fr.student_id = s.id
            JOIN feedback_forms f ON fr.form_id = f.id";
        $stats_where = [];
        if (!empty($_GET['department'])) $stats_where[] = "f.department='".$conn->real_escape_string($_GET['department'])."'";
        if (!empty($_GET['year'])) $stats_where[] = "f.year='".$conn->real_escape_string($_GET['year'])."'";
        if (!empty($_GET['semester'])) $stats_where[] = "f.semester='".$conn->real_escape_string($_GET['semester'])."'";
        if ($stats_where) $stats_sql .= ' WHERE ' . implode(' AND ', $stats_where);
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result ? $stats_result->fetch_assoc() : ['total_students' => 0, 'total_responses' => 0, 'total_departments' => 0];
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon students">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_students']) ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon responses">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_responses']) ?></div>
                <div class="stat-label">Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon departments">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?= number_format($stats['total_departments']) ?></div>
                <div class="stat-label">Departments</div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                Filter Feedback
            </h3>
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select" required>
                        <option value="">Select Department</option>
                        <option value="ECE"<?= isset($_GET['department']) && $_GET['department']==='ECE'?' selected':''; ?>>Electronics & Communication (ECE)</option>
                        <option value="CSE"<?= isset($_GET['department']) && $_GET['department']==='CSE'?' selected':''; ?>>Computer Science (CSE)</option>
                        <option value="EEE"<?= isset($_GET['department']) && $_GET['department']==='EEE'?' selected':''; ?>>Electrical & Electronics (EEE)</option>
                        <option value="MECH"<?= isset($_GET['department']) && $_GET['department']==='MECH'?' selected':''; ?>>Mechanical Engineering (MECH)</option>
                        <option value="CIVIL"<?= isset($_GET['department']) && $_GET['department']==='CIVIL'?' selected':''; ?>>Civil Engineering (CIVIL)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Academic Year</label>
                    <select name="year" class="form-select" required>
                        <option value="">Select Year</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= isset($_GET['year']) && $_GET['year']==$i?' selected':''; ?>>Year <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select" required>
                        <option value="">Select Semester</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= isset($_GET['semester']) && $_GET['semester']==$i?' selected':''; ?>>Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Data Card -->
        <div class="data-card">
            <div class="data-card-header">
                <h3 class="data-card-title">
                    <i class="fas fa-table"></i>
                    Student Feedback Responses
                </h3>
                <?php
                $pdf_link = 'feedback_pdf.php';
                if (isset($_GET['department'],$_GET['year'],$_GET['semester']) && $_GET['department'] && $_GET['year'] && $_GET['semester']) {
                    $pdf_link .= '?department='.urlencode($_GET['department']).'&year='.urlencode($_GET['year']).'&semester='.urlencode($_GET['semester']);
                }
                ?>
                <a href="<?= $pdf_link ?>" class="btn btn-success download-btn">
                    <i class="fas fa-download"></i>
                    Download PDF
                </a>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>SIN Number</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stu_sql = "SELECT DISTINCT s.id, s.name, s.sin_number, s.department, s.year, s.semester 
                                    FROM feedback_responses fr 
                                    JOIN students s ON fr.student_id = s.id";
                        $where = [];
                        if (!empty($_GET['department'])) $where[] = "s.department='".$conn->real_escape_string($_GET['department'])."'";
                        if (!empty($_GET['year'])) $where[] = "s.year='".$conn->real_escape_string($_GET['year'])."'";
                        if (!empty($_GET['semester'])) $where[] = "s.semester='".$conn->real_escape_string($_GET['semester'])."'";
                        if ($where) $stu_sql .= ' WHERE ' . implode(' AND ', $where);
                        $stu_sql .= ' ORDER BY s.name';
                        $stu_result = $conn->query($stu_sql);

                        if ($stu_result && $stu_result->num_rows > 0):
                            while ($stu = $stu_result->fetch_assoc()):
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($stu['name']) ?></div>
                                </td>
                                <td>
                                    <span style="font-family: 'Courier New', monospace; background: var(--gray-100); padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem;">
                                        <?= htmlspecialchars($stu['sin_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500;">
                                        <?= htmlspecialchars($stu['department']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($stu['year']) ?></td>
                                <td><?= htmlspecialchars($stu['semester']) ?></td>
                                <td>
                                    <a href="view_student_response.php?student_id=<?= $stu['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                        View Response
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <i class="fas fa-search"></i>
                                    <div style="font-weight: 500; margin-bottom: 0.5rem;">No feedback data found</div>
                                    <div>Try adjusting your filter criteria to find feedback responses.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-text">&copy; <?= date('Y') ?> College Feedback Portal. All rights reserved.</div>
            <div class="footer-subtext">Enhancing education through continuous feedback and improvement</div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('mobile-open');
        }

        // Auto-hide mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navMenu = document.getElementById('navMenu');
            const menuButton = document.querySelector('.mobile-menu-btn');
            
            if (!navMenu.contains(event.target) && !menuButton.contains(event.target)) {
                navMenu.classList.remove('mobile-open');
            }
        });
    </script>
</body>
</html>