<?php
// Place as cuidar-private/config.php, alongside (not inside) public_html.
// Populate privately on Hostinger. NEVER commit the completed file or upload it to GitHub.
return [
    'registration_enabled' => false,
    'dsn' => 'mysql:host=localhost;dbname=u989902912_cuidar2026;charset=utf8mb4',
    'user' => 'u989902912_cuidar2026',
    // Existing database password in a separate private file, with no quotes or PHP code.
    'password' => rtrim(file_get_contents(__DIR__ . '/db-password.txt'), "\r\n"),
    'rate_secret' => '',
    'privacy_retention_approved' => false,
];
