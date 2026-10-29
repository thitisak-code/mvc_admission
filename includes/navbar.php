<?php
/**
 * Shared Unified Navigation Bar & Mobile App-like Bottom Navigation
 * ระบบรับสมัครนักเรียน นักศึกษา วิทยาลัยอาชีวศึกษามหาสารคาม
 */

// 1. ตรวจสอบการโหลดข้อมูลการตั้งค่าเว็บไซต์ (Homepage Settings)
if (!isset($hp) || !is_array($hp)) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }
    require_once __DIR__ . '/../config/homepage_settings.php';
    $hp = getHomepageSettings($pdo);
}

// 2. ตรวจสอบปีการศึกษาปัจจุบัน
if (!isset($active_year) || empty($active_year)) {
    $active_year = '2570';
    try {
        if (isset($pdo)) {
            $stmt_yr = $pdo->query("SELECT year_name FROM academic_years WHERE is_active = 1 ORDER BY year_name DESC LIMIT 1");
            $yr_row = $stmt_yr->fetch(PDO::FETCH_ASSOC);
            if ($yr_row && !empty($yr_row['year_name'])) {
                $active_year = $yr_row['year_name'];
            }
        }
    } catch (Exception $e) {}
}

// 3. ตรวจสอบสถานะการเข้าสู่ระบบ (Session)
$nav_is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$nav_user_type    = $_SESSION['user_type'] ?? '';
$nav_user_name    = $_SESSION['user_name'] ?? '';
$nav_citizen_id   = $_SESSION['citizen_id'] ?? '';
$nav_is_admin     = ($nav_is_logged_in && $nav_user_type === 'admin') || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$nav_display_name = $nav_is_admin ? ($nav_user_name ?: ($_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ')) : ($nav_user_name ?: 'ผู้สมัคร');

// 4. ตรวจสอบหน้าปัจจุบัน ($current_page)
if (!isset($current_page) || empty($current_page)) {
    $script_name = basename($_SERVER['PHP_SELF'] ?? '');
    switch ($script_name) {
        case 'index.php':
            $current_page = 'index';
            break;
        case 'programs.php':
            $current_page = 'programs';
            break;
        case 'register.php':
            $current_page = 'register';
            break;
        case 'status.php':
            $current_page = 'status';
            break;
        case 'print_application.php':
            $current_page = 'print';
            break;
        case 'login.php':
            $current_page = 'login';
            break;
        case 'admin.php':
            $current_page = 'admin';
            break;
        default:
            $current_page = '';
            break;
    }
}
?>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- ==========================================================================
     Shared Navbar & Mobile Bottom Navigation Styles
     ========================================================================== -->
<style>
  :root {
    --nav-primary: #8B1E2B;
    --nav-primary-deep: #6F1420;
    --nav-primary-light: #B22B3B;
    --nav-primary-tint: #FDF2F2;
    --nav-secondary: #1B365D;
    --nav-secondary-deep: #13294B;
  }

  /* Print Styles - Completely hide shared navbars and bottom navigation */
  @media print {
    .shared-topbar,
    .shared-navbar,
    .mobile-bottom-nav,
    .no-print {
      display: none !important;
    }
    body {
      padding-bottom: 0 !important;
    }
  }

  /* Top Announcement Bar */
  .shared-topbar {
    background-color: var(--nav-primary-deep);
    color: #FFFFFF;
    font-size: 0.85rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.12);
  }

  .shared-topbar a {
    color: #FFFFFF;
    text-decoration: none;
    transition: opacity 0.2s ease;
  }

  .shared-topbar a:hover {
    color: #FEE2E2;
    text-decoration: none;
  }

  .shared-topbar-badge {
    background-color: rgba(255, 255, 255, 0.2);
    color: #FFFFFF;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.775rem;
    font-weight: 600;
    letter-spacing: 0.3px;
  }

  /* Main Desktop Navbar */
  .shared-navbar {
    background-color: #FFFFFF;
    border-bottom: 2px solid #E2E8F0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    z-index: 1030;
  }

  .shared-navbar .brand-title-wrap {
    color: var(--nav-primary-deep);
    font-weight: 700;
    font-size: clamp(0.95rem, 0.85rem + 0.5vw, 1.25rem);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
  }

  .shared-navbar .brand-logo-img {
    width: 48px;
    height: 48px;
    aspect-ratio: 1 / 1;
    object-fit: contain;
    flex-shrink: 0;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.08));
    transition: transform 0.25s ease;
  }

  .shared-navbar .brand-logo-img:hover {
    transform: scale(1.06);
  }

  .shared-navbar .nav-link {
    color: #475569;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 0.5rem 0.85rem;
    transition: color 0.25s ease;
    position: relative;
    background: transparent !important;
    border: none !important;
  }

  /* Underline Animation on Hover/Active */
  .shared-navbar .nav-link::after {
    content: '';
    position: absolute;
    bottom: 2px;
    left: 12px;
    right: 12px;
    height: 2.5px;
    background-color: var(--nav-primary);
    border-radius: 2px;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
  }

  .shared-navbar .nav-link:hover {
    color: var(--nav-primary);
    background-color: transparent !important;
  }

  .shared-navbar .nav-link:hover::after,
  .shared-navbar .nav-link.active::after {
    transform: scaleX(1);
  }

  .shared-navbar .nav-link.active {
    color: var(--nav-primary);
    font-weight: 700;
    background-color: transparent !important;
  }

  /* ==========================================================================
     Mobile App-like Bottom Navigation Bar (เมนูบาร์ด้านล่างบนโทรศัพท์มือถือ)
     ========================================================================== */
  .mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 64px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-top: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 -4px 24px rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-around;
    z-index: 1040;
    padding: 0 4px;
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }

  /* Hide Bottom Nav on Large Screens (Desktop / Large Tablet) */
  @media (min-width: 992px) {
    .mobile-bottom-nav {
      display: none !important;
    }
  }

  /* Ensure page content is never covered by bottom nav on mobile */
  @media (max-width: 991.98px) {
    body {
      padding-bottom: 72px !important;
    }
  }

  .bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #64748B;
    padding: 6px 2px;
    position: relative;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    -webkit-tap-highlight-color: transparent;
  }

  .bottom-nav-item:active {
    transform: scale(0.92);
  }

  .bottom-nav-icon {
    font-size: 1.25rem;
    line-height: 1.2;
    transition: transform 0.2s ease, color 0.2s ease;
  }

  .bottom-nav-label {
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 2px;
    line-height: 1.1;
    white-space: nowrap;
    transition: color 0.2s ease, font-weight 0.2s ease;
  }

  .bottom-nav-item.active {
    color: var(--nav-primary);
  }

  .bottom-nav-item.active .bottom-nav-icon {
    color: var(--nav-primary);
    transform: translateY(-1px);
  }

  .bottom-nav-item.active .bottom-nav-label {
    color: var(--nav-primary);
    font-weight: 700;
  }

  .bottom-nav-item.active::after {
    content: '';
    position: absolute;
    bottom: 3px;
    width: 16px;
    height: 3px;
    background-color: var(--nav-primary);
    border-radius: 3px;
  }

  /* Elevated Floating Center Action Button (ปุ่มเด่นตรงกลาง: สมัครเรียน) */
  .bottom-nav-center-item {
    flex: 1.1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    position: relative;
    margin-top: -16px;
    -webkit-tap-highlight-color: transparent;
  }

  .bottom-nav-fab {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--nav-primary) 0%, var(--nav-primary-light) 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 18px rgba(139, 30, 43, 0.38);
    border: 3px solid #FFFFFF;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .bottom-nav-center-item:active .bottom-nav-fab {
    transform: scale(0.92);
    box-shadow: 0 3px 10px rgba(139, 30, 43, 0.3);
  }

  .bottom-nav-center-item.active .bottom-nav-fab {
    background: linear-gradient(135deg, var(--nav-primary-deep) 0%, var(--nav-primary) 100%);
    box-shadow: 0 6px 20px rgba(139, 30, 43, 0.55);
  }

  .bottom-nav-center-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--nav-primary);
    margin-top: 3px;
    line-height: 1.1;
    white-space: nowrap;
  }

  .bottom-nav-center-item:not(.active) .bottom-nav-center-label {
    color: #475569;
  }
</style>

<!-- 1. Top Announcement Bar (เน้นสีแดงสถาบัน Deep Crimson) -->
<header class="shared-topbar py-2 d-none d-md-block no-print">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-geo-alt-fill text-warning"></i>
      <span><?= htmlspecialchars($hp['topbar_address'] ?? 'วิทยาลัยอาชีวศึกษามหาสารคาม') ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span><i class="bi bi-telephone-fill me-1"></i> โทร. <?= htmlspecialchars($hp['topbar_phone'] ?? '043-711-371') ?></span>
      <span class="opacity-50">|</span>
      <span class="shared-topbar-badge">
        <i class="bi bi-mortarboard-fill me-1"></i> ปีการศึกษา <?= htmlspecialchars($active_year) ?>
      </span>
      <?php if ($nav_is_admin): ?>
        <span class="opacity-50">|</span>
        <a href="admin.php" class="text-warning fw-semibold">
          <i class="bi bi-speedometer2 me-1"></i> ระบบผู้ดูแล (Admin)
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- 2. Main Desktop Navbar -->
<nav class="navbar navbar-expand-lg shared-navbar sticky-top no-print" aria-label="เมนูหลัก">
  <div class="container">
    
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($show_sidebar_toggle)): ?>
        <button class="btn btn-sm btn-light border text-secondary rounded-2 me-1 sidebar-toggle-btn" type="button" id="sidebarToggle" title="เปิด/ปิดเมนูด้านข้าง">
          <i class="fa-solid fa-bars"></i>
        </button>
      <?php endif; ?>
      
      <a class="navbar-brand brand-title-wrap m-0" href="index.php">
        <img src="<?= htmlspecialchars($hp['site_logo'] ?? 'img/Logo-W.png') ?>" alt="ตราสัญลักษณ์" class="brand-logo-img" onerror="this.src='img/Logo-W.png'">
        <div>
          <div style="line-height: 1.15; font-size: 1.05rem;"><?= htmlspecialchars($hp['navbar_title'] ?? 'วิทยาลัยอาชีวศึกษามหาสารคาม') ?></div>
          <small style="font-size: 0.725rem; font-weight: 600; color: var(--nav-primary);">
            <?= htmlspecialchars($hp['navbar_subtitle'] ?? 'Mahasarakham Vocational College') ?>
          </small>
        </div>
      </a>
    </div>

    <!-- Desktop Collapsible Menu Toggler (สำหรับ Tablet/Desktop) -->
    <button class="navbar-toggler border-0 shadow-none d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#sharedMainMenu" aria-controls="sharedMainMenu" aria-expanded="false" aria-label="เปิด/ปิด เมนูนำทาง">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="sharedMainMenu">
      <ul class="navbar-nav ms-auto gap-lg-1 align-items-lg-center mt-3 mt-lg-0">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-house-door me-1"></i> หน้าแรก
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'programs' ? 'active' : '' ?>" href="programs.php">
            <i class="bi bi-mortarboard me-1"></i> สาขาวิชาที่เปิดรับ
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'register' ? 'active' : '' ?>" href="register.php">
            <i class="bi bi-pencil-square me-1"></i> สมัครเรียนออนไลน์
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'status' ? 'active' : '' ?>" href="status.php">
            <i class="bi bi-search me-1"></i> ตรวจสอบสถานะ
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'print' ? 'active' : '' ?>" href="print_application.php">
            <i class="bi bi-printer me-1"></i> พิมพ์ใบสมัคร/เอกสาร
          </a>
        </li>

        <?php if ($nav_is_logged_in): ?>
          <li class="nav-item dropdown ms-lg-2 mt-2 mt-lg-0">
            <button class="btn btn-danger dropdown-toggle py-2 px-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.9rem; background-color: var(--nav-primary); border-color: var(--nav-primary); border-radius: 8px;">
              <?php if ($nav_is_admin): ?>
                <i class="bi bi-shield-lock-fill"></i>
                <span>ผู้ดูแล: <?= htmlspecialchars($nav_display_name) ?></span>
              <?php else: ?>
                <i class="bi bi-person-circle"></i>
                <span><?= htmlspecialchars($nav_display_name) ?></span>
              <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-1">
              <?php if ($nav_is_admin): ?>
                <li><a class="dropdown-item py-2" href="admin.php?tab=applicants"><i class="bi bi-people text-danger me-2"></i> จัดการผู้สมัครเรียน</a></li>
                <li><a class="dropdown-item py-2" href="admin.php?tab=departments"><i class="bi bi-mortarboard text-warning me-2"></i> จัดการแผนกและสาขาวิชา</a></li>
                <li><a class="dropdown-item py-2" href="admin.php?tab=rounds"><i class="bi bi-calendar2-week text-info me-2"></i> จัดการรอบการรับสมัคร</a></li>
                <li><a class="dropdown-item py-2" href="admin.php?tab=admins"><i class="bi bi-shield-person text-primary me-2"></i> จัดการผู้ดูแลระบบ</a></li>
                <li><a class="dropdown-item py-2 text-danger fw-semibold" href="admin.php?tab=homepage"><i class="bi bi-layout-text-window-reverse me-2"></i> ปรับแต่งหน้าแรก (Index)</a></li>
              <?php else: ?>
                <li><a class="dropdown-item py-2" href="status.php?citizen_id=<?= urlencode($nav_citizen_id) ?>"><i class="bi bi-card-checklist text-danger me-2"></i> สถานะการสมัครของฉัน</a></li>
                <li><a class="dropdown-item py-2" href="print_application.php?search=<?= urlencode($nav_citizen_id) ?>"><i class="bi bi-printer-fill text-primary me-2"></i> พิมพ์ใบสมัคร / แนบเอกสาร</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item py-2 text-danger fw-semibold" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
            <a href="login.php" class="btn btn-outline-danger py-2 px-3 <?= $current_page === 'login' ? 'active' : '' ?>" style="font-size: 0.9rem; border-color: var(--nav-primary); color: var(--nav-primary);">
              <i class="bi bi-person-fill"></i> เข้าสู่ระบบ
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>

  </div>
</nav>

<!-- ==========================================================================
     3. Mobile App-like Bottom Navigation Bar (แถบเมนูล่างบนโทรศัพท์มือถือ พร้อมไอคอนสวยงาม)
     ========================================================================== -->
<div class="mobile-bottom-nav no-print" aria-label="แถบเมนูนำทางบนมือถือ">
  
  <!-- เมนูที่ 1: หน้าแรก -->
  <a href="index.php" class="bottom-nav-item <?= $current_page === 'index' ? 'active' : '' ?>">
    <i class="bi bi-house-door<?= $current_page === 'index' ? '-fill' : '' ?> bottom-nav-icon"></i>
    <span class="bottom-nav-label">หน้าแรก</span>
  </a>

  <!-- เมนูที่ 2: สาขาวิชา -->
  <a href="programs.php" class="bottom-nav-item <?= $current_page === 'programs' ? 'active' : '' ?>">
    <i class="bi bi-mortarboard<?= $current_page === 'programs' ? '-fill' : '' ?> bottom-nav-icon"></i>
    <span class="bottom-nav-label">สาขาวิชา</span>
  </a>

  <!-- เมนูที่ 3 (เด่นตรงกลาง): สมัครเรียน -->
  <a href="register.php" class="bottom-nav-center-item <?= $current_page === 'register' ? 'active' : '' ?>">
    <div class="bottom-nav-fab">
      <i class="bi bi-pencil-square fs-5"></i>
    </div>
    <span class="bottom-nav-center-label">สมัครเรียน</span>
  </a>

  <!-- เมนูที่ 4: ตรวจสถานะ -->
  <a href="status.php" class="bottom-nav-item <?= $current_page === 'status' ? 'active' : '' ?>">
    <i class="bi bi-search bottom-nav-icon"></i>
    <span class="bottom-nav-label">ตรวจสถานะ</span>
  </a>

  <!-- เมนูที่ 5: พิมพ์ใบสมัคร หรือ โปรไฟล์ -->
  <?php if ($nav_is_logged_in): ?>
    <?php if ($nav_is_admin): ?>
      <a href="admin.php" class="bottom-nav-item <?= $current_page === 'admin' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2 bottom-nav-icon text-danger"></i>
        <span class="bottom-nav-label">แอดมิน</span>
      </a>
    <?php else: ?>
      <a href="print_application.php?search=<?= urlencode($nav_citizen_id) ?>" class="bottom-nav-item <?= $current_page === 'print' ? 'active' : '' ?>">
        <i class="bi bi-printer<?= $current_page === 'print' ? '-fill' : '' ?> bottom-nav-icon"></i>
        <span class="bottom-nav-label">พิมพ์ใบสมัคร</span>
      </a>
    <?php endif; ?>
  <?php else: ?>
    <a href="print_application.php" class="bottom-nav-item <?= $current_page === 'print' ? 'active' : '' ?>">
      <i class="bi bi-printer<?= $current_page === 'print' ? '-fill' : '' ?> bottom-nav-icon"></i>
      <span class="bottom-nav-label">พิมพ์เอกสาร</span>
    </a>
  <?php endif; ?>

</div>
