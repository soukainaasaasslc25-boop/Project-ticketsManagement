<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function format_date_en($date_str) {
    if (!$date_str) return '';
    return date('M d, Y h:i A', strtotime($date_str));
}

$student_id = (int) $_SESSION['user_id'];
$ticket_id  = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid ticket ID.';
    redirect('/student/dashboard.php');
}

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
    $_SESSION['flash_error'] = 'Ticket not found or access denied.';
    redirect('/student/dashboard.php');
}

if ($ticket['status'] === 'draft') {
    redirect('/student/edit_ticket.php?id=' . $ticket_id);
}

$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.role
    FROM ticket_responses r
    JOIN users u ON u.id = r.sender_id
    WHERE r.ticket_id = :tid AND r.is_internal = 0
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
$s_color = $sts_colors[$ticket['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
$s_label = $sts_labels[$ticket['status']] ?? ucfirst($ticket['status']);

$pri_colors = [
    'low' => 'bg-slate-100 text-slate-700 border-slate-200',
    'medium' => 'bg-blue-100 text-blue-700 border-blue-200',
    'high' => 'bg-orange-100 text-orange-700 border-orange-200',
    'urgent' => 'bg-rose-100 text-rose-700 border-rose-200'
];
$p_color = $pri_colors[$ticket['priority']] ?? 'bg-slate-100 text-slate-700 border-slate-200';

$is_complaint = $ticket['type'] === 'complaint';
$theme_color = $is_complaint ? 'rose' : 'indigo';
$icon = $is_complaint ? 'bi-exclamation-octagon' : 'bi-file-earmark-text';
$back_url = $is_complaint ? '/student/reclamations.php' : '/student/demandes.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= e($ticket['reference']) ?> — UniPortal</title>
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
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-<?= $theme_color ?>-500 selection:text-white">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Header -->
<div class="mb-6">
    <a href="/pfe<?= $back_url ?>" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-<?= $theme_color ?>-600 mb-4 transition-colors">
        <i class="bi bi-arrow-left me-1.5"></i> Back to List
    </a>
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-<?= $theme_color ?>-50 text-<?= $theme_color ?>-500 flex items-center justify-center text-xl shrink-0 mt-1">
                <i class="bi <?= $icon ?>"></i>
            </div>
            <div>
                <div class="flex items-center gap-3 flex-wrap mb-1">
                    <h1 class="text-2xl font-bold text-slate-900"><?= e($ticket['subject']) ?></h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $s_color ?>">
                        <?= $s_label ?>
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $p_color ?>">
                        <?= ucfirst($ticket['priority']) ?> Priority
                    </span>
                </div>
                <div class="flex items-center gap-3 text-sm text-slate-500 font-medium">
                    <span class="font-mono text-<?= $theme_color ?>-600 bg-<?= $theme_color ?>-50 px-2 py-0.5 rounded-md">#<?= e($ticket['reference']) ?></span>
                    <span>•</span>
                    <span><?= e($ticket['category_name']) ?></span>
                    <span>•</span>
                    <span>Created on <?= date('M d, Y', strtotime($ticket['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl">
    
    <!-- Left Column: Details & Attachments -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Original Description -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <i class="bi bi-text-paragraph text-slate-400"></i>
                <h3 class="font-bold text-slate-800">Description</h3>
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
                                <a href="/pfe/<?= e($att['file_path']) ?>" target="_blank" class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-<?= $theme_color ?>-200 hover:bg-<?= $theme_color ?>-50 transition-colors group">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-<?= $theme_color ?>-100 text-slate-500 group-hover:text-<?= $theme_color ?>-600 flex items-center justify-center shrink-0">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-700 group-hover:text-<?= $theme_color ?>-700 truncate"><?= e($att['original_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= number_format($att['file_size'] / 1024, 1) ?> KB</p>
                                    </div>
                                    <i class="bi bi-download text-slate-300 group-hover:text-<?= $theme_color ?>-500 transition-colors"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ticket['rejection_reason']): ?>
            <div class="bg-rose-50 border border-rose-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-rose-100 bg-rose-100/50 flex items-center gap-2">
                    <i class="bi bi-x-circle-fill text-rose-500"></i>
                    <h3 class="font-bold text-rose-800">Rejection Reason</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-rose-700 leading-relaxed"><?= e($ticket['rejection_reason']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Conversation Thread -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-full min-h-[500px]">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                <i class="bi bi-chat-dots text-slate-400"></i>
                <h3 class="font-bold text-slate-800">Conversation History</h3>
            </div>
            
            <div class="flex-1 p-6 overflow-y-auto bg-slate-50/30">
                <?php if (empty($responses)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center p-8">
                        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-300 flex items-center justify-center text-3xl mb-4">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 mb-1">No replies yet</h4>
                        <p class="text-sm text-slate-500 max-w-sm">Administration has not responded to this ticket yet. You will be notified when they do.</p>
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
                                        <i class="bi bi-info-circle-fill text-blue-400"></i> 
                                        <span><?= $sys_text ?></span>
                                        <span class="text-slate-400 ml-2 border-l border-slate-200 pl-2"><?= format_date_en($r['created_at']) ?></span>
                                    </div>
                                </div>
                                <?php
                                continue;
                            }

                            $is_admin    = $r['role'] === 'admin';
                            $sender_name = e($r['first_name']) . ' ' . e($r['last_name']);
                            $time_fmt    = format_date_en($r['created_at']);
                            $initials    = mb_strtoupper(mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1));
                            ?>
                            <div class="flex gap-4 <?= $is_admin ? '' : 'flex-row-reverse' ?>">
                                <!-- Avatar -->
                                <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-bold shadow-sm <?= $is_admin ? 'bg-slate-800 text-white' : 'bg-'.$theme_color.'-600 text-white' ?>">
                                    <?= $initials ?>
                                </div>
                                
                                <!-- Message Body -->
                                <div class="flex-1 max-w-xl <?= $is_admin ? 'items-start' : 'items-end' ?> flex flex-col">
                                    <div class="mb-1.5 flex items-center gap-2 <?= $is_admin ? '' : 'flex-row-reverse' ?>">
                                        <span class="text-xs font-bold text-slate-700">
                                            <?= $sender_name ?>
                                            <?= $is_admin ? '<i class="bi bi-patch-check-fill text-blue-500 ml-1" title="Admin"></i>' : '' ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?= $time_fmt ?></span>
                                    </div>
                                    <div class="text-sm px-5 py-3.5 leading-relaxed whitespace-pre-line shadow-sm <?= $is_admin ? 'bg-white border border-slate-100 text-slate-700 rounded-2xl rounded-tl-sm' : 'bg-'.$theme_color.'-600 text-white rounded-2xl rounded-tr-sm' ?>">
                                        <?= e($r['message']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($ticket['status'] !== 'completed' && $ticket['status'] !== 'rejected'): ?>
                <div class="p-4 border-t border-slate-100 bg-white">
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-center">
                        <i class="bi bi-lock text-slate-400 text-xl mb-2 block"></i>
                        <p class="text-sm text-slate-500 font-medium">Responses are managed by administration.</p>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
