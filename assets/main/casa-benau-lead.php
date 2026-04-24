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

function detail_row($label, $value)
{
  $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
  $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  return "<tr><td style='padding:10px 0;border-top:1px solid #f1f1f1;color:#6b7280;font-size:13px;'>$safeLabel</td><td style='padding:10px 0;border-top:1px solid #f1f1f1;font-size:14px;font-weight:600;'>$safeValue</td></tr>";
}

function mail_headers($replyTo = '', $cc = [])
{
  $headers = "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  $headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>\r\n";

  if ($replyTo) {
    $headers .= "Reply-To: " . htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8') . "\r\n";
  }

  if (!empty($cc) && is_array($cc)) {
    $headers .= "Cc: " . implode(', ', array_map('htmlspecialchars', $cc)) . "\r\n";
  }

  return $headers;
}

function email_shell($title, $subtitle, $description, $content, $footer)
{
  $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
  $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
  $safeFooter = htmlspecialchars($footer, ENT_QUOTES, 'UTF-8');

  return "
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
        $safeTitle
      </td>
    </tr>
    <tr>
      <td style='padding:22px;'>
        <h2 style='margin:0 0 8px;font-size:18px;color:#1f2937;'>$safeSubtitle</h2>
        <p style='margin:0 0 16px;font-size:14px;color:#6b7280;'>$safeDescription</p>
        $content
        <p style='margin:24px 0 0;font-size:12px;color:#9ca3af;border-top:1px solid #f3f4f6;padding-top:16px;'>$safeFooter</p>
      </td>
    </tr>
  </table>
</body>
</html>";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(false, 'Method not allowed', 405);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$project = trim($_POST['project'] ?? 'Casa Benau 94 & 95');
$sourcePage = trim($_POST['source_page'] ?? '/projects/Casa-Benau.html');
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

if ($name === '' || $email === '' || $phone === '') {
  respond(false, 'Name, email and phone are required.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, 'Invalid email address.', 400);
}

if (!preg_match('/^[\d\s\-\+\(\)]{7,}$/', $phone)) {
  respond(false, 'Invalid phone number.', 400);
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

$to = 'rthomas@pivotmkg.com,sales@jskbuildwell.com,jskbuildwell@gmail.com';
// $to = 'aakash@pivotmkg.com';
$ccRecipients = [];
$salesEmail = 'sales@jskbuildwell.com';
$salesPhone = '+91 22 6236 5020';
$subject = 'New Casa Benau Lead - JSK Buildwell';
$userSubject = 'Thank you for your Casa Benau enquiry';

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeProject = htmlspecialchars($project, ENT_QUOTES, 'UTF-8');
$safeSourcePage = htmlspecialchars($sourcePage, ENT_QUOTES, 'UTF-8');
$safeSalesEmail = htmlspecialchars($salesEmail, ENT_QUOTES, 'UTF-8');
$safeSalesPhone = htmlspecialchars($salesPhone, ENT_QUOTES, 'UTF-8');
$safeProjectUrl = 'https://www.jskbuildwell.com/projects/Casa-Benau.html';
$submittedAt = date('Y-m-d H:i:s');
$ipAddress = htmlspecialchars((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ENT_QUOTES, 'UTF-8');

$adminContent = "
<div style='margin-bottom:20px;padding:18px 20px;border-radius:16px;background-color:#fbf8f2;border:1px solid #efe2ca;'>
  <p style='margin:0;color:#5f584d;font-size:14px;line-height:1.7;'>A new popup lead has been submitted from the Casa Benau project page. The enquiry details are below for immediate follow-up.</p>
</div>
<table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;'>
  " . detail_row('Project', $safeProject) . "
  " . detail_row('Name', $safeName) . "
  " . detail_row('Phone number', $safePhone) . "
  " . detail_row('Email', $safeEmail) . "
  " . detail_row('Source page', $safeSourcePage) . "
  " . detail_row('IP address', $ipAddress) . "
  " . detail_row('Submitted', htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8')) . "
</table>";

$adminMessageBody = email_shell(
  'New Lead',
  'Casa Benau popup enquiry received',
  'A prospective buyer has submitted their details and is expecting a follow-up from the Goa villa team.',
  $adminContent,
  'This notification was generated automatically from the JSK Buildwell website.'
);

$userContent = "
<p style='margin:0 0 16px;color:#4f4a40;font-size:15px;line-height:1.8;'>Dear $safeName,</p>
<p style='margin:0 0 18px;color:#4f4a40;font-size:15px;line-height:1.8;'>Thank you for your interest in <strong>$safeProject</strong>. We have received your enquiry and our team will get in touch shortly with the project brochure, pricing details, and next steps for a site visit.</p>
<div style='margin:0 0 22px;padding:18px 20px;border-radius:16px;background-color:#fbf8f2;border:1px solid #efe2ca;'>
  <div style='font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#9a7231;font-weight:700;margin-bottom:10px;'>Your enquiry details</div>
  <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;'>
    " . detail_row('Name', $safeName) . "
    " . detail_row('Phone number', $safePhone) . "
    " . detail_row('Email', $safeEmail) . "
    " . detail_row('Submitted', htmlspecialchars($submittedAt, ENT_QUOTES, 'UTF-8')) . "
  </table>
</div>
<div style='margin-bottom:22px;'>
  <div style='font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#9a7231;font-weight:700;margin-bottom:10px;'>What happens next</div>
  <ul style='margin:0;padding-left:18px;color:#4f4a40;font-size:15px;line-height:1.8;'>
    <li style='margin-bottom:8px;'>Our team will review your enquiry and contact you soon.</li>
    <li style='margin-bottom:8px;'>You can request brochure details, pricing, and a private site visit.</li>
    <li>For urgent assistance, reply to this email or call us directly.</li>
  </ul>
</div>
<table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:separate;border-spacing:0 12px;'>
  <tr>
    <td bgcolor='#1f2533' style='padding:16px 18px;border-radius:16px;background-color:#1f2533;color:#ffffff;'>
      <div style='font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#e8d6b1;font-weight:700;margin-bottom:8px;'>Contact JSK Buildwell</div>
      <div style='font-size:15px;line-height:1.8;'>Email: <a href='mailto:$safeSalesEmail' style='color:#ffffff;text-decoration:none;'>$safeSalesEmail</a><br>Phone: <a href='tel:$safeSalesPhone' style='color:#ffffff;text-decoration:none;'>$safeSalesPhone</a></div>
    </td>
  </tr>
</table>
<div style='margin-top:10px;'>
  <a href='$safeProjectUrl' style='display:inline-block;padding:13px 22px;border-radius:999px;background-color:#c19d60;color:#ffffff;text-decoration:none;font-weight:700;'>View Casa Benau</a>
</div>";

$userMessageBody = email_shell(
  'Thank You',
  'Your Casa Benau enquiry is with us',
  'We appreciate your interest in JSK Buildwell. This is a confirmation that your enquiry has been received successfully.',
  $userContent,
  'If you did not submit this enquiry, you can ignore this email.'
);

if (!mail($to, $subject, $adminMessageBody, mail_headers($email, $ccRecipients))) {
  respond(false, 'Failed to send the enquiry email.', 500);
}

if (!mail($email, $userSubject, $userMessageBody, mail_headers($salesEmail))) {
  error_log('Failed to send Casa Benau confirmation email to: ' . $email);
}

respond(true, 'Lead submitted successfully.');
