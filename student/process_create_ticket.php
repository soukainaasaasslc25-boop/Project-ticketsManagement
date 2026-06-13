<?php

// FILE    : student/process_create_ticket.php
// PURPOSE : Handles POST from create_ticket.php
//           1. Validates CSRF token
//           2. Sanitizes & validates all fields
//           3. Verifies category / subcategory exist in DB
//           4. Uploads attachments securely (max 3, 5 MB, whitelist MIME)
//           5. Inserts ticket row
//           6. Inserts attachment rows
//           7. Redirects with flash message
//
// Actions (set via hidden <input name="action">):
//   'draft'  → saves with status = 'draft',  submitted_at = NULL
//   'submit' → saves with status = 'new',    submitted_at = NOW()

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/create_ticket.php');
}

// STEP 1 — CSRF Verification
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token   = $_SESSION['csrf_token'] ?? '';

if (empty($submitted_token) || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['flash_error'] = 'Erreur de sécurité. Veuillez recharger la page et réessayer.';
    redirect('/student/create_ticket.php');
}

// STEP 2 — Determine Action
$action = ($_POST['action'] ?? '') === 'submit' ? 'submit' : 'draft';

// STEP 3 — Collect & Sanitize Inputs
$category_id    = (int) ($_POST['category_id']    ?? 0);
$subcategory_id = (int) ($_POST['subcategory_id'] ?? 0);
$priority       = trim($_POST['priority']       ?? 'medium');
$subject        = trim($_POST['subject']        ?? '');
$description    = trim($_POST['description']    ?? '');

$allowed_priorities = ['low', 'medium', 'high', 'urgent'];

// STEP 4 — Validation
$errors = [];

if ($subcategory_id <= 0) {
    $errors[] = 'Veuillez sélectionner une sous-catégorie.';
}

if (mb_strlen($subject) < 5) {
    $errors[] = 'L\'objet doit contenir au moins 5 caractères.';
}

// Stricter rules for 'submit' (drafts can be incomplete)
if ($action === 'submit') {
    if (mb_strlen($description) < 20) {
        $errors[] = 'La description doit contenir au moins 20 caractères pour soumettre.';
    }
    if (!in_array($priority, $allowed_priorities, true)) {
        $errors[] = 'Priorité invalide.';
    }
}

// Infer category_id from subcategory_id
$verified_subcategory_id = null;
$category = null;
if ($subcategory_id > 0 && empty($errors)) {
    $stmt = $pdo->prepare('
        SELECT s.id, s.category_id, c.type 
        FROM subcategories s
        JOIN categories c ON c.id = s.category_id
        WHERE s.id = ? AND s.is_active = 1 AND c.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([$subcategory_id]);
    $sub = $stmt->fetch();
    
    if ($sub) {
        $verified_subcategory_id = $sub['id'];
        $category_id = $sub['category_id'];
        $category = ['type' => $sub['type']];
    } else {
        $errors[] = 'La sous-catégorie sélectionnée est introuvable ou désactivée.';
    }
}

// On validation failure → redirect back with errors and old values
if (!empty($errors)) {
    $_SESSION['flash_error']     = implode('<br>', $errors);
    $_SESSION['form_repopulate'] = $_POST;
    // Redirect back to the correct form
    $redirect_url = ($_POST['form_type'] ?? '') === 'complaint' ? '/student/create_reclamation.php' : '/student/create_demande.php';
    redirect($redirect_url);
}

// STEP 5 — Secure File Upload

// Map of allowed MIME types to clean extensions
$allowed_mimes = [
    'application/pdf'    => 'pdf',
    'image/jpeg'         => 'jpg',
    'image/png'          => 'png',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

$uploaded_files = [];
$upload_errors  = [];

if (!empty($_FILES['attachments']['name'][0])) {

    $files      = $_FILES['attachments'];
    $file_count = min(count($files['name']), 3); // hard cap at 3

    for ($i = 0; $i < $file_count; $i++) {

        // Skip if user left the slot empty
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        // PHP upload error (e.g. file too large at php.ini level)
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $upload_errors[] = 'Erreur lors de l\'upload du fichier ' . ($i + 1) . '.';
            continue;
        }

        $original_name = $files['name'][$i];
        $tmp_path      = $files['tmp_name'][$i];
        $file_size     = (int) $files['size'][$i];

        // Enforce 5 MB maximum per file
        if ($file_size > UPLOAD_MAX_BYTES) {
            $upload_errors[] = 'Le fichier « ' . e($original_name) . ' » dépasse la taille maximale de 5 Mo.';
            continue;
        }

        // Detect real MIME type using finfo (NOT the browser-supplied type)
        $finfo     = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($tmp_path);

        if (!array_key_exists($mime_type, $allowed_mimes)) {
            $upload_errors[] = 'Le fichier « ' . e($original_name) . ' » n\'est pas d\'un type autorisé (PDF, JPG, PNG, DOC, DOCX).';
            continue;
        }

        // Build monthly sub-directory: uploads/YYYY/MM/
        $year_month = date('Y/m');
        $dir        = UPLOAD_PATH . '/' . $year_month;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $upload_errors[] = 'Impossible de créer le répertoire d\'upload.';
            continue;
        }

        // Generate a cryptographically random filename (prevents guessing)
        $ext         = $allowed_mimes[$mime_type];
        $stored_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest_path   = $dir . '/' . $stored_name;
        $file_path   = 'uploads/' . $year_month . '/' . $stored_name;

        if (!move_uploaded_file($tmp_path, $dest_path)) {
            $upload_errors[] = 'Impossible de déplacer le fichier « ' . e($original_name) . ' ».';
            continue;
        }

        // File is safe — add to list
        $uploaded_files[] = [
            'original_name' => $original_name,
            'stored_name'   => $stored_name,
            'file_path'     => $file_path,
            'mime_type'     => $mime_type,
            'file_size'     => $file_size,
        ];
    }
}

// Block submission if there are upload errors (draft is more lenient)
if (!empty($upload_errors) && $action === 'submit') {
    $_SESSION['flash_error']     = implode('<br>', $upload_errors);
    $_SESSION['form_repopulate'] = $_POST;
    redirect('/student/create_ticket.php');
}

// STEP 6 — Generate Unique Ticket Reference  (TKT-YYYY-NNNNN)
$student_id   = (int) $_SESSION['user_id'];
$ticket_year  = date('Y');
$prefix       = TICKET_REF_PREFIX . '-' . $ticket_year . '-';

// Find the highest existing number for this year
$stmt = $pdo->prepare("
    SELECT reference FROM tickets
    WHERE reference LIKE ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$prefix . '%']);
$last_ref = $stmt->fetchColumn();

$last_num  = $last_ref ? (int) substr($last_ref, strrpos($last_ref, '-') + 1) : 0;
$reference = $prefix . str_pad($last_num + 1, 5, '0', STR_PAD_LEFT);

// STEP 7 — Insert Ticket + Attachments (inside a transaction)
$status       = ($action === 'submit') ? 'new'  : 'draft';
$submitted_at = ($action === 'submit') ? date('Y-m-d H:i:s') : null;
$ticket_type  = $category['type']; // 'request' or 'complaint'
$safe_priority = in_array($priority, $allowed_priorities, true) ? $priority : 'medium';

try {
    $pdo->beginTransaction();

    // Insert ticket
    $stmt = $pdo->prepare('
        INSERT INTO tickets (
            reference, user_id, category_id, subcategory_id,
            type, priority, subject, description,
            status, submitted_at, created_at, updated_at
        ) VALUES (
            :reference, :user_id, :category_id, :subcategory_id,
            :type, :priority, :subject, :description,
            :status, :submitted_at, NOW(), NOW()
        )
    ');
    $stmt->execute([
        ':reference'      => $reference,
        ':user_id'        => $student_id,
        ':category_id'    => $category_id,
        ':subcategory_id' => $verified_subcategory_id,
        ':type'           => $ticket_type,
        ':priority'       => $safe_priority,
        ':subject'        => $subject,
        ':description'    => $description,
        ':status'         => $status,
        ':submitted_at'   => $submitted_at,
    ]);
    $ticket_id = (int) $pdo->lastInsertId();

    // Insert attachment rows (if any files were uploaded)
    if (!empty($uploaded_files)) {
        $stmt_att = $pdo->prepare('
            INSERT INTO ticket_attachments (
                ticket_id, response_id, uploaded_by,
                original_name, stored_name, file_path,
                mime_type, file_size, created_at
            ) VALUES (
                :ticket_id, NULL, :uploaded_by,
                :original_name, :stored_name, :file_path,
                :mime_type, :file_size, NOW()
            )
        ');
        foreach ($uploaded_files as $file) {
            $stmt_att->execute([
                ':ticket_id'     => $ticket_id,
                ':uploaded_by'   => $student_id,
                ':original_name' => $file['original_name'],
                ':stored_name'   => $file['stored_name'],
                ':file_path'     => $file['file_path'],
                ':mime_type'     => $file['mime_type'],
                ':file_size'     => $file['file_size'],
            ]);
        }
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Erreur base de données. Veuillez réessayer.';
    $redirect_url = ($_POST['form_type'] ?? '') === 'complaint' ? '/student/create_reclamation.php' : '/student/create_demande.php';
    redirect($redirect_url);
}

// STEP 8 — Clear session and redirect with success message
unset($_SESSION['csrf_token'], $_SESSION['form_repopulate']);

if ($action === 'submit') {
    $_SESSION['flash_success'] = "Votre ticket <strong>{$reference}</strong> a été soumis avec succès. L'administration vous répondra sous peu.";
} else {
    $_SESSION['flash_success'] = "Brouillon <strong>{$reference}</strong> enregistré. Soumettez-le depuis vos tickets quand vous êtes prêt.";
}

if ($action === 'draft') {
    redirect('/student/drafts.php');
} else {
    $final_redirect = ($ticket_type === 'complaint') ? '/student/reclamations.php' : '/student/demandes.php';
    redirect($final_redirect);
}
