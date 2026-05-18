<?php
// =============================================================================
// FILE    : includes/header.php
// PURPOSE : Shared HTML head + top navigation for admin and student areas.
//
// Required variables before including:
//   $page_title  — string shown in <title> and page heading
//   $layout_role — 'admin' or 'student'
//   $active_nav  — which menu item is active (e.g. 'dashboard', 'tickets')
// =============================================================================

if (!isset($page_title, $layout_role, $active_nav)) {
    die('header.php requires $page_title, $layout_role, and $active_nav');
}

$nav_items = $layout_role === 'admin'
    ? [
        'dashboard' => ['label' => 'Tableau de bord', 'url' => '/admin/dashboard.php', 'icon' => 'bi-speedometer2'],
        'tickets'   => ['label' => 'Tickets', 'url' => '#', 'icon' => 'bi-ticket-detailed', 'disabled' => true],
      ]
    : [
        'dashboard' => ['label' => 'Tableau de bord', 'url' => '/student/dashboard.php', 'icon' => 'bi-speedometer2'],
        'tickets'   => ['label' => 'Mes tickets', 'url' => '#', 'icon' => 'bi-ticket-detailed', 'disabled' => true],
        'create'    => ['label' => 'Nouveau ticket', 'url' => '#', 'icon' => 'bi-plus-circle', 'disabled' => true],
      ];

$role_label = $layout_role === 'admin' ? 'Administrateur' : 'Étudiant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Système de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
        .app-navbar {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            box-shadow: 0 2px 12px rgba(30, 58, 138, 0.25);
        }
        .app-navbar .navbar-brand { font-weight: 700; font-size: 1.05rem; }
        .nav-pills .nav-link {
            color: rgba(255,255,255,0.85);
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 8px;
            padding: 0.45rem 0.9rem;
        }
        .nav-pills .nav-link:hover:not(.disabled) { background: rgba(255,255,255,0.12); color: #fff; }
        .nav-pills .nav-link.active { background: rgba(255,255,255,0.2); color: #fff; }
        .nav-pills .nav-link.disabled { opacity: 0.45; cursor: not-allowed; }
        .user-badge {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }
        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        .content-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg app-navbar navbar-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-white" href="<?= e(BASE_URL . ($layout_role === 'admin' ? '/admin/dashboard.php' : '/student/dashboard.php')) ?>">
            <i class="bi bi-ticket-perforated-fill me-2"></i>Tickets
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto nav-pills gap-1 ms-lg-3">
                <?php foreach ($nav_items as $key => $item): ?>
                    <?php $is_disabled = !empty($item['disabled']); ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_nav === $key ? 'active' : '' ?> <?= $is_disabled ? 'disabled' : '' ?>"
                           href="<?= $is_disabled ? '#' : e(BASE_URL . $item['url']) ?>"
                           <?= $is_disabled ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                            <i class="bi <?= e($item['icon']) ?> me-1"></i><?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <span class="user-badge text-white d-none d-md-inline">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= e(current_user_full_name()) ?>
                    <span class="opacity-75 ms-1">(<?= e($role_label) ?>)</span>
                </span>
                <a href="<?= e(BASE_URL) ?>/auth/logout.php" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 pb-5">
