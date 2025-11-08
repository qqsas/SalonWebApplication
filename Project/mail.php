<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Composer autoloader

function getMailer() {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@example.com'; // SMTP username
        $mail->Password   = 'your_email_password';    // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = 587; // 465 for SSL, 587 for TLS

        // Sender info
        $mail->setFrom('your_email@example.com', 'Your Name');

        return $mail;
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
        return null;
    }
}

function sendEmail(string $to, string $subject, string $htmlBody, array $bcc = []): bool {
    $mail = getMailer();
    if ($mail === null) {
        return false;
    }
    try {
        $mail->clearAddresses();
        $mail->clearAllRecipients();
        $mail->addAddress($to);
        foreach ($bcc as $b) {
            if (filter_var($b, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($b);
            }
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        return $mail->send();
    } catch (Exception $e) {
        // Swallow mail errors for UX; log in real system
        return false;
    }
}

