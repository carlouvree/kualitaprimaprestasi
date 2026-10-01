<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('staff');
$current_page = 'riwayat';
$user_id = $_SESSION['user_id'];

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM project_reports WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$report = $stmt->fetch();
if(!$report){ header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan — KPP Staff</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
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
            <div><h1>Detail Laporan</h1><p>Laporan tanggal <?= date('d F Y', strtotime($report['report_date'])) ?></p></div>
        </div>
        <div class="topbar-right">
            <a href="riwayat.php?tab=laporan" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
    <div class="portal-content">
        <div class="report-detail-card">
            <?php if(!empty($report['photo_evidence'])): ?>
                <img src="../uploads/reports/<?= htmlspecialchars($report['photo_evidence']) ?>" class="report-img" alt="Bukti Lapangan">
            <?php endif; ?>
            <div class="report-body">
                <div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
                    <span class="badge badge-available"><i class="fa-regular fa-calendar"></i> <?= date('d F Y', strtotime($report['report_date'])) ?></span>
                    <span class="badge badge-staff"><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($report['created_at'])) ?> WIB</span>
                </div>
                <h3 style="font-family:'Montserrat',sans-serif;font-size:20px;color:var(--text-dark);margin-bottom:15px;">Deskripsi Aktivitas</h3>
                <div style="padding:20px;background:var(--bg-gray);border-radius:var(--radius);line-height:1.8;color:var(--text-dark);font-size:15px;">
                    <?= nl2br(htmlspecialchars($report['description'])) ?>
                </div>
                <div style="margin-top:20px;padding:15px;background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.15);border-radius:8px;font-size:13px;color:var(--warning);display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-lock"></i> Laporan ini bersifat <strong>read-only</strong> dan tidak dapat diubah.
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
