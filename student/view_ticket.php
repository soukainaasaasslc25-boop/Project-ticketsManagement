<?php
// =============================================================================
// FILE    : student/view_ticket.php
// PURPOSE : Detailed view of a single ticket for the student.
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function format_date_fr($date_str) {
    if (!$date_str) return '';
    $months = ['Jan'=>'janv.', 'Feb'=>'févr.', 'Mar'=>'mars', 'Apr'=>'avr.', 'May'=>'mai', 'Jun'=>'juin', 'Jul'=>'juil.', 'Aug'=>'août', 'Sep'=>'sept.', 'Oct'=>'oct.', 'Nov'=>'nov.', 'Dec'=>'déc.'];
    $dt = date('d M Y à H:i', strtotime($date_str));
    return strtr($dt, $months);
}

$student_id = (int) $_SESSION['user_id'];
$ticket_id  = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Identifiant de ticket invalide.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// Load ticket
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name, s.name AS subcategory_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    WHERE t.id = :id AND t.user_id = :uid
    LIMIT 1
");
$stmt->execute([':id' => $ticket_id, ':uid' => $student_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable ou accès refusé.';
    redirect('/student/my_tickets.php');
}

// If draft, redirect to edit form
if ($ticket['status'] === 'draft') {
    redirect('/student/edit_ticket.php?id=' . $ticket_id);
}

// ---------------------------------------------------------------------------
// Load conversation (ONLY PUBLIC RESPONSES)
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.role
    FROM ticket_responses r
    JOIN users u ON u.id = r.sender_id
    WHERE r.ticket_id = :tid AND r.is_internal = 0
    ORDER BY r.created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$responses = $stmt->fetchAll();

// ---------------------------------------------------------------------------
// Load attachments (from initial creation)
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM ticket_attachments
    WHERE ticket_id = :tid AND response_id IS NULL
    ORDER BY created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$attachments = $stmt->fetchAll();

$sts_cfg = [
    'new'         => ['En attente', 'bg-amber-100 text-amber-700'],
    'opened'      => ['Ouvert',     'bg-cyan-100 text-cyan-700'],
    'in_progress' => ['En cours',   'bg-orange-100 text-orange-700'],
    'completed'   => ['Résolu',     'bg-green-100 text-green-700'],
    'rejected'    => ['Rejeté',     'bg-red-100 text-red-700'],
];
[$sts_label, $sts_class] = $sts_cfg[$ticket['status']] ?? [$ticket['status'], 'bg-slate-100 text-slate-600'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du ticket <?= e($ticket['reference']) ?></title>
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
            <a href="/pfe/student/my_tickets.php"     class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-ticket-detailed me-1"></i>Mes tickets</a>
            <a href="/pfe/student/drafts.php"         class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-pencil-square me-1"></i>Brouillons</a>
            <a href="/pfe/auth/logout.php"            class="text-red-300 hover:text-red-100 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-4xl mx-auto px-4 py-8">
    
    <div class="mb-4">
        <a href="/pfe/student/my_tickets.php" class="text-sm text-slate-500 hover:text-blue-600 transition flex items-center gap-1">
            <i class="bi bi-arrow-left"></i> Retour à mes tickets
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <span class="font-mono text-sm font-semibold text-blue-600"><?= e($ticket['reference']) ?></span>
                <h1 class="text-xl font-bold text-slate-800 mt-1"><?= e($ticket['subject']) ?></h1>
                <div class="flex items-center gap-3 mt-2 text-xs text-slate-500 flex-wrap">
                    <span><i class="bi bi-tag"></i> <?= e($ticket['category_name']) ?></span>
                    <span><i class="bi bi-calendar3"></i> <?= format_date_fr($ticket['submitted_at'] ?? $ticket['created_at']) ?></span>
                </div>
            </div>
            <div>
                <span class="text-sm px-3 py-1 rounded-full font-semibold <?= $sts_class ?>"><?= $sts_label ?></span>
            </div>
        </div>

        <div class="px-6 py-5">
            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Votre description</h3>
            <div class="text-slate-700 text-sm leading-relaxed whitespace-pre-line bg-slate-50 rounded-xl p-4 border border-slate-100">
                <?= e($ticket['description']) ?>
            </div>
        </div>

        <?php if (!empty($attachments)): ?>
            <div class="px-6 pb-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Pièces jointes</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($attachments as $att): ?>
                        <a href="/pfe/<?= e($att['file_path']) ?>" target="_blank"
                           class="flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition border border-blue-100">
                            <i class="bi bi-paperclip"></i>
                            <?= e($att['original_name']) ?>
                            <span class="text-slate-400">(<?= round($att['file_size'] / 1024) ?> Ko)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ticket['rejection_reason']): ?>
            <div class="mx-6 mb-5 bg-red-50 border border-red-200 rounded-2xl p-5 text-sm">
                <h3 class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-2 flex items-center gap-2">
                    <i class="bi bi-x-circle-fill"></i> Motif de rejet
                </h3>
                <p class="text-red-700 leading-relaxed"><?= e($ticket['rejection_reason']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- CONVERSATION -->
    <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
        <i class="bi bi-chat-dots text-blue-600"></i> Suivi de votre demande
    </h2>

    <?php if (empty($responses)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center">
            <i class="bi bi-clock-history text-4xl text-slate-300 mb-3 block"></i>
            <p class="text-slate-500 font-medium">Aucune réponse de l'administration pour le moment.</p>
            <p class="text-sm text-slate-400 mt-1">Vous recevrez une notification dès qu'un administrateur traitera votre ticket.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($responses as $r): ?>
                <?php
                if (str_starts_with($r['message'], '[SYSTEM]')) {
                    $sys_text = e(str_replace('[SYSTEM] ', '', $r['message']));
                    $sys_text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $sys_text);
                    ?>
                    <div class="flex justify-center my-3">
                        <span class="bg-white border border-slate-200 text-slate-500 text-xs px-3 py-1.5 rounded-full font-medium flex items-center gap-1.5 shadow-sm">
                            <i class="bi bi-info-circle text-blue-500"></i> <?= $sys_text ?>
                            <span class="opacity-50 ms-1 text-[0.65rem]"><?= format_date_fr($r['created_at']) ?></span>
                        </span>
                    </div>
                    <?php
                    continue;
                }

                $is_admin    = $r['role'] === 'admin';
                $sender_name = e($r['first_name']) . ' ' . e($r['last_name']);
                $time_fmt    = format_date_fr($r['created_at']);
                ?>
                <div class="flex gap-3 <?= $is_admin ? '' : 'flex-row-reverse' ?>">
                    <!-- Avatar -->
                    <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-bold
                                <?= $is_admin ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-200 text-slate-600' ?>">
                        <?= mb_strtoupper(mb_substr($r['first_name'], 0, 1)) ?>
                    </div>
                    <!-- Bubble -->
                    <div class="flex-1 max-w-xl <?= $is_admin ? 'items-start' : 'items-end' ?> flex flex-col">
                        <div class="mb-1 flex items-center gap-2 <?= $is_admin ? '' : 'flex-row-reverse' ?>">
                            <span class="text-xs font-semibold text-slate-700">
                                <?= $sender_name ?>
                                <?= $is_admin ? '<i class="bi bi-patch-check-fill text-blue-500 ms-0.5" title="Administration"></i>' : '' ?>
                            </span>
                            <span class="text-xs text-slate-400"><?= $time_fmt ?></span>
                        </div>
                        <div class="text-sm px-5 py-4 leading-relaxed whitespace-pre-line shadow-sm
                                    <?= $is_admin ? 'bg-white border border-slate-100 text-slate-700 rounded-2xl rounded-tl-sm' : 'bg-blue-600 text-white rounded-2xl rounded-tr-sm' ?>">
                            <?= e($r['message']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
