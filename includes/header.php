<?php require_once 'config/database.php'; ?>
<!DOCTYPE html>
<html lang="id" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PT. Kualita Prima Prestasi - Solusi Engineering & Alat Berat</title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.svg?v=1779250813') ?>">
    <!-- Authentic Corporate Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=<?= time() ?>">
</head>
<body>

    <div class="header-wrapper" style="position: -webkit-sticky; position: sticky; top: 0; z-index: 1050; width: 100%;">
    <!-- Top Bar (Contact Info) -->
    <div class="top-bar">
        <div class="container top-bar-inner">
            <div class="top-bar-left">
                <a href="https://maps.google.com/?q=Jl.+Janti+Bar.+No.36,+Bandungrejosari,+Sukun,+Malang" target="_blank" rel="noopener" class="top-bar-map-link">
                    <i class="fa-solid fa-location-dot" style="color:var(--secondary-accent);"></i>
                    <span>Workshop: Jl. Janti Bar. No.36, Sukun, Malang</span>
                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:10px; opacity:0.7; margin-left:4px;"></i>
                </a>
            </div>
            <div class="top-bar-right" style="align-items: center;">
                <div class="language-switcher" style="margin-top: 0; margin-right: 15px; border-right: 1px solid rgba(255,255,255,0.15); padding-right: 15px;">
                    <button class="lang-btn active" data-lang="id" title="Bahasa Indonesia">
                        <img src="https://flagcdn.com/w20/id.png" alt="ID" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Ind</span>
                    </button>
                    <button class="lang-btn" data-lang="en" title="English">
                        <img src="https://flagcdn.com/w20/gb.png" alt="EN" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Eng</span>
                    </button>
                    <button class="lang-btn" data-lang="zh" title="中文">
                        <img src="https://flagcdn.com/w20/cn.png" alt="CN" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Chn</span>
                    </button>
                </div>
                <a href="https://www.instagram.com/kualitaprima?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" rel="noopener" class="top-bar-instagram">
                    <i class="fa-brands fa-instagram"></i> @kualitaprima
                </a>
                <a href="mailto:info@kualitaprima.com"><i class="fa-regular fa-envelope" style="color:var(--secondary-accent);"></i> info@kualitaprima.com</a>
                <a href="tel:+62811323169"><i class="fa-solid fa-phone" style="color:var(--secondary-accent);"></i> +62 811 323 169</a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="main-header">
        <div class="container header-inner">
            <!-- Logo -->
            <a href="<?= base_url() ?>" class="logo">
                <?php $logoImg = get_setting('logo_image'); if($logoImg): ?>
                    <img src="<?= base_url('uploads/' . htmlspecialchars($logoImg)) ?>" alt="Logo" style="width:48px; height:48px; border-radius:50%; object-fit:cover; background:#fff; border:2px solid #fff; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div class="logo-icon"><span>ptku</span></div>
                <?php endif; ?>
                <div class="logo-text"><?= htmlspecialchars(get_setting('logo_text', 'PT Kualita Prima Prestasi')) ?><small>Engineering & Construction</small></div>
            </a>

            <!-- Toggle Button for Mobile -->
            <button class="menu-toggle" aria-label="Toggle Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Navigation Links -->
            <nav class="main-nav">
                <ul>
                    <li><a href="<?= base_url() ?>" data-i18n="nav_home">Beranda</a></li>
                    <li><a href="<?= base_url('profil-perusahaan.php') ?>" data-i18n="nav_profile">Profil</a></li>
                    <li class="has-dropdown">
                        <a href="javascript:void(0)" style="cursor: default;">
                            <span data-i18n="nav_services" style="cursor: pointer;">Layanan</span> 
                            <i class="fa-solid fa-chevron-down" style="font-size:10px; margin-left:3px; position:relative; top:-1px; cursor: pointer;"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="<?= base_url('layanan-engineering.php') ?>" data-i18n="nav_svc1">Engineering</a></li>
                            <li><a href="<?= base_url('layanan-construction.php') ?>" data-i18n="nav_svc2">Construction</a></li>
                            <li><a href="<?= base_url('layanan-procurement.php') ?>" data-i18n="nav_svc3">Procurement</a></li>
                        </ul>
                    </li>
                    <li><a href="<?= base_url() ?>katalog.php" data-i18n="nav_catalog">Katalog</a></li>
                    <li><a href="<?= base_url() ?>portofolio.php" data-i18n="nav_portfolio">Portofolio</a></li>
                    <li><a href="<?= base_url() ?>#kontak" data-i18n="nav_contact">Kontak</a></li>
                    <li><a href="<?= base_url('login.php') ?>" class="btn-login" data-i18n-aria="nav_staff"><i class="fa-solid fa-right-to-bracket"></i> <span data-i18n="nav_staff">Staff Portal</span></a></li>
                    
                    <!-- Mobile Language Switcher -->
                    <li class="mobile-lang-container" style="display: none; padding-top: 15px; border-top: 1px solid #e2e8f0; width: 100%; margin-top: 10px;">
                        <span style="font-size: 13px; font-weight: 700; color: var(--primary-color); margin-bottom: 12px; display: block; font-family: 'Montserrat', sans-serif;">Pilih Bahasa / Language</span>
                        <div class="language-switcher" style="margin-top: 0; display:flex; gap: 10px;">
                            <button class="lang-btn active" data-lang="id" title="Bahasa Indonesia" style="border-color: #cbd5e1; color: var(--primary-color);">
                                <img src="https://flagcdn.com/w20/id.png" alt="ID" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Ind</span>
                            </button>
                            <button class="lang-btn" data-lang="en" title="English" style="border-color: #cbd5e1; color: var(--primary-color);">
                                <img src="https://flagcdn.com/w20/gb.png" alt="EN" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Eng</span>
                            </button>
                            <button class="lang-btn" data-lang="zh" title="中文" style="border-color: #cbd5e1; color: var(--primary-color);">
                                <img src="https://flagcdn.com/w20/cn.png" alt="CN" width="20" height="13" class="flag-img" onerror="this.style.display='none'"> <span class="lang-label">Chn</span>
                            </button>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    </div>
