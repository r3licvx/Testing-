<?php
header('Content-Type: application/json');

// Storage directory
$targetDir = "uploads/";
$maxFileSize = 20 * 1024 * 1024; // 20 MB limit

if (!file_exists($targetDir)) {
    @mkdir($targetDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "Valid file select nahi ki ya upload limits issue hai!"]);
        exit;
    }

    $file = $_FILES['fileToUpload'];

    // 20MB Size Check
    if ($file['size'] > $maxFileSize) {
        echo json_encode(["status" => "error", "message" => "File 20MB se badi hai! Upload allowed nahi hai."]);
        exit;
    }

    // Dynamic Host and Protocol for Wasmer
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "https://"; // Wasmer handles SSL edge
    $baseUrl = $protocol . $host . '/' . $targetDir;

    // Unique Content Hash (MD5) for Deduplication
    $fileHash = md5_file($file['tmp_name']);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check if duplicate file already exists
    $existingFiles = glob($targetDir . $fileHash . ".*");

    if (!empty($existingFiles)) {
        // Return existing file URL immediately!
        $existingFileName = basename($existingFiles[0]);
        echo json_encode([
            "status" => "success",
            "message" => "Same file pehle se exist karti hai! Instant link returned.",
            "duplicate" => true,
            "url" => $baseUrl . $existingFileName
        ]);
        exit;
    }

    // New Unique Filename
    $newFileName = $fileHash . ($extension ? '.' . $extension : '');
    $targetFilePath = $targetDir . $newFileName;

    // Move file to uploads directory
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        echo json_encode([
            "status" => "success",
            "message" => "File mast upload ho gayi!",
            "duplicate" => false,
            "url" => $baseUrl . $newFileName
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Wasmer storage issue: File save nahi ho saki."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request Method."]);
}
?>
