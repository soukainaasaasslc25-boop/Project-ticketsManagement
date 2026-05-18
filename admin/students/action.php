<?php
// =============================================================================
// FILE    : admin/students/action.php
// PURPOSE : Handle POST actions for students (activate, deactivate, delete)
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/students/index.php');
}

// ---------------------------------------------------------------------------
// CSRF Validation
// ---------------------------------------------------------------------------
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['flash_error'] = 'Erreur de sécurité CSRF.';
    redirect('/admin/students/index.php');
}

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    $_SESSION['flash_error'] = 'Action invalide.';
    redirect('/admin/students/index.php');
}

// ---------------------------------------------------------------------------
// Security Check: Target must be a student
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['flash_error'] = 'Étudiant introuvable ou vous n\'avez pas les droits de modifier cet utilisateur.';
    redirect('/admin/students/index.php');
}

$name = e($student['first_name'] . ' ' . $student['last_name']);

// ---------------------------------------------------------------------------
// Execute Action
// ---------------------------------------------------------------------------
try {
    if ($action === 'delete') {
        // Warning: Requires ON DELETE CASCADE on tickets.user_id if the user has tickets
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = "Le compte de l'étudiant <strong>$name</strong> a été supprimé définitivement.";
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = "Le compte de <strong>$name</strong> est désormais actif.";
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = "Le compte de <strong>$name</strong> a été désactivé.";
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Erreur SQL : Impossible d'effectuer cette action (il y a peut-être des données liées).";
}

redirect('/admin/students/index.php');
