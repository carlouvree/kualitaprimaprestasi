<?php
// Portal Partitioning Logic for PT Kualita Prima
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect current portal
$script_path = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
$portal = null;
if (strpos($script_path, '/admin/') !== false) $portal = 'admin';
elseif (strpos($script_path, '/staff/') !== false) $portal = 'staff';

if ($portal) {
    // Map portal-specific data to global session keys for the current request
    if (isset($_SESSION['portal_auth'][$portal])) {
        foreach ($_SESSION['portal_auth'][$portal] as $key => $value) {
            $_SESSION[$key] = $value;
        }
    } else {
        // If in portal but not authenticated for it, clear global keys to trigger auth_check
        unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'], $_SESSION['full_name']);
    }
} else {
    // If NOT in a portal (root area like login.php), clear global keys 
    // to allow a fresh login while preserving the partitioned portal auths in the background
    unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['username'], $_SESSION['full_name']);
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'kualitaprima_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // true parameterized queries
} catch (PDOException $e) {
    // Log error secara internal, JANGAN tampilkan detail ke pengguna
    error_log("DB Connection Error: " . $e->getMessage());
    http_response_code(503);
    die("<h3 style='font-family:sans-serif;color:#c0392b;padding:30px;'>Layanan sementara tidak tersedia. Silakan coba lagi nanti.</h3>");
}

// Function helper for base url
function base_url($path = '') {
    $base = 'http://localhost/ptkualitaprima/'; // Sesuaikan dengan folder XAMPP htdocs Anda
    return $base . ltrim($path, '/');
}

// Fetch global settings
$global_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
    while ($row = $stmt->fetch()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Ignore if table doesn't exist yet
}

function get_setting($key, $default = '') {
    global $global_settings;
    return $global_settings[$key] ?? $default;
}

/**
 * Check if a staff member has checked in today
 */
function is_staff_checked_in($pdo, $user_id) {
    if (!$user_id) return false;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id FROM attendances WHERE user_id = ? AND date = ? LIMIT 1");
    $stmt->execute([$user_id, $today]);
    return (bool)$stmt->fetch();
}

/**
 * Helper to display Indonesian formatted date
 */
function indo_date($timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    $hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    $bulan = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
    $d = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    return $hari[date('l', $d)] . ', ' . date('d ', $d) . $bulan[date('F', $d)] . date(' Y', $d);
}
