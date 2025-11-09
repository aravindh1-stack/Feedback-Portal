<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

// === HELPER FUNCTIONS (ITHU THEVAI) ===
function getGradeDetails(float $percentage): array
{
    if ($percentage >= 91) return ['grade' => 'A+', 'status' => 'Outstanding', 'desc' => '91-100% - Exceptional teaching performance'];
    if ($percentage >= 81) return ['grade' => 'A', 'status' => 'Excellent', 'desc' => '81-90% - Very good teaching effectiveness'];
    if ($percentage >= 71) return ['grade' => 'B+', 'status' => 'Very Good', 'desc' => '71-80% - Good teaching performance'];
    if ($percentage >= 61) return ['grade' => 'B', 'status' => 'Good', 'desc' => '61-70% - Satisfactory teaching methods'];
    if ($percentage >= 51) return ['grade' => 'C', 'status' => 'Average', 'desc' => '51-60% - Needs some improvement'];
    return ['grade' => 'D', 'status' => 'Below Average', 'desc' => 'Below 51% - Requires significant improvement'];
}

function toRoman(string $number): string
{
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI', '7' => 'VII', '8' => 'VIII'];
    return $map[$number] ?? $number; // Return the original number if not found in the map
}

// Get URL parameters
$form_id = $_GET["form_id"] ?? '';
$department = $_GET["department"] ?? '';
$year = $_GET["year"] ?? '';
$semester = $_GET["semester"] ?? '';
$form_number_param = $_GET['form_number'] ?? '';

// Default values
$stats = ['class_strength' => 0, 'forms_submitted' => 0, 'avg_rating' => 0, 'total_responses' => 0];
$grade_distribution = [];
$subject_details = [];
$faculty_list = []; // Puthu list (New list)

// --- PUTHU LOGIC: Ellathayum fetch panna oru form_number venum ---
$form_numbers = [];
$param_types = '';
$param_values = [];
$where = [];

if (!empty($department) && !empty($year) && !empty($semester)) {
    // Get all form_numbers for this criteria
    $fn_query = $conn->prepare("SELECT DISTINCT form_number FROM feedback_forms WHERE department = ? AND year = ? AND semester = ?");
    $fn_query->bind_param("sss", $department, $year, $semester);
    $fn_query->execute();
    $fn_result = $fn_query->get_result();
    while($fn_row = $fn_result->fetch_assoc()) {
        $form_numbers[] = $fn_row['form_number'];
    }
    
    if(!empty($form_numbers)) {
        $placeholders = implode(',', array_fill(0, count($form_numbers), '?'));
        $where[] = "fr.form_number IN ($placeholders)";
        $param_types = str_repeat('s', count($form_numbers));
        $param_values = $form_numbers;
    }
    
} else if (!empty($form_id)) {
    // Get form_number from form_id
    $fn_query = $conn->prepare("SELECT form_number, department, year, semester FROM feedback_forms WHERE id = ? LIMIT 1");
    $fn_query->bind_param("i", $form_id);
    $fn_query->execute();
    $fn_result = $fn_query->get_result()->fetch_assoc();
    
    if ($fn_result) {
        $form_numbers = [$fn_result['form_number']]; // Use array for consistency
        $department = $fn_result['department'];
        $year = $fn_result['year'];
        $semester = $fn_result['semester'];
        
        $where[] = "fr.form_number = ?";
        $param_types = 's';
        $param_values = [$fn_result['form_number']];
    }
} else if (!empty($form_number_param)) {
    // Direct form_number provided in query string
    $form_numbers = [$form_number_param];
    $where[] = "fr.form_number = ?";
    $param_types = 's';
    $param_values = [$form_number_param];

    // Resolve department/year/semester for header/PDF
    $meta_stmt = $conn->prepare("SELECT department, year, semester FROM feedback_forms WHERE form_number = ? LIMIT 1");
    $meta_stmt->bind_param('s', $form_number_param);
    $meta_stmt->execute();
    if ($meta = $meta_stmt->get_result()->fetch_assoc()) {
        $department = $department ?: ($meta['department'] ?? '');
        $year = $year ?: ($meta['year'] ?? '');
        $semester = $semester ?: ($meta['semester'] ?? '');
    }
}

if (!empty($param_values)) {
    try {
        // --- STUDENT WHERE CLAUSE (Corrected Logic) ---
        $student_where = [];
        $student_params = [];
        $student_types = '';
        if (!empty($department)) {
            $student_where[] = "s.department = ?";
            $student_params[] = $department;
            $student_types .= 's';
        }
        if (!empty($year)) {
            $student_where[] = "s.year = ?";
            $student_params[] = $year;
            $student_types .= 's';
        }
        if (!empty($semester)) {
            $student_where[] = "s.semester = ?";
            $student_params[] = $semester;
            $student_types .= 's';
        }
        $student_where_clause = !empty($student_where) ? ("WHERE " . implode(' AND ', $student_where)) : '';

        // --- WHERE CLAUSE for responses ---
        $placeholders = implode(',', array_fill(0, count($param_values), '?'));
        $where_clause = "WHERE fr.form_number IN ($placeholders)";
        
        // 1. Get Class Strength
        $stats_query = "SELECT COUNT(DISTINCT s.id) as class_strength FROM students s $student_where_clause";
        $stmt = $conn->prepare($stats_query);
        if (!empty($student_params)) {
            $stmt->bind_param($student_types, ...$student_params);
        }
        $stmt->execute();
        $class_strength = $stmt->get_result()->fetch_assoc()['class_strength'] ?? 0;

        // 2. Get Total Students Responded & Avg Rating
        $stats_query = "SELECT 
                            COALESCE(AVG(fr.rating), 0) as avg_rating,
                            COUNT(DISTINCT fr.student_id) as total_students_responded
                          FROM feedback_responses fr
                          $where_clause";
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
        $stats_result = $stmt->get_result()->fetch_assoc();
        
        $stats = [
            'class_strength' => $class_strength,
            'forms_submitted' => count($form_numbers),
            'avg_rating' => $stats_result['avg_rating'] ?? 0,
            'total_responses' => $stats_result['total_students_responded'] ?? 0
        ];

        // 3. Get Grade Distribution
        // ... (This logic is correct, no change needed)
        $grade_distribution = [
            ['grade' => 'A+', 'count' => 0, 'percentage' => 0, 'desc' => '5-Star (Excellent)'],
            ['grade' => 'A', 'count' => 0, 'percentage' => 0, 'desc' => '4-Star (Good)'],
            ['grade' => 'B+', 'count' => 0, 'percentage' => 0, 'desc' => '3-Star (Average)'],
            ['grade' => 'B', 'count' => 0, 'percentage' => 0, 'desc' => '2-Star (Fair)'],
            ['grade' => 'C', 'count' => 0, 'percentage' => 0, 'desc' => '1-Star (Poor)'],
        ];
        $rating_query = "SELECT fr.rating, COUNT(fr.id) as count
                         FROM feedback_responses fr
                         $where_clause
                         GROUP BY fr.rating";
        $stmt = $conn->prepare($rating_query);
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
        $rating_result = $stmt->get_result();
        $total_ratings = 0;
        $rating_counts = [];
        while ($row = $rating_result->fetch_assoc()) {
            $rating = $row['rating']; $count = (int)$row['count'];
            $rating_counts[$rating] = $count; $total_ratings += $count;
        }
        if ($total_ratings > 0) {
            $grade_distribution[0]['count'] = $rating_counts[5] ?? 0;
            $grade_distribution[1]['count'] = $rating_counts[4] ?? 0;
            $grade_distribution[2]['count'] = $rating_counts[3] ?? 0;
            $grade_distribution[3]['count'] = $rating_counts[2] ?? 0;
            $grade_distribution[4]['count'] = $rating_counts[1] ?? 0;
            foreach ($grade_distribution as &$grade) {
                $grade['percentage'] = round(($grade['count'] / $total_ratings) * 100, 1);
            }
        }

        // 4. Get Subject-wise details
        $subject_query = "SELECT 
                            f.subject_code,
                            f.faculty_id, -- PUTHUSA ADD PANNIRUKKEN
                            COALESCE(fac.name, 'Not Assigned') as faculty_name,
                            COALESCE(AVG(fr.rating), 0) as avg_rating,
                            COUNT(DISTINCT fr.student_id) as response_count,
                            CASE 
                                WHEN COALESCE(AVG(fr.rating), 0) >= 4.5 THEN 'A+'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 4.0 THEN 'A'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 3.5 THEN 'B+'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 3.0 THEN 'B'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 2.5 THEN 'C'
                                ELSE 'D'
                            END as grade,
                            CASE 
                                WHEN COALESCE(AVG(fr.rating), 0) >= 4.5 THEN 'Outstanding'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 4.0 THEN 'Very Good'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 3.5 THEN 'Good'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 3.0 THEN 'Satisfactory'
                                WHEN COALESCE(AVG(fr.rating), 0) >= 2.5 THEN 'Needs Improvement'
                                ELSE 'Poor'
                            END as performance_status
                          FROM feedback_forms f
                          LEFT JOIN feedback_responses fr ON f.form_number = fr.form_number AND f.subject_code = fr.subject_code AND f.faculty_id = fr.faculty_id
                          LEFT JOIN faculty fac ON f.faculty_id = fac.id
                          WHERE f.form_number IN ($placeholders)
                          GROUP BY f.subject_code, fac.name, f.faculty_id
                          ORDER BY f.subject_code";

        $stmt = $conn->prepare($subject_query);
        $stmt->bind_param($param_types, ...$param_values);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        while ($row = $subject_result->fetch_assoc()) {
            $subject_details[] = $row;
            // PUTHU FEATURE-KAAGA: Unique faculty list edukkalam
            if (!isset($faculty_list[$row['faculty_id']])) {
                 $faculty_list[$row['faculty_id']] = $row['faculty_name'];
            }
        }
        
    } catch (Exception $e) {
        $db_error = $e->getMessage();
    }
}

// Calculate additional metrics
$percentage = ($stats['avg_rating'] ?? 0) > 0 ? (($stats['avg_rating'] ?? 0) / 5.0) * 100 : 0;
$gradeDetails = getGradeDetails($percentage);
$overall_grade = $gradeDetails['grade'];
$performance_status = $gradeDetails['status'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Analytics Report - Aarasys</title>
    
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* === NEW DESIGN STYLES === */
        /* All CSS variables and base styles are imported from the main files */
        :root {
            --primary-blue: #3b82f6; --primary-purple: #6366F1; --dark-bg: #1f2937;
            --light-bg: #f8f9fa; --card-bg: #ffffff; --border-color: #e5e7eb;
            --text-dark: #111827; --text-body: #4b5563; --text-light: #f9fafb;
            --text-muted: #9ca3af; --success-bg: #dcfce7; --success-text: #16a34a;
            --danger-bg: #fee2e2; --danger-text: #dc2626; --info-bg: #eff6ff;
            --info-text: #2563eb; --warning-bg: #fef3c7; --warning-text: #d97706;
            --sidebar-width: 280px; --header-height: 88px; --radius-md: 0.5rem;
            --radius-lg: 0.75rem; --radius-xl: 1rem; --radius-full: 9999px;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        body.dark-theme {
            --light-bg: #111827; --card-bg: #1f2937; --border-color: #374151;
            --text-dark: #f9fafb; --text-body: #9ca3af; --text-muted: #6b7280;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; background-color: var(--light-bg);
            color: var(--text-body); -webkit-font-smoothing: antialiased;
            transition: background-color 0.3s ease;
        }
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; }
        .admin-layout { display: flex; }
        .main-content {
            flex: 1; margin-left: var(--sidebar-width); display: flex;
            flex-direction: column; min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        body.sidebar-collapsed .main-content { margin-left: 92px; }
        .content-area { padding: 2rem 2.5rem; flex: 1; }
        .header {
            height: var(--header-height); background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color); display: flex;
            align-items: center; justify-content: space-between;
            padding: 0 2.5rem; position: sticky; top: 0; z-index: 20;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .header-title { font-size: 1.75rem; font-weight: 700; color: var(--text-dark); }
        .header-actions { display: flex; align-items: center; gap: 1rem; }
        .header-btn {
            width: 44px; height: 44px; border-radius: var(--radius-md);
            border: 1px solid var(--border-color); background-color: var(--card-bg);
            display: grid; place-items: center; font-size: 1.1rem;
            color: var(--text-body); cursor: pointer; transition: all 0.2s ease;
        }
        .header-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
        .user-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background-color: var(--primary-purple); color: var(--text-light);
            display: grid; place-items: center; font-weight: 600;
            font-size: 1.1rem; border: 2px solid var(--card-bg);
            box-shadow: 0 0 0 2px var(--primary-purple); cursor: pointer;
        }
        body.dark-theme .header-btn {
            background-color: var(--card-bg); border-color: var(--border-color);
            color: var(--text-body);
        }
        body.dark-theme .header-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.65rem 1rem; border-radius: var(--radius-md);
            font-weight: 600; text-decoration: none; border: none;
            cursor: pointer; font-size: 0.9rem; transition: all 0.2s ease;
        }
        .btn-primary { background-color: var(--primary-blue); color: var(--text-light); }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary {
            background-color: var(--card-bg); color: var(--text-body);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background-color: var(--light-bg); }
        body.dark-theme .btn-secondary {
            background-color: var(--light-bg); color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .btn-secondary:hover { background-color: #374151; }
        .grid-card {
            background-color: var(--card-bg); border-radius: var(--radius-xl);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem; transition: background-color 0.3s ease, border-color 0.3s ease;
            overflow: hidden;
        }
        .grid-card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; flex-wrap: wrap;
            transition: border-color 0.3s ease;
        }
        .card-title { font-size: 1.25rem; font-weight: 600; color: var(--text-dark); }
        .grid-card-body { padding: 1.5rem; }
        .grid-card-body-no-padding { padding: 0; }
        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%; border-collapse: collapse; font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            padding: 0.85rem 1.5rem; text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap; transition: border-color 0.3s ease;
        }
        .data-table thead {
            background-color: var(--light-bg);
            transition: background-color 0.3s ease;
        }
        .data-table th {
            font-size: 0.8rem; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .data-table tbody tr:hover { background-color: var(--light-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        body.dark-theme .data-table thead { background-color: var(--dark-bg); }
        body.dark-theme .data-table tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
        .badge {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.25rem 0.6rem; border-radius: var(--radius-full);
            font-size: 0.8rem; font-weight: 500;
            background: var(--light-bg); color: var(--text-body);
            border: 1px solid var(--border-color);
        }
        .badge-blue { background-color: var(--info-bg); color: var(--info-text); border-color: transparent; }
        body.dark-theme .badge {
            background-color: var(--light-bg); color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .badge-blue { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .no-data-placeholder { text-align: center; padding: 3rem 1.5rem; }
        .no-data-placeholder i { font-size: 3rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 1rem; }
        .no-data-placeholder h3 { font-size: 1.25rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.25rem; }
        .no-data-placeholder p { color: var(--text-body); }
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;
        }
        .stat-card {
            display: flex; flex-direction: column; gap: 0.25rem;
        }
        .stat-label {
            font-size: 0.9rem; color: var(--text-body); font-weight: 500;
        }
        .stat-value {
            font-size: 1.75rem; font-weight: 700; color: var(--text-dark);
            line-height: 1.2;
        }
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 999px;
            font-size: 0.8rem; font-weight: 600;
        }
        /* Grade-based colors */
        .status-A { background-color: var(--success-bg); color: var(--success-text); }
        .status-B { background-color: var(--info-bg); color: var(--info-text); }
        .status-C { background-color: var(--warning-bg); color: var(--warning-text); }
        .status-D { background-color: var(--danger-bg); color: var(--danger-text); }
        
        .rating-stars { color: #f59e0b; }
        .rating-stars .empty { color: var(--border-color); }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1001;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius-xl);
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            position: relative;
            transform: scale(0.95) translateY(10px);
            transition: all 0.2s ease-out;
        }
        .modal-overlay.active .modal-content { transform: scale(1) translateY(0); }
        .modal-close {
            position: absolute; top: 0.75rem; right: 0.75rem;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: none; border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            display: grid; place-items: center;
            transition: all 0.2s ease;
        }
        .modal-close:hover { background-color: var(--light-bg); color: var(--text-dark); }
        .modal-content h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        .faculty-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        .faculty-list-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }
        .faculty-list-item:hover {
            background-color: var(--light-bg);
            border-color: var(--primary-blue);
        }
        .faculty-list-item i {
            color: var(--primary-blue);
        }
        .faculty-list-item span {
            font-weight: 500;
            color: var(--text-dark);
        }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { font-size: 1.25rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .header-actions .btn-secondary { display: none; }
            .header-actions .btn-primary { 
                font-size: 0.8rem;
                padding: 0.6rem 0.8rem;
            }
        }
    </style>
</head>
<body class="dark-theme"> 
    <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Feedback Report</h1>
                <div class="header-actions">
                    <a href="view_feedback.php?view=forms&department=<?php echo urlencode($department); ?>&year=<?php echo urlencode($year); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button onclick="generatePDF('full')" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Download (Full Class)
                    </button>
                    <button onclick="openModal('facultyDownloadModal')" class="btn btn-primary">
                        <i class="fas fa-user-tie"></i> Download by Faculty
                    </button>
                    <button class="header-btn" title="Toggle Theme" id="themeToggleBtn">
                        <i class="fas fa-sun"></i>
                    </button>
                    <div class="user-avatar" title="Admin">AD</div>
                </div>
            </header>
            
            <div class="content-area">

                <?php if (empty($param_values)): ?>
                    <div class="grid-card grid-card-body-padded">
                        <div class="no-data-placeholder">
                            <i class="fas fa-filter"></i>
                            <h3>No Filters Selected</h3>
                            <p>Please go back to the 'View Feedback' page and select filters to generate a report.</p>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="grid-card" style="margin-bottom: 1.5rem;">
                        <div class="grid-card-header">
                            <h3 class="card-title">Summary Statistics</h3>
                        </div>
                        <div class="grid-card-body">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">Department</div>
                                    <div class="stat-value"><?= htmlspecialchars($department) ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Year & Semester</div>
                                    <div class="stat-value"><?= toRoman($year) ?> / <?= toRoman($semester) ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Class Strength</div>
                                    <div class="stat-value"><?= $stats['class_strength'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Students Responded</div>
                                    <div class="stat-value"><?= $stats['total_responses'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Average Rating</div>
                                    <div class="stat-value"><?= number_format($stats['avg_rating'] ?? 0, 2); ?>/5.0</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Overall Grade</div>
                                    <div class="stat-value">
                                        <span class="status-badge status-<?= substr($overall_grade, 0, 1) ?>"><?= $overall_grade ?></span>
                                    </div>
                                </div>
                                <div class="stat-card" style="grid-column: span 2;">
                                    <div class="stat-label">Performance Status</div>
                                    <div class="stat-value"><?= $performance_status ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid-card" style="margin-bottom: 1.5rem;">
                        <div class="grid-card-header">
                            <h3 class="card-title">Rating Distribution</h3>
                        </div>
                        <div class="grid-card-body-no-padding">
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Rating</th>
                                            <th>Description</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grade_distribution as $grade): ?>
                                            <tr>
                                                <td><span class="rating-stars" style="font-size: 1.1rem;"><?php echo str_repeat('★', (int)substr($grade['grade'], 0, 1) ?: 1); ?></span></td>
                                                <td><?= $grade['desc'] ?></td>
                                                <td><?= $grade['count'] ?></td>
                                                <td><?= $grade['percentage'] ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="grid-card">
                        <div class="grid-card-header">
                            <h3 class="card-title">Subject-wise Performance</h3>
                        </div>
                        <div class="grid-card-body-no-padding">
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Faculty Name</th>
                                            <th>Avg. Rating</th>
                                            <th>Grade</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($subject_details)): ?>
                                            <?php foreach ($subject_details as $row): ?>
                                                <tr>
                                                    <td><span class="badge"><?= htmlspecialchars($row['subject_code']) ?></span></td>
                                                    <td><?= htmlspecialchars($row['faculty_name']) ?></td>
                                                    <td>
                                                        <span style="font-weight: 600; color: var(--text-dark);"><?= number_format($row['avg_rating'], 2) ?></span>
                                                        / 5.0
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?= substr($row['grade'], 0, 1) ?>"><?= $row['grade'] ?></span>
                                                    </td>
                                                    <td><?= $row['performance_status'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="no-data-placeholder">
                                                        <i class="fas fa-info-circle"></i>
                                                        <h3>No Feedback Records Found</h3>
                                                        <p>No feedback records were found for the selected criteria.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                
                <?php endif; ?>

            </div> </main> </div> <div id="facultyDownloadModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('facultyDownloadModal')">&times;</button>
            <h2>Download Faculty-Specific Report</h2>
            <p style="font-size: 0.9rem; margin-top: -1rem; margin-bottom: 1.5rem; color: var(--text-muted);">Select a faculty member to download their individual report.</p>
            
            <div class="faculty-list">
                <?php if (empty($faculty_list)): ?>
                    <p>No faculty found for this class.</p>
                <?php else: ?>
                    <?php foreach ($faculty_list as $fac_id => $fac_name): ?>
                        <a href="faculty_report_pdf.php?faculty_id=<?= $fac_id ?>&department=<?= urlencode($department) ?>&year=<?= urlencode($year) ?>&semester=<?= urlencode($semester) ?>" 
                           class="faculty-list-item" target="_blank">
                            <i class="fas fa-user-tie"></i>
                            <span><?= htmlspecialchars($fac_name) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <script>
        // === PUTHU JAVASCRIPT ===
        function openModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.add('active');
        }
        function closeModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.remove('active');
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModal(event.target.id);
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });
        
        // --- End Puthu JS ---

        document.addEventListener('DOMContentLoaded', function() {
            // Theme Toggle
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            if(themeToggleBtn) {
                // Check local storage for theme
                const currentTheme = localStorage.getItem('theme');
                if (currentTheme === 'dark') {
                    document.body.classList.add('dark-theme');
                    themeToggleBtn.querySelector('i').classList.replace('fa-sun', 'fa-moon');
                } else {
                     document.body.classList.remove('dark-theme');
                     themeToggleBtn.querySelector('i').classList.replace('fa-moon', 'fa-sun');
                }

                themeToggleBtn.addEventListener('click', () => {
                    document.body.classList.toggle('dark-theme');
                    const icon = themeToggleBtn.querySelector('i');
                    if (document.body.classList.contains('dark-theme')) {
                        icon.classList.replace('fa-sun', 'fa-moon');
                        localStorage.setItem('theme', 'dark');
                    } else {
                        icon.classList.replace('fa-moon', 'fa-sun');
                        localStorage.setItem('theme', 'light');
                    }
                });
            }
        });
        
        function generatePDF(type) {
            // Get current parameters
            const department = '<?php echo htmlspecialchars($department); ?>';
            const year = '<?php echo htmlspecialchars($year); ?>';
            const semester = '<?php echo htmlspecialchars($semester); ?>';
            
            let pdfUrl = '';
            
            if (type === 'full') {
                 // Full class report (the one we fixed)
                pdfUrl = `generate_report_pdf.php?department=${encodeURIComponent(department)}&year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}`;
            }
            // Note: Individual faculty downloads are handled by the links in the modal
            
            if (pdfUrl) {
                window.open(pdfUrl, '_blank');
            }
        }
    </script>
</body>
</html>