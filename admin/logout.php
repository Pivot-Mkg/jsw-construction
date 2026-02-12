<?php
require_once __DIR__ . '/includes/auth.php';

admin_start_session();
admin_logout();

header('Location: login.php');
exit;

