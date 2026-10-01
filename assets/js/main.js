document.addEventListener('DOMContentLoaded', () => {

    // =========================================================
    // 1. AOS (Animation On Scroll)
    // =========================================================
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 800, easing: 'ease-out-cubic', once: true, offset: 80, disable: false });
    }

    // =========================================================
    // 2. Scroll-to-Top & Sticky Nav
    // =========================================================
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    const mainHeader   = document.querySelector('.main-header');

    window.addEventListener('scroll', () => {
        if (scrollTopBtn) {
            scrollTopBtn.classList.toggle('show', window.scrollY > 400);
        }
        if (mainHeader) {
            mainHeader.style.boxShadow = window.scrollY > 50
                ? '0 10px 40px rgba(11,26,69,0.08)'
                : '0 4px 20px rgba(0,0,0,0.03)';
        }
    });

    if (scrollTopBtn) {
        scrollTopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // =========================================================
    // 3. Mobile Hamburger Menu
    // =========================================================
    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav    = document.querySelector('.main-nav');
    const navOverlay = document.createElement('div');
    navOverlay.classList.add('nav-overlay');
    document.body.appendChild(navOverlay);

    if (menuToggle && mainNav) {
        function toggleMenu() {
            mainNav.classList.toggle('active');
            navOverlay.classList.toggle('active');
            const icon = menuToggle.querySelector('i');
            if (mainNav.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-xmark');
                document.body.style.overflow = 'hidden';
            } else {
                icon.classList.replace('fa-xmark', 'fa-bars');
                document.body.style.overflow = '';
            }
        }
        menuToggle.addEventListener('click', toggleMenu);
        navOverlay.addEventListener('click', toggleMenu);
        mainNav.querySelectorAll('a').forEach(link => {
            // Don't close menu when clicking dropdown parent
            if (link.closest('.has-dropdown') && link.parentElement.classList.contains('has-dropdown')) return;
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) toggleMenu();
            });
        });

        // Mobile dropdown toggle (click instead of hover)
        mainNav.querySelectorAll('.has-dropdown > a').forEach(dropdownTrigger => {
            dropdownTrigger.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const dropdownMenu = dropdownTrigger.nextElementSibling;
                    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                        dropdownMenu.classList.toggle('mobile-open');
                        const chevron = dropdownTrigger.querySelector('.fa-chevron-down');
                        if (chevron) {
                            chevron.style.transform = dropdownMenu.classList.contains('mobile-open') ? 'rotate(180deg)' : '';
                        }
                    }
                }
            });
        });
    }

    // =========================================================
    // 4. Smooth Scrolling
    // =========================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (!targetId || targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                const offsetPosition = target.getBoundingClientRect().top + window.pageYOffset - 85;
                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            }
        });
    });

    // =========================================================
    // 5. LANGUAGE SWITCHER SYSTEM
    // =========================================================

    // --- Label text for each language button ---
    const langLabels = { id: 'Ind', en: 'Eng', zh: 'Chn' };

    // --- Full translation table ---
    const translations = {
        id: {
            // Navigation
            nav_home: 'Beranda', nav_profile: 'Profil', nav_services: 'Layanan',
            nav_catalog: 'Katalog', nav_portfolio: 'Portofolio', nav_contact: 'Kontak',
            nav_staff: 'Staff Portal',
            nav_svc1: 'Engineering', nav_svc2: 'Construction', nav_svc3: 'Procurement',
            // Hero
            hero_title_0: 'Solusi Engineering dan Konstruksi Terpercaya',
            hero_subtitle_0: 'Inovasi, Kualitas, Keberlanjutan. PT. Kualita Prima Prestasi menyediakan layanan teknik dan konstruksi dengan standar terbaik untuk mendukung keberhasilan proyek Anda.',
            hero_title_1: 'Armada Alat Berat untuk Konstruksi Skala Besar',
            hero_subtitle_1: 'Pengadaan & Logistik Aset. Kami menyewakan berbagai alat berat industri dengan jaminan operasional tanpa gangguan dan pemeliharaan terdepan untuk proyek Anda.',
            hero_title_2: 'Fabrikasi Tepat Guna dan Instalasi Ahli',
            hero_subtitle_2: 'Instalasi Presisi Tinggi. Tim spesialis kami siap menangani rekayasa manufaktur, silo baja, dan sistem conveyor berkapasitas besar dengan akurasi terukur.',
            hero_cta: 'Jelajahi Layanan Utama',
            // Profil
            profil_label: 'Profil Perusahaan KPP',
            profil_title: 'Kemitraan Jangka Panjang: Inovasi, Keahlian, & Integritas',
            profil_desc1: 'berkomitmen menghadirkan spesialisasi level tertinggi di bidang engineering, fabrikasi konstruksi, serta penyediaan sewa alat berat industri. Dengan puluhan tahun dedikasi pada standarisasi mutu lapangan, kami memastikan solusi rancang bangun yang mulus.',
            profil_desc2: 'Pengerahan armada berat kami dipadukan dengan strategi Procurement efektif sehingga setiap jam proyek Anda berjalan dengan traksi profitabilitas tertinggi tanpa ada penundaan sistem operasional.',
            profil_stat1: 'Technical Specialists', profil_stat2: 'ISO Compliance Level',
            // Services
            services_label: 'Cakupan Bisnis Logistik', services_title: 'Layanan <span style="color:var(--secondary-accent);">Kelas Dunia</span>',
            svc1_title: 'Engineering Heavy',
            svc1_desc: 'Spesialisasi fabrikasi presisi engineering sesuai spesifikasi blueprint lapangan. Merakit crusher plant raksasa, silo baja, dan sistem conveyor industri secara sistematis.',
            svc2_title: 'Construction Safety',
            svc2_desc: 'Pengerahan tim spesialis untuk memastikan solusi perakitan efisien, aman, dan berstandar internasional. Proses bongkar muat dan perakitan tower crane vertikal yang solid.',
            svc3_title: 'Procurement Assets',
            svc3_desc: 'Penyewaan dan penjualan aset suku cadang machinery kelas berat bermesin hidrolik tinggi untuk jaminan operasional berkelanjutan proyek Anda.',
            // Metrics
            metrics_label: 'Data & Keandalan Metrik',
            metrics_title: 'Pertumbuhan Skala Proyek & Rekam Jejak Sukses',
            metrics_desc1: 'Dalam 5 tahun terakhir, Kualitas Prima Prestasi menorehkan angka stabilitas luar biasa dalam pergerakan permintaan armada penyewaan konstruksi berskala nasional.',
            metrics_desc2: 'Grafik analitik ini secara gamblang merepresentasikan tingginya kepercayaan BUMN maupun swasta terhadap jaminan logistik instrumen engineering kami yang bebas kendala hambatan letak (Zero Downtime Tolerance).',
            metrics_cta: 'Lihat Bukti Kerja',
            // Portfolio
            port_label: 'Dapur Portofolio', port_title: 'Bukti Nyata Kinerja Proyek',
            port_explore: 'Eksplorasi Galeri Ekstensif',
            port1_tag: 'Instalasi Base', port1_title: 'Penanaman Fondasi Batching',
            port1_desc: 'Penyelesaian proyek instalasi struktural permanen di area mining sisi timur Jawa...',
            port2_tag: 'Fabrikasi Besi', port2_title: 'Konstruksi Tower Silo',
            port2_desc: 'Rekayasa manufaktur sistem penampung semen skala massal yang terkoneksi hidrolik mekanis...',
            port3_tag: 'Delivery Logistics', port3_title: 'Pengerahan Excavator PC200',
            port3_desc: 'Dukungan logistik penuh tanpa henti (24h shift) untuk pembersihan medan proyek kilang minyak...',
            // CTA & Footer
            cta_title: 'Siap Membangun Kemitraan Proyek Skala Besar dengan Kami?',
            cta_desc: 'Konsultasikan kebutuhan engineering, penyewaan alat berat, hingga konstruksi dengan tim tenaga ahli terbaik KPP.',
            cta_whatsapp: 'Hubungi via WhatsApp', cta_location: 'Lihat Lokasi Workshop',
            footer_desc: 'Perusahaan logistik alat berat berkualitas tinggi dengan layanan unggulan di bidang Engineering, Instalasi, Konstruksi dan Pengadaan terintegrasi.',
            footer_contact_title: 'Informasi Kantor', footer_workshop: 'Workshop Induk:',
            footer_phone: 'Layanan Telepon:', footer_email: 'Email Surat Resmi:',
            footer_quicklinks: 'Tautan Cepat',
            footer_link_profile: 'Profil Perusahaan', footer_link_services: 'Bidang Layanan Ahli',
            footer_link_catalog: 'Beranda Katalog Aset', footer_link_portfolio: 'Portofolio Kinerja',
            footer_link_staff: 'Staff Internal Portal',
            footer_rights: 'Hak Cipta Dilindungi Undang-Undang.',
            // Katalog page
            katalog_title: 'Katalog Produk & Peralatan',
            katalog_subtitle: 'Temukan beragam koleksi alat berat dan unit perlengkapan engineering terbaik dengan standar industri terverifikasi untuk mendukung kelancaran mega proyek Anda.',
            katalog_filter: 'Filter Kategori',
            katalog_search_btn: 'Terapkan Pencarian',
            katalog_cta_title: 'Butuh Solusi Kustom?',
            katalog_cta_desc: 'Konsultasikan kebutuhan mesin dan instalasi alat berat spesifik Anda.',
            katalog_cta_btn: 'Hubungi Teknisi',
            katalog_spec_btn: 'Lihat Spesifikasi',
            katalog_price_btn: 'Harga Penawaran',
            // Service Detail Pages
            detail_eng_title: 'Heavy <span style="color:var(--secondary-accent);">Engineering</span>',
            detail_eng_subtitle: 'Spesialisasi rekayasa fabrikasi presisi dengan standar kualitas industri kelas berat, mendukung visi infrastruktur masa depan Anda.',
            detail_eng_overview_title: 'Deskripsi Layanan',
            detail_eng_overview_desc: 'PT. Kualita Prima Prestasi menawarkan keahlian teknik tingkat lanjut dalam manufaktur struktural skala besar. Mulai dari merakit crusher plant raksasa yang berfungsi optimal secara mekanis, fabrikasi silo baja berkapasitas masif, hingga perakitan sistem conveyor belt industri kelas berat. Didukung oleh tim ahli tersertifikasi, kami menjamin implementasi blueprint lapangan dengan presisi sempurna dan efisiensi waktu yang tinggi.',
            detail_eng_benefit_title: 'Keunggulan Kami',
            detail_eng_benefit_1: 'Fabrikasi Presisi Tinggi dengan Standar ISO',
            detail_eng_benefit_2: 'Integrasi Sistem Mekanik & Elektrik Lanjutan',
            detail_eng_benefit_3: 'Dukungan Teknisi Bersertifikat Internasional',
            
            detail_con_title: 'Construction <span style="color:var(--secondary-accent);">Safety</span>',
            detail_con_subtitle: 'Solusi perakitan dan bongkar muat paling handal, menjamin keselamatan dan efisiensi di setiap tahap pembangunan vertikal yang solid.',
            detail_con_overview_title: 'Ringkasan Layanan',
            detail_con_overview_desc: 'Layanan konstruksi kami bukan sekadar membongkar dan memuat material. Kami mengerahkan tim spesialis yang sanggup merakit struktur kompleks seperti tower crane dengan tingkat keamanan maksimum (Zero Accident Policy). Kami menerapkan manajemen risiko di lapangan untuk memastikan setiap perakitan berjalan lancar layaknya jarum jam operasional yang presisi.',
            detail_con_benefit_1: 'Penerapan K3 (Keselamatan dan Kesehatan Kerja) Tingkat Tinggi',
            detail_con_benefit_2: 'Skema Bongkar Muat Zero-Downtime',
            detail_con_benefit_3: 'Sistem Perakitan Cepat Sesuai Tenggat Waktu',

            detail_pro_title: 'Procurement <span style="color:var(--secondary-accent);">Assets</span>',
            detail_pro_subtitle: 'Mitra pengadaan dan penyewaan aset heavy machinery dengan dukungan penuh logistik, memperkuat lini operasional proyek mega.',
            detail_pro_overview_title: 'Akses Ke Aset Industri',
            detail_pro_overview_desc: 'Efisiensi proyek bergantung pada keandalan armada alat berat yang Anda miliki. Kami memfasilitasi kebutuhan proyek Anda melalui sistem penyewaan (*rental*) jangka panjang maupun penjualan suku cadang *machinery* berat bermesin hidrolik tingkat elit. Dengan inventaris aset kami, produktivitas Anda akan selalu terjamin.',
            detail_pro_benefit_1: 'Ketersediaan Beragam Alat Berat (Excavator, Crane, dll)',
            detail_pro_benefit_2: 'Pemeliharaan Rutin & Garansi Kebersihan Mesin',
            detail_pro_benefit_3: 'Fleksibilitas Paket Sewa Perusahaan B2B',
            
            detail_back_btn: '<i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda',
            // Company Profile Page
            page_profil_title: 'Kemitraan Terpercaya:<br> <span style="color:var(--secondary-accent);">Inovasi, Keahlian, dan Keandalan</span>',
            page_profil_desc1: 'PT. KUALITA PRIMA PRESTASI berkomitmen menghadirkan layanan terbaik di bidang engineering, konstruksi, serta penjualan alat berat. Dengan pengalaman luas dan dedikasi pada mutu, kami menyediakan solusi yang dirancang khusus untuk memenuhi kebutuhan proyek industri, mulai dari tahap perencanaan hingga eksekusi pemasangan.',
            page_profil_desc2: 'Didukung oleh inovasi dan penerapan standar kerja yang tinggi, PT. KUALITA PRIMA PRESTASI adalah mitra ideal untuk mendukung keberhasilan proyek Anda dan mencapai hasil yang maksimal.',
            page_profil_clients: 'Clients', page_profil_projects: 'Projects', page_profil_repairing: 'Repairing',
            html_lang: 'id',
        },
        en: {
            // Navigation
            nav_home: 'Home', nav_profile: 'Profile', nav_services: 'Services',
            nav_catalog: 'Catalog', nav_portfolio: 'Portfolio', nav_contact: 'Contact',
            nav_staff: 'Staff Portal',
            nav_svc1: 'Engineering', nav_svc2: 'Construction', nav_svc3: 'Procurement',
            // Hero
            hero_title_0: 'Trusted Engineering & Construction Solutions',
            hero_subtitle_0: 'Innovation, Quality, Sustainability. PT. Kualita Prima Prestasi provides engineering and construction services with the highest standards to support the success of your project.',
            hero_title_1: 'Heavy Equipment Fleet for Large-Scale Construction',
            hero_subtitle_1: 'Procurement & Asset Logistics. We rent out a variety of industrial heavy equipment with continuous operational guarantees and leading maintenance for your projects.',
            hero_title_2: 'Purpose-Built Fabrication & Expert Installation',
            hero_subtitle_2: 'High-Precision Installation. Our specialist team handles manufacturing engineering, steel silos, and large-capacity conveyor systems with measurable accuracy.',
            hero_cta: 'Explore Our Services',
            // Profil
            profil_label: 'KPP Company Profile',
            profil_title: 'Long-Term Partnership: Innovation, Expertise, & Integrity',
            profil_desc1: 'is committed to delivering the highest level of specialization in engineering, construction fabrication, and heavy industrial equipment rental. With decades of dedication to field quality standards, we ensure seamless engineering solutions.',
            profil_desc2: 'Our heavy fleet deployment is combined with an effective Procurement strategy, ensuring every hour of your project runs at the highest profitability traction without any operational system delays.',
            profil_stat1: 'Technical Specialists', profil_stat2: 'ISO Compliance Level',
            // Services
            services_label: 'Logistics Business Scope', services_title: 'World-Class <span style="color:var(--secondary-accent);">Services</span>',
            svc1_title: 'Heavy Engineering',
            svc1_desc: 'Specializing in precision engineering fabrication according to field blueprint specifications. Assembling giant crusher plants, steel silos, and industrial conveyor systems systematically.',
            svc2_title: 'Construction Safety',
            svc2_desc: 'Deploying specialist teams to ensure efficient, safe, and internationally standardized assembly solutions. Solid vertical loading and tower crane assembly processes.',
            svc3_title: 'Procurement Assets',
            svc3_desc: 'Rental and sale of heavy-class machinery spare part assets with high hydraulic engines for sustained operational guarantees for your projects.',
            // Metrics
            metrics_label: 'Data & Reliability Metrics',
            metrics_title: 'Project Scale Growth & <br><span style="color:var(--secondary-accent);">Track Record of Success</span>',
            metrics_desc1: 'Over the past 5 years, Kualita Prima Prestasi has recorded remarkable stability figures in the movement of national-scale construction rental fleet demand.',
            metrics_desc2: 'This analytical chart clearly represents the high trust of SOEs and private entities in our engineering instrument logistics guarantees that are free from location obstacles (Zero Downtime Tolerance).',
            metrics_cta: 'View Our Work',
            // Portfolio
            port_label: 'Project Portfolio', port_title: 'Proven Project Performance',
            port_explore: 'Explore Extensive Gallery',
            port1_tag: 'Base Installation', port1_title: 'Batching Foundation Installation',
            port1_desc: 'Completion of permanent structural installation project in the east Java mining area...',
            port2_tag: 'Steel Fabrication', port2_title: 'Silo Tower Construction',
            port2_desc: 'Manufacturing engineering of a mass-scale cement storage system connected with mechanical hydraulics...',
            port3_tag: 'Delivery Logistics', port3_title: 'PC200 Excavator Deployment',
            port3_desc: 'Full non-stop logistics support (24h shift) for clearing the oil refinery project site...',
            // CTA & Footer
            cta_title: 'Ready to Build a Large-Scale Project Partnership with Us?',
            cta_desc: 'Consult your engineering, heavy equipment rental, and construction needs with the KPP team of expert professionals.',
            cta_whatsapp: 'Contact via WhatsApp', cta_location: 'View Workshop Location',
            footer_desc: 'A premium heavy equipment logistics company specializing in Engineering, Installation, Construction and integrated Procurement services.',
            footer_contact_title: 'Office Information', footer_workshop: 'Main Workshop:',
            footer_phone: 'Phone Service:', footer_email: 'Official Email:',
            footer_quicklinks: 'Quick Links',
            footer_link_profile: 'Company Profile', footer_link_services: 'Expert Services',
            footer_link_catalog: 'Asset Catalog', footer_link_portfolio: 'Project Portfolio',
            footer_link_staff: 'Staff Internal Portal',
            footer_rights: 'All Rights Reserved.',
            // Katalog page
            katalog_title: 'Product & Equipment Catalog',
            katalog_subtitle: 'Discover our diverse collection of heavy equipment and premium engineering units with verified industry standards to support the success of your mega projects.',
            katalog_filter: 'Filter Category',
            katalog_search_btn: 'Apply Search',
            katalog_cta_title: 'Need a Custom Solution?',
            katalog_cta_desc: 'Consult your specific machinery and heavy equipment installation needs.',
            katalog_cta_btn: 'Contact Technician',
            katalog_spec_btn: 'View Specifications',
            katalog_price_btn: 'Get Price Quote',
            // Service Detail Pages
            detail_eng_title: 'Heavy <span style="color:var(--secondary-accent);">Engineering</span>',
            detail_eng_subtitle: 'Specialized in precision engineering fabrication with heavy-industry quality standards, supporting your future infrastructure vision.',
            detail_eng_overview_title: 'Service Description',
            detail_eng_overview_desc: 'PT. Kualita Prima Prestasi offers advanced engineering expertise in large-scale structural manufacturing. From assembling mechanically optimal giant crusher plants, massive capacity steel silo fabrication, to assembling heavy-duty industrial conveyor belt systems. Backed by certified experts, we guarantee the implementation of field blueprints with perfect precision and high time efficiency.',
            detail_eng_benefit_title: 'Our Advantages',
            detail_eng_benefit_1: 'High Precision Fabrication with ISO Standards',
            detail_eng_benefit_2: 'Advanced Mechanical & Electrical System Integration',
            detail_eng_benefit_3: 'Internationally Certified Technician Support',
            
            detail_con_title: 'Construction <span style="color:var(--secondary-accent);">Safety</span>',
            detail_con_subtitle: 'The most reliable assembly and loading/unloading solutions, ensuring safety and efficiency in every stage of solid vertical construction.',
            detail_con_overview_title: 'Service Summary',
            detail_con_overview_desc: 'Our construction services are not just about loading and unloading materials. We deploy specialist teams capable of assembling complex structures like tower cranes with maximum safety (Zero Accident Policy). We implement field risk management to ensure every assembly runs as smoothly as a precision operational clock.',
            detail_con_benefit_1: 'High-Level Implementation of OHS (Occupational Health & Safety)',
            detail_con_benefit_2: 'Zero-Downtime Loading and Unloading Scheme',
            detail_con_benefit_3: 'Rapid Assembly System Meeting Deadlines',

            detail_pro_title: 'Procurement <span style="color:var(--secondary-accent);">Assets</span>',
            detail_pro_subtitle: 'Procurement and heavy machinery rental partner with full logistical support, strengthening mega project operational lines.',
            detail_pro_overview_title: 'Access to Industrial Assets',
            detail_pro_overview_desc: 'Project efficiency relies on the reliability of your heavy equipment fleet. We facilitate your project needs through long-term rental systems as well as the sale of elite hydraulic heavy machinery spare parts. With our asset inventory, your productivity will always be guaranteed.',
            detail_pro_benefit_1: 'Availability of Various Heavy Equipment (Excavators, Cranes, etc)',
            detail_pro_benefit_2: 'Routine Maintenance & Engine Cleanliness Guarantee',
            detail_pro_benefit_3: 'Flexible B2B Corporate Rental Packages',
            
            detail_back_btn: '<i class="fa-solid fa-arrow-left"></i> Back to Home',
            // Company Profile Page
            page_profil_title: 'Trusted Partnership:<br> <span style="color:var(--secondary-accent);">Innovation, Expertise, and Reliability</span>',
            page_profil_desc1: 'PT. KUALITA PRIMA PRESTASI is committed to delivering the best services in engineering, construction, and heavy equipment sales. With extensive experience and a dedication to quality, we provide custom-designed solutions to meet industrial project needs, from the planning stage to installation execution.',
            page_profil_desc2: 'Supported by innovation and the implementation of high working standards, PT. KUALITA PRIMA PRESTASI is the ideal partner to support the success of your project and achieve maximum results.',
            page_profil_clients: 'Clients', page_profil_projects: 'Projects', page_profil_repairing: 'Repairing',
            html_lang: 'en',
        },
        zh: {
            // Navigation
            nav_home: '首页', nav_profile: '公司简介', nav_services: '服务',
            nav_catalog: '产品目录', nav_portfolio: '工程案例', nav_contact: '联系我们',
            nav_staff: '员工门户',
            nav_svc1: 'Engineering', nav_svc2: 'Construction', nav_svc3: 'Procurement',
            // Hero
            hero_title_0: '值得信赖的工程与建筑解决方案',
            hero_subtitle_0: '创新、质量、可持续性。PT. Kualita Prima Prestasi 以最高标准提供工程和建筑服务，支持您项目的成功。',
            hero_title_1: '适用于大规模建筑的重型设备车队',
            hero_subtitle_1: '采购与资产物流。我们出租各种工业重型设备，并为您的项目提供不间断的运行保证和领先的维护。',
            hero_title_2: '定制制造与专家安装',
            hero_subtitle_2: '高精度安装。我们的专家团队以可衡量的精度处理制造工程、钢制筒仓和大容量输送系统。',
            hero_cta: '探索我们的服务',
            // Profil
            profil_label: 'KPP 公司简介',
            profil_title: '长期合作伙伴关系：创新、专业技能与诚信',
            profil_desc1: '致力于在工程、建筑制造及工业重型设备租赁领域提供最高水平的专业化服务。凭借数十年对现场质量标准的专注，我们确保工程解决方案的顺畅实施。',
            profil_desc2: '我们的重型机队部署结合有效的采购策略，确保您的项目每小时都以最高盈利效率运行，不存在任何运营系统延误。',
            profil_stat1: '技术专家', profil_stat2: 'ISO 合规水平',
            // Services
            services_label: '物流业务范围', services_title: '世界级 <span style="color:var(--secondary-accent);">服务</span>',
            svc1_title: '重型工程',
            svc1_desc: '专注于按照现场蓝图规格进行精密工程制造。系统化组装大型破碎机厂、钢制筒仓和工业输送系统。',
            svc2_title: '建筑安全',
            svc2_desc: '派遣专家团队，确保高效、安全且符合国际标准的装配解决方案。坚固的垂直装卸和塔吊组装流程。',
            svc3_title: '采购资产',
            svc3_desc: '租赁和销售配备高液压发动机的重型机械备件资产，为您的项目提供持续运营保障。',
            // Metrics
            metrics_label: '数据与可靠性指标',
            metrics_title: '项目规模增长与<br><span style="color:var(--secondary-accent);">成功记录</span>',
            metrics_desc1: '在过去5年中，Kualita Prima Prestasi在全国规模建筑租赁机队需求变动中记录了非凡的稳定数字。',
            metrics_desc2: '这张分析图表清晰地展示了国有企业和私人企业对我们工程仪器物流保障的高度信任，完全无场所障碍（零停机容忍度）。',
            metrics_cta: '查看我们的工作',
            // Portfolio
            port_label: '项目案例库', port_title: '项目绩效的实际证明',
            port_explore: '探索更多案例',
            port1_tag: '基础安装', port1_title: '搅拌站基础安装',
            port1_desc: '在爪哇岛东部采矿区完成的永久性结构安装项目...',
            port2_tag: '钢铁制造', port2_title: '筒仓塔建造',
            port2_desc: '与机械液压装置相连的大型水泥储存系统的制造工程...',
            port3_tag: '物流配送', port3_title: '挖掘机PC200部署',
            port3_desc: '为石油精炼厂项目现场清理提供全天候不间断物流支持（24小时轮班）...',
            // CTA & Footer
            cta_title: '准备好与我们建立大规模项目合作伙伴关系了吗？',
            cta_desc: '与KPP专家团队咨询您的工程、重型设备租赁和建筑需求。',
            cta_whatsapp: '通过 WhatsApp 联系', cta_location: '查看工厂位置',
            footer_desc: '高品质重型设备物流公司，专注于工程、安装、建筑和采购综合服务。',
            footer_contact_title: '办公室信息', footer_workshop: '主要工厂:',
            footer_phone: '电话服务:', footer_email: '官方邮箱:',
            footer_quicklinks: '快速链接',
            footer_link_profile: '公司简介', footer_link_services: '专业服务',
            footer_link_catalog: '资产目录', footer_link_portfolio: '项目案例',
            footer_link_staff: '内部员工门户',
            footer_rights: '版权所有。',
            // Katalog page
            katalog_title: '产品与设备目录',
            katalog_subtitle: '发现我们多样化的重型设备和优质工程单元系列，符合经过验证的行业标准，支持您大型项目的成功。',
            katalog_filter: '筛选类别',
            katalog_search_btn: '应用搜索',
            katalog_cta_title: '需要定制解决方案？',
            katalog_cta_desc: '咨询您的具体机械和重型设备安装需求。',
            katalog_cta_btn: '联系技术员',
            katalog_spec_btn: '查看规格',
            katalog_price_btn: '获取报价',
            // Service Detail Pages
            detail_eng_title: '重型 <span style="color:var(--secondary-accent);">工程</span>',
            detail_eng_subtitle: '专注于具有重工业质量标准的精密工程制造，支持您未来的基础设施愿景。',
            detail_eng_overview_title: '服务说明',
            detail_eng_overview_desc: 'PT. Kualita Prima Prestasi 在大型结构制造领域提供先进的工程技术。从组装机械性能最佳的巨型破碎机厂，大容量钢制筒仓制造，到组装重型工业输送带系统。在认证专家的支持下，我们保证以完美的精度和高时间效率实施现场蓝图。',
            detail_eng_benefit_title: '我们的优势',
            detail_eng_benefit_1: '符合ISO标准的高精度制造',
            detail_eng_benefit_2: '先进的机械与电气系统集成',
            detail_eng_benefit_3: '国际认证技术人员支持',
            
            detail_con_title: '建筑 <span style="color:var(--secondary-accent);">安全</span>',
            detail_con_subtitle: '最可靠的组装和装卸解决方案，确保坚固垂直施工各个阶段的安全和效率。',
            detail_con_overview_title: '服务摘要',
            detail_con_overview_desc: '我们的建筑服务不仅仅是装卸材料。我们部署的专家团队能够以最高安全性（零事故政策）组装塔式起重机等复杂结构。我们实施现场风险管理，以确保每个装配都像精确的运行时间一样顺利运行。',
            detail_con_benefit_1: '高水平实施OHS（职业健康与安全）',
            detail_con_benefit_2: '零停机装卸方案',
            detail_con_benefit_3: '符合截止日期的快速组装系统',

            detail_pro_title: '采购 <span style="color:var(--secondary-accent);">资产</span>',
            detail_pro_subtitle: '提供全方位物流支持的重型机械采购和租赁合作伙伴，加强大型项目运营线。',
            detail_pro_overview_title: '获取工业资产',
            detail_pro_overview_desc: '项目效率取决于重型设备车队的可靠性。我们通过长期租赁系统以及销售先进液压重型机械备件来满足您的项目需求。有了我们的资产库存，您的生产力将始终得到保证。',
            detail_pro_benefit_1: '提供各种重型设备（挖掘机、起重机等）',
            detail_pro_benefit_2: '日常维护和发动机清洁度保证',
            detail_pro_benefit_3: '灵活的B2B企业租赁套餐',
            
            detail_back_btn: '<i class="fa-solid fa-arrow-left"></i> 返回首页',
            // Company Profile Page
            page_profil_title: '值得信赖的合作伙伴:<br> <span style="color:var(--secondary-accent);">创新、专业与可靠性</span>',
            page_profil_desc1: 'PT. KUALITA PRIMA PRESTASI 致力于在工程、建筑和重型设备销售领域提供最佳服务。凭借丰富的经验和对质量的专注，我们提供定制设计的解决方案，以满足从规划阶段到安装执行的工业项目需求。',
            page_profil_desc2: '在创新和高工作标准的实施支持下，PT. KUALITA PRIMA PRESTASI 是支持您项目成功并取得最大成果的理想合作伙伴。',
            page_profil_clients: '客户数', page_profil_projects: '项目数', page_profil_repairing: '设备维修',
            html_lang: 'zh',
        }
    };

    // -------------------------------------------------------
    // Core: Apply a language to the whole page
    // SAFE: only updates leaf-level text via textContent.
    // Does NOT touch elements that contain child elements.
    // -------------------------------------------------------
    function applyLanguage(lang) {
        const t = translations[lang];
        if (!t) return;

        // Update every [data-i18n] element — safely
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (t[key] === undefined) return;               // key not found → skip

            // Custom coloring for profile title based on colon
            if (key === 'profil_title') {
                const parts = t[key].split(':');
                if (parts.length > 1) {
                    el.innerHTML = `${parts[0]}:<br><span style="color: var(--secondary-accent);">${parts.slice(1).join(':')}</span>`;
                } else if (t[key].includes('：')) {
                    const cParts = t[key].split('：');
                    el.innerHTML = `${cParts[0]}：<br><span style="color: var(--secondary-accent);">${cParts.slice(1).join('：')}</span>`;
                } else {
                    el.textContent = t[key];
                }
                return;
            }

            // Custom HTML attribute handling
            if (el.hasAttribute('data-i18n-html')) {
                el.innerHTML = t[key];
                return;
            }

            if (el.children.length > 0) return;             // has child elements → SKIP (safety guard)
            el.textContent = t[key];                        // only change pure-text nodes
        });

        // Update <html lang>
        const htmlEl = document.getElementById('html-root');
        if (htmlEl) htmlEl.setAttribute('lang', t.html_lang);

        const langLabels = { id: 'Ind', en: 'Eng', zh: 'Chn' };

        // Update the label text inside each footer lang-btn (only the .lang-label span)
        document.querySelectorAll('.lang-btn').forEach(btn => {
            const btnLang  = btn.getAttribute('data-lang');
            const labelEl  = btn.querySelector('.lang-label');
            if (labelEl && langLabels[btnLang]) {
                labelEl.textContent = langLabels[btnLang]; // always stays in its own language
            }
            // Toggle active class
            btn.classList.toggle('active', btnLang === lang);
        });

        // Persist preference
        localStorage.setItem('kpp_lang', lang);
    }

    // -------------------------------------------------------
    // Event delegation — catches all .lang-btn clicks
    // Works even if buttons are added after DOM load
    // -------------------------------------------------------
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.lang-btn');
        if (btn) {
            const lang = btn.getAttribute('data-lang');
            if (lang && translations[lang]) applyLanguage(lang);
        }
    });

    // Load saved preference on page load
    const savedLang = localStorage.getItem('kpp_lang') || 'id';
    applyLanguage(savedLang);


    // =========================================================
    // 6. Chart.js — Project Volume Chart (Looping Animation)
    // =========================================================
    const chartCanvas = document.getElementById('serviceGrowthChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        let chartRendered = false;
        let myChart = null;

        const renderChart = () => {
            if (myChart) myChart.destroy(); // Destroy previous instance to restart the grow animation
            myChart = new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: ['2020', '2021', '2022', '2023', '2024 (YTD)'],
                    datasets: [
                        { label: 'Proyek Engineering',  data: [15, 22, 35, 48, 65],  borderColor: '#0b1a45', backgroundColor: 'rgba(11, 26, 69, 0.1)', fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6 },
                        { label: 'Layanan Konstruksi',  data: [10, 15, 25, 40, 55],  borderColor: '#00a3ff', backgroundColor: 'rgba(0, 163, 255, 0.1)', fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6 },
                        { label: 'Rental Alat Berat',   data: [30, 45, 60, 85, 110], borderColor: '#4b38aa', backgroundColor: 'rgba(75, 56, 170, 0.1)', fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 1800, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { position: 'top', labels: { font: { family: "'Open Sans', sans-serif", size: 13 } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: 'rgba(0,0,0,0.05)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
            chartRendered = true;
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !chartRendered) {
                    renderChart();
                    // Setup 5-second interval loop for animation refresh
                    setInterval(() => {
                        if(entry.isIntersecting) renderChart();
                    }, 5000);
                }
            });
        }, { threshold: 0.5 });

        observer.observe(chartCanvas);
    }

    // =========================================================
    // 7. Background Auto-Slider
    // =========================================================
    const heroTitle = document.getElementById('hero-title');
    const heroSubtitle = document.getElementById('hero-subtitle');
    const slideBgs = document.querySelectorAll('.hero-slide-bg');

    if (slideBgs.length > 0 && heroTitle && heroSubtitle) {
        let slideIndex = 0;
        
        setInterval(() => {
            heroTitle.classList.add('fade-out');
            heroSubtitle.classList.add('fade-out');
            
            setTimeout(() => {
                // Change background smoothly
                slideBgs[slideIndex].classList.remove('active');
                slideIndex = (slideIndex + 1) % slideBgs.length;
                slideBgs[slideIndex].classList.add('active');
                
                // Update translations
                heroTitle.setAttribute('data-i18n', `hero_title_${slideIndex}`);
                heroSubtitle.setAttribute('data-i18n', `hero_subtitle_${slideIndex}`);
                
                const currentLang = localStorage.getItem('kpp_lang') || 'id';
                applyLanguage(currentLang);
                
                heroTitle.classList.remove('fade-out');
                heroSubtitle.classList.remove('fade-out');
            }, 1000);
        }, 6000);
    }

    // =========================================================
    // 8. Portfolio Slider
    // =========================================================
    const portfolioTrack = document.getElementById('portfolioTrack');
    const portNext = document.getElementById('port-next');
    const portPrev = document.getElementById('port-prev');

    if (portfolioTrack && portNext && portPrev) {
        // Asumsi card weight sekitar 340px termasuk margin/gap
        const scrollAmount = 340; 
        
        portNext.addEventListener('click', () => {
            portfolioTrack.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
        
        portPrev.addEventListener('click', () => {
            portfolioTrack.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
    }

    // =========================================================
    // 9. Technical Specialist Counter Animation
    // =========================================================
    const techCounterElement = document.getElementById('techSpecialistCounter');
    if (techCounterElement) {
        const animateTechCounter = () => {
            let start = 0;
            const end = 10;
            const duration = 1500; // 1.5 seconds animation
            const frameRate = 30;
            const increment = end / (duration / frameRate);
            
            // clear it first
            techCounterElement.textContent = '0+';

            const timer = setInterval(() => {
                start += increment;
                if (start >= end) {
                    clearInterval(timer);
                    techCounterElement.textContent = end + '+';
                } else {
                    techCounterElement.textContent = Math.floor(start) + '+';
                }
            }, frameRate);
        };
        
        // Initial run
        animateTechCounter();
        
        // Refresh every 4 seconds
        setInterval(() => {
            animateTechCounter();
        }, 4000);
    }

    // =========================================================
    // 10. Profile Metrics Counter Animation (5 seconds loop)
    // =========================================================
    const metricElements = document.querySelectorAll('.metric-animate');
    if (metricElements.length > 0) {
        const animateMetrics = () => {
            metricElements.forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                const duration = 2500; // 2.5 seconds duration
                let startTime = null;
                
                el.textContent = '0+';
                el.classList.add('text-outline-active'); // Keep it always blue for better design match
                
                const step = (timestamp) => {
                    if (!startTime) startTime = timestamp;
                    const progress = Math.min((timestamp - startTime) / duration, 1);
                    // easeOutQuart easing for very smooth slowing down effect
                    const easeOutProgress = 1 - Math.pow(1 - progress, 4);
                    
                    const objVal = Math.floor(easeOutProgress * target);
                    el.textContent = objVal + '+';
                    
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        el.textContent = target + '+';
                    }
                };
                
                window.requestAnimationFrame(step);
            });
        };
        
        // Initial run
        animateMetrics();
        
        // Loop continuously every 5.5 seconds (2.5s animation + 3s hold)
        setInterval(animateMetrics, 5500);
    }

});
