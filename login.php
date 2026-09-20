<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
if (!app_is_installed()) { header('Location: install.php'); exit; }

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (site_check()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (site_attempt($email, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}

$assetPrefix = '';
$screenLabel = 'เข้าสู่ระบบ';
$pageTitle = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap-narrow" style="display:flex;justify-content:center;padding-top:80px">
  <div class="card elev-md" style="width:100%;max-width:400px;padding:28px">
    <div class="seg" style="margin-bottom:22px;width:100%">
      <label class="seg-opt" style="flex:1;justify-content:center"><input type="radio" checked>เข้าสู่ระบบ</label>
      <label class="seg-opt" style="flex:1;justify-content:center" onclick="location.href='signup.php'"><input type="radio">สมัครสมาชิก</label>
    </div>
    <?php if ($error): ?><div class="errors"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post">
      <div class="field" style="margin-bottom:16px">
        <label for="email">อีเมล</label>
        <input class="input" id="email" name="email" type="email" placeholder="you@example.com" required value="<?php echo h($_POST['email'] ?? ''); ?>">
      </div>
      <div class="field" style="margin-bottom:20px">
        <label for="password">รหัสผ่าน</label>
        <input class="input" id="password" name="password" type="password" placeholder="••••••••" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">เข้าสู่ระบบ</button>
    </form>
    <p class="text-muted" style="font-size:12px;text-align:center;margin-top:14px;margin-bottom:0">ยังไม่มีบัญชี? <a href="signup.php">สมัครสมาชิกฟรี</a></p>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
