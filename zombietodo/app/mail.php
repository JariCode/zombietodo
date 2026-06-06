<?php
// ================================
// mail.php — Sähköpostin lähetys PHPMailerilla
// ================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

// Lähettää salasanan palautuslinkin annettuun osoitteeseen.
// Palauttaa true jos lähetys onnistui, muuten false.
function sendResetEmail($toEmail, $toName, $resetLink) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['SMTP_USER'], 'Zombie To-Do');
        $mail->addAddress($toEmail, $toName);

        $mail->Subject = 'Salasanan palautus - Zombie To-Do';
        $mail->Body =
            "Hei " . $toName . ",\n\n" .
            "Pyysit salasanan palautusta. Vaihda salasanasi tästä linkistä:\n\n" .
            $resetLink . "\n\n" .
            "Linkki on voimassa tunnin. Jos et pyytänyt tätä, jätä viesti huomiotta.\n\n" .
            "Zombie To-Do";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}