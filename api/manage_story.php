<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

$action = $_POST['action'] ?? '';
if (!csrf_check($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'توکن نامعتبر']);
    exit;
}

$meta = read_json_locked(STORIES_META_FILE);

if ($action === 'upload' && isset($_FILES['story'])) {
    $file = $_FILES['story'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeMap = $GLOBALS['ALLOWED_STORY_MIME'];

    if (!isset($mimeMap[$ext]) || $file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400); echo json_encode(['error' => 'فرمت فایل مجاز نیست']); exit;
    }
    if ($file['size'] > STORY_MAX_UPLOAD_SIZE) {
        http_response_code(400); echo json_encode(['error' => 'حجم فایل بیش از حد مجاز است']); exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    if (!in_array($realMime, $mimeMap[$ext])) {
        http_response_code(400); echo json_encode(['error' => 'نوع فایل با پسوند مطابقت ندارد']); exit;
    }

    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], STORIES_DIR . '/' . $newName)) {
        http_response_code(500); echo json_encode(['error' => 'خطا در ذخیره فایل']); exit;
    }
    $meta[$newName] = ['caption' => trim($_POST['caption'] ?? ''), 'pinned' => !empty($_POST['pinned']), 'duration' => null];
    write_json_locked(STORIES_META_FILE, $meta);
    echo json_encode(['ok' => true, 'id' => $newName]);
    exit;
}

if ($action === 'delete') {
    $id = basename($_POST['id'] ?? '');
    $path = STORIES_DIR . '/' . $id;
    if ($id && file_exists($path)) {
        unlink($path);
        unset($meta[$id]);
        write_json_locked(STORIES_META_FILE, $meta);
        $stats = read_json_locked(STATS_FILE);
        unset($stats[$id]);
        write_json_locked(STATS_FILE, $stats);
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(404); echo json_encode(['error' => 'یافت نشد']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'عملیات نامعتبر']);
