<?php
$targetDir = "uploads/";
$maxFileSize = 100 * 1024 * 1024; // Wasmer Friendly 100 MB Limit
$metadataFile = "uploads_meta.json";

// Helper: Load & Save Metadata for Expiry Tracking
function getMetadata($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
}
function saveMetadata($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Cleanup Expired Files Automatically on Request
if (file_exists($targetDir)) {
    $meta = getMetadata($metadataFile);
    $currentTime = time();
    $updatedMeta = [];

    foreach ($meta as $filePath => $info) {
        if (isset($info['expire_at']) && $info['expire_at'] > 0 && $currentTime >= $info['expire_at']) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        } else {
            $updatedMeta[$filePath] = $info;
        }
    }
    saveMetadata($metadataFile, $updatedMeta);
}

// Handle Direct Delete Action (API Request)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
    header('Content-Type: application/json');
    $fileToDelete = isset($_GET['file']) ? basename($_GET['file']) : '';
    $filePath = $targetDir . $fileToDelete;

    if ($fileToDelete && file_exists($filePath)) {
        @unlink($filePath);
        $meta = getMetadata($metadataFile);
        unset($meta[$filePath]);
        saveMetadata($metadataFile, $meta);

        echo json_encode(["status" => "success", "message" => "File permanently delete ho gayi!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "File nahi mili ya already delete ho chuki hai!"]);
    }
    exit;
}

// Server-side Upload Handling (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!file_exists($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    if (!isset($_FILES['fileToUpload']) || $_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "Valid file select nahi ki!"]);
        exit;
    }

    $file = $_FILES['fileToUpload'];
    $expirationSeconds = isset($_POST['expiration']) ? (int)$_POST['expiration'] : 0;

    // 100MB Limit Check
    if ($file['size'] > $maxFileSize) {
        echo json_encode(["status" => "error", "message" => "File 100MB se badi hai! Max allowed 100MB hai."]);
        exit;
    }

    // Dynamic Protocol & Host Detection
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $scriptPath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;
    $baseUrl = $protocol . $host . $scriptPath . '/';

    // File Content Hash (MD5) for Deduplication
    $fileHash = md5_file($file['tmp_name']);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check if duplicate exists
    $existingFiles = glob($targetDir . $fileHash . ".*");

    if (!empty($existingFiles)) {
        $existingFileName = basename($existingFiles[0]);
        echo json_encode([
            "status" => "success",
            "message" => "Same file pehle se exist karti hai! Instant link generated.",
            "duplicate" => true,
            "url" => $baseUrl . $targetDir . $existingFileName,
            "filename" => $existingFileName
        ]);
        exit;
    }

    // Save with unique content hash filename
    $newFileName = $fileHash . ($extension ? '.' . $extension : '');
    $targetFilePath = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        // Track Expiration Metadata
        $meta = getMetadata($metadataFile);
        $expireAt = ($expirationSeconds > 0) ? (time() + $expirationSeconds) : 0;
        $meta[$targetFilePath] = [
            "uploaded_at" => time(),
            "expire_at" => $expireAt
        ];
        saveMetadata($metadataFile, $meta);

        echo json_encode([
            "status" => "success",
            "message" => "File mast upload ho gayi!",
            "duplicate" => false,
            "url" => $baseUrl . $targetFilePath,
            "filename" => $newFileName,
            "expire_in" => $expirationSeconds
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Server error: File save nahi ho saki."]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DropHost — Ultra Clean File Hosting</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
    }

    body {
      background-color: #f8fafc;
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      width: 100%;
      max-width: 520px;
      background: #ffffff;
      padding: 40px;
      border-radius: 28px;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.06);
      border: 1px solid #f1f5f9;
      text-align: center;
    }

    .logo {
      font-size: 30px;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 6px;
      letter-spacing: -0.8px;
    }

    .logo span {
      color: #6366f1;
    }

    .subtitle {
      font-size: 14px;
      color: #64748b;
      margin-bottom: 28px;
    }

    /* Expiry Select Option */
    .controls-bar {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f8fafc;
      padding: 10px 16px;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
    }

    .controls-label {
      font-size: 13px;
      font-weight: 600;
      color: #475569;
    }

    .expiry-select {
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 13px;
      font-weight: 500;
      color: #334155;
      outline: none;
      cursor: pointer;
    }

    /* Drop Zone */
    .drop-zone {
      border: 2px dashed #cbd5e1;
      border-radius: 20px;
      padding: 44px 20px;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      background: #fafafa;
    }

    .drop-zone:hover, .drop-zone.dragover {
      border-color: #6366f1;
      background: #f5f3ff;
      transform: translateY(-2px);
    }

    .drop-zone-icon {
      width: 52px;
      height: 52px;
      background: #eef2ff;
      color: #6366f1;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 14px;
      transition: transform 0.2s ease;
    }

    .drop-zone:hover .drop-zone-icon {
      transform: scale(1.1);
    }

    .drop-zone-text {
      font-size: 15px;
      font-weight: 600;
      color: #1e293b;
    }

    .drop-zone-subtext {
      font-size: 12px;
      color: #94a3b8;
      margin-top: 6px;
    }

    #file-input {
      display: none;
    }

    /* Modern Loader & Progress */
    .file-details {
      margin-top: 24px;
      display: none;
      text-align: left;
      background: #f8fafc;
      padding: 20px;
      border-radius: 16px;
      border: 1px solid #f1f5f9;
    }

    .file-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 12px;
    }

    .spinner-box {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .spinner {
      width: 16px;
      height: 16px;
      border: 2px solid #e2e8f0;
      border-top-color: #6366f1;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .progress-bar-bg {
      width: 100%;
      height: 8px;
      background: #e2e8f0;
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #6366f1, #818cf8);
      border-radius: 4px;
      transition: width 0.2s ease;
    }

    /* Result Actions Box */
    .result-box {
      margin-top: 24px;
      display: none;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .link-container {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }

    .link-input {
      flex: 1;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid #cbd5e1;
      font-size: 13px;
      outline: none;
      background: #ffffff;
      color: #334155;
      font-weight: 500;
    }

    .action-btn {
      padding: 12px 18px;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .copy-btn {
      background: #6366f1;
      color: #ffffff;
    }

    .copy-btn:hover {
      background: #4f46e5;
    }

    .delete-btn {
      background: #fef2f2;
      color: #ef4444;
      border: 1px solid #fecaca;
    }

    .delete-btn:hover {
      background: #fee2e2;
    }

    .error-msg {
      color: #ef4444;
      font-size: 13px;
      margin-top: 16px;
      display: none;
      background: #fef2f2;
      padding: 12px;
      border-radius: 12px;
      border: 1px solid #fecaca;
      font-weight: 500;
    }

    footer {
      margin-top: 32px;
      font-size: 12px;
      color: #94a3b8;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="logo">Drop<span>Host</span></div>
    <p class="subtitle">Ultra-fast temporary & permanent file storage</p>

    <!-- Expiry Setting Control -->
    <div class="controls-bar">
      <span class="controls-label">Auto Delete File:</span>
      <select id="expiry-select" class="expiry-select">
        <option value="0">Never (Permanent)</option>
        <option value="10">After 10 Seconds</option>
        <option value="30">After 30 Seconds</option>
        <option value="60">After 1 Minute</option>
        <option value="300">After 5 Minutes</option>
      </select>
    </div>

    <!-- Dropzone -->
    <div class="drop-zone" id="drop-zone">
      <div class="drop-zone-icon">
        <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
        </svg>
      </div>
      <div class="drop-zone-text">Click to upload or drag & drop</div>
      <div class="drop-zone-subtext">Supports images, PDFs & files up to 100MB</div>
      <input type="file" id="file-input">
    </div>

    <!-- Upload Progress with Animated Spinner -->
    <div class="file-details" id="file-details">
      <div class="file-info">
        <div class="spinner-box">
          <div class="spinner"></div>
          <span id="file-name">Uploading...</span>
        </div>
        <span id="progress-percent">0%</span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" id="progress-bar"></div>
      </div>
    </div>

    <!-- Error Message -->
    <div class="error-msg" id="error-msg">Something went wrong.</div>

    <!-- Result Box with Copy and Delete Buttons -->
    <div class="result-box" id="result-box">
      <div class="link-container">
        <input type="text" class="link-input" id="result-url" readonly>
        <button class="action-btn copy-btn" id="copy-btn">Copy</button>
        <button class="action-btn delete-btn" id="delete-btn">Delete</button>
      </div>
    </div>
  </div>

  <footer>Wasmer Powered High Speed Server</footer>

  <script>
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const fileDetails = document.getElementById('file-details');
    const fileName = document.getElementById('file-name');
    const progressPercent = document.getElementById('progress-percent');
    const progressBar = document.getElementById('progress-bar');
    const resultBox = document.getElementById('result-box');
    const resultUrl = document.getElementById('result-url');
    const copyBtn = document.getElementById('copy-btn');
    const deleteBtn = document.getElementById('delete-btn');
    const errorMsg = document.getElementById('error-msg');
    const expirySelect = document.getElementById('expiry-select');

    const MAX_SIZE_MB = 100;
    let currentUploadedFilename = '';

    dropZone.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      }, false);
    });

    dropZone.addEventListener('drop', (e) => {
      const files = e.dataTransfer.files;
      if (files.length) handleUpload(files[0]);
    });

    fileInput.addEventListener('change', () => {
      if (fileInput.files.length) handleUpload(fileInput.files[0]);
    });

    function handleUpload(file) {
      if (file.size > MAX_SIZE_MB * 1024 * 1024) {
        showError(`File limit exceeded! Max size allowed is ${MAX_SIZE_MB}MB.`);
        return;
      }

      errorMsg.style.display = 'none';
      resultBox.style.display = 'none';
      fileDetails.style.display = 'block';
      fileName.textContent = file.name;
      progressBar.style.width = '0%';
      progressPercent.textContent = '0%';

      const formData = new FormData();
      formData.append('fileToUpload', file);
      formData.append('expiration', expirySelect.value);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'index.php', true);

      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
          progressPercent.textContent = percent + '%';
        }
      };

      xhr.onload = function() {
        if (xhr.status === 200) {
          try {
            const res = JSON.parse(xhr.responseText);
            if (res.status === 'success') {
              resultUrl.value = res.url;
              currentUploadedFilename = res.filename;
              fileDetails.style.display = 'none';
              resultBox.style.display = 'block';
            } else {
              showError(res.message);
            }
          } catch(e) {
            showError('Server side response error.');
          }
        } else {
          showError('Upload failed.');
        }
      };

      xhr.onerror = function() {
        showError('Network error connection!');
      };

      xhr.send(formData);
    }

    function showError(msg) {
      errorMsg.textContent = msg;
      errorMsg.style.display = 'block';
      fileDetails.style.display = 'none';
    }

    // Copy Action
    copyBtn.addEventListener('click', () => {
      resultUrl.select();
      navigator.clipboard.writeText(resultUrl.value);
      copyBtn.textContent = 'Copied!';
      setTimeout(() => copyBtn.textContent = 'Copy', 2000);
    });

    // Delete Action
    deleteBtn.addEventListener('click', () => {
      if (!currentUploadedFilename) return;
      
      deleteBtn.textContent = 'Deleting...';
      fetch(`index.php?action=delete&file=${encodeURIComponent(currentUploadedFilename)}`, {
        method: 'DELETE'
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          resultBox.style.display = 'none';
          showError('File deleted successfully from server!');
        } else {
          alert(data.message);
        }
      })
      .catch(() => alert('Network error during deletion.'))
      .finally(() => {
        deleteBtn.textContent = 'Delete';
      });
    });
  </script>
</body>
</html>
