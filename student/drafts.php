<?php
// =============================================================================
// FILE    : student/drafts.php
// PURPOSE : Shows only the connected student's DRAFT tickets.
//           Actions per draft: Edit | Submit | Delete
// HOW TO TEST:
//   1. Log in as a student
//   2. Create a ticket → click "Enregistrer brouillon"
//   3. Navigate to /pfe/student/drafts.php
//   4. You should see the draft with three action buttons
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

// Generate CSRF token for the inline submit/delete forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ---------------------------------------------------------------------------
// Fetch ONLY draft tickets for this student
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id, t.reference, t.subject, t.priority,
        t.created_at, t.updated_at,
        c.name AS category_name,
        s.name AS subcategory_name
    FROM tickets t
    JOIN  categories    c ON c.id = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    WHERE t.user_id = :uid
      AND t.status  = 'draft'
    ORDER BY t.updated_at DESC
");
$stmt->execute([':uid' => $student_id]);
$drafts = $stmt->fetchAll();

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$priority_labels = [
    'low'    => ['Basse',   'bg-slate-100 text-slate-500'],
    'medium' => ['Moyenne', 'bg-yellow-100 text-yellow-700'],
    'high'   => ['Haute',   'bg-orange-100 text-orange-600'],
    'urgent' => ['Urgente', 'bg-red-100 text-red-600'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes brouillons — Système de Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- NAV -->
<nav class="bg-gradient-to-r from-blue-900 to-blue-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pfe/student/dashboard.php" class="flex items-center gap-2 text-white font-bold">
            <i class="bi bi-ticket-perforated-fill text-blue-300"></i> TicketSystem
        </a>
        <div class="flex items-center gap-1 text-sm flex-wrap">
            <a href="/pfe/student/dashboard.php"     class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-grid me-1"></i>Dashboard</a>
            <a href="/pfe/student/my_tickets.php"    class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-ticket-detailed me-1"></i>Mes tickets</a>
            <a href="/pfe/student/drafts.php"        class="text-white bg-white/20 px-3 py-1.5 rounded-lg font-semibold"><i class="bi bi-pencil-square me-1"></i>Brouillons <span class="bg-white/30 text-xs px-1.5 py-0.5 rounded-full ml-1"><?= count($drafts) ?></span></a>
            <a href="/pfe/student/create_ticket.php" class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-plus-circle me-1"></i>Nouveau</a>
            <a href="/pfe/auth/logout.php"           class="text-red-300 hover:text-red-100 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Mes brouillons</h1>
            <p class="text-slate-500 text-sm mt-0.5">
                <?= count($drafts) ?> brouillon<?= count($drafts) !== 1 ? 's' : '' ?> enregistré<?= count($drafts) !== 1 ? 's' : '' ?>
            </p>
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

    <!-- Info banner -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-6 flex items-center gap-3 text-sm text-amber-700">
        <i class="bi bi-info-circle-fill text-amber-500 text-lg flex-shrink-0"></i>
        <span>Les brouillons sont <strong>visibles uniquement par vous</strong>. Soumettez-les pour que l'administration puisse les traiter.</span>
    </div>

    <!-- Draft list -->
    <?php if (empty($drafts)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 text-center py-16 px-4">
            <i class="bi bi-pencil-square text-6xl text-slate-200"></i>
            <p class="text-slate-500 font-medium mt-4">Aucun brouillon.</p>
            <p class="text-slate-400 text-sm mt-1">
                <a href="/pfe/student/create_ticket.php" class="text-blue-500 underline">Créer une nouvelle demande</a>
                et l'enregistrer comme brouillon.
            </p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <ul class="divide-y divide-slate-50">
                <?php foreach ($drafts as $d): ?>
                    <?php [$pri_label, $pri_class] = $priority_labels[$d['priority']] ?? [$d['priority'], 'bg-slate-100 text-slate-600']; ?>
                    <li class="px-5 py-4 hover:bg-slate-50 transition">
                        <div class="flex items-start justify-between gap-4 flex-wrap">

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="font-mono text-xs font-semibold text-blue-600"><?= e($d['reference']) ?></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-600">Brouillon</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $pri_class ?>"><?= $pri_label ?></span>
                                </div>
                                <p class="font-semibold text-slate-800 text-sm truncate"><?= e($d['subject']) ?></p>
                                <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
                                    <span><i class="bi bi-tag me-0.5"></i><?= e($d['category_name']) ?><?= $d['subcategory_name'] ? ' · ' . e($d['subcategory_name']) : '' ?></span>
                                    <span><i class="bi bi-calendar3 me-0.5"></i>Créé le <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></span>
                                    <span><i class="bi bi-arrow-clockwise me-0.5"></i>Modifié <?= date('d/m/Y H:i', strtotime($d['updated_at'])) ?></span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">

                                <!-- Edit -->
                                <a href="/pfe/student/edit_ticket.php?id=<?= (int)$d['id'] ?>"
                                   class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg
                                          border border-blue-200 text-blue-600 hover:bg-blue-50 transition">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>

                                <!-- Submit -->
                                <form method="POST" action="/pfe/student/submit_draft.php"
                                      onsubmit="return confirm('Soumettre ce brouillon à l\'administration ?')">
                                    <input type="hidden" name="csrf_token"  value="<?= e($csrf) ?>">
                                    <input type="hidden" name="ticket_id"   value="<?= (int)$d['id'] ?>">
                                    <button type="submit"
                                            class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg
                                                   border border-green-200 text-green-600 hover:bg-green-50 transition">
                                        <i class="bi bi-send"></i> Soumettre
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form method="POST" action="/pfe/student/delete_draft.php"
                                      onsubmit="return confirm('Supprimer définitivement ce brouillon ?')">
                                    <input type="hidden" name="csrf_token"  value="<?= e($csrf) ?>">
                                    <input type="hidden" name="ticket_id"   value="<?= (int)$d['id'] ?>">
                                    <button type="submit"
                                            class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg
                                                   border border-red-200 text-red-500 hover:bg-red-50 transition">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
