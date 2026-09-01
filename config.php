<?php
// config.php - تنظیمات اصلی پروژه
session_start();

define('BASE_PATH', __DIR__);
define('STORIES_DIR', BASE_PATH . '/stories');
define('DATA_DIR', BASE_PATH . '/data');
define('GALLERY_DIR', BASE_PATH . '/assets/img/gallery');
define('PROFILE_FILE', DATA_DIR . '/profile.json');
define('STATS_FILE', DATA_DIR . '/stats.json');
define('STORIES_META_FILE', DATA_DIR . '/stories_meta.json');
define('VISITS_FILE', DATA_DIR . '/visits.json');
define('GUESTBOOK_FILE', DATA_DIR . '/guestbook.json');

// --- ورود ادمین ---
define('ADMIN_USER', 'admin');
// این هش را با اجرای generate_hash.php بساز و همین‌جا جای‌گزین کن، بعد آن فایل را حذف کن
define('ADMIN_PASS_HASH', '$2y$10$NnHntIHJdn.T91yaYhjqXOPs2mJFjWNeuyP6eDCz67UWHrhFfpYIG');

// --- تنظیمات استوری ---
define('STORY_EXPIRY_HOURS', 24);            // 0 = هیچ‌وقت منقضی نشو
define('STORY_DEFAULT_DURATION', 5000);      // مدت‌زمان پیش‌فرض تصویر (میلی‌ثانیه)
define('STORY_MAX_VIDEO_DURATION', 15000);   // سقف مدت‌زمان ویدیو در ویووِر
define('STORY_MAX_UPLOAD_SIZE', 25 * 1024 * 1024); // 25MB

// --- تنظیمات دفترچه یادگاری ---
define('GUESTBOOK_MAX_LEN', 300);
define('GUESTBOOK_RATE_LIMIT_SEC', 30); // فاصلهٔ مجاز بین دو پیام از یک نشست

$GLOBALS['ALLOWED_STORY_MIME'] = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'gif' => ['image/gif'], 'webp' => ['image/webp'],
    'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
];
$GLOBALS['ALLOWED_IMAGE_MIME'] = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'gif' => ['image/gif'], 'webp' => ['image/webp'],
];

foreach ([STORIES_DIR, DATA_DIR, GALLERY_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

if (!file_exists(PROFILE_FILE)) {
    $default = [
        "name" => "نام شما",
        "username" => "username",
        "bio" => "بیو خودتو اینجا بنویس",
        "avatar" => "assets/img/avatar.jpg",
        "online" => true,
        "stats" => ["followers" => 0, "following" => 0],
        "contact" => ["phone" => "", "email" => ""],
        "playlist" => [[
            "title" => "نام آهنگ", "artist" => "نام خواننده",
            "file" => "assets/audio/song.mp3", "cover" => "assets/img/song-cover.jpg"
        ]],
        "socials" => [
            ["name" => "Telegram", "icon" => "fa-telegram", "url" => "https://t.me/"],
        ],
        "skills" => [
            ["name" => "PHP", "level" => 85, "icon" => "fa-brands fa-php"],
        ],
        "projects" => [],
        "gallery" => []
    ];
    file_put_contents(PROFILE_FILE, json_encode($default, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if (!file_exists(STATS_FILE)) file_put_contents(STATS_FILE, json_encode([]));
if (!file_exists(STORIES_META_FILE)) file_put_contents(STORIES_META_FILE, json_encode([]));
if (!file_exists(VISITS_FILE)) file_put_contents(VISITS_FILE, json_encode(['total' => 0, 'days' => []]));
if (!file_exists(GUESTBOOK_FILE)) file_put_contents(GUESTBOOK_FILE, json_encode([]));

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

// --- نرمال‌سازی پروفایل برای همخوانی با نسخه‌های قدیمی داده ---
function normalize_profile(array $p): array {
    $p['skills'] = $p['skills'] ?? [];
    $p['projects'] = $p['projects'] ?? [];
    $p['gallery'] = $p['gallery'] ?? [];
    $p['stats'] = $p['stats'] ?? ['followers' => 0, 'following' => 0];
    $p['contact'] = $p['contact'] ?? ['phone' => '', 'email' => ''];
    if (!isset($p['playlist']) && isset($p['song'])) {
        $p['playlist'] = [$p['song']];
    }
    $p['playlist'] = $p['playlist'] ?? [];
    $p['socials'] = $p['socials'] ?? [];
    return $p;
}
