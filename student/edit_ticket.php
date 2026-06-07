<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];
$ticket_id = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid ticket.';
    redirect('/student/drafts.php');
}

$stmt = $pdo->prepare('
    SELECT t.*, c.name AS category_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    WHERE t.id = :id
    LIMIT 1
');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket not found.';
    redirect('/student/drafts.php');
}

if ((int) $ticket['user_id'] !== $student_id) {
    $_SESSION['flash_error'] = 'Access denied.';
    redirect('/student/drafts.php');
}

$editable_statuses = ['draft', 'new'];
if (!in_array($ticket['status'], $editable_statuses, true)) {
    $_SESSION['flash_error'] = 'This ticket can no longer be edited as it is being processed.';
    redirect('/student/my_tickets.php');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$stmt = $pdo->query('SELECT id, type, name FROM categories WHERE is_active = 1 ORDER BY type, name');
$all_cats = $stmt->fetchAll();
$cats_by_type = ['request' => [], 'complaint' => []];
foreach ($all_cats as $c) {
    $cats_by_type[$c['type']][] = $c;
}

$old = $_SESSION['form_repopulate'] ?? [];
unset($_SESSION['form_repopulate']);

$v_category_id    = (int)    ($old['category_id']    ?? $ticket['category_id']);
$v_subcategory_id = (int)    ($old['subcategory_id'] ?? $ticket['subcategory_id'] ?? 0);
$v_priority       =           $old['priority']       ?? $ticket['priority'];
$v_subject        =           $old['subject']        ?? $ticket['subject'];
$v_description    =           $old['description']    ?? $ticket['description'];

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$is_complaint = $ticket['type'] === 'complaint';
$theme_color = $is_complaint ? 'rose' : 'indigo';
$icon = $is_complaint ? 'bi-exclamation-octagon' : 'bi-file-earmark-text';
$page_title = 'Edit ' . ($is_complaint ? 'Complaint' : 'Request');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — UniPortal</title>
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

<!-- Content Header -->
<div class="mb-6">
    <a href="/pfe/student/<?= $ticket['status'] === 'draft' ? 'drafts' : ($is_complaint ? 'reclamations' : 'demandes') ?>.php" 
       class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-<?= $theme_color ?>-600 mb-2 transition-colors">
        <i class="bi bi-arrow-left me-1.5"></i> Back
    </a>
    <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl font-bold text-slate-900"><?= e($page_title) ?></h1>
        <span class="px-2.5 py-0.5 rounded-lg text-sm font-mono font-semibold bg-<?= $theme_color ?>-100 text-<?= $theme_color ?>-700">
            <?= e($ticket['reference']) ?>
        </span>
    </div>
    <p class="text-slate-500 text-sm mt-1">Modify the details of your ticket below.</p>
</div>

<?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_error ?></div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-4xl">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/50">
        <i class="bi bi-pencil-square text-<?= $theme_color ?>-500 text-lg"></i>
        <h3 class="font-bold text-slate-800">Edit Form</h3>
    </div>

    <div class="p-6 sm:p-8">
        <form id="edit-form" action="/pfe/student/process_edit_ticket.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="ticket_id" value="<?= (int)$ticket_id ?>">
            <input type="hidden" name="action" id="form-action" value="save">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Category -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Category <span class="text-rose-500">*</span></label>
                    <select id="category_id" name="category_id" required class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-<?= $theme_color ?>-500 focus:border-transparent transition-shadow text-sm bg-white appearance-none">
                        <option value="">— Select Category —</option>
                        <?php if (!empty($cats_by_type['request'])): ?>
                            <optgroup label="📋 Requests" class="font-semibold text-slate-900 bg-slate-50">
                                <?php foreach ($cats_by_type['request'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $v_category_id ? 'selected' : '' ?> class="font-normal text-slate-700 bg-white">
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($cats_by_type['complaint'])): ?>
                            <optgroup label="⚠️ Complaints" class="font-semibold text-slate-900 bg-slate-50">
                                <?php foreach ($cats_by_type['complaint'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $v_category_id ? 'selected' : '' ?> class="font-normal text-slate-700 bg-white">
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Subcategory -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Subcategory <span class="text-slate-400 font-normal">(Optional)</span></label>
                    <select id="subcategory_id" name="subcategory_id" disabled class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-<?= $theme_color ?>-500 focus:border-transparent transition-shadow text-sm bg-slate-50 appearance-none disabled:opacity-60">
                        <option value="">Loading...</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Priority -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Priority <span class="text-rose-500">*</span></label>
                    <select name="priority" class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-<?= $theme_color ?>-500 focus:border-transparent transition-shadow text-sm bg-white appearance-none">
                        <option value="low" <?= $v_priority == 'low' ? 'selected' : '' ?>>Low (Non-urgent)</option>
                        <option value="medium" <?= $v_priority == 'medium' ? 'selected' : '' ?>>Medium (Standard)</option>
                        <option value="high" <?= $v_priority == 'high' ? 'selected' : '' ?>>High (Important)</option>
                        <option value="urgent" <?= $v_priority == 'urgent' ? 'selected' : '' ?>>Urgent (Blocking)</option>
                    </select>
                </div>
            </div>

            <!-- Subject -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Subject <span class="text-rose-500">*</span></label>
                <input type="text" name="subject" value="<?= e($v_subject) ?>" required minlength="5" maxlength="255" 
                       class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-<?= $theme_color ?>-500 focus:border-transparent transition-shadow text-sm">
            </div>

            <!-- Description -->
            <div class="mb-8">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Detailed Description <span class="text-rose-500">*</span></label>
                <textarea name="description" rows="6" required minlength="20" 
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-<?= $theme_color ?>-500 focus:border-transparent transition-shadow text-sm resize-y"><?= e($v_description) ?></textarea>
                <p class="text-xs text-slate-500 mt-1.5">Minimum 20 characters required.</p>
            </div>

            <!-- Footer Actions -->
            <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
                <a href="/pfe/student/<?= $ticket['status'] === 'draft' ? 'drafts' : ($is_complaint ? 'reclamations' : 'demandes') ?>.php" 
                   class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold transition-colors text-center">
                    Cancel
                </a>
                <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-3">
                    <button type="button" id="btn-save" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                        <i class="bi bi-floppy"></i> Save <?= $ticket['status'] === 'draft' ? 'Draft' : 'Changes' ?>
                    </button>
                    <?php if ($ticket['status'] === 'draft'): ?>
                        <button type="button" id="btn-submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-<?= $theme_color ?>-600 hover:bg-<?= $theme_color ?>-700 text-white text-sm font-semibold shadow-sm shadow-<?= $theme_color ?>-200 transition-colors flex items-center justify-center gap-2">
                            <i class="bi bi-send"></i> Submit
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const categorySelect    = document.getElementById('category_id');
const subcategorySelect = document.getElementById('subcategory_id');
const preselectedSubId  = <?= $v_subcategory_id ?>;
const formAction        = document.getElementById('form-action');

categorySelect.addEventListener('change', function () {
    const cid = this.value;
    subcategorySelect.disabled = true;
    subcategorySelect.innerHTML = '<option value="">Loading...</option>';
    subcategorySelect.classList.add('bg-slate-50');

    if (!cid) {
        subcategorySelect.innerHTML = '<option value="">— Select Category First —</option>';
        return;
    }
    fetch(`/pfe/student/ajax_subcategories.php?category_id=${cid}`)
        .then(r => r.json())
        .then(subs => {
            if (subs.length === 0) {
                subcategorySelect.innerHTML = '<option value="">— No Subcategories —</option>';
            } else {
                let html = '<option value="">— Optional —</option>';
                subs.forEach(s => {
                    const sel = s.id == preselectedSubId ? ' selected' : '';
                    html += `<option value="${s.id}"${sel} class="font-normal text-slate-700 bg-white">${s.name}</option>`;
                });
                subcategorySelect.innerHTML = html;
                subcategorySelect.disabled = false;
                subcategorySelect.classList.remove('bg-slate-50');
                subcategorySelect.classList.add('bg-white');
            }
        })
        .catch(() => {
            subcategorySelect.innerHTML = '<option value="">Load Error</option>';
        });
});

if (categorySelect.value) categorySelect.dispatchEvent(new Event('change'));

document.getElementById('btn-save').addEventListener('click', () => {
    formAction.value = 'save';
    document.getElementById('edit-form').submit();
});

const submitBtn = document.getElementById('btn-submit');
if (submitBtn) {
    submitBtn.addEventListener('click', () => {
        formAction.value = 'submit';
        document.getElementById('edit-form').submit();
    });
}
</script>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
