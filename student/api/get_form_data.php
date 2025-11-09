<?php
session_start();
header('Content-Type: application/json');

// Require logged-in student
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode([ 'error' => 'Unauthorized' ]);
    exit();
}

require_once __DIR__ . '/../../config/db.php';
// Throw exceptions on mysqli errors to catch and return cleanly
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([ 'error' => 'DB connection failed' ]);
    exit();
}

$form_number = $_GET['form_number'] ?? '';
if ($form_number === '') {
    http_response_code(400);
    echo json_encode([ 'error' => 'Missing form_number' ]);
    exit();
}

try {
    // Form meta: infer from feedback_forms (department/year/semester)
    $stmtMeta = $conn->prepare("SELECT department, year, semester FROM feedback_forms WHERE form_number = ? LIMIT 1");
    $stmtMeta->bind_param('s', $form_number);
    $stmtMeta->execute();
    $metaRes = $stmtMeta->get_result();
    if ($metaRes->num_rows === 0) {
        echo json_encode([ 'error' => 'Form not found' ]);
        exit();
    }
    $meta = $metaRes->fetch_assoc();

    // Assignments: unique subject_code + faculty_id from feedback_forms
    $stmtA = $conn->prepare("SELECT DISTINCT subject_code, faculty_id FROM feedback_forms WHERE form_number = ? ORDER BY subject_code ASC");
    $stmtA->bind_param('s', $form_number);
    $stmtA->execute();
    $assignmentsRes = $stmtA->get_result();
    $assignments = [];

    // Load faculty names
    $facMap = [];
    while ($row = $assignmentsRes->fetch_assoc()) {
        $facId = (int)$row['faculty_id'];
        if (!isset($facMap[$facId])) {
            $stmtF = $conn->prepare("SELECT name FROM faculty WHERE id = ? LIMIT 1");
            $stmtF->bind_param('i', $facId);
            $stmtF->execute();
            $rF = $stmtF->get_result();
            $facMap[$facId] = $rF->num_rows ? ($rF->fetch_assoc()['name'] ?? 'Faculty #'.$facId) : 'Faculty #'.$facId;
        }
        $assignments[] = [
            // Composite assignment id as stable key (no dedicated table)
            'assignment_key' => $row['subject_code'] . '|' . $facId,
            'subject_code' => $row['subject_code'],
            'subject_name' => null, // not stored; optional
            'faculty_id' => $facId,
            'faculty_name' => $facMap[$facId],
        ];
    }

    // Questions from form_questions if the table exists; otherwise fallback to DISTINCT question text
    $questions = [];
    $hasFormQuestions = false;
    $chk = $conn->query("SHOW TABLES LIKE 'form_questions'");
    if ($chk && $chk->num_rows > 0) { $hasFormQuestions = true; }
    if ($hasFormQuestions) {
        $resQ = $conn->prepare("SELECT id, question_text FROM form_questions WHERE form_number = ? ORDER BY id ASC");
        $resQ->bind_param('s', $form_number);
        $resQ->execute();
        $qr = $resQ->get_result();
        if ($qr && $qr->num_rows > 0) {
            while ($q = $qr->fetch_assoc()) {
                $questions[] = [ 'question_id' => (int)$q['id'], 'text' => $q['question_text'] ];
            }
        }
    }
    if (empty($questions)) {
        // fallback (works for legacy forms created before form_questions table was added)
        $stmtQ2 = $conn->prepare("SELECT DISTINCT question FROM feedback_forms WHERE form_number = ? ORDER BY question ASC");
        $stmtQ2->bind_param('s', $form_number);
        $stmtQ2->execute();
        $q2r = $stmtQ2->get_result();
        $qid = 1;
        while ($q = $q2r->fetch_assoc()) {
            $questions[] = [ 'question_id' => $qid++, 'text' => $q['question'] ];
        }
    }

    echo json_encode([
        'form' => [
            'form_number' => $form_number,
            'department' => $meta['department'],
            'year' => $meta['year'],
            'semester' => $meta['semester'],
            'total_faculty' => count($assignments),
        ],
        'assignments' => $assignments,
        'questions' => $questions,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'error' => 'Server error: ' . $e->getMessage() ]);
}
