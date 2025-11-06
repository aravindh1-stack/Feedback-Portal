 <?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

// Get user data from session
$user = $_SESSION['user'];

// Function to convert numbers to Roman numerals
function toRoman($num) {
    if (!is_numeric($num) || $num <= 0) return 'N/A';
    
    $map = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
        'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
        'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
    ];
    $return = '';
    foreach ($map as $roman => $int) {
        while ($num >= $int) {
            $return .= $roman;
            $num -= $int;
        }
    }
    return $return;
}

// Database connection (assuming you have a database connection)
// Include your database connection file here
// require_once '../includes/db_connect.php';

// Get student statistics from database
// This is a placeholder - replace with actual database queries
$student_stats = [
    'forms_completed' => 0,
    'forms_available' => 0,
    'last_submission' => null,
    'current_cycle' => 'No active cycle'
];

// If you have database connection, uncomment and modify these queries:
/*
try {
    // Get completed forms count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback_submissions WHERE student_id = ?");
    $stmt->execute([$user['id']]);
    $student_stats['forms_completed'] = $stmt->fetchColumn();
    
    // Get available forms count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback_forms f 
                          LEFT JOIN feedback_submissions fs ON f.id = fs.form_id AND fs.student_id = ? 
                          WHERE f.is_active = 1 AND fs.id IS NULL");
    $stmt->execute([$user['id']]);
    $student_stats['forms_available'] = $stmt->fetchColumn();
    
    // Get last submission date
    $stmt = $pdo->prepare("SELECT MAX(submitted_at) FROM feedback_submissions WHERE student_id = ?");
    $stmt->execute([$user['id']]);
    $student_stats['last_submission'] = $stmt->fetchColumn();
    
    // Get current active cycle
    $stmt = $pdo->prepare("SELECT cycle_name FROM feedback_cycles WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $current_cycle = $stmt->fetchColumn();
    $student_stats['current_cycle'] = $current_cycle ?: 'No active cycle';
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
*/

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - College Portal</title>
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header */
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

        .mobile-menu-button:hover {
            background-color: var(--gray-100);
            color: var(--gray-900);
        }

        /* Main Container */
        .main-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 400;
        }

        /* Cards */
        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Student Profile Card */
        .student-profile-card {
            margin-bottom: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 4rem;
            height: 4rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .profile-role {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .profile-details {
            padding: 2rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--gray-200);
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .detail-value.highlight {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.8rem;
            display: inline-block;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.15s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--gray-100);
            color: var(--primary-color);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
            margin-top: 0.25rem;
        }

        .stat-change {
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.neutral {
            color: var(--gray-500);
        }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .action-icon {
            width: 3rem;
            height: 3rem;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 1rem;
        }

        .action-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .action-description {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            min-height: 2.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .status-badge.success {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        .status-badge.warning {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }

        .status-badge.info {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        /* Recent Activity */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 0.5rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-100);
        }

        .activity-icon {
            width: 2rem;
            height: 2rem;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .activity-description {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Footer */
        .footer {
            background: var(--white);
            border-top: 1px solid var(--gray-200);
            padding: 2rem 0;
            text-align: center;
            color: var(--gray-600);
            margin-top: 4rem;
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Mobile Responsive */
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

            .main-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .profile-header {
                padding: 1.5rem;
            }

            .profile-details {
                padding: 1.5rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .quick-actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .action-card {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        .loading-pulse {
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Focus States */
        .btn:focus,
        .nav-link:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span>Student Portal</span>
            </a>
            
            <button class="mobile-menu-button" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                </a>
                <a href="../includes/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <main class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?= htmlspecialchars($user['name'] ?? 'Student') ?>! Here's an overview of your academic feedback activities.</p>
        </div>

        <!-- Student Profile Card -->
        <div class="card student-profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($user['name'] ?? 'Student Name') ?></h2>
                <p class="profile-role">Student Profile</p>
                <div class="status-badge <?= $student_stats['forms_available'] > 0 ? 'warning' : 'success' ?>">
                    <i class="fas fa-<?= $student_stats['forms_available'] > 0 ? 'clock' : 'check-circle' ?>"></i>
                    <span><?= $student_stats['forms_available'] > 0 ? 'Pending Feedback' : 'All Forms Complete' ?></span>
                </div>
            </div>
            <div class="profile-details">
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-value"><?= htmlspecialchars($user['name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">SIN Number</div>
                        <div class="detail-value highlight"><?= htmlspecialchars($user['sin_number'] ?? $user['username'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Department</div>
                        <div class="detail-value"><?= htmlspecialchars($user['department'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Academic Year</div>
                        <div class="detail-value">
                            <?php 
                            if (isset($user['year']) && is_numeric($user['year'])) {
                                echo toRoman((int)$user['year']) . ' Year';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Current Semester</div>
                        <div class="detail-value">
                            <?php 
                            if (isset($user['semester']) && is_numeric($user['semester'])) {
                                echo toRoman((int)$user['semester']) . ' Semester';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Account Status</div>
                        <div class="detail-value" style="color: var(--success-color); font-weight: 600;">
                            <i class="fas fa-check-circle"></i> Active
                        </div>
                    </div>
                </div>
            </div>
        </div>

       

        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h3 class="action-title">Submit Feedback</h3>
                <p class="action-description">Provide feedback for your courses and faculty members to help improve the academic experience.</p>
                <a href="feedback.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    <span>Start Feedback</span>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon" style="background: var(--success-color);">
                    <i class="fas fa-history"></i>
                </div>
                <h3 class="action-title">View History</h3>
                <p class="action-description">Review your previous feedback submissions and track your participation in feedback cycles.</p>
                    <i class="fas fa-eye"></i>
                    <span>Feedback history access is admin-controlled. Contact admin for details.</span>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon" style="background: var(--warning-color);">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3 class="action-title">Update Profile</h3>
                <p class="action-description">Keep your profile information up to date to ensure accurate communication and records.</p>
                    <i class="fas fa-cog"></i>
                    <span>Profile updates via admin only. Please contact support.</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-activity"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="card-content">
                <div class="activity-list">
                    <?php if ($student_stats['last_submission']): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Last Feedback Submitted</div>
                            <div class="activity-description">
                                Feedback form completed successfully
                            </div>
                            <div class="activity-time">
                                <?php 
                                $date = new DateTime($student_stats['last_submission']);
                                echo $date->format('F j, Y \a\t g:i A'); 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($student_stats['forms_available'] > 0): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: var(--warning-color);">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?= $student_stats['forms_available'] ?> Feedback Form<?= $student_stats['forms_available'] > 1 ? 's' : '' ?> Available</div>
                            <div class="activity-description">
                                Please complete your pending feedback submissions
                            </div>
                            <div class="activity-time">Pending</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="activity-item">
                        <div class="activity-icon" style="background: var(--success-color);">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Current Feedback Cycle</div>
                            <div class="activity-description">
                                <?= htmlspecialchars($student_stats['current_cycle']) ?>
                            </div>
                            <div class="activity-time">Active cycle</div>
                        </div>
                    </div>
                    
                    <?php if ($student_stats['forms_completed'] > 0): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: var(--primary-color);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Feedback Progress</div>
                            <div class="activity-description">
                                You have completed <?= $student_stats['forms_completed'] ?> feedback form<?= $student_stats['forms_completed'] > 1 ? 's' : '' ?> this semester
                            </div>
                            <div class="activity-time">Overall progress</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($student_stats['last_submission']) && $student_stats['forms_completed'] == 0 && $student_stats['forms_available'] == 0): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: var(--gray-400);">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Welcome to the Feedback Portal</div>
                            <div class="activity-description">
                                No recent activity found. Check back when feedback cycles become available.
                            </div>
                            <div class="activity-time">Getting started</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 College Feedback Portal. All rights reserved.</p>
            <p style="font-size: 0.875rem; margin-top: 0.5rem; opacity: 0.8;">
                Enhancing education through continuous feedback and improvement
            </p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('mobile-open');
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Loading state for buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.href.includes('javascript:')) {
                    const icon = this.querySelector('i');
                    const text = this.querySelector('span');
                    
                    if (icon && text) {
                        const originalIcon = icon.className;
                        const originalText = text.textContent;
                        
                        icon.className = 'fas fa-spinner fa-spin';
                        text.textContent = 'Loading...';
                        this.style.pointerEvents = 'none';
                        
                        // Reset after timeout (in case navigation fails)
                        setTimeout(() => {
                            icon.className = originalIcon;
                            text.textContent = originalText;
                            this.style.pointerEvents = 'auto';
                        }, 3000);
                    }
                }
            });
        });

        // Dynamic stats update simulation
        function updateStats() {
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                stat.classList.add('loading-pulse');
                setTimeout(() => {
                    stat.classList.remove('loading-pulse');
                }, 1000);
            });
        }

        // Responsive navigation
        window.addEventListener('resize', function() {
            const navMenu = document.getElementById('navMenu');
            if (window.innerWidth > 768) {
                navMenu.classList.remove('mobile-open');
            }
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            const navMenu = document.getElementById('navMenu');
            const mobileButton = document.querySelector('.mobile-menu-button');
            
            if (!navMenu.contains(e.target) && !mobileButton.contains(e.target)) {
                navMenu.classList.remove('mobile-open');
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.card, .stat-card, .action-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Display user info in console for debugging (remove in production)
            console.log('Current user session:', <?= json_encode($user) ?>);
            console.log('Student stats:', <?= json_encode($student_stats) ?>);
        });

        // Refresh page data periodically (optional)
        function refreshDashboard() {
            // Add AJAX call to refresh data without page reload
            // This would require a separate PHP endpoint
            console.log('Dashboard data refresh triggered at:', new Date().toLocaleTimeString());
        }

        // Auto-refresh every 5 minutes (optional - uncomment if needed)
        // setInterval(refreshDashboard, 300000);
    </script>
</body>
</html