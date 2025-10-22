<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';
require_once __DIR__ . '/otp_helpers.php';

$role = $_POST['role'];
$username = $_POST['username'];
$password = $_POST['password'];

if ($role === 'student') {
    $table = 'students';
    $userField = 'sin_number';
} elseif ($role === 'faculty') {
    $table = 'faculty';
    $userField = 'email';
} else {
    $table = 'admin';
    $userField = 'username';
}

// First get the user record to check password
$sql = "SELECT * FROM $table WHERE $userField = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Check password - handle both hashed and plain text passwords
    $stored_password = $row['password'];
    $password_valid = false;
    
    // Try password_verify first (for hashed passwords)
    if (password_verify($password, $stored_password)) {
        $password_valid = true;
    } 
    // If that fails, try direct comparison (for plain text passwords)
    elseif ($password === $stored_password) {
        $password_valid = true;
    }
    
    if (!$password_valid) {
        $_SESSION['error'] = 'Invalid credentials!';
        header('Location: ../login/index.php');
        exit();
    }
    // Determine recipient email (registered email)
    // Students/Faculty have email column; Admin may not
    $toEmail = isset($row['email']) ? $row['email'] : null;
    if (!$toEmail && $role === 'admin') {
        // Fallback: use configured admin OTP email
        $mailCfg = include __DIR__ . '/../config/mail.php';
        $toEmail = $mailCfg['admin_otp_email'] ?? null;
    }
    if (!$toEmail) {
        $_SESSION["error"] = 'Email not found for this account. Contact administrator.';
        header('Location: ../login/index.php');
        exit();
    }

    // Generate OTP and store in session (5 minutes expiry)
    $otp = generate_otp(6);
    $_SESSION['pending_user'] = $row;
    $_SESSION['pending_role'] = $role;
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_expires'] = time() + 300; // 5 minutes
    $_SESSION['otp_email'] = $toEmail;

    // Send OTP to user's email
    $sent = send_otp_email($toEmail, $otp);
    if (!$sent) {
        $_SESSION['error'] = 'Failed to send OTP email. Please try again later.';
        header('Location: ../login/index.php');
        exit();
    }

    // Redirect to OTP entry page
    header('Location: ../login/otp.php');
    exit();
} else {
    $_SESSION['error'] = 'Invalid credentials!';
    header('Location: ../login/index.php');
    exit();
}
