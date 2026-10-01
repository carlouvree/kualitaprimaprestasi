<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'dms';

// Filter Bulan, Tahun, Staff & Proyek
$f_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$f_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$f_staff = isset($_GET['staff_id']) && $_GET['staff_id'] !== '' ? (int) $_GET['staff_id'] : null;
$f_project = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int) $_GET['project_id'] : null;

$months = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

// Ambil list staff untuk dropdown filter
$staff_list = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC")->fetchAll();
// Ambil list proyek untuk dropdown filter
$project_list = $pdo->query("SELECT id, title, project_code FROM tasks ORDER BY title ASC")->fetchAll();

// --- LOGIKA REKAP ABSENSI ---
$queryAbsen = "
    SELECT u.id, u.full_name, 
           COUNT(a.id) as total_present,
           SUM(CASE WHEN a.time_in > '08:15:00' THEN 1 ELSE 0 END) as total_late
    FROM users u
    LEFT JOIN attendances a ON u.id = a.user_id AND MONTH(a.date) = ? AND YEAR(a.date) = ?
    WHERE u.role = 'staff'
";
$paramsAbsen = [$f_month, $f_year];
if ($f_staff) {
    $queryAbsen .= " AND u.id = ?";
    $paramsAbsen[] = $f_staff;
}
$queryAbsen .= " GROUP BY u.id ORDER BY u.full_name ASC";

$stmtAbsen = $pdo->prepare($queryAbsen);
$stmtAbsen->execute($paramsAbsen);
$attendance_recap = $stmtAbsen->fetchAll();

// --- LOGIKA REKAP PROYEK ---
$queryProyek = "
    SELECT t.*, u.full_name as staff_name 
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE ((MONTH(t.start_date) = ? AND YEAR(t.start_date) = ?)
       OR (MONTH(t.created_at) = ? AND YEAR(t.created_at) = ?))
";
$paramsProyek = [$f_month, $f_year, $f_month, $f_year];
if ($f_project) {
    $queryProyek .= " AND t.id = ?";
    $paramsProyek[] = $f_project;
}
$queryProyek .= " ORDER BY t.created_at DESC";

$stmtProyekList = $pdo->prepare($queryProyek);
$stmtProyekList->execute($paramsProyek);
$projects = $stmtProyekList->fetchAll();

$project_stats = [
    'total_tasks' => count($projects),
    'completed_tasks' => count(array_filter($projects, fn($p) => $p['status'] === 'completed')),
    'active_tasks' => count(array_filter($projects, fn($p) => $p['status'] === 'in_progress')),
    'total_allocated_budget' => array_sum(array_column($projects, 'total_budget'))
];

// --- DOKUMEN REKAP (DMS) ---
$stmtDocs = $pdo->prepare("
    SELECT r.*, u.full_name as uploader 
    FROM monthly_recaps r
    JOIN users u ON r.uploaded_by = u.id
    WHERE r.month = ? AND r.year = ?
    ORDER BY r.created_at DESC
");
$stmtDocs->execute([$f_month, $f_year]);
$recaps = $stmtDocs->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMS & Rekap Bulanan — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <style>
        :root {
            --glass: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        .filter-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
            border: 1px solid var(--border);
        }

        .recap-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .recap-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .card-header {
            border-bottom: 1px solid var(--border);
            background: #fafafa;
            padding: 20px 25px;
        }

        /* Premium Buttons */
        .btn-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-family: 'Montserrat';
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            border: none;
        }

        .btn-premium-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-premium-indigo:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-premium-cyan {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .btn-premium-cyan:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
        }

        .stat-box {
            background: white;
            border: 1px solid #f1f5f9;
            border-radius: 15px;
            padding: 18px;
            text-align: center;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .stat-box .val {
            font-size: 24px;
            font-weight: 800;
            color: #1e1b4b;
            font-family: 'Montserrat';
        }

        .doc-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .doc-item:hover {
            border-color: var(--accent-color);
            background: #fdfdff;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.05);
        }

        /* Modal Fullscreen Preview */
        #modalPreview .modal-box {
            max-width: 900px;
            width: 95%;
            height: 90vh;
            display: flex;
            flex-direction: column;
        }

        #modalPreview .modal-body {
            flex: 1;
            overflow-y: auto;
            background: #f8fafc;
            padding: 0;
            position: relative;
        }

        .preview-inner {
            background: white;
            padding: 40px;
            margin: 30px auto;
            width: 90%;
            min-height: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }

        .preview-toolbar {
            padding: 15px 25px;
            background: white;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
    </style>
</head>

<body>
    <?php require_once '../includes/admin_sidebar.php'; ?>
    <main class="portal-main">
        <div class="portal-topbar">
            <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
                <button class="sidebar-toggle"
                    onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i
                        class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>DMS & Rekap Bulanan</h1>
                    <p>Periode Kehadiran & Project Assignment: <?= $months[$f_month] ?> <?= $f_year ?></p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-user"><?php if (!empty($_SESSION['profile_photo'])): ?><img
                            src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm"
                            style="object-fit:cover; border:none; padding:0;"><?php else: ?>
                        <div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div>
                    <?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?>
                </div>
            </div>
        </div>

        <div class="portal-content">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Berhasil:
                    <?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <!-- Filter Periode -->
            <form class="filter-card" method="GET">
                <div class="form-group" style="margin:0; width:180px;">
                    <label class="form-label" style="font-size:11px; font-weight:700;">BULAN REKAP</label>
                    <select name="month" class="form-control">
                        <?php foreach ($months as $m => $name): ?>
                            <option value="<?= $m ?>" <?= $f_month === $m ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0; width:120px;">
                    <label class="form-label" style="font-size:11px; font-weight:700;">TAHUN</label>
                    <select name="year" class="form-control">
                        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $f_year === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0; width:220px;">
                    <label class="form-label" style="font-size:11px; font-weight:700;">FILTER STAFF</label>
                    <select name="staff_id" class="form-control">
                        <option value="">-- Semua Staff --</option>
                        <?php foreach ($staff_list as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $f_staff === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0; width:220px;">
                    <label class="form-label" style="font-size:11px; font-weight:700;">FILTER PROYEK</label>
                    <select name="project_id" class="form-control">
                        <option value="">-- Semua Proyek --</option>
                        <?php foreach ($project_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $f_project === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($p['project_code'] ? '[' . $p['project_code'] . '] ' : '') . $p['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-premium btn-premium-indigo" style="height:42px;"><i
                        class="fa-solid fa-filter"></i> Filter Periode</button>
            </form>

            <div class="recap-grid">
                <!-- REKAP ABSENSI -->
                <div class="card">
                    <div class="card-header" style="justify-content:space-between; display:flex; align-items:center;">
                        <h3><i class="fa-solid fa-clipboard-user" style="color:var(--accent-color);"></i> Kehadiran
                            Staff</h3>
                        <button class="btn-premium btn-premium-indigo" onclick="openPreview('attendance')"><i
                                class="fa-solid fa-file-invoice"></i> Pratinjau & Generate</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" style="font-size:13px;">
                                <thead>
                                    <tr>
                                        <th>Nama Staff</th>
                                        <th>Hadir</th>
                                        <th>Terlambat</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_recap as $a):
                                        $percent = $a['total_present'] > 0 ? round(($a['total_present'] / 22) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><strong
                                                    style="color:#1e1b4b;"><?= htmlspecialchars($a['full_name']) ?></strong>
                                            </td>
                                            <td><span class="badge"
                                                    style="background:#dcfce7; color:#15803d; font-weight:700;"><?= $a['total_present'] ?>
                                                    Hari</span></td>
                                            <td><span class="badge"
                                                    style="background:<?= $a['total_late'] > 3 ? '#fee2e2' : '#fef3c7' ?>; color:<?= $a['total_late'] > 3 ? '#991b1b' : '#92400e' ?>;"><?= $a['total_late'] ?>x</span>
                                            </td>
                                            <td><small><?= $percent ?>%</small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- REKAP PROYEK -->
                <div class="card">
                    <div class="card-header" style="justify-content:space-between; display:flex; align-items:center;">
                        <h3><i class="fa-solid fa-list-check" style="color:#0ea5e9;"></i> Project Assignment</h3>
                        <button class="btn-premium btn-premium-cyan" onclick="openPreview('project')"><i
                                class="fa-solid fa-file-invoice"></i> Pratinjau & Generate</button>
                    </div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px;">
                            <div class="stat-box">
                                <div class="val"><?= $project_stats['total_tasks'] ?></div>
                                <div class="lbl">TOTAL</div>
                            </div>
                            <div class="stat-box">
                                <div class="val"><?= $project_stats['active_tasks'] ?></div>
                                <div class="lbl">AKTIF</div>
                            </div>
                            <div class="stat-box">
                                <div class="val" style="color:#10b981;"><?= $project_stats['completed_tasks'] ?></div>
                                <div class="lbl">DONE</div>
                            </div>
                        </div>
                        <div
                            style="padding:15px; background:linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius:15px; border:1px solid #bbf7d0; text-align:center;">
                            <small
                                style="color:#166534; text-transform:uppercase; font-size:10px; font-weight:800; letter-spacing:1px;">Estimasi
                                Anggaran Terlibat</small>
                            <div style="font-size:20px; font-weight:800; color:#15803d; font-family:'Montserrat';">Rp
                                <?= number_format($project_stats['total_allocated_budget'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DMS: DOKUMEN ARSIP -->
            <div class="card" style="margin-top:30px; border-radius:24px;">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3><i class="fa-solid fa-box-archive" style="color:var(--accent-color);"></i> Arsip Dokumen Digital
                        (DMS)</h3>
                    <button class="btn-premium" style="background:#f8fafc; border:1px solid #e2e8f0; color:#64748b;"
                        onclick="openUploadModal('other')"><i class="fa-solid fa-upload"></i> Unggah Manual</button>
                </div>
                <div class="card-body">
                    <?php if (empty($recaps)): ?>
                        <div class="empty-state" style="padding:40px;"><i class="fa-solid fa-file-circle-xmark"></i>
                            <p>Belum ada rekaman dokumen untuk bulan ini.</p>
                        </div>
                    <?php else: ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                            <?php foreach ($recaps as $r): ?>
                                <div class="doc-item">
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <div
                                            style="width:45px; height:45px; border-radius:12px; background:<?= $r['recap_type'] === 'attendance' ? '#e0e7ff' : '#e0f2fe' ?>; color:<?= $r['recap_type'] === 'attendance' ? '#4f46e5' : '#0ea5e9' ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">
                                            <i
                                                class="fa-solid <?= $r['recap_type'] === 'attendance' ? 'fa-user-check' : 'fa-briefcase' ?>"></i>
                                        </div>
                                        <div>
                                            <strong
                                                style="display:block; font-size:15px; color:#1e1b4b;"><?= htmlspecialchars($r['title']) ?></strong>
                                            <small style="color:#64748b; font-weight:600;"><?= strtoupper($r['recap_type']) ?> •
                                                <?= date('d M Y, H:i', strtotime($r['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <button onclick="viewFileRecord(<?= $r['id'] ?>)" class="btn-premium"
                                            style="background:#f1f5f9; color:#475569; padding:8px 12px;" title="Lihat"><i
                                                class="fa-solid fa-eye"></i></button>
                                        <a href="../uploads/documents/<?= $r['file_name'] ?>" download class="btn-premium"
                                            style="background:#f1f5f9; color:#475569; padding:8px 12px;" title="Download"><i
                                                class="fa-solid fa-download"></i></a>
                                        <button onclick="showConfirmDelete(<?= $r['id'] ?>)" class="btn-premium"
                                            style="background:#fee2e2; color:#ef4444; padding:8px 12px;" title="Hapus"><i
                                                class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Preview -->
    <div class="modal-overlay" id="modalPreview">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--accent-color);"></i> Pratinjau
                    Dokumen Rekap</h3>
                <button class="modal-close" onclick="closeModal('modalPreview')" type="button">&times;</button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Content via AJAX -->
                <div style="display:flex; justify-content:center; align-items:center; height:100%;"><i
                        class="fa-solid fa-circle-notch fa-spin" style="font-size:30px; color:var(--accent-color);"></i>
                </div>
            </div>
            <div class="preview-toolbar">
                <input type="hidden" id="currentRecapType">
                <button class="btn-premium" style="background:#f1f5f9; color:#475569;"
                    onclick="closeModal('modalPreview')">Tutup</button>
                <div style="background:#e2e8f0; width:1px; height:25px; margin:0 5px;"></div>
                <button class="btn-premium" style="background:#0ea5e9; color:white;" onclick="exportReport('pdf')"><i
                        class="fa-solid fa-file-pdf"></i> Cetak PDF</button>
                <button class="btn-premium" style="background:#4f46e5; color:white;" onclick="showConfirmGen()"><i
                        class="fa-solid fa-file-word"></i> Simpan ke DMS (Word)</button>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Custom -->
    <div class="modal-overlay" id="modalConfirm" style="z-index: 2100;">
        <div class="modal-box" style="max-width:400px; text-align:center; padding:30px; border-radius:24px;">
            <div
                style="width:70px; height:70px; background:#e0e7ff; color:#4f46e5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px;">
                <i class="fa-solid fa-file-circle-check"></i>
            </div>
            <h3 style="font-family:'Montserrat'; margin-bottom:10px;">Konfirmasi Generate</h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:25px; line-height:1.6;">Dokumen Word akan dihasilkan
                otomatis dan disimpan ke dalam arsip DMS. Lanjutkan?</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button class="btn-premium"
                    style="background:#f1f5f9; color:#475569; width:100px; justify-content:center;"
                    onclick="closeModal('modalConfirm')">Batal</button>
                <button class="btn-premium btn-premium-indigo" style="width:160px; justify-content:center;"
                    onclick="executeExport()">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    <!-- Modal Manual Upload -->
    <div class="modal-overlay" id="modalUpload">
        <div class="modal-box" style="max-width:500px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-upload"></i> Unggah Dokumen Manual</h3><button class="modal-close"
                    onclick="closeModal('modalUpload')">&times;</button>
            </div>
            <div class="modal-body" style="padding:25px;">
                <form action="dms_handler.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload"><input type="hidden" name="month"
                        value="<?= $f_month ?>"><input type="hidden" name="year" value="<?= $f_year ?>">
                    <div class="form-group"><label class="form-label">Tipe Laporan</label><select name="recap_type"
                            class="form-control">
                            <option value="attendance">Absensi</option>
                            <option value="project">Proyek</option>
                        </select></div>
                    <div class="form-group"><label class="form-label">Judul Dokumen</label><input type="text"
                            name="title" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Pilih File</label><input type="file" name="file"
                            class="form-control" required></div>
                    <button type="submit" class="btn-premium btn-premium-indigo"
                        style="width:100%; padding:14px; margin-top:10px;">Simpan ke Arsip</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete Custom -->
    <div class="modal-overlay" id="modalDelete" style="z-index: 2200;">
        <div class="modal-box" style="max-width:400px; text-align:center; padding:30px; border-radius:24px;">
            <div
                style="width:70px; height:70px; background:#fee2e2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:30px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 style="font-family:'Montserrat'; margin-bottom:10px;">Hapus Arsip?</h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:25px; line-height:1.6;">Dokumen ini akan dihapus
                permanen dari server dan tidak dapat dikembalikan. Lanjutkan?</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button class="btn-premium"
                    style="background:#f1f5f9; color:#475569; width:100px; justify-content:center;"
                    onclick="closeModal('modalDelete')">Batal</button>
                <input type="hidden" id="deleteTargetId">
                <button class="btn-premium"
                    style="background:#ef4444; color:white; width:160px; justify-content:center;"
                    onclick="executeDelete()">Ya, Hapus</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        function openPreview(type) {
            const modal = document.getElementById('modalPreview');
            const content = document.getElementById('previewContent');
            document.getElementById('currentRecapType').value = type;

            modal.classList.add('active');
            content.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100%;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:30px; color:var(--accent-color);"></i></div>';

            fetch(`dms_preview_data.php?type=${type}&month=<?= $f_month ?>&year=<?= $f_year ?>&staff_id=<?= $f_staff ?? '' ?>&project_id=<?= $f_project ?? '' ?>`)
                .then(res => res.text())
                .then(html => {
                    content.innerHTML = `<div class="preview-inner">${html}</div>`;
                });
        }

        function exportReport(format) {
            const type = document.getElementById('currentRecapType').value;
            const month = <?= $f_month ?>;
            const year = <?= $f_year ?>;
            const staff_id = '<?= $f_staff ?? '' ?>';
            const project_id = '<?= $f_project ?? '' ?>';

            if (format === 'pdf') {
                window.open(`rekap_print.php?type=${type}&month=${month}&year=${year}&staff_id=${staff_id}&project_id=${project_id}`, '_blank');
            }
        }

        function showConfirmGen() {
            document.getElementById('modalConfirm').classList.add('active');
        }

        function executeExport() {
            const type = document.getElementById('currentRecapType').value;
            const month = <?= $f_month ?>;
            const year = <?= $f_year ?>;
            const staff_id = '<?= $f_staff ?? '' ?>';
            const project_id = '<?= $f_project ?? '' ?>';
            window.location.href = `dms_generate.php?type=${type}&month=${month}&year=${year}&staff_id=${staff_id}&project_id=${project_id}`;
        }

        function showConfirmDelete(id) {
            document.getElementById('deleteTargetId').value = id;
            document.getElementById('modalDelete').classList.add('active');
        }

        function executeDelete() {
            const id = document.getElementById('deleteTargetId').value;
            window.location.href = `dms_handler.php?delete=${id}&month=<?= $f_month ?>&year=<?= $f_year ?>`;
        }

        function viewFileRecord(id) {
            const modal = document.getElementById('modalPreview');
            const content = document.getElementById('previewContent');

            modal.classList.add('active');
            content.innerHTML = '<div style="display:flex; justify-content:center; align-items:center; height:100%;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:30px; color:var(--accent-color);"></i></div>';

            fetch(`dms_view_file.php?id=${id}`)
                .then(res => res.text())
                .then(html => {
                    content.innerHTML = `<div class="preview-inner">${html}</div>`;
                });
        }
    </script>
</body>

</html>