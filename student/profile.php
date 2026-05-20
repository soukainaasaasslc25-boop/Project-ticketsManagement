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
        $_SESSION['flash_error'] = 'Erreur de sécurité CSRF.';
        redirect('/student/profile.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_username') {
        $new_username = mb_strtolower(str_replace(' ', '', $_POST['username'] ?? ''), 'UTF-8');
        
        if (mb_strlen($new_username) < 5) {
            $_SESSION['flash_error'] = "Le nom d'utilisateur doit contenir au moins 5 caractères.";
        } else {
            // Check uniqueness
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$new_username, $student_id]);
            if ($check->fetch()) {
                $_SESSION['flash_error'] = "This username is already taken. Veuillez en choisir un autre.";
            } else {
                $upd = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $upd->execute([$new_username, $student_id]);
                $_SESSION['username'] = $new_username; // update session
                $_SESSION['flash_success'] = "Nom d'utilisateur mis à jour avec succès.";
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
            $_SESSION['flash_error'] = "Mot de passe actuel incorrect.";
        } elseif (mb_strlen($new_pass) < 8) {
            $_SESSION['flash_error'] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash_error'] = "Les mots de passe ne correspondent pas.";
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
            $upd->execute([$new_hash, $student_id]);
            
            // Update session
            $_SESSION['must_change_password'] = 0;
            $_SESSION['flash_success'] = "Mot de passe mis à jour avec succès. Votre compte est sécurisé.";
            
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — Espace Étudiant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">

    <?php if ($first_login || $user['must_change_password'] == 1): ?>
        <div class="alert alert-warning d-flex align-items-start gap-3 border-warning mb-4" style="border-radius: 12px; background: #fffbeb;">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
            <div>
                <h5 class="mb-1 fw-bold text-dark">Sécurité de votre compte</h5>
                <p class="mb-0 text-muted" style="font-size: 0.95rem;">For security reasons, we recommend changing your password before continuing. Vous utilisez actuellement le mot de passe par défaut.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-1">Mon Profil</h3>
        <p class="text-muted mb-0">Gérez vos informations personnelles et paramètres de sécurité.</p>
    </div>

    <!-- Alerts -->
    <?php if ($flash_success): ?>
        <div class="alert alert-success d-flex align-items-center gap-3" style="border-radius: 12px;"><i class="bi bi-check-circle-fill fs-5"></i><div><?= $flash_success ?></div></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-3" style="border-radius: 12px;"><i class="bi bi-exclamation-triangle-fill fs-5"></i><div><?= $flash_error ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Left Col: Profile Info -->
        <div class="col-md-4">
            <div class="card-custom text-center p-4">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                    <?= mb_strtoupper(mb_substr($user['first_name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <div class="badge bg-light text-secondary border font-monospace px-3 py-2 mb-4 fs-6">@<?= e($user['username']) ?></div>
                
                <hr class="mb-4">
                
                <div class="text-start">
                    <div class="mb-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Groupe</div>
                        <div class="fw-semibold text-dark"><?= e($user['group_name'] ?: 'Non assigné') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Filière</div>
                        <div class="fw-semibold text-dark"><?= e($user['filiere'] ?: 'Non définie') ?></div>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Statut du compte</div>
                        <?php if ($user['account_status'] === 'active'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 border border-success border-opacity-25 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Actif</span>
                        <?php else: ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 border border-warning border-opacity-25 rounded-pill"><i class="bi bi-exclamation-circle-fill me-1"></i> Inactif</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Col: Forms -->
        <div class="col-md-8">
            
            <!-- Edit Username -->
            <div class="card-custom mb-4">
                <div class="card-header-custom bg-light">
                    <span><i class="bi bi-person-badge text-primary me-2"></i> Modifier le nom d'utilisateur</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/pfe/student/profile.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="update_username">
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Nouveau nom d'utilisateur</label>
                            <input type="text" name="username" value="<?= e($user['username']) ?>" required minlength="5" class="form-control" style="border-radius: 10px;">
                            <div class="form-text">Minimum 5 caractères. Les espaces seront automatiquement supprimés et le texte mis en minuscules.</div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-dark" style="border-radius: 10px;">Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Password -->
            <div class="card-custom">
                <div class="card-header-custom bg-light">
                    <span><i class="bi bi-key text-primary me-2"></i> Changer le mot de passe</span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/pfe/student/profile.php<?= isset($_GET['first_login']) ? '?first_login=1' : '' ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="update_password">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mot de passe actuel</label>
                            <input type="password" name="current_password" required class="form-control" style="border-radius: 10px;">
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Nouveau mot de passe</label>
                                <input type="password" name="new_password" required minlength="8" class="form-control" style="border-radius: 10px;">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" required minlength="8" class="form-control" style="border-radius: 10px;">
                            </div>
                        </div>
                        <div class="form-text mb-4">Le mot de passe doit contenir au moins 8 caractères.</div>
                        
                        <div class="text-end border-top pt-4">
                            <button type="submit" class="btn btn-primary shadow-sm" style="border-radius: 10px;">
                                <i class="bi bi-shield-check"></i> Enregistrer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
