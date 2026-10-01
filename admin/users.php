<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'users';

$msg = '';

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id == $_SESSION['user_id']) {
        $msg = 'error|Tidak dapat menghapus akun yang sedang aktif!';
    } else {
        // Proteksi: Cek apakah staff masih memiliki transaksi terkait
        $taskCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
        $taskCount->execute([$id]);
        $totalTasks = (int)$taskCount->fetchColumn();

        $reportCount = $pdo->prepare("SELECT COUNT(*) FROM project_reports WHERE user_id = ?");
        $reportCount->execute([$id]);
        $totalReports = (int)$reportCount->fetchColumn();

        $attendCount = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE user_id = ?");
        $attendCount->execute([$id]);
        $totalAttend = (int)$attendCount->fetchColumn();

        $totalTransaksi = $totalTasks + $totalReports + $totalAttend;

        if ($totalTransaksi > 0) {
            $detail = [];
            if ($totalTasks > 0) $detail[] = "$totalTasks proyek";
            if ($totalReports > 0) $detail[] = "$totalReports laporan";
            if ($totalAttend > 0) $detail[] = "$totalAttend absensi";
            $msg = 'error|Tidak dapat menghapus staff! Masih memiliki ' . implode(', ', $detail) . ' terkait. Hapus atau pindahkan data tersebut terlebih dahulu.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            header("Location: users.php?msg=deleted"); exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $id         = (int)$_POST['id'];
        $full_name  = trim($_POST['full_name']);
        $username   = trim($_POST['username']);
        $phone      = trim($_POST['phone'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        // Whitelist role — jangan terima nilai sembarang dari POST
        $allowed_roles = ['admin', 'staff'];
        $role = in_array($_POST['role'], $allowed_roles) ? $_POST['role'] : 'staff';

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, phone = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$full_name, $username, $phone, $email, $role, $id]);

        if (!empty($_POST['password'])) {
            $new_password = trim($_POST['password']);
            if (strlen($new_password) >= 6) {
                $password = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$password, $id]);
            }
        }
        header("Location: users.php?msg=updated"); exit;
    } else {
        $full_name  = trim($_POST['full_name']);
        $username   = trim($_POST['username']);
        $password   = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        // Whitelist role
        $allowed_roles = ['admin', 'staff'];
        $role = in_array($_POST['role'], $allowed_roles) ? $_POST['role'] : 'staff';
        $phone      = trim($_POST['phone'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        // Validasi password minimal 6 karakter
        if (strlen(trim($_POST['password'])) < 6) {
            $msg = 'error|Password minimal 6 karakter!';
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $msg = 'error|Username sudah dipakai!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role, phone, email) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $username, $password, $role, $phone, $email]);
                header("Location: users.php?msg=success"); exit;
            }
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Staff — Admin KPP</title>
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
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1>Manajemen Staff & Akses</h1><p>Kelola akun pengguna admin dan staff lapangan</p></div>
        </div>
        <div class="topbar-right">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
        </div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> Perubahan berhasil disimpan.</div>
        <?php endif; ?>
        <?php if($msg && strpos($msg,'error') === 0): ?>
            <div class="alert alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?= explode('|', $msg)[1] ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-id-badge"></i> Daftar Pengguna</h3>
                <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fa-solid fa-user-plus"></i> Tambah Pengguna</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Pengguna</th><th>Username</th><th>Kontak</th><th>Role</th><th>Bergabung</th><th style="text-align:center;">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="avatar"><?= strtoupper(mb_substr($u['full_name'], 0, 1)) ?></div>
                                    <div>
                                        <strong style="display:block;color:var(--primary-color);font-family:'Montserrat',sans-serif;"><?= htmlspecialchars($u['full_name']) ?></strong>
                                        <small style="color:var(--text-light);"><?= $u['role']==='admin'?'Administrator':'Staff Lapangan' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code style="background:var(--bg-gray);padding:3px 8px;border-radius:4px;font-size:13px;">@<?= htmlspecialchars($u['username']) ?></code></td>
                            <td>
                                <?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : '' ?>
                                <?php if(!empty($u['email'])): ?><br><small style="color:var(--text-light);"><?= htmlspecialchars($u['email']) ?></small><?php endif; ?>
                                <?php if(empty($u['phone']) && empty($u['email'])): ?><span style="color:#ccc;">-</span><?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
                            <td style="color:var(--text-light);font-size:13px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="staff_riwayat.php?id=<?= $u['id'] ?>" class="btn btn-icon view" title="Lihat Riwayat"><i class="fa-solid fa-clock-rotate-left"></i></a>
                                <button type="button" class="btn btn-icon edit" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" title="Edit"><i class="fa-solid fa-user-pen"></i></button>
                                <button type="button" class="btn btn-icon delete" onclick="showConfirm('Hapus akun <?= addslashes($u['full_name']) ?>? Data tidak dapat dipulihkan.', () => location='users.php?hapus=<?= $u['id'] ?>')" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                <?php else: ?><span style="color:#ccc;font-size:12px;">Aktif</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalTambah">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus" style="color:var(--secondary-accent);"></i> Daftarkan Pengguna</h3>
            <button class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST">
                <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">No. HP</label><input type="text" name="phone" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">Email (Opsional)</label><input type="email" name="email" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Role</label><select name="role" class="form-control"><option value="staff">Staff Lapangan</option><option value="admin">Administrator</option></select></div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan & Aktifkan</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-pen" style="color:var(--accent-color);"></i> Perbarui Pengguna</h3>
            <button class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>
        <div class="modal-body">
            <form action="" method="POST">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="full_name" id="edit_name" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" id="edit_username" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">No. HP</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label">Email (Opsional)</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-control">
                            <option value="staff">Staff Lapangan</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
            </form>
        </div>
    </div>
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
            <div class="grid-2-responsive" style="gap:10px;">
                <button type="button" class="btn" style="background:#f1f5f9; color:#64748b;" onclick="closeModal('modalConfirm')">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}

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

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_name').value = user.full_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    openModal('modalEdit');
}
</script>
</body>
</html>
