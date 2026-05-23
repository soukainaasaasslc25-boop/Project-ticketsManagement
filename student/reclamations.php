<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = $_SESSION['user_id'];
$allowed_statuses = ['all', 'draft', 'new', 'opened', 'in_progress', 'completed', 'rejected'];
$filter_status    = in_array($_GET['status'] ?? 'all', $allowed_statuses, true) ? ($_GET['status'] ?? 'all') : 'all';

$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;

$conditions = ["t.user_id = :user_id", "t.type = 'complaint'"];
$params = [':user_id' => $student_id];

if ($filter_status !== 'all') {
    $conditions[] = "t.status = :status";
    $params[':status'] = $filter_status;
}

if ($search !== '') {
    $conditions[] = "(t.reference LIKE :search_ref OR t.subject LIKE :search_sub OR c.name LIKE :search_cat)";
    $params[':search_ref'] = "%$search%";
    $params[':search_sub'] = "%$search%";
    $params[':search_cat'] = "%$search%";
}

$where_sql = "WHERE " . implode(" AND ", $conditions);
$base_sql = "FROM tickets t JOIN categories c ON c.id = t.category_id $where_sql";

// Count total for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) " . $base_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_tickets / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Fetch data
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name " . $base_sql . " ORDER BY t.updated_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// Get counts for tabs
$stmt_tabs = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM tickets WHERE user_id = ? AND type = 'complaint' GROUP BY status");
$stmt_tabs->execute([$student_id]);
$tab_counts = ['all' => 0];
foreach ($stmt_tabs->fetchAll() as $row) {
    $tab_counts[$row['status']] = (int) $row['cnt'];
    $tab_counts['all']         += (int) $row['cnt'];
}

function page_url($p, $s, $q) {
    $params = ['page' => $p];
    if ($s !== 'all') $params['status'] = $s;
    if ($q !== '') $params['search'] = $q;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réclamations — Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .nav-tabs-custom { border-bottom: 1px solid #e2e8f0; display: flex; overflow-x: auto; padding: 0 1rem; margin-bottom: 0; }
        .nav-tabs-custom .nav-item { margin-bottom: -1px; }
        .nav-tabs-custom .nav-link { 
            color: #64748b; font-weight: 500; font-size: 0.9rem; padding: 0.8rem 1.2rem; text-decoration: none;
            border: none; border-bottom: 2px solid transparent; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; transition: all 0.2s;
        }
        .nav-tabs-custom .nav-link:hover { color: #0f172a; }
        .nav-tabs-custom .nav-link.active { color: #dc3545; border-bottom-color: #dc3545; }
        .tab-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 50rem; background: #f1f5f9; color: #64748b; font-weight: 600; }
        .nav-link.active .tab-badge { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .status-badge { padding: 0.35em 0.8em; font-weight: 500; font-size: 0.75rem; border-radius: 50rem; }
        .status-draft { background-color: #f1f5f9; color: #475569; }
        .status-new { background-color: #fef3c7; color: #b45309; }
        .status-opened { background-color: #e0f2fe; color: #0369a1; }
        .status-in_progress { background-color: #ede9fe; color: #6d28d9; }
        .status-completed { background-color: #dcfce7; color: #15803d; }
        .status-rejected { background-color: #fee2e2; color: #b91c1c; }
        
        .priority-badge { font-size: 0.7rem; padding: 0.2em 0.6em; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .priority-low { background-color: #f1f5f9; color: #64748b; }
        .priority-medium { background-color: #fef3c7; color: #d97706; }
        .priority-high { background-color: #ffedd5; color: #ea580c; }
        .priority-urgent { background-color: #fee2e2; color: #ef4444; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1">Mes Réclamations</h3>
            <p class="text-muted mb-0">Suivez l'état de vos plaintes et réclamations.</p>
        </div>
        <a href="/pfe/student/create_reclamation.php" class="btn btn-danger shadow-sm" style="border-radius: 10px;">
            <i class="bi bi-exclamation-circle"></i> Nouvelle réclamation
        </a>
    </div>

    <div class="card-custom mb-4 overflow-hidden">
        <?php
        $tab_defs = [
            'all'         => ['Tous',        'bi-collection'],
            'draft'       => ['Brouillons',  'bi-pencil-square'],
            'new'         => ['En attente',  'bi-clock'],
            'opened'      => ['Ouverts',     'bi-folder2-open'],
            'in_progress' => ['En cours',    'bi-arrow-repeat'],
            'completed'   => ['Résolus',     'bi-check-circle'],
            'rejected'    => ['Rejetés',     'bi-x-circle'],
        ];
        ?>
        <div class="bg-white" style="border-bottom: 1px solid #e2e8f0;">
            <ul class="nav-tabs-custom list-unstyled">
                <?php foreach ($tab_defs as $key => [$label, $icon]): ?>
                    <?php $active = $filter_status === $key; $cnt = $tab_counts[$key] ?? 0; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= e(page_url(1, $key, $search)) ?>">
                            <i class="bi <?= $icon ?>"></i> <?= $label ?>
                            <?php if ($cnt > 0): ?>
                                <span class="tab-badge"><?= $cnt ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-body p-3 bg-light border-bottom">
            <form method="GET" class="d-flex gap-2">
                <?php if ($filter_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                <?php endif; ?>
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" name="search" class="form-control ps-5" placeholder="Chercher par référence ou sujet..." value="<?= e($search) ?>" style="border-radius: 8px;">
                </div>
                <button type="submit" class="btn btn-danger" style="border-radius: 8px;">Chercher</button>
                <?php if($search): ?>
                    <a href="<?= e(page_url(1, $filter_status, '')) ?>" class="btn btn-outline-secondary d-flex align-items-center" style="border-radius: 8px;"><i class="bi bi-x"></i> Effacer</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive bg-white">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="border-0 px-4 py-3 text-muted fw-semibold" style="font-size: 0.85rem;">Réclamation</th>
                        <th class="border-0 py-3 text-muted fw-semibold" style="font-size: 0.85rem;">Statut</th>
                        <th class="border-0 py-3 text-muted fw-semibold" style="font-size: 0.85rem;">Priorité</th>
                        <th class="border-0 py-3 text-muted fw-semibold" style="font-size: 0.85rem;">Date</th>
                        <th class="border-0 px-4 py-3 text-end text-muted fw-semibold" style="font-size: 0.85rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($tickets)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary opacity-50"></i>
                            <p class="mb-1 fw-medium text-dark">Aucune réclamation trouvée.</p>
                            <a href="/pfe/student/create_reclamation.php" class="text-danger text-decoration-none small">Créer une nouvelle réclamation</a>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach($tickets as $t): ?>
                            <?php 
                                $labels = ['draft'=>'Brouillon', 'new'=>'En attente', 'opened'=>'Ouvert', 'in_progress'=>'En cours', 'completed'=>'Résolu', 'rejected'=>'Rejeté'];
                                $lbl = $labels[$t['status']] ?? $t['status'];
                                
                                $pri_labels = ['low'=>'Basse', 'medium'=>'Moyenne', 'high'=>'Haute', 'urgent'=>'Urgente'];
                                $pri_lbl = $pri_labels[$t['priority']] ?? $t['priority'];
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-danger bg-opacity-10 text-danger rounded p-2 d-none d-sm-flex"><i class="bi bi-exclamation-triangle"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                                <?= e($t['subject']) ?>
                                            </div>
                                            <div class="text-muted small d-flex align-items-center gap-2">
                                                <span class="font-monospace text-danger bg-light px-1 rounded"><?= e($t['reference']) ?></span>
                                                <span>•</span>
                                                <span><?= e($t['category_name']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge status-<?= e($t['status']) ?>"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i><?= $lbl ?></span></td>
                                <td><span class="priority-badge priority-<?= e($t['priority']) ?>"><?= $pri_lbl ?></span></td>
                                <td class="text-muted small">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($t['created_at'])) ?>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <?php if ($t['status'] === 'draft'): ?>
                                            <a href="/pfe/student/edit_ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="/pfe/student/submit_draft.php" onsubmit="return confirm('Soumettre ce brouillon ?');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius: 8px;"><i class="bi bi-send"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="/pfe/student/view_ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-light border shadow-sm" style="border-radius: 8px;">Détails</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-0 px-4 py-3" style="border-radius: 0 0 12px 12px; border-top: 1px solid #e2e8f0 !important;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">Affichage de <?= count($tickets) ?> sur <?= $total_tickets ?> réclamation(s)</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link text-danger" href="<?= e(page_url($page - 1, $filter_status, $search)) ?>"><i class="bi bi-chevron-left"></i></a></li>
                        <?php endif; ?>
                        <?php for($i=max(1, $page-2); $i<=min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link <?= $i == $page ? 'bg-danger border-danger' : 'text-danger' ?>" href="<?= e(page_url($i, $filter_status, $search)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item"><a class="page-link text-danger" href="<?= e(page_url($page + 1, $filter_status, $search)) ?>"><i class="bi bi-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
