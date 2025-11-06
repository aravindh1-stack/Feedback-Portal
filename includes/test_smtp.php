<?php
// Simple SMTP test runner. Visit /includes/test_smtp.php in browser.
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/otp_helpers.php';
$config = require __DIR__ . '/../config/mail.php';
$to = $config['admin_otp_email'] ?? $config['from_email'];
$otp = '123456';
$ok = send_otp_email($to, $otp);
$log = __DIR__ . '/../logs/mail.log';

header('Content-Type: text/plain');
echo "SMTP Enabled: ".(!empty($config['smtp_enabled']) ? 'yes' : 'no')."\n";
echo "Host: ".$config['smtp_host']."\n";
echo "Port/Secure: ".$config['smtp_port']."/".$config['smtp_secure']."\n";
echo "Username: ".$config['smtp_username']."\n";
echo "From: ".$config['from_email']."\n";
echo "To: $to\n\n";

echo $ok ? "SUCCESS: Mail dispatched (check inbox).\n" : "FAILED: Mail not sent.\n";
if (!empty($_SESSION['mail_error'])) {
  echo "Hint: ".$_SESSION['mail_error']."\n";
}
if (file_exists($log)) {
  echo "\n--- logs/mail.log (tail) ---\n";
  $lines = @file($log);
  if ($lines !== false) {
    $tail = array_slice($lines, -50);
    echo implode('', $tail);
  } else {
    echo "Unable to read log file.";
  }
} else {
  echo "\nNo log file found at $log\n";
}
