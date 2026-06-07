<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_admin();
require_once __DIR__ . '/../config/database.php';

// Stats queries
$stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM tickets WHERE status != 'draft' GROUP BY status");
$tickets_by_status = [];
$total_tickets = 0;
foreach ($stmt->fetchAll() as $row) {
    $tickets_by_status[$row['status']] = (int)$row['cnt'];
    $total_tickets += (int)$row['cnt'];
}
$new_count = $tickets_by_status['new'] ?? 0;
$opened_count = $tickets_by_status['opened'] ?? 0;
$in_progress_count = $tickets_by_status['in_progress'] ?? 0;
$completed_count = $tickets_by_status['completed'] ?? 0;
$rejected_count = $tickets_by_status['rejected'] ?? 0;

// Type chart data
$stmt = $pdo->query("SELECT type, COUNT(*) as count FROM tickets WHERE status != 'draft' GROUP BY type");
$type_data = $stmt->fetchAll();
$type_counts = ['request' => 0, 'complaint' => 0];
foreach ($type_data as $row) {
    $type_counts[$row['type']] = (int)$row['count'];
}

// Category chart data
$stmt = $pdo->query("SELECT c.name, COUNT(*) as count FROM tickets t JOIN categories c ON c.id = t.category_id WHERE t.status != 'draft' GROUP BY c.name");
$cat_data = $stmt->fetchAll();
$cat_labels = json_encode(array_column($cat_data, 'name'));
$cat_counts = json_encode(array_column($cat_data, 'count'));

// Monthly activity data
$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND status != 'draft' GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y') ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC");
$monthly_data = $stmt->fetchAll();
$monthly_labels = json_encode(array_column($monthly_data, 'month'));
$monthly_counts = json_encode(array_column($monthly_data, 'count'));

// Most Active Students
$stmt = $pdo->query("SELECT u.first_name, u.last_name, COUNT(t.id) as ticket_count FROM users u JOIN tickets t ON u.id = t.user_id WHERE u.role = 'student' AND t.status != 'draft' GROUP BY u.id ORDER BY ticket_count DESC LIMIT 5");
$active_students = $stmt->fetchAll();

// Recent tickets
$stmt = $pdo->query("
    SELECT t.id, t.reference, t.status, t.priority, t.subject, t.type,
           t.updated_at, u.first_name, u.last_name
    FROM tickets t
    JOIN users u ON u.id = t.user_id
    WHERE t.status != 'draft'
    ORDER BY t.updated_at DESC
    LIMIT 5
");
$recent_tickets = $stmt->fetchAll();

$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — UniPortal Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Dashboard Overview</h1>
        <p class="text-slate-500 text-sm mt-1">Platform statistics and recent activity.</p>
    </div>
    <div class="flex gap-3">
        <a href="/pfe/admin/tickets/index.php" class="bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-xl text-sm font-semibold transition-all">
            View All Tickets
        </a>
    </div>
</div>

<?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-emerald-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_success ?></div>
    </div>
<?php endif; ?>

<!-- ===== Stats Grid: 6 status summary cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <?php
    // Each item: [label, value, icon class, icon background + color]
    $stats_cards = [
        ['Total Tickets', $total_tickets,    'bi-collection',   'bg-indigo-50 text-indigo-600'],
        ['New',           $new_count,         'bi-clock',        'bg-blue-50 text-blue-600'],
        ['Opened',        $opened_count,      'bi-folder2-open', 'bg-purple-50 text-purple-600'],
        ['In Progress',   $in_progress_count, 'bi-arrow-repeat', 'bg-amber-50 text-amber-600'],
        ['Completed',     $completed_count,   'bi-check-circle', 'bg-emerald-50 text-emerald-600'],
        ['Rejected',      $rejected_count,    'bi-x-circle',     'bg-rose-50 text-rose-600'],
    ];
    foreach ($stats_cards as [$label, $val, $icon, $bg]):
    ?>
    <!-- Single stat card (same style as Student Dashboard) -->
    <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl <?= $bg ?> flex items-center justify-center text-lg shrink-0">
            <i class="bi <?= $icon ?>"></i>
        </div>
        <div>
            <p class="text-xs font-medium text-slate-500"><?= $label ?></p>
            <h3 class="text-xl font-bold text-slate-800"><?= $val ?></h3>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== Main Dashboard: Two-column layout (left 3/5, right 2/5) ===== -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">

    <!-- ===== LEFT COLUMN: Recent Tickets + Most Used Categories ===== -->
    <div class="lg:col-span-3 flex flex-col gap-6">

        <!-- CARD: Recent Tickets (most important card on the page) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col">

            <!-- Card header -->
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Recent Tickets</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Latest submitted tickets</p>
                </div>
                <a href="/pfe/admin/tickets/index.php"
                   class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition-colors">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <!-- Ticket rows -->
            <?php if (empty($recent_tickets)): ?>
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mb-3">
                        <i class="bi bi-inbox text-xl text-slate-400"></i>
                    </div>
                    <p class="text-sm text-slate-500">No tickets yet</p>
                </div>
            <?php else: ?>
                <?php
                // Badge colors and labels per status
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
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($recent_tickets as $ticket): ?>
                        <?php
                            $b_color      = $badge_colors[$ticket['status']] ?? 'bg-slate-100 text-slate-600';
                            $b_label      = $badge_labels[$ticket['status']] ?? ucfirst($ticket['status']);
                            $is_complaint = $ticket['type'] === 'complaint';
                        ?>
                        <li class="group hover:bg-slate-50 transition-colors duration-150">
                            <a href="/pfe/admin/tickets/view.php?id=<?= $ticket['id'] ?>"
                               class="flex items-center px-6 py-4 gap-4">

                                <!-- Type icon (complaint = rose, request = indigo) -->
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $is_complaint ? 'bg-rose-50 text-rose-500' : 'bg-indigo-50 text-indigo-500' ?>">
                                    <i class="bi <?= $is_complaint ? 'bi-exclamation-triangle' : 'bi-file-earmark-text' ?> text-sm"></i>
                                </div>

                                <!-- Subject + reference + student name -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate"><?= e($ticket['subject']) ?></p>
                                    <p class="text-xs text-slate-400 mt-0.5 truncate">
                                        <?= e($ticket['reference']) ?> &middot; <?= e($ticket['first_name'] . ' ' . $ticket['last_name']) ?>
                                    </p>
                                </div>

                                <!-- Status badge + date + chevron -->
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="hidden sm:flex flex-col items-end gap-1">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $b_color ?>">
                                            <?= $b_label ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?= date('M d', strtotime($ticket['updated_at'])) ?></span>
                                    </div>
                                    <i class="bi bi-chevron-right text-slate-300 text-xs group-hover:text-slate-500 transition-colors"></i>
                                </div>

                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </div>

        <!-- CARD: Most Used Categories with progress bars -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">

            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-4">Most Used Categories</p>

            <?php if (empty($cat_data)): ?>
                <p class="text-sm text-slate-500 text-center py-6">No category data yet.</p>
            <?php else: ?>
                <?php
                // Find the highest ticket count to scale progress bars
                $max_cat = max(array_column($cat_data, 'count'));
                ?>
                <div class="flex flex-col gap-4">
                    <?php foreach ($cat_data as $cat): ?>
                        <?php
                            // Percentage relative to the most-used category
                            $pct = $max_cat > 0 ? round(($cat['count'] / $max_cat) * 100) : 0;
                        ?>
                        <div>
                            <!-- Category name and count -->
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-sm font-medium text-slate-700 truncate"><?= e($cat['name']) ?></span>
                                <span class="text-xs font-bold text-slate-500 ml-2 shrink-0"><?= $cat['count'] ?></span>
                            </div>
                            <!-- Progress bar -->
                            <div class="w-full bg-slate-100 rounded-full h-1.5">
                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- ===== RIGHT COLUMN: Most Active Students + Status Overview ===== -->
    <div class="lg:col-span-2 flex flex-col gap-6">

        <!-- CARD: Most Active Students -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">

            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-4">Most Active Students</p>

            <?php if (empty($active_students)): ?>
                <p class="text-sm text-slate-500 text-center py-6">No student activity yet.</p>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <?php foreach ($active_students as $student): ?>
                        <div class="flex items-center justify-between">

                            <!-- Avatar initials + full name -->
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                                    <?= mb_strtoupper(mb_substr($student['first_name'], 0, 1) . mb_substr($student['last_name'], 0, 1)) ?>
                                </div>
                                <span class="text-sm font-medium text-slate-700 truncate">
                                    <?= e($student['first_name'] . ' ' . $student['last_name']) ?>
                                </span>
                            </div>

                            <!-- Ticket count pill -->
                            <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-full shrink-0">
                                <?= $student['ticket_count'] ?>
                            </span>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- CARD: Ticket Status Overview table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">

            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-4">Status Overview</p>

            <?php
            // Each row: [display label, count variable, badge color classes]
            $status_rows = [
                ['New',         $new_count,         'bg-blue-100 text-blue-700'],
                ['Opened',      $opened_count,       'bg-purple-100 text-purple-700'],
                ['In Progress', $in_progress_count,  'bg-amber-100 text-amber-700'],
                ['Completed',   $completed_count,    'bg-emerald-100 text-emerald-700'],
                ['Rejected',    $rejected_count,     'bg-rose-100 text-rose-700'],
            ];
            ?>
            <div class="flex flex-col">
                <?php foreach ($status_rows as [$label, $count, $badge_class]): ?>
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                        <!-- Colored status badge -->
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $badge_class ?>">
                            <?= $label ?>
                        </span>
                        <!-- Count number -->
                        <span class="text-sm font-bold text-slate-800"><?= $count ?></span>
                    </div>
                <?php endforeach; ?>

                <!-- Total row at the bottom -->
                <div class="flex items-center justify-between pt-3">
                    <span class="text-sm font-semibold text-slate-500">Total</span>
                    <span class="text-sm font-bold text-slate-800"><?= $total_tickets ?></span>
                </div>
            </div>

        </div>

    </div>

</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
