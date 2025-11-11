<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Composer autoloader

function getMailer(): ?PHPMailer
{
    $mail = new PHPMailer(true);

    $host = getenv('SMTP_HOST') ?: 'smtp.example.com'; // Your SMTP server
    $username = getenv('SMTP_USERNAME') ?: 'your_email@gmail.com'; // SMTP username
    $password = getenv('SMTP_PASSWORD') ?: 'your password';
    $port = (int)(getenv('SMTP_PORT') ?: 587); // 465 for SSL, 587 for TLS
    $encryption = getenv('SMTP_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
    $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'sender@example.com'; // Sender email
    $fromName = getenv('MAIL_FROM_NAME') ?: 'testing'; // Sender's Name
    $replyTo = getenv('MAIL_REPLY_TO') ?: null; // Reply-to Email

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid MAIL_FROM_ADDRESS configured for mailer.');
        }

        $mail->setFrom($fromEmail, $fromName);

        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        return $mail;
    } catch (Exception $e) {
        error_log("Mailer configuration error: " . $e->getMessage());
        return null;
    }
}

function sendEmail(string $to, string $subject, string $htmlBody, array $bcc = [], array $attachments = []): bool
{
    $mail = getMailer();
    if ($mail === null) {
        return false;
    }
    try {
        $mail->clearAddresses();
        $mail->clearAllRecipients();
        $mail->clearAttachments();

        $mail->addAddress($to);
        foreach ($bcc as $b) {
            if (filter_var($b, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($b);
            }
        }

        foreach ($attachments as $path => $name) {
            if (is_int($path)) {
                $path = $name;
                $name = '';
            }
            if (is_string($path) && file_exists($path)) {
                $mail->addAttachment($path, $name ?: basename($path));
            }
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        return $mail->send();
    } catch (Exception $e) {
        error_log("Mailer send error: " . $e->getMessage());
        return false;
    }
}

