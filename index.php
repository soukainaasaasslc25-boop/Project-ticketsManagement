<?php
// =============================================================================
// FILE    : index.php
// PURPOSE : Entry point — sends visitors to login or their dashboard.
// =============================================================================

require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        redirect('/admin/dashboard.php');
    }
    redirect('/student/dashboard.php');
}

redirect('/auth/login.php');
