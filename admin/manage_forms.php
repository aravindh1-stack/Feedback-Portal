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
        $stmt = $conn->prepare("SELECT question, subject_code, faculty_id FROM feedback_forms WHERE form_number = ? ORDER BY id");
        $stmt->bind_param("s", $form_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = [];
        $facultyMap = [];
        // Get faculty names for mapping
        $facultyRes = $conn->query("SELECT id, name FROM faculty");
        if ($facultyRes) {
            while ($f = $facultyRes->fetch_assoc()) {
                $facultyMap[$f['id']] = $f['name'];
            }
        }
        while ($row = $result->fetch_assoc()) {
            $questions[] = [
                'question_text' => $row['question'],
                'subject_code' => $row['subject_code'],
                'faculty_name' => isset($facultyMap[$row['faculty_id']]) ? $facultyMap[$row['faculty_id']] : $row['faculty_id']
            ];
        }
        $stmt->close();
        echo json_encode([
            'success' => true,
            'form' => [
                'form_number' => $form_number,
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
    $form_id = intval($_POST['delete_form_id']);
    
    try {
        // Start transaction
        $conn->autocommit(FALSE);
        
        // Delete related responses first (foreign key constraint)
        $delete_responses = $conn->prepare("DELETE FROM feedback_responses WHERE form_id = ?");
        if ($delete_responses) {
            $delete_responses->bind_param("i", $form_id);
            $delete_responses->execute();
            $delete_responses->close();
        }
        
        // Delete the form
        $delete_form = $conn->prepare("DELETE FROM feedback_forms WHERE id = ?");
        if (!$delete_form) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $delete_form->bind_param("i", $form_id);
        $delete_form->execute();
        
        if ($delete_form->affected_rows > 0) {
            $conn->commit();
            $message = 'Form deleted successfully!';
        } else {
            $conn->rollback();
            $error_message = 'Form not found or could not be deleted.';
        }
        
        $delete_form->close();
        $conn->autocommit(TRUE);
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        $error_message = 'Error deleting form: ' . $e->getMessage();
        error_log("Delete form error: " . $e->getMessage());
    }
}

// Fetch feedback forms with error handling
try {
    $sql = "SELECT f.*, faculty.name as faculty_name,
                   (SELECT COUNT(*) FROM feedback_responses WHERE form_id = f.id) as response_count
            FROM feedback_forms f 
            INNER JOIN faculty ON f.faculty_id = faculty.id 
            WHERE f.id = (
                SELECT MIN(id) FROM feedback_forms 
                WHERE form_number = f.form_number
            )
            ORDER BY f.created_at DESC, f.department, f.year, f.semester, f.subject_code";
    
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Manage Feedback Forms - Admin Portal</title>
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

     body {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: #f8fafc;
    color: #334155;
    line-height: 1.6;
    /* Add these for sticky footer */
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}
        /* Header */
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: #3b82f6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .mobile-menu-button {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            color: #64748b;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .nav-link.active {
            background: #3b82f6;
            color: white;
        }

        .nav-link.active:hover {
            background: #2563eb;
        }

        .nav-link.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        /* Alert Messages */
        .success-alert {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .success-icon {
            background: #22c55e;
        }

        .error-icon {
            background: #dc2626;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-content h3 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .refresh-btn {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .refresh-btn:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .data-table th:nth-child(2),
        .data-table th:nth-child(3) {
            text-align: center;
        }

        .data-table th:last-child {
            text-align: right;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .data-table td:nth-child(2),
        .data-table td:nth-child(3) {
            text-align: center;
        }

        .data-table td:last-child {
            padding-right: 2rem;
            text-align: right;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .id-badge {
            background: #e0e7ff;
            color: #3730a3;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .response-badge {
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .response-badge.zero {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Buttons */
        .view-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-btn:hover {
            background: #059669;
        }

        .delete-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .no-data-icon {
            width: 64px;
            height: 64px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: #94a3b8;
            font-size: 24px;
        }

        .no-data h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        /* Action Buttons */
        .action-section {
            text-align: center;
            padding: 2rem 0;
        }

        .back-btn {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .back-btn:hover {
            background: #2563eb;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(30, 41, 59, 0.4);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 900px;
            width: 95vw;
            margin: auto;
            padding: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .spinner {
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Footer */
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

    /* Mobile Responsive */
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
      
      .welcome-section {
        margin-bottom: 1.5rem;
      }

      .welcome-title {
        font-size: 1.5rem;
      }

      .welcome-subtitle {
        font-size: 0.875rem;
      }
      
      .profile-card {
        padding: 1.5rem 1rem;
        margin-bottom: 1.5rem;
      }

      .profile-avatar {
        width: 64px;
        height: 64px;
        font-size: 1.5rem;
      }

      .profile-name {
        font-size: 1.25rem;
      }
      
      .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .info-card {
        padding: 1rem;
      }
      
      .action-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .action-card {
        padding: 1.25rem;
      }

      .action-icon {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
      }
      
      .quick-links-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }

      .quick-links {
        padding: 1rem;
      }

      .section-title {
        font-size: 1.125rem;
      }

      .footer {
        padding: 1.5rem 0.75rem;
      }

      .footer-text {
        font-size: 0.75rem;
      }

      .footer-subtext {
        font-size: 0.625rem;
      }
    }
     /* Responsive */
        @media (max-width: 768px) {
            .header-container {
                padding: 1rem;
            }

            .mobile-menu-button {
                display: block;
            }

            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                border-top: 1px solid #e2e8f0;
                padding: 1rem;
                gap: 0.5rem;
            }

            .nav-menu.active {
                display: flex;
            }

            .nav-link {
                width: 100%;
                justify-content: flex-start;
                padding: 0.75rem 1rem;
            }

            .container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .table-card {
                border-radius: 8px;
            }

            .table-header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 1rem;
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
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span>Admin Portal</span>
            </a>
            <button class="mobile-menu-button" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <nav class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="../includes/logout.php" class="nav-link danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Manage Feedback Forms</h1>
                <p class="page-subtitle">View and manage all feedback forms in the system. Delete forms that are no longer needed.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="success-alert">
                    <div class="alert-icon success-icon">✓</div>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-alert">
                    <div class="alert-icon error-icon">✕</div>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dbeafe; color: #1d4ed8;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count($forms) ?></h3>
                        <p>Total Forms</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= array_sum(array_column($forms, 'response_count')) ?></h3>
                        <p>Total Responses</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_unique(array_column($forms, 'faculty_id'))) ?></h3>
                        <p>Unique Faculty</p>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h2 class="table-title">All Feedback Forms</h2>
                    <div class="table-actions">
                        <button class="refresh-btn" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <?php if (count($forms) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Form Details</th>
                                    <th>Responses</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($forms as $form): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <span class="id-badge">
                                                    <?= htmlspecialchars($form['form_number']) ?>
                                                </span>
                                            </div>
                                            <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #64748b;">
                                                <?= htmlspecialchars($form['department']) ?> | Year <?= $form['year'] ?> | Sem <?= $form['semester'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="response-badge <?= $form['response_count'] == 0 ? 'zero' : '' ?>">
                                                <?= $form['response_count'] ?> responses
                                            </span>
                                        </td>
                                        <td style="font-size: 0.75rem; color: #64748b;">
                                            <?= date('M j, Y', strtotime($form['created_at'])) ?><br>
                                            <?= date('g:i A', strtotime($form['created_at'])) ?>
                                        </td>
                                        <td>
                                            <button class="view-btn view-form-btn" 
                                                data-form-number="<?= htmlspecialchars($form['form_number']) ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="delete_form_id" value="<?= $form['id'] ?>">
                                                <button type="submit" class="delete-btn" 
                                                    onclick="return confirm('Are you sure you want to delete this form?\n\nForm: <?= htmlspecialchars($form['form_number']) ?>\nFaculty: <?= htmlspecialchars($form['faculty_name']) ?>\nResponses: <?= $form['response_count'] ?>\n\nThis action cannot be undone.');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <div class="no-data-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No Forms Found</h3>
                        <p>There are currently no feedback forms in the system.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-section">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Modal for Form Details -->
    <div id="formModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div id="modalContent" class="loading">
                <i class="fas fa-spinner spinner"></i> Loading form details...
            </div>
        </div>
    </div>

    <!-- Footer -->
     <footer class="footer">
    <div class="footer-content">
      <div class="footer-text">&copy; <?php echo date('Y'); ?> College Feedback Portal. All rights reserved.</div>
      <div class="footer-subtext">Enhancing education through continuous feedback and improvement</div>
    </div>
  </footer>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('navMenu');
            menu.classList.toggle('active');
        }

        function openModal() {
            document.getElementById('formModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('formModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('formModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Handle view form button clicks
        document.querySelectorAll('.view-form-btn').forEach(button => {
            button.addEventListener('click', function() {
                const formNumber = this.dataset.formNumber;
                loadFormDetails(formNumber);
            });
        });

        function loadFormDetails(formNumber) {
            openModal();
            
            fetch(`?action=get_form_details&form_number=${encodeURIComponent(formNumber)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFormDetails(data.form);
                    } else {
                        document.getElementById('modalContent').innerHTML = `
                            <div style="text-align: center; color: #dc2626;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <h3>Error Loading Form</h3>
                                <p>${data.error}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalContent').innerHTML = `
                        <div style="text-align: center; color: #dc2626;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <h3>Error Loading Form</h3>
                            <p>An error occurred while loading the form details.</p>
                        </div>
                    `;
                });
        }

        function displayFormDetails(form) {
            let questionsHtml = '';
            if (form.questions && form.questions.length > 0) {
                questionsHtml = form.questions.map((q, index) => `
                    <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; margin-bottom: 0.75rem; border-left: 4px solid #3b82f6;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="background: #3b82f6; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 600; flex-shrink: 0;">${index + 1}</span>
                            <p style="margin: 0; font-size: 0.95rem; line-height: 1.5; color: #1e293b;">${q.question_text}</p>
                        </div>
                    </div>
                `).join('');
            } else {
                questionsHtml = '<p style="color: #64748b; font-style: italic; text-align: center; padding: 2rem;">No questions found for this form.</p>';
            }

            document.getElementById('modalContent').innerHTML = `
                <div>
                    <div style="text-align: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="color: #1e293b; margin-bottom: 0.5rem;">Feedback Questions</h2>
                        <p style="color: #64748b; font-size: 0.875rem;">Form: <strong>${form.form_number}</strong> • ${form.total_questions} Questions</p>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                        ${questionsHtml}
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                        <button onclick="closeModal()" style="background: #3b82f6; color: white; border: none; padding: 0.75rem 2rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background-color 0.2s;">
                            Close
                        </button>
                    </div>
                </div>
            `;
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>



