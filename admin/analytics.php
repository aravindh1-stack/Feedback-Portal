<?php
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
$show_data = !empty($selected_department) || !empty($selected_year) || !empty($selected_semester);

// Initialize empty arrays
$dept_analysis = [];
$dept_stats = [];
$faculty_performance = [];
$rating_distribution = [];
$subject_analysis = [];

// Only fetch data if filters are applied
if ($show_data) {
    try {
        // Build WHERE conditions based on filters
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
        
        // Get department statistics with filters
        $dept_stats_query = "SELECT 
            f.department,
            f.year,
            f.semester,
            COUNT(DISTINCT f.id) as total_subjects,
            COUNT(fr.id) as total_responses,
            AVG(fr.rating) as avg_rating,
            COUNT(DISTINCT fr.student_id) as participating_students
            FROM feedback_forms f
            LEFT JOIN feedback_responses fr ON f.id = fr.form_id
            $where_clause
            GROUP BY f.department, f.year, f.semester
            ORDER BY total_responses DESC";
        
        $stmt = $conn->prepare($dept_stats_query);
        if (!empty($params)) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $dept_stats_result = $stmt->get_result();
        while ($row = $dept_stats_result->fetch_assoc()) {
            $dept_stats[] = $row;
        }

        // Get subject-wise analysis with filters
        $subject_analysis_query = "SELECT 
            f.subject_code,
            f.subject_code as subject_name,
            f.department,
            f.year,
            f.semester,
            COUNT(fr.id) as response_count,
            AVG(fr.rating) as avg_rating,
            COUNT(DISTINCT fr.student_id) as student_count
            FROM feedback_forms f
            LEFT JOIN feedback_responses fr ON f.id = fr.form_id
            $where_clause
            GROUP BY f.id, f.subject_code, f.department, f.year, f.semester
            ORDER BY avg_rating DESC";
        
        $stmt2 = $conn->prepare($subject_analysis_query);
        if (!empty($params)) {
            $stmt2->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt2->execute();
        $subject_analysis_result = $stmt2->get_result();
        while ($row = $subject_analysis_result->fetch_assoc()) {
            $subject_analysis[] = $row;
        }

        // Get rating distribution with filters
        $rating_distribution_query = "SELECT 
            fr.rating,
            COUNT(*) as count
            FROM feedback_responses fr
            JOIN feedback_forms f ON fr.form_id = f.id
            $where_clause AND fr.rating IS NOT NULL
            GROUP BY fr.rating
            ORDER BY fr.rating";
        
        $stmt3 = $conn->prepare($rating_distribution_query);
        if (!empty($params)) {
            $stmt3->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt3->execute();
        $rating_distribution_result = $stmt3->get_result();
        while ($row = $rating_distribution_result->fetch_assoc()) {
            $rating_distribution[] = $row;
        }

        // Get faculty performance with filters
        $faculty_performance_query = "SELECT 
            fac.name as faculty_name,
            fac.department,
            COUNT(fr.id) as total_responses,
            AVG(fr.rating) as avg_rating
            FROM faculty fac
            JOIN feedback_responses fr ON fac.id = fr.faculty_id
            JOIN feedback_forms f ON fr.form_id = f.id
            $where_clause AND fr.rating IS NOT NULL
            GROUP BY fac.id, fac.name, fac.department
            HAVING total_responses > 0
            ORDER BY avg_rating DESC";
        
        $stmt4 = $conn->prepare($faculty_performance_query);
        if (!empty($params)) {
            $stmt4->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt4->execute();
        $faculty_performance_result = $stmt4->get_result();
        while ($row = $faculty_performance_result->fetch_assoc()) {
            $faculty_performance[] = $row;
        }

    } catch (Exception $e) {
        // Keep arrays empty on error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - College Feedback System</title>
    
    <!-- External Libraries -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono:wght@400&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            /* CRT Terminal Colors */
            --crt-bg: #0a0a0a;
            --crt-screen: #001100;
            --crt-green: #00ff41;
            --crt-green-dim: #00cc33;
            --crt-green-bright: #66ff66;
            --crt-amber: #ffb000;
            --crt-red: #ff4444;
            --crt-blue: #4488ff;
            --crt-cyan: #44ffff;
            --crt-border: #333;
            --crt-glow: 0 0 10px var(--crt-green), 0 0 20px var(--crt-green), 0 0 30px var(--crt-green);
            --crt-text-glow: 0 0 5px currentColor;
            --sidebar-width: 280px;
            --header-height: 60px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @keyframes flicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.98; }
        }
        
        @keyframes scanlines {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100vh); }
        }
        
        body {
            font-family: 'Share Tech Mono', monospace;
            background: var(--crt-bg);
            color: var(--crt-green);
            line-height: 1.4;
            overflow-x: hidden;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                linear-gradient(transparent 50%, rgba(0, 255, 65, 0.03) 50%),
                linear-gradient(90deg, transparent 50%, rgba(0, 255, 65, 0.02) 50%);
            background-size: 100% 4px, 4px 100%;
            pointer-events: none;
            z-index: 1000;
            animation: flicker 0.15s infinite linear;
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at center, transparent 60%, rgba(0, 0, 0, 0.3) 100%);
            pointer-events: none;
            z-index: 999;
        }

        .admin-layout { 
            display: flex;
            background: var(--crt-screen);
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--crt-screen);
            border-radius: 15px;
            margin: 10px;
            border: 2px solid var(--crt-green-dim);
            box-shadow: var(--crt-glow), inset 0 0 50px rgba(0, 255, 65, 0.1);
            position: relative;
        }
        
        .content { 
            flex: 1; 
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--crt-bg);
            border-right: 2px solid var(--crt-green-dim);
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1001;
            box-shadow: inset -5px 0 20px rgba(0, 255, 65, 0.1);
        }
        
        .sidebar-header {
            height: var(--header-height);
            padding: 0 1rem;
            border-bottom: 1px solid var(--crt-green-dim);
            display: flex;
            align-items: center;
            background: rgba(0, 255, 65, 0.05);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Orbitron', monospace;
            font-size: 1rem;
            font-weight: 700;
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
        }
        
        .sidebar-logo i {
            width: 32px; 
            height: 32px;
            background: transparent;
            color: var(--crt-green);
            border: 1px solid var(--crt-green);
            border-radius: 4px;
            display: grid;
            place-items: center;
            font-size: 1rem;
            text-shadow: var(--crt-text-glow);
        }
        
        .sidebar-subtitle { 
            font-size: 0.7rem; 
            color: var(--crt-green-dim); 
            font-weight: 400;
            font-family: 'Share Tech Mono', monospace;
        }
        
        .sidebar-nav { 
            padding: 1rem 0; 
            flex-grow: 1; 
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0 1rem 0.5rem;
            color: var(--crt-green-dim);
            border-bottom: 1px solid rgba(0, 255, 65, 0.2);
            margin-bottom: 0.5rem;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--crt-green);
            text-decoration: none;
            margin: 2px 0.5rem;
            border-left: 2px solid transparent;
            font-weight: 400;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .nav-item:hover { 
            background: rgba(0, 255, 65, 0.1); 
            border-left-color: var(--crt-green);
            text-shadow: var(--crt-text-glow);
        }
        
        .nav-item.active {
            background-color: rgba(0, 255, 65, 0.15);
            border-left-color: var(--crt-green-bright);
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
        }
        
        .nav-item.danger:hover { 
            background-color: rgba(255, 68, 68, 0.1); 
            color: var(--crt-red);
            border-left-color: var(--crt-red);
        }

        /* Header */
        .header {
            height: var(--header-height);
            background: rgba(0, 17, 0, 0.9);
            border-bottom: 1px solid var(--crt-green-dim);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 998;
            backdrop-filter: blur(5px);
        }
        
        .page-title { 
            font-family: 'Orbitron', monospace;
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .breadcrumb { 
            font-size: 0.8rem; 
            color: var(--crt-green-dim);
            font-family: 'Share Tech Mono', monospace;
        }
        
        .breadcrumb span {
            color: var(--crt-green);
        }

        /* Cards */
        .card {
            background: rgba(0, 17, 0, 0.8);
            border: 1px solid var(--crt-green-dim);
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 255, 65, 0.1), inset 0 0 20px rgba(0, 255, 65, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--crt-green), transparent);
        }
        
        .card-header { 
            padding: 1rem; 
            border-bottom: 1px solid var(--crt-green-dim);
            background: rgba(0, 255, 65, 0.1);
            position: relative;
        }
        
        .card-title { 
            font-family: 'Orbitron', monospace;
            font-size: 1.1rem; 
            font-weight: 700; 
            margin-bottom: 4px;
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
            text-transform: uppercase;
        }
        
        .card-subtitle { 
            font-size: 0.8rem; 
            color: var(--crt-green-dim);
            font-family: 'Share Tech Mono', monospace;
        }
        
        .card-body { 
            padding: 1rem;
            background: rgba(0, 0, 0, 0.3);
        }

        /* Analytics Grid */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container { 
            position: relative; 
            height: 350px; 
            margin-bottom: 1rem;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid var(--crt-green-dim);
            border-radius: 4px;
            padding: 10px;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.9rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--crt-green-dim);
            color: var(--crt-green);
        }
        
        .data-table th {
            background: rgba(0, 255, 65, 0.1);
            font-weight: 700;
            color: var(--crt-green-bright);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            text-shadow: var(--crt-text-glow);
            border-bottom: 2px solid var(--crt-green);
        }
        
        .data-table tr:hover {
            background: rgba(0, 255, 65, 0.05);
        }
        
        .data-table td strong {
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(0, 0, 0, 0.6);
            padding: 1.5rem;
            border: 1px solid var(--crt-green-dim);
            border-radius: 4px;
            border-left: 4px solid var(--crt-green);
            box-shadow: 0 0 15px rgba(0, 255, 65, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 49%, rgba(0, 255, 65, 0.03) 50%, transparent 51%);
            pointer-events: none;
        }
        
        .stat-value {
            font-family: 'Orbitron', monospace;
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--crt-green-dim);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            z-index: 1;
        }

        /* Form Elements */
        select, button {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid var(--crt-green-dim);
            color: var(--crt-green);
            padding: 0.5rem;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.9rem;
        }
        
        select:focus, button:focus {
            outline: none;
            border-color: var(--crt-green);
            box-shadow: 0 0 10px rgba(0, 255, 65, 0.3);
        }
        
        button {
            background: rgba(0, 255, 65, 0.1);
            color: var(--crt-green-bright);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        button:hover {
            background: rgba(0, 255, 65, 0.2);
            box-shadow: 0 0 15px rgba(0, 255, 65, 0.4);
            text-shadow: var(--crt-text-glow);
        }
        
        label {
            color: var(--crt-green-dim);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Terminal-style links */
        a {
            color: var(--crt-cyan);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        a:hover {
            color: var(--crt-green-bright);
            text-shadow: var(--crt-text-glow);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; margin: 5px; }
            .analytics-grid { grid-template-columns: 1fr; }
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
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="analytics.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Analytics</a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="manage_users.php" class="nav-item"><i class="fas fa-users"></i> Users</a>
                    <a href="manage_forms.php" class="nav-item"><i class="fas fa-file-alt"></i> Manage Forms</a>
                    <a href="form_reports.php" class="nav-item"><i class="fas fa-chart-line"></i> Form Reports</a>
                    <a href="view_feedback.php" class="nav-item"><i class="fas fa-comments"></i> View Feedback</a>
                    <a href="student_feedback_list.php" class="nav-item"><i class="fas fa-user-graduate"></i> Student Data</a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="#" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
                    <a href="../includes/logout.php" class="nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div>
                        <h1 class="page-title">Student Feedback Analytics</h1>
                        <div class="breadcrumb">
                            <span><i class="fas fa-home"></i> Admin</span>
                            <span>&nbsp;/&nbsp;</span>
                            <span>Analytics</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Filter Selection Section -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3 class="card-title">Select Filters to View Analytics</h3>
                        <p class="card-subtitle">Choose department, year, and semester to analyze feedback data</p>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="analytics.php" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Department:</label>
                                <select name="department" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.5rem; min-width: 150px;">
                                    <option value="">Select Department</option>
                                    <?php 
                                    $dept_query = "SELECT DISTINCT department FROM feedback_forms ORDER BY department";
                                    $dept_result = $conn->query($dept_query);
                                    while ($dept_result && $row = $dept_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($row['department']); ?>" 
                                                <?php echo ($selected_department == $row['department']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['department']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Year:</label>
                                <select name="year" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.5rem; min-width: 120px;">
                                    <option value="">Select Year</option>
                                    <option value="1" <?php echo ($selected_year == '1') ? 'selected' : ''; ?>>Year 1</option>
                                    <option value="2" <?php echo ($selected_year == '2') ? 'selected' : ''; ?>>Year 2</option>
                                    <option value="3" <?php echo ($selected_year == '3') ? 'selected' : ''; ?>>Year 3</option>
                                    <option value="4" <?php echo ($selected_year == '4') ? 'selected' : ''; ?>>Year 4</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Semester:</label>
                                <select name="semester" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.5rem; min-width: 140px;">
                                    <option value="">Select Semester</option>
                                    <option value="1" <?php echo ($selected_semester == '1') ? 'selected' : ''; ?>>Semester 1</option>
                                    <option value="2" <?php echo ($selected_semester == '2') ? 'selected' : ''; ?>>Semester 2</option>
                                    <option value="3" <?php echo ($selected_semester == '3') ? 'selected' : ''; ?>>Semester 3</option>
                                    <option value="4" <?php echo ($selected_semester == '4') ? 'selected' : ''; ?>>Semester 4</option>
                                    <option value="5" <?php echo ($selected_semester == '5') ? 'selected' : ''; ?>>Semester 5</option>
                                    <option value="6" <?php echo ($selected_semester == '6') ? 'selected' : ''; ?>>Semester 6</option>
                                    <option value="7" <?php echo ($selected_semester == '7') ? 'selected' : ''; ?>>Semester 7</option>
                                    <option value="8" <?php echo ($selected_semester == '8') ? 'selected' : ''; ?>>Semester 8</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" style="background: var(--primary-500); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600;">
                                    <i class="fas fa-search"></i> View Analytics
                                </button>
                            </div>
                            <?php if ($show_data): ?>
                            <div>
                                <a href="comprehensive_report.php?department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($selected_semester); ?>" 
                                   style="background: var(--success-500); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; margin-right: 10px;">
                                    <i class="fas fa-file-alt"></i> Detailed Report
                                </a>
                                <a href="generate_report.php?department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($selected_semester); ?>" 
                                   style="background: var(--primary-500); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block;">
                                    <i class="fas fa-download"></i> Simple Report
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if (!$show_data): ?>
                <!-- No Filter Selected Message -->
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-chart-bar" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--heading-color); margin-bottom: 0.5rem;">Select Filters to View Analytics</h3>
                        <p style="color: var(--text-color);">Please select at least one filter (Department, Year, or Semester) to view the feedback analytics data.</p>
                    </div>
                </div>
                <?php else: ?>
                
                <?php if (empty($subject_analysis)): ?>
                <!-- No Data Found Message -->
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--warning-500); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--heading-color); margin-bottom: 0.5rem;">No Data Found</h3>
                        <p style="color: var(--text-color);">No feedback data found for the selected filters. Please try different filter combinations or ensure feedback responses exist for the selected criteria.</p>
                    </div>
                </div>
                <?php else: ?>

                <!-- Department Statistics -->
                <div class="stats-grid">
                    <?php foreach ($dept_stats as $dept): ?>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $dept['participating_students']; ?></div>
                            <div class="stat-label"><?php echo htmlspecialchars($dept['department']); ?> - Students Participated</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Analytics Charts -->
                <div class="analytics-grid">
                    <!-- Department-wise Response Analysis -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Department-wise Feedback Analysis</h3>
                            <p class="card-subtitle">Response count by department</p>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="deptResponseChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Rating Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Rating Distribution</h3>
                            <p class="card-subtitle">Distribution of student ratings</p>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="ratingDistChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Performance -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Faculty Performance</h3>
                            <p class="card-subtitle">Average ratings by faculty</p>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="facultyPerfChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Subject-wise Analysis -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Subject-wise Analysis</h3>
                            <p class="card-subtitle">Performance by subjects</p>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="subjectAnalysisChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detailed Subject Analysis</h3>
                        <p class="card-subtitle">Complete breakdown of feedback by subjects</p>
                    </div>
                    <div class="card-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Department</th>
                                    <th>Responses</th>
                                    <th>Students</th>
                                    <th>Avg Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subject_analysis as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['department']); ?></td>
                                        <td><?php echo $subject['response_count']; ?></td>
                                        <td><?php echo $subject['student_count']; ?></td>
                                        <td><strong><?php echo round($subject['avg_rating'], 2); ?>/5.0</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php endif; // End of data found check ?>
                <?php endif; // End of show_data check ?>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($show_data && !empty($subject_analysis)): ?>
        // CRT Terminal Chart colors
        const colors = ['#00ff41', '#66ff66', '#44ffff', '#ffb000', '#ff4444', '#4488ff', '#00cc33', '#88ff88'];
        
        // Chart.js default configuration for CRT theme
        Chart.defaults.color = '#00ff41';
        Chart.defaults.borderColor = 'rgba(0, 255, 65, 0.2)';
        Chart.defaults.backgroundColor = 'rgba(0, 255, 65, 0.1)';
        Chart.defaults.font.family = 'Share Tech Mono, monospace';
        Chart.defaults.font.size = 11;
        
        // Department Response Chart
        const deptData = <?php echo json_encode($dept_stats); ?>;
        const deptLabels = deptData.map(d => d.department);
        const deptResponses = deptData.map(d => parseInt(d.total_responses));
        
        if (document.getElementById('deptResponseChart')) {
            new Chart(document.getElementById('deptResponseChart'), {
            type: 'bar',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Total Responses',
                    data: deptResponses,
                    backgroundColor: colors.map(c => c + '80'),
                    borderColor: colors,
                    borderWidth: 2,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: false 
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    },
                    y: { 
                        beginAtZero: true,
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    }
                }
            }
        });

        // Rating Distribution Chart
        const ratingData = <?php echo json_encode($rating_distribution); ?>;
        const ratingLabels = ratingData.map(r => `${r.rating} Stars`);
        const ratingCounts = ratingData.map(r => parseInt(r.count));
        
        new Chart(document.getElementById('ratingDistChart'), {
            type: 'doughnut',
            data: {
                labels: ratingLabels,
                datasets: [{
                    data: ratingCounts,
                    backgroundColor: colors.map(c => c + '80'),
                    borderColor: colors,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            color: '#00ff41',
                            font: {
                                family: 'Share Tech Mono, monospace',
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Faculty Performance Chart
        const facultyData = <?php echo json_encode($faculty_performance); ?>;
        const facultyLabels = facultyData.map(f => f.faculty_name);
        const facultyRatings = facultyData.map(f => parseFloat(f.avg_rating));
        
        new Chart(document.getElementById('facultyPerfChart'), {
            type: 'bar',
            data: {
                labels: facultyLabels,
                datasets: [{
                    label: 'Average Rating',
                    data: facultyRatings,
                    backgroundColor: colors[0] + '80',
                    borderColor: colors[0],
                    borderWidth: 2,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { 
                        beginAtZero: true, 
                        max: 5,
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    },
                    y: {
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    }
                }
            }
        });

        // Subject Analysis Chart
        const subjectData = <?php echo json_encode($subject_analysis); ?>;
        const subjectLabels = subjectData.map(s => s.subject_code);
        const subjectRatings = subjectData.map(s => parseFloat(s.avg_rating));
        
        new Chart(document.getElementById('subjectAnalysisChart'), {
            type: 'line',
            data: {
                labels: subjectLabels,
                datasets: [{
                    label: 'Average Rating',
                    data: subjectRatings,
                    borderColor: colors[1],
                    backgroundColor: colors[1] + '40',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: colors[1],
                    pointBorderColor: '#001100',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    },
                    y: { 
                        beginAtZero: true, 
                        max: 5,
                        ticks: { color: '#00ff41' },
                        grid: { color: 'rgba(0, 255, 65, 0.2)' }
                    }
                }
            }
        });
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>
