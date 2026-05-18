<?php
// =============================================================================
// FILE    : student/my_tickets.php
// PURPOSE : Lists all tickets belonging to the logged-in student.
//           Features: status filter tabs, keyword search, pagination (10/page)
//           Edit button shown only for draft/new tickets (admin hasn't started yet)
// HOW TO TEST:
//   1. Log in as any student → click "Mes tickets" in the navbar
//   2. Use the status tabs to filter (All / Brouillons / En attente / ...)
//   3. Type in the search box → searches reference + subject
//   4. Check that Edit only appears for draft/new tickets
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------------------
// FILTERS — read from GET, sanitize immediately
// ---------------------------------------------------------------------------
$allowed_statuses = ['all', 'draft', 'new', 'opened', 'in_progress', 'completed', 'rejected'];
$filter_status    = in_array($_GET['status'] ?? 'all', $allowed_statuses, true)
                    ? ($_GET['status'] ?? 'all')
                    : 'all';

$search      = mb_substr(trim($_GET['search'] ?? ''), 0, 100);
$per_page    = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));

// ---------------------------------------------------------------------------
// BUILD WHERE clause — we collect conditions and params separately
// ---------------------------------------------------------------------------
// KEY FIX: Named params used MORE than once must be given unique names.
// e.g., :search cannot appear twice → use :search_ref and :search_sub instead.
// ---------------------------------------------------------------------------

$conditions = ['t.user_id = :student_id'];
$params     = [':student_id' => $student_id];

if ($filter_status !== 'all') {
    $conditions[]      = 't.status = :status';
    $params[':status'] = $filter_status;
}

if ($search !== '') {
    // Two separate named placeholders for the same search value
    $conditions[]         = '(t.reference LIKE :search_ref OR t.subject LIKE :search_sub)';
    $params[':search_ref'] = '%' . $search . '%';
    $params[':search_sub'] = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

// ---------------------------------------------------------------------------
// COUNT total rows (for pagination)
// ---------------------------------------------------------------------------
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tickets t $where");
$stmt_count->execute($params);
$total_rows  = (int) $stmt_count->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;

// ---------------------------------------------------------------------------
// FETCH the actual page of tickets
// NOTE: LIMIT / OFFSET must use bindValue with PDO::PARAM_INT when
//       ATTR_EMULATE_PREPARES = false, because they can't be quoted strings.
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id, t.reference, t.type, t.status, t.priority,
        t.subject, t.created_at, t.submitted_at, t.updated_at,
        c.name  AS category_name,
        s.name  AS subcategory_name,
        (SELECT COUNT(*) FROM ticket_attachments ta
         WHERE ta.ticket_id = t.id) AS attachment_count
    FROM tickets t
    JOIN  categories    c ON c.id = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    $where
    ORDER BY t.updated_at DESC
    LIMIT :lim OFFSET :off
");

// Bind all filter params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
// Bind pagination with explicit integer type
$stmt->bindValue(':lim', $per_page,  PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,    PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// ---------------------------------------------------------------------------
// COUNT per status for the tab badges
// ---------------------------------------------------------------------------
$stmt_tabs = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt FROM tickets WHERE user_id = ? GROUP BY status'
);
$stmt_tabs->execute([$student_id]);
$tab_counts = ['all' => 0];
foreach ($stmt_tabs->fetchAll() as $row) {
    $tab_counts[$row['status']] = (int) $row['cnt'];
    $tab_counts['all']         += (int) $row['cnt'];
}

// ---------------------------------------------------------------------------
// Flash messages (set by process files on redirect)
// ---------------------------------------------------------------------------
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---------------------------------------------------------------------------
// Helper: build a URL preserving current filters + changing only one param
// ---------------------------------------------------------------------------
function page_url(int $page, string $status, string $search): string
{
    $p = ['page' => $page];
    if ($status !== 'all') $p['status'] = $status;
    if ($search !== '')    $p['search'] = $search;
    return '/pfe/student/my_tickets.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes tickets — Système de Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- NAV -->
<nav class="bg-gradient-to-r from-blue-900 to-blue-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pfe/student/dashboard.php" class="flex items-center gap-2 text-white font-bold">
            <i class="bi bi-ticket-perforated-fill text-blue-300"></i> TicketSystem
        </a>
        <div class="flex items-center gap-1 text-sm flex-wrap">
            <a href="/pfe/student/dashboard.php"      class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-grid me-1"></i>Dashboard</a>
            <a href="/pfe/student/my_tickets.php"     class="text-white bg-white/20 px-3 py-1.5 rounded-lg font-semibold"><i class="bi bi-ticket-detailed me-1"></i>Mes tickets</a>
            <a href="/pfe/student/drafts.php"         class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-pencil-square me-1"></i>Brouillons</a>
            <a href="/pfe/student/create_ticket.php"  class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-plus-circle me-1"></i>Nouveau</a>
            <a href="/pfe/auth/logout.php"            class="text-red-300 hover:text-red-100 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Mes tickets</h1>
            <p class="text-slate-500 text-sm mt-0.5"><?= $total_rows ?> résultat<?= $total_rows !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/pfe/student/create_ticket.php"
           class="flex items-center gap-2 bg-gradient-to-r from-blue-700 to-blue-500 text-white
                  px-4 py-2.5 rounded-xl text-sm font-semibold shadow-md shadow-blue-200 hover:from-blue-800 hover:to-blue-600 transition">
            <i class="bi bi-plus-lg"></i> Nouvelle demande
        </a>
    </div>

    <!-- Flash -->
    <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3 text-sm">
            <i class="bi bi-check-circle-fill text-green-500 text-lg mt-0.5 flex-shrink-0"></i>
            <div><?= $flash_success ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3 text-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg mt-0.5 flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- STATUS TABS -->
        <?php
        $tab_defs = [
            'all'         => ['Tous',        'bi-collection'],
            'draft'       => ['Brouillons',  'bi-pencil-square'],
            'new'         => ['En attente',  'bi-clock'],
            'opened'      => ['Ouverts',     'bi-folder2-open'],
            'in_progress' => ['En cours',    'bi-arrow-repeat'],
            'completed'   => ['Résolus',     'bi-check-circle'],
            'rejected'    => ['Rejetés',     'bi-x-circle'],
        ];
        ?>
        <div class="flex overflow-x-auto border-b border-slate-100 px-2 pt-2 gap-1">
            <?php foreach ($tab_defs as $key => [$label, $icon]): ?>
                <?php $active = $filter_status === $key; $cnt = $tab_counts[$key] ?? 0; ?>
                <a href="<?= e(page_url(1, $key, $search)) ?>"
                   class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-t-lg whitespace-nowrap transition
                          <?= $active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <i class="bi <?= $icon ?>"></i>
                    <?= $label ?>
                    <?php if ($cnt > 0): ?>
                        <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold
                                     <?= $active ? 'bg-white/30 text-white' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $cnt ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- SEARCH BAR -->
        <div class="px-4 py-3 border-b border-slate-50 bg-slate-50">
            <form method="GET" action="/pfe/student/my_tickets.php" class="flex gap-2">
                <?php if ($filter_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                <?php endif; ?>
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="<?= e($search) ?>"
                           placeholder="Chercher par référence ou objet..."
                           class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                    Chercher
                </button>
                <?php if ($search): ?>
                    <a href="<?= e(page_url(1, $filter_status, '')) ?>"
                       class="px-3 py-2 bg-white border border-slate-200 text-slate-500 text-sm rounded-xl hover:bg-slate-50 transition flex items-center gap-1">
                        <i class="bi bi-x"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- TICKET LIST -->
        <?php if (empty($tickets)): ?>
            <div class="text-center py-16 px-4">
                <i class="bi bi-inbox text-6xl text-slate-200"></i>
                <p class="text-slate-500 font-medium mt-4">Aucun ticket trouvé.</p>
                <p class="text-slate-400 text-sm mt-1">
                    <a href="/pfe/student/create_ticket.php" class="text-blue-500 underline">Créer une demande</a>
                    <?= $search ? ' ou essayez un autre mot-clé.' : '.' ?>
                </p>
            </div>
        <?php else: ?>
            <?php
            // Inline badge config — status
            $sts_cfg = [
                'draft'       => ['Brouillon',  'bg-slate-100 text-slate-600'],
                'new'         => ['En attente', 'bg-amber-100 text-amber-700'],
                'opened'      => ['Ouvert',     'bg-blue-100 text-blue-700'],
                'in_progress' => ['En cours',   'bg-violet-100 text-violet-700'],
                'completed'   => ['Résolu',     'bg-emerald-100 text-emerald-700'],
                'rejected'    => ['Rejeté',     'bg-red-100 text-red-600'],
            ];
            // Priority badge config
            $pri_cfg = [
                'low'    => ['Basse',   'bg-slate-100 text-slate-500'],
                'medium' => ['Moyenne', 'bg-yellow-100 text-yellow-700'],
                'high'   => ['Haute',   'bg-orange-100 text-orange-600'],
                'urgent' => ['Urgente', 'bg-red-100 text-red-600'],
            ];
            // Statuses that students are ALLOWED to edit
            $editable_statuses = ['draft'];
            ?>
            <ul class="divide-y divide-slate-50">
                <?php foreach ($tickets as $t): ?>
                    <?php
                    [$sts_label, $sts_class] = $sts_cfg[$t['status']]    ?? [$t['status'],   'bg-slate-100 text-slate-600'];
                    [$pri_label, $pri_class] = $pri_cfg[$t['priority']]   ?? [$t['priority'], 'bg-slate-100 text-slate-600'];
                    $can_edit = in_array($t['status'], $editable_statuses, true);
                    ?>
                    <li class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition group flex-wrap">

                        <!-- Left: info -->
                        <div class="min-w-0 flex-1">
                            <!-- Ref + badges -->
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-mono text-xs font-semibold text-blue-600"><?= e($t['reference']) ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sts_class ?>"><?= $sts_label ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $pri_class ?>"><?= $pri_label ?></span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                             <?= $t['type'] === 'complaint' ? 'bg-red-50 text-red-500' : 'bg-blue-50 text-blue-500' ?>">
                                    <?= $t['type'] === 'complaint' ? 'Réclamation' : 'Demande' ?>
                                </span>
                            </div>
                            <!-- Subject -->
                            <p class="font-semibold text-slate-800 text-sm truncate"><?= e($t['subject']) ?></p>
                            <!-- Meta -->
                            <div class="flex items-center gap-3 mt-1 text-xs text-slate-400 flex-wrap">
                                <span><i class="bi bi-tag me-0.5"></i><?= e($t['category_name']) ?><?= $t['subcategory_name'] ? ' · ' . e($t['subcategory_name']) : '' ?></span>
                                <span><i class="bi bi-clock me-0.5"></i><?= date('d/m/Y', strtotime($t['created_at'])) ?></span>
                                <?php if ((int)$t['attachment_count'] > 0): ?>
                                    <span><i class="bi bi-paperclip me-0.5"></i><?= (int)$t['attachment_count'] ?> fichier<?= (int)$t['attachment_count'] > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right: action buttons -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="/pfe/student/view_ticket.php?id=<?= (int)$t['id'] ?>"
                               class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
                                <i class="bi bi-eye me-1"></i>Voir
                            </a>
                            <?php if ($t['status'] === 'draft'): ?>
                                <!-- Draft: Edit + Submit + Delete -->
                                <a href="/pfe/student/edit_ticket.php?id=<?= (int)$t['id'] ?>"
                                   class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition">
                                    <i class="bi bi-pencil me-1"></i>Modifier
                                </a>
                                <form method="POST" action="/pfe/student/submit_draft.php"
                                      onsubmit="return confirm('Soumettre ce brouillon à l\'administration ?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-green-200 text-green-600 hover:bg-green-50 transition">
                                        <i class="bi bi-send me-1"></i>Soumettre
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between px-5 py-4 border-t border-slate-100 flex-wrap gap-2">
                <p class="text-xs text-slate-400">Page <?= $current_page ?> / <?= $total_pages ?> (<?= $total_rows ?> résultat<?= $total_rows !== 1 ? 's' : '' ?>)</p>
                <div class="flex items-center gap-1">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= e(page_url($current_page - 1, $filter_status, $search)) ?>"
                           class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $current_page - 2); $p <= min($total_pages, $current_page + 2); $p++): ?>
                        <a href="<?= e(page_url($p, $filter_status, $search)) ?>"
                           class="px-3 py-1.5 text-sm rounded-lg transition <?= $p === $current_page ? 'bg-blue-600 text-white font-semibold' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= e(page_url($current_page + 1, $filter_status, $search)) ?>"
                           class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
