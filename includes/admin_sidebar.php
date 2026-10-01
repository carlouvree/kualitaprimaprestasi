<!-- Admin Sidebar -->
<aside class="portal-sidebar" id="portalSidebar">
    <div class="sidebar-brand">
        <?php $sb_logo = get_setting('logo_image'); if($sb_logo): ?>
            <img src="<?= base_url('uploads/' . htmlspecialchars($sb_logo)) ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; background:#fff; border:2px solid #e2e8f0; padding:0;">
        <?php else: ?>
            <div class="brand-icon"><span>ptku</span></div>
        <?php endif; ?>
        <div class="brand-text">
            <h2>Admin</h2>
            <small>Kualita Prima Prestasi</small>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-menu-label">Menu Utama</li>
        <li><a href="index.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="products.php" class="<?= ($current_page ?? '') === 'products' ? 'active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i> Katalog & Aset</a></li>
        <li><a href="portfolios.php" class="<?= ($current_page ?? '') === 'portfolios' ? 'active' : '' ?>"><i class="fa-solid fa-images"></i> Portofolio Proyek</a></li>
        <li class="sidebar-menu-label">Monitoring</li>
        <li><a href="tugas.php" class="<?= ($current_page ?? '') === 'tugas' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Penugasan Proyek</a></li>
        <li><a href="rental_monitor.php" class="<?= ($current_page ?? '') === 'rental_monitor' ? 'active' : '' ?>"><i class="fa-solid fa-truck-ramp-box"></i> Monitoring Sewa</a></li>
        <li><a href="absensi_monitor.php" class="<?= ($current_page ?? '') === 'absensi' ? 'active' : '' ?>"><i class="fa-solid fa-clipboard-user"></i> Absensi</a></li>
        <li><a href="laporan.php" class="<?= ($current_page ?? '') === 'laporan' ? 'active' : '' ?>"><i class="fa-solid fa-file-signature"></i> Persetujuan Laporan</a></li>
        <li><a href="users.php" class="<?= ($current_page ?? '') === 'users' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear"></i> Manajemen Staff</a></li>
        <li><a href="dms.php" class="<?= ($current_page ?? '') === 'dms' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice"></i> DMS & Rekap Bulanan</a></li>
        <li class="sidebar-menu-label">Sistem</li>
        <li><a href="procurement_categories.php" class="<?= ($current_page ?? '') === 'proc_cat' ? 'active' : '' ?>"><i class="fa-solid fa-tags"></i> Kategori Pengadaan</a></li>
        <li><a href="settings.php" class="<?= ($current_page ?? '') === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Pengaturan</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="../logout.php?session_role=admin"><i class="fa-solid fa-right-from-bracket"></i> Keluar Akun</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.getElementById('portalSidebar').classList.remove('open');this.classList.remove('open');"></div>
<script src="<?= base_url('assets/js/portal_utils.js') ?>" defer></script>
