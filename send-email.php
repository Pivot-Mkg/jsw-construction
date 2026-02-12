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

$to = 'aakash@pivotmkg.com';
$subject = 'New Private Tour Request - JSK Buildwell';

$messageBody = "<html><body>";
$messageBody .= "<h2>New Private Tour Request</h2>";
$messageBody .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
$messageBody .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
$messageBody .= "<p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>";
$messageBody .= "<p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>";
$messageBody .= "</body></html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: JSK Buildwell <noreply@jskbuildwell.com>\r\n";
$headers .= "Reply-To: " . $email . "\r\n";

if (mail($to, $subject, $messageBody, $headers)) {
    respond(true, 'Request sent successfully');
}

respond(false, 'Failed to send request', 500);

