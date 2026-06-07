<?php
$current_page = basename($_SERVER['PHP_SELF']);
$dir_name = basename(dirname($_SERVER['PHP_SELF']));

$user_first_name = $_SESSION['first_name'] ?? 'Admin';
$user_last_name = $_SESSION['last_name'] ?? '';
$user_initials = strtoupper(substr($user_first_name, 0, 1) . substr($user_last_name, 0, 1));
?>
<div class="flex h-screen bg-slate-50 font-sans overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#1e1e2d] text-slate-300 flex flex-col shrink-0 transition-transform duration-300 z-50 fixed md:relative h-full -translate-x-full md:translate-x-0" id="sidebar">
        <!-- Brand -->
        <div class="h-16 flex items-center px-6 border-b border-slate-700/50 mb-4">
            <a href="/pfe/admin/dashboard.php" class="flex items-center gap-3 text-white font-bold text-lg text-decoration-none">
                <div class="bg-indigo-500 text-white rounded-lg p-1.5 flex items-center justify-center">
                    <i class="bi bi-mortarboard-fill text-xl"></i>
                </div>
                <span>UniPortal</span>
            </a>
            <button class="ml-auto md:hidden text-slate-400 hover:text-white" onclick="document.getElementById('sidebar').classList.add('-translate-x-full')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto">
            <a href="/pfe/admin/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= $current_page == 'dashboard.php' ? 'bg-indigo-600 text-white font-medium shadow-md shadow-indigo-500/20' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="bi bi-grid-1x2 text-lg <?= $current_page == 'dashboard.php' ? 'text-white' : 'text-slate-400' ?>"></i> 
                Dashboard
            </a>
            
            <a href="/pfe/admin/students/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= $dir_name == 'students' ? 'bg-indigo-600 text-white font-medium shadow-md shadow-indigo-500/20' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="bi bi-people text-lg <?= $dir_name == 'students' ? 'text-white' : 'text-slate-400' ?>"></i> 
                Students
            </a>
            
            <a href="/pfe/admin/tickets/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= $dir_name == 'tickets' ? 'bg-indigo-600 text-white font-medium shadow-md shadow-indigo-500/20' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="bi bi-ticket-detailed text-lg <?= $dir_name == 'tickets' ? 'text-white' : 'text-slate-400' ?>"></i> 
                Tickets
            </a>
            
            <a href="/pfe/admin/activity_logs.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= $current_page == 'activity_logs.php' ? 'bg-indigo-600 text-white font-medium shadow-md shadow-indigo-500/20' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="bi bi-journal-text text-lg <?= $current_page == 'activity_logs.php' ? 'text-white' : 'text-slate-400' ?>"></i> 
                Activity Logs
            </a>
            
            <a href="/pfe/admin/settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors <?= $current_page == 'settings.php' ? 'bg-indigo-600 text-white font-medium shadow-md shadow-indigo-500/20' : 'hover:bg-white/5 hover:text-white' ?>">
                <i class="bi bi-gear text-lg <?= $current_page == 'settings.php' ? 'text-white' : 'text-slate-400' ?>"></i> 
                Settings
            </a>
        </nav>

        <!-- Bottom -->
        <div class="p-4 border-t border-slate-700/50 mt-auto">
            <a href="/pfe/auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-white/5 hover:text-red-400 transition-colors group">
                <i class="bi bi-box-arrow-left text-lg text-slate-400 group-hover:text-red-400"></i> 
                Logout
            </a>
        </div>
    </aside>

    <!-- Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden" onclick="document.getElementById('sidebar').classList.add('-translate-x-full'); this.classList.add('hidden')"></div>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <!-- Topbar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 shrink-0 z-30">
            <!-- Mobile Menu Button & Search -->
            <div class="flex items-center gap-4 flex-1">
                <button class="md:hidden text-slate-500 hover:text-slate-700" onclick="document.getElementById('sidebar').classList.remove('-translate-x-full'); document.getElementById('sidebar-overlay').classList.remove('hidden')">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                
                <div class="relative hidden sm:block max-w-md w-full">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" placeholder="Search tickets, students..." class="w-full bg-slate-100 border-transparent focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 rounded-full pl-10 pr-4 py-2 text-sm transition-all outline-none">
                </div>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-3 sm:gap-5">
                <button class="relative text-slate-500 hover:text-indigo-600 transition-colors">
                    <i class="bi bi-bell text-xl"></i>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full border border-white"></span>
                </button>

                <div class="flex items-center gap-3 border-l border-slate-200 pl-4 sm:pl-5">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                        <?= htmlspecialchars($user_initials) ?>
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user_first_name . ' ' . $user_last_name) ?></div>
                        <div class="text-xs text-slate-500">Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50/50 p-4 sm:p-6 lg:p-8">
