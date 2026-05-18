<?php
// =============================================================================
// FILE    : auth/logout.php
// PURPOSE : Logs the user out cleanly and securely.
//           - Destroys the session
//           - Deletes the remember-me cookie from the browser
//           - Deletes the remember-me token from the database
//           - Redirects to the login page
// =============================================================================

// Start session so we can access and destroy it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// =============================================================================
// STEP 1: Delete remember-me cookie and its database token (if any)
//
// We must do this BEFORE destroying the session because we might need
// the PDO connection which was set up after the session.
// =============================================================================

if (isset($_COOKIE['remember_me'])) {

    // Split the cookie to extract the selector
    $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);

    if (count($cookie_parts) === 2) {
        [$selector] = $cookie_parts;

        // Delete the token row from the database using the selector
        // This prevents the old cookie from being reused after logout
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
    }

    // Tell the browser to delete the cookie by setting an expired date
    // The empty string '' as value + past expiry time = cookie is deleted
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
    unset($_COOKIE['remember_me']);
}

// =============================================================================
// STEP 2: Clear all session variables
//
// $_SESSION = [] clears all values but keeps the session alive.
// This is cleaner than calling session_unset().
// =============================================================================

$_SESSION = [];

// =============================================================================
// STEP 3: Delete the session cookie from the browser
//
// Even after clearing $_SESSION, the browser still has the PHPSESSID cookie.
// We need to expire that cookie too for a complete logout.
// =============================================================================

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),   // Usually 'PHPSESSID'
        '',
        time() - 42000,  // Very old expiry date = delete the cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// =============================================================================
// STEP 4: Destroy the session on the server side
//
// session_destroy() removes the session file/data from the server.
// Combined with the steps above, this is a complete logout.
// =============================================================================

session_destroy();

// =============================================================================
// STEP 5: Redirect to login page with a success message
// =============================================================================

header('Location: /pfe/auth/login.php?message=logged_out');
exit();
