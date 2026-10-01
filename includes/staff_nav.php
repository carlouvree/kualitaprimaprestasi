<!-- Staff Sidebar (Desktop) + Bottom Nav (Mobile) -->
<aside class="portal-sidebar" id="portalSidebar">
    <div class="sidebar-brand">
        <?php $sb_logo = get_setting('logo_image'); if($sb_logo): ?>
            <img src="<?= base_url('uploads/' . htmlspecialchars($sb_logo)) ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; background:#fff; border:2px solid #e2e8f0; padding:0;">
        <?php else: ?>
            <div class="brand-icon"><span>ptku</span></div>
        <?php endif; ?>
        <div class="brand-text">
            <h2>Staff</h2>
            <small>Kualita Prima Prestasi</small>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-menu-label">Menu</li>
        <li><a href="index.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Beranda</a></li>
        <li><a href="absen.php?tipe=masuk" class="<?= ($current_page ?? '') === 'absen' ? 'active' : '' ?>"><i class="fa-solid fa-camera"></i> Absensi</a></li>
        <?php 
        $user_checked_in = is_staff_checked_in($pdo, $_SESSION['user_id'] ?? 0); 
        if ($user_checked_in): 
        ?>
            <li><a href="proyek.php" class="<?= ($current_page ?? '') === 'proyek' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Proyek Saya</a></li>
        <?php else: ?>
            <li><a href="absen.php?tipe=masuk" style="opacity: 0.6; cursor: not-allowed;" title="Silakan Absen Check-In Terlebih Dahulu"><i class="fa-solid fa-lock"></i> Proyek Saya <small style="font-size: 10px; display: block; margin-left: 28px;">(Absen Dulu)</small></a></li>
        <?php endif; ?>
        <li><a href="report.php" class="<?= ($current_page ?? '') === 'report' ? 'active' : '' ?>"><i class="fa-solid fa-file-pen"></i> Laporan</a></li>
        <li class="sidebar-menu-label">Riwayat</li>
        <li><a href="riwayat.php" class="<?= ($current_page ?? '') === 'riwayat' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Lengkap</a></li>
        <li class="sidebar-menu-label">Akun</li>
        <li><a href="profil.php" class="<?= ($current_page ?? '') === 'profil' ? 'active' : '' ?>"><i class="fa-solid fa-user-gear"></i> Profil</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="../logout.php?session_role=staff"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('portalSidebar').classList.remove('open');this.classList.remove('open');"></div>
<script src="<?= base_url('assets/js/portal_utils.js') ?>" defer></script>

<!-- Mobile Bottom Nav -->
<nav class="staff-bottom-nav">
    <a href="index.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Beranda</a>
    <a href="proyek.php" class="<?= ($current_page ?? '') === 'proyek' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Proyek</a>
    <a href="absen.php?tipe=masuk" class="<?= ($current_page ?? '') === 'absen' ? 'active' : '' ?>"><i class="fa-solid fa-camera"></i> Absen</a>
    <a href="riwayat.php" class="<?= ($current_page ?? '') === 'riwayat' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</a>
    <a href="profil.php" class="<?= ($current_page ?? '') === 'profil' ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> Profil</a>
</nav>
