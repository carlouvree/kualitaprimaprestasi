<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'tugas';

// Delete task
if(isset($_GET['hapus'])){ 
    $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([(int)$_GET['hapus']]); 
    header("Location: tugas.php?msg=deleted"); exit; 
}

// Finalisasi proyek oleh admin
if(isset($_GET['finalize'])){
    $pdo->prepare("UPDATE tasks SET admin_final_status='final' WHERE id=?")->execute([(int)$_GET['finalize']]);
    header("Location: tugas.php?msg=finalized"); exit;
}

// Generate project code
function generateProjectCode($pdo) {
    $today = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_code LIKE ?");
    $stmt->execute(["PRJ-$today-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return "PRJ-$today-" . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Generate order code
function generateOrderCode($pdo) {
    $today = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE order_code LIKE ?");
    $stmt->execute(["ORD-$today-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return "ORD-$today-" . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
if($_SERVER['REQUEST_METHOD']==='POST'){
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $priority = $_POST['priority'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $assigned_by = $_SESSION['user_id'];
    
    // Existing fields
    $transport_mode = trim($_POST['transport_mode'] ?? '');
    $transport_cost = !empty($_POST['transport_cost']) ? (float)preg_replace('/[^0-9]/', '', $_POST['transport_cost']) : null;
    $hotel_name = trim($_POST['hotel_name'] ?? '');
    $hotel_cost = !empty($_POST['hotel_cost']) ? (float)preg_replace('/[^0-9]/', '', $_POST['hotel_cost']) : null;
    $meal_allowance = !empty($_POST['meal_allowance']) ? (float)preg_replace('/[^0-9]/', '', $_POST['meal_allowance']) : null;
    $project_days = !empty($_POST['project_days']) ? (int)$_POST['project_days'] : null;

    // New fields
    $project_location = trim($_POST['project_location'] ?? '');
    $client_name = trim($_POST['client_name'] ?? '');
    $client_pic = trim($_POST['client_pic'] ?? '');
    $client_pic_phone = trim($_POST['client_pic_phone'] ?? '');
    $project_category = $_POST['project_category'] ?? 'other';
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $special_notes = trim($_POST['special_notes'] ?? '');
    $total_budget = !empty($_POST['total_budget']) ? (float)preg_replace('/[^0-9]/', '', $_POST['total_budget']) : 0;
    $accommodation_limit = !empty($_POST['accommodation_limit']) ? (float)preg_replace('/[^0-9]/', '', $_POST['accommodation_limit']) : 5000000;
    $project_value = !empty($_POST['project_value']) ? (float)preg_replace('/[^0-9]/', '', $_POST['project_value']) : 0;

    // Server-side date validation
    $today = date('Y-m-d');
    if ($start_date && $start_date < $today) {
        header("Location: tugas.php?msg=error_date&detail=past"); exit;
    }
    if ($start_date && $end_date && $end_date < $start_date) {
        header("Location: tugas.php?msg=error_date&detail=end"); exit;
    }
    if ($start_date && $due_date && $due_date < $start_date) {
        header("Location: tugas.php?msg=error_date&detail=deadline"); exit;
    }

    // Check active project limit
    if (empty($_POST['id']) && $assigned_to) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status != 'completed'");
        $check->execute([$assigned_to]);
        if($check->fetchColumn() > 0) {
            header("Location: tugas.php?msg=error_active"); exit;
        }
    }

    // Server-side validation for accommodation limit
    $accommodation_total = ($transport_cost ?? 0) + ($hotel_cost ?? 0) + ($meal_allowance ?? 0);
    if ($accommodation_total > $accommodation_limit) {
        header("Location: tugas.php?msg=error_budget&limit=" . $accommodation_limit); exit;
    }

    // Handle Transfer Proof Upload by Admin
    $dana_photo = '';
    if(isset($_FILES['dana_photo']) && $_FILES['dana_photo']['error']===0){
        $ext = strtolower(pathinfo($_FILES['dana_photo']['name'], PATHINFO_EXTENSION));
        $dana_photo = 'dana_admin_'.time().'.'.$ext;
        if(!is_dir('../uploads/proyek/')) mkdir('../uploads/proyek/', 0777, true);
        move_uploaded_file($_FILES['dana_photo']['tmp_name'], '../uploads/proyek/'.$dana_photo);
    }

    // Determine admin_final_status
    $admin_final = (!empty($title) && $assigned_to && $start_date) ? 'draft' : 'draft';
    if (!empty($_POST['mark_final'])) $admin_final = 'final';

    if(isset($_POST['id']) && !empty($_POST['id'])){
        // UPDATE
        if (empty($dana_photo)) {
            $stmt = $pdo->prepare("SELECT dana_diterima_photo FROM tasks WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $dana_photo = $stmt->fetchColumn();
        }

        $pdo->prepare("UPDATE tasks SET title=?, description=?, assigned_to=?, priority=?, due_date=?, 
            transport_mode=?, transport_cost=?, hotel_name=?, hotel_cost=?, meal_allowance=?, project_days=?,
            project_location=?, client_name=?, client_pic=?, client_pic_phone=?, project_category=?, 
            start_date=?, end_date=?, special_notes=?, total_budget=?, dana_diterima_photo=?, accommodation_limit=?,
            project_value=?, admin_final_status=?
            WHERE id=?")->execute([
            $title, $description, $assigned_to, $priority, $due_date,
            $transport_mode, $transport_cost, $hotel_name, $hotel_cost, $meal_allowance, $project_days,
            $project_location, $client_name, $client_pic, $client_pic_phone, $project_category,
            $start_date, $end_date, $special_notes, $total_budget, $dana_photo, $accommodation_limit,
            $project_value, $admin_final,
            (int)$_POST['id']
        ]);
        $task_id = (int)$_POST['id'];
        
        // Delete old materials then re-insert
        $pdo->prepare("DELETE FROM project_materials WHERE task_id=?")->execute([$task_id]);
    } else {
        // INSERT
        $project_code = generateProjectCode($pdo);
        $order_code = generateOrderCode($pdo);
        $pdo->prepare("INSERT INTO tasks (project_code, order_code, title, description, assigned_to, assigned_by, priority, due_date, 
            transport_mode, transport_cost, hotel_name, hotel_cost, meal_allowance, project_days,
            project_location, client_name, client_pic, client_pic_phone, project_category,
            start_date, end_date, special_notes, total_budget, dana_diterima_photo, accommodation_limit,
            project_value, admin_final_status) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            $project_code, $order_code, $title, $description, $assigned_to, $assigned_by, $priority, $due_date,
            $transport_mode, $transport_cost, $hotel_name, $hotel_cost, $meal_allowance, $project_days,
            $project_location, $client_name, $client_pic, $client_pic_phone, $project_category,
            $start_date, $end_date, $special_notes, $total_budget, $dana_photo, $accommodation_limit,
            $project_value, $admin_final
        ]);
        $task_id = $pdo->lastInsertId();
    }

    // Insert materials
    if(!empty($_POST['mat_name'])) {
        $stmtMat = $pdo->prepare("INSERT INTO project_materials (task_id, item_name, item_category, quantity, unit, unit_price, total_price, supplier, notes) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach($_POST['mat_name'] as $i => $name) {
            $name = trim($name);
            if(empty($name)) continue;
            $cat = $_POST['mat_category'][$i] ?? 'bahan';
            $qty = (float)($_POST['mat_qty'][$i] ?? 1);
            $unit = trim($_POST['mat_unit'][$i] ?? 'pcs');
            $price = (float)preg_replace('/[^0-9]/', '', $_POST['mat_price'][$i] ?? '0');
            $total = $qty * $price;
            $supplier = trim($_POST['mat_supplier'][$i] ?? '');
            $notes = trim($_POST['mat_notes'][$i] ?? '');
            $stmtMat->execute([$task_id, $name, $cat, $qty, $unit, $price, $total, $supplier, $notes]);
        }
    }

    header("Location: tugas.php?msg=success"); exit;
}

$tasks = $pdo->query("SELECT t.*, u.full_name as staff_name, a.full_name as admin_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users a ON t.assigned_by = a.id ORDER BY FIELD(t.status,'pending','in_progress','completed'), FIELD(t.priority,'urgent','high','medium','low'), t.created_at DESC")->fetchAll();
// Hanya staff yang TIDAK punya task aktif (untuk penugasan baru)
$staffListFree = $pdo->query("SELECT id, full_name FROM users WHERE role='staff' AND id NOT IN (SELECT DISTINCT assigned_to FROM tasks WHERE status != 'completed' AND assigned_to IS NOT NULL) ORDER BY full_name")->fetchAll();
// Semua staff (untuk edit task yg sudah punya assigned_to)
$staffListAll = $pdo->query("SELECT id, full_name FROM users WHERE role='staff' ORDER BY full_name")->fetchAll();
// Kategori pengadaan dari DB
try {
    $procCats = $pdo->query("SELECT * FROM procurement_categories ORDER BY id ASC")->fetchAll();
} catch(Exception $e) {
    $procCats = [['id'=>'alat','name'=>'Alat'],['id'=>'bahan','name'=>'Bahan'],['id'=>'consumable','name'=>'Consumable'],['id'=>'sewa','name'=>'Sewa'],['id'=>'jasa','name'=>'Jasa'],['id'=>'lainnya','name'=>'Lainnya']];
}
// Produk dari katalog (untuk relasi)
try {
    $catalogProducts = $pdo->query("SELECT p.id, p.name, p.sku, c.name as category_name, c.type as category_type FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name ASC")->fetchAll();
} catch(Exception $e) {
    $catalogProducts = [];
}

// Preload materials per task
$allMaterials = [];
$matStmt = $pdo->query("SELECT * FROM project_materials ORDER BY id ASC");
if($matStmt) {
    foreach($matStmt->fetchAll() as $m) {
        $allMaterials[$m['task_id']][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penugasan Proyek — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css?v=<?= time() ?>">
    <style>
        .detail-row { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .detail-item { background: #f8fafc; padding: 10px 15px; border-radius: 8px; flex: 1; min-width: 150px; font-size: 13px; border: 1px solid #e2e8f0; }
        .detail-item i { color: var(--accent-color); margin-right: 5px; }
        
        /* Tab styles */
        .form-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 20px; overflow-x: auto; }
        .form-tab { 
            padding: 10px 16px; font-size: 12px; font-weight: 700; 
            font-family: 'Montserrat', sans-serif; cursor: pointer;
            color: var(--text-light);
            transition: var(--transition); white-space: nowrap; background: none; border: none;
            border-bottom: 3px solid transparent;
        }
        .form-tab:hover { color: var(--accent-color); }
        .form-tab.active { color: var(--accent-color); border-bottom-color: var(--accent-color); }
        .form-tab i { margin-right: 5px; }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
        
        /* Material table */
        .mat-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        .mat-table th { background: var(--bg-gray); padding: 8px 6px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-light); }
        .mat-table td { padding: 4px 3px; vertical-align: top; }
        .mat-table input, .mat-table select { width: 100%; padding: 7px 6px; border: 1.5px solid var(--border); border-radius: 6px; font-size: 12px; font-family: 'Open Sans', sans-serif; }
        .mat-table input:focus, .mat-table select:focus { outline: none; border-color: var(--accent-color); }
        .btn-remove-row { background: rgba(239,68,68,0.1); color: var(--danger); border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-size: 13px; transition: var(--transition); }
        .btn-remove-row:hover { background: var(--danger); color: #fff; }
        
        /* Project code badge */
        .project-code { 
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, rgba(75,56,170,0.08), rgba(99,102,241,0.08));
            padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;
            font-family: 'Montserrat', sans-serif; color: var(--accent-color); letter-spacing: 0.5px;
        }
        .order-code {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(245,158,11,0.08); padding: 3px 10px; border-radius: 6px;
            font-size: 10px; font-weight: 700; font-family: 'Montserrat', sans-serif;
            color: #d97706; letter-spacing: 0.5px;
        }
        
        /* Category badge */
        .badge-engineering { background: rgba(59,130,246,0.1); color: #2563eb; }
        .badge-procurement { background: rgba(245,158,11,0.1); color: #d97706; }
        .badge-construction { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-other { background: rgba(100,116,139,0.1); color: #64748b; }
        
        /* Material summary in table */
        .mat-summary { font-size: 12px; margin-top: 8px; padding: 8px 12px; background: rgba(245,158,11,0.05); border-radius: 6px; border: 1px solid rgba(245,158,11,0.15); }
        .mat-summary i { color: var(--warning); }
        
        /* Budget display */
        .budget-display {
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.03));
            border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 15px;
            text-align: center; margin-top: 15px;
        }
        .budget-display .label { font-size: 12px; color: var(--text-light); font-weight: 600; }
        .budget-display .amount { font-size: 22px; font-weight: 800; color: var(--success); font-family: 'Montserrat', sans-serif; }

        /* Search & Filter Toolbar */
        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .toolbar-search { flex: 1; min-width: 200px; position: relative; }
        .toolbar-search input { width: 100%; padding: 10px 15px 10px 40px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Open Sans', sans-serif; transition: var(--transition); }
        .toolbar-search input:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 3px rgba(75,56,170,0.1); }
        .toolbar-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .toolbar select { padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Open Sans', sans-serif; background: white; cursor: pointer; min-width: 140px; }
        .toolbar select:focus { outline: none; border-color: var(--accent-color); }

        /* Badge for admin final status */
        .badge-draft { background: rgba(245,158,11,0.15); color: #92400e; font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 700; }
        .badge-final { background: rgba(16,185,129,0.15); color: #065f46; font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 700; }

        /* Project value display */
        .project-value-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.05));
            padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;
            font-family: 'Montserrat', sans-serif; color: #059669; margin-top: 4px;
        }
    </style>
</head>
<body>
<?php require_once '../includes/admin_sidebar.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Penugasan Proyek</h1><p>Berikan proyek harian ke staff lapangan</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Operasi berhasil!</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'finalized'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Proyek berhasil di-finalisasi oleh admin!</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'error_active'): ?>
            <div class="alert alert-danger" style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:10px;margin-bottom:20px;border-left:4px solid #ef4444;"><i class="fa-solid fa-circle-exclamation"></i> Gagal: Staff tersebut masih memiliki proyek aktif yang belum selesai.</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'error_budget'): ?>
            <div class="alert alert-danger" style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:10px;margin-bottom:20px;border-left:4px solid #ef4444;"><i class="fa-solid fa-circle-exclamation"></i> Gagal: Total anggaran akomodasi melebihi batas (Rp <?= number_format($_GET['limit']??0, 0, ',', '.') ?>)!</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'error_date'): ?>
            <div class="alert alert-danger" style="background:#fee2e2;color:#991b1b;padding:15px;border-radius:10px;margin-bottom:20px;border-left:4px solid #ef4444;"><i class="fa-solid fa-circle-exclamation"></i> Gagal: <?= ($_GET['detail']??'') === 'past' ? 'Tanggal mulai proyek baru tidak boleh sebelum hari ini!' : (($_GET['detail']??'') === 'deadline' ? 'Deadline tidak boleh sebelum tanggal mulai!' : 'Tanggal selesai tidak boleh sebelum tanggal mulai!') ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:25px;">
            <div class="card-header">
                <h3><i class="fa-solid fa-list-check"></i> Daftar Proyek</h3>
                <div style="display:flex; gap:10px;">
                    <a href="laporan_pdf.php?type=proyek" target="_blank" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export PDF</a>
                    <button class="btn btn-primary" onclick="openModal('modalTugas')"><i class="fa-solid fa-plus"></i> Buat Proyek</button>
                </div>
            </div>

            <!-- Search & Filter Toolbar -->
            <div style="padding: 15px 20px; border-bottom: 1px solid var(--border);">
                <div class="toolbar">
                    <div class="toolbar-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Cari nama proyek, kode, klien, staff..." oninput="filterTable()">
                    </div>
                    <select id="sortSelect" onchange="sortTable()">
                        <option value="default">Urutkan: Default</option>
                        <option value="value_desc">Nilai Proyek Termahal</option>
                        <option value="value_asc">Nilai Proyek Termurah</option>
                        <option value="newest">Terbaru</option>
                        <option value="oldest">Terlama</option>
                        <option value="priority">Prioritas Tertinggi</option>
                    </select>
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="all">Status: Semua</option>
                        <option value="pending">Menunggu</option>
                        <option value="in_progress">Dikerjakan</option>
                        <option value="completed_draft">Selesai (Belum Final)</option>
                        <option value="completed_final">Selesai (Final)</option>
                    </select>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table" id="projectTable">
                    <thead><tr><th>Kode & Detail Proyek</th><th>Klien & Lokasi</th><th>Jadwal</th><th>Staff</th><th>Status</th><th>Bukti & LPJ</th><th>Aksi</th></tr></thead>
                    <tbody id="projectTableBody">
                    <?php if(empty($tasks)): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada proyek.</p></div></td></tr>
                    <?php else: foreach($tasks as $t): 
                        $mats = $allMaterials[$t['id']] ?? [];
                        $matTotal = array_sum(array_column($mats, 'total_price'));
                        $adminFinal = $t['admin_final_status'] ?? 'draft';
                        $isOverdue = (!empty($t['due_date']) && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'completed');
                    ?>
                    <tr style="<?= $t['status']==='completed'?'opacity:0.8;':''; ?>" 
                        data-search="<?= htmlspecialchars(strtolower(($t['project_code']??'').' '.($t['order_code']??'').' '.$t['title'].' '.($t['client_name']??'').' '.($t['staff_name']??''))) ?>"
                        data-status="<?= $t['status'] === 'completed' ? 'completed_'.$adminFinal : $t['status'] ?>"
                        data-value="<?= (float)($t['project_value'] ?? 0) ?>"
                        data-created="<?= $t['created_at'] ?>"
                        data-priority="<?= $t['priority'] ?>">
                        <td>
                            <?php if(!empty($t['project_code'])): ?><div class="project-code"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($t['project_code']) ?></div><?php endif; ?>
                            <?php if(!empty($t['order_code'])): ?><div class="order-code" style="margin-top:3px;"><i class="fa-solid fa-receipt"></i> <?= htmlspecialchars($t['order_code']) ?></div><?php endif; ?>
                            <strong style="display:block;color:var(--text-dark);font-family:'Montserrat',sans-serif;font-size:15px;margin-top:4px;"><?= htmlspecialchars($t['title']) ?></strong>
                            <span class="badge badge-<?= $t['priority'] ?>" style="margin-top:4px;font-size:11px;"><?= strtoupper($t['priority']) ?></span>
                            <?php if(!empty($t['project_category']) && $t['project_category'] !== 'other'): ?>
                                <span class="badge badge-<?= $t['project_category'] ?>" style="font-size:11px;"><?= ucfirst($t['project_category']) ?></span>
                            <?php endif; ?>
                            <?php if($t['description']): ?><br><small style="color:var(--text-light);"><?= htmlspecialchars(mb_substr($t['description'],0,80)) ?>...</small><?php endif; ?>
                            
                            <?php if(($t['project_value'] ?? 0) > 0): ?>
                            <div class="project-value-badge"><i class="fa-solid fa-coins"></i> Rp <?= number_format($t['project_value'], 0, ',', '.') ?></div>
                            <?php endif; ?>
                            
                            <div class="detail-row">
                                <?php if($t['transport_mode']): ?>
                                <div class="detail-item"><i class="fa-solid fa-car"></i> <b>Transport:</b> <?= htmlspecialchars($t['transport_mode']) ?> (Rp <?= number_format($t['transport_cost']??0, 0, ',', '.') ?>)</div>
                                <?php endif; ?>
                                <?php if($t['hotel_name']): ?>
                                <div class="detail-item"><i class="fa-solid fa-hotel"></i> <b>Hotel:</b> <?= htmlspecialchars($t['hotel_name']) ?> (Rp <?= number_format($t['hotel_cost']??0, 0, ',', '.') ?>)</div>
                                <?php endif; ?>
                                <?php if($t['meal_allowance']): ?>
                                <div class="detail-item"><i class="fa-solid fa-utensils"></i> <b>Uang Makan:</b> Rp <?= number_format($t['meal_allowance'], 0, ',', '.') ?></div>
                                <?php endif; ?>
                                <?php if($t['project_days']): ?>
                                <div class="detail-item"><i class="fa-solid fa-calendar-day"></i> <b>Waktu:</b> <?= $t['project_days'] ?> Hari</div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(count($mats) > 0): ?>
                            <div class="mat-summary">
                                <i class="fa-solid fa-boxes-stacked"></i> <b><?= count($mats) ?> item material</b> — Total: Rp <?= number_format($matTotal, 0, ',', '.') ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($t['client_name'])): ?>
                                <strong style="color:var(--text-dark);font-size:13px;"><i class="fa-solid fa-building" style="color:var(--accent-color);margin-right:3px;"></i> <?= htmlspecialchars($t['client_name']) ?></strong><br>
                            <?php endif; ?>
                            <?php if(!empty($t['client_pic'])): ?>
                                <small style="color:var(--text-light);"><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($t['client_pic']) ?></small><br>
                            <?php endif; ?>
                            <?php if(!empty($t['project_location'])): ?>
                                <small style="color:var(--text-light);"><i class="fa-solid fa-location-dot" style="color:var(--danger);"></i> <?= htmlspecialchars(mb_substr($t['project_location'],0,40)) ?></small>
                            <?php else: ?>
                                <span style="color:#cbd5e1;font-size:12px;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;font-size:12px;">
                            <?php if(!empty($t['start_date'])): ?>
                                <div><i class="fa-solid fa-play" style="color:var(--success);font-size:10px;"></i> <?= date('d M Y', strtotime($t['start_date'])) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($t['end_date'])): ?>
                                <div><i class="fa-solid fa-flag-checkered" style="color:var(--danger);font-size:10px;"></i> <?= date('d M Y', strtotime($t['end_date'])) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($t['due_date'])): ?>
                                <div style="margin-top:3px;<?= $isOverdue ? 'color:#ef4444;font-weight:700;' : '' ?>"><i class="fa-regular fa-clock" style="font-size:10px;"></i> DL: <?= date('d M Y',strtotime($t['due_date'])) ?><?= $isOverdue ? ' ⚠️' : '' ?></div>
                            <?php endif; ?>
                            <?php if(empty($t['start_date']) && empty($t['end_date']) && empty($t['due_date'])): ?>
                                <span style="color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $t['staff_name'] ? htmlspecialchars($t['staff_name']) : '<span style="color:var(--text-light);">-</span>' ?></strong></td>
                        <td>
                            <span class="badge badge-<?= $t['status'] ?>"><?= $t['status']==='pending'?'Menunggu':($t['status']==='in_progress'?'Dikerjakan':'✓ Selesai') ?></span>
                            <?php 
                            $isIncomplete = empty($t['client_name']) || empty($t['project_location']) || empty($t['start_date']) || empty($t['end_date']) || empty($t['transport_mode']);
                            if($isIncomplete && $t['status'] !== 'completed'): ?>
                                <br><span class="badge" style="background:#fee2e2; color:#991b1b; margin-top:4px; font-size:10px; display:inline-block; padding: 3px 6px; border-radius: 4px;"><i class="fa-solid fa-triangle-exclamation"></i> Data Blm Lengkap</span>
                            <?php endif; ?>
                            <?php if($t['status'] === 'completed'): ?>
                                <br><span class="badge-<?= $adminFinal ?>" style="margin-top:4px;display:inline-block;"><?= $adminFinal === 'final' ? '✓ Final' : '⏳ Belum Final' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($t['photo_evidence'])): ?>
                                <img src="../uploads/proyek/<?= htmlspecialchars($t['photo_evidence']) ?>" class="img-thumb" style="cursor:pointer;margin-bottom:5px;" onclick="window.open(this.src)" title="<?= htmlspecialchars($t['completion_notes']??'') ?>"><br>
                            <?php else: ?><span style="color:var(--text-light);font-size:12px;">Foto (-)</span><br><?php endif; ?>
                            
                            <?php if(!empty($t['lpj_file'])): ?>
                                <a href="../uploads/lpj/<?= htmlspecialchars($t['lpj_file']) ?>" target="_blank" class="btn btn-sm" style="background:#e0f2fe; color:#0284c7; font-size:11px; padding:6px 12px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:5px; border: 1px solid rgba(2, 132, 199, 0.2); transition: all 0.3s ease;" onmouseover="this.style.background='#0284c7'; this.style.color='#fff';" onmouseout="this.style.background='#e0f2fe'; this.style.color='#0284c7';"><i class="fa-solid fa-file-pdf"></i> LPJ</a>
                            <?php else: ?><span style="color:var(--text-light);font-size:12px;">LPJ (-)</span><?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;flex-direction:column;">
                                <a href="tugas_detail.php?id=<?= $t['id'] ?>" class="btn btn-icon" style="background:rgba(75,56,170,0.1);color:var(--accent-color);" title="Lihat Detail"><i class="fa-solid fa-eye"></i></a>
                                <?php if($t['status']!=='completed'): ?>
                                <button type="button" class="btn btn-icon edit" onclick='editTask(<?= json_encode($t) ?>, <?= json_encode($mats) ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <?php endif; ?>
                                <?php if($t['status'] === 'completed' && $adminFinal === 'draft'): ?>
                                <button type="button" class="btn btn-icon" style="background:rgba(16,185,129,0.1);color:#059669;" onclick="showConfirm('Finalisasi proyek ini? Data akan dikunci.', () => location='tugas.php?finalize=<?= $t['id'] ?>')" title="Finalisasi"><i class="fa-solid fa-check-double"></i></button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-icon delete" onclick="showConfirm('Hapus proyek ini secara permanen?', () => location='tugas.php?hapus=<?= $t['id'] ?>')" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
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

<!-- Modal -->
<div class="modal-overlay" id="modalTugas" style="overflow-y:auto; align-items:flex-start; padding: 20px 0;">
    <div class="modal-box" style="margin: auto; width: 100%; max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-list-check" style="color:var(--accent-color);"></i> <span id="modalTugasTitle">Buat Proyek Baru</span></h3>
            <button class="modal-close" onclick="closeModal('modalTugas')" type="button">&times;</button>
        </div>
        <div class="modal-body" style="padding:15px 25px 25px;">
            <form action="" method="POST" id="formTugas" enctype="multipart/form-data">
                <input type="hidden" name="id" id="task_id">
                
                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="form-tab active" onclick="switchTab(this,'tabBasic')"><i class="fa-solid fa-info-circle"></i> Dasar</button>
                    <button type="button" class="form-tab" onclick="switchTab(this,'tabClient')"><i class="fa-solid fa-building"></i> Klien & Lokasi</button>
                    <button type="button" class="form-tab" onclick="switchTab(this,'tabSchedule')"><i class="fa-solid fa-calendar-days"></i> Jadwal</button>
                    <button type="button" class="form-tab" onclick="switchTab(this,'tabAdmin')"><i class="fa-solid fa-wallet"></i> Akomodasi</button>
                    <button type="button" class="form-tab" onclick="switchTab(this,'tabMaterial')"><i class="fa-solid fa-boxes-stacked"></i> Pengadaan Barang</button>
                    <button type="button" class="form-tab" onclick="switchTab(this,'tabNotes')"><i class="fa-solid fa-note-sticky"></i> Catatan</button>
                </div>
                
                <!-- Tab 1: Basic Info -->
                <div class="tab-content active" id="tabBasic">
                    <div class="form-group"><label class="form-label">Nama Proyek <span style="color:var(--danger);">*</span></label><input type="text" name="title" id="task_title" class="form-control" required placeholder="Contoh: Inspeksi Tower Crane Site A"></div>
                    <div class="form-group"><label class="form-label">Deskripsi Proyek</label><textarea name="description" id="task_desc" class="form-control" rows="3" placeholder="Detail pekerjaan yang harus dikerjakan staff..."></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Ditugaskan ke <span style="color:var(--danger);">*</span></label>
                            <select name="assigned_to" id="task_staff" class="form-control" required>
                                <option value="">-- Pilih Staff --</option>
                                <?php foreach($staffListFree as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option><?php endforeach; ?>
                            </select>
                            <p class="form-hint" style="color:var(--success);"><i class="fa-solid fa-circle-check"></i> Hanya menampilkan staff yang belum memiliki proyek aktif.</p>
                        </div>
                        <div class="form-group"><label class="form-label">Prioritas</label>
                            <select name="priority" id="task_priority" class="form-control">
                                <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Kategori Proyek</label>
                            <select name="project_category" id="task_category" class="form-control">
                                <option value="other">Lainnya</option>
                                <option value="engineering">Engineering</option>
                                <option value="procurement">Procurement</option>
                                <option value="construction">Construction</option>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label"><i class="fa-solid fa-coins" style="color:var(--success);"></i> Nilai Proyek (Kontrak)</label>
                            <input type="text" name="project_value" id="task_project_value" class="form-control currency-input" placeholder="Rp. 0">
                            <p class="form-hint"><i class="fa-solid fa-circle-info"></i> Nilai kontrak/kesepakatan proyek keseluruhan (terpisah dari akomodasi).</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tab 2: Client & Location -->
                <div class="tab-content" id="tabClient">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-building" style="color:var(--accent-color);"></i> Nama PT / Perusahaan / Instansi Klien</label>
                        <input type="text" name="client_name" id="task_client_name" class="form-control" placeholder="Contoh: PT. Pertamina Hulu Energi">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-user-tie" style="color:var(--accent-color);"></i> Nama PIC (Contact Person)</label>
                            <input type="text" name="client_pic" id="task_client_pic" class="form-control" placeholder="Contoh: Bapak Ahmad Sudirman">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-phone" style="color:var(--accent-color);"></i> Telepon PIC</label>
                            <input type="text" name="client_pic_phone" id="task_client_pic_phone" class="form-control" placeholder="Contoh: 0812-3456-7890">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-location-dot" style="color:var(--danger);"></i> Alamat / Lokasi Proyek</label>
                        <textarea name="project_location" id="task_project_location" class="form-control" rows="2" placeholder="Contoh: Jl. Raya Balikpapan KM 12, Kalimantan Timur — Site Area B3"></textarea>
                    </div>
                </div>
                
                <!-- Tab 3: Schedule -->
                <div class="tab-content" id="tabSchedule">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-play" style="color:var(--success);"></i> Tanggal Mulai Kerja</label>
                            <input type="date" name="start_date" id="task_start_date" class="form-control" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-flag-checkered" style="color:var(--danger);"></i> Tanggal Selesai Kerja</label>
                            <input type="date" name="end_date" id="task_end_date" class="form-control" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Durasi Pengerjaan (Hari)</label><input type="number" name="project_days" id="task_project_days" class="form-control" placeholder="0"></div>
                        <div class="form-group"><label class="form-label">Deadline</label><input type="date" name="due_date" id="task_due" class="form-control" min="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div id="dateValidationWarn" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-top:10px;font-size:13px;color:#991b1b;">
                        <i class="fa-solid fa-circle-exclamation"></i> <strong id="dateWarnMsg">Tanggal tidak valid!</strong>
                    </div>
                    <p class="form-hint"><i class="fa-solid fa-circle-info"></i> Durasi dihitung otomatis dari tanggal mulai & selesai jika diisi keduanya.</p>
                </div>
                
                <!-- Tab 4: Accommodation -->
                <div class="tab-content" id="tabAdmin">
                    <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);border-radius:8px;padding:15px;margin-bottom:15px;display:flex;gap:15px;align-items:center;">
                        <div style="font-size:24px;color:var(--accent-color);"><i class="fa-solid fa-shield-halved"></i></div>
                        <div style="flex:1;">
                            <label class="form-label" style="margin-bottom:5px;font-weight:800;color:var(--text-dark);">Tentukan Batas Limit Akomodasi</label>
                            <input type="text" name="accommodation_limit" id="task_accommodation_limit" class="form-control currency-input" value="Rp. 5.000.000" style="font-weight:700;color:var(--accent-color);font-size:16px;" oninput="calcBudget()">
                            <p class="form-hint" style="margin-top:5px;line-height:1.4;">Custom limit per-proyek (Standard: Rp 5jt). Bisa ditingkatkan sesuai kebutuhan proyek besar.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label"><i class="fa-solid fa-car" style="color:var(--accent-color);"></i> Transportasi (Kendaraan)</label>
                            <select name="transport_mode" id="task_transport_mode" class="form-control">
                                <option value="">-- Pilih Kendaraan --</option>
                                <option value="Grab">Grab</option>
                                <option value="Gojek">Gojek</option>
                                <option value="Kereta">Kereta</option>
                                <option value="Pesawat">Pesawat</option>
                                <option value="Angkutan Umum">Angkutan Umum</option>
                                <option value="Kapal">Kapal</option>
                                <option value="Mobil Pribadi">Mobil Pribadi</option>
                                <option value="Motor Pribadi">Motor Pribadi</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Biaya Transport</label><input type="text" name="transport_cost" id="task_transport_cost" class="form-control currency-input" placeholder="Rp. 0"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label"><i class="fa-solid fa-hotel" style="color:var(--accent-color);"></i> Hotel / Penginapan</label><input type="text" name="hotel_name" id="task_hotel_name" class="form-control" placeholder="Cth: Hotel Santika"></div>
                        <div class="form-group"><label class="form-label">Biaya Penginapan</label><input type="text" name="hotel_cost" id="task_hotel_cost" class="form-control currency-input" placeholder="Rp. 0"></div>
                    </div>
                    <div class="form-group"><label class="form-label"><i class="fa-solid fa-utensils" style="color:var(--accent-color);"></i> Uang Makan Total</label><input type="text" name="meal_allowance" id="task_meal_allowance" class="form-control currency-input" placeholder="Rp. 0"></div>
                    
                    <div class="form-group" style="border-top:1px dashed var(--border); padding-top:15px; margin-top:15px;">
                        <label class="form-label"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--success);"></i> Unggah Bukti Transfer Akomodasi</label>
                        <input type="file" name="dana_photo" class="form-control">
                        <p class="form-hint"><i class="fa-solid fa-circle-info"></i> Lampirkan file struk/bukti transfer jika dana sudah dikirim ke staff.</p>
                    </div>

                    
                    <div id="budgetLimitWarn" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-top:10px;font-size:13px;color:#991b1b;">
                        <i class="fa-solid fa-circle-exclamation"></i> <strong>Batas limit terlampaui!</strong> Total akomodasi tidak boleh melebihi limit yang ditentukan.
                    </div>
                    <div class="budget-display">
                        <div class="label">ESTIMASI TOTAL BUDGET AKOMODASI</div>
                        <div class="amount" id="budgetDisplay">Rp 0</div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Sisa limit: <span id="budgetRemain" style="font-weight:700;">Rp 5.000.000</span></div>
                        <input type="hidden" name="total_budget" id="task_total_budget" value="0">
                    </div>
                </div>
                
                <!-- Tab 5: Materials / Procurement -->
                <div class="tab-content" id="tabMaterial">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <h4 style="font-family:'Montserrat',sans-serif;font-size:14px;color:var(--text-dark);"><i class="fa-solid fa-boxes-stacked" style="color:var(--warning);"></i> Daftar Barang / Alat / Bahan</h4>
                        <button type="button" class="btn btn-sm btn-accent" onclick="addMaterialRow()"><i class="fa-solid fa-plus"></i> Tambah Item</button>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="mat-table" id="materialTable">
                        <thead>
                            <tr>
                                <th style="width:22%;">Nama Barang</th>
                                <th style="width:12%;">Kategori</th>
                                <th style="width:8%;">Qty</th>
                                <th style="width:8%;">Satuan</th>
                                <th style="width:14%;">Harga Satuan</th>
                                <th style="width:14%;">Subtotal</th>
                                <th style="width:14%;">Supplier</th>
                                <th style="width:5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="materialBody">
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--bg-gray);">
                                <td colspan="5" style="text-align:right;padding:10px;font-weight:700;font-family:'Montserrat',sans-serif;font-size:13px;">Total Material:</td>
                                <td style="padding:10px;font-weight:700;color:var(--success);font-family:'Montserrat',sans-serif;" id="materialTotalDisplay">Rp 0</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <p class="form-hint" style="margin-top:10px;"><i class="fa-solid fa-circle-info"></i> Subtotal dihitung otomatis dari Qty × Harga Satuan. Semua biaya akan dijumlahkan ke Total Budget.</p>
                </div>

                <!-- Tab 6: Special Notes -->
                <div class="tab-content" id="tabNotes">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-note-sticky" style="color:var(--warning);"></i> Catatan / Instruksi Khusus untuk Staff</label>
                        <textarea name="special_notes" id="task_special_notes" class="form-control" rows="5" placeholder="Catatan penting, instruksi khusus, hal yang harus diperhatikan di lapangan, dsb..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;margin-top:15px;"><i class="fa-solid fa-save"></i> Simpan Proyek</button>
            </form>
        </div>
    </div>
</div>

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
// Format Rupiah function
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
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('currency-input')) {
        let val = e.target.value.replace(/Rp\.\s/g, '');
        e.target.value = formatRupiah(val, 'Rp. ');
        calcBudget();
    }
});

// Tab switching
function switchTab(btn, tabId) {
    document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

function openModal(id){ 
    document.getElementById(id).classList.add('active'); 
}
function closeModal(id){ 
    document.getElementById(id).classList.remove('active'); 
    if(id === 'modalTugas') {
        document.getElementById('formTugas').reset(); 
        document.getElementById('task_id').value=''; 
        document.getElementById('modalTugasTitle').textContent='Buat Proyek Baru';
        document.getElementById('materialBody').innerHTML = '';
        // Reset tabs
        document.querySelectorAll('.form-tab').forEach((t,i) => t.classList.toggle('active', i===0));
        document.querySelectorAll('.tab-content').forEach((t,i) => t.classList.toggle('active', i===0));
        calcBudget();
        // Reset staff dropdown to free list
        const staffSel = document.getElementById('task_staff');
        const freeStaff = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'name'=>$s['full_name']], $staffListFree)) ?>;
        staffSel.innerHTML = '<option value="">-- Pilih Staff --</option>' + freeStaff.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    }
}

function editTask(t, materials) {
    // Saat edit: tampilkan dropdown dengan semua staff (termasuk yang sudah kerja)
    const staffSel = document.getElementById('task_staff');
    const allStaff = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'name'=>$s['full_name']], $staffListAll)) ?>;
    staffSel.innerHTML = '<option value="">-- Pilih Staff --</option>' + allStaff.map(s => `<option value="${s.id}" ${s.id==t.assigned_to?'selected':''}>${s.name}</option>`).join('');
    
    document.getElementById('task_id').value=t.id;
    document.getElementById('task_title').value=t.title;
    document.getElementById('task_desc').value=t.description||'';
    document.getElementById('task_priority').value=t.priority;
    
    document.getElementById('task_due').value=t.due_date||'';
    
    document.getElementById('task_transport_mode').value=t.transport_mode||'';
    document.getElementById('task_transport_cost').value=t.transport_cost ? formatRupiah(t.transport_cost, 'Rp. ') : '';
    document.getElementById('task_hotel_name').value=t.hotel_name||'';
    document.getElementById('task_hotel_cost').value=t.hotel_cost ? formatRupiah(t.hotel_cost, 'Rp. ') : '';
    document.getElementById('task_meal_allowance').value=t.meal_allowance ? formatRupiah(t.meal_allowance, 'Rp. ') : '';
    document.getElementById('task_project_days').value=t.project_days||'';

    // New fields
    document.getElementById('task_project_location').value=t.project_location||'';
    document.getElementById('task_client_name').value=t.client_name||'';
    document.getElementById('task_client_pic').value=t.client_pic||'';
    document.getElementById('task_client_pic_phone').value=t.client_pic_phone||'';
    document.getElementById('task_category').value=t.project_category||'other';
    
    document.getElementById('task_start_date').value=t.start_date||'';
    
    document.getElementById('task_end_date').value=t.end_date||'';
    
    document.getElementById('task_special_notes').value=t.special_notes||'';
    document.getElementById('task_accommodation_limit').value = t.accommodation_limit ? formatRupiah(t.accommodation_limit, 'Rp. ') : 'Rp. 5.000.000';
    document.getElementById('task_project_value').value = t.project_value ? formatRupiah(t.project_value, 'Rp. ') : '';

    // Load materials
    document.getElementById('materialBody').innerHTML = '';
    if(materials && materials.length > 0) {
        materials.forEach(function(m) {
            addMaterialRow(m);
        });
    }

    document.getElementById('modalTugasTitle').textContent='Edit Proyek';
    calcBudget();
    openModal('modalTugas');
}

// Material rows
let matRowCount = 0;
function addMaterialRow(data) {
    matRowCount++;
    const tbody = document.getElementById('materialBody');
    const tr = document.createElement('tr');
    // Build category options from PHP
    const procCatOptions = <?= json_encode(array_map(fn($c) => ['id' => strtolower($c['name']), 'name' => $c['name']], $procCats)) ?>;
    const catOpts = procCatOptions.map(c => `<option value="${c.id}" ${data && data.item_category===c.id?'selected':''}>${c.name}</option>`).join('');
    tr.innerHTML = `
        <td><input type="text" name="mat_name[]" value="${data ? escHtml(data.item_name) : ''}" placeholder="Nama item"></td>
        <td><select name="mat_category[]">${catOpts}</select></td>
        <td><input type="number" name="mat_qty[]" value="${data ? data.quantity : '1'}" min="0" step="0.01" onchange="calcMatRow(this)" onkeyup="calcMatRow(this)"></td>
        <td><input type="text" name="mat_unit[]" value="${data ? escHtml(data.unit) : 'pcs'}" placeholder="pcs"></td>
        <td><input type="text" name="mat_price[]" value="${data ? formatRupiah(data.unit_price, 'Rp. ') : ''}" class="form-control currency-input" onkeyup="calcMatRow(this)"></td>
        <td><span class="mat-subtotal" style="font-weight:700;font-size:12px;color:var(--success);">Rp ${data ? parseInt(data.total_price).toLocaleString('id-ID') : '0'}</span></td>
        <td><input type="text" name="mat_supplier[]" value="${data ? escHtml(data.supplier||'') : ''}" placeholder="Supplier"></td>
        <td><input type="hidden" name="mat_notes[]" value="${data ? escHtml(data.notes||'') : ''}"><button type="button" class="btn-remove-row" onclick="this.closest('tr').remove();calcBudget();"><i class="fa-solid fa-xmark"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function escHtml(str) {
    if(!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function calcMatRow(input) {
    const tr = input.closest('tr');
    const qty = parseFloat(tr.querySelector('[name="mat_qty[]"]').value) || 0;
    let priceVal = tr.querySelector('[name="mat_price[]"]').value.replace(/[^0-9]/g, '');
    const price = parseFloat(priceVal) || 0;
    const subtotal = qty * price;
    tr.querySelector('.mat-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    calcBudget();
}

let AKOMODASI_LIMIT = 5000000;

function calcBudget() {
    let transportVal = document.getElementById('task_transport_cost').value.replace(/[^0-9]/g, '');
    let transport = parseFloat(transportVal) || 0;
    
    let hotelVal = document.getElementById('task_hotel_cost').value.replace(/[^0-9]/g, '');
    let hotel = parseFloat(hotelVal) || 0;
    
    let mealVal = document.getElementById('task_meal_allowance').value.replace(/[^0-9]/g, '');
    let meal = parseFloat(mealVal) || 0;

    let limitVal = document.getElementById('task_accommodation_limit').value.replace(/[^0-9]/g, '');
    AKOMODASI_LIMIT = parseFloat(limitVal) || 5000000;

    
    // Sum materials
    let matTotal = 0;
    document.querySelectorAll('#materialBody tr').forEach(tr => {
        const qty = parseFloat(tr.querySelector('[name="mat_qty[]"]')?.value) || 0;
        let pVal = tr.querySelector('[name="mat_price[]"]')?.value.replace(/[^0-9]/g, '') || '0';
        const price = parseFloat(pVal) || 0;
        matTotal += qty * price;
    });
    
    let akomodasi = transport + hotel + meal;
    let total = akomodasi + matTotal;
    
    // Check accommodation limit
    const warn = document.getElementById('budgetLimitWarn');
    const submitBtn = document.querySelector('#formTugas button[type="submit"]');
    const remain = AKOMODASI_LIMIT - akomodasi;
    const remainEl = document.getElementById('budgetRemain');
    
    if (warn) {
        if (akomodasi > AKOMODASI_LIMIT) {
            warn.style.display = 'block';
            if(submitBtn) submitBtn.disabled = true;
            if(remainEl) { remainEl.textContent = '- Rp ' + Math.abs(remain).toLocaleString('id-ID'); remainEl.style.color = '#ef4444'; }
        } else {
            warn.style.display = 'none';
            if(submitBtn) submitBtn.disabled = false;
            if(remainEl) { remainEl.textContent = 'Rp ' + remain.toLocaleString('id-ID'); remainEl.style.color = '#10b981'; }
        }
    }
    
    document.getElementById('budgetDisplay').textContent = 'Rp ' + akomodasi.toLocaleString('id-ID');
    document.getElementById('task_total_budget').value = total;
    document.getElementById('materialTotalDisplay').textContent = 'Rp ' + matTotal.toLocaleString('id-ID');
}

// Date validation (client-side)
function validateDates() {
    const startDate = document.getElementById('task_start_date').value;
    const endDate = document.getElementById('task_end_date').value;
    const dueDate = document.getElementById('task_due').value;
    const warnEl = document.getElementById('dateValidationWarn');
    const warnMsg = document.getElementById('dateWarnMsg');
    const submitBtn = document.querySelector('#formTugas button[type="submit"]');
    
    let hasError = false;
    
    if (startDate && endDate && endDate < startDate) {
        warnMsg.textContent = 'Tanggal selesai tidak boleh sebelum tanggal mulai!';
        hasError = true;
    } else if (startDate && dueDate && dueDate < startDate) {
        warnMsg.textContent = 'Deadline tidak boleh sebelum tanggal mulai!';
        hasError = true;
    }
    
    if (hasError) {
        warnEl.style.display = 'block';
        if(submitBtn) submitBtn.disabled = true;
    } else {
        warnEl.style.display = 'none';
        // Re-enable only if budget is also OK
        const akoWarn = document.getElementById('budgetLimitWarn');
        if(submitBtn && akoWarn && akoWarn.style.display === 'none') submitBtn.disabled = false;
        else if(submitBtn && !akoWarn) submitBtn.disabled = false;
    }
}

// Auto-calculate duration from dates
document.getElementById('task_start_date').addEventListener('change', function(){ calcDuration(); validateDates(); });
document.getElementById('task_end_date').addEventListener('change', function(){ calcDuration(); validateDates(); });
document.getElementById('task_due').addEventListener('change', validateDates);

function calcDuration() {
    const s = document.getElementById('task_start_date').value;
    const e = document.getElementById('task_end_date').value;
    if(s && e) {
        const diff = Math.ceil((new Date(e) - new Date(s)) / (1000*60*60*24)) + 1;
        if(diff > 0) document.getElementById('task_project_days').value = diff;
    }
}

// Budget auto-calc on input change
['task_transport_cost','task_hotel_cost','task_meal_allowance'].forEach(id => {
    document.getElementById(id).addEventListener('input', calcBudget);
});

// ========== SEARCH, FILTER & SORT ==========
function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#projectTableBody tr[data-search]');
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        
        let matchSearch = !search || searchData.includes(search);
        let matchStatus = statusFilter === 'all' || rowStatus === statusFilter || 
                          (statusFilter === 'completed_draft' && rowStatus === 'completed_draft') ||
                          (statusFilter === 'completed_final' && rowStatus === 'completed_final');
        
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    });
}

function sortTable() {
    const sortBy = document.getElementById('sortSelect').value;
    const tbody = document.getElementById('projectTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr[data-search]'));
    
    if (sortBy === 'default') { location.reload(); return; }
    
    const priorityOrder = { 'urgent': 0, 'high': 1, 'medium': 2, 'low': 3 };
    
    rows.sort((a, b) => {
        switch(sortBy) {
            case 'value_desc':
                return parseFloat(b.getAttribute('data-value')) - parseFloat(a.getAttribute('data-value'));
            case 'value_asc':
                return parseFloat(a.getAttribute('data-value')) - parseFloat(b.getAttribute('data-value'));
            case 'newest':
                return new Date(b.getAttribute('data-created')) - new Date(a.getAttribute('data-created'));
            case 'oldest':
                return new Date(a.getAttribute('data-created')) - new Date(b.getAttribute('data-created'));
            case 'priority':
                return (priorityOrder[a.getAttribute('data-priority')] || 99) - (priorityOrder[b.getAttribute('data-priority')] || 99);
            default:
                return 0;
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}
</script>
</body>
</html>
