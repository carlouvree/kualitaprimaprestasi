<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');
$current_page = 'tugas';

if(!isset($_GET['id'])) { header("Location: tugas.php"); exit; }
$task_id = (int)$_GET['id'];

// Fetch Task Info
$stmt = $pdo->prepare("SELECT t.*, u.full_name as staff_name, a.full_name as admin_name 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN users a ON t.assigned_by = a.id 
    WHERE t.id = ?");
$stmt->execute([$task_id]);
$t = $stmt->fetch();

if(!$t) { header("Location: tugas.php"); exit; }

// Fetch Materials
$mats = $pdo->prepare("SELECT * FROM project_materials WHERE task_id = ? ORDER BY id ASC");
$mats->execute([$task_id]);
$materials = $mats->fetchAll();
$matTotal = array_sum(array_column($materials, 'total_price'));

// Fetch Daily Reports linked to this task
$reps = $pdo->prepare("SELECT pr.*, u.full_name 
    FROM project_reports pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.task_id = ? 
    ORDER BY pr.report_date DESC, pr.created_at DESC");
$reps->execute([$task_id]);
$reports = $reps->fetchAll();

// Dana diterima info
try {
    $danaDiterimaSql = $pdo->prepare("SELECT dana_diterima_confirmed, dana_diterima_photo, dana_diterima_at FROM tasks WHERE id=?");
    $danaDiterimaSql->execute([$task_id]);
    $danaRow = $danaDiterimaSql->fetch();
} catch(Exception $e) { $danaRow = null; }

// Handle Word Download logic in Admin
if(isset($_GET['download_word']) && (int)$_GET['download_word'] === $task_id) {
    if($t['lpj_type'] == 'digital') {
        $data = json_decode($t['lpj_content_json'], true);
        header("Content-Type: application/vnd.ms-word");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("content-disposition: attachment;filename=LPJ_".str_replace(' ','_',$t['title'])."_".date('Ymd').".doc");
        echo "<html><head><meta charset='utf-8'><style>body{font-family:Arial;line-height:1.6}.header{text-align:center;border-bottom:2px solid #000;margin-bottom:20px}.section{margin-bottom:20px}.label{font-weight:bold;background:#edf2f7;padding:5px}</style></head><body>
            <div class='header'><h1>PT KUALITA PRIMA</h1><h3>LAPORAN PERTANGGUNGJAWABAN PROYEK</h3></div>
            <p><b>Kode Proyek:</b> ".($t['project_code']??'-')."</p><p><b>Nama Proyek:</b> ".$t['title']."</p>
            <p><b>Staff Pelaksana:</b> ".$t['staff_name']."</p><p><b>Tanggal:</b> ".date('d F Y', strtotime($data['created_at']))."</p>
            <div class='section'><div class='label'>1. RINGKASAN PEKERJAAN</div><div>".nl2br(htmlspecialchars($data['overview']))."</div></div>
            <div class='section'><div class='label'>2. RINCIAN AKTIVITAS</div><div>".nl2br(htmlspecialchars($data['activities']))."</div></div>
            <div class='section'><div class='label'>3. KENDALA</div><div>".nl2br(htmlspecialchars($data['obstacles'] ?: 'Tidak ada'))."</div></div>
            <div class='section'><div class='label'>4. KESIMPULAN</div><div>".nl2br(htmlspecialchars($data['conclusion']))."</div></div>
        </body></html>";
        exit;
    }
}


$categoryLabels = ['engineering'=>'Engineering','procurement'=>'Procurement','construction'=>'Construction','other'=>'Lainnya'];
$priorityColors = ['low'=>'#64748b', 'medium'=>'#f59e0b', 'high'=>'#ef4444', 'urgent'=>'#dc2626'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Proyek: <?= htmlspecialchars($t['project_code'] ?: $t['title']) ?> — Admin KPP</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <style>
        .project-header-card { 
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white; padding: 30px; border-radius: var(--radius); margin-bottom: 25px;
            box-shadow: var(--shadow-lg); position: relative; overflow: hidden;
        }
        .project-header-card::after {
            content: '\f0ae'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; right: -20px; bottom: -20px; font-size: 150px;
            opacity: 0.1; transform: rotate(-15deg);
        }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .project-id-badge { background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: 'Montserrat'; backdrop-filter: blur(5px); }
        .project-status-large { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        
        .timeline-container { position: relative; padding-left: 30px; margin-top: 20px; }
        .timeline-container::before { content: ''; position: absolute; left: 7px; top: 0; bottom: 0; width: 2px; background: var(--border); }
        .timeline-item { position: relative; margin-bottom: 25px; }
        .timeline-dot { position: absolute; left: -30px; width: 16px; height: 16px; border-radius: 50%; background: var(--border); border: 3px solid white; box-shadow: var(--shadow-sm); z-index: 2; }
        .timeline-dot.active { background: var(--accent-color); border-color: #e0e7ff; }
        .timeline-content { background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid var(--border); }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 25px; }
        .info-box { background: white; padding: 15px; border-radius: 10px; border: 1px solid var(--border); }
        .info-box .label { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; margin-bottom: 5px; }
        .info-box .value { font-size: 14px; font-weight: 700; color: var(--text-dark); }
        
        .section-title { font-size: 16px; font-weight: 800; color: var(--text-dark); font-family: 'Montserrat'; margin: 30px 0 15px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid var(--bg-gray); padding-bottom: 8px; }
        .section-title i { color: var(--accent-color); }
    </style>
</head>
<body>
<?php require_once '../includes/admin_sidebar.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <a href="tugas.php" class="btn btn-icon btn-outline"><i class="fa-solid fa-arrow-left"></i></a>
            <div><h1>Detail Proyek</h1><p><?= htmlspecialchars($t['title']) ?></p></div>
        </div>
        <div class="topbar-right" style="display:flex;gap:10px;">
            <div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?><?= htmlspecialchars($_SESSION['username']) ?></div>
            <a href="laporan_pdf.php?type=keuangan&task_id=<?= $task_id ?>" target="_blank" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);"><i class="fa-solid fa-file-pdf"></i> Export PDF</a>
        </div>
    </div>
    <div class="portal-content">
        <div class="project-header-card">
            <div class="header-top">
                <div class="project-id-badge">ID: <?= htmlspecialchars($t['project_code'] ?: '#'.$t['id']) ?></div>
                <div class="project-status-large">
                    <span class="badge badge-<?= $t['status'] ?>" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);"><?= strtoupper($t['status']) ?></span>
                </div>
            </div>
            <h1 style="font-family:'Montserrat',sans-serif; font-size:28px; font-weight:800; margin-bottom:10px;"><?= htmlspecialchars($t['title']) ?></h1>
            <p style="opacity:0.9; font-size:14px; max-width:700px;"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
            
            <div style="display:flex; gap:20px; margin-top:25px; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-user-gear"></i> Staff: <strong><?= htmlspecialchars($t['staff_name'] ?: 'N/A') ?></strong></div>
                <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-calendar"></i> DL: <strong><?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : '-' ?></strong></div>
                <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-tags"></i> Kategori: <strong><?= $categoryLabels[$t['project_category']] ?? 'Lainnya' ?></strong></div>
            </div>
        </div>

        <!-- Custom responsive class for detail layout -->
        <style>
            .detail-main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
            @media (max-width: 900px) { .detail-main-grid { grid-template-columns: 1fr; } }
        </style>
        <div class="detail-main-grid">
            <div class="left-col">
                <!-- Project Overview Info -->
                <div class="section-title"><i class="fa-solid fa-circle-info"></i> Informasi Lapangan & Klien</div>
                <div class="info-grid">
                    <div class="info-box">
                        <div class="label">Klien / Perusahaan</div>
                        <div class="value"><?= htmlspecialchars($t['client_name'] ?: '-') ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">PIC Klien</div>
                        <div class="value"><?= htmlspecialchars($t['client_pic'] ?: '-') ?> (<?= htmlspecialchars($t['client_pic_phone'] ?: '-') ?>)</div>
                    </div>
                    <div class="info-box" style="grid-column: span 2;">
                        <div class="label">Lokasi Proyek</div>
                        <div class="value"><i class="fa-solid fa-location-dot" style="color:var(--danger);"></i> <?= htmlspecialchars($t['project_location'] ?: '-') ?></div>
                    </div>
                </div>

                <!-- Procurement Section -->
                <div class="section-title"><i class="fa-solid fa-boxes-stacked"></i> Pengadaan Barang & Material</div>
                <div class="card">
                    <div class="card-body" style="padding:0;">
                        <table class="data-table">
                            <thead><tr><th>Nama Barang</th><th>Kategori</th><th>Qty</th><th>Harga Unit</th><th>Total</th><th>Supplier</th></tr></thead>
                            <tbody>
                            <?php if(empty($materials)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-light);">Tidak ada pengadaan barang.</td></tr>
                            <?php else: foreach($materials as $m): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($m['item_name']) ?></strong></td>
                                    <td><span class="badge badge-info" style="font-size:10px;"><?= ucfirst($m['item_category']) ?></span></td>
                                    <td><?= $m['quantity'] ?> <?= htmlspecialchars($m['unit']) ?></td>
                                    <td>Rp <?= number_format($m['unit_price'], 0, ',', '.') ?></td>
                                    <td style="font-weight:700;">Rp <?= number_format($m['total_price'], 0, ',', '.') ?></td>
                                    <td><small><?= htmlspecialchars($m['supplier'] ?: '-') ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                            <tfoot>
                                <tr style="backgroundCount:var(--bg-gray);">
                                    <td colspan="4" style="text-align:right; font-weight:700; padding:12px;">Total Material:</td>
                                    <td colspan="2" style="font-weight:800; color:var(--success); font-size:15px; padding:12px;">Rp <?= number_format($matTotal, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Daily Reports Timeline -->
                <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Histori Laporan Harian</div>
                <?php if(empty($reports)): ?>
                    <div class="empty-state" style="background:white; border-radius:12px; border:1px solid var(--border);"><i class="fa-solid fa-file-circle-xmark"></i><p>Belum ada laporan harian untuk proyek ini.</p></div>
                <?php else: ?>
                    <div class="timeline-container">
                        <?php foreach($reports as $r): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot active"></div>
                            <div class="timeline-content">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                    <div>
                                        <div style="font-weight:800; color:var(--text-dark); font-size:14px;"><?= date('l, d F Y', strtotime($r['report_date'])) ?></div>
                                        <small style="color:var(--text-light);"><i class="fa-regular fa-clock"></i> Submit: <?= date('H:i', strtotime($r['created_at'])) ?></small>
                                    </div>
                                    <span class="badge badge-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span>
                                </div>
                                <p style="font-size:13px; line-height:1.6; color:var(--text-dark);"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                                <?php if($r['photo_evidence']): ?>
                                    <img src="../uploads/reports/<?= htmlspecialchars($r['photo_evidence']) ?>" style="width:100px; height:70px; object-fit:cover; border-radius:8px; margin-top:10px; cursor:pointer;" onclick="window.open(this.src)">
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="right-col">
                <!-- Accommodation Summary -->
                <div class="section-title"><i class="fa-solid fa-wallet"></i> Budget & Akomodasi</div>
                <div class="card" style="margin-bottom:25px;">
                    <div class="card-body">
                        <div style="text-align:center; padding-bottom:20px; border-bottom:1px solid var(--border); margin-bottom:20px;">
                            <small style="color:var(--text-light); font-weight:700; text-transform:uppercase; letter-spacing:1px; font-size:11px;">Total Budget Alokasi</small>
                            <div style="font-size:24px; font-weight:800; color:var(--success); font-family:'Montserrat';">Rp <?= number_format($t['total_budget'], 0, ',', '.') ?></div>
                        </div>
                        <ul style="list-style:none; font-size:13px;">
                            <li style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                <span style="color:var(--text-light);"><i class="fa-solid fa-car"></i> Transport</span>
                                <strong>Rp <?= number_format($t['transport_cost'] ?: 0, 0, ',', '.') ?></strong>
                            </li>
                            <li style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                <span style="color:var(--text-light);"><i class="fa-solid fa-hotel"></i> Hotel</span>
                                <strong>Rp <?= number_format($t['hotel_cost'] ?: 0, 0, ',', '.') ?></strong>
                            </li>
                            <li style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                <span style="color:var(--text-light);"><i class="fa-solid fa-utensils"></i> Uang Makan</span>
                                <strong>Rp <?= number_format($t['meal_allowance'] ?: 0, 0, ',', '.') ?></strong>
                            </li>
                            <li style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                <span style="color:var(--text-light);"><i class="fa-solid fa-boxes-stacked"></i> Material</span>
                                <strong>Rp <?= number_format($matTotal, 0, ',', '.') ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Dana Diterima Status -->
                <div class="section-title"><i class="fa-solid fa-money-bill-wave" style="color:var(--success);"></i> Konfirmasi Dana</div>
                <div class="card" style="margin-bottom:25px;">
                    <div class="card-body">
                        <?php if(!empty($danaRow) && !empty($danaRow['dana_diterima_confirmed'])): ?>
                            <div style="text-align:center; padding-bottom:15px;">
                                <div style="width:60px;height:60px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><i class="fa-solid fa-check-circle" style="font-size:28px;color:var(--success);"></i></div>
                                <strong style="color:var(--success);">Dana Sudah Diterima Staff</strong><br>
                                <small style="color:var(--text-light);"><?= !empty($danaRow['dana_diterima_at']) ? date('d M Y, H:i', strtotime($danaRow['dana_diterima_at'])).' WIB' : '' ?></small>
                            </div>
                            <?php if(!empty($danaRow['dana_diterima_photo'])): ?>
                                <div>
                                    <p style="font-size:12px;color:var(--text-light);margin-bottom:5px;"><i class="fa-solid fa-image"></i> Bukti Transfer:</p>
                                    <img src="../uploads/proyek/<?= htmlspecialchars($danaRow['dana_diterima_photo']) ?>" style="width:100%;border-radius:10px;cursor:pointer;max-height:200px;object-fit:cover;" onclick="window.open(this.src)" title="Klik untuk besar">
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:20px; color:var(--text-light);">
                                <i class="fa-solid fa-hourglass-half" style="font-size:32px; color:var(--border); margin-bottom:10px; display:block;"></i>
                                <p style="font-size:13px;">Staff belum mengkonfirmasi penerimaan dana.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Final Evidence -->
                <div class="section-title"><i class="fa-solid fa-check-double"></i> Bukti Penyelesaian</div>
                <div class="card">
                    <div class="card-body">
                        <?php if($t['photo_evidence']): ?>
                            <img src="../uploads/proyek/<?= htmlspecialchars($t['photo_evidence']) ?>" style="width:100%; height:200px; object-fit:cover; border-radius:12px; margin-bottom:15px; border:1px solid var(--border);">
                            <div style="font-weight:700; font-size:13px; color:var(--text-dark); margin-bottom:5px;">Catatan Akhir:</div>
                            <p style="font-size:13px; color:var(--text-light); line-height:1.5;"><?= nl2br(htmlspecialchars($t['completion_notes'] ?: '-')) ?></p>
                            <?php if($t['lpj_file']): ?>
                                <a href="../uploads/lpj/<?= htmlspecialchars($t['lpj_file']) ?>" target="_blank" class="btn btn-outline btn-block" style="margin-top:15px;"><i class="fa-solid fa-file-pdf"></i> Lihat Dokumen LPJ (Manual)</a>
                            <?php elseif($t['lpj_type'] == 'digital' && $t['lpj_content_json']): 
                                $lpjData = json_decode($t['lpj_content_json'], true);
                            ?>
                                <div style="margin-top:20px; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                                    <h4 style="font-size:13px; font-family:'Montserrat'; margin-bottom:12px; color:var(--accent-color); border-bottom:1px solid #e2e8f0; padding-bottom:8px;"><i class="fa-solid fa-file-contract"></i> Laporan Digital LPJ</h4>
                                    
                                    <div style="margin-bottom:10px;">
                                        <small style="color:var(--text-light); text-transform:uppercase; font-size:10px; font-weight:700;">Ringkasan:</small>
                                        <p style="font-size:12px; margin-top:2px;"><?= nl2br(htmlspecialchars($lpjData['overview'])) ?></p>
                                    </div>
                                    <div style="margin-bottom:10px;">
                                        <small style="color:var(--text-light); text-transform:uppercase; font-size:10px; font-weight:700;">Aktivitas:</small>
                                        <p style="font-size:12px; margin-top:2px;"><?= nl2br(htmlspecialchars($lpjData['activities'])) ?></p>
                                    </div>
                                    <div style="margin-bottom:10px;">
                                        <small style="color:var(--text-light); text-transform:uppercase; font-size:10px; font-weight:700;">Kendala:</small>
                                        <p style="font-size:12px; margin-top:2px;"><?= nl2br(htmlspecialchars($lpjData['obstacles'] ?: '-')) ?></p>
                                    </div>
                                    <div>
                                        <small style="color:var(--text-light); text-transform:uppercase; font-size:10px; font-weight:700;">Kesimpulan:</small>
                                        <p style="font-size:12px; margin-top:2px;"><?= nl2br(htmlspecialchars($lpjData['conclusion'])) ?></p>
                                    </div>
                                    
                                    <a href="?id=<?= $task_id ?>&download_word=<?= $task_id ?>" class="btn btn-sm btn-block" style="margin-top:15px; background:#2b6cb0; color:white; font-size:11px;">
                                        <i class="fa-solid fa-file-word"></i> Download sebagai Word (.doc)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:30px; color:var(--text-light);">
                                <i class="fa-solid fa-hourglass-start" style="font-size:40px; color:var(--border); margin-bottom:15px; display:block;"></i>
                                <p>Proyek belum diselesaikan oleh staff.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div class="section-title"><i class="fa-solid fa-shield"></i> Administrasi</div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="tugas.php?edit=<?= $t['id'] ?>" class="btn btn-accent btn-block"><i class="fa-solid fa-pen"></i> Edit Detail Proyek</a>
                    <button class="btn btn-danger btn-block" style="opacity:0.6;"><i class="fa-solid fa-ban"></i> Batalkan Proyek</button>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
