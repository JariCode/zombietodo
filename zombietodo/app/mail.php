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

        $mail->isHTML(true); // Sähköposti lähetetään HTML-muodossa
        $mail->Subject = 'Salasanan palautus - Zombie To-Do';

        // Suojataan käyttäjän nimi siltä varalta että se sisältää HTML-merkkejä
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');

        // HTML-versio — linkki näytetään siistinä nappina, ei paljaana osoitteena
       $mail->Body =
            '<div style="font-family: \'Courier New\', Courier, monospace; background:#140000; color:#e8e8e8; padding:35px; border:2px solid #660000; border-radius:6px; max-width:600px; margin:0 auto; box-shadow:0 0 15px #000000;">' .
                '<h2 style="font-family: Impact, Haettenschweiler, sans-serif; color:#ff2222; letter-spacing:3px; text-transform:uppercase; font-size:30px; margin:0 0 25px 0; text-shadow:2px 2px black;">SALASANAN PALAUTUS &#129503;</h2>' .
                '<p style="font-size:15px; line-height:1.6; color:#e8e8e8; text-shadow:1px 1px black;">Hei ' . $safeName . ',</p>' .
                '<p style="font-size:15px; line-height:1.6; color:#e8e8e8; text-shadow:1px 1px black;">Pyysit salasanan palautusta. Painike vie salasanan vaihtosivulle, jossa voit asettaa uuden salasanan:</p>' .
                '<p style="text-align:center; margin:35px 0;">' .
                    '<a href="' . $resetLink . '" style="font-family: Impact, Haettenschweiler, sans-serif; background:#990000; color:#ffffff; padding:16px 32px; text-decoration:none; border-radius:3px; font-weight:bold; font-size:16px; letter-spacing:2px; text-transform:uppercase; display:inline-block;">VAIHDA SALASANA &#128273;</a>' .
                '</p>' .
                '<p style="font-size:13px; color:#999999; border-top:1px solid #660000; padding-top:18px; margin-top:25px;">Linkki on voimassa tunnin. Jos et pyytänyt tätä, jätä viesti huomiotta.</p>' .
                '<p style="font-family: Impact, Haettenschweiler, sans-serif; color:#ff2222; letter-spacing:3px; text-transform:uppercase; font-size:20px; margin:15px 0 0 0; text-shadow:2px 2px black;">Zombie To-Do</p>' .
            '</div>';

        // Tekstiversio varalle (näytetään jos sähköpostiohjelma ei tue HTML:ää)
        $mail->AltBody =
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