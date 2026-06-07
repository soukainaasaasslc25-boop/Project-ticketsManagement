<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student_id = (int) $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Security error (CSRF). Please try again.';
        redirect('/student/profile.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_username') {
        $new_username = mb_strtolower(str_replace(' ', '', $_POST['username'] ?? ''), 'UTF-8');
        
        if (mb_strlen($new_username) < 5) {
            $_SESSION['flash_error'] = "Username must be at least 5 characters long.";
        } else {
            // Check uniqueness
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$new_username, $student_id]);
            if ($check->fetch()) {
                $_SESSION['flash_error'] = "This username is already taken. Please choose another.";
            } else {
                $upd = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $upd->execute([$new_username, $student_id]);
                $_SESSION['username'] = $new_username; // update session
                $_SESSION['flash_success'] = "Username updated successfully.";
            }
        }
        redirect('/student/profile.php');
    }

    if ($action === 'update_password') {
        // Fetch current password hash
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$student_id]);
        $curr = $stmt->fetch();

        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_pass, $curr['password_hash'])) {
            $_SESSION['flash_error'] = "Current password is incorrect.";
        } elseif (mb_strlen($new_pass) < 8) {
            $_SESSION['flash_error'] = "New password must be at least 8 characters long.";
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash_error'] = "New passwords do not match.";
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
            $upd->execute([$new_hash, $student_id]);
            
            // Update session
            $_SESSION['must_change_password'] = 0;
            $_SESSION['flash_success'] = "Password updated successfully. Your account is secure.";
            
            // Redirect to dashboard after successful first-time setup or regular update
            redirect('/student/dashboard.php');
        }
        redirect('/student/profile.php' . (isset($_GET['first_login']) ? '?first_login=1' : ''));
    }
}

// Load full user details for view
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "student" LIMIT 1');
$stmt->execute([$student_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('/auth/logout.php');
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$first_login = isset($_GET['first_login']) && $_GET['first_login'] == 1;

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — UniPortal</title>
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
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Profile Settings</h1>
    <p class="text-slate-500 text-sm mt-1">Manage your personal information and security preferences</p>
</div>

<?php if ($first_login || $user['must_change_password'] == 1): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-4">
        <div class="bg-amber-100 text-amber-600 p-2 rounded-xl shrink-0"><i class="bi bi-shield-lock-fill text-xl"></i></div>
        <div>
            <h3 class="text-amber-800 font-semibold mb-1">Account Security</h3>
            <p class="text-amber-700 text-sm">For security reasons, we recommend changing your password before continuing. You are currently using the default password.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-emerald-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_success ?></div>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_error ?></div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Personal Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-8 text-center border-b border-slate-100">
                <!-- Avatar -->
                <div class="w-24 h-24 rounded-full bg-indigo-600 text-white flex items-center justify-center text-3xl font-bold mx-auto mb-4 shadow-lg shadow-indigo-200">
                    <?= $initials ?>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-sm font-mono mb-4">
                    @<?= e($user['username']) ?>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50/50 space-y-4">
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Group</h4>
                    <p class="text-sm font-semibold text-slate-700"><?= e($user['group_name'] ?: 'Unassigned') ?></p>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Field of Study</h4>
                    <p class="text-sm font-semibold text-slate-700"><?= e($user['filiere'] ?: 'Undefined') ?></p>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Account Status</h4>
                    <?php if ($user['account_status'] === 'active'): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">
                            <i class="bi bi-check-circle-fill me-1.5"></i> Active
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">
                            <i class="bi bi-exclamation-circle-fill me-1.5"></i> Inactive
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Settings Forms -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Username Settings -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/50">
                <i class="bi bi-person-badge text-indigo-500 text-lg"></i>
                <h3 class="font-bold text-slate-800">Edit Username</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="/pfe/student/profile.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="update_username">
                    
                    <div class="mb-4 max-w-md">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">New Username</label>
                        <input type="text" name="username" value="<?= e($user['username']) ?>" required minlength="5" 
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-shadow text-sm">
                        <p class="text-xs text-slate-500 mt-2">Minimum 5 characters. Spaces will be removed and text converted to lowercase.</p>
                    </div>
                    
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-2 rounded-xl text-sm font-semibold shadow-sm transition-colors">
                            Update Username
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Settings -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 bg-slate-50/50">
                <i class="bi bi-shield-lock text-indigo-500 text-lg"></i>
                <h3 class="font-bold text-slate-800">Change Password</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="/pfe/student/profile.php<?= isset($_GET['first_login']) ? '?first_login=1' : '' ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="space-y-4 max-w-xl">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-shadow text-sm">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">New Password</label>
                                <input type="password" name="new_password" required minlength="8" 
                                       class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-shadow text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8" 
                                       class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-shadow text-sm">
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Password must be at least 8 characters long.</p>
                    </div>
                    
                    <div class="flex justify-end pt-6 mt-4 border-t border-slate-100">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm shadow-indigo-200 transition-colors flex items-center gap-2">
                            <i class="bi bi-shield-check"></i> Save New Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
