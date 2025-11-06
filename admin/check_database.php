<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

echo "<h2>Database Structure Check</h2>";

// Check feedback_forms table
echo "<h3>1. Feedback Forms:</h3>";
$forms_query = "SELECT * FROM feedback_forms LIMIT 10";
$forms_result = $conn->query($forms_query);

if ($forms_result && $forms_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    
    // Get column names
    $fields = $forms_result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th style='padding: 8px;'>{$field->name}</th>";
    }
    echo "</tr>";
    
    // Reset and show data
    $forms_result->data_seek(0);
    while ($row = $forms_result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td style='padding: 8px;'>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No feedback forms found.";
}

// Check feedback_responses table
echo "<h3>2. Feedback Responses:</h3>";
$responses_query = "SELECT * FROM feedback_responses LIMIT 10";
$responses_result = $conn->query($responses_query);

if ($responses_result && $responses_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    
    // Get column names
    $fields = $responses_result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th style='padding: 8px;'>{$field->name}</th>";
    }
    echo "</tr>";
    
    // Reset and show data
    $responses_result->data_seek(0);
    while ($row = $responses_result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td style='padding: 8px;'>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>No feedback responses found! This is why analytics shows no data.</strong></p>";
}

// Check students table
echo "<h3>3. Students:</h3>";
$students_query = "SELECT id, name, sin_number, department FROM students LIMIT 5";
$students_result = $conn->query($students_query);

if ($students_result && $students_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>Name</th><th style='padding: 8px;'>SIN Number</th><th style='padding: 8px;'>Department</th></tr>";
    
    while ($row = $students_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$row['id']}</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['sin_number']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['department']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No students found.";
}

// Check faculty table
echo "<h3>4. Faculty:</h3>";
$faculty_query = "SELECT id, name, department FROM faculty LIMIT 5";
$faculty_result = $conn->query($faculty_query);

if ($faculty_result && $faculty_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>Name</th><th style='padding: 8px;'>Department</th></tr>";
    
    while ($row = $faculty_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$row['id']}</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['department']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No faculty found.";
}

// Show table structures
echo "<h3>5. Table Structures:</h3>";

echo "<h4>feedback_responses table structure:</h4>";
$structure_query = "DESCRIBE feedback_responses";
$structure_result = $conn->query($structure_query);

if ($structure_result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px;'>Field</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Null</th><th style='padding: 8px;'>Key</th><th style='padding: 8px;'>Default</th></tr>";
    
    while ($row = $structure_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>{$row['Field']}</strong></td>";
        echo "<td style='padding: 8px;'>{$row['Type']}</td>";
        echo "<td style='padding: 8px;'>{$row['Null']}</td>";
        echo "<td style='padding: 8px;'>{$row['Key']}</td>";
        echo "<td style='padding: 8px;'>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3, h4 { color: #333; }
table { width: 100%; max-width: 800px; }
th { background: #f0f0f0; }
</style>

<div style="margin-top: 30px; padding: 20px; background: #e8f4fd; border-radius: 8px;">
    <h3>🔧 Next Steps:</h3>
    <p><strong>If no feedback responses found:</strong></p>
    <ol>
        <li>Students need to submit feedback through the student portal</li>
        <li>OR we can add sample data for testing</li>
        <li>Check if the feedback submission process is working</li>
    </ol>
    
    <p><a href="add_sample_responses.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Add Sample Data for Testing</a></p>
    <p><a href="analytics.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Analytics</a></p>
</div>
