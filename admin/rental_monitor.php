<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'rental_monitor';

// Kembalikan barang
if(isset($_GET['return'])){
    $rental_id = (int)$_GET['return'];
    $pdo->prepare("UPDATE product_rentals SET status='returned' WHERE id=?")->execute([$rental_id]);
    // Update product status back to available
    $stmt = $pdo->prepare("SELECT product_id FROM product_rentals WHERE id=?");
    $stmt->execute([$rental_id]);
    $row = $stmt->fetch();
    if($row) {
        $pdo->prepare("UPDATE products SET status='available' WHERE id=?")->execute([$row['product_id']]);
    }
    header("Location: rental_monitor.php?msg=returned"); exit;
}

// Tambah sewa baru
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $product_id = (int)$_POST['product_id'];
    $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
    $rental_start = $_POST['rental_start'];
    $rental_end = !empty($_POST['rental_end']) ? $_POST['rental_end'] : null;
    $daily_rate = !empty($_POST['daily_rate']) ? (float)preg_replace('/[^0-9]/', '', $_POST['daily_rate']) : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Hitung total cost
    $total_cost = 0;
    if($rental_end && $daily_rate > 0) {
        $days = max(1, (strtotime($rental_end) - strtotime($rental_start)) / 86400);
        $total_cost = $days * $daily_rate;
    }

    // Generate order code
    $today = date('Ymd');
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM product_rentals WHERE order_code LIKE ?");
    $cntStmt->execute(["RNT-$today-%"]);
    $cnt = (int)$cntStmt->fetchColumn() + 1;
    $order_code = "RNT-$today-" . str_pad($cnt, 3, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO product_rentals (product_id, task_id, order_code, rental_start, rental_end, daily_rate, total_cost, status, notes) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$product_id, $task_id, $order_code, $rental_start, $rental_end, $daily_rate, $total_cost, 'active', $notes]);

    // Update product status to rented
    $pdo->prepare("UPDATE products SET status='rented' WHERE id=?")->execute([$product_id]);

    header("Location: rental_monitor.php?msg=success"); exit;
}

// Fetch rentals
$rentals = $pdo->query("
    SELECT r.*, p.name as product_name, p.sku, p.image_url, 
           c.name as category_name, c.type as category_type,
           t.title as project_title, t.project_code
    FROM product_rentals r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN tasks t ON r.task_id = t.id
    ORDER BY FIELD(r.status, 'active', 'overdue', 'returned'), r.rental_start DESC
")->fetchAll();

// Auto-mark overdue
foreach($rentals as &$r) {
    if($r['status'] === 'active' && $r['rental_end'] && $r['rental_end'] < date('Y-m-d')) {
        $pdo->prepare("UPDATE product_rentals SET status='overdue' WHERE id=?")->execute([$r['id']]);
        $r['status'] = 'overdue';
    }
}
unset($r);

// Fetch available products for rental
$availProducts = $pdo->query("SELECT p.id, p.name, p.sku, c.name as cat_name, c.type 
    FROM products p LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'available' 
    ORDER BY p.name")->fetchAll();

// Fetch active projects for linking
$activeProjects = $pdo->query("SELECT id, project_code, title FROM tasks WHERE status != 'completed' ORDER BY created_at DESC")->fetchAll();

// Stats
$totalActive = 0; $totalOverdue = 0; $totalReturned = 0;
foreach($rentals as $r) {
    if($r['status'] === 'active') $totalActive++;
    elseif($r['status'] === 'overdue') $totalOverdue++;
    elseif($r['status'] === 'returned') $totalReturned++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Sewa Barang — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css?v=<?= time() ?>">
    <style>
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid var(--border); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-info h4 { font-size: 24px; font-weight: 800; font-family: 'Montserrat', sans-serif; margin: 0; line-height: 1; }
        .stat-info p { font-size: 12px; color: var(--text-light); margin: 4px 0 0; font-weight: 600; }

        .rental-status-active { background: rgba(16,185,129,0.1); color: #059669; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .rental-status-overdue { background: rgba(239,68,68,0.1); color: #dc2626; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; animation: pulse 2s infinite; }
        .rental-status-returned { background: rgba(100,116,139,0.1); color: #64748b; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }
    </style>
</head>
<body>
<?php require_once '../includes/admin_sidebar.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Monitoring Sewa Barang</h1><p>Pantau status penyewaan barang dari katalog</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Sewa barang berhasil dicatat!</div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'returned'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Barang berhasil ditandai dikembalikan!</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,0.1);color:#059669;"><i class="fa-solid fa-key"></i></div>
                <div class="stat-info"><h4><?= $totalActive ?></h4><p>Sedang Disewa</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:#dc2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info"><h4><?= $totalOverdue ?></h4><p>Terlambat</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(100,116,139,0.1);color:#64748b;"><i class="fa-solid fa-rotate-left"></i></div>
                <div class="stat-info"><h4><?= $totalReturned ?></h4><p>Dikembalikan</p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-truck-ramp-box"></i> Daftar Sewa Barang</h3>
                <button class="btn btn-primary" onclick="document.getElementById('modalSewa').classList.add('active')"><i class="fa-solid fa-plus"></i> Catat Sewa Baru</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Kode Sewa</th><th>Barang</th><th>Proyek Terkait</th><th>Periode Sewa</th><th>Tarif/Hari</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if(empty($rentals)): ?>
                        <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Belum ada data sewa.</p></div></td></tr>
                    <?php else: foreach($rentals as $r): ?>
                    <tr style="<?= $r['status']==='returned'?'opacity:0.6;':'' ?>">
                        <td><span style="font-family:'Montserrat',sans-serif;font-size:12px;font-weight:700;color:var(--accent-color);"><?= htmlspecialchars($r['order_code'] ?? '-') ?></span></td>
                        <td>
                            <strong style="font-size:13px;color:var(--text-dark);"><?= htmlspecialchars($r['product_name']) ?></strong>
                            <?php if($r['sku']): ?><br><small style="color:var(--text-light);">SKU: <?= htmlspecialchars($r['sku']) ?></small><?php endif; ?>
                            <?php if($r['category_name']): ?><br><small style="color:var(--text-light);"><?= htmlspecialchars($r['category_name']) ?> (<?= strtoupper($r['category_type']) ?>)</small><?php endif; ?>
                        </td>
                        <td>
                            <?php if($r['project_title']): ?>
                                <a href="tugas_detail.php?id=<?= $r['task_id'] ?>" style="font-size:12px;font-weight:700;color:var(--accent-color);text-decoration:none;">
                                    <?= htmlspecialchars($r['project_code'] ?? '') ?><br>
                                    <span style="font-weight:400;color:var(--text-light);"><?= htmlspecialchars(mb_substr($r['project_title'],0,30)) ?></span>
                                </a>
                            <?php else: ?><span style="color:#cbd5e1;">-</span><?php endif; ?>
                        </td>
                        <td style="font-size:12px;white-space:nowrap;">
                            <div><i class="fa-solid fa-play" style="color:var(--success);font-size:10px;"></i> <?= date('d M Y', strtotime($r['rental_start'])) ?></div>
                            <?php if($r['rental_end']): ?>
                            <div><i class="fa-solid fa-stop" style="color:var(--danger);font-size:10px;"></i> <?= date('d M Y', strtotime($r['rental_end'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;font-weight:600;">Rp <?= number_format($r['daily_rate'], 0, ',', '.') ?></td>
                        <td style="font-size:13px;font-weight:700;color:var(--success);">Rp <?= number_format($r['total_cost'], 0, ',', '.') ?></td>
                        <td>
                            <span class="rental-status-<?= $r['status'] ?>">
                                <?= $r['status']==='active'?'🔑 Aktif':($r['status']==='overdue'?'⚠️ Terlambat':'✓ Kembali') ?>
                            </span>
                        </td>
                        <td>
                            <?php if($r['status'] !== 'returned'): ?>
                            <button class="btn btn-icon" style="background:rgba(16,185,129,0.1);color:#059669;" onclick="if(confirm('Tandai barang ini sudah dikembalikan?')) location='rental_monitor.php?return=<?= $r['id'] ?>'" title="Kembalikan"><i class="fa-solid fa-rotate-left"></i></button>
                            <?php else: ?>
                            <span style="color:#cbd5e1;font-size:12px;">-</span>
                            <?php endif; ?>
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

<!-- Modal Sewa Baru -->
<div class="modal-overlay" id="modalSewa">
    <div class="modal-box" style="max-width:550px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-truck-ramp-box" style="color:var(--accent-color);"></i> Catat Sewa Baru</h3>
            <button class="modal-close" onclick="document.getElementById('modalSewa').classList.remove('active')" type="button">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label">Barang dari Katalog <span style="color:var(--danger);">*</span></label>
                    <select name="product_id" class="form-control" required>
                        <option value="">-- Pilih Barang (hanya yang tersedia) --</option>
                        <?php foreach($availProducts as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> <?= $p['sku'] ? "({$p['sku']})" : '' ?> — <?= htmlspecialchars($p['cat_name'] ?? '') ?> [<?= strtoupper($p['type'] ?? '') ?>]</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Proyek Terkait (Opsional)</label>
                    <select name="task_id" class="form-control">
                        <option value="">-- Tanpa Proyek --</option>
                        <?php foreach($activeProjects as $p): ?>
                        <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['project_code']) ?>] <?= htmlspecialchars($p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Mulai Sewa <span style="color:var(--danger);">*</span></label><input type="date" name="rental_start" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label class="form-label">Selesai Sewa</label><input type="date" name="rental_end" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">Tarif Harian (Rp)</label><input type="text" name="daily_rate" class="form-control" placeholder="Rp. 0"></div>
                <div class="form-group"><label class="form-label">Catatan</label><input type="text" name="notes" class="form-control" placeholder="Catatan opsional..."></div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan Sewa</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
</script>
</body>
</html>
