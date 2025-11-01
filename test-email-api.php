#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Brevo Transactional Email API Test ===\n\n";

// Check environment variable
echo "Checking BREVO_API_KEY: ";
$api_key = getenv('BREVO_API_KEY');
if ($api_key) {
    echo "✅ EXISTS (length: " . strlen($api_key) . ")\n";
} else {
    echo "❌ NOT FOUND\n";
    exit(1);
}

echo "\n=== Loading Brevo API v3 SDK ===\n";

require 'mailNotify/include/vendor/sendinblue/api-v3-sdk/autoload.php';

use SendinBlue\Client\Configuration;
use SendinBlue\Client\Api\SMTPApi;
use SendinBlue\Client\Model\SendSmtpEmail;

try {
    // Configure API key authorization
    $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);
    
    // Create API instance
    $apiInstance = new SMTPApi(null, $config);
    
    // Create email object with proper setter methods (matching Mailer.php implementation)
    $sendSmtpEmail = new SendSmtpEmail();
    
    // Sender - use setSender() method
    $sendSmtpEmail->setSender([
        'email' => 'noreply@travian-game.test',
        'name' => 'Travian Game Server'
    ]);
    
    // Recipients - use setTo() method
    $sendSmtpEmail->setTo([
        ['email' => 'test@example.com', 'name' => 'Test User']
    ]);
    
    // Subject - use setSubject() method
    $subject = 'Travian Email Test - ' . date('Y-m-d H:i:s');
    $sendSmtpEmail->setSubject($subject);
    
    // HTML Content - use setHtmlContent() method
    $htmlContent = '
        <html>
            <body>
                <h1>✅ Email Test Successful!</h1>
                <p>This is a test email from <strong>Travian game server</strong> on Replit.</p>
                <p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p>Email delivery via <strong>Brevo Transactional Email API</strong> is working correctly!</p>
                <hr>
                <p style="color: #666; font-size: 12px;">Sent via Brevo API v3</p>
            </body>
        </html>
    ';
    $sendSmtpEmail->setHtmlContent($htmlContent);
    
    // Text Content - use setTextContent() method
    $sendSmtpEmail->setTextContent('Email Test Successful! This is a test email from Travian game server. Timestamp: ' . date('Y-m-d H:i:s'));
    
    echo "\n📧 Sending test email via Brevo API...\n";
    echo "To: test@example.com\n";
    echo "Subject: {$subject}\n\n";
    
    // Send the email
    $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
    
    echo "\n✅ SUCCESS! Email sent via Brevo API\n";
    echo "Message ID: " . $result['messageId'] . "\n";
    echo "\n";
    echo "API Response:\n";
    print_r($result);
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponseBody')) {
        echo "\nAPI Response Body:\n";
        print_r($e->getResponseBody());
    }
    exit(1);
}
