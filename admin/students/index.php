<?php
require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$search   = trim($_GET['search'] ?? '');
$filiere  = trim($_GET['filiere'] ?? 'all');
$status   = trim($_GET['status'] ?? 'all');
$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = "role = 'student'";
$params = [];

if ($search !== '') {
    $where .= " AND (first_name LIKE :s1 OR last_name LIKE :s2 OR username LIKE :s3 OR group_name LIKE :s4)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
}

if ($filiere !== 'all') {
    $where .= " AND filiere = :filiere";
    $params[':filiere'] = $filiere;
}

if ($status !== 'all') {
    $where .= " AND account_status = :status";
    $params[':status'] = $status;
}

$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$cnt_stmt->execute($params);
$total_rows  = (int) $cnt_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = max(0, ($page - 1) * $per_page);

$stmt = $pdo->prepare("
    SELECT id, username, first_name, last_name, group_name, filiere, account_status, created_at
    FROM users
    WHERE $where
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

$stats_raw = $pdo->query("SELECT account_status, COUNT(*) as cnt FROM users WHERE role = 'student' GROUP BY account_status")->fetchAll();
$total_students = 0;
$active_students = 0;
$inactive_students = 0;

foreach ($stats_raw as $s) {
    $total_students += $s['cnt'];
    if ($s['account_status'] === 'active') $active_students = $s['cnt'];
    if ($s['account_status'] === 'inactive') $inactive_students = $s['cnt'];
}

$filieres = $pdo->query("SELECT DISTINCT filiere FROM users WHERE role = 'student' AND filiere IS NOT NULL AND filiere != '' ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

function aurl(array $overrides = []): string {
    $base = ['search' => '', 'filiere' => 'all', 'status' => 'all', 'page' => 1];
    $p    = array_merge($base, array_filter($_GET, fn($v) => $v !== ''), $overrides);
    if ($p['search']  === '')    unset($p['search']);
    if ($p['filiere'] === 'all') unset($p['filiere']);
    if ($p['status']  === 'all') unset($p['status']);
    if ($p['page']    === 1)     unset($p['page']);
    return '/pfe/admin/students/index.php' . ($p ? '?' . http_build_query($p) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — UniPortal Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Students Directory</h1>
        <p class="text-slate-500 text-sm mt-1">Manage all student accounts, statuses, and profiles.</p>
    </div>
    <a href="/pfe/admin/students/import.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm shadow-indigo-200 flex items-center gap-2">
        <i class="bi bi-file-earmark-arrow-up"></i> Import Students
    </a>
</div>

<?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-emerald-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_success ?></div>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_error ?></div>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-people-fill"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-slate-800 leading-tight"><?= $total_students ?></div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Students</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-person-check-fill"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-slate-800 leading-tight"><?= $active_students ?></div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Accounts</div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-person-dash-fill"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-slate-800 leading-tight"><?= $inactive_students ?></div>
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Inactive Accounts</div>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <!-- Filters Bar -->
    <div class="p-4 bg-slate-50/50 border-b border-slate-100">
        <form method="GET" action="/pfe/admin/students/index.php" class="flex flex-wrap gap-3 items-center">
            
            <div class="relative flex-1 min-w-[200px]">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, username, group..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm">
            </div>

            <select name="filiere" class="px-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm appearance-none font-medium text-slate-700 min-w-[140px]">
                <option value="all" <?= $filiere === 'all' ? 'selected' : '' ?>>All Fields</option>
                <?php foreach ($filieres as $fil): ?>
                    <option value="<?= e($fil) ?>" <?= $filiere === $fil ? 'selected' : '' ?>><?= e($fil) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="px-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm appearance-none font-medium text-slate-700 min-w-[140px]">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm shadow-indigo-200">
                Filter
            </button>

            <?php if ($search !== '' || $filiere !== 'all' || $status !== 'all'): ?>
                <a href="<?= e(aurl(['search' => '', 'filiere' => 'all', 'status' => 'all', 'page' => 1])) ?>" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-1.5">
                    <i class="bi bi-x-lg text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Students Table -->
    <?php if (empty($students)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-slate-300">
                <i class="bi bi-people"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">No students found</h3>
            <p class="text-sm text-slate-500">Adjust your filters or try a different search term.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Group / Field</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Registered</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($students as $stu): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm shrink-0">
                                        <?= mb_strtoupper(mb_substr($stu['first_name'], 0, 1) . mb_substr($stu['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800"><?= e($stu['first_name'] . ' ' . $stu['last_name']) ?></p>
                                        <p class="text-xs font-mono text-slate-500">@<?= e($stu['username']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-700"><?= e($stu['group_name'] ?: 'No Group') ?></p>
                                <p class="text-xs text-slate-500"><?= e($stu['filiere'] ?: '—') ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($stu['account_status'] === 'active'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-600 font-medium"><?= date('M d, Y', strtotime($stu['created_at'])) ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="/pfe/admin/students/action.php" class="inline-flex items-center justify-end gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$stu['id'] ?>">
                                    
                                    <?php if ($stu['account_status'] === 'active'): ?>
                                        <button type="submit" name="action" value="deactivate" class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-700 flex items-center justify-center transition-colors" title="Deactivate">
                                            <i class="bi bi-pause-fill"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 flex items-center justify-center transition-colors" title="Activate">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button type="submit" name="action" value="delete" onclick="return confirm('Warning: Deleting this student will also delete all associated tickets. Continue?');" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 hover:text-rose-700 flex items-center justify-center transition-colors" title="Delete">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                <p class="text-sm text-slate-500">Showing page <span class="font-bold text-slate-700"><?= $page ?></span> of <span class="font-bold text-slate-700"><?= $total_pages ?></span></p>
                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(aurl(['page' => $page - 1])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?= e(aurl(['page' => $p])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors <?= $p === $page ? 'bg-indigo-600 text-white shadow-sm' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= e(aurl(['page' => $page + 1])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
