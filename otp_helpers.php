<?php
// Helper functions for OTP flow

function generate_otp($length = 6) {
    $digits = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $otp;
}

function send_otp_email($toEmail, $otp) {
    $config = include __DIR__ . '/../config/mail.php';
    $from = $config['from_email'] ?? 'no-reply@example.com';
    $fromName = $config['from_name'] ?? 'College Feedback Portal';

    $subject = 'Your One-Time Password (OTP)';
    $message = "<html><body>"
             . "<p>Dear user,</p>"
             . "<p>Your OTP is: <strong>$otp</strong></p>"
             . "<p>This code will expire in 5 minutes.</p>"
             . "<p>If you did not request this, please ignore this email.</p>"
             . "<p>Regards,<br>$fromName</p>"
             . "</body></html>";

    // If SMTP is enabled and PHPMailer is available, use it
    if (!empty($config['smtp_enabled'])) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $config['smtp_host'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp_username'] ?? $from;
                $mail->Password = $config['smtp_password'] ?? '';
                $secure = $config['smtp_secure'] ?? 'tls';
                if ($secure === 'ssl') { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
                else { $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
                $mail->Port = (int)($config['smtp_port'] ?? 587);

                // Optional debug
                if (!empty($config['smtp_debug'])) {
                    $mail->SMTPDebug = 2; // verbose
                    $debugBuffer = '';
                    $mail->Debugoutput = function($str) use (&$debugBuffer) { $debugBuffer .= $str . "\n"; };
                }

                // Use authenticated username as From for Gmail/SMTP deliverability
                $fromSender = $config['smtp_username'] ?? $from;
                $mail->setFrom($fromSender, $fromName);
                // Optional reply-to as the configured from
                if (!empty($from) && $from !== $fromSender) { $mail->addReplyTo($from, $fromName); }
                $mail->CharSet = 'UTF-8';
                $mail->Timeout = 15; // seconds
                $mail->addAddress($toEmail);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message;

                $sent = $mail->send();
                if (!$sent && !empty($config['smtp_debug'])) {
                    $logDir = __DIR__ . '/../logs';
                    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
                    @file_put_contents($logDir . '/mail.log', '['.date('Y-m-d H:i:s')."] SMTP debug (send returned false)\n".$debugBuffer."\n", FILE_APPEND);
                }
                return $sent;
            } catch (Throwable $e) {
                // Log SMTP error for debugging
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
                $logMsg = '[' . date('Y-m-d H:i:s') . "] SMTP error: " . $e->getMessage() . "\n";
                @file_put_contents($logDir . '/mail.log', $logMsg, FILE_APPEND);
                // Expose sanitized message in session for UI
                if (session_status() === PHP_SESSION_NONE) { @session_start(); }
                $_SESSION['mail_error'] = 'SMTP error occurred. Check logs/mail.log.';
                // fall through to mail() fallback
            }
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: $fromName <$from>\r\n";
    $result = @mail($toEmail, $subject, $message, $headers);
    if (!$result) {
        // Log mail() failure too
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
        $logMsg = '[' . date('Y-m-d H:i:s') . "] mail() failed for $toEmail\n";
        @file_put_contents($logDir . '/mail.log', $logMsg, FILE_APPEND);
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $_SESSION['mail_error'] = 'mail() failed. Check logs/mail.log.';
    }
    return $result;
}
