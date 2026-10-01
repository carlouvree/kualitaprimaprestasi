<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('staff');
$current_page = 'dashboard';
$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Absensi hari ini
$stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? AND date = ? LIMIT 1");
$stmt->execute([$user_id, $today]);
$absen = $stmt->fetch();
$sudah_masuk = (bool)$absen;
$sudah_pulang = $absen && $absen['time_out'] !== null;

// Proyek aktif untuk staff ini
// Kriteria: belum selesai + (hari ini di antara start/end date OR DL hari ini/NULL)
$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM tasks 
    WHERE assigned_to = ? 
    AND status != 'completed'
    AND (
        (? BETWEEN start_date AND end_date) 
        OR (due_date = ? OR due_date IS NULL)
    )");
$stmtPending->execute([$user_id, $today, $today]);
$pending_projects = (int)$stmtPending->fetchColumn();

// Semua proyek aktif hari ini untuk dashboard
$stmtAllTasks = $pdo->prepare("SELECT t.*, u.full_name as admin_name FROM tasks t 
    LEFT JOIN users u ON t.assigned_by = u.id 
    WHERE t.assigned_to = ? 
    AND status != 'completed'
    AND (
        (? BETWEEN start_date AND end_date) 
        OR (due_date = ? OR due_date IS NULL)
    )
    ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC 
    LIMIT 5");
$stmtAllTasks->execute([$user_id, $today, $today]);
$today_tasks = $stmtAllTasks->fetchAll();

$all_done = $pending_projects === 0;
$has_tasks = count($today_tasks) > 0;

// Cek apakah sudah submit laporan hari ini
$existRpt = $pdo->prepare("SELECT id FROM project_reports WHERE user_id=? AND report_date=? LIMIT 1");
$existRpt->execute([$user_id,$today]); $already_reported = (bool)$existRpt->fetch();

// Bisa laporan & checkout hanya jika: sudah masuk + semua proyek selesai (atau tidak ada proyek)
$can_report = $sudah_masuk && !$sudah_pulang && $all_done && !$already_reported;
$can_checkout = $sudah_masuk && !$sudah_pulang && $all_done;

// Stats
$month_start = date('Y-m-01');
$monthly_hadir = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ? AND date >= ?");
$monthly_hadir->execute([$user_id, $month_start]); $monthly_hadir = $monthly_hadir->fetchColumn();
$monthly_reports = $pdo->prepare("SELECT COUNT(*) FROM project_reports WHERE user_id = ? AND report_date >= ?");
$monthly_reports->execute([$user_id, $month_start]); $monthly_reports = $monthly_reports->fetchColumn();

// Riwayat
$stmtH = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC LIMIT 5");
$stmtH->execute([$user_id]); $history = $stmtH->fetchAll();
$stmtR = $pdo->prepare("SELECT * FROM project_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmtR->execute([$user_id]); $recent_reports = $stmtR->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal — KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css?v=<?= time() ?>">
</head>
<body class="staff-body">
<?php require_once '../includes/staff_nav.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Halo, <?= htmlspecialchars($_SESSION['full_name']) ?>! 👋</h1><p><i class="fa-regular fa-calendar-days"></i> <?= indo_date() ?></p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> 
            <?php if($_GET['msg']==='report_success'): ?>Laporan berhasil dikirim!<?php elseif($_GET['msg']==='absen_success'): ?>Absensi berhasil!<?php else: ?>Operasi berhasil!<?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-info"><h4>Hadir Bulan Ini</h4><div class="stat-value"><?= $monthly_hadir ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-file-lines"></i></div>
                <div class="stat-info"><h4>Laporan Bulan Ini</h4><div class="stat-value"><?= $monthly_reports ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?= $sudah_masuk && !$sudah_pulang ? 'green' : 'orange' ?>"><i class="fa-solid fa-<?= $sudah_masuk && !$sudah_pulang ? 'check-circle' : ($sudah_pulang ? 'check-double' : 'clock') ?>"></i></div>
                <div class="stat-info"><h4>Absensi</h4><div class="stat-value" style="font-size:15px;"><?= !$sudah_masuk ? 'Belum Masuk' : ($sudah_pulang ? 'Selesai' : 'Sudah Check-in') ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?= $all_done ? 'green' : 'orange' ?>"><i class="fa-solid fa-<?= $all_done ? 'circle-check' : 'hourglass-half' ?>"></i></div>
                <div class="stat-info"><h4>Proyek Pending</h4><div class="stat-value"><?= $pending_projects ?></div></div>
            </div>
        </div>

        <!-- Workflow Info Banner -->
        <?php if($sudah_masuk && !$sudah_pulang && !$all_done): ?>
        <div class="alert alert-info" style="margin-bottom:25px;">
            <i class="fa-solid fa-info-circle"></i>
            <div><strong>Ada <?= $pending_projects ?> proyek belum selesai.</strong> Selesaikan semua proyek dengan foto bukti sebelum bisa input laporan dan check-out.</div>
        </div>
        <?php elseif($sudah_masuk && !$sudah_pulang && $all_done && $has_tasks): ?>
        <div class="alert alert-success" style="margin-bottom:25px;">
            <i class="fa-solid fa-check-circle"></i>
            <div><strong>Semua proyek selesai!</strong> Silakan input laporan harian dan check-out.</div>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;">
            <!-- 1. Absensi Masuk -->
            <div class="action-card" style="border-top-color:var(--success);">
                <div class="card-icon" style="background:rgba(16,185,129,0.1);color:var(--success);"><i class="fa-solid fa-camera"></i></div>
                <h4>① Absensi Masuk</h4>
                <?php if(!$sudah_masuk): ?>
                    <p>Langkah pertama: absen check-in.</p>
                    <a href="absen.php?tipe=masuk" class="btn btn-success btn-block"><i class="fa-solid fa-right-to-bracket"></i> Check-In</a>
                <?php else: ?>
                    <div class="status-chip success"><i class="fa-solid fa-check-circle"></i> Masuk <?= date('H:i',strtotime($absen['time_in'])) ?></div>
                    <button class="btn btn-block" style="background:var(--bg-gray);color:var(--text-light);cursor:default;" disabled>✓ Selesai</button>
                <?php endif; ?>
            </div>

            <!-- 2. Kerjakan Proyek -->
            <div class="action-card" style="border-top-color:var(--accent-color);">
                <div class="card-icon" style="background:rgba(99,102,241,0.1);color:var(--accent-color);"><i class="fa-solid fa-list-check"></i></div>
                <h4>② Kerjakan Proyek</h4>
                <?php if(!$sudah_masuk): ?>
                    <p style="color:var(--text-light);font-size:12px;font-style:italic;">(Check-in terlebih dahulu)</p>
                    <button class="btn btn-block" style="background:var(--bg-gray);color:var(--text-light);cursor:not-allowed;" disabled><i class="fa-solid fa-lock"></i> Terkunci</button>
                <?php elseif(!$all_done): ?>
                    <p><strong style="color:var(--danger);"><?= $pending_projects ?></strong> proyek belum selesai.</p>
                    <a href="proyek.php" class="btn btn-primary btn-block"><i class="fa-solid fa-hammer"></i> Kerjakan Proyek</a>
                <?php else: ?>
                    <div class="status-chip success"><i class="fa-solid fa-check-double"></i> Semua Selesai</div>
                    <button class="btn btn-block" style="background:var(--bg-gray);color:var(--text-light);cursor:default;" disabled>✓ Selesai</button>
                <?php endif; ?>
            </div>

            <!-- 3. Laporan & Checkout -->
            <div class="action-card" style="border-top-color:var(--secondary-accent);">
                <div class="card-icon" style="background:rgba(56,189,248,0.1);color:var(--secondary-accent);"><i class="fa-solid fa-file-signature"></i></div>
                <h4>③ Laporan & Pulang</h4>
                <?php if($can_report): ?>
                    <p>Input laporan lalu check-out.</p>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <a href="report.php" class="btn btn-accent btn-block"><i class="fa-solid fa-plus"></i> Buat Laporan</a>
                        <a href="absen.php?tipe=pulang" class="btn btn-danger btn-block" style="font-size:13px;"><i class="fa-solid fa-right-from-bracket"></i> Check-Out</a>
                    </div>
                <?php elseif($sudah_masuk && !$sudah_pulang && $all_done && $already_reported): ?>
                    <div class="status-chip success"><i class="fa-solid fa-circle-check"></i> Laporan Terkirim</div>
                    <a href="absen.php?tipe=pulang" class="btn btn-danger btn-block" style="font-size:13px;"><i class="fa-solid fa-right-from-bracket"></i> Check-Out</a>
                <?php elseif($sudah_pulang): ?>
                    <div class="status-chip done"><i class="fa-solid fa-check-double"></i> Hari Selesai</div>
                    <button class="btn btn-block" style="background:var(--bg-gray);color:var(--text-light);cursor:default;" disabled>✓ Selesai</button>
                <?php else: ?>
                    <p style="color:var(--text-light);font-size:12px;font-style:italic;">(Selesaikan semua proyek dulu)</p>
                    <button class="btn btn-block" style="background:var(--bg-gray);color:var(--text-light);cursor:not-allowed;" disabled><i class="fa-solid fa-lock"></i> Terkunci</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Proyek Hari Ini -->
        <?php if(count($today_tasks) > 0): ?>
        <div class="card" style="margin-top:25px;">
            <div class="card-header"><h3><i class="fa-solid fa-list-check"></i> Proyek Hari Ini</h3><a href="proyek.php" class="btn btn-outline btn-sm">Lihat Semua</a></div>
            <div class="card-body" style="padding:15px 20px;">
                <?php foreach($today_tasks as $task): ?>
                <div class="task-card priority-<?= $task['priority'] ?>">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                        <div>
                            <?php if(!empty($task['project_code'])): ?>
                                <small style="display:inline-block;background:rgba(99,102,241,0.1);color:var(--accent-color);padding:2px 8px;border-radius:4px;font-weight:700;font-size:10px;margin-bottom:5px;">#<?= htmlspecialchars($task['project_code']) ?></small>
                            <?php endif; ?>
                            <h4 style="margin:0;font-size:15px;"><?= htmlspecialchars($task['title']) ?></h4>
                            <?php if(!empty($task['client_name'])): ?>
                                <div style="font-size:11px;color:var(--text-light);margin-top:3px;"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($task['client_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge-<?= $task['priority'] ?>" style="font-size:10px;"><?= strtoupper($task['priority']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid-2-responsive" style="margin-top:25px;">
            <!-- Riwayat Absensi -->
            <div class="card">
                <div class="card-header"><h3 style="font-size:15px;"><i class="fa-solid fa-clock-rotate-left"></i> Absensi Terakhir</h3><a href="riwayat.php" class="btn btn-outline btn-sm">Semua</a></div>
                <div class="card-body" style="padding:0;">
                    <?php if(empty($history)): ?>
                        <div class="empty-state" style="padding:25px;"><p>Belum ada riwayat.</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                    <table class="data-table" style="font-size:13px;">
                        <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th></tr></thead>
                        <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td style="font-weight:600;"><?= date('d M',strtotime($h['date'])) ?></td>
                            <td><span class="badge badge-available"><?= date('H:i',strtotime($h['time_in'])) ?></span></td>
                            <td><?= $h['time_out']?'<span class="badge badge-sold">'.date('H:i',strtotime($h['time_out'])).'</span>':'-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Laporan Terakhir -->
            <div class="card">
                <div class="card-header"><h3 style="font-size:15px;"><i class="fa-solid fa-file-lines"></i> Laporan Terakhir</h3><a href="riwayat.php?tab=laporan" class="btn btn-outline btn-sm">Semua</a></div>
                <div class="card-body" style="padding:10px 20px;">
                    <?php if(empty($recent_reports)): ?>
                        <div class="empty-state" style="padding:25px;"><p>Belum ada laporan.</p></div>
                    <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach($recent_reports as $r): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background:rgba(56,189,248,0.1);color:var(--secondary-accent);"><i class="fa-solid fa-file-lines"></i></div>
                            <div style="flex:1;"><a href="laporan_detail.php?id=<?= $r['id'] ?>" class="activity-text" style="color:var(--text-dark);font-weight:600;"><?= mb_substr(htmlspecialchars($r['description']),0,50) ?>...</a><div class="activity-time"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i',strtotime($r['created_at'])) ?></div></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
