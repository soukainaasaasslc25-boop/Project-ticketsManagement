<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Sidebar layout CSS */
body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; margin: 0; }
.sidebar {
    width: 260px; background: #0f172a; color: white; display: flex; flex-direction: column;
    position: fixed; top: 0; bottom: 0; left: 0; z-index: 1000;
}
.sidebar-brand { padding: 1.5rem; font-size: 1.2rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 0.75rem; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.05); }
.sidebar-nav { padding: 1rem 0; flex-grow: 1; display: flex; flex-direction: column; gap: 0.25rem; }
.nav-link { color: #cbd5e1; padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.95rem; text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
.nav-link:hover { color: white; background: rgba(255,255,255,0.05); border-left-color: #3b82f6; }
.nav-link.active { color: white; background: rgba(59,130,246,0.1); border-left-color: #3b82f6; font-weight: 600; }
.sidebar-bottom { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05); margin-top: auto; }
.btn-logout { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; background: rgba(239,68,68,0.1); color: #ef4444; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s; }
.btn-logout:hover { background: rgba(239,68,68,0.2); color: #f87171; }
.main-content { margin-left: 260px; flex-grow: 1; padding: 2rem; max-width: 1400px; width: calc(100% - 260px); }

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.show { transform: translateX(0); }
    .main-content { margin-left: 0; width: 100%; padding: 1rem; }
    .mobile-header { display: flex !important; }
}
.mobile-header { display: none; background: #0f172a; color: white; padding: 1rem; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 999; }

/* Status Badges */
.badge-draft { background: #f1f5f9; color: #64748b; }
.badge-new { background: #eff6ff; color: #3b82f6; }
.badge-opened { background: #ecfeff; color: #06b6d4; }
.badge-in_progress { background: #fff7ed; color: #f97316; }
.badge-completed { background: #f0fdf4; color: #22c55e; }
.badge-rejected { background: #fef2f2; color: #ef4444; }

/* Cards */
.card-custom { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.card-header-custom { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
</style>

<div class="mobile-header">
    <div class="fw-bold d-flex align-items-center gap-2"><i class="bi bi-ticket-perforated-fill text-primary"></i> TicketSystem</div>
    <button class="btn btn-sm btn-outline-light" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
</div>

<div class="sidebar">
    <a href="/pfe/student/dashboard.php" class="sidebar-brand">
        <i class="bi bi-ticket-perforated-fill text-primary"></i> TicketSystem
    </a>
    <div class="sidebar-nav">
        <a href="/pfe/student/dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a href="/pfe/student/demandes.php" class="nav-link <?= $current_page == 'demandes.php' ? 'active' : '' ?>">
            <i class="bi bi-envelope-paper"></i> Demandes
        </a>
        <a href="/pfe/student/reclamations.php" class="nav-link <?= $current_page == 'reclamations.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i> Réclamations
        </a>
        <a href="/pfe/student/drafts.php" class="nav-link <?= $current_page == 'drafts.php' ? 'active' : '' ?>">
            <i class="bi bi-pencil-square"></i> Brouillons
        </a>
        <a href="/pfe/student/profile.php" class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> Profil
        </a>
    </div>
    <div class="sidebar-bottom">
        <a href="/pfe/auth/logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-left"></i> Déconnexion
        </a>
    </div>
</div>
