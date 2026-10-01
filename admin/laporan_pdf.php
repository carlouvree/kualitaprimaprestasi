<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');

$type = $_GET['type'] ?? 'proyek';
$filter_date = $_GET['date'] ?? null;
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$target_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

$targetUser = null;
if ($target_user_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $targetUser = $stmt->fetch();
}

$targetTask = null;
if ($target_task_id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$target_task_id]);
    $targetTask = $stmt->fetch();
}

// Export PDF for various report types
// This page renders a printable HTML that users can print/save as PDF
$now = date('d F Y, H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Laporan — KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; padding: 20px; }
        .toolbar { background: #1e293b; color: white; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .toolbar h2 { flex:1; font-size:16px; }
        .btn-export { background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 700; }
        .btn-back { background: rgba(255,255,255,0.1); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; }
        
        #pdfContent { background: white; padding: 40px; max-width: 900px; margin: 0 auto; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); }
        .pdf-header { text-align: center; border-bottom: 3px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px; }
        .pdf-header h1 { font-size: 22px; color: #1e293b; margin-bottom: 5px; }
        .pdf-header p { color: #64748b; font-size: 13px; }
        .pdf-header .logo-text { font-size: 28px; font-weight: 900; color: #4b38aa; letter-spacing: -1px; }
        
        .section-title { font-size: 14px; font-weight: 700; color: #334155; background: #f1f5f9; padding: 8px 12px; border-radius: 6px; margin: 20px 0 10px; border-left: 4px solid #4b38aa; }
        
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
        th { background: #1e293b; color: white; padding: 10px 8px; text-align: left; font-size: 11px; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-in_progress { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .summary-box { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
        .summary-item { flex: 1; min-width: 150px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
        .summary-item .val { font-size: 24px; font-weight: 900; color: #1e293b; }
        .summary-item .lbl { font-size: 11px; color: #64748b; margin-top: 3px; }
        
        .footer-sign { margin-top: 50px; display: flex; justify-content: flex-end; }
        .sign-box { text-align: center; }
        .sign-line { border-top: 1px solid #334155; margin-top: 60px; padding-top: 5px; font-size: 12px; }
    </style>
</head>
<body>

<div class="toolbar">
    <h2><i>📄</i> Laporan KPP — Export PDF</h2>
    <select id="typeSelect" onchange="location='laporan_pdf.php?type='+this.value" style="padding:6px 10px;border-radius:6px;border:none;font-size:13px;">
        <option value="proyek" <?= $type==='proyek'?'selected':'' ?>>Laporan Proyek</option>
        <option value="laporan_harian" <?= $type==='laporan_harian'?'selected':'' ?>>Laporan Harian Staff</option>
        <option value="absensi" <?= $type==='absensi'?'selected':'' ?>>Laporan Absensi</option>
        <option value="keuangan" <?= $type==='keuangan'?'selected':'' ?>>Laporan Keuangan</option>
    </select>
    <button class="btn-export" onclick="exportToPDF()">⬇ Download PDF</button>
    <a href="javascript:history.back()" class="btn-back">← Kembali</a>
</div>

<div id="pdfContent">
    <div class="pdf-header">
        <div class="logo-text">PT KUALITA PRIMA</div>
        <h1>
            <?php 
            if($type==='proyek') echo 'Laporan Daftar Proyek';
            elseif($type==='laporan_harian') echo 'Laporan Harian Staff' . ($targetUser ? ': '.$targetUser['full_name'] : '');
            elseif($type==='absensi') echo 'Laporan Rekap Absensi' . ($targetUser ? ': '.$targetUser['full_name'] : '') . ($filter_date ? ' ('.date('d M Y', strtotime($filter_date)).')' : '');
            else echo 'Laporan Keuangan' . ($targetTask ? ': '.$targetTask['title'] : ' Proyek');
            ?>
        </h1>
        <p>Dicetak pada: <?= $now ?> | Oleh: <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></p>
    </div>

    <?php if($type === 'proyek'): ?>
    <?php
    $tasks = $pdo->query("SELECT t.*, u.full_name as staff_name, a.full_name as admin_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users a ON t.assigned_by = a.id ORDER BY t.created_at DESC")->fetchAll();
    $total = count($tasks);
    $pending = count(array_filter($tasks, fn($t) => $t['status']==='pending'));
    $inprog = count(array_filter($tasks, fn($t) => $t['status']==='in_progress'));
    $done = count(array_filter($tasks, fn($t) => $t['status']==='completed'));
    $totalBudget = array_sum(array_column($tasks, 'total_budget'));
    ?>
    <div class="summary-box">
        <div class="summary-item"><div class="val"><?= $total ?></div><div class="lbl">Total Proyek</div></div>
        <div class="summary-item"><div class="val" style="color:#f59e0b;"><?= $pending ?></div><div class="lbl">Pending</div></div>
        <div class="summary-item"><div class="val" style="color:#3b82f6;"><?= $inprog ?></div><div class="lbl">In Progress</div></div>
        <div class="summary-item"><div class="val" style="color:#10b981;"><?= $done ?></div><div class="lbl">Selesai</div></div>
        <div class="summary-item"><div class="val" style="color:#4b38aa;font-size:16px;">Rp <?= number_format($totalBudget,0,',','.') ?></div><div class="lbl">Total Budget</div></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Kode</th><th>Proyek</th><th>Staff</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Mulai</th><th>Deadline</th><th>Budget</th></tr></thead>
        <tbody>
        <?php foreach($tasks as $i => $t): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($t['project_code'] ?: '-') ?></strong></td>
            <td><strong><?= htmlspecialchars($t['title']) ?></strong><?php if($t['client_name']): ?><br><small style="color:#64748b;"><?= htmlspecialchars($t['client_name']) ?></small><?php endif; ?></td>
            <td><?= htmlspecialchars($t['staff_name'] ?: '-') ?></td>
            <td><?= ucfirst($t['project_category'] ?: '-') ?></td>
            <td><?= strtoupper($t['priority']) ?></td>
            <td><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
            <td><?= $t['start_date'] ? date('d/m/Y', strtotime($t['start_date'])) : '-' ?></td>
            <td><?= $t['due_date'] ? date('d/m/Y', strtotime($t['due_date'])) : '-' ?></td>
            <td><?= $t['total_budget'] > 0 ? 'Rp '.number_format($t['total_budget'],0,',','.') : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif($type === 'laporan_harian'): ?>
    <?php
    $sql = "SELECT pr.*, u.full_name, t.title as project_title FROM project_reports pr JOIN users u ON pr.user_id = u.id LEFT JOIN tasks t ON pr.task_id = t.id";
    $params = [];
    if($target_user_id) { $sql .= " WHERE pr.user_id = ?"; $params[] = $target_user_id; }
    $sql .= " ORDER BY pr.report_date DESC, pr.created_at DESC";
    $reports = $pdo->prepare($sql);
    $reports->execute($params);
    $reports = $reports->fetchAll();
    
    $pending = count(array_filter($reports, fn($r) => $r['status']==='pending'));
    $approved = count(array_filter($reports, fn($r) => $r['status']==='approved'));
    $rejected = count(array_filter($reports, fn($r) => $r['status']==='rejected'));
    ?>
    <div class="summary-box">
        <div class="summary-item"><div class="val"><?= count($reports) ?></div><div class="lbl">Total Laporan</div></div>
        <?php if(!$target_user_id): ?>
        <div class="summary-item"><div class="val" style="color:#f59e0b;"><?= $pending ?></div><div class="lbl">Pending</div></div>
        <div class="summary-item"><div class="val" style="color:#10b981;"><?= $approved ?></div><div class="lbl">Disetujui</div></div>
        <div class="summary-item"><div class="val" style="color:#ef4444;"><?= $rejected ?></div><div class="lbl">Ditolak</div></div>
        <?php endif; ?>
    </div>
    <table>
        <thead><tr><th>#</th><th>Tanggal</th><th>Staff</th><th>Proyek</th><th>Ringkasan Laporan</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($reports as $i => $r): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= date('d M Y', strtotime($r['report_date'])) ?><br><small><?= date('H:i', strtotime($r['created_at'])) ?> WIB</small></td>
            <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['project_title'] ?: '-') ?></td>
            <td><?= nl2br(htmlspecialchars(mb_substr($r['description'], 0, 200))) ?>...</td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif($type === 'absensi'): ?>
    <?php
    $sql = "SELECT a.*, u.full_name FROM attendances a JOIN users u ON a.user_id = u.id";
    $where = []; $params = [];
    if($filter_date) { $where[] = "a.date = ?"; $params[] = $filter_date; }
    if($target_user_id) { $where[] = "a.user_id = ?"; $params[] = $target_user_id; }
    
    if(!empty($where)) { $sql .= " WHERE " . implode(" AND ", $where); }
    $sql .= " ORDER BY a.date DESC, a.time_in DESC";
    
    $absensi = $pdo->prepare($sql);
    $absensi->execute($params);
    $absensi = $absensi->fetchAll();
    ?>
    <table>
        <thead><tr><th>#</th><th>Tanggal</th><th>Staff</th><th>Check-In</th><th>Check-Out</th><th>Durasi</th><th>Lokasi Masuk</th></tr></thead>
        <tbody>
        <?php foreach($absensi as $i => $a): 
            $dur = '';
            if(!empty($a['time_out'])) {
                $diff = (strtotime($a['time_out']) - strtotime($a['time_in'])) / 3600;
                $dur = round($diff, 1) . ' jam';
            }
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= date('d M Y', strtotime($a['date'])) ?></td>
            <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
            <td><?= date('H:i', strtotime($a['time_in'])) ?> WIB</td>
            <td><?= $a['time_out'] ? date('H:i', strtotime($a['time_out'])).' WIB' : '<span style="color:#ef4444;">Belum</span>' ?></td>
            <td><?= $dur ?: '-' ?></td>
            <td><small><?= $a['latitude_in'] ? $a['latitude_in'].', '.$a['longitude_in'] : '-' ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif($type === 'keuangan'): ?>
    <?php
    $sql = "SELECT t.*, u.full_name as staff_name, 
            (SELECT SUM(total_price) FROM project_materials WHERE task_id = t.id) as material_cost 
            FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id";
    $params = [];
    if($target_task_id) {
        $sql .= " WHERE t.id = ?";
        $params[] = $target_task_id;
    } else {
        $sql .= " WHERE t.total_budget > 0";
    }
    $sql .= " ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    $totalBudget = array_sum(array_column($tasks, 'total_budget'));
    $totalActualAkomodasi = array_sum(array_map(fn($t) => ($t['actual_transport_cost']??0)+($t['actual_hotel_cost']??0)+($t['actual_meal_cost']??0), $tasks));
    $totalMat = array_sum(array_column($tasks, 'material_cost'));
    ?>
    <div class="summary-box">
        <div class="summary-item"><div class="val" style="font-size:14px;">Rp <?= number_format($totalBudget,0,',','.') ?></div><div class="lbl">Total Budget Alokasi</div></div>
        <div class="summary-item"><div class="val" style="font-size:14px; color:#f59e0b;">Rp <?= number_format($totalActualAkomodasi,0,',','.') ?></div><div class="lbl">Realisasi Akomodasi</div></div>
        <div class="summary-item"><div class="val" style="font-size:14px; color:#3b82f6;">Rp <?= number_format($totalMat,0,',','.') ?></div><div class="lbl">Total Pengadaan Barang</div></div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Kode</th>
                <th>Nama Proyek</th>
                <th>Staff</th>
                <th>Total Budget</th>
                <th style="background:#fffbeb; color:#92400e; border-bottom:2px solid #fcd34d;">Aktual Akomodasi</th>
                <th style="background:#eff6ff; color:#1e40af; border-bottom:2px solid #93c5fd;">Pengadaan Barang</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($tasks as $i => $t): 
            $akomodasi = ($t['actual_transport_cost']??0) + ($t['actual_hotel_cost']??0) + ($t['actual_meal_cost']??0);
            $barang = $t['material_cost'] ?? 0;
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($t['project_code'] ?: '-') ?></td>
            <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
            <td><?= htmlspecialchars($t['staff_name'] ?: '-') ?></td>
            <td>Rp <?= number_format($t['total_budget'],0,',','.') ?></td>
            <td style="background:#fffbeb; font-weight:700; color:#92400e;">Rp <?= number_format($akomodasi,0,',','.') ?></td>
            <td style="background:#eff6ff; font-weight:700; color:#1e40af;">Rp <?= number_format($barang,0,',','.') ?></td>
            <td><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="footer-sign">
        <div class="sign-box">
            <p style="font-size:12px;color:#64748b;">Dicetak oleh Admin,</p>
            <div class="sign-line"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
        </div>
    </div>
</div>

<script>
function exportToPDF() {
    const el = document.getElementById('pdfContent');
    const type = document.getElementById('typeSelect').value;
    const titles = {
        proyek: 'Laporan_Proyek',
        laporan_harian: 'Laporan_Harian_Staff',
        absensi: 'Rekap_Absensi',
        keuangan: 'Laporan_Keuangan'
    };
    return html2pdf().set({
        margin: 0.5,
        filename: titles[type] + '_KPP_<?= date('Ymd') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    }).from(el).save();
}

window.onload = function() {
    if(new URLSearchParams(window.location.search).get('auto_export') == '1') {
        exportToPDF().then(() => {
            if(window.parent) window.parent.postMessage('pdf_exported', '*');
        });
    }
};
</script>
</body>
</html>
