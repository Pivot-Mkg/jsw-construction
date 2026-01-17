<?php
// Test PHPMailer installation
require 'vendor/autoload.php';

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "✓ PHPMailer loaded successfully!\n";
    echo "✓ PHPMailer version: " . PHPMailer\PHPMailer\PHPMailer::VERSION . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
