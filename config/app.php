<?php
// =============================================================================
// FILE    : config/app.php
// PURPOSE : Application-wide constants (paths, URLs, upload limits).
//           Include this wherever you need BASE_URL or upload settings.
// USAGE   : require_once __DIR__ . '/../config/app.php';
// =============================================================================

// Base URL path — change only this if you move the project folder
// Example: http://localhost/pfe/  →  BASE_URL = '/pfe'
define('BASE_URL', '/pfe');

// Absolute filesystem path to the project root
define('ROOT_PATH', dirname(__DIR__));

// Where uploaded ticket files are stored (must be writable by Apache)
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Allowed file extensions for ticket attachments
define('UPLOAD_ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Maximum upload size in bytes (5 MB)
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);

// Ticket reference prefix (TKT-2025-00001)
define('TICKET_REF_PREFIX', 'TKT');
