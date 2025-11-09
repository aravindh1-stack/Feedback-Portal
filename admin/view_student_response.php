<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

if (!isset($_GET['student_id'])) {
    die('Student ID not provided.');
}

$student_id = intval($_GET['student_id']);

// Handle rating update (if any)
if (
    isset($_POST['save_rating'], $_POST['subject_code'], $_POST['faculty_name'], $_POST['question'], $_POST['new_rating'])
) {
    // This logic is preserved but not used in the new UI.
    // We can add an "Edit" button later if needed.
    $subject_code = $_POST['subject_code'];
    $faculty_name = $_POST['faculty_name'];
    $question = $_POST['question'];
    $new_rating = intval($_POST['new_rating']);

    $fac_stmt = $conn->prepare("SELECT id FROM faculty WHERE name=? LIMIT 1");
    $fac_stmt->bind_param("s", $faculty_name);
    $fac_stmt->execute();
    $fac_stmt->bind_result($faculty_id);
    $fac_stmt->fetch();
    $fac_stmt->close();

    $upd = $conn->prepare("UPDATE feedback_responses fr 
        JOIN feedback_forms f ON fr.form_number = f.form_number AND fr.subject_code = f.subject_code AND fr.faculty_id = f.faculty_id
        SET fr.rating=? 
        WHERE fr.student_id=? AND f.subject_code=? AND fr.faculty_id=? AND f.question=?");
    $upd->bind_param("iisis", $new_rating, $student_id, $subject_code, $faculty_id, $question);
    $upd->execute();
    $upd->close();

    header("Location: view_student_response.php?student_id=$student_id");
    exit();
}

// Fetch student info
$sqstu = $conn->prepare("SELECT name, sin_number, department, year, semester FROM students WHERE id=?");
$sqstu->bind_param("i", $student_id);
$sqstu->execute();
$student = $sqstu->get_result()->fetch_assoc();
$sqstu->close();

if (!$student) {
    die('Student not found.');
}

// Fetch responses
$sql = "SELECT f.subject_code, fac.name as faculty_name, COALESCE(fq.question_text, f.question) AS question, fr.rating 
        FROM feedback_responses fr 
        JOIN feedback_forms f 
            ON fr.form_number = f.form_number
            AND fr.subject_code = f.subject_code
            AND fr.faculty_id = f.faculty_id
        JOIN faculty fac ON fr.faculty_id = fac.id
        LEFT JOIN form_questions fq
            ON fq.form_number = fr.form_number AND fq.id = fr.question_id
        WHERE fr.student_id=? 
        ORDER BY f.subject_code, fac.name, COALESCE(fq.question_text, f.question)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$responses_result = $stmt->get_result();

// Group responses by subject and faculty
$responses_grouped = [];
while ($row = $responses_result->fetch_assoc()) {
    $key = $row['subject_code'] . '|' . $row['faculty_name'];
    if (!isset($responses_grouped[$key])) {
        $responses_grouped[$key] = [
            'subject_code' => $row['subject_code'],
            'faculty_name' => $row['faculty_name'],
            'questions' => []
        ];
    }
    $responses_grouped[$key]['questions'][] = [
        'text' => $row['question'],
        'rating' => $row['rating']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Response - <?= htmlspecialchars($student['name']) ?></title>
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* === NEW DESIGN STYLES === */

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
            --info-bg: #eff6ff;
            --info-text: #2563eb;
            
            /* Sizing & Spacing */
            --sidebar-width: 280px;
            --header-height: 88px;
            --radius-md: 0.5rem; --radius-lg: 0.75rem;
            --radius-xl: 1rem; --radius-full: 9999px;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
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
            transition: margin-left 0.3s ease;
        }
        body.sidebar-collapsed .main-content {
            margin-left: 92px;
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
        .btn-secondary {
            background-color: var(--card-bg);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background-color: var(--light-bg); border-color: #d1d5db; }
        body.dark-theme .btn-secondary {
            background-color: var(--light-bg);
            color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .btn-secondary:hover { background-color: #374151; }

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

        /* 8. Badge */
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

        /* 10. NEW STYLES for this page */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .detail-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        /* Response Grouping */
        .subject-group {
            margin-bottom: 1.5rem;
        }
        .subject-group-header {
            padding: 0.75rem 1.25rem;
            background-color: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            border-bottom: none;
        }
        .subject-group-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .subject-group-header p {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .subject-group .table-wrapper {
            border: 1px solid var(--border-color);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            overflow: hidden;
        }
        .subject-group .user-table th {
            background: var(--card-bg); /* White header for sub-table */
        }
        .subject-group .user-table td {
            white-space: normal;
        }
        .rating-stars {
            font-size: 1rem;
            color: #f59e0b; /* Yellow */
        }
        .rating-stars .fa-star.empty {
            color: var(--border-color);
        }

        /* 11. Responsive */
        @media (max-width: 992px) {
            .profile-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { font-size: 1.25rem; }
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dark-theme"> <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Student Response</h1>
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
                    <div class="grid-card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-graduate" style="color: var(--primary-blue); margin-right: 0.5rem;"></i>
                            <?= htmlspecialchars($student['name']) ?>
                        </h3>
                    </div>
                    <div class="grid-card-body">
                        <div class="profile-grid">
                            <div class="detail-group">
                                <div class="detail-label">SIN Number</div>
                                <div class="detail-value">
                                    <span class="badge"><?= htmlspecialchars($student['sin_number']) ?></span>
                                </div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Department</div>
                                <div class="detail-value">
                                    <span class="badge badge-blue"><?= htmlspecialchars($student['department']) ?></span>
                                </div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Academic Year</div>
                                <div class="detail-value"><?= htmlspecialchars($student['year']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Semester</div>
                                <div class="detail-value"><?= htmlspecialchars($student['semester']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid-card">
                    <div class="grid-card-header">
                        <h3 class="card-title">Feedback Responses</h3>
                    </div>
                    <div class="grid-card-body-padded">
                        <?php if (empty($responses_grouped)): ?>
                            <div class="no-data-placeholder">
                                <i class="fas fa-comment-slash"></i>
                                <h3>No Responses Found</h3>
                                <p>This student has not submitted any feedback.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($responses_grouped as $group): ?>
                                <div class="subject-group">
                                    <div class="subject-group-header">
                                        <h3><?= htmlspecialchars($group['subject_code']) ?></h3>
                                        <p><?= htmlspecialchars($group['faculty_name']) ?></p>
                                    </div>
                                    <div class="table-wrapper">
                                        <table class="user-table">
                                            <thead>
                                                <tr>
                                                    <th>Question</th>
                                                    <th style="text-align: center;">Rating</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group['questions'] as $q): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($q['text']) ?></td>
                                                        <td style="text-align: center; white-space: nowrap;">
                                                            <span class="rating-stars">
                                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?= $i > $q['rating'] ? 'empty' : '' ?>"></i>
                                                                <?php endfor; ?>
                                                            </span>
                                                            (<?= $q['rating'] ?>/5)
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Student List
                    </a>
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
        });
    </script>
</body>
</html>