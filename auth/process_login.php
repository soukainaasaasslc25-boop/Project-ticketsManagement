<?php
// =============================================================================
// FILE    : auth/process_login.php
// PURPOSE : Handles the login form submission (POST request only).
//           Validates credentials, starts session, handles remember-me,
//           activates inactive student accounts, and redirects by role.
// =============================================================================

// Only allow POST requests — reject direct browser access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pfe/auth/login.php');
    exit();
}

// Start the session before doing anything else
session_start();

// Load the database connection ($pdo is now available)
require_once __DIR__ . '/../config/database.php';

// =============================================================================
// STEP 1: Read and sanitize form inputs
// =============================================================================

// trim() removes accidental spaces from start/end
$username    = trim($_POST['username'] ?? '');
$password    = $_POST['password'] ?? '';  // Do NOT trim passwords
$remember_me = isset($_POST['remember_me']); // true if checkbox was checked

// --- Basic validation: make sure fields are not empty ---
if (empty($username) || empty($password)) {
    // Redirect back to login with an error code
    header('Location: /pfe/auth/login.php?error=empty_fields');
    exit();
}

// =============================================================================
// STEP 2: Look up the user by username
//
// We use a prepared statement — NEVER put $username directly in the SQL string.
// Prepared statements prevent SQL injection attacks completely.
// =============================================================================

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(); // Returns an array or false if not found

// --- Check if user exists ---
if (!$user) {
    // Username not found in the database
    header('Location: /pfe/auth/login.php?error=invalid_credentials');
    exit();
}

// =============================================================================
// STEP 3: Verify the password
//
// password_verify() compares the plain-text password against the stored hash.
// It NEVER compares passwords as plain text — it uses bcrypt internally.
// =============================================================================

if (!password_verify($password, $user['password_hash'])) {
    // Password does not match — wrong credentials
    header('Location: /pfe/auth/login.php?error=invalid_credentials');
    exit();
}

// =============================================================================
// STEP 4: Auto-activate student account on first login
//
// Students imported from Excel start as 'inactive'.
// The moment they log in successfully for the first time,
// we automatically set their account_status to 'active'.
// =============================================================================

if ($user['role'] === 'student' && $user['account_status'] === 'inactive') {
    $activate = $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?');
    $activate->execute(['active', $user['id']]);
    $user['account_status'] = 'active'; // Update local variable too
}

// =============================================================================
// STEP 5: Update last login timestamp
//
// Every time a user logs in successfully, we record the date/time.
// This is useful for audit trails and admin reporting.
// =============================================================================

$update_login = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
$update_login->execute([$user['id']]);

// =============================================================================
// STEP 6: Regenerate session ID
//
// session_regenerate_id(true) creates a brand new session ID.
// This prevents "session fixation" attacks where an attacker
// tricks the user into using a session ID controlled by the attacker.
// =============================================================================

session_regenerate_id(true);

// =============================================================================
// STEP 7: Store user data in the session
//
// We store just enough data to identify and authorize the user.
// Do NOT store the password hash or sensitive data in session.
// =============================================================================

$_SESSION['user_id']              = $user['id'];
$_SESSION['username']             = $user['username'];
$_SESSION['role']                 = $user['role'];
$_SESSION['first_name']           = $user['first_name'];
$_SESSION['last_name']            = $user['last_name'];
$_SESSION['account_status']       = $user['account_status'];
$_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);

// =============================================================================
// STEP 8: Handle "Remember Me" checkbox
//
// This uses the selector/validator pattern — the most secure approach:
//
//   selector   → public, used to FIND the token in the DB
//   validator  → secret, stored HASHED in DB, stored PLAIN in cookie
//
// Why hash the validator?
//   If the DB is ever stolen, attackers cannot use the raw validator
//   because they only have the hash, not the original value.
//
// Cookie format: "selector:validator"
// =============================================================================

if ($remember_me) {

    // Generate cryptographically secure random bytes
    $selector  = bin2hex(random_bytes(32)); // 64 hex chars
    $validator = bin2hex(random_bytes(32)); // 64 hex chars

    // Hash the validator for safe DB storage
    $hashed_validator = hash('sha256', $validator);

    // Token expires in 30 days from now
    $expires_at     = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
    $cookie_expires = time() + (30 * 24 * 60 * 60);

    // Save token to the database
    $insert_token = $pdo->prepare('
        INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at)
        VALUES (?, ?, ?, ?)
    ');
    $insert_token->execute([$user['id'], $selector, $hashed_validator, $expires_at]);

    // Set the cookie in the user's browser
    // Parameters: name, value, expire, path, domain, secure, httponly
    //   httponly = true → JavaScript cannot read this cookie (prevents XSS)
    //   secure   = false → works on localhost without HTTPS (set true on production)
    setcookie(
        'remember_me',
        $selector . ':' . $validator,
        $cookie_expires,
        '/',
        '',
        false, // Set to true on production (HTTPS)
        true   // HttpOnly — blocks JavaScript from reading the cookie
    );
}

// =============================================================================
// STEP 9: Redirect based on user role
//
// Admin  → admin dashboard
// Student → student dashboard
// =============================================================================

if ($user['role'] === 'admin') {
    header('Location: /pfe/admin/dashboard.php');
} else {
    if ($_SESSION['must_change_password'] === 1) {
        header('Location: /pfe/student/profile.php?first_login=1');
    } else {
        header('Location: /pfe/student/dashboard.php');
    }
}
exit();
