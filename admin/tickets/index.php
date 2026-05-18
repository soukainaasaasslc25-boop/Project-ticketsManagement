<?php
// =============================================================================
// FILE    : admin/tickets/index.php
// PURPOSE : Admin ticket management hub — shows all tickets with:
//           - Summary statistics (total, new, in_progress, completed)
//           - Status filter tabs
//           - Priority filter
//           - Keyword search (reference, subject, student name)
//           - Pagination (15 per page)
// HOW TO TEST:
//   1. Log in as admin → navigate to /pfe/admin/tickets/index.php
//   2. Click status tabs — count badges should update
//   3. Type a reference in search → matching row appears
//   4. Click "Voir" on any row → opens admin/tickets/view.php
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// ---------------------------------------------------------------------------
// FILTERS
// ---------------------------------------------------------------------------
$allowed_statuses = ['all','new','opened','in_progress','completed','rejected'];
$filter_status    = in_array($_GET['status']   ?? 'all', $allowed_statuses, true) ? ($_GET['status'] ?? 'all') : 'all';
$filter_priority  = in_array($_GET['priority'] ?? 'all', ['all','low','medium','high','urgent'], true) ? ($_GET['priority'] ?? 'all') : 'all';
$search           = mb_substr(trim($_GET['search'] ?? ''), 0, 100);
$per_page         = 15;
$current_page     = max(1, (int) ($_GET['page'] ?? 1));

// ---------------------------------------------------------------------------
// STATISTICS (always across all tickets, ignoring filters)
// ---------------------------------------------------------------------------
$stats_raw = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM tickets WHERE status != 'draft' GROUP BY status
")->fetchAll();

$stats = ['total' => 0, 'new' => 0, 'in_progress' => 0, 'completed' => 0, 'rejected' => 0, 'opened' => 0];
foreach ($stats_raw as $r) {
    $stats[$r['status']] = (int) $r['cnt'];
    $stats['total']     += (int) $r['cnt'];
}

// ---------------------------------------------------------------------------
// BUILD WHERE clause
// ---------------------------------------------------------------------------
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

if ($search !== '') {
    // Three separate named params for the same search term
    $conditions[]            = '(t.reference LIKE :s1 OR t.subject LIKE :s2 OR CONCAT(u.first_name," ",u.last_name) LIKE :s3)';
    $params[':s1']           = '%' . $search . '%';
    $params[':s2']           = '%' . $search . '%';
    $params[':s3']           = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

// COUNT
$cnt_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id $where
");
$cnt_stmt->execute($params);
$total_rows  = (int) $cnt_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

// FETCH page
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

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// URL builder helper
function aurl(array $overrides = []): string {
    $base = ['status' => 'all', 'priority' => 'all', 'search' => '', 'page' => 1];
    $p    = array_merge($base, array_filter($_GET, fn($v) => $v !== ''), $overrides);
    // remove defaults to keep URLs clean
    if ($p['status']   === 'all') unset($p['status']);
    if ($p['priority'] === 'all') unset($p['priority']);
    if ($p['search']   === '')    unset($p['search']);
    if ($p['page']     === 1)     unset($p['page']);
    return '/pfe/admin/tickets/index.php' . ($p ? '?' . http_build_query($p) : '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des tickets — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- NAV -->
<nav class="bg-gradient-to-r from-slate-900 to-slate-700 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pfe/admin/dashboard.php" class="flex items-center gap-2 text-white font-bold text-base">
            <i class="bi bi-ticket-perforated-fill text-blue-400"></i> TicketSystem
            <span class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded-full font-semibold ml-1">Admin</span>
        </a>
        <div class="flex items-center gap-1 text-sm">
            <a href="/pfe/admin/dashboard.php"       class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <a href="/pfe/admin/tickets/index.php"   class="text-white bg-white/15 px-3 py-1.5 rounded-lg font-semibold"><i class="bi bi-ticket-detailed me-1"></i>Tickets</a>
            <a href="/pfe/auth/logout.php"           class="text-red-400 hover:text-red-300 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Page header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Gestion des tickets</h1>
        <p class="text-slate-500 text-sm mt-0.5">Toutes les demandes et réclamations des étudiants.</p>
    </div>

    <!-- Flash -->
    <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3 text-sm">
            <i class="bi bi-check-circle-fill text-green-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_success ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3 text-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <!-- -----------------------------------------------------------------------
         STATS CARDS
         ----------------------------------------------------------------------- -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
        <?php
        $stat_cards = [
            ['Total',       $stats['total'],       'bi-collection',    'from-slate-700 to-slate-500'],
            ['Nouveaux',    $stats['new'],          'bi-clock',         'from-amber-600 to-amber-400'],
            ['Ouverts',     $stats['opened'],       'bi-folder2-open',  'from-blue-700 to-blue-500'],
            ['En cours',    $stats['in_progress'],  'bi-arrow-repeat',  'from-violet-700 to-violet-500'],
            ['Résolus',     $stats['completed'],    'bi-check-circle',  'from-emerald-700 to-emerald-500'],
            ['Rejetés',     $stats['rejected'],     'bi-x-circle',      'from-red-700 to-red-500'],
        ];
        foreach ($stat_cards as [$label, $count, $icon, $grad]):
        ?>
            <div class="bg-gradient-to-br <?= $grad ?> rounded-2xl p-4 text-white shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium opacity-80"><?= $label ?></span>
                    <i class="bi <?= $icon ?> opacity-70 text-lg"></i>
                </div>
                <div class="text-3xl font-bold"><?= $count ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- MAIN CARD -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- STATUS TABS -->
        <?php
        $tab_defs = [
            'all'         => ['Tous',        'bi-collection',   $stats['total']],
            'new'         => ['Nouveaux',    'bi-clock',        $stats['new']],
            'opened'      => ['Ouverts',     'bi-folder2-open', $stats['opened']],
            'in_progress' => ['En cours',   'bi-arrow-repeat',  $stats['in_progress']],
            'completed'   => ['Résolus',    'bi-check-circle',  $stats['completed']],
            'rejected'    => ['Rejetés',    'bi-x-circle',      $stats['rejected']],
        ];
        ?>
        <div class="flex overflow-x-auto border-b border-slate-100 px-2 pt-2 gap-1">
            <?php foreach ($tab_defs as $key => [$label, $icon, $cnt]): ?>
                <?php $active = $filter_status === $key; ?>
                <a href="<?= e(aurl(['status' => $key, 'page' => 1])) ?>"
                   class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-t-lg whitespace-nowrap transition
                          <?= $active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <i class="bi <?= $icon ?>"></i> <?= $label ?>
                    <?php if ($cnt > 0): ?>
                        <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold
                                     <?= $active ? 'bg-white/30 text-white' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $cnt ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- SEARCH + PRIORITY FILTER -->
        <div class="px-4 py-3 border-b border-slate-50 bg-slate-50">
            <form method="GET" action="/pfe/admin/tickets/index.php" class="flex gap-2 flex-wrap">
                <?php if ($filter_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                <?php endif; ?>

                <!-- Keyword search -->
                <div class="relative flex-1 min-w-48">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="<?= e($search) ?>"
                           placeholder="Référence, objet, nom étudiant..."
                           class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                </div>

                <!-- Priority filter -->
                <select name="priority"
                        class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                    <option value="all"    <?= $filter_priority === 'all'    ? 'selected' : '' ?>>Toutes priorités</option>
                    <option value="urgent" <?= $filter_priority === 'urgent' ? 'selected' : '' ?>>🔴 Urgente</option>
                    <option value="high"   <?= $filter_priority === 'high'   ? 'selected' : '' ?>>🟠 Haute</option>
                    <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>🟡 Moyenne</option>
                    <option value="low"    <?= $filter_priority === 'low'    ? 'selected' : '' ?>>🟢 Basse</option>
                </select>

                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                    Filtrer
                </button>
                <?php if ($search || $filter_priority !== 'all'): ?>
                    <a href="<?= e(aurl(['search' => '', 'priority' => 'all', 'page' => 1])) ?>"
                       class="px-3 py-2 bg-white border border-slate-200 text-slate-500 text-sm rounded-xl hover:bg-slate-50 transition flex items-center gap-1">
                        <i class="bi bi-x"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- TICKET TABLE -->
        <?php if (empty($tickets)): ?>
            <div class="text-center py-16">
                <i class="bi bi-inbox text-6xl text-slate-200"></i>
                <p class="text-slate-500 mt-4 font-medium">Aucun ticket trouvé.</p>
            </div>
        <?php else: ?>
            <?php
            $sts_cfg = [
                'new'         => ['Nouveau',    'bg-blue-100 text-blue-700'],
                'opened'      => ['Ouvert',     'bg-cyan-100 text-cyan-700'],
                'in_progress' => ['En cours',   'bg-orange-100 text-orange-700'],
                'completed'   => ['Résolu',     'bg-green-100 text-green-700'],
                'rejected'    => ['Rejeté',     'bg-red-100 text-red-700'],
            ];
            $pri_cfg = [
                'low'    => ['🟢 Basse',   'text-slate-500'],
                'medium' => ['🟡 Moyenne', 'text-yellow-600'],
                'high'   => ['🟠 Haute',   'text-orange-600'],
                'urgent' => ['🔴 Urgente', 'text-red-600 font-semibold'],
            ];
            ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left">Référence</th>
                            <th class="px-3 py-3 text-left">Étudiant</th>
                            <th class="px-3 py-3 text-left">Objet</th>
                            <th class="px-3 py-3 text-left">Catégorie</th>
                            <th class="px-3 py-3 text-left">Priorité</th>
                            <th class="px-3 py-3 text-left">Statut</th>
                            <th class="px-3 py-3 text-left">Assigné à</th>
                            <th class="px-3 py-3 text-left">Date</th>
                            <th class="px-3 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($tickets as $t): ?>
                            <?php
                            [$sts_label, $sts_class] = $sts_cfg[$t['status']]   ?? [$t['status'],   'bg-slate-100 text-slate-600'];
                            [$pri_label, $pri_class] = $pri_cfg[$t['priority']] ?? [$t['priority'],  'text-slate-600'];
                            $student_name = e($t['first_name']) . ' ' . e($t['last_name']);
                            $assigned     = $t['assigned_first']
                                            ? e($t['assigned_first']) . ' ' . e($t['assigned_last'])
                                            : '<span class="text-slate-300 italic">Non assigné</span>';
                            ?>
                            <tr class="hover:bg-slate-50 transition group">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-semibold text-blue-600"><?= e($t['reference']) ?></span>
                                    <?php if ((int)$t['reply_count'] > 0): ?>
                                        <span class="ml-1 text-xs text-slate-400">
                                            <i class="bi bi-chat-dots"></i> <?= (int)$t['reply_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-700 whitespace-nowrap"><?= $student_name ?></div>
                                    <div class="text-xs text-slate-400"><?= e($t['group_name'] ?? '') ?></div>
                                </td>
                                <td class="px-3 py-3 max-w-xs">
                                    <p class="truncate text-slate-700 font-medium"><?= e($t['subject']) ?></p>
                                    <span class="text-xs <?= $t['type'] === 'complaint' ? 'text-red-400' : 'text-blue-400' ?>">
                                        <?= $t['type'] === 'complaint' ? 'Réclamation' : 'Demande' ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-500 text-xs whitespace-nowrap"><?= e($t['category_name']) ?></td>
                                <td class="px-3 py-3 text-xs <?= $pri_class ?> whitespace-nowrap"><?= $pri_label ?></td>
                                <td class="px-3 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sts_class ?>"><?= $sts_label ?></span>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500"><?= $assigned ?></td>
                                <td class="px-3 py-3 text-xs text-slate-400 whitespace-nowrap">
                                    <?= $t['submitted_at'] ? date('d/m/Y', strtotime($t['submitted_at'])) : date('d/m/Y', strtotime($t['created_at'])) ?>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <a href="/pfe/admin/tickets/view.php?id=<?= (int)$t['id'] ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg
                                              bg-blue-50 text-blue-600 hover:bg-blue-100 transition whitespace-nowrap">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between px-5 py-4 border-t border-slate-100 flex-wrap gap-2">
                <p class="text-xs text-slate-400">Page <?= $current_page ?> / <?= $total_pages ?> (<?= $total_rows ?> résultats)</p>
                <div class="flex items-center gap-1">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= e(aurl(['page' => $current_page - 1])) ?>" class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                        <a href="<?= e(aurl(['page' => $p])) ?>"
                           class="px-3 py-1.5 text-sm rounded-lg transition <?= $p === $current_page ? 'bg-blue-600 text-white font-semibold' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= e(aurl(['page' => $current_page + 1])) ?>" class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
