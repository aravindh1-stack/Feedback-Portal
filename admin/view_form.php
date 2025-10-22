<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/db.php';

$form_number = isset($_GET['form_number']) ? $_GET['form_number'] : '';
$form_info = null;
$form_questions = [];

if ($form_number) {
    // Get form info
    $stmt = $conn->prepare("SELECT f.*, faculty.name as faculty_name, faculty.department as faculty_dept FROM feedback_forms f JOIN faculty ON f.faculty_id = faculty.id WHERE f.form_number = ? LIMIT 1");
    $stmt->bind_param("s", $form_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $form_info = $result->fetch_assoc();
    $stmt->close();

    // Get questions for this form
    $stmt2 = $conn->prepare("SELECT * FROM feedback_questions WHERE form_id = ? ORDER BY id");
    $stmt2->bind_param("i", $form_info['id']);
    $stmt2->execute();
    $questions_result = $stmt2->get_result();
    while ($q = $questions_result->fetch_assoc()) {
        $form_questions[] = $q;
    }
    $stmt2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback Form - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #222; }
        .container { max-width: 700px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); padding: 2rem; }
        .form-header { border-bottom: 1.5px solid #e5e7eb; margin-bottom: 1.5rem; padding-bottom: 1rem; }
        .form-number-badge { background: linear-gradient(90deg, #6366f1 0%, #818cf8 100%); color: #fff; border-radius: 6px; padding: 0.4rem 1.2rem; font-weight: 600; font-size: 1.1rem; display: inline-block; margin-bottom: 0.5rem; }
        .meta { color: #64748b; margin-bottom: 1rem; }
        .questions-list { margin-top: 2rem; }
        .question-card { background: #f3f4f6; border-radius: 8px; margin-bottom: 1.2rem; padding: 1.2rem; }
        .question-title { font-weight: 600; margin-bottom: 0.3rem; color: #3730a3; }
        .question-meta { color: #64748b; font-size: 0.95rem; margin-bottom: 0.5rem; }
        .back-btn { display: inline-block; margin-top: 2rem; padding: 0.7rem 1.4rem; background: #3b82f6; color: #fff; border-radius: 7px; text-decoration: none; font-weight: 500; transition: background 0.18s; }
        .back-btn:hover { background: #2563eb; }
    </style>
</head>
<body>
<div class="container">
    <!-- DEBUG OUTPUT START -->
    <div style="background:#fef9c3; color:#92400e; padding:0.5rem 1rem; border-radius:6px; margin-bottom:1rem; font-size:0.95rem;">
        <b>Debug Info:</b><br>
        <b>form_number:</b> <?= htmlspecialchars($form_number) ?><br>
        <b>form_info is set:</b> <?= $form_info ? 'YES' : 'NO' ?><br>
        <b>MySQL error:</b> <?= isset($conn) && $conn->error ? htmlspecialchars($conn->error) : 'None' ?>
    </div>
    <!-- DEBUG OUTPUT END -->
    <?php if (!$form_number): ?>
        <div style="color:#dc2626; font-weight:600;">No form number provided.</div>
    <?php elseif (!$form_info): ?>
        <div style="color:#dc2626; font-weight:600;">No form found for this form number.</div>
    <?php else: ?>
        <div class="form-header">
            <div class="form-number-badge">Form No: <?= htmlspecialchars($form_info['form_number']) ?></div>
            <h2>Feedback Form Details</h2>
            <div class="meta">
                <div><b>Department:</b> <?= htmlspecialchars($form_info['department']) ?></div>
                <div><b>Year:</b> <?= htmlspecialchars($form_info['year']) ?> | <b>Semester:</b> <?= htmlspecialchars($form_info['semester']) ?></div>
                <div><b>Subject Code:</b> <?= htmlspecialchars($form_info['subject_code']) ?></div>
                <div><b>Faculty:</b> <?= htmlspecialchars($form_info['faculty_name']) ?> (<?= htmlspecialchars($form_info['faculty_dept']) ?>)</div>
                <?php if (!empty($form_info['created_at'])): ?>
                    <div><b>Created At:</b> <?= htmlspecialchars($form_info['created_at']) ?></div>
                <?php endif; ?>
                <?php if (!empty($form_info['updated_at'])): ?>
                    <div><b>Updated At:</b> <?= htmlspecialchars($form_info['updated_at']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="questions-list">
            <h3>Questions</h3>
            <?php foreach ($form_questions as $idx => $q): ?>
                <div class="question-card">
                    <div class="question-title">Q<?= $idx+1 ?>: <?= htmlspecialchars($q['question']) ?></div>
                    <div class="question-meta">
                        <b>Type:</b> <?= htmlspecialchars($q['question_type']) ?> | 
                        <b>Subject Code:</b> <?= htmlspecialchars($q['subject_code']) ?> | 
                        <b>Faculty:</b> <?= htmlspecialchars($form_info['faculty_name']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="color:#dc2626; font-weight:600;">Invalid or missing form number.</div>
    <?php endif; ?>
    <a href="manage_forms.php" class="back-btn">← Back to Forms</a>
</div>
</body>
</html>
