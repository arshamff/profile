<?php
require_once __DIR__ . '/config.php';

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'] ?? '', ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
    } else {
        $error = 'نام کاربری یا رمز اشتباه است';
    }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$isAdmin = $_SESSION['is_admin'] ?? false;

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login'])) {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $msg = 'توکن امنیتی نامعتبر است، دوباره تلاش کن';
    } else {

        if (isset($_FILES['story']) && $_FILES['story']['size'] > 0) {
            $file = $_FILES['story'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeMap = $GLOBALS['ALLOWED_STORY_MIME'];
            if (!isset($mimeMap[$ext]) || $file['error'] !== UPLOAD_ERR_OK) {
                $msg = 'فرمت فایل مجاز نیست ❌';
            } elseif ($file['size'] > STORY_MAX_UPLOAD_SIZE) {
                $msg = 'حجم فایل بیش از حد مجاز است ❌';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $realMime = $finfo->file($file['tmp_name']);
                if (!in_array($realMime, $mimeMap[$ext])) {
                    $msg = 'نوع فایل با پسوند مطابقت ندارد ❌';
                } else {
                    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], STORIES_DIR . '/' . $newName);
                    $meta = read_json_locked(STORIES_META_FILE);
                    $meta[$newName] = ['caption' => trim($_POST['caption'] ?? ''), 'pinned' => !empty($_POST['pinned']), 'duration' => null];
                    write_json_locked(STORIES_META_FILE, $meta);
                    $msg = 'استوری با موفقیت اضافه شد ✅';
                }
            }
        }

        if (isset($_POST['delete_story'])) {
            $id = basename($_POST['delete_story']);
            $path = STORIES_DIR . '/' . $id;
            if (file_exists($path)) {
                unlink($path);
                $meta = read_json_locked(STORIES_META_FILE); unset($meta[$id]); write_json_locked(STORIES_META_FILE, $meta);
                $stats = read_json_locked(STATS_FILE); unset($stats[$id]); write_json_locked(STATS_FILE, $stats);
                $msg = 'استوری حذف شد ✅';
            }
        }

        if (isset($_POST['update_story'])) {
            $id = basename($_POST['update_story']);
            if (file_exists(STORIES_DIR . '/' . $id)) {
                $meta = read_json_locked(STORIES_META_FILE);
                $meta[$id] = ['caption' => trim($_POST['caption'] ?? ''), 'pinned' => !empty($_POST['pinned']), 'duration' => $meta[$id]['duration'] ?? null];
                write_json_locked(STORIES_META_FILE, $meta);
                $msg = 'استوری بروزرسانی شد ✅';
            }
        }

        if (isset($_POST['gallery_image']) && $_FILES['gallery_image']['size'] > 0) {
            $file = $_FILES['gallery_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeMap = $GLOBALS['ALLOWED_IMAGE_MIME'];
            if (isset($mimeMap[$ext]) && $file['error'] === UPLOAD_ERR_OK && $file['size'] <= STORY_MAX_UPLOAD_SIZE) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if (in_array($finfo->file($file['tmp_name']), $mimeMap[$ext])) {
                    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], GALLERY_DIR . '/' . $newName);
                    $profile = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);
                    $profile['gallery'][] = ['image' => 'assets/img/gallery/' . $newName];
                    file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $msg = 'عکس به گالری اضافه شد ✅';
                } else { $msg = 'نوع فایل نامعتبر ❌'; }
            } else { $msg = 'فایل گالری نامعتبر است ❌'; }
        }

        if (isset($_POST['delete_gallery_image'])) {
            $img = $_POST['delete_gallery_image'];
            $profile = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);
            $profile['gallery'] = array_values(array_filter($profile['gallery'], fn($g) => $g['image'] !== $img));
            file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $path = BASE_PATH . '/' . $img;
            if (file_exists($path) && strpos(realpath($path), realpath(GALLERY_DIR)) === 0) unlink($path);
            $msg = 'عکس از گالری حذف شد ✅';
        }

        if (isset($_POST['delete_guestbook'])) {
            $id = $_POST['delete_guestbook'];
            $list = read_json_locked(GUESTBOOK_FILE);
            $list = array_values(array_filter($list, fn($g) => $g['id'] !== $id));
            write_json_locked(GUESTBOOK_FILE, $list);
            $msg = 'پیام دفترچه یادگاری حذف شد ✅';
        }

        if (isset($_POST['save_profile'])) {
            $profile = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);
            $profile['name'] = $_POST['name'];
            $profile['username'] = $_POST['username'];
            $profile['bio'] = $_POST['bio'];
            $profile['stats']['followers'] = (int)($_POST['followers'] ?? 0);
            $profile['stats']['following'] = (int)($_POST['following'] ?? 0);
            $profile['contact']['phone'] = trim($_POST['phone'] ?? '');
            $profile['contact']['email'] = trim($_POST['email'] ?? '');

            $skills = [];
            foreach (explode("\n", trim($_POST['skills'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 2 && $parts[0] !== '') $skills[] = ['name' => $parts[0], 'level' => (int)$parts[1], 'icon' => $parts[2] ?? ''];
            }
            $profile['skills'] = $skills;

            $projects = [];
            foreach (explode("\n", trim($_POST['projects'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 1 && $parts[0] !== '') {
                    $projects[] = ['title' => $parts[0], 'url' => $parts[1] ?? '#', 'desc' => $parts[2] ?? '', 'image' => $parts[3] ?? ''];
                }
            }
            $profile['projects'] = $projects;

            $socials = [];
            foreach (explode("\n", trim($_POST['socials'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 3 && $parts[0] !== '') $socials[] = ['name' => $parts[0], 'icon' => $parts[1], 'url' => $parts[2]];
            }
            $profile['socials'] = $socials;

            $playlist = [];
            foreach (explode("\n", trim($_POST['playlist'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 3 && $parts[0] !== '') {
                    $playlist[] = ['title' => $parts[0], 'artist' => $parts[1], 'file' => $parts[2], 'cover' => $parts[3] ?? $profile['avatar']];
                }
            }
            $profile['playlist'] = $playlist;

            file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $msg = 'پروفایل بروزرسانی شد ✅';
        }
    }
}

$p = normalize_profile(json_decode(file_get_contents(PROFILE_FILE), true) ?: []);
$meta = read_json_locked(STORIES_META_FILE);
$guestbook = read_json_locked(GUESTBOOK_FILE);
usort($guestbook, fn($a,$b) => $b['time'] - $a['time']);

$skillsText = implode("\n", array_map(fn($s) => $s['name'] . ' | ' . $s['level'] . ' | ' . ($s['icon'] ?? ''), $p['skills']));
$projectsText = implode("\n", array_map(fn($pr) => ($pr['title'] ?? '') . ' | ' . ($pr['url'] ?? '') . ' | ' . ($pr['desc'] ?? '') . ' | ' . ($pr['image'] ?? ''), $p['projects']));
$socialsText = implode("\n", array_map(fn($s) => $s['name'] . ' | ' . $s['icon'] . ' | ' . $s['url'], $p['socials']));
$playlistText = implode("\n", array_map(fn($m) => $m['title'] . ' | ' . $m['artist'] . ' | ' . $m['file'] . ' | ' . ($m['cover'] ?? ''), $p['playlist']));

$storyFiles = [];
foreach (glob(STORIES_DIR . '/*') as $path) {
    $filename = basename($path);
    $storyFiles[] = ['id' => $filename, 'url' => 'stories/' . $filename, 'meta' => $meta[$filename] ?? []];
}

// آمار هفت روز اخیر برای نمودار
$visits = read_json_locked(VISITS_FILE);
$chartDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chartDays[] = ['label' => date('m/d', strtotime($d)), 'count' => $visits['days'][$d] ?? 0];
}
$maxCount = max(1, max(array_column($chartDays, 'count')));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><title>پنل مدیریت</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--bg:#0b0b14;--card:#171723;--border:#2a2a3a;--text:#f1f1f5;--muted:#9a9aab;--accent1:#7c5cff;--accent2:#ff5c8a}
*{box-sizing:border-box}
body{font-family:'Vazirmatn',Tahoma;background:var(--bg);color:var(--text);padding:20px;max-width:640px;margin:0 auto}
h2,h3{margin:18px 0 10px}
input,textarea{width:100%;padding:10px;margin:6px 0;border-radius:10px;border:1px solid var(--border);background:var(--card);color:#fff;box-sizing:border-box;font-family:inherit}
button{padding:10px 20px;background:linear-gradient(135deg,var(--accent1),var(--accent2));border:none;border-radius:10px;color:#fff;cursor:pointer;margin-top:8px;font-family:inherit;font-weight:600}
.section-box{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:16px}
.story-card{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:10px}
.story-card img,.story-card video{width:70px;height:70px;object-fit:cover;border-radius:8px;flex-shrink:0}
.story-card form{flex:1}
.danger{background:#c0392b}
label.pin{display:flex;align-items:center;gap:6px;font-size:13px;color:#aaa}
small{color:#888;display:block;margin-top:6px}
.chart-wrap{display:flex;align-items:flex-end;gap:8px;height:120px;padding:10px 0}
.chart-bar{flex:1;background:linear-gradient(180deg,var(--accent2),var(--accent1));border-radius:6px 6px 0 0;min-height:4px;position:relative}
.chart-bar span{position:absolute;top:-20px;left:0;right:0;text-align:center;font-size:11px;color:var(--muted)}
.chart-labels{display:flex;gap:8px}
.chart-labels span{flex:1;text-align:center;font-size:11px;color:var(--muted)}
.gallery-admin-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.gallery-admin-grid .item{position:relative}
.gallery-admin-grid img{width:100%;height:70px;object-fit:cover;border-radius:8px}
.gallery-admin-grid form{position:absolute;top:2px;left:2px}
.gallery-admin-grid button{padding:2px 6px;font-size:10px;margin:0}
.gb-admin-item{border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.gb-admin-item .msg{font-size:13px;color:#ddd}
.gb-admin-item .who{font-size:12px;color:var(--muted);margin-bottom:4px}
.top-bar{display:flex;justify-content:space-between;align-items:center}
</style>
</head>
<body>
<?php if (!$isAdmin): ?>
<h2><i class="fa-solid fa-lock"></i> ورود مدیر</h2>
<p style="color:#f66"><?= htmlspecialchars($error) ?></p>
<form method="post">
  <input name="username" placeholder="نام کاربری">
  <input name="password" type="password" placeholder="رمز عبور">
  <button name="login" value="1">ورود</button>
</form>
<?php else: ?>
<div class="top-bar">
  <h2><i class="fa-solid fa-gauge"></i> پنل مدیریت</h2>
  <a href="?logout=1" style="color:#f66">خروج</a>
</div>
<p style="color:#6f6"><?= htmlspecialchars($msg) ?></p>

<div class="section-box">
  <h3><i class="fa-solid fa-chart-column"></i> بازدید ۷ روز اخیر (مجموع: <?= (int)($visits['total'] ?? 0) ?>)</h3>
  <div class="chart-wrap">
    <?php foreach ($chartDays as $cd): ?>
      <div class="chart-bar" style="height:<?= max(4, ($cd['count'] / $maxCount) * 100) ?>%"><span><?= $cd['count'] ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="chart-labels"><?php foreach ($chartDays as $cd): ?><span><?= $cd['label'] ?></span><?php endforeach; ?></div>
</div>

<div class="section-box">
  <h3><i class="fa-solid fa-circle-play"></i> افزودن استوری جدید</h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="file" name="story" accept="image/*,video/*">
    <input type="text" name="caption" placeholder="کپشن (اختیاری)">
    <label class="pin"><input type="checkbox" name="pinned" value="1"> پین دائمی (منقضی نشود)</label>
    <button type="submit">آپلود</button>
  </form>
</div>

<div class="section-box">
  <h3>مدیریت استوری‌ها (<?= count($storyFiles) ?>)</h3>
  <?php foreach ($storyFiles as $sf): $m = $sf['meta']; $ext = strtolower(pathinfo($sf['id'], PATHINFO_EXTENSION)); ?>
    <div class="story-card">
      <?= in_array($ext, ['mp4','webm']) ? '<video src="'.htmlspecialchars($sf['url']).'" muted></video>' : '<img src="'.htmlspecialchars($sf['url']).'">' ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="update_story" value="<?= htmlspecialchars($sf['id']) ?>">
        <input type="text" name="caption" value="<?= htmlspecialchars($m['caption'] ?? '') ?>" placeholder="کپشن">
        <label class="pin"><input type="checkbox" name="pinned" value="1" <?= !empty($m['pinned']) ? 'checked' : '' ?>> پین دائمی</label>
        <button type="submit">ذخیره</button>
      </form>
      <form method="post" onsubmit="return confirm('حذف شود؟')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="delete_story" value="<?= htmlspecialchars($sf['id']) ?>">
        <button type="submit" class="danger">حذف</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<div class="section-box">
  <h3><i class="fa-solid fa-images"></i> مدیریت گالری</h3>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="file" name="gallery_image" accept="image/*">
    <button type="submit">افزودن به گالری</button>
  </form>
  <div class="gallery-admin-grid" style="margin-top:12px">
    <?php foreach ($p['gallery'] as $g): ?>
      <div class="item">
        <img src="<?= htmlspecialchars($g['image']) ?>">
        <form method="post" onsubmit="return confirm('حذف شود؟')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="delete_gallery_image" value="<?= htmlspecialchars($g['image']) ?>">
          <button type="submit" class="danger">حذف</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="section-box">
  <h3><i class="fa-solid fa-book-open"></i> دفترچه یادگاری (<?= count($guestbook) ?>)</h3>
  <?php foreach ($guestbook as $g): ?>
    <div class="gb-admin-item">
      <div>
        <p class="who"><?= $g['name'] ?> — <?= date('Y-m-d H:i', $g['time']) ?></p>
        <p class="msg"><?= nl2br($g['message']) ?></p>
      </div>
      <form method="post" onsubmit="return confirm('حذف شود؟')">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="delete_guestbook" value="<?= htmlspecialchars($g['id']) ?>">
        <button type="submit" class="danger">حذف</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (empty($guestbook)): ?><p style="color:var(--muted)">پیامی وجود ندارد</p><?php endif; ?>
</div>

<div class="section-box">
  <h3><i class="fa-solid fa-user-pen"></i> ویرایش پروفایل</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input name="name" value="<?= htmlspecialchars($p['name']) ?>" placeholder="نام">
    <input name="username" value="<?= htmlspecialchars($p['username']) ?>" placeholder="یوزرنیم">
    <textarea name="bio" rows="4"><?= htmlspecialchars($p['bio']) ?></textarea>
    <input name="followers" type="number" value="<?= (int)$p['stats']['followers'] ?>" placeholder="تعداد دنبال‌کننده">
    <input name="following" type="number" value="<?= (int)$p['stats']['following'] ?>" placeholder="تعداد دنبال‌شونده">
    <input name="phone" value="<?= htmlspecialchars($p['contact']['phone'] ?? '') ?>" placeholder="شماره تلفن (برای vCard)">
    <input name="email" value="<?= htmlspecialchars($p['contact']['email'] ?? '') ?>" placeholder="ایمیل">

    <small>مهارت‌ها؛ هر خط: نام | درصد | کلاس‌آیکون (اختیاری مثل fa-brands fa-php)</small>
    <textarea name="skills" rows="4"><?= htmlspecialchars($skillsText) ?></textarea>

    <small>پروژه‌ها؛ هر خط: عنوان | لینک | توضیح | آدرس‌عکس</small>
    <textarea name="projects" rows="4"><?= htmlspecialchars($projectsText) ?></textarea>

    <small>شبکه‌های اجتماعی؛ هر خط: نام | کلاس‌آیکون (fa-telegram) | لینک</small>
    <textarea name="socials" rows="3"><?= htmlspecialchars($socialsText) ?></textarea>

    <small>پلی‌لیست موزیک؛ هر خط: عنوان | خواننده | آدرس‌فایل | آدرس‌کاور</small>
    <textarea name="playlist" rows="4"><?= htmlspecialchars($playlistText) ?></textarea>

    <button name="save_profile" value="1">ذخیره تغییرات</button>
  </form>
</div>

<a href="index.php" style="color:#6cf">بازگشت به صفحه اصلی</a>
<?php endif; ?>
</body>
</html>
