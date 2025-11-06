<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

// Filters
$filter_dept = $_GET['department'] ?? '';
$filter_year = $_GET['year'] ?? '';
$filter_sem = $_GET['semester'] ?? '';
$where = [];

if ($filter_dept !== '') $where[] = "s.department='".$conn->real_escape_string($filter_dept)."'";
if ($filter_year !== '') $where[] = "s.year='".$conn->real_escape_string($filter_year)."'";
if ($filter_sem !== '') $where[] = "s.semester='".$conn->real_escape_string($filter_sem)."'";

$sql = "SELECT DISTINCT s.id, s.name, s.sin_number, s.department, s.year, s.semester 
        FROM students s 
        JOIN feedback_responses fr ON fr.student_id = s.id";
if ($where) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY s.name";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback List - Admin Portal</title>
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
            background: #f8fafc;
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

        /* Main Content Wrapper */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            width: 100%;
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

        /* Filters Section */
        .filters-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .filters-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .form-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
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
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .filter-button:hover {
            background: #2563eb;
        }

        /* Students Table Section */
        .students-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .students-count {
            background: #3b82f6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Table Styles */
        .students-table {
            width: 100%;
            border-collapse: collapse;
        }

        .students-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .students-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .students-table tbody tr:hover {
            background: #f8fafc;
        }

        .student-name {
            font-weight: 600;
            color: #1e293b;
        }

        .sin-number {
            font-family: monospace;
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
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

        .year-semester {
            color: #64748b;
            font-size: 0.875rem;
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .view-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
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

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .empty-description {
            font-size: 0.875rem;
        }

        /* Action Buttons */
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
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

        /* Footer - Fixed at bottom */
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

            .students-table {
                font-size: 0.875rem;
            }

            .students-table th,
            .students-table td {
                padding: 0.75rem 1rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Student Feedback List</h1>
                <p class="page-subtitle">Students who have submitted their feedback responses</p>
            </div>

            <!-- Filters Card -->
            <div class="filters-card">
                <div class="filters-header">
                    <i class="fas fa-filter" style="color: #64748b;"></i>
                    <h3 class="filters-title">Filter Students</h3>
                </div>
                
                <form method="get" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">All Departments</option>
                            <option value="CSE" <?= $filter_dept==='CSE'?'selected':'' ?>>Computer Science (CSE)</option>
                            <option value="ECE" <?= $filter_dept==='ECE'?'selected':'' ?>>Electronics & Communication (ECE)</option>
                            <option value="EEE" <?= $filter_dept==='EEE'?'selected':'' ?>>Electrical & Electronics (EEE)</option>
                            <option value="MECH" <?= $filter_dept==='MECH'?'selected':'' ?>>Mechanical (MECH)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <select name="year" class="form-select">
                            <option value="">All Years</option>
                            <option value="1" <?= $filter_year==='1'?'selected':'' ?>>I Year</option>
                            <option value="2" <?= $filter_year==='2'?'selected':'' ?>>II Year</option>
                            <option value="3" <?= $filter_year==='3'?'selected':'' ?>>III Year</option>
                            <option value="4" <?= $filter_year==='4'?'selected':'' ?>>IV Year</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="">All Semesters</option>
                            <option value="1" <?= $filter_sem==='1'?'selected':'' ?>>I Semester</option>
                            <option value="2" <?= $filter_sem==='2'?'selected':'' ?>>II Semester</option>
                            <option value="3" <?= $filter_sem==='3'?'selected':'' ?>>III Semester</option>
                            <option value="4" <?= $filter_sem==='4'?'selected':'' ?>>IV Semester</option>
                            <option value="5" <?= $filter_sem==='5'?'selected':'' ?>>V Semester</option>
                            <option value="6" <?= $filter_sem==='6'?'selected':'' ?>>VI Semester</option>
                            <option value="7" <?= $filter_sem==='7'?'selected':'' ?>>VII Semester</option>
                            <option value="8" <?= $filter_sem==='8'?'selected':'' ?>>VIII Semester</option>
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

            <!-- Students Table -->
            <div class="students-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        Students
                    </h3>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <span class="students-count"><?= $result->num_rows ?> Students</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>SIN Number</th>
                                <th>Department</th>
                                <th>Year & Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($stu = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="student-name"><?= htmlspecialchars($stu['name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="sin-number"><?= htmlspecialchars($stu['sin_number']) ?></span>
                                    </td>
                                    <td>
                                        <span class="department-badge"><?= htmlspecialchars($stu['department']) ?></span>
                                    </td>
                                    <td>
                                        <div class="year-semester">
                                            <?php
                                            $romanYear = ['', 'I', 'II', 'III', 'IV'][$stu['year']] ?? $stu['year'];
                                            $romanSem = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'][$stu['semester']] ?? $stu['semester'];
                                            echo $romanYear . ' Year, ' . $romanSem . ' Sem';
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a class="view-btn" href="view_student_response.php?student_id=<?= $stu['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                            View Response
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3 class="empty-title">No Students Found</h3>
                        <p class="empty-description">
                            <?php if ($filter_dept || $filter_year || $filter_sem): ?>
                                No students match your current filter criteria. Try adjusting the filters above.
                            <?php else: ?>
                                No students have submitted feedback responses yet.
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
            </div>
        </main>
    </div>

    <!-- Footer -->
    <footer class="footer">
        © <?= date('Y') ?> College Feedback Portal. All rights reserved.<br>
        Enhancing education through continuous feedback and improvement
    </footer>

    <script>
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('active');
        }

        // Add some interactive feedback for the filter form
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filter-form');
            const filterButton = document.querySelector('.filter-button');
            
            filterForm.addEventListener('submit', function() {
                filterButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
            });
        });
    </script>
</body>
</html>