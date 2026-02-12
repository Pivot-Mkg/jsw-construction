<?php
require_once __DIR__ . '/includes/auth.php';

admin_start_session();

if (admin_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if (admin_count_admins() === 0) {
    header('Location: setup.php');
    exit;
}

header('Location: login.php');
exit;

