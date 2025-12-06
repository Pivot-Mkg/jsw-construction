<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Keep warnings/notices from breaking JSON responses
ini_set('display_errors', '0');
set_error_handler(function ($severity, $message, $file, $line) {
    // Convert warnings to JSON so the frontend can surface a clean message
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
});

// Handle CORS preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Basic validation
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $phone === '' || $city === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$safe = fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$to = 'aakash@pivotmkg.com';
$subject = 'New Contact Form Submission - JSK Buildwell';

$emailBody = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #C19D60; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; margin-top: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #C19D60; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Contact Form Submission</h2>
        </div>
        <div class='content'>
            <div class='field'>
                <span class='label'>Name:</span> {$safe($name)}
            </div>
            <div class='field'>
                <span class='label'>Email:</span> {$safe($email)}
            </div>
            <div class='field'>
                <span class='label'>Phone:</span> {$safe($phone)}
            </div>
            <div class='field'>
                <span class='label'>City:</span> {$safe($city)}
            </div>
            <div class='field'>
                <span class='label'>Message:</span><br>" . nl2br($safe($message)) . "
            </div>
        </div>
    </div>
</body>
</html>
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";
$headers .= "From: noreply@jskbuildwell.com\r\n";
$headers .= "Reply-To: {$safe($email)}\r\n";

// If the PHP mail transport is not available, mail() will return false.
if (!function_exists('mail')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail function is not available on this server. Configure SMTP or enable mail().']);
    exit;
}

$sent = mail($to, $subject, $emailBody, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} else {
    $lastError = error_get_last();
    http_response_code(500);
    $reason = $lastError['message'] ?? 'mail() could not send. Server mail transport is likely not configured.';
    echo json_encode(['success' => false, 'message' => $reason]);
}
