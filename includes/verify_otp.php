<?php
session_start();
if (!isset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_role'])) {
  $_SESSION['error'] = 'OTP session expired. Please login again.';
  header('Location: ../login/index.php');
  exit;
}

$code = (string)($_POST['d1'].$_POST['d2'].$_POST['d3'].$_POST['d4'].$_POST['d5'].$_POST['d6']);
if (strlen($code) !== 6 || !ctype_digit($code)) {
  $_SESSION['error'] = 'Invalid code.';
  header('Location: ../login/otp.php');
  exit;
}

if (time() > $_SESSION['otp_expires']) {
  $_SESSION['error'] = 'OTP expired. Please request a new one.';
  header('Location: ../login/otp.php');
  exit;
}

if ($code !== $_SESSION['otp_code']) {
  $_SESSION['error'] = 'Incorrect OTP. Try again.';
  header('Location: ../login/otp.php');
  exit;
}

// OTP verified: finalize login
$_SESSION['user'] = $_SESSION['pending_user'];
$_SESSION['role'] = $_SESSION['pending_role'];

// Set role-specific session ids for downstream pages/APIs
if ($_SESSION['role'] === 'student') {
    if (isset($_SESSION['user']['id'])) { $_SESSION['student_id'] = (int)$_SESSION['user']['id']; }
} elseif ($_SESSION['role'] === 'faculty') {
    if (isset($_SESSION['user']['id'])) { $_SESSION['faculty_id'] = (int)$_SESSION['user']['id']; }
}

// Clear OTP data
unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_role'], $_SESSION['otp_email']);

// Redirect based on role
switch ($_SESSION['role']) {
  case 'admin':
    header('Location: ../admin/dashboard.php');
    break;
  case 'student':
    header('Location: ../student/dashboard.php');
    break;
  default:
    header('Location: ../faculty/dashboard.php');
}
exit;
