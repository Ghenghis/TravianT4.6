<?php
namespace Core;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    public static function sendMail($to, $subject, $html)
    {
        global $indexConfig;
        if(empty($to)){
            return true;
        }
        
        // Try Brevo API first if API key is available
        $brevoApiKey = getenv('BREVO_API_KEY');
        if ($brevoApiKey && self::sendViaBrevoAPI($to, $subject, $html, $brevoApiKey)) {
            return true;
        }
        
        // Fallback to PHPMailer SMTP
        $mail = new PHPMailer(TRUE);
        if(isset($indexConfig['mail']['type']) && $indexConfig['mail']['type'] == 'smtp'){
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Host = $indexConfig['mail']['host'];
            $mail->Port = $indexConfig['mail']['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $indexConfig['mail']['username'];
            $mail->Password = $indexConfig['mail']['password'];
            
            if (isset($indexConfig['mail']['secure'])) {
                if ($indexConfig['mail']['secure'] === 'tls') {
                    $mail->SMTPSecure = 'tls';
                } elseif ($indexConfig['mail']['secure'] === 'ssl') {
                    $mail->SMTPSecure = 'ssl';
                }
            }
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('noreply@' . WebService::getJustDomain());
        $mail->addReplyTo('noreply@' . WebService::getJustDomain());
        if(is_array($to)) {
            foreach($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }
        $mail->Subject = $subject;
        $mail->msgHTML($html);
        $result = $mail->send();
        return $result;
    }
    
    private static function sendViaBrevoAPI($to, $subject, $html, $apiKey)
    {
        try {
            require_once __DIR__ . '/../vendor/sendinblue/api-v3-sdk/autoload.php';
            
            $config = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
            $apiInstance = new \SendinBlue\Client\Api\SMTPApi(null, $config);
            $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail();
            
            // Use proper setter methods
            $sender = new \SendinBlue\Client\Model\SendSmtpEmailSender();
            $sender->setName('Travian Game Server');
            $sender->setEmail('noreply@' . WebService::getJustDomain());
            $sendSmtpEmail->setSender($sender);
            
            // Build recipient list
            $recipients = [];
            if (is_array($to)) {
                foreach ($to as $email) {
                    $recipient = new \SendinBlue\Client\Model\SendSmtpEmailTo();
                    $recipient->setEmail($email);
                    $recipients[] = $recipient;
                }
            } else {
                $recipient = new \SendinBlue\Client\Model\SendSmtpEmailTo();
                $recipient->setEmail($to);
                $recipients[] = $recipient;
            }
            $sendSmtpEmail->setTo($recipients);
            
            $sendSmtpEmail->setSubject($subject);
            $sendSmtpEmail->setHtmlContent($html);
            
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            return $result && $result->getMessageId() !== null;
            
        } catch (\Exception $e) {
            error_log("Brevo API failed: " . $e->getMessage());
            return false;
        }
    }
}