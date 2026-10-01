<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('admin');
$current_page = 'portfolios';

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmtImg = $pdo->prepare("SELECT main_image FROM portfolios WHERE id = ?");
    $stmtImg->execute([$id]);
    $imgData = $stmtImg->fetch();
    if ($imgData && !empty($imgData['main_image'])) {
        $fp = '../uploads/portfolios/' . $imgData['main_image'];
        if (file_exists($fp)) unlink($fp);
    }
    $pdo->prepare("DELETE FROM portfolios WHERE id = ?")->execute([$id]);
    header("Location: portfolios.php?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $client_name = trim($_POST['client_name']);
    $location = trim($_POST['location'] ?? '');
    $project_date = empty($_POST['project_date']) ? null : $_POST['project_date'];
    $description = trim($_POST['description']);

    $image_url = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $ext = strtolower(pathinfo(basename($_FILES['image']['name']), PATHINFO_EXTENSION));
        $allowed_img_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed_img_ext)) {
            $filename = 'porto_' . time() . '.' . $ext;
            if (!is_dir('../uploads/portfolios/')) mkdir('../uploads/portfolios/', 0755, true);
            if(compress_image_upload($_FILES['image']['tmp_name'], '../uploads/portfolios/' . $filename, 75, 1200)) $image_url = $filename;
        }
    }

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        $id = (int)$_POST['id']; // Cast integer untuk keamanan
        if ($image_url != '') {
            $stmt = $pdo->prepare("UPDATE portfolios SET title=?, client_name=?, location=?, completion_date=?, description=?, main_image=? WHERE id=?");
            $stmt->execute([$title, $client_name, $location, $project_date, $description, $image_url, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE portfolios SET title=?, client_name=?, location=?, completion_date=?, description=? WHERE id=?");
            $stmt->execute([$title, $client_name, $location, $project_date, $description, $id]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO portfolios (title, client_name, location, completion_date, description, main_image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $client_name, $location, $project_date, $description, $image_url]);
    }
    header("Location: portfolios.php?msg=success"); exit;
}

$portfolios = $pdo->query("SELECT * FROM portfolios ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Portofolio — Admin KPP</title>
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
            <div><h1>Portofolio Proyek</h1><p>Kelola galeri proyek yang telah diselesaikan</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Data berhasil diperbarui!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-briefcase"></i> Daftar Proyek</h3>
                <button class="btn btn-primary" onclick="openModal('modalAdd')"><i class="fa-solid fa-plus"></i> Tambah Proyek</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Foto</th><th>Judul / Klien</th><th>Lokasi</th><th>Tanggal</th><th style="text-align:center;">Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($portfolios) == 0): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada data proyek.</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach($portfolios as $p): ?>
                        <tr>
                            <td>
                                <?php if($p['main_image']): ?>
                                    <img src="../uploads/portfolios/<?= $p['main_image'] ?>" class="img-thumb" alt="">
                                <?php else: ?>
                                    <div class="img-thumb-placeholder"><i class="fa-solid fa-camera"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--primary-color); display:block;"><?= htmlspecialchars($p['title']) ?></strong>
                                <small style="color:var(--text-light);"><i class="fa-regular fa-building"></i> <?= htmlspecialchars($p['client_name'] ?: '-') ?></small>
                            </td>
                            <td style="color:var(--text-light);font-size:13px;"><?= htmlspecialchars($p['location'] ?: '-') ?></td>
                            <td style="font-size:13px;"><?= $p['completion_date'] ? date('d M Y', strtotime($p['completion_date'])) : '-' ?></td>
                            <td style="text-align:center;">
                                <div style="display:flex;gap:6px;justify-content:center;">
                                    <button class="btn btn-icon edit" onclick='editPorto(<?= json_encode($p) ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-icon delete" onclick="if(confirm('Hapus proyek ini?')) location='portfolios.php?hapus=<?= $p['id'] ?>'" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
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

<div class="modal-overlay" id="modalAdd">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-briefcase" style="color:var(--accent-color);"></i> <span id="mTitle">Tambah Proyek</span></h3>
            <button class="modal-close" onclick="closeModal('modalAdd')">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="e_id">
                <div class="form-group"><label class="form-label">Judul Proyek</label><input type="text" name="title" id="e_title" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Klien</label><input type="text" name="client_name" id="e_client" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Lokasi</label><input type="text" name="location" id="e_location" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Tanggal Selesai</label><input type="date" name="project_date" id="e_date" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Foto</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                </div>
                <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="description" id="e_desc" class="form-control" rows="3"></textarea></div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');document.getElementById('e_id').value='';document.getElementById('mTitle').textContent='Tambah Proyek';}
function editPorto(p){
    document.getElementById('e_id').value=p.id;
    document.getElementById('e_title').value=p.title;
    document.getElementById('e_client').value=p.client_name||'';
    document.getElementById('e_location').value=p.location||'';
    document.getElementById('e_date').value=p.completion_date||'';
    document.getElementById('e_desc').value=p.description||'';
    document.getElementById('mTitle').textContent='Edit: '+p.title;
    openModal('modalAdd');
}
</script>
</body>
</html>
