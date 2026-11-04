<?php
require_once __DIR__ . '/includes/auth_middleware.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

/**
 * Helper Function untuk Mengirim Email via SMTP
 */
function send_email(string $to, string $subject, string $body): bool {
    $mail = new PHPMailer(true);

    try {
        // Konfigurasi Server SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL (Port 465)
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Pengirim & Penerima
        $mail->setFrom(SMTP_USER, "Iqra' Society");
        $mail->addAddress($to);

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body); // Mengubah newline menjadi <br>
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error jika terjadi kegagalan (opsional)
        error_log("Gagal mengirim email: {$mail->ErrorInfo}");
        return false;
    }
}