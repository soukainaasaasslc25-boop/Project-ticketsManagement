<?php
// =============================================================================
// FILE    : student/edit_ticket.php
// PURPOSE : Edit form for tickets that are still editable (draft or new only).
//           Once admin opens/processes the ticket, editing is LOCKED.
// PERMISSION RULES:
//   - Student MUST own the ticket
//   - Status MUST be 'draft' OR 'new'
//   - 'opened', 'in_progress', 'completed', 'rejected' → ACCESS DENIED
// HOW TO TEST:
//   1. Log in → create a ticket → save as draft OR submit (status=new)
//   2. Navigate to /pfe/student/edit_ticket.php?id=X
//   3. Edit fields → click "Enregistrer" or "Soumettre"
//   4. Try editing a completed ticket → should see access denied message
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------------------
// Read & validate ticket ID from URL
// ---------------------------------------------------------------------------
$ticket_id = (int) ($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Ticket invalide.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// Load the ticket from the database
// ---------------------------------------------------------------------------
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
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// OWNERSHIP CHECK — must belong to logged-in student
// ---------------------------------------------------------------------------
if ((int) $ticket['user_id'] !== $student_id) {
    $_SESSION['flash_error'] = 'Accès refusé.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// EDIT PERMISSION CHECK — only draft or new are editable
// ---------------------------------------------------------------------------
$editable_statuses = ['draft'];
if (!in_array($ticket['status'], $editable_statuses, true)) {
    $_SESSION['flash_error'] = 'Ce ticket ne peut plus être modifié — l\'administration l\'a déjà pris en charge.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// Generate CSRF token
// ---------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ---------------------------------------------------------------------------
// Load categories for the dropdown
// ---------------------------------------------------------------------------
$stmt = $pdo->query('SELECT id, type, name FROM categories WHERE is_active = 1 ORDER BY type, name');
$all_cats = $stmt->fetchAll();
$cats_by_type = ['request' => [], 'complaint' => []];
foreach ($all_cats as $c) {
    $cats_by_type[$c['type']][] = $c;
}

// ---------------------------------------------------------------------------
// Pre-populate from form repopulation (after failed edit submission)
// OR use the current ticket values
// ---------------------------------------------------------------------------
$old = $_SESSION['form_repopulate'] ?? [];
unset($_SESSION['form_repopulate']);

$v_category_id    = (int)    ($old['category_id']    ?? $ticket['category_id']);
$v_subcategory_id = (int)    ($old['subcategory_id'] ?? $ticket['subcategory_id'] ?? 0);
$v_priority       =           $old['priority']       ?? $ticket['priority'];
$v_subject        =           $old['subject']        ?? $ticket['subject'];
$v_description    =           $old['description']    ?? $ticket['description'];

$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le ticket <?= e($ticket['reference']) ?> — Système de Tickets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

<div class="max-w-3xl mx-auto px-4 py-8">

    <!-- Back link -->
    <a href="/pfe/student/drafts.php"
       class="text-sm text-slate-500 hover:text-blue-600 transition flex items-center gap-1 mb-4">
        <i class="bi bi-arrow-left"></i> Retour
    </a>

    <!-- Page header -->
    <div class="mb-6">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-slate-800">Modifier le ticket</h1>
            <span class="font-mono text-sm font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg">
                <?= e($ticket['reference']) ?>
            </span>
        </div>
        <p class="text-slate-500 text-sm mt-1">
            Modifiez votre demande ci-dessous. Enregistrez comme brouillon ou soumettez directement.
        </p>
    </div>

    <!-- Error flash -->
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex items-start gap-3 text-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <!-- Form card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- Card header -->
        <div class="bg-gradient-to-r from-blue-900 to-blue-600 px-6 py-4">
            <h2 class="text-white font-semibold flex items-center gap-2">
                <i class="bi bi-pencil-fill"></i> Formulaire de modification
            </h2>
        </div>

        <!-- Form body -->
        <form id="edit-form"
              action="/pfe/student/process_edit_ticket.php"
              method="POST"
              class="px-6 py-6 space-y-6">

            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="ticket_id"  value="<?= (int)$ticket_id ?>">
            <input type="hidden" name="action"     id="form-action" value="save">

            <!-- Row 1: Category + Subcategory -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="category_id" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Catégorie <span class="text-red-500">*</span>
                    </label>
                    <select id="category_id" name="category_id" required
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white transition">
                        <option value="">— Choisir —</option>
                        <?php if (!empty($cats_by_type['request'])): ?>
                            <optgroup label="📋 Demandes">
                                <?php foreach ($cats_by_type['request'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $v_category_id ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($cats_by_type['complaint'])): ?>
                            <optgroup label="⚠️ Réclamations">
                                <?php foreach ($cats_by_type['complaint'] as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $v_category_id ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="subcategory_id" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Sous-catégorie <span class="text-slate-400 text-xs">(optionnel)</span>
                    </label>
                    <select id="subcategory_id" name="subcategory_id" disabled
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-slate-50 transition disabled:opacity-60">
                        <option value="">Chargement...</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Priority -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="priority" class="block text-sm font-medium text-slate-700 mb-1.5">
                        Priorité <span class="text-red-500">*</span>
                    </label>
                    <select id="priority" name="priority"
                            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white transition">
                        <option value="low"    <?= $v_priority === 'low'    ? 'selected' : '' ?>>🟢 Basse</option>
                        <option value="medium" <?= $v_priority === 'medium' ? 'selected' : '' ?>>🟡 Moyenne</option>
                        <option value="high"   <?= $v_priority === 'high'   ? 'selected' : '' ?>>🟠 Haute</option>
                        <option value="urgent" <?= $v_priority === 'urgent' ? 'selected' : '' ?>>🔴 Urgente</option>
                    </select>
                </div>
            </div>

            <!-- Row 3: Subject -->
            <div>
                <label for="subject" class="block text-sm font-medium text-slate-700 mb-1.5">
                    Objet <span class="text-red-500">*</span>
                </label>
                <input type="text" id="subject" name="subject" maxlength="255" required
                       value="<?= e($v_subject) ?>"
                       class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 placeholder-slate-300 transition">
            </div>

            <!-- Row 4: Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">
                    Description <span class="text-red-500">*</span>
                </label>
                <textarea id="description" name="description" rows="6" required
                          class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 placeholder-slate-300 transition resize-none"><?= e($v_description) ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Minimum 20 caractères pour soumettre.</p>
            </div>

            <!-- Footer buttons -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 gap-3 flex-wrap">
                <a href="/pfe/student/<?= $ticket['status'] === 'draft' ? 'drafts' : 'my_tickets' ?>.php"
                   class="text-sm text-slate-500 hover:text-slate-700 hover:underline transition">
                    Annuler
                </a>
                <div class="flex items-center gap-3">
                    <!-- Save (always as same status) -->
                    <button type="button" id="btn-save"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold
                                   border border-slate-200 text-slate-600 bg-white hover:bg-slate-50 transition">
                        <i class="bi bi-floppy"></i> Enregistrer
                    </button>
                    <!-- Save & Submit (set status to new) -->
                    <button type="button" id="btn-submit"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                                   bg-gradient-to-r from-blue-700 to-blue-500 text-white shadow-md shadow-blue-200 hover:from-blue-800 hover:to-blue-600 transition">
                        <i class="bi bi-send-fill"></i> Enregistrer & Soumettre
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JS: AJAX subcategories + button actions -->
<script>
const categorySelect    = document.getElementById('category_id');
const subcategorySelect = document.getElementById('subcategory_id');
const preselectedSubId  = <?= $v_subcategory_id ?>;
const formAction        = document.getElementById('form-action');

categorySelect.addEventListener('change', function () {
    const cid = this.value;
    subcategorySelect.disabled = true;
    subcategorySelect.innerHTML = '<option value="">Chargement...</option>';

    if (!cid) {
        subcategorySelect.innerHTML = '<option value="">— Choisir une catégorie d\'abord —</option>';
        return;
    }
    fetch(`/pfe/student/ajax_subcategories.php?category_id=${cid}`)
        .then(r => r.json())
        .then(subs => {
            if (subs.length === 0) {
                subcategorySelect.innerHTML = '<option value="">— Aucune sous-catégorie —</option>';
            } else {
                let html = '<option value="">— Optionnel —</option>';
                subs.forEach(s => {
                    const sel = s.id == preselectedSubId ? ' selected' : '';
                    html += `<option value="${s.id}"${sel}>${s.name}</option>`;
                });
                subcategorySelect.innerHTML = html;
                subcategorySelect.disabled = false;
            }
        })
        .catch(() => {
            subcategorySelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
});

// Trigger on load (to preload subcategories for current category)
if (categorySelect.value) categorySelect.dispatchEvent(new Event('change'));

// Save button → keep current status
document.getElementById('btn-save').addEventListener('click', () => {
    formAction.value = 'save';
    document.getElementById('edit-form').submit();
});

// Submit button → promote to 'new' if draft
document.getElementById('btn-submit').addEventListener('click', () => {
    formAction.value = 'submit';
    document.getElementById('edit-form').submit();
});
</script>
</body>
</html>
