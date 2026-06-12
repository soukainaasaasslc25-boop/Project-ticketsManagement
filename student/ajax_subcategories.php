<?php
// FILE    : student/ajax_subcategories.php
// PURPOSE : AJAX endpoint — returns JSON array of active subcategories
//           for a given category_id.
//
// Called by: student/create_ticket.php (fetch on category change)
// Returns  : JSON array of { id, name, description } objects, or []

require_once __DIR__ . '/../auth/auth_check.php';
require_student();

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([]);
    exit();
}

$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

if (!$category_id || $category_id < 1) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare('
    SELECT id, name, description
    FROM subcategories
    WHERE category_id = ?
      AND is_active = 1
    ORDER BY name ASC
');
$stmt->execute([$category_id]);
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subcategories);
