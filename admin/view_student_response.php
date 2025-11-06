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
        JOIN feedback_forms f ON fr.form_id = f.id 
        SET fr.rating=? 
        WHERE fr.student_id=? AND f.subject_code=? AND fr.faculty_id=? AND fr.question=?");
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
$sqstu->bind_result($name, $sin, $dept, $year, $sem);
$sqstu->fetch();
$sqstu->close();

// Fetch responses
$sql = "SELECT f.subject_code, fac.name as faculty_name, f.question, fr.rating 
        FROM feedback_responses fr 
        JOIN feedback_forms f ON fr.form_id = f.id 
        JOIN faculty fac ON fr.faculty_id = fac.id 
        WHERE fr.student_id=? 
        ORDER BY f.subject_code, fac.name, f.question";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$responses = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Response Details - Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #1e293b;
            font-weight: 700;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .logo:hover {
            color: #3b82f6;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .mobile-menu-button {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .mobile-menu-button:hover {
            background: #f1f5f9;
            color: #1e293b;
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
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            background: #f8fafc;
            color: #1e293b;
        }

        .nav-link.active {
            background: #3b82f6;
            color: white;
        }

        .nav-link.active:hover {
            background: #2563eb;
        }

        .nav-link.danger {
            color: #ef4444;
        }

        .nav-link.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .nav-link i {
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-content {
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

        /* Student Profile Section */
        .student-profile {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .profile-header {
            background: #f8fafc;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .profile-info h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .profile-label {
            color: #64748b;
            font-size: 0.875rem;
        }

        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #dcfce7;
            color: #16a34a;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .profile-details {
            padding: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        /* Feedback Responses Section */
        .responses-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Table Styles */
        .responses-table {
            width: 100%;
            border-collapse: collapse;
        }

        .responses-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .responses-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .responses-table tbody tr:hover {
            background: #f8fafc;
        }

        .subject-code {
            font-weight: 600;
            color: #1e293b;
        }

        .faculty-name {
            color: #64748b;
        }

        .question-text {
            color: #64748b;
            line-height: 1.5;
        }

        .rating-value {
            font-weight: 600;
            color: #1e293b;
            text-align: center;
        }

        /* Back Button */
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
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
                gap: 0;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                border-top: 1px solid #e2e8f0;
                padding: 1rem;
            }

            .nav-menu.active {
                display: flex;
            }

            .nav-link {
                justify-content: flex-start;
                padding: 1rem;
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }

            .header-container {
                padding: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .profile-details {
                grid-template-columns: 1fr;
                padding: 1.5rem;
            }

            .responses-table {
                font-size: 0.875rem;
            }

            .responses-table th,
            .responses-table td {
                padding: 0.75rem 1rem;
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Student Response Details</h1>
            <p class="page-subtitle">Detailed feedback responses and ratings overview</p>
        </div>

        <?php
        function toRoman($num) {
            $map = [
                'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
                'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
                'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
            ];
            $return = '';
            foreach ($map as $roman => $int) {
                while($num >= $int) {
                    $return .= $roman;
                    $num -= $int;
                }
            }
            return $return;
        }
        ?>

        <!-- Student Profile -->
        <div class="student-profile">
            <div class="profile-header">
                <div class="profile-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($name) ?></h2>
                    <div class="profile-label">Student Profile</div>
                    <div class="profile-status">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                        Active
                    </div>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-group">
                    <div class="detail-label">SIN Number</div>
                    <div class="detail-value"><?= htmlspecialchars($sin) ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Department</div>
                    <div class="detail-value"><?= htmlspecialchars($dept) ?></div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Academic Year</div>
                    <div class="detail-value"><?= toRoman((int)$year) ?> Year</div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Current Semester</div>
                    <div class="detail-value"><?= toRoman((int)$sem) ?> Semester</div>
                </div>
            </div>
        </div>

        <!-- Feedback Responses -->
        <div class="responses-section">
            <div class="section-header">
                <h3 class="section-title">Feedback Responses</h3>
            </div>
            
            <?php if ($responses->num_rows > 0): ?>
                <table class="responses-table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Faculty Name</th>
                            <th>Question</th>
                            <th style="text-align: center;">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $responses->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="subject-code"><?= htmlspecialchars($row['subject_code']) ?></div>
                                </td>
                                <td>
                                    <div class="faculty-name"><?= htmlspecialchars($row['faculty_name']) ?></div>
                                </td>
                                <td>
                                    <div class="question-text"><?= htmlspecialchars($row['question']) ?></div>
                                </td>
                                <td>
                                    <div class="rating-value"><?= htmlspecialchars($row['rating']) ?>/5</div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Feedback Responses</h3>
                    <p>This student hasn't submitted any feedback responses yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="student_feedback_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Student List
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
    <div class="footer-content">
      <div class="footer-text">&copy; <?php echo date('Y'); ?> College Feedback Portal. All rights reserved.</div>
      <div class="footer-subtext">Enhancing education through continuous feedback and improvement</div>
    </div>
  </footer>

    <script>
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('active');
        }
    </script>
</body>
</html>