<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function respond($success, $message, $status = 200) {
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

if ($name === '' || $email === '' || $phone === '') {
    respond(false, 'Name, email and phone are required', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address', 400);
}

try {
    require_once __DIR__ . '/admin/includes/db.php';
    admin_insert_submission([
        'form_type' => 'index',
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'city' => null,
        'message' => null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
} catch (Throwable $e) {
    error_log('Failed to store index submission: ' . $e->getMessage());
    respond(false, 'Failed to save submission', 500);
}

$to = 'aakash@pivotmkg.com';
$subject = 'New Private Tour Request - JSK Buildwell';

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$submittedAt = date('Y-m-d H:i:s');

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
        JSK Buildwell - Private Tour Request
      </td>
    </tr>
    <tr>
      <td style='padding:22px;'>
        <p style='margin:0 0 16px;font-size:14px;color:#6b7280;'>A new private tour request has been submitted.</p>
        <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;'>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;width:130px;color:#6b7280;font-size:13px;'>Name</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeName</td></tr>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Email</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeEmail</td></tr>
          <tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>Phone</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safePhone</td></tr>
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
    respond(true, 'Request sent successfully');
}

respond(false, 'Failed to send request', 500);
