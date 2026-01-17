<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($phone) || empty($city)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email, phone and city are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$to = 'aakash@pivotmkg.com';
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

// Use PHPMailer without SMTP (uses local mail server)
try {
    // Check if PHPMailer is available, otherwise fallback to mail()
    if (file_exists('vendor/PHPMailer/src/PHPMailer.php')) {
        require 'vendor/PHPMailer/src/Exception.php';
        require 'vendor/PHPMailer/src/PHPMailer.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Use local mail server (no SMTP authentication)
        $mail->isMail(); // Use PHP's mail() function internally
        
        // Recipients
        $mail->setFrom('noreply@jskbuildwell.com', 'JSK Buildwell');
        $mail->addAddress($to, 'JSK Buildwell Contact');
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailMessage;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $emailMessage));
        
        $mail->send();
        error_log("Email sent successfully using PHPMailer to: $to");
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        
    } else {
        // Fallback to enhanced mail() function
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>" . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Log the attempt
        error_log("Attempting to send email to: $to using mail() function");
        
        if (mail($to, $subject, $emailMessage, $headers)) {
            error_log("Email sent successfully to: $to");
            echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        } else {
            error_log("Failed to send email to: $to");
            throw new Exception('Mail function returned false');
        }
    }
    
} catch (Exception $e) {
    error_log('Mail error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
}
?>
