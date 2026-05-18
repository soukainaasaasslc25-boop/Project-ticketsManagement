<?php
// =============================================================================
// FILE    : student/dashboard.php
// PURPOSE : Student dashboard — shows the student their own tickets.
//           Protected by require_student() — only students can access.
// =============================================================================

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';

// =============================================================================
// Fetch this student's tickets grouped by status
// We use $_SESSION['user_id'] which was set during login — never trust GET/POST
// =============================================================================

$student_id = $_SESSION['user_id'];

// All tickets belonging to this student (exclude deleted ones)
$stmt = $pdo->prepare('
    SELECT t.*, c.name AS category_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
');
$stmt->execute([$student_id]);
$my_tickets = $stmt->fetchAll();

// Count tickets by status for this student
$stmt = $pdo->prepare('
    SELECT status, COUNT(*) AS cnt
    FROM tickets
    WHERE user_id = ?
    GROUP BY status
');
$stmt->execute([$student_id]);
$status_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $status_counts[$row['status']] = $row['cnt'];
}

$total_my_tickets = array_sum($status_counts);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace — Système de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }

        /* Top navigation bar */
        .topnav {
            background: #0f172a;
            padding: 0.85rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .topnav-brand {
            color: white; font-weight: 700; font-size: 1rem;
            display: flex; align-items: center; gap: 0.6rem;
            text-decoration: none;
        }
        .topnav-menu a {
            color: #94a3b8; font-size: 0.87rem; text-decoration: none;
            padding: 0.4rem 0.85rem; border-radius: 8px;
            transition: all 0.2s;
        }
        .topnav-menu a:hover, .topnav-menu a.active {
            background: rgba(255,255,255,0.08); color: white;
        }

        /* Page wrapper */
        .page-wrapper { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }

        /* Stat mini cards */
        .mini-card {
            background: white; border-radius: 12px;
            padding: 1.25rem; border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            text-align: center;
        }
        .mini-card .val { font-size: 1.8rem; font-weight: 700; color: #1e293b; line-height: 1; }
        .mini-card .lbl { font-size: 0.78rem; color: #64748b; margin-top: 0.3rem; }

        /* Ticket cards */
        .ticket-item {
            background: white; border-radius: 12px;
            padding: 1.25rem 1.5rem;
            border: 1px solid #f1f5f9;
            margin-bottom: 0.75rem;
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s;
        }
        .ticket-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

        .ticket-ref {
            font-size: 0.75rem; font-weight: 600;
            color: #3b82f6; font-family: monospace;
        }
        .ticket-subject { font-weight: 600; color: #1e293b; font-size: 0.95rem; }
        .ticket-meta { font-size: 0.8rem; color: #64748b; }

        .status-badge {
            font-size: 0.75rem; font-weight: 600;
            padding: 0.3em 0.8em; border-radius: 20px;
            white-space: nowrap;
        }
        .status-draft       { background:#f1f5f9; color:#64748b; }
        .status-new         { background:#fef3c7; color:#d97706; }
        .status-opened      { background:#dbeafe; color:#1d4ed8; }
        .status-in_progress { background:#ede9fe; color:#6d28d9; }
        .status-completed   { background:#d1fae5; color:#059669; }
        .status-rejected    { background:#fee2e2; color:#dc2626; }

        /* Action button */
        .btn-new-ticket {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white; border: none; border-radius: 10px;
            padding: 0.7rem 1.5rem; font-weight: 600; font-size: 0.9rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-new-ticket:hover {
            color: white; transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.35);
        }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 16px; padding: 1.75rem 2rem;
            color: white; margin-bottom: 1.5rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem;
        }
        .welcome-banner h4 { font-weight: 700; margin: 0 0 0.25rem; }
        .welcome-banner p  { margin: 0; opacity: 0.85; font-size: 0.9rem; }
    </style>
</head>
<body>

<!-- ========================================================================= -->
<!-- TOP NAVIGATION                                                             -->
<!-- ========================================================================= -->
<nav class="topnav">
    <a href="/pfe/student/dashboard.php" class="topnav-brand">
        <i class="bi bi-ticket-perforated-fill text-primary"></i>
        TicketSystem
    </a>
    <div class="topnav-menu d-flex align-items-center gap-1">
        <a href="/pfe/student/dashboard.php" class="active">
            <i class="bi bi-grid me-1"></i>Tableau de bord
        </a>
        <a href="/pfe/student/my_tickets.php">
            <i class="bi bi-ticket-detailed me-1"></i>Mes tickets
        </a>
        <a href="/pfe/student/create_ticket.php">
            <i class="bi bi-plus-circle me-1"></i>Nouvelle demande
        </a>
        <a href="/pfe/auth/logout.php" style="color:#f87171;">
            <i class="bi bi-box-arrow-left me-1"></i>Déconnexion
        </a>
    </div>
</nav>

<!-- ========================================================================= -->
<!-- PAGE CONTENT                                                               -->
<!-- ========================================================================= -->
<div class="page-wrapper">

    <?php
    // Flash messages from create/process ticket
    $flash_success = $_SESSION['flash_success'] ?? null;
    $flash_error   = $_SESSION['flash_error']   ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    ?>
    <?php if ($flash_success): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem;">
            <i class="bi bi-check-circle-fill" style="color:#16a34a;font-size:1.1rem;margin-top:1px;"></i>
            <div><?= $flash_success ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem;">
            <i class="bi bi-exclamation-circle-fill" style="color:#dc2626;font-size:1.1rem;margin-top:1px;"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div>
            <h4>Bonjour, <?= htmlspecialchars($_SESSION['first_name']) ?> 👋</h4>
            <p>
                Bienvenue dans votre espace personnel.
                Authentification réussie — votre compte est maintenant actif.
            </p>
        </div>
        <a href="/pfe/student/create_ticket.php" class="btn-new-ticket">
            <i class="bi bi-plus-lg"></i> Nouvelle demande
        </a>
    </div>

    <!-- Mini Stats Row -->
    <div class="row row-cols-2 row-cols-md-5 g-3 mb-4">
        <div class="col">
            <div class="mini-card">
                <div class="val"><?= $total_my_tickets ?></div>
                <div class="lbl">Total</div>
            </div>
        </div>
        <div class="col">
            <div class="mini-card">
                <div class="val" style="color:#d97706;"><?= $status_counts['new'] ?? 0 ?></div>
                <div class="lbl">En attente</div>
            </div>
        </div>
        <div class="col">
            <div class="mini-card">
                <div class="val" style="color:#6d28d9;"><?= $status_counts['in_progress'] ?? 0 ?></div>
                <div class="lbl">En cours</div>
            </div>
        </div>
        <div class="col">
            <div class="mini-card">
                <div class="val" style="color:#059669;"><?= $status_counts['completed'] ?? 0 ?></div>
                <div class="lbl">Résolus</div>
            </div>
        </div>
        <div class="col">
            <div class="mini-card">
                <div class="val" style="color:#dc2626;"><?= $status_counts['rejected'] ?? 0 ?></div>
                <div class="lbl">Rejetés</div>
            </div>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold" style="color:#1e293b;">
            <i class="bi bi-ticket-detailed me-2 text-primary"></i>Mes demandes récentes
        </h6>
    </div>

    <?php if (empty($my_tickets)): ?>
        <!-- Empty state -->
        <div class="text-center py-5" style="background:white;border-radius:14px;">
            <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
            <p class="mt-3 text-muted">Vous n'avez pas encore soumis de demande.</p>
            <a href="/pfe/student/create_ticket.php" class="btn-new-ticket">
                <i class="bi bi-plus-lg"></i> Créer ma première demande
            </a>
        </div>
    <?php else: ?>

        <?php
        $status_labels = [
            'draft'       => 'Brouillon',
            'new'         => 'En attente',
            'opened'      => 'Ouvert',
            'in_progress' => 'En cours',
            'completed'   => 'Résolu',
            'rejected'    => 'Rejeté',
        ];
        ?>

        <?php foreach ($my_tickets as $ticket): ?>
            <div class="ticket-item">
                <div>
                    <div class="ticket-ref mb-1"><?= htmlspecialchars($ticket['reference']) ?></div>
                    <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                    <div class="ticket-meta mt-1">
                        <i class="bi bi-tag me-1"></i><?= htmlspecialchars($ticket['category_name']) ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-clock me-1"></i>
                        <?= date('d/m/Y', strtotime($ticket['created_at'])) ?>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="status-badge status-<?= htmlspecialchars($ticket['status']) ?>">
                        <?= $status_labels[$ticket['status']] ?? $ticket['status'] ?>
                    </span>
                    <a href="/pfe/student/my_tickets.php" class="btn btn-sm btn-outline-secondary"
                       style="font-size:0.8rem;border-radius:8px;">
                        Voir tous <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /page-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
