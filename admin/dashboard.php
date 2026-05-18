<?php
// =============================================================================
// FILE    : admin/dashboard.php
// PURPOSE : Admin dashboard — placeholder page protected by require_admin().
//           Shows basic statistics and confirms authentication is working.
// =============================================================================

// Protect this page: only admins can access it
// auth_check.php provides require_admin(), require_student(), require_login()
require_once __DIR__ . '/../auth/auth_check.php';
require_admin();

// Load the database connection for stats queries
require_once __DIR__ . '/../config/database.php';

// =============================================================================
// Fetch quick statistics to display on dashboard
// =============================================================================

// Total tickets
$stmt = $pdo->query('SELECT COUNT(*) FROM tickets');
$total_tickets = $stmt->fetchColumn();

// Tickets by status
$stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status");
$tickets_by_status = [];
foreach ($stmt->fetchAll() as $row) {
    $tickets_by_status[$row['status']] = $row['cnt'];
}

// Total students
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
$total_students = $stmt->fetchColumn();

// New (unhandled) tickets — need attention
$new_count        = $tickets_by_status['new'] ?? 0;
$in_progress_count = $tickets_by_status['in_progress'] ?? 0;

// Recent 5 tickets for the quick view table
$stmt = $pdo->query("
    SELECT t.id, t.reference, t.status, t.priority, t.subject,
           t.created_at, t.submitted_at,
           u.first_name, u.last_name
    FROM tickets t
    JOIN users u ON u.id = t.user_id
    WHERE t.status != 'draft'
    ORDER BY t.updated_at DESC
    LIMIT 6
");
$recent_tickets = $stmt->fetchAll();

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Système de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .sidebar {
            background: #0f172a;
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0; left: 0;
            padding: 1.5rem 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 1rem;
        }
        .sidebar-brand h5 {
            color: white; font-weight: 700; font-size: 1rem; margin: 0;
        }
        .sidebar-brand p { color: #64748b; font-size: 0.75rem; margin: 0; }
        .nav-link-sidebar {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.65rem 1.5rem; color: #94a3b8;
            text-decoration: none; font-size: 0.88rem; font-weight: 500;
            border-radius: 0; transition: all 0.2s;
        }
        .nav-link-sidebar:hover, .nav-link-sidebar.active {
            background: rgba(59,130,246,0.12); color: #60a5fa;
        }
        .topnav-menu a:hover, .topnav-menu a.active {
            background: rgba(59,130,246,0.12); color: #60a5fa;
        }
        .main-content { margin-left: 250px; padding: 2rem; }
        .topbar {
            background: white; border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            margin-left: 250px; position: sticky; top: 0; z-index: 99;
            display: flex; justify-content: space-between; align-items: center;
        }
        .stat-card {
            background: white; border-radius: 14px;
            padding: 1.5rem; border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 1rem;
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: #1e293b; line-height: 1; }
        .stat-label { font-size: 0.82rem; color: #64748b; margin-top: 0.3rem; }
        .badge-status { font-size: 0.75rem; padding: 0.3em 0.7em; border-radius: 6px; }
    </style>
</head>
<body>

<!-- ========================================================================= -->
<!-- SIDEBAR                                                                    -->
<!-- ========================================================================= -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-ticket-perforated-fill text-primary fs-5"></i>
            <h5>TicketSystem</h5>
        </div>
        <p>Administration</p>
    </div>

    <nav>
        <a href="/pfe/admin/dashboard.php" class="nav-link-sidebar active">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="/pfe/admin/tickets/index.php" class="nav-link-sidebar">
            <i class="bi bi-ticket-detailed"></i> Tous les tickets
            <?php if ($new_count > 0): ?>
                <span class="ms-auto badge bg-danger rounded-pill" style="font-size:0.68rem;"><?= $new_count ?></span>
            <?php endif; ?>
        </a>
        <a href="/pfe/admin/tickets/index.php?status=new" class="nav-link-sidebar">
            <i class="bi bi-clock"></i> Nouveaux
        </a>
        <a href="/pfe/admin/tickets/index.php?status=in_progress" class="nav-link-sidebar">
            <i class="bi bi-arrow-repeat"></i> En cours
        </a>
        <hr style="border-color:rgba(255,255,255,0.08); margin: 0.75rem 1.5rem;">
        <a href="/pfe/admin/students/index.php" class="nav-link-sidebar">
            <i class="bi bi-people"></i> Étudiants
        </a>
        <a href="/pfe/auth/logout.php" class="nav-link-sidebar" style="color:#f87171;">
            <i class="bi bi-box-arrow-left"></i> Déconnexion
        </a>
    </nav>
</div>

<!-- ========================================================================= -->
<!-- TOP BAR                                                                    -->
<!-- ========================================================================= -->
<div class="topbar">
    <div>
        <h6 class="mb-0 fw-600" style="font-weight:600; color:#1e293b;">
            Tableau de bord
        </h6>
        <small class="text-muted">
            <?= date('l, d F Y') ?>
        </small>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if ($new_count > 0): ?>
            <span class="badge bg-danger rounded-pill">
                <?= $new_count ?> nouveau<?= $new_count > 1 ? 'x' : '' ?>
            </span>
        <?php endif; ?>
        <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;background:#1e40af;border-radius:50%;
                        display:flex;align-items:center;justify-content:center;color:white;font-size:0.9rem;font-weight:700;">
                <?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:0.85rem;font-weight:600;color:#1e293b;">
                    <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?>
                </div>
                <div style="font-size:0.75rem;color:#64748b;">Administrateur</div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MAIN CONTENT                                                               -->
<!-- ========================================================================= -->
<div class="main-content">

    <!-- Flash messages -->
    <?php if ($flash_success): ?>
        <div class="alert border-0 mb-4" style="background:#f0fdf4;border-radius:14px;color:#15803d;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                <div><?= $flash_success ?></div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert border-0 mb-4" style="background:#fef2f2;border-radius:14px;color:#dc2626;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= $flash_error ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="alert alert-success border-0 mb-4"
         style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:14px;">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-check-circle-fill text-success fs-4"></i>
            <div>
                <strong>Authentification réussie !</strong>
                Bienvenue <?= htmlspecialchars($_SESSION['first_name']) ?>.
                Le système d'authentification fonctionne correctement.
            </div>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">
                    <i class="bi bi-ticket-detailed-fill"></i>
                </div>
                <div class="stat-value"><?= $total_tickets ?></div>
                <div class="stat-label">Total tickets</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff7ed;color:#f97316;">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-value"><?= $new_count ?></div>
                <div class="stat-label">Nouveaux tickets</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4;color:#22c55e;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-value"><?= $tickets_by_status['completed'] ?? 0 ?></div>
                <div class="stat-label">Tickets résolus</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-value"><?= $total_students ?></div>
                <div class="stat-label">Étudiants</div>
            </div>
        </div>

    </div>

    <!-- Tickets by Status Table -->
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="mb-0 fw-semibold">Répartition des tickets par statut</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th class="px-4 py-3 text-muted" style="font-size:0.8rem;font-weight:600;">STATUT</th>
                        <th class="px-4 py-3 text-muted" style="font-size:0.8rem;font-weight:600;">NOMBRE</th>
                        <th class="px-4 py-3 text-muted" style="font-size:0.8rem;font-weight:600;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $status_config = [
                        'new'         => ['label' => 'Nouveaux',     'badge' => 'warning', 'icon' => 'bi-envelope-fill'],
                        'opened'      => ['label' => 'Ouverts',      'badge' => 'info',    'icon' => 'bi-folder2-open'],
                        'in_progress' => ['label' => 'En cours',     'badge' => 'primary', 'icon' => 'bi-arrow-repeat'],
                        'completed'   => ['label' => 'Terminés',     'badge' => 'success', 'icon' => 'bi-check-circle'],
                        'rejected'    => ['label' => 'Rejetés',      'badge' => 'danger',  'icon' => 'bi-x-circle'],
                        'draft'       => ['label' => 'Brouillons',   'badge' => 'secondary','icon' => 'bi-pencil'],
                    ];
                    foreach ($status_config as $status => $cfg):
                        $count = $tickets_by_status[$status] ?? 0;
                    ?>
                    <tr>
                        <td class="px-4 py-3">
                            <i class="bi <?= $cfg['icon'] ?> me-2 text-<?= $cfg['badge'] ?>"></i>
                            <?= $cfg['label'] ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge bg-<?= $cfg['badge'] ?> badge-status">
                                <?= $count ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/pfe/admin/tickets/index.php?status=<?= $status ?>" class="btn btn-sm btn-outline-secondary" style="font-size:0.8rem;">
                                Voir <i class="bi bi-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Tickets Table -->
    <div class="card border-0 shadow-sm mt-4" style="border-radius:14px;overflow:hidden;">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Tickets récents</h6>
            <a href="/pfe/admin/tickets/index.php" class="btn btn-sm btn-primary" style="font-size:0.8rem;border-radius:8px;">
                Voir tous <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent_tickets)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                    Aucun ticket soumis pour l'instant.
                </div>
            <?php else: ?>
                <?php
                $sts_colors = [
                    'new'         => ['Nouveau',    'warning'],
                    'opened'      => ['Ouvert',     'info'],
                    'in_progress' => ['En cours',   'primary'],
                    'completed'   => ['Résolu',     'success'],
                    'rejected'    => ['Rejeté',     'danger'],
                ];
                $pri_labels = [
                    'low'    => ['Basse',   'secondary'],
                    'medium' => ['Moyenne', 'warning'],
                    'high'   => ['Haute',   'orange'],
                    'urgent' => ['Urgente', 'danger'],
                ];
                ?>
                <table class="table table-hover mb-0" style="font-size:0.875rem;">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">RÉFÉRENCE</th>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">ÉTUDIANT</th>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">OBJET</th>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">PRIORITÉ</th>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">STATUT</th>
                            <th class="px-4 py-3 text-muted" style="font-size:0.78rem;font-weight:600;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tickets as $t): ?>
                            <?php
                            [$sts_label, $sts_badge] = $sts_colors[$t['status']]   ?? [$t['status'], 'secondary'];
                            [$pri_label, $pri_badge] = $pri_labels[$t['priority']] ?? [$t['priority'], 'secondary'];
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <code class="text-primary" style="font-size:0.8rem;"><?= htmlspecialchars($t['reference']) ?></code>
                                </td>
                                <td class="px-4 py-3">
                                    <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?>
                                </td>
                                <td class="px-4 py-3" style="max-width:220px;">
                                    <span class="d-block text-truncate"><?= htmlspecialchars($t['subject']) ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge bg-<?= $pri_badge ?> badge-status"><?= $pri_label ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge bg-<?= $sts_badge ?> badge-status"><?= $sts_label ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="/pfe/admin/tickets/view.php?id=<?= (int)$t['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" style="font-size:0.78rem;border-radius:8px;">
                                        <i class="bi bi-eye me-1"></i>Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
