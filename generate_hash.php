<?php
// =============================================================================
// FILE    : generate_hash.php
// PURPOSE : One-time utility — generates correct bcrypt hashes for seed.sql.
//           Run this ONCE in the browser, copy the hashes, then delete this file.
//
// URL     : http://localhost/pfe/generate_hash.php
// DELETE  : Remove this file after use — it has no place in production!
// =============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
        .hash-value { background: #0f172a; border-radius: 8px; padding: 0.75rem 1rem; color: #34d399; font-size: 0.85rem; word-break: break-all; }
        h2 { color: #60a5fa; }
        .label { color: #94a3b8; font-size: 0.8rem; margin-bottom: 0.4rem; }
        .password-name { color: #fbbf24; font-weight: bold; }
        .warning { background: #450a0a; border: 1px solid #b91c1c; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; color: #fca5a5; }
    </style>
</head>
<body>

<h2>🔐 Password Hash Generator</h2>
<p style="color:#94a3b8">Generated hashes for seed.sql — copy and use below.</p>

<div class="warning">
    ⚠️ <strong>DELETE this file after use!</strong> Never leave it on a production server.
</div>

<?php

$passwords = [
    'Admin@2025'   => 'Admin account (username: admin)',
    'Student@123'  => 'All student accounts (default password)',
];

foreach ($passwords as $plain => $description):
    $hash = password_hash($plain, PASSWORD_BCRYPT);
?>
<div class="card">
    <div class="label">Password</div>
    <div class="password-name mb-2"><?= htmlspecialchars($plain) ?></div>
    <div class="label">Used for</div>
    <div style="color:#e2e8f0; margin-bottom:1rem;"><?= htmlspecialchars($description) ?></div>
    <div class="label">Bcrypt Hash (copy this into seed.sql)</div>
    <div class="hash-value"><?= htmlspecialchars($hash) ?></div>
    <div class="mt-2" style="color:#64748b;font-size:0.78rem;">
        ✅ Verified: <?= password_verify($plain, $hash) ? 'Hash is valid' : '❌ ERROR' ?>
    </div>
</div>
<?php endforeach; ?>

<div class="card" style="border-color:#1d4ed8;">
    <p style="color:#60a5fa;margin:0;">
        <strong>Instructions:</strong><br>
        1. Copy the hash for <span class="password-name">Admin@2025</span> → paste into seed.sql line 28<br>
        2. Copy the hash for <span class="password-name">Student@123</span> → paste into seed.sql for all student rows<br>
        3. Re-import seed.sql in phpMyAdmin<br>
        4. <strong>Delete this file!</strong>
    </p>
</div>

</body>
</html>
