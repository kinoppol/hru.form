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
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($fullName === '' || $email === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } else {
        [$ok, $err] = site_register($fullName, $email, $password);
        if ($ok) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $err;
    }
}

$assetPrefix = '';
$screenLabel = 'สมัครสมาชิก';
$pageTitle = 'สมัครสมาชิก';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap-narrow" style="display:flex;justify-content:center;padding-top:80px">
  <div class="card elev-md" style="width:100%;max-width:400px;padding:28px">
    <div class="seg" style="margin-bottom:22px;width:100%">
      <label class="seg-opt" style="flex:1;justify-content:center" onclick="location.href='login.php'"><input type="radio">เข้าสู่ระบบ</label>
      <label class="seg-opt" style="flex:1;justify-content:center"><input type="radio" checked>สมัครสมาชิก</label>
    </div>
    <?php if ($error): ?><div class="errors"><?php echo h($error); ?></div><?php endif; ?>
    <form method="post">
      <div class="field" style="margin-bottom:16px">
        <label for="full_name">ชื่อ-นามสกุล</label>
        <input class="input" id="full_name" name="full_name" type="text" placeholder="สมชาย ใจดี" required value="<?php echo h($fullName); ?>">
      </div>
      <div class="field" style="margin-bottom:16px">
        <label for="email">อีเมล</label>
        <input class="input" id="email" name="email" type="email" placeholder="you@example.com" required value="<?php echo h($email); ?>">
      </div>
      <div class="field" style="margin-bottom:20px">
        <label for="password">รหัสผ่าน</label>
        <input class="input" id="password" name="password" type="password" placeholder="อย่างน้อย 8 ตัวอักษร" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">สมัครสมาชิก</button>
    </form>
    <p class="text-muted" style="font-size:12px;text-align:center;margin-top:14px;margin-bottom:0">สมัครสมาชิกได้ฟรี ไม่ต้องใช้บัตรเครดิต</p>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
