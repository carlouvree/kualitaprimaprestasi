<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('admin');
$current_page = 'settings';

$user_id = $_SESSION['user_id'];
$msg = '';

// Ambil data profil admin saat ini
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$settings_query = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
$global_settings_admin = [];
while ($row = $settings_query->fetch()) {
    $global_settings_admin[$row['setting_key']] = $row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Handle photo upload
        $photo = $user['profile_photo'];
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
            if (!is_dir('../uploads/profiles/')) mkdir('../uploads/profiles/', 0777, true);
            if(compress_image_upload($_FILES['photo']['tmp_name'], '../uploads/profiles/' . $filename, 80, 500)){
                $photo = $filename;
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, profile_photo=? WHERE id=?");
        $stmt->execute([$full_name, $email, $phone, $photo, $user_id]);
        $_SESSION['full_name'] = $full_name;
        $_SESSION['profile_photo'] = $photo;
        
        $role = $_SESSION['role'] ?? 'admin';
        $_SESSION['portal_auth'][$role]['full_name'] = $full_name;
        $_SESSION['portal_auth'][$role]['profile_photo'] = $photo;
        
        $msg = 'success|Profil berhasil diperbarui!';
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }

    if ($action === 'change_password') {
        $current_pw = $_POST['current_password'];
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];

        if (!password_verify($current_pw, $user['password'])) {
            $msg = 'error|Password lama salah!';
        } elseif (strlen($new_pw) < 6) {
            $msg = 'error|Password baru minimal 6 karakter!';
        } elseif ($new_pw !== $confirm_pw) {
            $msg = 'error|Konfirmasi password tidak cocok!';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
            $msg = 'success|Password berhasil diubah!';
        }
    }
    
    if ($action === 'update_web') {
        $updates = [
            'logo_text' => $_POST['logo_text'] ?? '',
            'profile_desc1' => $_POST['profile_desc1'] ?? '',
            'profile_desc2' => $_POST['profile_desc2'] ?? '',
            'landing_video' => $_POST['landing_video'] ?? ''
        ];
        
        // Handle logo image upload
        if (isset($_FILES['logo_image_file']) && $_FILES['logo_image_file']['error'] == 0) {
            $ext = pathinfo($_FILES['logo_image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
            if(compress_image_upload($_FILES['logo_image_file']['tmp_name'], '../uploads/' . $filename, 90, 800)){
                $updates['logo_image'] = $filename;
            }
        }
        // Handle profile image upload
        if (isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] == 0) {
            $ext = pathinfo($_FILES['profile_image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . time() . '.' . $ext;
            if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
            if(compress_image_upload($_FILES['profile_image_file']['tmp_name'], '../uploads/' . $filename, 80, 1200)){
                $updates['profile_image'] = $filename;
            }
        }
        // Handle about image upload
        if (isset($_FILES['about_image_file']) && $_FILES['about_image_file']['error'] == 0) {
            $ext = pathinfo($_FILES['about_image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'about_' . time() . '.' . $ext;
            if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
            if(compress_image_upload($_FILES['about_image_file']['tmp_name'], '../uploads/' . $filename, 80, 1200)){
                $updates['about_image'] = $filename;
            }
        }
        
        $services = ['eng', 'con', 'pro'];
        foreach($services as $svc) {
            $field = 'img_service_' . $svc;
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] == 0) {
                $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                $filename = 'svc_' . $svc . '_' . time() . '.' . $ext;
                if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
                if(compress_image_upload($_FILES[$field]['tmp_name'], '../uploads/' . $filename, 80, 1200)){
                    $updates[$field] = $filename;
                }
            }
        }
        
        $stmt_update = $pdo->prepare("INSERT INTO global_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach($updates as $k => $v) {
            $stmt_update->execute([$k, $v]);
        }
        $msg = 'success|Pengaturan web berhasil diperbarui!';
        
        $settings_query = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
        $global_settings_admin = [];
        while ($row = $settings_query->fetch()) {
            $global_settings_admin[$row['setting_key']] = $row['setting_value'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
<?php require_once '../includes/admin_sidebar.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Pengaturan Akun</h1><p>Edit profil dan keamanan akun administrator</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if($msg): ?>
            <?php $parts = explode('|', $msg); ?>
            <div class="alert alert-<?= $parts[0] === 'success' ? 'success' : 'error' ?>"><i class="fa-solid fa-<?= $parts[0]==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= $parts[1] ?></div>
        <?php endif; ?>

        <div class="grid-2-responsive">
            <!-- Edit Profile -->
            <div class="card">
                <div class="card-header"><h3><i class="fa-solid fa-user-pen"></i> Edit Profil</h3></div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div style="text-align:center; margin-bottom:25px;">
                            <?php if($user['profile_photo']): ?>
                                <img src="../uploads/profiles/<?= $user['profile_photo'] ?>" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--border);margin-bottom:10px;">
                            <?php else: ?>
                                <div class="avatar" style="width:90px;height:90px;font-size:36px;margin:0 auto 10px;"><?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <p style="font-size:13px;color:var(--text-light);">@<?= htmlspecialchars($user['username']) ?> • <span class="badge badge-<?= $user['role'] ?>"><?= strtoupper($user['role']) ?></span></p>
                        </div>
                        
                        <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required></div>
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">No. Telepon</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label class="form-label">Foto Profil</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                        <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan Profil</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><h3><i class="fa-solid fa-lock"></i> Ubah Password</h3></div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group"><label class="form-label">Password Lama</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Password Baru</label><input type="password" name="new_password" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Konfirmasi Password Baru</label><input type="password" name="confirm_password" class="form-control" required></div>
                        <button type="submit" class="btn btn-accent" style="width:100%;padding:13px;"><i class="fa-solid fa-key"></i> Ubah Password</button>
                    </form>
                </div>

                <!-- System Info -->
                <div class="card-body" style="border-top:1px solid var(--border);">
                    <h4 style="font-family:'Montserrat',sans-serif;font-size:15px;color:var(--primary-color);margin-bottom:15px;"><i class="fa-solid fa-circle-info" style="color:var(--secondary-accent);"></i> Info Sistem</h4>
                    <div style="font-size:13px;color:var(--text-light);line-height:2;">
                        <div><strong>Perusahaan:</strong> PT. Kualita Prima Prestasi</div>
                        <div><strong>Versi Portal:</strong> 2.0</div>
                        <div><strong>Server:</strong> PHP (versi tersembunyi)</div>
                        <div><strong>Database:</strong> MariaDB</div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div style="margin-top:25px;">
            <div class="card">
                <div class="card-header"><h3><i class="fa-solid fa-globe"></i> Pengaturan Konten Web Utama</h3></div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_web">
                        <div class="form-group">
                            <label class="form-label">Teks Logo (Kiri Atas)</label>
                            <input type="text" name="logo_text" class="form-control" value="<?= htmlspecialchars($global_settings_admin['logo_text'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ganti Ikon Logo (Opsional)</label>
                            <?php if(!empty($global_settings_admin['logo_image'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="../uploads/<?= htmlspecialchars($global_settings_admin['logo_image']) ?>" style="height:50px; border-radius:8px; border:1px solid #ddd;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo_image_file" class="form-control" accept="image/*">
                            <small style="color:#666;">Jika diisi, logo lingkaran teks bawaan akan diganti dengan file foto ini.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL Video Background (Landing Page)</label>
                            <input type="text" name="landing_video" class="form-control" value="<?= htmlspecialchars($global_settings_admin['landing_video'] ?? '') ?>" placeholder="https://...">
                            <small style="color:#666; display:block; margin-top:5px;">Gunakan link video mp4 (contoh: dari hosting Anda atau external)</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deskripsi Profil Landing Page (Paragraf 1)</label>
                            <textarea name="profile_desc1" class="form-control" rows="3"><?= htmlspecialchars($global_settings_admin['profile_desc1'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deskripsi Profil Landing Page (Paragraf 2)</label>
                            <textarea name="profile_desc2" class="form-control" rows="3"><?= htmlspecialchars($global_settings_admin['profile_desc2'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Proyek / Profil Utama (Landing Page)</label>
                            <?php if(!empty($global_settings_admin['profile_image'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="../uploads/<?= htmlspecialchars($global_settings_admin['profile_image']) ?>" style="height:100px; border-radius:8px; border:1px solid #ddd; object-fit:cover;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="profile_image_file" class="form-control" accept="image/*">
                            <small style="color:#666;">Mengganti foto ekskavator/proyek yang tampil membesar di landing page utama.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Halaman Detail Profil Perusahaan</label>
                            <?php if(!empty($global_settings_admin['about_image'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="../uploads/<?= htmlspecialchars($global_settings_admin['about_image']) ?>" style="height:100px; border-radius:8px; border:1px solid #ddd; object-fit:cover;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="about_image_file" class="form-control" accept="image/*">
                            <small style="color:#666;">Mengganti foto pekerja yang muncul di halaman khusus Profil Perusahaan.</small>
                        </div>
                        
                        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
                        <h4 style="margin-bottom:15px; color:var(--primary-color);">Foto Halaman Layanan</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Foto Layanan Engineering</label>
                            <?php if(!empty($global_settings_admin['img_service_eng'])): ?>
                                <div style="margin-bottom:10px;"><img src="<?= (strpos($global_settings_admin['img_service_eng'], 'assets')===0) ? '../'.$global_settings_admin['img_service_eng'] : '../uploads/'.$global_settings_admin['img_service_eng'] ?>" style="height:100px; border-radius:8px; border:1px solid #ddd; object-fit:cover;"></div>
                            <?php endif; ?>
                            <input type="file" name="img_service_eng" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Layanan Construction</label>
                            <?php if(!empty($global_settings_admin['img_service_con'])): ?>
                                <div style="margin-bottom:10px;"><img src="<?= (strpos($global_settings_admin['img_service_con'], 'assets')===0) ? '../'.$global_settings_admin['img_service_con'] : '../uploads/'.$global_settings_admin['img_service_con'] ?>" style="height:100px; border-radius:8px; border:1px solid #ddd; object-fit:cover;"></div>
                            <?php endif; ?>
                            <input type="file" name="img_service_con" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Layanan Procurement</label>
                            <?php if(!empty($global_settings_admin['img_service_pro'])): ?>
                                <div style="margin-bottom:10px;"><img src="<?= (strpos($global_settings_admin['img_service_pro'], 'assets')===0) ? '../'.$global_settings_admin['img_service_pro'] : '../uploads/'.$global_settings_admin['img_service_pro'] ?>" style="height:100px; border-radius:8px; border:1px solid #ddd; object-fit:cover;"></div>
                            <?php endif; ?>
                            <input type="file" name="img_service_pro" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: auto; padding: 10px 20px;"><i class="fa-solid fa-save"></i> Simpan Pengaturan Web</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
