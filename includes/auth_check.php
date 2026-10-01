<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check jika user belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . base_url('login.php'));
    exit;
}

// Helper untuk Cek Role
if (!function_exists('require_role')) {
    function require_role($role_needed) {
        $user_role = $_SESSION['role'] ?? '';
        if ($user_role !== $role_needed) {
            $target = ($user_role === 'admin') ? 'admin/index.php' : 'staff/index.php';
            
            // Verifikasi agar tidak redirect ke halaman yang sama (mencegah loop)
            $current_script = basename($_SERVER['SCRIPT_NAME']);
            $target_script = basename($target);
            
            if ($current_script !== $target_script) {
                header("Location: " . base_url($target));
                exit;
            } else if ($user_role !== 'admin' && $user_role !== 'staff') {
                 // Jika role tidak dikenal dan kita ada di salah satu dashboard, logout saja
                 header("Location: " . base_url('logout.php'));
                 exit;
            }
        }
    }
}
