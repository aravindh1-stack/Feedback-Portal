<?php
// Check the actual structure of the students table
require_once 'config/db.php';

echo "<h2>Students Table Structure</h2>";

// Get table structure
$result = $conn->query("DESCRIBE students");
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td><strong>{$row['Field']}</strong></td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Show sample data
echo "<h2>Sample Student Data</h2>";
$sample = $conn->query("SELECT * FROM students LIMIT 3");
if ($sample->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    
    // Get column names
    $fields = $sample->fetch_fields();
    echo "<tr style='background: #f0f0f0;'>";
    foreach ($fields as $field) {
        echo "<th>{$field->name}</th>";
    }
    echo "</tr>";
    
    // Reset pointer and show data
    $sample->data_seek(0);
    while ($row = $sample->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No students found in the table.";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; }
th, td { padding: 8px 12px; border: 1px solid #ddd; }
th { background: #f0f0f0; font-weight: bold; }
</style>
