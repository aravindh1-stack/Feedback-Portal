<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';
$message = '';
$error_message = '';
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
    header("Location: manage_forms.php");
    exit();
}

// Fetch feedback forms with error handling
try {
    // Intha query correct aa irukku. Group by form_number panni MIN(id) edukkuthu.
    $sql = "SELECT f.*, 
                   (SELECT COUNT(DISTINCT student_id) FROM feedback_responses fr WHERE fr.form_id IN (SELECT id FROM feedback_forms WHERE form_number = f.form_number)) as response_count
            FROM feedback_forms f
            WHERE f.id IN (
                SELECT MIN(id) 
                FROM feedback_forms 
                GROUP BY form_number
            )
            ORDER BY f.created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $forms = [];
    while ($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }
    
} catch (Exception $e) {
    $error_message = 'Error fetching forms: ' . $e->getMessage();
    error_log("Fetch forms error: " . $e->getMessage());
    $forms = [];
}

// Session message check
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Forms - Aarasys</title>

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

        /* 2. Base & Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-body);
            -webkit-font-smoothing: antialiased;
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

        /* 6. Card */
        .grid-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        .grid-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
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
        }
        .user-table thead {
            background-color: var(--light-bg);
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
        .user-table td .badge {
            font-weight: 500;
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
        .badge-blue {
            background-color: var(--info-bg);
            color: var(--info-text);
            border-color: transparent;
        }
        .badge-green {
            background-color: var(--success-bg);
            color: var(--success-text);
            border-color: transparent;
        }
        .badge-red {
            background-color: var(--danger-bg);
            color: var(--danger-text);
            border-color: transparent;
        }
        .action-buttons { display: flex; gap: 0.5rem; justify-content: flex-end; }
        
        /* 8. Modals */
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
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
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
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }
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
        
        /* 9. Loader */
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
        .loader-spinner {
            border: 5px solid var(--border-color);
            border-top: 5px solid var(--primary-blue);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* 10. NEW STYLES for this page */
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

        /* 11. Responsive */
        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { display: none; }
            .header .search-wrapper { display: none; }
        }

    </style>
</head>
<body>
    
    <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <div class="admin-layout">
        
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            
            <header class="header">
                <h1 class="header-title">Manage Forms</h1>
                
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
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= count($forms) ?></div>
                                    <div class="stat-label">Total Forms</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid-card">
                        <div class="grid-card-body-padded">
                            <div class="stat-card-new">
                                <div class="stat-icon-new" style="background-color: var(--success-bg); color: var(--success-text);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= array_sum(array_column($forms, 'response_count')) ?></div>
                                    <div class="stat-label">Total Responses</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid-card">
                         <div class="grid-card-body-padded">
                            <div class="stat-card-new">
                                <div class="stat-icon-new" style="background-color: var(--warning-bg); color: var(--warning-text);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-info-new">
                                    <div class="stat-value"><?= count(array_unique(array_column($forms, 'faculty_id'))) ?></div>
                                    <div class="stat-label">Unique Faculty</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid-card">
                    <div class="grid-card-header">
                        <h3 class="card-title">All Feedback Forms</h3>
                        <div class="table-actions">
                            <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="grid-card-body">
                        <?php if (count($forms) > 0): ?>
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
                                        <?php foreach ($forms as $form): ?>
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
                                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= date('g:i A', strtotime($form['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-secondary btn-sm view-form-btn" 
                                                            data-form-number="<?= htmlspecialchars($form['form_number']) ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="openDeleteConfirmationModal(<?= $form['id'] ?>, '<?= htmlspecialchars($form['form_number']) ?>', <?= $form['response_count'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
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
                                <i class="fas fa-inbox"></i>
                                <h3>No Forms Found</h3>
                                <p>You haven't created any feedback forms yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div> </main> </div> <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 450px;">
            <button class="modal-close" onclick="closeModal('messageModal')">&times;</button>
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.6;"></h2>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">Close</button>
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
            
            fetch(`?action=get_form_details&form_number=${encodeURIComponent(formNumber)}`)
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
                if (localStorage.getItem('theme') === 'dark') {
                    document.body.classList.add('dark-theme');
                    themeToggleBtn.querySelector('i').classList.replace('fa-sun', 'fa-moon');
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