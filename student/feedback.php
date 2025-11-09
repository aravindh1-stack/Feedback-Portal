<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

$user = $_SESSION['user'];
$department = $user['department'];
$year = $user['year'];
$semester = $user['semester'];
$student_id = $user['id'];

// Find available form_numbers (not yet answered by this student)
$sql = "SELECT DISTINCT f.form_number
        FROM feedback_forms f
        LEFT JOIN feedback_responses r
          ON f.form_number = r.form_number AND r.student_id = ?
        WHERE f.department = ? AND f.year = ? AND f.semester = ?
          AND f.form_number IS NOT NULL AND f.form_number <> ''
          AND r.id IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isii", $student_id, $department, $year, $semester);
$stmt->execute();
$res = $stmt->get_result();
$formNumbers = [];
while ($row = $res->fetch_assoc()) {
    $fn = trim((string)($row['form_number'] ?? ''));
    if ($fn !== '') { $formNumbers[] = $fn; }
}
$stmt->close();
$noForm = count($formNumbers) === 0;

// If no forms available, check for Chief Guest feedback
if ($noForm) {
    $cgCount = 0;
    $cgStmt = $conn->prepare("SELECT COUNT(DISTINCT form_number) AS cnt FROM chief_guest_feedback_forms WHERE department = ? AND academic_year = ? AND semester = ?");
    if ($cgStmt) {
        $cgStmt->bind_param("sii", $department, $year, $semester);
        $cgStmt->execute();
        $cgStmt->bind_result($cgCount);
        $cgStmt->fetch();
        $cgStmt->close();
    }
    if ($cgCount > 0) { 
    }
}

// Get form number to use
$form_number = null;
if (!$noForm) {
    if (count($formNumbers) >= 1) {
        $form_number = $formNumbers[0];
    } else {
        // Backfill form number for legacy data
        $form_number = 'FF' . date('Y') . mt_rand(1000, 9999);
        $upd = $conn->prepare("UPDATE feedback_forms SET form_number = ? WHERE department = ? AND year = ? AND semester = ? AND (form_number IS NULL OR form_number = '')");
        if ($upd) {
            $upd->bind_param('ssii', $form_number, $department, $year, $semester);
            $upd->execute();
            $upd->close();
        }
    }
}

// Function to convert numbers to Roman numerals
function toRoman($num) {
    if (!is_numeric($num) || $num <= 0) return 'N/A';
    $map = ['M'=>1000,'CM'=>900,'D'=>500,'CD'=>400,'C'=>100,'XC'=>90,'L'=>50,'XL'=>40,'X'=>10,'IX'=>9,'V'=>5,'IV'=>4,'I'=>1];
    $return = '';
    foreach ($map as $roman => $int) {
        while ($num >= $int) {
            $return .= $roman;
            $num -= $int;
        }
    }
    return $return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback - College Portal</title>
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
        }

        /* Progress Card */
        .progress-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--white);
            box-shadow: var(--shadow-lg);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .progress-label {
            font-size: 1rem;
            font-weight: 600;
        }

        .progress-stats {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .progress-bar-container {
            height: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--white);
            border-radius: 9999px;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
        }

        /* Student Info Card */
        .student-info-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            background: var(--gray-50);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--gray-100);
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Main Card */
        .feedback-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            position: relative;
        }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .faculty-info {
            margin-bottom: 1rem;
        }

        .faculty-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .subject-info {
            font-size: 0.9rem;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subject-info i {
            color: var(--primary-color);
        }

        /* Rating Legend */
        .rating-legend {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .legend-title {
            font-weight: 600;
            color: #78350f;
            font-size: 0.875rem;
        }

        .legend-item {
            font-size: 0.8rem;
            font-weight: 500;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .legend-num {
            background: #f59e0b;
            color: white;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.75rem;
        }

        /* Card Body */
        .card-body {
            padding: 1.5rem;
            min-height: 300px;
        }

        .question-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .question-item:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-sm);
        }

        .question-text {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .question-number {
            background: var(--primary-color);
            color: var(--white);
            min-width: 1.75rem;
            height: 1.75rem;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .rating-options {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .rating-option {
            position: relative;
        }

        .rating-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .rating-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.375rem;
            padding: 0.75rem 1rem;
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 4rem;
            user-select: none;
        }

        .rating-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-600);
        }

        .rating-text {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rating-option input:checked + .rating-label {
            background: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .rating-option input:checked + .rating-label .rating-number,
        .rating-option input:checked + .rating-label .rating-text {
            color: var(--white);
        }

        .rating-label:hover {
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        /* Error Message */
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-align: center;
            margin-top: 1rem;
            display: none;
        }

        .error-message i {
            margin-right: 0.5rem;
        }

        /* Footer */
        .card-footer {
            padding: 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .btn {
            padding: 0.625rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 2.5rem;
            text-decoration: none;
        }

        .btn i {
            font-size: 0.875rem;
        }

        /* Small button variant */
        .btn-sm {
            padding: 0.45rem 0.9rem;
            font-size: 0.8125rem;
            min-height: 2rem;
            border-radius: 0.4rem;
            gap: 0.4rem;
        }

        .btn-sm i {
            font-size: 0.75rem;
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover:not(.btn-disabled) {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover:not(.btn-disabled) {
            background: var(--primary-dark);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
        }

        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Loader */
        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            gap: 1rem;
        }

        .spinner {
            width: 3rem;
            height: 3rem;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .loader-text {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        /* No Forms State */
        .no-forms {
            text-align: center;
            padding: 3rem 2rem;
        }

        .no-forms i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .no-forms h3 {
            font-size: 1.25rem;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .no-forms p {
            font-size: 0.95rem;
            color: var(--gray-600);
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }

        /* Animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
            }

            .main-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .rating-options {
                gap: 0.5rem;
            }

            .rating-label {
                min-width: 3.5rem;
                padding: 0.625rem 0.75rem;
            }

            .card-footer {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="feedback.php" class="nav-link active">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Feedback</span>
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
            <h1 class="page-title">Faculty Feedback System</h1>
            <p class="page-subtitle">Provide your valuable feedback to help improve our educational services</p>
        </div>

        <?php if (!$noForm && $form_number): ?>
        <!-- Progress Card -->
        <div class="progress-card">
            <div class="progress-header">
                <div class="progress-label" id="progressLabel">Loading...</div>
                <div class="progress-stats" id="progressStats">0 / 0</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progressBar" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Student Info -->
        <div class="student-info-card">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($department) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Year</div>
                    <div class="info-value"><?= toRoman($year) ?> Year</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Semester</div>
                    <div class="info-value"><?= toRoman($semester) ?> Semester</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Form Number</div>
                    <div class="info-value" style="color: var(--primary-color);"><?= htmlspecialchars($form_number) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feedback Card -->
        <div class="feedback-card">
            <?php if (!$noForm && $form_number): ?>
            <div class="loader-overlay" id="loaderOverlay">
                <div class="spinner"></div>
                <div class="loader-text">Loading feedback form...</div>
            </div>
            <?php endif; ?>

            <?php if ($noForm): ?>
            <div class="card-body">
                <div class="no-forms">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No form avalible</h3>
                    <p>You have completed everything</p>
                    <a href="dashboard.php" class="btn btn-primary btn-sm">Back to Dashboard</a>
                </div>
            </div>
            <?php else: ?>
            <div class="card-header">
                <div class="faculty-info">
                    <h2 class="faculty-name" id="facultyName">Loading...</h2>
                    <div class="subject-info" id="subjectInfo">
                        <i class="fas fa-book"></i>
                        <span>Loading subject information...</span>
                    </div>
                </div>

                <div class="rating-legend">
                    <span class="legend-title">Rating Scale:</span>
                    <span class="legend-item"><span class="legend-num">1</span> Poor</span>
                    <span class="legend-item"><span class="legend-num">2</span> Below Avg</span>
                    <span class="legend-item"><span class="legend-num">3</span> Average</span>
                    <span class="legend-item"><span class="legend-num">4</span> Good</span>
                    <span class="legend-item"><span class="legend-num">5</span> Excellent</span>
                </div>
            </div>

            <div class="card-body">
                <form id="questionsForm"></form>
                <div class="error-message" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"></span>
                </div>
            </div>

            <div class="card-footer">
                <button type="button" class="btn btn-secondary btn-disabled" id="prevBtn">
                    <i class="fas fa-chevron-left"></i>
                    Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn">
                    Next
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if (!$noForm && $form_number): ?>
    <script>
        const formNumber = <?php echo json_encode($form_number); ?>;
        let assignments = [];
        let questions = [];
        let currentIndex = 0;
        const answersByKey = {};

        const loaderOverlay = document.getElementById('loaderOverlay');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const formEl = document.getElementById('questionsForm');
        const progressBar = document.getElementById('progressBar');
        const progressLabel = document.getElementById('progressLabel');
        const progressStats = document.getElementById('progressStats');
        const facultyName = document.getElementById('facultyName');
        const subjectInfo = document.getElementById('subjectInfo');

        function assignmentKey(a) {
            return a.assignment_key || (a.subject_code + '|' + a.faculty_id);
        }

        function showLoader() { loaderOverlay.style.display = 'flex'; }
        function hideLoader() { loaderOverlay.style.display = 'none'; }
        
        function showError(msg) {
            errorText.textContent = msg;
            errorMessage.style.display = 'block';
            setTimeout(() => { errorMessage.style.display = 'none'; }, 5000);
        }
        
        function clearError() { errorMessage.style.display = 'none'; }

        async function loadData() {
            showLoader();
            try {
                const res = await fetch('./api/get_form_data.php?form_number=' + encodeURIComponent(formNumber));
                if (!res.ok) throw new Error('Network error');
                
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                
                assignments = data.assignments || [];
                questions = data.questions || [];
                
                if (assignments.length === 0) {
                    facultyName.textContent = 'No Faculty Assigned';
                    subjectInfo.innerHTML = '<i class="fas fa-info-circle"></i> <span>No assignments found</span>';
                    formEl.innerHTML = '<p style="text-align:center;color:var(--gray-500);padding:2rem;">No feedback assignments available.</p>';
                    prevBtn.classList.add('btn-disabled');
                    nextBtn.classList.add('btn-disabled');
                    return;
                }
                
                renderStep();
            } catch (err) {
                showError(err.message || 'Failed to load data');
                facultyName.textContent = 'Error Loading Data';
                prevBtn.classList.add('btn-disabled');
                nextBtn.classList.add('btn-disabled');
            } finally {
                hideLoader();
            }
        }

        function renderStep() {
            const total = assignments.length;
            const current = currentIndex + 1;
            const a = assignments[currentIndex];
            
            const percentage = (current / total) * 100;
            progressBar.style.width = `${percentage}%`;
            progressLabel.textContent = `Faculty ${current} of ${total}`;
            progressStats.textContent = `${current} / ${total}`;

            facultyName.textContent = a.faculty_name || 'Faculty Name';
            const subjectName = a.subject_name ? `${a.subject_name} ` : '';
            subjectInfo.innerHTML = `<i class="fas fa-book"></i> <span>Subject: ${subjectName}(${a.subject_code})</span>`;

            const key = assignmentKey(a);
            const saved = answersByKey[key] || {};

            formEl.innerHTML = '';
            questions.forEach((q, idx) => {
                const qNum = idx + 1;
                const qName = `q_${q.question_id}`;
                const savedRating = saved[q.question_id]?.rating;

                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-item';
                questionDiv.innerHTML = `
                    <div class="question-text">
                        <span class="question-number">${qNum}</span>
                        <span>${q.text}</span>
                    </div>
                    <div class="rating-options">
                        ${[1, 2, 3, 4, 5].map(val => {
                            const labels = ['Poor', 'Below Avg', 'Average', 'Good', 'Excellent'];
                            return `
                                <div class="rating-option">
                                    <input type="radio" 
                                           name="${qName}" 
                                           value="${val}" 
                                           id="${qName}_${val}"
                                           ${savedRating === val ? 'checked' : ''}>
                                    <label for="${qName}_${val}" class="rating-label">
                                        <span class="rating-number">${val}</span>
                                        <span class="rating-text">${labels[val - 1]}</span>
                                    </label>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
                formEl.appendChild(questionDiv);
            });

            prevBtn.classList.toggle('btn-disabled', currentIndex === 0);
            
            if (currentIndex === total - 1) {
                nextBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
            } else {
                nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
            }
            
            clearError();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function collectCurrentAnswers() {
            const a = assignments[currentIndex];
            const key = assignmentKey(a);
            const ratings = {};
            let allAnswered = true;
            
            questions.forEach(q => {
                const selectedOption = document.querySelector(`input[name="q_${q.question_id}"]:checked`);
                if (!selectedOption) {
                    allAnswered = false;
                } else {
                    ratings[q.question_id] = {
                        rating: parseInt(selectedOption.value, 10),
                        comment: ''
                    };
                }
            });
            
            if (!allAnswered) {
                showError('Please answer all questions before proceeding.');
                return { ok: false };
            }
            
            clearError();
            answersByKey[key] = ratings;
            return { ok: true };
        }

        async function submitAll() {
            showLoader();
            nextBtn.classList.add('btn-disabled');
            prevBtn.classList.add('btn-disabled');

            const answers = [];
            assignments.forEach(a => {
                const key = assignmentKey(a);
                const ratings = answersByKey[key] || {};
                
                questions.forEach(q => {
                    const r = ratings[q.question_id];
                    if (r && typeof r.rating === 'number') {
                        answers.push({
                            assignment_key: key,
                            subject_code: a.subject_code,
                            faculty_id: a.faculty_id,
                            question_id: q.question_id,
                            rating: r.rating,
                            comment: r.comment || ''
                        });
                    }
                });
            });

            try {
                const res = await fetch('./api/submit_feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        form_number: formNumber, 
                        answers: answers 
                    })
                });
                
                if (!res.ok) throw new Error('Network error during submission');
                
                const data = await res.json();
                if (data && data.status === 'ok') {
                    alert('✓ Thank you! Your feedback has been submitted successfully.');
                    window.location.href = './thank_you.php';
                } else {
                    throw new Error(data.error || 'Submission failed. Please try again.');
                }
            } catch (err) {
                showError(err.message || 'An error occurred during submission.');
                nextBtn.classList.remove('btn-disabled');
                prevBtn.classList.remove('btn-disabled');
            } finally {
                hideLoader();
            }
        }

        prevBtn.addEventListener('click', () => {
            if (currentIndex === 0 || prevBtn.classList.contains('btn-disabled')) return;
            collectCurrentAnswers();
            currentIndex -= 1;
            renderStep();
        });

        nextBtn.addEventListener('click', async () => {
            if (nextBtn.classList.contains('btn-disabled')) return;

            const result = collectCurrentAnswers();
            if (!result.ok) return;
            
            if (currentIndex === assignments.length - 1) {
                await submitAll();
                return;
            }
            
            currentIndex += 1;
            renderStep();
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
        });
    </script>
    <?php endif; ?>
</body>
</html>