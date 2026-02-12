<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

admin_start_session();
admin_require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$entry = $id > 0 ? admin_submission_by_id($id) : null;

if (!$entry) {
    http_response_code(404);
    echo 'Submission not found.';
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $phone === '') {
            $error = 'Name, email and phone are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email is invalid.';
        } elseif ($entry['form_type'] === 'contact' && $city === '') {
            $error = 'City is required for contact submissions.';
        } else {
            admin_update_submission((int) $entry['id'], [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'city' => $entry['form_type'] === 'contact' ? $city : null,
                'message' => $entry['form_type'] === 'contact' ? $message : null,
            ]);
            header('Location: dashboard.php?view=' . urlencode((string) $entry['form_type']) . '&updated=1');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Submission</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title" style="margin-bottom:4px;">Edit Submission #<?= (int) $entry['id'] ?></h1>
                <p class="sub" style="margin:0;">Form type: <?= admin_e((string) $entry['form_type']) ?></p>
            </div>
            <a class="btn btn-link" href="dashboard.php?view=<?= admin_e((string) $entry['form_type']) ?>">Back</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= admin_e($error) ?></div>
        <?php endif; ?>

        <div class="card" style="padding:20px;">
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">

                <div class="split">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" required value="<?= admin_e((string) ($_POST['name'] ?? $entry['name'])) ?>">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" required value="<?= admin_e((string) ($_POST['email'] ?? $entry['email'])) ?>">
                    </div>
                    <div>
                        <label>Phone</label>
                        <input type="text" name="phone" required value="<?= admin_e((string) ($_POST['phone'] ?? $entry['phone'])) ?>">
                    </div>

                    <?php if ($entry['form_type'] === 'contact'): ?>
                        <div>
                            <label>City</label>
                            <input type="text" name="city" required value="<?= admin_e((string) ($_POST['city'] ?? ($entry['city'] ?? ''))) ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($entry['form_type'] === 'contact'): ?>
                    <label>Message</label>
                    <textarea name="message"><?= admin_e((string) ($_POST['message'] ?? ($entry['message'] ?? ''))) ?></textarea>
                <?php endif; ?>

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

