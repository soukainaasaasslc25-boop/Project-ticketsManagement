<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? ''); $type = $_POST['type'] ?? '';
        $desc = trim($_POST['description'] ?? ''); $active = isset($_POST['is_active']) ? 1 : 0;
        if (!$name || !in_array($type, ['request','complaint'])) {
            header('Location: settings.php?section=categories&modal=1&error=Name+and+type+are+required'); exit;
        }
        $pdo->prepare("INSERT INTO categories (name,type,description,is_active) VALUES (?,?,?,?)")->execute([$name,$type,$desc?:null,$active]);
        header('Location: settings.php?section=categories&modal=1&msg=Category+added+successfully'); exit;
    }

    if ($action === 'edit_category') {
        $id = (int)($_POST['id']??0); $name = trim($_POST['name']??''); $type = $_POST['type']??'';
        $desc = trim($_POST['description']??''); $active = isset($_POST['is_active'])?1:0;
        if (!$id || !$name || !in_array($type,['request','complaint'])) {
            header('Location: settings.php?section=categories&modal=1&error=Invalid+data'); exit;
        }
        $pdo->prepare("UPDATE categories SET name=?,type=?,description=?,is_active=? WHERE id=?")->execute([$name,$type,$desc?:null,$active,$id]);
        header('Location: settings.php?section=categories&modal=1&msg=Category+updated+successfully'); exit;
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id']??0);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE category_id=?"); $stmt->execute([$id]);
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) { header("Location: settings.php?section=categories&modal=1&error=Cannot+delete:+{$count}+ticket(s)+linked+to+this+category"); exit; }
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        header('Location: settings.php?section=categories&modal=1&msg=Category+deleted'); exit;
    }

    if ($action === 'toggle_category') 
        {
        $id = (int)($_POST['id']??0);

        $pdo->prepare("UPDATE categories SET is_active=1-is_active WHERE id=?")->execute([$id]);
        
        header('Location: settings.php?section=categories&modal=1&msg=Status+updated'); exit;
    }

    if ($action === 'add_subcategory') {
        $catid = (int)($_POST['category_id']??0); $name = trim($_POST['name']??'');
        $desc = trim($_POST['description']??''); $active = isset($_POST['is_active'])?1:0;
        if (!$catid || !$name) { header('Location: settings.php?section=categories&modal=1&error=Category+and+name+are+required'); exit; }
        $pdo->prepare("INSERT INTO subcategories (category_id,name,description,is_active) VALUES (?,?,?,?)")->execute([$catid,$name,$desc?:null,$active]);
        header('Location: settings.php?section=categories&modal=1&msg=Subcategory+added+successfully'); exit;
    }

    if ($action === 'edit_subcategory') {
        $id = (int)($_POST['id']??0); $catid = (int)($_POST['category_id']??0);
        $name = trim($_POST['name']??''); $desc = trim($_POST['description']??''); $active = isset($_POST['is_active'])?1:0;
        if (!$id || !$catid || !$name) { header('Location: settings.php?section=categories&modal=1&error=All+fields+required'); exit; }
        $pdo->prepare("UPDATE subcategories SET category_id=?,name=?,description=?,is_active=? WHERE id=?")->execute([$catid,$name,$desc?:null,$active,$id]);
        header('Location: settings.php?section=categories&modal=1&msg=Subcategory+updated+successfully'); exit;
    }

    if ($action === 'delete_subcategory') {
        $id = (int)($_POST['id']??0);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE subcategory_id=?"); $stmt->execute([$id]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) { header("Location: settings.php?section=categories&modal=1&error=Cannot+delete:+{$count}+ticket(s)+linked+to+this+subcategory"); exit; }
        $pdo->prepare("DELETE FROM subcategories WHERE id=?")->execute([$id]);
        header('Location: settings.php?section=categories&modal=1&msg=Subcategory+deleted'); exit;
    }

    if ($action === 'toggle_subcategory') {
        $id = (int)($_POST['id']??0);
        $pdo->prepare("UPDATE subcategories SET is_active=1-is_active WHERE id=?")->execute([$id]);
        header('Location: settings.php?section=categories&modal=1&msg=Status+updated'); exit;
    }
}

// Read URL params
$msg        = $_GET['msg']     ?? '';
$error      = $_GET['error']   ?? '';
$section    = $_GET['section'] ?? 'account';
$open_modal = isset($_GET['modal']);

// Load data
$categories = $pdo->query("
    SELECT c.id,c.name,c.type,c.description,c.is_active,COUNT(t.id) AS ticket_count
    FROM categories c LEFT JOIN tickets t ON t.category_id=c.id AND t.status!='draft'
    GROUP BY c.id ORDER BY c.type ASC,c.name ASC")->fetchAll();

$subcategories = $pdo->query("
    SELECT s.id,s.name,s.description,s.is_active,s.category_id,c.name AS category_name
    FROM subcategories s JOIN categories c ON c.id=s.category_id
    ORDER BY c.name ASC,s.name ASC")->fetchAll();

$all_categories = $pdo->query("SELECT id,name,type FROM categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — UniPortal Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']},colors:{brand:{50:'#eef2ff',500:'#6366f1',600:'#4f46e5'}}}}}</script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-brand-500 selection:text-white">

<div class="flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 p-8">
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">System Settings</h1>
                <p class="text-slate-500 text-sm mt-1">Configure application parameters, ticket categories, and notifications.</p>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl px-4 py-3 text-sm">
            <i class="bi bi-check-circle-fill shrink-0"></i> <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="mb-5 flex items-center gap-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl px-4 py-3 text-sm">
            <i class="bi bi-exclamation-circle-fill shrink-0"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="md:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-2 space-y-1">
                    <?php
                    $navItems = [
                        ['account',       'bi-person-gear',   'Account Settings'],
                        ['categories',    'bi-tags',          'Categories'],
                        ['notifications', 'bi-bell',          'Notifications'],
                        ['security',      'bi-shield-lock',   'Security'],
                    ];
                    foreach ($navItems as [$key, $icon, $label]):
                        $active = $section === $key;
                    ?>
                    <a href="?section=<?= $key ?>"
                       class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors <?= $active ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        <i class="bi <?= $icon ?> text-lg"></i> <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="md:col-span-2">

                <?php if ($section === 'account'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                        <i class="bi bi-person-gear text-indigo-500 text-lg"></i>
                        <h2 class="font-bold text-slate-800">Account Settings (Placeholder)</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center gap-5 mb-8">
                            <div class="w-20 h-20 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl font-bold">
                                <?= mb_strtoupper(mb_substr($_SESSION['first_name'] ?? 'A',0,1).mb_substr($_SESSION['last_name'] ?? 'A',0,1)) ?>
                            </div>
                            <div>
                                <button class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold opacity-50 cursor-not-allowed">Change Avatar</button>
                                <p class="text-xs text-slate-400 mt-2">JPG, GIF or PNG. Max size of 800K</p>
                            </div>
                        </div>
                        <div class="space-y-4 max-w-md">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">First Name</label>
                                <input type="text" disabled value="<?= htmlspecialchars($_SESSION['first_name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Last Name</label>
                                <input type="text" disabled value="<?= htmlspecialchars($_SESSION['last_name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                                <input type="email" disabled value="admin@uniportal.edu" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-500 cursor-not-allowed">
                            </div>
                        </div>
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <p class="text-sm text-slate-500"><i class="bi bi-info-circle-fill text-indigo-400 mr-1"></i>These settings are read-only in the current version.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($section === 'categories'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                        <i class="bi bi-tags text-indigo-500 text-lg"></i>
                        <h2 class="font-bold text-slate-800">Categories</h2>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-500 mb-6">Manage ticket categories and subcategories. Only active items are visible to students.</p>
                        <button onclick="document.getElementById('modal-categories').classList.remove('hidden');document.getElementById('modal-categories').classList.add('flex')"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors shadow-sm shadow-indigo-200">
                            <i class="bi bi-tags"></i> Manage Categories
                        </button>
                        <div class="mt-6 flex gap-4">
                            <div class="bg-slate-50 rounded-xl px-6 py-4 text-center">
                                <p class="text-2xl font-bold text-slate-800"><?= count($categories) ?></p>
                                <p class="text-xs text-slate-500 mt-1">Categories</p>
                            </div>
                            <div class="bg-slate-50 rounded-xl px-6 py-4 text-center">
                                <p class="text-2xl font-bold text-slate-800"><?= count($subcategories) ?></p>
                                <p class="text-xs text-slate-500 mt-1">Subcategories</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($section === 'notifications'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                        <i class="bi bi-bell text-indigo-500 text-lg"></i>
                        <h2 class="font-bold text-slate-800">Notifications</h2>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-500"><i class="bi bi-info-circle-fill text-indigo-400 mr-1"></i>Notification settings are not available in the current version.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($section === 'security'): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-2">
                        <i class="bi bi-shield-lock text-indigo-500 text-lg"></i>
                        <h2 class="font-bold text-slate-800">Security</h2>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-500"><i class="bi bi-info-circle-fill text-indigo-400 mr-1"></i>Security settings are not available in the current version.</p>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<div id="modal-categories" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">

    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="text-lg font-bold text-slate-800"><i class="bi bi-tags text-indigo-500 mr-2"></i>Manage Categories</h3>
        <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
    </div>

    <div class="overflow-y-auto flex-1 p-6">

        <div id="inline-form" class="hidden mb-6 bg-indigo-50 border border-indigo-100 rounded-2xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h4 id="form-title" class="font-bold text-slate-800 text-sm"></h4>
                <button type="button" onclick="hideForm()" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
            </div>
            <form method="POST" id="inline-form-el" class="space-y-3">
                <input type="hidden" name="action" id="f-action">
                <input type="hidden" name="id" id="f-id">
                
                <div id="f-cat-wrapper">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Parent Category <span class="text-rose-500">*</span></label>
                    <select name="category_id" id="f-cat" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($all_categories as $c): ?>
                            <option value="<?= $c['id'] ?>">[<?= $c['type']==='request'?'D':'R' ?>] <?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="f-name" required placeholder="Enter name..."
                        class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div id="f-type-wrapper">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Type <span class="text-rose-500">*</span></label>
                    <select name="type" id="f-type" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="request">Demande (Request)</option>
                        <option value="complaint">Réclamation (Complaint)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <textarea name="description" id="f-desc" rows="2" placeholder="Short description..."
                        class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="f-active" value="1" checked class="w-4 h-4 accent-indigo-600">
                    <label for="f-active" class="text-sm font-medium text-slate-700">Active (visible to students)</label>
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition-colors">Save</button>
                    <button type="button" onclick="hideForm()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2 rounded-xl text-sm font-semibold transition-colors">Cancel</button>
                </div>
            </form>
        </div>

        <div class="mb-2">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-bold text-slate-800">Categories</h4>
                <button onclick="showCatForm(0,'','request','',1)"
                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                    <i class="bi bi-plus-lg"></i> Add Category
                </button>
            </div>
            <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Tickets</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No categories yet.</td></tr>
                <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr id="cat-row-<?= $cat['id'] ?>"
                    onclick="selectCategory(<?= $cat['id'] ?>, <?= htmlspecialchars(json_encode($cat['name'])) ?>)"
                    class="hover:bg-slate-50 cursor-pointer transition-colors">
                    <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($cat['name']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($cat['type']==='request'): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">Demande</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">Réclamation</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= (int)$cat['ticket_count'] ?></td>
                    <td class="px-4 py-3">
                        <?php if ($cat['is_active']): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3" onclick="event.stopPropagation()">
                        <div class="flex items-center gap-1">
                            <button onclick="event.stopPropagation(); showCatForm(<?= $cat['id'] ?>, <?= htmlspecialchars(json_encode($cat['name'])) ?>, '<?= $cat['type'] ?>', <?= htmlspecialchars(json_encode($cat['description']??'')) ?>, <?= $cat['is_active'] ?>)"
                                class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit">
                                <i class="bi bi-pencil text-xs"></i>
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_category">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors" title="Toggle">
                                    <i class="bi bi-arrow-repeat text-xs"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this category?')">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors" title="Delete">
                                    <i class="bi bi-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div id="sub-section" class="hidden mt-6">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-bold text-slate-800 text-sm">
                    Subcategories of: <span id="sub-cat-name" class="text-indigo-600"></span>
                </h4>
                <button onclick="showSubForm(0,selectedCatId,'','',1)"
                    class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                    <i class="bi bi-plus-lg"></i> Add Subcategory
                </button>
            </div>
            <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="sub-tbody">
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400 text-sm">Click a category above to view its subcategories.</td></tr>
                </tbody>
            </table>
            </div>
        </div>

    </div>
</div>
</div>

<script>
var allSubs = <?= json_encode($subcategories) ?>;
var selectedCatId = 0;

function closeModal() {
    document.getElementById('modal-categories').classList.add('hidden');
    document.getElementById('modal-categories').classList.remove('flex');
}

document.getElementById('modal-categories').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function selectCategory(id, name) {
    selectedCatId = id;
    document.querySelectorAll('[id^="cat-row-"]').forEach(function(row) {
        row.classList.remove('bg-indigo-50','ring-1','ring-indigo-200');
    });
    document.getElementById('cat-row-' + id).classList.add('bg-indigo-50','ring-1','ring-indigo-200');
    document.getElementById('sub-cat-name').textContent = name;
    renderSubs(id);
    document.getElementById('sub-section').classList.remove('hidden');
}

function renderSubs(catId) {
    var filtered = allSubs.filter(function(s) { return s.category_id == catId; });
    var tbody = document.getElementById('sub-tbody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-6 text-center text-slate-400 text-sm">No subcategories yet. Click Add Subcategory.</td></tr>';
        return;
    }
    var html = '';
    filtered.forEach(function(s) {
        var badge = s.is_active == 1
            ? '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>'
            : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">Inactive</span>';
        
        var safeName = s.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        var safeDesc = (s.description||'').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        
        html += '<tr class="hover:bg-slate-50 border-b border-slate-100">';
        html += '<td class="px-4 py-3 font-medium text-slate-800">' + escHtml(s.name) + '</td>';
        html += '<td class="px-4 py-3">' + badge + '</td>';
        html += '<td class="px-4 py-3"><div class="flex items-center gap-1">';
        html += '<button onclick="event.stopPropagation(); showSubForm(' + s.id + ',' + s.category_id + ',\'' + safeName + '\',\'' + safeDesc + '\',' + s.is_active + ')" class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50" title="Edit"><i class="bi bi-pencil text-xs"></i></button>';
        html += '<form method="POST" class="inline"><input type="hidden" name="action" value="toggle_subcategory"><input type="hidden" name="id" value="' + s.id + '"><button type="submit" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50" title="Toggle"><i class="bi bi-arrow-repeat text-xs"></i></button></form>';
        html += '<form method="POST" class="inline" onsubmit="return confirm(\'Delete this subcategory?\')"><input type="hidden" name="action" value="delete_subcategory"><input type="hidden" name="id" value="' + s.id + '"><button type="submit" class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50" title="Delete"><i class="bi bi-trash text-xs"></i></button></form>';
        html += '</div></td></tr>';
    });
    tbody.innerHTML = html;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showCatForm(id, name, type, desc, isActive) {
    document.getElementById('f-action').value = id ? 'edit_category' : 'add_category';
    document.getElementById('f-id').value     = id;
    document.getElementById('f-name').value   = name;
    document.getElementById('f-type').value   = type;
    document.getElementById('f-desc').value   = desc;
    document.getElementById('f-active').checked = isActive == 1;
    document.getElementById('form-title').textContent = id ? 'Edit Category' : 'Add New Category';
    document.getElementById('f-type-wrapper').classList.remove('hidden');
    document.getElementById('f-cat-wrapper').classList.add('hidden');
    document.getElementById('inline-form').classList.remove('hidden');
    document.getElementById('f-name').focus();
    document.getElementById('inline-form').scrollIntoView({behavior:'smooth', block:'nearest'});
}

function showSubForm(id, catId, name, desc, isActive) {
    document.getElementById('f-action').value = id ? 'edit_subcategory' : 'add_subcategory';
    document.getElementById('f-id').value     = id;
    document.getElementById('f-cat').value    = catId;
    document.getElementById('f-name').value   = name;
    document.getElementById('f-desc').value   = desc;
    document.getElementById('f-active').checked = isActive == 1;
    document.getElementById('form-title').textContent = id ? 'Edit Subcategory' : 'Add New Subcategory';
    document.getElementById('f-cat-wrapper').classList.remove('hidden');
    document.getElementById('f-type-wrapper').classList.add('hidden');
    document.getElementById('inline-form').classList.remove('hidden');
    document.getElementById('f-name').focus();
    document.getElementById('inline-form').scrollIntoView({behavior:'smooth', block:'nearest'});
}

function hideForm() {
    document.getElementById('inline-form').classList.add('hidden');
    document.getElementById('inline-form-el').reset();
}

<?php if ($open_modal): ?>
window.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modal-categories').classList.remove('hidden');
    document.getElementById('modal-categories').classList.add('flex');
});
<?php endif; ?>
</script>
</body>
</html>