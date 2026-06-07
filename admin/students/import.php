<?php
require_once __DIR__ . '/../../auth/auth_check.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
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
        $flash_error = "Security error (CSRF). Please try again.";
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $flash_error = "File upload error. Error code: " . ($_FILES['import_file']['error'] ?? 'unknown');
    } else {
        $file_tmp  = $_FILES['import_file']['tmp_name'];
        $file_name = $_FILES['import_file']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx'])) {
            $flash_error = "Unsupported format. Please use .xlsx or .csv.";
        } else {
            try {
                $spreadsheet = IOFactory::load($file_tmp);
                $worksheet   = $spreadsheet->getActiveSheet();
                $rows        = $worksheet->toArray();

                if (count($rows) <= 1) {
                    $flash_error = "The file is empty or only contains headers.";
                } else {
                    $header = array_map(function($h) { return mb_strtolower(trim((string)$h), 'UTF-8'); }, $rows[0]);
                    
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
                        if ($fn_idx === false) $missing[]  = "first name (or prénom)";
                        if ($ln_idx === false) $missing[]  = "last name (or nom)";
                        if ($grp_idx === false) $missing[] = "group (or groupe)";
                        
                        $flash_error = "Missing required columns: " . implode(', ', $missing) . ".";
                    } else {
                        $passwordHash = password_hash('Student@123', PASSWORD_BCRYPT);
                        $has_run      = true;

                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            
                            $fn  = trim((string)($row[$fn_idx] ?? ''));
                            $ln  = trim((string)($row[$ln_idx] ?? ''));
                            $grp = trim((string)($row[$grp_idx] ?? ''));

                            if ($fn === '' && $ln === '' && $grp === '') {
                                $skipped_count++;
                                continue;
                            }

                            $base_username = mb_strtolower(str_replace(' ', '', $ln . $fn), 'UTF-8');
                            $username = preg_replace('/[^a-z0-9]/', '', $base_username);

                            $grp_upper = mb_strtoupper($grp, 'UTF-8');
                            if (str_starts_with($grp_upper, 'DWB')) {
                                $filiere = 'Web Development';
                            } elseif (str_starts_with($grp_upper, 'DMB')) {
                                $filiere = 'Mobile Development';
                            } else {
                                $filiere = 'General';
                            }

                            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $check->execute([$username]);
                            if ($check->fetch()) {
                                $duplicates[] = $username;
                                $skipped_count++;
                                continue;
                            }

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
                            $flash_success = "Import completed successfully!";
                        } else {
                            $flash_error = "No students were imported. Please check for duplicates or empty rows.";
                        }
                    }
                }
            } catch (Exception $e) {
                $flash_error = "Error reading file: " . $e->getMessage();
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students — UniPortal Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Content Header -->
<div class="mb-6">
    <a href="/pfe/admin/students/index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-indigo-600 mb-2 transition-colors">
        <i class="bi bi-arrow-left me-1.5"></i> Back to Students
    </a>
    <h1 class="text-2xl font-bold text-slate-900">Import Students</h1>
    <p class="text-slate-500 text-sm mt-1">Upload an Excel (.xlsx) or CSV file to add multiple students at once.</p>
</div>

<!-- Alerts -->
<?php if ($flash_success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-emerald-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_success ?></div>
    </div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-xl shrink-0 mt-0.5"></i>
        <div class="text-sm font-medium"><?= $flash_error ?></div>
    </div>
<?php endif; ?>

<!-- Results Panel -->
<?php if ($has_run): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
        <h3 class="font-bold text-slate-800 mb-4 text-lg">Import Results</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-5">
            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-center">
                <div class="text-3xl font-bold text-emerald-600 mb-1"><?= $success_count ?></div>
                <div class="text-xs font-semibold text-emerald-700 uppercase">Successfully Imported</div>
            </div>
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-center">
                <div class="text-3xl font-bold text-amber-600 mb-1"><?= $skipped_count ?></div>
                <div class="text-xs font-semibold text-amber-700 uppercase">Rows Skipped</div>
            </div>
        </div>

        <?php if (!empty($duplicates)): ?>
            <div class="mt-4">
                <h4 class="text-sm font-semibold text-slate-700 mb-2">Skipped Accounts (existing usernames):</h4>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 max-h-48 overflow-y-auto text-xs font-mono text-slate-600">
                    <?= implode(', ', array_map('e', $duplicates)) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Upload Form -->
    <div class="md:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet text-indigo-500 text-lg"></i>
                <h2 class="font-bold text-slate-800">Select File</h2>
            </div>
            <form action="/pfe/admin/students/import.php" method="POST" enctype="multipart/form-data" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-2">.xlsx or .csv File</label>
                    <input type="file" name="import_file" accept=".xlsx,.csv" required
                           class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition-colors border border-slate-200 rounded-xl p-2 cursor-pointer bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5 mb-6 text-sm text-indigo-900 leading-relaxed">
                    <p class="font-bold mb-2 flex items-center gap-2"><i class="bi bi-info-circle-fill text-indigo-500"></i> Automated Actions</p>
                    <ul class="list-disc pl-5 space-y-1.5 text-indigo-800">
                        <li><strong>Username:</strong> Generated as lowercase(lastname + firstname)</li>
                        <li><strong>Password:</strong> Defaulted to <code>Student@123</code></li>
                        <li><strong>Field (Filière):</strong> DWB* groups mapped to Web Development, DMB* to Mobile.</li>
                        <li><strong>Status:</strong> Set to Inactive by default.</li>
                    </ul>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="flex items-center gap-2 bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-indigo-700 transition-colors shadow-sm shadow-indigo-200">
                        <i class="bi bi-upload"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div>
        <div class="bg-slate-800 text-white rounded-2xl p-6 shadow-sm sticky top-24">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2 text-indigo-300">
                <i class="bi bi-file-earmark-text"></i> Required Format
            </h3>
            <p class="text-sm text-slate-300 mb-4">The file must contain a header row with at least these columns (order does not matter):</p>
            
            <ul class="space-y-3 font-mono text-xs mb-5">
                <li class="bg-slate-700/50 p-3 rounded-xl border border-slate-600/50 flex justify-between items-center">
                    <span class="text-indigo-200 font-bold text-sm">first_name</span> <span class="text-slate-400">or prenom</span>
                </li>
                <li class="bg-slate-700/50 p-3 rounded-xl border border-slate-600/50 flex justify-between items-center">
                    <span class="text-indigo-200 font-bold text-sm">last_name</span> <span class="text-slate-400">or nom</span>
                </li>
                <li class="bg-slate-700/50 p-3 rounded-xl border border-slate-600/50 flex justify-between items-center">
                    <span class="text-indigo-200 font-bold text-sm">group_name</span> <span class="text-slate-400">or groupe</span>
                </li>
            </ul>

            <div class="bg-indigo-900/30 border border-indigo-500/20 p-4 rounded-xl text-xs text-indigo-200 leading-relaxed flex items-start gap-3">
                <i class="bi bi-lightbulb-fill text-indigo-400 mt-0.5"></i>
                <p>The file can contain as many extra columns as you want (e.g., DOB, Gender). They will be safely ignored by the importer.</p>
            </div>
        </div>
    </div>
</div>

        </main> <!-- /main from sidebar.php -->
    </div> <!-- /content wrapper from sidebar.php -->
</div> <!-- /layout flex from sidebar.php -->
</body>
</html>
