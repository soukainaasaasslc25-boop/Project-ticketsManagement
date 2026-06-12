<?php
// =============================================================================
// FILE    : student/process_edit_ticket.php
// PURPOSE : Handles POST from edit_ticket.php
//           - Validates CSRF + ownership + editable status
//           - Updates the ticket row in DB
//           - action=save  → keeps current status (draft stays draft, new stays new)
//           - action=submit → promotes draft to 'new' + sets submitted_at
// HOW TO TEST:
//   1. Edit a draft ticket → click "Enregistrer" → status stays 'draft'
//   2. Edit a draft ticket → click "Enregistrer & Soumettre" → status becomes 'new'

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/my_tickets.php');
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
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// STEP 2 — Collect & sanitize inputs
// ---------------------------------------------------------------------------
$ticket_id      = (int) ($_POST['ticket_id']      ?? 0);
$category_id    = (int) ($_POST['category_id']    ?? 0);
$subcategory_id = (int) ($_POST['subcategory_id'] ?? 0);
$priority       = trim($_POST['priority']       ?? 'medium');
$subject        = trim($_POST['subject']        ?? '');
$description    = trim($_POST['description']    ?? '');
$action         = ($_POST['action'] ?? '') === 'submit' ? 'submit' : 'save';
$student_id     = (int) $_SESSION['user_id'];

$allowed_priorities = ['low', 'medium', 'high', 'urgent'];

// ---------------------------------------------------------------------------
// STEP 3 — Load ticket, verify ownership & editable status
// ---------------------------------------------------------------------------
if ($ticket_id <= 0) {
    $_SESSION['flash_error'] = 'Ticket invalide.';
    redirect('/student/my_tickets.php');
}

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_error'] = 'Ticket introuvable.';
    redirect('/student/my_tickets.php');
}

// Ownership check
if ((int) $ticket['user_id'] !== $student_id) {
    $_SESSION['flash_error'] = 'Accès refusé.';
    redirect('/student/my_tickets.php');
}

// Editable status check — draft and new are editable
$editable = ['draft', 'new'];
if (!in_array($ticket['status'], $editable, true)) {
    $_SESSION['flash_error'] = 'Ce ticket ne peut plus être modifié — il est en cours de traitement par l\'administration.';
    redirect('/student/my_tickets.php');
}

// ---------------------------------------------------------------------------
// STEP 4 — Validate form inputs
// ---------------------------------------------------------------------------
$errors = [];

if ($category_id <= 0) {
    $errors[] = 'Veuillez sélectionner une catégorie.';
}
if (mb_strlen($subject) < 5) {
    $errors[] = 'L\'objet doit contenir au moins 5 caractères.';
}
if (mb_strlen($description) < 20) {
    $errors[] = 'La description doit contenir au moins 20 caractères.';
}
if (!in_array($priority, $allowed_priorities, true)) {
    $errors[] = 'Priorité invalide.';
}

// Verify category exists and get its type
$cat_stmt = $pdo->prepare('SELECT id, type FROM categories WHERE id = ? AND is_active = 1 LIMIT 1');
$cat_stmt->execute([$category_id]);
$category = $cat_stmt->fetch();

if (!$category) {
    $errors[] = 'Catégorie introuvable ou désactivée.';
}

if (!empty($errors)) {
    $_SESSION['flash_error']     = implode('<br>', $errors);
    $_SESSION['form_repopulate'] = $_POST;
    redirect('/student/edit_ticket.php?id=' . $ticket_id);
}

// Verify subcategory belongs to category (if provided)
$verified_sub = null;
if ($subcategory_id > 0 && $category) {
    $sub_stmt = $pdo->prepare(
        'SELECT id FROM subcategories WHERE id = ? AND category_id = ? AND is_active = 1 LIMIT 1'
    );
    $sub_stmt->execute([$subcategory_id, $category_id]);
    if ($sub_stmt->fetch()) {
        $verified_sub = $subcategory_id;
    }
}

// ---------------------------------------------------------------------------
// STEP 5 — Determine new status and submitted_at
// ---------------------------------------------------------------------------
$new_status   = $ticket['status']; // default: keep existing status
$submitted_at = $ticket['submitted_at'];

if ($action === 'submit' && $ticket['status'] === 'draft') {
    // Promote draft → new
    $new_status   = 'new';
    $submitted_at = date('Y-m-d H:i:s');
}
// If ticket is already 'new' and action=save → keep 'new'

// ---------------------------------------------------------------------------
// STEP 6 — Run the UPDATE
// ---------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare('
        UPDATE tickets
        SET category_id    = :category_id,
            subcategory_id = :subcategory_id,
            type           = :type,
            priority       = :priority,
            subject        = :subject,
            description    = :description,
            status         = :status,
            submitted_at   = :submitted_at,
            updated_at     = NOW()
        WHERE id      = :id
          AND user_id = :user_id
    ');
    $stmt->execute([
        ':category_id'    => $category_id,
        ':subcategory_id' => $verified_sub,
        ':type'           => $category['type'],
        ':priority'       => $priority,
        ':subject'        => $subject,
        ':description'    => $description,
        ':status'         => $new_status,
        ':submitted_at'   => $submitted_at,
        ':id'             => $ticket_id,
        ':user_id'        => $student_id,
    ]);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = 'Erreur lors de la mise à jour. Veuillez réessayer.';
    redirect('/student/edit_ticket.php?id=' . $ticket_id);
}

// ---------------------------------------------------------------------------
// STEP 7 — Clear CSRF + redirect with message
// ---------------------------------------------------------------------------
unset($_SESSION['csrf_token'], $_SESSION['form_repopulate']);

$ref = e($ticket['reference']);

if ($action === 'submit' && $new_status === 'new') {
    $_SESSION['flash_success'] = "Le ticket <strong>{$ref}</strong> a été mis à jour et soumis à l'administration.";
    redirect('/student/dashboard.php');
} else {
    $_SESSION['flash_success'] = "Le ticket <strong>{$ref}</strong> a été mis à jour avec succès.";
    if ($new_status === 'draft') {
        $back = '/student/drafts.php';
    } elseif ($category['type'] === 'complaint') {
        $back = '/student/reclamations.php';
    } else {
        $back = '/student/demandes.php';
    }
    redirect($back);
}
