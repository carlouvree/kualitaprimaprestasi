<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'users'; // Active sidebar link

$target_user_id = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'absensi';

$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$target_user_id]);
$targetUser = $stmtUser->fetch();

if(!$targetUser) {
    header("Location: users.php"); exit;
}

// Absensi 7 hari
$stmt7 = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC LIMIT 7");
$stmt7->execute([$target_user_id]); $absen7 = $stmt7->fetchAll();

// Absensi semua
$stmtAll = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC");
$stmtAll->execute([$target_user_id]); $absenAll = $stmtAll->fetchAll();

// Laporan 7 hari
$start7 = date('Y-m-d', strtotime('-7 days'));
$stmtR7 = $pdo->prepare("SELECT pr.*, t.title as project_title FROM project_reports pr LEFT JOIN tasks t ON pr.task_id = t.id WHERE pr.user_id = ? AND pr.report_date >= ? ORDER BY pr.created_at DESC");
$stmtR7->execute([$target_user_id, $start7]); $report7 = $stmtR7->fetchAll();

// Laporan semua
$stmtRAll = $pdo->prepare("SELECT pr.*, t.title as project_title FROM project_reports pr LEFT JOIN tasks t ON pr.task_id = t.id WHERE pr.user_id = ? ORDER BY pr.created_at DESC");
$stmtRAll->execute([$target_user_id]); $reportAll = $stmtRAll->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Staff — Admin KPP</title>
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
            <div><h1>Riwayat: <?= htmlspecialchars($targetUser['full_name']) ?></h1><p>Absensi dan laporan staff</p></div>
        </div>
        <div class="topbar-right" style="display:flex; gap:10px;">
            <a href="laporan_pdf.php?type=absensi&user_id=<?= $target_user_id ?>" target="_blank" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export Absen</a>
            <a href="laporan_pdf.php?type=laporan_harian&user_id=<?= $target_user_id ?>" target="_blank" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export Laporan</a>
            <a href="users.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
    <div class="portal-content">
        <!-- Tab Switcher -->
        <div style="display:flex;gap:10px;margin-bottom:25px;">
            <a href="staff_riwayat.php?id=<?= $target_user_id ?>&tab=absensi" class="btn <?= $tab==='absensi'?'btn-primary':'btn-outline' ?>"><i class="fa-solid fa-clipboard-user"></i> Absensi</a>
            <a href="staff_riwayat.php?id=<?= $target_user_id ?>&tab=laporan" class="btn <?= $tab==='laporan'?'btn-primary':'btn-outline' ?>"><i class="fa-solid fa-file-lines"></i> Laporan</a>
        </div>

        <?php if($tab === 'absensi'): ?>
        <!-- Absensi 7 Hari -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h3><i class="fa-solid fa-calendar-week"></i> 7 Hari Terakhir</h3><span class="badge badge-staff"><?= count($absen7) ?> hari</span></div>
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th style="width:50px;">Aksi</th></tr></thead>
                    <tbody>
                    <?php if(empty($absen7)): ?>
                        <tr><td colspan="4"><div class="empty-state"><p>Belum ada data.</p></div></td></tr>
                    <?php else: foreach($absen7 as $a): ?>
                    <tr onclick='openAbsenDetail(<?= htmlspecialchars(json_encode($a)) ?>)' style="cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="font-weight:700;color:var(--text-dark);"><?= date('d M Y',strtotime($a['date'])) ?></td>
                        <td><span class="badge badge-available"><?= date('H:i',strtotime($a['time_in'])) ?></span></td>
                        <td><?= $a['time_out']?'<span class="badge badge-sold">'.date('H:i',strtotime($a['time_out'])).'</span>':'<span style="color:var(--text-light);">-</span>' ?></td>
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
        <!-- Laporan Semua -->
        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-file-signature"></i> Seluruh Laporan Harian</h3><span class="badge badge-admin"><?= count($reportAll) ?> laporan</span></div>
            <div class="card-body" style="padding:0;max-height:500px;overflow-y:auto;">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Terkait Proyek</th><th>Status</th><th style="width:50px;">Aksi</th></tr></thead>
                    <tbody>
                    <?php if(empty($reportAll)): ?>
                        <tr><td colspan="4"><div class="empty-state"><p>Belum ada laporan.</p></div></td></tr>
                    <?php else: foreach($reportAll as $r): ?>
                    <tr onclick='openReportDetail(<?= htmlspecialchars(json_encode($r)) ?>)' style="cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td>
                            <strong><?= date('d M Y',strtotime($r['report_date'])) ?></strong><br>
                            <small style="color:var(--text-light);"><?= date('H:i',strtotime($r['created_at'])) ?> WIB</small>
                        </td>
                        <td><?= $r['project_title'] ? htmlspecialchars($r['project_title']) : '-' ?></td>
                        <td>
                            <?php if($r['status'] === 'approved') echo '<span class="badge badge-success">Disetujui</span>'; ?>
                            <?php if($r['status'] === 'rejected') echo '<span class="badge badge-danger">Ditolak</span>'; ?>
                            <?php if($r['status'] === 'pending') echo '<span class="badge badge-warning">Pending</span>'; ?>
                        </td>
                        <td><button class="btn btn-sm btn-outline"><i class="fa-solid fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

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
                    <p style="margin:0 0 15px; font-size:12px; color:var(--text-light);">Lokasi GPS: <br><strong id="mdlCoordsIn" style="color:var(--text-dark);"></strong></p>
                    <img id="mdlPhotoIn" src="" style="width:100%; border-radius:6px; object-fit:cover; display:none; max-height:200px; cursor:pointer;" onclick="window.open(this.src)">
                </div>

                <div style="flex:1; min-width:250px; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.2); border-radius:8px; padding:15px;">
                    <h4 style="margin:0 0 10px; color:var(--danger); border-bottom:1px solid rgba(239,68,68,0.2); padding-bottom:8px;"><i class="fa-solid fa-right-from-bracket"></i> Check-Out Pulang</h4>
                    <p style="margin:0 0 5px; font-size:13px; color:var(--text-light);">Waktu: <strong id="mdlTimeOut" style="color:var(--text-dark); font-size:15px;"></strong> WIB</p>
                    <p style="margin:0 0 15px; font-size:12px; color:var(--text-light);">Lokasi GPS: <br><strong id="mdlCoordsOut" style="color:var(--text-dark);"></strong></p>
                    <img id="mdlPhotoOut" src="" style="width:100%; border-radius:6px; object-fit:cover; display:none; max-height:200px; cursor:pointer;" onclick="window.open(this.src)">
                </div>
            </div>
            <button class="btn btn-outline" style="width:100%; margin-top:20px; padding:12px;" onclick="document.getElementById('modalAbsenDetail').classList.remove('active')">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Report Detail -->
<div class="modal-overlay" id="modalReportDetail">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-signature" style="color:var(--accent-color);"></i> Detail Laporan Harian</h3>
            <button class="modal-close" onclick="document.getElementById('modalReportDetail').classList.remove('active')" type="button">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--border); padding-bottom:15px; margin-bottom:15px;">
                <div>
                    <h4 id="detStaffName" style="margin:0 0 5px; font-family:'Montserrat',sans-serif; color:var(--text-dark); font-size:16px;"></h4>
                    <p id="detDate" style="margin:0; font-size:13px; color:var(--text-light);"></p>
                </div>
                <div id="detStatusBadge"></div>
            </div>
            
            <div id="detProjectInfo" style="background:rgba(99,102,241,0.05); border:1px solid rgba(99,102,241,0.2); padding:12px 15px; border-radius:8px; margin-bottom:15px; display:none;">
                <p style="margin:0; font-size:12px; color:var(--text-light);"><i class="fa-solid fa-briefcase"></i> Terkait Penugasan Proyek:</p>
                <p id="detProjectName" style="margin:5px 0 0; font-weight:700; color:var(--accent-color); font-size:14px; font-family:'Montserrat',sans-serif;"></p>
            </div>

            <h5 style="margin:0 0 10px; font-size:14px; color:var(--text-dark); font-family:'Montserrat',sans-serif;">Aktivitas / Hasil Pekerjaan:</h5>
            <div id="detDesc" style="background:var(--bg-gray); padding:15px; border-radius:8px; font-size:14px; line-height:1.6; color:var(--text-dark); max-height:250px; overflow-y:auto; margin-bottom:20px; border:1px solid var(--border);"></div>
            
            <div id="detPhotoContainer" style="display:none; margin-bottom:20px;">
                <h5 style="margin:0 0 10px; font-size:14px; color:var(--text-dark); font-family:'Montserrat',sans-serif;"><i class="fa-solid fa-camera"></i> Bukti Foto / Visual Lapangan:</h5>
                <img id="detPhoto" src="" style="width:100%; border-radius:8px; cursor:pointer; object-fit:cover; max-height:300px;" onclick="window.open(this.src)" alt="Bukti Foto">
            </div>

            <button class="btn btn-outline" style="width:100%; padding:12px;" onclick="document.getElementById('modalReportDetail').classList.remove('active')">Tutup Jendela Detail</button>
        </div>
    </div>
</div>

<script>
function reverseGeocode(lat, lng, elId) {
    if(!lat || !lng) return;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`)
    .then(r=>r.json()).then(d=>{
        document.getElementById(elId).innerHTML = '<i class="fa-solid fa-location-dot" style="color:var(--secondary-accent);"></i> '+(d.display_name ? d.display_name.split(',').slice(0,3).join(', ') : lat+', '+lng);
    }).catch(()=>{ document.getElementById(elId).textContent = lat+', '+lng; });
}

function openAbsenDetail(data) {
    document.getElementById('mdlAbsenDate').textContent = data.date;
    
    // Set IN data
    document.getElementById('mdlTimeIn').textContent = data.time_in ? data.time_in : '-';
    document.getElementById('mdlCoordsIn').textContent = data.latitude_in ? data.latitude_in + ', ' + data.longitude_in : '-';
    reverseGeocode(data.latitude_in, data.longitude_in, 'mdlCoordsIn');
    
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
    reverseGeocode(data.latitude_out, data.longitude_out, 'mdlCoordsOut');
    
    let photoOut = document.getElementById('mdlPhotoOut');
    if (data.photo_out) {
        photoOut.src = '../uploads/absen/' + data.photo_out;
        photoOut.style.display = 'block';
    } else {
        photoOut.style.display = 'none';
    }

    document.getElementById('modalAbsenDetail').classList.add('active');
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/\n/g, "<br>");
}

function openReportDetail(data) {
    // Format Date string
    const d = new Date(data.report_date);
    const dateStr = d.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
    
    document.getElementById('detDate').innerHTML = '<i class="fa-regular fa-calendar" style="margin-right:5px;"></i> ' + dateStr + ' <br><i class="fa-regular fa-clock" style="margin-right:5px;"></i> ' + data.created_at.split(' ')[1] + ' WIB';
    
    let statusBadge = '';
    if (data.status === 'approved') statusBadge = '<span class="badge badge-success" style="font-size:13px;"><i class="fa-solid fa-check-double"></i> Disetujui</span>';
    else if (data.status === 'rejected') statusBadge = '<span class="badge badge-danger" style="font-size:13px;"><i class="fa-solid fa-xmark"></i> Ditolak</span>';
    else statusBadge = '<span class="badge badge-warning" style="font-size:13px;"><i class="fa-solid fa-clock"></i> Pending</span>';
    document.getElementById('detStatusBadge').innerHTML = statusBadge;

    if (data.project_title) {
        document.getElementById('detProjectInfo').style.display = 'block';
        document.getElementById('detProjectName').innerHTML = escapeHtml(data.project_title);
    } else {
        document.getElementById('detProjectInfo').style.display = 'none';
    }

    document.getElementById('detDesc').innerHTML = escapeHtml(data.description);

    if (data.photo_evidence) {
        document.getElementById('detPhotoContainer').style.display = 'block';
        document.getElementById('detPhoto').src = '../uploads/reports/' + data.photo_evidence;
    } else {
        document.getElementById('detPhotoContainer').style.display = 'none';
    }

    document.getElementById('modalReportDetail').classList.add('active');
}
</script>
</body>
</html>
