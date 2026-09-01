<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = basename($input['id'] ?? '');
$action = $input['action'] ?? '';

if (!$id || !in_array($action, ['like','view']) || !file_exists(STORIES_DIR . '/' . $id)) {
    http_response_code(400);
    echo json_encode(['error' => 'درخواست نامعتبر']);
    exit;
}

$fp = fopen(STATS_FILE, 'c+');
flock($fp, LOCK_EX);
$content = stream_get_contents($fp);
$stats = json_decode($content, true) ?: [];
if (!isset($stats[$id])) $stats[$id] = ['views' => 0, 'likes' => 0];

$liked = null;
if ($action === 'like') {
    $_SESSION['liked'] = $_SESSION['liked'] ?? [];
    if (in_array($id, $_SESSION['liked'])) {
        $stats[$id]['likes'] = max(0, $stats[$id]['likes'] - 1);
        $_SESSION['liked'] = array_values(array_diff($_SESSION['liked'], [$id]));
        $liked = false;
    } else {
        $stats[$id]['likes']++;
        $_SESSION['liked'][] = $id;
        $liked = true;
    }
} else {
    $stats[$id]['views']++;
}

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($stats, JSON_UNESCAPED_UNICODE));
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['views' => $stats[$id]['views'], 'likes' => $stats[$id]['likes'], 'liked' => $liked]);
