<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

echo "<h2>Fix Analytics Data Issues</h2>";

// Check what data exists
echo "<h3>1. Current Database Status:</h3>";

// Check feedback_forms
$forms_count = $conn->query("SELECT COUNT(*) as count FROM feedback_forms")->fetch_assoc()['count'];
echo "<p>📋 Feedback Forms: <strong>{$forms_count}</strong></p>";

// Check feedback_responses  
$responses_count = $conn->query("SELECT COUNT(*) as count FROM feedback_responses")->fetch_assoc()['count'];
echo "<p>💬 Feedback Responses: <strong>{$responses_count}</strong></p>";

// Check students
$students_count = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
echo "<p>👨‍🎓 Students: <strong>{$students_count}</strong></p>";

// Check faculty
$faculty_count = $conn->query("SELECT COUNT(*) as count FROM faculty")->fetch_assoc()['count'];
echo "<p>👩‍🏫 Faculty: <strong>{$faculty_count}</strong></p>";

if ($responses_count == 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
    echo "<h4>⚠️ Issue Found: No Feedback Responses</h4>";
    echo "<p>You have feedback forms but no student responses. This is why analytics shows 'No data found'.</p>";
    echo "<p><strong>Solutions:</strong></p>";
    echo "<ol>";
    echo "<li>Students need to submit feedback through the student portal</li>";
    echo "<li>OR add sample data for testing purposes</li>";
    echo "</ol>";
    echo "<p><a href='add_sample_responses.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Add Sample Data Now</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>";
    echo "<h4>✅ Data Looks Good!</h4>";
    echo "<p>You have feedback responses in the database. Let's check why analytics might not be showing data.</p>";
    echo "</div>";
    
    // Check sample data from responses
    echo "<h3>2. Sample Feedback Responses:</h3>";
    $sample_responses = $conn->query("
        SELECT fr.*, f.subject_code, f.department, f.year, f.semester, s.name as student_name
        FROM feedback_responses fr 
        JOIN feedback_forms f ON fr.form_id = f.id 
        JOIN students s ON fr.student_id = s.id 
        LIMIT 5
    ");
    
    if ($sample_responses && $sample_responses->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>Student</th>";
        echo "<th style='padding: 8px;'>Subject</th>";
        echo "<th style='padding: 8px;'>Department</th>";
        echo "<th style='padding: 8px;'>Year</th>";
        echo "<th style='padding: 8px;'>Semester</th>";
        echo "<th style='padding: 8px;'>Rating</th>";
        echo "</tr>";
        
        while ($row = $sample_responses->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['subject_code']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['department']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['year']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($row['semester']) . "</td>";
            echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($row['rating']) . "/5</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show available filter options
        echo "<h3>3. Available Filter Options:</h3>";
        
        echo "<h4>Departments:</h4>";
        $departments = $conn->query("SELECT DISTINCT department FROM feedback_forms ORDER BY department");
        while ($dept = $departments->fetch_assoc()) {
            echo "<span style='background: #e9ecef; padding: 4px 8px; margin: 2px; border-radius: 4px; display: inline-block;'>" . htmlspecialchars($dept['department']) . "</span> ";
        }
        
        echo "<h4>Years:</h4>";
        $years = $conn->query("SELECT DISTINCT year FROM feedback_forms ORDER BY year");
        while ($year = $years->fetch_assoc()) {
            echo "<span style='background: #e9ecef; padding: 4px 8px; margin: 2px; border-radius: 4px; display: inline-block;'>Year " . htmlspecialchars($year['year']) . "</span> ";
        }
        
        echo "<h4>Semesters:</h4>";
        $semesters = $conn->query("SELECT DISTINCT semester FROM feedback_forms ORDER BY semester");
        while ($sem = $semesters->fetch_assoc()) {
            echo "<span style='background: #e9ecef; padding: 4px 8px; margin: 2px; border-radius: 4px; display: inline-block;'>Semester " . htmlspecialchars($sem['semester']) . "</span> ";
        }
        
        echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>";
        echo "<h4>💡 How to Use Analytics:</h4>";
        echo "<ol>";
        echo "<li>Go to <a href='analytics.php'>Analytics Page</a></li>";
        echo "<li>Select one of the departments, years, or semesters shown above</li>";
        echo "<li>Click 'View Analytics' to see the data</li>";
        echo "<li>Use 'Download Report' to generate PDF reports</li>";
        echo "</ol>";
        echo "</div>";
    }
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3, h4 { color: #333; }
table { border-collapse: collapse; }
th, td { border: 1px solid #ddd; text-align: left; }
th { background-color: #f8f9fa; font-weight: bold; }
</style>

<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
    <h3>🚀 Quick Actions:</h3>
    <p>
        <a href="analytics.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">📊 View Analytics</a>
        <a href="check_database.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;">🔍 Check Database</a>
        <a href="dashboard.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🏠 Dashboard</a>
    </p>
</div>
