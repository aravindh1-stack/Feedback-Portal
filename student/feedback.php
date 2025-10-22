 <?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

$user = $_SESSION['user'];
$department = $user['department'];
$year = $user['year'];
$semester = $user['semester'];

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responses']) && is_array($_POST['responses'])) {
    $success = true;
    $responseCount = 0;
    
    foreach ($_POST['responses'] as $form_id => $rating) {
        // Get form details
        $stmt = $conn->prepare("SELECT question, faculty_id, subject_code, department, year, semester, form_number FROM feedback_forms WHERE id = ?");
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($formData = $result->fetch_assoc()) {
            $stmt->close();
            
            // If form_number is empty, generate one
            if (empty($formData['form_number'])) {
                $formData['form_number'] = 'FF' . date('Y') . str_pad($form_id, 4, '0', STR_PAD_LEFT);
                // Update the feedback_forms table with the generated form number
                $updateStmt = $conn->prepare("UPDATE feedback_forms SET form_number = ? WHERE id = ?");
                $updateStmt->bind_param("si", $formData['form_number'], $form_id);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            // Check if response already exists
            $check = $conn->prepare("SELECT id FROM feedback_responses WHERE student_id=? AND form_id=?");
            $check->bind_param("ii", $user['id'], $form_id);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows == 0) {
                // Insert new response (without student_name, faculty_name not needed as we have faculty_id)
                $insert = $conn->prepare("INSERT INTO feedback_responses (student_id, form_id, form_number, faculty_id, question, rating, department, year, semester, subject_code, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("isisssiis", 
                    $user['id'], 
                    $form_id, 
                    $formData['form_number'],
                    $formData['faculty_id'], 
                    $formData['question'], 
                    $rating,
                    $formData['department'],
                    $formData['year'],
                    $formData['semester'],
                    $formData['subject_code']
                );
                
                if ($insert->execute()) {
                    $responseCount++;
                } else {
                    $success = false;
                }
                $insert->close();
            }
            $check->close();
        } else {
            $stmt->close();
            $success = false;
        }
    }
    
    if ($success && $responseCount > 0) {
        $message = "Feedback submitted successfully! Thank you for your valuable input.";
        $messageType = 'success';
    } elseif ($responseCount === 0) {
        $message = "No new feedback to submit. You may have already submitted feedback for these forms.";
        $messageType = 'info';
    } else {
        $message = "Some feedback could not be submitted. Please try again.";
        $messageType = 'error';
    }
}

// Get all active feedback forms for student's dept/year/semester
$sql = "SELECT f.*, faculty.name as faculty_name 
        FROM feedback_forms f 
        JOIN faculty ON f.faculty_id = faculty.id 
        WHERE f.department=? AND f.year=? AND f.semester=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $department, $year, $semester);
$stmt->execute();
$allForms = $stmt->get_result();

$formsToShow = [];
$student_id = $user['id'];
foreach ($allForms as $form) {
    $check = $conn->prepare("SELECT id FROM feedback_responses WHERE student_id=? AND form_id=?");
    $check->bind_param("ii", $student_id, $form['id']);
    $check->execute();
    $check->store_result();
    if ($check->num_rows == 0) {
        $formsToShow[] = $form;
    }
    $check->close();
}
$noForm = count($formsToShow) === 0;

// If no faculty forms, check for Chief Guest forms and redirect if available
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
        header('Location: chief_guest_feedback.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback - College Portal</title>
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
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
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

        /* Feedback Table */
        .feedback-form {
            overflow-x: auto;
        }

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

        .feedback-table th:nth-child(n+4) {
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
            font-weight: 600;
            color: #4f46e5;
        }

        .feedback-table td:nth-child(2) {
            font-weight: 500;
            color: #1f2937;
        }

        .feedback-table td:nth-child(3) {
            color: #4b5563;
            max-width: 300px;
            line-height: 1.4;
        }

        .feedback-table td:nth-child(n+4) {
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
            border-color: #4f46e5;
        }

        .radio-input:checked {
            border-color: #4f46e5;
            background: #4f46e5;
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
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #4f46e5;
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }

        .submit-btn:hover {
            background: #4338ca;
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
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

            .card-content {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .rating-scale {
                flex-wrap: wrap;
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .feedback-table td:nth-child(3) {
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
            <h1 class="page-title">Student Feedback System</h1>
            <p class="page-subtitle">Provide your valuable feedback to help improve our educational services</p>
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
                    <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($department) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Academic Year</div>
                    <div class="info-value"><?= htmlspecialchars($year) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Semester</div>
                    <div class="info-value"><?= htmlspecialchars($semester) ?></div>
                </div>
            </div>
        </div>

        <!-- Content Card -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">
                    <div class="card-title-left">
                        <i class="fas fa-clipboard-check"></i>
                        Faculty Feedback Forms
                    </div>
                    <?php if (!$noForm && !empty($formsToShow)): ?>
                        <?php 
                        // Debug: Let's see what's in the forms (disabled)
                        // echo '<pre style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px;">'; 
                        // echo "DEBUG - Forms data:\n";
                        // foreach($formsToShow as $form) {
                        //     echo "ID: {$form['id']}, Form Number: '{$form['form_number']}', Subject: {$form['subject_code']}\n";
                        // }
                        // echo '</pre>';
                        
                        // Get unique form numbers from the forms to show
                        $formNumbers = array_unique(array_column($formsToShow, 'form_number'));
                        $formNumbers = array_filter($formNumbers); // Remove empty values
                        ?>
                        <div class="form-number-badge">
                            <i class="fas fa-hashtag"></i>
                            <?php if (!empty($formNumbers)): ?>
                                <?= count($formNumbers) === 1 ? htmlspecialchars(reset($formNumbers)) : 'Multiple Forms' ?>
                            <?php else: ?>
                                No Form Number
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </h2>
                <p class="card-subtitle">Please evaluate each faculty member based on your experience</p>
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
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Feedback Forms Available</h3>
                        <p>There are currently no active feedback forms for your department, year, and semester. Please check back later or contact your academic coordinator for assistance.</p>
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

                    <form method="post" action="submit_feedback.php" class="feedback-form" id="feedbackForm">
                        <table class="feedback-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Faculty Name</th>
                                    <th>Evaluation Criteria</th>
                                    <th>1</th>
                                    <th>2</th>
                                    <th>3</th>
                                    <th>4</th>
                                    <th>5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($formsToShow as $form): ?>
                                    <tr class="form-row" data-form-id="<?= $form['id'] ?>">
                                        <td><?= htmlspecialchars($form['subject_code']) ?></td>
                                        <td><?= htmlspecialchars($form['faculty_name']) ?></td>
                                        <td><?= htmlspecialchars($form['question']) ?></td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <td>
                                                <div class="radio-container">
                                                    <input type="radio" 
                                                           name="responses[<?= $form['id'] ?>]" 
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

                        <div class="submit-container">
                            <button type="submit" class="submit-btn" id="submitBtn">
                                <i class="fas fa-paper-plane"></i>
                                Submit Feedback
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
            const formRows = document.querySelectorAll('.form-row');
            let allValid = true;
            
            formRows.forEach(row => {
                const formId = row.dataset.formId;
                const radios = document.querySelectorAll(`input[name="responses[${formId}]"]:checked`);
                
                if (radios.length === 0) {
                    row.classList.add('invalid');
                    allValid = false;
                } else {
                    row.classList.remove('invalid');
                }
            });
            
            if (!allValid) {
                e.preventDefault();
                
                // Show validation message
                const validationMsg = document.createElement('div');
                validationMsg.className = 'validation-message';
                validationMsg.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please provide ratings for all faculty members before submitting your feedback.';
                
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
    </script>
</body>
</html