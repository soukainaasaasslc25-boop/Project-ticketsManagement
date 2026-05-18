<?php
// =============================================================================
// FILE    : admin/tickets/view.php
// PURPOSE : Detailed view of a single ticket for admin.
//           Sections:
//           1. Ticket info (subject, category, priority, status)
//           2. Student info (name, group, filière)
//           3. Assignment panel
//           4. Conversation thread (public replies + internal notes + system msgs)
//           5. Unified update form (reply + status + rejection reason)
// HOW TO TEST:
//   1. Go to /pfe/admin/tickets/index.php → click "Voir" on any ticket
//   2. Type a reply, select a new status, submit → updates both
//   3. Auto-transition: first reply on 'new' auto-changes to 'opened'
//   4. View system messages injected when status changes
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

function format_date_fr($date_str) {
    if (!$date_str) return '';
    $months = ['Jan'=>'janv.', 'Feb'=>'févr.', 'Mar'=>'mars', 'Apr'=>'avr.', 'May'=>'mai', 'Jun'=>'juin', 'Jul'=>'juil.', 'Aug'=>'août', 'Sep'=>'sept.', 'Oct'=>'oct.', 'Nov'=>'nov.', 'Dec'=>'déc.'];
    $dt = date('d M Y à H:i', strtotime($date_str));
    return strtr($dt, $months);
}

$admin_id  = (int) $_SESSION['user_id'];
$ticket_id = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Identifiant de ticket invalide.';
    redirect('/admin/tickets/index.php');
}

// ---------------------------------------------------------------------------
// Load ticket with all joins
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.*,
        c.name   AS category_name,
        s.name   AS subcategory_name,
        u.first_name AS student_first, u.last_name AS student_last,
        u.username AS student_username, u.group_name, u.filiere,
        adm.first_name AS assigned_first, adm.last_name AS assigned_last
    FROM tickets t
    JOIN  categories    c   ON c.id   = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    JOIN  users         u   ON u.id   = t.user_id
    LEFT JOIN users     adm ON adm.id = t.assigned_to
    WHERE t.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/admin/tickets/index.php');
}

if ($ticket['status'] === 'draft') {
    $_SESSION['flash_error'] = 'Ce ticket est un brouillon. Accès refusé.';
    redirect('/admin/tickets/index.php');
}

$is_closed = in_array($ticket['status'], ['completed', 'rejected'], true);

// ---------------------------------------------------------------------------
// Load conversation thread (admin sees ALL: public + internal)
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.role
    FROM ticket_responses r
    JOIN users u ON u.id = r.sender_id
    WHERE r.ticket_id = :tid
    ORDER BY r.created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$responses = $stmt->fetchAll();

// ---------------------------------------------------------------------------
// Load attachments
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT * FROM ticket_attachments
    WHERE ticket_id = :tid AND response_id IS NULL
    ORDER BY created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$attachments = $stmt->fetchAll();

// ---------------------------------------------------------------------------
// Load all admins for the assign dropdown
// ---------------------------------------------------------------------------
$admins = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'admin' ORDER BY first_name")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Badge helpers
$sts_cfg = [
    'draft'       => ['Brouillon',  'bg-slate-100 text-slate-600'],
    'new'         => ['Nouveau',    'bg-blue-100 text-blue-700'],
    'opened'      => ['Ouvert',     'bg-cyan-100 text-cyan-700'],
    'in_progress' => ['En cours',   'bg-orange-100 text-orange-700'],
    'completed'   => ['Résolu',     'bg-green-100 text-green-700'],
    'rejected'    => ['Rejeté',     'bg-red-100 text-red-700'],
];
$pri_cfg = [
    'low'    => ['Basse',   'bg-slate-100 text-slate-500'],
    'medium' => ['Moyenne', 'bg-yellow-100 text-yellow-700'],
    'high'   => ['Haute',   'bg-orange-100 text-orange-600'],
    'urgent' => ['Urgente', 'bg-red-100 text-red-600'],
];
[$sts_label, $sts_class] = $sts_cfg[$ticket['status']]   ?? [$ticket['status'], 'bg-slate-100 text-slate-600'];
[$pri_label, $pri_class] = $pri_cfg[$ticket['priority']] ?? [$ticket['priority'], 'bg-slate-100 text-slate-500'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ticket['reference']) ?> — Admin Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- NAV -->
<nav class="bg-gradient-to-r from-slate-900 to-slate-700 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pfe/admin/dashboard.php" class="flex items-center gap-2 text-white font-bold">
            <i class="bi bi-ticket-perforated-fill text-blue-400"></i> TicketSystem
            <span class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded-full font-semibold ml-1">Admin</span>
        </a>
        <div class="flex items-center gap-1 text-sm">
            <a href="/pfe/admin/tickets/index.php" class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-arrow-left me-1"></i>Retour aux tickets</a>
            <a href="/pfe/auth/logout.php"         class="text-red-400 hover:text-red-300 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Flash -->
    <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 flex gap-3 text-sm">
            <i class="bi bi-check-circle-fill text-green-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_success ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex gap-3 text-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ===== LEFT COLUMN: Ticket content + thread ===== -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Ticket Header Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-gradient-to-r from-slate-800 to-slate-600 px-6 py-4">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <span class="font-mono text-sm font-semibold text-blue-300"><?= e($ticket['reference']) ?></span>
                            <h1 class="text-white font-bold text-lg mt-1"><?= e($ticket['subject']) ?></h1>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $sts_class ?>"><?= $sts_label ?></span>
                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $pri_class ?>"><?= $pri_label ?></span>
                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $ticket['type'] === 'complaint' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-700' ?>">
                                <?= $ticket['type'] === 'complaint' ? 'Réclamation' : 'Demande' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ticket meta row -->
                <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-4 text-xs text-slate-500">
                    <span><i class="bi bi-tag me-1"></i><?= e($ticket['category_name']) ?><?= $ticket['subcategory_name'] ? ' › ' . e($ticket['subcategory_name']) : '' ?></span>
                    <span><i class="bi bi-calendar3 me-1"></i>Soumis le <?= $ticket['submitted_at'] ? date('d/m/Y H:i', strtotime($ticket['submitted_at'])) : '—' ?></span>
                    <span><i class="bi bi-arrow-clockwise me-1"></i>Mis à jour <?= date('d/m/Y H:i', strtotime($ticket['updated_at'])) ?></span>
                </div>

                <!-- Description -->
                <div class="px-6 py-5">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Description</h3>
                    <div class="text-slate-700 text-sm leading-relaxed whitespace-pre-line bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <?= e($ticket['description']) ?>
                    </div>
                </div>

                <!-- Attachments -->
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
            </div>

            <!-- CONVERSATION THREAD & UNIFIED FORM -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                        <i class="bi bi-chat-dots text-blue-500"></i> Conversation
                        <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= count($responses) ?></span>
                    </h2>
                </div>

                <?php if (empty($responses)): ?>
                    <div class="px-6 py-8 text-center text-slate-400 text-sm">
                        <i class="bi bi-chat-square-dots text-3xl block mb-2 opacity-30"></i>
                        Aucun message pour l'instant.
                    </div>
                <?php else: ?>
                    <div class="px-6 py-4 space-y-4">
                        <?php foreach ($responses as $r): ?>
                            <?php
                            if (str_starts_with($r['message'], '[SYSTEM]')) {
                                $sys_text = e(str_replace('[SYSTEM] ', '', $r['message']));
                                $sys_text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $sys_text);
                                ?>
                                <div class="flex justify-center my-3">
                                    <span class="bg-slate-50 border border-slate-100 text-slate-500 text-xs px-3 py-1.5 rounded-full font-medium flex items-center gap-1.5 shadow-sm">
                                        <i class="bi bi-info-circle"></i> <?= $sys_text ?>
                                        <span class="opacity-50 ms-1"><?= format_date_fr($r['created_at']) ?></span>
                                    </span>
                                </div>
                                <?php
                                continue;
                            }
                            
                            $is_admin    = $r['role'] === 'admin';
                            $is_internal = (bool) $r['is_internal'];
                            $sender_name = e($r['first_name']) . ' ' . e($r['last_name']);
                            $time_fmt    = format_date_fr($r['created_at']);
                            ?>
                            <div class="flex gap-3 <?= $is_admin ? 'flex-row-reverse' : '' ?>">
                                <!-- Avatar -->
                                <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold
                                            <?= $is_internal ? 'bg-amber-100 text-amber-600 ring-2 ring-amber-200 ring-offset-1' : ($is_admin ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                                    <?= mb_strtoupper(mb_substr($r['first_name'], 0, 1)) ?>
                                </div>
                                <!-- Bubble -->
                                <div class="flex-1 max-w-lg <?= $is_admin ? 'items-end' : 'items-start' ?> flex flex-col">
                                    <div class="<?= $is_admin ? 'text-right' : '' ?> mb-1 flex items-center gap-2 <?= $is_admin ? 'flex-row-reverse' : '' ?>">
                                        <span class="text-xs font-semibold text-slate-700">
                                            <?= $sender_name ?> 
                                            <?= $is_admin ? '<i class="bi bi-patch-check-fill text-blue-500 ms-0.5" title="Admin"></i>' : '' ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?= $time_fmt ?></span>
                                        <?php if ($is_internal): ?>
                                            <span class="text-xs bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded font-medium border border-amber-200">
                                                <i class="bi bi-lock-fill"></i> Note interne
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm px-4 py-3 leading-relaxed whitespace-pre-line shadow-sm
                                                <?= $is_internal
                                                    ? 'bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl rounded-tr-sm border-dashed'
                                                    : ($is_admin ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white border border-slate-100 text-slate-700 rounded-2xl rounded-tl-sm') ?>">
                                        <?= e($r['message']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- UNIFIED UPDATE FORM -->
                <?php if ($is_closed): ?>
                    <div class="px-6 py-6 border-t border-slate-100 bg-slate-50 text-center">
                        <i class="bi bi-lock-fill text-slate-300 text-3xl mb-2 block"></i>
                        <p class="text-slate-600 font-medium">Ce ticket est fermé.</p>
                        <p class="text-sm text-slate-400 mt-1">Les réponses et modifications de statut sont désactivées.</p>
                    </div>
                <?php else: ?>
                    <div class="px-6 py-5 border-t border-slate-100 bg-slate-50">
                        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Mettre à jour le ticket</h3>
                        <form id="update-form" method="POST" action="/pfe/admin/tickets/update_status.php">
                            <input type="hidden" name="csrf_token"  value="<?= e($csrf) ?>">
                            <input type="hidden" name="ticket_id"   value="<?= (int)$ticket_id ?>">
                            <input type="hidden" name="action"      value="update">

                            <!-- Row: Message -->
                            <textarea name="message" rows="3" placeholder="Rédigez une réponse (optionnel si changement de statut uniquement)..."
                                      class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white resize-none mb-3"></textarea>

                            <!-- Row: Status & Internal Note -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Statut</label>
                                    <select name="status" id="status-select" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white font-medium">
                                        <?php
                                        $status_options = [
                                            'new'         => 'Nouveau',
                                            'opened'      => 'Ouvert',
                                            'in_progress' => 'En cours',
                                            'completed'   => 'Résolu',
                                            'rejected'    => 'Rejeté',
                                        ];
                                        foreach ($status_options as $val => $lbl):
                                        ?>
                                            <option value="<?= $val ?>" <?= $ticket['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex flex-col justify-center pt-5">
                                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer w-max">
                                        <input type="checkbox" name="is_internal" value="1"
                                               class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400">
                                        <i class="bi bi-lock-fill text-amber-500"></i>
                                        <span>Note interne (invisible pour l'étudiant)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Row: Rejection Reason (Hidden by default) -->
                            <div id="rejection-wrap" class="mb-4 <?= $ticket['status'] === 'rejected' ? '' : 'hidden' ?>">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Motif de rejet <span class="text-red-500">*</span></label>
                                <textarea name="rejection_reason" id="rejection_reason" rows="2"
                                          class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
                                          placeholder="Expliquez pourquoi ce ticket est rejeté..."><?= e($ticket['rejection_reason'] ?? '') ?></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition shadow-sm">
                                    <i class="bi bi-send-fill"></i> Mettre à jour
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== RIGHT COLUMN: Admin controls ===== -->
        <div class="space-y-5">

            <!-- Student Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4 flex items-center gap-2">
                    <i class="bi bi-person-circle text-blue-400"></i> Étudiant
                </h3>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                        <?= mb_strtoupper(mb_substr($ticket['student_first'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800"><?= e($ticket['student_first']) . ' ' . e($ticket['student_last']) ?></p>
                        <p class="text-xs text-slate-400">@<?= e($ticket['student_username']) ?></p>
                    </div>
                </div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Groupe</span>
                        <span class="font-medium text-slate-700"><?= e($ticket['group_name'] ?? '—') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Filière</span>
                        <span class="font-medium text-slate-700"><?= e($ticket['filiere'] ?? '—') ?></span>
                    </div>
                </div>
            </div>

            <!-- ASSIGN -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4 flex items-center gap-2">
                    <i class="bi bi-person-check text-blue-400"></i> Assignation
                </h3>
                <?php if ($ticket['assigned_first']): ?>
                    <div class="flex items-center gap-2 mb-3 p-2 bg-blue-50 rounded-xl border border-blue-100">
                        <i class="bi bi-person-fill text-blue-500"></i>
                        <span class="text-sm font-medium text-blue-700">
                            <?= e($ticket['assigned_first']) . ' ' . e($ticket['assigned_last']) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-2 mb-3 p-2 bg-slate-50 rounded-xl border border-slate-100">
                        <i class="bi bi-person text-slate-400"></i>
                        <span class="text-sm font-medium text-slate-500">Non assigné</span>
                    </div>
                <?php endif; ?>

                <?php if ($is_closed): ?>
                    <div class="text-xs text-slate-400 bg-slate-50 p-2 rounded-lg text-center border border-slate-100">
                        <i class="bi bi-lock-fill"></i> Assignation verrouillée (ticket fermé)
                    </div>
                <?php else: ?>
                    <form method="POST" action="/pfe/admin/tickets/assign.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="ticket_id"  value="<?= (int)$ticket_id ?>">

                        <select name="assigned_to"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white">
                            <option value="">— Non assigné —</option>
                            <?php foreach ($admins as $adm): ?>
                                <option value="<?= (int)$adm['id'] ?>"
                                    <?= (int)$ticket['assigned_to'] === (int)$adm['id'] ? 'selected' : '' ?>>
                                    <?= e($adm['first_name']) . ' ' . e($adm['last_name']) ?>
                                    <?= (int)$adm['id'] === $admin_id ? ' (moi)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="flex gap-2">
                            <!-- Quick self-assign -->
                            <button type="submit" name="assigned_to" value="<?= $admin_id ?>"
                                    class="flex-1 py-2 bg-slate-100 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-200 transition">
                                <i class="bi bi-person-fill me-1"></i> M'assigner
                            </button>
                            <button type="submit"
                                    class="flex-1 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                                Assigner
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Quick info panel -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 text-sm space-y-2">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Informations</h3>
                <div class="flex justify-between"><span class="text-slate-400">Référence</span><span class="font-mono text-blue-600 font-semibold"><?= e($ticket['reference']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Type</span><span><?= $ticket['type'] === 'complaint' ? 'Réclamation' : 'Demande' ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Catégorie</span><span class="text-right max-w-32 truncate" title="<?= e($ticket['category_name']) ?>"><?= e($ticket['category_name']) ?></span></div>
                <?php if ($ticket['subcategory_name']): ?>
                    <div class="flex justify-between"><span class="text-slate-400">Sous-catégorie</span><span class="text-right max-w-32 truncate" title="<?= e($ticket['subcategory_name']) ?>"><?= e($ticket['subcategory_name']) ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between"><span class="text-slate-400">Créé</span><span><?= format_date_fr($ticket['created_at']) ?></span></div>
                <?php if ($ticket['resolved_at']): ?>
                    <div class="flex justify-between"><span class="text-slate-400">Résolu</span><span><?= format_date_fr($ticket['resolved_at']) ?></span></div>
                <?php endif; ?>
            </div>

            <?php if ($ticket['rejection_reason']): ?>
                <div class="bg-red-50 border border-red-200 rounded-2xl p-5 text-sm">
                    <h3 class="text-xs font-semibold text-red-500 uppercase tracking-wide mb-2 flex items-center gap-2">
                        <i class="bi bi-x-circle-fill"></i> Motif de rejet
                    </h3>
                    <p class="text-red-700 leading-relaxed"><?= e($ticket['rejection_reason']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const statusSelect = document.getElementById('status-select');
const rejectionWrap = document.getElementById('rejection-wrap');
const rejectionInput = document.getElementById('rejection_reason');

if (statusSelect && rejectionWrap) {
    statusSelect.addEventListener('change', function () {
        const isRejected = this.value === 'rejected';
        rejectionWrap.classList.toggle('hidden', !isRejected);
        if (isRejected) {
            rejectionInput.setAttribute('required', 'required');
        } else {
            rejectionInput.removeAttribute('required');
        }
    });
    // Trigger on load to set correct state
    statusSelect.dispatchEvent(new Event('change'));
}
</script>
</body>
</html>
