#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Testing Fixed Brevo API Implementation ===\n\n";

// Load Mailer class
require_once 'mailNotify/include/Core/WebService.php';
require_once 'mailNotify/include/Core/Mailer.php';

use Core\Mailer;

// Test email
echo "Testing Mailer::sendMail() with Brevo API...\n";
$result = Mailer::sendMail(
    'test@example.com',
    'Test Email - Fixed Implementation',
    '<h1>Success!</h1><p>Email sent using proper setter methods.</p>'
);

if ($result) {
    echo "✅ Email sent successfully!\n";
    exit(0);
} else {
    echo "❌ Email failed to send\n";
    exit(1);
}
