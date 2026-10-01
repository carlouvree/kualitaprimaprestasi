<?php
/**
 * Utility untuk kompresi dan resize gambar
 */

/**
 * Kompresi dan ubah ukuran gambar sebelum disimpan
 * @param string $source Path file sumber sementara (misal: $_FILES['x']['tmp_name'])
 * @param string $destination Path file tujuan (misal: '../uploads/nama_file.jpg')
 * @param int $quality Kualitas kompresi JPG/WEBP (0-100)
 * @param int $maxWidth Lebar maksimal gambar dalam pixel
 * @return bool
 */
function compress_image_upload($source, $destination, $quality = 70, $maxWidth = 1000) {
    // Selalu validasi bahwa ini benar-benar file gambar via MIME, terlepas dari GD
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $source);
        finfo_close($finfo);
        if (!in_array($real_mime, $allowed_mime_types)) {
            return false; // Tolak file non-gambar
        }
    } else {
        $info = @getimagesize($source);
        if ($info === false) return false; // Tolak jika bukan gambar
    }

    // Fallback jika PHP GD extension tidak diaktifkan
    if (!extension_loaded('gd')) {
        return move_uploaded_file($source, $destination);
    }

    $info = @getimagesize($source);
    if ($info === false) {
        return false;
    }

    $mime = $info['mime'];
    
    // Create image resource from source
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($source);
            break;
        default:
            return move_uploaded_file($source, $destination);
    }

    if (!$image) return move_uploaded_file($source, $destination);

    // Resize logic
    $width = imagesx($image);
    $height = imagesy($image);
    
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagecolortransparent($resizedImage, imagecolorallocatealpha($resizedImage, 0, 0, 0, 127));
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resizedImage;
    }

    $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
    $result = false;
    
    if ($ext == 'png') {
        // PNG quality is 0-9
        $pngQuality = round((100 - $quality) / 100 * 9);
        $result = imagepng($image, $destination, $pngQuality);
    } elseif ($ext == 'gif') {
        $result = imagegif($image, $destination);
    } elseif ($ext == 'webp') {
        $result = imagewebp($image, $destination, $quality);
    } else {
        $result = imagejpeg($image, $destination, $quality);
    }

    imagedestroy($image);
    return $result;
}
?>
