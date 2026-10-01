<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('staff');
$current_page = 'profil';
$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$user_id]); $user=$user->fetch();
$msg = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['update_profile'])){
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pdo->prepare("UPDATE users SET full_name=?,phone=?,email=? WHERE id=?")->execute([$full_name,$phone,$email,$user_id]);
        $_SESSION['full_name'] = $full_name;
        $msg = 'Profil berhasil diperbarui.';
        $user = $pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$user_id]); $user=$user->fetch();
    }
    if(isset($_POST['change_password'])){
        $current = trim($_POST['current_password']);
        $new = trim($_POST['new_password']);
        if(password_verify($current, $user['password'])){
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $msg = 'Password berhasil diubah.';
        } else { $msg = 'error:Password lama salah.'; }
    }
}

$monthly = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id=? AND date >= ?");
$monthly->execute([$user_id, date('Y-m-01')]); $hadir = $monthly->fetchColumn();
$reports = $pdo->prepare("SELECT COUNT(*) FROM project_reports WHERE user_id=? AND report_date >= ?");
$reports->execute([$user_id, date('Y-m-01')]); $laporan = $reports->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — KPP Staff</title>
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
            <div><h1>Profil Saya</h1><p>Kelola informasi akun</p></div>
        </div>
        <div class="topbar-right">
            <a href="../logout.php" class="topbar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
    <div class="portal-content">
        <?php if($msg): ?>
            <div class="alert <?= strpos($msg,'error')===0?'alert-error':'alert-success' ?>"><i class="fa-solid fa-<?= strpos($msg,'error')===0?'triangle-exclamation':'check-circle' ?>"></i> <?= str_replace('error:','',$msg) ?></div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="card" style="margin-bottom:25px;">
            <div class="card-body" style="text-align:center;padding:40px 25px;">
                <div class="avatar" style="width:80px;height:80px;font-size:32px;margin:0 auto 20px;"><?= strtoupper(mb_substr($user['full_name'],0,1)) ?></div>
                <h3 style="font-family:'Montserrat',sans-serif;font-size:22px;color:var(--text-dark);margin-bottom:5px;"><?= htmlspecialchars($user['full_name']) ?></h3>
                <span class="badge badge-staff" style="margin-bottom:20px;"><i class="fa-solid fa-shield-halved"></i> Staff Field</span>
                <div style="display:flex;gap:20px;justify-content:center;margin-top:20px;">
                    <div><span style="font-size:24px;font-weight:800;color:var(--accent-color);font-family:'Montserrat',sans-serif;"><?= $hadir ?></span><br><small style="color:var(--text-light);">Hadir</small></div>
                    <div style="width:1px;background:var(--border);"></div>
                    <div><span style="font-size:24px;font-weight:800;color:var(--secondary-accent);font-family:'Montserrat',sans-serif;"><?= $laporan ?></span><br><small style="color:var(--text-light);">Laporan</small></div>
                </div>
            </div>
        </div>

        <div class="grid-2-responsive">
            <!-- Edit Profile -->
            <div class="card">
                <div class="card-header"><h3><i class="fa-solid fa-user-pen"></i> Edit Profil</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">No. Telepon</label><input type="text" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']??'') ?>" class="form-control"></div>
                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-save"></i> Simpan</button>
                    </form>
                </div>
            </div>
            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><h3><i class="fa-solid fa-key"></i> Ganti Password</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group"><label class="form-label">Password Lama</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                        <button type="submit" class="btn btn-accent" style="width:100%;"><i class="fa-solid fa-key"></i> Ubah Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
