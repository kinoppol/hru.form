<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

session_name('hruform_install_sid');
session_start();

$step = $_GET['step'] ?? ($_SESSION['install_step'] ?? 'requirements');
$errors = [];
$notices = [];

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function checkRequirements(): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = ['label' => 'PHP >= 8.0 (พบ ' . PHP_VERSION . ')', 'ok' => $phpOk, 'critical' => true];

    foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'] as $ext) {
        $checks[] = ['label' => "PHP extension: {$ext}", 'ok' => extension_loaded($ext), 'critical' => true];
    }

    $configDir = APP_ROOT . '/config';
    $checks[] = ['label' => 'เขียนไฟล์ได้ที่โฟลเดอร์ /config', 'ok' => is_dir($configDir) && is_writable($configDir), 'critical' => true];

    $migrationsDir = APP_ROOT . '/migrations';
    $checks[] = ['label' => 'อ่านไฟล์ได้ที่โฟลเดอร์ /migrations', 'ok' => is_dir($migrationsDir) && is_readable($migrationsDir), 'critical' => true];

    return $checks;
}

// Allow re-running the installer even if already installed, but require explicit confirmation.
$alreadyInstalled = app_is_installed();
$forceReinstall = isset($_GET['reinstall']) || isset($_SESSION['install_allow_reinstall']);
if ($alreadyInstalled && $forceReinstall) {
    $_SESSION['install_allow_reinstall'] = true;
}

if ($alreadyInstalled && !$forceReinstall) {
    $step = 'already_installed';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postStep = $_POST['step'] ?? '';

    if ($postStep === 'requirements') {
        $checks = checkRequirements();
        $criticalFail = false;
        foreach ($checks as $c) {
            if ($c['critical'] && !$c['ok']) {
                $criticalFail = true;
            }
        }
        if ($criticalFail) {
            $errors[] = 'กรุณาแก้ไขรายการที่ยังไม่ผ่านก่อนดำเนินการต่อ';
            $step = 'requirements';
        } else {
            $step = 'database';
            $_SESSION['install_step'] = 'database';
        }
    } elseif ($postStep === 'database') {
        $db = [
            'host' => trim($_POST['db_host'] ?? ''),
            'port' => trim($_POST['db_port'] ?? '3306'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
            'charset' => 'utf8mb4',
        ];

        if ($db['host'] === '' || $db['name'] === '' || $db['user'] === '') {
            $errors[] = 'กรุณากรอกข้อมูลฐานข้อมูลให้ครบถ้วน';
        } else {
            try {
                $pdo = db_connect($db, false);
                $dbNameQuoted = str_replace('`', '', $db['name']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameQuoted}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                db_connect($db, true); // verify we can connect to it directly too

                $_SESSION['install_db'] = $db;
                $_SESSION['install_step'] = 'admin';
                $step = 'admin';
            } catch (Throwable $e) {
                $errors[] = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
                $step = 'database';
            }
        }
    } elseif ($postStep === 'admin') {
        $username = trim($_POST['admin_username'] ?? '');
        $password = (string) ($_POST['admin_password'] ?? '');
        $passwordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
        $appName = trim($_POST['app_name'] ?? 'HRU Form');
        $appUrl = trim($_POST['app_url'] ?? '');

        if (!isset($_SESSION['install_db'])) {
            $errors[] = 'กรุณาตั้งค่าฐานข้อมูลก่อน';
            $step = 'database';
        } elseif (strlen($username) < 3) {
            $errors[] = 'ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
            $step = 'admin';
        } elseif (strlen($password) < 8) {
            $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
            $step = 'admin';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
            $step = 'admin';
        } else {
            try {
                $dbCfg = $_SESSION['install_db'];
                $pdo = db_connect($dbCfg, true);

                require_once __DIR__ . '/includes/migrator.php';
                $migrator = new Migrator($pdo, APP_ROOT . '/migrations');
                $migrator->migrate();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('
                    INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)
                ');
                $stmt->execute([$username, $hash]);

                $configContent = "<?php\nreturn " . var_export([
                    'db' => $dbCfg,
                    'app' => [
                        'name' => $appName !== '' ? $appName : 'HRU Form',
                        'url' => $appUrl !== '' ? $appUrl : '',
                        'timezone' => 'Asia/Bangkok',
                    ],
                ], true) . ";\n";

                if (file_put_contents(CONFIG_FILE, $configContent) === false) {
                    throw new RuntimeException('ไม่สามารถเขียนไฟล์ config/config.php ได้ กรุณาตรวจสอบสิทธิ์การเขียนไฟล์');
                }
                file_put_contents(INSTALL_LOCK, date('c'));

                unset($_SESSION['install_db'], $_SESSION['install_step'], $_SESSION['install_allow_reinstall']);
                $step = 'done';
            } catch (Throwable $e) {
                $errors[] = 'ติดตั้งไม่สำเร็จ: ' . $e->getMessage();
                $step = 'admin';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ - HRU Form</title>
<style>
  body{font-family:-apple-system,Segoe UI,Tahoma,sans-serif;background:#f4f5f7;margin:0;padding:40px 16px;color:#1f2430}
  .wrap{max-width:640px;margin:0 auto}
  .card{background:#fff;border-radius:10px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
  h1{font-size:22px;margin:0 0 4px}
  .steps{display:flex;gap:8px;margin-bottom:24px;font-size:13px;color:#888}
  .steps span.active{color:#2f6fed;font-weight:600}
  .field{margin-bottom:16px}
  label{display:block;font-size:13px;margin-bottom:6px;font-weight:600}
  input[type=text],input[type=password],input[type=number],input[type=url]{width:100%;padding:9px 12px;border:1px solid #d5d8dd;border-radius:6px;font-size:14px;box-sizing:border-box}
  .btn{display:inline-block;background:#2f6fed;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-size:14px;cursor:pointer}
  .btn:hover{background:#255bd0}
  ul.checklist{list-style:none;padding:0;margin:0 0 20px}
  ul.checklist li{padding:8px 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between}
  .ok{color:#16a34a;font-weight:600}
  .fail{color:#dc2626;font-weight:600}
  .errors{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:14px}
  .notice{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:12px 16px;border-radius:6px;margin-bottom:20px;font-size:14px}
  .muted{color:#888;font-size:13px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>ตัวติดตั้งระบบ HRU Form</h1>
    <p class="muted">รองรับการติดตั้งซ้ำ &mdash; หากต้องการติดตั้งใหม่ทับของเดิม ระบบจะให้ยืนยันก่อนทุกครั้ง</p>

    <?php if (!empty($errors)): ?>
      <div class="errors"><?php foreach ($errors as $e) echo '<p style="margin:4px 0">' . h($e) . '</p>'; ?></div>
    <?php endif; ?>

    <?php if ($step === 'already_installed'): ?>
      <div class="notice">ระบบได้ถูกติดตั้งไปแล้ว หากต้องการติดตั้งซ้ำ (ทับการตั้งค่าเดิม) กรุณากดปุ่มด้านล่าง</div>
      <a class="btn" href="install.php?step=requirements&reinstall=1">ติดตั้งซ้ำ</a>
      <a class="btn" style="background:#6b7280;margin-left:8px" href="admin/login.php">ไปหน้าเข้าสู่ระบบผู้ดูแล</a>

    <?php elseif ($step === 'requirements'): ?>
      <div class="steps"><span class="active">1. ตรวจสอบระบบ</span><span>2. ฐานข้อมูล</span><span>3. ผู้ดูแลระบบ</span><span>4. เสร็จสิ้น</span></div>
      <?php $checks = checkRequirements(); ?>
      <ul class="checklist">
        <?php foreach ($checks as $c): ?>
          <li><span><?php echo h($c['label']); ?></span><span class="<?php echo $c['ok'] ? 'ok' : 'fail'; ?>"><?php echo $c['ok'] ? 'ผ่าน' : 'ไม่ผ่าน'; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <form method="post">
        <input type="hidden" name="step" value="requirements">
        <button class="btn" type="submit">ดำเนินการต่อ</button>
      </form>

    <?php elseif ($step === 'database'): ?>
      <div class="steps"><span>1. ตรวจสอบระบบ</span><span class="active">2. ฐานข้อมูล</span><span>3. ผู้ดูแลระบบ</span><span>4. เสร็จสิ้น</span></div>
      <form method="post">
        <input type="hidden" name="step" value="database">
        <div class="field"><label>Database Host</label><input type="text" name="db_host" value="127.0.0.1" required></div>
        <div class="field"><label>Database Port</label><input type="number" name="db_port" value="3306" required></div>
        <div class="field"><label>Database Name</label><input type="text" name="db_name" value="hru_form" required></div>
        <div class="field"><label>Database User</label><input type="text" name="db_user" value="root" required></div>
        <div class="field"><label>Database Password</label><input type="password" name="db_pass" value=""></div>
        <button class="btn" type="submit">ทดสอบและดำเนินการต่อ</button>
      </form>

    <?php elseif ($step === 'admin'): ?>
      <div class="steps"><span>1. ตรวจสอบระบบ</span><span>2. ฐานข้อมูล</span><span class="active">3. ผู้ดูแลระบบ</span><span>4. เสร็จสิ้น</span></div>
      <form method="post">
        <input type="hidden" name="step" value="admin">
        <div class="field"><label>ชื่อระบบ</label><input type="text" name="app_name" value="HRU Form"></div>
        <div class="field"><label>URL ของระบบ</label><input type="url" name="app_url" placeholder="http://localhost/hru.form"></div>
        <div class="field"><label>ชื่อผู้ใช้ผู้ดูแลระบบ</label><input type="text" name="admin_username" required></div>
        <div class="field"><label>รหัสผ่าน</label><input type="password" name="admin_password" required></div>
        <div class="field"><label>ยืนยันรหัสผ่าน</label><input type="password" name="admin_password_confirm" required></div>
        <button class="btn" type="submit">ติดตั้งระบบ</button>
      </form>

    <?php elseif ($step === 'done'): ?>
      <div class="notice">ติดตั้งระบบสำเร็จ! คุณสามารถเข้าสู่ระบบผู้ดูแลได้ทันที</div>
      <a class="btn" href="admin/login.php">ไปหน้าเข้าสู่ระบบผู้ดูแล</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
