<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');

$type = $_GET['type'] ?? 'attendance';
$f_month = (int)($_GET['month'] ?? date('n'));
$f_year = (int)($_GET['year'] ?? date('Y'));
$f_staff = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int)$_GET['staff_id'] : null;
$f_project = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$staff_name_suffix = "";
if ($f_staff) {
    $stmtStaffName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmtStaffName->execute([$f_staff]);
    $selected_staff_name = $stmtStaffName->fetchColumn();
    if ($selected_staff_name) {
        $staff_name_suffix = " (" . strtoupper($selected_staff_name) . ")";
    }
}

$project_name_suffix = "";
if ($f_project) {
    $stmtProjectName = $pdo->prepare("SELECT title, project_code FROM tasks WHERE id = ?");
    $stmtProjectName->execute([$f_project]);
    $sel_proj = $stmtProjectName->fetch();
    if ($sel_proj) {
        $project_name_suffix = " (" . strtoupper(($sel_proj['project_code'] ? '['.$sel_proj['project_code'].'] ' : '') . $sel_proj['title']) . ")";
    }
}

$title = "LAPORAN REKAPITULASI " . ($type === 'attendance' ? 'ABSENSI STAFF' : 'PENUGASAN PROYEK') . ($type === 'attendance' ? $staff_name_suffix : $project_name_suffix);
$subtitle = $months[$f_month] . " " . $f_year;
$company_name = get_setting('logo_text', 'PT. Kualita Prima Prestasi');
$company_logo = get_setting('logo_image');

// Fetch Data
if ($type === 'attendance') {
    $query = "
        SELECT u.full_name, 
               COUNT(a.id) as total_present,
               SUM(CASE WHEN a.time_in > '08:15:00' THEN 1 ELSE 0 END) as total_late
        FROM users u
        LEFT JOIN attendances a ON u.id = a.user_id AND MONTH(a.date) = ? AND YEAR(a.date) = ?
        WHERE u.role = 'staff'
    ";
    $params = [$f_month, $f_year];
    if ($f_staff) {
        $query .= " AND u.id = ?";
        $params[] = $f_staff;
    }
    $query .= " GROUP BY u.id ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
} else {
    $query = "
        SELECT t.*, u.full_name as staff_name 
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE ((MONTH(t.start_date) = ? AND YEAR(t.start_date) = ?)
           OR (MONTH(t.created_at) = ? AND YEAR(t.created_at) = ?))
    ";
    $params = [$f_month, $f_year, $f_month, $f_year];
    if ($f_project) {
        $query .= " AND t.id = ?";
        $params[] = $f_project;
    }
    $query .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?> - <?= $subtitle ?></title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Open Sans', Arial, sans-serif; color: #1e293b; margin: 0; padding: 40px; line-height: 1.5; background: #fff; }
        .no-print { display: flex; justify-content: flex-end; margin-bottom: 30px; gap: 10px; }
        .btn { padding: 12px 24px; text-decoration: none; border-radius: 8px; cursor: pointer; border: none; font-size: 14px; font-weight: 700; font-family: 'Montserrat', sans-serif; display: flex; align-items: center; gap: 8px; }
        .btn-print { background: #4f46e5; color: white; box-shadow: 0 4px 12px rgba(79,70,229,0.3); }
        .btn-print:hover { background: #4338ca; }
        
        .header { display: flex; align-items: center; border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 30px; }
        .header-logo { flex-shrink: 0; margin-right: 25px; display: flex; align-items: center; }
        .header-text { text-align: left; flex-grow: 1; }
        .header-text h1 { margin: 0; font-size: 26px; color: #1e1b4b; text-transform: uppercase; letter-spacing: 1px; }
        .header-text p { margin: 5px 0 0; font-size: 14px; color: #64748b; }

        .report-meta { text-align: center; margin-bottom: 40px; }
        .report-meta h2 { margin: 0; font-size: 20px; color: #000; text-decoration: underline; }
        .report-meta p { margin: 8px 0 0; font-weight: bold; color: #334155; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { border: 1px solid #cbd5e1; padding: 14px; font-size: 14px; text-align: left; }
        th { background: #f1f5f9; color: #1e293b; font-weight: 800; text-transform: uppercase; font-size: 12px; }
        
        .footer { margin-top: 80px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 250px; }
        .sig-box p { margin: 0; font-size: 14px; color: #475569; }
        .sig-box .space { height: 100px; }
        .sig-box .name { font-weight: 800; text-decoration: underline; color: #0f172a; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body onload="setTimeout(() => {}, 500)">
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()"><i class="fa fa-print"></i> Cetak / Simpan PDF</button>
        <button class="btn" style="background:#f1f5f9; color:#475569;" onclick="window.close()">Tutup Halaman</button>
    </div>

    <div class="header">
        <?php if($company_logo): ?>
            <div class="header-logo">
                <img src="<?= base_url('uploads/' . htmlspecialchars($company_logo)) ?>" alt="Logo" style="height: 80px; object-fit: contain;">
            </div>
        <?php endif; ?>
        <div class="header-text">
            <?php 
                $c_name = strtoupper(htmlspecialchars($company_name));
                $c_name = str_replace('PT KUALITA PRIMA', '<span style="color: #2563eb;">PT KUALITA PRIMA</span>', $c_name); // Brighter Blue
                $c_name = str_replace('KUALITA PRIMA', '<span style="color: #2563eb;">KUALITA PRIMA</span>', $c_name); // Fallback
                $c_name = str_replace('PRESTASI', '<span style="color: #15803d;">PRESTASI</span>', $c_name); // Green
            ?>
            <h1><?= $c_name ?></h1>
            <p>Laporan Resmi Manajemen Proyek & Sumber Daya Manusia</p>
        </div>
    </div>

    <div class="report-meta">
        <h2><?= $title ?></h2>
        <p>Periode: <?= $subtitle ?></p>
    </div>

    <?php if ($type === 'attendance'): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>Nama Staff Pelaksana</th>
                <th style="text-align: center;">Total Kehadiran</th>
                <th style="text-align: center;">Total Terlambat</th>
                <th>Keterangan Kinerja</th>
            </tr>
        </thead>
        <tbody>
            <?php $no=1; foreach($data as $row): ?>
            <tr>
                <td style="text-align: center;"><?= $no++ ?></td>
                <td><strong style="color: #1e1b4b;"><?= htmlspecialchars($row['full_name']) ?></strong></td>
                <td style="text-align: center;"><?= $row['total_present'] ?> Hari</td>
                <td style="text-align: center;"><?= $row['total_late'] ?>x</td>
                <td><?= $row['total_present'] >= 20 ? 'Sangat Memuaskan' : ($row['total_present'] > 0 ? 'Cukup Baik' : '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>Detail Kode & Judul Proyek</th>
                <th>Staff Penanggung Jawab</th>
                <th>Status</th>
                <th style="text-align: right;">Alokasi Budget (IDR)</th>
            </tr>
        </thead>
        <tbody>
            <?php $no=1; $total=0; foreach($data as $row): $total += $row['total_budget']; ?>
            <tr>
                <td style="text-align: center;"><?= $no++ ?></td>
                <td><strong>[<?= htmlspecialchars($row['project_code'] ?? '-') ?>]</strong> <?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['staff_name'] ?? '-') ?></td>
                <td><span style="text-transform: uppercase; font-weight: bold;"><?= $row['status'] ?></span></td>
                <td style="text-align: right;">Rp <?= number_format($row['total_budget'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: #f8fafc; font-weight: 800; font-size: 16px;">
                <td colspan="4" style="text-align: right;">TOTAL ESTIMASI ANGGARAN:</td>
                <td style="text-align: right; color: #15803d;">Rp <?= number_format($total, 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="footer">
        <div class="sig-box">
            <p>Mengetahui,</p>
            <div class="space"></div>
            <p class="name">( ____________________ )</p>
            <p>Operations Manager</p>
        </div>
        <div class="sig-box">
            <p>Dokumen Sah, <?= date('d F Y') ?></p>
            <div class="space"></div>
            <p class="name">( <?= htmlspecialchars($_SESSION['full_name']) ?> )</p>
            <p>Administrator Portal</p>
        </div>
    </div>
</body>
</html>
