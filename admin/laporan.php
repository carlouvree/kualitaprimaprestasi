<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'laporan';

if(isset($_GET['approve'])){
    $pdo->prepare("UPDATE project_reports SET status='approved' WHERE id=?")->execute([(int)$_GET['approve']]);
    header("Location: laporan.php?msg=approved"); exit;
}
if(isset($_GET['reject'])){
    $pdo->prepare("UPDATE project_reports SET status='rejected' WHERE id=?")->execute([(int)$_GET['reject']]);
    header("Location: laporan.php?msg=rejected"); exit;
}

$reports = $pdo->query("SELECT pr.*, u.full_name, u.email, t.title as project_title, t.project_code 
    FROM project_reports pr 
    JOIN users u ON pr.user_id = u.id 
    LEFT JOIN tasks t ON pr.task_id = t.id
    ORDER BY FIELD(pr.status, 'pending', 'rejected', 'approved'), pr.report_date DESC, pr.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Laporan Harian — Admin KPP</title>
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
            <div><h1>Persetujuan Laporan</h1><p>Setujui laporan agar Staff bisa melakukan Check-Out</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Status laporan berhasil diperbarui!</div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-file-signature"></i> Daftar Laporan Harian Staff</h3><button type="button" onclick="exportPDF()" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export PDF</button></div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Tanggal / Waktu</th><th>Staff</th><th>Isi Laporan</th><th>Bukti Foto</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if(empty($reports)): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada laporan masuk.</p></div></td></tr>
                    <?php else: foreach($reports as $r): ?>
                    <tr style="<?= $r['status']==='approved'?'opacity:0.7;':'' ?>">
                        <td>
                            <strong><?= date('d M Y',strtotime($r['report_date'])) ?></strong><br>
                            <small style="color:var(--text-light);"><i class="fa-regular fa-clock"></i> <?= date('H:i',strtotime($r['created_at'])) ?> WIB</small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                            <?php if($r['project_title']): ?>
                                <div style="font-size:11px; color:var(--accent-color); margin-top:3px;">
                                    <i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($r['project_title']) ?>
                                    <?php if($r['project_code']): ?><br><small style="font-weight:700;">[<?= htmlspecialchars($r['project_code']) ?>]</small><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:13px;color:var(--text-dark);max-width:300px;">
                                <?= nl2br(htmlspecialchars(mb_substr($r['description'],0,150))) ?><?= mb_strlen($r['description'])>150?'...':'' ?>
                            </div>
                        </td>
                        <td>
                            <?php if($r['photo_evidence']): ?>
                                <img src="../uploads/reports/<?= htmlspecialchars($r['photo_evidence']) ?>" class="img-thumb" style="cursor:pointer;" onclick="window.open(this.src)">
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td>
                            <?php if($r['status']==='approved'): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Disetujui</span>
                            <?php elseif($r['status']==='rejected'): ?>
                                <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Ditolak</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><i class="fa-solid fa-clock-rotate-left"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <button class="btn btn-sm btn-outline" style="padding:6px 12px; font-weight:700; font-size:11px;" onclick='openDetail(<?= htmlspecialchars(json_encode($r)) ?>)' title="Lihat Detail"><i class="fa-solid fa-eye"></i> Detail</button>
                            <?php if($r['status']==='pending'): ?>
                                <button type="button" class="btn btn-sm btn-success" style="width:34px; height:34px;" onclick="showConfirm('Setujui laporan ini? Staff akan diizinkan checkout.', () => location='laporan.php?approve=<?= $r['id'] ?>')" title="Setujui"><i class="fa-solid fa-check"></i></button>
                                <button type="button" class="btn btn-sm btn-danger" style="width:34px; height:34px;" onclick="showConfirm('Tolak laporan ini? Staff harus merevisi laporannya.', () => location='laporan.php?reject=<?= $r['id'] ?>')" title="Tolak"><i class="fa-solid fa-xmark"></i></button>
                            <?php elseif($r['status']==='rejected'): ?>
                                <span style="font-size:11px;color:var(--text-light);margin-left:5px;font-style:italic;">Menunggu revisi...</span>
                            <?php else: ?>
                                <span style="font-size:11px;color:var(--success);margin-left:5px;font-weight:700;"><i class="fa-solid fa-check-circle"></i> Selesai</span>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Detail Laporan -->
<div class="modal-overlay" id="modalDetailLaporan">
    <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-signature" style="color:var(--accent-color);"></i> Detail Laporan Harian</h3>
            <button class="modal-close" onclick="document.getElementById('modalDetailLaporan').classList.remove('active')" type="button">&times;</button>
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

            <div id="detActionBtns" style="display:flex; gap:10px; margin-top:20px; border-top:1px solid var(--border); padding-top:20px;">
                <!-- Buttons populated dynamically -->
            </div>
        </div>
    </div>
</div>

</div>

<!-- Modal Konfirmasi Custom -->
<div class="modal-overlay" id="modalConfirm" style="z-index: 2000;">
    <div class="modal-box" style="max-width:380px; text-align:center;">
        <div class="modal-body" style="padding:30px 20px;">
            <div style="width:60px; height:60px; background:rgba(245,158,11,0.1); color:var(--warning); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:24px;">
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <h3 style="font-family:'Montserrat',sans-serif; margin-bottom:10px;">Konfirmasi</h3>
            <p id="confirmMessage" style="font-size:14px; color:var(--text-light); margin-bottom:25px;"></p>
            <div class="grid-2-responsive" style="gap:10px;">
                <button type="button" class="btn" style="background:#f1f5f9; color:#64748b;" onclick="closeModal('modalConfirm')">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script>
function exportPDF() {
    const btn = document.querySelector('button[onclick="exportPDF()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    btn.disabled = true;

    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.left = '-9999px';
    iframe.style.width = '1024px';
    iframe.style.height = '768px';
    iframe.src = 'laporan_pdf.php?type=laporan_harian&auto_export=1';
    iframe.id = 'hiddenPdfIframe';
    
    document.body.appendChild(iframe);

    const listener = function(event) {
        if(event.data === 'pdf_exported') {
            btn.innerHTML = originalText;
            btn.disabled = false;
            document.body.removeChild(iframe);
            window.removeEventListener('message', listener);
        }
    };
    window.addEventListener('message', listener);
    
    setTimeout(() => {
        if(document.getElementById('hiddenPdfIframe')) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            document.body.removeChild(iframe);
            window.removeEventListener('message', listener);
        }
    }, 15000); // Increased timeout to 15s to allow slower devices to generate PDF
}

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

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/\n/g, "<br>");
}

function openDetail(data) {
    document.getElementById('detStaffName').innerHTML = '<i class="fa-solid fa-user-tie" style="color:var(--accent-color); margin-right:5px;"></i> ' + escapeHtml(data.full_name);
    
    // Format Date string
    const d = new Date(data.report_date);
    const dateStr = d.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
    
    document.getElementById('detDate').innerHTML = '<i class="fa-regular fa-calendar" style="margin-right:5px;"></i> ' + dateStr + ' <br><i class="fa-regular fa-clock" style="margin-right:5px;"></i> ' + data.created_at.split(' ')[1] + ' WIB';
    
    let statusBadge = '';
    if (data.status === 'approved') statusBadge = '<span class="badge badge-success" style="font-size:13px;"><i class="fa-solid fa-check-double"></i> Disetujui</span>';
    else if (data.status === 'rejected') statusBadge = '<span class="badge badge-danger" style="font-size:13px;"><i class="fa-solid fa-xmark"></i> Ditolak</span>';
    else statusBadge = '<span class="badge badge-warning" style="font-size:13px;"><i class="fa-solid fa-clock"></i> Pending (Menunggu)</span>';
    document.getElementById('detStatusBadge').innerHTML = statusBadge;

    if (data.project_title) {
        document.getElementById('detProjectInfo').style.display = 'block';
        document.getElementById('detProjectName').innerHTML = escapeHtml(data.project_title) + (data.project_code ? ' <strong style="color:var(--text-light);">['+escapeHtml(data.project_code)+']</strong>' : '');
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

    let actions = '';
    if (data.status === 'pending') {
        actions = `<button class="btn btn-success" style="flex:1; padding:12px; font-weight:800;" onclick="showConfirm('Setujui laporan ini? Staff akan bisa checkout.', () => location='laporan.php?approve=${data.id}')"><i class="fa-solid fa-check"></i> Setujui & Izinkan Checkout</button>
                   <button class="btn btn-danger" style="padding:12px; font-weight:800;" onclick="showConfirm('Tolak laporan ini? Staff harus revisi dan batal checkout.', () => location='laporan.php?reject=${data.id}')"><i class="fa-solid fa-xmark"></i> Tolak Laporan</button>`;
    } else {
        actions = `<button class="btn btn-outline" style="width:100%; padding:12px; font-weight:700;" onclick="closeModal('modalDetailLaporan')">Tutup Jendela Detail</button>`;
    }
    document.getElementById('detActionBtns').innerHTML = actions;

    document.getElementById('modalDetailLaporan').classList.add('active');
}
</script>
</body>
</html>

