<?php
require_once __DIR__ . '/config.php';

$profile = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);

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
        continue;
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
$guestbook = read_json_locked(GUESTBOOK_FILE);
usort($guestbook, fn($a,$b) => $b['time'] - $a['time']);

$avatarAbs = htmlspecialchars($profile['avatar']);
$postsCount = count($profile['gallery']);

$tabs = ['about' => 'درباره'];
if (!empty($profile['skills']))   $tabs['skills']   = 'مهارت‌ها';
if (!empty($profile['projects'])) $tabs['projects'] = 'پروژه‌ها';
if (!empty($profile['gallery']))  $tabs['gallery']  = 'گالری';
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
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="page-loader" id="page-loader"><div class="loader-ring"></div></div>
<canvas id="bg-canvas"></canvas>
<div class="spotlight" id="spotlight"></div>

<div class="app">
  <header class="profile-header">
    <div class="avatar-ring <?= !empty($stories) ? 'has-story' : '' ?>" id="avatar-ring">
      <img src="<?= $avatarAbs ?>" alt="avatar" class="avatar-img" id="avatar-img">
      <?php if ($profile['online']): ?><span class="online-dot" title="آنلاین"></span><?php endif; ?>
      <?php if ($isAdmin): ?>
        <button class="add-story-btn" id="add-story-btn" title="افزودن استوری"><i class="fa-solid fa-plus"></i></button>
      <?php endif; ?>
    </div>

    <h1 class="profile-name"><span class="gradient-text"><?= htmlspecialchars($profile['name']) ?></span> <i class="fa-solid fa-certificate verified" title="تایید شده"></i></h1>
    <button class="username-btn" id="username-btn" title="کپی یوزرنیم">@<?= htmlspecialchars(ltrim($profile['username'],'@')) ?> <i class="fa-regular fa-copy"></i></button>
    <p class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>

    <div class="stats-row">
      <div class="stat-item"><b><?= $postsCount ?></b><span>پست</span></div>
      <div class="stat-divider"></div>
      <div class="stat-item"><b><?= (int)$profile['stats']['followers'] ?></b><span>دنبال‌کننده</span></div>
      <div class="stat-divider"></div>
      <div class="stat-item"><b><?= (int)$profile['stats']['following'] ?></b><span>دنبال‌شونده</span></div>
    </div>

    <div class="social-row">
      <?php foreach ($profile['socials'] as $s): ?>
        <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener" class="social-btn" title="<?= htmlspecialchars($s['name']) ?>">
          <i class="fa-brands <?= htmlspecialchars($s['icon']) ?>"></i>
        </a>
      <?php endforeach; ?>
      <button class="social-btn" id="vcard-btn" title="افزودن مخاطب"><i class="fa-solid fa-address-card"></i></button>
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

  <?php if (!empty($profile['playlist'])): ?>
  <section class="music-card reveal">
    <div class="vinyl" id="vinyl">
      <img id="vinyl-cover" src="<?= htmlspecialchars($profile['playlist'][0]['cover']) ?>" alt="cover">
    </div>
    <div class="music-info">
      <p class="song-title" id="song-title"><?= htmlspecialchars($profile['playlist'][0]['title']) ?></p>
      <p class="song-artist" id="song-artist"><?= htmlspecialchars($profile['playlist'][0]['artist']) ?></p>
      <div class="music-progress" id="music-progress"><div class="music-progress-fill" id="progress-fill"></div></div>
    </div>
    <div class="music-controls">
      <?php if (count($profile['playlist']) > 1): ?><button class="mini-btn" id="prev-btn"><i class="fa-solid fa-backward-step"></i></button><?php endif; ?>
      <button class="play-btn" id="play-btn"><i class="fa-solid fa-play"></i></button>
      <?php if (count($profile['playlist']) > 1): ?><button class="mini-btn" id="next-btn"><i class="fa-solid fa-forward-step"></i></button><?php endif; ?>
    </div>
    <audio id="audio-player" src="<?= htmlspecialchars($profile['playlist'][0]['file']) ?>"></audio>
  </section>
  <?php endif; ?>

  <nav class="tabs-nav reveal">
    <?php foreach ($tabs as $key => $label): ?>
      <button class="tab-btn <?= $key === 'about' ? 'active' : '' ?>" data-tab="<?= $key ?>"><?= $label ?></button>
    <?php endforeach; ?>
  </nav>

  <section class="tab-panel active" id="tab-about">
    <div class="about-card reveal">
      <p class="about-text"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
      <?php if (!empty($profile['contact']['phone']) || !empty($profile['contact']['email'])): ?>
      <div class="contact-row">
        <?php if (!empty($profile['contact']['phone'])): ?>
          <a class="contact-chip" href="tel:<?= htmlspecialchars($profile['contact']['phone']) ?>"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($profile['contact']['phone']) ?></a>
        <?php endif; ?>
        <?php if (!empty($profile['contact']['email'])): ?>
          <a class="contact-chip" href="mailto:<?= htmlspecialchars($profile['contact']['email']) ?>"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($profile['contact']['email']) ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="tags">
        <span class="tag">🎮 گیمر</span>
        <span class="tag">💻 برنامه‌نویس</span>
        <span class="tag">🎵 موزیک‌باز</span>
        <span class="tag">📷 عکاس</span>
      </div>
    </div>
  </section>

  <?php if (!empty($profile['skills'])): ?>
  <section class="tab-panel" id="tab-skills">
    <div class="skills-card reveal">
      <?php foreach ($profile['skills'] as $sk): ?>
        <div class="skill-row">
          <div class="skill-label">
            <span><?php if (!empty($sk['icon'])): ?><i class="<?= htmlspecialchars($sk['icon']) ?>"></i><?php endif; ?> <?= htmlspecialchars($sk['name']) ?></span>
            <span><?= (int)$sk['level'] ?>٪</span>
          </div>
          <div class="skill-bar"><div class="skill-fill" data-level="<?= (int)$sk['level'] ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($profile['projects'])): ?>
  <section class="tab-panel" id="tab-projects">
    <div class="projects-card reveal">
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
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($profile['gallery'])): ?>
  <section class="tab-panel" id="tab-gallery">
    <div class="gallery-card reveal">
      <div class="gallery-grid" id="gallery-grid">
        <?php foreach ($profile['gallery'] as $gi => $g): ?>
          <div class="gallery-item" data-index="<?= $gi ?>">
            <img src="<?= htmlspecialchars($g['image']) ?>" alt="" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="guestbook-card reveal">
    <h3 class="section-title"><i class="fa-solid fa-book-open"></i> دفترچه یادگاری</h3>
    <form id="guestbook-form" class="guestbook-form">
      <input type="text" name="website" id="gb-honeypot" autocomplete="off">
      <input type="text" id="gb-name" placeholder="اسمت (اختیاری)" maxlength="40">
      <textarea id="gb-message" placeholder="یه چیزی بنویس..." maxlength="<?= GUESTBOOK_MAX_LEN ?>" rows="2" required></textarea>
      <button type="submit"><i class="fa-solid fa-paper-plane"></i> ارسال</button>
    </form>
    <div class="guestbook-list" id="guestbook-list">
      <?php if (empty($guestbook)): ?>
        <p class="empty-msg">هنوز پیامی ثبت نشده، اولین نفر باش!</p>
      <?php endif; ?>
      <?php foreach ($guestbook as $g): ?>
        <div class="guestbook-item" data-id="<?= htmlspecialchars($g['id']) ?>">
          <div class="gb-avatar"><?= mb_substr($g['name'], 0, 1) ?></div>
          <div class="gb-body">
            <p class="gb-name"><?= $g['name'] ?> <span class="gb-time" data-time="<?= $g['time'] ?>"></span></p>
            <p class="gb-message"><?= nl2br($g['message']) ?></p>
          </div>
          <?php if ($isAdmin): ?><button class="gb-delete" title="حذف"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <footer class="app-footer">
    <button class="theme-toggle" id="theme-toggle"><i class="fa-solid fa-moon"></i></button>
    <p class="visits-count"><i class="fa-solid fa-eye"></i> <span id="visits-num"><?= (int)($visits['total'] ?? 0) ?></span> بازدید پروفایل</p>
    <p>ساخته‌شده با <i class="fa-solid fa-heart" style="color:#e74c3c"></i> روی XAMPP</p>
    <?php if ($isAdmin): ?><a href="admin.php" class="admin-link">پنل مدیریت</a><?php endif; ?>
  </footer>
</div>

<button class="scroll-top-btn" id="scroll-top-btn"><i class="fa-solid fa-arrow-up"></i></button>

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

<div class="modal" id="lightbox-modal">
  <div class="lightbox-box">
    <button id="lightbox-close" class="viewer-icon-btn"><i class="fa-solid fa-xmark"></i></button>
    <button id="lightbox-prev" class="lightbox-nav-btn"><i class="fa-solid fa-chevron-right"></i></button>
    <img id="lightbox-img" src="" alt="">
    <button id="lightbox-next" class="lightbox-nav-btn"><i class="fa-solid fa-chevron-left"></i></button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  window.STORIES = <?= json_encode($stories, JSON_UNESCAPED_UNICODE) ?>;
  window.GALLERY = <?= json_encode($profile['gallery'], JSON_UNESCAPED_UNICODE) ?>;
  window.PLAYLIST = <?= json_encode($profile['playlist'], JSON_UNESCAPED_UNICODE) ?>;
  window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
  window.CSRF = "<?= csrf_token() ?>";
  window.PROFILE_NAME = "<?= htmlspecialchars($profile['name']) ?>";
  window.PROFILE_PHONE = "<?= htmlspecialchars($profile['contact']['phone'] ?? '') ?>";
  window.PROFILE_USERNAME = "<?= htmlspecialchars(ltrim($profile['username'],'@')) ?>";
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
