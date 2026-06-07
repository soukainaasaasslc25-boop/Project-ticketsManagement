<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = $_SESSION['user_id'];
$allowed_statuses = ['all', 'new', 'opened', 'in_progress', 'completed', 'rejected'];
$filter_status    = in_array($_GET['status'] ?? 'all', $allowed_statuses, true) ? ($_GET['status'] ?? 'all') : 'all';

$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;

// Base conditions: exclude drafts from Reclamations view (they have their own page)
$conditions = ["t.user_id = :user_id", "t.type = 'complaint'", "t.status != 'draft'"];
$params = [':user_id' => $student_id];

if ($filter_status !== 'all') {
    $conditions[] = "t.status = :status";
    $params[':status'] = $filter_status;
}

if ($search !== '') {
    $conditions[] = "(t.reference LIKE :search_ref OR t.subject LIKE :search_sub OR c.name LIKE :search_cat)";
    $params[':search_ref'] = "%$search%";
    $params[':search_sub'] = "%$search%";
    $params[':search_cat'] = "%$search%";
}

$where_sql = "WHERE " . implode(" AND ", $conditions);
$base_sql = "FROM tickets t JOIN categories c ON c.id = t.category_id $where_sql";

// Count total for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) " . $base_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_tickets / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Fetch data
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name " . $base_sql . " ORDER BY t.updated_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// Get counts for tabs (excluding drafts)
$stmt_tabs = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM tickets WHERE user_id = ? AND type = 'complaint' AND status != 'draft' GROUP BY status");
$stmt_tabs->execute([$student_id]);
$tab_counts = ['all' => 0];
foreach ($stmt_tabs->fetchAll() as $row) {
    $tab_counts[$row['status']] = (int) $row['cnt'];
    $tab_counts['all']         += (int) $row['cnt'];
}

function page_url($p, $s, $q) {
    $params = ['page' => $p];
    if ($s !== 'all') $params['status'] = $s;
    if ($q !== '') $params['search'] = $q;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints — UniPortal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#fff1f2', 500: '#f43f5e', 600: '#e11d48' } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">My Complaints</h1>
        <p class="text-slate-500 text-sm mt-1">Track and manage your submitted complaints</p>
    </div>
    <a href="/pfe/student/create_reclamation.php" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm shadow-rose-200 transition-all flex items-center gap-2">
        <i class="bi bi-plus-lg"></i> New Complaint
    </a>
</div>

<!-- Filters and Tabs -->
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <?php
    $tab_defs = [
        'all'         => 'All',
        'new'         => 'New',
        'opened'      => 'Opened',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'rejected'    => 'Rejected',
    ];
    ?>
    <div class="flex overflow-x-auto pb-2 md:pb-0 hide-scrollbar gap-2">
        <?php foreach ($tab_defs as $key => $label): ?>
            <?php 
                $active = $filter_status === $key; 
                $cnt = $tab_counts[$key] ?? 0; 
            ?>
            <a href="<?= e(page_url(1, $key, $search)) ?>" class="whitespace-nowrap px-4 py-2 rounded-full text-sm font-semibold transition-colors flex items-center gap-2 <?= $active ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:border-slate-300' ?>">
                <?= $label ?>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' ?>">
                    <?= $cnt ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search Box -->
    <form method="GET" class="relative max-w-sm w-full md:w-auto shrink-0">
        <?php if ($filter_status !== 'all'): ?>
            <input type="hidden" name="status" value="<?= e($filter_status) ?>">
        <?php endif; ?>
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search complaints..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-shadow">
        <?php if($search): ?>
            <a href="<?= e(page_url(1, $filter_status, '')) ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="bi bi-x-circle-fill"></i></a>
        <?php endif; ?>
    </form>
</div>

<!-- Main Data Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100 text-xs uppercase tracking-wider font-semibold text-slate-500">
                    <th class="px-6 py-4">Title</th>
                    <th class="px-6 py-4">Category</th>
                    <th class="px-6 py-4">Priority</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Date</th>
                    <th class="px-6 py-4 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 text-slate-400 mb-4">
                                <i class="bi bi-inbox fs-3"></i>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700 mb-1">No complaints found</h3>
                            <p class="text-sm text-slate-500">You haven't submitted any complaints matching these filters.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <?php 
                            // Status badges
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
                            $s_color = $sts_colors[$t['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                            $s_label = $sts_labels[$t['status']] ?? ucfirst($t['status']);

                            // Priority badges
                            $pri_colors = [
                                'low' => 'text-slate-500 font-medium',
                                'medium' => 'text-blue-600 font-semibold',
                                'high' => 'text-amber-600 font-bold',
                                'urgent' => 'text-rose-600 font-black'
                            ];
                            $p_color = $pri_colors[$t['priority']] ?? 'text-slate-600';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group cursor-pointer" onclick="window.location='/pfe/student/view_ticket.php?id=<?= $t['id'] ?>'">
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 text-rose-400 shrink-0"><i class="bi bi-exclamation-triangle"></i></div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-slate-800 truncate"><?= e($t['subject']) ?></div>
                                        <div class="text-xs text-slate-400 font-mono mt-0.5"><?= e($t['reference']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-600 font-medium"><?= e($t['category_name']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm <?= $p_color ?>"><?= ucfirst(e($t['priority'])) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $s_color ?>">
                                    <?= $s_label ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-500 whitespace-nowrap"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <i class="bi bi-chevron-right text-slate-300 group-hover:text-rose-500 transition-colors"></i>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
            <span class="text-sm text-slate-500">Showing page <?= $page ?> of <?= $total_pages ?></span>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= e(page_url($page - 1, $filter_status, $search)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition-colors"><i class="bi bi-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="<?= e(page_url($i, $filter_status, $search)) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i == $page ? 'bg-rose-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= e(page_url($page + 1, $filter_status, $search)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition-colors"><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
