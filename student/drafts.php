<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$stmt = $pdo->prepare("
    SELECT
        t.id, t.reference, t.subject, t.priority, t.type, t.description,
        t.created_at, t.updated_at,
        c.name AS category_name,
        s.name AS subcategory_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    LEFT JOIN subcategories s ON s.id = t.subcategory_id
    WHERE t.user_id = :uid
      AND t.status  = 'draft'
    ORDER BY t.updated_at DESC
");
$stmt->execute([':uid' => $student_id]);
$drafts = $stmt->fetchAll();

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$priority_labels = [
    'low'    => 'Low',
    'medium' => 'Medium',
    'high'   => 'High',
    'urgent' => 'Urgent',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drafts — UniPortal</title>
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

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Drafts</h1>
        <p class="text-slate-500 text-sm mt-1">Your saved drafts — edit or submit when ready</p>
    </div>
    <div class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-xl text-sm font-semibold border border-indigo-100 flex items-center gap-2">
        <span><?= count($drafts) ?> draft<?= count($drafts) !== 1 ? 's' : '' ?></span>
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

<?php if (empty($drafts)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-50 text-slate-300 mb-6">
            <i class="bi bi-pencil-square text-4xl"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800 mb-2">No drafts</h3>
        <p class="text-slate-500 mb-6">You don't have any saved drafts at the moment.</p>
        <div class="flex justify-center gap-4">
            <a href="/pfe/student/create_demande.php" class="text-indigo-600 font-semibold text-sm hover:underline">New Request</a>
            <span class="text-slate-300">|</span>
            <a href="/pfe/student/create_reclamation.php" class="text-rose-600 font-semibold text-sm hover:underline">New Complaint</a>
        </div>
    </div>
<?php else: ?>
    <!-- Drafts Grid (Matches the mockup's card style) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($drafts as $d): ?>
            <?php 
                $is_request = $d['type'] === 'request';
                $icon_color = $is_request ? 'text-indigo-600 bg-indigo-50' : 'text-rose-600 bg-rose-50';
                $icon_class = $is_request ? 'bi-file-earmark-text' : 'bi-exclamation-triangle';
                $type_bg = $is_request ? 'bg-indigo-100 text-indigo-700' : 'bg-rose-100 text-rose-700';
                $type_label = $is_request ? 'Request' : 'Complaint';
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                
                <div class="p-6 flex-1 relative">
                    <!-- Actions Dropdown / Delete -->
                    <div class="absolute top-4 right-4 flex items-center gap-1">
                        <form method="POST" action="/pfe/student/delete_draft.php" onsubmit="return confirm('Are you sure you want to delete this draft?')" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="ticket_id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-xl <?= $icon_color ?> flex items-center justify-center text-lg mb-4">
                        <i class="bi <?= $icon_class ?>"></i>
                    </div>

                    <!-- Tags -->
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $type_bg ?>"><?= $type_label ?></span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?= e($d['category_name']) ?></span>
                    </div>

                    <!-- Title & Description -->
                    <h3 class="text-base font-bold text-slate-800 mb-2 line-clamp-1" title="<?= e($d['subject']) ?>">
                        <?= e($d['subject']) ?> <span class="text-slate-400 font-normal">(draft)</span>
                    </h3>
                    <p class="text-sm text-slate-500 line-clamp-2">
                        <?= e(mb_substr($d['description'], 0, 100)) ?><?= mb_strlen($d['description']) > 100 ? '...' : '' ?>
                    </p>
                </div>

                <!-- Footer Actions -->
                <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
                    <div class="text-xs text-slate-400 flex items-center gap-1.5">
                        <i class="bi bi-clock"></i> <?= date('M d, Y', strtotime($d['updated_at'])) ?>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <a href="/pfe/student/edit_ticket.php?id=<?= (int)$d['id'] ?>" class="px-3 py-1.5 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-200 bg-slate-100 transition-colors">
                            Edit
                        </a>
                        <form method="POST" action="/pfe/student/submit_draft.php" onsubmit="return confirm('Submit this draft to administration?')" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="ticket_id" value="<?= (int)$d['id'] ?>">
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-colors flex items-center gap-1.5">
                                <i class="bi bi-send text-xs"></i> Submit
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
