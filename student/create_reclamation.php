<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Load only subcategories of type 'complaint'
$stmt = $pdo->query("
    SELECT s.id, s.name as sub_name, c.name as cat_name
    FROM subcategories s
    JOIN categories c ON c.id = s.category_id
    WHERE c.type = 'complaint' AND c.is_active = 1 AND s.is_active = 1
    ORDER BY c.name ASC, s.name ASC
");
$subs = $stmt->fetchAll();

$grouped_subs = [];
foreach ($subs as $row) {
    $grouped_subs[$row['cat_name']][] = $row;
}

$flash_error   = $_SESSION['flash_error']   ?? null;
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$old = $_SESSION['form_repopulate'] ?? [];
unset($_SESSION['form_repopulate']);

$old_subcategory_id = (int) ($old['subcategory_id'] ?? 0);
$old_priority       = $old['priority']    ?? 'medium';
$old_subject        = $old['subject']     ?? '';
$old_description    = $old['description'] ?? '';
$page_title = 'New Complaint';
$current_page = 'reclamations.php'; // to highlight sidebar
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
                    colors: { brand: { 50: '#fff1f2', 500: '#f43f5e', 600: '#e11d48' } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6">
    <a href="/pfe/student/reclamations.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-rose-600 mb-2 transition-colors">
        <i class="bi bi-arrow-left me-1.5"></i> Back to Complaints
    </a>
    <h1 class="text-2xl font-bold text-slate-900">File a New Complaint</h1>
    <p class="text-slate-500 text-sm mt-1">Fill out the form below to report an issue or submit a formal complaint.</p>
</div>

<?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_error ?></div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-4xl">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
        <div class="flex items-center gap-2">
            <i class="bi bi-exclamation-octagon text-rose-500 text-lg"></i>
            <h3 class="font-bold text-slate-800">Complaint Form</h3>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">Complaint</span>
    </div>

    <div class="p-6 sm:p-8">
        <form id="ticket-form" action="/pfe/student/process_create_ticket.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="action" id="form-action" value="submit">
            <input type="hidden" name="form_type" value="complaint">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Subcategory -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Category <span class="text-rose-500">*</span></label>
                    <select name="subcategory_id" required class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-shadow text-sm bg-white appearance-none">
                        <option value="">— Select the nature of your complaint —</option>
                        <?php foreach ($grouped_subs as $cat_name => $cat_subs): ?>
                            <optgroup label="<?= e($cat_name) ?>" class="font-semibold text-slate-900 bg-slate-50">
                                <?php foreach ($cat_subs as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" <?= $old_subcategory_id == $sub['id'] ? 'selected' : '' ?> class="font-normal text-slate-700 bg-white">
                                        <?= e($sub['sub_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-1.5">Choose the category that best matches your issue.</p>
                </div>
                
                <!-- Priority -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Priority <span class="text-rose-500">*</span></label>
                    <select name="priority" class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-shadow text-sm bg-white appearance-none">
                        <option value="low" <?= $old_priority == 'low' ? 'selected' : '' ?>>Low (Non-urgent)</option>
                        <option value="medium" <?= $old_priority == 'medium' ? 'selected' : '' ?>>Medium (Standard)</option>
                        <option value="high" <?= $old_priority == 'high' ? 'selected' : '' ?>>High (Important)</option>
                        <option value="urgent" <?= $old_priority == 'urgent' ? 'selected' : '' ?>>Urgent (Blocking)</option>
                    </select>
                </div>
            </div>

            <!-- Subject -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Subject <span class="text-rose-500">*</span></label>
                <input type="text" name="subject" value="<?= e($old_subject) ?>" required minlength="5" maxlength="255" placeholder="e.g., Issue with exam grading" 
                       class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-shadow text-sm">
            </div>

            <!-- Description -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Detailed Description <span class="text-rose-500">*</span></label>
                <textarea name="description" rows="5" required minlength="20" placeholder="Describe the issue you encountered..." 
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition-shadow text-sm resize-y"><?= e($old_description) ?></textarea>
                <p class="text-xs text-slate-500 mt-1.5">Please provide as much context as possible to help us resolve this. Minimum 20 characters.</p>
            </div>

            <!-- Attachments -->
            <div class="mb-8">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Attachments <span class="text-slate-400 font-normal">(Optional)</span></label>
                
                <div id="drop-zone" onclick="document.getElementById('attachments').click()" 
                     class="border-2 border-dashed border-slate-200 rounded-xl p-8 text-center cursor-pointer hover:border-rose-400 hover:bg-rose-50/50 transition-colors group">
                    <div class="w-12 h-12 rounded-full bg-slate-50 group-hover:bg-rose-100 flex items-center justify-center mx-auto mb-3 transition-colors">
                        <i class="bi bi-cloud-arrow-up text-2xl text-slate-400 group-hover:text-rose-600 transition-colors"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-700 mb-1">Click or drag & drop files here</p>
                    <p class="text-xs text-slate-500">Supported formats: PDF, JPG, PNG, DOC, DOCX (Max 3 files, 5MB each)</p>
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="hidden">
                </div>
                
                <ul id="file-preview" class="mt-4 space-y-2 empty:hidden"></ul>
            </div>

            <!-- Footer Actions -->
            <div class="pt-6 border-t border-slate-100 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
                <a href="/pfe/student/reclamations.php" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-semibold transition-colors text-center">
                    Cancel
                </a>
                <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-3">
                    <button type="button" id="btn-draft" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                        <i class="bi bi-floppy"></i> Save Draft
                    </button>
                    <button type="submit" id="btn-submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm shadow-rose-200 transition-colors flex items-center justify-center gap-2">
                        <i class="bi bi-send"></i> Submit Complaint
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// File upload logic
const fileInput = document.getElementById('attachments');
const dropZone = document.getElementById('drop-zone');
const preview = document.getElementById('file-preview');
let selectedFiles = [];

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function renderPreview() {
    preview.innerHTML = '';
    selectedFiles.forEach((f, i) => {
        preview.innerHTML += `
            <li class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50/50">
                <div class="flex items-center gap-3 min-w-0">
                    <i class="bi bi-file-earmark-text text-rose-500 text-lg shrink-0"></i>
                    <div class="truncate">
                        <p class="text-sm font-semibold text-slate-700 truncate">${f.name}</p>
                        <p class="text-xs text-slate-400">${formatSize(f.size)}</p>
                    </div>
                </div>
                <button type="button" onclick="event.stopPropagation(); removeFile(${i})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors shrink-0 ml-2">
                    <i class="bi bi-x-lg"></i>
                </button>
            </li>`;
    });
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderPreview();
}

fileInput.addEventListener('change', function() {
    for (let f of this.files) {
        if (selectedFiles.length >= 3) { alert('Maximum 3 files allowed.'); break; }
        if (f.size > 5 * 1024 * 1024) { alert(`File too large: ${f.name}`); continue; }
        selectedFiles.push(f);
    }
    this.value = '';
    renderPreview();
});

dropZone.addEventListener('dragover', e => { 
    e.preventDefault(); 
    dropZone.classList.add('border-rose-400', 'bg-rose-50/50'); 
});
dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-rose-400', 'bg-rose-50/50');
});
dropZone.addEventListener('drop', e => {
    e.preventDefault(); 
    dropZone.classList.remove('border-rose-400', 'bg-rose-50/50');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

// Draft vs Submit logic
document.getElementById('btn-draft').addEventListener('click', () => {
    document.getElementById('form-action').value = 'draft';
    document.getElementById('ticket-form').noValidate = true;
    document.getElementById('ticket-form').submit();
});
document.getElementById('btn-submit').addEventListener('click', () => {
    document.getElementById('form-action').value = 'submit';
});
</script>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
