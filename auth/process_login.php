<?php
// FILE    : auth/process_login.php
// PURPOSE : Handles the login form submission (POST request only).
//           Validates credentials, starts session, handles remember-me,
//           and redirects by role or account status.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pfe/auth/login.php');
    exit();
}

session_start();

require_once __DIR__ . '/../config/database.php';

// STEP 1: Read and sanitize form inputs
$username    = trim($_POST['username'] ?? '');
$password    = $_POST['password'] ?? ''; 
$remember_me = isset($_POST['remember_me']);

if (empty($username) || empty($password)) {
    header('Location: /pfe/auth/login.php?error=empty_fields');
    exit();
}

// STEP 2: Look up the user by username
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /pfe/auth/login.php?error=invalid_credentials');
    exit();
}

// STEP 3: Verify the password
if (!password_verify($password, $user['password_hash'])) {
    header('Location: /pfe/auth/login.php?error=invalid_credentials');
    exit();
}

// STEP 4: Update last login timestamp
$update_login = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
$update_login->execute([$user['id']]);

// STEP 5: Regenerate session ID
session_regenerate_id(true);

// STEP 6: Store user data in the session
$_SESSION['user_id']        = $user['id'];
$_SESSION['username']       = $user['username'];
$_SESSION['role']           = $user['role'];
$_SESSION['first_name']     = $user['first_name'];
$_SESSION['last_name']      = $user['last_name'];
$_SESSION['account_status'] = $user['account_status'];

// STEP 7: Handle "Remember Me" checkbox
if ($remember_me) {
    $selector  = bin2hex(random_bytes(32));
    $validator = bin2hex(random_bytes(32));
    $hashed_validator = hash('sha256', $validator);

    $expires_at     = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
    $cookie_expires = time() + (30 * 24 * 60 * 60);

    $insert_token = $pdo->prepare('
        INSERT INTO remember_tokens (user_id, selector, hashed_validator, expires_at)
        VALUES (?, ?, ?, ?)
    ');
    $insert_token->execute([$user['id'], $selector, $hashed_validator, $expires_at]);

    setcookie(
        'remember_me',
        $selector . ':' . $validator,
        $cookie_expires,
        '/',
        '',
        false,
        true
    );
}

// STEP 8: Redirect based on user role and status
if ($user['role'] === 'admin') {
    header('Location: /pfe/admin/dashboard.php');
} else {
    // Ila kan l-étudiant baqi 'inactive' (awel login) -> Forcih l profile!
    if ($user['account_status'] === 'inactive') {
        header('Location: /pfe/student/profile.php?first_login=1');
    } else {
        header('Location: /pfe/student/dashboard.php');
    }
}
exit();