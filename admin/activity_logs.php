<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — UniPortal Admin</title>
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
        <h1 class="text-2xl font-bold text-slate-900">Activity Logs</h1>
        <p class="text-slate-500 text-sm mt-1">Monitor system events, logins, and administrative actions.</p>
    </div>
</div>

<!-- Placeholder Notice -->
<div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-6 text-center max-w-2xl mx-auto mt-12">
    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 text-3xl text-indigo-500 shadow-sm border border-indigo-100">
        <i class="bi bi-tools"></i>
    </div>
    <h3 class="text-lg font-bold text-slate-800 mb-2">Feature in Development</h3>
    <p class="text-slate-600 text-sm mb-6">The activity logging module is currently being built. Soon you will be able to track detailed audit trails for all system actions.</p>
    <a href="/pfe/admin/dashboard.php" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors shadow-sm">
        <i class="bi bi-arrow-left"></i> Return to Dashboard
    </a>
</div>

        </main>
    </div>
</div>
</body>
</html>
