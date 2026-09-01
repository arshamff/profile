<?php
// config.php - تنظیمات اصلی پروژه
session_start();

define('BASE_PATH', __DIR__);
define('STORIES_DIR', BASE_PATH . '/stories');
define('DATA_DIR', BASE_PATH . '/data');
define('PROFILE_FILE', DATA_DIR . '/profile.json');
define('STATS_FILE', DATA_DIR . '/stats.json');

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'Arsham1386@'); // حتماً این رمز رو عوض کن

if (!is_dir(STORIES_DIR)) mkdir(STORIES_DIR, 0777, true);
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);

if (!file_exists(PROFILE_FILE)) {
    $default = [
        "name" => "نام شما",
        "username" => "Arshipesarr@",
        "bio" => "Lose Yourself",
        "avatar" => "assets/img/avatar.jpg",
        "online" => true,
        "song" => [
            "title" => "نام آهنگ",
            "artist" => "نام خواننده",
            "file" => "assets/audio/song.mp3",
            "cover" => "assets/img/song-cover.jpg"
        ],
        "socials" => [
            ["name" => "Telegram", "icon" => "fa-telegram", "url" => "https://t.me/Arshi_pesar"],
            ["name" => "Instagram", "icon" => "fa-instagram", "url" => "https://instagram.com/Arshipesarr"],
        ]
    ];
    file_put_contents(PROFILE_FILE, json_encode($default, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if (!file_exists(STATS_FILE)) {
    file_put_contents(STATS_FILE, json_encode([]));
}
