<?php
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
    } else {
        $error = 'نام کاربری یا رمز اشتباه است';
    }
}
if (isset($_GET['logout'])) { unset($_SESSION['is_admin']); header('Location: admin.php'); exit; }

$isAdmin = $_SESSION['is_admin'] ?? false;
$msg = '';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['story'])) {
    $file = $_FILES['story'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','mp4','webm'];
    if (in_array($ext, $allowed) && $file['error'] === UPLOAD_ERR_OK) {
        $newName = time() . '_' . preg_replace('/[^a-zA-Z0-9]/','', pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], STORIES_DIR . '/' . $newName);
        $msg = 'استوری با موفقیت اضافه شد ✅';
    } else {
        $msg = 'فرمت فایل مجاز نیست ❌';
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $profile = json_decode(file_get_contents(PROFILE_FILE), true);
    $profile['name'] = $_POST['name'];
    $profile['username'] = $_POST['username'];
    $profile['bio'] = $_POST['bio'];
    $profile['song']['title'] = $_POST['song_title'];
    $profile['song']['artist'] = $_POST['song_artist'];
    file_put_contents(PROFILE_FILE, json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $msg = 'پروفایل بروزرسانی شد ✅';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><title>پنل مدیریت</title>
<style>
body{font-family:Tahoma;background:#111;color:#eee;padding:20px;max-width:480px;margin:0 auto}
input,textarea{width:100%;padding:8px;margin:6px 0;border-radius:8px;border:1px solid #444;background:#222;color:#fff}
button{padding:10px 20px;background:#6c5ce7;border:none;border-radius:8px;color:#fff;cursor:pointer;margin-top:8px}
</style>
</head>
<body>
<?php if (!$isAdmin): ?>
<h2>ورود مدیر</h2>
<p style="color:#f66"><?= $error ?></p>
<form method="post">
  <input name="username" placeholder="نام کاربری">
  <input name="password" type="password" placeholder="رمز عبور">
  <button name="login" value="1">ورود</button>
</form>
<?php else: ?>
<h2>پنل مدیریت — <a href="?logout=1" style="color:#f66">خروج</a></h2>
<p style="color:#6f6"><?= $msg ?></p>

<h3>افزودن استوری جدید</h3>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="story" accept="image/*,video/*">
  <button type="submit">آپلود</button>
</form>

<h3>ویرایش پروفایل</h3>
<?php $p = json_decode(file_get_contents(PROFILE_FILE), true); ?>
<form method="post">
  <input name="name" value="<?= htmlspecialchars($p['name']) ?>" placeholder="نام">
  <input name="username" value="<?= htmlspecialchars($p['username']) ?>" placeholder="یوزرنیم">
  <textarea name="bio" rows="4"><?= htmlspecialchars($p['bio']) ?></textarea>
  <input name="song_title" value="<?= htmlspecialchars($p['song']['title']) ?>" placeholder="نام آهنگ">
  <input name="song_artist" value="<?= htmlspecialchars($p['song']['artist']) ?>" placeholder="خواننده">
  <button name="save_profile" value="1">ذخیره</button>
</form>
<a href="index.php" style="color:#6cf">بازگشت به صفحه اصلی</a>
<?php endif; ?>
</body>
</html>
