<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if session is properly set
if (!isset($_SESSION['role'])) {
    die("Session not found. Please log in again.");
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Check if database connection file exists
if (!file_exists('../config/db.php')) {
    die("Database configuration file not found.");
}

require_once '../config/db.php';

// Check if database connection was successful
if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

// Initialize variables
$events_result = null;
$stats = ['total_events' => 0, 'total_respondents' => 0, 'total_responses' => 0, 'average_rating' => 0];
$event_stats_result = null;

try {
    // Get all chief guest events for filter
    $events_query = "SELECT DISTINCT 
        event_name, 
        event_date 
    FROM chief_guest_feedback_forms 
    ORDER BY event_date DESC";
    $events_result = $conn->query($events_query);
    
    if (!$events_result) {
        throw new Exception("Error fetching events: " . $conn->error);
    }

    // Get statistics
    $stats_query = "SELECT 
        COUNT(DISTINCT r.event_name) as total_events,
        COUNT(DISTINCT r.student_id) as total_respondents,
        COUNT(*) as total_responses,
        AVG(r.rating) as average_rating
    FROM chief_guest_feedback_responses r";
    
    $stats_result = $conn->query($stats_query);
    
    if ($stats_result && $stats_result->num_rows > 0) {
        $stats = $stats_result->fetch_assoc();
    }

    // Get responses by event
    $event_stats_query = "SELECT 
        r.event_name,
        r.event_date,
        r.chief_guest_name,
        COUNT(DISTINCT r.student_id) as response_count,
        AVG(r.rating) as avg_rating
    FROM chief_guest_feedback_responses r
    WHERE r.event_name IS NOT NULL AND r.event_name != ''
    GROUP BY r.event_name, r.event_date, r.chief_guest_name 
    ORDER BY r.event_date DESC";
    
    $event_stats_result = $conn->query($event_stats_query);
    
    if (!$event_stats_result) {
        throw new Exception("Error fetching event statistics: " . $conn->error);
    }

} catch (Exception $e) {
    // Log the error and show a user-friendly message
    error_log("Chief Guest Report Error: " . $e->getMessage());
    die("An error occurred while loading the report. Error: " . $e->getMessage() . "<br><br>Please check if the chief_guest_feedback_forms and chief_guest_feedback_responses tables exist and have the correct structure.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chief Guest Feedback Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: var(--white);
            padding: 1.5rem 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--gray-900);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .breadcrumb {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card:nth-child(1) .stat-icon { background: #dbeafe; color: var(--primary-color); }
        .stat-card:nth-child(2) .stat-icon { background: #d1fae5; color: var(--success-color); }
        .stat-card:nth-child(3) .stat-icon { background: #fef3c7; color: var(--warning-color); }
        .stat-card:nth-child(4) .stat-icon { background: #fee2e2; color: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .filters-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: var(--white);
            color: var(--gray-900);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success-color);
            color: var(--white);
        }

        .btn-success:hover {
            background: #047857;
        }

        .table-card {
            background: var(--white);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        td {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .rating-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .rating-excellent { background: #dcfce7; color: #166534; }
        .rating-good { background: #dbeafe; color: #1e40af; }
        .rating-average { background: #fef3c7; color: #92400e; }
        .rating-poor { background: #fee2e2; color: #991b1b; }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> Chief Guest Feedback Report</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / Chief Guest Reports
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_events'] ?? 0; ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_respondents'] ?? 0; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_responses'] ?? 0; ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['average_rating'] ?? 0, 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <h3 class="filters-title">Export Reports</h3>
            <form method="GET" action="chief_guest_download_report.php" class="filters-form">
                <div class="form-group">
                    <label class="form-label">Select Event</label>
                    <select name="event_name" class="form-select">
                        <option value="">All Events</option>
                        <?php 
                        if ($events_result && $events_result->num_rows > 0): 
                            // Reset the result pointer to the beginning
                            $events_result->data_seek(0);
                            while($event = $events_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($event['event_name']); ?>">
                                <?php echo htmlspecialchars($event['event_name'] . ' - ' . date('M d, Y', strtotime($event['event_date']))); ?>
                            </option>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <option disabled>No events found</option>
                        <?php 
                        endif; 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download"></i> Download PDF Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Event Statistics Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title">Feedback Statistics by Event</h3>
            </div>
            <div class="table-container">
                <?php if($event_stats_result && $event_stats_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Event Date</th>
                            <th>Chief Guest</th>
                            <th>Response Count</th>
                            <th>Average Rating</th>
                            <th>Rating Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $event_stats_result->fetch_assoc()): ?>
                            <?php
                            $rating = floatval($row['avg_rating']);
                            $rating_class = 'rating-poor';
                            $rating_text = 'Poor';
                            
                            if($rating >= 4.5) {
                                $rating_class = 'rating-excellent';
                                $rating_text = 'Excellent';
                            } elseif($rating >= 3.5) {
                                $rating_class = 'rating-good';
                                $rating_text = 'Good';
                            } elseif($rating >= 2.5) {
                                $rating_class = 'rating-average';
                                $rating_text = 'Average';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['event_name']); ?></strong></td>
                                <td><?php echo $row['event_date'] ? date('M d, Y', strtotime($row['event_date'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['chief_guest_name'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo $row['response_count']; ?></strong> students</td>
                                <td><strong><?php echo number_format($rating, 1); ?></strong>/5.0</td>
                                <td><span class="rating-badge <?php echo $rating_class; ?>"><?php echo $rating_text; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No feedback data found</h3>
                    <p>No chief guest feedback has been submitted yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>