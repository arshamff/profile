<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['error'=>'دسترسی غیرمجاز']); exit; }

$action = $_POST['action'] ?? '';
if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(403); echo json_encode(['error'=>'توکن نامعتبر']); exit; }

$profile = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);

if ($action === 'upload' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeMap = $GLOBALS['ALLOWED_IMAGE_MIME'];
    if (!isset($mimeMap[$ext]) || $file['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['error'=>'فرمت مجاز نیست']); exit; }
    if ($file['size'] > STORY_MAX_UPLOAD_SIZE) { http_response_code(400); echo json_encode(['error'=>'حجم زیاد است']); exit; }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($finfo->file($file['tmp_name']), $mimeMap[$ext])) { http_response_code(400); echo json_encode(['error'=>'نوع فایل نامعتبر']); exit; }

    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], GALLERY_DIR . '/' . $newName);
    $profile['gallery'][] = ['image' => 'assets/img/gallery/' . $newName];
    file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true, 'image' => 'assets/img/gallery/' . $newName]);
    exit;
}

if ($action === 'delete') {
    $img = $_POST['image'] ?? '';
    $profile['gallery'] = array_values(array_filter($profile['gallery'], fn($g) => $g['image'] !== $img));
    file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $path = BASE_PATH . '/' . $img;
    if (file_exists($path) && strpos(realpath($path), realpath(GALLERY_DIR)) === 0) unlink($path);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'عملیات نامعتبر']);
