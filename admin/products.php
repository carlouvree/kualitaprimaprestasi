<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('admin');
$current_page = 'products';

// Hapus
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmtImg = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmtImg->execute([$id]);
    $imgData = $stmtImg->fetch();
    if ($imgData && !empty($imgData['image_url'])) {
        $fp = '../uploads/products/' . $imgData['image_url'];
        if (file_exists($fp)) unlink($fp);
    }
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    header("Location: products.php?msg=deleted");
    exit;
}

// Tambah / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $status = $_POST['status'];
    $sku = trim($_POST['sku'] ?? '');
    $model = trim($_POST['model'] ?? '');

    $image_url = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'prod_' . time() . '.' . $ext;
        if (!is_dir('../uploads/products/')) mkdir('../uploads/products/', 0777, true);
        if(compress_image_upload($_FILES['image']['tmp_name'], '../uploads/products/' . $filename, 75, 1000)){
            $image_url = $filename;
        }
    }

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = $_POST['id'];
        if ($image_url != '') {
            $stmt = $pdo->prepare("UPDATE products SET name=?, category_id=?, status=?, sku=?, model=?, image_url=? WHERE id=?");
            $stmt->execute([$name, $category_id, $status, $sku, $model, $image_url, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, category_id=?, status=?, sku=?, model=? WHERE id=?");
            $stmt->execute([$name, $category_id, $status, $sku, $model, $id]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, status, sku, model, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category_id, $status, $sku, $model, $image_url]);
    }
    header("Location: products.php?msg=success");
    exit;
}

// Filter
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
if($filter_type === 'jual') {
    $products = $pdo->query("SELECT p.*, c.name as category_name, c.type FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.type='jual' ORDER BY p.id DESC")->fetchAll();
} elseif($filter_type === 'sewa') {
    $products = $pdo->query("SELECT p.*, c.name as category_name, c.type FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE c.type='sewa' ORDER BY p.id DESC")->fetchAll();
} else {
    $products = $pdo->query("SELECT p.*, c.name as category_name, c.type FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY type, name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Katalog — Admin KPP</title>
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
            <div><h1>Manajemen Katalog & Aset</h1><p>Kelola inventaris alat berat yang dijual dan disewakan</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>

    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Operasi database berhasil!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div style="display:flex; align-items:center; gap: 20px; flex-wrap:wrap;">
                    <h3><i class="fa-solid fa-box-open"></i> Daftar Aset</h3>
                    <ul class="filter-tabs">
                        <li><a href="products.php?type=all" class="<?= $filter_type==='all'?'active':'' ?>">Semua</a></li>
                        <li><a href="products.php?type=jual" class="<?= $filter_type==='jual'?'active':'' ?>"><i class="fa-solid fa-tag"></i> Jual</a></li>
                        <li><a href="products.php?type=sewa" class="<?= $filter_type==='sewa'?'active':'' ?>"><i class="fa-solid fa-handshake"></i> Sewa</a></li>
                    </ul>
                </div>
                <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fa-solid fa-plus"></i> Tambah Aset</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama Aset</th>
                            <th>Kategori</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($products) == 0): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada data aset.</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <td>
                                <?php if($p['image_url']): ?>
                                    <img src="../uploads/products/<?= $p['image_url'] ?>" class="img-thumb" alt="">
                                <?php else: ?>
                                    <div class="img-thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--primary-color); display:block;"><?= htmlspecialchars($p['name']) ?></strong>
                                <small style="color:var(--text-light);">SKU: <?= $p['sku'] ?? '-' ?> <?= $p['model'] ? '• '.$p['model'] : '' ?></small>
                            </td>
                            <td><?= $p['category_name'] ?? '-' ?></td>
                            <td><span class="badge badge-<?= $p['type'] ?? 'jual' ?>"><?= strtoupper($p['type'] ?? '-') ?></span></td>
                            <td><span class="badge badge-<?= strtolower($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td style="text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;">
                                    <button class="btn btn-icon edit" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-icon delete" onclick="if(confirm('Yakin hapus aset ini?')) location='products.php?hapus=<?= $p['id'] ?>'" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Add/Edit -->
<div class="modal-overlay" id="modalAdd">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-box-open" style="color:var(--secondary-accent);"></i> <span id="modalTitle">Tambah Aset Baru</span></h3>
            <button class="modal-close" onclick="closeModal('modalAdd')">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Nama Aset</label>
                    <input type="text" name="name" id="edit_name" class="form-control" placeholder="Contoh: Tower Crane TC-5013" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SKU (Opsional)</label>
                        <input type="text" name="sku" id="edit_sku" class="form-control" placeholder="KPP-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model (Opsional)</label>
                        <input type="text" name="model" id="edit_model" class="form-control" placeholder="CAT 320D">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="category_id" id="edit_category" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?> (<?= strtoupper($c['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="available">Tersedia</option>
                            <option value="rented">Disewa</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="sold">Terjual</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Upload Foto</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <p class="form-hint">Format: JPG, PNG. Maks 2MB.</p>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px;"><i class="fa-solid fa-save"></i> Simpan Data</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); resetForm(); }
function resetForm() {
    document.getElementById('edit_id').value = '';
    document.getElementById('edit_name').value = '';
    document.getElementById('edit_sku').value = '';
    document.getElementById('edit_model').value = '';
    document.getElementById('edit_category').value = '';
    document.getElementById('edit_status').value = 'available';
    document.getElementById('modalTitle').textContent = 'Tambah Aset Baru';
}
function editProduct(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_name').value = p.name;
    document.getElementById('edit_sku').value = p.sku || '';
    document.getElementById('edit_model').value = p.model || '';
    document.getElementById('edit_category').value = p.category_id;
    document.getElementById('edit_status').value = p.status;
    document.getElementById('modalTitle').textContent = 'Edit Aset: ' + p.name;
    openModal('modalAdd');
}
</script>
</body>
</html>
