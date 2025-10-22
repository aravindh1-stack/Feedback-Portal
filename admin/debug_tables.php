<?php
// debug_tables.php - Run this to see your table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin login required.");
}

require_once '../config/db.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

echo "<h2>Table Structure Analysis</h2>";

// Check chief_guest_feedback_forms structure
echo "<h3>chief_guest_feedback_forms Structure:</h3>";
$result = $conn->query("DESCRIBE chief_guest_feedback_forms");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Check chief_guest_feedback_responses structure
echo "<h3>chief_guest_feedback_responses Structure:</h3>";
$result = $conn->query("DESCRIBE chief_guest_feedback_responses");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
}

// Show sample data from both tables
echo "<h3>Sample Data from chief_guest_feedback_forms (first 3 rows):</h3>";
$result = $conn->query("SELECT * FROM chief_guest_feedback_forms LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    $first_row = true;
    while ($row = $result->fetch_assoc()) {
        if ($first_row) {
            echo "<tr>";
            foreach (array_keys($row) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            $first_row = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found in chief_guest_feedback_forms</p>";
}

echo "<h3>Sample Data from chief_guest_feedback_responses (first 3 rows):</h3>";
$result = $conn->query("SELECT * FROM chief_guest_feedback_responses LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
    $first_row = true;
    while ($row = $result->fetch_assoc()) {
        if ($first_row) {
            echo "<tr>";
            foreach (array_keys($row) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            $first_row = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found in chief_guest_feedback_responses</p>";
}

$conn->close();
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 1200px; 
    margin: 20px auto; 
    padding: 20px; 
}
table { 
    border-collapse: collapse; 
    width: 100%; 
    margin-bottom: 20px; 
}
th, td { 
    border: 1px solid #ddd; 
    padding: 8px; 
    text-align: left; 
}
th { 
    background-color: #f2f2f2; 
    font-weight: bold; 
}
h2, h3 { 
    color: #333; 
}
</style>