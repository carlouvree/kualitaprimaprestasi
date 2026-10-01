<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'absensi';

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Hapus absensi
if (isset($_GET['hapus_absen'])) {
    $id = (int)$_GET['hapus_absen'];
    $pdo->prepare("DELETE FROM attendances WHERE id = ?")->execute([$id]);
    header("Location: absensi_monitor.php?date=$filter_date&msg=deleted"); exit;
}
// Hapus laporan
if (isset($_GET['hapus_report'])) {
    $id = (int)$_GET['hapus_report'];
    $pdo->prepare("DELETE FROM project_reports WHERE id = ?")->execute([$id]);
    header("Location: absensi_monitor.php?date=$filter_date&msg=deleted"); exit;
}

$stmtAbsensi = $pdo->prepare("SELECT a.*, u.full_name, u.username FROM attendances a JOIN users u ON a.user_id = u.id WHERE a.date = ? ORDER BY a.time_in ASC");
$stmtAbsensi->execute([$filter_date]);
$attendances = $stmtAbsensi->fetchAll();

$stmtRepo = $pdo->prepare("SELECT r.*, u.full_name FROM project_reports r JOIN users u ON r.user_id = u.id WHERE r.report_date = ? ORDER BY r.created_at DESC");
$stmtRepo->execute([$filter_date]);
$reports = $stmtRepo->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Lapangan — Admin KPP</title>
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
            <div><h1>Monitoring Lapangan</h1><p>Pantau kehadiran dan laporan proyek harian</p></div>
        </div>
        <div class="topbar-right">
            <form style="display:flex;gap:10px;align-items:center;" method="GET">
                <input type="date" name="date" class="form-control" value="<?= $filter_date ?>" style="width:auto;">
                <button type="submit" class="btn btn-accent btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="laporan_pdf.php?type=absensi&date=<?= $filter_date ?>" target="_blank" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export</a>
            </form>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Data berhasil dihapus.</div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid" style="margin-bottom:25px;">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info"><h4>Staff Hadir</h4><div class="stat-value"><?= count($attendances) ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-file-lines"></i></div>
                <div class="stat-info"><h4>Laporan Masuk</h4><div class="stat-value"><?= count($reports) ?></div></div>
            </div>
        </div>

        <!-- Tabel Absensi -->
        <div class="card" style="margin-bottom:25px;">
            <div class="card-header"><h3><i class="fa-solid fa-clipboard-user"></i> Log Absensi (Selfie & GPS)</h3></div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Pegawai</th><th>Masuk</th><th>Selfie & GPS Masuk</th><th>Pulang</th><th>Selfie & GPS Pulang</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php if(count($attendances)===0): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada absensi.</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach($attendances as $a): ?>
                        <tr>
                            <td><strong style="color:var(--primary-color);"><?= htmlspecialchars($a['full_name']) ?></strong></td>
                            <td><span class="badge badge-available"><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($a['time_in'])) ?></span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <?php if($a['photo_in']): ?>
                                    <a href="../uploads/attendances/<?= $a['photo_in'] ?>" target="_blank"><img src="../uploads/attendances/<?= $a['photo_in'] ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:var(--shadow-sm);"></a>
                                    <?php endif; ?>
                                    <?php if($a['latitude_in']): ?>
                                    <a href="https://maps.google.com/?q=<?= $a['latitude_in'] ?>,<?= $a['longitude_in'] ?>" target="_blank" style="font-size:12px;color:var(--secondary-accent);font-weight:600;"><i class="fa-solid fa-location-dot"></i> Peta</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php if($a['time_out']): ?><span class="badge badge-sold"><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($a['time_out'])) ?></span><?php else: ?><span style="color:#aaa;font-size:13px;font-style:italic;">Belum</span><?php endif; ?></td>
                            <td>
                                <?php if($a['time_out'] && $a['photo_out']): ?>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <a href="../uploads/attendances/<?= $a['photo_out'] ?>" target="_blank"><img src="../uploads/attendances/<?= $a['photo_out'] ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:var(--shadow-sm);"></a>
                                    <?php if($a['latitude_out']): ?>
                                    <a href="https://maps.google.com/?q=<?= $a['latitude_out'] ?>,<?= $a['longitude_out'] ?>" target="_blank" style="font-size:12px;color:var(--secondary-accent);font-weight:600;"><i class="fa-solid fa-location-dot"></i> Peta</a>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?><span style="color:#aaa;">-</span><?php endif; ?>
                            </td>
                            <td><button type="button" class="btn btn-icon delete" onclick="showConfirm('Hapus data absensi ini?', () => location='absensi_monitor.php?date=<?= $filter_date ?>&hapus_absen=<?= $a['id'] ?>')"><i class="fa-solid fa-trash-can"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Laporan Proyek -->
        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-file-signature"></i> Laporan Aktivitas Lapangan</h3></div>
            <div class="card-body">
                <?php if(count($reports)===0): ?>
                <div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada laporan masuk.</p></div>
                <?php else: ?>
                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px;">
                    <?php foreach($reports as $r): ?>
                    <div style="border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; transition:var(--transition);">
                        <?php $img = empty($r['photo_evidence']) ? '' : '../uploads/reports/'.$r['photo_evidence']; ?>
                        <?php if($img): ?>
                        <a href="<?= $img ?>" target="_blank"><img src="<?= $img ?>" style="width:100%;height:200px;object-fit:cover;" onerror="this.style.display='none'"></a>
                        <?php endif; ?>
                        <div style="padding:16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <strong style="color:var(--primary-color);font-family:'Montserrat',sans-serif;font-size:14px;"><i class="fa-solid fa-user-gear"></i> <?= htmlspecialchars($r['full_name']) ?></strong>
                                <button type="button" class="btn btn-icon delete" style="width:28px;height:28px;font-size:12px;" onclick="showConfirm('Hapus laporan ini?', () => location='absensi_monitor.php?date=<?= $filter_date ?>&hapus_report=<?= $r['id'] ?>')"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                            <p style="font-size:13px;color:var(--text-light);line-height:1.6;margin-bottom:10px;"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                            <small style="color:#aaa;font-size:12px;"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($r['created_at'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal Konfirmasi Custom -->
<div class="modal-overlay" id="modalConfirm" style="z-index: 2000;">
    <div class="modal-box" style="max-width:380px; text-align:center;">
        <div class="modal-body" style="padding:30px 20px;">
            <div style="width:60px; height:60px; background:rgba(245,158,11,0.1); color:var(--warning); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:24px;">
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <h3 style="font-family:'Montserrat',sans-serif; margin-bottom:10px;">Konfirmasi</h3>
            <p id="confirmMessage" style="font-size:14px; color:var(--text-light); margin-bottom:25px;"></p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button type="button" class="btn" style="background:#f1f5f9; color:#64748b;" onclick="closeModal('modalConfirm')">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script>
// Custom Confirm Handler
let confirmCallback = null;
function showConfirm(msg, callback) {
    document.getElementById('confirmMessage').textContent = msg;
    confirmCallback = callback;
    document.getElementById('confirmActionBtn').onclick = function() {
        if(confirmCallback) confirmCallback();
        closeModal('modalConfirm');
    };
    document.getElementById('modalConfirm').classList.add('active');
}

function closeModal(id){ document.getElementById(id).classList.remove('active'); }
</script>
</body>
</html>
