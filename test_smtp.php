<?php
// Simple SMTP test runner. Visit /includes/test_smtp.php in your browser.
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Ensure helper files exist
if (!file_exists(__DIR__ . '/otp_helpers.php') || !file_exists(__DIR__ . '/../config/mail.php')) {
    die("Error: Missing required files 'otp_helpers.php' or 'config/mail.php'.");
}

require_once __DIR__ . '/otp_helpers.php';
$config = require __DIR__ . '/../config/mail.php';

$to = $config['admin_otp_email'] ?? $config['from_email'] ?? 'test@example.com';
$otp = '123456'; // A test OTP

// Attempt to send the email
$ok = send_otp_email($to, $otp);
$log = __DIR__ . '/../logs/mail.log';

header('Content-Type: text/plain');
echo "--- SMTP Email Test --- \n\n";

echo "SMTP Enabled: ".(!empty($config['smtp_enabled']) ? 'Yes' : 'No')."\n";
echo "Host: ".$config['smtp_host']."\n";
echo "Port / Secure: ".$config['smtp_port']." / ".$config['smtp_secure']."\n";
echo "Username: ".$config['smtp_username']."\n";
echo "From: ".$config['from_email']."\n";
echo "Sending test email to: $to\n\n";

echo "-------------------------\n";
echo "RESULT:\n";
echo $ok ? "SUCCESS: Mail was sent successfully (check your inbox/spam folder).\n" : "FAILED: Mail could not be sent.\n";
echo "-------------------------\n\n";

if (!empty($_SESSION['mail_error'])) {
  echo "Hint from session: ".$_SESSION['mail_error']."\n\n";
  unset($_SESSION['mail_error']); // Clear the message after displaying
}

if (file_exists($log)) {
  echo "--- Last 50 lines from logs/mail.log ---\n";
  $lines = @file($log);
  if ($lines !== false) {
    $tail = array_slice($lines, -50);
    echo implode('', $tail);
  } else {
    echo "Unable to read log file at $log\n";
  }
} else {
  echo "No log file found at $log\n";
}
?>