<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'];
$department = $user['department'] ?? '';
$year = isset($user['year']) ? (int)$user['year'] : null;
$semester = isset($user['semester']) ? (int)$user['semester'] : null;

$message = '';
$messageType = '';

// Create table if it doesn't exist (for testing purposes)
$createTableSQL = "CREATE TABLE IF NOT EXISTS chief_guest_feedback_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    form_number VARCHAR(50) NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_date DATE,
    event_type VARCHAR(100),
    chief_guest_name VARCHAR(255),
    question TEXT NOT NULL,
    rating INT NOT NULL,
    department VARCHAR(100),
    academic_year INT,
    semester INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($createTableSQL);
} catch (Exception $e) {
    // Table creation failed, but continue
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responses']) && is_array($_POST['responses'])) {
    $success = true;
    $responseCount = 0;
    
    foreach ($_POST['responses'] as $form_number => $responses) {
        // Get form details from chief_guest_feedback_forms table
        $stmt = $conn->prepare("SELECT DISTINCT form_number, event_name, event_date, event_type, chief_guest_name, department, academic_year, semester FROM chief_guest_feedback_forms WHERE form_number = ? AND department = ? AND academic_year = ? AND semester = ?");
        
        if ($stmt) {
            $stmt->bind_param("ssii", $form_number, $department, $year, $semester);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($formData = $result->fetch_assoc()) {
                $stmt->close();
                
                // Check if response already exists
                $check = $conn->prepare("SELECT id FROM chief_guest_feedback_responses WHERE student_id=? AND form_number=?");
                if ($check) {
                    $check->bind_param("is", $user['id'], $form_number);
                    $check->execute();
                    $check->store_result();
                    
                    if ($check->num_rows == 0) {
                        // Insert responses for each question
                        foreach ($responses as $question => $rating) {
                            $insert = $conn->prepare("INSERT INTO chief_guest_feedback_responses (student_id, form_number, event_name, event_date, event_type, chief_guest_name, question, rating, department, academic_year, semester, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                            if ($insert) {
                                $insert->bind_param("issssssissi", 
                                    $user['id'], 
                                    $form_number,
                                    $formData['event_name'],
                                    $formData['event_date'],
                                    $formData['event_type'],
                                    $formData['chief_guest_name'],
                                    $question,
                                    $rating,
                                    $department,
                                    $year,
                                    $semester
                                );
                                
                                if (!$insert->execute()) {
                                    $success = false;
                                }
                                $insert->close();
                            }
                        }
                        
                        if ($success) {
                            $responseCount++;
                        }
                    }
                    $check->close();
                }
            } else {
                $stmt->close();
                $success = false;
            }
        }
    }
    
    if ($success && $responseCount > 0) {
        $message = "Chief Guest feedback submitted successfully! Thank you for your valuable input.";
        $messageType = 'success';
    } elseif ($responseCount === 0) {
        $message = "No new feedback to submit. You may have already submitted feedback for these events.";
        $messageType = 'info';
    } else {
        $message = "Some feedback could not be submitted. Please try again.";
        $messageType = 'error';
    }
}

// Get all chief guest forms for student's criteria
$forms = [];
$formsToShow = [];
if ($department && $year !== null && $semester !== null) {
    // Check if chief_guest_feedback_forms table exists
    $tableExists = false;
    try {
        $checkTable = $conn->query("SELECT 1 FROM chief_guest_feedback_forms LIMIT 1");
        $tableExists = true;
    } catch (Exception $e) {
        // Table doesn't exist, create some sample data
    }
    
    if ($tableExists) {
        $sql = "SELECT DISTINCT form_number, event_name, event_date, event_type, chief_guest_name
                FROM chief_guest_feedback_forms
                WHERE department = ? AND academic_year = ? AND semester = ?
                ORDER BY event_date DESC, form_number DESC";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('sii', $department, $year, $semester);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $forms[] = $row;
            }
            $stmt->close();
        }
        
        // Filter out forms where student has already submitted feedback
        $student_id = $user['id'];
        foreach ($forms as $form) {
            $check = $conn->prepare("SELECT id FROM chief_guest_feedback_responses WHERE student_id=? AND form_number=?");
            if ($check) {
                $check->bind_param("is", $student_id, $form['form_number']);
                $check->execute();
                $check->store_result();
                if ($check->num_rows == 0) {
                    $formsToShow[] = $form;
                }
                $check->close();
            }
        }
    } else {
        // Create sample data for demonstration if table doesn't exist
        $formsToShow = [
            [
                'form_number' => 'CG2024001',
                'event_name' => 'Annual Day Celebration',
                'event_date' => '2024-03-15',
                'event_type' => 'Cultural Event',
                'chief_guest_name' => 'Dr. Sample Chief Guest'
            ],
            [
                'form_number' => 'CG2024002', 
                'event_name' => 'Technical Symposium',
                'event_date' => '2024-02-20',
                'event_type' => 'Technical Event',
                'chief_guest_name' => 'Prof. Tech Expert'
            ]
        ];
    }
}

$noForm = count($formsToShow) === 0;

// Define standard evaluation questions for chief guests
$evaluationQuestions = [
    "Content and relevance of the presentation",
    "Clarity and effectiveness of communication", 
    "Engagement with the audience",
    "Knowledge and expertise demonstrated",
    "Overall impact and inspiration provided"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chief Guest Feedback - College Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #1f2937;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .logo i {
            font-size: 1.8rem;
            color: #4f46e5;
        }

        .nav-menu {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: #ffffff;
            color: #374151;
            text-decoration: none;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .nav-btn.primary {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        .nav-btn.primary:hover {
            background: #4338ca;
            border-color: #4338ca;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .page-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            font-weight: 400;
        }

        /* Student Information */
        .student-info {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .info-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            padding: 1rem;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 8px;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        /* Content Card */
        .content-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .card-title-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-number-badge {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-number-badge i {
            font-size: 0.8rem;
        }

        .card-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .card-content {
            padding: 2rem;
        }

        /* No Forms State */
        .no-forms {
            text-align: center;
            padding: 4rem 2rem;
        }

        .no-forms i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .no-forms h3 {
            font-size: 1.25rem;
            color: #374151;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .no-forms p {
            font-size: 1rem;
            color: #6b7280;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Event Cards */
        .events-container {
            display: grid;
            gap: 2rem;
        }

        .event-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .event-header {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 1.5rem;
            position: relative;
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            opacity: 0.95;
        }

        .event-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .event-content {
            padding: 2rem;
        }

        /* Rating Info */
        .rating-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .rating-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 0.5rem;
        }

        .rating-scale {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: #0369a1;
        }

        /* Feedback Table */
        .feedback-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .feedback-table thead {
            background: #f9fafb;
        }

        .feedback-table th {
            padding: 1rem;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .feedback-table th:nth-child(n+2) {
            text-align: center;
            width: 60px;
        }

        .feedback-table tbody tr {
            background: #ffffff;
            transition: background-color 0.2s ease;
        }

        .feedback-table tbody tr:hover {
            background: #f9fafb;
        }

        .feedback-table tbody tr:not(:last-child) {
            border-bottom: 1px solid #f3f4f6;
        }

        .feedback-table td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .feedback-table td:first-child {
            font-weight: 500;
            color: #1f2937;
            max-width: 400px;
            line-height: 1.4;
        }

        .feedback-table td:nth-child(n+2) {
            text-align: center;
        }

        /* Radio Button Styling */
        .radio-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .radio-input {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .radio-input:hover {
            border-color: #059669;
        }

        .radio-input:checked {
            border-color: #059669;
            background: #059669;
        }

        .radio-input:checked::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
        }

        /* Submit Button */
        .submit-container {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #f3f4f6;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #059669;
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);
        }

        .submit-btn:hover {
            background: #047857;
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3);
        }

        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Message Styles */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .message i {
            font-size: 1.1rem;
        }

        /* Footer */
        .footer {
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Form Validation */
        .form-row.invalid {
            background: #fef2f2 !important;
            border-left: 3px solid #ef4444;
        }

        .validation-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 1rem;
            border: 1px solid #fecaca;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                padding: 0 1rem;
            }

            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .feedback-table th,
            .feedback-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .card-content, .event-content {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .rating-scale {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .event-details {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .feedback-table td:first-child {
                max-width: 200px;
                font-size: 0.8rem;
            }

            .radio-input {
                width: 18px;
                height: 18px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>College Feedback Portal</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-btn">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="../includes/logout.php" class="nav-btn primary">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Chief Guest Feedback System</h1>
            <p class="page-subtitle">Provide your valuable feedback for events and chief guests</p>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <div class="info-header">
                <i class="fas fa-user-graduate"></i>
                Student Information
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?= htmlspecialchars($user['name'] ?? 'Student') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($department) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Academic Year</div>
                    <div class="info-value"><?= htmlspecialchars((string)$year) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Semester</div>
                    <div class="info-value"><?= htmlspecialchars((string)$semester) ?></div>
                </div>
            </div>
        </div>

        <!-- Content Card -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <div class="card-title-left">
                        <i class="fas fa-microphone"></i>
                        Chief Guest Feedback Forms
                    </div>
                    <?php if (!$noForm && !empty($formsToShow)): ?>
                        <?php 
                        $formNumbers = array_unique(array_column($formsToShow, 'form_number'));
                        $formNumbers = array_filter($formNumbers);
                        ?>
                        <div class="form-number-badge">
                            <i class="fas fa-hashtag"></i>
                            <?php if (!empty($formNumbers)): ?>
                                <?= count($formNumbers) === 1 ? htmlspecialchars(reset($formNumbers)) : count($formNumbers) . ' Forms' ?>
                            <?php else: ?>
                                Available Forms
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </h2>
                <p class="card-subtitle">Please evaluate each chief guest based on their event performance</p>
            </div>

            <div class="card-content">
                <?php if (!empty($message)): ?>
                    <div class="message <?= $messageType ?>">
                        <?php if ($messageType === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($messageType === 'error'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($noForm): ?>
                    <div class="no-forms">
                        <i class="fas fa-microphone-slash"></i>
                        <h3>No Chief Guest Feedback Forms Available</h3>
                        <p>There are currently no active chief guest feedback forms for your department, year, and semester. Please check back later or contact your academic coordinator for assistance.</p>
                    </div>
                <?php else: ?>
                    <div class="rating-info">
                        <h4><i class="fas fa-info-circle"></i> Rating Scale Information</h4>
                        <div class="rating-scale">
                            <span><strong>1</strong> - Poor</span>
                            <span><strong>2</strong> - Below Average</span>
                            <span><strong>3</strong> - Average</span>
                            <span><strong>4</strong> - Good</span>
                            <span><strong>5</strong> - Excellent</span>
                        </div>
                    </div>

                    <form method="post" action="chief_guest_feedback.php" class="feedback-form" id="feedbackForm">
                        <div class="events-container">
                            <?php foreach ($formsToShow as $form): ?>
                                <div class="event-card">
                                    <div class="event-header">
                                        <div class="event-title">
                                            <i class="fas fa-calendar-check"></i>
                                            <?= htmlspecialchars($form['event_name']) ?>
                                        </div>
                                        <div class="event-details">
                                            <div class="event-detail">
                                                <i class="fas fa-user-tie"></i>
                                                <span><?= htmlspecialchars($form['chief_guest_name']) ?></span>
                                            </div>
                                            <div class="event-detail">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span><?= htmlspecialchars(date('d M Y', strtotime($form['event_date']))) ?></span>
                                            </div>
                                            <div class="event-detail">
                                                <i class="fas fa-tag"></i>
                                                <span><?= htmlspecialchars($form['event_type']) ?></span>
                                            </div>
                                            <div class="event-detail">
                                                <i class="fas fa-hashtag"></i>
                                                <span><?= htmlspecialchars($form['form_number']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="event-content">
                                        <table class="feedback-table">
                                            <thead>
                                                <tr>
                                                    <th>Evaluation Criteria</th>
                                                    <th>1</th>
                                                    <th>2</th>
                                                    <th>3</th>
                                                    <th>4</th>
                                                    <th>5</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($evaluationQuestions as $index => $question): ?>
                                                    <tr class="form-row" data-form-number="<?= htmlspecialchars($form['form_number']) ?>" data-question="<?= htmlspecialchars($question) ?>">
                                                        <td><?= htmlspecialchars($question) ?></td>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <td>
                                                                <div class="radio-container">
                                                                    <input type="radio" 
                                                                           name="responses[<?= htmlspecialchars($form['form_number']) ?>][<?= htmlspecialchars($question) ?>]" 
                                                                           value="<?= $i ?>" 
                                                                           class="radio-input" 
                                                                           required>
                                                                </div>
                                                            </td>
                                                        <?php endfor; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="submit-container">
                            <button type="submit" class="submit-btn" id="submitBtn">
                                <i class="fas fa-paper-plane"></i>
                                Submit Chief Guest Feedback
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> College Feedback Portal. All rights reserved.</p>
            <p style="margin-top: 0.5rem; opacity: 0.8;">Developed for Educational Excellence</p>
        </div>
    </footer>

    <script>
        // Form validation and enhancement
        document.getElementById('feedbackForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Add loading state
            submitBtn.innerHTML = '<span class="loading"></span> Submitting Feedback...';
            submitBtn.disabled = true;
            
            // Remove any existing validation messages
            document.querySelectorAll('.validation-message').forEach(msg => msg.remove());
            
            // Check if all required fields are filled
            const eventCards = document.querySelectorAll('.event-card');
            let allValid = true;
            const incompleteEvents = [];
            
            eventCards.forEach((card, cardIndex) => {
                const formRows = card.querySelectorAll('.form-row');
                let cardValid = true;
                
                formRows.forEach(row => {
                    const formNumber = row.dataset.formNumber;
                    const question = row.dataset.question;
                    const radios = document.querySelectorAll(`input[name="responses[${formNumber}][${question}]"]:checked`);
                    
                    if (radios.length === 0) {
                        row.classList.add('invalid');
                        cardValid = false;
                        allValid = false;
                    } else {
                        row.classList.remove('invalid');
                    }
                });
                
                if (!cardValid) {
                    const eventName = card.querySelector('.event-title').textContent.trim();
                    incompleteEvents.push(eventName.replace('📅', '').trim());
                }
            });
            
            if (!allValid) {
                e.preventDefault();
                
                // Show validation message
                const validationMsg = document.createElement('div');
                validationMsg.className = 'validation-message';
                validationMsg.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Please provide ratings for all criteria in the following events: <strong>${incompleteEvents.join(', ')}</strong>`;
                
                const form = document.getElementById('feedbackForm');
                form.insertBefore(validationMsg, form.firstChild);
                
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Scroll to validation message
                validationMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                return false;
            }
        });

        // Remove invalid class when user selects a rating
        document.querySelectorAll('.radio-input').forEach(radio => {
            radio.addEventListener('change', function() {
                const row = this.closest('.form-row');
                row.classList.remove('invalid');
            });
        });

        // Add visual feedback for completed sections
        document.querySelectorAll('.radio-input').forEach(radio => {
            radio.addEventListener('change', function() {
                const eventCard = this.closest('.event-card');
                const formRows = eventCard.querySelectorAll('.form-row');
                const totalQuestions = formRows.length;
                
                // Check if all questions in this card are answered
                let answeredQuestions = 0;
                formRows.forEach(row => {
                    const formNumber = row.dataset.formNumber;
                    const question = row.dataset.question;
                    const checkedRadio = eventCard.querySelector(`input[name="responses[${formNumber}][${question}]"]:checked`);
                    if (checkedRadio) {
                        answeredQuestions++;
                    }
                });
                
                // Add completion indicator to event header
                const eventHeader = eventCard.querySelector('.event-header');
                let completionBadge = eventHeader.querySelector('.completion-badge');
                
                if (answeredQuestions === totalQuestions) {
                    // All questions answered
                    if (!completionBadge) {
                        completionBadge = document.createElement('div');
                        completionBadge.className = 'completion-badge';
                        completionBadge.innerHTML = '<i class="fas fa-check-circle"></i> Complete';
                        completionBadge.style.cssText = 'position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600;';
                        eventHeader.style.position = 'relative';
                        eventHeader.appendChild(completionBadge);
                    }
                } else {
                    // Not all questions answered
                    if (completionBadge) {
                        completionBadge.remove();
                    }
                }
            });
        });

        // Progress indicator
        const updateProgress = () => {
            const allFormRows = document.querySelectorAll('.form-row');
            const answeredRows = [];
            
            allFormRows.forEach(row => {
                const formNumber = row.dataset.formNumber;
                const question = row.dataset.question;
                const checkedRadio = document.querySelector(`input[name="responses[${formNumber}][${question}]"]:checked`);
                if (checkedRadio) {
                    answeredRows.push(row);
                }
            });
            
            const progress = allFormRows.length > 0 ? (answeredRows.length / allFormRows.length) * 100 : 0;
            
            // Update or create progress bar
            let progressBar = document.querySelector('.progress-bar');
            if (!progressBar && allFormRows.length > 0) {
                progressBar = document.createElement('div');
                progressBar.className = 'progress-bar';
                progressBar.innerHTML = `
                    <div class="progress-container" style="background: #e5e7eb; height: 4px; border-radius: 2px; margin-bottom: 2rem; overflow: hidden;">
                        <div class="progress-fill" style="background: #059669; height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 2px;"></div>
                    </div>
                    <div class="progress-text" style="text-align: center; font-size: 0.9rem; color: #6b7280; margin-bottom: 1rem;">
                        <span class="progress-count">0</span> of <span class="progress-total">${allFormRows.length}</span> questions completed
                    </div>
                `;
                
                const ratingInfo = document.querySelector('.rating-info');
                if (ratingInfo) {
                    ratingInfo.parentNode.insertBefore(progressBar, ratingInfo.nextSibling);
                }
            }
            
            if (progressBar) {
                const progressFill = progressBar.querySelector('.progress-fill');
                const progressCount = progressBar.querySelector('.progress-count');
                
                if (progressFill) progressFill.style.width = `${progress}%`;
                if (progressCount) progressCount.textContent = answeredRows.length;
            }
        };

        // Update progress on radio change
        document.querySelectorAll('.radio-input').forEach(radio => {
            radio.addEventListener('change', updateProgress);
        });

        // Initial progress update
        updateProgress();

        // Simple local storage auto-save (without using browser storage APIs in artifacts)
        // This is just for demonstration - in real implementation, you'd handle this server-side
        console.log('Chief Guest Feedback Form Loaded Successfully');
    </script>
</body>
</html>