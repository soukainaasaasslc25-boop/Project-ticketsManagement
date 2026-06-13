<?php
// =============================================================================
// FILE    : student/delete_draft.php
// PURPOSE : Deletes a student's DRAFT ticket permanently.
// SECURITY RULES:
//   - CSRF token verified
//   - Ticket must belong to logged-in student
//   - ONLY tickets with status = 'draft' can be deleted here
//   - Students cannot delete submitted/in-progress/completed tickets

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/drafts.php');
}

// ---------------------------------------------------------------------------
// STEP 1 — CSRF Verification
// ---------------------------------------------------------------------------
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token   = $_SESSION['csrf_token'] ?? '';

if (empty($submitted_token) || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['flash_error'] = 'Erreur de sécurité. Veuillez réessayer.';
    redirect('/student/drafts.php');
}

// ---------------------------------------------------------------------------
// STEP 2 — Validate input
// ---------------------------------------------------------------------------
$ticket_id  = (int) ($_POST['ticket_id'] ?? 0);
$student_id = (int) $_SESSION['user_id'];

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Identifiant de ticket invalide.';
    redirect('/student/drafts.php');
}

// STEP 3 — Load ticket and verify ownership + status
$stmt = $pdo->prepare('
    SELECT id, reference, status, user_id
    FROM tickets
    WHERE id = :id
    LIMIT 1
');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/student/drafts.php');
}

// Ownership check
if ((int) $ticket['user_id'] !== $student_id) {
    $_SESSION['flash_error'] = 'Accès refusé.';
    redirect('/student/drafts.php');
}

// Status check — only drafts can be deleted by the student
if ($ticket['status'] !== 'draft') {
    $_SESSION['flash_error'] = 'Seuls les brouillons peuvent être supprimés. Ce ticket est déjà en cours de traitement.';
    redirect('/student/drafts.php');
}

// STEP 4 — Delete the ticket (attachments cascade via FK ON DELETE CASCADE)
// The WHERE clause includes both id, user_id AND status = draft for safety
$stmt = $pdo->prepare('
    DELETE FROM tickets
    WHERE id      = :id
      AND user_id = :uid
      AND status  = :status
');
$stmt->execute([
    ':id'     => $ticket_id,
    ':uid'    => $student_id,
    ':status' => 'draft',
]);

if ($stmt->rowCount() === 0) {
    $_SESSION['flash_error'] = 'La suppression a échoué. Veuillez réessayer.';
    redirect('/student/drafts.php');
}

// STEP 5 — Success redirect
$ref = e($ticket['reference']);
$_SESSION['flash_success'] = "Le brouillon <strong>{$ref}</strong> a été supprimé définitivement.";
redirect('/student/drafts.php');
