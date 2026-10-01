<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'dashboard';

// Real counts from DB
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$rented_assets = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'rented'")->fetchColumn();
$total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$active_projects = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed'")->fetchColumn();

// Recent activity
$recent_projects = $pdo->query("SELECT t.*, u.full_name as staff_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
$recent_attend = $pdo->query("SELECT a.*, u.full_name FROM attendances a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

$full_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css?v=<?= time() ?>">
</head>
<body>

<?php require_once '../includes/admin_sidebar.php'; ?>

<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1>Welcome Home, <?= htmlspecialchars($full_name) ?>...</h1>
                <p><i class="fa-regular fa-calendar-days"></i> <?= indo_date() ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user">
                <?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?>
                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </div>
        </div>
    </div>

    <div class="portal-content">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-box-open"></i></div>
                <div class="stat-info">
                    <h4>Total Aset</h4>
                    <div class="stat-value"><?= $total_products ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <div class="stat-info">
                    <h4>Sedang Disewa</h4>
                    <div class="stat-value"><?= $rented_assets ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <h4>Total Staff</h4>
                    <div class="stat-value"><?= $total_staff ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-briefcase"></i></div>
                <div class="stat-info">
                    <h4>Proyek Aktif</h4>
                    <div class="stat-value"><?= $active_projects ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="stats-grid" style="margin-bottom: 30px;">
            <a href="tugas.php" class="stat-card" style="cursor:pointer; border-bottom: 3px solid var(--accent-color);">
                <div class="stat-icon purple"><i class="fa-solid fa-list-check"></i></div>
                <div class="stat-info"><h4 style="color:var(--accent-color);">Penugasan Proyek</h4></div>
            </a>
            <a href="absensi_monitor.php" class="stat-card" style="cursor:pointer; border-bottom: 3px solid var(--success);">
                <div class="stat-icon green"><i class="fa-solid fa-binoculars"></i></div>
                <div class="stat-info"><h4 style="color:var(--success);">Monitor Lapangan</h4></div>
            </a>
            <a href="products.php" class="stat-card" style="cursor:pointer; border-bottom: 3px solid var(--secondary-accent);">
                <div class="stat-icon blue"><i class="fa-solid fa-box-open"></i></div>
                <div class="stat-info"><h4 style="color:var(--secondary-accent);">Inventaris Gudang</h4></div>
            </a>
            <a href="users.php" class="stat-card" style="cursor:pointer; border-bottom: 3px solid var(--warning);">
                <div class="stat-icon orange"><i class="fa-solid fa-user-gear"></i></div>
                <div class="stat-info"><h4 style="color:var(--warning);">Manajemen Staff</h4></div>
            </a>
        </div>

        <!-- Activity Feed -->
        <div class="grid-2-responsive">
            <!-- Recent Projects -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-briefcase"></i> Proyek Terbaru</h3>
                    <a href="tugas.php" style="font-size:12px; font-weight:700; color:var(--accent-color);">Lihat Semua</a>
                </div>
                <div class="card-body" style="padding: 10px 25px;">
                    <?php if(count($recent_projects) === 0): ?>
                        <div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-inbox"></i><p>Belum ada proyek.</p></div>
                    <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach($recent_projects as $t): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background:rgba(99,102,241,0.1); color:var(--accent-color);"><i class="fa-solid fa-hashtag"></i></div>
                            <div style="flex:1;">
                                <div class="activity-text"><strong><?= htmlspecialchars($t['title']) ?></strong></div>
                                <div style="font-size:11px; color:var(--text-light); margin-top:2px;">
                                    Ditugaskan ke: <?= htmlspecialchars($t['staff_name'] ?: 'Belum ada') ?>
                                </div>
                                <div class="activity-time"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div>
                            </div>
                            <span class="badge badge-<?= $t['status'] ?>" style="font-size:9px;"><?= $t['status'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-clipboard-user"></i> Absensi Terbaru</h3>
                    <a href="absensi_monitor.php" style="font-size:12px; font-weight:700; color:var(--success);">Monitor</a>
                </div>
                <div class="card-body" style="padding: 10px 25px;">
                    <?php if(count($recent_attend) === 0): ?>
                        <div class="empty-state" style="padding:30px 0;"><i class="fa-solid fa-inbox"></i><p>Belum ada data absensi.</p></div>
                    <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach($recent_attend as $a): ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="background:rgba(16,185,129,0.1); color:var(--success);"><i class="fa-solid fa-user-check"></i></div>
                            <div>
                                <div class="activity-text"><strong><?= htmlspecialchars($a['full_name']) ?></strong> check-in <?= date('H:i', strtotime($a['time_in'])) ?> WIB</div>
                                <div class="activity-time"><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($a['date'])) ?></div>
                            </div>
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
