<?php
// api/upload.php - Handle receipt image uploads
require_once __DIR__ . '/../config/database.php';

$uploadDir = __DIR__ . '/../uploads/receipts/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. Handle file upload via Multipart Form
if (isset($_FILES['receipt']) || isset($_FILES['image'])) {
    $file = isset($_FILES['receipt']) ? $_FILES['receipt'] : $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(['status' => 'error', 'message' => 'Gagal mengunggah file. Kode error: ' . $file['error']], 400);
    }

    // Validate size (max 8MB)
    if ($file['size'] > 8 * 1024 * 1024) {
        sendJsonResponse(['status' => 'error', 'message' => 'Ukuran file maksimal 8MB.'], 400);
    }

    // Validate mime type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        sendJsonResponse(['status' => 'error', 'message' => 'Format file tidak didukung. Harap gunakan format JPG, PNG, atau WebP.'], 400);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        $extension = ($mimeType === 'image/png') ? 'png' : 'jpg';
    }

    $newFilename = 'receipt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    $destination = $uploadDir . $newFilename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $publicUrl = 'uploads/receipts/' . $newFilename;
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Foto nota berhasil diunggah.',
            'url' => $publicUrl,
            'filename' => $newFilename
        ]);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Gagal memindahkan file ke direktori uploads.'], 500);
    }
    return;
}

// 2. Handle base64 image data from JSON payload
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!empty($data['image_base64'])) {
    $base64 = $data['image_base64'];
    
    // Extract base64 part
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
        $base64 = substr($base64, strpos($base64, ',') + 1);
        $ext = strtolower($type[1]);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $ext = 'jpg';
        }
    } else {
        $ext = 'jpg';
    }

    $decodedData = base64_decode($base64);
    if ($decodedData === false) {
        sendJsonResponse(['status' => 'error', 'message' => 'Data base64 tidak valid.'], 400);
    }

    $newFilename = 'receipt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $newFilename;

    if (file_put_contents($destination, $decodedData)) {
        $publicUrl = 'uploads/receipts/' . $newFilename;
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Foto nota berhasil disimpan.',
            'url' => $publicUrl,
            'filename' => $newFilename
        ]);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Gagal menulis file gambar.'], 500);
    }
    return;
}

sendJsonResponse(['status' => 'error', 'message' => 'Tidak ada file atau gambar yang diunggah.'], 400);
