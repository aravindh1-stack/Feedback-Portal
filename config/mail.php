<?php
// Mail configuration for OTP delivery
// Update these values with your SMTP provider if using PHPMailer in future.
return [
    'from_email' => 'sampleworkareas@gmail.com',
    'from_name'  => 'College Feedback Portal',
    // Optional: if your admin table doesn't have an email field, set this to receive admin OTPs
    'admin_otp_email' => 'sampleworkareas@gmail.com',

    // SMTP (PHPMailer) settings - recommended for local XAMPP
    // Set smtp_enabled => true after you install PHPMailer via Composer
    // composer require phpmailer/phpmailer
    'smtp_enabled' => true,              // enabled for SMTP sending
    'smtp_host'    => 'smtp.gmail.com',  // e.g., Gmail SMTP
    'smtp_username'=> 'sampleworkareas@gmail.com', // your SMTP username
    'smtp_password'=> 'yroavxrzisivhjaw', // Gmail App Password (no spaces)
    'smtp_port'    => 587,               // 587 for TLS, 465 for SSL
    'smtp_secure'  => 'tls',             // 'tls' or 'ssl'
    // Optional diagnostics and hosting tweaks
    // Turn on only temporarily for debugging; it logs to logs/mail.log
    // 'smtp_debug'   => true,
    // Some shared hosts need relaxed SSL verification
    // 'smtp_allow_self_signed' => true,

    // Optional HTTP email provider (works even if SMTP and mail() are blocked)
    // Set 'email_provider' => 'brevo' and fill 'brevo_api_key'.
    // 'email_provider' => 'brevo',
    // 'brevo_api_key'  => 'YOUR_BREVO_API_KEY',
];
