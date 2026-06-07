<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = $_SESSION['user_id'];

// Stats queries
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'request' AND status != 'draft' THEN 1 ELSE 0 END) as total_demandes,
        SUM(CASE WHEN type = 'complaint' AND status != 'draft' THEN 1 ELSE 0 END) as total_reclamations,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM tickets 
    WHERE user_id = ?
");
$stmt->execute([$student_id]);
$stats = $stmt->fetch();

// Category chart data
$stmt = $pdo->prepare("
    SELECT c.name, COUNT(*) as count 
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.status != 'draft'
    GROUP BY c.name
");
$stmt->execute([$student_id]);
$cat_data = $stmt->fetchAll();
$cat_labels = json_encode(array_column($cat_data, 'name'));
$cat_counts = json_encode(array_column($cat_data, 'count'));

// Monthly activity data (last 6 months)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count
    FROM tickets
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status != 'draft'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
");
$stmt->execute([$student_id]);
$monthly_data = $stmt->fetchAll();
$monthly_labels = json_encode(array_column($monthly_data, 'month'));
$monthly_counts = json_encode(array_column($monthly_data, 'count'));

// Recent updates (last 5 tickets)
$stmt = $pdo->prepare("
    SELECT t.id, t.reference, t.subject, t.status, t.type, t.updated_at, c.name as category_name
    FROM tickets t
    JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.status != 'draft'
    ORDER BY t.updated_at DESC LIMIT 5
");
$stmt->execute([$student_id]);
$recent_tickets = $stmt->fetchAll();

$must_change_password = $_SESSION['must_change_password'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — UniPortal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 900: '#1e1b4b' }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom scrollbar for main area */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
        <p class="text-slate-500 text-sm mt-1">Overview of your tickets and activity.</p>
    </div>
    <div class="flex gap-3">
        <a href="/pfe/student/create_demande.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm shadow-indigo-200 transition-all flex items-center gap-2">
            <i class="bi bi-file-earmark-plus"></i> New Request
        </a>
        <a href="/pfe/student/create_reclamation.php" class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm shadow-rose-200 transition-all flex items-center gap-2">
            <i class="bi bi-exclamation-octagon"></i> New Complaint
        </a>
    </div>
</div>

<?php if ($must_change_password == 1): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-4">
        <div class="bg-amber-100 text-amber-600 p-2 rounded-xl shrink-0"><i class="bi bi-shield-lock-fill text-xl"></i></div>
        <div>
            <h3 class="text-amber-800 font-semibold mb-1">Account Security</h3>
            <p class="text-amber-700 text-sm mb-2">We recommend updating your default password to secure your account.</p>
            <a href="/pfe/student/profile.php?first_login=1" class="text-amber-700 font-semibold text-sm hover:underline">Update Password &rarr;</a>
        </div>
    </div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Demandes -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-file-earmark-text"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Total Demandes</p>
            <h3 class="text-2xl font-bold text-slate-800"><?= (int)$stats['total_demandes'] ?></h3>
        </div>
    </div>

    <!-- Total Reclamations -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Total Réclamations</p>
            <h3 class="text-2xl font-bold text-slate-800"><?= (int)$stats['total_reclamations'] ?></h3>
        </div>
    </div>

    <!-- Drafts -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-pencil-square"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Brouillons</p>
            <h3 class="text-2xl font-bold text-slate-800"><?= (int)$stats['drafts'] ?></h3>
        </div>
    </div>

    <!-- En Cours -->
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
            <i class="bi bi-arrow-repeat"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500">Tickets En Cours</p>
            <h3 class="text-2xl font-bold text-slate-800"><?= (int)$stats['in_progress'] ?></h3>
        </div>
    </div>
</div>

<!-- Dashboard Cards: Quick Actions + My Tickets (left) | Recent Tickets (right) -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">

    <!-- LEFT COLUMN (2/5) -->
    <div class="lg:col-span-2 flex flex-col gap-5">

        <!-- Quick Actions Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-4">Quick Actions</p>
            <div class="flex flex-col gap-3">

                <!-- New Demande -->
                <a href="/pfe/student/create_demande.php"
                   class="group flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-200 transition-all duration-200">
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-200">
                        <i class="bi bi-file-earmark-text text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800">New Demande</p>
                        <p class="text-xs text-slate-500">Submit an academic or admin request</p>
                    </div>
                    <i class="bi bi-arrow-right text-slate-400 group-hover:text-indigo-500 transition-colors"></i>
                </a>

                <!-- New Réclamation -->
                <a href="/pfe/student/create_reclamation.php"
                   class="group flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50 hover:bg-rose-50 hover:border-rose-200 transition-all duration-200">
                    <div class="w-10 h-10 rounded-lg bg-rose-100 text-rose-500 flex items-center justify-center shrink-0 group-hover:bg-rose-500 group-hover:text-white transition-all duration-200">
                        <i class="bi bi-exclamation-triangle text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800">New Réclamation</p>
                        <p class="text-xs text-slate-500">Report an issue or concern</p>
                    </div>
                    <i class="bi bi-arrow-right text-slate-400 group-hover:text-rose-400 transition-colors"></i>
                </a>

                <!-- View Brouillons -->
                <a href="/pfe/student/drafts.php"
                   class="group flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50 hover:bg-violet-50 hover:border-violet-200 transition-all duration-200">
                    <div class="w-10 h-10 rounded-lg bg-violet-100 text-violet-600 flex items-center justify-center shrink-0 group-hover:bg-violet-600 group-hover:text-white transition-all duration-200">
                        <i class="bi bi-pencil-square text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800">View Brouillons</p>
                        <p class="text-xs text-slate-500"><?= (int)$stats['drafts'] ?> saved <?= (int)$stats['drafts'] === 1 ? 'draft' : 'drafts' ?></p>
                    </div>
                    <i class="bi bi-arrow-right text-slate-400 group-hover:text-violet-500 transition-colors"></i>
                </a>

            </div>
        </div>

        <!-- My Tickets Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-4">My Tickets</p>
            <div class="flex flex-col gap-3">

                <!-- Requests -->
                <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 shrink-0"></span>
                        <span class="text-sm font-medium text-slate-700">Demandes</span>
                    </div>
                    <span class="text-sm font-bold text-slate-800"><?= (int)$stats['total_demandes'] ?></span>
                </div>

                <!-- Complaints -->
                <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 shrink-0"></span>
                        <span class="text-sm font-medium text-slate-700">Réclamations</span>
                    </div>
                    <span class="text-sm font-bold text-slate-800"><?= (int)$stats['total_reclamations'] ?></span>
                </div>

                <!-- Drafts -->
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-violet-400 shrink-0"></span>
                        <span class="text-sm font-medium text-slate-700">Brouillons</span>
                    </div>
                    <span class="text-sm font-bold text-slate-800"><?= (int)$stats['drafts'] ?></span>
                </div>

            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN (3/5): Recent Tickets -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 h-full flex flex-col">

            <!-- Card Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Recent Tickets</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Your latest submissions</p>
                </div>
                <a href="/pfe/student/demandes.php"
                   class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition-colors">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <!-- Ticket List -->
            <?php if (empty($recent_tickets)): ?>
                <div class="flex-1 flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                        <i class="bi bi-inbox text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-500">No tickets yet</p>
                    <p class="text-xs text-slate-400 mt-1">Create your first request or complaint</p>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-slate-100 flex-1">
                    <?php
                        $badge_colors = [
                            'new'         => 'bg-blue-100 text-blue-700',
                            'opened'      => 'bg-purple-100 text-purple-700',
                            'in_progress' => 'bg-amber-100 text-amber-700',
                            'completed'   => 'bg-emerald-100 text-emerald-700',
                            'rejected'    => 'bg-rose-100 text-rose-700',
                        ];
                        $badge_labels = [
                            'new'         => 'New',
                            'opened'      => 'Opened',
                            'in_progress' => 'In Progress',
                            'completed'   => 'Completed',
                            'rejected'    => 'Rejected',
                        ];
                    ?>
                    <?php foreach ($recent_tickets as $t): ?>
                        <?php
                            $b_color = $badge_colors[$t['status']] ?? 'bg-slate-100 text-slate-600';
                            $b_label = $badge_labels[$t['status']] ?? ucfirst($t['status']);
                            $is_complaint = $t['type'] === 'complaint';
                        ?>
                        <li class="group hover:bg-slate-50 transition-colors duration-150">
                            <a href="/pfe/student/view_ticket.php?id=<?= $t['id'] ?>"
                               class="flex items-center px-6 py-4 gap-4">
                                <!-- Icon -->
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $is_complaint ? 'bg-rose-50 text-rose-500' : 'bg-indigo-50 text-indigo-500' ?>">
                                    <i class="bi <?= $is_complaint ? 'bi-exclamation-triangle' : 'bi-file-earmark-text' ?> text-sm"></i>
                                </div>
                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate"><?= e($t['subject']) ?></p>
                                    <p class="text-xs text-slate-400 mt-0.5 truncate">
                                        <?= e($t['category_name']) ?> &middot; <?= date('M d', strtotime($t['updated_at'])) ?>
                                    </p>
                                </div>
                                <!-- Badge + Arrow -->
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="hidden sm:inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $b_color ?>">
                                        <?= $b_label ?>
                                    </span>
                                    <i class="bi bi-chevron-right text-slate-300 text-xs group-hover:text-slate-500 transition-colors"></i>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </div>
    </div>

</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
