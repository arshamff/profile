<?php
require_once __DIR__ . '/config.php';
$profile = json_decode(file_get_contents(PROFILE_FILE), true);
$stats = json_decode(file_get_contents(STATS_FILE), true) ?: [];

$stories = [];
$allowed = ['jpg','jpeg','png','gif','webp','mp4','webm'];
foreach (glob(STORIES_DIR . '/*') as $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    $filename = basename($path);
    $stories[] = [
        'id' => $filename,
        'url' => 'stories/' . $filename,
        'type' => in_array($ext, ['mp4','webm']) ? 'video' : 'image',
        'time' => filemtime($path),
        'views' => $stats[$filename]['views'] ?? 0,
        'likes' => $stats[$filename]['likes'] ?? 0,
    ];
}
usort($stories, fn($a,$b) => $b['time'] - $a['time']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
<title><?= htmlspecialchars($profile['name']) ?> | پروفایل</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<canvas id="bg-canvas"></canvas>

<div class="app">
  <header class="profile-header">
    <div class="avatar-ring <?= !empty($stories) ? 'has-story' : '' ?>" id="avatar-ring">
      <img src="<?= htmlspecialchars($profile['avatar']) ?>" alt="avatar" class="avatar-img">
      <?php if ($profile['online']): ?><span class="online-dot"></span><?php endif; ?>
    </div>
    <h1 class="profile-name"><?= htmlspecialchars($profile['name']) ?> <i class="fa-solid fa-certificate verified"></i></h1>
    <p class="profile-username">@<?= htmlspecialchars(ltrim($profile['username'],'@')) ?></p>
    <p class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>

    <div class="social-row">
      <?php foreach ($profile['socials'] as $s): ?>
        <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" class="social-btn" title="<?= htmlspecialchars($s['name']) ?>">
          <i class="fa-brands <?= htmlspecialchars($s['icon']) ?>"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </header>

  <section class="stories-section">
    <h3 class="section-title"><i class="fa-solid fa-circle-play"></i> استوری‌ها</h3>
    <div class="stories-scroll" id="stories-scroll">
      <?php foreach ($stories as $i => $st): ?>
        <div class="story-circle" data-index="<?= $i ?>">
          <div class="story-ring">
            <?php if ($st['type'] === 'video'): ?>
              <video src="<?= htmlspecialchars($st['url']) ?>" muted></video>
            <?php else: ?>
              <img src="<?= htmlspecialchars($st['url']) ?>" alt="story">
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($stories)): ?>
        <p class="empty-msg">هنوز استوری‌ای اضافه نشده — از پنل مدیریت اضافه کن</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="music-card">
    <div class="vinyl" id="vinyl">
      <img src="<?= htmlspecialchars($profile['song']['cover']) ?>" alt="cover">
    </div>
    <div class="music-info">
      <p class="song-title"><?= htmlspecialchars($profile['song']['title']) ?></p>
      <p class="song-artist"><?= htmlspecialchars($profile['song']['artist']) ?></p>
      <div class="music-progress"><div class="music-progress-fill" id="progress-fill"></div></div>
    </div>
    <button class="play-btn" id="play-btn"><i class="fa-solid fa-play"></i></button>
    <audio id="audio-player" src="<?= htmlspecialchars($profile['song']['file']) ?>"></audio>
  </section>

  <section class="about-card">
    <h3 class="section-title"><i class="fa-solid fa-user"></i> درباره من</h3>
    <p class="about-text"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
    <div class="tags">
      <span class="tag">🎮 گیمر</span>
      <span class="tag">💻 برنامه‌نویس</span>
      <span class="tag">🎵 موزیک‌باز</span>
      <span class="tag">📷 عکاس</span>
    </div>
  </section>

  <footer class="app-footer">
    <button class="theme-toggle" id="theme-toggle"><i class="fa-solid fa-moon"></i></button>
    <p>ساخته‌شده با <i class="fa-solid fa-heart" style="color:#e74c3c"></i> روی XAMPP</p>
  </footer>
</div>

<div class="story-viewer" id="story-viewer">
  <div class="story-progress-bar" id="story-progress-bar"></div>
  <div class="story-header">
    <img id="viewer-avatar" src="<?= htmlspecialchars($profile['avatar']) ?>">
    <span id="viewer-name"><?= htmlspecialchars($profile['name']) ?></span>
    <span id="viewer-time"></span>
    <button id="close-viewer"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="story-content" id="story-content"></div>
  <div class="story-nav">
    <div class="nav-zone left" id="nav-prev"></div>
    <div class="nav-zone right" id="nav-next"></div>
  </div>
  <div class="story-footer">
    <button id="like-btn"><i class="fa-regular fa-heart"></i> <span id="like-count">0</span></button>
    <span id="view-count"><i class="fa-solid fa-eye"></i> <span id="view-num">0</span></span>
  </div>
  <div class="heart-burst" id="heart-burst"></div>
</div>

<script>
  window.STORIES = <?= json_encode($stories, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
