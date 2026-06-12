<?php

// --- Database credentials ---
// Change these to match your XAMPP / MySQL setup.
$db_host     = 'localhost';      
$db_name     = 'ticket_system';
$db_user     = 'root';          
$db_password = '';               
$db_charset  = 'utf8mb4';       

// --- Build the DSN (Data Source Name) ---
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";


$options = [
    // Throw exceptions on errors instead of silent failures
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

  
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

  
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Create the connection ---
// We wrap it in try/catch so we get a clean error message if it fails
try {
    $pdo = new PDO($dsn, $db_user, $db_password, $options);
} catch (PDOException $e) {
    // In production, NEVER show the raw error to users.
    
    die('Database connection failed: ' . $e->getMessage());
}


