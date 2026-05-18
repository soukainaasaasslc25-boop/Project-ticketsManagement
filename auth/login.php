<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Système de Tickets</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ------------------------------------------------------------------ */
        /* Base & Background                                                    */
        /* ------------------------------------------------------------------ */
        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            padding: 1rem;
        }

        /* Subtle animated background dots */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(59,130,246,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(99,102,241,0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        /* ------------------------------------------------------------------ */
        /* Login Card                                                           */
        /* ------------------------------------------------------------------ */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }

        /* Colored header bar at top of card */
        .login-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            color: white;
        }

        .login-header .logo-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
        }

        .login-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0 0 0.3rem;
            letter-spacing: -0.3px;
        }

        .login-header p {
            font-size: 0.85rem;
            margin: 0;
            opacity: 0.85;
        }

        /* ------------------------------------------------------------------ */
        /* Form Body                                                            */
        /* ------------------------------------------------------------------ */
        .login-body {
            padding: 2rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .input-group-text {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #64748b;
        }

        .form-control {
            border-color: #e2e8f0;
            font-size: 0.95rem;
            padding: 0.6rem 0.85rem;
            color: #1e293b;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        /* Show/hide password toggle button */
        .btn-toggle-pw {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-left: none;
            color: #64748b;
            cursor: pointer;
            padding: 0.6rem 0.85rem;
            transition: all 0.2s;
        }
        .btn-toggle-pw:hover { background: #e2e8f0; color: #1e293b; }

        /* ------------------------------------------------------------------ */
        /* Submit Button                                                        */
        /* ------------------------------------------------------------------ */
        .btn-login {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem;
            border-radius: 10px;
            width: 100%;
            letter-spacing: 0.3px;
            transition: all 0.25s;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.4);
            color: white;
        }

        .btn-login:active { transform: translateY(0); }

        /* ------------------------------------------------------------------ */
        /* Alerts                                                               */
        /* ------------------------------------------------------------------ */
        .alert {
            font-size: 0.88rem;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ------------------------------------------------------------------ */
        /* Remember me + footer                                                 */
        /* ------------------------------------------------------------------ */
        .form-check-label {
            font-size: 0.88rem;
            color: #64748b;
        }

        .form-check-input:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        .login-footer {
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: 1rem 2rem;
            text-align: center;
        }

        .login-footer p {
            font-size: 0.8rem;
            color: #94a3b8;
            margin: 0;
        }

        /* ------------------------------------------------------------------ */
        /* Demo credentials box                                                 */
        /* ------------------------------------------------------------------ */
        .demo-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
        }

        .demo-box p {
            font-size: 0.8rem;
            margin: 0;
            color: #1e40af;
        }

        .demo-box strong { color: #1e3a8a; }

        /* ------------------------------------------------------------------ */
        /* Loading spinner on button                                            */
        /* ------------------------------------------------------------------ */
        .btn-login .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 2px;
        }
    </style>
</head>
<body>

<?php
// =============================================================================
// Login page: display error or success messages from URL parameters.
//
// URL parameters set by process_login.php and logout.php:
//   ?error=empty_fields        → username or password was empty
//   ?error=invalid_credentials → wrong username or password
//   ?error=session_expired     → session timed out
//   ?error=unauthorized        → student tried to access admin area
//   ?message=logged_out        → successful logout
// =============================================================================

$error_messages = [
    'empty_fields'         => 'Veuillez saisir votre identifiant et votre mot de passe.',
    'invalid_credentials'  => 'Identifiant ou mot de passe incorrect. Veuillez réessayer.',
    'session_expired'      => 'Votre session a expiré. Veuillez vous reconnecter.',
    'unauthorized'         => 'Accès non autorisé. Vous avez été redirigé.',
];

$error_key = $_GET['error'] ?? '';
$message   = $_GET['message'] ?? '';
$error_msg = $error_messages[$error_key] ?? '';
?>

    <div class="login-card">

        <!-- ================================================================ -->
        <!-- Card Header                                                        -->
        <!-- ================================================================ -->
        <div class="login-header">
            <div class="logo-icon">
                <i class="bi bi-ticket-perforated-fill"></i>
            </div>
            <h1>Système de Tickets</h1>
            <p>Demandes &amp; Réclamations Étudiants</p>
        </div>

        <!-- ================================================================ -->
        <!-- Card Body — Login Form                                             -->
        <!-- ================================================================ -->
        <div class="login-body">

            <!-- Error Alert -->
            <?php if ($error_msg): ?>
                <div class="alert alert-danger mb-3">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Success Alert (after logout) -->
            <?php if ($message === 'logged_out'): ?>
                <div class="alert alert-success mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                    Vous avez été déconnecté avec succès.
                </div>
            <?php endif; ?>

            <!-- Demo Credentials Box (remove in production) -->
            <div class="demo-box">
                <p><strong><i class="bi bi-info-circle me-1"></i>Comptes de test :</strong></p>
                <p>Admin : <strong>admin</strong> / <strong>Admin@2025</strong></p>
                <p>Étudiant : <strong>asaassoukaina</strong> / <strong>Student@123</strong></p>
            </div>

            <!-- Login Form -->
            <!-- action points to process_login.php which handles the POST -->
            <form action="/pfe/auth/process_login.php" method="POST" id="loginForm" novalidate>

                <!-- Username Field -->
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="bi bi-person me-1"></i>Identifiant
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-at"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="Entrez votre identifiant"
                            value="<?= htmlspecialchars($_GET['username'] ?? '') ?>"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>Mot de passe
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-key"></i>
                        </span>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Entrez votre mot de passe"
                            autocomplete="current-password"
                            required
                        >
                        <!-- Toggle show/hide password -->
                        <button type="button" class="btn-toggle-pw" id="togglePw"
                                title="Afficher/Masquer le mot de passe">
                            <i class="bi bi-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input"
                           id="remember_me" name="remember_me" value="1">
                    <label class="form-check-label" for="remember_me">
                        Se souvenir de moi pendant 30 jours
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <span id="btnText">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                    </span>
                    <span id="btnLoading" class="d-none">
                        <span class="spinner-border" role="status"></span>
                        Connexion en cours...
                    </span>
                </button>

            </form>
        </div>

        <!-- ================================================================ -->
        <!-- Card Footer                                                        -->
        <!-- ================================================================ -->
        <div class="login-footer">
            <p>
                <i class="bi bi-shield-check me-1"></i>
                Plateforme sécurisée — Établissement de formation
            </p>
        </div>

    </div>

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ===========================================================================
// Toggle password visibility
// Clicking the eye icon shows/hides the password field content
// ===========================================================================
document.getElementById('togglePw').addEventListener('click', function () {
    const pwField = document.getElementById('password');
    const icon    = document.getElementById('togglePwIcon');

    if (pwField.type === 'password') {
        pwField.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwField.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

// ===========================================================================
// Show loading spinner on form submit
// Prevents double-clicking and gives visual feedback
// ===========================================================================
document.getElementById('loginForm').addEventListener('submit', function (e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    // Client-side validation before submitting
    if (!username || !password) {
        e.preventDefault();
        return;
    }

    // Show spinner, hide normal button text
    document.getElementById('btnText').classList.add('d-none');
    document.getElementById('btnLoading').classList.remove('d-none');
    document.getElementById('loginBtn').disabled = true;
});
</script>

</body>
</html>
