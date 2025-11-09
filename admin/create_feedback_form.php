<?php
// Entha session'il user login seithullara enbathaiyum, avarudaya role'aiyum ariya, session'ai thodanga.
session_start();

// User login seyyamal irunthalo allathu admin aaga illamalo irunthal, login pakkathirku thiruppi anuppa vendum.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Database configuration'ai ullekka.
require_once __DIR__ . '/../config/db.php';

// Database aatchiyil ethenum thavarugal erpattal, athai velipaduththu.
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Puthiya form number'ai uruvaakkum seyarkadu
function generateFormNumber() {
    return 'FF' . date('Y') . mt_rand(1000, 9999);
}

// --- FORM SUBMISSION HANDLING ( படிவம் சமர்ப்பிப்பு கையாளுதல் ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department = $_POST['department'] ?? '';
    $year = $_POST['year'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $assignments = $_POST['assignments'] ?? [];
    $questions = $_POST['questions'] ?? [];
    $form_number = $_POST['form_number'] ?? '';

    if (!empty($department) && !empty($year) && !empty($semester) && !empty($questions) && !empty($assignments) && !empty($form_number)) {
        try {
            $conn->begin_transaction();
            // Ensure form_questions table exists
            $conn->query("CREATE TABLE IF NOT EXISTS form_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_number VARCHAR(64) NOT NULL,
                question_text VARCHAR(500) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_form_question (form_number, question_text)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Insert unique questions for this form into form_questions
            $seenTexts = [];
            $stmtQ = $conn->prepare("INSERT IGNORE INTO form_questions (form_number, question_text) VALUES (?, ?)");
            foreach ($questions as $q) {
                $qtext = trim($q['text'] ?? '');
                if ($qtext !== '' && !isset($seenTexts[$qtext])) {
                    $seenTexts[$qtext] = true;
                    $stmtQ->bind_param("ss", $form_number, $qtext);
                    $stmtQ->execute();
                }
            }

            // Cross-assign all question texts to every subject/faculty assignment
            foreach ($assignments as $row) {
                $subject_code = trim($row['code'] ?? '');
                $subject_name = trim($row['name'] ?? ''); // optional, not stored currently
                $faculty_id = intval($row['faculty'] ?? 0);
                if ($subject_code !== '' && $faculty_id > 0) {
                    foreach ($questions as $q) {
                        $qtext = trim($q['text'] ?? '');
                        if ($qtext !== '') {
                            $stmt = $conn->prepare("INSERT INTO feedback_forms (form_number, department, year, semester, subject_code, faculty_id, question, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                            $stmt->bind_param("sssssis", $form_number, $department, $year, $semester, $subject_code, $faculty_id, $qtext);
                            $stmt->execute();
                        }
                    }
                }
            }
            $conn->commit();
            $_SESSION['message'] = "Feedback form created successfully! Form Number: " . htmlspecialchars($form_number);
            $_SESSION['message_type'] = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Error creating form: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = "Please fill all required fields, add at least one subject/faculty assignment and at least one question.";
        $_SESSION['message_type'] = 'error';
    }
    header("Location: create_feedback_form.php");
    exit();
}

// Puthiya form'ukku oru form number'ai uruvaakkavum
$current_form_number = generateFormNumber();

// Aasiriyar thagavalgalai database'il irunthu eduththal
$facultyData = [];
$sql = "SELECT id, name, department FROM faculty ORDER BY department, name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facultyData[] = $row;
    }
}

// Thanithuvaana thuraigalin pattiyalai eduththal
$departments_query = $conn->query("SELECT DISTINCT department FROM students UNION SELECT DISTINCT department FROM faculty ORDER BY department ASC");
$departments = [];
while($row = $departments_query->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}


// Session'il irukkum seithiyai eduththu, piraku aliththuvida vendum
$message = '';
$message_type = '';
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
    <title>Create Feedback Form - Aarasys</title>

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
        .btn-success { background-color: var(--success-text); color: var(--text-light); }
        .btn-success:hover { background-color: #15803d; box-shadow: var(--shadow-md); }

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
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .card-title .badge {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.2rem 0.6rem;
            background-color: var(--info-bg);
            color: var(--info-text);
            border-radius: var(--radius-full);
            vertical-align: middle;
            margin-left: 0.5rem;
        }
        .grid-card-body {
            padding: 1.5rem;
        }

        /* 7. Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
        }
        .form-control {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background-color: var(--card-bg);
            color: var(--text-body);
            transition: all 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        .form-control.textarea-lg {
            min-height: 120px;
            padding-top: 0.75rem;
            padding-right: 3rem;
            resize: vertical;
        }
        
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
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--radius-xl);
            max-width: 700px;
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

        /* Sub-cards for questions/assignments */
        .sub-card {
            background-color: var(--light-bg); /* "Dim white" bg */
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            box-shadow: none; /* No shadow for sub-cards */
            animation: fadeInUp 0.4s ease-out forwards;
            opacity: 0;
        }
        .sub-card-header {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sub-card-title {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        /* Icon button for delete */
        .btn-icon {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: none; border: none;
            cursor: pointer; color: var(--text-muted);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .btn-icon.danger:hover {
            background-color: var(--danger-bg);
            color: var(--danger-text);
        }
        
        /* Suggestion Button Style */
        .textarea-wrapper { position: relative; }
        .suggestion-btn {
            position: absolute;
            top: 0.75rem; right: 0.75rem;
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 1px solid #f59e0b; /* Orange */
            background: #fef3c7; /* Light orange */
            color: #d97706; /* Dark orange */
            cursor: pointer;
            display: grid; place-items: center;
            transition: all 0.2s ease;
        }
        .suggestion-btn:hover {
            background: #f59e0b;
            color: white;
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }
        
        /* Preview Modal Styles */
        #previewBody {
            max-height: 60vh;
            overflow-y: auto;
            padding: 0 0.5rem;
        }
        #previewBody h4 {
            font-size: 1.1rem;
            color: var(--text-dark);
            font-weight: 600;
            margin-top: 1.25rem;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.25rem;
        }
        #previewBody p {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        #previewBody p strong {
            color: var(--text-dark);
        }
        #previewBody ul {
            list-style: none;
            padding-left: 0;
        }
        #previewBody li {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        #previewBody li small {
            color: var(--text-body);
            font-style: italic;
            display: block;
            margin-top: 0.25rem;
        }

        /* 11. Responsive */
        @media (max-width: 992px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-grid-assignments { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { display: none; /* Add JS to toggle */ }
            .content-area { padding: 1.5rem 1rem; }
            .header { padding: 0 1rem; }
            .header-title { display: none; }
            .header .search-wrapper { display: none; }
        }

        /* 12. Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animated {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
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
                <h1 class="header-title">Create Form</h1>
                
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
                
                <form id="feedbackForm" method="post" onsubmit="showLoader()">
                    <input type="hidden" name="form_number" value="<?php echo htmlspecialchars($current_form_number); ?>">
                    
                    <div class="grid-card animated" style="animation-delay: 0.1s;">
                        <div class="grid-card-header">
                            <h3 class="card-title">
                                Step 1: Form Details
                                <span class="badge"><?php echo htmlspecialchars($current_form_number); ?></span>
                            </h3>
                        </div>
                        <div class="grid-card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <select id="department" name="department" class="form-control" required>
                                        <option value="">Select Department</option>
                                         <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="year">Academic Year</label>
                                    <select id="year" name="year" class="form-control" required>
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <select id="semester" name="semester" class="form-control" required>
                                        <option value="">Select Semester</option>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid-card animated" style="animation-delay: 0.2s;">
                        <div class="grid-card-header">
                            <h3 class="card-title">Step 2: Assign Subjects & Faculty</h3>
                        </div>
                        <div class="grid-card-body">
                            <div id="assignmentsContainer">
                                </div>
                            <div style="margin-top: 1.5rem;">
                                <button type="button" class="btn btn-secondary" onclick="addAssignmentRow()">
                                    <i class="fas fa-plus"></i> Add Another Subject
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid-card animated" style="animation-delay: 0.25s;">
                        <div class="grid-card-header">
                            <h3 class="card-title">Step 3: Create Questions</h3>
                        </div>
                        <div class="grid-card-body">
                            <div id="questionsContainer">
                                </div>
                            <div style="margin-top: 1.5rem;">
                                <button type="button" class="btn btn-secondary" onclick="addQuestion()">
                                    <i class="fas fa-plus"></i> Add Blank Question
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="animated" style="animation-delay: 0.3s; margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                        <button type="button" class="btn btn-secondary" onclick="previewForm()"><i class="fas fa-eye"></i> Preview</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-size: 1rem;"><i class="fas fa-check"></i> Create & Save Form</button>
                    </div>
                </form>
                
            </div> </main> </div> <div id="messageModal" class="modal-overlay">
        <div class="modal-content" style="text-align: center; max-width: 450px;">
            <button class="modal-close" onclick="closeModal('messageModal')">&times;</button>
            <div id="messageIcon" style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <h2 id="messageText" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.6;"></h2>
            <button class="btn btn-primary" onclick="closeModal('messageModal')">Close</button>
        </div>
    </div>
    
    <div id="previewModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 700px;">
             <button class="modal-close" onclick="closeModal('previewModal')">&times;</button>
            <h2 id="previewTitle">Form Preview</h2>
            <div id="previewBody"></div>
        </div>
    </div>
    
    <div id="loadingOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <script>
        let questionCount = 0;
        let assignmentCount = 0;
        const facultyData = <?php echo json_encode($facultyData); ?>;
        const sampleQuestions = {
            'CSE': [
                'How would you rate the clarity of explanation for Data Structures concepts?',
                'Was the pace of the Operating Systems course appropriate?',
                'How relevant were the programming assignments in the Web Development course?',
                'Rate the faculty\'s ability to answer questions in the Algorithms class.',
                'Were the lab resources for Machine Learning adequate and accessible?'
            ],
            'ECE': [
                'How effective was the practical demonstration in the Analog Circuits lab?',
                'Rate the explanation of concepts in Digital Signal Processing.',
                'Was the course material for Microprocessors and Microcontrollers up-to-date?',
                'How would you rate the faculty\'s guidance on the final year project?',
                'Were the concepts of Communication Systems explained with sufficient real-world examples?'
            ],
            'Default': [
                'How would you rate the overall teaching effectiveness of the faculty?',
                'Was the faculty available for consultation outside of class hours?',
                'Did the faculty cover the entire syllabus as per the course plan?',
                'How would you rate the quality of the course materials provided?',
                'Did the faculty encourage interactive sessions and discussions?'
            ]
        };

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
            text.textContent = message;
            openModal('messageModal');
        }

        function generateFacultyOptions() {
            let options = '<option value="">Select Faculty</option>';
            facultyData.forEach(faculty => {
                options += `<option value="${faculty.id}">${faculty.name} (${faculty.department})</option>`;
            });
            return options;
        }

        function addSampleQuestion(index) {
            const department = document.getElementById('department').value;
            if (!department) {
                showMessageModal('Please select a department first to get relevant question suggestions.', 'error');
                return;
            }
            const questions = sampleQuestions[department] || sampleQuestions['Default'];
            const randomQuestion = questions[Math.floor(Math.random() * questions.length)];
            const textarea = document.querySelector(`[data-q-index="${index}"] textarea`);
            if (textarea) {
                textarea.value = randomQuestion;
            }
        }

        function addQuestion(questionText = '') {
            const container = document.getElementById('questionsContainer');
            const newIndex = questionCount++;
            const card = document.createElement('div');
            // USE NEW CSS CLASSES
            card.className = 'sub-card animated'; 
            card.setAttribute('data-q-index', newIndex);
            card.style.animationDelay = `${(container.children.length * 0.05)}s`;

            // USE NEW HTML STRUCTURE
            card.innerHTML = `
                <div class="sub-card-header">
                    <span class="sub-card-title">Question ${container.children.length + 1}</span>
                    <button type="button" class="btn-icon danger" title="Remove" onclick="removeQuestion(${newIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="grid-card-body">
                    <div class="form-group">
                        <label>Question Text</label>
                        <div class="textarea-wrapper">
                            <textarea class="form-control textarea-lg" name="questions[${newIndex}][text]" placeholder="E.g., Rate the clarity of concepts explained..." required>${questionText}</textarea>
                            <button type="button" class="suggestion-btn" title="Get a suggestion" onclick="addSampleQuestion(${newIndex})">
                                <i class="fas fa-lightbulb"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
            updateQuestionNumbers();
        }

        function removeQuestion(index) {
            const card = document.querySelector(`[data-q-index="${index}"]`);
            if (card) {
                card.remove();
                updateQuestionNumbers();
            }
        }
        
        function updateQuestionNumbers() {
            document.querySelectorAll('#questionsContainer .sub-card').forEach((card, index) => {
                card.querySelector('.sub-card-title').textContent = `Question ${index + 1}`;
            });
        }

        function addAssignmentRow() {
            const container = document.getElementById('assignmentsContainer');
            const idx = assignmentCount++;
            const row = document.createElement('div');
            // USE NEW CSS CLASSES
            row.className = 'sub-card animated';
            row.setAttribute('data-assign-index', idx);
            row.style.animationDelay = `${(container.children.length * 0.05)}s`;

            // USE NEW HTML STRUCTURE
            row.innerHTML = `
                <div class="sub-card-header">
                    <span class="sub-card-title">Subject/Faculty ${container.children.length + 1}</span>
                    <button type="button" class="btn-icon danger" title="Remove" onclick="removeAssignmentRow(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="grid-card-body">
                    <div class="form-grid form-grid-assignments" style="grid-template-columns: 1fr 2fr 1.5fr;">
                        <div class="form-group">
                            <label>Subject Code</label>
                            <input type="text" class="form-control" name="assignments[${idx}][code]" placeholder="E.g., CS101" required>
                        </div>
                        <div class="form-group">
                            <label>Subject Name</label>
                            <input type="text" class="form-control" name="assignments[${idx}][name]" placeholder="E.g., Data Structures">
                        </div>
                        <div class="form-group">
                            <label>Faculty</label>
                            <select class="form-control" name="assignments[${idx}][faculty]" required>
                                ${generateFacultyOptions()}
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(row);
            updateAssignmentNumbers();
        }

        function removeAssignmentRow(idx) {
            const row = document.querySelector(`[data-assign-index="${idx}"]`);
            if (row) {
                row.remove();
                updateAssignmentNumbers();
            }
        }

        function updateAssignmentNumbers() {
            document.querySelectorAll('#assignmentsContainer .sub-card').forEach((card, index) => {
                const label = card.querySelector('.sub-card-title');
                if (label) label.textContent = `Subject/Faculty ${index + 1}`;
            });
        }
        
        function previewForm() {
            const formNumber = document.querySelector('input[name="form_number"]').value;
            const deptSelect = document.getElementById('department');
            const yearSelect = document.getElementById('year');
            const semSelect = document.getElementById('semester');

            const department = deptSelect.options[deptSelect.selectedIndex].text;
            const year = yearSelect.options[yearSelect.selectedIndex].text;
            const semester = semSelect.options[semSelect.selectedIndex].text;
            
            const assignments = Array.from(document.querySelectorAll('#assignmentsContainer .sub-card')).map((card, index) => {
                const code = card.querySelector('input[name^="assignments"][name$="[code]"]').value;
                const name = card.querySelector('input[name^="assignments"][name$="[name]"]').value;
                const facultySelect = card.querySelector('select[name^="assignments"][name$="[faculty]"]');
                const facultyName = facultySelect && facultySelect.selectedIndex > 0 ? facultySelect.options[facultySelect.selectedIndex].text : 'Not Selected';
                return `<li><strong>${code || 'No Code'}</strong> - ${name || 'Untitled Subject'}<small>Faculty: ${facultyName}</small></li>`;
            }).join('');

            const questions = Array.from(document.querySelectorAll('#questionsContainer .sub-card')).map((card, index) => {
                const text = card.querySelector('textarea').value;
                return `<li><strong>Q${index+1}:</strong> ${text || '<i>Empty Question</i>'}</li>`;
            }).join('');
            
            const previewBody = document.getElementById('previewBody');
            previewBody.innerHTML = `
                <p><strong>Form Number:</strong> ${formNumber}</p>
                <p><strong>Department:</strong> ${department}</p>
                <p><strong>Year:</strong> ${year}</p>
                <p><strong>Semester:</strong> ${semester}</p>
                
                <h4>Subjects & Faculty:</h4>
                <ul>${assignments || '<li>No subjects assigned yet.</li>'}</ul>
                
                <h4 style="margin-top: 1rem;">Questions:</h4>
                <ul>${questions || '<li>No questions added yet.</li>'}</ul>
            `;
            openModal('previewModal');
        }

        document.addEventListener('DOMContentLoaded', function() {
            addAssignmentRow(); // Start with one subject/faculty row
            addQuestion(); // Start with one blank question
            
            // Show session message if it exists
            <?php if (!empty($message)): ?>
                showMessageModal('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
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
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>