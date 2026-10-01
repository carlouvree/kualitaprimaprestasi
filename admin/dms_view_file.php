<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM monthly_recaps WHERE id = ?");
$stmt->execute([$id]);
$recap = $stmt->fetch();

if (!$recap) {
    echo "<p>Dokumen tidak ditemukan.</p>";
    exit;
}

// Sanitasi nama file: gunakan basename() untuk cegah path traversal
$safe_filename = basename($recap['file_name']);
$file_path = "../uploads/documents/" . $safe_filename;

if (!file_exists($file_path)) {
    echo "<p>File fisik tidak ditemukan di server.</p>";
    exit;
}

$ext = strtolower(pathinfo($safe_filename, PATHINFO_EXTENSION));

if (in_array($ext, ['doc', 'html', 'txt'])) {
    // Baca konten file tapi SANITASI agar aman dari XSS
    $content = file_get_contents($file_path);
    // Tampilkan sebagai teks biasa yang di-escape, bukan HTML mentah
    echo '<pre style="white-space:pre-wrap; word-break:break-word; font-size:13px; font-family:inherit; background:#f8fafc; padding:15px; border-radius:8px;">';
    echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    echo '</pre>';
} elseif (in_array($ext, ['pdf'])) {
    // Tampilan PDF via URL (tidak ekspos path server langsung)
    $view_url = '../uploads/documents/' . urlencode($safe_filename);
    echo '<iframe src="' . htmlspecialchars($view_url) . '" style="width:100%; height:600px; border:none;"></iframe>';
} elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
    $view_url = '../uploads/documents/' . urlencode($safe_filename);
    echo '<div style="text-align:center;"><img src="' . htmlspecialchars($view_url) . '" style="max-width:100%; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1);" alt="Document Image"></div>';
} else {
    $download_url = '../uploads/documents/' . urlencode($safe_filename);
    echo '<div style="text-align:center; padding:50px;">
            <i class="fa-solid fa-file-circle-question" style="font-size:50px; color:#cbd5e1; margin-bottom:20px; display:block;"></i>
            <p>Format file (.' . htmlspecialchars($ext) . ') tidak mendukung pratinjau langsung.</p>
            <a href="' . htmlspecialchars($download_url) . '" download class="btn-premium btn-premium-indigo" style="display:inline-block; margin-top:10px;">Download File</a>
          </div>';
}
?>
