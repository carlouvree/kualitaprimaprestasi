<?php
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role('admin');

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $month = (int)$_POST['month'];
    $year  = (int)$_POST['year'];
    $type  = in_array($_POST['recap_type'], ['engineering','construction','procurement','umum']) ? $_POST['recap_type'] : 'umum';
    $title = trim($_POST['title']);
    $notes = trim($_POST['notes'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (empty($title) || !isset($_FILES['file'])) {
        header("Location: dms.php?month=$month&year=$year&error=missing_data");
        exit;
    }

    $file = $_FILES['file'];

    // Validasi ukuran file (maks 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        header("Location: dms.php?month=$month&year=$year&error=file_too_large");
        exit;
    }

    // Validasi extension
    $original_name = basename($file['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_ext  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

    if (!in_array($ext, $allowed_ext)) {
        header("Location: dms.php?month=$month&year=$year&error=invalid_file");
        exit;
    }

    // Validasi MIME type yang sebenarnya (bukan hanya ekstensi)
    $allowed_mime = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'application/zip',
        'application/x-zip-compressed', 'application/octet-stream'
    ];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            header("Location: dms.php?month=$month&year=$year&error=invalid_file");
            exit;
        }
    }

    $new_name = "REKAP_" . strtoupper($type) . "_" . $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . "_" . time() . "." . $ext;
    $target_dir = "../uploads/documents/";
    
    // Ensure dir exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $target_dir . $new_name)) {
        $stmt = $pdo->prepare("INSERT INTO monthly_recaps (month, year, recap_type, file_name, title, notes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$month, $year, $type, $new_name, $title, $notes, $user_id]);
        header("Location: dms.php?month=$month&year=$year&msg=uploaded");
    } else {
        header("Location: dms.php?month=$month&year=$year&error=upload_failed");
    }
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $month = (int)($_GET['month'] ?? date('n'));
    $year = (int)($_GET['year'] ?? date('Y'));

    $stmt = $pdo->prepare("SELECT file_name FROM monthly_recaps WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();

    if ($file) {
        $path = "../uploads/documents/" . $file['file_name'];
        if (file_exists($path)) {
            unlink($path);
        }
        $pdo->prepare("DELETE FROM monthly_recaps WHERE id = ?")->execute([$id]);
        header("Location: dms.php?month=$month&year=$year&msg=deleted");
    } else {
        header("Location: dms.php?month=$month&year=$year&error=not_found");
    }
    exit;
}

header("Location: dms.php");
exit;
