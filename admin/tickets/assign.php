<?php
// =============================================================================
// FILE    : admin/tickets/assign.php
// PURPOSE : Handles admin ticket assignment via POST from view.php
//           Updates the assigned_to column on the ticket.
//           Admin can:
//             - Assign to any admin (including themselves)
//             - Unassign (set to NULL) by choosing "Non assigné"
//
// SECURITY:
//   - CSRF token verified
//   - require_admin() guards the page
//   - assigned_to value is either NULL or an existing admin user ID
//

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/tickets/index.php');
}

// ---------------------------------------------------------------------------
// STEP 1 — CSRF check
// ---------------------------------------------------------------------------
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['flash_error'] = 'Erreur de sécurité. Veuillez réessayer.';
    redirect('/admin/tickets/index.php');
}

// ---------------------------------------------------------------------------
// STEP 2 — Read and validate inputs
// ---------------------------------------------------------------------------
$ticket_id   = (int) ($_POST['ticket_id']   ?? 0);
// assigned_to can be empty string (unassign) or an integer admin ID
$raw_assign  = $_POST['assigned_to'] ?? '';
$assigned_to = ($raw_assign !== '') ? (int) $raw_assign : null;

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Ticket invalide.';
    redirect('/admin/tickets/index.php');
}

$back_url = '/admin/tickets/view.php?id=' . $ticket_id;

// ---------------------------------------------------------------------------
// STEP 3 — Verify the ticket exists
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare('SELECT id FROM tickets WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $ticket_id]);
if (!$stmt->fetch()) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/admin/tickets/index.php');
}

// ---------------------------------------------------------------------------
// STEP 4 — If assigning to someone, verify that user is actually an admin
// ---------------------------------------------------------------------------
if ($assigned_to !== null) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'admin' LIMIT 1");
    $stmt->execute([':id' => $assigned_to]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = 'L\'utilisateur sélectionné n\'est pas un administrateur valide.';
        redirect($back_url);
    }
}

// ---------------------------------------------------------------------------
// STEP 5 — Update assigned_to on the ticket
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare('
    UPDATE tickets
    SET assigned_to = :assigned_to,
        updated_at  = NOW()
    WHERE id = :id
');
$stmt->execute([
    ':assigned_to' => $assigned_to, // NULL or admin user ID
    ':id'          => $ticket_id,
]);

// ---------------------------------------------------------------------------
// STEP 6 — Redirect with success message
// ---------------------------------------------------------------------------
if ($assigned_to === null) {
    $_SESSION['flash_success'] = 'Le ticket a été désassigné.';
} else {
    // Fetch the assigned admin's name for the message
    $stmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $assigned_to]);
    $admin_user = $stmt->fetch();
    $admin_name = $admin_user
        ? e($admin_user['first_name']) . ' ' . e($admin_user['last_name'])
        : 'l\'administrateur';
    $_SESSION['flash_success'] = "Ticket assigné à <strong>{$admin_name}</strong> avec succès.";
}

redirect($back_url);
