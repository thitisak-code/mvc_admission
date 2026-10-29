<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/homepage_settings.php';
require_once __DIR__ . '/config/round_helper.php';

$hp = getHomepageSettings($pdo);
$active_rounds = getActiveAdmissionRounds($pdo);

// ดึงปีการศึกษาที่เปิดใช้งานจากระบบตั้งค่า (Admin)
$active_year = '2570';
try {
    $stmt_yr = $pdo->query("SELECT year_name FROM academic_years WHERE is_active = 1 ORDER BY year_name DESC LIMIT 1");
    $yr_row = $stmt_yr->fetch(PDO::FETCH_ASSOC);
    if ($yr_row && !empty($yr_row['year_name'])) {
        $active_year = $yr_row['year_name'];
    }
} catch (PDOException $e) {}

// ดึงรายชื่อสาขาวิชาทั้งหมดที่เปิดรับสมัคร
try {
    $stmt_majors = $pdo->prepare("
        SELECT p.id AS major_id,
               p.program_code,
               p.program_name AS major_name,
               p.education_level AS level_name,
               p.program_type,
               p.seats_available,
               p.status,
               d.id AS dept_id,
               d.dept_code,
               d.dept_name,
               d.description AS dept_desc,
               CASE p.education_level
                   WHEN 'ปวช.' THEN 1
                   WHEN 'ปวส.' THEN 2
                   WHEN 'ปริญญาตรี' THEN 3
                   ELSE 1
               END AS level_id
        FROM programs p
        JOIN departments d ON p.dept_id = d.id
        WHERE p.status = 'open' AND (p.academic_year = :yr OR p.academic_year IS NULL OR p.academic_year = '')
        ORDER BY level_id ASC, d.id ASC, p.id ASC
    ");
    $stmt_majors->execute([':yr' => $active_year]);
    $programs = $stmt_majors->fetchAll(PDO::FETCH_ASSOC);

    if (empty($programs)) {
        $stmt_all = $pdo->query("
            SELECT p.id AS major_id,
                   p.program_code,
                   p.program_name AS major_name,
                   p.education_level AS level_name,
                   p.program_type,
                   p.seats_available,
                   p.status,
                   d.id AS dept_id,
                   d.dept_code,
                   d.dept_name,
                   d.description AS dept_desc,
                   CASE p.education_level
                       WHEN 'ปวช.' THEN 1
                       WHEN 'ปวส.' THEN 2
                       WHEN 'ปริญญาตรี' THEN 3
                       ELSE 1
                   END AS level_id
            FROM programs p
            JOIN departments d ON p.dept_id = d.id
            WHERE p.status = 'open'
            ORDER BY level_id ASC, d.id ASC, p.id ASC
        ");
        $programs = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $programs = [];
}

// นับจำนวนสาขาวิชาแต่ละระดับ
$count_all = count($programs);
$count_pvc = 0;
$count_pvs = 0;
$count_deg = 0;
$departments_list = [];

foreach ($programs as $pr) {
    if ($pr['level_name'] === 'ปวช.') $count_pvc++;
    elseif ($pr['level_name'] === 'ปวส.') $count_pvs++;
    elseif ($pr['level_name'] === 'ปริญญาตรี') $count_deg++;

    if (!isset($departments_list[$pr['dept_id']])) {
        $departments_list[$pr['dept_id']] = $pr['dept_name'];
    }
}

// ตรวจสอบสถานะล็อกอิน
$is_logged_in    = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_type       = $_SESSION['user_type'] ?? '';
$user_name       = $_SESSION['user_name'] ?? '';
$user_citizen_id = $_SESSION['citizen_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สาขาวิชาและหลักสูตรที่เปิดรับสมัคร | วิทยาลัยอาชีวศึกษามหาสารคาม</title>
  <meta name="description" content="ตรวจสอบสาขาวิชาที่เปิดรับสมัครเรียนต่อ ระดับ ปวช. ปวส. และปริญญาตรี ประจำปีการศึกษา <?= htmlspecialchars($active_year) ?> วิทยาลัยอาชีวศึกษามหาสารคาม">

  <!-- Favicon Logo -->
  <link rel="icon" type="image/png" href="img/Logo-W.png">
  <link rel="shortcut icon" href="favicon.ico">

  <!-- Bootstrap 5.3.3 & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  
  <!-- Google Fonts: Sarabun & Prompt -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- AOS Animation CSS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <style>
    :root {
      --color-primary: #8B1E2B;
      --color-primary-deep: #6F1420;
      --color-primary-hover: #580E18;
      --color-primary-light: #B22B3B;
      --color-primary-tint: #FDF2F2;
      --color-primary-border: #FECACA;
      --color-secondary: #1B365D;
      --color-secondary-deep: #13294B;
      --color-bg: #F8FAFC;
      --color-surface: #FFFFFF;
      --color-text-primary: #0F172A;
      --color-text-secondary: #475569;
      --color-border-light: #E2E8F0;
      --radius-sm: 6px;
      --radius-md: 12px;
      --radius-lg: 20px;
      --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.04);
      --shadow-md: 0 8px 24px rgba(139, 30, 43, 0.08);
      --shadow-lg: 0 16px 36px rgba(139, 30, 43, 0.14);
    }

    html, body {
      font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--color-text-primary);
      background-color: var(--color-bg);
      line-height: 1.65;
      margin: 0;
      min-height: 100dvh;
      max-width: 100%;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* Topbar */
    .topbar {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      font-size: 0.85rem;
      border-bottom: 2px solid rgba(255, 255, 255, 0.12);
    }
    .topbar a {
      color: #FFFFFF;
      text-decoration: none;
      transition: opacity 0.2s ease;
    }
    .topbar a:hover {
      opacity: 0.85;
      text-decoration: underline;
    }

    /* Navbar */
    .navbar {
      background-color: var(--color-surface);
      border-bottom: 2px solid var(--color-border-light);
      box-shadow: var(--shadow-sm);
    }
    .brand-title {
      color: var(--color-primary-deep);
      font-weight: 700;
      font-size: clamp(0.95rem, 0.85rem + 0.5vw, 1.25rem);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
      max-width: calc(100% - 56px);
      white-space: normal;
    }
    .brand-logo-img {
      width: 48px;
      height: 48px;
      aspect-ratio: 1 / 1;
      object-fit: contain;
      flex-shrink: 0;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.08));
    }
    .navbar .nav-link {
      color: var(--color-text-secondary);
      font-weight: 500;
      font-size: 0.95rem;
      padding: 0.5rem 0.85rem;
      transition: color 0.25s ease;
      position: relative;
    }
    .navbar .nav-link::after {
      content: '';
      position: absolute;
      bottom: 2px;
      left: 12px;
      right: 12px;
      height: 2.5px;
      background-color: var(--color-primary);
      border-radius: 2px;
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .navbar .nav-link:hover {
      color: var(--color-primary);
    }
    .navbar .nav-link:hover::after,
    .navbar .nav-link.active::after {
      transform: scaleX(1);
    }
    .navbar .nav-link.active {
      color: var(--color-primary);
      font-weight: 700;
    }

    /* Hero Banner Header */
    .programs-hero {
      background: linear-gradient(135deg, #FFF5F5 0%, #FFFFFF 50%, #F0F4F8 100%);
      padding: 50px 0 40px;
      border-bottom: 1px solid var(--color-border-light);
      position: relative;
    }
    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      border: 1px solid var(--color-primary-border);
      border-radius: 30px;
      padding: 6px 16px;
      font-size: 0.875rem;
      font-weight: 600;
    }
    .programs-hero-title {
      color: var(--color-secondary-deep);
      font-weight: 800;
      font-size: clamp(1.6rem, 1.25rem + 1.5vw, 2.4rem);
      line-height: 1.3;
      margin-top: 12px;
      margin-bottom: 8px;
    }
    .programs-hero-subtitle {
      color: var(--color-text-secondary);
      font-size: 1.05rem;
      max-width: 720px;
      margin: 0 auto;
    }

    /* Filter & Search Bar */
    .filter-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border-light);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
      margin-top: -25px;
      position: relative;
      z-index: 10;
    }
    .filter-btn {
      border: 1.5px solid var(--color-border-light);
      background-color: #FFFFFF;
      color: var(--color-text-secondary);
      font-weight: 600;
      font-size: 0.92rem;
      padding: 8px 18px;
      border-radius: 30px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
    }
    .filter-btn:hover {
      border-color: var(--color-primary);
      color: var(--color-primary);
      background-color: var(--color-primary-tint);
    }
    .filter-btn.active {
      background-color: var(--color-primary);
      border-color: var(--color-primary);
      color: #FFFFFF;
      box-shadow: 0 3px 10px rgba(139, 30, 43, 0.25);
    }

    /* Program Cards */
    .program-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border-light);
      border-radius: 14px;
      height: 100%;
      display: flex;
      flex-direction: column;
      padding: 24px 22px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .program-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--color-border-light);
      transition: background 0.25s ease;
    }
    .program-card.level-1::before { background: #DC2626; } /* ปวช. สีแดง */
    .program-card.level-2::before { background: #2563EB; } /* ปวส. สีน้ำเงิน */
    .program-card.level-3::before { background: #7C3AED; } /* ปริญญาตรี สีม่วง */

    .program-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
      border-color: #CBD5E1;
    }

    .level-tag {
      font-weight: 700;
      font-size: 0.8rem;
      padding: 4px 10px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .level-tag.level-1 {
      background-color: #FEF2F2;
      color: #B91C1C;
      border: 1px solid #FECACA;
    }
    .level-tag.level-2 {
      background-color: #EFF6FF;
      color: #1D4ED8;
      border: 1px solid #BFDBFE;
    }
    .level-tag.level-3 {
      background-color: #F5F3FF;
      color: #6D28D9;
      border: 1px solid #DDD6FE;
    }

    .dept-badge {
      font-size: 0.82rem;
      color: var(--color-text-secondary);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .program-title {
      font-weight: 700;
      font-size: 1.18rem;
      color: var(--color-secondary-deep);
      line-height: 1.35;
      margin-top: 10px;
      margin-bottom: 6px;
    }

    .program-code-badge {
      font-family: 'Prompt', monospace;
      font-size: 0.8rem;
      background-color: #F1F5F9;
      color: #475569;
      padding: 2px 8px;
      border-radius: 6px;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 10px;
    }

    .program-desc {
      color: #64748B;
      font-size: 0.9rem;
      line-height: 1.55;
      margin-bottom: 18px;
      flex-grow: 1;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .program-meta {
      border-top: 1px solid #F1F5F9;
      padding-top: 14px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.85rem;
    }

    .btn-apply-major {
      background-color: var(--color-primary);
      color: #FFFFFF;
      font-weight: 600;
      font-size: 0.95rem;
      border-radius: 8px;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      transition: all 0.2s ease;
      border: 1px solid var(--color-primary);
      box-shadow: 0 2px 6px rgba(139, 30, 43, 0.2);
    }
    .btn-apply-major:hover {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(139, 30, 43, 0.35);
    }

    /* Footer */
    footer {
      background-color: var(--color-secondary-deep);
      color: #CBD5E1;
      padding: 50px 0 30px;
      border-top: 4px solid var(--color-primary);
      margin-top: 60px;
    }
    footer a {
      color: #94A3B8;
      text-decoration: none;
      transition: color 0.2s;
    }
    footer a:hover {
      color: #FFFFFF;
    }

    @media (max-width: 767.98px) {
      .filter-card {
        padding: 16px;
      }
      .filter-buttons-wrap {
        display: flex;
        overflow-x: auto;
        padding-bottom: 6px;
        gap: 6px;
      }
      .filter-btn {
        white-space: nowrap;
        font-size: 0.85rem;
        padding: 6px 14px;
      }
    }

    /* ==========================================================================
       Preloader with Logo (หน้าโหลดพร้อมโลโก้วิทยาลัย)
       ========================================================================== */
    .preloader-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: radial-gradient(circle at center, #FFFFFF 0%, #F8FAFC 100%);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
      visibility: visible;
      transition: opacity 0.35s ease, visibility 0.35s ease;
    }

    .preloader-overlay.fade-out {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .preloader-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .preloader-logo-wrap {
      position: relative;
      width: 104px;
      height: 104px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .preloader-pulse-ring {
      position: absolute;
      top: -6px;
      left: -6px;
      right: -6px;
      bottom: -6px;
      border-radius: 50%;
      border: 2.5px solid var(--color-primary);
      opacity: 0.8;
      animation: preloaderPulse 1.8s infinite cubic-bezier(0.25, 1, 0.5, 1);
    }

    .preloader-logo {
      width: 90px;
      height: 90px;
      aspect-ratio: 1 / 1;
      object-fit: contain;
      border-radius: 50%;
      padding: 6px;
      background: #FFFFFF;
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.15);
      animation: preloaderBreathing 2s infinite ease-in-out;
      position: relative;
      z-index: 2;
    }

    @keyframes preloaderPulse {
      0% {
        transform: scale(0.9);
        opacity: 0.8;
      }
      50% {
        transform: scale(1.18);
        opacity: 0.2;
      }
      100% {
        transform: scale(1.35);
        opacity: 0;
      }
    }

    @keyframes preloaderBreathing {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.06);
      }
    }

    .preloader-title {
      font-weight: 700;
      color: var(--color-primary-deep);
      font-size: 1.1rem;
      letter-spacing: -0.2px;
    }

    .preloader-subtitle {
      font-weight: 500;
      font-size: 0.85rem;
      color: #64748B;
    }

    .preloader-spinner-bar {
      width: 140px;
      height: 4px;
      background: #E2E8F0;
      border-radius: 4px;
      overflow: hidden;
      position: relative;
      margin-top: 8px;
    }

    .preloader-progress {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 45%;
      background: linear-gradient(90deg, var(--color-primary), #EA5455);
      border-radius: 4px;
      animation: preloaderSlide 1.2s infinite ease-in-out;
    }

    @keyframes preloaderSlide {
      0% {
        left: -45%;
      }
      100% {
        left: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- Preloader with College Logo -->
  <div id="preloader" class="preloader-overlay">
    <div class="preloader-content text-center">
      <div class="preloader-logo-wrap">
        <div class="preloader-pulse-ring"></div>
        <img src="img/Logo-W.png" alt="ตราสัญลักษณ์ วิทยาลัยอาชีวศึกษามหาสารคาม" class="preloader-logo" onerror="this.src='assets/images/mvc_round_seal.png'">
      </div>
      <h6 class="preloader-title mt-3 mb-1">วิทยาลัยอาชีวศึกษามหาสารคาม</h6>
      <p class="preloader-subtitle small mb-2">ระบบรับสมัครนักเรียน นักศึกษา ออนไลน์</p>
      <div class="preloader-spinner-bar">
        <div class="preloader-progress"></div>
      </div>
    </div>
  </div>

  <!-- Unified Header & Mobile Bottom Navigation Bar -->
  <?php 
    $current_page = 'programs';
    require __DIR__ . '/includes/navbar.php'; 
  ?>

  <!-- Hero Header -->
  <section class="programs-hero text-center">
    <div class="container" data-aos="fade-down" data-aos-duration="700">
      <div class="hero-badge-pill mb-2">
        <i class="bi bi-mortarboard-fill text-danger"></i>
        <span>หลักสูตรวิชาชีพ ประจำปีการศึกษา <?= htmlspecialchars($active_year) ?></span>
      </div>
      <h1 class="programs-hero-title">สาขาวิชาและหลักสูตรที่เปิดรับสมัคร</h1>
      <p class="programs-hero-subtitle">
        เลือกดูสาขาวิชาที่ท่านสนใจ พร้อมตรวจสอบเกณฑ์และจำนวนที่นั่งที่เปิดรับ แล้วคลิกปุ่ม <strong>"สมัครเรียนสาขานี้"</strong> เพื่อเริ่มกรอกใบสมัครได้ทันที
      </p>
    </div>
  </section>

  <!-- Main Content & Filters -->
  <main class="container my-4">
    
    <!-- Search & Filter Card -->
    <div class="filter-card mb-4" data-aos="fade-up" data-aos-duration="600">
      <div class="row g-3 align-items-center">
        <!-- Text Search -->
        <div class="col-lg-5 col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-secondary">
              <i class="bi bi-search"></i>
            </span>
            <input type="text" id="searchInput" class="form-control border-start-0 ps-1" placeholder="พิมพ์ค้นหาชื่อสาขา, รหัสสาขา, หรือภาควิชา..." autocomplete="off">
          </div>
        </div>

        <!-- Department Select Filter -->
        <div class="col-lg-7 col-md-6 d-flex align-items-center justify-content-md-end gap-2">
          <select id="deptSelect" class="form-select form-select-sm" style="max-width: 280px;">
            <option value="">-- ทุกภาควิชา (All Departments) --</option>
            <?php foreach ($departments_list as $d_id => $d_name): ?>
              <option value="<?= (int)$d_id ?>"><?= htmlspecialchars($d_name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Level Filter Buttons -->
        <div class="col-12 pt-2 border-top">
          <div class="filter-buttons-wrap d-flex flex-wrap align-items-center gap-2">
            <span class="text-secondary small fw-semibold me-2 d-none d-sm-inline">
              <i class="bi bi-funnel-fill text-danger me-1"></i> เลือกระดับ:
            </span>
            <button type="button" class="filter-btn active" data-filter="all">
              <span>ทั้งหมด</span>
              <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= $count_all ?></span>
            </button>
            <button type="button" class="filter-btn" data-filter="1">
              <span class="text-danger"><i class="bi bi-circle-fill" style="font-size: 8px;"></i></span>
              <span>ระดับ ปวช.</span>
              <span class="badge bg-danger-subtle text-danger rounded-pill"><?= $count_pvc ?></span>
            </button>
            <button type="button" class="filter-btn" data-filter="2">
              <span class="text-primary"><i class="bi bi-circle-fill" style="font-size: 8px;"></i></span>
              <span>ระดับ ปวส.</span>
              <span class="badge bg-primary-subtle text-primary rounded-pill"><?= $count_pvs ?></span>
            </button>
            <button type="button" class="filter-btn" data-filter="3">
              <span class="text-purple" style="color: #7C3AED;"><i class="bi bi-circle-fill" style="font-size: 8px;"></i></span>
              <span>ระดับ ปริญญาตรี (ทล.บ.)</span>
              <span class="badge rounded-pill" style="background-color: #EDE9FE; color: #6D28D9;"><?= $count_deg ?></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Level Jump Bar -->
    <div class="d-flex align-items-center flex-wrap gap-2 mb-3 px-1">
      <span class="text-secondary small fw-semibold">
        <i class="bi bi-bookmarks-fill text-danger me-1"></i> กระโดดไปดูระดับ:
      </span>
      <a href="#sec-pvc" class="badge text-decoration-none px-3 py-2 rounded-pill bg-danger-subtle text-danger border border-danger-subtle">
        <i class="bi bi-arrow-down-circle me-1"></i> ระดับ ปวช. (<?= $count_pvc ?> สาขา)
      </a>
      <a href="#sec-pvs" class="badge text-decoration-none px-3 py-2 rounded-pill bg-primary-subtle text-primary border border-primary-subtle">
        <i class="bi bi-arrow-down-circle me-1"></i> ระดับ ปวส. (<?= $count_pvs ?> สาขา)
      </a>
      <a href="#sec-deg" class="badge text-decoration-none px-3 py-2 rounded-pill border" style="background-color: #EDE9FE; color: #6D28D9; border-color: #DDD6FE !important;">
        <i class="bi bi-arrow-down-circle me-1"></i> ระดับ ปริญญาตรี ทล.บ. (<?= $count_deg ?> สาขา)
      </a>
    </div>

    <!-- Active Filter Summary -->
    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
      <div class="text-secondary small">
        กำลังแสดงผล: <strong id="visibleCount" class="text-dark"><?= count($programs) ?></strong> สาขาวิชา
      </div>
      <a href="register.php" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-pencil-square me-1"></i> ไปที่หน้ากรอกใบสมัครโดยตรง &rarr;
      </a>
    </div>

    <!-- Programs Grid -->
    <div class="row g-4" id="programsGrid">
      <?php if (!empty($programs)): ?>
        <?php 
        $last_level = 0;
        foreach ($programs as $index => $p): 
          $curr_level = (int)$p['level_id'];
          if ($curr_level !== $last_level):
            $last_level = $curr_level;
            $level_titles = [
              1 => ['title' => 'ระดับประกาศนียบัตรวิชาชีพ (ปวช.)', 'sub' => 'หลักสูตร 3 ปี สำหรับผู้สำเร็จการศึกษาระดับ ม.3 หรือเทียบเท่า', 'class' => 'danger', 'id' => 'sec-pvc', 'color' => '#8B1E2B', 'bg' => '#FDF2F2', 'border' => '#FECACA'],
              2 => ['title' => 'ระดับประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.)', 'sub' => 'หลักสูตร 2 ปี สำหรับผู้สำเร็จการศึกษาระดับ ปวช.3 หรือ ม.6 หรือเทียบเท่า', 'class' => 'primary', 'id' => 'sec-pvs', 'color' => '#1B365D', 'bg' => '#EBF2FA', 'border' => '#BFDBFE'],
              3 => ['title' => 'ระดับปริญญาตรี (หลักสูตรเทคโนโลยีบัณฑิต ทล.บ.)', 'sub' => 'หลักสูตรต่อเนื่อง 2 ปี สำหรับผู้สำเร็จการศึกษาระดับ ปวส. หรือเทียบเท่า', 'class' => 'purple', 'id' => 'sec-deg', 'color' => '#6D28D9', 'bg' => '#EDE9FE', 'border' => '#DDD6FE'],
            ];
            $cfg = $level_titles[$curr_level] ?? ['title' => 'สาขาวิชา', 'sub' => '', 'id' => '', 'color' => '#475569', 'bg' => '#f8fafc', 'border' => '#e2e8f0'];
        ?>
          <div class="col-12 level-section-header mt-4 mb-2" data-section-level="<?= $curr_level ?>" id="<?= $cfg['id'] ?>">
            <div class="p-3 rounded-3 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2" style="background-color: <?= $cfg['bg'] ?>; border: 1.5px solid <?= $cfg['border'] ?>;">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem; background: <?= $cfg['color'] ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
                  <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div>
                  <h3 class="mb-0 fw-bold" style="color: <?= $cfg['color'] ?>; font-size: 1.25rem;">
                    <?= $cfg['title'] ?>
                  </h3>
                  <div class="small text-muted"><?= $cfg['sub'] ?></div>
                </div>
              </div>
              <div>
                <span class="badge rounded-pill px-3 py-2 fs-6 fw-bold shadow-sm" style="background-color: #ffffff; color: <?= $cfg['color'] ?>; border: 1px solid <?= $cfg['border'] ?>;">
                  <?= $curr_level === 1 ? $count_pvc : ($curr_level === 2 ? $count_pvs : $count_deg) ?> สาขาวิชา
                </span>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <?php
          $level_class = 'level-' . (int)$p['level_id'];
        ?>
          <div class="col-12 col-md-6 col-lg-4 program-item"
               data-level="<?= (int)$p['level_id'] ?>"
               data-dept="<?= (int)$p['dept_id'] ?>"
               data-search="<?= htmlspecialchars(mb_strtolower($p['program_code'] . ' ' . $p['major_name'] . ' ' . $p['dept_name'] . ' ' . $p['level_name'])) ?>">
            <div class="program-card <?= $level_class ?>">
              
              <!-- Card Top: Level & Department -->
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <span class="level-tag <?= $level_class ?>">
                  <i class="bi bi-mortarboard-fill"></i> <?= htmlspecialchars($p['level_name']) ?>
                </span>
                <span class="dept-badge">
                  <i class="bi bi-building"></i> <?= htmlspecialchars($p['dept_name']) ?>
                </span>
              </div>

              <!-- Program Code & Curriculum Type -->
              <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                <span class="program-code-badge">รหัส <?= htmlspecialchars($p['program_code']) ?></span>
                <?php if (($p['program_type'] ?? '') === 'ทวิภาคี'): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size: 0.725rem;"><i class="bi bi-briefcase me-1"></i>ระบบทวิภาคี</span>
                <?php elseif (($p['program_type'] ?? '') === 'ปกติและทวิภาคี'): ?>
                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-1" style="font-size: 0.725rem;"><i class="bi bi-arrow-repeat me-1"></i>ปกติ & ทวิภาคี</span>
                <?php else: ?>
                  <span class="badge bg-light text-secondary border rounded-pill px-2 py-1" style="font-size: 0.725rem;"><i class="bi bi-building me-1"></i>ระบบปกติ</span>
                <?php endif; ?>
              </div>

              <!-- Program Title -->
              <h2 class="program-title"><?= htmlspecialchars($p['major_name']) ?></h2>

              <!-- Description -->
              <p class="program-desc">
                <?= htmlspecialchars(!empty($p['dept_desc']) ? $p['dept_desc'] : 'หลักสูตรมุ่งเน้นเสริมสร้างทักษะวิชาชีพมาตรฐานสากล พร้อมฝึกปฏิบัติงานจริงในสถานประกอบการชั้นนำ') ?>
              </p>

              <!-- Meta: Seats & Status -->
              <div class="program-meta">
                <div class="d-flex align-items-center gap-1 text-primary fw-semibold">
                  <i class="bi bi-people-fill"></i>
                  <span>เปิดรับ <?= (int)$p['seats_available'] ?> ที่นั่ง</span>
                </div>
                <div class="badge bg-success-subtle text-success border border-success-subtle">
                  <i class="bi bi-check-circle-fill me-1"></i> เปิดรับสมัคร
                </div>
              </div>

              <!-- Action Button: Leads directly to register.php with preselected major -->
              <a href="register.php?major_id=<?= (int)$p['major_id'] ?>&level_id=<?= (int)$p['level_id'] ?>" 
                 class="btn-apply-major" 
                 title="คลิกเพื่อสมัครเรียนในสาขาวิชานี้">
                <span>สมัครเรียนสาขานี้</span>
                <i class="bi bi-arrow-right"></i>
              </a>

            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12 text-center py-5">
          <div class="text-muted mb-3"><i class="bi bi-journal-x fs-1"></i></div>
          <h4 class="text-secondary">ยังไม่มีข้อมูลสาขาวิชาที่เปิดรับสมัครในขณะนี้</h4>
          <a href="index.php" class="btn btn-admission mt-3">กลับหน้าหลัก</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- No Search Results Found Message (Hidden by default) -->
    <div id="noResultsBox" class="text-center py-5 d-none">
      <div class="text-secondary mb-3"><i class="bi bi-search fs-1 text-danger opacity-75"></i></div>
      <h4 class="fw-bold text-dark">ไม่พบสาขาวิชาที่ตรงกับคำค้นหา</h4>
      <p class="text-secondary">กรุณาลองพิมพ์คำค้นหาใหม่ หรือกดปุ่ม "ทั้งหมด" เพื่อรีเซ็ตตัวกรอง</p>
      <button type="button" class="btn btn-outline-danger mt-2" onclick="resetFilters()">
        <i class="bi bi-arrow-clockwise me-1"></i> แสดงสาขาวิชาทั้งหมด
      </button>
    </div>

  </main>

  <!-- Footer -->
  <footer>
    <div class="container">
      <div class="row g-4 text-start">
        <div class="col-lg-5">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="img/Logo-W.png" alt="ตราสัญลักษณ์" style="width: 44px; height: 44px; object-fit: contain;">
            <h5 class="mb-0 text-white"><?= htmlspecialchars($hp['footer_org_name']) ?></h5>
          </div>
          <p class="small mb-2 text-light opacity-75">
            <?= htmlspecialchars($hp['footer_org_en']) ?><br>
            <?= htmlspecialchars($hp['footer_org_desc']) ?>
          </p>
          <div class="small opacity-75">
            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($hp['footer_address']) ?>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <h6 class="text-white fw-bold mb-3">ลิงก์ด่วน</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
            <li><a href="index.php"><i class="bi bi-chevron-right text-danger me-1"></i> หน้าแรก</a></li>
            <li><a href="programs.php"><i class="bi bi-chevron-right text-danger me-1"></i> สาขาวิชาที่เปิดรับ</a></li>
            <li><a href="register.php"><i class="bi bi-chevron-right text-danger me-1"></i> สมัครเรียนออนไลน์</a></li>
            <li><a href="status.php"><i class="bi bi-chevron-right text-danger me-1"></i> ตรวจสอบสถานะการสมัคร</a></li>
            <li><a href="print_application.php"><i class="bi bi-chevron-right text-danger me-1"></i> พิมพ์ใบสมัคร / แนบเอกสาร</a></li>
          </ul>
        </div>

        <div class="col-sm-6 col-lg-4">
          <h6 class="text-white fw-bold mb-3">ติดต่อสอบถามงานรับสมัคร</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2 opacity-75">
            <li><i class="bi bi-telephone-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_phone']) ?></li>
            <li><i class="bi bi-envelope-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_email']) ?></li>
            <li><i class="bi bi-clock-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_hours']) ?></li>
          </ul>
        </div>
      </div>

      <hr style="border-color: rgba(255,255,255,0.12); margin: 30px 0;">
      <div class="text-center small opacity-75">
        <?= htmlspecialchars($hp['footer_copyright']) ?> | ออกแบบและพัฒนาโดย <?= htmlspecialchars($hp['footer_developer']) ?>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- AOS Animation JS -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

  <script>
    // 1. Preloader Control
    function hidePreloader() {
      const loader = document.getElementById('preloader');
      if (loader && !loader.classList.contains('fade-out')) {
        loader.classList.add('fade-out');
        setTimeout(() => {
          if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
          }
        }, 400);
      }
    }

    window.addEventListener('load', hidePreloader);
    // Fail-safe hide after 900ms so the user is never kept waiting
    setTimeout(hidePreloader, 900);

    document.addEventListener('DOMContentLoaded', function () {
      // AOS for static page banner only
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 500,
          easing: 'ease-out-cubic',
          once: true,
          offset: 40
        });
      }

      const searchInput = document.getElementById('searchInput');
      const deptSelect = document.getElementById('deptSelect');
      const filterBtns = document.querySelectorAll('.filter-btn');
      const programItems = document.querySelectorAll('.program-item');
      const visibleCountEl = document.getElementById('visibleCount');
      const noResultsBox = document.getElementById('noResultsBox');

      // Pre-cache item data into memory for 0ms filter performance
      const itemsData = [];
      programItems.forEach(item => {
        itemsData.push({
          el: item,
          level: item.getAttribute('data-level'),
          dept: item.getAttribute('data-dept'),
          search: item.getAttribute('data-search') || ''
        });
      });

      const headerElements = [];
      document.querySelectorAll('.level-section-header').forEach(hdr => {
        headerElements.push({
          el: hdr,
          level: hdr.getAttribute('data-section-level')
        });
      });

      let currentLevelFilter = 'all';

      // Instant Synchronous Filter function (runs in < 0.2ms)
      function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const selectedDept = deptSelect.value;
        let visibleCount = 0;
        const levelCounts = { '1': 0, '2': 0, '3': 0 };

        for (let i = 0; i < itemsData.length; i++) {
          const item = itemsData[i];
          const matchLevel = (currentLevelFilter === 'all' || item.level === currentLevelFilter);
          const matchDept  = (!selectedDept || item.dept === selectedDept);
          const matchQuery = (!query || item.search.indexOf(query) !== -1);

          if (matchLevel && matchDept && matchQuery) {
            item.el.style.display = '';
            visibleCount++;
            levelCounts[item.level] = (levelCounts[item.level] || 0) + 1;
          } else {
            item.el.style.display = 'none';
          }
        }

        visibleCountEl.textContent = visibleCount;

        // Instantly update Level Section Headers using pre-counted totals
        for (let j = 0; j < headerElements.length; j++) {
          const hdr = headerElements[j];
          const count = levelCounts[hdr.level] || 0;
          if (count > 0 && (currentLevelFilter === 'all' || currentLevelFilter === hdr.level)) {
            hdr.el.style.display = '';
          } else {
            hdr.el.style.display = 'none';
          }
        }

        if (visibleCount === 0) {
          noResultsBox.classList.remove('d-none');
        } else {
          noResultsBox.classList.add('d-none');
        }
      }

      // Filter by Level Buttons (Instant Event)
      filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
          filterBtns.forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          currentLevelFilter = this.getAttribute('data-filter');
          applyFilters();
        });
      });

      // Filter by Department Dropdown (Instant Event)
      deptSelect.addEventListener('change', applyFilters);

      // Search Input (Instant Event)
      searchInput.addEventListener('input', applyFilters);

      // Smooth Jump Links for #sec-
      document.querySelectorAll('a[href^="#sec-"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('href').substring(1);
          const targetEl = document.getElementById(targetId);
          if (targetEl) {
            const secLevel = targetEl.getAttribute('data-section-level');
            if (currentLevelFilter !== 'all' && currentLevelFilter !== secLevel) {
              filterBtns.forEach(b => b.classList.remove('active'));
              document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
              currentLevelFilter = 'all';
              applyFilters();
            }
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });

      window.resetFilters = function() {
        searchInput.value = '';
        deptSelect.value = '';
        filterBtns.forEach(b => b.classList.remove('active'));
        document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
        currentLevelFilter = 'all';
        applyFilters();
      };
    });
  </script>
</body>
</html>

