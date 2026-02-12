<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

admin_start_session();
admin_require_login();

$view = ($_GET['view'] ?? 'index') === 'contact' ? 'contact' : 'index';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } elseif ($action === 'delete' && $id > 0) {
        admin_delete_submission($id);
        header('Location: dashboard.php?view=' . urlencode($view) . '&deleted=1');
        exit;
    }
}

$counts = admin_submission_counts();
$rows = admin_submissions_by_type($view);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div>
                <div class="brand">JSK Buildwell Admin</div>
                <div class="sub" style="margin:2px 0 0;">Signed in as <?= admin_e((string) $_SESSION['admin_email']) ?></div>
            </div>
            <a class="btn btn-link" href="logout.php">Logout</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= admin_e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-ok">Entry deleted.</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-ok">Entry updated.</div>
        <?php endif; ?>

        <div class="chip-row">
            <a class="chip <?= $view === 'index' ? 'active' : '' ?>" href="dashboard.php?view=index">
                Index Form (<?= (int) $counts['index'] ?>)
            </a>
            <a class="chip <?= $view === 'contact' ? 'active' : '' ?>" href="dashboard.php?view=contact">
                Contact Form (<?= (int) $counts['contact'] ?>)
            </a>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <?php if ($view === 'contact'): ?>
                            <th>City</th>
                            <th>Message</th>
                        <?php endif; ?>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td class="empty" colspan="<?= $view === 'contact' ? '8' : '6' ?>">No submissions yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= admin_e((string) $row['name']) ?></td>
                                <td><?= admin_e((string) $row['email']) ?></td>
                                <td><?= admin_e((string) $row['phone']) ?></td>
                                <?php if ($view === 'contact'): ?>
                                    <td><?= admin_e((string) ($row['city'] ?? '')) ?></td>
                                    <td><?= admin_e((string) ($row['message'] ?? '')) ?></td>
                                <?php endif; ?>
                                <td><?= admin_e((string) $row['created_at']) ?></td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn-link" href="edit.php?id=<?= (int) $row['id'] ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this entry?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

