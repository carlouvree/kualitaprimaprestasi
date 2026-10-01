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
        $staff_name_suffix = " - " . $selected_staff_name;
    }
}

$project_name_suffix = "";
if ($f_project) {
    $stmtProjectName = $pdo->prepare("SELECT title, project_code FROM tasks WHERE id = ?");
    $stmtProjectName->execute([$f_project]);
    $sel_proj = $stmtProjectName->fetch();
    if ($sel_proj) {
        $project_name_suffix = " - " . ($sel_proj['project_code'] ? '['.$sel_proj['project_code'].'] ' : '') . $sel_proj['title'];
    }
}

$title = "Rekap " . ($type === 'attendance' ? 'Absensi Staff' : 'Penugasan Proyek') . " - " . $months[$f_month] . " " . $f_year . ($type === 'attendance' ? $staff_name_suffix : $project_name_suffix);
$company_name = get_setting('logo_text', 'PT. Kualita Prima Prestasi');
$company_logo = get_setting('logo_image');

?>
<div style="font-family: Arial, sans-serif;">
    <div style="display: flex; align-items: center; border-bottom: 2px solid #1e1b4b; padding-bottom: 15px; margin-bottom: 20px;">
        <?php if($company_logo): ?>
            <div style="margin-right: 20px; flex-shrink: 0;">
                <img src="<?= base_url('uploads/' . htmlspecialchars($company_logo)) ?>" alt="Logo" style="height: 70px; object-fit: contain;">
            </div>
        <?php endif; ?>
        <div style="text-align: left; flex-grow: 1;">
            <?php 
                $c_name = strtoupper(htmlspecialchars($company_name));
                $c_name = str_replace('PT KUALITA PRIMA', '<span style="color: #2563eb;">PT KUALITA PRIMA</span>', $c_name); // Brighter Blue
                $c_name = str_replace('KUALITA PRIMA', '<span style="color: #2563eb;">KUALITA PRIMA</span>', $c_name); // Fallback
                $c_name = str_replace('PRESTASI', '<span style="color: #15803d;">PRESTASI</span>', $c_name); // Green
            ?>
            <h2 style="margin: 0; color: #1e1b4b; text-transform: uppercase; font-size: 22px;"><?= $c_name ?></h2>
            <p style="margin: 5px 0 0; font-size: 13px; color: #64748b;">Laporan Rekapitulasi Menajemen Proyek & SDM</p>
        </div>
    </div>

    <h3 style="text-align: center; font-size: 18px; margin-bottom: 20px; text-decoration: underline;"><?= strtoupper($title) ?></h3>

    <?php if ($type === 'attendance'): ?>
        <?php
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
        ?>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f8fafc;">
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: left;">Nama Staff</th>
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: center;">Hadir</th>
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: center;">Terlambat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td style="border: 1px solid #e2e8f0; padding: 12px;"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td style="border: 1px solid #e2e8f0; padding: 12px; text-align: center;"><?= $row['total_present'] ?> Hari</td>
                    <td style="border: 1px solid #e2e8f0; padding: 12px; text-align: center;"><?= $row['total_late'] ?>x</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <?php
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
        $total_budget = 0;
        ?>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f8fafc;">
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: left;">Nama Proyek</th>
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: left;">Pelaksana</th>
                    <th style="border: 1px solid #e2e8f0; padding: 12px; text-align: right;">Budget (IDR)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): $total_budget += $row['total_budget']; ?>
                <tr>
                    <td style="border: 1px solid #e2e8f0; padding: 12px;"><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td style="border: 1px solid #e2e8f0; padding: 12px;"><?= htmlspecialchars($row['staff_name'] ?? '-') ?></td>
                    <td style="border: 1px solid #e2e8f0; padding: 12px; text-align: right;"><?= number_format($row['total_budget'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f1f5f9; font-weight: bold;">
                    <td colspan="2" style="border: 1px solid #e2e8f0; padding: 12px; text-align: right;">TOTAL ALOKASI:</td>
                    <td style="border: 1px solid #e2e8f0; padding: 12px; text-align: right;">Rp <?= number_format($total_budget, 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 50px; display: flex; justify-content: space-between; font-size: 14px;">
        <div style="text-align: center; width: 200px;">
            <p>Disetujui Oleh,</p>
            <div style="height: 80px;"></div>
            <p><b>( ________________ )</b></p>
        </div>
        <div style="text-align: center; width: 200px;">
            <p>Admin Sistem,</p>
            <div style="height: 80px;"></div>
            <p><b>( <?= htmlspecialchars($_SESSION['full_name']) ?> )</b></p>
        </div>
    </div>
</div>
