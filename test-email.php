#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Email Configuration Test ===\n\n";

// Check environment variables
echo "Checking BREVO_SMTP_KEY: ";
$smtp_key = getenv('BREVO_SMTP_KEY');
if ($smtp_key) {
    echo "✅ EXISTS (length: " . strlen($smtp_key) . ")\n";
} else {
    echo "❌ NOT FOUND\n";
    exit(1);
}

echo "\n=== Testing PHPMailer with Brevo SMTP ===\n";

require 'mailNotify/include/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings for Brevo SMTP
    $mail->SMTPDebug = 2;  // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    // Brevo SMTP uses the account login email as username
    // Trying different username formats
    $mail->Username   = getenv('BREVO_API_KEY') ? 'api-key' : 'your-login@email.com';
    $mail->Password   = $smtp_key;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->AuthType   = 'LOGIN';  // Force LOGIN auth method

    // Recipients
    $mail->setFrom('noreply@travian.test', 'Travian Game Server');
    $mail->addAddress('test@example.com', 'Test User');  // Replace with your email for real test

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Travian Email Test - ' . date('Y-m-d H:i:s');
    $mail->Body    = '<h1>Email Test Successful!</h1><p>This is a test email from Travian game server on Replit.</p><p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'Email Test Successful! This is a test email from Travian game server.';

    echo "\n\n📧 Attempting to send test email...\n";
    $result = $mail->send();
    
    if ($result) {
        echo "\n✅ Email sent successfully!\n";
        echo "To: test@example.com\n";
        echo "Subject: {$mail->Subject}\n";
        exit(0);
    } else {
        echo "\n❌ Email send failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: {$mail->ErrorInfo}\n";
    echo "Exception: " . $e->getMessage() . "\n";
    exit(1);
}
