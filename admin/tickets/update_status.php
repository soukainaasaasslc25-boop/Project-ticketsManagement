<?php
// =============================================================================
// FILE    : admin/tickets/update_status.php
// PURPOSE : Handles unified POST action from admin/tickets/view.php
//
//   action = 'update' → Updates ticket status, optional reply, auto-transitions
//
// SECURITY:
//   - CSRF token verified
//   - Admin role verified
//   - Validations for status and rejection reason
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/tickets/index.php');
}

// ---------------------------------------------------------------------------
// CSRF verification
// ---------------------------------------------------------------------------
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['flash_error'] = 'Erreur de sécurité. Veuillez réessayer.';
    redirect('/admin/tickets/index.php');
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');
$admin_id  = (int) $_SESSION['user_id'];

if ($ticket_id <= 0 || $action !== 'update') {
    $_SESSION['flash_error'] = 'Action invalide.';
    redirect('/admin/tickets/index.php');
}

$stmt = $pdo->prepare('SELECT id, status, rejection_reason FROM tickets WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/admin/tickets/index.php');
}

$back_url = '/admin/tickets/view.php?id=' . $ticket_id;

// Abort if already closed
if (in_array($ticket['status'], ['completed', 'rejected'], true)) {
    $_SESSION['flash_error'] = 'Ce ticket est fermé. Aucune action supplémentaire n\'est autorisée.';
    redirect($back_url);
}

// ---------------------------------------------------------------------------
// Read Inputs
// ---------------------------------------------------------------------------
$new_status       = trim($_POST['status'] ?? '');
$message          = trim($_POST['message'] ?? '');
$is_internal      = isset($_POST['is_internal']) && $_POST['is_internal'] === '1' ? 1 : 0;
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

$allowed_statuses = ['new', 'opened', 'in_progress', 'completed', 'rejected'];
if (!in_array($new_status, $allowed_statuses, true)) {
    $_SESSION['flash_error'] = 'Statut invalide.';
    redirect($back_url);
}

if ($new_status === 'rejected' && mb_strlen($rejection_reason) < 5) {
    $_SESSION['flash_error'] = 'Veuillez saisir un motif de rejet (min. 5 caractères).';
    redirect($back_url);
}

// Auto-transition: First public reply on 'new' moves to 'opened' if no status change requested
if ($ticket['status'] === 'new' && $new_status === 'new' && $message !== '' && !$is_internal) {
    $new_status = 'opened';
}

$status_changed = ($new_status !== $ticket['status']);
$reason_changed = ($rejection_reason !== ($ticket['rejection_reason'] ?? ''));

// If absolutely nothing was done
if (!$status_changed && !$reason_changed && $message === '') {
    $_SESSION['flash_error'] = 'Aucune modification apportée.';
    redirect($back_url);
}

try {
    $pdo->beginTransaction();

    // 1. Update status
    if ($status_changed || $reason_changed) {
        $resolved_at = in_array($new_status, ['completed', 'rejected']) ? date('Y-m-d H:i:s') : null;
        $rejection_reason_db = ($new_status === 'rejected') ? $rejection_reason : null;

        $stmt = $pdo->prepare('
            UPDATE tickets
            SET status           = :status,
                rejection_reason = :rejection_reason,
                resolved_at      = :resolved_at,
                updated_at       = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':status'           => $new_status,
            ':rejection_reason' => $rejection_reason_db,
            ':resolved_at'      => $resolved_at,
            ':id'               => $ticket_id,
        ]);

        if ($status_changed) {
            $tr = [
                'new' => 'Nouveau', 'opened' => 'Ouvert', 'in_progress' => 'En cours',
                'completed' => 'Résolu', 'rejected' => 'Rejeté', 'draft' => 'Brouillon'
            ];
            $old_str = $tr[$ticket['status']] ?? $ticket['status'];
            $new_str = $tr[$new_status] ?? $new_status;

            $sys_msg = "[SYSTEM] Le statut a été modifié de **$old_str** à **$new_str**.";
            $stmt = $pdo->prepare('
                INSERT INTO ticket_responses (ticket_id, sender_id, message, is_internal, created_at, updated_at)
                VALUES (:tid, :sid, :msg, 0, NOW(), NOW())
            ');
            $stmt->execute([
                ':tid' => $ticket_id,
                ':sid' => $admin_id,
                ':msg' => $sys_msg
            ]);
        }
    }

    // 2. Insert message if provided
    if ($message !== '') {
        $stmt = $pdo->prepare('
            INSERT INTO ticket_responses (ticket_id, sender_id, message, is_internal, created_at, updated_at)
            VALUES (:ticket_id, :sender_id, :message, :is_internal, NOW(), NOW())
        ');
        $stmt->execute([
            ':ticket_id'   => $ticket_id,
            ':sender_id'   => $admin_id,
            ':message'     => $message,
            ':is_internal' => $is_internal,
        ]);

        if (!$status_changed) {
            $pdo->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$ticket_id]);
        }
    }

    $pdo->commit();

    $_SESSION['flash_success'] = 'Mise à jour effectuée avec succès.';
    redirect('/admin/tickets/index.php?status=' . urlencode($new_status));

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Une erreur est survenue lors de la mise à jour.';
    redirect($back_url);
}
