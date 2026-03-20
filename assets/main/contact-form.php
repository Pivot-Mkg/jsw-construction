<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function respond($success, $message, $status = 200)
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed', 405);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$message = trim($_POST['message'] ?? '');
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

if ($name === '' || $email === '' || $phone === '' || $city === '') {
    respond(false, 'Name, email, phone and city are required', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address', 400);
}

// Verify reCAPTCHA
if (empty($recaptchaResponse)) {
    respond(false, 'Please complete the reCAPTCHA verification', 400);
}

$recaptchaSecret = '6Lfy2JAsAAAAAHIIie7FqCsHzlZzCsODs0FsGzSP';
$recaptchaUrl = 'https://www.google.com/recaptcha/api/siteverify';
$recaptchaData = [
    'secret' => $recaptchaSecret,
    'response' => $recaptchaResponse,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

$options = [
    'http' => [
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($recaptchaData)
    ]
];

$context = stream_context_create($options);
$recaptchaResult = file_get_contents($recaptchaUrl, false, $context);
$recaptchaJson = json_decode($recaptchaResult);

if (!$recaptchaJson || !$recaptchaJson->success) {
    respond(false, 'reCAPTCHA verification failed. Please try again.', 400);
}

try {
    require_once __DIR__ . '../admin/includes/db.php';
    admin_insert_submission([
        'form_type' => 'contact',
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'city' => $city,
        'message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
} catch (Throwable $e) {
    error_log('Failed to store contact submission: ' . $e->getMessage());
    respond(false, 'Failed to save submission', 500);
}

// $to = 'aakash@pivotmkg.com,rthomas@pivotmkg.com,sales@jskbuildwell.com,jskbuildwell@gmail.com';
$to = 'aakash@pivotmkg.com';
$subject = 'New Contact Form Submission - JSK Buildwell';

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeCity = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$submittedAt = date('Y-m-d H:i:s');

$messageBlock = '';
if ($message !== '') {
    $messageBlock = "<tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Message</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeMessage</td></tr>";
}

$messageBody = "
<!doctype html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin:0;padding:24px;background:#f7f4ee;font-family:Arial,sans-serif;color:#1f2937;'>
  <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #eadfcf;border-radius:14px;overflow:hidden;'>
    <tr>
      <td style='background:#c19d60;color:#ffffff;padding:18px 22px;font-size:20px;font-weight:700;'>
        JSK Buildwell - Contact Form Submission
      </td>
    </tr>
    <tr>
      <td style='padding:22px;'>
        <p style='margin:0 0 16px;font-size:14px;color:#6b7280;'>A new contact enquiry has been received.</p>
        <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;'>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;width:130px;color:#6b7280;font-size:13px;'>Name</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeName</td></tr>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Email</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeEmail</td></tr>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Phone</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safePhone</td></tr>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>City</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeCity</td></tr>
          $messageBlock
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Submitted</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$submittedAt</td></tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>\r\n";
$headers .= "Reply-To: " . $email . "\r\n";

if (mail($to, $subject, $messageBody, $headers)) {
    respond(true, 'Message sent successfully');
}

respond(false, 'Failed to send message', 500);

