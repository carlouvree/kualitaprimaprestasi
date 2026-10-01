<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('staff');
$current_page = 'report';
$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Cek absen
$absen = $pdo->prepare("SELECT * FROM attendances WHERE user_id=? AND date=? LIMIT 1");
$absen->execute([$user_id,$today]); $absen=$absen->fetch();
$sudah_masuk = (bool)$absen;
$sudah_pulang = $absen && $absen['time_out'] !== null;

// Cek proyek pending
$pending = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND (due_date=? OR due_date IS NULL) AND status!='completed'");
$pending->execute([$user_id,$today]); $pending_count=(int)$pending->fetchColumn();

// Cek apakah sudah submit laporan hari ini
$existingReport = $pdo->prepare("SELECT * FROM project_reports WHERE user_id=? AND report_date=? LIMIT 1");
$existingReport->execute([$user_id,$today]); $todayReport=$existingReport->fetch();
$already_submitted = (bool)$todayReport;
$status_laporan = $todayReport['status'] ?? null;

// Fetch projects assigned to this staff that are active or were worked on today
$stmtProyek = $pdo->prepare("SELECT id, title, project_code FROM tasks WHERE assigned_to = ? AND status != 'completed' ORDER BY created_at DESC");
$stmtProyek->execute([$user_id]);
$my_projects = $stmtProyek->fetchAll();

$can_report = $sudah_masuk && !$sudah_pulang && (!$already_submitted || $status_laporan === 'rejected');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_report) {
    $desc = trim($_POST['description']);
    $photo = $todayReport['photo_evidence'] ?? '';
    if(isset($_FILES['photo']) && $_FILES['photo']['error']===0){
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo = 'report_' . $user_id . '_' . time() . '.' . $ext;
        if(!is_dir('../uploads/reports/')) mkdir('../uploads/reports/', 0777, true);
        compress_image_upload($_FILES['photo']['tmp_name'], '../uploads/reports/' . $photo, 70, 1000);
    }
    
    $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
    
    if ($already_submitted && $status_laporan === 'rejected') {
        $stmt = $pdo->prepare("UPDATE project_reports SET task_id=?, description=?, photo_evidence=?, status='pending' WHERE id=?");
        $stmt->execute([$task_id, $desc, $photo, $todayReport['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO project_reports (user_id, task_id, report_date, description, photo_evidence, status) VALUES (?,?,?,?,?,'pending')");
        $stmt->execute([$user_id, $task_id, $today, $desc, $photo]);
    }
    header("Location: index.php?msg=report_success"); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Proyek — KPP Staff</title>
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
            <div><h1>Laporan Harian</h1><p><?= date('d F Y') ?></p></div>
        </div>
        <div class="topbar-right"><div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?></div></div>
    </div>
    <div class="portal-content">
        <?php if($already_submitted && $status_laporan !== 'rejected'): ?>
        <!-- Sudah submit hari ini dan tidak ditolak -->
        <div class="card" style="max-width:600px;">
            <div class="card-body" style="text-align:center;padding:50px 30px;">
                <?php if($status_laporan === 'approved'): ?>
                    <div style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><i class="fa-solid fa-check-circle" style="font-size:36px;color:var(--success);"></i></div>
                    <h3 style="font-family:'Montserrat',sans-serif;color:var(--success);margin-bottom:10px;">Laporan Disetujui ✓</h3>
                    <p style="color:var(--text-light);margin-bottom:15px;">Laporan harian Anda telah disetujui oleh admin. Anda sudah bisa melakukan Check-Out.</p>
                <?php else: ?>
                    <div style="width:80px;height:80px;border-radius:50%;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><i class="fa-solid fa-clock-rotate-left" style="font-size:36px;color:var(--warning);"></i></div>
                    <h3 style="font-family:'Montserrat',sans-serif;color:var(--text-dark);margin-bottom:10px;">Laporan Pending</h3>
                    <p style="color:var(--text-light);margin-bottom:15px;">Laporan Anda sedang menunggu persetujuan Admin agar Anda dapat melakukan Check-Out pulang.</p>
                <?php endif; ?>

                <div style="padding:15px;background:var(--bg-gray);border-radius:var(--radius);text-align:left;margin-bottom:20px;">
                    <small style="color:var(--text-light);display:block;margin-bottom:5px;"><i class="fa-regular fa-clock"></i> Dikirim <?= date('H:i',strtotime($todayReport['created_at'])) ?> WIB</small>
                    <p style="font-size:14px;color:var(--text-dark);line-height:1.6;"><?= htmlspecialchars(mb_substr($todayReport['description'],0,150)) ?><?= mb_strlen($todayReport['description'])>150?'...':'' ?></p>
                </div>
                <div style="display:flex;gap:10px;">
                    <?php if($status_laporan === 'approved'): ?>
                        <a href="absen.php?tipe=pulang" class="btn btn-success" style="flex:1;"><i class="fa-solid fa-right-from-bracket"></i> Check-Out Sekarang</a>
                    <?php endif; ?>
                    <a href="laporan_detail.php?id=<?= $todayReport['id'] ?>" class="btn btn-outline" style="flex:1;"><i class="fa-solid fa-eye"></i> Detail Lengkap</a>
                </div>
            </div>
        </div>

        <?php elseif(!$can_report): ?>
        <!-- Terkunci -->
        <div class="card" style="max-width:600px;">
            <div class="card-body" style="text-align:center;padding:50px 30px;">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><i class="fa-solid fa-lock" style="font-size:32px;color:var(--danger);"></i></div>
                <h3 style="font-family:'Montserrat',sans-serif;color:var(--text-dark);margin-bottom:10px;">Laporan Terkunci</h3>
                <?php if(!$sudah_masuk): ?>
                    <p style="color:var(--text-light);margin-bottom:20px;">Anda harus check-in terlebih dahulu.</p>
                    <a href="absen.php?tipe=masuk" class="btn btn-success"><i class="fa-solid fa-right-to-bracket"></i> Check-In Dulu</a>
                <?php elseif($sudah_pulang): ?>
                    <p style="color:var(--text-light);margin-bottom:20px;">Anda sudah check-out hari ini.</p>
                    <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-house"></i> Kembali</a>
                <?php else: ?>
                    <p style="color:var(--text-light);margin-bottom:10px;">Masih ada <strong style="color:var(--danger);"><?= $pending_count ?> proyek</strong> yang belum diselesaikan.</p>
                    <p style="color:var(--text-light);font-size:13px;margin-bottom:20px;">Selesaikan semua proyek secara berurutan dan terlaporkan (LPJ) terlebih dahulu.</p>
                    <a href="proyek.php" class="btn btn-primary"><i class="fa-solid fa-hammer"></i> Kerjakan Proyek</a>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Form laporan -->
        <div class="card" style="max-width:700px;">
            <div class="card-header"><h3><i class="fa-solid fa-file-signature"></i> Form Laporan Proyek Akhir Hari</h3></div>
            <div class="card-body">
                <?php if($status_laporan === 'rejected'): ?>
                    <div class="alert alert-danger" style="margin-bottom:20px;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div><strong>Perhatian:</strong> Laporan Anda sebelumnya DITOLAK oleh admin. Silakan perbaiki penjelasan kegiatan Anda lalu kirim ulang.</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-bottom:20px;">
                        <i class="fa-solid fa-info-circle"></i>
                        <div>Laporan hanya dikirim setelah seluruh proyek hari ini selesai. Status laporan akan menjadi <strong>Pending</strong> sampai disetujui admin sebelum diizinkan Check-Out.</div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php if(!empty($my_projects)): ?>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-briefcase" style="color:var(--accent-color);"></i> Pilih Proyek Yang Dikerjakan <span style="color:var(--danger);">*</span></label>
                        <select name="task_id" class="form-control" required>
                            <option value="">-- Pilih Proyek --</option>
                            <?php foreach($my_projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($todayReport['task_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                    [<?= htmlspecialchars($p['project_code'] ?: 'PRJ') ?>] <?= htmlspecialchars($p['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Laporan ini akan ditautkan ke histori proyek tersebut.</p>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Deskripsi / Rangkuman Kegiatan Hari Ini <span style="color:var(--danger);">*</span></label>
                        <textarea name="description" class="form-control" rows="6" required placeholder="Jelaskan detail aktivitas proyek hari ini..."><?= $todayReport['description'] ?? '' ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bukti Foto Keseluruhan (Opsional)</label>
                        <input type="file" name="photo" accept="image/*" class="form-control" capture="environment">
                        <p class="form-hint">Kosongkan jika tidak ingin mengubah foto sebelumnya. Format: JPG, PNG. Optimal gunakan kamera langsung.</p>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <a href="index.php" class="btn btn-outline" style="flex:1;"><i class="fa-solid fa-arrow-left"></i> Batal</a>
                        <button type="button" class="btn btn-primary" style="flex:2; font-weight:800;" onclick="showConfirm('Kirim Laporan Harian untuk disetujui Admin?', () => this.closest('form').submit())"><i class="fa-solid fa-paper-plane"></i> Kirim Laporan</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
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
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Ya, Kirim</button>
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
