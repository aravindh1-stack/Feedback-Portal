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
    // Today's responses count (using current date if no created_at column)
    $today = date('Y-m-d');
    $today_responses_query = "SELECT COUNT(*) as count FROM feedback_responses";
    $today_responses_result = $conn->query($today_responses_query);
    $today_responses = $today_responses_result ? $today_responses_result->fetch_assoc()['count'] : 0;

    // Total responses count
    $total_responses_query = "SELECT COUNT(*) as count FROM feedback_responses";
    $total_responses_result = $conn->query($total_responses_query);
    $total_responses = $total_responses_result ? $total_responses_result->fetch_assoc()['count'] : 0;

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

    // Department distribution for chart
    $dept_distribution_query = "SELECT f.department, COUNT(fr.id) as response_count 
                               FROM feedback_responses fr 
                               JOIN feedback_forms f ON fr.form_id = f.id 
                               GROUP BY f.department 
                               ORDER BY response_count DESC";
    $dept_distribution_result = $conn->query($dept_distribution_query);
    $dept_data = [];
    $dept_labels = [];
    while ($dept_distribution_result && $row = $dept_distribution_result->fetch_assoc()) {
        $dept_labels[] = $row['department'];
        $dept_data[] = $row['response_count'];
    }

    // Top performing faculty based on ratings
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

    // Monthly feedback trends (simplified for now - showing current data)
    $monthly_labels = ['Current Period'];
    $monthly_data = [$total_responses];

} catch (Exception $e) {
    // Fallback to default values if database queries fail
    $today_responses = 0;
    $total_responses = 0;
    $avg_rating = 0;
    $active_users = 0;
    $dept_data = [];
    $dept_labels = [];
    $top_faculty = [];
    $monthly_labels = [];
    $monthly_data = [];
}
?>
<?php
    $page_title = 'Admin Dashboard - College Feedback System';
    $extra_head = <<<HTML
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">
    
    <!-- External Libraries -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    
    
    <style>
        /* === TAILWIND-INSPIRED PROFESSIONAL THEME === */
        :root {
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-500: #3b82f6; 
            --primary-600: #2563eb;

            --success-100: #dcfce7;
            --success-500: #16a34a;
            --warning-100: #fef3c7;
            --warning-500: #d97706;
            --danger-100: #fee2e2;
            --danger-500: #dc2626;
            --danger-600: #b91c1c;

            --pink-100: #fce7f3;
            --pink-500: #db2777;
            --purple-100: #f3e8ff;
            --purple-500: #8b5cf6;

            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --gray-900: #111827;

            --body-bg: var(--gray-50);
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --header-bg: rgba(255, 255, 255, 0.85);
            --border-color: var(--gray-200);
            --text-color: var(--gray-600);
            --heading-color: var(--gray-900);

            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.06);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
            --shadow-lg: 0 20px 40px rgba(0,0,0,0.12);
            
            --radius-md: 0.5rem;
            --radius-lg: 0.875rem;
            --radius-xl: 1.125rem;
            --radius-2xl: 1.5rem;
            
            --spacing-2: 0.5rem;
            --spacing-3: 0.75rem;
            --spacing-4: 1rem;
            --spacing-5: 1.25rem;
            --spacing-6: 1.5rem;
            --spacing-8: 2rem;
            
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;

            --transition-fast: all 0.2s ease-in-out;
            --transition-normal: all 0.3s ease-in-out;

            --sidebar-width: 280px;
            --header-height: 80px;
        }
        
        /* Dark Theme Variables */
        body.dark-theme {
            --body-bg: #111827;
            --sidebar-bg: var(--gray-900);
            --card-bg: var(--gray-900);
            --header-bg: rgba(17, 24, 39, 0.85);
            --border-color: var(--gray-700);
            --text-color: var(--gray-400);
            --heading-color: var(--gray-100);
        }

        /* Base & Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--body-bg);
            color: var(--text-color);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            transition: background-color var(--transition-normal);
        }

        /* Layout */
        .admin-layout { display: flex; }
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .content { 
            flex: 1; 
            padding: 4rem var(--spacing-8);
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: var(--transition-normal);
        }
        .sidebar-header {
            height: var(--header-height);
            padding: 0 var(--spacing-6);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--heading-color);
        }
        .sidebar-logo i {
            width: 40px; height: 40px;
            background: var(--primary-500);
            color: white;
            border-radius: var(--radius-lg);
            display: grid;
            place-items: center;
            font-size: 1.2rem;
        }
        .sidebar-subtitle { font-size: var(--font-size-sm); color: var(--gray-500); font-weight: 500; }
        .sidebar-nav { padding: var(--spacing-4) 0; flex-grow: 1; }
        .nav-section-title {
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 var(--spacing-6) var(--spacing-3);
            color: var(--gray-400);
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            padding: var(--spacing-3) var(--spacing-6);
            color: var(--text-color);
            text-decoration: none;
            margin: var(--spacing-2) var(--spacing-4);
            border-radius: var(--radius-lg);
            border: 1px solid transparent;
            font-weight: 500;
            transition: var(--transition-fast);
            animation: slideInLeft 0.4s ease-out forwards;
            opacity: 0;
        }
        .nav-item:hover { background: var(--primary-50); color: var(--primary-600); border-color: rgba(59,130,246,0.15); }
        body.dark-theme .nav-item:hover { background: rgba(59, 130, 246, 0.1); }
        .nav-item.active {
            background-color: var(--primary-50);
            color: var(--primary-600);
            font-weight: 700;
            position: relative;
            border-color: rgba(59,130,246,0.25);
        }
        body.dark-theme .nav-item.active { background: rgba(59, 130, 246, 0.1); }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: var(--primary-500);
            border-radius: 0 4px 4px 0;
        }
        .nav-item.danger:hover { background-color: var(--danger-100); color: var(--danger-600); }
        body.dark-theme .nav-item.danger:hover { background: rgba(220, 38, 38, 0.1); }
        .nav-item i { width: 20px; text-align: center; }

        /* Header */
        .header {
            height: var(--header-height);
            background: var(--header-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--spacing-8);
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: var(--shadow-sm);
        }
        .page-title { font-size: var(--font-size-2xl); font-weight: 700; color: var(--heading-color); }
        .breadcrumb { font-size: var(--font-size-sm); color: var(--gray-500); }
        .header-right { display: flex; align-items: center; gap: var(--spacing-4); }
        .header-search { position: relative; width: 320px; }
        .search-input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            background: var(--card-bg);
            color: var(--text-color);
            transition: var(--transition-fast);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .search-icon { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); }
        .header-actions { display: flex; align-items: center; gap: var(--spacing-3); }
        .header-btn {
            width: 44px; height: 44px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: var(--transition-fast);
            position: relative;
            color: var(--text-color);
            font-size: 1.1rem;
            box-shadow: var(--shadow-sm);
        }
        .header-btn:hover { border-color: var(--primary-500); color: var(--primary-500); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .notification-badge {
            position: absolute;
            top: -5px; right: -5px;
            background: var(--danger-500);
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            border: 2px solid var(--card-bg);
        }
        .user-avatar {
            width: 44px; height: 44px;
            background: var(--primary-500);
            border-radius: var(--radius-lg);
            display: grid;
            place-items: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        .user-avatar:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }

        /* Generic Card */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition-normal);
            overflow: hidden;
        }
        .card:hover { box-shadow: var(--shadow-lg); transform: translateY(-6px); }
        .card-header { padding: var(--spacing-6) var(--spacing-6) var(--spacing-4); border-bottom: 1px solid var(--border-color); }
        .card-title { font-size: var(--font-size-lg); font-weight: 700; color: var(--heading-color); margin-bottom: 2px; }
        .card-subtitle { font-size: var(--font-size-sm); color: var(--gray-500); }
        .card-body { padding: var(--spacing-6); }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafb 100%);
            padding: calc(var(--spacing-8) + 0.25rem);
            border-radius: var(--radius-2xl);
            margin-bottom: var(--spacing-8);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }
        .welcome-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: calc(var(--spacing-8) - 0.25rem);
            gap: var(--spacing-6);
        }
        .welcome-title { font-size: var(--font-size-3xl); font-weight: 800; color: var(--heading-color); letter-spacing: -0.01em; }
        .welcome-subtitle { font-size: var(--font-size-lg); color: var(--text-color); max-width: 560px; }
        .welcome-illustration {
            max-width: 200px;
            flex-shrink: 0;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }
        .btn-primary { background: var(--primary-500); color: white; }
        .btn-primary:hover { background: var(--primary-600); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
        .btn-danger { background: var(--danger-500); color: white; box-shadow: var(--shadow-sm); }
        .btn-danger:hover { background: var(--danger-600); transform: translateY(-2px); box-shadow: var(--shadow-md); }

        .welcome-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--spacing-6); }
        .welcome-stat {
            background: var(--body-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: var(--spacing-5);
            display: flex;
            align-items: center;
            gap: var(--spacing-5);
            transition: var(--transition-normal);
        }
        .welcome-stat:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary-500); }
        .welcome-stat-icon {
            width: 48px; height: 48px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .welcome-stat:nth-child(1) .welcome-stat-icon { background: var(--primary-100); color: var(--primary-500); }
        .welcome-stat:nth-child(2) .welcome-stat-icon { background: #e0f2fe; color: #0284c7; }
        .welcome-stat:nth-child(3) .welcome-stat-icon { background: var(--warning-100); color: var(--warning-500); }
        .welcome-stat:nth-child(4) .welcome-stat-icon { background: var(--success-100); color: var(--success-500); }
        .welcome-stat-value { font-size: var(--font-size-2xl); font-weight: 700; color: var(--heading-color); line-height: 1.2; }
        .welcome-stat-label { font-size: var(--font-size-sm); color: var(--text-color); }
        
        /* 8 QUICK ACTIONS CARDS */
        .section-title {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--heading-color);
            margin-bottom: var(--spacing-6);
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--spacing-6);
            margin-bottom: var(--spacing-8);
        }
        .action-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            text-align: center;
            transition: var(--transition-normal);
        }
        .action-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            margin: 0 auto var(--spacing-4);
            font-size: 1.5rem;
            transition: transform 0.3s ease; /* Added for hover effect */
        }
        .action-card:hover .action-icon {
            transform: scale(1.06) rotate(-4deg); /* Added for hover effect */
        }
        .action-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--heading-color);
            margin-bottom: var(--spacing-3);
        }
        .action-description {
            font-size: var(--font-size-sm);
            color: var(--text-color);
            margin-bottom: var(--spacing-6);
            min-height: 50px;
        }
        /* Icon Colors */
        .action-card:nth-child(1) .action-icon { background-color: var(--primary-100); color: var(--primary-500); }
        .action-card:nth-child(2) .action-icon { background-color: var(--success-100); color: var(--success-500); }
        .action-card:nth-child(3) .action-icon { background-color: var(--warning-100); color: var(--warning-500); }
        .action-card:nth-child(4) .action-icon { background-color: #E0F2FE; color: #0284C7; }
        .action-card:nth-child(5) .action-icon { background-color: var(--pink-100); color: var(--pink-500); }
        .action-card:nth-child(6) .action-icon { background-color: var(--purple-100); color: var(--purple-500); }
        .action-card:nth-child(7) .action-icon { background-color: var(--purple-100); color: var(--purple-500); }
        .action-card:nth-child(8) .action-icon { background-color: var(--danger-100); color: var(--danger-500); }
        
        /* Dashboard Grid */
        .dashboard-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: var(--spacing-6); margin-bottom: var(--spacing-8); }
        .dashboard-grid > .card:nth-child(1), .dashboard-grid > .card:nth-child(2) { grid-column: span 6; }
        .dashboard-grid > .card:nth-child(3) { grid-column: span 8; }
        .dashboard-grid > .card:nth-child(4) { grid-column: span 4; }
        
        /* Charts & Lists */
        .chart-container { position: relative; height: 320px; }
        .scrollable-list { max-height: 360px; overflow-y: auto; padding-right: 10px; }
        .scrollable-list::-webkit-scrollbar { width: 6px; }
        .scrollable-list::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 3px; }
        body.dark-theme .scrollable-list::-webkit-scrollbar-track { background: var(--gray-800); }
        .scrollable-list::-webkit-scrollbar-thumb { background: var(--gray-400); border-radius: 3px; }
        .activity-item, .faculty-item { display: flex; align-items: center; gap: var(--spacing-4); padding: var(--spacing-4) 0; border-bottom: 1px solid var(--border-color); }
        .activity-item:last-child, .faculty-item:last-child { border-bottom: none; }
        .activity-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-lg);
            display: grid; place-items: center;
            color: white; flex-shrink: 0;
            background: var(--primary-500);
        }
        .activity-icon.success { background: var(--success-500); }
        .activity-icon.warning { background: var(--warning-500); }
        .activity-description { font-weight: 500; color: var(--heading-color); margin-bottom: 2px; line-height: 1.4; }
        .activity-meta { font-size: var(--font-size-sm); color: var(--gray-500); }
        .faculty-name { font-weight: 600; color: var(--heading-color); }
        .faculty-department { font-size: var(--font-size-sm); color: var(--gray-500); }
        .faculty-rating { display: flex; align-items: center; gap: var(--spacing-2); font-weight: 600; margin-left: auto; }
        .rating-stars { color: var(--warning-500); }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .welcome-section, .actions-section, .dashboard-grid {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .actions-section { animation-delay: 0.1s; }
        .dashboard-grid { animation-delay: 0.2s; }
        

        /* Responsive Design */
        @media (max-width: 1200px) { 
            .actions-grid { grid-template-columns: repeat(3, 1fr); }
            .dashboard-grid > .card { grid-column: span 12 !important; } 
        }
        @media (max-width: 992px) {
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
            .welcome-banner { flex-direction: column; align-items: flex-start; text-align: center; }
            .welcome-illustration { margin: 1rem auto 0; max-width: 160px; }
            .welcome-text { width: 100%; }
        }
        @media (max-width: 768px) {
            .actions-grid { grid-template-columns: 1fr; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .header, .content { padding-left: var(--spacing-4); padding-right: var(--spacing-4); }
            .header-search, .breadcrumb { display: none; }
            .sidebar-toggle { display: grid !important; }
        }
    </style>
    HTML;
    include __DIR__ . '/includes/header.php';
?>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php $page_heading='Dashboard'; $show_theme_toggle=true; $show_search=true; include __DIR__ . '/includes/topbar.php'; ?>

            <!-- Content -->
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-banner">
                        <div class="welcome-text">
                            <h2 class="welcome-title">Welcome back, Admin!</h2>
                            <p class="welcome-subtitle">Here is your data summary for <?php echo date('l, j F Y'); ?>.</p>
                        </div>
                        <div class="welcome-illustration">
                            <svg width="100%" viewBox="0 0 222 153" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="34.1802" y="0.890625" width="187.525" height="113.313" rx="12" fill="#EBF4FF"/>
                                <rect x="42.3052" y="8.84375" width="171.275" height="12.9375" rx="4" fill="#CBD5E1"/>
                                <rect x="42.3052" y="30.0938" width="81.5625" height="8.625" rx="4" fill="#A0AEC0"/>
                                <rect x="162.868" y="30.0938" width="50.7125" height="8.625" rx="4" fill="#CBD5E1"/>
                                <rect x="0.5" y="47.5" width="133.625" height="104.625" rx="12" fill="white" stroke="#E2E8F0"/>
                                <rect x="8.625" y="55.4531" width="117.375" height="12.9375" rx="4" fill="#E2E8F0"/>
                                <rect x="8.625" y="76.7031" width="75.8125" height="8.625" rx="4" fill="#CBD5E1"/>
                                <rect x="42.3052" y="52.1562" width="171.275" height="54.2812" rx="8" fill="white" stroke="#E2E8F0"/>
                                <path d="M57.6979 92.549C62.062 85.8344 70.2981 77.3484 78.4716 80.9839C86.6451 84.6193 91.0663 94.6231 99.5516 96.9363C108.037 99.2495 116.522 92.549 124.965 91.4924C133.408 90.4358 141.741 94.8118 150.074 92.549C158.407 90.2862 165.755 81.5034 174.457 82.6527C183.159 83.802 188.468 91.8794 196.545 94.8118" stroke="#5A67D8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="124.965" cy="91.5625" r="5.75" fill="#4C51BF" stroke="white" stroke-width="2"/>
                            </svg>
                        </div>
                    </div>
                    <div class="welcome-stats">
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon"><i class="fas fa-inbox"></i></div>
                            <div class="welcome-stat-info">
                                <div class="welcome-stat-value" data-count="<?php echo $today_responses; ?>"><?php echo $today_responses; ?></div>
                                <div class="welcome-stat-label">Today's Responses</div>
                            </div>
                        </div>
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon"><i class="fas fa-poll"></i></div>
                            <div class="welcome-stat-info">
                                <div class="welcome-stat-value" data-count="<?php echo $total_responses; ?>"><?php echo $total_responses; ?></div>
                                <div class="welcome-stat-label">Total Responses</div>
                            </div>
                        </div>
                        <div class="welcome-stat">
                            <div class="welcome-stat-icon"><i class="fas fa-star-half-alt"></i></div>
                            <div class="welcome-stat-info">
                                <div class="welcome-stat-value"><?php echo $avg_rating > 0 ? $avg_rating . '/5.0' : 'No ratings yet'; ?></div>
                                <div class="welcome-stat-label">Overall Rating</div>
                            </div>
                        </div>
                        <div class="welcome-stat">
                             <div class="welcome-stat-icon"><i class="fas fa-users"></i></div>
                            <div class="welcome-stat-info">
                                <div class="welcome-stat-value" data-count="<?php echo $active_users; ?>"><?php echo $active_users; ?></div>
                                <div class="welcome-stat-label">Active Users</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8 Quick Actions -->
                <div class="actions-section">
                    <h2 class="section-title">Quick Actions</h2>
                    <div class="actions-grid">
                        <div class="action-card" style="--i: 1;">
                            <div class="action-icon"><i class="fas fa-users-cog"></i></div>
                            <h3 class="action-title">Manage Users</h3>
                            <p class="action-description">Add, edit, or remove students and faculty members.</p>
                            <a href="manage_users.php" class="btn btn-primary btn-sm">Manage Users</a>
                        </div>
                        <div class="action-card" style="--i: 2;">
                            <div class="action-icon"><i class="fas fa-file-plus"></i></div>
                            <h3 class="action-title">Create Feedback Form</h3>
                            <p class="action-description">Design new feedback forms for various departments.</p>
                            <a href="create_feedback_form.php" class="btn btn-primary btn-sm">Create Form</a>
                        </div>
                        <div class="action-card" style="--i: 3;">
                            <div class="action-icon"><i class="fas fa-tasks"></i></div>
                            <h3 class="action-title">Manage Forms</h3>
                            <p class="action-description">Activate, deactivate, or edit existing feedback forms.</p>
                            <a href="manage_forms.php" class="btn btn-primary btn-sm">Manage Forms</a>
                        </div>
                        <div class="action-card" style="--i: 4;">
                            <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                            <h3 class="action-title">View Feedback</h3>
                            <p class="action-description">Review submissions and generate insightful reports.</p>
                            <a href="view_feedback.php" class="btn btn-primary btn-sm">View Reports</a>
                        </div>
                        <div class="action-card" style="--i: 5;">
                            <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
                            <h3 class="action-title">Student Feedback</h3>
                            <p class="action-description">Access student-specific data and participation tracking.</p>
                            <a href="student_feedback_list.php" class="btn btn-primary btn-sm">Student Data</a>
                        </div>
                        <div class="action-card" style="--i: 6;">
                            <div class="action-icon"><i class="fas fa-user-tie"></i></div>
                            <h3 class="action-title">Chief Guest</h3>
                            <p class="action-description">Manage event-based feedback forms for chief guests.</p>
                            <a href="chief_guest_feedback.php" class="btn btn-primary btn-sm">Manage Guest</a>
                        </div>
                         <div class="action-card" style="--i: 7;">
                            <div class="action-icon"><i class="fas fa-file-invoice"></i></div>
                            <h3 class="action-title">Guest Reports</h3>
                            <p class="action-description">Download and analyze feedback from chief guests.</p>
                            <a href="chief_guest_feedback_report.php" class="btn btn-primary btn-sm">View Reports</a>
                        </div>
                        <div class="action-card" style="--i: 8;">
                            <div class="action-icon"><i class="fas fa-sign-out-alt"></i></div>
                            <h3 class="action-title">System Logout</h3>
                            <p class="action-description">Safely log out of the admin portal to end your session.</p>
                            <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
                        </div>
                    </div>
                </div>

                
            </div>
            <?php include __DIR__ . '/includes/footer.php'; ?>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- THEME & UI ---
        window.toggleSidebar = () => document.querySelector('.sidebar').classList.toggle('active');

        // --- DYNAMIC CONTENT & ANIMATIONS ---
        function animateCounters() {
            document.querySelectorAll('[data-count]').forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                let current = 0;
                const step = (target / 1500) * 16;
                const update = () => {
                    if (current < target) {
                        current = Math.min(current + step, target);
                        counter.textContent = Math.ceil(current).toLocaleString();
                        requestAnimationFrame(update);
                    }
                };
                update();
            });
        }
        setTimeout(animateCounters, 300);

        

        // Sidebar animation staggering
        document.querySelectorAll('.sidebar-nav .nav-item').forEach((item, index) => {
            item.style.animationDelay = `${0.2 + index * 0.05}s`;
        });
    });
    </script>
</body>
</html>