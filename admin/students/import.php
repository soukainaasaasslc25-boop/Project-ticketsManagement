<?php
// =============================================================================
// FILE    : admin/students/import.php
// PURPOSE : Import students from .xlsx or .csv
// =============================================================================

require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Composer autoload for PhpSpreadsheet
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$flash_success = '';
$flash_error   = '';
$duplicates    = [];
$success_count = 0;
$skipped_count = 0;
$has_run       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $flash_error = "Erreur de sécurité (CSRF). Veuillez réessayer.";
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $flash_error = "Erreur lors du téléchargement du fichier. Code erreur: " . ($_FILES['import_file']['error'] ?? 'inconnu');
    } else {
        $file_tmp  = $_FILES['import_file']['tmp_name'];
        $file_name = $_FILES['import_file']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx'])) {
            $flash_error = "Format non supporté. Veuillez utiliser un fichier .xlsx ou .csv.";
        } else {
            try {
                $spreadsheet = IOFactory::load($file_tmp);
                $worksheet   = $spreadsheet->getActiveSheet();
                $rows        = $worksheet->toArray();

                if (count($rows) <= 1) {
                    $flash_error = "Le fichier est vide ou ne contient que la ligne d'en-tête.";
                } else {
                    // Extract headers (first row) and normalize
                    $header = array_map(function($h) { return mb_strtolower(trim((string)$h), 'UTF-8'); }, $rows[0]);
                    
                    // Allow aliases for flexibility
                    $fn_aliases  = ['prenom', 'prénom', 'first_name', 'first name'];
                    $ln_aliases  = ['nom', 'last_name', 'last name'];
                    $grp_aliases = ['groupe', 'group_name', 'group name', 'group'];

                    $fn_idx  = false;
                    $ln_idx  = false;
                    $grp_idx = false;

                    foreach ($header as $idx => $colName) {
                        if ($fn_idx === false && in_array($colName, $fn_aliases, true)) $fn_idx = $idx;
                        if ($ln_idx === false && in_array($colName, $ln_aliases, true)) $ln_idx = $idx;
                        if ($grp_idx === false && in_array($colName, $grp_aliases, true)) $grp_idx = $idx;
                    }

                    if ($fn_idx === false || $ln_idx === false || $grp_idx === false) {
                        $missing = [];
                        if ($fn_idx === false) $missing[]  = "prénom (ou first_name)";
                        if ($ln_idx === false) $missing[]  = "nom (ou last_name)";
                        if ($grp_idx === false) $missing[] = "groupe (ou group_name)";
                        
                        $flash_error = "Colonnes requises manquantes : " . implode(', ', $missing) . ". L'ordre n'a pas d'importance, mais ces colonnes doivent exister.";
                    } else {
                        $passwordHash = password_hash('Student@123', PASSWORD_BCRYPT);
                        $has_run      = true;

                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            
                            $fn  = trim((string)($row[$fn_idx] ?? ''));
                            $ln  = trim((string)($row[$ln_idx] ?? ''));
                            $grp = trim((string)($row[$grp_idx] ?? ''));

                            // Skip empty rows
                            if ($fn === '' && $ln === '' && $grp === '') {
                                $skipped_count++;
                                continue;
                            }

                            // Generate username: lowercase(last_name + first_name) removing spaces
                            $base_username = mb_strtolower(str_replace(' ', '', $ln . $fn), 'UTF-8');
                            // Ensure safe characters (optional, but requested logic is simple)
                            $username = preg_replace('/[^a-z0-9]/', '', $base_username);

                            // Filiere logic
                            $grp_upper = mb_strtoupper($grp, 'UTF-8');
                            if (str_starts_with($grp_upper, 'DWB')) {
                                $filiere = 'Web Development';
                            } elseif (str_starts_with($grp_upper, 'DMB')) {
                                $filiere = 'Mobile Development';
                            } else {
                                $filiere = 'General';
                            }

                            // Check duplicate username
                            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $check->execute([$username]);
                            if ($check->fetch()) {
                                $duplicates[] = $username;
                                $skipped_count++;
                                continue;
                            }

                            // Insert new student
                            $ins = $pdo->prepare("
                                INSERT INTO users (username, password_hash, first_name, last_name, role, group_name, filiere, account_status)
                                VALUES (:username, :password_hash, :first_name, :last_name, 'student', :group_name, :filiere, 'inactive')
                            ");
                            if ($ins->execute([
                                ':username'      => $username,
                                ':password_hash' => $passwordHash,
                                ':first_name'    => $fn,
                                ':last_name'     => $ln,
                                ':group_name'    => $grp,
                                ':filiere'       => $filiere
                            ])) {
                                $success_count++;
                            } else {
                                $skipped_count++;
                            }
                        }
                        
                        if ($success_count > 0) {
                            $flash_success = "Import terminé avec succès !";
                        } else {
                            $flash_error = "Aucun étudiant n'a été importé (vérifiez les doublons ou le contenu).";
                        }
                    }
                }
            } catch (Exception $e) {
                $flash_error = "Erreur lors de la lecture du fichier : " . $e->getMessage();
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Étudiants — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- NAV -->
<nav class="bg-gradient-to-r from-slate-900 to-slate-700 shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="/pfe/admin/dashboard.php" class="flex items-center gap-2 text-white font-bold text-base">
            <i class="bi bi-ticket-perforated-fill text-blue-400"></i> TicketSystem
            <span class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded-full font-semibold ml-1">Admin</span>
        </a>
        <div class="flex items-center gap-1 text-sm flex-wrap">
            <a href="/pfe/admin/dashboard.php"       class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <a href="/pfe/admin/tickets/index.php"   class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-ticket-detailed me-1"></i>Tickets</a>
            <a href="/pfe/admin/students/index.php"  class="text-slate-300 hover:text-white hover:bg-white/10 px-3 py-1.5 rounded-lg transition"><i class="bi bi-people me-1"></i>Étudiants</a>
            <a href="/pfe/admin/students/import.php" class="text-white bg-white/15 px-3 py-1.5 rounded-lg font-semibold"><i class="bi bi-upload me-1"></i>Import</a>
            <a href="/pfe/auth/logout.php"           class="text-red-400 hover:text-red-300 hover:bg-white/10 px-3 py-1.5 rounded-lg transition ml-2"><i class="bi bi-box-arrow-left me-1"></i>Déconnexion</a>
        </div>
    </div>
</nav>

<div class="max-w-4xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Importer des étudiants</h1>
        <p class="text-slate-500 text-sm mt-1">Uploadez un fichier Excel (.xlsx) ou CSV pour ajouter des étudiants en masse.</p>
    </div>

    <!-- Alerts -->
    <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 flex gap-3 text-sm">
            <i class="bi bi-check-circle-fill text-green-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_success ?></div>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 flex gap-3 text-sm">
            <i class="bi bi-exclamation-circle-fill text-red-500 text-lg flex-shrink-0"></i>
            <div><?= $flash_error ?></div>
        </div>
    <?php endif; ?>

    <!-- Results panel -->
    <?php if ($has_run): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
            <h3 class="font-bold text-slate-800 mb-4 text-lg">Résultats de l'importation</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
                <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-center">
                    <div class="text-3xl font-bold text-green-600 mb-1"><?= $success_count ?></div>
                    <div class="text-xs font-semibold text-green-700 uppercase">Importés avec succès</div>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                    <div class="text-3xl font-bold text-amber-600 mb-1"><?= $skipped_count ?></div>
                    <div class="text-xs font-semibold text-amber-700 uppercase">Lignes ignorées</div>
                </div>
            </div>

            <?php if (!empty($duplicates)): ?>
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Comptes ignorés (noms d'utilisateur existants) :</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 max-h-48 overflow-y-auto text-xs font-mono text-slate-600">
                        <?= implode(', ', array_map('e', $duplicates)) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Form -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="font-bold text-slate-800">Sélectionner le fichier</h2>
                </div>
                <form action="/pfe/admin/students/import.php" method="POST" enctype="multipart/form-data" class="p-6">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Fichier .xlsx ou .csv</label>
                        <input type="file" name="import_file" accept=".xlsx,.csv" required
                               class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition border border-slate-200 rounded-xl p-2 cursor-pointer bg-slate-50">
                    </div>

                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 text-sm text-blue-800 leading-relaxed">
                        <p class="font-semibold mb-2"><i class="bi bi-info-circle-fill"></i> Ce que le système fera automatiquement :</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Créer le nom d'utilisateur (ex: <code>ASAAS SOUKAINA</code> &rarr; <code>asaassoukaina</code>)</li>
                            <li>Définir le mot de passe par défaut : <code>Student@123</code></li>
                            <li>Définir la filière (DWB* &rarr; Web, DMB* &rarr; Mobile)</li>
                            <li>Marquer le compte comme <strong>Inactif</strong> (à activer manuellement)</li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-blue-700 transition shadow-md shadow-blue-200">
                            <i class="bi bi-upload"></i> Lancer l'importation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Instructions -->
        <div>
            <div class="bg-slate-800 text-white rounded-2xl p-6 shadow-sm">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-blue-300">
                    <i class="bi bi-file-earmark-excel"></i> Format attendu
                </h3>
                <p class="text-sm text-slate-300 mb-4">Le fichier doit contenir une ligne d'en-tête (la première ligne) avec au moins ces colonnes (l'ordre n'a pas d'importance) :</p>
                
                <ul class="space-y-3 font-mono text-xs mb-5">
                    <li class="bg-slate-700 p-2 rounded-lg border border-slate-600 flex justify-between">
                        <span>prenom</span> <span class="text-slate-400">ou first_name</span>
                    </li>
                    <li class="bg-slate-700 p-2 rounded-lg border border-slate-600 flex justify-between">
                        <span>nom</span> <span class="text-slate-400">ou last_name</span>
                    </li>
                    <li class="bg-slate-700 p-2 rounded-lg border border-slate-600 flex justify-between">
                        <span>groupe</span> <span class="text-slate-400">ou group_name</span>
                    </li>
                </ul>

                <div class="bg-blue-900/40 border border-blue-500/30 p-3 rounded-lg text-xs text-blue-200 mt-4 leading-relaxed">
                    <i class="bi bi-lightbulb-fill text-blue-400"></i> Le fichier peut contenir autant de colonnes supplémentaires que vous voulez (ex: <i>DateNaissance</i>, <i>CIN</i>, <i>Sexe</i>...). Elles seront <strong>automatiquement ignorées</strong> par le système.
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>
