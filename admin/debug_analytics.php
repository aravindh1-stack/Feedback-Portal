<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

echo "<h2>Debug Analytics Query</h2>";

// Get the exact same parameters as analytics page
$selected_department = $_GET['department'] ?? 'ECE';
$selected_year = $_GET['year'] ?? '2';
$selected_semester = $_GET['semester'] ?? '3';

echo "<h3>Testing with:</h3>";
echo "<p><strong>Department:</strong> {$selected_department}</p>";
echo "<p><strong>Year:</strong> {$selected_year}</p>";
echo "<p><strong>Semester:</strong> {$selected_semester}</p>";

// Build WHERE conditions exactly like analytics
$where_conditions = [];
$params = [];

if (!empty($selected_department)) {
    $where_conditions[] = "f.department = ?";
    $params[] = $selected_department;
}
if (!empty($selected_year)) {
    $where_conditions[] = "f.year = ?";
    $params[] = $selected_year;
}
if (!empty($selected_semester)) {
    $where_conditions[] = "f.semester = ?";
    $params[] = $selected_semester;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

echo "<h3>SQL Query:</h3>";
$query = "SELECT 
    f.subject_code,
    f.subject_name,
    f.department,
    f.year,
    f.semester,
    COUNT(fr.id) as response_count,
    AVG(fr.rating) as avg_rating,
    COUNT(DISTINCT fr.student_id) as student_count
    FROM feedback_forms f
    LEFT JOIN feedback_responses fr ON f.id = fr.form_id
    $where_clause
    GROUP BY f.id, f.subject_code, f.subject_name, f.department, f.year, f.semester
    ORDER BY avg_rating DESC";

echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo htmlspecialchars($query);
echo "</pre>";

echo "<h3>Parameters:</h3>";
echo "<pre>";
print_r($params);
echo "</pre>";

// Execute the query
try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<h3>Query Results:</h3>";
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>Subject Code</th>";
        echo "<th style='padding: 8px;'>Subject Name</th>";
        echo "<th style='padding: 8px;'>Department</th>";
        echo "<th style='padding: 8px;'>Year</th>";
        echo "<th style='padding: 8px;'>Semester</th>";
        echo "<th style='padding: 8px;'>Responses</th>";
        echo "<th style='padding: 8px;'>Avg Rating</th>";
        echo "<th style='padding: 8px;'>Students</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['subject_code']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['subject_name']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['department']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['year']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['semester']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['response_count']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['avg_rating'] ?? 'NULL') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['student_count']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>No results found!</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Also check what data actually exists
echo "<h3>All Available Data:</h3>";
$all_data_query = "SELECT 
    f.id as form_id,
    f.subject_code,
    f.department,
    f.year,
    f.semester,
    COUNT(fr.id) as response_count
    FROM feedback_forms f
    LEFT JOIN feedback_responses fr ON f.id = fr.form_id
    GROUP BY f.id
    ORDER BY f.department, f.year, f.semester";

$all_result = $conn->query($all_data_query);
if ($all_result && $all_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #e8f4fd;'>";
    echo "<th style='padding: 8px;'>Form ID</th>";
    echo "<th style='padding: 8px;'>Subject Code</th>";
    echo "<th style='padding: 8px;'>Department</th>";
    echo "<th style='padding: 8px;'>Year</th>";
    echo "<th style='padding: 8px;'>Semester</th>";
    echo "<th style='padding: 8px;'>Responses</th>";
    echo "</tr>";
    
    while ($row = $all_result->fetch_assoc()) {
        $highlight = ($row['response_count'] > 0) ? "background: #d4edda;" : "background: #f8d7da;";
        echo "<tr style='{$highlight}'>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['form_id']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['subject_code']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['year']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['semester']) . "</td>";
        echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($row['response_count']) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><span style='background: #d4edda; padding: 2px 6px;'>Green = Has responses</span> <span style='background: #f8d7da; padding: 2px 6px;'>Red = No responses</span></p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; text-align: left; }
</style>

<div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px;">
    <h3>🔍 Quick Tests:</h3>
    <p><a href="debug_analytics.php?department=ECE&year=2&semester=3" style="background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Test ECE Year 2 Semester 3</a></p>
    <p><a href="debug_analytics.php?department=ECE" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Test ECE Only</a></p>
    <p><a href="analytics.php" style="background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Back to Analytics</a></p>
</div>
