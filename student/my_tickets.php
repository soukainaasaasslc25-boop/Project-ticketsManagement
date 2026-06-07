<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

$allowed_statuses = ['all', 'draft', 'new', 'opened', 'in_progress', 'completed', 'rejected'];
$filter_status    = in_array($_GET['status'] ?? 'all', $allowed_statuses, true) ? ($_GET['status'] ?? 'all') : 'all';
$search           = mb_substr(trim($_GET['search'] ?? ''), 0, 100);
$per_page         = 10;
$current_page     = max(1, (int) ($_GET['page'] ?? 1));

$conditions = ['t.user_id = :student_id'];
$params     = [':student_id' => $student_id];

if ($filter_status !== 'all') {
    $conditions[]      = 't.status = :status';
    $params[':status'] = $filter_status;
}
if ($search !== '') {
    $conditions[]          = '(t.reference LIKE :search_ref OR t.subject LIKE :search_sub)';
    $params[':search_ref'] = '%' . $search . '%';
    $params[':search_sub'] = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tickets t $where");
$stmt_count->execute($params);
$total_rows   = (int) $stmt_count->fetchColumn();
$total_pages  = max(1, (int) ceil($total_rows / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT t.id, t.reference, t.type, t.status, t.priority,
           t.subject, t.created_at, t.updated_at,
           c.name AS category_name, s.name AS subcategory_name,
           (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) AS attachment_count
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    $where
    ORDER BY t.updated_at DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

$stmt_tabs = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM tickets WHERE user_id = ? GROUP BY status');
$stmt_tabs->execute([$student_id]);
$tab_counts = ['all' => 0];
foreach ($stmt_tabs->fetchAll() as $row) {
    $tab_counts[$row['status']] = (int) $row['cnt'];
    $tab_counts['all']         += (int) $row['cnt'];
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function page_url(int $page, string $status, string $search): string {
    $p = ['page' => $page];
    if ($status !== 'all') $p['status'] = $status;
    if ($search !== '')    $p['search'] = $search;
    return '/pfe/student/my_tickets.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Tickets — UniPortal</title>
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
<body class="bg-slate-50 text-slate-800 antialiased">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">All My Tickets</h1>
        <p class="text-slate-500 text-sm mt-1"><?= $total_rows ?> ticket<?= $total_rows !== 1 ? 's' : '' ?> total</p>
    </div>
    <div class="flex gap-3">
        <a href="/pfe/student/create_demande.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> New Request
        </a>
        <a href="/pfe/student/create_reclamation.php" class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> New Complaint
        </a>
    </div>
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
    <?php
    $tab_defs = [
        'all'         => ['All', 'bi-collection'],
        'draft'       => ['Drafts', 'bi-pencil-square'],
        'new'         => ['New', 'bi-clock'],
        'opened'      => ['Opened', 'bi-folder2-open'],
        'in_progress' => ['In Progress', 'bi-arrow-repeat'],
        'completed'   => ['Completed', 'bi-check-circle'],
        'rejected'    => ['Rejected', 'bi-x-circle'],
    ];
    ?>
    <div class="flex overflow-x-auto border-b border-slate-100 px-4 pt-4 gap-2">
        <?php foreach ($tab_defs as $key => [$label, $icon]): ?>
            <?php $active = $filter_status === $key; $cnt = $tab_counts[$key] ?? 0; ?>
            <a href="<?= e(page_url(1, $key, $search)) ?>"
               class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold rounded-t-xl whitespace-nowrap transition-colors <?= $active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50 border border-transparent hover:border-slate-200 border-b-0' ?>">
                <i class="bi <?= $icon ?>"></i> <?= $label ?>
                <?php if ($cnt > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' ?>"><?= $cnt ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search Bar -->
    <div class="p-4 bg-slate-50/50 border-b border-slate-100">
        <form method="GET" action="/pfe/student/my_tickets.php" class="flex gap-3">
            <?php if ($filter_status !== 'all'): ?>
                <input type="hidden" name="status" value="<?= e($filter_status) ?>">
            <?php endif; ?>
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by reference or subject..."
                       class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm transition-all">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-colors">Search</button>
            <?php if ($search): ?>
                <a href="<?= e(page_url(1, $filter_status, '')) ?>" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-1.5">
                    <i class="bi bi-x-lg text-xs"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Ticket List -->
    <?php if (empty($tickets)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-slate-300">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">No tickets found</h3>
            <p class="text-sm text-slate-500">Try adjusting your filters or create a new ticket.</p>
        </div>
    <?php else: ?>
        <?php
        $sts_cfg = [
            'draft'       => ['Draft',       'bg-slate-100 text-slate-600 border-slate-200'],
            'new'         => ['New',          'bg-amber-100 text-amber-700 border-amber-200'],
            'opened'      => ['Opened',       'bg-purple-100 text-purple-700 border-purple-200'],
            'in_progress' => ['In Progress',  'bg-blue-100 text-blue-700 border-blue-200'],
            'completed'   => ['Completed',    'bg-emerald-100 text-emerald-700 border-emerald-200'],
            'rejected'    => ['Rejected',     'bg-rose-100 text-rose-700 border-rose-200'],
        ];
        $pri_cfg = [
            'low'    => ['Low',    'text-slate-500'],
            'medium' => ['Medium', 'text-blue-600'],
            'high'   => ['High',   'text-orange-500 font-bold'],
            'urgent' => ['Urgent', 'text-rose-600 font-black'],
        ];
        ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($tickets as $t): ?>
                <?php
                [$sts_label, $sts_class] = $sts_cfg[$t['status']]   ?? [ucfirst($t['status']), 'bg-slate-100 text-slate-600 border-slate-200'];
                [$pri_label, $pri_class] = $pri_cfg[$t['priority']]  ?? [ucfirst($t['priority']), 'text-slate-500'];
                $is_complaint = $t['type'] === 'complaint';
                ?>
                <li class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/50 transition-colors group flex-wrap sm:flex-nowrap">
                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 <?= $is_complaint ? 'bg-rose-50 text-rose-500' : 'bg-indigo-50 text-indigo-500' ?>">
                        <i class="bi <?= $is_complaint ? 'bi-exclamation-triangle' : 'bi-file-earmark-text' ?>"></i>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded"><?= e($t['reference']) ?></span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $sts_class ?>"><?= $sts_label ?></span>
                            <span class="text-xs <?= $pri_class ?>"><i class="bi bi-flag-fill mr-1"></i><?= $pri_label ?></span>
                            <span class="text-[10px] uppercase font-bold tracking-wider <?= $is_complaint ? 'text-rose-400' : 'text-indigo-400' ?>">
                                <?= $is_complaint ? 'Complaint' : 'Request' ?>
                            </span>
                        </div>
                        <p class="font-semibold text-slate-800 text-sm truncate"><?= e($t['subject']) ?></p>
                        <div class="flex items-center gap-3 mt-1 text-xs text-slate-400 flex-wrap">
                            <span><i class="bi bi-tag mr-0.5"></i><?= e($t['category_name']) ?><?= $t['subcategory_name'] ? ' · ' . e($t['subcategory_name']) : '' ?></span>
                            <span><i class="bi bi-clock mr-0.5"></i><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                            <?php if ((int)$t['attachment_count'] > 0): ?>
                                <span><i class="bi bi-paperclip mr-0.5"></i><?= (int)$t['attachment_count'] ?> file<?= (int)$t['attachment_count'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="/pfe/student/view_ticket.php?id=<?= (int)$t['id'] ?>"
                           class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 flex items-center justify-center transition-colors" title="View">
                            <i class="bi bi-eye-fill"></i>
                        </a>
                        <?php if ($t['status'] === 'draft'): ?>
                            <a href="/pfe/student/edit_ticket.php?id=<?= (int)$t['id'] ?>"
                               class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center transition-colors" title="Edit">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form method="POST" action="/pfe/student/submit_draft.php"
                                  onsubmit="return confirm('Submit this draft to the administration?')">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition-colors" title="Submit">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-3">
                <p class="text-sm text-slate-500">Page <span class="font-bold text-slate-700"><?= $current_page ?></span> of <span class="font-bold text-slate-700"><?= $total_pages ?></span></p>
                <div class="flex items-center gap-1.5">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= e(page_url($current_page - 1, $filter_status, $search)) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                        <a href="<?= e(page_url($p, $filter_status, $search)) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition-colors <?= $p === $current_page ? 'bg-indigo-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= e(page_url($current_page + 1, $filter_status, $search)) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

        </main>
    </div>
</div>
</body>
</html>
