<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

admin_start_session();

if (admin_count_admins() === 0) {
    header('Location: setup.php');
    exit;
}

if (admin_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = 'aakash@pivotmkg.com';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!admin_login($email, $password)) {
        $error = 'Invalid email or password.';
    } else {
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="card auth-card">
            <h1 class="title">Admin Login</h1>
            <p class="sub">Manage submissions from both website forms.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= admin_e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_input() ?>
                <label>Email</label>
                <input type="email" name="email" required value="<?= admin_e($email) ?>" autocomplete="username">

                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password">

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Sign In</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

