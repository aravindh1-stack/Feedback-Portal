<?php
// Entha session'il user login seithullara enbathaiyum, avarudaya role'aiyum ariya, session'ai thodanga.
session_start();

// User login seyyamal irunthalo allathu admin aaga illamalo irunthal, login pakkathirku thiruppi anuppa vendum.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Include database connection
require_once '../config/db.php';

// Fetch real data from database
try {
    // Helper: check if a column exists
    $has_created_at_res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='feedback_responses' AND COLUMN_NAME='created_at'");
    $has_fr_created_at = $has_created_at_res && $has_created_at_res->num_rows > 0;

    // Today's responses count
    $today = date('Y-m-d');
    if ($has_fr_created_at) {
        $today_sql = "
            SELECT COUNT(DISTINCT CONCAT(fr.student_id,'-',fr.form_number)) AS cnt
            FROM feedback_responses fr
            WHERE DATE(fr.created_at) = ?
        ";
        $stmt = $conn->prepare($today_sql);
        if ($stmt) {
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : ['cnt' => 0];
            $today_responses = (int)($row['cnt'] ?? 0);
            $stmt->close();
        } else {
            $today_responses = 0;
        }
    } else {
        // Fallback: use feedback_forms.created_at (date a form batch was created) to estimate today's submissions
        $today_sql = "
            SELECT COUNT(DISTINCT CONCAT(fr.student_id,'-',fr.form_number)) AS cnt
            FROM feedback_responses fr
            JOIN feedback_forms f ON fr.form_number = f.form_number
            WHERE DATE(f.created_at) = ?
        ";
        $stmt = $conn->prepare($today_sql);
        if ($stmt) {
            $stmt->bind_param('s', $today);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : ['cnt' => 0];
            $today_responses = (int)($row['cnt'] ?? 0);
            $stmt->close();
        } else {
            $today_responses = 0;
        }
    }

    // Total submissions (distinct student + form_number)
    $total_responses_query = "SELECT COUNT(DISTINCT CONCAT(student_id,'-',form_number)) AS cnt FROM feedback_responses";
    $total_responses_result = $conn->query($total_responses_query);
    $total_responses = $total_responses_result ? (int)$total_responses_result->fetch_assoc()['cnt'] : 0;

    // Overall rating calculation
    $avg_rating_query = "SELECT AVG(rating) as avg_rating FROM feedback_responses WHERE rating IS NOT NULL";
    $avg_rating_result = $conn->query($avg_rating_query);
    $avg_rating = $avg_rating_result ? round($avg_rating_result->fetch_assoc()['avg_rating'], 1) : 0;

    // Active users count (students + faculty)
    $students_count_query = "SELECT COUNT(*) as count FROM students";
    $students_count_result = $conn->query($students_count_query);
    $students_count = $students_count_result ? $students_count_result->fetch_assoc()['count'] : 0;

    $faculty_count_query = "SELECT COUNT(*) as count FROM faculty";
    $faculty_count_result = $conn->query($faculty_count_query);
    $faculty_count = $faculty_count_result ? $faculty_count_result->fetch_assoc()['count'] : 0;

    $active_users = $students_count + $faculty_count;

    // Department distribution for chart (responses grouped by department)
    // Join on form_number only (department is consistent per form_number)
    $dept_distribution_query = "
        SELECT f.department, COUNT(fr.id) AS response_count
        FROM feedback_responses fr
        JOIN feedback_forms f ON fr.form_number = f.form_number
        GROUP BY f.department
        ORDER BY response_count DESC
    ";
    $dept_distribution_result = $conn->query($dept_distribution_query);
    $dept_data = [];
    $dept_labels = [];
    while ($dept_distribution_result && $row = $dept_distribution_result->fetch_assoc()) {
        $dept_labels[] = $row['department'];
        $dept_data[] = (int) $row['response_count']; // Cast to int for JS
    }

    // Top performing faculty based on ratings (Design shows two lists)
    // Your query is perfect.
    $top_faculty_query = "SELECT f.name, f.department, AVG(fr.rating) as avg_rating, COUNT(fr.id) as response_count
                          FROM faculty f 
                          LEFT JOIN feedback_responses fr ON f.id = fr.faculty_id 
                          WHERE fr.rating IS NOT NULL
                          GROUP BY f.id, f.name, f.department 
                          HAVING response_count > 0
                          ORDER BY avg_rating DESC, response_count DESC 
                          LIMIT 10";
    $top_faculty_result = $conn->query($top_faculty_query);
    $top_faculty = [];
    while ($top_faculty_result && $row = $top_faculty_result->fetch_assoc()) {
        $top_faculty[] = $row;
    }
    
    // Split faculty for the two lists in the design
    $top_faculty_list_1 = array_slice($top_faculty, 0, 2); // First 2
    $top_faculty_list_2 = array_slice($top_faculty, 2, 3); // Next 3

} catch (Exception $e) {
    // Fallback to default values if database queries fail
    $today_responses = 0;
    $total_responses = 0;
    $avg_rating = 0;
    $active_users = 0;
    $dept_data = [10, 20, 30]; // Example data
    $dept_labels = ['Error 1', 'Error 2', 'Error 3']; // Example data
    $top_faculty = [];
    $top_faculty_list_1 = [];
    $top_faculty_list_2 = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aarasys</title>

    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* === NEW DESIGN STYLES (Based on Image) === */

        /* 1. CSS Variables (Theme) */
        :root {
            /* Palette */
            --primary-blue: #3b82f6; /* Blue for buttons */
            --primary-purple: #6366F1; /* Purple for banner */
            --dark-bg: #1f2937;     /* Sidebar bg */
            --light-bg: #f3f4f6;    /* Main body bg */
            --card-bg: #ffffff;     /* Card bg */
            --border-color: #e5e7eb;/* Light border */
            --text-dark: #111827;   /* Dark heading */
            --text-body: #4b5563;   /* Body copy */
            --text-light: #f9fafb;  /* Text on dark bg */
            --text-muted: #9ca3af;  /* Muted text (sidebar) */
            --text-blue: #2563eb;   /* Text on blue bg */
            
            /* Sizing & Spacing */
            --sidebar-width: 280px;
            --header-height: 88px;
            --radius-sm: 0.375rem; /* 6px */
            --radius-md: 0.5rem;   /* 8px */
            --radius-lg: 0.75rem;  /* 12px */
            --radius-xl: 1rem;     /* 16px */
            --radius-2xl: 1.5rem;  /* 24px */

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
        }

        /* 2. Base & Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
        }
        
        a { text-decoration: none; color: inherit; }

        /* 3. Main Layout */
        .admin-layout {
            display: flex;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width); /* Matches sidebar.php */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .content-area {
            padding: 2rem 2.5rem;
            flex: 1;
        }

        /* 4. Header (Topbar) */
        .header {
            height: var(--header-height);
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        
        .header-title {
            font-size: 1.75rem; /* 28px */
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .search-wrapper {
            position: relative;
        }
        
        .search-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .search-input {
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--light-bg);
            font-size: 0.9rem;
            width: 280px;
            transition: all 0.2s ease;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background-color: var(--card-bg);
        }
        
        .header-btn {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            color: var(--text-body);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .header-btn:hover {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--primary-purple);
            color: var(--text-light);
            display: grid;
            place-items: center;
            font-weight: 600;
            font-size: 1.1rem;
            border: 2px solid var(--card-bg);
            box-shadow: 0 0 0 2px var(--primary-purple);
            cursor: pointer;
        }

        /* 5. Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
        }
        
        .grid-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        .grid-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Specific Card Placements */
        .card-welcome-banner { grid-column: span 12; }
        .card-stats { grid-column: span 12; }
        .card-welcome-alt { grid-column: span 6; }
        .card-chart-dept { grid-column: span 6; }
        .card-top-faculty-1 { grid-column: span 6; }
        .card-top-faculty-2 { grid-column: span 6; }
        
        /* Responsive Grid */
        @media (max-width: 1200px) {
            .card-welcome-alt { grid-column: span 12; }
            .card-chart-dept { grid-column: span 12; }
        }
        @media (max-width: 992px) {
            .card-top-faculty-1 { grid-column: span 12; }
            .card-top-faculty-2 { grid-column: span 12; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            /* Mobile sidebar styles (hidden by default) would go here */
        }


        /* 6. Card Content Styles */
        
        /* Card: Welcome Banner (Purple) */
        .card-welcome-banner {
            background-color: var(--primary-purple);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem;
            background-image: 
                radial-gradient(circle at 100% 0%, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 25%),
                radial-gradient(circle at 0% 100%, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 20%);
        }
        .welcome-banner-text h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .welcome-banner-text p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1.25rem;
        }
        .welcome-banner-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background-color: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem 0.5rem 0.5rem;
            border-radius: var(--radius-2xl);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .welcome-banner-user img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid var(--text-light);
        }
        .btn-report {
            background-color: var(--card-bg);
            color: var(--primary-purple);
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-report:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        /* Card: Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem; /* tighter gap to look like 4 cut boxes */
        }
        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-sm);
        }
        .stat-item-value {
            font-size: 2.25rem; /* 36px */
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.1;
        }
        .stat-item-label {
            font-size: 0.9rem;
            color: var(--text-body);
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* Card: Welcome Alt (White) */
        .card-welcome-alt h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .card-welcome-alt p {
            font-size: 1rem;
            margin: 0.25rem 0 1.25rem;
        }
        .btn-primary {
            background-color: var(--primary-blue);
            color: var(--text-light);
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: var(--text-blue);
            box-shadow: var(--shadow-md);
        }
        
        /* Card: Chart */
        .card-chart-dept h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        .chart-container {
            height: 250px;
            width: 100%;
        }
        
        /* Card: Top Faculty */
        .card-top-faculty-1 h3,
        .card-top-faculty-2 h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        .faculty-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .faculty-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .faculty-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-bg);
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            color: var(--text-body);
            flex-shrink: 0;
        }
        .faculty-info .name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        .faculty-info .dept {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .faculty-count {
            margin-left: auto;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    
    <div class="admin-layout">
        
        <?php 
            // This includes your new sidebar.php file
            include __DIR__ . '/includes/sidebar.php'; 
        ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Dashboard</h1>
                
                <div class="header-actions">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search...">
                    </div>
                    
                    <button class="header-btn" title="Toggle Theme">
                        <i class="fas fa-sun"></i>
                    </button>
                    <button class="header-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    
                    <div class="user-avatar" title="Admin">
                        AD
                    </div>
                </div>
            </header>
            
            <div class="content-area">
                
                <div class="dashboard-grid">
                    
                    <div class="grid-card card-welcome-banner">
                        <div class="welcome-banner-text">
                            <h2>Welcome back, Admin!</h2>
                            <p>Here is your data summary for <?php echo date('j F Y'); ?>.</p>
                            <a href="view_feedback.php" class="btn-report">
                                <i class="fas fa-file-alt"></i> View Full Report
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid-card card-stats">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-item-value"><?php echo $today_responses; ?></div>
                                <div class="stat-item-label">Today's Responses</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-value"><?php echo $total_responses; ?></div>
                                <div class="stat-item-label">Total Responses</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-value"><?php echo $avg_rating; ?></div>
                                <div class="stat-item-label">Avg. Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-value"><?php echo $active_users; ?></div>
                                <div class="stat-item-label">Active Users</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid-card card-welcome-alt">
                        <h3>Feedback Overview</h3>
                        <p>Review detailed reports, manage forms, and oversee all user activity from one place.</p>
                        <a href="manage_forms.php" class="btn-primary">
                            <i class="fas fa-clipboard-list"></i> Manage Forms
                        </a>
                    </div>
                    
                    <div class="grid-card card-chart-dept">
                        <h3>Department Distribution</h3>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="grid-card card-top-faculty-1">
                        <h3>Top Faculty (by Rating)</h3>
                        <div class="faculty-list">
                            <?php if (!empty($top_faculty_list_1)): ?>
                                <?php foreach ($top_faculty_list_1 as $faculty): ?>
                                    <div class="faculty-item">
                                        <div class="faculty-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div class="faculty-info">
                                            <div class="name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                            <div class="dept"><?php echo htmlspecialchars($faculty['department']); ?></div>
                                        </div>
                                        <div class="faculty-count"><?php echo $faculty['response_count']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No faculty data available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid-card card-top-faculty-2">
                        <h3>Top Faculty (Continued)</h3>
                        <div class="faculty-list">
                             <?php if (!empty($top_faculty_list_2)): ?>
                                <?php foreach ($top_faculty_list_2 as $faculty): ?>
                                    <div class="faculty-item">
                                        <div class="faculty-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div class="faculty-info">
                                            <div class="name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                            <div class="dept"><?php echo htmlspecialchars($faculty['department']); ?></div>
                                        </div>
                                        <div class="faculty-count"><?php echo $faculty['response_count']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No more faculty data.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div> </div> </main> </div> <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Chart.js ---
        // We get the data from PHP and encode it as JSON
        const deptLabels = <?php echo json_encode($dept_labels); ?>;
        const deptData = <?php echo json_encode($dept_data); ?>;

        const ctx = document.getElementById('departmentChart');
        
        if (ctx && deptData.length > 0) {
            // The design shows a mix of bar and doughnut. A doughnut chart is cleaner here.
            new Chart(ctx, {
                type: 'doughnut', // You can change this to 'bar'
                data: {
                    labels: deptLabels,
                    datasets: [{
                        label: 'Responses',
                        data: deptData,
                        backgroundColor: [
                            '#3b82f6', '#6366F1', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6'
                        ],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                boxWidth: 12,
                                font: {
                                    family: 'Inter',
                                    size: 13
                                }
                            }
                        }
                    },
                    cutout: '70%' // Makes it a doughnut
                }
            });
        }

        // You can add your theme toggle logic here
        const themeToggleBtn = document.querySelector('.header-btn[title="Toggle Theme"]');
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                // Example: Toggle dark theme on the sidebar's body tag
                // (Your sidebar.php already checks for body.dark-theme)
                document.body.classList.toggle('dark-theme');
                
                // Update icon
                const icon = themeToggleBtn.querySelector('i');
                if (document.body.classList.contains('dark-theme')) {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                } else {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                }
            });
        }

    });
    </script>

</body>
</html>