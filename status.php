<?php
/**
 * ตรวจสอบสถานะการสมัครเรียน | วิทยาลัยอาชีวศึกษามหาสารคาม
 */
session_start();
require_once __DIR__ . '/config/database.php';

$citizen_id = isset($_GET['citizen_id']) ? preg_replace('/[^0-9]/', '', $_GET['citizen_id']) : '';

// หากเข้าสู่ระบบในฐานะผู้สมัคร และไม่ได้ระบุ citizen_id มา ให้นำเลขบัตรตนเองมาแสดงผลอัตโนมัติ
if (empty($citizen_id) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !empty($_SESSION['citizen_id'])) {
    $citizen_id = $_SESSION['citizen_id'];
}

$applicant = null;
$searched = false;
$error_message = '';

if (!empty($citizen_id)) {
    $searched = true;
    if (strlen($citizen_id) === 13) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    a.*, 
                    p.program_name, 
                    p.program_code,
                    d.dept_name
                FROM applicants a
                LEFT JOIN programs p ON a.program_id = p.id
                LEFT JOIN departments d ON p.dept_id = d.id
                WHERE a.citizen_id = :citizen_id
                LIMIT 1
            ");
            $stmt->execute([':citizen_id' => $citizen_id]);
            $applicant = $stmt->fetch();
        } catch (PDOException $e) {
            $error_message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
        }
    } else {
        $error_message = "กรุณากรอกเลขประจำตัวประชาชนให้ครบถ้วน 13 หลัก";
    }
}

// ฟังก์ชันแปลงสถานะเป็นข้อความและสี Badge
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return ['text' => 'ผ่านการคัดเลือก / รอรายงานตัว', 'class' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'];
        case 'paid':
            return ['text' => 'ขึ้นทะเบียนนักศึกษาเรียบร้อย', 'class' => 'bg-primary text-white', 'icon' => 'bi-award-fill'];
        case 'document_incomplete':
            return ['text' => 'เอกสารไม่สมบูรณ์ / กรุณาแก้ไข', 'class' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-triangle-fill'];
        case 'rejected':
            return ['text' => 'ไม่ผ่านการคัดเลือก', 'class' => 'bg-danger text-white', 'icon' => 'bi-x-circle-fill'];
        case 'pending':
        default:
            return ['text' => 'รอการตรวจสอบเอกสาร', 'class' => 'bg-secondary text-white', 'icon' => 'bi-hourglass-split'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ตรวจสอบสถานะการสมัครเรียน | วิทยาลัยอาชีวศึกษามหาสารคาม</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="img/Logo-W.png">
  <link rel="shortcut icon" href="favicon.ico">

  <style>
    :root {
      --color-primary: #8B1E2B;
      --color-primary-deep: #6F1420;
      --color-primary-tint: #FDF2F2;
      --color-secondary-deep: #13294B;
      --color-bg: #F8FAFC;
      --color-surface: #FFFFFF;
      --color-text-primary: #0F172A;
      --color-text-secondary: #475569;
      --color-border-light: #E2E8F0;
    }

    body {
      font-family: 'Sarabun', sans-serif;
      color: var(--color-text-primary);
      background-color: var(--color-bg);
      line-height: 1.6;
      min-height: 100dvh;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      font-size: 0.875rem;
    }

    .navbar {
      background-color: var(--color-surface);
      border-bottom: 2px solid var(--color-border-light);
      box-shadow: 0 2px 4px rgba(15, 23, 42, 0.04);
    }

    .brand-logo-img {
      width: 44px;
      height: 44px;
      aspect-ratio: 1 / 1;
      object-fit: contain;
    }

    .status-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border-light);
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.08);
      overflow: hidden;
    }

    .status-card-header {
      background-color: var(--color-primary-tint);
      border-bottom: 1px solid rgba(139, 30, 43, 0.15);
      padding: 20px 28px;
    }

    .btn-admission {
      background-color: var(--color-primary);
      color: #FFFFFF;
      font-weight: 600;
      border-radius: 8px;
      padding: 10px 22px;
      border: 1px solid var(--color-primary);
      text-decoration: none;
      transition: all 0.2s ease;
    }

    .btn-admission:hover {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
    }

    .info-label {
      font-weight: 600;
      color: var(--color-text-secondary);
      font-size: 0.9rem;
    }

    .info-value {
      font-weight: 600;
      color: var(--color-secondary-deep);
      font-size: 1.05rem;
      text-align: left;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    @media (max-width: 575.98px) {
      .status-card-header {
        padding: 16px 18px;
      }
      .btn-admission {
        width: 100%;
        text-align: center;
      }
    }

    footer {
      margin-top: auto;
      background-color: var(--color-secondary-deep);
      color: #CBD5E1;
      padding: 30px 0;
      border-top: 4px solid var(--color-primary);
    }
  </style>
</head>
<body>

  <!-- Topbar -->
  <header class="topbar py-2">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <i class="bi bi-geo-alt-fill text-warning me-1"></i>
        <span>วิทยาลัยอาชีวศึกษามหาสารคาม</span>
      </div>
      <div>
        <span><i class="bi bi-telephone-fill me-1"></i> โทร. 043-711-371</span>
      </div>
    </div>
  </header>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="index.php">
        <img src="img/Logo-W.png" alt="โลโก้" class="brand-logo-img">
        <div>
          <div style="font-weight: 700; color: var(--color-primary-deep); line-height: 1.15;">ระบบรับสมัครนักศึกษา</div>
          <small style="font-size: 0.725rem; font-weight: 600; color: var(--color-primary);">วิทยาลัยอาชีวศึกษามหาสารคาม</small>
        </div>
      </a>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="programs.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-mortarboard me-1"></i> สาขาวิชาที่เปิดรับ
        </a>
        <a href="register.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-pencil-square me-1"></i> สมัครเรียนออนไลน์
        </a>
        <a href="print_application.php<?= !empty($citizen_id) ? '?search=' . urlencode($citizen_id) : '' ?>" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-printer me-1"></i> พิมพ์ใบสมัคร/เอกสาร
        </a>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
          <?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?>
            <a href="admin.php" class="btn btn-danger btn-sm" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-speedometer2 me-1"></i> แดชบอร์ดแอดมิน
            </a>
          <?php else: ?>
            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill d-none d-sm-inline-block">
              <i class="bi bi-person-fill me-1"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'ผู้สมัคร') ?>
            </span>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
          </a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-person-fill me-1"></i> เข้าสู่ระบบ
          </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-light btn-sm border text-secondary">
          <i class="bi bi-house me-1"></i> หน้าแรก
        </a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- Search Form Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
          <h4 class="fw-bold mb-3" style="color: var(--color-primary-deep);">
            <i class="bi bi-search text-danger me-2"></i>ตรวจสอบสถานะการสมัครเรียน
          </h4>
          <form action="status.php" method="GET" class="row g-2">
            <div class="col-md-9">
              <input
                type="text"
                name="citizen_id"
                class="form-control form-control-lg fs-6"
                placeholder="กรอกเลขประจำตัวประชาชน 13 หลัก"
                maxlength="13"
                value="<?= htmlspecialchars($citizen_id) ?>"
                inputmode="numeric"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);"
                required
              >
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-admission w-100 h-100">
                <i class="bi bi-search me-1"></i> ค้นหา
              </button>
            </div>
          </form>
          <div class="small text-muted mt-2">
            <i class="bi bi-info-circle me-1"></i> ระบบเชื่อมต่อฐานข้อมูล <strong>mvc_admission</strong> เรียบร้อยแล้ว
          </div>
        </div>

        <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger rounded-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($searched && $applicant): ?>
          <?php $badge = getStatusBadge($applicant['status']); ?>
          <!-- Result Found Card -->
          <div class="status-card">
            <div class="status-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-1">
                  ปีการศึกษา <?= htmlspecialchars($applicant['academic_year']) ?>
                </span>
                <h5 class="fw-bold mb-0" style="color: var(--color-primary-deep);">
                  รหัสใบสมัคร: <?= htmlspecialchars($applicant['application_no']) ?>
                </h5>
              </div>
              <span class="badge <?= $badge['class'] ?> px-3 py-2 fs-6 rounded-pill">
                <i class="bi <?= $badge['icon'] ?> me-1"></i> <?= $badge['text'] ?>
              </span>
            </div>

            <div class="p-4">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="info-label">ชื่อ - นามสกุล ผู้สมัคร</div>
                  <div class="info-value">
                    <?= htmlspecialchars($applicant['prefix'] . $applicant['first_name'] . ' ' . $applicant['last_name']) ?>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="info-label">เลขประจำตัวประชาชน</div>
                  <div class="info-value font-monospace">
                    <?= htmlspecialchars(substr($applicant['citizen_id'], 0, 1) . '-' . substr($applicant['citizen_id'], 1, 4) . '-XXXXX-' . substr($applicant['citizen_id'], 10, 2) . '-' . substr($applicant['citizen_id'], 12, 1)) ?>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="info-label">ระดับการศึกษาที่สมัคร</div>
                  <div class="info-value">
                    <span class="badge bg-danger text-white px-2 py-1"><?= htmlspecialchars($applicant['education_level']) ?></span>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="info-label">เบอร์โทรศัพท์ติดต่อ</div>
                  <div class="info-value"><?= htmlspecialchars($applicant['phone']) ?></div>
                </div>

                <div class="col-12">
                  <div class="info-label">สาขาวิชาที่เลือกศึกษาต่อ</div>
                  <div class="info-value text-primary fw-bold" style="color: var(--color-primary) !important;">
                    <?= htmlspecialchars($applicant['program_name'] ?? 'ไม่ระบุ') ?>
                  </div>
                  <small class="text-secondary"><?= htmlspecialchars($applicant['dept_name'] ?? '') ?></small>
                </div>

                <?php if (!empty($applicant['status_note'])): ?>
                  <div class="col-12">
                    <div class="p-3 rounded-3" style="background-color: #FEF3C7; border-left: 4px solid #D97706;">
                      <div class="fw-bold text-dark mb-1">
                        <i class="bi bi-chat-left-dots-fill text-warning me-1"></i> ข้อความแจ้งเตือนจากงานทะเบียน:
                      </div>
                      <div class="text-dark small"><?= nl2br(htmlspecialchars($applicant['status_note'])) ?></div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <hr class="my-4">

              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                  วันที่ยื่นใบสมัคร: <?= date('d/m/Y H:i น.', strtotime($applicant['created_at'])) ?>
                </small>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="print_application.php?search=<?= urlencode($applicant['citizen_id']) ?>" class="btn btn-outline-danger btn-sm fw-semibold">
                    <i class="bi bi-printer-fill me-1"></i> พิมพ์ใบสมัครฉบับจริง / แนบเอกสาร
                  </a>
                  <a href="index.php" class="btn btn-admission btn-sm">
                    เสร็จสิ้น
                  </a>
                </div>
              </div>

            </div>
          </div>

        <?php elseif ($searched && empty($error_message)): ?>
          <!-- Not Found Card -->
          <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <div class="text-secondary mb-3" style="font-size: 3rem;">
              <i class="bi bi-folder-x"></i>
            </div>
            <h5 class="fw-bold mb-2">ไม่พบข้อมูลการสมัคร</h5>
            <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
              ไม่พบประวัติการสมัครของเลขประจำตัวประชาชน <strong class="text-danger"><?= htmlspecialchars($citizen_id) ?></strong> 
              กรุณาตรวจสอบความถูกต้อง หรือลงทะเบียนสมัครเรียนใหม่
            </p>
            <div class="d-flex justify-content-center gap-3">
              <a href="index.php#search-citizen-section" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-clockwise me-1"></i> ลองค้นหาใหม่
              </a>
              <a href="register.php" class="btn btn-admission">
                <i class="bi bi-pencil-square me-1"></i> สมัครเรียนออนไลน์
              </a>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer>
    <div class="container text-center small">
      &copy; 2570 วิทยาลัยอาชีวศึกษามหาสารคาม. ระบบรับสมัครนักเรียน นักศึกษาใหม่ออนไลน์
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
