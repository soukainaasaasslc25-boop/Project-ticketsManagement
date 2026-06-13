<?php
require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$allowed_statuses = ['all','new','opened','in_progress','completed','rejected'];
$filter_status    = in_array($_GET['status']   ?? 'all', $allowed_statuses, true) ? ($_GET['status'] ?? 'all') : 'all';
$filter_priority  = in_array($_GET['priority'] ?? 'all', ['all','low','medium','high','urgent'], true) ? ($_GET['priority'] ?? 'all') : 'all';
$filter_type      = in_array($_GET['type'] ?? 'all', ['all','request','complaint'], true) ? ($_GET['type'] ?? 'all') : 'all';
$search           = mb_substr(trim($_GET['search'] ?? ''), 0, 100);
$per_page         = 15;
$current_page     = max(1, (int) ($_GET['page'] ?? 1));

// Stats
$stats_raw = $pdo->query("SELECT status, COUNT(*) AS cnt FROM tickets WHERE status != 'draft' GROUP BY status")->fetchAll();
$stats = ['total' => 0, 'new' => 0, 'in_progress' => 0, 'completed' => 0, 'rejected' => 0, 'opened' => 0];
foreach ($stats_raw as $r) {
    $stats[$r['status']] = (int) $r['cnt'];
    $stats['total']     += (int) $r['cnt'];
}

// Build WHERE
$conditions = ["t.status != 'draft'"];
$params     = [];

if ($filter_status !== 'all') {
    $conditions[]      = 't.status = :status';
    $params[':status'] = $filter_status;
}
if ($filter_priority !== 'all') {
    $conditions[]        = 't.priority = :priority';
    $params[':priority'] = $filter_priority;
}
if ($filter_type !== 'all') {
    $conditions[]    = 't.type = :type';
    $params[':type'] = $filter_type;
}
if ($search !== '') {
    $conditions[]            = '(t.reference LIKE :s1 OR t.subject LIKE :s2 OR CONCAT(u.first_name," ",u.last_name) LIKE :s3)';
    $params[':s1']           = '%' . $search . '%';
    $params[':s2']           = '%' . $search . '%';
    $params[':s3']           = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id $where");
$cnt_stmt->execute($params);
$total_rows  = (int) $cnt_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT
        t.id, t.reference, t.type, t.status, t.priority,
        t.subject, t.created_at, t.submitted_at, t.updated_at,
        u.first_name, u.last_name, u.group_name,
        c.name  AS category_name,
        adm.first_name AS assigned_first, adm.last_name AS assigned_last,
        (SELECT COUNT(*) FROM ticket_responses tr WHERE tr.ticket_id = t.id AND tr.is_internal = 0) AS reply_count
    FROM tickets t
    JOIN  users       u   ON u.id   = t.user_id
    JOIN  categories  c   ON c.id   = t.category_id
    LEFT JOIN users  adm  ON adm.id = t.assigned_to
    $where
    ORDER BY
        FIELD(t.status,'new','opened','in_progress','completed','rejected'),
        t.updated_at DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);

$stmt->bindValue(':off', $offset,   PDO::PARAM_INT);

$stmt->execute();

$tickets = $stmt->fetchAll();


$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

function aurl(array $overrides = []): string {
    $base = ['status' => 'all', 'priority' => 'all', 'type' => 'all', 'search' => '', 'page' => 1];
    $p    = array_merge($base, array_filter($_GET, fn($v) => $v !== ''), $overrides);
    if ($p['status']   === 'all') unset($p['status']);
    if ($p['priority'] === 'all') unset($p['priority']);
    if ($p['type']     === 'all') unset($p['type']);
    if ($p['search']   === '')    unset($p['search']);
    if ($p['page']     === 1)     unset($p['page']);
    return '/pfe/admin/tickets/index.php' . ($p ? '?' . http_build_query($p) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Tickets — UniPortal Admin</title>
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
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Ticket Management</h1>
    <p class="text-slate-500 text-sm mt-1">Review, process, and manage all student requests and complaints.</p>
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

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <!-- Status Tabs -->
    <div class="flex overflow-x-auto border-b border-slate-100 px-4 pt-4 gap-2">
        <?php
        $tabs = [
            'all' => ['All Tickets', 'bi-collection', $stats['total']],
            'new' => ['New', 'bi-clock', $stats['new']],
            'opened' => ['Opened', 'bi-folder2-open', $stats['opened']],
            'in_progress' => ['In Progress', 'bi-arrow-repeat', $stats['in_progress']],
            'completed' => ['Completed', 'bi-check-circle', $stats['completed']],
            'rejected' => ['Rejected', 'bi-x-circle', $stats['rejected']],
        ];
        foreach ($tabs as $key => [$label, $icon, $cnt]):
            $active = $filter_status === $key;
        ?>
            <a href="<?= e(aurl(['status' => $key, 'page' => 1])) ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-t-xl whitespace-nowrap transition-colors <?= $active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50 border border-transparent hover:border-slate-200 border-b-0' ?>">
                <i class="bi <?= $icon ?>"></i> <?= $label ?>
                <?php if ($cnt > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $active ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-500' ?>"><?= $cnt ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters Bar -->
    <div class="p-4 bg-slate-50/50 border-b border-slate-100">
        <form method="GET" action="/pfe/admin/tickets/index.php" class="flex flex-wrap gap-3 items-center">
            <?php if ($filter_status !== 'all'): ?>
                <input type="hidden" name="status" value="<?= e($filter_status) ?>">
            <?php endif; ?>

            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search reference, subject, or student..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm">
            </div>

            <!-- Type Filter -->
            <select name="type" class="px-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm appearance-none font-medium text-slate-700 min-w-[120px]">
                <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="request" <?= $filter_type == 'request' ? 'selected' : '' ?>>Requests</option>
                <option value="complaint" <?= $filter_type == 'complaint' ? 'selected' : '' ?>>Complaints</option>
            </select>

            <!-- Priority Filter -->
            <select name="priority" class="px-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm appearance-none font-medium text-slate-700 min-w-[140px]">
                <option value="all" <?= $filter_priority == 'all' ? 'selected' : '' ?>>All Priorities</option>
                <option value="urgent" <?= $filter_priority == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                <option value="high" <?= $filter_priority == 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= $filter_priority == 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= $filter_priority == 'low' ? 'selected' : '' ?>>Low</option>
            </select>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-colors shadow-sm shadow-indigo-200">
                Filter
            </button>

            <?php if ($search || $filter_priority !== 'all' || $filter_type !== 'all'): ?>
                <a href="<?= e(aurl(['search' => '', 'priority' => 'all', 'type' => 'all', 'page' => 1])) ?>" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-1.5">
                    <i class="bi bi-x-lg text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tickets Table -->
    <?php if (empty($tickets)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-slate-300">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">No tickets found</h3>
            <p class="text-sm text-slate-500">Adjust your filters or try a different search term.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 font-semibold text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Ticket</th>
                        <th class="px-6 py-4">Student</th>
                        <th class="px-6 py-4">Status & Priority</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Assigned To</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php
                    $sts_colors = [
                        'new' => 'bg-amber-100 text-amber-700 border-amber-200',
                        'opened' => 'bg-purple-100 text-purple-700 border-purple-200',
                        'in_progress' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                        'rejected' => 'bg-rose-100 text-rose-700 border-rose-200'
                    ];
                    $sts_labels = [
                        'new' => 'New', 'opened' => 'Opened', 'in_progress' => 'In Progress', 
                        'completed' => 'Completed', 'rejected' => 'Rejected'
                    ];
                    $pri_colors = [
                        'low' => 'text-slate-500',
                        'medium' => 'text-blue-500',
                        'high' => 'text-orange-500',
                        'urgent' => 'text-rose-600 font-bold'
                    ];
                    ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">#<?= e($t['reference']) ?></span>
                                        <?php if ($t['type'] === 'complaint'): ?>
                                            <span class="text-[10px] uppercase font-bold text-rose-500 tracking-wider"><i class="bi bi-exclamation-octagon mr-0.5"></i> Complaint</span>
                                        <?php else: ?>
                                            <span class="text-[10px] uppercase font-bold text-indigo-500 tracking-wider"><i class="bi bi-file-earmark-text mr-0.5"></i> Request</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="font-medium text-slate-800 line-clamp-1" title="<?= e($t['subject']) ?>"><?= e($t['subject']) ?></p>
                                    <div class="text-xs text-slate-400">
                                        Created: <?= date('M d, Y', strtotime($t['created_at'])) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <?= mb_strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-700"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= e($t['group_name'] ?: 'No Group') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-2 items-start">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $sts_colors[$t['status']] ?? 'bg-slate-100' ?>">
                                        <?= $sts_labels[$t['status']] ?? ucfirst($t['status']) ?>
                                    </span>
                                    <span class="text-xs flex items-center gap-1.5 <?= $pri_colors[$t['priority']] ?? 'text-slate-500' ?>">
                                        <i class="bi bi-flag-fill"></i> <?= ucfirst($t['priority']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-600 font-medium"><?= e($t['category_name']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($t['assigned_first']): ?>
                                    <div class="flex items-center gap-2 text-sm text-slate-700">
                                        <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold shrink-0 text-slate-600">
                                            <?= mb_strtoupper(mb_substr($t['assigned_first'], 0, 1) . mb_substr($t['assigned_last'], 0, 1)) ?>
                                        </div>
                                        <?= e($t['assigned_first'] . ' ' . $t['assigned_last']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-slate-100 text-slate-500 text-xs font-medium border border-dashed border-slate-300">
                                        Unassigned
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="/pfe/admin/tickets/view.php?id=<?= (int)$t['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors" title="View Details">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                <p class="text-sm text-slate-500">Showing page <span class="font-bold text-slate-700"><?= $current_page ?></span> of <span class="font-bold text-slate-700"><?= $total_pages ?></span></p>
                <div class="flex items-center gap-1.5">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= e(aurl(['page' => $current_page - 1])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                        <a href="<?= e(aurl(['page' => $p])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors <?= $p === $current_page ? 'bg-indigo-600 text-white shadow-sm' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= e(aurl(['page' => $current_page + 1])) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-right"></i></a>
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
