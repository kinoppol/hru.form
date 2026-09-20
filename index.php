<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

if (!app_is_installed()) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/helpers.php';

$assetPrefix = '';
$screenLabel = 'Landing';
$pageTitle = 'สร้างฟอร์มและแบบทดสอบ';
require __DIR__ . '/includes/site_layout_start.php';
?>
<section class="wrap" style="padding-top:64px">
  <div style="max-width:680px">
    <span class="tag tag-accent">ยุคใหม่ของฟอร์มและแบบทดสอบ</span>
    <h1 style="font-size:42px;margin-top:18px;line-height:1.2">สร้างฟอร์มและแบบทดสอบ ให้ระบบช่วยคุณตั้งแต่ต้นจนจบ</h1>
    <p style="font-size:17px;opacity:.8;max-width:56ch">นำเข้าคลังคำถามเดิมของคุณ ไม่ว่าจะเป็น Aiken, GIFT หรือ CSV แล้วแชร์ลิงก์สั้นให้กลุ่มเป้าหมายตอบได้ในไม่กี่วินาที</p>
    <div style="display:flex;gap:12px;margin-top:28px;flex-wrap:wrap">
      <a class="btn btn-primary" href="signup.php">เริ่มใช้งานฟรี</a>
      <a class="btn btn-secondary" href="login.php">เข้าสู่ระบบ</a>
    </div>
  </div>

  <div class="grid-forms" style="margin-top:80px">
    <div class="card elev-sm">
      <div class="card-kicker">นำเข้าได้ทันที</div>
      <div class="card-title">รองรับหลายฟอร์แมต</div>
      <p class="card-body">นำเข้าคำถามจากไฟล์ Aiken, GIFT หรือ CSV ได้ในคลิกเดียว</p>
    </div>
    <div class="card elev-sm">
      <div class="card-kicker">แชร์ง่าย</div>
      <div class="card-title">ลิงก์สั้นพร้อมแชร์</div>
      <p class="card-body">สร้างลิงก์สั้นให้กลุ่มเป้าหมายตอบแบบฟอร์มหรือแบบทดสอบ กำหนดได้ว่าต้องเข้าสู่ระบบก่อนตอบหรือไม่</p>
    </div>
    <div class="card elev-sm">
      <div class="card-kicker">ควบคุมได้เอง</div>
      <div class="card-title">แสดง/ไม่แสดงคะแนน</div>
      <p class="card-body">เลือกได้ว่าจะให้ผู้ตอบเห็นคะแนนและเฉลยข้อถูก-ผิดทันทีหลังส่งคำตอบ หรือเก็บไว้ดูฝั่งเดียว</p>
    </div>
    <div class="card elev-sm">
      <div class="card-kicker">แยกส่วนชัดเจน</div>
      <div class="card-title">ข้อมูลผู้ตอบ vs แบบทดสอบ</div>
      <p class="card-body">แบ่งส่วนข้อมูลส่วนตัวออกจากคำตอบแบบทดสอบ พร้อมสุ่มลำดับคำถามในแต่ละส่วนได้</p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/site_layout_end.php'; ?>
