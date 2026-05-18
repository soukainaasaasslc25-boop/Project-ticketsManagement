<?php
// =============================================================================
// FILE    : student/create_ticket.php
// PURPOSE : Ticket creation form for students.
//           - Loads categories from DB grouped by type (request / complaint)
//           - Subcategories load dynamically via AJAX on category change
//           - Supports "Save as Draft" and "Submit" actions
//           - CSRF token protects the form
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student(); // redirect if not a logged-in student

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ---------------------------------------------------------------------------
// Generate CSRF token (stored in session, checked in process_create_ticket.php)
// ---------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------------------------------------------------------------------------
// Load ALL active categories from the database
// We group them by type so we can show "Request" and "Complaint" optgroups
// ---------------------------------------------------------------------------
$stmt = $pdo->query('
    SELECT id, type, name, description
    FROM categories
    WHERE is_active = 1
    ORDER BY type ASC, name ASC
');
$all_categories = $stmt->fetchAll();

// Separate into two groups for the <optgroup>
$categories_by_type = ['request' => [], 'complaint' => []];
foreach ($all_categories as $cat) {
    $categories_by_type[$cat['type']][] = $cat;
}

// ---------------------------------------------------------------------------
// Flash messages (set by process_create_ticket.php on redirect)
// ---------------------------------------------------------------------------
$flash_error   = $_SESSION['flash_error']   ?? null;
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ---------------------------------------------------------------------------
// Repopulate form values after a failed submission
// ---------------------------------------------------------------------------
$old = $_SESSION['form_repopulate'] ?? [];
unset($_SESSION['form_repopulate']);

$old_category_id    = (int) ($old['category_id']    ?? 0);
$old_subcategory_id = (int) ($old['subcategory_id'] ?? 0);
$old_priority       = $old['priority']    ?? 'medium';
$old_subject        = $old['subject']     ?? '';
$old_description    = $old['description'] ?? '';

// Page meta for <title>
$page_title = 'Nouvelle demande';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Système de Tickets</title>

    <!-- Tailwind CSS (Play CDN — development only) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        // Extend Tailwind with custom font
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        /* File drop zone hover ring */
        #drop-zone.drag-over { border-color: #3b82f6; background-color: #eff6ff; }
    </style>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- =========================================================================
     TOP NAVIGATION
     ========================================================================= -->
<nav class="bg-gradient-to-r from-blue-900 to-blue-600 shadow-lg sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <!-- Brand -->
        <a href="/pfe/student/dashboard.php"
           class="flex items-center gap-2 text-white font-bold text-base">
            <i class="bi bi-ticket-perforated-fill text-blue-300"></i>
            TicketSystem
        </a>

        <!-- Nav links -->
        <div class="flex items-center gap-1 text-sm">
            <a href="/pfe/student/dashboard.php"
               class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition">
                <i class="bi bi-grid me-1"></i>Tableau de bord
            </a>
            <a href="/pfe/student/my_tickets.php"
               class="text-blue-200 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition">
                <i class="bi bi-ticket-detailed me-1"></i>Mes tickets
            </a>
            <a href="/pfe/student/create_ticket.php"
               class="text-white bg-white/20 px-3 py-1.5 rounded-lg font-semibold">
                <i class="bi bi-plus-circle me-1"></i>Nouvelle demande
            </a>
            <a href="/pfe/auth/logout.php"
               class="text-red-300 hover:text-red-100 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2">
                <i class="bi bi-box-arrow-left me-1"></i>Déconnexion
            </a>
        </div>
    </div>
</nav>

<!-- =========================================================================
     PAGE WRAPPER
     ========================================================================= -->
<div class="max-w-3xl mx-auto px-4 py-8">

    <!-- Page header -->
    <div class="mb-6">
        <a href="/pfe/student/dashboard.php"
           class="text-sm text-slate-500 hover:text-blue-600 transition flex items-center gap-1 mb-2">
            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Nouvelle demande</h1>
        <p class="text-slate-500 text-sm mt-1">
            Remplissez le formulaire ci-dessous pour soumettre une demande ou une réclamation à l'administration.
        </p>
    </div>

    <!-- -----------------------------------------------------------------------
         FLASH MESSAGES
         ----------------------------------------------------------------------- -->
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3">
            <i class="bi bi-exclamation-circle-fill text-red-500 mt-0.5 text-lg flex-shrink-0"></i>
            <div class="text-sm"><?= $flash_error /* already escaped or is a hardcoded string */ ?></div>
        </div>
    <?php endif; ?>

    <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3">
            <i class="bi bi-check-circle-fill text-green-500 mt-0.5 text-lg flex-shrink-0"></i>
            <div class="text-sm"><?= $flash_success ?></div>
        </div>
    <?php endif; ?>

    <!-- -----------------------------------------------------------------------
         FORM CARD
         ----------------------------------------------------------------------- -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- Card header -->
        <div class="bg-gradient-to-r from-blue-900 to-blue-600 px-6 py-4">
            <h2 class="text-white font-semibold text-base flex items-center gap-2">
                <i class="bi bi-send-fill"></i>
                Formulaire de demande
            </h2>
            <p class="text-blue-200 text-xs mt-0.5">
                Les champs marqués <span class="text-red-300 font-bold">*</span> sont obligatoires pour la soumission.
            </p>
        </div>

        <!-- Form body -->
        <form id="ticket-form"
              action="/pfe/student/process_create_ticket.php"
              method="POST"
              enctype="multipart/form-data"
              novalidate
              class="px-6 py-6 space-y-6">

            <!-- CSRF hidden token — prevents cross-site request forgery -->
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <!-- Hidden "action" field — set to 'draft' or 'submit' by the buttons -->
            <input type="hidden" name="action" id="form-action" value="submit">

            <!-- =================================================================
                 ROW 1: Category + Subcategory
                 ================================================================= -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Catégorie <span class="text-red-500">*</span>
                    </label>
                    <select id="category_id" name="category_id"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent
                                   bg-white transition"
                            required>
                        <option value="">— Choisir une catégorie —</option>

                        <?php if (!empty($categories_by_type['request'])): ?>
                            <optgroup label="📋 Demandes">
                                <?php foreach ($categories_by_type['request'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"
                                        <?= $old_category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>

                        <?php if (!empty($categories_by_type['complaint'])): ?>
                            <optgroup label="⚠️ Réclamations">
                                <?php foreach ($categories_by_type['complaint'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>"
                                        <?= $old_category_id === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Sélectionnez d'abord la catégorie pour charger les sous-catégories.</p>
                </div>

                <!-- Subcategory — populated dynamically via JS fetch -->
                <div>
                    <label for="subcategory_id" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Sous-catégorie <span class="text-slate-400 text-xs font-normal">(optionnel)</span>
                    </label>
                    <div class="relative">
                        <select id="subcategory_id" name="subcategory_id"
                                disabled
                                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800
                                       focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent
                                       bg-slate-50 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <option value="">— Sélectionnez une catégorie d'abord —</option>
                        </select>
                        <!-- Loading spinner shown during AJAX fetch -->
                        <span id="sub-spinner"
                              class="hidden absolute right-3 top-3 text-blue-500 text-xs animate-spin">
                            <i class="bi bi-arrow-repeat"></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- =================================================================
                 ROW 2: Priority
                 ================================================================= -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="priority" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Priorité <span class="text-red-500">*</span>
                    </label>
                    <select id="priority" name="priority"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent
                                   bg-white transition">
                        <option value="low"    <?= $old_priority === 'low'    ? 'selected' : '' ?>>🟢 Basse</option>
                        <option value="medium" <?= $old_priority === 'medium' ? 'selected' : '' ?>>🟡 Moyenne</option>
                        <option value="high"   <?= $old_priority === 'high'   ? 'selected' : '' ?>>🟠 Haute</option>
                        <option value="urgent" <?= $old_priority === 'urgent' ? 'selected' : '' ?>>🔴 Urgente</option>
                    </select>
                </div>

                <!-- Info box explaining priority levels -->
                <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-xs text-blue-700 self-end">
                    <p class="font-semibold mb-1">ℹ️ Guide de priorité</p>
                    <ul class="space-y-0.5 text-blue-600">
                        <li>🟢 <strong>Basse</strong> — Pas urgent, délai flexible</li>
                        <li>🟡 <strong>Moyenne</strong> — Standard (recommandé)</li>
                        <li>🟠 <strong>Haute</strong> — Important, besoin rapide</li>
                        <li>🔴 <strong>Urgente</strong> — Bloque vos études</li>
                    </ul>
                </div>
            </div>

            <!-- =================================================================
                 ROW 3: Subject
                 ================================================================= -->
            <div>
                <label for="subject" class="block text-sm font-medium text-slate-700 mb-1.5">
                    Objet <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="subject"
                       name="subject"
                       maxlength="255"
                       placeholder="Ex : Demande d'attestation de réussite pour dossier de stage"
                       value="<?= e($old_subject) ?>"
                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800
                              focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent
                              placeholder-slate-300 transition"
                       required>
                <p class="text-xs text-slate-400 mt-1 flex justify-between">
                    <span>Résumez votre demande en une phrase claire (5 caractères min.).</span>
                    <span id="subject-count" class="tabular-nums">0 / 255</span>
                </p>
            </div>

            <!-- =================================================================
                 ROW 4: Description
                 ================================================================= -->
            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">
                    Description détaillée <span class="text-red-500">*</span>
                </label>
                <textarea id="description"
                          name="description"
                          rows="6"
                          placeholder="Décrivez votre demande en détail : contexte, ce que vous avez déjà essayé, ce que vous attendez de l'administration..."
                          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800
                                 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent
                                 placeholder-slate-300 transition resize-none"
                          required><?= e($old_description) ?></textarea>
                <p class="text-xs text-slate-400 mt-1">
                    Minimum 20 caractères pour la soumission.
                </p>
            </div>

            <!-- =================================================================
                 ROW 5: File Upload (drag & drop zone)
                 ================================================================= -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Pièces jointes <span class="text-slate-400 text-xs font-normal">(max. 3 fichiers · 5 Mo chacun)</span>
                </label>

                <!-- Drop zone -->
                <div id="drop-zone"
                     class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center
                            cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all duration-200">
                    <i class="bi bi-cloud-arrow-up text-4xl text-slate-300"></i>
                    <p class="text-slate-500 text-sm mt-2 font-medium">
                        Glissez vos fichiers ici ou
                        <span class="text-blue-500 underline cursor-pointer" onclick="document.getElementById('attachments').click()">
                            parcourez
                        </span>
                    </p>
                    <p class="text-slate-400 text-xs mt-1">
                        Formats acceptés : PDF, JPG, PNG, DOC, DOCX
                    </p>

                    <!-- Actual hidden file input -->
                    <input type="file"
                           id="attachments"
                           name="attachments[]"
                           multiple
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                           class="hidden">
                </div>

                <!-- Preview list — files appear here after selection -->
                <ul id="file-preview" class="mt-3 space-y-2"></ul>
            </div>

            <!-- =================================================================
                 FORM FOOTER: action buttons
                 ================================================================= -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 gap-3 flex-wrap">

                <!-- Left: cancel -->
                <a href="/pfe/student/dashboard.php"
                   class="text-sm text-slate-500 hover:text-slate-700 hover:underline transition">
                    Annuler
                </a>

                <!-- Right: draft + submit -->
                <div class="flex items-center gap-3">
                    <!-- Save as draft — sets hidden action to 'draft' -->
                    <button type="button"
                            id="btn-draft"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold
                                   border border-slate-200 text-slate-600 bg-white
                                   hover:bg-slate-50 hover:border-slate-300 transition">
                        <i class="bi bi-floppy"></i>
                        Enregistrer brouillon
                    </button>

                    <!-- Submit — sets hidden action to 'submit' -->
                    <button type="submit"
                            id="btn-submit"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                                   bg-gradient-to-r from-blue-700 to-blue-500 text-white
                                   hover:from-blue-800 hover:to-blue-600 shadow-md shadow-blue-200
                                   hover:shadow-lg transition">
                        <i class="bi bi-send-fill"></i>
                        Soumettre la demande
                    </button>
                </div>
            </div>

        </form>
    </div><!-- /card -->

    <!-- Help note -->
    <p class="text-center text-xs text-slate-400 mt-6">
        Besoin d'aide ? Consultez le <a href="#" class="underline text-blue-400">guide d'utilisation</a>.
    </p>

</div><!-- /page-wrapper -->

<!-- =========================================================================
     JAVASCRIPT
     ========================================================================= -->
<script>
// ---------------------------------------------------------------------------
// 1. Dynamic subcategory loading via fetch() when category changes
// ---------------------------------------------------------------------------
const categorySelect    = document.getElementById('category_id');
const subcategorySelect = document.getElementById('subcategory_id');
const subSpinner        = document.getElementById('sub-spinner');

// Pre-selected value to restore after AJAX load (from PHP repopulate)
const preselectedSubId = <?= $old_subcategory_id ?>;

categorySelect.addEventListener('change', function () {
    const categoryId = this.value;

    // Reset subcategory dropdown while loading
    subcategorySelect.disabled = true;
    subcategorySelect.innerHTML = '<option value="">Chargement...</option>';
    subSpinner.classList.remove('hidden');

    if (!categoryId) {
        subcategorySelect.innerHTML = '<option value="">— Sélectionnez une catégorie d\'abord —</option>';
        subSpinner.classList.add('hidden');
        return;
    }

    // Fetch subcategories for the selected category
    fetch(`/pfe/student/ajax_subcategories.php?category_id=${categoryId}`)
        .then(response => response.json())
        .then(subcategories => {
            subSpinner.classList.add('hidden');

            if (subcategories.length === 0) {
                subcategorySelect.innerHTML = '<option value="">— Aucune sous-catégorie disponible —</option>';
            } else {
                let html = '<option value="">— Choisir (optionnel) —</option>';
                subcategories.forEach(sub => {
                    html += `<option value="${sub.id}">${sub.name}</option>`;
                });
                subcategorySelect.innerHTML = html;
                subcategorySelect.disabled = false;
            }
        })
        .catch(() => {
            subSpinner.classList.add('hidden');
            subcategorySelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
});

// Auto-trigger if a category was preselected (form repopulation after error)
if (categorySelect.value) {
    categorySelect.dispatchEvent(new Event('change'));
}

// ---------------------------------------------------------------------------
// 2. Character counter for subject field
// ---------------------------------------------------------------------------
const subjectInput  = document.getElementById('subject');
const subjectCount  = document.getElementById('subject-count');

function updateSubjectCount() {
    subjectCount.textContent = subjectInput.value.length + ' / 255';
}
subjectInput.addEventListener('input', updateSubjectCount);
updateSubjectCount(); // init on load

// ---------------------------------------------------------------------------
// 3. File upload: drag & drop + preview
// ---------------------------------------------------------------------------
const dropZone      = document.getElementById('drop-zone');
const fileInput     = document.getElementById('attachments');
const filePreview   = document.getElementById('file-preview');
const MAX_FILES     = 3;
const MAX_SIZE_MB   = 5;
let selectedFiles   = [];

// Format bytes to human-readable string
function formatSize(bytes) {
    if (bytes < 1024)       return bytes + ' o';
    if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1048576).toFixed(1) + ' Mo';
}

// File icon based on extension
function fileIcon(name) {
    const ext = name.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png'].includes(ext)) return 'bi-file-image';
    if (ext === 'pdf')                       return 'bi-file-pdf';
    if (['doc','docx'].includes(ext))        return 'bi-file-word';
    return 'bi-file-earmark';
}

// Render selected files as a preview list
function renderPreview() {
    filePreview.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-sm';
        li.innerHTML = `
            <div class="flex items-center gap-2 text-slate-700 truncate">
                <i class="bi ${fileIcon(file.name)} text-blue-400 text-base"></i>
                <span class="truncate max-w-xs">${file.name}</span>
                <span class="text-slate-400 text-xs whitespace-nowrap">${formatSize(file.size)}</span>
            </div>
            <button type="button" onclick="removeFile(${index})"
                    class="text-slate-400 hover:text-red-500 transition ml-2 flex-shrink-0">
                <i class="bi bi-x-circle"></i>
            </button>
        `;
        filePreview.appendChild(li);
    });

    // Sync file input with selectedFiles using DataTransfer
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
}

// Add files (enforce max 3 and 5 MB)
function addFiles(newFiles) {
    for (const file of newFiles) {
        if (selectedFiles.length >= MAX_FILES) {
            alert('Maximum 3 fichiers autorisés.');
            break;
        }
        if (file.size > MAX_SIZE_MB * 1024 * 1024) {
            alert(`Le fichier "${file.name}" dépasse 5 Mo.`);
            continue;
        }
        selectedFiles.push(file);
    }
    renderPreview();
}

// Remove a file from selection by index
function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderPreview();
}

// File input change event
fileInput.addEventListener('change', function () {
    addFiles(Array.from(this.files));
    this.value = ''; // reset so same file can be re-added
});

// Drag and drop events
dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('drag-over');
});
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    addFiles(Array.from(e.dataTransfer.files));
});

// ---------------------------------------------------------------------------
// 4. Draft / Submit button logic
// ---------------------------------------------------------------------------
const formAction = document.getElementById('form-action');
const btnDraft   = document.getElementById('btn-draft');
const btnSubmit  = document.getElementById('btn-submit');

// "Save as Draft" — skip HTML5 required validation
btnDraft.addEventListener('click', function () {
    formAction.value = 'draft';
    document.getElementById('ticket-form').noValidate = true;
    document.getElementById('ticket-form').submit();
});

// "Submit" — run HTML5 validation first
btnSubmit.addEventListener('click', function () {
    formAction.value = 'submit';
});

// Loading state on form submit
document.getElementById('ticket-form').addEventListener('submit', function () {
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Envoi...';
});
</script>

</body>
</html>
