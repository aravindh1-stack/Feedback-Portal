<?php
// Test script to verify student login functionality
require_once 'config/db.php';

echo "<h2>Student Login Test</h2>";

// Test 1: Check students table structure
echo "<h3>1. Students Table Structure:</h3>";
$result = $conn->query("DESCRIBE students");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Default']}<br>";
}

// Test 2: Check current students
echo "<h3>2. Current Students:</h3>";
$result = $conn->query("SELECT id, name, stu_number, email, LEFT(password, 20) as password_preview FROM students LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']}, Student#: {$row['stu_number']}, Email: " . ($row['email'] ?: 'NULL') . ", Password: {$row['password_preview']}...<br>";
}

// Test 3: Test password verification
echo "<h3>3. Password Test:</h3>";
$test_student = $conn->query("SELECT stu_number, password FROM students LIMIT 1")->fetch_assoc();
if ($test_student) {
    echo "Testing student: {$test_student['stu_number']}<br>";
    echo "Password in DB: " . substr($test_student['password'], 0, 30) . "...<br>";
    
    // Test if it's a hashed password
    if (strlen($test_student['password']) > 50 && strpos($test_student['password'], '$') !== false) {
        echo "✅ Password appears to be hashed<br>";
    } else {
        echo "⚠️ Password appears to be plain text<br>";
    }
}

// Test 4: Email check
echo "<h3>4. Email Status:</h3>";
$email_stats = $conn->query("SELECT 
    COUNT(*) as total,
    COUNT(email) as with_email,
    COUNT(*) - COUNT(email) as without_email
    FROM students WHERE email IS NOT NULL AND email != ''")->fetch_assoc();

echo "Total students: {$email_stats['total']}<br>";
echo "With email: {$email_stats['with_email']}<br>";
echo "Without email: {$email_stats['without_email']}<br>";

if ($email_stats['without_email'] > 0) {
    echo "<br><strong>⚠️ Some students don't have email addresses!</strong><br>";
    echo "<a href='fix_student_emails.php' style='background: #007cba; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Fix Student Emails</a>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
</style>
