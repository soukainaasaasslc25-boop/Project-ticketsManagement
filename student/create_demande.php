<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Load only subcategories of type 'request'
$stmt = $pdo->query("
    SELECT s.id, s.name as sub_name, c.name as cat_name
    FROM subcategories s
    JOIN categories c ON c.id = s.category_id
    WHERE c.type = 'request' AND c.is_active = 1 AND s.is_active = 1
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
$page_title = 'Nouvelle Demande';
$current_page = 'demandes.php'; // to highlight sidebar
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Système de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc; }
        .drop-zone:hover, .drop-zone.drag-over { border-color: #3b82f6; background: #eff6ff; }
        .drop-zone i { font-size: 2.5rem; color: #94a3b8; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    
    <div class="mb-4">
        <a href="/pfe/student/demandes.php" class="text-decoration-none text-muted mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Retour aux demandes</a>
        <h3 class="fw-bold text-dark mb-1">Créer une Demande</h3>
        <p class="text-muted mb-0">Remplissez le formulaire ci-dessous pour soumettre une nouvelle demande.</p>
    </div>

    <?php if ($flash_error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3" style="border-radius:12px;"><i class="bi bi-exclamation-triangle-fill fs-5"></i><div><?= $flash_error ?></div></div>
    <?php endif; ?>

    <div class="card-custom mb-5">
        <div class="card-header-custom bg-light">
            <span class="d-flex align-items-center gap-2"><i class="bi bi-send-fill text-primary"></i> Formulaire de demande</span>
            <span class="badge bg-primary">Demande</span>
        </div>
        <div class="card-body p-4">
            <form id="ticket-form" action="/pfe/student/process_create_ticket.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" id="form-action" value="submit">
                <input type="hidden" name="form_type" value="request">

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sous-catégorie <span class="text-danger">*</span></label>
                        <select name="subcategory_id" class="form-select" required style="border-radius:10px;">
                            <option value="">— Sélectionnez la nature de la demande —</option>
                            <?php foreach ($grouped_subs as $cat_name => $cat_subs): ?>
                                <optgroup label="<?= e($cat_name) ?>">
                                    <?php foreach ($cat_subs as $sub): ?>
                                        <option value="<?= $sub['id'] ?>" <?= $old_subcategory_id == $sub['id'] ? 'selected' : '' ?>>
                                            <?= e($sub['sub_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choisissez la sous-catégorie qui correspond le mieux à votre demande.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Priorité <span class="text-danger">*</span></label>
                        <select name="priority" class="form-select" style="border-radius:10px;">
                            <option value="low" <?= $old_priority == 'low' ? 'selected' : '' ?>>Basse (Non urgent)</option>
                            <option value="medium" <?= $old_priority == 'medium' ? 'selected' : '' ?>>Moyenne (Standard)</option>
                            <option value="high" <?= $old_priority == 'high' ? 'selected' : '' ?>>Haute (Important)</option>
                            <option value="urgent" <?= $old_priority == 'urgent' ? 'selected' : '' ?>>Urgente (Bloquant)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Objet de la demande <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" style="border-radius:10px;" placeholder="Ex: Demande d'attestation de scolarité" value="<?= e($old_subject) ?>" required minlength="5" maxlength="255">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Description détaillée <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" style="border-radius:10px;" rows="5" placeholder="Décrivez votre besoin..." required minlength="20"><?= e($old_description) ?></textarea>
                    <div class="form-text">Veuillez fournir le maximum de détails. Minimum 20 caractères.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Pièces jointes <span class="text-muted fw-normal">(Optionnel, max 3 fichiers, 5 Mo max par fichier)</span></label>
                    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('attachments').click()">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <p class="mt-2 mb-1 fw-medium text-dark">Cliquez ou glissez-déposez vos fichiers ici</p>
                        <p class="small text-muted mb-0">Formats supportés : PDF, JPG, PNG, DOC, DOCX</p>
                        <input type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="d-none">
                    </div>
                    <ul id="file-preview" class="list-group mt-3 border-0"></ul>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="/pfe/student/demandes.php" class="btn btn-light border" style="border-radius:10px;">Annuler</a>
                    <div class="d-flex gap-2">
                        <button type="button" id="btn-draft" class="btn btn-outline-secondary" style="border-radius:10px;"><i class="bi bi-floppy"></i> Enregistrer brouillon</button>
                        <button type="submit" id="btn-submit" class="btn btn-primary shadow-sm" style="border-radius:10px;"><i class="bi bi-send"></i> Soumettre</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// File upload logic
const fileInput = document.getElementById('attachments');
const dropZone = document.getElementById('drop-zone');
const preview = document.getElementById('file-preview');
let selectedFiles = [];

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / 1048576).toFixed(1) + ' Mo';
}

function renderPreview() {
    preview.innerHTML = '';
    selectedFiles.forEach((f, i) => {
        preview.innerHTML += `
            <li class="list-group-item d-flex justify-content-between align-items-center bg-light border mb-2 rounded">
                <div><i class="bi bi-file-earmark-text text-primary me-2"></i> <strong>${f.name}</strong> <span class="text-muted small ms-2">${formatSize(f.size)}</span></div>
                <button type="button" class="btn-close" onclick="event.stopPropagation(); removeFile(${i})"></button>
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
        if (selectedFiles.length >= 3) { alert('Maximum 3 fichiers.'); break; }
        if (f.size > 5 * 1024 * 1024) { alert(`Fichier trop volumineux: ${f.name}`); continue; }
        selectedFiles.push(f);
    }
    this.value = '';
    renderPreview();
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
});

// Draft logic
document.getElementById('btn-draft').addEventListener('click', () => {
    document.getElementById('form-action').value = 'draft';
    document.getElementById('ticket-form').noValidate = true;
    document.getElementById('ticket-form').submit();
});
document.getElementById('btn-submit').addEventListener('click', () => {
    document.getElementById('form-action').value = 'submit';
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
