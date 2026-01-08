<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (empty($name) || empty($email) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email and phone are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Log the submission for backup
$logData = "Name: $name, Email: $email, Phone: $phone, Date: " . date('Y-m-d H:i:s') . "\n";
file_put_contents('form_submissions.log', $logData, FILE_APPEND | LOCK_EX);

// Send email using PHPMailer with SMTP (you'll need to configure this)
$to = 'aakash@pivotmkg.com';
$subject = 'New Private Tour Request - JSK Buildwell';

$emailMessage = "
<html>
<head>
    <title>New Private Tour Request</title>
</head>
<body>
    <h2>New Private Tour Request</h2>
    <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
    <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
    <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
    <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
</body>
</html>
";

// For now, just log and return success
// TODO: Configure actual email sending
echo json_encode(['success' => true, 'message' => 'Private tour request received successfully! We will contact you soon.']);
?>
