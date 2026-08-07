<?php
declare(strict_types=1);

require __DIR__ . '/mailer/PHPMailer.php';
require __DIR__ . '/mailer/SMTP.php';
require __DIR__ . '/mailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

// Détecter une requête AJAX
$isAjax = isset($_POST['ajax'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function respond(int $code, string $status, string $message): void
{
    http_response_code($code);
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => $status, 'message' => $message]);
    } else {
        if ($status === 'success') {
            header("Location: index.html?status=success#contact");
        } else {
            header("Location: index.html?status=error#contact");
        }
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, 'error', 'Méthode de requête non autorisée.');
}

function clean_input(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$name    = clean_input($_POST['name'] ?? '');
$email   = clean_input($_POST['email'] ?? '');
$subject = clean_input($_POST['subject'] ?? '');
$message = clean_input($_POST['message'] ?? '');

// Validation des champs
if ($name === '' || $email === '' || $message === '') {
    respond(400, 'error', 'Veuillez remplir tous les champs obligatoires.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, 'error', 'Adresse email invalide.');
}

// Limiter la longueur des champs
$name    = mb_substr($name, 0, 100);
$subject = mb_substr($subject, 0, 150);
$message = mb_substr($message, 0, 5000);

// Honeypot anti-spam (champ caché)
if (!empty($_POST['website'])) {
    respond(403, 'error', 'Spam détecté.');
}

// Adresse email de destination
$to = "elhadjngagnediagne@gmail.com";

$smtpConfig = require __DIR__ . '/smtp_config.php';

if (empty($smtpConfig['username']) || str_contains($smtpConfig['username'], '@REM') ||
    empty($smtpConfig['password']) || str_contains($smtpConfig['password'], 'VOTRE_')) {
    respond(500, 'error', "Configuration SMTP manquante dans smtp_config.php. Veuillez renseigner vos identifiants SMTP.");
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    $mail->SMTPSecure = $smtpConfig['secure'] ?? 'tls';
    $mail->Port       = (int)($smtpConfig['port'] ?? 587);
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtpConfig['username'], 'Elhadji Ngagne Diagne — Portfolio');
    $mail->addAddress($to);
    $mail->addReplyTo($email, $name);

    $email_subject = $subject !== ''
        ? "Nouveau message de votre portfolio : $subject"
        : "Nouveau message de votre portfolio";

    $mail->Subject = $email_subject;
    $mail->Body    = "Vous avez reçu un nouveau message de votre formulaire de contact.\n\n"
        . "Nom: $name\n"
        . "Email: $email\n"
        . "Sujet: $subject\n"
        . "Message:\n$message\n";

    $mail->send();
    respond(200, 'success', 'Merci ! Votre message a bien été envoyé. Je vous répondrai rapidement.');
} catch (MailerException $e) {
    respond(500, 'error', "Désolé, une erreur s'est produite lors de l'envoi de votre message. Veuillez réessayer plus tard.");
} catch (Throwable $e) {
    respond(500, 'error', "Désolé, une erreur s'est produite lors de l'envoi de votre message. Veuillez réessayer plus tard.");
}
