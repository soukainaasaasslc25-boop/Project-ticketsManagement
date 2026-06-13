<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Student Services Portal</title>

    <!-- Tailwind CSS (utility-first CSS framework) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap Icons (used for small icons throughout the page) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Inter font from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        // Tell Tailwind to use the Inter font everywhere
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        /* Animated gradient background for the left panel */
        .left-panel {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 40%, #2563eb 70%, #1e40af 100%);
        }

        /* Subtle grid pattern overlay on the left panel */
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(255,255,255,0.06) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255,255,255,0.04) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glowing card effect behind the stats */
        .glow-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Input focus ring that matches the blue theme */
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
    </style>
</head>
<body class="font-sans antialiased">

<?php
// ==
// Read URL parameters set by process_login.php and logout.php
//
// ?error=empty_fields        → username or password was empty
// ?error=invalid_credentials → wrong username or password
// ?error=session_expired     → session timed out
// ?error=unauthorized        → student tried to access admin area
// ?message=logged_out        → successful logout redirect
// ==

$error_messages = [
    'empty_fields'         => 'Please enter your username and password.',
    'invalid_credentials'  => 'Incorrect username or password. Please try again.',
    'session_expired'      => 'Your session has expired. Please sign in again.',
    'unauthorized'         => 'Access denied. You have been redirected.',
];

$error_key = $_GET['error'] ?? '';
$message   = $_GET['message'] ?? '';
$error_msg = $error_messages[$error_key] ?? '';
?>

<!-- ============================================================ -->
<!-- FULL-HEIGHT SPLIT SCREEN LAYOUT                              -->
<!-- Left panel = blue gradient | Right panel = white form        -->
<!-- ============================================================ -->
<div class="min-h-screen flex flex-col lg:flex-row">


    <!-- ========================================================= -->
    <!-- LEFT PANEL — Branding, description, and stats             -->
    <!-- Hidden on small screens, visible on large screens (lg+)   -->
    <!-- ========================================================= -->
    <div class="left-panel relative hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between p-12 text-white overflow-hidden">

        <!-- Top section: badge + title + description -->
        <div class="relative z-10 flex-1 flex flex-col justify-center max-w-lg">

            <!-- Trust badge -->
            <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-sm font-medium mb-8 w-fit">
                <i class="bi bi-shield-check text-blue-200"></i>
                <span>Trusted by Students &amp; Administrators</span>
            </div>

            <!-- Main headline -->
            <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight mb-6 tracking-tight">
                Simplify Student<br>
                <span class="text-blue-200">Requests and Complaints</span><br>
                Management.
            </h1>

            <!-- Supporting description -->
            <p class="text-blue-100 text-lg leading-relaxed mb-12 max-w-md">
                Submit, track, and manage student requests and complaints through a secure and transparent platform designed for educational institutions.
            </p>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Stat 1: Tickets Resolved -->
                <div class="glow-card rounded-2xl p-5">
                    <p class="text-3xl font-bold mb-1">1,200+</p>
                    <p class="text-blue-200 text-sm font-medium">Tickets Resolved</p>
                </div>

                <!-- Stat 2: Student Satisfaction -->
                <div class="glow-card rounded-2xl p-5">
                    <p class="text-3xl font-bold mb-1">98%</p>
                    <p class="text-blue-200 text-sm font-medium">Student Satisfaction</p>
                </div>

            </div>
        </div>

        <!-- Bottom section: security note -->
        <div class="relative z-10 mt-8">
            <p class="text-blue-200/70 text-xs flex items-center gap-2">
                <i class="bi bi-lock-fill"></i>
                Secure platform — All data is encrypted and protected.
            </p>
        </div>

    </div>


    <!-- RIGHT PANEL — Login form                                   -->
    <!-- Full width on mobile, half width on large screens         -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-white">

        <!-- Form container (max width so it doesn't stretch too wide) -->
        <div class="w-full max-w-md">

            <!-- Portal logo icon + name -->
            <div class="mb-8">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-blue-200">
                    <i class="bi bi-mortarboard-fill text-white text-xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-900">Student Services Portal</h2>
                <p class="text-slate-500 text-sm mt-1">Sign in to access your student or administrator account.</p>
            </div>

            <!-- ------------------------------------------------- -->
            <!-- Error alert — shown when process_login.php         -->
            <!-- redirects back with ?error=... in the URL          -->
            <!-- ------------------------------------------------- -->
            <?php if ($error_msg): ?>
                <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl px-4 py-3 mb-5 text-sm">
                    <i class="bi bi-exclamation-circle-fill shrink-0"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <!-- ------------------------------------------------- -->
            <!-- Success alert — shown after successful logout      -->
            <!-- ------------------------------------------------- -->
            <?php if ($message === 'logged_out'): ?>
                <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 mb-5 text-sm">
                    <i class="bi bi-check-circle-fill shrink-0"></i>
                    <span>You have been signed out successfully.</span>
                </div>
            <?php endif; ?>

            <!-- ------------------------------------------------- -->
            <!-- Demo credentials box                               -->
            <!-- Remove this block before going to production       -->
            <!-- ------------------------------------------------- -->
            <!-- <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-6 text-sm">
                <p class="font-semibold text-blue-800 mb-1">
                    <i class="bi bi-info-circle me-1"></i> Test accounts:
                </p>
                <p class="text-blue-700">Admin: <strong>admin</strong> / <strong>Admin@2025</strong></p>
                <p class="text-blue-700">Student: <strong>asaassoukaina</strong> / <strong>Student@123</strong></p>
            </div> -->

            <!-- ------------------------------------------------- -->
            <!-- LOGIN FORM                                         -->
            <!-- action and method must not be changed             -->
            <!-- ------------------------------------------------- -->
            <form action="/pfe/auth/process_login.php" method="POST" id="loginForm" novalidate>

                <!-- Username field -->
                <div class="mb-4">
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Username
                    </label>
                    <div class="relative">
                        <!-- Left icon inside the input -->
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <i class="bi bi-at"></i>
                        </span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            value="<?= htmlspecialchars($_GET['username'] ?? '') ?>"
                            autocomplete="username"
                            required
                            class="form-input w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 bg-slate-50 transition-all"
                        >
                    </div>
                </div>

                <!-- Password field -->
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="password" class="text-sm font-semibold text-slate-700">
                            Password
                        </label>
                        <!-- Forgot password link — update href if you add a reset page -->
                        <a href="#" class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline transition-colors">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <!-- Left icon inside the input -->
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            class="form-input w-full pl-9 pr-11 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-800 placeholder-slate-400 bg-slate-50 transition-all"
                        >
                        <!-- Toggle show/hide password button (right side of input) -->
                        <button
                            type="button"
                            id="togglePw"
                            title="Show / Hide password"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <i class="bi bi-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me checkbox -->
                <!-- name="remember_me" must stay unchanged for process_login.php -->
                <div class="flex items-center gap-2 mb-6">
                    <input
                        type="checkbox"
                        id="remember_me"
                        name="remember_me"
                        value="1"
                        class="w-4 h-4 rounded border-slate-300 text-blue-600 accent-blue-600 cursor-pointer"
                    >
                    <label for="remember_me" class="text-sm text-slate-600 cursor-pointer select-none">
                        Remember me for 30 days
                    </label>
                </div>

                <!-- Sign In button -->
                <button
                    type="submit"
                    id="loginBtn"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold text-sm py-3 rounded-xl shadow-md shadow-blue-200 transition-all duration-200 flex items-center justify-center gap-2"
                >
                    <!-- Normal state text -->
                    <span id="btnText" class="flex items-center gap-2">
                        Sign In to Portal
                        <i class="bi bi-arrow-right"></i>
                    </span>
                    <!-- Loading state (shown after form submit, hidden by default) -->
                    <span id="btnLoading" class="hidden items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        Signing in...
                    </span>
                </button>

            </form>

            <!-- Footer note -->
            <p class="text-center text-xs text-slate-400 mt-8">
                <i class="bi bi-shield-lock me-1"></i>
                Secure connection — Educational Institution Platform
            </p>

        </div>
    </div>

</div>

<script>
// 
// Toggle password visibility
// Clicking the eye icon switches between showing and hiding the password
// 
document.getElementById('togglePw').addEventListener('click', function () {
    const pwField = document.getElementById('password');
    const icon    = document.getElementById('togglePwIcon');

    if (pwField.type === 'password') {
        // Show password
        pwField.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        // Hide password
        pwField.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

// 
// Show loading spinner when the form is submitted
// Prevents double-clicking and gives the user visual feedback
// 
document.getElementById('loginForm').addEventListener('submit', function (e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    // Stop submission if fields are empty (client-side check)
    if (!username || !password) {
        e.preventDefault();
        return;
    }

    // Show spinner, hide the normal button text
    document.getElementById('btnText').classList.add('hidden');
    document.getElementById('btnLoading').classList.remove('hidden');
    document.getElementById('btnLoading').classList.add('flex');
    document.getElementById('loginBtn').disabled = true;
});
</script>

</body>
</html>
