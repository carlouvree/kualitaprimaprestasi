<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');

$type = $_GET['type'] ?? 'attendance';
$f_month = (int)($_GET['month'] ?? date('n'));
$f_year = (int)($_GET['year'] ?? date('Y'));
$f_staff = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int)$_GET['staff_id'] : null;
$f_project = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
$user_id = $_SESSION['user_id'];

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$staff_name_suffix = "";
$filename_suffix_staff = "";
if ($f_staff) {
    $stmtStaffName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmtStaffName->execute([$f_staff]);
    $selected_staff_name = $stmtStaffName->fetchColumn();
    if ($selected_staff_name) {
        $staff_name_suffix = " - " . $selected_staff_name;
        $filename_suffix_staff = "_" . preg_replace('/[^A-Za-z0-9]/', '', $selected_staff_name);
    }
}

$project_name_suffix = "";
$filename_suffix_project = "";
if ($f_project) {
    $stmtProjectName = $pdo->prepare("SELECT title, project_code FROM tasks WHERE id = ?");
    $stmtProjectName->execute([$f_project]);
    $sel_proj = $stmtProjectName->fetch();
    if ($sel_proj) {
        $project_name_suffix = " - " . ($sel_proj['project_code'] ? '['.$sel_proj['project_code'].'] ' : '') . $sel_proj['title'];
        $filename_suffix_project = "_" . preg_replace('/[^A-Za-z0-9]/', '', ($sel_proj['project_code'] ?? '') . $sel_proj['title']);
    }
}

$title = "Rekap " . ($type === 'attendance' ? 'Absensi Staff' : 'Penugasan Proyek') . " - " . $months[$f_month] . " " . $f_year . ($type === 'attendance' ? $staff_name_suffix : $project_name_suffix);
$filename = "REKAP_GEN_" . strtoupper($type) . "_" . $f_year . "_" . str_pad($f_month, 2, '0', STR_PAD_LEFT) . ($type === 'attendance' ? $filename_suffix_staff : $filename_suffix_project) . "_" . time() . ".doc";

// Company Name
$company_name = get_setting('logo_text', 'PT. Kualita Prima Prestasi');
$company_logo = get_setting('logo_image');

$html = '
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>' . $title . '</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <style>
        body { font-family: "Arial", sans-serif; line-height: 1.6; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header-table { width: 100%; border: none; }
        .header-table td { border: none; padding: 0; }
        .header-table .img-cell { width: 80px; text-align: left; vertical-align: middle; }
        .header-table .text-cell { text-align: left; vertical-align: middle; padding-left: 15px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 12px; }
        .report-title { text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; }
        .footer { margin-top: 50px; }
        .signature-table { width: 100%; border: none; }
        .signature-table td { border: none; width: 50%; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>';
    if($company_logo){
        $html .= '<td class="img-cell"><img src="' . base_url('uploads/' . htmlspecialchars($company_logo)) . '" alt="Logo" style="height: 70px;"></td>';
    }

    $c_name = strtoupper(htmlspecialchars($company_name));
    $c_name = str_replace('PT KUALITA PRIMA', '<span style="color: #2563eb;">PT KUALITA PRIMA</span>', $c_name);
    $c_name = str_replace('KUALITA PRIMA', '<span style="color: #2563eb;">KUALITA PRIMA</span>', $c_name);
    $c_name = str_replace('PRESTASI', '<span style="color: #15803d;">PRESTASI</span>', $c_name);

$html .= '
                <td class="text-cell">
                    <h1>' . $c_name . '</h1>
                    <p>Laporan Rekapitulasi Menajemen Proyek & SDM</p>
                </td>
            </tr>
        </table>
    </div>
    <div class="report-title">' . strtoupper($title) . '</div>

    <p>Periode: ' . $months[$f_month] . ' ' . $f_year . '</p>
';

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

    $html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>Nama Lengkap</th>
                <th>Hari Hadir</th>
                <th>Terlambat</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($data as $row) {
        $html .= '
            <tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($row['full_name']) . '</td>
                <td>' . $row['total_present'] . ' Hari</td>
                <td>' . $row['total_late'] . 'x</td>
                <td>' . ($row['total_present'] >= 20 ? 'Sangat Baik' : 'Cukup') . '</td>
            </tr>';
    }
    $html .= '</tbody></table>';

} else {
    // Project Stats
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

    $html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th>Kode & Judul Proyek</th>
                <th>Staff Pelaksana</th>
                <th>Status</th>
                <th>Budget (IDR)</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    $total_budget = 0;
    foreach ($data as $row) {
        $total_budget += $row['total_budget'];
        $html .= '
            <tr>
                <td>' . $no++ . '</td>
                <td>[' . htmlspecialchars($row['project_code'] ?? '-') . '] ' . htmlspecialchars($row['title']) . '</td>
                <td>' . htmlspecialchars($row['staff_name'] ?? '-') . '</td>
                <td>' . strtoupper($row['status']) . '</td>
                <td style="text-align: right;">' . number_format($row['total_budget'], 0, ',', '.') . '</td>
            </tr>';
    }
    $html .= '
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td colspan="4" style="text-align: right;">TOTAL ALOKASI ANGGARAN:</td>
                <td style="text-align: right;">' . number_format($total_budget, 0, ',', '.') . '</td>
            </tr>
        </tbody></table>';
}

$html .= '
    <div class="footer">
        <table class="signature-table">
            <tr>
                <td>
                    <p>Diketahui oleh,</p>
                    <br><br><br>
                    <p><b>( _________________ )</b><br>Kepala Operasional</p>
                </td>
                <td>
                    <p>Dicetak pada: ' . date('d F Y') . '</p>
                    <br><br><br>
                    <p><b>( ' . htmlspecialchars($_SESSION['full_name']) . ' )</b><br>Administrator</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>';

// Save File
$target_dir = "../uploads/documents/";
if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

if (file_put_contents($target_dir . $filename, $html)) {
    // Record into DB
    $ins = $pdo->prepare("INSERT INTO monthly_recaps (month, year, recap_type, file_name, title, notes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$f_month, $f_year, $type, $filename, "Automated: " . $title, "Dihasilkan secara otomatis oleh sistem.", $user_id]);
    
    header("Location: dms.php?month=$f_month&year=$f_year&msg=generated");
} else {
    header("Location: dms.php?month=$f_month&year=$f_year&error=gen_failed");
}
exit;
