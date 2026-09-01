<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$today = date('Y-m-d');
$fp = fopen(VISITS_FILE, 'c+');
flock($fp, LOCK_EX);
$data = json_decode(stream_get_contents($fp), true) ?: ['total' => 0, 'days' => []];

if (empty($_SESSION['visited_' . $today])) {
    $data['total'] = ($data['total'] ?? 0) + 1;
    $data['days'][$today] = ($data['days'][$today] ?? 0) + 1;
    $_SESSION['visited_' . $today] = true;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
}
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['total' => $data['total']]);
