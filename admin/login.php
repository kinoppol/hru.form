<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (!app_is_installed()) {
    header('Location: ../install.php');
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

if (auth_check()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (auth_attempt($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบผู้ดูแล - HRU Form</title>
<style>
  body{font-family:-apple-system,Segoe UI,Tahoma,sans-serif;background:#f4f5f7;margin:0;padding:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
  .card{background:#fff;border-radius:10px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,.08);width:100%;max-width:360px}
  h1{font-size:20px;margin:0 0 20px}
  .field{margin-bottom:16px}
  label{display:block;font-size:13px;margin-bottom:6px;font-weight:600}
  input{width:100%;padding:9px 12px;border:1px solid #d5d8dd;border-radius:6px;font-size:14px;box-sizing:border-box}
  .btn{width:100%;background:#2f6fed;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-size:14px;cursor:pointer}
  .btn:hover{background:#255bd0}
  .error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:13px}
</style>
</head>
<body>
<div class="card">
  <h1>เข้าสู่ระบบผู้ดูแล</h1>
  <?php if ($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
  <form method="post">
    <div class="field"><label>ชื่อผู้ใช้</label><input type="text" name="username" required autofocus></div>
    <div class="field"><label>รหัสผ่าน</label><input type="password" name="password" required></div>
    <button class="btn" type="submit">เข้าสู่ระบบ</button>
  </form>
</div>
</body>
</html>
