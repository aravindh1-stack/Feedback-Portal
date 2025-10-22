<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

$user = $_SESSION['user'];
$faculty_id = $user['id'];

// Filters
$where = [];
$params = [$faculty_id];
$types = "i";

if (!empty($_GET['department'])) {
    $where[] = "f.department=?";
    $params[] = $_GET['department'];
    $types .= "s";
}
if (!empty($_GET['year'])) {
    $where[] = "f.year=?";
    $params[] = $_GET['year'];
    $types .= "i";
}
if (!empty($_GET['semester'])) {
    $where[] = "f.semester=?";
    $params[] = $_GET['semester'];
    $types .= "i";
}

$where_sql = $where ? " AND " . implode(" AND ", $where) : "";

$sql = "SELECT f.subject_code, f.year, f.semester, f.department, fr.question, fr.rating 
        FROM feedback_responses fr 
        JOIN feedback_forms f ON fr.form_id = f.id 
        WHERE fr.faculty_id=? $where_sql 
        ORDER BY f.subject_code, f.year, f.semester, fr.question";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Group by question
$questions = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['subject_code'].'|'.$row['year'].'|'.$row['semester'].'|'.$row['question'];
    if (!isset($questions[$key])) {
        $questions[$key] = [
            'subject_code' => $row['subject_code'],
            'year' => $row['year'],
            'semester' => $row['semester'],
            'department' => $row['department'],
            'question' => $row['question'],
            1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0
        ];
    }
    $rating = (int)$row['rating'];
    if ($rating >= 1 && $rating <= 5) $questions[$key][$rating]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Feedback Summary - College Feedback Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #1e293b;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            flex-shrink: 0;
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
        }

        .nav-link:hover {
            background: #f8fafc;
            color: #1e293b;
        }

        .nav-link.active {
            background: #3b82f6;
            color: white;
        }

        .nav-link.danger {
            color: #ef4444;
        }

        .nav-link.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Main Content Wrapper */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        /* Faculty Info Card */
        .faculty-info-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-left: 6px solid #3b82f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .faculty-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .faculty-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .faculty-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .faculty-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .faculty-role {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Filters Section */
        .filters-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .filters-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .form-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            transition: all 0.2s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-button {
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .filter-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        /* Feedback Table Section */
        .feedback-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .feedback-count {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Table Styles */
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .feedback-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 1rem 0.75rem;
            text-align: center;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .feedback-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            text-align: center;
        }

        .feedback-table tbody tr:hover {
            background: #f8fafc;
        }

        .question-cell {
            text-align: left !important;
            max-width: 300px;
            font-weight: 500;
        }

        .department-badge {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .rating-cell {
            font-weight: 600;
        }

        .rating-1 { color: #dc2626; }
        .rating-2 { color: #ea580c; }
        .rating-3 { color: #d97706; }
        .rating-4 { color: #65a30d; }
        .rating-5 { color: #16a34a; }

        .total-cell {
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .empty-description {
            font-size: 1rem;
        }

        /* Action Buttons */
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        /* Footer */
        .footer {
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 2rem;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
            margin-top: auto;
            flex-shrink: 0;
        }

        /* Table Responsive */
        .table-container {
            overflow-x: auto;
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

            .filter-form {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .faculty-info-card {
                flex-direction: column;
                text-align: center;
            }

            .section-header {
                flex-direction: column;
                text-align: center;
            }

            .feedback-table {
                font-size: 0.75rem;
            }

            .feedback-table th,
            .feedback-table td {
                padding: 0.5rem 0.25rem;
            }

            .question-cell {
                max-width: 200px;
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
                <span>Faculty Portal</span>
            </a>
            <button class="mobile-menu-button" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
            <nav class="nav-menu" id="navMenu">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="view_feedback.php" class="nav-link active">
                    <i class="fas fa-chart-bar"></i>
                    <span>View Feedback</span>
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
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line"></i>
                    Feedback Summary & Analytics
                </h1>
                <p class="page-subtitle">View detailed feedback responses and ratings for your subjects</p>
            </div>

            <!-- Faculty Info Card -->
            <div class="faculty-info-card">
                <div class="faculty-details">
                    <div class="faculty-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="faculty-info">
                        <div class="faculty-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Faculty Member') ?></div>
                        <div class="faculty-role">Faculty ID: <?= htmlspecialchars($_SESSION['faculty_id'] ?? $_SESSION['user_id'] ?? 'FAC001') ?></div>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-header">
                    <i class="fas fa-filter" style="color: #3b82f6;"></i>
                    <h3 class="filters-title">Filter Feedback Data</h3>
                </div>
                
                <form method="get" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <option value="CSE" <?= isset($_GET['department']) && $_GET['department'] === 'CSE' ? 'selected' : '' ?>>Computer Science (CSE)</option>
                            <option value="ECE" <?= isset($_GET['department']) && $_GET['department'] === 'ECE' ? 'selected' : '' ?>>Electronics & Communication (ECE)</option>
                            <option value="EEE" <?= isset($_GET['department']) && $_GET['department'] === 'EEE' ? 'selected' : '' ?>>Electrical & Electronics (EEE)</option>
                            <option value="MECH" <?= isset($_GET['department']) && $_GET['department'] === 'MECH' ? 'selected' : '' ?>>Mechanical (MECH)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            <?php for ($i=1;$i<=4;$i++): ?>
                                <option value="<?= $i ?>" <?= (isset($_GET['year']) && $_GET['year']==$i)?'selected':''; ?>>Year <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <?php for ($i=1;$i<=8;$i++): ?>
                                <option value="<?= $i ?>" <?= (isset($_GET['semester']) && $_GET['semester']==$i)?'selected':''; ?>>Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="filter-button">
                            <i class="fas fa-search"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Feedback Table -->
            <div class="feedback-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-table"></i>
                        Feedback Details
                    </h3>
                    <?php if (!empty($questions)): ?>
                        <span class="feedback-count"><?= count($questions) ?> Records</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($questions)): ?>
                    <div class="table-container">
                        <table class="feedback-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Subject Code</th>
                                    <th>Year</th>
                                    <th>Semester</th>
                                    <th>Question</th>
                                    <th>1★</th>
                                    <th>2★</th>
                                    <th>3★</th>
                                    <th>4★</th>
                                    <th>5★</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $q): ?>
                                    <tr>
                                        <td>
                                            <span class="department-badge"><?= htmlspecialchars($q['department']) ?></span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($q['subject_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($q['year']) ?></td>
                                        <td><?= htmlspecialchars($q['semester']) ?></td>
                                        <td class="question-cell"><?= htmlspecialchars($q['question']) ?></td>
                                        <?php $total = 0; for ($i=1;$i<=5;$i++) { $total += $q[$i]; ?>
                                            <td class="rating-cell rating-<?= $i ?>"><?= $q[$i] ?></td>
                                        <?php } ?>
                                        <td class="total-cell"><?= $total ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3 class="empty-title">No Feedback Data Available</h3>
                        <p class="empty-description">
                            <?php if (!empty($_GET['department']) || !empty($_GET['year']) || !empty($_GET['semester'])): ?>
                                No feedback data matches your current filter criteria. Try adjusting the filters above.
                            <?php else: ?>
                                No students have submitted feedback for your subjects yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <?php if (!empty($questions)):
                    // Construct the query string for the download link
                    $queryParams = http_build_query([
                        'department' => $_GET['department'] ?? '',
                        'year' => $_GET['year'] ?? '',
                        'semester' => $_GET['semester'] ?? ''
                    ]);
                ?>
                    <a href="download_faculty_report.php?<?= $queryParams ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        Download Report
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>

{{ ... }}
                    container.style.position = 'relative';
                }
            }
        );

    </script>
</body>
</html>