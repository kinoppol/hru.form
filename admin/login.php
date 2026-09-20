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
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-body">
<div class="card">
  <span class="dot"></span>
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
