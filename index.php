<?php
// 1. Backend Configuration & Upload Logic
$uploadDir = 'uploads/';
$message = '';
$uploadedFileUrl = '';

// Agar folder nahi bana hai toh automatically bana dega
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $file = $_FILES['fileToUpload'];
    
    // File details
    $fileName = basename($file['name']);
    $targetFilePath = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    
    // Sirf images allowed hain (Catbox style restricted version)
    $allowedTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
    
    if (empty($fileName)) {
        $message = "Bhai, pehle koi photo select toh kar le!";
    } elseif ($file['error'] !== 0) {
        $message = "File upload me kuch locha ho gaya. Error Code: " . $file['error'];
    } elseif (!in_array($fileType, $allowedTypes)) {
        $message = "Hoshiyari nahi! Sirf JPG, JPEG, PNG, WEBP aur GIF allowed hain.";
    } elseif ($file['size'] > 5000000) { // 5MB limit
        $message = "Bhai, itna bada bhandara nahi chahiye. 5MB se kam ki photo laa.";
    } else {
        // Sanitize file name to avoid breaks (Catbox style naming shortcut)
        $cleanName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
        $targetFilePath = $uploadDir . $cleanName;
        
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            // Success! Generate Link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $uploadedFileUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $targetFilePath;
            $message = "Success! Tera maal upload ho gaya hai.";
        } else {
            $message = "Server ne mana kar diya. Permissions check kar uploads folder ki.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catbox Style Uploader</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #121214; color: #e1e1e6; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: #202024; padding: 40px; border-radius: 12px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 8px 24px rgba(0,0,0,0.5); border: 1px solid #29292e; }
        h1 { font-size: 2rem; color: #00b37e; margin-bottom: 10px; font-weight: 700; letter-spacing: 1px; }
        p.subtitle { color: #8d8d99; font-size: 0.9rem; margin-bottom: 30px; }
        
        .upload-box { border: 2px dashed #00b37e; padding: 30px; border-radius: 8px; background: #121214; cursor: pointer; transition: 0.3s; position: relative; }
        .upload-box:hover { background: #1a1a1e; }
        .upload-box input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .upload-box p { color: #c4c4cc; font-size: 1rem; }
        .upload-box span { display: block; color: #8d8d99; font-size: 0.8rem; margin-top: 5px; }

        button { width: 100%; margin-top: 20px; background: #00b37e; color: #fff; border: none; padding: 12px; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.2s; }
        button:hover { background: #00875f; }
        
        .status-msg { margin-top: 20px; padding: 10px; border-radius: 6px; font-size: 0.95rem; background: #29292e; color: #ff79c6; }
        .success-msg { color: #00b37e; }
        
        .result-box { margin-top: 25px; background: #121214; padding: 15px; border-radius: 6px; border: 1px solid #29292e; text-align: left; }
        .result-box label { font-size: 0.8rem; color: #8d8d99; display: block; margin-bottom: 5px; }
        .url-input { width: 100%; background: #202024; border: 1px solid #29292e; color: #00b37e; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; read-only: true; }
    </style>
</head>
<body>

<div class="container">
    <h1>CATBOX LITE</h1>
    <p class="subtitle">Direct image uploader. No corporate bulls**t.</p>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="upload-box">
            <input type="file" name="fileToUpload" id="fileInput" onchange="updateFileName()">
            <p id="fileNameDisplay">Select or Drop your Photo here</p>
            <span>(JPG, PNG, WEBP, GIF up to 5MB)</span>
        </div>
        
        <button type="submit">Upload To Space</button>
    </form>

    <?php if (!empty($message)): ?>
        <div class="status-msg <?php echo ($uploadedFileUrl) ? 'success-msg' : ''; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($uploadedFileUrl)): ?>
        <div class="result-box">
            <label>Bhai ye rha tera direct link:</label>
            <input type="text" class="url-input" value="<?php echo $uploadedFileUrl; ?>" onclick="this.select();" readonly>
        </div>
    <?php endif; ?>
</div>

<script>
function updateFileName() {
    const input = document.getElementById('fileInput');
    const display = document.getElementById('fileNameDisplay');
    if(input.files.length > 0) {
        display.innerText = "Selected: " + input.files[0].name;
        display.style.color = "#00b37e";
    }
}
</script>
</body>
</html>
