<?php
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/csrf.php';

    admin_start_session();

    if (admin_count_admins() > 0) {
    header('Location: login.php');
    exit;
    }

    $error   = '';
    $success = '';
    $email   = 'aakash@pivotmkg.com';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        if ($password === '' || $confirm === '') {
            $error = 'Password fields are required.';
        } elseif (strlen($password) < 10) {
            $error = 'Use at least 10 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            admin_create_user($email, $password);
            $success = 'Admin account created. Continue to login.';
        }
    }
    }
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Setup</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>

<body>
    <div class="auth-wrap">
        <div class="card auth-card">
            <h1 class="title">Admin Setup</h1>
            <p class="sub">Create the secured admin login for form management.</p>

            <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo admin_e($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
            <div class="alert alert-ok"><?php echo admin_e($success) ?></div>
            <p><a class="btn-link" href="login.php">Go to login</a></p>
            <?php else: ?>
            <form method="post">
                <?php echo csrf_input() ?>
                <label>Email</label>
                <input type="email" value="<?php echo admin_e($email) ?>" readonly>

                <label>Password</label>
                <input type="password" name="password" required minlength="10" autocomplete="new-password">

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password">

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Create Admin</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>