<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

function wants_json_response(): bool
{
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function respond(bool $success, string $message, int $status = 200): void
{
    if (wants_json_response()) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);
        exit;
    }

    if ($success) {
        header('Location: /thank-you.html');
        exit;
    }

    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function mail_headers(?string $replyTo = null, array $ccRecipients = []): string
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>\r\n";

    if ($replyTo !== null && $replyTo !== '') {
        $headers .= "Reply-To: $replyTo\r\n";
    }

    $ccRecipients = array_values(array_unique(array_filter(array_map('trim', $ccRecipients))));
    if ($ccRecipients !== []) {
        $headers .= 'Cc: ' . implode(',', $ccRecipients) . "\r\n";
    }

    return $headers;
}

function detail_row(string $label, string $value): string
{
    return "<tr>
        <td style='padding:12px 0;border-top:1px solid #ece7de;width:140px;color:#7a7468;font-size:13px;vertical-align:top;'>$label</td>
        <td style='padding:12px 0;border-top:1px solid #ece7de;color:#1f2937;font-size:14px;font-weight:600;line-height:1.6;'>$value</td>
    </tr>";
}

function email_shell(string $eyebrow, string $title, string $intro, string $content, string $footer = ''): string
{
    $footerBlock = $footer !== ''
        ? "<p style='margin:20px 0 0;color:#7a7468;font-size:12px;line-height:1.6;'>$footer</p>"
        : '';

    return "
<!doctype html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin:0;padding:24px;background:#f4efe7;font-family:Arial,sans-serif;color:#1f2937;'>
  <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='max-width:680px;margin:0 auto;border-collapse:collapse;'>
    <tr>
      <td style='padding-bottom:18px;text-align:center;'>
        <div style='display:inline-block;padding:7px 14px;border-radius:999px;background:#efe2ca;color:#9a7231;font-size:11px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;'>$eyebrow</div>
      </td>
    </tr>
    <tr>
      <td style='background:#ffffff;border:1px solid #eadfcf;border-radius:20px;overflow:hidden;box-shadow:0 12px 40px rgba(31,41,55,0.08);'>
        <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;'>
          <tr>
            <td style='padding:34px 32px 18px;background:linear-gradient(135deg,#1f2533 0%,#3e2f20 100%);color:#ffffff;'>
              <div style='font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:#e8d6b1;font-weight:700;'>JSK Buildwell</div>
              <h1 style='margin:14px 0 10px;font-size:30px;line-height:1.1;font-family:Georgia,serif;font-weight:700;color:#ffffff;'>$title</h1>
              <p style='margin:0;font-size:15px;line-height:1.7;color:#f3eee4;'>$intro</p>
            </td>
          </tr>
          <tr>
            <td style='padding:28px 32px 32px;background:#ffffff;'>
              $content
              $footerBlock
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed', 405);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$project = trim((string) ($_POST['project'] ?? 'Casa Benau 94 & 95'));
$sourcePage = trim((string) ($_POST['source_page'] ?? '/projects/Casa-Benau.html'));

if ($name === '' || $email === '' || $phone === '') {
    respond(false, 'Name, email and phone are required.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address.', 400);
}

if (!preg_match('/^[0-9+\-\s()]{7,}$/', $phone)) {
    respond(false, 'Invalid phone number.', 400);
}

$to = 'sales@jskbuildwell.com';
$ccRecipients = [
    'aakash@pivotmkg.com',
    'rthomas@pivotmkg.com',
    'jskbuildwell@gmail.com',
];
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
<div style='margin-bottom:20px;padding:18px 20px;border-radius:16px;background:#fbf8f2;border:1px solid #efe2ca;'>
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
<div style='margin:0 0 22px;padding:18px 20px;border-radius:16px;background:#fbf8f2;border:1px solid #efe2ca;'>
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
    <td style='padding:16px 18px;border-radius:16px;background:#1f2533;color:#ffffff;'>
      <div style='font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#e8d6b1;font-weight:700;margin-bottom:8px;'>Contact JSK Buildwell</div>
      <div style='font-size:15px;line-height:1.8;'>Email: <a href='mailto:$safeSalesEmail' style='color:#ffffff;text-decoration:none;'>$safeSalesEmail</a><br>Phone: <a href='tel:$safeSalesPhone' style='color:#ffffff;text-decoration:none;'>$safeSalesPhone</a></div>
    </td>
  </tr>
</table>
<div style='margin-top:10px;'>
  <a href='$safeProjectUrl' style='display:inline-block;padding:13px 22px;border-radius:999px;background:#c19d60;color:#ffffff;text-decoration:none;font-weight:700;'>View Casa Benau</a>
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
