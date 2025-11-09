<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || empty($data['form_number']) || !isset($data['answers']) || !is_array($data['answers'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

$form_number = $data['form_number'];
$student_id = $_SESSION['student_id'];
$answers = $data['answers'];

try {
    // Ensure feedback_responses table exists
    $conn->query("CREATE TABLE IF NOT EXISTS feedback_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_number VARCHAR(64) NOT NULL,
        student_id INT NOT NULL,
        subject_code VARCHAR(64) NOT NULL,
        faculty_id INT NOT NULL,
        question_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment VARCHAR(500) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_once (form_number, student_id, subject_code, faculty_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Lightweight migration for legacy tables missing columns or unique index shape
    $colCheck = $conn->query("SHOW COLUMNS FROM feedback_responses LIKE 'question_id'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        // Add question_id and recreate unique index to include it
        $conn->query("ALTER TABLE feedback_responses ADD COLUMN question_id INT NOT NULL AFTER faculty_id");
        // Drop existing uq if present (ignore errors) and recreate
        try { $conn->query("ALTER TABLE feedback_responses DROP INDEX uq_student_once"); } catch (Throwable $ign) {}
        $conn->query("ALTER TABLE feedback_responses ADD UNIQUE KEY uq_student_once (form_number, student_id, subject_code, faculty_id, question_id)");
    } else {
        // Ensure the unique key includes question_id
        $idx = $conn->query("SHOW INDEX FROM feedback_responses WHERE Key_name='uq_student_once'");
        $needFix = true;
        if ($idx && $idx->num_rows > 0) {
            $cols = [];
            while ($r = $idx->fetch_assoc()) { $cols[(int)$r['Seq_in_index']] = $r['Column_name']; }
            ksort($cols);
            $joined = implode(',', $cols);
            if ($joined === 'form_number,student_id,subject_code,faculty_id,question_id') { $needFix = false; }
        }
        if ($needFix) {
            try { $conn->query("ALTER TABLE feedback_responses DROP INDEX uq_student_once"); } catch (Throwable $ign) {}
            $conn->query("ALTER TABLE feedback_responses ADD UNIQUE KEY uq_student_once (form_number, student_id, subject_code, faculty_id, question_id)");
        }
    }

    // Add 'comment' column if missing (legacy tables)
    $colComment = $conn->query("SHOW COLUMNS FROM feedback_responses LIKE 'comment'");
    if (!$colComment || $colComment->num_rows === 0) {
        $conn->query("ALTER TABLE feedback_responses ADD COLUMN comment VARCHAR(500) DEFAULT '' AFTER rating");
    }

    $conn->begin_transaction();

    // Optional: verify the student is eligible to fill this form by checking feedback_forms meta
    $check = $conn->prepare("SELECT 1 FROM feedback_forms WHERE form_number = ? LIMIT 1");
    $check->bind_param('s', $form_number);
    $check->execute();
    $elig = $check->get_result();
    if ($elig->num_rows === 0) {
        throw new Exception('Form not found');
    }

    $ins = $conn->prepare("INSERT INTO feedback_responses (form_number, student_id, subject_code, faculty_id, question_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($answers as $ans) {
        $subject_code = $ans['subject_code'] ?? '';
        $faculty_id = (int)($ans['faculty_id'] ?? 0);
        $question_id = (int)($ans['question_id'] ?? 0);
        $rating = (int)($ans['rating'] ?? 0);
        $comment = substr(trim($ans['comment'] ?? ''), 0, 500);
        if ($subject_code === '' || $faculty_id <= 0 || $question_id <= 0 || $rating < 1 || $rating > 5) {
            continue; // skip invalid
        }
        $ins->bind_param('sisiiis', $form_number, $student_id, $subject_code, $faculty_id, $question_id, $rating, $comment);
        $ins->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
