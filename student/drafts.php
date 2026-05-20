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
        t.id, t.reference, t.subject, t.priority,
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
    'low'    => ['Basse', 'bg-light text-secondary border'],
    'medium' => ['Moyenne', 'bg-warning text-dark border-warning'],
    'high'   => ['Haute', 'bg-orange text-white'],
    'urgent' => ['Urgente', 'bg-danger text-white'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Brouillons — Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .bg-orange { background-color: #fd7e14; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1">Mes Brouillons</h3>
            <p class="text-muted mb-0"><?= count($drafts) ?> brouillon(s) enregistré(s)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/pfe/student/create_demande.php" class="btn btn-primary shadow-sm" style="border-radius: 10px;">
                <i class="bi bi-plus-circle"></i> Demande
            </a>
            <a href="/pfe/student/create_reclamation.php" class="btn btn-danger shadow-sm" style="border-radius: 10px;">
                <i class="bi bi-exclamation-circle"></i> Réclamation
            </a>
        </div>
    </div>

    <?php if ($flash_success): ?>
        <div class="alert alert-success d-flex align-items-center gap-3" style="border-radius:12px;"><i class="bi bi-check-circle-fill fs-5"></i><div><?= $flash_success ?></div></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3" style="border-radius:12px;"><i class="bi bi-exclamation-triangle-fill fs-5"></i><div><?= $flash_error ?></div></div>
    <?php endif; ?>

    <div class="alert alert-info d-flex align-items-center gap-3 border-info mb-4" style="border-radius: 12px; background: #eff6ff;">
        <i class="bi bi-info-circle-fill fs-4 text-info"></i>
        <div>
            <span class="text-dark">Les brouillons sont <strong>visibles uniquement par vous</strong>. Soumettez-les pour que l'administration puisse les traiter.</span>
        </div>
    </div>

    <?php if (empty($drafts)): ?>
        <div class="card-custom text-center py-5">
            <i class="bi bi-pencil-square text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
            <h5 class="mt-3 fw-bold text-dark">Aucun brouillon</h5>
            <p class="text-muted mb-4">Vous n'avez pas de brouillons en attente.</p>
        </div>
    <?php else: ?>
        <div class="card-custom">
            <div class="list-group list-group-flush" style="border-radius: 12px;">
                <?php foreach ($drafts as $d): ?>
                    <?php [$pri_label, $pri_class] = $priority_labels[$d['priority']] ?? ['Moyenne', 'bg-warning text-dark border-warning']; ?>
                    <div class="list-group-item p-4 hover-bg-light transition">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="font-monospace fw-bold text-primary" style="font-size:0.85rem;"><?= e($d['reference']) ?></span>
                                    <span class="badge bg-secondary rounded-pill">Brouillon</span>
                                    <span class="badge <?= $pri_class ?> rounded-pill px-2"><?= $pri_label ?></span>
                                </div>
                                <h6 class="fw-bold text-dark mb-2"><?= e($d['subject']) ?></h6>
                                <div class="text-muted small d-flex flex-wrap gap-3">
                                    <span><i class="bi bi-tag text-secondary"></i> <?= e($d['category_name']) ?><?= $d['subcategory_name'] ? ' · ' . e($d['subcategory_name']) : '' ?></span>
                                    <span><i class="bi bi-calendar3 text-secondary"></i> Créé le <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></span>
                                    <span><i class="bi bi-arrow-clockwise text-secondary"></i> Modifié <?= date('d/m/Y H:i', strtotime($d['updated_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="/pfe/student/edit_ticket.php?id=<?= (int)$d['id'] ?>" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;">
                                    <i class="bi bi-pencil"></i> Modifier
                                </a>
                                
                                <form method="POST" action="/pfe/student/submit_draft.php" onsubmit="return confirm('Soumettre ce brouillon à l\'administration ?')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success shadow-sm" style="border-radius: 8px;">
                                        <i class="bi bi-send"></i> Soumettre
                                    </button>
                                </form>
                                
                                <form method="POST" action="/pfe/student/delete_draft.php" onsubmit="return confirm('Supprimer définitivement ce brouillon ?')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
