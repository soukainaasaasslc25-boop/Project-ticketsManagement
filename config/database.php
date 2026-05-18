<?php
// =============================================================================
// FILE    : config/database.php
// PURPOSE : Create and return a PDO database connection.
//           This file is included at the top of every PHP file that needs
//           to talk to the database. It returns a $pdo variable ready to use.
// USAGE   : require_once __DIR__ . '/../config/database.php';
// =============================================================================

// --- Database credentials ---
// Change these to match your XAMPP / MySQL setup.
$db_host     = 'localhost';      // Almost always localhost on XAMPP
$db_name     = 'ticket_system'; // The database name from schema.sql
$db_user     = 'root';          // Default XAMPP MySQL username
$db_password = '';               // Default XAMPP MySQL password (empty)
$db_charset  = 'utf8mb4';       // Supports French accents, emojis, Arabic

// --- Build the DSN (Data Source Name) ---
// PDO needs a DSN string that tells it: which driver, host, db name, charset
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

// --- PDO options ---
// These options make PDO safer and easier to use:
$options = [
    // Throw exceptions on errors instead of silent failures
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Return rows as associative arrays (e.g. $row['username'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Disable emulated prepared statements for real security
    // This ensures SQL is sent to MySQL as a true prepared statement
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create the connection ---
// We wrap it in try/catch so we get a clean error message if it fails
try {
    $pdo = new PDO($dsn, $db_user, $db_password, $options);
} catch (PDOException $e) {
    // In production, NEVER show the raw error to users.
    // Log it and show a friendly message instead.
    // For development (XAMPP), we show the message to debug quickly.
    die('Database connection failed: ' . $e->getMessage());
}

// After this file is included, $pdo is available in the including file.
// Example usage:
//   $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
//   $stmt->execute([$username]);
//   $user = $stmt->fetch();
