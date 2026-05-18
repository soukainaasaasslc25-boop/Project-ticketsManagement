<?php
// =============================================================================
// FILE    : admin/students/index.php
// PURPOSE : List all students, manage status, search, and delete.
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Filters
$search   = trim($_GET['search'] ?? '');
$filiere  = trim($_GET['filiere'] ?? 'all');
$status   = trim($_GET['status'] ?? 'all');
$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));

// Build where clause
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

// Pagination
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$cnt_stmt->execute($params);
$total_rows  = (int) $cnt_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Fetch students
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

// Statistics
$stats_raw = $pdo->query("SELECT account_status, COUNT(*) as cnt FROM users WHERE role = 'student' GROUP BY account_status")->fetchAll();
$total_students = 0;
$active_students = 0;
$inactive_students = 0;

foreach ($stats_raw as $s) {
    $total_students += $s['cnt'];
    if ($s['account_status'] === 'active') $active_students = $s['cnt'];
    if ($s['account_status'] === 'inactive') $inactive_students = $s['cnt'];
}

// Get distinct filieres for the filter dropdown
$filieres = $pdo->query("SELECT DISTINCT filiere FROM users WHERE role = 'student' AND filiere IS NOT NULL AND filiere != '' ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

// URL builder
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des étudiants — Admin</title>
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
        <div class="flex items-center gap-1 text-sm flex-wrap">
            <a href="/pfe/admin/dashboard.php"       class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <a href="/pfe/admin/tickets/index.php"   class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-ticket-detailed me-1"></i>Tickets</a>
            <a href="/pfe/admin/students/index.php"  class="text-white bg-white/15 px-3 py-1.5 rounded-lg font-semibold"><i class="bi bi-people me-1"></i>Étudiants</a>
            <a href="/pfe/admin/students/import.php" class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-upload me-1"></i>Import</a>
            <a href="/pfe/auth/logout.php"           class="text-red-400 hover:text-red-300 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-8">

    <div class="flex justify-between items-end mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Gestion des étudiants</h1>
            <p class="text-slate-500 text-sm mt-0.5">Annuaire de tous les étudiants enregistrés dans le système.</p>
        </div>
        <a href="/pfe/admin/students/import.php" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
            <i class="bi bi-file-earmark-excel"></i> Importer
        </a>
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

    <!-- STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-800"><?= $total_students ?></div>
                <div class="text-xs font-semibold text-slate-500 uppercase">Total étudiants</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-800"><?= $active_students ?></div>
                <div class="text-xs font-semibold text-slate-500 uppercase">Comptes actifs</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="bi bi-person-dash-fill"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-800"><?= $inactive_students ?></div>
                <div class="text-xs font-semibold text-slate-500 uppercase">Comptes inactifs</div>
            </div>
        </div>
    </div>

    <!-- MAIN CARD -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        
        <!-- SEARCH & FILTERS -->
        <div class="px-5 py-4 border-b border-slate-50 bg-slate-50">
            <form method="GET" action="/pfe/admin/students/index.php" class="flex gap-3 flex-wrap">
                <!-- Keyword search -->
                <div class="relative flex-1 min-w-48">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="search" value="<?= e($search) ?>"
                           placeholder="Nom, prénom, nom d'utilisateur, groupe..."
                           class="w-full border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                </div>

                <!-- Filiere filter -->
                <select name="filiere" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white min-w-40">
                    <option value="all">Toutes les filières</option>
                    <?php foreach ($filieres as $fil): ?>
                        <option value="<?= e($fil) ?>" <?= $filiere === $fil ? 'selected' : '' ?>><?= e($fil) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Status filter -->
                <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                </select>

                <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                    Rechercher
                </button>
                <?php if ($search !== '' || $filiere !== 'all' || $status !== 'all'): ?>
                    <a href="<?= e(aurl(['search' => '', 'filiere' => 'all', 'status' => 'all', 'page' => 1])) ?>" class="px-3 py-2 bg-white border border-slate-200 text-slate-500 text-sm rounded-xl hover:bg-slate-50 transition flex items-center gap-1">
                        <i class="bi bi-x"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- STUDENTS TABLE -->
        <?php if (empty($students)): ?>
            <div class="text-center py-16">
                <i class="bi bi-people text-6xl text-slate-200"></i>
                <p class="text-slate-500 mt-4 font-medium">Aucun étudiant trouvé.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left">Étudiant</th>
                            <th class="px-3 py-3 text-left">Utilisateur</th>
                            <th class="px-3 py-3 text-left">Groupe / Filière</th>
                            <th class="px-3 py-3 text-left">Statut</th>
                            <th class="px-3 py-3 text-left">Inscrit le</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($students as $stu): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                            <?= mb_strtoupper(mb_substr($stu['first_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-slate-800"><?= e($stu['first_name']) . ' ' . e($stu['last_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-500">
                                    @<?= e($stu['username']) ?>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-700"><?= e($stu['group_name'] ?: '—') ?></div>
                                    <div class="text-xs text-slate-400"><?= e($stu['filiere'] ?: '—') ?></div>
                                </td>
                                <td class="px-3 py-3">
                                    <?php if ($stu['account_status'] === 'active'): ?>
                                        <span class="text-xs px-2 py-1 rounded-full font-semibold bg-emerald-100 text-emerald-700">Actif</span>
                                    <?php else: ?>
                                        <span class="text-xs px-2 py-1 rounded-full font-semibold bg-amber-100 text-amber-700">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-500">
                                    <?= date('d/m/Y', strtotime($stu['created_at'])) ?>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Actions (Activate/Deactivate/Delete) -->
                                        <form method="POST" action="/pfe/admin/students/action.php" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$stu['id'] ?>">
                                            
                                            <?php if ($stu['account_status'] === 'active'): ?>
                                                <button type="submit" name="action" value="deactivate" 
                                                        class="text-xs font-medium px-2.5 py-1.5 rounded bg-amber-50 text-amber-600 hover:bg-amber-100 transition"
                                                        title="Désactiver">
                                                    <i class="bi bi-pause-fill"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="activate" 
                                                        class="text-xs font-medium px-2.5 py-1.5 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition"
                                                        title="Activer">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button type="submit" name="action" value="delete"
                                                    onclick="return confirm('Attention : La suppression de cet étudiant supprimera également tous ses tickets associés. Continuer ?');"
                                                    class="text-xs font-medium px-2.5 py-1.5 rounded bg-red-50 text-red-600 hover:bg-red-100 transition"
                                                    title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
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
                <p class="text-xs text-slate-400">Page <?= $page ?> / <?= $total_pages ?> (<?= $total_rows ?> résultats)</p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(aurl(['page' => $page - 1])) ?>" class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?= e(aurl(['page' => $p])) ?>"
                           class="px-3 py-1.5 text-sm rounded-lg transition <?= $p === $page ? 'bg-blue-600 text-white font-semibold' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= e(aurl(['page' => $page + 1])) ?>" class="px-3 py-1.5 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
