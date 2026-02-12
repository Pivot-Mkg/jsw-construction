<?php
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/csrf.php';

    admin_start_session();
    admin_require_login();

    $view     = ($_GET['view'] ?? 'index') === 'contact' ? 'contact' : 'index';
    $search   = trim((string) ($_GET['q'] ?? ''));
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo   = trim((string) ($_GET['date_to'] ?? ''));
    $error    = '';

    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
    }
    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
    }

    function dashboard_query(array $overrides = []): string
    {
    global $view, $search, $dateFrom, $dateTo;
    $params = [
        'view' => $view,
    ];

    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return 'dashboard.php?' . http_build_query($params);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if (! csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token.';
    } elseif ($action === 'delete' && $id > 0) {
        admin_delete_submission($id);
        header('Location: ' . dashboard_query(['deleted' => '1']));
        exit;
    } elseif ($action === 'import_csv') {
        if (! isset($_FILES['import_file']) || (int) ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please choose a valid CSV file.';
        } else {
            $tmpPath = (string) ($_FILES['import_file']['tmp_name'] ?? '');
            $handle  = @fopen($tmpPath, 'r');
            if (! $handle) {
                $error = 'Unable to read uploaded CSV.';
            } else {
                $header = fgetcsv($handle);
                if (! is_array($header)) {
                    $error = 'CSV appears empty.';
                } else {
                    $normalizedHeader = array_map(static function ($col): string {
                        return strtolower(trim((string) $col));
                    }, $header);
                    $map = array_flip($normalizedHeader);

                    $required = ['name', 'email', 'phone'];
                    if ($view === 'contact') {
                        $required[] = 'city';
                    }

                    $missing = array_values(array_filter($required, static function ($field) use ($map): bool {
                        return ! array_key_exists($field, $map);
                    }));

                    if ($missing) {
                        $error = 'Missing required CSV columns: ' . implode(', ', $missing);
                    } else {
                        $imported = 0;
                        $skipped  = 0;

                        while (($row = fgetcsv($handle)) !== false) {
                            $name    = trim((string) ($row[$map['name']] ?? ''));
                            $email   = trim((string) ($row[$map['email']] ?? ''));
                            $phone   = trim((string) ($row[$map['phone']] ?? ''));
                            $city    = trim((string) ($row[$map['city']] ?? ''));
                            $message = trim((string) ($row[$map['message']] ?? ''));

                            if ($name === '' || $email === '' || $phone === '') {
                                $skipped++;
                                continue;
                            }
                            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $skipped++;
                                continue;
                            }
                            if ($view === 'contact' && $city === '') {
                                $skipped++;
                                continue;
                            }

                            admin_insert_submission([
                                'form_type'  => $view,
                                'name'       => $name,
                                'email'      => $email,
                                'phone'      => $phone,
                                'city'       => $view === 'contact' ? $city : null,
                                'message'    => $view === 'contact' ? $message : null,
                                'ip_address' => 'import',
                                'user_agent' => 'csv import',
                            ]);
                            $imported++;
                        }

                        fclose($handle);
                        header('Location: ' . dashboard_query([
                            'imported' => (string) $imported,
                            'skipped'  => (string) $skipped,
                        ]));
                        exit;
                    }
                }
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }
    }

    $rows = admin_submissions_by_type($view);

    if ($search !== '' || $dateFrom !== '' || $dateTo !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($search, $dateFrom, $dateTo): bool {
        if ($dateFrom !== '' || $dateTo !== '') {
            $created   = (string) ($row['created_at'] ?? '');
            $createdTs = strtotime($created);
            if ($createdTs === false) {
                return false;
            }
            if ($dateFrom !== '') {
                $fromTs = strtotime($dateFrom . ' 00:00:00');
                if ($fromTs !== false && $createdTs < $fromTs) {
                    return false;
                }
            }
            if ($dateTo !== '') {
                $toTs = strtotime($dateTo . ' 23:59:59');
                if ($toTs !== false && $createdTs > $toTs) {
                    return false;
                }
            }
        }

        if ($search !== '') {
            $needle   = strtolower($search);
            $haystack = strtolower(implode(' ', [
                (string) ($row['id'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['phone'] ?? ''),
                (string) ($row['city'] ?? ''),
                (string) ($row['message'] ?? ''),
                (string) ($row['created_at'] ?? ''),
            ]));
            if (strpos($haystack, $needle) === false) {
                return false;
            }
        }

        return true;
    }));
    }

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
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>JSK Buildwell Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                    "surface-light": "#FFFFFF"
                },
                fontFamily: {
                    sans: ["Inter", "sans-serif"]
                }
            }
        }
    };
    </script>
    <style>
    .flatpickr-input[readonly] {
        background-color: #fff !important;
        cursor: pointer;
    }
    </style>
</head>

<body class="bg-background-light text-slate-800 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <aside id="sidebar"
            class="hidden md:flex flex-col w-64 bg-sidebar-light border-r border-slate-200 shadow-xl transition-all duration-300">
            <div id="sidebarHeader"
                class="p-4 flex items-center justify-between border-b border-slate-200 transition-all duration-300">
                <div id="sidebarBrandWrap" class="flex items-center gap-3 min-w-0 transition-all duration-300">
                    <span class="material-icons text-primary text-3xl">apartment</span>
                    <div class="sidebar-text">
                        <h1 class="text-base font-bold tracking-wide text-secondary leading-tight">JSK Buildwell</h1>
                        <span class="text-[10px] text-primary uppercase tracking-widest font-medium">Admin Panel</span>
                    </div>
                </div>
                <button id="sidebarToggle" type="button"
                    class="rounded-md p-1.5 text-slate-500 hover:text-primary hover:bg-white border border-slate-200">
                    <span id="sidebarToggleIcon" class="material-icons text-base">chevron_left</span>
                </button>
            </div>

            <nav class="flex-1 py-6 space-y-2 px-3">
                <a class="sidebar-link flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 hover:text-primary rounded-lg transition-colors group"
                    href="<?php echo admin_e(dashboard_query()) ?>">
                    <span
                        class="sidebar-icon material-icons text-slate-400 group-hover:text-primary mr-3">dashboard</span>
                    <span class="sidebar-text font-medium">Dashboard</span>
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 <?php echo $view === 'index' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' ?> rounded-lg transition-colors"
                    href="<?php echo admin_e(dashboard_query(['view' => 'index'])) ?>">
                    <span
                        class="sidebar-icon material-icons <?php echo $view === 'index' ? 'text-primary' : 'text-slate-400' ?> mr-3">table_chart</span>
                    <span class="sidebar-text font-medium">Index Forms</span>
                </a>
                <a class="sidebar-link flex items-center px-4 py-3 <?php echo $view === 'contact' ? 'bg-white text-primary shadow-sm ring-1 ring-primary/20' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' ?> rounded-lg transition-colors"
                    href="<?php echo admin_e(dashboard_query(['view' => 'contact'])) ?>">
                    <span
                        class="sidebar-icon material-icons <?php echo $view === 'contact' ? 'text-primary' : 'text-slate-400' ?> mr-3">contact_mail</span>
                    <span class="sidebar-text font-medium">Contact Forms</span>
                </a>
            </nav>

            <div class="p-4 border-t border-slate-200">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold border border-primary/20">
                        A</div>
                    <div class="sidebar-text flex-1 min-w-0">
                        <p class="text-sm font-medium text-secondary truncate">aakash</p>
                        <p class="text-xs text-slate-500 truncate"><?php echo admin_e((string) $_SESSION['admin_email']) ?></p>
                    </div>
                    <a class="text-slate-400 hover:text-primary transition-colors" href="logout.php" title="Logout">
                        <span class="material-icons text-sm">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-y-auto bg-background-light">
            <header
                class="md:hidden flex items-center justify-between p-4 bg-white text-secondary border-b border-slate-200">
                <span class="font-bold text-lg">JSK Buildwell</span>
                <a class="text-secondary focus:outline-none" href="logout.php"><span
                        class="material-icons">logout</span></a>
            </header>

            <header
                class="hidden md:flex justify-between items-center py-5 px-8 bg-surface-light border-b border-slate-200/60 shadow-sm">
                <div>
                    <h2 class="text-2xl font-bold text-secondary">
                        <?php echo $view === 'index' ? 'Index Forms' : 'Contact Forms' ?></h2>
                    <p class="text-sm text-slate-500 mt-1">Manage submission entries from your website forms.</p>
                </div>
            </header>

            <main class="flex-1 p-6 md:p-8 bg-background-light">
                <?php if ($error !== ''): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo admin_e($error) ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Entry deleted.</div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Entry updated.</div>
                <?php endif; ?>
                <?php if (isset($_GET['imported'])): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Imported <?php echo (int) $_GET['imported'] ?>
                    row(s)<?php echo isset($_GET['skipped']) ? '; skipped ' . (int) $_GET['skipped'] . ' invalid row(s).' : '.' ?>
                </div>
                <?php endif; ?>

                <div class="bg-surface-light rounded-xl border border-slate-100 overflow-hidden">
                    <div
                        class="px-6 py-4 border-b border-slate-100 bg-slate-50/30 flex flex-wrap justify-between items-end gap-3">
                        <form class="flex flex-wrap items-end gap-3" method="get">
                            <input type="hidden" name="view" value="<?php echo admin_e($view) ?>">
                            <div class="relative">
                                <span class="material-icons absolute left-3 top-2.5 text-primary text-sm">search</span>
                                <input
                                    class="pl-9 pr-4 py-2 text-sm border-slate-200 rounded-lg bg-white focus:ring-primary focus:border-primary w-72 shadow-sm"
                                    name="q" placeholder="Search entries..." type="text"
                                    value="<?php echo admin_e($search) ?>" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">From</label>
                                <input
                                    class="js-date-input py-2 px-3 text-sm border-slate-200 rounded-lg bg-white focus:ring-primary focus:border-primary shadow-sm"
                                    name="date_from" type="text" placeholder="YYYY-MM-DD"
                                    value="<?php echo admin_e($dateFrom) ?>">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">To</label>
                                <input
                                    class="js-date-input py-2 px-3 text-sm border-slate-200 rounded-lg bg-white focus:ring-primary focus:border-primary shadow-sm"
                                    name="date_to" type="text" placeholder="YYYY-MM-DD" value="<?php echo admin_e($dateTo) ?>">
                            </div>
                            <button
                                class="inline-flex items-center px-4 py-2 border border-primary rounded-lg text-primary bg-white hover:bg-primary hover:text-white transition-colors text-sm font-medium shadow-sm"
                                type="submit">
                                <span class="material-icons text-sm mr-1">filter_alt</span>
                                Apply
                            </button>
                            <a class="inline-flex items-center px-4 py-2 border border-slate-200 rounded-lg text-slate-600 bg-white hover:border-primary/40 hover:text-primary transition-colors text-sm font-medium shadow-sm"
                                href="<?php echo admin_e(dashboard_query(['q' => '', 'date_from' => '', 'date_to' => ''])) ?>">
                                Clear
                            </a>
                        </form>
                        <form id="csvImportForm" class="flex items-end gap-2" method="post"
                            enctype="multipart/form-data">
                            <?php echo csrf_input() ?>
                            <input type="hidden" name="action" value="import_csv">
                            <input id="csvImportInput" type="file" name="import_file" accept=".csv,text/csv"
                                class="hidden" required>
                            <button id="csvImportTrigger"
                                class="inline-flex items-center px-4 py-2 border border-primary rounded-lg text-primary bg-white hover:bg-primary hover:text-white transition-colors text-sm font-medium shadow-sm"
                                type="button">
                                <span class="material-icons text-sm mr-1">file_upload</span>
                                Import CSV
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-50/80 text-xs uppercase font-semibold text-slate-500">
                                <tr>
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
                                <?php if (! $rows): ?>
                                <tr>
                                    <td class="px-6 py-8 text-center text-slate-500"
                                        colspan="<?php echo $view === 'contact' ? '7' : '5' ?>">No submissions found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                <?php
                                    $entryName   = (string) ($row['name'] ?? '');
                                    $initials    = initials_from_name($entryName);
                                    $created     = (string) ($row['created_at'] ?? '');
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
                                <tr
                                    class="hover:bg-amber-50/50 transition-colors group border-l-2 border-transparent hover:border-primary">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-8 w-8 rounded-full bg-gradient-to-br from-primary to-[#D4B073] flex items-center justify-center text-white font-bold text-xs shadow-sm">
                                                <?php echo admin_e($initials) ?></div>
                                            <span class="font-semibold text-slate-800"><?php echo admin_e($entryName) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-xs text-primary/70">email</span>
                                            <a class="text-slate-600 hover:text-primary hover:underline transition-colors"
                                                href="mailto:<?php echo admin_e((string) $row['email']) ?>"><?php echo admin_e((string) $row['email']) ?></a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons text-xs text-primary/70">phone</span>
                                            <span><?php echo admin_e((string) $row['phone']) ?></span>
                                        </div>
                                    </td>
                                    <?php if ($view === 'contact'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo admin_e((string) ($row['city'] ?? '')) ?></td>
                                    <td class="px-6 py-4 max-w-xs truncate"
                                        title="<?php echo admin_e((string) ($row['message'] ?? '')) ?>">
                                        <?php echo admin_e((string) ($row['message'] ?? '')) ?></td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                                        <?php echo admin_e($createdDate) ?><?php if ($createdTime !== ''): ?><span
                                            class="text-xs ml-1 opacity-75"><?php echo admin_e($createdTime) ?></span><?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <div
                                            class="flex items-center justify-end gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a class="inline-flex items-center px-3 py-1.5 border border-primary/30 rounded-md text-primary bg-primary/5 hover:bg-primary hover:text-white transition-colors text-xs font-medium shadow-sm"
                                                href="edit.php?id=<?php echo (int) $row['id'] ?>">
                                                <span class="material-icons text-sm mr-1">edit</span>
                                                Edit
                                            </a>
                                            <form method="post" onsubmit="return confirm('Delete this entry?');">
                                                <?php echo csrf_input() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int) $row['id'] ?>">
                                                <button
                                                    class="inline-flex items-center px-3 py-1.5 border border-red-100 rounded-md text-red-600 bg-red-50 hover:bg-red-100 transition-colors text-xs font-medium shadow-sm"
                                                    type="submit">
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
                </div>
            </main>
        </div>
    </div>

    <script>
    (function() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const toggleIcon = document.getElementById('sidebarToggleIcon');
        const key = 'jsk_admin_sidebar_collapsed';

        if (!sidebar || !toggleBtn || !toggleIcon) {
            return;
        }

        function setCollapsed(collapsed) {
            sidebar.classList.toggle('w-64', !collapsed);
            sidebar.classList.toggle('w-24', collapsed);

            document.querySelectorAll('.sidebar-text').forEach((el) => {
                el.classList.toggle('hidden', collapsed);
            });

            document.querySelectorAll('.sidebar-link').forEach((el) => {
                el.classList.toggle('justify-center', collapsed);
            });

            document.querySelectorAll('.sidebar-icon').forEach((el) => {
                el.classList.toggle('mr-3', !collapsed);
                el.classList.toggle('mr-0', collapsed);
            });

            var sidebarHeader = document.getElementById('sidebarHeader');
            var sidebarBrandWrap = document.getElementById('sidebarBrandWrap');
            if (sidebarHeader) {
                sidebarHeader.classList.toggle('p-4', !collapsed);
                sidebarHeader.classList.toggle('p-2', collapsed);
            }
            if (sidebarBrandWrap) {
                sidebarBrandWrap.classList.toggle('gap-3', !collapsed);
                sidebarBrandWrap.classList.toggle('gap-1', collapsed);
            }

            toggleIcon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
            localStorage.setItem(key, collapsed ? '1' : '0');
        }

        const initial = localStorage.getItem(key) === '1';
        setCollapsed(initial);

        toggleBtn.addEventListener('click', function() {
            const collapsed = sidebar.classList.contains('w-20');
            setCollapsed(!collapsed);
        });
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    (function() {
        if (typeof flatpickr === 'undefined') {
            return;
        }
        document.querySelectorAll('.js-date-input').forEach(function(el) {
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                allowInput: true
            });
        });

        var importForm = document.getElementById('csvImportForm');
        var importInput = document.getElementById('csvImportInput');
        var importTrigger = document.getElementById('csvImportTrigger');

        if (importForm && importInput && importTrigger) {
            importTrigger.addEventListener('click', function() {
                importInput.click();
            });
            importInput.addEventListener('change', function() {
                if (importInput.files && importInput.files.length > 0) {
                    importForm.submit();
                }
            });
        }
    })();
    </script>
</body>

</html>