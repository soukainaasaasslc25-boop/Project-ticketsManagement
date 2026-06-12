<?php
// FILE    : auth/auth_check.php
// PURPOSE : Session & remember-me authentication guard.
//           Include this at the TOP of any protected page.
//
// Provides 3 functions:
//   require_login()   → any logged-in user (student or admin)
//   require_admin()   → admin only
//   require_student() → student only

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// FUNCTION: is_logged_in()
// Checks if the user has an active session OR a valid remember-me cookie.
// Returns true if logged in, false if not.
function is_logged_in(): bool
{
    // --- Step 1: Check if session already has user data ---
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // --- Step 2: Check for remember-me cookie ---
    // Cookie name is 'remember_me'
    // Cookie value format: "selector:validator"
    if (!isset($_COOKIE['remember_me'])) {
        return false;
    }

    // Split the cookie value on ':' to get selector and validator
    $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($cookie_parts) !== 2) {
        // Cookie format is invalid — clear it
        delete_remember_cookie();
        return false;
    }

    [$selector, $validator] = $cookie_parts;

    // --- Step 3: Look up the token in the database using selector ---
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT rt.*, u.id AS user_id, u.username, u.role,
               u.first_name, u.last_name, u.account_status
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = ?
          AND rt.expires_at > NOW()
        LIMIT 1
    ');
    $stmt->execute([$selector]);
    $token_row = $stmt->fetch();

    if (!$token_row) {
        // No matching token or token expired — clear the cookie
        delete_remember_cookie();
        return false;
    }

    // --- Step 4: Verify the validator using timing-safe comparison ---
    // We NEVER store the plain validator in DB.
    // We store hash('sha256', $validator) and compare hashes.
    // hash_equals() prevents timing attacks.
    $hashed_input = hash('sha256', $validator);
    if (!hash_equals($token_row['hashed_validator'], $hashed_input)) {
        // Validator doesn't match — someone tampered with the cookie
        delete_remember_cookie();
        return false;
    }

    // --- Step 5: Token is valid — rebuild the session ---
    $_SESSION['user_id']    = $token_row['user_id'];
    $_SESSION['username']   = $token_row['username'];
    $_SESSION['role']       = $token_row['role'];
    $_SESSION['first_name'] = $token_row['first_name'];
    $_SESSION['last_name']  = $token_row['last_name'];

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Update last_login_at
    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$token_row['user_id']]);

    return true;
}

// FUNCTION: require_login()
// Redirects to login page if the user is NOT authenticated.
// Use this at the top of any page that requires login (student or admin).
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /pfe/auth/login.php?error=session_expired');
        exit();
    }
}

// FUNCTION: require_admin()
// Redirects to login (or 403 page) if the user is NOT an admin.
// Use this at the top of every admin page.
function require_admin(): void
{
    require_login();

    if ($_SESSION['role'] !== 'admin') {
        // Student tried to access admin area — deny access
        header('Location: /pfe/auth/login.php?error=unauthorized');
        exit();
    }
}

// FUNCTION: require_student()
// Redirects away if the user is NOT a student.
// Use this at the top of every student page.
function require_student(): void
{
    require_login();

    if ($_SESSION['role'] !== 'student') {
        // Admin tried to access student area — redirect to admin dashboard
        header('Location: /pfe/admin/dashboard.php');
        exit();
    }
}

// FUNCTION: delete_remember_cookie()
// Deletes the browser cookie and removes the token from the database.
// Called on logout or when a cookie is found to be invalid.
function delete_remember_cookie(): void
{
    if (isset($_COOKIE['remember_me'])) {
        $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);

        if (count($cookie_parts) === 2) {
            [$selector] = $cookie_parts;

            // Delete the token row from the database
            global $pdo;
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $stmt->execute([$selector]);
        }

        // Expire the cookie in the browser (set past expiry date)
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        unset($_COOKIE['remember_me']);
    }
}
