<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/mail-error.log');

function jsonResponse($success, $message, $statusCode = 200, $extra = [])
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', 405);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($phone) || empty($city)) {
    jsonResponse(false, 'Name, email, phone and city are required', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address', 400);
}

$to = getenv('SMTP_TO') ?: 'aakash@pivotmkg.com';
$subject = 'New Contact Form Submission - JSK Buildwell';

// Create HTML email content
$emailMessage = "
<html>
<head>
    <title>New Contact Form Submission</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #C19D60; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; }
        .field { margin: 10px 0; }
        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class='header'>
        <h2 style='margin: 0; font-size: 24px;'>New Contact Form Submission</h2>
    </div>
    <div class='content'>
        <div class='field'><strong>Name:</strong> " . htmlspecialchars($name) . "</div>
        <div class='field'><strong>Email:</strong> " . htmlspecialchars($email) . "</div>
        <div class='field'><strong>Phone:</strong> " . htmlspecialchars($phone) . "</div>
        <div class='field'><strong>City:</strong> " . htmlspecialchars($city) . "</div>";

if (!empty($message)) {
    $emailMessage .= "<div class='field'><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</div>";
}

$emailMessage .= "
        <div class='footer'>
            <strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "<br>
            <strong>IP Address:</strong> " . $_SERVER['REMOTE_ADDR'] . "
        </div>
    </div>
</body>
</html>";

try {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require $autoloadPath;

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $smtpHost = getenv('SMTP_HOST') ?: '';
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        $smtpPort = getenv('SMTP_PORT') ?: '';
        $smtpSecure = strtolower(getenv('SMTP_SECURE') ?: '');

        $useSmtp = $smtpHost && $smtpUser && $smtpPass;
        if ($useSmtp) {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->Port = $smtpPort ? (int) $smtpPort : 587;
            if ($smtpSecure && $smtpSecure !== 'none') {
                $mail->SMTPSecure = $smtpSecure === 'starttls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : $smtpSecure;
            } else {
                $mail->SMTPSecure = $mail->Port === 465
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->isMail();
        }

        $fromEmail = getenv('SMTP_FROM') ?: 'noreply@jskbuildwell.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'JSK Buildwell';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, 'JSK Buildwell Contact');
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailMessage;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $emailMessage));

        $mail->send();
        error_log("Email sent successfully using PHPMailer to: $to");
        jsonResponse(true, 'Message sent successfully');
    }

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>" . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    error_log("Attempting to send email to: $to using mail() function");

    if (mail($to, $subject, $emailMessage, $headers)) {
        error_log("Email sent successfully to: $to");
        jsonResponse(true, 'Message sent successfully');
    }

    error_log("Failed to send email to: $to");
    jsonResponse(false, 'Mail function returned false', 500);
} catch (Exception $e) {
    error_log('Mail error: ' . $e->getMessage());
    jsonResponse(false, 'Failed to send message: ' . $e->getMessage(), 500);
}
