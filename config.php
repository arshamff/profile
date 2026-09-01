<?php
// config.php - تنظیمات اصلی پروژه
session_start();

define('BASE_PATH', __DIR__);
define('STORIES_DIR', BASE_PATH . '/stories');
define('DATA_DIR', BASE_PATH . '/data');
define('PROFILE_FILE', DATA_DIR . '/profile.json');
define('STATS_FILE', DATA_DIR . '/stats.json');
define('STORIES_META_FILE', DATA_DIR . '/stories_meta.json');
define('VISITS_FILE', DATA_DIR . '/visits.json');

// --- ورود ادمین ---
define('ADMIN_USER', 'admin');
// این هش را با اجرای generate_hash.php بساز و همین‌جا جای‌گزین کن، بعد آن فایل را حذف کن
define('ADMIN_PASS_HASH', '$2y$10$NnHntIHJdn.T91yaYhjqXOPs2mJFjWNeuyP6eDCz67UWHrhFfpYIG');

// --- تنظیمات استوری ---
define('STORY_EXPIRY_HOURS', 24);            // 0 = هیچ‌وقت منقضی نشو
define('STORY_DEFAULT_DURATION', 5000);      // مدت‌زمان پیش‌فرض تصویر (میلی‌ثانیه)
define('STORY_MAX_VIDEO_DURATION', 15000);   // سقف مدت‌زمان ویدیو در ویووِر
define('STORY_MAX_UPLOAD_SIZE', 25 * 1024 * 1024); // 25MB

$GLOBALS['ALLOWED_STORY_MIME'] = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'gif' => ['image/gif'], 'webp' => ['image/webp'],
    'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
];

if (!is_dir(STORIES_DIR)) mkdir(STORIES_DIR, 0755, true);
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

if (!file_exists(PROFILE_FILE)) {
    $default = [
        "name" => "نام شما",
        "username" => "Arshipesarr@",
        "bio" => "Lose Yourself",
        "avatar" => "assets/img/avatar.jpg",
        "online" => true,
        "song" => [
            "title" => "نام آهنگ", "artist" => "نام خواننده",
            "file" => "assets/audio/song.mp3", "cover" => "assets/img/song-cover.jpg"
        ],
        "socials" => [
            ["name" => "Telegram", "icon" => "fa-telegram", "url" => "https://t.me/Arshi_pesar"],
            ["name" => "Instagram", "icon" => "fa-instagram", "url" => "https://instagram.com/Arshipesarr"],
        ],
        "skills" => [
            ["name" => "PHP", "level" => 85],
            ["name" => "JavaScript", "level" => 80],
            ["name" => "طراحی UI/UX", "level" => 70],
        ],
        "projects" => []
    ];
    file_put_contents(PROFILE_FILE, json_encode($default, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if (!file_exists(STATS_FILE)) file_put_contents(STATS_FILE, json_encode([]));
if (!file_exists(STORIES_META_FILE)) file_put_contents(STORIES_META_FILE, json_encode([]));
if (!file_exists(VISITS_FILE)) file_put_contents(VISITS_FILE, json_encode(['total' => 0, 'days' => []]));

// --- CSRF ---
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check($token): bool {
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// --- خواندن/نوشتن JSON با قفل فایل ---
function read_json_locked(string $file) {
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?: [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}
function write_json_locked(string $file, $data): void {
    $fp = fopen($file, 'c+');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}
