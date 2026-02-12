<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

admin_start_session();
admin_require_login();

$view = ($_GET['view'] ?? 'index') === 'contact' ? 'contact' : 'index';
$search = trim((string) ($_GET['q'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } elseif ($action === 'delete' && $id > 0) {
        admin_delete_submission($id);
        $redirect = 'dashboard.php?view=' . urlencode($view) . '&deleted=1';
        if ($search !== '') {
            $redirect .= '&q=' . urlencode($search);
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$counts = admin_submission_counts();
$rows = admin_submissions_by_type($view);
$allCountForView = count($rows);

if ($search !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
        $needle = strtolower($search);
        $haystack = strtolower(implode(' ', [
            (string) ($row['id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['message'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

$filteredCount = count($rows);

function initials_from_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    if (count($parts) === 1 && $parts[0] !== '') {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return 'NA';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>JSK Buildwell Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#C19D60",
                        secondary: "#1E293B",
                        "sidebar-light": "#F8F8F8",
                        "background-light": "#FAF9F6",
                        "surface-light": "#FFFFFF",
                        "gold-accent": "#C19D60",
                        "text-dark": "#334155"
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"]
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-background-light text-slate-800 font-sans antialiased">
<div class="flex h-screen overflow-hidden">
    <aside class="hidden md:flex flex-col w-64 bg-sidebar-light border-r border-slate-200 shadow-xl">
        <div class="p-6 flex items-center justify-center border-b border-slate-200">
            <div class="flex flex-col items-center">
                <span class="material-icons text-primary text-4xl mb-2">apartment</span>
                <h1 class="text-xl font-bold tracking-wide text-secondary">JSK Buildwell</h1>
                <span class="text-xs text-primary uppercase tracking-widest font-medium">Admin Panel</span>
            </div>
        </div>
        <nav class="flex-1 py-6 space-y-2 px-3">
            <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 hover:text-primary rounded-lg transition-colors group" href="dashboard.php?view=<?= admin_e($view) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                <span class="material-icons text-slate-400 group-hover:text-primary mr-3">dashboard</span>
                <span class="font-medium">Dashboard</span>
            </a>
            <a class="flex items-center px-4 py-3 <?= $view === 'index' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' ?> rounded-lg transition-colors" href="dashboard.php?view=index<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                <span class="material-icons <?= $view === 'index' ? 'text-primary' : 'text-slate-400' ?> mr-3">table_chart</span>
                <span class="font-medium">Index Forms</span>
                <span class="ml-auto <?= $view === 'index' ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600' ?> text-xs font-bold px-2 py-0.5 rounded-full"><?= (int) $counts['index'] ?></span>
            </a>
            <a class="flex items-center px-4 py-3 <?= $view === 'contact' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' ?> rounded-lg transition-colors" href="dashboard.php?view=contact<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                <span class="material-icons <?= $view === 'contact' ? 'text-primary' : 'text-slate-400' ?> mr-3">contact_mail</span>
                <span class="font-medium">Contact Forms</span>
                <span class="ml-auto <?= $view === 'contact' ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600' ?> text-xs font-bold px-2 py-0.5 rounded-full"><?= (int) $counts['contact'] ?></span>
            </a>
        </nav>
        <div class="p-4 border-t border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold border border-primary/20">A</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-secondary truncate">aakash</p>
                    <p class="text-xs text-slate-500 truncate"><?= admin_e((string) $_SESSION['admin_email']) ?></p>
                </div>
                <a class="text-slate-400 hover:text-primary transition-colors" href="logout.php" title="Logout">
                    <span class="material-icons text-sm">logout</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-y-auto bg-background-light">
        <header class="md:hidden flex items-center justify-between p-4 bg-white text-secondary border-b border-slate-200">
            <span class="font-bold text-lg">JSK Buildwell</span>
            <a class="text-secondary focus:outline-none" href="logout.php"><span class="material-icons">logout</span></a>
        </header>

        <header class="hidden md:flex justify-between items-center py-5 px-8 bg-surface-light border-b border-slate-200/60 shadow-sm">
            <div>
                <h2 class="text-2xl font-bold text-secondary"><?= $view === 'index' ? 'Index Forms' : 'Contact Forms' ?></h2>
                <p class="text-sm text-slate-500 mt-1">Manage submission entries from your website forms.</p>
            </div>
            <div class="flex items-center gap-4">
                <a class="text-sm font-medium text-secondary hover:text-primary transition-colors flex items-center gap-1" href="logout.php">
                    Logout
                    <span class="material-icons text-sm">logout</span>
                </a>
            </div>
        </header>

        <main class="flex-1 p-6 md:p-8 bg-background-light">
            <?php if ($error !== ''): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= admin_e($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Entry deleted.</div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Entry updated.</div>
            <?php endif; ?>

            <div class="mb-8 flex flex-wrap gap-3">
                <a class="px-6 py-2.5 rounded-full <?= $view === 'index' ? 'bg-primary text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-primary/50 hover:text-primary' ?> font-semibold shadow-sm transition-all" href="dashboard.php?view=index<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                    Index Form (<?= (int) $counts['index'] ?>)
                </a>
                <a class="px-6 py-2.5 rounded-full <?= $view === 'contact' ? 'bg-primary text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-primary/50 hover:text-primary' ?> font-semibold shadow-sm transition-all" href="dashboard.php?view=contact<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                    Contact Form (<?= (int) $counts['contact'] ?>)
                </a>
            </div>

            <div class="bg-surface-light rounded-xl shadow-lg border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap justify-between items-center gap-3 bg-slate-50/30">
                    <form class="relative" method="get">
                        <input type="hidden" name="view" value="<?= admin_e($view) ?>">
                        <span class="material-icons absolute left-3 top-2.5 text-primary text-sm">search</span>
                        <input class="pl-9 pr-4 py-2 text-sm border-slate-200 rounded-lg bg-white focus:ring-primary focus:border-primary w-72 shadow-sm" name="q" placeholder="Search entries..." type="text" value="<?= admin_e($search) ?>"/>
                    </form>
                    <div class="text-xs text-slate-500">
                        Showing <span class="font-semibold text-slate-800"><?= (int) $filteredCount ?></span> of <span class="font-semibold text-slate-800"><?= (int) $allCountForView ?></span> <?= $view === 'index' ? 'index' : 'contact' ?> submissions
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50/80 text-xs uppercase font-semibold text-slate-500">
                        <tr>
                            <th class="px-6 py-4 tracking-wider" scope="col">ID</th>
                            <th class="px-6 py-4 tracking-wider" scope="col">Name</th>
                            <th class="px-6 py-4 tracking-wider" scope="col">Email</th>
                            <th class="px-6 py-4 tracking-wider" scope="col">Phone</th>
                            <?php if ($view === 'contact'): ?>
                                <th class="px-6 py-4 tracking-wider" scope="col">City</th>
                                <th class="px-6 py-4 tracking-wider" scope="col">Message</th>
                            <?php endif; ?>
                            <th class="px-6 py-4 tracking-wider" scope="col">Created</th>
                            <th class="px-6 py-4 tracking-wider text-right" scope="col">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (!$rows): ?>
                            <tr>
                                <td class="px-6 py-8 text-center text-slate-500" colspan="<?= $view === 'contact' ? '8' : '6' ?>">No submissions found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $entryName = (string) ($row['name'] ?? '');
                                $initials = initials_from_name($entryName);
                                $created = (string) ($row['created_at'] ?? '');
                                $createdDate = $created;
                                $createdTime = '';
                                if ($created !== '') {
                                    $parts = preg_split('/[T ]/', $created);
                                    if (is_array($parts) && count($parts) > 1) {
                                        $createdDate = $parts[0];
                                        $createdTime = substr($parts[1], 0, 8);
                                    }
                                }
                                ?>
                                <tr class="hover:bg-amber-50/50 transition-colors group border-l-2 border-transparent hover:border-primary">
                                    <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">#<?= (int) $row['id'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-primary to-[#D4B073] flex items-center justify-center text-white font-bold text-xs shadow-sm"><?= admin_e($initials) ?></div>
                                            <span class="font-semibold text-slate-800"><?= admin_e($entryName) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-xs text-primary/70">email</span>
                                            <a class="text-slate-600 hover:text-primary hover:underline transition-colors" href="mailto:<?= admin_e((string) $row['email']) ?>"><?= admin_e((string) $row['email']) ?></a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-xs text-primary/70">phone</span>
                                            <span><?= admin_e((string) $row['phone']) ?></span>
                                        </div>
                                    </td>
                                    <?php if ($view === 'contact'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= admin_e((string) ($row['city'] ?? '')) ?></td>
                                        <td class="px-6 py-4 max-w-xs truncate" title="<?= admin_e((string) ($row['message'] ?? '')) ?>"><?= admin_e((string) ($row['message'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                                        <?= admin_e($createdDate) ?><?php if ($createdTime !== ''): ?><span class="text-xs ml-1 opacity-75"><?= admin_e($createdTime) ?></span><?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a class="inline-flex items-center px-3 py-1.5 border border-primary/30 rounded-md text-primary bg-primary/5 hover:bg-primary hover:text-white transition-colors text-xs font-medium shadow-sm" href="edit.php?id=<?= (int) $row['id'] ?>">
                                                <span class="material-icons text-sm mr-1">edit</span>
                                                Edit
                                            </a>
                                            <form method="post" onsubmit="return confirm('Delete this entry?');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <button class="inline-flex items-center px-3 py-1.5 border border-red-100 rounded-md text-red-600 bg-red-50 hover:bg-red-100 transition-colors text-xs font-medium shadow-sm" type="submit">
                                                    <span class="material-icons text-sm mr-1">delete</span>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Showing <span class="font-medium text-slate-900"><?= $filteredCount > 0 ? 1 : 0 ?></span>
                        to <span class="font-medium text-slate-900"><?= (int) $filteredCount ?></span>
                        of <span class="font-medium text-slate-900"><?= (int) $allCountForView ?></span> results
                    </span>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
