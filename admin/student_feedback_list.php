<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

// Filters
$filter_dept = $_GET['department'] ?? '';
$filter_year = $_GET['year'] ?? '';
$filter_sem = $_GET['semester'] ?? '';
$where = [];
$params = [];
$types = '';

if ($filter_dept !== '') {
    $where[] = "s.department = ?";
    $params[] = $filter_dept;
    $types .= 's';
}
if ($filter_year !== '') {
    $where[] = "s.year = ?";
    $params[] = $filter_year;
    $types .= 's';
}
if ($filter_sem !== '') {
    $where[] = "s.semester = ?";
    $params[] = $filter_sem;
    $types .= 's';
}

// Main query to get students who have submitted feedback
$sql = "SELECT DISTINCT s.id, s.name, s.sin_number, s.department, s.year, s.semester 
        FROM students s 
        JOIN feedback_responses fr ON fr.student_id = s.id";
if ($where) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY s.name";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$students = [];
if($result) {
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get departments for dropdown (More efficient query)
$departments_query = "SELECT DISTINCT department FROM students WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
while ($departments_result && $row = $departments_result->fetch_assoc()) {
    $departments[] = $row['department'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback List - Aarasys</title>

    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* === NEW DESIGN STYLES (FROM manage_users.php) === */

        /* 1. CSS Variables (Theme) */
        :root {
            /* Palette */
            --primary-blue: #3b82f6; 
            --primary-purple: #6366F1;
            --dark-bg: #1f2937;
            --light-bg: #f8f9fa;    /* <-- "Dim White" Page Background */
            --card-bg: #ffffff;     /* <-- White Card Background */
            --border-color: #e5e7eb;
            --text-dark: #111827;
            --text-body: #4b5563;
            --text-light: #f9fafb;
            --text-muted: #9ca3af;
            --text-blue: #2563eb;
            --success-bg: #dcfce7;
            --success-text: #16a34a;
            --danger-bg: #fee2e2;
            --danger-text: #dc2626;
            --info-bg: #eff6ff;
            --info-text: #2563eb;
            
            /* Sizing & Spacing */
            --sidebar-width: 280px;
            --header-height: 88px;
            --radius-sm: 0.375rem; --radius-md: 0.5rem; --radius-lg: 0.75rem;
            --radius-xl: 1rem; --radius-2xl: 1.5rem; --radius-full: 9999px;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1);
        }
        
        /* Dark Theme */
        body.dark-theme {
            --light-bg: #111827;
            --card-bg: #1f2937;
            --border-color: #374151;
            --text-dark: #f9fafb;
            --text-body: #9ca3af;
            --text-muted: #6b7280;
        }

        /* 2. Base & Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.3s ease;
        }
        
        a { text-decoration: none; color: inherit; }
        button { font-family: inherit; }

        /* 3. Main Layout */
        .admin-layout { display: flex; }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease; /* Sidebar collapse animation */
        }
        body.sidebar-collapsed .main-content {
            margin-left: 92px; /* Collapsed sidebar width */
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
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .header-title {
            font-size: 1.75rem; 
            font-weight: 700;
            color: var(--text-dark);
        }
        .header-actions { display: flex; align-items: center; gap: 1rem; }
        .header-btn {
            width: 44px; height: 44px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            display: grid; place-items: center;
            font-size: 1.1rem; color: var(--text-body);
            cursor: pointer; transition: all 0.2s ease;
        }
        .header-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }
        .user-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            background-color: var(--primary-purple);
            color: var(--text-light);
            display: grid; place-items: center;
            font-weight: 600; font-size: 1.1rem;
            border: 2px solid var(--card-bg);
            box-shadow: 0 0 0 2px var(--primary-purple);
            cursor: pointer;
        }
        
        body.dark-theme .header-btn {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-body);
        }
        body.dark-theme .header-btn:hover { border-color: var(--primary-blue); color: var(--primary-blue); }

        /* 5. Buttons */
        .btn {
            display: inline-flex; align-items: center;
            gap: 0.5rem; padding: 0.65rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600; text-decoration: none;
            border: none; cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        .btn-primary { background-color: var(--primary-blue); color: var(--text-light); }
        .btn-primary:hover { background-color: var(--text-blue); box-shadow: var(--shadow-md); }

        /* 6. Card */
        .grid-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .grid-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            transition: border-color 0.3s ease;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .grid-card-body {
            padding: 0; /* Remove padding for full-width table */
        }
        .grid-card-body-padded {
            padding: 1.5rem;
        }

        /* 7. Table */
        .table-wrapper { overflow-x: auto; }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .user-table th, .user-table td {
            padding: 0.85rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            transition: border-color 0.3s ease;
        }
        .user-table thead {
            background-color: var(--light-bg);
            transition: background-color 0.3s ease;
        }
        .user-table th {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .user-table tbody tr:hover {
            background-color: var(--light-bg);
        }
        body.dark-theme .user-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.6rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 500;
            background: var(--light-bg);
            color: var(--text-body);
            border: 1px solid var(--border-color);
        }
        .badge-blue { background-color: var(--info-bg); color: var(--info-text); border-color: transparent; }
        
        body.dark-theme .badge {
            background-color: var(--light-bg);
            color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .badge-blue { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        
        .action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
        
        /* 8. Filter Form */
        .filter-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .form-select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background-color: var(--card-bg);
            color: var(--text-body);
            transition: all 0.2s ease;
        }
        .form-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        body.dark-theme .form-select {
            background-color: var(--light-bg);
            border-color: var(--border-color);
            color: var(--text-body);
        }

        /* 9. No Data Placeholder */
        .no-data-placeholder {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        .no-data-placeholder i {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        .no-data-placeholder h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }
        .no-data-placeholder p {
            color: var(--text-body);
        }

        /* 10. Responsive */
        @media (max-width: 992px) {
            .filter-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { display: none; }
            .filter-form { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body class="dark-theme"> <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Student Feedback List</h1>
                
                <div class="header-actions">
                    <button class="header-btn" title="Toggle Theme" id="themeToggleBtn">
                        <i class="fas fa-sun"></i>
                    </button>
                    <button class="header-btn" title="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                    <div class="user-avatar" title="Admin">AD</div>
                </div>
            </header>
            
            <div class="content-area">
                
                <div class="grid-card">
                    <div class="grid-card-body-padded">
                        <form method="get" class="filter-form">
                            <div class="form-group">
                                <label class="form-label" for="filter-dept">Department</label>
                                <select name="department" id="filter-dept" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" 
                                                <?php echo ($filter_dept == $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="filter-year">Year</label>
                                <select name="year" id="filter-year" class="form-select">
                                    <option value="">All Years</option>
                                    <option value="1" <?php echo ($filter_year == '1') ? 'selected' : ''; ?>>Year 1</option>
                                    <option value="2" <?php echo ($filter_year == '2') ? 'selected' : ''; ?>>Year 2</option>
                                    <option value="3" <?php echo ($filter_year == '3') ? 'selected' : ''; ?>>Year 3</option>
                                    <option value="4" <?php echo ($filter_year == '4') ? 'selected' : ''; ?>>Year 4</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="filter-sem">Semester</label>
                                <select name="semester" id="filter-sem" class="form-select">
                                    <option value="">All Semesters</option>
                                    <?php for($i=1; $i<=8; $i++): ?>
                                        <option value="<?=$i?>" <?php echo ($filter_sem == $i) ? 'selected' : ''; ?>>Semester <?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label> <button type="submit" class="btn btn-primary" id="filterButton">
                                    <i class="fas fa-search"></i>
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="grid-card">
                    <div class="grid-card-header">
                        <h3 class="card-title">
                            Students Who Submitted Feedback (<?= count($students) ?>)
                        </h3>
                    </div>
                    <div class="grid-card-body">
                        <?php if (count($students) > 0): ?>
                            <div class="table-wrapper">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>SIN Number</th>
                                            <th>Department</th>
                                            <th>Year</th>
                                            <th>Semester</th>
                                            <th style="text-align: right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $stu): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stu['name']) ?></td>
                                                <td><span class="badge"><?= htmlspecialchars($stu['sin_number']) ?></span></td>
                                                <td><span class="badge badge-blue"><?= htmlspecialchars($stu['department']) ?></span></td>
                                                <td><?= htmlspecialchars($stu['year']) ?></td>
                                                <td><?= htmlspecialchars($stu['semester']) ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a class="btn btn-primary btn-sm" href="view_student_response.php?student_id=<?= $stu['id'] ?>" target="_blank">
                                                            <i class="fas fa-eye"></i>
                                                            View Response
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data-placeholder">
                                <i class="fas fa-users-slash"></i>
                                <h3 class="empty-title">No Students Found</h3>
                                <p class="empty-description">
                                    <?php if ($filter_dept || $filter_year || $filter_sem): ?>
                                        No students match your current filter criteria. Try adjusting the filters.
                                    <?php else: ?>
                                        No students have submitted feedback responses yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div> </main> </div> <script>
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
            
            // Filter button loading state
            const filterForm = document.querySelector('.filter-form');
            const filterButton = document.getElementById('filterButton');
            
            if(filterForm && filterButton) {
                filterForm.addEventListener('submit', function() {
                    filterButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                    filterButton.disabled = true;
                });
            }
        });
    </script>
</body>
</html>