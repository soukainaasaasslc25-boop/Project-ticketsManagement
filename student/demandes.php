<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query for demandes
$sql = "FROM tickets t JOIN categories c ON c.id = t.category_id WHERE t.user_id = :user_id AND t.type = 'request' AND t.status != 'draft'";
$params = [':user_id' => $student_id];

if ($status_filter) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status_filter;
}
if ($search) {
    $sql .= " AND (t.reference LIKE :search OR t.subject LIKE :search OR c.name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count total
$stmt = $pdo->prepare("SELECT COUNT(*) " . $sql);
$stmt->execute($params);
$total_tickets = $stmt->fetchColumn();
$total_pages = ceil($total_tickets / $limit);

// Fetch data
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name " . $sql . " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Demandes — Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1">Mes Demandes</h3>
            <p class="text-muted mb-0">Suivez l'état de vos demandes administratives et techniques.</p>
        </div>
        <a href="/pfe/student/create_demande.php" class="btn btn-primary shadow-sm" style="border-radius: 10px;">
            <i class="bi bi-plus-circle"></i> Nouvelle demande
        </a>
    </div>

    <div class="card-custom mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Rechercher (référence, sujet...)" value="<?= e($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="new" <?= $status_filter == 'new' ? 'selected' : '' ?>>Nouveau</option>
                        <option value="opened" <?= $status_filter == 'opened' ? 'selected' : '' ?>>Ouvert</option>
                        <option value="in_progress" <?= $status_filter == 'in_progress' ? 'selected' : '' ?>>En cours</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Résolu</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejeté</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100" style="border-radius: 8px;"><i class="bi bi-search"></i> Filtrer</button>
                    <?php if($search || $status_filter): ?>
                        <a href="?" class="btn btn-outline-secondary" style="border-radius: 8px;"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card-custom">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="border-0 px-4 py-3">Référence</th>
                        <th class="border-0 py-3">Sujet</th>
                        <th class="border-0 py-3">Catégorie</th>
                        <th class="border-0 py-3">Statut</th>
                        <th class="border-0 py-3">Créé le</th>
                        <th class="border-0 px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($tickets)): ?>
                        <tr><td colspan="6" class="text-center p-4 text-muted">Aucune demande trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach($tickets as $t): ?>
                            <?php 
                                $labels = ['new'=>'Nouveau', 'opened'=>'Ouvert', 'in_progress'=>'En cours', 'completed'=>'Résolu', 'rejected'=>'Rejeté'];
                                $lbl = $labels[$t['status']] ?? $t['status'];
                            ?>
                            <tr>
                                <td class="px-4"><span class="font-monospace fw-bold text-primary" style="font-size:0.85rem;"><?= e($t['reference']) ?></span></td>
                                <td class="fw-medium text-dark"><?= e($t['subject']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($t['category_name']) ?></span></td>
                                <td><span class="badge rounded-pill badge-<?= e($t['status']) ?> px-2 py-1"><?= $lbl ?></span></td>
                                <td class="text-muted small"><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                                <td class="px-4 text-end">
                                    <a href="/pfe/student/view_ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" style="border-radius: 6px;">Ouvrir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-0 px-4 py-3" style="border-radius: 0 0 12px 12px;">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
