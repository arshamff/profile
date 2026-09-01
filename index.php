<?php
require_once __DIR__ . '/config.php';

$profile = json_decode(file_get_contents(PROFILE_FILE), true);
$profile['skills'] = $profile['skills'] ?? [];
$profile['projects'] = $profile['projects'] ?? [];

$stats = read_json_locked(STATS_FILE);
$meta  = read_json_locked(STORIES_META_FILE);
$isAdmin = $_SESSION['is_admin'] ?? false;

$stories = [];
$allowed = ['jpg','jpeg','png','gif','webp','mp4','webm'];
$now = time();
foreach (glob(STORIES_DIR . '/*') as $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;
    $filename = basename($path);
    $m = $meta[$filename] ?? [];
    $pinned = $m['pinned'] ?? false;
    $time = filemtime($path);

    if (!$isAdmin && STORY_EXPIRY_HOURS > 0 && !$pinned && ($now - $time) > STORY_EXPIRY_HOURS * 3600) {
        continue; // برای بازدیدکننده عادی، استوری منقضی‌شده نشان داده نمی‌شود
    }

    $stories[] = [
        'id' => $filename,
        'url' => 'stories/' . $filename,
        'type' => in_array($ext, ['mp4','webm']) ? 'video' : 'image',
        'time' => $time,
        'views' => $stats[$filename]['views'] ?? 0,
        'likes' => $stats[$filename]['likes'] ?? 0,
        'caption' => $m['caption'] ?? '',
        'pinned' => (bool)$pinned,
        'duration' => $m['duration'] ?? null,
    ];
}
usort($stories, fn($a,$b) => $b['time'] - $a['time']);

$visits = read_json_locked(VISITS_FILE);
$avatarAbs = htmlspecialchars($profile['avatar']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
<title><?= htmlspecialchars($profile['name']) ?> | پروفایل</title>
<meta name="description" content="<?= htmlspecialchars(mb_substr($profile['bio'],0,150)) ?>">
<meta name="theme-color" content="#0b0b14">

<meta property="og:title" content="<?= htmlspecialchars($profile['name']) ?>">
<meta property="og:description" content="<?= htmlspecialchars(mb_substr($profile['bio'],0,150)) ?>">
<meta property="og:image" content="<?= $avatarAbs ?>">
<meta property="og:type" content="profile">
<meta name="twitter:card" content="summary">

<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="<?= $avatarAbs ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<canvas id="bg-canvas"></canvas>

<div class="app">
  <header class="profile-header">
    <div class="avatar-ring <?= !empty($stories) ? 'has-story' : '' ?>" id="avatar-ring">
      <img src="<?= $avatarAbs ?>" alt="avatar" class="avatar-img">
      <?php if ($profile['online']): ?><span class="online-dot"></span><?php endif; ?>
      <?php if ($isAdmin): ?>
        <button class="add-story-btn" id="add-story-btn" title="افزودن استوری"><i class="fa-solid fa-plus"></i></button>
      <?php endif; ?>
    </div>
    <h1 class="profile-name"><?= htmlspecialchars($profile['name']) ?> <i class="fa-solid fa-certificate verified"></i></h1>
    <p class="profile-username">@<?= htmlspecialchars(ltrim($profile['username'],'@')) ?></p>
    <p class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>

    <div class="social-row">
      <?php foreach ($profile['socials'] as $s): ?>
        <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener" class="social-btn" title="<?= htmlspecialchars($s['name']) ?>">
          <i class="fa-brands <?= htmlspecialchars($s['icon']) ?>"></i>
        </a>
      <?php endforeach; ?>
      <button class="social-btn" id="share-btn" title="اشتراک‌گذاری پروفایل"><i class="fa-solid fa-share-nodes"></i></button>
    </div>
  </header>

  <section class="stories-section">
    <h3 class="section-title"><i class="fa-solid fa-circle-play"></i> استوری‌ها</h3>
    <div class="stories-scroll" id="stories-scroll">
      <?php foreach ($stories as $i => $st): ?>
        <div class="story-circle <?= $st['pinned'] ? 'pinned' : '' ?>" data-index="<?= $i ?>">
          <div class="story-ring">
            <?php if ($st['type'] === 'video'): ?>
              <video src="<?= htmlspecialchars($st['url']) ?>" muted preload="metadata"></video>
            <?php else: ?>
              <img src="<?= htmlspecialchars($st['url']) ?>" alt="story" loading="lazy">
            <?php endif; ?>
          </div>
          <?php if ($st['pinned']): ?><i class="fa-solid fa-thumbtack pin-badge"></i><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($stories)): ?>
        <p class="empty-msg">هنوز استوری‌ای اضافه نشده<?= $isAdmin ? ' — از دکمه‌ی + بالای عکس‌پروفایل اضافه کن' : '' ?></p>
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

  <?php if (!empty($profile['skills'])): ?>
  <section class="skills-card">
    <h3 class="section-title"><i class="fa-solid fa-chart-simple"></i> مهارت‌ها</h3>
    <?php foreach ($profile['skills'] as $sk): ?>
      <div class="skill-row">
        <div class="skill-label"><span><?= htmlspecialchars($sk['name']) ?></span><span><?= (int)$sk['level'] ?>٪</span></div>
        <div class="skill-bar"><div class="skill-fill" data-level="<?= (int)$sk['level'] ?>"></div></div>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($profile['projects'])): ?>
  <section class="projects-card">
    <h3 class="section-title"><i class="fa-solid fa-diagram-project"></i> پروژه‌ها</h3>
    <div class="projects-grid">
      <?php foreach ($profile['projects'] as $pr): ?>
        <a class="project-item" href="<?= htmlspecialchars($pr['url'] ?? '#') ?>" target="_blank" rel="noopener">
          <?php if (!empty($pr['image'])): ?><img src="<?= htmlspecialchars($pr['image']) ?>" alt="" loading="lazy"><?php endif; ?>
          <div class="project-info">
            <p class="project-title"><?= htmlspecialchars($pr['title']) ?></p>
            <p class="project-desc"><?= htmlspecialchars($pr['desc'] ?? '') ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

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
    <p class="visits-count"><i class="fa-solid fa-eye"></i> <span id="visits-num"><?= (int)($visits['total'] ?? 0) ?></span> بازدید پروفایل</p>
    <p>ساخته‌شده با <i class="fa-solid fa-heart" style="color:#e74c3c"></i> روی XAMPP</p>
  </footer>
</div>

<div class="story-viewer" id="story-viewer">
  <div class="story-progress-bar" id="story-progress-bar"></div>
  <div class="story-header">
    <img id="viewer-avatar" src="<?= $avatarAbs ?>">
    <span id="viewer-name"><?= htmlspecialchars($profile['name']) ?></span>
    <span id="viewer-time"></span>
    <button id="mute-toggle" class="viewer-icon-btn" title="بی‌صدا/باصدا"><i class="fa-solid fa-volume-xmark"></i></button>
    <?php if ($isAdmin): ?><button id="delete-story-btn" class="viewer-icon-btn" title="حذف استوری"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
    <button id="close-viewer" class="viewer-icon-btn"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="story-content" id="story-content"></div>
  <p class="story-caption" id="story-caption"></p>
  <div class="story-nav">
    <div class="nav-zone left" id="nav-prev"></div>
    <div class="nav-zone right" id="nav-next"></div>
  </div>
  <div class="story-footer">
    <button id="like-btn"><i class="fa-regular fa-heart"></i> <span id="like-count">0</span></button>
    <span id="view-count"><i class="fa-solid fa-eye"></i> <span id="view-num">0</span></span>
    <button id="story-share-btn"><i class="fa-solid fa-share-nodes"></i></button>
  </div>
  <div class="heart-burst" id="heart-burst"></div>
</div>

<?php if ($isAdmin): ?>
<div class="modal" id="add-story-modal">
  <div class="modal-box">
    <h3>افزودن استوری</h3>
    <input type="file" id="story-file-input" accept="image/*,video/*">
    <input type="text" id="story-caption-input" placeholder="کپشن (اختیاری)">
    <label class="pin-check"><input type="checkbox" id="story-pin-input"> پین دائمی (منقضی نشود)</label>
    <div class="modal-actions">
      <button id="story-upload-cancel">انصراف</button>
      <button id="story-upload-submit">آپلود</button>
    </div>
    <div class="upload-progress" id="upload-progress"><div class="upload-progress-fill" id="upload-progress-fill"></div></div>
  </div>
</div>
<?php endif; ?>

<div class="modal" id="share-modal">
  <div class="modal-box">
    <h3>اشتراک‌گذاری پروفایل</h3>
    <canvas id="qr-canvas"></canvas>
    <button id="copy-link-btn"><i class="fa-solid fa-link"></i> کپی لینک</button>
    <button id="native-share-btn"><i class="fa-solid fa-share-nodes"></i> اشتراک‌گذاری</button>
    <button id="share-modal-close">بستن</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  window.STORIES = <?= json_encode($stories, JSON_UNESCAPED_UNICODE) ?>;
  window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  window.CSRF = "<?= csrf_token() ?>";
  window.STORY_MAX_VIDEO_DURATION = <?= STORY_MAX_VIDEO_DURATION ?>;
  window.STORY_DEFAULT_DURATION = <?= STORY_DEFAULT_DURATION ?>;
</script>
<script src="assets/js/app.js"></script>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(()=>{}));
}
</script>
</body>
</html>
