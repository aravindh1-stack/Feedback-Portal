<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/db.php';

echo "<h2>Adding Sample Feedback Responses</h2>";

try {
    // First, get available forms, students, and faculty
    $forms_query = "SELECT id, subject_code, subject_name, department, year, semester FROM feedback_forms LIMIT 5";
    $forms_result = $conn->query($forms_query);
    $forms = [];
    while ($forms_result && $row = $forms_result->fetch_assoc()) {
        $forms[] = $row;
    }

    $students_query = "SELECT id, name, sin_number FROM students LIMIT 10";
    $students_result = $conn->query($students_query);
    $students = [];
    while ($students_result && $row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }

    $faculty_query = "SELECT id, name, department FROM faculty LIMIT 5";
    $faculty_result = $conn->query($faculty_query);
    $faculty = [];
    while ($faculty_result && $row = $faculty_result->fetch_assoc()) {
        $faculty[] = $row;
    }

    if (empty($forms)) {
        echo "<p style='color: red;'>No feedback forms found. Please create feedback forms first.</p>";
        exit;
    }

    if (empty($students)) {
        echo "<p style='color: red;'>No students found. Please add students first.</p>";
        exit;
    }

    if (empty($faculty)) {
        echo "<p style='color: red;'>No faculty found. Please add faculty first.</p>";
        exit;
    }

    // Check if responses already exist
    $existing_check = $conn->query("SELECT COUNT(*) as count FROM feedback_responses");
    $existing_count = $existing_check->fetch_assoc()['count'];

    if ($existing_count > 0) {
        echo "<p style='color: orange;'>Found {$existing_count} existing responses. Adding more sample data...</p>";
    }

    // Sample questions for feedback
    $sample_questions = [
        "How would you rate the teaching quality?",
        "How clear were the explanations?", 
        "How well organized was the course?",
        "How helpful were the course materials?",
        "How would you rate overall satisfaction?"
    ];

    $responses_added = 0;

    // Add sample responses for each form
    foreach ($forms as $form) {
        echo "<h3>Adding responses for: {$form['subject_code']} - {$form['subject_name']}</h3>";
        
        // Get a faculty member (preferably from same department)
        $form_faculty = null;
        foreach ($faculty as $fac) {
            if ($fac['department'] == $form['department']) {
                $form_faculty = $fac;
                break;
            }
        }
        if (!$form_faculty) {
            $form_faculty = $faculty[0]; // Use first faculty if no department match
        }

        // Add responses from multiple students
        $student_count = min(count($students), 8); // Use up to 8 students per form
        
        for ($i = 0; $i < $student_count; $i++) {
            $student = $students[$i];
            
            // Add responses for each question
            foreach ($sample_questions as $question) {
                $rating = rand(3, 5); // Random rating between 3-5 for good feedback
                
                $insert_query = "INSERT INTO feedback_responses (form_id, student_id, faculty_id, question, rating) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("iiisi", $form['id'], $student['id'], $form_faculty['id'], $question, $rating);
                
                if ($stmt->execute()) {
                    $responses_added++;
                    echo "✅ Added response: {$student['name']} rated '{$question}' as {$rating}/5<br>";
                } else {
                    echo "❌ Failed to add response for {$student['name']}: " . $stmt->error . "<br>";
                }
                $stmt->close();
            }
        }
        echo "<br>";
    }

    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724;'>✅ Success!</h3>";
    echo "<p>Added <strong>{$responses_added}</strong> sample feedback responses to the database.</p>";
    echo "<p>Now you can view analytics with real data!</p>";
    echo "</div>";

    // Show summary
    echo "<h3>Summary of Added Data:</h3>";
    echo "<ul>";
    echo "<li><strong>Forms used:</strong> " . count($forms) . "</li>";
    echo "<li><strong>Students participated:</strong> " . min(count($students), 8) . "</li>";
    echo "<li><strong>Questions per student:</strong> " . count($sample_questions) . "</li>";
    echo "<li><strong>Total responses:</strong> {$responses_added}</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
</style>

<div style="margin-top: 30px; padding: 20px; background: #e8f4fd; border-radius: 8px;">
    <h3>🎯 Next Steps:</h3>
    <ol>
        <li><a href="analytics.php" style="background: #007cba; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">View Analytics</a> - Check the analytics page now</li>
        <li><a href="check_database.php" style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Check Database</a> - Verify the data was added</li>
        <li><a href="dashboard.php" style="background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">Dashboard</a> - Return to main dashboard</li>
    </ol>
</div>
