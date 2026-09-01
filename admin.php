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

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

        if (isset($_POST['save_profile'])) {
            $profile = json_decode(file_get_contents(PROFILE_FILE), true);
            $profile['name'] = $_POST['name'];
            $profile['username'] = $_POST['username'];
            $profile['bio'] = $_POST['bio'];
            $profile['song']['title'] = $_POST['song_title'];
            $profile['song']['artist'] = $_POST['song_artist'];

            $skills = [];
            foreach (explode("\n", trim($_POST['skills'] ?? '')) as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) === 2 && $parts[0] !== '') $skills[] = ['name' => $parts[0], 'level' => (int)$parts[1]];
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

            file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $msg = 'پروفایل بروزرسانی شد ✅';
        }
    }
}

$p = json_decode(file_get_contents(PROFILE_FILE), true);
$p['skills'] = $p['skills'] ?? [];
$p['projects'] = $p['projects'] ?? [];
$meta = read_json_locked(STORIES_META_FILE);

$skillsText = implode("\n", array_map(fn($s) => $s['name'] . ' | ' . $s['level'], $p['skills']));
$projectsText = implode("\n", array_map(fn($pr) => ($pr['title'] ?? '') . ' | ' . ($pr['url'] ?? '') . ' | ' . ($pr['desc'] ?? '') . ' | ' . ($pr['image'] ?? ''), $p['projects']));

$storyFiles = [];
foreach (glob(STORIES_DIR . '/*') as $path) {
    $filename = basename($path);
    $storyFiles[] = ['id' => $filename, 'url' => 'stories/' . $filename, 'meta' => $meta[$filename] ?? []];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><title>پنل مدیریت</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Tahoma;background:#111;color:#eee;padding:20px;max-width:560px;margin:0 auto}
input,textarea{width:100%;padding:8px;margin:6px 0;border-radius:8px;border:1px solid #444;background:#222;color:#fff;box-sizing:border-box}
button{padding:10px 20px;background:#6c5ce7;border:none;border-radius:8px;color:#fff;cursor:pointer;margin-top:8px}
.story-card{display:flex;gap:10px;align-items:flex-start;border:1px solid #333;border-radius:10px;padding:10px;margin-bottom:10px}
.story-card img,.story-card video{width:70px;height:70px;object-fit:cover;border-radius:8px;flex-shrink:0}
.story-card form{flex:1}
.danger{background:#c0392b}
label.pin{display:flex;align-items:center;gap:6px;font-size:13px;color:#aaa}
small{color:#888}
</style>
</head>
<body>
<?php if (!$isAdmin): ?>
<h2>ورود مدیر</h2>
<p style="color:#f66"><?= htmlspecialchars($error) ?></p>
<form method="post">
  <input name="username" placeholder="نام کاربری">
  <input name="password" type="password" placeholder="رمز عبور">
  <button name="login" value="1">ورود</button>
</form>
<?php else: ?>
<h2>پنل مدیریت — <a href="?logout=1" style="color:#f66">خروج</a></h2>
<p style="color:#6f6"><?= htmlspecialchars($msg) ?></p>

<h3>افزودن استوری جدید</h3>
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="file" name="story" accept="image/*,video/*">
  <input type="text" name="caption" placeholder="کپشن (اختیاری)">
  <label class="pin"><input type="checkbox" name="pinned" value="1"> پین دائمی (منقضی نشود)</label>
  <button type="submit">آپلود</button>
</form>

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

<h3>ویرایش پروفایل</h3>
<form method="post">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input name="name" value="<?= htmlspecialchars($p['name']) ?>" placeholder="نام">
  <input name="username" value="<?= htmlspecialchars($p['username']) ?>" placeholder="یوزرنیم">
  <textarea name="bio" rows="4"><?= htmlspecialchars($p['bio']) ?></textarea>
  <input name="song_title" value="<?= htmlspecialchars($p['song']['title']) ?>" placeholder="نام آهنگ">
  <input name="song_artist" value="<?= htmlspecialchars($p['song']['artist']) ?>" placeholder="خواننده">

  <small>مهارت‌ها؛ هر خط: نام | درصد</small>
  <textarea name="skills" rows="4"><?= htmlspecialchars($skillsText) ?></textarea>

  <small>پروژه‌ها؛ هر خط: عنوان | لینک | توضیح | آدرس‌عکس</small>
  <textarea name="projects" rows="4"><?= htmlspecialchars($projectsText) ?></textarea>

  <button name="save_profile" value="1">ذخیره</button>
</form>
<a href="index.php" style="color:#6cf">بازگشت به صفحه اصلی</a>
<?php endif; ?>
</body>
</html>
