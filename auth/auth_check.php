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
// Vérifie si l'utilisateur est connecté (via Session ou Cookie)
function is_logged_in(): bool
{
    // 1. Vérification de la session active
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // 2. Vérification du cookie Remember-me
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

    // 3. Récupération du token valide en Base de Données
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

    // 4. Vérification du hash du validator (protection contre les attaques par canal auxiliaire)
    $hashed_input = hash('sha256', $validator);
    if (!hash_equals($token_row['hashed_validator'], $hashed_input)) {
        // Validator doesn't match — someone tampered with the cookie
        delete_remember_cookie();
        return false;
    }

    // 5. Le token est valide — reconstruction de la session
    $_SESSION['user_id']    = $token_row['user_id'];
    $_SESSION['username']   = $token_row['username'];
    $_SESSION['role']       = $token_row['role'];
    $_SESSION['first_name'] = $token_row['first_name'];
    $_SESSION['last_name']  = $token_row['last_name'];

    // régénère l'ID de session pour prévenir les attaques par fixation de session
    session_regenerate_id(true);

    // Met à jour last_login_at
    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $update->execute([$token_row['user_id']]);

    return true;
}

// Redirige vers la page de connexion si l'utilisateur n'est pas authentifié.
// À utiliser au début de toute page nécessitant une connexion (étudiant ou administrateur).
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /pfe/auth/login.php?error=session_expired');
        exit();
    }
}

// Redirige vers la page de connexion (ou 403) si l'utilisateur n'est PAS un administrateur.
// À utiliser au début de chaque page d'administration.
function require_admin(): void
{
    require_login();

    if ($_SESSION['role'] !== 'admin') {
        // L'étudiant a tenté d'accéder à la zone d'administration — accès refusé
        header('Location: /pfe/auth/login.php?error=unauthorized');
        exit();
    }
}

// Redirige si l'utilisateur n'est PAS un étudiant.
// À utiliser au début de chaque page étudiante.
function require_student(): void
{
    require_login();

    if ($_SESSION['role'] !== 'student') {
        // L'administrateur a tenté d'accéder à la zone étudiante — redirection vers le tableau de bord de l'administrateur
        header('Location: /pfe/admin/dashboard.php');
        exit();
    }
}

// Supprime le cookie du navigateur et retire le token de la base de données.
// Appelée lors de la déconnexion ou lorsqu'un cookie est jugé invalide.
function delete_remember_cookie(): void
{
    if (isset($_COOKIE['remember_me'])) {
        $cookie_parts = explode(':', $_COOKIE['remember_me'], 2);

        if (count($cookie_parts) === 2) {
            [$selector] = $cookie_parts;

            // Supprime le token de la base de données
            global $pdo;
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $stmt->execute([$selector]);
        }

        // Supprime le cookie en définissant une date d'expiration passée
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        unset($_COOKIE['remember_me']);
    }
}
