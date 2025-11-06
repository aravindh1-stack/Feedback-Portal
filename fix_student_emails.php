<?php
// Quick fix script to add email addresses to students
require_once 'config/db.php';

try {
    // First, let's see which students don't have emails
    $check_sql = "SELECT id, name, stu_number, email FROM students WHERE email IS NULL OR email = ''";
    $result = $conn->query($check_sql);
    
    echo "<h2>Students without email addresses:</h2>";
    $students_to_update = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "ID: {$row['id']}, Name: {$row['name']}, Student Number: {$row['stu_number']}, Email: " . ($row['email'] ?: 'NULL') . "<br>";
            $students_to_update[] = $row;
        }
        
        echo "<br><h2>Updating emails...</h2>";
        
        // Update emails using student number + @student.edu
        foreach ($students_to_update as $student) {
            $email = strtolower($student['stu_number']) . '@student.edu';
            $update_sql = "UPDATE students SET email = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param('si', $email, $student['id']);
            
            if ($stmt->execute()) {
                echo "✅ Updated {$student['name']} ({$student['stu_number']}) with email: {$email}<br>";
            } else {
                echo "❌ Failed to update {$student['name']}: " . $stmt->error . "<br>";
            }
            $stmt->close();
        }
        
        echo "<br><h2>Final check - All students with emails:</h2>";
        $final_check = $conn->query("SELECT name, stu_number, email FROM students ORDER BY name");
        while ($row = $final_check->fetch_assoc()) {
            echo "{$row['name']} ({$row['stu_number']}) - {$row['email']}<br>";
        }
        
    } else {
        echo "All students already have email addresses!<br>";
        
        // Show all students
        echo "<h2>Current student emails:</h2>";
        $all_students = $conn->query("SELECT name, stu_number, email FROM students ORDER BY name");
        while ($row = $all_students->fetch_assoc()) {
            echo "{$row['name']} ({$row['stu_number']}) - {$row['email']}<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
</style>
