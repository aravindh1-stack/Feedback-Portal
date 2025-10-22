<?php
session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable error reporting for development (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

/**
 * Feedback Form API Endpoint
 * Retrieves form details and associated questions for admin users
 */

// Input validation and sanitization functions
function validateFormNumber($form_number) {
    return !empty($form_number) && is_string($form_number) && strlen($form_number) <= 20;
}

function sendJsonResponse($success, $data = null, $error = null, $http_code = 200) {
    http_response_code($http_code);
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    
    if ($error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Authentication check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, null, 'Unauthorized access', 401);
}

// Input validation
if (!isset($_GET['form_number']) || !validateFormNumber($_GET['form_number'])) {
    sendJsonResponse(false, null, 'Valid form number is required', 400);
}

$form_number = trim($_GET['form_number']);

// Database connection
require_once '../config/db.php';

if (!$conn) {
    sendJsonResponse(false, null, 'Database connection failed', 500);
}

try {
    // Set charset for security
    $conn->set_charset("utf8mb4");
    
    // Get form details with prepared statement
    $sql = "SELECT f.id, f.department, f.year, f.semester, f.subject_code, 
                   f.faculty_id, f.question, f.form_number, f.created_at,
                   faculty.name as faculty_name,
                   faculty.email as faculty_email,
                   faculty.department as faculty_department
            FROM feedback_forms f 
            INNER JOIN faculty ON f.faculty_id = faculty.id 
            WHERE f.form_number = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $form_number);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendJsonResponse(false, null, 'Form not found', 404);
    }
    
    $form = $result->fetch_assoc();
    $stmt->close();
    
    // Get questions using multiple methods
    $questions = [];
    $form_id = $form['id'];
    
    // Method 1: Get questions from feedback_responses table
    $sql_responses = "SELECT DISTINCT question_text, question_id, question_type
                      FROM feedback_responses 
                      WHERE form_id = ? 
                      ORDER BY question_id ASC";
    
    $stmt_responses = $conn->prepare($sql_responses);
    if ($stmt_responses) {
        $stmt_responses->bind_param("i", $form_id);
        $stmt_responses->execute();
        $responses_result = $stmt_responses->get_result();
        
        while ($question = $responses_result->fetch_assoc()) {
            $questions[] = [
                'id' => (int)$question['question_id'],
                'question_text' => htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'),
                'question_type' => $question['question_type'] ?? 'rating'
            ];
        }
        $stmt_responses->close();
    }
    
    // Method 2: If no responses, try form_questions table
    if (empty($questions)) {
        $possible_question_tables = [
            'form_questions' => ['form_id', 'question_text', 'question_type'],
            'questions' => ['form_id', 'text', 'type'],
            'survey_questions' => ['survey_id', 'question', 'answer_type']
        ];
        
        foreach ($possible_question_tables as $table => $columns) {
            // Check if table exists
            $check_table_sql = "SELECT 1 FROM information_schema.tables 
                               WHERE table_schema = DATABASE() AND table_name = ?";
            $check_stmt = $conn->prepare($check_table_sql);
            $check_stmt->bind_param("s", $table);
            $check_stmt->execute();
            $table_exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($table_exists) {
                $form_id_col = $columns[0];
                $question_col = $columns[1];
                $type_col = $columns[2] ?? 'question_type';
                
                $sql_questions = "SELECT id, $question_col as question_text, $type_col as question_type 
                                 FROM $table 
                                 WHERE $form_id_col = ? 
                                 ORDER BY id ASC";
                
                $stmt_questions = $conn->prepare($sql_questions);
                if ($stmt_questions) {
                    $stmt_questions->bind_param("i", $form_id);
                    $stmt_questions->execute();
                    $questions_result = $stmt_questions->get_result();
                    
                    while ($question = $questions_result->fetch_assoc()) {
                        $questions[] = [
                            'id' => (int)$question['id'],
                            'question_text' => htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'),
                            'question_type' => $question['question_type'] ?? 'rating'
                        ];
                    }
                    $stmt_questions->close();
                    
                    if (!empty($questions)) {
                        break; // Found questions, stop searching other tables
                    }
                }
            }
        }
    }
    
    // Method 3: Default questions if none found in database
    if (empty($questions)) {
        $default_questions = [
            'How would you rate the faculty\'s knowledge of the subject?',
            'How clear and understandable are the faculty\'s explanations?',
            'How well does the faculty encourage student participation?',
            'How punctual and regular is the faculty?',
            'How effectively does the faculty use teaching aids and resources?',
            'How accessible is the faculty for doubts and queries outside class?',
            'How fair and transparent is the faculty\'s evaluation method?',
            'How would you rate the overall teaching effectiveness?',
            'How well does the faculty maintain classroom discipline?',
            'Overall, how satisfied are you with this faculty\'s teaching?'
        ];
        
        foreach ($default_questions as $index => $question_text) {
            $questions[] = [
                'id' => $index + 1,
                'question_text' => $question_text,
                'question_type' => 'rating'
            ];
        }
    }
    
    // Sanitize form data
    $form_data = [
        'id' => (int)$form['id'],
        'department' => htmlspecialchars($form['department'], ENT_QUOTES, 'UTF-8'),
        'year' => (int)$form['year'],
        'semester' => (int)$form['semester'],
        'subject_code' => htmlspecialchars($form['subject_code'], ENT_QUOTES, 'UTF-8'),
        'faculty_id' => (int)$form['faculty_id'],
        'question' => htmlspecialchars($form['question'] ?? '', ENT_QUOTES, 'UTF-8'),
        'form_number' => htmlspecialchars($form['form_number'], ENT_QUOTES, 'UTF-8'),
        'created_at' => $form['created_at'],
        'faculty_name' => htmlspecialchars($form['faculty_name'], ENT_QUOTES, 'UTF-8'),
        'faculty_email' => htmlspecialchars($form['faculty_email'] ?? '', ENT_QUOTES, 'UTF-8'),
        'faculty_department' => htmlspecialchars($form['faculty_department'] ?? '', ENT_QUOTES, 'UTF-8'),
        'questions' => $questions,
        'total_questions' => count($questions)
    ];
    
    // Success response
    sendJsonResponse(true, ['form' => $form_data]);
    
} catch (mysqli_sql_exception $e) {
    error_log("Database error in feedback form API: " . $e->getMessage());
    sendJsonResponse(false, null, 'Database operation failed', 500);
    
} catch (Exception $e) {
    error_log("General error in feedback form API: " . $e->getMessage());
    sendJsonResponse(false, null, 'An unexpected error occurred', 500);
    
} finally {
    // Ensure database connection is closed
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>