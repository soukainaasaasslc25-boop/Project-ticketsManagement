<?php
// =============================================================================
// FILE    : student/submit_draft.php
// PURPOSE : Converts a student's DRAFT ticket into a submitted ticket (status=new).
//           Workflow: draft → new
// SECURITY:
//   - CSRF token verified
//   - Ownership verified (ticket must belong to logged-in student)
//   - Only 'draft' tickets can be submitted here



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
// STEP 2 — Validate ticket_id input
// ---------------------------------------------------------------------------
$ticket_id  = (int) ($_POST['ticket_id'] ?? 0);
$student_id = (int) $_SESSION['user_id'];

if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Ticket invalide.';
    redirect('/student/drafts.php');
}

// ---------------------------------------------------------------------------
// STEP 3 — Load the ticket and verify ownership + status
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare('
    SELECT id, reference, status, user_id
    FROM tickets
    WHERE id = :id
    LIMIT 1
');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

// Ticket must exist, belong to this student, and be a draft
if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/student/drafts.php');
}

if ((int) $ticket['user_id'] !== $student_id) {
    // Student is trying to submit someone else's ticket — deny
    $_SESSION['flash_error'] = 'Accès refusé.';
    redirect('/student/drafts.php');
}

if ($ticket['status'] !== 'draft') {
    // Already submitted or processed — can't submit again
    $_SESSION['flash_error'] = 'Ce ticket n\'est pas un brouillon et ne peut pas être soumis ici.';
    redirect('/student/drafts.php');
}

// ---------------------------------------------------------------------------
// STEP 4 — Update: draft → new, set submitted_at = NOW()
// ---------------------------------------------------------------------------
$stmt = $pdo->prepare('
    UPDATE tickets
    SET status       = :status,
        submitted_at = NOW(),
        updated_at   = NOW()
    WHERE id         = :id
      AND user_id    = :uid
      AND status     = :old_status
');
$stmt->execute([
    ':status'     => 'new',
    ':id'         => $ticket_id,
    ':uid'        => $student_id,
    ':old_status' => 'draft', // double-check in SQL too
]);

if ($stmt->rowCount() === 0) {
    $_SESSION['flash_error'] = 'La soumission a échoué. Veuillez réessayer.';
    redirect('/student/drafts.php');
}

// ---------------------------------------------------------------------------
// STEP 5 — Redirect with success
// ---------------------------------------------------------------------------
$ref = e($ticket['reference']);
$_SESSION['flash_success'] = "Le ticket <strong>{$ref}</strong> a été soumis. L'administration traitera votre demande sous peu.";
redirect('/student/dashboard.php');
