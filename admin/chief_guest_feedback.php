<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// Generate unique form number
function generateFormNumber() {
    return 'CGF' . date('Y') . sprintf('%04d', rand(1000, 9999));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = $_POST['event_name'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $chief_guest_name = $_POST['chief_guest_name'] ?? '';
    $chief_guest_designation = $_POST['chief_guest_designation'] ?? '';
    $questions = $_POST['questions'] ?? [];
    $form_number = $_POST['form_number'] ?? '';
    $target_audience = $_POST['target_audience'] ?? '';
    $department = $_POST['department'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    $semester = $_POST['semester'] ?? '';
    
    if (!empty($event_name) && !empty($event_date) && !empty($event_type) && !empty($department) && !empty($academic_year) && !empty($semester) && !empty($chief_guest_name) && !empty($questions) && !empty($form_number)) {
        try {
            $conn->begin_transaction();
            
            foreach ($questions as $question) {
                if (!empty($question['text']) && !empty($question['category'])) {
                    $stmt = $conn->prepare("INSERT INTO chief_guest_feedback_forms (form_number, event_name, event_date, event_type, chief_guest_name, chief_guest_designation, target_audience, department, academic_year, semester, question_category, question_text, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssssssssisss", $form_number, $event_name, $event_date, $event_type, $chief_guest_name, $chief_guest_designation, $target_audience, $department, $academic_year, $semester, $question['category'], $question['text']);
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            $success_message = "Chief Guest feedback form created successfully! Form Number: " . $form_number;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error creating form: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill all required fields.";
    }
}

// Generate form number for new form
$current_form_number = generateFormNumber();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Chief Guest Feedback Form - Admin Portal</title>
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
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* HEADER */
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

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }

        /* FORM CARD */
        .form-card {
            background: white;
            border-radius: 0.75rem;
            border: 2px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #8b5a3c 0%, #6b4226 100%);
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }

        .form-number-display {
            position: absolute;
            top: 2rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-number-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .form-number-value {
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.25);
            padding: 0.2rem 0.6rem;
            border-radius: 0.3rem;
            letter-spacing: 0.5px;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-right: 200px;
        }

        .card-description {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            margin-right: 200px;
        }

        .card-body {
            padding: 2rem;
        }

        /* FORM ELEMENTS */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
            color: var(--gray-900);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* QUESTION CARDS */
        .questions-section {
            margin-top: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .question-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .question-number {
            background: #8b5a3c;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .remove-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remove-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .question-fields {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 1fr 200px;
        }

        .textarea-group {
            position: relative;
        }

        .sample-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .sample-btn:hover {
            background: #047857;
        }

        /* CATEGORY COLORS */
        .category-presentation { border-left: 4px solid #3b82f6; }
        .category-content { border-left: 4px solid #10b981; }
        .category-engagement { border-left: 4px solid #f59e0b; }
        .category-overall { border-left: 4px solid #8b5cf6; }
        .category-relevance { border-left: 4px solid #ef4444; }

        /* ACTION BUTTONS */
        .add-question-btn {
            width: 100%;
            background: var(--success-color);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            margin: 2rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .add-question-btn:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .add-question-btn:active {
            transform: translateY(0);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #8b5a3c;
            color: white;
        }

        .btn-primary:hover {
            background: #6b4226;
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        /* FOOTER */
        .footer {
            background: var(--white);
            color: var(--gray-600);
            font-size: 0.875rem;
            text-align: center;
            padding: 2rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            margin-top: auto;
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
        }

        .footer-text {
            margin-bottom: 0.5rem;
        }

        .footer-subtext {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        /* ALERT MESSAGES */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        .alert-success::before {
            content: "✓";
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .alert-error::before {
            content: "✕";
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* EVENT INFO CARD */
        .event-info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 1px solid var(--gray-300);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .event-info-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* RESPONSIVE */
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

            .main-content {
                padding: 1rem 0.75rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .question-fields {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .question-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .form-number-display {
                position: static;
                margin-bottom: 1rem;
                align-self: flex-end;
            }
            
            .card-title,
            .card-description {
                margin-right: 0;
            }
            
            .card-header {
                display: flex;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span>Chief Guest Portal</span>
            </a>
            
            <button class="mobile-menu-button" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-link active">
                    <i class="fas fa-user-tie"></i>
                    <span>Chief Guest Forms</span>
                </a>
                <a href="../includes/logout.php" class="nav-link danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Create Chief Guest Feedback Form</h1>
            <p class="page-subtitle">Design comprehensive feedback forms to evaluate chief guests and improve event experiences.</p>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
        </div>

        <!-- Form Card -->
        <div class="form-card" id="formCard">
            <div class="card-header">
                <div class="form-number-display">
                    <span class="form-number-label">Form Number:</span>
                    <span class="form-number-value"><?= htmlspecialchars($current_form_number) ?></span>
                </div>
                <div class="card-title">
                    <i class="fas fa-user-tie"></i>
                    Chief Guest Feedback Form Builder
                </div>
                <div class="card-description">
                    Create detailed feedback forms for chief guest evaluation and event assessment
                </div>
            </div>

            <div class="card-body">
                <form id="chiefGuestForm" method="post">
                    <input type="hidden" name="form_number" value="<?= htmlspecialchars($current_form_number) ?>">
                    
                    <!-- Event Information -->
                    <div class="event-info-card">
                        <div class="event-info-title">
                            <i class="fas fa-calendar-alt"></i>
                            Event Information
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Event Name *</label>
                                <input type="text" class="form-input" name="event_name" id="event_name" placeholder="e.g., Annual Day Celebration, Technical Symposium" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Date *</label>
                                <input type="date" class="form-input" name="event_date" id="event_date" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Type *</label>
                                <select class="form-select" name="event_type" id="event_type" required>
                                    <option value="">Select Event Type</option>
                                    <option value="Cultural Event">Cultural Event</option>
                                    <option value="Technical Symposium">Technical Symposium</option>
                                    <option value="Annual Day">Annual Day</option>
                                    <option value="Graduation Ceremony">Graduation Ceremony</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="Conference">Conference</option>
                                    <option value="Sports Event">Sports Event</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Target Audience *</label>
                                <select class="form-select" name="target_audience" id="target_audience" required>
                                    <option value="">Select Audience</option>
                                    <option value="All Students">All Students</option>
                                    <option value="Final Year Students">Final Year Students</option>
                                    <option value="Engineering Students">Engineering Students</option>
                                    <option value="Department Specific">Department Specific</option>
                                    <option value="Faculty and Students">Faculty and Students</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department" id="department" required>
                                    <option value="">Select Department</option>
                                    <option value="CSE">CSE</option>
                                    <option value="IT">IT</option>
                                    <option value="ECE">ECE</option>
                                    <option value="EEE">EEE</option>
                                    <option value="MECH">MECH</option>
                                    <option value="CIVIL">CIVIL</option>
                                    <option value="AIML">AIML</option>
                                    <option value="MBA">MBA</option>
                                    <option value="MCA">MCA</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-select" name="academic_year" id="academic_year" required>
                                    <option value="">Select Year</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Semester *</label>
                                <select class="form-select" name="semester" id="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                </select>
                            </div>

                            <div class="form-group form-group-full">
                                <label class="form-label">Chief Guest Name *</label>
                                <input type="text" class="form-input" name="chief_guest_name" id="chief_guest_name" placeholder="Full name of the chief guest" required>
                            </div>

                            <div class="form-group form-group-full">
                                <label class="form-label">Chief Guest Designation</label>
                                <input type="text" class="form-input" name="chief_guest_designation" id="chief_guest_designation" placeholder="e.g., CEO of XYZ Company, Former Director of ABC Institute">
                            </div>
                        </div>
                    </div>

                    <!-- Questions Section -->
                    <div class="questions-section">
                        <h3 class="section-title">
                            <i class="fas fa-question-circle"></i>
                            Feedback Questions
                        </h3>

                        <div id="questionsContainer">
                            <!-- Question cards will be generated dynamically by JS -->
                        </div>

                        <button type="button" class="add-question-btn" id="addQuestionBtn" onclick="handleAddQuestion()">
                            <i class="fas fa-plus"></i>
                            Add Another Question
                        </button>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="previewForm()">
                            <i class="fas fa-eye"></i>
                            Preview Form
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i>
                            Create Chief Guest Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-text">&copy; 2025 Chief Guest Feedback Portal. All rights reserved.</div>
            <div class="footer-subtext">Enhancing event experiences through valuable feedback and continuous improvement</div>
        </div>
    </footer>

    <script>
        // Global variables
        let questionCount = 0;
        
        // Sample questions for different categories
        const sampleQuestions = {
            'presentation': [
                'How would you rate the clarity of the chief guest\'s presentation?',
                'Was the presentation well-structured and easy to follow?',
                'How effectively did the chief guest use visual aids or examples?',
                'Rate the overall delivery and speaking skills of the chief guest'
            ],
            'content': [
                'How relevant was the content to your field of study?',
                'Did the chief guest provide valuable insights and knowledge?',
                'How practical and applicable were the shared experiences?',
                'Rate the depth and quality of the subject matter discussed'
            ],
            'engagement': [
                'How well did the chief guest engage with the audience?',
                'Was the chief guest approachable and interactive?',
                'How effectively did the chief guest handle questions and discussions?',
                'Rate the chief guest\'s ability to connect with students'
            ],
            'relevance': [
                'How relevant was the speech to current industry trends?',
                'Did the content inspire you toward your career goals?',
                'How useful were the career guidance and advice provided?',
                'Rate the motivational impact of the chief guest\'s message'
            ],
            'overall': [
                'What is your overall impression of the chief guest?',
                'Would you recommend this chief guest for future events?',
                'How would you rate the overall event experience?',
                'Did the chief guest meet your expectations?'
            ]
        };

        // Add new question function
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            if (!container) {
                console.error('Questions container not found!');
                return;
            }

            const questionHTML = `
                <div class="question-card" data-index="${questionCount}">
                    <div class="question-header">
                        <span class="question-number">
                            <i class="fas fa-hashtag"></i> Question ${questionCount + 1}
                        </span>
                        <button type="button" class="remove-btn" onclick="removeQuestion(${questionCount})">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                    <div class="question-fields">
                        <div class="form-group">
                            <label class="form-label">Question Text *</label>
                            <div class="textarea-group">
                                <textarea class="form-textarea" name="questions[${questionCount}][text]" placeholder="Enter your feedback question about the chief guest..." required></textarea>
                                <button type="button" class="sample-btn" onclick="addSampleQuestion(${questionCount})" title="Add sample question">
                                    <i class="fas fa-lightbulb"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Question Category *</label>
                            <select class="form-select" name="questions[${questionCount}][category]" onchange="updateQuestionCardStyle(${questionCount}, this.value)" required>
                                <option value="">Select Category</option>
                                <option value="presentation">Presentation Skills</option>
                                <option value="content">Content Quality</option>
                                <option value="engagement">Audience Engagement</option>
                                <option value="relevance">Relevance & Impact</option>
                                <option value="overall">Overall Experience</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHTML);
            questionCount++;
            updateQuestionNumbers();
        }

        // Remove question function
        function removeQuestion(index) {
            const questionCard = document.querySelector(`[data-index="${index}"]`);
            if (questionCard) {
                questionCard.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    questionCard.remove();
                    updateQuestionNumbers();
                }, 300);
            }
        }

        // Update question numbers
        function updateQuestionNumbers() {
            const questionCards = document.querySelectorAll('.question-card');
            questionCards.forEach((card, index) => {
                const numberSpan = card.querySelector('.question-number');
                if (numberSpan) {
                    numberSpan.innerHTML = `<i class="fas fa-hashtag"></i> Question ${index + 1}`;
                }
            });
        }

        // Update question card style based on category
        function updateQuestionCardStyle(index, category) {
            const questionCard = document.querySelector(`[data-index="${index}"]`);
            if (questionCard) {
                // Remove existing category classes
                questionCard.classList.remove('category-presentation', 'category-content', 'category-engagement', 'category-relevance', 'category-overall');
                
                // Add new category class
                if (category) {
                    questionCard.classList.add(`category-${category}`);
                }
            }
        }

        // Add sample question
        function addSampleQuestion(index) {
            const categorySelect = document.querySelector(`[data-index="${index}"] select[name*="[category]"]`);
            const category = categorySelect ? categorySelect.value : '';
            
            if (category && sampleQuestions[category]) {
                const samples = sampleQuestions[category];
                const randomQuestion = samples[Math.floor(Math.random() * samples.length)];
                const textarea = document.querySelector(`[data-index="${index}"] textarea`);
                
                if (textarea) {
                    textarea.value = randomQuestion;
                } else {
                    console.error('Textarea not found for index:', index);
                }
            } else {
                alert('Please select a category first to get sample questions.');
            }
        }

        // Handle add question button click
        function handleAddQuestion() {
            addQuestion();
        }

        // Preview Form Modal
        function previewForm() {
            const existingModal = document.getElementById('previewModal');
            if (existingModal) existingModal.remove();

            const formNumber = document.querySelector('input[name="form_number"]').value;
            const eventName = document.getElementById('event_name').value;
            const eventDate = document.getElementById('event_date').value;
            const eventType = document.getElementById('event_type').value;
            const chiefGuestName = document.getElementById('chief_guest_name').value;
            const chiefGuestDesignation = document.getElementById('chief_guest_designation').value;
            const targetAudience = document.getElementById('target_audience').value;
            
            const questions = Array.from(document.querySelectorAll('.question-card')).map(card => {
                const text = card.querySelector('textarea')?.value || '';
                const categorySelect = card.querySelector('select[name*="[category]"]');
                const category = categorySelect ? categorySelect.value : '';
                const categoryText = categorySelect ? categorySelect.options[categorySelect.selectedIndex]?.text || '' : '';
                return { text, category, categoryText };
            });

            const modalHtml = `
                <div id="previewModal" class="preview-modal-overlay">
                    <div class="preview-modal">
                        <div class="preview-modal-header">
                            <span class="preview-modal-title"><i class='fas fa-user-tie'></i> Chief Guest Feedback Form Preview</span>
                            <button class="preview-modal-close" onclick="document.getElementById('previewModal').remove()">&times;</button>
                        </div>
                        <div class="preview-modal-body">
                            <div class="preview-section">
                                <h4>Form Information</h4>
                                <ul class="preview-list">
                                    <li><strong>Form Number:</strong> <span style="font-family: 'Courier New', monospace; background: #e0e7ff; padding: 2px 6px; border-radius: 3px; color: #2563eb;">${formNumber}</span></li>
                                    <li><strong>Event Name:</strong> ${eventName || '<em>Not specified</em>'}</li>
                                    <li><strong>Event Date:</strong> ${eventDate || '<em>Not specified</em>'}</li>
                                    <li><strong>Event Type:</strong> ${eventType || '<em>Not selected</em>'}</li>
                                    <li><strong>Chief Guest:</strong> ${chiefGuestName || '<em>Not specified</em>'}</li>
                                    <li><strong>Designation:</strong> ${chiefGuestDesignation || '<em>Not specified</em>'}</li>
                                    <li><strong>Target Audience:</strong> ${targetAudience || '<em>Not selected</em>'}</li>
                                </ul>
                            </div>
                            <div class="preview-section">
                                <h4>Feedback Questions</h4>
                                <ol class="preview-questions-list">
                                    ${questions.map((q, i) => `
                                        <li>
                                            <div><strong>Question ${i+1}:</strong> ${q.text || '<em>No question text</em>'}</div>
                                            <div><strong>Category:</strong> <span style="background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 0.85em;">${q.categoryText || '<em>Not selected</em>'}</span></div>
                                        </li>
                                    `).join('')}
                                </ol>
                            </div>
                        </div>
                        <div class="preview-modal-footer">
                            <button class="preview-btn" onclick="document.getElementById('previewModal').remove()">Close Preview</button>
                        </div>
                    </div>
                </div>
                <style>
                .preview-modal-overlay {
                    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                    background: rgba(30, 41, 59, 0.55); z-index: 2000;
                    display: flex; align-items: center; justify-content: center;
                }
                .preview-modal {
                    background: #fff; border-radius: 1rem; box-shadow: 0 8px 32px #0003;
                    max-width: 700px; width: 98vw; max-height: 90vh; overflow-y: auto;
                    position: relative; animation: fadeIn 0.3s;
                }
                .preview-modal-header {
                    display: flex; justify-content: space-between; align-items: center;
                    border-bottom: 1px solid #e5e7eb; padding: 1.5rem 2rem; background: #8b5a3c; color: white;
                    border-radius: 1rem 1rem 0 0;
                }
                .preview-modal-title { font-size: 1.15rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
                .preview-modal-close {
                    background: none; border: none; font-size: 2rem; color: rgba(255, 255, 255, 0.8); cursor: pointer;
                    line-height: 1; transition: color 0.15s;
                }
                .preview-modal-close:hover { color: #fef2f2; }
                .preview-modal-body { padding: 2rem; }
                .preview-modal-footer { 
                    padding: 1rem 2rem; border-top: 1px solid #e5e7eb; 
                    background: #f8fafc; text-align: center; border-radius: 0 0 1rem 1rem;
                }
                .preview-btn {
                    background: #8b5a3c; color: white; border: none; padding: 0.5rem 1.5rem;
                    border-radius: 0.5rem; cursor: pointer; font-weight: 500;
                }
                .preview-btn:hover { background: #6b4226; }
                .preview-section { margin-bottom: 2rem; }
                .preview-section h4 { margin-bottom: 1rem; color: #374151; font-size: 1.1rem; font-weight: 600; }
                .preview-list { list-style: none; padding: 0; margin: 0 0 0.5rem 0; }
                .preview-list li { margin-bottom: 0.5rem; font-size: 0.95rem; }
                .preview-questions-list { padding-left: 1.2rem; }
                .preview-questions-list li { margin-bottom: 1.2rem; background: #f8fafc; border-radius: 0.5rem; padding: 1rem; }
                @media (max-width: 700px) {
                    .preview-modal { margin: 1rem 0.5rem; max-height: calc(100vh - 2rem); }
                    .preview-modal-header, .preview-modal-body, .preview-modal-footer { padding: 1rem; }
                }
                </style>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('mobile-open');
        }

        // Form validation
        function validateForm() {
            const requiredFields = document.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc2626';
                    isValid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            return isValid;
        }

        // Initialize form on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add first question automatically
            addQuestion();
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').min = today;
            
            // Add form validation on submit
            document.getElementById('chiefGuestForm').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('Please fill all required fields before submitting.');
                }
            });
            
            console.log('Chief Guest Feedback Form initialized successfully');
        });

        // Auto-save form data to localStorage (optional enhancement)
        function autoSaveForm() {
            const formData = new FormData(document.getElementById('chiefGuestForm'));
            const formObject = Object.fromEntries(formData.entries());
            localStorage.setItem('chiefGuestFormData', JSON.stringify(formObject));
        }

        // Load saved form data
        function loadSavedForm() {
            const savedData = localStorage.getItem('chiefGuestFormData');
            if (savedData) {
                const formObject = JSON.parse(savedData);
                // Populate form fields with saved data
                Object.keys(formObject).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field) {
                        field.value = formObject[key];
                    }
                });
            }
        }

        // Clear saved form data after successful submission
        function clearSavedForm() {
            localStorage.removeItem('chiefGuestFormData');
        }
    </script>

</body>
</html>