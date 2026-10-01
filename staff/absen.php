<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_once '../includes/image_helper.php';
require_role('staff');
$current_page = 'absen';
$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];
$tipe = $_GET['tipe'] ?? 'masuk';

$stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id=? AND date=? LIMIT 1");
$stmt->execute([$user_id,$today]); $absen = $stmt->fetch();

$already_masuk = ($tipe === 'masuk' && $absen);
$already_pulang = ($tipe === 'pulang' && $absen && !empty($absen['time_out']));

// Cek laporan harian hari ini apakah sudah approved
$report_stmt = $pdo->prepare("SELECT status FROM project_reports WHERE user_id=? AND report_date=?");
$report_stmt->execute([$user_id, $today]);
$daily_report = $report_stmt->fetch();
$has_approved_report = ($daily_report && $daily_report['status'] === 'approved');

$can_checkout = $absen && !$absen['time_out'] && $has_approved_report;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = $_POST['latitude'] ?? ''; 
    $lng = $_POST['longitude'] ?? '';
    $photo = '';
    if(isset($_FILES['selfie']) && $_FILES['selfie']['error']===0){
        $ext = strtolower(pathinfo($_FILES['selfie']['name'], PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp'])) $ext = 'jpg';
        $photo = 'absen_'.$user_id.'_'.time().'.'.$ext;
        if(!is_dir('../uploads/absen/')) mkdir('../uploads/absen/', 0777, true);
        compress_image_upload($_FILES['selfie']['tmp_name'], '../uploads/absen/'.$photo, 60, 800);
    }
    if($tipe==='masuk' && !$absen){
        $pdo->prepare("INSERT INTO attendances (user_id,date,time_in,latitude_in,longitude_in,photo_in) VALUES (?,?,CURTIME(),?,?,?)")->execute([$user_id,$today,$lat,$lng,$photo]);
        header("Location: proyek.php?msg=absen_success"); exit;
    } elseif($tipe==='pulang' && $can_checkout){
        $pdo->prepare("UPDATE attendances SET time_out=CURTIME(),latitude_out=?,longitude_out=?,photo_out=? WHERE id=?")->execute([$lat,$lng,$photo,$absen['id']]);
        header("Location: index.php?msg=absen_success"); exit;
    }
}
$is_masuk = $tipe==='masuk';
$show_block = $tipe==='pulang' && !$can_checkout && $absen && !$absen['time_out'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi — KPP Staff</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg?v=1779250812">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <!-- face-api.js for face detection -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        #cameraBox { position:relative; background:#000; border-radius:var(--radius); overflow:hidden; width:100%; aspect-ratio:4/3; margin-bottom:10px; }
        #cameraBox video, #cameraBox img { width:100%; height:100%; object-fit:cover; display:block; }
        #cameraBox .cam-placeholder { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#666; gap:10px; }
        #cameraBox .cam-placeholder i { font-size:48px; }
        .cam-actions { display:flex; gap:8px; margin-bottom:15px; }
        .cam-actions .btn { flex:1; }
        
        #faceStatus { 
            padding: 12px 15px; border-radius: 8px; margin-bottom: 12px; 
            font-size: 13px; font-weight: 600; text-align: center;
            display: none; align-items: center; justify-content: center; gap: 8px;
        }
        #faceStatus.loading,
        #faceStatus.faceStatus.loading { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: #92400e; display: flex; }
        #faceStatus.success,
        #faceStatus.faceStatus.success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--success); display: flex; }
        #faceStatus.danger,
        #faceStatus.faceStatus.danger  { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: var(--danger);  display: flex; }
        
        #faceOverlay { position: absolute; top: 0; left: 0; width:100%; height:100%; pointer-events:none; }
        
        @keyframes shake   { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
        @keyframes pulse   { 0%,100%{opacity:1} 50%{opacity:0.6} }
        @keyframes slideUp { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes fadeIn  { from{opacity:0} to{opacity:1} }
        .face-guide-circle {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%,-55%);
            width: 55%; aspect-ratio: 1; border-radius: 50%;
            border: 3px dashed rgba(255,255,255,0.5);
            pointer-events: none;
            animation: pulse 2s ease infinite;
            transition: border-color 0.3s ease;
        }
        
        /* Switch Toggle */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }
        .slider.round { border-radius: 24px; }
        .slider.round:before { border-radius: 50%; }

        /* Countdown Overlay */
        #countdownOverlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center;
            z-index: 20;
        }
        #countdownNumber {
            font-size: 96px; font-weight: 800; color: #fff;
            font-family: 'Montserrat', sans-serif;
            text-shadow: 0 0 20px rgba(0, 153, 204, 0.8), 0 0 40px rgba(0, 153, 204, 0.4);
            animation: zoomInOut 1s ease infinite;
        }
        @keyframes zoomInOut {
            0% { transform: scale(1.6); opacity: 0; }
            40% { transform: scale(1.0); opacity: 1; }
            70% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(0.7); opacity: 0; }
        }
    </style>
</head>
<body class="staff-body">
<?php require_once '../includes/staff_nav.php'; ?>
<main class="portal-main">
    <div class="portal-topbar">
        <div class="topbar-left" style="display:flex;align-items:center;gap:15px;">
            <button class="sidebar-toggle" onclick="document.getElementById('portalSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fa-solid fa-bars"></i></button>
            <div><h1><?= $is_masuk ? 'Check-In Masuk' : 'Check-Out Pulang' ?></h1><p><?= date('l, d F Y') ?></p></div>
        </div>
        <div class="topbar-right"><div class="topbar-user"><?php if(!empty($_SESSION['profile_photo'])): ?><img src="../uploads/profiles/<?= htmlspecialchars($_SESSION['profile_photo']) ?>" class="avatar-sm" style="object-fit:cover; border:none; padding:0;"><?php else: ?><div class="avatar-sm"><?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div><?php endif; ?></div></div>
    </div>
    <div class="portal-content">
        <?php if(isset($_GET['msg']) && $_GET['msg']==='need_checkin'): ?>
            <div class="alert alert-danger" style="margin-bottom:20px; border-left:4px solid #b91c1c;">
                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Akses Terkunci!</strong> Silakan lakukan <strong>Check-In</strong> terlebih dahulu untuk dapat mengakses menu Proyek.
            </div>
        <?php endif; ?>

        <?php if($show_block): ?>
        <div class="card" style="max-width:600px;">
            <div class="card-body" style="text-align:center;padding:50px 30px;">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><i class="fa-solid fa-lock" style="font-size:32px;color:var(--danger);"></i></div>
                <h3 style="font-family:'Montserrat',sans-serif;color:var(--text-dark);margin-bottom:10px;">Check-Out Terkunci</h3>
                <p style="color:var(--text-light);margin-bottom:10px;">Anda belum bisa melakukan Check-Out pulang.</p>
                <?php if(!$daily_report): ?>
                    <p style="color:var(--text-light);font-size:13px;margin-bottom:20px;">Anda belum mengirim <strong>Laporan Harian</strong> hari ini.</p>
                    <a href="report.php" class="btn btn-primary"><i class="fa-solid fa-file-signature"></i> Buat Laporan</a>
                <?php elseif($daily_report['status'] === 'pending'): ?>
                    <p style="color:var(--text-light);font-size:13px;margin-bottom:20px;">Laporan harian Anda sedang <strong>menunggu persetujuan (Pending)</strong> dari Admin.</p>
                <?php elseif($daily_report['status'] === 'rejected'): ?>
                    <p style="color:var(--text-light);font-size:13px;margin-bottom:20px;color:var(--danger);">Laporan Anda ditolak Admin. Silakan perbarui dan kirim ulang.</p>
                    <a href="report.php" class="btn btn-warning"><i class="fa-solid fa-pen"></i> Perbarui Laporan</a>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif($already_masuk || $already_pulang): ?>
        <div class="card" style="max-width:600px;">
            <div class="card-body" style="text-align:center;padding:50px 30px;">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><i class="fa-solid fa-check-circle" style="font-size:32px;color:var(--success);"></i></div>
                <h3 style="font-family:'Montserrat',sans-serif;color:var(--text-dark);margin-bottom:10px;">Anda Sudah <?= $is_masuk?'Check-In':'Check-Out' ?>!</h3>
                <p style="color:var(--text-light);margin-bottom:10px;">Sistem mencatat kehadiran Anda pada pukul <strong><?= date('H:i', strtotime($is_masuk ? $absen['time_in'] : $absen['time_out'])) ?> WIB</strong>.</p>
                <div style="background:#f8fafc;padding:15px;border-radius:10px;margin:20px 0;border:1px solid #e2e8f0;">
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:8px;">Jadwal Absen berikutnya akan terbuka dalam:</p>
                    <div id="countdownTimer" style="font-size:28px;font-weight:800;color:var(--accent-color);font-family:'Montserrat',sans-serif;letter-spacing:1px;">--:--:--</div>
                </div>
                <a href="index.php" class="btn btn-outline" style="min-width:200px;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
            </div>
        </div>
        <script>
            function updateCountdown() {
                const now = new Date();
                const tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0);
                const diff = tomorrow - now;
                if (diff <= 0) { location.reload(); return; }
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const secs = Math.floor((diff % (1000 * 60)) / 1000);
                document.getElementById('countdownTimer').textContent = 
                    String(hours).padStart(2,'0') + " : " + 
                    String(mins).padStart(2,'0') + " : " + 
                    String(secs).padStart(2,'0');
            }
            setInterval(updateCountdown, 1000);
            updateCountdown();
        </script>
        <?php else: ?>
        <div class="card" style="max-width:600px;">
            <div class="card-header" style="background:linear-gradient(135deg,<?= $is_masuk?'#10b981,#059669':'#ef4444,#dc2626' ?>);color:#fff;">
                <h3 style="color:#fff;"><i class="fa-solid fa-<?= $is_masuk?'right-to-bracket':'right-from-bracket' ?>"></i> Absensi <?= $is_masuk?'Masuk':'Pulang' ?></h3>
                <span style="font-size:28px;font-weight:800;font-family:'Montserrat',sans-serif;" id="liveTime"></span>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formAbsen">
                    <input type="hidden" name="latitude" id="lat">
                    <input type="hidden" name="longitude" id="lng">

                    <!-- GPS Status -->
                    <div style="padding:15px;background:var(--bg-gray);border-radius:var(--radius);margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                            <i class="fa-solid fa-location-dot" style="color:var(--secondary-accent);font-size:18px;"></i>
                            <strong style="font-size:14px;">Lokasi GPS</strong>
                        </div>
                        <p id="gpsCoords" style="font-size:12px;color:var(--text-light);margin-bottom:5px;"><i class="fa-solid fa-spinner fa-spin"></i> Mengambil koordinat...</p>
                        <p id="gpsAddress" style="font-size:13px;color:var(--text-dark);font-weight:600;"><i class="fa-solid fa-spinner fa-spin"></i> Memuat alamat...</p>
                    </div>

                    <!-- Camera with Face Detection -->
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-camera"></i> Foto Selfie <span style="font-size:11px;color:var(--accent-color);font-weight:600;">(Deteksi Wajah Otomatis)</span></label>
                        
                        <div id="faceStatus" class="loading">
                            <i class="fa-solid fa-spinner fa-spin"></i> Memuat model deteksi wajah...
                        </div>
                        
                        <div id="cameraBox">
                            <video id="video" autoplay playsinline muted style="display:none; opacity:0; transition:opacity 0.3s;"></video>
                            <img id="preview" style="display:none;">
                            <canvas id="canvas" style="display:none;"></canvas>
                            <canvas id="faceOverlay"></canvas>
                            <div id="faceOverlayContainer" style="position: absolute; inset: 0; display: none;"></div>
                            <div class="face-guide-circle" id="faceGuide" style="display:none;"></div>
                            
                            <!-- Countdown Overlay -->
                            <div id="countdownOverlay">
                                <div id="countdownNumber">3</div>
                            </div>
                            
                            <div class="cam-placeholder" id="camPlaceholder">
                                <i class="fa-solid fa-camera"></i>
                                <span>Kamera belum aktif</span>
                            </div>
                            <!-- Camera Switcher (Icon) -->
                            <button type="button" id="btnSwitchCam" onclick="switchCamera()" class="btn-sm" style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.5); color:#fff; border:none; width:36px; height:36px; border-radius:50%; display:none; align-items:center; justify-content:center; cursor:pointer;" title="Ganti Kamera">
                                <i class="fa-solid fa-camera-rotate"></i>
                            </button>
                        </div>

                        <!-- Gesture Auto-Capture Toggle -->
                        <div class="gesture-toggle-container" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-gray); padding:12px 15px; border-radius:var(--radius); margin-top:12px; border: 1.5px solid var(--border);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <i class="fa-solid fa-hand" style="color:var(--accent-color); font-size:18px;"></i>
                                <div style="text-align:left;">
                                    <strong style="font-size:13px; display:block;">Sensor Tangan (Auto-Capture)</strong>
                                    <span style="font-size:11px; color:var(--text-light);">Tunjukkan telapak tangan terbuka untuk hitung mundur</span>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="toggleGesture" onchange="toggleGestureCapture(this.checked)">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <!-- Camera Selection Dropdown -->
                        <div class="form-group" id="cameraSelectGroup" style="display:none; margin-top: 12px; margin-bottom: 12px;">
                            <label class="form-label" style="font-size:12px; color:var(--text-light); margin-bottom:4px; display:block;">
                                <i class="fa-solid fa-sliders"></i> Pilih Kamera / Webcam:
                            </label>
                            <select id="cameraSelect" class="form-control" onchange="switchCameraTo(this.value)" style="padding: 8px 12px; font-size: 13px; border-radius: 8px; width:100%; font-family:'Open Sans',sans-serif; border: 1.5px solid #e2e8f0; background:#fff;">
                            </select>
                        </div>

                        <div class="cam-actions">
                            <button type="button" id="btnStartCam" class="btn btn-outline" onclick="startCamera()"><i class="fa-solid fa-video"></i> Buka Kamera</button>
                            <button type="button" id="btnCapture" class="btn btn-accent" style="display:none;" onclick="capturePhoto()"><i class="fa-solid fa-camera"></i> Ambil Foto</button>
                            <button type="button" id="btnRetake" class="btn btn-outline" style="display:none;" onclick="retakePhoto()"><i class="fa-solid fa-redo"></i> Ulangi</button>
                        </div>
                        
                        <div style="text-align:center; padding-top:5px;">
                            <p style="font-size:12px; color:var(--text-light); margin-bottom:8px;">Kamera bermasalah atau tidak terdeteksi?</p>
                            <button type="button" class="btn btn-sm btn-outline" onclick="triggerManualUpload()" style="font-size:11px; border-style:dashed; color:var(--accent-color);">
                                <i class="fa-solid fa-upload"></i> Gunakan Upload Foto Manual (Dashcam/Lainnya)
                            </button>
                        </div>
                        <input type="file" name="selfie" id="selfieInput" accept="image/*" style="display:none;" onchange="handleManualFile(this)">
                    </div>

                    <button type="submit" class="btn <?= $is_masuk?'btn-success':'btn-danger' ?> btn-block" id="btnSubmit" disabled style="padding:16px;font-size:16px;">
                        <i class="fa-solid fa-<?= $is_masuk?'check-circle':'right-from-bracket' ?>"></i>
                        <?= $is_masuk?'Konfirmasi Check-In':'Konfirmasi Check-Out' ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
// ==========================================
// ABSENSI — Kamera & Deteksi Wajah
// Mendukung: kamera internal, webcam USB eksternal
// ==========================================

// Live clock
function updateClock() {
    const el = document.getElementById('liveTime');
    if (el) el.textContent = new Date().toLocaleTimeString('id-ID');
}
setInterval(updateClock, 1000);
updateClock();

// State
let gpsReady = false, photoReady = false, stream = null, modelsLoaded = false;
let detectionInterval = null, currentDeviceId = null, videoDevices = [];
let preferredCameraId = localStorage.getItem('preferredCameraId');

// Gesture Capture State
let gestureEnabled = localStorage.getItem('gestureCaptureEnabled') === 'true';
let handsInstance = null;
let countdownTimerActive = false;
let faceDetectedThisFrame = false;
let countdownInterval = null;

// Dynamic script loader for MediaPipe
function loadGestureScripts() {
    return new Promise((resolve, reject) => {
        if (window.Hands) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = "https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js";
        script.crossOrigin = "anonymous";
        script.onload = () => resolve();
        script.onerror = (e) => reject(e);
        document.head.appendChild(script);
    });
}

// Initialize MediaPipe Hands
async function initGestureDetection() {
    if (!gestureEnabled) return;
    
    const toggleEl = document.getElementById('toggleGesture');
    if (toggleEl) toggleEl.checked = true;
    
    // Status text update
    const faceEl = document.getElementById('faceStatus');
    if (faceEl && faceEl.innerHTML && !window.Hands) {
        faceEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memuat sensor tangan...';
    }
    
    try {
        await loadGestureScripts();
        if (!handsInstance) {
            handsInstance = new Hands({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
            });
            handsInstance.setOptions({
                maxNumHands: 1,
                modelComplexity: 1,
                minDetectionConfidence: 0.6,
                minTrackingConfidence: 0.6
            });
            handsInstance.onResults(onHandResults);
        }
        
        if (modelsLoaded) {
            showFaceStatus('success', '✓ Model deteksi & sensor tangan siap. Buka kamera.');
        }
    } catch (e) {
        console.error("Gagal menginisialisasi sensor tangan:", e);
        showFaceStatus('danger', 'Sensor tangan gagal dimuat. Hubungi Administrator.');
    }
}

// Toggle callback
function toggleGestureCapture(enabled) {
    gestureEnabled = enabled;
    localStorage.setItem('gestureCaptureEnabled', enabled);
    const checkbox = document.getElementById('toggleGesture');
    if (checkbox) checkbox.checked = enabled;
    
    if (enabled) {
        initGestureDetection();
    } else {
        if (modelsLoaded) {
            showFaceStatus('success', '✓ Model deteksi siap. Silakan buka kamera atau upload.');
        }
    }
}

// Synthesize countdown tick/shutter sound via Web Audio API
function playTickSound(isShutter = false) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        
        osc.connect(gain);
        gain.connect(ctx.destination);
        
        if (isShutter) {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(800, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(100, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0.01, ctx.currentTime + 0.15);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } else {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(600, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0.01, ctx.currentTime + 0.1);
            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        }
    } catch (e) {
        // Ignored if browser blocks AudioContext before user interaction
    }
}

// Trigger count down and photo capture
function startCountdown() {
    if (countdownTimerActive) return;
    countdownTimerActive = true;
    
    stopFaceDetection();
    
    const overlay = document.getElementById('countdownOverlay');
    const numberEl = document.getElementById('countdownNumber');
    
    if (overlay && numberEl) {
        overlay.style.display = 'flex';
        
        let count = 3;
        numberEl.textContent = count;
        
        const playAnimation = () => {
            numberEl.style.animation = 'none';
            numberEl.offsetHeight; /* trigger reflow */
            numberEl.style.animation = 'zoomInOut 1s ease infinite';
        };
        
        playAnimation();
        playTickSound(false);
        
        countdownInterval = setInterval(() => {
            count--;
            if (count > 0) {
                numberEl.textContent = count;
                playAnimation();
                playTickSound(false);
            } else {
                clearInterval(countdownInterval);
                overlay.style.display = 'none';
                countdownTimerActive = false;
                
                // Play shutter sound and capture photo!
                playTickSound(true);
                capturePhoto();
            }
        }, 1000);
    }
}

// MediaPipe Hand Detection results callback
function onHandResults(results) {
    if (countdownTimerActive || !video.srcObject) return;
    
    let gestureDetected = false;
    if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
        const landmarks = results.multiHandLandmarks[0];
        
        // Check if 4 fingers are open (index, middle, ring, pinky)
        // Joint 8 is index tip, Joint 6 is index pip
        const indexOpen = landmarks[8].y < landmarks[6].y;
        const middleOpen = landmarks[12].y < landmarks[10].y;
        const ringOpen = landmarks[16].y < landmarks[14].y;
        const pinkyOpen = landmarks[20].y < landmarks[18].y;
        
        if (indexOpen && middleOpen && ringOpen && pinkyOpen) {
            gestureDetected = true;
        }
    }
    
    if (gestureDetected && faceDetectedThisFrame) {
        startCountdown();
    }
}

function checkReady() {
    const btn = document.getElementById('btnSubmit');
    if (btn) btn.disabled = !(gpsReady && photoReady);
}

// ---- Status wajah ----
function showFaceStatus(type, msg) {
    const el = document.getElementById('faceStatus');
    if (!el) return;
    el.className = 'faceStatus ' + type;
    const icon = type === 'loading' ? '<i class="fa-solid fa-spinner fa-spin"></i>'
               : type === 'success' ? '<i class="fa-solid fa-face-smile"></i>'
               : '<i class="fa-solid fa-face-frown"></i>';
    el.innerHTML = icon + ' ' + msg;
    el.style.display = 'flex';
}

// ---- Muat model face-api ----
async function loadFaceModels() {
    showFaceStatus('loading', 'Memuat model deteksi wajah...');
    try {
        const MODEL_URL = '../assets/weights';
        // Muat tinyFaceDetector untuk realtime & ssdMobilenetv1 untuk validasi upload manual yang akurat
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL)
        ]);
        modelsLoaded = true;
        showFaceStatus('success', '✓ Model deteksi siap. Silakan buka kamera atau upload.');
    } catch (e) {
        showFaceStatus('danger', 'Gagal memuat model deteksi dari server lokal. Silakan hubungi Administrator.');
        console.error('face-api load error:', e);
    }
}

// ---- GPS ----
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        pos => {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
            document.getElementById('gpsCoords').innerHTML =
                '<i class="fa-solid fa-check-circle" style="color:var(--success);"></i> ' +
                lat.toFixed(6) + ', ' + lng.toFixed(6);
            gpsReady = true;
            checkReady();
            fetch('https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('gpsAddress').innerHTML =
                        '<i class="fa-solid fa-map-pin" style="color:var(--success);"></i> ' +
                        (d.display_name || lat + ', ' + lng);
                })
                .catch(() => { document.getElementById('gpsAddress').textContent = lat + ', ' + lng; });
        },
        err => {
            document.getElementById('gpsCoords').innerHTML =
                '<i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);"></i> GPS gagal. Aktifkan lokasi.';
            gpsReady = true;
            checkReady();
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

// ---- Enumerate kamera ----
async function enumerateVideoDevices() {
    try {
        // Minta izin stream sementara agar label device terisi
        const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false }).catch(() => null);
        if (tempStream) tempStream.getTracks().forEach(t => t.stop());

        const all = await navigator.mediaDevices.enumerateDevices();
        videoDevices = all.filter(d => d.kind === 'videoinput');

        return videoDevices;
    } catch (e) {
        return [];
    }
}

// ---- Isi Dropdown Kamera ----
async function populateCameraList() {
    try {
        await enumerateVideoDevices();
        const select = document.getElementById('cameraSelect');
        const group = document.getElementById('cameraSelectGroup');
        if (!select || !group) return;

        select.innerHTML = '';

        if (videoDevices.length > 0) {
            videoDevices.forEach((dev, idx) => {
                const opt = document.createElement('option');
                opt.value = dev.deviceId;
                opt.textContent = dev.label || `Kamera ${idx + 1} (${dev.deviceId.substring(0, 5)})`;
                select.appendChild(opt);
            });

            if (videoDevices.length > 1) {
                group.style.display = 'block';
                document.getElementById('btnSwitchCam').style.display = 'flex';
            } else {
                group.style.display = 'none';
                document.getElementById('btnSwitchCam').style.display = 'none';
            }

            if (currentDeviceId) {
                select.value = currentDeviceId;
            }
        } else {
            group.style.display = 'none';
            document.getElementById('btnSwitchCam').style.display = 'none';
        }
    } catch (e) {
        console.error('Error populating cameras:', e);
    }
}

// ---- Pindah Kamera Dropdown ----
function switchCameraTo(deviceId) {
    if (deviceId === currentDeviceId) return;
    localStorage.setItem('preferredCameraId', deviceId);
    preferredCameraId = deviceId;
    startCamera(deviceId);
}

// ---- Buka kamera ----
async function startCamera(deviceId = null) {
    // Hentikan stream sebelumnya
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }

    const video      = document.getElementById('video');
    const placeholder = document.getElementById('camPlaceholder');
    placeholder.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:32px;"></i><span>Menghubungkan kamera...</span>';
    placeholder.style.display = 'flex';
    
    video.style.display = 'block';
    video.style.opacity = '0';
    
    if (modelsLoaded) {
        showFaceStatus('loading', 'Menghubungkan ke kamera...');
    } else {
        showFaceStatus('loading', 'Menghubungkan ke kamera... (Model wajah sedang memuat)');
    }

    await enumerateVideoDevices();

    let opened = false;
    let selectedId = deviceId || preferredCameraId;

    // 1) Coba dengan selected/preferred camera
    if (selectedId) {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: { exact: selectedId }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            });
            currentDeviceId = selectedId;
            opened = true;
        } catch (e) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { exact: selectedId } },
                    audio: false
                });
                currentDeviceId = selectedId;
                opened = true;
            } catch (e2) {
                console.warn('Preferred camera failed, fallback to defaults...', e2);
            }
        }
    }

    // 2) Coba device terdeteksi
    if (!opened) {
        for (const dev of videoDevices) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { exact: dev.deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false
                });
                currentDeviceId = dev.deviceId;
                opened = true;
                break;
            } catch (e) {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { deviceId: { exact: dev.deviceId } },
                        audio: false
                    });
                    currentDeviceId = dev.deviceId;
                    opened = true;
                    break;
                } catch (e2) { /* lanjut */ }
            }
        }
    }

    // 3) Fallback total
    if (!opened) {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            opened = true;
        } catch (e) { /* benar-benar gagal */ }
    }

    // Semua cara gagal
    if (!opened || !stream) {
        placeholder.innerHTML =
            '<i class="fa-solid fa-camera-slash" style="font-size:36px;color:#ef4444;"></i>' +
            '<span style="font-size:12px;text-align:center;color:#ef4444;padding:0 10px;">' +
            'Kamera tidak dapat diakses.<br>Pastikan webcam terhubung & izin browser diberikan.<br>' +
            'Gunakan <strong>Upload Manual</strong> di bawah.</span>';
        placeholder.style.display = 'flex';
        video.style.display = 'none';
        
        const btnStart = document.getElementById('btnStartCam');
        btnStart.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Coba Lagi';
        btnStart.style.display = 'flex';
        
        showFaceStatus('danger', 'Kamera tidak dapat diakses. Gunakan tombol Upload Manual.');
        return;
    }

    // Berhasil buka stream
    video.srcObject = stream;

    // Info kamera aktif
    const activeTracks = stream.getVideoTracks();
    if (activeTracks.length > 0) {
        const settings = activeTracks[0].getSettings();
        currentDeviceId = settings.deviceId || currentDeviceId;
        const label = activeTracks[0].label || 'Kamera';
        
        if (currentDeviceId) {
            localStorage.setItem('preferredCameraId', currentDeviceId);
        }
        
        if (modelsLoaded) {
            showFaceStatus('success', '✓ Kamera aktif: ' + label.substring(0, 40));
        } else {
            showFaceStatus('loading', '✓ Kamera aktif. Menunggu model wajah selesai dimuat...');
        }
    }

    video.onloadedmetadata = () => {
        video.play().catch(() => {});
        video.style.opacity = '1';
        placeholder.style.display = 'none';
        document.getElementById('faceGuide').style.display = 'block';
        document.getElementById('btnStartCam').style.display = 'none';
        document.getElementById('btnCapture').style.display = 'flex';

        // Setup overlay canvas
        const overlay = document.getElementById('faceOverlay');
        overlay.width  = video.videoWidth  || 640;
        overlay.height = video.videoHeight || 480;

        startFaceDetection();
        populateCameraList();
    };
}

// ---- Ganti kamera (Switch icon) ----
function switchCamera() {
    if (videoDevices.length < 2) return;
    let idx = videoDevices.findIndex(d => d.deviceId === currentDeviceId);
    if (idx < 0) idx = 0;
    const next = videoDevices[(idx + 1) % videoDevices.length];
    switchCameraTo(next.deviceId);
}

// ---- Deteksi wajah realtime ----
function startFaceDetection() {
    const video   = document.getElementById('video');
    const overlay = document.getElementById('faceOverlay');
    const ctx     = overlay.getContext('2d');

    if (detectionInterval) clearInterval(detectionInterval);

    detectionInterval = setInterval(async () => {
        if (!modelsLoaded) return;
        if (video.paused || video.ended || !video.videoWidth) return;

        if (overlay.width !== video.videoWidth)  overlay.width  = video.videoWidth;
        if (overlay.height !== video.videoHeight) overlay.height = video.videoHeight;

        try {
            const detections = await faceapi.detectAllFaces(
                video,
                new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
            );
            ctx.clearRect(0, 0, overlay.width, overlay.height);

            if (detections.length > 0) {
                faceDetectedThisFrame = true;
                const sx = overlay.width  / video.videoWidth;
                const sy = overlay.height / video.videoHeight;
                detections.forEach(det => {
                    const b = det.box;
                    ctx.strokeStyle = '#10b981';
                    ctx.lineWidth   = 3;
                    ctx.strokeRect(b.x * sx, b.y * sy, b.width * sx, b.height * sy);
                });
                
                // If hand gesture sensor is active and ready, send frame
                if (gestureEnabled && handsInstance && !countdownTimerActive) {
                    showFaceStatus('success', '✓ Wajah terdeteksi! Klik "Ambil Foto" atau tunjukkan telapak tangan terbuka.');
                    handsInstance.send({image: video}).catch(e => console.error("Hands send error:", e));
                } else if (!countdownTimerActive) {
                    showFaceStatus('success', '✓ Wajah terdeteksi! Klik "Ambil Foto" sekarang.');
                }
                
                document.getElementById('faceGuide').style.borderColor = '#10b981';
            } else {
                faceDetectedThisFrame = false;
                if (!countdownTimerActive) {
                    showFaceStatus('danger', '⚠ Wajah tidak terdeteksi! Dekatkan & perlihatkan wajah ke kamera.');
                    document.getElementById('faceGuide').style.borderColor = '#ef4444';
                }
            }
        } catch (e) { /* abaikan */ }
    }, 400);
}

function stopFaceDetection() {
    if (detectionInterval) { clearInterval(detectionInterval); detectionInterval = null; }
}

// ---- Ambil foto ----
async function capturePhoto() {
    if (!modelsLoaded) {
        showFaceStatus('danger', 'Model deteksi wajah belum dimuat sepenuhnya. Harap tunggu.');
        return;
    }
    
    const video = document.getElementById('video');
    showFaceStatus('loading', 'Memverifikasi wajah...');

    let detections;
    try {
        detections = await faceapi.detectAllFaces(
            video,
            new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
        );
    } catch (e) {
        showFaceStatus('danger', 'Gagal mendeteksi wajah. Pastikan pencahayaan cukup.');
        return;
    }

    if (detections.length === 0) {
        showFaceStatus('danger', '❌ Tidak ada wajah! Dekatkan & perlihatkan wajah ke kamera, lalu coba lagi.');
        const camBox = document.getElementById('cameraBox');
        camBox.style.animation = 'shake 0.3s ease';
        setTimeout(() => { camBox.style.animation = ''; }, 400);
        return;
    }

    const canvas = document.getElementById('canvas');
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(blob => {
        if (!blob) { showFaceStatus('danger', 'Gagal mengambil foto. Coba lagi.'); return; }

        const file = new File([blob], 'selfie_' + Date.now() + '.jpg', { type: 'image/jpeg' });
        const dt   = new DataTransfer();
        dt.items.add(file);
        document.getElementById('selfieInput').files = dt.files;

        const preview = document.getElementById('preview');
        preview.src   = URL.createObjectURL(blob);
        preview.style.display  = 'block';
        video.style.display    = 'none';

        const overlay = document.getElementById('faceOverlay');
        overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
        document.getElementById('faceGuide').style.display = 'none';

        document.getElementById('btnCapture').style.display = 'none';
        document.getElementById('btnRetake').style.display  = 'flex';
        document.getElementById('cameraSelectGroup').style.display = 'none';

        stopFaceDetection();
        if (stream) stream.getTracks().forEach(t => t.stop());

        showFaceStatus('success', '✓ Foto wajah berhasil diambil!');
        photoReady = true;
        checkReady();
    }, 'image/jpeg', 0.85);
}

// ---- Upload manual ----
function triggerManualUpload() {
    document.getElementById('selfieInput').click();
}

async function handleManualFile(input) {
    if (!input.files || !input.files[0]) return;

    if (!modelsLoaded) {
        input.value = '';
        showFaceStatus('danger', '❌ Validasi gagal: Model deteksi belum siap.');
        showRejectModal('Sistem sedang memuat model deteksi wajah. Harap pastikan koneksi internet aktif, tunggu beberapa detik, lalu coba lagi.');
        return;
    }

    showFaceStatus('loading', 'Memvalidasi foto... memastikan ada wajah manusia...');

    const objectUrl = URL.createObjectURL(input.files[0]);
    const tempImg   = new Image();
    tempImg.src     = objectUrl;

    tempImg.onload = async () => {
        let detections = [];
        try {
            // Gunakan SSD Mobilenet v1 karena jauh lebih akurat untuk foto statis
            detections = await faceapi.detectAllFaces(
                tempImg,
                new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 })
            );
            
            // Cadangan: Tiny Face Detector
            if (detections.length === 0) {
                detections = await faceapi.detectAllFaces(
                    tempImg,
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.45 })
                );
            }
        } catch (e) {
            console.error('Face detection error:', e);
            detections = []; // JANGAN izinkan jika terjadi error!
        }

        if (detections.length === 0) {
            // TOLAK — tidak ada wajah
            input.value = '';
            showFaceStatus('danger', '❌ Foto ditolak! Tidak terdeteksi wajah manusia pada gambar.');
            showRejectModal('Foto yang Anda upload tidak mengandung wajah manusia yang dapat terdeteksi.<br>Harap gunakan <strong>foto selfie wajah</strong> yang jelas, tidak terhalang, dan pencahayaan cukup.');
            URL.revokeObjectURL(objectUrl);
            return;
        }

        // TERIMA — tampilkan preview
        const preview = document.getElementById('preview');
        preview.src   = objectUrl;
        preview.style.display = 'block';

        document.getElementById('camPlaceholder').style.display = 'none';
        document.getElementById('video').style.display          = 'none';
        document.getElementById('faceGuide').style.display      = 'none';
        document.getElementById('btnStartCam').style.display    = 'none';
        document.getElementById('btnCapture').style.display     = 'none';
        document.getElementById('btnRetake').style.display      = 'flex';
        document.getElementById('btnSwitchCam').style.display   = 'none';
        document.getElementById('cameraSelectGroup').style.display = 'none';

        stopFaceDetection();
        if (stream) stream.getTracks().forEach(t => t.stop());

        showFaceStatus('success', '✓ Foto wajah berhasil diverifikasi & diunggah secara manual.');
        photoReady = true;
        checkReady();
    };

    tempImg.onerror = () => {
        showFaceStatus('danger', 'Gagal membaca file gambar. Coba file lain.');
        input.value = '';
    };
}

// ---- Modal penolakan foto ----
function showRejectModal(pesan) {
    const existing = document.getElementById('rejectModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'rejectModal';
    modal.style.cssText =
        'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.65);' +
        'backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:18px;padding:36px 28px;max-width:380px;width:92%;
                    text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.35);
                    animation:slideUp 0.3s ease;">
            <div style="width:76px;height:76px;border-radius:50%;background:rgba(239,68,68,0.12);
                        display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                <i class="fa-solid fa-face-frown" style="font-size:38px;color:#ef4444;"></i>
            </div>
            <h3 style="font-family:'Montserrat',sans-serif;color:#1e293b;margin-bottom:10px;font-size:19px;">
                ⚠ Foto Ditolak
            </h3>
            <p style="color:#64748b;font-size:14px;margin-bottom:24px;line-height:1.6;">
                ${pesan}
            </p>
            <button onclick="document.getElementById('rejectModal').remove()"
                style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;
                       border:none;border-radius:10px;padding:13px 0;font-size:15px;
                       font-weight:700;cursor:pointer;width:100%;font-family:'Montserrat',sans-serif;
                       letter-spacing:0.3px;">
                <i class="fa-solid fa-arrow-left"></i>&nbsp; Upload Ulang
            </button>
        </div>`;

    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
    document.body.appendChild(modal);
}

// ---- Ulangi foto ----
function retakePhoto() {
    const video       = document.getElementById('video');
    const preview     = document.getElementById('preview');
    const overlay     = document.getElementById('faceOverlay');
    const placeholder = document.getElementById('camPlaceholder');

    // Reset countdown states
    if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
    countdownTimerActive = false;
    faceDetectedThisFrame = false;
    const countdownOverlay = document.getElementById('countdownOverlay');
    if (countdownOverlay) countdownOverlay.style.display = 'none';

    // Reset preview & overlay
    preview.style.display = 'none';
    preview.src = '';
    overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);

    // Reset tombol
    document.getElementById('btnRetake').style.display   = 'none';
    document.getElementById('btnCapture').style.display  = 'none';
    document.getElementById('faceGuide').style.display   = 'none';
    document.getElementById('btnSwitchCam').style.display = 'none';
    document.getElementById('cameraSelectGroup').style.display = 'none';

    const btnStart = document.getElementById('btnStartCam');
    btnStart.innerHTML = '<i class="fa-solid fa-video"></i> Buka Kamera';
    btnStart.style.display = 'flex';

    placeholder.innerHTML = '<i class="fa-solid fa-camera"></i><span>Kamera belum aktif</span>';
    placeholder.style.display = 'flex';

    // Reset file input
    document.getElementById('selfieInput').value = '';
    video.style.display = 'none';

    photoReady = false;
    checkReady();
    stopFaceDetection();

    showFaceStatus('loading', 'Pilih "Buka Kamera" atau "Upload Manual".');
}

// ---- Init saat halaman siap ----
window.addEventListener('DOMContentLoaded', () => {
    // Set checkbox initial state
    const toggleEl = document.getElementById('toggleGesture');
    if (toggleEl) {
        toggleEl.checked = gestureEnabled;
    }

    const waitFaceApi = setInterval(() => {
        if (typeof faceapi !== 'undefined') {
            clearInterval(waitFaceApi);
            loadFaceModels().then(() => {
                if (gestureEnabled) {
                    initGestureDetection();
                }
            });
        }
    }, 100);
    setTimeout(() => clearInterval(waitFaceApi), 20000);
});
</script>
</body>
</html>
