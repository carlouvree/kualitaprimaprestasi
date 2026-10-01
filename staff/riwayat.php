<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('staff');
$current_page = 'riwayat';
$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'absensi';

// Absensi 7 hari
$stmt7 = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC LIMIT 7");
$stmt7->execute([$user_id]); $absen7 = $stmt7->fetchAll();

// Absensi semua
$stmtAll = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC");
$stmtAll->execute([$user_id]); $absenAll = $stmtAll->fetchAll();

// Laporan 7 hari
$start7 = date('Y-m-d', strtotime('-7 days'));
$stmtR7 = $pdo->prepare("SELECT * FROM project_reports WHERE user_id = ? AND report_date >= ? ORDER BY created_at DESC");
$stmtR7->execute([$user_id, $start7]); $report7 = $stmtR7->fetchAll();

// Laporan semua
$stmtRAll = $pdo->prepare("SELECT * FROM project_reports WHERE user_id = ? ORDER BY created_at DESC");
$stmtRAll->execute([$user_id]); $reportAll = $stmtRAll->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat — KPP Staff</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250813">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body class="staff-body">
<?php require_once '../includes/staff_nav.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Riwayat Lengkap</h1><p>Absensi dan laporan selama bekerja</p></div>
        </div>
        <div class="topbar-right"><div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?></div></div>
    </div>
    <div class="portal-content">
        <!-- Tab Switcher -->
        <div style="display:flex;gap:10px;margin-bottom:25px;">
            <a href="riwayat.php?tab=absensi" class="btn <?= $tab==='absensi'?'btn-primary':'btn-outline' ?>"><i class="fa-solid fa-clipboard-user"></i> Absensi</a>
            <a href="riwayat.php?tab=laporan" class="btn <?= $tab==='laporan'?'btn-primary':'btn-outline' ?>"><i class="fa-solid fa-file-lines"></i> Laporan</a>
        </div>

        <?php if($tab === 'absensi'): ?>
        <!-- Absensi 7 Hari -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h3><i class="fa-solid fa-calendar-week"></i> 7 Hari Terakhir</h3><span class="badge badge-staff"><?= count($absen7) ?> hari</span></div>
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Lokasi Masuk</th></tr></thead>
                    <tbody>
                    <?php if(empty($absen7)): ?>
                        <tr><td colspan="4"><div class="empty-state"><p>Belum ada data.</p></div></td></tr>
                    <?php else: foreach($absen7 as $a): ?>
                    <tr onclick='openAbsenDetail(<?= htmlspecialchars(json_encode($a)) ?>)' style="cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="font-weight:700;color:var(--text-dark);"><?= date('d M Y',strtotime($a['date'])) ?></td>
                        <td><span class="badge badge-available"><?= date('H:i',strtotime($a['time_in'])) ?></span></td>
                        <td><?= $a['time_out']?'<span class="badge badge-sold">'.date('H:i',strtotime($a['time_out'])).'</span>':'<span style="color:var(--text-light);">-</span>' ?></td>
                        <td style="font-size:12px;">
                            <?php if($a['latitude_in']): ?>
                                <span class="gps-address" data-lat="<?= $a['latitude_in'] ?>" data-lng="<?= $a['longitude_in'] ?>"><i class="fa-solid fa-spinner fa-spin"></i> Memuat lokasi...</span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><button class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Absensi Lengkap -->
        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-clock-rotate-left"></i> Semua Riwayat Absensi</h3><span class="badge badge-admin"><?= count($absenAll) ?> total</span></div>
            <div class="card-body" style="padding:0;max-height:400px;overflow-y:auto;">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th style="width:50px;">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach($absenAll as $a): ?>
                    <tr onclick='openAbsenDetail(<?= htmlspecialchars(json_encode($a)) ?>)' style="cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="font-weight:600;"><?= date('d M Y',strtotime($a['date'])) ?></td>
                        <td><span class="badge badge-available"><?= date('H:i',strtotime($a['time_in'])) ?></span></td>
                        <td><?= $a['time_out']?'<span class="badge badge-sold">'.date('H:i',strtotime($a['time_out'])).'</span>':'-' ?></td>
                        <td><button class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <!-- Laporan 7 Hari -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h3><i class="fa-solid fa-calendar-week"></i> 7 Hari Terakhir</h3><span class="badge badge-staff"><?= count($report7) ?> laporan</span></div>
            <div class="card-body">
                <?php if(empty($report7)): ?>
                    <div class="empty-state"><p>Belum ada laporan.</p></div>
                <?php else: foreach($report7 as $r): ?>
                <div style="display:flex;gap:15px;padding:15px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:12px;transition:var(--transition);align-items:center;" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='none'">
                    <?php if(!empty($r['photo_evidence'])): ?>
                    <img src="../uploads/reports/<?= $r['photo_evidence'] ?>" style="width:60px;height:60px;border-radius:8px;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                    <div style="width:60px;height:60px;border-radius:8px;background:var(--bg-gray);display:flex;align-items:center;justify-content:center;color:var(--border);font-size:20px;flex-shrink:0;"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:0;">
                        <a href="laporan_detail.php?id=<?= $r['id'] ?>" style="font-weight:700;color:var(--text-dark);font-size:14px;display:block;margin-bottom:4px;"><?= htmlspecialchars(mb_substr($r['description'],0,80)) ?>...</a>
                        <small style="color:var(--text-light);"><i class="fa-regular fa-calendar"></i> <?= date('d M Y, H:i',strtotime($r['created_at'])) ?></small>
                    </div>
                    <a href="laporan_detail.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <!-- Laporan Lengkap -->
        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-clock-rotate-left"></i> Semua Laporan</h3><span class="badge badge-admin"><?= count($reportAll) ?> total</span></div>
            <div class="card-body" style="max-height:400px;overflow-y:auto;">
                <?php if(empty($reportAll)): ?>
                    <div class="empty-state"><p>Belum ada laporan.</p></div>
                <?php else: foreach($reportAll as $r): ?>
                <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);align-items:center;">
                    <div class="activity-icon" style="background:rgba(56,189,248,0.1);color:var(--secondary-accent);"><i class="fa-solid fa-file"></i></div>
                    <div style="flex:1;min-width:0;">
                        <a href="laporan_detail.php?id=<?= $r['id'] ?>" style="font-weight:600;font-size:13px;color:var(--text-dark);"><?= htmlspecialchars(mb_substr($r['description'],0,60)) ?>...</a>
                        <div style="font-size:11px;color:var(--text-light);"><?= date('d M Y',strtotime($r['report_date'])) ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
// Reverse geocode GPS addresses
document.querySelectorAll('.gps-address').forEach(el => {
    const lat = el.dataset.lat, lng = el.dataset.lng;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`)
    .then(r=>r.json()).then(d=>{
        el.innerHTML = '<i class="fa-solid fa-location-dot" style="color:var(--secondary-accent);"></i> '+(d.display_name ? d.display_name.split(',').slice(0,3).join(', ') : lat+', '+lng);
        el.dataset.addressName = d.display_name; // Store full address
    }).catch(()=>{ el.textContent = lat+', '+lng; });
});

function openAbsenDetail(data) {
    document.getElementById('mdlAbsenDate').textContent = data.date;
    
    // Set IN data
    document.getElementById('mdlTimeIn').textContent = data.time_in ? data.time_in : '-';
    document.getElementById('mdlCoordsIn').textContent = data.latitude_in ? data.latitude_in + ', ' + data.longitude_in : '-';
    let photoIn = document.getElementById('mdlPhotoIn');
    if (data.photo_in) {
        photoIn.src = '../uploads/absen/' + data.photo_in;
        photoIn.style.display = 'block';
    } else {
        photoIn.style.display = 'none';
    }

    // Set OUT data
    document.getElementById('mdlTimeOut').textContent = data.time_out ? data.time_out : '-';
    document.getElementById('mdlCoordsOut').textContent = data.latitude_out ? data.latitude_out + ', ' + data.longitude_out : '-';
    let photoOut = document.getElementById('mdlPhotoOut');
    if (data.photo_out) {
        photoOut.src = '../uploads/absen/' + data.photo_out;
        photoOut.style.display = 'block';
    } else {
        photoOut.style.display = 'none';
    }

    document.getElementById('modalAbsenDetail').classList.add('active');
}
</script>

<!-- Modal for Absen Detail -->
<div class="modal-overlay" id="modalAbsenDetail">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-clipboard-user" style="color:var(--accent-color);"></i> Detail Kehadiran</h3>
            <button class="modal-close" onclick="document.getElementById('modalAbsenDetail').classList.remove('active')" type="button">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="text-align:center; font-weight:700; font-size:18px; color:var(--text-dark); margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;">
                Tanggal: <span id="mdlAbsenDate" style="color:var(--accent-color);"></span>
            </p>
            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:250px; background:rgba(16,185,129,0.05); border:1px solid rgba(16,185,129,0.2); border-radius:8px; padding:15px;">
                    <h4 style="margin:0 0 10px; color:var(--success); border-bottom:1px solid rgba(16,185,129,0.2); padding-bottom:8px;"><i class="fa-solid fa-right-to-bracket"></i> Check-In Masuk</h4>
                    <p style="margin:0 0 5px; font-size:13px; color:var(--text-light);">Waktu: <strong id="mdlTimeIn" style="color:var(--text-dark); font-size:15px;"></strong> WIB</p>
                    <p style="margin:0 0 15px; font-size:12px; color:var(--text-light);">Koordinat GPS: <br><span id="mdlCoordsIn" style="color:var(--text-dark);"></span></p>
                    <img id="mdlPhotoIn" src="" style="width:100%; border-radius:6px; object-fit:cover; display:none; max-height:200px; cursor:pointer;" onclick="window.open(this.src)">
                </div>

                <div style="flex:1; min-width:250px; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.2); border-radius:8px; padding:15px;">
                    <h4 style="margin:0 0 10px; color:var(--danger); border-bottom:1px solid rgba(239,68,68,0.2); padding-bottom:8px;"><i class="fa-solid fa-right-from-bracket"></i> Check-Out Pulang</h4>
                    <p style="margin:0 0 5px; font-size:13px; color:var(--text-light);">Waktu: <strong id="mdlTimeOut" style="color:var(--text-dark); font-size:15px;"></strong> WIB</p>
                    <p style="margin:0 0 15px; font-size:12px; color:var(--text-light);">Koordinat GPS: <br><span id="mdlCoordsOut" style="color:var(--text-dark);"></span></p>
                    <img id="mdlPhotoOut" src="" style="width:100%; border-radius:6px; object-fit:cover; display:none; max-height:200px; cursor:pointer;" onclick="window.open(this.src)">
                </div>
            </div>
            <button class="btn btn-outline" style="width:100%; margin-top:20px; padding:12px;" onclick="document.getElementById('modalAbsenDetail').classList.remove('active')">Tutup</button>
        </div>
    </div>
</div>

</body>
</html>
