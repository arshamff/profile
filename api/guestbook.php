<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        if (empty($_SESSION['is_admin'])) { http_response_code(403); echo json_encode(['error'=>'دسترسی غیرمجاز']); exit; }
        if (!csrf_check($input['csrf'] ?? '')) { http_response_code(403); echo json_encode(['error'=>'توکن نامعتبر']); exit; }
        $id = $input['id'] ?? '';
        $list = read_json_locked(GUESTBOOK_FILE);
        $list = array_values(array_filter($list, fn($g) => $g['id'] !== $id));
        write_json_locked(GUESTBOOK_FILE, $list);
        echo json_encode(['ok' => true]);
        exit;
    }

    // افزودن پیام جدید
    if (!empty($input['website'])) { // هانی‌پات: ربات‌ها این فیلد را پر می‌کنند
        echo json_encode(['ok' => true]); // پاسخ موفق تقلبی به ربات
        exit;
    }
    $now = time();
    if (!empty($_SESSION['gb_last']) && ($now - $_SESSION['gb_last']) < GUESTBOOK_RATE_LIMIT_SEC) {
        http_response_code(429);
        echo json_encode(['error' => 'کمی صبر کن و دوباره تلاش کن']);
        exit;
    }
    $name = trim(mb_substr($input['name'] ?? 'ناشناس', 0, 40));
    $message = trim(mb_substr($input['message'] ?? '', 0, GUESTBOOK_MAX_LEN));
    if ($name === '') $name = 'ناشناس';
    if ($message === '') { http_response_code(400); echo json_encode(['error' => 'متن پیام خالی است']); exit; }

    $entry = [
        'id' => bin2hex(random_bytes(6)),
        'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'time' => $now,
    ];
    $list = read_json_locked(GUESTBOOK_FILE);
    $list[] = $entry;
    write_json_locked(GUESTBOOK_FILE, $list);
    $_SESSION['gb_last'] = $now;
    echo json_encode(['ok' => true, 'entry' => $entry]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'متد نامعتبر']);
