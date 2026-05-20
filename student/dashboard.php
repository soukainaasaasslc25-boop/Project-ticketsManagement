<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = $_SESSION['user_id'];

// Stats queries
// Total, Demandes, Reclamations, Brouillons, Completes
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type = 'request' AND status != 'draft' THEN 1 ELSE 0 END) as demandes,
        SUM(CASE WHEN type = 'complaint' AND status != 'draft' THEN 1 ELSE 0 END) as reclamations,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM tickets 
    WHERE user_id = ?
");
$stmt->execute([$student_id]);
$stats = $stmt->fetch();

// Recent updates (last 5 tickets)
$stmt = $pdo->prepare("
    SELECT t.id, t.reference, t.subject, t.status, t.type, t.updated_at 
    FROM tickets t
    WHERE t.user_id = ? AND t.status != 'draft'
    ORDER BY t.updated_at DESC LIMIT 5
");
$stmt->execute([$student_id]);
$recent_tickets = $stmt->fetchAll();

// Latest admin responses (ticket_responses where is_internal=0 and sender is admin)
$stmt = $pdo->prepare("
    SELECT r.message, r.created_at, t.reference, t.id as ticket_id
    FROM ticket_responses r
    JOIN tickets t ON t.id = r.ticket_id
    JOIN users u ON u.id = r.sender_id
    WHERE t.user_id = ? AND u.role = 'admin' AND r.is_internal = 0
    ORDER BY r.created_at DESC LIMIT 5
");
$stmt->execute([$student_id]);
$recent_responses = $stmt->fetchAll();

// Get must change password flag
$must_change_password = $_SESSION['must_change_password'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    
    <?php if ($must_change_password == 1): ?>
        <div class="alert alert-warning d-flex align-items-center gap-3 border-warning mb-4" style="border-radius: 12px; background: #fffbeb;">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
            <div>
                <h6 class="mb-1 fw-bold text-dark">Sécurité de votre compte</h6>
                <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                    Nous vous recommandons de modifier votre mot de passe par défaut. 
                    <a href="/pfe/student/profile.php?first_login=1" class="fw-bold text-warning text-decoration-none">Mettre à jour &rarr;</a>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1">Bonjour, <?= htmlspecialchars($_SESSION['first_name']) ?> 👋</h3>
            <p class="text-muted mb-0">Bienvenue dans votre espace étudiant.</p>
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

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 text-center h-100">
                <div class="fs-1 fw-bold text-primary"><?= (int)$stats['total'] ?></div>
                <div class="text-muted small text-uppercase fw-semibold">Total Tickets</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 text-center h-100">
                <div class="fs-1 fw-bold text-info"><?= (int)$stats['demandes'] ?></div>
                <div class="text-muted small text-uppercase fw-semibold">Demandes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 text-center h-100">
                <div class="fs-1 fw-bold text-danger"><?= (int)$stats['reclamations'] ?></div>
                <div class="text-muted small text-uppercase fw-semibold">Réclamations</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card-custom p-3 text-center h-100">
                <div class="fs-1 fw-bold text-secondary"><?= (int)$stats['drafts'] ?></div>
                <div class="text-muted small text-uppercase fw-semibold">Brouillons</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Répartition par Statut</div>
                <div class="p-4 d-flex justify-content-center">
                    <canvas id="statusChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Demandes vs Réclamations</div>
                <div class="p-4 d-flex justify-content-center">
                    <canvas id="typeChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Tickets récemment mis à jour</div>
                <div class="p-0">
                    <?php if (empty($recent_tickets)): ?>
                        <div class="p-4 text-center text-muted">Aucun ticket récent.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="border-radius: 0 0 12px 12px;">
                            <?php foreach ($recent_tickets as $t): ?>
                                <?php 
                                    $labels = ['new'=>'Nouveau', 'opened'=>'Ouvert', 'in_progress'=>'En cours', 'completed'=>'Résolu', 'rejected'=>'Rejeté'];
                                    $lbl = $labels[$t['status']] ?? $t['status'];
                                ?>
                                <a href="/pfe/student/view_ticket.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark font-monospace" style="font-size:0.85rem;"><?= e($t['reference']) ?></span>
                                        <span class="badge rounded-pill badge-<?= e($t['status']) ?> px-2 py-1"><?= $lbl ?></span>
                                    </div>
                                    <div class="text-muted small text-truncate"><?= e($t['subject']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;"><i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($t['updated_at'])) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card-custom h-100">
                <div class="card-header-custom">Dernières réponses (Admin)</div>
                <div class="p-0">
                    <?php if (empty($recent_responses)): ?>
                        <div class="p-4 text-center text-muted">Aucune réponse récente.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="border-radius: 0 0 12px 12px;">
                            <?php foreach ($recent_responses as $r): ?>
                                <a href="/pfe/student/view_ticket.php?id=<?= $r['ticket_id'] ?>" class="list-group-item list-group-item-action p-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-semibold text-primary" style="font-size:0.85rem;"><i class="bi bi-chat-left-dots"></i> <?= e($r['reference']) ?></span>
                                        <span class="text-muted" style="font-size: 0.7rem;"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></span>
                                    </div>
                                    <div class="text-muted small text-truncate">
                                        <?= e(str_starts_with($r['message'], '[SYSTEM]') ? 'Mise à jour du statut' : $r['message']) ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Nouveau', 'Ouvert', 'En cours', 'Complété', 'Rejeté', 'Brouillon'],
        datasets: [{
            data: [
                <?= (int)$stats['new'] ?>, 
                <?= (int)$stats['opened'] ?>, 
                <?= (int)$stats['in_progress'] ?>, 
                <?= (int)$stats['completed'] ?>, 
                <?= (int)$stats['rejected'] ?>, 
                <?= (int)$stats['drafts'] ?>
            ],
            backgroundColor: ['#3b82f6', '#06b6d4', '#f97316', '#22c55e', '#ef4444', '#cbd5e1']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
});

const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'pie',
    data: {
        labels: ['Demandes', 'Réclamations'],
        datasets: [{
            data: [<?= (int)$stats['demandes'] ?>, <?= (int)$stats['reclamations'] ?>],
            backgroundColor: ['#3b82f6', '#ef4444']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
