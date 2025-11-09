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
while ($departments_result && $row = $departments_result->fetch_assoc()) {
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
$types = '';
if (!empty($selected_department)) {
    $where_conditions[] = "f.department = ?";
    $params[] = $selected_department;
    $types .= 's';
}
if (!empty($selected_year)) {
    $where_conditions[] = "f.year = ?";
    $params[] = $selected_year;
    $types .= 's';
}
if (!empty($selected_semester)) {
    $where_conditions[] = "f.semester = ?";
    $params[] = $selected_semester;
    $types .= 's';
}
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// --- Start Stat Calculation (Moved to top) ---
try {
    // 1. Total students
    $students_sql = "SELECT COUNT(DISTINCT s.id) as total_students FROM students s";
    $student_params = [];
    $student_types = '';
    $student_where = [];
    if (!empty($selected_department)) {
        $student_where[] = "s.department = ?";
        $student_params[] = $selected_department;
        $student_types .= 's';
    }
    if (!empty($selected_year)) {
        $student_where[] = "s.year = ?";
        $student_params[] = $selected_year;
        $student_types .= 's';
    }
    if (!empty($selected_semester)) {
        $student_where[] = "s.semester = ?";
        $student_params[] = $selected_semester;
        $student_types .= 's';
    }
    if (!empty($student_where)) $students_sql .= ' WHERE ' . implode(' AND ', $student_where);
    
    $stmt = $conn->prepare($students_sql);
    if (!empty($student_params)) {
        $stmt->bind_param($student_types, ...$student_params);
    }
    $stmt->execute();
    $total_students = $stmt->get_result()->fetch_assoc()['total_students'] ?? 0;

    // 2. Total forms
    $forms_sql = "SELECT COUNT(DISTINCT f.form_number) as total_forms FROM feedback_forms f $where_clause";
    $stmt = $conn->prepare($forms_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_forms = $stmt->get_result()->fetch_assoc()['total_forms'] ?? 0;
    
    // 3. Total responses
    $responses_sql = "SELECT COUNT(fr.id) as total_responses 
                      FROM feedback_responses fr 
                      JOIN feedback_forms f ON fr.form_number = f.form_number 
                      $where_clause";
    $stmt = $conn->prepare($responses_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_responses_stat = $stmt->get_result()->fetch_assoc()['total_responses'] ?? 0;
    
    $stats = [
        'total_students' => $total_students,
        'total_forms' => $total_forms,
        'total_responses' => $total_responses_stat
    ];
} catch (Exception $e) {
    $stats = ['total_students' => 0, 'total_forms' => 0, 'total_responses' => 0];
    $error_message = "Error fetching stats: " . $e->getMessage();
}
// --- End Stat Calculation ---


// Fetch data based on view type
if ($view_type === 'feedback') {
    $sql = "SELECT DISTINCT s.id, s.name, s.sin_number, s.department, s.year, s.semester 
            FROM feedback_responses fr 
            JOIN students s ON fr.student_id = s.id
            JOIN feedback_forms f ON fr.form_number = f.form_number
            $where_clause
            ORDER BY s.name";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $feedback_data[] = $row;
    }
    
} elseif ($view_type === 'forms') {
    // Forms view query
    $forms_query = "SELECT 
        f.form_number,
        f.department,
        f.year,
        f.semester,
        MIN(f.id) as form_id, -- Get the MIN ID for deletion reference
        MIN(f.created_at) AS created_at,
        (SELECT COUNT(DISTINCT fr.student_id) FROM feedback_responses fr WHERE fr.form_number = f.form_number) as response_count
        FROM feedback_forms f
        $where_clause
        GROUP BY f.form_number, f.department, f.year, f.semester
        ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($forms_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $forms_result = $stmt->get_result();
    while ($forms_result && $row = $forms_result->fetch_assoc()) {
        $forms_data[] = $row;
    }

} elseif ($view_type === 'analytics') {
    if ($stats['total_responses'] > 0) {
        $analytics_query = "SELECT 
                COALESCE(fq.question_text, f.question) AS question,
                AVG(fr.rating) as avg_rating,
                COUNT(fr.id) as response_count,
                SUM(CASE WHEN fr.rating = 5 THEN 1 ELSE 0 END) as excellent_5,
                SUM(CASE WHEN fr.rating = 4 THEN 1 ELSE 0 END) as good_4,
                SUM(CASE WHEN fr.rating = 3 THEN 1 ELSE 0 END) as average_3,
                SUM(CASE WHEN fr.rating = 2 THEN 1 ELSE 0 END) as fair_2,
                SUM(CASE WHEN fr.rating = 1 THEN 1 ELSE 0 END) as need_improvement_1
                FROM feedback_responses fr
                JOIN feedback_forms f 
                    ON fr.form_number = f.form_number
                    AND fr.subject_code = f.subject_code
                    AND fr.faculty_id = f.faculty_id
                LEFT JOIN form_questions fq
                    ON fq.form_number = fr.form_number AND fq.id = fr.question_id
                $where_clause
                GROUP BY COALESCE(fq.question_text, f.question)
                ORDER BY response_count DESC";

        try {
            $stmt = $conn->prepare($analytics_query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($result && $row = $result->fetch_assoc()) {
                $analytics_data[] = $row;
            }
        } catch (Exception $e) {
             $error_message = "Error fetching analytics: " . $e->getMessage();
        }
    }
}

// Session message check
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// AJAX: Get all questions for a form number
if (isset($_GET['action']) && $_GET['action'] === 'get_form_details') {
    header('Content-Type: application/json');
    if (!isset($_GET['form_number']) || empty($_GET['form_number'])) {
        echo json_encode(['success' => false, 'error' => 'Form number is required']);
        exit();
    }
    $form_number = trim($_GET['form_number']);
    try {
        // Intha query la oru chinna optimization: DISTINCT use pannalam
        $stmt = $conn->prepare("SELECT DISTINCT question FROM feedback_forms WHERE form_number = ? ORDER BY id");
        $stmt->bind_param("s", $form_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row['question'];
        }
        
        // Form details-a yum eduthukalam
        $stmt_details = $conn->prepare("SELECT department, year, semester FROM feedback_forms WHERE form_number = ? LIMIT 1");
        $stmt_details->bind_param("s", $form_number);
        $stmt_details->execute();
        $details_result = $stmt_details->get_result();
        $details = $details_result->fetch_assoc();

        $stmt->close();
        $stmt_details->close();
        
        echo json_encode([
            'success' => true,
            'form' => [
                'form_number' => $form_number,
                'department' => $details['department'] ?? 'N/A',
                'year' => $details['year'] ?? 'N/A',
                'semester' => $details['semester'] ?? 'N/A',
                'questions' => $questions,
                'total_questions' => count($questions)
            ]
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Error in get_form_details: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Unable to load questions. Please try again.']);
        exit();
    }
}


// Handle delete request
if (isset($_POST['delete_form_id'])) {
    $form_id_to_delete = intval($_POST['delete_form_id']); // This ID is the MIN(id) from the form
    
    // We need the form_number to delete all related entries, not just one ID
    $stmt_get_num = $conn->prepare("SELECT form_number FROM feedback_forms WHERE id = ?");
    $stmt_get_num->bind_param("i", $form_id_to_delete);
    $stmt_get_num->execute();
    $result_num = $stmt_get_num->get_result();
    $form_data = $result_num->fetch_assoc();
    
    if ($form_data) {
        $form_number_to_delete = $form_data['form_number'];
        
        try {
            $conn->begin_transaction();

            // 1. Get all form IDs with this form_number
            $form_ids = [];
            $stmt_ids = $conn->prepare("SELECT id FROM feedback_forms WHERE form_number = ?");
            $stmt_ids->bind_param("s", $form_number_to_delete);
            $stmt_ids->execute();
            $result_ids = $stmt_ids->get_result();
            while($row = $result_ids->fetch_assoc()) {
                $form_ids[] = $row['id'];
            }
            $stmt_ids->close();

            if (!empty($form_ids)) {
                $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
                $types = str_repeat('i', count($form_ids));

                // 2. Delete related responses first
                $delete_responses = $conn->prepare("DELETE FROM feedback_responses WHERE form_id IN ($placeholders)");
                $delete_responses->bind_param($types, ...$form_ids);
                $delete_responses->execute();
                $delete_responses->close();
                
                // 3. Delete from form_questions table
                $delete_fq = $conn->prepare("DELETE FROM form_questions WHERE form_number = ?");
                $delete_fq->bind_param("s", $form_number_to_delete);
                $delete_fq->execute();
                $delete_fq->close();

                // 4. Delete all form entries from feedback_forms
                $delete_form = $conn->prepare("DELETE FROM feedback_forms WHERE form_number = ?");
                $delete_form->bind_param("s", $form_number_to_delete);
                $delete_form->execute();
                
                if ($delete_form->affected_rows > 0) {
                    $conn->commit();
                    $_SESSION['message'] = "Form ($form_number_to_delete) and all its related data deleted successfully!";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $conn->rollback();
                    $_SESSION['message'] = 'Form not found or could not be deleted.';
                    $_SESSION['message_type'] = 'error';
                }
                $delete_form->close();
            } else {
                 $conn->rollback();
                 $_SESSION['message'] = 'Form not found.';
                 $_SESSION['message_type'] = 'error';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = 'Error deleting form: '. $e->getMessage();
            $_SESSION['message_type'] = 'error';
            error_log("Delete form error: " . $e->getMessage());
        }
    } else {
        $_SESSION['message'] = 'Could not find form to delete.';
        $_SESSION['message_type'] = 'error';
    }
    
    $stmt_get_num->close();
    header("Location: view_feedback.php?" . http_build_query($_GET)); // Stay on the same filtered view
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - Aarasys</title>

    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0iIzQzMzhDMyIvPgo8cGF0aCBkPSJNOCAxMkg5VjIwSDhWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTEgMTJIMTJWMjBIMTFWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTQgMTJIMTVWMjBIMTRWMTJaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --warning-bg: #fef3c7;
            --warning-text: #d97706;
            
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
        .search-wrapper { position: relative; }
        .search-wrapper i {
            position: absolute; left: 1rem; top: 50%;
            transform: translateY(-50%); color: var(--text-muted);
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
            outline: none; border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background-color: var(--card-bg);
        }
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
        body.dark-theme .search-input {
             background-color: var(--light-bg);
             border-color: var(--border-color);
        }

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
        .btn-secondary {
            background-color: var(--card-bg);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background-color: var(--light-bg); border-color: #d1d5db; }
        .btn-danger { background-color: var(--danger-text); color: var(--text-light); }
        .btn-danger:hover { background-color: #b91c1c; box-shadow: var(--shadow-md); }
        body.dark-theme .btn-secondary {
            background-color: var(--light-bg);
            color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .btn-secondary:hover { background-color: #374151; }

        /* 6. Tabs */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        .tab-btn {
            padding: 0.6rem 1.25rem;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-body);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .tab-btn.active, .tab-btn:hover {
            background: var(--card-bg);
            color: var(--primary-blue);
            border-color: var(--primary-blue);
            box-shadow: var(--shadow-sm);
        }
        .tab-btn.active {
             background: var(--info-bg);
        }
        body.dark-theme .tab-btn {
            background: var(--light-bg);
            color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .tab-btn.active, body.dark-theme .tab-btn:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        /* 7. Card */
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

        /* 8. Table */
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
        .badge-green { background-color: var(--success-bg); color: var(--success-text); border-color: transparent; }
        .badge-red { background-color: var(--danger-bg); color: var(--danger-text); border-color: transparent; }
        
        body.dark-theme .badge {
            background-color: var(--light-bg);
            color: var(--text-body);
            border-color: var(--border-color);
        }
        body.dark-theme .badge-blue { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        body.dark-theme .badge-green { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
        body.dark-theme .badge-red { background-color: rgba(239, 68, 68, 0.1); color: #f87171; }
        
        .action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
        
        /* 9. Modals */
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
            max-width: 600px;
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
        
        /* 10. Loader */
        .loader-overlay {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        body.dark-theme .loader-overlay {
             background: rgba(17, 24, 39, 0.7);
        }
        .loader-spinner {
            border: 5px solid var(--border-color);
            border-top: 5px solid var(--primary-blue);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* 11. NEW STYLES for this page */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .stat-card-new {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon-new {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .stat-info-new .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
        }
        .stat-info-new .stat-label {
            font-size: 0.9rem;
            color: var(--text-body);
        }
        
        /* No Data Placeholder */
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
        
        /* Filter Form */
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

        /* View Form Modal Styles */
        #viewFormModal .modal-content {
            max-width: 700px;
        }
        #viewFormModal h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        #viewFormModal .form-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        #viewFormModal .form-meta .badge {
            margin: 0 0.25rem;
        }
        #viewFormModal .question-list {
            max-height: 60vh;
            overflow-y: auto;
            padding: 0.5rem;
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
        }
        #viewFormModal .question-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #viewFormModal .question-list li {
            padding: 0.75rem 1rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        #viewFormModal .question-list li:last-child {
            margin-bottom: 0;
        }
        #viewFormModal .question-list li strong {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--primary-blue);
            flex-shrink: 0;
            margin-top: 0.15rem;
        }
        
        /* Analytics Chart Container */
        .chart-container {
            padding: 1.5rem;
            height: 400px; /* Set a fixed height */
            width: 100%;
        }

        /* 12. Responsive */
        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .filter-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { display: none; }
            .header .search-wrapper { display: none; }
            .filter-form { grid-template-columns: 1fr; }
        }

    </style>
</head>
<body class="dark-theme"> <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Feedback & Analytics</h1>
                
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

                <div class="dashboard-grid">
                    <div class="grid-card">
                        <div class="grid-card-body-padded">
                            <div class="stat-card-new">
                                <div class="stat-icon-new" style="background-color: var(--info-bg); color: var(--info-text);">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= number_format($stats['total_students'] ?? 0) ?></div>
                                    <div class="stat-label">Students in Filter</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid-card">
                        <div class="grid-card-body-padded">
                            <div class="stat-card-new">
                                <div class="stat-icon-new" style="background-color: var(--warning-bg); color: var(--warning-text);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= number_format($stats['total_forms'] ?? 0) ?></div>
                                    <div class="stat-label">Forms in Filter</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid-card">
                         <div class="grid-card-body-padded">
                            <div class="stat-card-new">
                                <div class="stat-icon-new" style="background-color: var(--success-bg); color: var(--success-text);">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= number_format($stats['total_responses'] ?? 0) ?></div>
                                    <div class="stat-label">Total Responses</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid-card">
                    <div class="grid-card-body-padded">
                        <form method="GET" class="filter-form">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_type); ?>">
                            
                            <div class="form-group">
                                <label class="form-label" for="filter-dept">Department</label>
                                <select name="department" id="filter-dept" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>" 
                                                <?php echo ($selected_department == $dept) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="filter-year">Year</label>
                                <select name="year" id="filter-year" class="form-select">
                                    <option value="">All Years</option>
                                    <option value="1" <?php echo ($selected_year == '1') ? 'selected' : ''; ?>>Year 1</option>
                                    <option value="2" <?php echo ($selected_year == '2') ? 'selected' : ''; ?>>Year 2</option>
                                    <option value="3" <?php echo ($selected_year == '3') ? 'selected' : ''; ?>>Year 3</option>
                                    <option value="4" <?php echo ($selected_year == '4') ? 'selected' : ''; ?>>Year 4</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="filter-sem">Semester</label>
                                <select name="semester" id="filter-sem" class="form-select">
                                    <option value="">All Semesters</option>
                                    <?php for($i=1; $i<=8; $i++): ?>
                                        <option value="<?=$i?>" <?php echo ($selected_semester == $i) ? 'selected' : ''; ?>>Semester <?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label> <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                    Apply Filters
                                </button>
                            </div>
                        </form>

                        <div class="tabs">
                            <a href="?view=feedback&department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($selected_semester); ?>" 
                               class="tab-btn <?php echo ($view_type === 'feedback') ? 'active' : ''; ?>">
                                <i class="fas fa-comments"></i> Feedback Responses
                            </a>
                            <a href="?view=forms&department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($selected_semester); ?>" 
                               class="tab-btn <?php echo ($view_type === 'forms') ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt"></i> Form Reports
                            </a>
                            <a href="?view=analytics&department=<?php echo urlencode($selected_department); ?>&year=<?php echo urlencode($selected_year); ?>&semester=<?php echo urlencode($selected_semester); ?>" 
                               class="tab-btn <?php echo ($view_type === 'analytics') ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar"></i> Analytics
                            </a>
                        </div>
                    </div>
                </div>


                <?php if ($view_type === 'analytics'): ?>
                    <div class="grid-card">
                        <?php if (!empty($analytics_data)): ?>
                             <div class="grid-card-body-padded">
                                 <div class="chart-container">
                                     <canvas id="ratingChart"></canvas>
                                 </div>
                             </div>
                             <div class="grid-card-body-padded" style="border-top: 1px solid var(--border-color)">
                                 <div class="chart-container">
                                    <canvas id="distributionChart"></canvas>
                                 </div>
                             </div>
                        <?php else: ?>
                            <div class="no-data-placeholder">
                                <i class="fas fa-chart-bar"></i>
                                <h3>No Analytics Data Found</h3>
                                <p>No feedback responses were found for the selected filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php elseif ($view_type === 'forms'): ?>
                    <div class="grid-card">
                        <div class="grid-card-header">
                            <h3 class="card-title">Form Reports</h3>
                        </div>
                        <div class="grid-card-body">
                            <?php if (!empty($forms_data)): ?>
                                <div class="table-wrapper">
                                    <table class="user-table" id="formsTable">
                                        <thead>
                                            <tr>
                                                <th>Form Number</th>
                                                <th>Details</th>
                                                <th>Responses</th>
                                                <th>Created</th>
                                                <th style="text-align: right;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($forms_data as $form): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-blue">
                                                        <?= htmlspecialchars($form['form_number']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500; color: var(--text-dark);"><?= htmlspecialchars($form['department']) ?></div>
                                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                        Year <?= $form['year'] ?> | Sem <?= $form['semester'] ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $form['response_count'] == 0 ? 'badge-red' : 'badge-green' ?>">
                                                        <?= $form['response_count'] ?> Responses
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-size: 0.85rem; color: var(--text-body);"><?= date('M j, Y', strtotime($form['created_at'])) ?></div>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="detailed_report.php?form_number=<?php echo urlencode($form['form_number']); ?>" 
                                                           class="btn btn-primary btn-sm" target="_blank">
                                                            <i class="fas fa-file-pdf"></i> View Report
                                                        </a>
                                                        <button class="btn btn-secondary btn-sm view-form-btn" 
                                                                data-form-number="<?= htmlspecialchars($form['form_number']) ?>">
                                                            <i class="fas fa-eye"></i> View Qs
                                                        </button>
                                                        <button class="btn btn-danger btn-sm" 
                                                                onclick="openDeleteConfirmationModal(<?= $form['form_id'] ?>, '<?= htmlspecialchars($form['form_number']) ?>', <?= $form['response_count'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data-placeholder">
                                    <i class="fas fa-file-alt"></i>
                                    <h3>No Forms Found</h3>
                                    <p>No forms were found for the selected filters.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: // Default 'feedback' view ?>
                    <div class="grid-card">
                        <div class="grid-card-header">
                            <h3 class="card-title">Student Feedback Responses</h3>
                            <?php
                            $pdf_link = 'feedback_pdf.php';
                            $query_string = http_build_query([
                                'department' => $selected_department,
                                'year' => $selected_year,
                                'semester' => $selected_semester
                            ]);
                            if ($query_string) $pdf_link .= '?' . $query_string;
                            ?>
                            <a href="<?= $pdf_link ?>" class="btn btn-secondary btn-sm" target="_blank">
                                <i class="fas fa-download"></i>
                                Download PDF
                            </a>
                        </div>
                        <div class="grid-card-body">
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
                                        <?php if (!empty($feedback_data)): ?>
                                            <?php foreach ($feedback_data as $stu): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($stu['name']) ?></td>
                                                    <td><span class="badge"><?= htmlspecialchars($stu['sin_number']) ?></span></td>
                                                    <td><?= htmlspecialchars($stu['department']) ?></td>
                                                    <td><?= htmlspecialchars($stu['year']) ?></td>
                                                    <td><?= htmlspecialchars($stu['semester']) ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="view_student_response.php?student_id=<?= $stu['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                                                                <i class="fas fa-eye"></i>
                                                                View Response
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; // ITHAAN ANTHA SARIPANNA LINE (THIS IS THE CORRECTED LINE) ?> 
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="no-data-placeholder">
                                                        <i class="fas fa-search"></i>
                                                        <h3>No Feedback Data Found</h3>
                                                        <p>No students have submitted feedback for the selected filters.</p>
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
                
            </div> </main> </div> <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 450px;">
            <button class="modal-close" onclick="closeModal('messageModal')">&times;</button>
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.6;"></h2>
            <button class="btn btn-primary" onclick="closeModalAndRefresh('messageModal')">Close</button>
        </div>
    </div>
    
    <div id="deleteConfirmationModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 420px;">
            <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--danger-text);"><i class="fas fa-exclamation-triangle"></i></div>
            <h2 style="margin-bottom: 0.5rem;">Are you sure?</h2>
            <p id="deleteConfirmationText" style="margin-bottom: 1.5rem; font-size: 1rem;">This action cannot be undone.</p>
            <form id="deleteForm" method="POST" onsubmit="showLoader()">
                <input type="hidden" id="delete_form_id" name="delete_form_id">
                <div style="display: flex; gap: 0.75rem; justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmationModal')">Cancel</button>
                    <button type="submit" id="delete_submit_button" name="delete_form" class="btn btn-danger">Yes, Delete Form</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewFormModal" class="modal-overlay">
        <div class="modal-content">
             <button class="modal-close" onclick="closeModal('viewFormModal')">&times;</button>
            <h2 id="viewFormTitle">Form Details</h2>
            <div id="viewFormMeta" class="form-meta">Loading...</div>
            <div class="question-list">
                <ul id="viewFormQuestions">
                    </ul>
            </div>
        </div>
    </div>

    
    <script>
        function showLoader() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function openModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.add('active');
        }
        function closeModal(modalId) { 
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.remove('active');
        }
        
        // PUTHU FUNCTION: Close panni refresh pannum
        function closeModalAndRefresh(modalId) {
            closeModal(modalId);
            // Form delete-ku apparam, message-a clear panna URL-a maathrom
            location.href = location.pathname + location.search;
        }
        
        function showMessageModal(message, type) {
            const modal = document.getElementById('messageModal');
            const icon = modal.querySelector('#messageIcon');
            const text = modal.querySelector('#messageText');
            if (type === 'success') {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success-text);"></i>';
            } else {
                icon.innerHTML = '<i class="fas fa-times-circle" style="color: var(--danger-text);"></i>';
            }
            text.innerHTML = message.replace(/\n/g, '<br>'); // Use innerHTML for <br>
            openModal('messageModal');
        }
        
        function openDeleteConfirmationModal(formId, formNumber, responseCount) {
            document.getElementById('delete_form_id').value = formId;
            const text = document.getElementById('deleteConfirmationText');
            text.innerHTML = `Do you really want to delete form <strong>${formNumber}</strong>?<br>This will also delete all <strong>${responseCount}</strong> associated responses. This action cannot be undone.`;
            openModal('deleteConfirmationModal');
        }
        
        // --- View Form AJAX ---
        document.querySelectorAll('.view-form-btn').forEach(button => {
            button.addEventListener('click', function() {
                const formNumber = this.dataset.formNumber;
                loadFormDetails(formNumber);
            });
        });

        function loadFormDetails(formNumber) {
            openModal('viewFormModal');
            // Set loading state
            document.getElementById('viewFormTitle').textContent = 'Loading Details...';
            document.getElementById('viewFormMeta').innerHTML = `<span class="badge">${formNumber}</span>`;
            document.getElementById('viewFormQuestions').innerHTML = '<li><i class="fas fa-spinner fa-spin"></i> Loading questions...</li>';
            
            // Note: We're adding a cache-buster to ensure we get fresh data
            const cacheBuster = new Date().getTime();
            fetch(`?action=get_form_details&form_number=${encodeURIComponent(formNumber)}&_=${cacheBuster}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFormDetails(data.form);
                    } else {
                        document.getElementById('viewFormQuestions').innerHTML = `<li>Error: ${data.error}</li>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewFormQuestions').innerHTML = '<li>An error occurred while loading.</li>';
                });
        }

        function displayFormDetails(form) {
            document.getElementById('viewFormTitle').textContent = 'Form Questions';
            document.getElementById('viewFormMeta').innerHTML = `
                <span class="badge badge-blue">${form.form_number}</span>
                <span class="badge">${form.department}</span>
                <span class="badge">Year ${form.year}</span>
                <span class="badge">Sem ${form.semester}</span>
            `;
            
            let questionsHtml = '';
            if (form.questions && form.questions.length > 0) {
                questionsHtml = form.questions.map((q, index) => `
                    <li><strong>Q${index + 1}:</strong> <span>${q}</span></li>
                `).join('');
            } else {
                questionsHtml = '<li>No questions found for this form.</li>';
            }
            document.getElementById('viewFormQuestions').innerHTML = questionsHtml;
        }

        // --- Global JS ---
        document.addEventListener('DOMContentLoaded', function() {
            // Show session message if it exists
            <?php if (!empty($message) || !empty($error_message)): ?>
                showMessageModal(
                    '<?php echo addslashes(empty($message) ? $error_message : $message); ?>',
                    '<?php echo empty($message) ? 'error' : 'success'; ?>'
                );
            <?php endif; ?>

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
                    
                    // Reload charts with new colors
                    if (typeof Chart.instances === 'object') {
                        Object.values(Chart.instances).forEach(chart => {
                            chart.destroy();
                        });
                    }
                    if (typeof loadCharts === 'function') {
                        loadCharts(); // We'll wrap chart logic in this function
                    }
                });
            }
            
            loadCharts(); // Initial chart load
        });
        
        function loadCharts() {
            // --- CHART.JS LOGIC ---
            <?php if ($view_type === 'analytics' && !empty($analytics_data)): ?>
                const analyticsData = <?php echo json_encode($analytics_data); ?>;
                const chartLabels = analyticsData.map((q, i) => `Q${i + 1} (${q.response_count} resp)`);
                const isDark = document.body.classList.contains('dark-theme');
                const textColor = isDark ? '#f9fafb' : '#111827';
                const gridColor = isDark ? '#374151' : '#e5e7eb';
                const tickColor = isDark ? '#9ca3af' : '#4b5563';
                const chartBg = isDark ? '#1f2937' : '#ffffff';

                // 1. Rating Bar Chart
                const ratingCtx = document.getElementById('ratingChart');
                if (ratingCtx) {
                    new Chart(ratingCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Average Rating',
                                data: analyticsData.map(q => parseFloat(q.avg_rating)),
                                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 2,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                title: { display: true, text: 'Average Rating per Question', font: { size: 16 }, color: textColor },
                                tooltip: {
                                    callbacks: {
                                        title: function(context) {
                                            const index = context[0].dataIndex;
                                            return analyticsData[index].question;
                                        },
                                        label: function(context) {
                                            return `Avg Rating: ${context.parsed.y.toFixed(2)}/5.0`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true, max: 5, ticks: { stepSize: 1, color: tickColor },
                                    grid: { color: gridColor }
                                },
                                x: { ticks: { color: tickColor }, grid: { display: false } }
                            }
                        }
                    });
                }

                // 2. Distribution Doughnut Chart (Overall)
                const distCtx = document.getElementById('distributionChart');
                if(distCtx) {
                    const overall = analyticsData.reduce((acc, q) => {
                        acc.excellent_5 += parseInt(q.excellent_5, 10);
                        acc.good_4 += parseInt(q.good_4, 10);
                        acc.average_3 += parseInt(q.average_3, 10);
                        acc.fair_2 += parseInt(q.fair_2, 10);
                        acc.need_improvement_1 += parseInt(q.need_improvement_1, 10);
                        return acc;
                    }, { excellent_5: 0, good_4: 0, average_3: 0, fair_2: 0, need_improvement_1: 0 });

                    new Chart(distCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Excellent (5)', 'Good (4)', 'Average (3)', 'Fair (2)', 'Needs Improvement (1)'],
                            datasets: [{
                                label: 'Total Responses',
                                data: [
                                    overall.excellent_5,
                                    overall.good_4,
                                    overall.average_3,
                                    overall.fair_2,
                                    overall.need_improvement_1
                                ],
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.7)', // green
                                    'rgba(59, 130, 246, 0.7)', // blue
                                    'rgba(245, 158, 11, 0.7)', // yellow
                                    'rgba(239, 68, 68, 0.7)', // red
                                    'rgba(107, 114, 128, 0.7)' // gray
                                ],
                                borderColor: chartBg,
                                borderWidth: 3,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { color: textColor } },
                                title: { display: true, text: 'Overall Rating Distribution', font: { size: 16 }, color: textColor }
                            }
                        }
                    });
                }
            <?php endif; ?>
        } // End of loadCharts()

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModal(event.target.id);
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });
    </script>
</body>
</html>