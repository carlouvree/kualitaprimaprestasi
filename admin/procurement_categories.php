<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'products';

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS procurement_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
} catch(Exception $e) {}

// Handle actions
if (isset($_GET['hapus_cat'])) {
    $id = (int)$_GET['hapus_cat'];
    $pdo->prepare("DELETE FROM procurement_categories WHERE id=?")->execute([$id]);
    header("Location: procurement_categories.php?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description'] ?? '');
        if (!empty($name)) {
            if ($_POST['action'] === 'edit' && !empty($_POST['id'])) {
                $pdo->prepare("UPDATE procurement_categories SET name=?, description=? WHERE id=?")->execute([$name, $desc, (int)$_POST['id']]);
            } else {
                $pdo->prepare("INSERT INTO procurement_categories (name,description) VALUES (?,?)")->execute([$name, $desc]);
            }
        }
        header("Location: procurement_categories.php?msg=success"); exit;
    }
}

$cats = $pdo->query("SELECT * FROM procurement_categories ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Kategori Pengadaan — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
<?php require_once '../includes/admin_sidebar.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Master Kategori Pengadaan</h1><p>Kelola kategori barang untuk pengadaan proyek</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Operasi berhasil!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-tags"></i> Daftar Kategori (<?= count($cats) ?>)</h3>
                <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fa-solid fa-plus"></i> Tambah Kategori</button>
            </div>
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Nama Kategori</th><th>Deskripsi</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if(empty($cats)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-tags"></i><p>Belum ada kategori. Silakan tambahkan.</p></div></td></tr>
                    <?php else: foreach($cats as $c): ?>
                        <tr>
                            <td><strong><?= $c['id'] ?></strong></td>
                            <td>
                                <strong style="color:var(--text-dark);"><?= htmlspecialchars($c['name']) ?></strong>
                                <br><span class="badge" style="background:rgba(99,102,241,0.1);color:var(--accent-color);font-size:10px;"><?= strtolower($c['name']) ?></span>
                            </td>
                            <td><small style="color:var(--text-light);"><?= htmlspecialchars($c['description'] ?: '-') ?></small></td>
                            <td><small><?= date('d M Y', strtotime($c['created_at'])) ?></small></td>
                            <td>
                                <div style="display:flex;gap:5px;">
                                    <button class="btn btn-icon edit" onclick='editCat(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-icon delete" onclick="if(confirm('Hapus kategori ini?'))location='procurement_categories.php?hapus_cat=<?= $c['id'] ?>'"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="card-body" style="padding:20px;">
                <p style="font-size:13px;color:var(--text-light);margin:0;"><i class="fa-solid fa-circle-info" style="color:var(--accent-color);"></i> Kategori yang ditambahkan di sini akan otomatis muncul di form <strong>Pengadaan Barang</strong> pada halaman Penugasan Proyek.</p>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div class="modal-overlay" id="modalAdd">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-tags" style="color:var(--accent-color);"></i> <span id="modalTitle">Tambah Kategori</span></h3>
            <button class="modal-close" onclick="closeModal('modalAdd')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="catId">
                <div class="form-group">
                    <label class="form-label">Nama Kategori <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="Contoh: Alat Berat" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi (Opsional)</label>
                    <input type="text" name="description" id="catDesc" class="form-control" placeholder="Keterangan singkat kategori ini">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('active'); }
function closeModal(id){ 
    document.getElementById(id).classList.remove('active');
    document.getElementById('catName').value='';
    document.getElementById('catDesc').value='';
    document.getElementById('catId').value='';
    document.getElementById('formAction').value='add';
    document.getElementById('modalTitle').textContent='Tambah Kategori';
}
function editCat(c){
    document.getElementById('catId').value=c.id;
    document.getElementById('catName').value=c.name;
    document.getElementById('catDesc').value=c.description||'';
    document.getElementById('formAction').value='edit';
    document.getElementById('modalTitle').textContent='Edit Kategori: '+c.name;
    openModal('modalAdd');
}
</script>
</body></html>
