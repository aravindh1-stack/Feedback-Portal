<?php
// AJAX: verify OTP and complete login, return JSON with redirect URL
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_role'])) {
  echo json_encode(['success' => false, 'message' => 'OTP session expired. Please login again.']);
  exit;
}

$d = fn($k) => isset($_POST[$k]) ? (string)$_POST[$k] : '';
$code = $d('d1').$d('d2').$d('d3').$d('d4').$d('d5').$d('d6');

if (strlen($code) !== 6 || !ctype_digit($code)) {
  echo json_encode(['success' => false, 'message' => 'Invalid code']);
  exit;
}

if (time() > $_SESSION['otp_expires']) {
  echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
  exit;
}

if ($code !== $_SESSION['otp_code']) {
  echo json_encode(['success' => false, 'message' => 'Incorrect OTP']);
  exit;
}

// Complete login
$_SESSION['user'] = $_SESSION['pending_user'];
$_SESSION['role'] = $_SESSION['pending_role'];
unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_role'], $_SESSION['otp_email']);

$role = $_SESSION['role'];
$redirect = '../faculty/dashboard.php';
if ($role === 'admin') $redirect = '../admin/dashboard.php';
elseif ($role === 'student') $redirect = '../student/dashboard.php';

echo json_encode(['success' => true, 'message' => 'OTP verified', 'redirect' => $redirect]);
