<?php
require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

function format_date_en($date_str) {
    if (!$date_str) return '';
    return date('M d, Y h:i A', strtotime($date_str));
}

$admin_id  = (int) $_SESSION['user_id'];
$ticket_id = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid ticket ID.';
    redirect('/admin/tickets/index.php');
}

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
    $_SESSION['flash_error'] = 'Ticket not found.';
    redirect('/admin/tickets/index.php');
}

if ($ticket['status'] === 'draft') {
    $_SESSION['flash_error'] = 'This ticket is a draft. Access denied.';
    redirect('/admin/tickets/index.php');
}

$is_closed = in_array($ticket['status'], ['completed', 'rejected'], true);

$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.role
    FROM ticket_responses r
    JOIN users u ON u.id = r.sender_id
    WHERE r.ticket_id = :tid
    ORDER BY r.created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$responses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT * FROM ticket_attachments
    WHERE ticket_id = :tid AND response_id IS NULL
    ORDER BY created_at ASC
");
$stmt->execute([':tid' => $ticket_id]);
$attachments = $stmt->fetchAll();

$admins = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'admin' ORDER BY first_name")->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$sts_cfg = [
    'new'         => ['New',    'bg-amber-100 text-amber-700 border-amber-200'],
    'opened'      => ['Opened',     'bg-purple-100 text-purple-700 border-purple-200'],
    'in_progress' => ['In Progress',   'bg-blue-100 text-blue-700 border-blue-200'],
    'completed'   => ['Completed',     'bg-emerald-100 text-emerald-700 border-emerald-200'],
    'rejected'    => ['Rejected',     'bg-rose-100 text-rose-700 border-rose-200'],
];
$pri_cfg = [
    'low'    => ['Low',   'text-slate-500 bg-slate-100 border-slate-200'],
    'medium' => ['Medium', 'text-blue-500 bg-blue-50 border-blue-200'],
    'high'   => ['High',   'text-orange-500 bg-orange-50 border-orange-200'],
    'urgent' => ['Urgent', 'text-rose-600 bg-rose-50 border-rose-200 font-bold'],
];
[$sts_label, $sts_class] = $sts_cfg[$ticket['status']]   ?? [ucfirst($ticket['status']), 'bg-slate-100 text-slate-600 border-slate-200'];
[$pri_label, $pri_class] = $pri_cfg[$ticket['priority']] ?? [ucfirst($ticket['priority']), 'bg-slate-100 text-slate-500 border-slate-200'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ticket['reference']) ?> — UniPortal Admin</title>
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
    <a href="/pfe/admin/tickets/index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-indigo-600 mb-4 transition-colors">
        <i class="bi bi-arrow-left me-1.5"></i> Back to Tickets
    </a>
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-<?= $ticket['type'] === 'complaint' ? 'rose' : 'indigo' ?>-50 text-<?= $ticket['type'] === 'complaint' ? 'rose' : 'indigo' ?>-500 flex items-center justify-center text-xl shrink-0 mt-1">
                <i class="bi <?= $ticket['type'] === 'complaint' ? 'bi-exclamation-octagon' : 'bi-file-earmark-text' ?>"></i>
            </div>
            <div>
                <div class="flex items-center gap-3 flex-wrap mb-1">
                    <h1 class="text-2xl font-bold text-slate-900"><?= e($ticket['subject']) ?></h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?= $sts_class ?>"><?= $sts_label ?></span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border <?= $pri_class ?>"><i class="bi bi-flag-fill mr-1"></i><?= $pri_label ?></span>
                </div>
                <div class="flex items-center gap-3 text-sm text-slate-500 font-medium">
                    <span class="font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">#<?= e($ticket['reference']) ?></span>
                    <span>•</span>
                    <span class="<?= $ticket['type'] === 'complaint' ? 'text-rose-500 font-semibold' : 'text-indigo-500 font-semibold' ?>"><?= $ticket['type'] === 'complaint' ? 'Complaint' : 'Request' ?></span>
                </div>
            </div>
        </div>
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT COLUMN -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Description -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <i class="bi bi-text-paragraph text-slate-400"></i>
                <h3 class="font-bold text-slate-800">Student Description</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line"><?= e($ticket['description']) ?></p>
            </div>
        </div>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <i class="bi bi-paperclip text-slate-400"></i>
                    <h3 class="font-bold text-slate-800">Attachments</h3>
                </div>
                <div class="p-4">
                    <ul class="space-y-2">
                        <?php foreach ($attachments as $att): ?>
                            <li>
                                <a href="/pfe/<?= e($att['file_path']) ?>" target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50 transition-colors group">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-indigo-100 text-slate-500 group-hover:text-indigo-600 flex items-center justify-center shrink-0">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-700 group-hover:text-indigo-700 truncate"><?= e($att['original_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= number_format($att['file_size'] / 1024, 1) ?> KB</p>
                                    </div>
                                    <i class="bi bi-download text-slate-300 group-hover:text-indigo-500 transition-colors"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Conversation & Update -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col min-h-[500px]">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="bi bi-chat-dots text-slate-400"></i>
                    <h3 class="font-bold text-slate-800">Conversation History</h3>
                </div>
                <span class="text-xs font-semibold bg-slate-200 text-slate-600 px-2.5 py-0.5 rounded-full"><?= count($responses) ?> Replies</span>
            </div>
            
            <!-- Thread -->
            <div class="flex-1 p-6 overflow-y-auto bg-slate-50/30">
                <?php if (empty($responses)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center py-12">
                        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-300 flex items-center justify-center text-3xl mb-4">
                            <i class="bi bi-chat-square-dots"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 mb-1">No activity yet</h4>
                        <p class="text-sm text-slate-500 max-w-sm">Start the conversation by replying below, or change the status.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($responses as $r): ?>
                            <?php
                            if (str_starts_with($r['message'], '[SYSTEM]')) {
                                $sys_text = e(str_replace('[SYSTEM] ', '', $r['message']));
                                $sys_text = preg_replace('/\*\*(.*?)\*\*/', '<span class="font-bold text-slate-700">$1</span>', $sys_text);
                                ?>
                                <div class="flex justify-center my-4">
                                    <div class="bg-white border border-slate-200 text-slate-500 text-xs px-4 py-2 rounded-full font-medium flex items-center gap-2 shadow-sm">
                                        <i class="bi bi-info-circle-fill text-indigo-400"></i> 
                                        <span><?= $sys_text ?></span>
                                        <span class="text-slate-400 ml-2 border-l border-slate-200 pl-2"><?= format_date_en($r['created_at']) ?></span>
                                    </div>
                                </div>
                                <?php
                                continue;
                            }

                            $is_admin    = $r['role'] === 'admin';
                            $is_internal = (bool) $r['is_internal'];
                            $sender_name = e($r['first_name']) . ' ' . e($r['last_name']);
                            $time_fmt    = format_date_en($r['created_at']);
                            $initials    = mb_strtoupper(mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1));
                            ?>
                            <div class="flex gap-4 <?= $is_admin ? 'flex-row-reverse' : '' ?>">
                                <!-- Avatar -->
                                <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-bold shadow-sm <?= $is_internal ? 'bg-amber-100 text-amber-600 ring-2 ring-amber-200' : ($is_admin ? 'bg-slate-800 text-white' : 'bg-indigo-600 text-white') ?>">
                                    <?= $initials ?>
                                </div>
                                
                                <!-- Bubble -->
                                <div class="flex-1 max-w-xl <?= $is_admin ? 'items-end' : 'items-start' ?> flex flex-col">
                                    <div class="mb-1.5 flex items-center gap-2 <?= $is_admin ? 'flex-row-reverse' : '' ?>">
                                        <span class="text-xs font-bold text-slate-700">
                                            <?= $sender_name ?>
                                            <?= $is_admin ? '<i class="bi bi-patch-check-fill text-indigo-500 ml-1" title="Admin"></i>' : '' ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?= $time_fmt ?></span>
                                        <?php if ($is_internal): ?>
                                            <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 flex items-center gap-1">
                                                <i class="bi bi-lock-fill"></i> Internal Note
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm px-5 py-3.5 leading-relaxed whitespace-pre-line shadow-sm <?= $is_internal ? 'bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl border-dashed '.($is_admin ? 'rounded-tr-sm' : 'rounded-tl-sm') : ($is_admin ? 'bg-indigo-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white border border-slate-100 text-slate-700 rounded-2xl rounded-tl-sm') ?>">
                                        <?= e($r['message']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Unified Form -->
            <?php if ($is_closed): ?>
                <div class="p-6 border-t border-slate-100 bg-white">
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 text-center">
                        <i class="bi bi-lock text-slate-400 text-2xl mb-2 block"></i>
                        <h4 class="font-bold text-slate-700 mb-1">Ticket is Closed</h4>
                        <p class="text-sm text-slate-500">Replies and status changes are disabled.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-6 border-t border-slate-100 bg-white">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-3">Update Ticket</h3>
                    <form id="update-form" method="POST" action="/pfe/admin/tickets/update_status.php">
                        <input type="hidden" name="csrf_token"  value="<?= e($csrf) ?>">
                        <input type="hidden" name="ticket_id"   value="<?= (int)$ticket_id ?>">
                        <input type="hidden" name="action"      value="update">

                        <textarea name="message" rows="3" placeholder="Write a reply (optional if only changing status)..."
                                  class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-shadow mb-4 resize-none"></textarea>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 mb-1.5">Change Status</label>
                                <select name="status" id="status-select" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-medium">
                                    <?php
                                    $status_options = [
                                        'new'         => 'New',
                                        'opened'      => 'Opened',
                                        'in_progress' => 'In Progress',
                                        'completed'   => 'Completed (Resolve)',
                                        'rejected'    => 'Rejected',
                                    ];
                                    foreach ($status_options as $val => $lbl):
                                    ?>
                                        <option value="<?= $val ?>" <?= $ticket['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2 text-sm text-slate-700 font-medium cursor-pointer group select-none">
                                    <input type="checkbox" name="is_internal" value="1" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 cursor-pointer">
                                    <i class="bi bi-lock-fill text-amber-500 group-hover:text-amber-600 transition-colors"></i>
                                    <span class="group-hover:text-slate-900 transition-colors">Internal Note (Hidden from student)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Rejection Reason -->
                        <div id="rejection-wrap" class="mb-4 <?= $ticket['status'] === 'rejected' ? '' : 'hidden' ?>">
                            <label class="block text-xs font-bold text-rose-600 mb-1.5">Rejection Reason <span class="text-rose-500">*</span></label>
                            <textarea name="rejection_reason" id="rejection_reason" rows="2"
                                      class="w-full border border-rose-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 bg-rose-50/30 resize-none"
                                      placeholder="Explain why this ticket is being rejected..."><?= e($ticket['rejection_reason'] ?? '') ?></textarea>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="flex items-center justify-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200 w-full sm:w-auto">
                                <i class="bi bi-send-fill"></i> Update & Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="space-y-6">

        <!-- Student Profile -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 flex items-center gap-2">
                <i class="bi bi-person-badge text-indigo-400"></i> Student Profile
            </h3>
            <div class="flex items-center gap-4 mb-5">
                <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-lg shrink-0">
                    <?= mb_strtoupper(mb_substr($ticket['student_first'], 0, 1) . mb_substr($ticket['student_last'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-bold text-slate-800 text-base"><?= e($ticket['student_first']) . ' ' . e($ticket['student_last']) ?></p>
                    <p class="text-xs font-medium text-slate-500">@<?= e($ticket['student_username']) ?></p>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="bg-slate-50 rounded-xl p-3 flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Group</span>
                    <span class="text-sm font-semibold text-slate-800"><?= e($ticket['group_name'] ?? 'N/A') ?></span>
                </div>
                <div class="bg-slate-50 rounded-xl p-3 flex justify-between items-center border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Field (Filière)</span>
                    <span class="text-sm font-semibold text-slate-800"><?= e($ticket['filiere'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>

        <!-- Assignment -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 flex items-center gap-2">
                <i class="bi bi-person-check text-indigo-400"></i> Assignment
            </h3>
            
            <?php if ($ticket['assigned_first']): ?>
                <div class="flex items-center gap-3 mb-4 p-3 bg-indigo-50 rounded-xl border border-indigo-100">
                    <div class="w-8 h-8 rounded-full bg-indigo-200 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                        <?= mb_strtoupper(mb_substr($ticket['assigned_first'], 0, 1) . mb_substr($ticket['assigned_last'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-xs text-indigo-400 font-bold uppercase tracking-wider">Assigned to</p>
                        <p class="text-sm font-bold text-indigo-800"><?= e($ticket['assigned_first']) . ' ' . e($ticket['assigned_last']) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-3 mb-4 p-3 bg-slate-50 rounded-xl border border-dashed border-slate-300 text-slate-500">
                    <i class="bi bi-person text-xl"></i>
                    <span class="text-sm font-medium">Currently unassigned</span>
                </div>
            <?php endif; ?>

            <?php if ($is_closed): ?>
                <div class="text-xs font-semibold text-slate-400 bg-slate-50 p-3 rounded-xl text-center border border-slate-100 flex items-center justify-center gap-2">
                    <i class="bi bi-lock-fill"></i> Assignment locked
                </div>
            <?php else: ?>
                <form method="POST" action="/pfe/admin/tickets/assign.php" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="ticket_id"  value="<?= (int)$ticket_id ?>">

                    <select name="assigned_to" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white font-medium">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($admins as $adm): ?>
                            <option value="<?= (int)$adm['id'] ?>" <?= (int)$ticket['assigned_to'] === (int)$adm['id'] ? 'selected' : '' ?>>
                                <?= e($adm['first_name']) . ' ' . e($adm['last_name']) ?>
                                <?= (int)$adm['id'] === $admin_id ? ' (Me)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="flex gap-2">
                        <button type="submit" name="assigned_to" value="<?= $admin_id ?>" class="flex-1 py-2.5 bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center gap-1">
                            <i class="bi bi-person-fill"></i> Assign to Me
                        </button>
                        <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-indigo-700 transition-colors">
                            Assign
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Ticket Metadata -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-4 flex items-center gap-2">
                <i class="bi bi-info-circle text-indigo-400"></i> Ticket Info
            </h3>
            <ul class="space-y-3 text-sm">
                <li class="flex justify-between items-center pb-2 border-b border-slate-50">
                    <span class="text-slate-500 font-medium">Category</span>
                    <span class="font-semibold text-slate-800 text-right max-w-[150px] truncate" title="<?= e($ticket['category_name']) ?>"><?= e($ticket['category_name']) ?></span>
                </li>
                <?php if ($ticket['subcategory_name']): ?>
                    <li class="flex justify-between items-center pb-2 border-b border-slate-50">
                        <span class="text-slate-500 font-medium">Subcategory</span>
                        <span class="font-semibold text-slate-800 text-right max-w-[150px] truncate" title="<?= e($ticket['subcategory_name']) ?>"><?= e($ticket['subcategory_name']) ?></span>
                    </li>
                <?php endif; ?>
                <li class="flex justify-between items-center pb-2 border-b border-slate-50">
                    <span class="text-slate-500 font-medium">Created</span>
                    <span class="font-semibold text-slate-800 text-right"><?= date('M d, Y', strtotime($ticket['created_at'])) ?></span>
                </li>
                <?php if ($ticket['submitted_at']): ?>
                    <li class="flex justify-between items-center pb-2 border-b border-slate-50">
                        <span class="text-slate-500 font-medium">Submitted</span>
                        <span class="font-semibold text-slate-800 text-right"><?= date('M d, Y', strtotime($ticket['submitted_at'])) ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($ticket['resolved_at']): ?>
                    <li class="flex justify-between items-center">
                        <span class="text-slate-500 font-medium">Resolved</span>
                        <span class="font-semibold text-emerald-600 text-right"><?= date('M d, Y', strtotime($ticket['resolved_at'])) ?></span>
                    </li>
                <?php endif; ?>
            </ul>
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
    // Trigger on load
    statusSelect.dispatchEvent(new Event('change'));
}
</script>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
