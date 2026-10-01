<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('staff');
$current_page = 'proyek';
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Attendance Guard: Must check-in before viewing projects
if (!is_staff_checked_in($pdo, $user_id)) {
    header("Location: absen.php?msg=need_checkin");
    exit;
}

// Submit penyelesaian proyek (foto + catatan)
if(isset($_POST['complete_project'])){
    $task_id = (int)$_POST['task_id'];
    $notes = trim($_POST['completion_notes']);
    $photo = '';
    if(isset($_FILES['evidence']) && $_FILES['evidence']['error']===0){
        $ext = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
        $photo = 'proyek_'.$user_id.'_'.time().'.'.$ext;
        if(!is_dir('../uploads/proyek/')) mkdir('../uploads/proyek/', 0777, true);
        compress_image_upload($_FILES['evidence']['tmp_name'], '../uploads/proyek/'.$photo, 75, 1000);
    }
    
    $lpj = '';
    if(isset($_FILES['lpj_file']) && $_FILES['lpj_file']['error']===0){
        $ext = pathinfo($_FILES['lpj_file']['name'], PATHINFO_EXTENSION);
        $lpj = 'lpj_'.$user_id.'_'.time().'.'.$ext;
        if(!is_dir('../uploads/lpj/')) mkdir('../uploads/lpj/', 0777, true);
        move_uploaded_file($_FILES['lpj_file']['tmp_name'], '../uploads/lpj/'.$lpj);
    }

    $actual_transport = !empty($_POST['actual_transport_cost']) ? (float)preg_replace('/[^0-9]/','',$_POST['actual_transport_cost']) : 0;
    $actual_hotel = !empty($_POST['actual_hotel_cost']) ? (float)preg_replace('/[^0-9]/','',$_POST['actual_hotel_cost']) : 0;
    $actual_meal = !empty($_POST['actual_meal_cost']) ? (float)preg_replace('/[^0-9]/','',$_POST['actual_meal_cost']) : 0;

    $reqTask = $pdo->prepare("SELECT transport_cost, hotel_cost, meal_allowance FROM tasks WHERE id=? AND assigned_to=?");
    $reqTask->execute([$task_id, $user_id]);
    $taskInfo = $reqTask->fetch();

    $t_receipt = '';
    if(isset($_FILES['transport_receipt']) && $_FILES['transport_receipt']['error']===0){
        $t_receipt = 'trans_'.$user_id.'_'.time().'.'.strtolower(pathinfo($_FILES['transport_receipt']['name'], PATHINFO_EXTENSION));
        compress_image_upload($_FILES['transport_receipt']['tmp_name'], '../uploads/proyek/'.$t_receipt, 70, 800);
    }
    $h_receipt = '';
    if(isset($_FILES['hotel_receipt']) && $_FILES['hotel_receipt']['error']===0){
        $h_receipt = 'hotel_'.$user_id.'_'.time().'.'.strtolower(pathinfo($_FILES['hotel_receipt']['name'], PATHINFO_EXTENSION));
        compress_image_upload($_FILES['hotel_receipt']['tmp_name'], '../uploads/proyek/'.$h_receipt, 70, 800);
    }
    $m_receipt = '';
    if(isset($_FILES['meal_receipt']) && $_FILES['meal_receipt']['error']===0){
        $m_receipt = 'meal_'.$user_id.'_'.time().'.'.strtolower(pathinfo($_FILES['meal_receipt']['name'], PATHINFO_EXTENSION));
        compress_image_upload($_FILES['meal_receipt']['tmp_name'], '../uploads/proyek/'.$m_receipt, 70, 800);
    }

    $provided_tr = (float)($taskInfo['transport_cost']??0);
    $provided_ht = (float)($taskInfo['hotel_cost']??0);
    $provided_ml = (float)($taskInfo['meal_allowance']??0);

    $total_given = $provided_tr + $provided_ht + $provided_ml;
    $total_actual = $actual_transport + $actual_hotel + $actual_meal;
    $emergency_fund = max(0, $total_given - $total_actual);

    // Digital LPJ
    $lpj_type = $_POST['lpj_method'] ?? 'file';
    $lpj_json = null;
    if($lpj_type === 'digital') {
        $lpj_data = [
            'overview' => $_POST['lpj_overview'] ?? '',
            'activities' => $_POST['lpj_activities'] ?? '',
            'obstacles' => $_POST['lpj_obstacles'] ?? '',
            'conclusion' => $_POST['lpj_conclusion'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $lpj_json = json_encode($lpj_data);
    }

    if(!empty($notes) && !empty($photo)){
        $pdo->prepare("UPDATE tasks SET status='completed', photo_evidence=?, completion_notes=?, lpj_file=?, 
                       actual_transport_cost=?, transport_receipt=?, actual_hotel_cost=?, hotel_receipt=?, 
                       actual_meal_cost=?, meal_receipt=?, emergency_fund=?, lpj_type=?, lpj_content_json=? 
                       WHERE id=? AND assigned_to=?")->execute([
            $photo, $notes, $lpj, 
            $actual_transport, $t_receipt, $actual_hotel, $h_receipt, 
            $actual_meal, $m_receipt, $emergency_fund, 
            $lpj_type, $lpj_json,
            $task_id, $user_id
        ]);
    }
    header("Location: proyek.php?msg=completed"); exit;
}

// Download LPJ as Word (HTML-based DOC)
if(isset($_GET['download_word']) && !empty($_GET['download_word'])){
    $tid = (int)$_GET['download_word'];
    $st = $pdo->prepare("SELECT t.*, u.full_name as staff_name FROM tasks t JOIN users u ON t.assigned_to = u.id WHERE t.id=? AND t.assigned_to=?");
    $st->execute([$tid, $user_id]);
    $t = $st->fetch();
    
    if($t && $t['lpj_type'] == 'digital') {
        $data = json_decode($t['lpj_content_json'], true);
        
        header("Content-Type: application/vnd.ms-word");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("content-disposition: attachment;filename=LPJ_".str_replace(' ','_',$t['title'])."_".date('Ymd').".doc");
        
        echo "<html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Arial', sans-serif; line-height: 1.6; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                h2 { color: #1a365d; }
                .section { margin-bottom: 20px; }
                .label { font-weight: bold; color: #2d3748; display: block; margin-bottom: 5px; background: #edf2f7; padding: 5px; }
                .content { padding-left: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>PT KUALITA PRIMA</h1>
                <h3>LAPORAN PERTANGGUNGJAWABAN PROYEK</h3>
            </div>
            
            <table width='100%' cellpadding='5'>
                <tr><td width='150'><b>Kode Proyek</b></td><td>: ".($t['project_code']??'-')."</td></tr>
                <tr><td><b>Nama Proyek</b></td><td>: ".($t['title'])."</td></tr>
                <tr><td><b>Staff Pelaksana</b></td><td>: ".($t['staff_name'])."</td></tr>
                <tr><td><b>Tanggal LPJ</b></td><td>: ".date('d F Y', strtotime($data['created_at']))."</td></tr>
            </table>
            
            <div class='section'>
                <div class='label'>1. RINGKASAN PEKERJAAN</div>
                <div class='content'>".nl2br(htmlspecialchars($data['overview']))."</div>
            </div>
            
            <div class='section'>
                <div class='label'>2. RINCIAN AKTIVITAS LAPANGAN</div>
                <div class='content'>".nl2br(htmlspecialchars($data['activities']))."</div>
            </div>
            
            <div class='section'>
                <div class='label'>3. KENDALA & MASALAH</div>
                <div class='content'>".nl2br(htmlspecialchars($data['obstacles'] ?: 'Tidak ada kendala berarti'))."</div>
            </div>
            
            <div class='section'>
                <div class='label'>4. KESIMPULAN / HASIL AKHIR</div>
                <div class='content'>".nl2br(htmlspecialchars($data['conclusion']))."</div>
            </div>
            
            <br><br>
            <table width='100%'>
                <tr>
                    <td width='50%' align='center'>
                        Diterima oleh,<br><br><br><br>
                        ( Admin KPP )
                    </td>
                    <td width='50%' align='center'>
                        Dibuat oleh,<br><br><br><br>
                        ( ".$t['staff_name']." )
                    </td>
                </tr>
            </table>
        </body>
        </html>";
        exit;
    }
}

// Update status ke in_progress
if(isset($_POST['start_project'])){
    $task_id = (int)$_POST['task_id'];
    $pdo->prepare("UPDATE tasks SET status='in_progress' WHERE id=? AND assigned_to=? AND status='pending'")->execute([$task_id, $user_id]);
    header("Location: proyek.php"); exit;
}

// Konfirmasi dana diterima (Updated: Staff only confirms, Admin uploads)
if(isset($_POST['confirm_dana'])){
    $task_id = (int)$_POST['task_id'];
    try {
        $pdo->prepare("UPDATE tasks SET dana_diterima_confirmed=1, dana_diterima_at=NOW() WHERE id=? AND assigned_to=?")->execute([$task_id, $user_id]);
        header("Location: proyek.php?msg=dana_confirmed"); exit;
    } catch(Exception $e) {
        header("Location: proyek.php?msg=error"); exit;
    }
}

$stmtActive = $pdo->prepare("SELECT t.*, u.full_name as admin_name FROM tasks t LEFT JOIN users u ON t.assigned_by = u.id WHERE t.assigned_to = ? AND t.status != 'completed' ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC");
$stmtActive->execute([$user_id]); $active_tasks = $stmtActive->fetchAll();

$stmtDone = $pdo->prepare("SELECT t.*, u.full_name as admin_name FROM tasks t LEFT JOIN users u ON t.assigned_by = u.id WHERE t.assigned_to = ? AND t.status = 'completed' ORDER BY t.created_at DESC LIMIT 10");
$stmtDone->execute([$user_id]); $done_tasks = $stmtDone->fetchAll();

// Preload materials
$allMaterials = [];
$taskIds = array_merge(array_column($active_tasks, 'id'), array_column($done_tasks, 'id'));
if(!empty($taskIds)) {
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $matStmt = $pdo->prepare("SELECT * FROM project_materials WHERE task_id IN ($placeholders) ORDER BY id ASC");
    $matStmt->execute($taskIds);
    foreach($matStmt->fetchAll() as $m) {
        $allMaterials[$m['task_id']][] = $m;
    }
}

$categoryLabels = ['engineering'=>'Engineering','procurement'=>'Procurement','construction'=>'Construction','other'=>'Lainnya'];
$categoryColors = ['engineering'=>'#2563eb','procurement'=>'#d97706','construction'=>'#059669','other'=>'#64748b'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Saya — KPP Staff</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250813">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .detail-card { background: #f8fafc; padding: 12px 15px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px; }
        .detail-card i { color: var(--accent-color); margin-right: 5px; width: 16px; text-align: center; }
        .detail-card .label { font-size: 11px; color: var(--text-light); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .detail-card .value { font-weight: 700; color: var(--text-dark); font-family: 'Montserrat', sans-serif; }
        
        .section-divider { 
            font-size: 13px; font-weight: 700; color: var(--text-dark); 
            font-family: 'Montserrat', sans-serif; margin: 20px 0 10px;
            padding-bottom: 8px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 8px;
        }
        .section-divider i { color: var(--accent-color); }
        
        .project-code { 
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, rgba(75,56,170,0.08), rgba(99,102,241,0.08));
            padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 700;
            font-family: 'Montserrat', sans-serif; color: var(--accent-color); letter-spacing: 0.5px;
        }
        
        .badge-cat { padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; font-family: 'Montserrat', sans-serif; }
        
        .material-list { margin-top: 10px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .material-list table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .material-list th { background: var(--bg-gray); padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; }
        .material-list td { padding: 8px 10px; border-bottom: 1px solid var(--border); }
        .material-list tr:last-child td { border-bottom: none; }
        .material-list .mat-total-row { background: rgba(16,185,129,0.05); }
        .material-list .mat-total-row td { font-weight: 700; font-family: 'Montserrat', sans-serif; color: var(--success); }
        
        .budget-bar {
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.03));
            border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 12px 18px;
            margin-top: 15px; display: flex; justify-content: space-between; align-items: center;
        }
        .budget-bar .label { font-size: 12px; color: var(--text-light); font-weight: 600; }
        .budget-bar .amount { font-size: 18px; font-weight: 800; color: var(--success); font-family: 'Montserrat', sans-serif; }
        
        .special-notes-box {
            background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.2);
            border-radius: 8px; padding: 12px 15px; margin-top: 12px; font-size: 13px;
        }
        .special-notes-box i { color: var(--warning); }
        
        #printTemplate { display: none; padding: 40px; background: white; color: black; font-family: 'Open Sans', sans-serif; }
        .print-header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
        .print-header h1 { margin: 0; font-size: 24px; color: #1e293b; }
        .print-header p { margin: 5px 0 0; color: #64748b; font-size: 14px; }
        .print-section { margin-bottom: 25px; }
        .print-section h3 { border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; font-size: 16px; color: #334155; margin-bottom: 15px; }
        .print-table { width: 100%; border-collapse: collapse; }
        .print-table th, .print-table td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; font-size: 13px; }
        .print-table th { background: #f1f5f9; width: 35%; color: #475569; }
        
        @media(max-width:600px) {
            .detail-grid { grid-template-columns: 1fr; }
        }

        /* Better File Input */
        .custom-file-input { display: none; }
        .file-upload-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 20px; background: #fff; border: 2px dashed #cbd5e1;
            border-radius: 10px; cursor: pointer; transition: all 0.3s ease;
            color: #64748b; font-weight: 600; font-size: 14px;
        }
        .file-upload-btn:hover { border-color: var(--accent-color); background: rgba(75,56,170,0.03); color: var(--accent-color); }
        .file-upload-btn i { font-size: 18px; }
        .file-name-preview { font-size: 12px; color: var(--success); margin-top: 5px; display: none; font-weight: 600; }

        /* Method Toggle */
        .method-toggle { display: flex; background: #f1f5f9; padding: 5px; border-radius: 10px; margin-bottom: 20px; }
        .method-btn { flex: 1; padding: 10px; border: none; background: none; border-radius: 8px; font-weight:700; font-size:12px; cursor:pointer; color:#64748b; display:flex; align-items:center; justify-content:center; gap:8px; }
        .method-btn.active { background: #fff; color: var(--accent-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="staff-body">
<?php require_once '../includes/staff_nav.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Proyek & Penugasan</h1><p>Proyek yang diberikan oleh admin</p></div>
        </div>
        <div class="topbar-right"><div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?></div></div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg']) && $_GET['msg']==='completed'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Proyek berhasil diselesaikan dan bukti telah disimpan!</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg']==='dana_confirmed'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-money-bill-wave"></i> Konfirmasi dana diterima berhasil disimpan!</div>
        <?php endif; ?>

        <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-info"><h4>Proyek Aktif (Prioritas)</h4><div class="stat-value"><?= count($active_tasks) ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
                <div class="stat-info"><h4>Proyek Selesai</h4><div class="stat-value"><?= count($done_tasks) ?></div></div>
            </div>
        </div>

        <!-- Active Tasks -->
        <div class="card" style="margin-bottom:25px;">
            <div class="card-header"><h3><i class="fa-solid fa-list-check"></i> Proyek Aktif</h3></div>
            <div class="card-body" style="padding:15px 20px;">
                <?php if(empty($active_tasks)): ?>
                    <div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>Tidak ada proyek aktif saat ini. Menunggu assignment dari Admin. 🎉</p></div>
                <?php else: foreach($active_tasks as $idx => $t): 
                    $mats = $allMaterials[$t['id']] ?? [];
                    $matTotal = array_sum(array_column($mats, 'total_price'));
                    $catLabel = $categoryLabels[$t['project_category'] ?? 'other'] ?? 'Lainnya';
                    $catColor = $categoryColors[$t['project_category'] ?? 'other'] ?? '#64748b';
                ?>
                <div class="task-card priority-<?= $t['priority'] ?>" style="<?= $idx>0 ? 'opacity:0.6; pointer-events:none;' : '' ?>; position:relative;">
                    <?php if($idx>0): ?><div style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); color:#fff; padding:5px 10px; border-radius:5px; font-size:11px;"><i class="fa-solid fa-lock"></i> Selesaikan proyek sebelumnya</div><?php endif; ?>
                    
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                        <div>
                            <?php if(!empty($t['project_code'])): ?><div class="project-code"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($t['project_code']) ?></div><?php endif; ?>
                            <h4 style="margin-top:5px;"><?= htmlspecialchars($t['title']) ?></h4>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <span class="badge badge-<?= $t['priority'] ?>"><?= strtoupper($t['priority']) ?></span>
                            <?php if(!empty($t['project_category']) && $t['project_category'] !== 'other'): ?>
                                <span class="badge-cat" style="background:<?= $catColor ?>15;color:<?= $catColor ?>;"><?= $catLabel ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if($t['description']): ?><p><?= nl2br(htmlspecialchars($t['description'])) ?></p><?php endif; ?>
                    
                    <!-- Client & Location -->
                    <?php if(!empty($t['client_name']) || !empty($t['project_location'])): ?>
                    <div class="section-divider"><i class="fa-solid fa-building"></i> Klien & Lokasi</div>
                    <div class="detail-grid">
                        <?php if(!empty($t['client_name'])): ?>
                        <div class="detail-card">
                            <div class="label">Perusahaan / Klien</div>
                            <div class="value"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($t['client_name']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['client_pic'])): ?>
                        <div class="detail-card">
                            <div class="label">PIC (Contact Person)</div>
                            <div class="value"><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($t['client_pic']) ?> <?php if(!empty($t['client_pic_phone'])): ?><br><small style="font-weight:400;color:var(--text-light);"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($t['client_pic_phone']) ?></small><?php endif; ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['project_location'])): ?>
                        <div class="detail-card" style="grid-column:1/-1;">
                            <div class="label">Lokasi Proyek</div>
                            <div class="value"><i class="fa-solid fa-location-dot" style="color:var(--danger);"></i> <?= htmlspecialchars($t['project_location']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Schedule -->
                    <?php if(!empty($t['start_date']) || !empty($t['end_date']) || !empty($t['due_date']) || !empty($t['project_days'])): ?>
                    <div class="section-divider"><i class="fa-solid fa-calendar-days"></i> Jadwal</div>
                    <div class="detail-grid">
                        <?php if(!empty($t['start_date'])): ?>
                        <div class="detail-card">
                            <div class="label">Mulai Kerja</div>
                            <div class="value"><i class="fa-solid fa-play" style="color:var(--success);"></i> <?= date('d M Y', strtotime($t['start_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['end_date'])): ?>
                        <div class="detail-card">
                            <div class="label">Selesai Kerja</div>
                            <div class="value"><i class="fa-solid fa-flag-checkered" style="color:var(--danger);"></i> <?= date('d M Y', strtotime($t['end_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['project_days'])): ?>
                        <div class="detail-card">
                            <div class="label">Durasi</div>
                            <div class="value"><i class="fa-solid fa-calendar-day"></i> <?= $t['project_days'] ?> Hari</div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['due_date'])): ?>
                        <div class="detail-card">
                            <div class="label">Deadline</div>
                            <div class="value"><i class="fa-regular fa-clock"></i> <?= date('d M Y', strtotime($t['due_date'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Accommodation -->
                    <?php if(!empty($t['transport_mode']) || !empty($t['hotel_name']) || !empty($t['meal_allowance'])): ?>
                    <div class="section-divider"><i class="fa-solid fa-wallet"></i> Akomodasi & Biaya</div>
                    <div class="detail-grid">
                        <?php if(!empty($t['transport_mode'])): ?>
                        <div class="detail-card">
                            <div class="label">Transportasi</div>
                            <div class="value"><i class="fa-solid fa-car"></i> <?= htmlspecialchars($t['transport_mode']) ?> <br><small style="font-weight:400;">(Alokasi: Rp <?= number_format($t['transport_cost']??0, 0, ',', '.') ?>)</small>
                            <?php if($t['status']==='completed' && !empty($t['actual_transport_cost'])): ?>
                            <br><small style="font-weight:600;color:var(--danger);">Terpakai: Rp <?= number_format($t['actual_transport_cost'], 0, ',', '.') ?></small>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['hotel_name'])): ?>
                        <div class="detail-card">
                            <div class="label">Penginapan</div>
                            <div class="value"><i class="fa-solid fa-hotel"></i> <?= htmlspecialchars($t['hotel_name']) ?> <br><small style="font-weight:400;">(Alokasi: Rp <?= number_format($t['hotel_cost']??0, 0, ',', '.') ?>)</small>
                            <?php if($t['status']==='completed' && !empty($t['actual_hotel_cost'])): ?>
                            <br><small style="font-weight:600;color:var(--danger);">Terpakai: Rp <?= number_format($t['actual_hotel_cost'], 0, ',', '.') ?></small>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($t['meal_allowance'])): ?>
                        <div class="detail-card">
                            <div class="label">Uang Makan</div>
                            <div class="value"><i class="fa-solid fa-utensils"></i> Rp <?= number_format($t['meal_allowance'], 0, ',', '.') ?>
                            <?php if($t['status']==='completed' && !empty($t['actual_meal_cost'])): ?>
                            <br><small style="font-weight:600;color:var(--danger);">Terpakai: Rp <?= number_format($t['actual_meal_cost'], 0, ',', '.') ?></small>
                            <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Bukti Transfer & Konfirmasi Dana -->
                        <div class="detail-card" style="grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-left: 4px solid <?= !empty($t['dana_diterima_confirmed']) ? 'var(--success)' : (!empty($t['dana_diterima_photo']) ? '#d97706' : '#94a3b8') ?>;">
                            <div>
                                <div class="label">Bukti Transfer & Penerimaan Dana</div>
                                <div class="value" style="font-size: 13px;">
                                    <?php if(!empty($t['dana_diterima_confirmed'])): ?>
                                        <span style="color:var(--success); font-weight:700;"><i class="fa-solid fa-circle-check"></i> Dana Diterima & Dikonfirmasi</span>
                                        <?php if(!empty($t['dana_diterima_at'])): ?>
                                            <br><small style="font-weight:400; color:var(--text-light); font-size:11px;"><i class="fa-solid fa-clock"></i> Dikonfirmasi: <?= date('d M Y H:i', strtotime($t['dana_diterima_at'])) ?></small>
                                        <?php endif; ?>
                                    <?php elseif(!empty($t['dana_diterima_photo'])): ?>
                                        <span style="color:#d97706; font-weight:700;"><i class="fa-solid fa-circle-exclamation"></i> Ada Dana Masuk (Belum Dikonfirmasi)</span>
                                        <br><small style="font-weight:400; color:var(--text-light); font-size:11px;">Silakan klik tombol di samping untuk memeriksa bukti dan konfirmasi.</small>
                                    <?php else: ?>
                                        <span style="color:var(--text-light); font-weight:600;"><i class="fa-solid fa-hourglass"></i> Menunggu Bukti Transfer dari Admin</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <?php if(!empty($t['dana_diterima_photo'])): ?>
                                    <button type="button" class="btn btn-sm" style="font-size: 12px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 5px; <?= empty($t['dana_diterima_confirmed']) ? 'background:var(--success); color:#fff;' : 'background:#e2e8f0; color:#334155;' ?>" onclick="openDanaModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                        <i class="fa-solid <?= empty($t['dana_diterima_confirmed']) ? 'fa-wallet' : 'fa-receipt' ?>"></i> 
                                        <?= empty($t['dana_diterima_confirmed']) ? 'Konfirmasi Dana' : 'Lihat Bukti Transfer' ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Materials (PRICE HIDDEN FROM STAFF) -->
                    <?php if(count($mats) > 0): ?>
                    <div class="section-divider"><i class="fa-solid fa-boxes-stacked" style="color:var(--warning);"></i> Daftar Barang / Alat / Bahan</div>
                    <div class="material-list">
                        <table>
                            <thead><tr><th>Item</th><th>Kategori</th><th>Qty</th><th>Satuan</th><th>Supplier</th></tr></thead>
                            <tbody>
                            <?php foreach($mats as $m): ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($m['item_name']) ?></td>
                                <td><span class="badge" style="font-size:10px;padding:2px 8px;"><?= ucfirst($m['item_category']) ?></span></td>
                                <td><?= $m['quantity'] ?></td>
                                <td><?= htmlspecialchars($m['unit']) ?></td>
                                <td style="font-size:11px;color:var(--text-light);"><?= htmlspecialchars($m['supplier'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Total Budget HIDDEN from staff -->
                    <!-- Special Notes -->
                    <?php if(!empty($t['special_notes'])): ?>
                    <div class="special-notes-box">
                        <i class="fa-solid fa-note-sticky"></i> <strong>Catatan Khusus:</strong><br>
                        <?= nl2br(htmlspecialchars($t['special_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top:12px;font-size:12px;color:var(--text-light);">
                        <i class="fa-regular fa-user"></i> Ditugaskan oleh: <strong><?= htmlspecialchars($t['admin_name']) ?></strong>
                    </div>

                    <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if($t['status']==='pending'): ?>
                        <form method="POST" id="formStart_<?= $t['id'] ?>" style="display:inline;">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>"><input type="hidden" name="start_project" value="1">
                            <button type="button" class="btn btn-accent" onclick="showConfirm('Mulai kerjakan proyek ini?', () => document.getElementById('formStart_<?= $t['id'] ?>').submit())">
                                <i class="fa-solid fa-play"></i> Mulai Kerjakan
                            </button>
                        </form>
                        <?php else: ?>
                        <button type="button" class="btn btn-success" onclick="openComplete(<?= htmlspecialchars(json_encode($t)) ?>)"><i class="fa-solid fa-check-double"></i> Selesaikan Proyek & LPJ</button>
                        <button type="button" class="btn" style="background:#e2e8f0; color:#334155;" onclick="exportPDF(<?= htmlspecialchars(json_encode($t)) ?>, <?= htmlspecialchars(json_encode($mats)) ?>)"><i class="fa-solid fa-file-pdf"></i> Cetak Detail</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Completed Tasks -->
        <?php if(count($done_tasks) > 0): ?>
        <div class="card">
            <div class="card-header"><h3><i class="fa-solid fa-check-circle"></i> Riwayat Proyek Terselesaikan</h3></div>
            <div class="card-body" style="padding:0;">
                <table class="data-table" style="font-size:13px;">
                    <thead><tr><th>Proyek & Detail</th><th>Klien</th><th>Prioritas</th><th>Bukti & LPJ</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach($done_tasks as $t): 
                        $mats = $allMaterials[$t['id']] ?? [];
                    ?>
                    <tr>
                        <td>
                            <?php if(!empty($t['project_code'])): ?><div class="project-code" style="margin-bottom:3px;"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($t['project_code']) ?></div><?php endif; ?>
                            <strong style="color:var(--text-dark);"><?= htmlspecialchars($t['title']) ?></strong>
                            <?php if(!empty($t['project_location'])): ?><div style="font-size:11px;color:var(--text-light);"><i class="fa-solid fa-location-dot" style="color:var(--danger);"></i> <?= htmlspecialchars(mb_substr($t['project_location'],0,30)) ?></div><?php endif; ?>
                            <div style="font-size:12px;color:var(--text-light);margin-top:4px;">Diselesaikan: <?= date('d M Y H:i', strtotime($t['created_at'])) ?></div>
                            <div style="font-size:12px;color:var(--text-light);margin-top:2px;">Catatan: <?= htmlspecialchars(mb_substr($t['completion_notes']??'',0,60)) ?></div>
                            <?php if(!empty($t['emergency_fund']) && $t['emergency_fund'] > 0): ?>
                            <div style="font-size:11px;font-weight:700;color:var(--success);margin-top:6px;background:rgba(16,185,129,0.1);display:inline-block;padding:3px 8px;border-radius:4px;"><i class="fa-solid fa-piggy-bank"></i> Sisa & Dana Darurat: Rp <?= number_format($t['emergency_fund'], 0, ',', '.') ?></div>
                            <?php endif; ?>
                            <?php if(!empty($t['dana_diterima_confirmed'])): ?>
                            <div style="font-size:11px;font-weight:700;color:var(--success);margin-top:6px;background:rgba(16,185,129,0.1);display:inline-block;padding:3px 8px;border-radius:4px;"><i class="fa-solid fa-check-circle"></i> Dana Diterima</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?php if(!empty($t['client_name'])): ?><strong><?= htmlspecialchars($t['client_name']) ?></strong><?php else: ?>-<?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $t['priority'] ?>"><?= strtoupper($t['priority']) ?></span></td>
                        <td>
                            <?php if(!empty($t['photo_evidence'])): ?>
                                <img src="../uploads/proyek/<?= htmlspecialchars($t['photo_evidence']) ?>" class="img-thumb" style="cursor:pointer;" onclick="window.open(this.src)">
                            <?php else: ?>-<?php endif; ?>
                            <?php if(!empty($t['lpj_file'])): ?>
                                <br><a href="../uploads/lpj/<?= htmlspecialchars($t['lpj_file']) ?>" target="_blank" style="font-size:12px; color:var(--accent-color);"><i class="fa-solid fa-paperclip"></i> Dokumen LPJ</a>
                            <?php elseif($t['lpj_type'] == 'digital'): ?>
                                <br><a href="?download_word=<?= $t['id'] ?>" style="font-size:12px; color:var(--success); font-weight:700;"><i class="fa-solid fa-file-word"></i> Download LPJ Word</a>
                            <?php endif; ?>
                            <?php if(!empty($t['dana_diterima_photo'])): ?>
                                <br><a href="../uploads/proyek/<?= htmlspecialchars($t['dana_diterima_photo']) ?>" target="_blank" style="font-size:12px; color:var(--accent-color);"><i class="fa-solid fa-receipt"></i> Bukti Transfer Admin</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm" style="background:#e2e8f0; color:#334155; padding:5px 10px;" onclick="exportPDF(<?= htmlspecialchars(json_encode($t)) ?>, <?= htmlspecialchars(json_encode($mats)) ?>)"><i class="fa-solid fa-file-export"></i> PDF</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Konfirmasi Dana Diterima -->
<div class="modal-overlay" id="modalDana">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-money-bill-wave" style="color:var(--success);"></i> Konfirmasi Dana Diterima</h3>
            <button class="modal-close" onclick="closeModal('modalDana')" type="button">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:12px;margin-bottom:18px;font-size:13px;">
                <i class="fa-solid fa-circle-info" style="color:var(--success);"></i> Silakan cek bukti transfer di bawah ini. Jika dana sudah masuk ke rekening Anda, klik tombol <strong>Konfirmasi Dana Diterima</strong>.
            </div>
            <form method="POST">
                <input type="hidden" name="task_id" id="dana_task_id">
                <div class="form-group" style="text-align:center;">
                    <label class="form-label" style="text-align:left;"><i class="fa-solid fa-image" style="color:var(--accent-color);"></i> Bukti Transfer dari Admin</label>
                    <div id="dana_photo_container" style="margin-top:10px; border:1px solid var(--border); border-radius:8px; padding:10px; background:#f8fafc;">
                        <img id="dana_photo_preview" src="" style="max-width:100%; border-radius:4px; display:none; cursor:pointer;" onclick="window.open(this.src)">
                        <div id="dana_photo_missing" style="padding:20px; color:var(--text-light);"><i class="fa-solid fa-receipt fa-2x"></i><br>Bukti belum diunggah Admin</div>
                    </div>
                </div>
                <button type="button" id="dana_confirm_btn" class="btn btn-success" style="width:100%;padding:14px;margin-top:10px;" onclick="showConfirm('Apakah Anda yakin dana sudah masuk?', () => this.closest('form').submit())"><i class="fa-solid fa-check-circle"></i> Konfirmasi Dana Diterima</button>
                <input type="hidden" name="confirm_dana" value="1">
            </form>
        </div>
    </div>
</div>

<!-- Modal Complete Project -->
<div class="modal-overlay" id="modalComplete">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-check-circle" style="color:var(--success);"></i> Selesaikan Proyek</h3>
            <button class="modal-close" onclick="closeModal('modalComplete')" type="button">&times;</button>
        </div>
        <div class="modal-body">
            <div style="padding:12px;background:var(--bg-gray);border-radius:8px;margin-bottom:20px;">
                <small style="color:var(--text-light);">Proyek yang diselesaikan:</small>
                <strong id="completeTitle" style="display:block;color:var(--text-dark);font-family:'Montserrat',sans-serif;"></strong>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="complete_project" value="1">
                <input type="hidden" name="task_id" id="complete_task_id">

                
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-camera" style="color:var(--accent-color);"></i> Foto Bukti Kondisi Akhir <span style="color:var(--danger);">*</span></label>
                    <label class="file-upload-btn" for="evidence_input">
                        <i class="fa-solid fa-cloud-arrow-up"></i> <span>Pilih Foto Bukti</span>
                    </label>
                    <input type="file" name="evidence" id="evidence_input" accept="image/*" class="custom-file-input" required capture="environment" onchange="previewFileName(this)">
                    <div class="file-name-preview" id="evidence_input_name"></div>
                </div>
                
                <div class="section-divider"><i class="fa-solid fa-file-contract"></i> Laporan Pertanggungjawaban (LPJ)</div>
                <div class="method-toggle">
                    <button type="button" class="method-btn active" id="btnMethodDigital" onclick="switchLPJMethod('digital')"><i class="fa-solid fa-keyboard"></i> Isi Template Digital</button>
                    <button type="button" class="method-btn" id="btnMethodFile" onclick="switchLPJMethod('file')"><i class="fa-solid fa-file-upload"></i> Unggah File (Word/PDF)</button>
                </div>
                <input type="hidden" name="lpj_method" id="lpj_method" value="digital">

                <div id="areaDigitalLPJ">
                    <div class="form-group">
                        <label class="form-label" style="font-size:12px;">1. Ringkasan Pekerjaan <span style="color:var(--danger);">*</span></label>
                        <textarea name="lpj_overview" class="form-control" rows="2" placeholder="Garis besar apa yang dilakukan..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size:12px;">2. Rincian Aktivitas Lapangan <span style="color:var(--danger);">*</span></label>
                        <textarea name="lpj_activities" class="form-control" rows="3" placeholder="Step-by-step aktivitas..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="font-size:12px;">3. Kendala di Lapangan</label>
                            <textarea name="lpj_obstacles" class="form-control" rows="2" placeholder="Jika ada..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-size:12px;">4. Kesimpulan <span style="color:var(--danger);">*</span></label>
                            <textarea name="lpj_conclusion" class="form-control" rows="2" placeholder="Hasil akhir..."></textarea>
                        </div>
                    </div>
                    <p class="form-hint"><i class="fa-solid fa-wand-magic-sparkles"></i> Laporan digital akan otomatis dikonversi menjadi dokumen Word saat diunduh Admin.</p>
                </div>

                <div id="areaFileLPJ" style="display:none;">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-file-invoice" style="color:var(--accent-color);"></i> Unggah Dokumen LPJ (Word/PDF)</label>
                        <label class="file-upload-btn" for="lpj_input">
                            <i class="fa-solid fa-file-word"></i> <span>Pilih Dokumen LPJ</span>
                        </label>
                        <input type="file" name="lpj_file" id="lpj_input" accept=".pdf,.doc,.docx,.xls,.xlsx" class="custom-file-input" onchange="previewFileName(this)">
                        <div class="file-name-preview" id="lpj_input_name"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-pen" style="color:var(--accent-color);"></i> Catatan / Hasil Pekerjaan <span style="color:var(--danger);">*</span></label>
                    <textarea name="completion_notes" class="form-control" rows="4" required placeholder="Jelaskan secara detail pekerjaan yang telah diselesaikan..."></textarea>
                </div>
                
                <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:12px;margin-bottom:20px;font-size:13px;color:#92400e;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Perhatian:</strong> Pastikan seluruh data valid. Proyek tidak dapat diubah setelah ditandai selesai.
                </div>
                <button type="button" class="btn btn-success" style="width:100%;padding:14px;" onclick="showConfirm('Kirim laporan proyek ini ke Admin?', () => this.closest('form').submit())"><i class="fa-solid fa-paper-plane"></i> Kirim Laporan Proyek</button>
            </form>
        </div>
    </div>
</div>

<!-- Print/PDF Template -->
<div id="printTemplate">
    <div class="print-header">
        <h1>PT KUALITA PRIMA</h1>
        <p>Laporan Pertanggungjawaban Proyek (LPJ/Project Assignment)</p>
        <p id="pdfCode" style="font-size:12px;color:#94a3b8;margin-top:5px;"></p>
    </div>
    
    <div class="print-section">
        <h3>Informasi Proyek</h3>
        <table class="print-table">
            <tr><th>Kode Proyek</th><td id="pdfProjectCode"></td></tr>
            <tr><th>Nama Proyek</th><td id="pdfTitle"></td></tr>
            <tr><th>Kategori</th><td id="pdfCategory"></td></tr>
            <tr><th>Prioritas</th><td id="pdfPriority"></td></tr>
            <tr><th>Diberikan Oleh</th><td id="pdfAdmin"></td></tr>
            <tr><th>Deskripsi Tugas</th><td id="pdfDesc"></td></tr>
        </table>
    </div>
    
    <div class="print-section">
        <h3>Klien & Lokasi</h3>
        <table class="print-table">
            <tr><th>Nama PT / Klien</th><td id="pdfClient"></td></tr>
            <tr><th>PIC (Contact Person)</th><td id="pdfPIC"></td></tr>
            <tr><th>Telepon PIC</th><td id="pdfPICPhone"></td></tr>
            <tr><th>Lokasi Proyek</th><td id="pdfLocation"></td></tr>
        </table>
    </div>
    
    <div class="print-section">
        <h3>Jadwal</h3>
        <table class="print-table">
            <tr><th>Tanggal Mulai</th><td id="pdfStartDate"></td></tr>
            <tr><th>Tanggal Selesai</th><td id="pdfEndDate"></td></tr>
            <tr><th>Durasi</th><td id="pdfDays"></td></tr>
            <tr><th>Deadline</th><td id="pdfDeadline"></td></tr>
        </table>
    </div>

    <div class="print-section">
        <h3>Alokasi & Akomodasi</h3>
        <table class="print-table">
            <tr><th>Transportasi (Dialokasikan)</th><td id="pdfTransport"></td></tr>
            <tr><th>Transportasi (Aktual)</th><td id="pdfActualTransport"></td></tr>
            <tr><th>Penginapan (Dialokasikan)</th><td id="pdfHotel"></td></tr>
            <tr><th>Penginapan (Aktual)</th><td id="pdfActualHotel"></td></tr>
            <tr><th>Uang Makan (Dialokasikan)</th><td id="pdfMeal"></td></tr>
            <tr><th>Uang Makan (Aktual)</th><td id="pdfActualMeal"></td></tr>
            <tr><th>Sisa Dana / Dana Darurat</th><td id="pdfEmergencyFund" style="font-weight:700;color:#059669;"></td></tr>
        </table>
    </div>
    
    <div class="print-section" id="pdfMaterialSection">
        <h3>Daftar Barang / Alat / Bahan</h3>
        <table class="print-table" id="pdfMaterialTable" style="width:100%;">
            <thead><tr><th style="width:5%;">No</th><th style="width:35%;">Nama Item</th><th>Kategori</th><th>Qty</th><th>Satuan</th><th>Supplier</th></tr></thead>
            <tbody id="pdfMaterialBody"></tbody>
        </table>
    </div>
    
    <div class="print-section">
        <h3>Budget & Penyelesaian</h3>
        <table class="print-table">
            <tr><th>Total Budget</th><td id="pdfBudget" style="font-weight:700;color:#059669;"></td></tr>
            <tr><th>Status</th><td id="pdfStatus"></td></tr>
            <tr><th>Catatan Penyelesaian</th><td id="pdfNotes"></td></tr>
            <tr><th>Catatan Khusus</th><td id="pdfSpecialNotes"></td></tr>
        </table>
    </div>

    <div style="margin-top:50px; text-align:right;">
        <p style="margin-bottom:60px;">Ditandatangani oleh (Staff),</p>
        <p><strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong></p>
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
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button type="button" class="btn" style="background:#f1f5f9; color:#64748b;" onclick="closeModal('modalConfirm')">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Ya, Lanjutkan</button>
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

function previewFileName(input) {
    const id = input.id + '_name';
    const preview = document.getElementById(id);
    if (input.files && input.files[0]) {
        preview.textContent = '✓ ' + input.files[0].name;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function switchLPJMethod(method) {
    document.getElementById('lpj_method').value = method;
    const btnDigital = document.getElementById('btnMethodDigital');
    const btnFile = document.getElementById('btnMethodFile');
    const areaDigital = document.getElementById('areaDigitalLPJ');
    const areaFile = document.getElementById('areaFileLPJ');
    
    if(method === 'digital') {
        btnDigital.classList.add('active');
        btnFile.classList.remove('active');
        areaDigital.style.display = 'block';
        areaFile.style.display = 'none';
        document.getElementById('lpj_input').required = false;
        // Make digital fields required
        areaDigital.querySelectorAll('textarea').forEach(t => {
            if(!t.placeholder.includes('Jika ada')) t.required = true;
        });
    } else {
        btnFile.classList.add('active');
        btnDigital.classList.remove('active');
        areaFile.style.display = 'block';
        areaDigital.style.display = 'none';
        document.getElementById('lpj_input').required = true;
        areaDigital.querySelectorAll('textarea').forEach(t => t.required = false);
    }
}
function formatRupiah(angka, prefix) {
    if (angka === undefined || angka === null || angka === '') return '';
    var number_string = angka.toString().replace(/[^,\d]/g, ''),
        split = number_string.split(','),
        sisa  = split[0].length % 3,
        rupiah  = split[0].substr(0, sisa),
        ribuan  = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
         separator = sisa ? '.' : '';
         rupiah += separator + ribuan.join('.');
    }
    return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('currency-input')) {
        let val = e.target.value.replace(/Rp\.\s/g, '');
        e.target.value = formatRupiah(val, 'Rp. ');
    }
});

function openComplete(task){
    document.getElementById('complete_task_id').value = task.id;
    document.getElementById('completeTitle').textContent = task.title;
    document.getElementById('modalComplete').classList.add('active');
}
function openDanaModal(task) {
    document.getElementById('dana_task_id').value = task.id;
    const img = document.getElementById('dana_photo_preview');
    const msg = document.getElementById('dana_photo_missing');
    
    if(task.dana_diterima_photo) {
        img.src = '../uploads/proyek/' + task.dana_diterima_photo;
        img.style.display = 'block';
        msg.style.display = 'none';
    } else {
        img.style.display = 'none';
        msg.style.display = 'block';
    }
    
    // Hide confirm button if already confirmed
    const confirmBtn = document.getElementById('dana_confirm_btn');
    if (confirmBtn) {
        if (task.dana_diterima_confirmed == 1) {
            confirmBtn.style.display = 'none';
        } else {
            confirmBtn.style.display = 'block';
        }
    }
    
    document.getElementById('modalDana').classList.add('active');
}

function closeModal(id){ document.getElementById(id).classList.remove('active'); }

function formatDate(d) {
    if(!d) return '-';
    const date = new Date(d);
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
}

function exportPDF(task, materials) {
    document.getElementById('pdfProjectCode').textContent = task.project_code || '-';
    document.getElementById('pdfCode').textContent = task.project_code || '';
    document.getElementById('pdfTitle').textContent = task.title || '-';
    document.getElementById('pdfCategory').textContent = (task.project_category || 'other').charAt(0).toUpperCase() + (task.project_category || 'other').slice(1);
    document.getElementById('pdfPriority').textContent = task.priority ? task.priority.toUpperCase() : '-';
    document.getElementById('pdfAdmin').textContent = task.admin_name || '-';
    document.getElementById('pdfDesc').textContent = task.description || '-';
    
    document.getElementById('pdfClient').textContent = task.client_name || '-';
    document.getElementById('pdfPIC').textContent = task.client_pic || '-';
    document.getElementById('pdfPICPhone').textContent = task.client_pic_phone || '-';
    document.getElementById('pdfLocation').textContent = task.project_location || '-';
    
    document.getElementById('pdfStartDate').textContent = formatDate(task.start_date);
    document.getElementById('pdfEndDate').textContent = formatDate(task.end_date);
    document.getElementById('pdfDays').textContent = task.project_days ? task.project_days + ' Hari' : '-';
    document.getElementById('pdfDeadline').textContent = formatDate(task.due_date);
    
    document.getElementById('pdfTransport').textContent = (task.transport_mode || '-') + ' (' + (task.transport_cost ? 'Rp ' + parseInt(task.transport_cost).toLocaleString('id-ID') : 'Rp 0') + ')';
    document.getElementById('pdfActualTransport').textContent = task.actual_transport_cost ? 'Rp ' + parseInt(task.actual_transport_cost).toLocaleString('id-ID') : '-';
    
    document.getElementById('pdfHotel').textContent = (task.hotel_name || '-') + ' (' + (task.hotel_cost ? 'Rp ' + parseInt(task.hotel_cost).toLocaleString('id-ID') : 'Rp 0') + ')';
    document.getElementById('pdfActualHotel').textContent = task.actual_hotel_cost ? 'Rp ' + parseInt(task.actual_hotel_cost).toLocaleString('id-ID') : '-';
    
    document.getElementById('pdfMeal').textContent = task.meal_allowance ? 'Rp ' + parseInt(task.meal_allowance).toLocaleString('id-ID') : 'Rp 0';
    document.getElementById('pdfActualMeal').textContent = task.actual_meal_cost ? 'Rp ' + parseInt(task.actual_meal_cost).toLocaleString('id-ID') : '-';
    
    const ef = parseFloat(task.emergency_fund) || 0;
    document.getElementById('pdfEmergencyFund').textContent = ef > 0 ? 'Rp ' + ef.toLocaleString('id-ID') : 'Rp 0';
    
    const matBody = document.getElementById('pdfMaterialBody');
    matBody.innerHTML = '';
    const matSection = document.getElementById('pdfMaterialSection');
    if(materials && materials.length > 0) {
        matSection.style.display = 'block';
        materials.forEach(function(m, i) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>'+(i+1)+'</td><td>'+m.item_name+'</td><td>'+m.item_category+'</td><td>'+m.quantity+'</td><td>'+m.unit+'</td><td>'+(m.supplier||'-')+'</td>';
            matBody.appendChild(tr);
        });
    } else {
        matSection.style.display = 'none';
    }
    
    document.getElementById('pdfBudget').textContent = task.total_budget ? 'Rp ' + parseInt(task.total_budget).toLocaleString('id-ID') : 'Rp 0';
    document.getElementById('pdfStatus').textContent = task.status === 'completed' ? 'SELESAI' : 'DALAM PENGERJAAN';
    document.getElementById('pdfNotes').textContent = task.completion_notes || '-';
    document.getElementById('pdfSpecialNotes').textContent = task.special_notes || '-';

    var element = document.getElementById('printTemplate');
    element.style.display = 'block';
    
    var opt = {
        margin:       0.7,
        filename:     'LPJ_' + (task.project_code || 'Proyek').replace(/[^a-z0-9]/gi, '_') + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        element.style.display = 'none';
    });
}
</script>
</body>
</html>



