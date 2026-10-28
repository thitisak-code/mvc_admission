<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/homepage_settings.php';
require_once __DIR__ . '/config/round_helper.php';
$hp = getHomepageSettings($pdo);
$active_rounds = getActiveAdmissionRounds($pdo);

$is_logged_in    = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_type       = $_SESSION['user_type'] ?? '';
$user_name       = $_SESSION['user_name'] ?? '';
$user_role       = $_SESSION['role'] ?? '';
$user_citizen_id = $_SESSION['citizen_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($hp['navbar_title']) ?> | <?= htmlspecialchars($hp['navbar_subtitle']) ?></title>
  <meta name="description" content="<?= htmlspecialchars($hp['hero_description']) ?>">

  <!-- Bootstrap 5.3.3 & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Google Fonts: Sarabun -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Favicon Logo -->
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($hp['site_logo']) ?>">
  <link rel="shortcut icon" href="favicon.ico">

  <!-- AOS Animation CSS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <style>
    /* ==========================================================================
       Design System: Red Identity (เน้นสีแดงสถาบัน) + Navy Structural Contrast
       WCAG AA / AAA Accessibility Compliant
       ========================================================================== */
    :root {
      /* Primary: Heritage Crimson (สีแดงหลักสถาบัน MVC) */
      --color-primary: #8B1E2B;          /* สีแดงเลือดหมูภูมิฐาน (Contrast 8.1:1 AAA บนขาว) */
      --color-primary-deep: #6F1420;     /* แดงเข้มสำหรับ Header, Topbar, Hover */
      --color-primary-hover: #580E18;    /* สถานะกด/Hover สีแดงเข้ม */
      --color-primary-light: #B22B3B;    /* แดงทับทิมสำหรับไฮไลท์ */
      --color-primary-tint: #FDF2F2;     /* พื้นหลังสีแดงอ่อน นุ่มตา สำหรับ Badge, Card */
      --color-primary-border: #FECACA;   /* เส้นขอบสีแดงอ่อน */
      --color-primary-contrast: #FFFFFF; /* สีข้อความบนพื้นแดง */

      /* Secondary: Institutional Navy (สีน้ำเงินเข้มสำหรับโครงสร้างและการตัดกันอย่างลงตัว) */
      --color-secondary: #1B365D;        /* สีน้ำเงินกรมท่าสำหรับข้อความหัวข้อ/โครงสร้าง */
      --color-secondary-deep: #13294B;   /* น้ำเงินเข้มสำหรับ Footer */
      --color-secondary-tint: #EBF2FA;   /* พื้นหลังสีฟ้าอ่อน */

      /* Neutrals & Background (60% Dominant Clean Space) */
      --color-bg: #F8FAFC;               /* พื้นหลังหลักนุ่มสบายตา (Slate 50) */
      --color-surface: #FFFFFF;          /* พื้นหลัง Card / Section */
      --color-text-primary: #0F172A;     /* สีตัวอักษรหลัก (Slate 900) - Contrast 15.9:1 */
      --color-text-secondary: #475569;   /* สีตัวอักษรรอง (Slate 600) - Contrast 5.7:1 */
      --color-border-light: #E2E8F0;     /* เส้นแบ่งส่วน / ขอบกล่อง */
      --color-border-dark: #CBD5E1;      /* เส้นขอบ Input Field */

      /* Semantic Status Colors */
      --color-success: #166534;
      --color-warning: #9A3412;
      --color-error: #991B1B;

      /* Shapes & Shadows */
      --radius-sm: 6px;
      --radius-md: 12px;
      --radius-lg: 20px;
      --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.04);
      --shadow-md: 0 8px 24px rgba(139, 30, 43, 0.08);
      --shadow-lg: 0 16px 36px rgba(139, 30, 43, 0.14);
    }

    /* Base Reset */
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

    /* Accessibility Focus Visible */
    a:focus-visible, button:focus-visible {
      outline: 3px solid var(--color-primary);
      outline-offset: 3px;
    }

    /* ==========================================================================
       Topbar (เน้นสีแดงสถาบัน Deep Crimson #6F1420)
       ========================================================================== */
    .topbar {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      font-size: 0.875rem;
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

    .topbar-badge {
      background-color: rgba(255, 255, 255, 0.2);
      color: #FFFFFF;
      padding: 3px 12px;
      border-radius: 20px;
      font-size: 0.775rem;
      font-weight: 600;
      letter-spacing: 0.3px;
    }

    /* ==========================================================================
       Navbar
       ========================================================================== */
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
      transition: transform 0.25s ease;
    }

    .brand-logo-img:hover {
      transform: scale(1.06);
    }

    .brand-logo-icon {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
      color: #FFFFFF;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 1.15rem;
      box-shadow: 0 4px 12px rgba(139, 30, 43, 0.3);
    }

    .navbar .nav-link {
      color: var(--color-text-secondary);
      font-weight: 500;
      font-size: 0.975rem;
      padding: 0.5rem 0.85rem;
      transition: color 0.25s ease;
      position: relative;
      background: transparent !important;
      border: none !important;
    }

    /* ขีดเส้นใต้เป็น Animation เมื่อชี้เมาส์ (เอากรอบออก) */
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
      background-color: transparent !important; /* เอากรอบสี่เหลี่ยมออก */
    }

    .navbar .nav-link:hover::after,
    .navbar .nav-link.active::after {
      transform: scaleX(1); /* แสดงขีดเส้นใต้พร้อม Animation */
    }

    .navbar .nav-link.active {
      color: var(--color-primary);
      font-weight: 700;
      background-color: transparent !important;
    }

    /* ==========================================================================
       Hero Section
       ========================================================================== */
    .hero {
      background: linear-gradient(180deg, #FFFFFF 0%, #FFF5F5 60%, #F8FAFC 100%);
      padding: 65px 0 75px;
      position: relative;
      overflow: hidden;
      border-bottom: 1px solid var(--color-border-light);
    }

    .hero::before {
      content: '';
      position: absolute;
      top: -100px;
      right: -60px;
      width: 480px;
      height: 480px;
      background: radial-gradient(circle, rgba(139, 30, 43, 0.06) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      border: 1px solid var(--color-primary-border);
      border-radius: 30px;
      padding: 6px 16px;
      font-size: 0.875rem;
      font-weight: 600;
      max-width: 100%;
    }

    .status-dot {
      width: 9px;
      height: 9px;
      flex-shrink: 0;
      background-color: var(--color-primary);
      border-radius: 50%;
      box-shadow: 0 0 0 3px rgba(139, 30, 43, 0.25);
      animation: pulse-dot 2s infinite;
    }

    @keyframes pulse-dot {
      0% { box-shadow: 0 0 0 0 rgba(139, 30, 43, 0.5); }
      70% { box-shadow: 0 0 0 8px rgba(139, 30, 43, 0); }
      100% { box-shadow: 0 0 0 0 rgba(139, 30, 43, 0); }
    }

    .hero-title {
      color: var(--color-secondary-deep);
      font-weight: 800;
      line-height: 1.35;
      font-size: clamp(1.65rem, 1.2rem + 2.2vw, 2.75rem);
      letter-spacing: -0.5px;
      word-break: break-word;
    }

    .hero-title .highlight {
      color: var(--color-primary);
      position: relative;
      display: inline-block;
    }

    .hero-subtitle {
      color: var(--color-primary);
      font-weight: 700;
      font-size: clamp(1.05rem, 0.95rem + 0.5vw, 1.25rem);
    }

    .hero-description {
      color: var(--color-text-secondary);
      font-size: clamp(0.95rem, 0.9rem + 0.25vw, 1.05rem);
      line-height: 1.7;
    }

    /* Red Badges for Education Levels (ปวช. / ปวส. / ปริญญาตรี) */
    .badge-level {
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      border: 1px solid var(--color-primary-border);
      border-radius: 30px;
      padding: 6px 14px;
      font-size: 0.875rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s ease;
    }

    .badge-level:hover {
      background-color: var(--color-primary);
      color: #FFFFFF;
      transform: translateY(-1px);
    }

    /* Buttons */
    .btn-admission {
      background-color: var(--color-primary);
      color: var(--color-primary-contrast);
      border: 1px solid var(--color-primary);
      border-radius: var(--radius-md);
      padding: 13px 28px;
      font-weight: 700;
      font-size: 1.05rem;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 4px 16px rgba(139, 30, 43, 0.35);
      text-decoration: none;
    }

    .btn-admission:hover {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      border-color: var(--color-primary-deep);
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.45);
    }

    .btn-outline-red {
      background-color: transparent;
      color: var(--color-primary);
      border: 1.5px solid var(--color-primary);
      border-radius: var(--radius-md);
      padding: 12px 24px;
      font-weight: 600;
      font-size: 1.05rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-outline-red:hover {
      background-color: var(--color-primary-tint);
      color: var(--color-primary-deep);
      border-color: var(--color-primary-deep);
    }

    /* Citizen ID Quick Search Box */
    .citizen-search-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-primary-border);
      border-left: 5px solid var(--color-primary);
      border-radius: var(--radius-md);
      padding: 18px 20px;
      box-shadow: 0 6px 18px rgba(139, 30, 43, 0.08);
      position: relative;
      transition: all 0.3s ease;
    }

    .citizen-search-card:hover {
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.12);
    }

    .search-label {
      font-size: 0.95rem;
      color: var(--color-secondary-deep);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .search-input-group .form-control {
      height: 48px;
      font-size: 0.95rem;
      border-color: var(--color-border-dark);
      transition: all 0.2s ease;
    }

    .search-input-group .input-group-text {
      border-color: var(--color-border-dark);
      padding-left: 14px;
      padding-right: 12px;
    }

    .search-input-group .form-control:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(139, 30, 43, 0.18);
      outline: none;
    }

    .search-input-group:focus-within .input-group-text {
      border-color: var(--color-primary);
      color: var(--color-primary);
    }

    .btn-search-citizen {
      background-color: var(--color-primary);
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.95rem;
      padding: 0 22px;
      border: 1px solid var(--color-primary);
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
    }

    .btn-search-citizen:hover {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      border-color: var(--color-primary-deep);
    }

    @keyframes pulse-search-box {
      0% { box-shadow: 0 0 0 0 rgba(139, 30, 43, 0.5); }
      50% { box-shadow: 0 0 0 10px rgba(139, 30, 43, 0.2); }
      100% { box-shadow: 0 0 0 0 rgba(139, 30, 43, 0); }
    }

    .animate-highlight {
      animation: pulse-search-box 1.2s ease-in-out;
      border-color: var(--color-primary) !important;
    }

    /* Hero Media Container - ปรับให้รูปพอดีกับกรอบอัตโนมัติ โดยไม่เสียสมดุล */
    .hero-image-wrapper {
      position: relative;
      width: 100%;
    }

    .hero-image {
      width: 100%;
      height: auto;
      aspect-ratio: 16 / 10;
      max-height: 440px;
      object-fit: cover;
      object-position: center;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      border: 4px solid var(--color-surface);
      background: linear-gradient(135deg, rgba(139, 30, 43, 0.05), rgba(27, 54, 93, 0.08));
      display: block;
      transition: transform 0.35s ease;
    }

    .hero-image:hover {
      transform: scale(1.015);
    }

    .hero-floating-card {
      position: absolute;
      bottom: -20px;
      left: 20px;
      background: var(--color-surface);
      border: 1px solid var(--color-primary-border);
      border-left: 4px solid var(--color-primary);
      border-radius: var(--radius-md);
      padding: 14px 18px;
      box-shadow: var(--shadow-md);
      display: flex;
      align-items: center;
      gap: 12px;
      max-width: 290px;
    }

    .floating-card-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: var(--color-primary-tint);
      color: var(--color-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
    }

    /* ==========================================================================
       Services Section
       ========================================================================== */
    .service-section {
      background-color: var(--color-bg);
      padding: 70px 0 85px;
    }

    .section-eyebrow {
      color: var(--color-primary);
      font-weight: 700;
      font-size: 0.875rem;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }

    .section-title {
      color: var(--color-secondary-deep);
      font-weight: 800;
      font-size: clamp(1.45rem, 1.2rem + 1.2vw, 2rem);
    }

    .service-card {
      background-color: var(--color-surface);
      height: 100%;
      border: 1px solid var(--color-border-light);
      border-radius: var(--radius-md);
      padding: 32px 24px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      display: flex;
      flex-direction: column;
    }

    /* การ์ดแรกเน้นพิเศษด้วยแถบสีแดง */
    .service-card.card-featured {
      border-top: 3px solid var(--color-primary);
    }

    .service-card:hover {
      transform: translateY(-6px);
      border-color: var(--color-primary-border);
      box-shadow: var(--shadow-md);
    }

    .service-icon-box {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: 20px;
      transition: all 0.25s ease;
    }

    .service-card:hover .service-icon-box {
      background-color: var(--color-primary);
      color: #FFFFFF;
      transform: scale(1.08);
    }

    .service-card h5 {
      color: var(--color-secondary-deep);
      font-weight: 700;
      font-size: 1.2rem;
      margin-bottom: 10px;
    }

    .service-card p {
      color: var(--color-text-secondary);
      font-size: 0.95rem;
      margin-bottom: 22px;
      flex-grow: 1;
    }

    .service-link {
      color: var(--color-primary);
      text-decoration: none;
      font-weight: 700;
      font-size: 0.95rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: gap 0.2s ease, color 0.2s ease;
    }

    .service-link:hover {
      color: var(--color-primary-deep);
      gap: 10px;
    }

    /* Notice Banner */
    .notice-box {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border-light);
      border-left: 5px solid var(--color-primary);
      border-radius: var(--radius-md);
      padding: 22px 26px;
      margin-top: 45px;
      box-shadow: var(--shadow-sm);
    }

    /* ==========================================================================
       Footer (Deep Navy & Red Header Border)
       ========================================================================== */
    footer {
      background-color: var(--color-secondary-deep);
      color: #CBD5E1;
      padding: 55px 0 35px;
      border-top: 4px solid var(--color-primary);
    }

    footer h5 {
      color: #FFFFFF;
      font-weight: 700;
      font-size: 1.15rem;
    }

    footer a {
      color: #94A3B8;
      text-decoration: none;
      transition: color 0.2s ease;
      position: relative;
      display: inline-block;
    }

    footer a::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      width: 100%;
      height: 1.5px;
      background-color: #FFFFFF;
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.25s ease;
    }

    footer a:hover {
      color: #FFFFFF;
    }

    footer a:hover::after {
      transform: scaleX(1);
    }

    footer .footer-divider {
      border-color: rgba(255, 255, 255, 0.12);
      margin: 30px 0;
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
      .hero {
        padding: 40px 0 55px;
      }
      .hero-image {
        height: auto;
        aspect-ratio: 16 / 10;
        margin-top: 10px;
      }
      .hero-floating-card {
        bottom: -15px;
        left: 14px;
        font-size: 0.85rem;
      }
      .navbar-collapse {
        padding-top: 12px;
        padding-bottom: 8px;
      }
      .navbar .nav-link {
        padding: 0.6rem 0.75rem;
      }
      .service-section {
        padding: 50px 0 65px;
      }
    }

    @media (max-width: 767.98px) {
      .topbar {
        font-size: 0.8rem;
      }
      .hero {
        padding: 32px 0 45px;
      }
      .citizen-search-card {
        padding: 16px;
      }
      .notice-box {
        padding: 18px 20px;
        margin-top: 32px;
      }
      footer {
        padding: 40px 0 28px;
      }
    }

    @media (max-width: 575.98px) {
      .brand-logo-img {
        width: 40px;
        height: 40px;
      }
      .hero {
        padding: 24px 0 36px;
      }
      .status-badge {
        font-size: 0.78rem;
        padding: 5px 12px;
        border-radius: 16px;
      }
      .hero-cta-group {
        flex-direction: column;
        width: 100%;
      }
      .hero-cta-group .btn-admission,
      .hero-cta-group .btn-outline-red {
        width: 100%;
        justify-content: center;
        padding: 11px 20px;
        font-size: 0.98rem;
      }
      .badge-level {
        font-size: 0.8rem;
        padding: 5px 12px;
      }
      .citizen-search-card {
        padding: 14px;
      }
      .search-input-group .form-control {
        height: 44px;
        font-size: 0.88rem;
        min-width: 0;
      }
      .btn-search-citizen {
        padding: 0 14px;
        font-size: 0.88rem;
      }
      .hero-floating-card {
        position: relative;
        bottom: auto;
        left: auto;
        max-width: 100%;
        margin-top: 12px;
        padding: 12px 14px;
      }
      .service-section {
        padding: 38px 0 48px;
      }
      .service-card {
        padding: 22px 18px;
      }
      .notice-box {
        padding: 16px;
      }
      .notice-box .btn-outline-red {
        width: 100%;
        justify-content: center;
      }
      .admin-quick-edit-btn {
        bottom: 16px;
        right: 16px;
      }
    }

    /* Thumbnail Box สำหรับบล็อกบริการที่มีการอัปโหลดรูปภาพ - ปรับให้พอดีกรอบอัตโนมัติ */
    .service-img-box {
      width: 100%;
      aspect-ratio: 16 / 9;
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 20px;
      background-color: #F8FAFC;
      border: 1px solid var(--color-border-light);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .service-img-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      display: block;
      transition: transform 0.35s ease;
    }
    .service-card:hover .service-img-box img {
      transform: scale(1.06);
    }

    /* ปุ่มลอยเข้าสู่โหมดปรับแต่งหน้าแรกสำหรับผู้ดูแลระบบ */
    .admin-quick-edit-btn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 1050;
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .admin-quick-edit-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(139, 30, 43, 0.45);
    }
  </style>
</head>

<body>

  <!-- Top Announcement Bar (เน้นสีแดงสถาบัน Deep Crimson #6F1420) -->
  <header class="topbar py-2">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt-fill text-warning"></i>
        <span><?= htmlspecialchars($hp['topbar_address']) ?></span>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span><i class="bi bi-telephone-fill me-1"></i> โทร. <?= htmlspecialchars($hp['topbar_phone']) ?></span>
        <span class="d-none d-md-inline opacity-50">|</span>
        <span class="topbar-badge d-none d-md-inline-block">
          <i class="bi bi-mortarboard-fill me-1"></i> <?= htmlspecialchars($hp['topbar_badge']) ?>
        </span>
      </div>
    </div>
  </header>

  <!-- Navigation Bar (Clean White Surface with Red Brand Identity) -->
  <nav class="navbar navbar-expand-lg sticky-top" aria-label="เมนูหลัก">
    <div class="container">
      <a class="navbar-brand brand-title" href="#">
        <img src="<?= htmlspecialchars($hp['site_logo']) ?>" alt="ตราสัญลักษณ์" class="brand-logo-img">
        <div>
          <div style="line-height: 1.15;"><?= htmlspecialchars($hp['navbar_title']) ?></div>
          <small style="font-size: 0.725rem; font-weight: 600; color: var(--color-primary);">
            <?= htmlspecialchars($hp['navbar_subtitle']) ?>
          </small>
        </div>
      </a>

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse"
              data-bs-target="#mainMenu"
              aria-controls="mainMenu"
              aria-expanded="false"
              aria-label="เปิด/ปิด เมนูนำทาง">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainMenu">
        <ul class="navbar-nav ms-auto gap-lg-1 align-items-lg-center">
          <li class="nav-item">
            <a class="nav-link active" href="index.php"><i class="bi bi-house-door me-1"></i> หน้าแรก</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="register.php"><i class="bi bi-pencil-square me-1"></i> สมัครเรียนออนไลน์</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="status.php"><i class="bi bi-search me-1"></i> ตรวจสอบสถานะ</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="print_application.php"><i class="bi bi-printer me-1"></i> พิมพ์ใบสมัคร/เอกสาร</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contact">ติดต่อเรา</a>
          </li>
          <?php if ($is_logged_in): ?>
            <li class="nav-item dropdown ms-lg-2 mt-2 mt-lg-0">
              <button class="btn btn-danger dropdown-toggle py-2 px-3 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.9rem; background-color: var(--color-primary); border-color: var(--color-primary); border-radius: 8px;">
                <?php if ($user_type === 'admin'): ?>
                  <i class="bi bi-shield-lock-fill"></i>
                  <span>ผู้ดูแลระบบ: <?= htmlspecialchars($user_name) ?></span>
                <?php else: ?>
                  <i class="bi bi-person-circle"></i>
                  <span><?= htmlspecialchars($user_name) ?></span>
                <?php endif; ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-1">
                <?php if ($user_type === 'admin'): ?>
                  <li><a class="dropdown-item py-2" href="admin.php?tab=applicants"><i class="bi bi-people text-danger me-2"></i> จัดการผู้สมัครเรียน</a></li>
                  <li><a class="dropdown-item py-2" href="admin.php?tab=departments"><i class="bi bi-mortarboard text-warning me-2"></i> จัดการแผนกและสาขาวิชา</a></li>
                  <li><a class="dropdown-item py-2" href="admin.php?tab=rounds"><i class="bi bi-calendar2-week text-info me-2"></i> จัดการรอบการรับสมัคร</a></li>
                  <li><a class="dropdown-item py-2" href="admin.php?tab=admins"><i class="bi bi-shield-person text-primary me-2"></i> จัดการผู้ดูแลระบบ</a></li>
                  <li><a class="dropdown-item py-2 text-danger fw-semibold" href="admin.php?tab=homepage"><i class="bi bi-layout-text-window-reverse me-2"></i> ปรับแต่งหน้าแรก (Index)</a></li>
                <?php else: ?>
                  <li><a class="dropdown-item py-2" href="status.php?citizen_id=<?= urlencode($user_citizen_id) ?>"><i class="bi bi-card-checklist text-danger me-2"></i> สถานะการสมัครของฉัน</a></li>
                  <li><a class="dropdown-item py-2" href="print_application.php?search=<?= urlencode($user_citizen_id) ?>"><i class="bi bi-printer-fill text-primary me-2"></i> พิมพ์ใบสมัคร / แนบเอกสาร</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger fw-semibold" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
              <a href="login.php" class="btn btn-outline-red py-2 px-3" style="font-size: 0.9rem;">
                <i class="bi bi-person-fill"></i> เข้าสู่ระบบ
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <main id="main-content">
    <!-- Hero Section (เน้นสีแดงสถาบัน พร้อมโครงสร้างสะอาดตา) -->
    <section class="hero" id="announcements">
      <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
          <div class="col-lg-6">
            <?php if (!empty($active_rounds)): ?>
              <div class="d-flex align-items-center flex-wrap gap-2 mb-3" data-aos="fade-down" data-aos-duration="600">
                <?php foreach ($active_rounds as $ar): ?>
                  <div class="status-badge" style="margin-bottom: 0; background: <?= $ar['round_type'] === 'quota' ? '#FEF3C7' : '#FDF2F2' ?>; border-color: <?= $ar['round_type'] === 'quota' ? '#FDE68A' : '#FECACA' ?>; color: <?= $ar['round_type'] === 'quota' ? '#92400E' : 'var(--color-primary-deep)' ?>;">
                    <span class="status-dot" style="background-color: <?= $ar['round_type'] === 'quota' ? '#D97706' : '#16A34A' ?>;"></span>
                    <strong class="me-1">
                      <?= $ar['round_type'] === 'quota' ? 'รอบโควตา' : 'รอบปกติ' ?>:
                    </strong>
                    <span><?= htmlspecialchars($ar['round_name']) ?></span>
                    <?php if (!empty($ar['end_date'])): ?>
                      <small class="opacity-75 ms-1">(ถึง <?= formatThaiDate($ar['end_date']) ?>)</small>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif (!empty($hp['hero_badge'])): ?>
              <div class="status-badge mb-3" data-aos="fade-down" data-aos-duration="600">
                <span class="status-dot"></span>
                <span><?= htmlspecialchars($hp['hero_badge']) ?></span>
              </div>
            <?php endif; ?>

            <h1 class="hero-title mb-3" data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
              <?= htmlspecialchars($hp['hero_title']) ?><br>
              <span class="highlight"><?= htmlspecialchars($hp['hero_highlight']) ?></span>
            </h1>

            <h2 class="hero-subtitle mb-3" data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
              <?= htmlspecialchars($hp['hero_subtitle']) ?>
            </h2>

            <p class="hero-description mb-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="300">
              <?= nl2br(htmlspecialchars($hp['hero_description'])) ?>
            </p>

            <div class="d-flex flex-wrap gap-3 mb-4 hero-cta-group" data-aos="fade-up" data-aos-duration="700" data-aos-delay="400">
              <?php if (!empty($hp['hero_btn1_text'])): ?>
                <a href="<?= htmlspecialchars($hp['hero_btn1_link']) ?>" class="btn-admission" id="cta-register">
                  <span><?= htmlspecialchars($hp['hero_btn1_text']) ?></span>
                  <i class="bi bi-arrow-right"></i>
                </a>
              <?php endif; ?>
              <?php if (!empty($hp['hero_btn2_text'])): ?>
                <a href="<?= htmlspecialchars($hp['hero_btn2_link']) ?>" class="btn-outline-red">
                  <i class="bi bi-journal-text"></i>
                  <span><?= htmlspecialchars($hp['hero_btn2_text']) ?></span>
                </a>
              <?php endif; ?>
            </div>

            <!-- Education Level Badges: ปวช., ปวส., ปริญญาตรี -->
            <?php
              $badgeItems = array_filter(array_map('trim', explode(',', $hp['hero_badges_text'] ?? '')));
              if (!empty($badgeItems)):
            ?>
              <div class="d-flex align-items-center flex-wrap gap-2 pt-2 mb-3" data-aos="fade-up" data-aos-duration="700" data-aos-delay="500">
                <?php foreach ($badgeItems as $b): ?>
                  <span class="badge-level">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($b) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Citizen ID Quick Search Box (ช่องค้นหาเลขบัตรประชาชน) -->
            <div class="citizen-search-card mt-3" id="search-citizen-section" data-aos="fade-up" data-aos-duration="700" data-aos-delay="600">
              <form action="status.php" method="GET" class="search-form" onsubmit="return handleCitizenSearch(event)">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label for="citizen_id" class="search-label mb-0">
                    <i class="bi bi-person-vcard-fill text-danger fs-5"></i>
                    <strong>ตรวจสอบสถานะการสมัคร</strong>
                  </label>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle small fw-semibold">
                    เลขบัตร 13 หลัก
                  </span>
                </div>

                <div class="input-group search-input-group">
                  <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-secondary"></i>
                  </span>
                  <input
                    type="text"
                    id="citizen_id"
                    name="citizen_id"
                    class="form-control border-start-0 ps-1"
                    placeholder="กรอกเลขประจำตัวประชาชน 13 หลัก..."
                    maxlength="13"
                    value="<?= htmlspecialchars($user_citizen_id) ?>"
                    inputmode="numeric"
                    pattern="[0-9]{13}"
                    required
                    autocomplete="off"
                    aria-label="เลขประจำตัวประชาชน 13 หลัก"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);"
                  >
                  <button type="submit" class="btn btn-search-citizen" id="btn-search-status">
                    <span>ค้นหา</span>
                    <i class="bi bi-arrow-right-short fs-5"></i>
                  </button>
                </div>

                <div class="search-help-text mt-2 d-flex justify-content-between align-items-center flex-wrap gap-1">
                  <span class="small text-secondary">
                    <i class="bi bi-shield-check text-success me-1"></i> ตรวจสอบผลการสมัครและพิมพ์เอกสาร
                  </span>
                  <?php if ($is_logged_in): ?>
                    <?php if ($user_type === 'admin'): ?>
                      <a href="admin.php" class="small text-danger text-decoration-none fw-semibold">
                        <i class="bi bi-speedometer2 me-1"></i> ไปที่แดชบอร์ดจัดการผู้สมัคร &rarr;
                      </a>
                    <?php else: ?>
                      <a href="status.php?citizen_id=<?= urlencode($user_citizen_id) ?>" class="small text-danger text-decoration-none fw-semibold">
                        <i class="bi bi-person-check-fill me-1"></i> ดูผลการสมัครของคุณ &rarr;
                      </a>
                    <?php endif; ?>
                  <?php else: ?>
                    <a href="login.php" class="small text-danger text-decoration-none fw-semibold">
                      เข้าสู่ระบบด้วยรหัสผ่าน &rarr;
                    </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="hero-image-wrapper" data-aos="fade-left" data-aos-duration="800" data-aos-delay="250">
              <img
                class="hero-image"
                src="<?= htmlspecialchars($hp['hero_image']) ?>"
                onerror="this.onerror=null; this.src='assets/images/hero.jpg';"
                alt="<?= htmlspecialchars($hp['hero_title']) ?>">
              
              <div class="hero-floating-card" data-aos="zoom-in" data-aos-duration="600" data-aos-delay="550">
                <div class="floating-card-icon">
                  <i class="bi <?= htmlspecialchars($hp['hero_card_icon'] ?: 'bi-award-fill') ?>"></i>
                </div>
                <div>
                  <div style="font-weight: 700; color: var(--color-primary); font-size: 0.95rem;">
                    <?= htmlspecialchars($hp['hero_card_title']) ?>
                  </div>
                  <div style="font-size: 0.8rem; color: var(--color-text-secondary);">
                    <?= htmlspecialchars($hp['hero_card_desc']) ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Services / Quick Navigation Section (บล็อกบริการและขั้นตอน) -->
    <section class="service-section" id="steps">
      <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="700">
          <div class="section-eyebrow"><?= htmlspecialchars($hp['services_eyebrow']) ?></div>
          <h2 class="section-title"><?= htmlspecialchars($hp['services_title']) ?></h2>
          <p class="text-secondary mx-auto" style="max-width: 600px;">
            <?= nl2br(htmlspecialchars($hp['services_desc'])) ?>
          </p>
        </div>

        <div class="row g-4">
          <?php for ($i = 1; $i <= 4; $i++): 
            $c_title    = $hp["card{$i}_title"] ?? '';
            $c_desc     = $hp["card{$i}_desc"] ?? '';
            $c_icon     = $hp["card{$i}_icon"] ?? 'bi-app';
            $c_img      = $hp["card{$i}_image"] ?? '';
            $c_btn      = $hp["card{$i}_btn_text"] ?? '';
            $c_link     = $hp["card{$i}_link"] ?? '#';
            $c_featured = !empty($hp["card{$i}_featured"]) && $hp["card{$i}_featured"] == '1';
          ?>
            <div class="col-md-6 col-lg-3" <?= $i === 3 ? 'id="documents"' : '' ?> data-aos="fade-up" data-aos-duration="600" data-aos-delay="<?= $i * 100 ?>">
              <div class="service-card <?= $c_featured ? 'card-featured' : '' ?>">
                <?php if (!empty($c_img)): ?>
                  <div class="service-img-box">
                    <img src="<?= htmlspecialchars($c_img) ?>" alt="<?= htmlspecialchars($c_title) ?>">
                  </div>
                <?php else: ?>
                  <div class="service-icon-box">
                    <i class="bi <?= htmlspecialchars($c_icon) ?>"></i>
                  </div>
                <?php endif; ?>
                <h5><?= htmlspecialchars($c_title) ?></h5>
                <p>
                  <?= nl2br(htmlspecialchars($c_desc)) ?>
                </p>
                <?php if (!empty($c_btn)): ?>
                  <a href="<?= htmlspecialchars($c_link) ?>" class="service-link" <?= $c_link === '#citizen_id' ? 'onclick="focusCitizenSearch(event)"' : '' ?>>
                    <?= htmlspecialchars($c_btn) ?> <i class="bi bi-arrow-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Academic Assistance & Schedule Notice (บล็อกกำหนดการสำคัญ) -->
        <div class="notice-box" data-aos="fade-up" data-aos-duration="700" data-aos-delay="150">
          <div class="row align-items-center g-3">
            <div class="col-md-8">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge px-2 py-1 text-white" style="background-color: var(--color-primary) !important;">
                  <?= htmlspecialchars($hp['notice_badge']) ?>
                </span>
                <strong style="color: var(--color-primary-deep);"><?= htmlspecialchars($hp['notice_title']) ?></strong>
              </div>
              <div style="color: var(--color-text-secondary); font-size: 0.95rem;">
                <?= nl2br(htmlspecialchars($hp['notice_desc'])) ?>
              </div>
              <?php if (!empty($active_rounds)): ?>
                <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-2">
                  <?php foreach ($active_rounds as $ar): ?>
                    <div class="small px-2 py-1 rounded border d-inline-flex align-items-center gap-1" style="background: <?= $ar['round_type'] === 'quota' ? '#FFFBEB' : '#F0F9FF' ?>; border-color: <?= $ar['round_type'] === 'quota' ? '#FDE68A' : '#BAE6FD' ?> !important;">
                      <i class="bi <?= $ar['round_type'] === 'quota' ? 'bi-award-fill text-warning' : 'bi-people-fill text-primary' ?>"></i>
                      <strong class="text-dark"><?= htmlspecialchars($ar['round_name']) ?>:</strong>
                      <span class="text-secondary"><?= formatThaiDate($ar['start_date']) ?> - <?= formatThaiDate($ar['end_date']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
              <?php if (!empty($hp['notice_btn_text'])): ?>
                <a href="<?= htmlspecialchars($hp['notice_btn_link']) ?>" class="btn btn-outline-red py-2 px-3" style="font-size: 0.9rem;">
                  <i class="bi bi-question-circle"></i> <?= htmlspecialchars($hp['notice_btn_text']) ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  <!-- Footer (Deep Navy with 4px Red Accent Top Border) -->
  <footer id="contact">
    <div class="container">
      <div class="row g-4 text-start">
        <div class="col-lg-5" data-aos="fade-up" data-aos-duration="600" data-aos-delay="100">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="<?= htmlspecialchars($hp['site_logo']) ?>" alt="ตราสัญลักษณ์" style="width: 44px; height: 44px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
            <h5 class="mb-0"><?= htmlspecialchars($hp['footer_org_name']) ?></h5>
          </div>
          <p class="small mb-2" style="color: #CBD5E1 !important;">
            <?= htmlspecialchars($hp['footer_org_en']) ?><br>
            <?= htmlspecialchars($hp['footer_org_desc']) ?>
          </p>
          <div class="small" style="color: #94A3B8;">
            <i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($hp['footer_address']) ?>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-duration="600" data-aos-delay="200">
          <h6 class="text-white fw-bold mb-3">ลิงก์ด่วน</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
            <li><a href="index.php"><i class="bi bi-chevron-right text-danger me-1"></i> หน้าแรก</a></li>
            <li><a href="register.php"><i class="bi bi-chevron-right text-danger me-1"></i> สมัครเรียนออนไลน์</a></li>
            <li><a href="status.php"><i class="bi bi-chevron-right text-danger me-1"></i> ตรวจสอบสถานะการสมัคร</a></li>
            <li><a href="print_application.php"><i class="bi bi-chevron-right text-danger me-1"></i> พิมพ์ใบสมัคร / แนบเอกสาร</a></li>
            <?php if ($is_logged_in && $user_type === 'admin'): ?>
              <li><a href="admin.php?tab=homepage" class="text-warning fw-semibold"><i class="bi bi-pencil-square text-warning me-1"></i> ปรับแต่งหน้าแรก (Admin)</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="col-sm-6 col-lg-4" data-aos="fade-up" data-aos-duration="600" data-aos-delay="300">
          <h6 class="text-white fw-bold mb-3">ติดต่อสอบถามงานรับสมัคร</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2" style="color: #CBD5E1;">
            <li><i class="bi bi-telephone-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_phone']) ?></li>
            <li><i class="bi bi-envelope-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_email']) ?></li>
            <li><i class="bi bi-clock-fill text-danger me-2"></i> <?= htmlspecialchars($hp['footer_hours']) ?></li>
          </ul>
        </div>
      </div>

      <hr class="footer-divider">
      <div class="text-center small" style="color: #94A3B8 !important;">
        <?= htmlspecialchars($hp['footer_copyright']) ?>
      </div>
      <div class="text-center small" style="color: #94A3B8 !important;">
        ออกแบบและพัฒนาโดย <a href="#" class="text-white fw-bold"><?= htmlspecialchars($hp['footer_developer']) ?></a>
      </div>
    </div>
  </footer>

  <!-- Admin Quick Edit Floating Button -->
  <?php if ($is_logged_in && $user_type === 'admin'): ?>
    <a href="admin.php?tab=homepage" class="btn btn-danger btn-sm rounded-pill py-2 px-3 admin-quick-edit-btn d-inline-flex align-items-center gap-2" title="ปรับแต่งและเปลี่ยนรูปภาพหน้าแรก">
      <i class="bi bi-pencil-square"></i>
      <span class="fw-semibold">ปรับแต่งหน้าแรกนี้</span>
    </a>
  <?php endif; ?>

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- AOS Animation JS -->
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      AOS.init({
        duration: 750,
        easing: 'ease-out-cubic',
        once: true,
        offset: 50
      });
    });
  </script>

  <!-- Citizen ID Search Helper Script -->
  <script>
    function focusCitizenSearch(e) {
      if (e) e.preventDefault();
      const input = document.getElementById('citizen_id');
      const card = document.getElementById('search-citizen-section');
      if (input && card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => {
          input.focus();
          card.classList.add('animate-highlight');
          setTimeout(() => card.classList.remove('animate-highlight'), 1300);
        }, 400);
      }
    }

    function handleCitizenSearch(e) {
      const input = document.getElementById('citizen_id');
      const val = input ? input.value.trim() : '';
      if (val.length !== 13 || !/^\d{13}$/.test(val)) {
        if (e) e.preventDefault();
        alert('กรุณากรอกเลขประจำตัวประชาชนให้ครบถ้วน 13 หลัก (เฉพาะตัวเลข)');
        if (input) input.focus();
        return false;
      }
      return true;
    }
  </script>
</body>
</html>
