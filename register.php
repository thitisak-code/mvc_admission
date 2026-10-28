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

// รอบรับสมัครปัจจุบัน
$current_round = !empty($active_rounds) ? $active_rounds[0] : [
    'id' => 1,
    'round_name' => 'รอบโควตาและรอบทั่วไป ประจำปีการศึกษา ' . $active_year,
    'round_type' => 'quota',
    'academic_year' => $active_year,
    'end_date' => date('Y-12-31')
];

// ดึงข้อมูลสาขาวิชาที่เปิดรับสมัครจากตาราง programs + departments (เชื่อมกับระบบตั้งค่าใน Admin)
$stmt_majors = $pdo->prepare("
    SELECT p.id AS major_id,
           p.program_code,
           p.program_name AS major_name,
           p.education_level AS level_name,
           p.seats_available,
           d.dept_name,
           CASE p.education_level
               WHEN 'ปวช.' THEN 1
               WHEN 'ปวส.' THEN 2
               WHEN 'ปริญญาตรี' THEN 3
               ELSE 1
           END AS level_id
    FROM programs p
    JOIN departments d ON p.dept_id = d.id
    WHERE p.status = 'open' AND p.academic_year = :yr
    ORDER BY level_id ASC, d.id ASC, p.id ASC
");
$stmt_majors->execute([':yr' => $active_year]);
$majors = $stmt_majors->fetchAll(PDO::FETCH_ASSOC);

if (empty($majors)) {
    $stmt_majors_all = $pdo->query("
        SELECT p.id AS major_id,
               p.program_code,
               p.program_name AS major_name,
               p.education_level AS level_name,
               p.seats_available,
               d.dept_name,
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
    $majors = $stmt_majors_all->fetchAll(PDO::FETCH_ASSOC);
}

$is_logged_in    = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_type       = $_SESSION['user_type'] ?? '';
$user_name       = $_SESSION['user_name'] ?? '';
$user_citizen_id = $_SESSION['citizen_id'] ?? '';

$is_admin = ($is_logged_in && $user_type === 'admin') || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_applicant = ($is_logged_in && $user_type === 'applicant') || (isset($_SESSION['applicant_logged_in']) && $_SESSION['applicant_logged_in'] === true);
$logged_user_name = $is_admin ? ($user_name ?: ($_SESSION['admin_name'] ?? 'Admin')) : ($is_applicant ? ($user_name ?: ($_SESSION['applicant_name'] ?? 'ผู้สมัคร')) : 'ผู้สมัครใหม่ (Guest)');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครเรียนออนไลน์ | <?= htmlspecialchars($hp['navbar_subtitle']) ?></title>
    <!-- Bootstrap 5.3.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- FontAwesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts Thai: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Select2 CSS & Select2 Bootstrap 5 Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($hp['site_logo']) ?>">
    <link rel="shortcut icon" href="favicon.ico">
    
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
            --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.04);

            --mvc-blue: #004ea2;
            --mvc-blue-dark: #003882;
            --mvc-blue-light: #e8f1fb;
            --mvc-maroon: #8B1E2B;
            --mvc-maroon-dark: #6F1420;
            --mvc-maroon-light: #9c1c1c;
            --mvc-coral: #ea5455;
            --mvc-yellow: #ffc107;
            --mvc-teal-border: #4dd0e1;
            --mvc-teal-focus: #00acc1;
            --mvc-bg-body: #f4f6fa;
            --mvc-text-dark: #2c3e50;
        }

        html, body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--mvc-bg-body);
            color: var(--color-text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            padding: 0;
            max-width: 100%;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ==========================================================================
           Topbar & Navbar (จากหน้า index.php)
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

        .navbar {
            background-color: var(--color-surface);
            border-bottom: 2px solid var(--color-border-light);
            box-shadow: var(--shadow-sm);
            z-index: 1035;
        }

        .brand-title {
            color: var(--color-primary-deep);
            font-weight: 700;
            font-size: clamp(1.02rem, 0.9rem + 0.5vw, 1.25rem);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-img {
            width: 48px;
            height: 48px;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.08));
            transition: transform 0.25s ease;
            flex-shrink: 0;
        }

        .brand-logo-img:hover {
            transform: scale(1.06);
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
            background-color: transparent !important;
        }

        .navbar .nav-link:hover::after,
        .navbar .nav-link.active::after {
            transform: scaleX(1);
        }

        .navbar .nav-link.active {
            color: var(--color-primary);
            font-weight: 700;
            background-color: transparent !important;
        }

        .btn-outline-red {
            background-color: #FFFFFF;
            color: var(--color-primary);
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 12px;
            border: 2px solid var(--color-primary);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline-red:hover {
            background-color: var(--color-primary-tint);
            color: var(--color-primary-deep);
            border-color: var(--color-primary-deep);
        }

        .sidebar-toggle-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1.5px solid var(--color-border-light);
            background: #FFFFFF;
            color: var(--color-primary-deep);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .sidebar-toggle-btn:hover {
            background-color: var(--color-primary-tint);
            border-color: var(--color-primary-border);
            color: var(--color-primary);
        }

        /* Sidebar Backdrop for Mobile Slide Menu */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        /* Sidebar */
        .sidebar-container {
            background-color: #ffffff;
            min-height: calc(100dvh - 115px);
            border-right: 1px solid #e9ecef;
            padding: 24px 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-profile {
            text-align: center;
            margin-bottom: 25px;
        }

        .sidebar-seal-img {
            width: 96px;
            height: 96px;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            object-fit: contain;
            box-shadow: 0 4px 14px rgba(128, 0, 0, 0.12);
            margin-bottom: 12px;
            padding: 3px;
            background: white;
            border: 2px solid #eef2f6;
            transition: transform 0.25s ease;
        }

        .sidebar-seal-img:hover {
            transform: scale(1.05);
        }

        .sidebar-user-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: #333;
            margin-bottom: 2px;
        }

        .sidebar-user-status {
            color: #8c98a4;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .quick-action-box {
            display: flex;
            justify-content: center;
            gap: 10px;
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #edf0f2;
            margin-bottom: 25px;
        }

        .quick-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .quick-action-btn:hover {
            color: var(--mvc-maroon);
            border-color: var(--mvc-maroon);
        }

        .quick-action-btn.active {
            color: var(--mvc-maroon);
            border-color: var(--mvc-maroon);
            background-color: #fdf2f2;
        }

        .sidebar-nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav-item {
            margin-bottom: 4px;
        }

        .sidebar-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: #4b5563;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .sidebar-nav-link:hover {
            color: var(--mvc-maroon);
            background-color: #fdf2f2;
        }

        .sidebar-nav-link.active {
            color: var(--mvc-maroon);
            background-color: #fdf2f2;
            font-weight: 600;
            border-left: 4px solid var(--mvc-maroon);
            border-radius: 0 6px 6px 0;
        }

        .sidebar-nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        /* Main Form Area */
        #mainFormCol {
            min-width: 0;
            max-width: 100%;
        }

        .main-content-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #edf0f5;
            padding: 30px 40px;
            margin-bottom: 40px;
            min-width: 0;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Form Card Header */
        .form-banner-header {
            display: flex;
            align-items: center;
            gap: 18px;
            border-left: 5px solid var(--mvc-maroon);
            padding-left: 18px;
            margin-bottom: 28px;
        }

        .form-banner-logo {
            height: 64px;
            width: 64px;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            object-fit: contain;
            box-shadow: 0 3px 10px rgba(128, 0, 0, 0.12);
            border: 2px solid #f1f3f5;
            background: #ffffff;
            padding: 3px;
            flex-shrink: 0;
            transition: transform 0.25s ease;
        }

        .form-banner-logo:hover {
            transform: scale(1.05);
        }

        .form-banner-text h4 {
            font-size: clamp(1.1rem, 0.95rem + 0.8vw, 1.35rem);
            font-weight: 700;
            color: var(--mvc-maroon);
            margin: 0 0 2px 0;
            line-height: 1.3;
        }

        .form-banner-text .form-banner-en {
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .form-banner-text p {
            margin: 0;
            color: #374151;
            font-size: 0.95rem;
        }

        .quota-highlight {
            color: var(--mvc-maroon);
            font-weight: 700;
        }

        /* Section Title (Maroon/Red Header matching college theme) */
        .form-section-header {
            color: var(--mvc-maroon);
            font-size: clamp(1.02rem, 0.92rem + 0.5vw, 1.2rem);
            font-weight: 700;
            margin-top: 30px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 8px;
        }

        /* Form Controls with Cyan/Teal border matching screenshots */
        .form-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--mvc-teal-border);
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 0.92rem;
            color: #334155;
            background-color: #ffffff;
            transition: all 0.2s ease;
            max-width: 100%;
            min-width: 0;
            text-overflow: ellipsis;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--mvc-teal-focus);
            box-shadow: 0 0 0 3px rgba(0, 172, 193, 0.15);
            outline: none;
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.88rem;
        }

        .form-control[readonly] {
            background-color: #f8fafc;
            color: #64748b;
        }

        /* 13 Digits ID Card Boxes (Responsive Proportional Flex) */
        .idcard-inputs-container {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            max-width: 420px;
        }

        .idcard-box {
            text-align: center;
            font-weight: 600;
            letter-spacing: 2px;
            min-width: 0;
            padding-left: 4px;
            padding-right: 4px;
        }

        .idcard-box-1 { flex: 1.2 1 0; max-width: 48px; }
        .idcard-box-4 { flex: 2.6 1 0; max-width: 85px; }
        .idcard-box-5 { flex: 3.2 1 0; max-width: 105px; }
        .idcard-box-2 { flex: 1.8 1 0; max-width: 65px; }

        /* File Upload Custom Styling */
        .custom-file-upload-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            border: 1.5px solid var(--mvc-teal-border);
            border-radius: 6px;
            background: #ffffff;
            overflow: hidden;
        }

        .custom-file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .custom-file-upload-text {
            flex-grow: 1;
            padding: 7px 14px;
            font-size: 0.88rem;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .custom-file-browse-btn {
            background-color: var(--mvc-maroon);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.88rem;
            padding: 7px 18px;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.2s;
        }

        .custom-file-upload-wrapper:hover .custom-file-browse-btn {
            background-color: var(--mvc-maroon-dark);
        }

        /* Photo Preview Box */
        .photo-preview-box {
            width: 150px;
            height: 180px;
            aspect-ratio: 5 / 6;
            border: 1.5px dashed #cbd5e1;
            background-color: #f8fafc;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            margin-top: 10px;
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-box .placeholder-icon {
            font-size: 2.5rem;
            color: #cbd5e1;
        }

        /* Custom Level Radio Buttons */
        .level-radio-group {
            display: flex;
            gap: 25px;
            align-items: center;
            flex-wrap: wrap;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .level-radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #475569;
            cursor: not-allowed;
        }

        .level-radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
            cursor: not-allowed;
        }

        .level-radio-item.selected {
            color: var(--mvc-blue);
        }

        /* PVS Options Cards */
        .pvs-options-wrapper {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }
        .pvs-radio-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .pvs-radio-card:hover {
            border-color: #3b82f6;
            background-color: #f0f7ff;
        }
        .pvs-radio-card:has(input[type="radio"]:checked),
        .pvs-radio-card.selected {
            border-color: #2563eb !important;
            background-color: #eff6ff !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        .pvs-radio-card input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #2563eb;
            cursor: pointer;
        }

        /* Select2 Searchable Dropdown Styling */
        .select2-container {
            max-width: 100% !important;
            width: 100% !important;
            min-width: 0 !important;
        }
        .select2-container--bootstrap-5 .select2-selection {
            font-family: 'Sarabun', sans-serif !important;
            font-size: 0.95rem;
            min-height: 40px;
            border-color: #cbd5e1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            max-width: 100%;
            overflow: hidden;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #334155;
            padding-left: 12px;
            line-height: 38px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            font-family: 'Sarabun', sans-serif !important;
            font-size: 0.95rem;
            border-color: #93c5fd;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            z-index: 9999;
        }
        .select2-container--bootstrap-5 .select2-search__field {
            font-family: 'Sarabun', sans-serif !important;
            font-size: 0.92rem;
            border-radius: 6px;
            padding: 8px 12px;
        }
        .select2-container--bootstrap-5 .select2-results__option {
            padding: 8px 12px;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: var(--mvc-blue);
            color: #fff;
        }
        .select2-container--bootstrap-5 .select2-selection--single.is-disabled,
        .select2-container--bootstrap-5.select2-container--disabled .select2-selection {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }

        /* Conditions and Agreements */
        .notice-blue {
            color: #0284c7;
            font-size: 0.92rem;
            margin-bottom: 5px;
        }

        .notice-highlight-red {
            color: var(--mvc-blue);
            font-weight: 700;
        }

        .notice-link-blue {
            color: #0369a1;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.95rem;
            margin-bottom: 12px;
            display: block;
        }

        .agreement-checkbox-wrapper {
            margin-bottom: 12px;
            padding: 8px 12px;
            background-color: #f8fafc;
            border-radius: 6px;
            border: 1px solid #edf2f7;
        }

        .agreement-checkbox-wrapper .form-check-input {
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--mvc-teal-border);
            margin-top: 3px;
            cursor: pointer;
        }

        .agreement-checkbox-wrapper .form-check-label {
            font-size: 0.92rem;
            color: #334155;
            cursor: pointer;
            padding-left: 5px;
        }

        /* Action Buttons */
        .btn-action-save {
            background-color: var(--mvc-maroon);
            border: none;
            color: white;
            font-weight: 600;
            padding: 9px 30px;
            border-radius: 6px;
            font-size: 0.95rem;
            box-shadow: 0 2px 8px rgba(128, 0, 0, 0.25);
            transition: all 0.2s;
        }

        .btn-action-save:hover {
            background-color: var(--mvc-maroon-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.35);
        }

        .btn-action-cancel {
            background-color: var(--mvc-yellow);
            border: none;
            color: #333333;
            font-weight: 600;
            padding: 9px 26px;
            border-radius: 6px;
            font-size: 0.95rem;
            box-shadow: 0 2px 6px rgba(255, 193, 7, 0.25);
            transition: all 0.2s;
        }

        .btn-action-cancel:hover {
            background-color: #e0a800;
            color: #222;
        }

        /* Footer */
        .page-footer {
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
            padding: 25px 15px;
            margin-top: 30px;
        }

        .page-footer-highlight {
            color: var(--mvc-maroon);
            font-weight: 600;
        }

        /* Floating Scroll to Top */
        .scroll-top-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 44px;
            height: 44px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.35);
            cursor: pointer;
            transition: all 0.2s;
            z-index: 1000;
        }

        .scroll-top-btn:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
        }

        @media (max-width: 991.98px) {
            .sidebar-container {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 280px;
                max-width: 82vw;
                min-height: 100dvh;
                z-index: 1045;
                box-shadow: 8px 0 30px rgba(15, 23, 42, 0.18);
                transform: translateX(-105%);
                overflow-y: auto;
            }
            .sidebar-container.mobile-open {
                transform: translateX(0);
            }
            .main-content-card {
                padding: 24px 20px;
                border-radius: 12px;
            }
        }

        @media (max-width: 767.98px) {
            .form-banner-header {
                gap: 14px;
                padding-left: 14px;
                margin-bottom: 22px;
            }
            .level-radio-group {
                gap: 14px;
                padding: 10px 12px;
            }
            .pvs-radio-card {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 575.98px) {
            .main-content-card {
                padding: 18px 14px;
                margin-bottom: 24px;
            }
            .form-banner-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding-left: 12px;
            }
            .form-banner-logo {
                width: 52px;
                height: 52px;
            }
            .idcard-inputs-container {
                gap: 4px;
            }
            .idcard-box {
                letter-spacing: 0.5px;
                font-size: 0.85rem;
                padding-left: 2px;
                padding-right: 2px;
            }
            .btn-action-save,
            .btn-action-cancel {
                width: 100%;
                padding: 10px 20px;
            }
            .scroll-top-btn {
                bottom: 16px;
                right: 16px;
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>

    <!-- Topbar (จาก index.php) -->
    <div class="topbar">
        <div class="container-fluid px-lg-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span><i class="bi bi-mortarboard-fill me-1 text-warning"></i> ระบบรับสมัครนักเรียน นักศึกษาออนไลน์ (MVC Admission)</span>
                <span class="d-none d-md-inline opacity-50">|</span>
                <span class="d-none d-md-inline"><i class="bi bi-calendar3 me-1"></i> ปีการศึกษา <?= htmlspecialchars($active_year) ?></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="tel:043222283"><i class="bi bi-telephone-fill me-1"></i> 043-222283</a>
                <span class="opacity-50">|</span>
                <?php if ($is_admin): ?>
                    <a href="admin.php" class="text-warning fw-semibold"><i class="bi bi-speedometer2 me-1"></i> จัดการระบบ (<?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>)</a>
                <?php else: ?>
                    <a href="login.php"><i class="bi bi-shield-lock me-1"></i> สำหรับเจ้าหน้าที่</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navbar (จาก index.php พร้อมปุ่มเปิด/ปิด Slide Menu เดิม) -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid px-lg-4">
            <div class="d-flex align-items-center gap-2">
                <button class="sidebar-toggle-btn" type="button" id="sidebarToggle" title="เปิด/ปิดเมนูด้านข้าง">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a class="navbar-brand d-flex align-items-center gap-2 gap-sm-3 m-0" href="index.php">
                    <img src="img/logo.png" alt="Logo" width="48" height="48" class="rounded-circle border border-2 border-danger-subtle p-1 bg-white shadow-sm" style="aspect-ratio: 1 / 1; object-fit: contain;" onerror="this.src='assets/images/mvc_round_seal.png'">
                    <div>
                        <div class="brand-title">วิทยาลัยอาชีวศึกษามหาสารคาม</div>
                        <div class="brand-subtitle d-none d-sm-block">Mahasarakham Vocational College</div>
                    </div>
                </a>
            </div>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-1 mt-3 mt-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i> หน้าแรก</a></li>
                    <li class="nav-item"><a class="nav-link active" href="register.php"><i class="bi bi-pencil-square me-1"></i> สมัครเรียนออนไลน์</a></li>
                    <li class="nav-item"><a class="nav-link" href="status.php"><i class="bi bi-search me-1"></i> ตรวจสอบสถานะ</a></li>
                    <li class="nav-item"><a class="nav-link" href="print_application.php"><i class="bi bi-printer me-1"></i> พิมพ์ใบสมัคร</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-outline-red btn-sm px-3 py-2" href="status.php">
                            <i class="bi bi-clipboard-check"></i> ตรวจผล / พิมพ์ใบสมัคร
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Backdrop สำหรับ Slide Menu บนจอมือถือ/แท็บเล็ต -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main Page Container with Sidebar -->
    <div class="container-fluid">
        <div class="row">
            
            <!-- Left Sidebar (Slide Menu เดิม) -->
            <div class="col-lg-2 sidebar-container" id="sidebarMenu">
                <div class="d-flex justify-content-end d-lg-none mb-2">
                    <button type="button" class="btn btn-sm btn-light border text-secondary rounded-circle" id="sidebarCloseBtn" style="width: 32px; height: 32px;" title="ปิดเมนู">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="sidebar-profile">
                    <img src="assets/images/mvc_round_seal.png?v=<?= time() ?>" alt="วิทยาลัยอาชีวศึกษามหาสารคาม" class="sidebar-seal-img">
                    <div class="sidebar-user-name"><?= htmlspecialchars($logged_user_name) ?></div>
                    <div class="sidebar-user-status">ปีการศึกษา <?= htmlspecialchars($active_year) ?></div>

                    <!-- 4 Quick Action Buttons -->
                    <div class="quick-action-box">
                        <a href="index.php" class="quick-action-btn" title="หน้าแรก">
                            <i class="fa-solid fa-house"></i>
                        </a>
                        <a href="status.php" class="quick-action-btn" title="ตรวจสอบสถานะ">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </a>
                        <a href="<?= $is_admin ? 'admin.php' : 'login.php' ?>" class="quick-action-btn" title="สำหรับผู้ดูแลระบบ / เข้าสู่ระบบ">
                            <i class="fa-regular fa-user"></i>
                        </a>
                        <a href="logout.php" class="quick-action-btn" title="ออกจากระบบ">
                            <i class="fa-solid fa-power-off"></i>
                        </a>
                    </div>
                </div>

                <!-- Navigation Links -->
                <ul class="sidebar-nav-list">
                    <li class="sidebar-nav-item">
                        <a href="index.php" class="sidebar-nav-link">
                            <i class="fa-solid fa-house"></i>
                            <span>หน้าแรก</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="register.php" class="sidebar-nav-link active">
                            <i class="fa-solid fa-envelope-open-text"></i>
                            <span>กรอกใบสมัคร</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="status.php" class="sidebar-nav-link">
                            <i class="fa-solid fa-clipboard-check"></i>
                            <span>ตรวจสอบสถานะ</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="print_application.php" class="sidebar-nav-link">
                            <i class="fa-solid fa-print"></i>
                            <span>พิมพ์ใบสมัคร / เอกสาร</span>
                        </a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="sidebar-nav-item">
                        <a href="admin.php" class="sidebar-nav-link text-danger">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>จัดการระบบ (Admin)</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Right Main Form Content -->
            <div class="col-lg-10 col-12 py-3 py-md-4 px-lg-4 px-2 px-sm-3" id="mainFormCol">
                <div class="main-content-card">
                    
                    <!-- Header inside Form Card -->
                    <div class="form-banner-header">
                        <img src="assets/images/mvc_round_seal.png?v=<?= time() ?>" alt="ตราสัญลักษณ์ วิทยาลัยอาชีวศึกษามหาสารคาม" class="form-banner-logo">
                        <div class="form-banner-text">
                            <h4>วิทยาลัยอาชีวศึกษามหาสารคาม</h4>
                            <div class="form-banner-en">Mahasarakham Vocational College</div>
                            <p class="mt-1">ใบสมัครเข้าศึกษาต่อวิทยาลัยอาชีวศึกษามหาสารคาม ประจำปีการศึกษา <span class="quota-highlight"><?= htmlspecialchars($active_year) ?></span></p>
                        </div>
                    </div>

                    <!-- Form start -->
                    <form action="save_register.php" method="POST" enctype="multipart/form-data" id="admissionForm">
                        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($active_year) ?>">
                        <input type="hidden" name="id_card" id="hidden_id_card" value="">
                        <input type="hidden" name="level_id" id="hidden_level_id" value="1">

                        <!-- Round & Quota Select -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">รอบการรับสมัคร <span class="text-danger">*</span></label>
                                <select name="round_id" class="form-select" required>
                                    <?php if (!empty($active_rounds)): ?>
                                        <?php foreach ($active_rounds as $r): ?>
                                            <option value="<?= (int)$r['id'] ?>">
                                                [<?= $r['round_type'] === 'quota' ? 'รอบโควตา' : 'รอบปกติ' ?>] <?= htmlspecialchars($r['round_name']) ?> (ปีการศึกษา <?= htmlspecialchars($r['academic_year']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="<?= (int)($current_round['id'] ?? 1) ?>">
                                            <?= htmlspecialchars($current_round['round_name']) ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเภทการสมัคร / ประเภทโควตา <span class="text-danger">*</span></label>
                                <select name="quota_type" class="form-select" required>
                                    <option value="">--กรุณาเลือกประเภท--</option>
                                    <option value="โควตาทั่วไป (เกรดเฉลี่ยสะสม)" selected>โควตาทั่วไป (เกรดเฉลี่ยสะสม)</option>
                                    <option value="โควตากรณีพิเศษ (เรียนดี / กิจกรรมเด่น / กีฬา)">โควตากรณีพิเศษ (เรียนดี / กิจกรรมเด่น / กีฬา)</option>
                                    <option value="โควตาความสามารถพิเศษ / ดนตรีและศิลปวัฒนธรรม">โควตาความสามารถพิเศษ / ดนตรีและศิลปวัฒนธรรม</option>
                                    <option value="โควตาสถานศึกษาเดิม">โควตาสถานศึกษาเดิม</option>
                                    <option value="รอบรับสมัครทั่วไป (รอบปกติ)">รอบรับสมัครทั่วไป (รอบปกติ)</option>
                                </select>
                            </div>
                        </div>

                        <!-- 1. ข้อมูลผู้สมัคร -->
                        <div class="form-section-header">1. ข้อมูลผู้สมัคร</div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">คำนำหน้าชื่อ <span class="text-danger">*</span></label>
                                <select name="prefix" class="form-select" required>
                                    <option value="">--กรุณาเลือก--</option>
                                    <option value="นาย">นาย</option>
                                    <option value="นางสาว">นางสาว</option>
                                    <option value="เด็กชาย">เด็กชาย</option>
                                    <option value="เด็กหญิง">เด็กหญิง</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" name="firstname" class="form-control" placeholder="โปรดระบุ" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="lastname" class="form-control" placeholder="โปรดระบุ" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">รหัสประจำตัวประชาชน <span class="text-danger">*</span></label>
                                <div class="idcard-inputs-container">
                                    <input type="text" class="form-control idcard-box idcard-box-1" maxlength="1" id="id_box_1" placeholder="x" pattern="[0-9]" required>
                                    <span>-</span>
                                    <input type="text" class="form-control idcard-box idcard-box-4" maxlength="4" id="id_box_2" placeholder="xxxx" pattern="[0-9]{4}" required>
                                    <span>-</span>
                                    <input type="text" class="form-control idcard-box idcard-box-5" maxlength="5" id="id_box_3" placeholder="xxxxx" pattern="[0-9]{5}" required>
                                    <span>-</span>
                                    <input type="text" class="form-control idcard-box idcard-box-2" maxlength="2" id="id_box_4" placeholder="xx" pattern="[0-9]{2}" required>
                                    <span>-</span>
                                    <input type="text" class="form-control idcard-box idcard-box-1" maxlength="1" id="id_box_5" placeholder="x" pattern="[0-9]" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">วัน/เดือน/ปีเกิด <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <select name="birth_day" id="birth_day" class="form-select" required>
                                            <option value="">วัน</option>
                                            <?php for($d=1; $d<=31; $d++): ?>
                                                <option value="<?= $d ?>"><?= $d ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select name="birth_month" id="birth_month" class="form-select" required>
                                            <option value="">เดือน</option>
                                            <?php 
                                            $thai_months = [
                                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                                5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                                9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                            ];
                                            foreach($thai_months as $m_num => $m_name): ?>
                                                <option value="<?= $m_num ?>"><?= $m_name ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select name="birth_year" id="birth_year" class="form-select" required>
                                            <option value="">ปีเกิด</option>
                                            <?php 
                                            $curr_b_year = 2568;
                                            for($y=$curr_b_year-12; $y>=$curr_b_year-45; $y--): ?>
                                                <option value="<?= $y ?>"><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-sm-4">
                                <label class="form-label">อายุ</label>
                                <input type="number" name="age" id="applicant_age" class="form-control" placeholder="โปรดระบุ" min="10" max="80">
                            </div>
                            <div class="col-md-4 col-sm-8">
                                <label class="form-label">สัญชาติ</label>
                                <input type="text" name="nationality" class="form-control" value="ไทย" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ศาสนา</label>
                                <select name="religion" class="form-select">
                                    <option value="">--กรุณาเลือก--</option>
                                    <option value="พุทธ" selected>พุทธ</option>
                                    <option value="อิสลาม">อิสลาม</option>
                                    <option value="คริสต์">คริสต์</option>
                                    <option value="พราหมณ์-ฮินดู">พราหมณ์-ฮินดู</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. ข้อมูลที่อยู่ -->
                        <div class="form-section-header">2. ข้อมูลที่อยู่</div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">ที่อยู่ที่ติดต่อได้เลขที่ <span class="text-danger">*</span></label>
                                <input type="text" name="addr_no" class="form-control" placeholder="โปรดระบุ" required>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">หมู่ที่</label>
                                <input type="text" name="addr_moo" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">ซอย</label>
                                <input type="text" name="addr_soi" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                <select name="addr_province" id="addr_province" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกจังหวัด--" required>
                                    <option value="">--พิมพ์/เลือกจังหวัด--</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">อำเภอ <span class="text-danger">*</span></label>
                                <select name="addr_district" id="addr_district" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกอำเภอ--" required disabled>
                                    <option value="">--พิมพ์/เลือกอำเภอ--</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label">ตำบล <span class="text-danger">*</span></label>
                                <select name="addr_subdistrict" id="addr_subdistrict" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกตำบล--" required disabled>
                                    <option value="">--พิมพ์/เลือกตำบล--</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                                <input type="text" name="addr_zipcode" id="addr_zipcode" class="form-control bg-light" placeholder="รหัสไปรษณีย์" readonly required>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">เบอร์โทรศัพท์มือถือของผู้สมัคร <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="กรุณากรอกให้ครบถ้วน" maxlength="15" required>
                            </div>
                        </div>

                        <!-- 3. ข้อมูลของบิดาและมารดา -->
                        <div class="form-section-header">3. ข้อมูลของบิดาและมารดา</div>

                        <!-- Row 1: ข้อมูลบิดา -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-2">
                                <label class="form-label">คำนำหน้าชื่อ (บิดา)</label>
                                <select name="father_prefix" class="form-select">
                                    <option value="">--กรุณาเลือก--</option>
                                    <option value="นาย" selected>นาย</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ชื่อ (บิดา)</label>
                                <input type="text" name="father_firstname" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">นามสกุล (บิดา)</label>
                                <input type="text" name="father_lastname" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">อาชีพ (บิดา)</label>
                                <input type="text" name="father_occupation" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" name="father_phone" class="form-control" placeholder="โปรดระบุ">
                            </div>
                        </div>

                        <!-- Row 2: ข้อมูลมารดา -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-2">
                                <label class="form-label">คำนำหน้าชื่อ (มารดา)</label>
                                <select name="mother_prefix" class="form-select">
                                    <option value="">--กรุณาเลือก--</option>
                                    <option value="นาง">นาง</option>
                                    <option value="นางสาว" selected>นางสาว</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ชื่อ (มารดา)</label>
                                <input type="text" name="mother_firstname" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">นามสกุล (มารดา)</label>
                                <input type="text" name="mother_lastname" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">อาชีพ (มารดา)</label>
                                <input type="text" name="mother_occupation" class="form-control" placeholder="โปรดระบุ">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" name="mother_phone" class="form-control" placeholder="โปรดระบุ">
                            </div>
                        </div>

                        <!-- 4. ข้อมูลการศึกษาที่ใช้สมัคร -->
                        <div class="form-section-header">4. ข้อมูลการศึกษาที่ใช้สมัคร</div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">สำเร็จการศึกษาหรือกำลังศึกษาในระดับ <span class="text-danger">*</span></label>
                                <select name="prev_level" id="prev_level" class="form-select" required>
                                    <option value="">--กรุณาเลือก--</option>
                                    <option value="ม.3">ม.3</option>
                                    <option value="ม.6">ม.6</option>
                                    <option value="กศน.">กศน.</option>
                                    <option value="ปวช.3">ปวช.3</option>
                                    <option value="ปวส.2">ปวส.2</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">กำลังศึกษาอยู่โรงเรียน / สถานศึกษาเดิม <span class="text-danger">*</span></label>
                                <input type="text" name="prev_school" class="form-control" placeholder="โปรดระบุชื่อโรงเรียน/วิทยาลัยเดิม" required>
                            </div>
                        </div>

                        <!-- ที่ตั้งสถานศึกษาเดิม (จังหวัด -> อำเภอ -> ตำบล -> รหัสไปรษณีย์ เหมือนข้อ 2) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                <select name="prev_province" id="prev_province" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกจังหวัด--" required>
                                    <option value="">--พิมพ์/เลือกจังหวัด--</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">อำเภอ <span class="text-danger">*</span></label>
                                <select name="prev_district" id="prev_district" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกอำเภอ--" required disabled>
                                    <option value="">--พิมพ์/เลือกอำเภอ--</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">ตำบล <span class="text-danger">*</span></label>
                                <select name="prev_subdistrict" id="prev_subdistrict" class="form-select select2-searchable" data-placeholder="--พิมพ์/เลือกตำบล--" required disabled>
                                    <option value="">--พิมพ์/เลือกตำบล--</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                                <input type="text" name="prev_zipcode" id="prev_zipcode" class="form-control bg-light" placeholder="รหัสไปรษณีย์" readonly required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">โทรศัพท์ (สถานศึกษาเดิม)</label>
                                <input type="tel" name="prev_phone" class="form-control" placeholder="เช่น 043-xxxxxx หรือเบอร์ติดต่อ">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">สาขาวิชา(ถ้ามี) <span class="text-secondary fw-normal">(กรณีจบ ม.3, ม.6, กศน. ให้เลือก -ไม่มี-)</span></label>
                                <select name="prev_major" id="prev_major" class="form-select">
                                    <option value="-ไม่มี-">-ไม่มี-</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ผลการเรียนเฉลี่ยสะสม (GPAX) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.00" max="4.00" name="gpax" class="form-control" placeholder="โปรดระบุ เช่น 3.50" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label">ความสามารถพิเศษ (โปรดระบุ) <span class="text-secondary fw-normal">(ถ้ามี ให้พิมพ์ระบุ เช่น ด้านกีฬา, ดนตรี, คอมพิวเตอร์, ศิลปะ)</span></label>
                                <input type="text" name="special_ability" class="form-control" placeholder="โปรดระบุความสามารถพิเศษของผู้สมัคร">
                            </div>
                        </div>

                        <!-- 5. ข้อมูลหลักสูตรและสาขาที่ต้องการสมัคร -->
                        <div class="form-section-header">5. ข้อมูลหลักสูตรและสาขาที่ต้องการสมัคร</div>

                        <div class="mb-3">
                            <label class="form-label d-block">เข้าศึกษาต่อในระดับ <span class="text-muted fw-normal small">(ระบบกำหนดให้อัตโนมัติตามระดับการศึกษาเดิมในข้อ 4)</span></label>
                            <div class="level-radio-group">
                                <label class="level-radio-item" id="label_level_1">
                                    <input type="radio" name="display_level" id="radio_level_1" value="1" disabled checked>
                                    <span>ประกาศนียบัตรวิชาชีพ (ปวช.)</span>
                                </label>
                                <label class="level-radio-item" id="label_level_2">
                                    <input type="radio" name="display_level" id="radio_level_2" value="2" disabled>
                                    <span>ประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.)</span>
                                </label>
                                <label class="level-radio-item" id="label_level_3">
                                    <input type="radio" name="display_level" id="radio_level_3" value="3" disabled>
                                    <span>หลักสูตรเทคโนโลยีบัณฑิต (ทล.บ.)</span>
                                </label>
                            </div>
                        </div>

                        <!-- ตัวเลือกเฉพาะระดับ ปวส. (แสดงเมื่อเข้าศึกษาต่อในระดับ ปวส.) -->
                        <div id="pvs_options_container" class="mb-3" style="display: none;">
                            <label class="form-label d-block fw-bold mb-2" style="color: var(--mvc-maroon);">
                                รูปแบบการเข้าศึกษาต่อระดับ ปวส. <span class="text-danger">*</span>
                            </label>
                            <div class="pvs-options-wrapper">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="pvs-radio-card" for="pvs_type_1">
                                            <input type="radio" name="pvs_type" id="pvs_type_1" value="ระดับ ปวส. จบ ปวช. ระบบปกติ" class="form-check-input mt-0">
                                            <span class="fw-semibold text-dark">ระดับ ปวส. จบ ปวช. ระบบปกติ</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pvs-radio-card" for="pvs_type_2">
                                            <input type="radio" name="pvs_type" id="pvs_type_2" value="ระดับ ปวส. จบ ม.6 ระบบปกติ" class="form-check-input mt-0">
                                            <span class="fw-semibold text-dark">ระดับ ปวส. จบ ม.6 ระบบปกติ</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pvs-radio-card" for="pvs_type_3">
                                            <input type="radio" name="pvs_type" id="pvs_type_3" value="ระดับ ปวส. จบ ปวช. ระบบทวิภาคี" class="form-check-input mt-0">
                                            <span class="fw-semibold text-dark">ระดับ ปวส. จบ ปวช. ระบบทวิภาคี</span>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="pvs-radio-card" for="pvs_type_4">
                                            <input type="radio" name="pvs_type" id="pvs_type_4" value="ระดับ ปวส. จบ ม.6 ระบบทวิภาคี" class="form-check-input mt-0">
                                            <span class="fw-semibold text-dark">ระดับ ปวส. จบ ม.6 ระบบทวิภาคี</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 col-md-8 col-lg-7">
                            <label class="form-label">ระบุสาขาวิชาที่ต้องการสมัคร <span class="text-danger">*</span></label>
                            <select name="major_id" id="major_id" class="form-select" required>
                                <option value="">--กรุณาเลือกสาขาวิชา--</option>
                                <?php foreach($majors as $m): ?>
                                    <option value="<?= (int)$m['major_id'] ?>" data-level="<?= (int)$m['level_id'] ?>">
                                        [<?= htmlspecialchars($m['program_code']) ?>] <?= htmlspecialchars($m['major_name']) ?> — <?= htmlspecialchars($m['dept_name']) ?> (รับ <?= (int)$m['seats_available'] ?> ที่นั่ง)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 6. กรุณาแนบเอกสารหลักฐานประกอบการสมัครเข้าศึกษาต่อ -->
                        <div class="form-section-header">6. กรุณาแนบเอกสารหลักฐานประกอบการสมัครเข้าศึกษาต่อ</div>

                        <!-- File 1: บัตร ปชช. -->
                        <div class="mb-3">
                            <label class="form-label">
                                1. สำเนา บัตรประจำตัวประชาชน ของผู้สมัคร จำนวน 1 ฉบับ 
                                <span class="fw-normal" style="color: var(--mvc-maroon);">(png, jpg, jpeg, pdf) ขนาดไฟล์ไม่เกิน 2MB</span>
                            </label>
                            <div class="custom-file-upload-wrapper">
                                <div class="custom-file-upload-text" id="file_idcard_text">เลือกไฟล์</div>
                                <div class="custom-file-browse-btn">Browse</div>
                                <input type="file" name="file_idcard" id="file_idcard" accept=".png,.jpg,.jpeg,.pdf" onchange="updateFileName(this, 'file_idcard_text')">
                            </div>
                        </div>

                        <!-- File 2: ใบรายงานผลการเรียน GPAX -->
                        <div class="mb-3">
                            <label class="form-label">
                                2. สำเนา ใบรายงานผลการศึกษา จำนวน 1 ฉบับ 
                                <span class="fw-normal" style="color: var(--mvc-maroon);">ที่ระบุผลการเรียน คะแนนเฉลี่ยสะสม (GPAX) (4 ภาคเรียน) (png, jpg, jpeg, pdf) ขนาดไฟล์ไม่เกิน 2MB</span>
                            </label>
                            <div class="custom-file-upload-wrapper">
                                <div class="custom-file-upload-text" id="file_transcript_text">เลือกไฟล์</div>
                                <div class="custom-file-browse-btn">Browse</div>
                                <input type="file" name="file_transcript" id="file_transcript" accept=".png,.jpg,.jpeg,.pdf" onchange="updateFileName(this, 'file_transcript_text')">
                            </div>
                        </div>

                        <!-- File 3: รูปถ่ายหน้าตรงขนาด 1 นิ้ว -->
                        <div class="mb-3">
                            <label class="form-label">
                                3. รูปถ่ายหน้าตรงขนาด 1 นิ้ว 
                                <span class="text-info fw-semibold">(กว้าง 300 พิกเซล x สูง 350 พิกเซล)</span>
                                <span class="fw-normal" style="color: var(--mvc-maroon);">(รูปถ่ายไม่เกิน 6 เดือน และต้องเป็นชุดของสถานศึกษาเดิมเท่านั้น) (png, jpg, jpeg) ขนาดไฟล์ไม่เกิน 2MB</span>
                            </label>
                            <div class="custom-file-upload-wrapper">
                                <div class="custom-file-upload-text" id="file_photo_text">เลือกไฟล์</div>
                                <div class="custom-file-browse-btn">Browse</div>
                                <input type="file" name="file_photo" id="file_photo" accept=".png,.jpg,.jpeg" onchange="previewPhoto(this)">
                            </div>

                            <!-- Preview photo box -->
                            <div class="mt-2">
                                <div class="photo-preview-box" id="photoPreviewContainer">
                                    <i class="fa-regular fa-image placeholder-icon" id="photoPlaceholder"></i>
                                    <img src="" id="photoPreviewImg" style="display: none;" alt="ตัวอย่างรูปถ่าย">
                                </div>
                                <div class="notice-link-blue mt-1" style="font-size: 0.88rem;">ตัวอย่างภาพที่แสดงในใบสมัคร</div>
                            </div>
                        </div>

                        <!-- ข้อตกลง และ ยินยอม PDPA -->
                        <div class="mt-4 pt-2 border-top">
                            <p class="notice-blue mb-1">
                                สำเนาเอกสารทุกแผ่นเขียนคำว่า <span class="notice-highlight-red">"สำเนาถูกต้อง"</span> และลงชื่อในเอกสารด้วยปากกาหมึกสีน้ำเงิน
                            </p>
                            <span class="notice-link-blue mb-2">กรุณาคลิกเพื่อ ยอมรับ เงื่อนไข</span>

                            <div class="agreement-checkbox-wrapper">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="certifyCheck" name="certify_check" required>
                                    <label class="form-check-label" for="certifyCheck">
                                        ข้าพเจ้า ขอรับรองว่า ข้อมูลและหลักฐาน ในการสมัครในครั้งนี้เป็นความจริงทุกประการและถือว่าผลการตัดสินของวิทยาลัยฯ เป็นที่สิ้นสุด
                                    </label>
                                </div>
                            </div>

                            <!-- ส่วนเพิ่มเติม: ยินยอมกฎหมาย PDPA -->
                            <div class="agreement-checkbox-wrapper mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pdpaCheck" name="pdpa_consent" value="1" required>
                                    <label class="form-check-label" for="pdpaCheck">
                                        ข้าพเจ้า ยินยอมให้วิทยาลัยอาชีวศึกษามหาสารคาม เก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล เพื่อประโยชน์ในการดำเนินการคัดเลือกและสมัครเข้าศึกษาต่อ ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA)
                                    </label>
                                </div>
                            </div>

                            <!-- ปุ่มบันทึก & ยกเลิก -->
                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" class="btn btn-action-save">
                                    <i class="fa-regular fa-floppy-disk me-1"></i> บันทึก
                                </button>
                                <button type="reset" class="btn btn-action-cancel" onclick="resetFormCustom()">
                                    <i class="fa-solid fa-xmark me-1"></i> ยกเลิก
                                </button>
                            </div>
                        </div>

                    </form>
                    <!-- Form end -->

                </div>

                <!-- Footer -->
                <footer class="page-footer">
                    <div>
                        ระบบจัดการการรับสมัครนักเรียน นักศึกษาใหม่ วิทยาลัยอาชีวศึกษามหาสารคาม 
                        ออกแบบและพัฒนาโดย <span class="page-footer-highlight">งานศูนย์ดิจิทัลและสื่อสารองค์กร</span> Version 1.0.0
                    </div>
                </footer>

            </div>
        </div>
    </div>

    <!-- Floating Back to Top Button -->
    <button class="scroll-top-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="กลับขึ้นด้านบน">
        <i class="fa-solid fa-chevron-up"></i>
    </button>

    <!-- Load Compact Thai Address Database (77 provinces, districts, subdistricts & zipcodes) -->
    <script src="assets/thai_address_data.js"></script>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // -------------------------------------------------------------------------
        // 1. จัดการเลขบัตรประชาชน 13 หลัก (5 กล่อง + กระโดดอัตโนมัติ + รองรับการวาง)
        // -------------------------------------------------------------------------
        const idBoxes = [
            document.getElementById('id_box_1'),
            document.getElementById('id_box_2'),
            document.getElementById('id_box_3'),
            document.getElementById('id_box_4'),
            document.getElementById('id_box_5')
        ];
        const hiddenIdCard = document.getElementById('hidden_id_card');

        function updateFullIdCard() {
            let fullId = idBoxes.map(b => b.value).join('');
            hiddenIdCard.value = fullId;
        }

        idBoxes.forEach((box, index) => {
            box.addEventListener('input', function(e) {
                // เก็บเฉพาะตัวเลข
                this.value = this.value.replace(/[^0-9]/g, '');

                // หากกรอกครบตาม max length ให้เลื่อนไปกล่องถัดไป
                if (this.value.length >= this.maxLength && index < idBoxes.length - 1) {
                    idBoxes[index + 1].focus();
                }
                updateFullIdCard();
            });

            box.addEventListener('keydown', function(e) {
                // ถอยหลังไปกล่องก่อนหน้าเมื่อกด Backspace ในช่องว่าง
                if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                    idBoxes[index - 1].focus();
                }
            });

            box.addEventListener('paste', function(e) {
                e.preventDefault();
                let pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                if (pasteData.length > 0) {
                    let pos = 0;
                    const lengths = [1, 4, 5, 2, 1];
                    for (let i = 0; i < idBoxes.length; i++) {
                        let len = lengths[i];
                        idBoxes[i].value = pasteData.substr(pos, len);
                        pos += len;
                    }
                    updateFullIdCard();
                    idBoxes[idBoxes.length - 1].focus();
                }
            });
        });

        // -------------------------------------------------------------------------
        // 2. คำนวณอายุอัตโนมัติเมื่อเลือกปีเกิด
        // -------------------------------------------------------------------------
        document.getElementById('birth_year').addEventListener('change', function() {
            const birthYear = parseInt(this.value);
            if (!isNaN(birthYear)) {
                const currentThaiYear = 2568;
                let age = currentThaiYear - birthYear;
                if (age > 0) {
                    document.getElementById('applicant_age').value = age;
                }
            }
        });

        // -------------------------------------------------------------------------
        // 3. ระบบที่อยู่ 77 จังหวัด อำเภอ ตำบล รหัสไปรษณีย์ (พิมพ์ค้นหาได้ด้วย Select2)
        // -------------------------------------------------------------------------
        // 3.1 โหลดรายชื่อ 77 จังหวัดสำหรับทั้งข้อ 2 และข้อ 4
        if (typeof THAI_ADDRESS_DATA !== 'undefined' && Array.isArray(THAI_ADDRESS_DATA)) {
            THAI_ADDRESS_DATA.forEach(p => {
                $('#addr_province').append(new Option(p.name, p.name));
                $('#prev_province').append(new Option(p.name, p.name));
            });
        }

        // 3.2 เริ่มต้นการทำงาน Select2 ให้พิมพ์ค้นหาได้ทันที
        function initSearchableSelects() {
            $('.select2-searchable').each(function() {
                const placeholder = $(this).data('placeholder') || '--พิมพ์/เลือกข้อมูล--';
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: placeholder,
                    allowClear: false,
                    language: {
                        noResults: function() {
                            return "ไม่พบข้อมูลที่พิมพ์ค้นหา";
                        },
                        searching: function() {
                            return "กำลังค้นหา...";
                        }
                    }
                });
            });
        }
        initSearchableSelects();

        // 3.3 ข้อ 2: ข้อมูลที่อยู่ผู้สมัคร (จังหวัด -> อำเภอ -> ตำบล -> รหัสไปรษณีย์)
        // เมื่อเลือกจังหวัดผู้สมัคร
        $('#addr_province').on('change', function() {
            const selectedProvName = $(this).val();
            const $dist = $('#addr_district');
            const $sub = $('#addr_subdistrict');
            const $zip = $('#addr_zipcode');

            $dist.empty().append('<option value="">--พิมพ์/เลือกอำเภอ--</option>');
            $sub.empty().append('<option value="">--พิมพ์/เลือกตำบล--</option>');
            $zip.val('');
            $sub.prop('disabled', true);

            if (!selectedProvName) {
                $dist.prop('disabled', true);
                $dist.trigger('change.select2');
                $sub.trigger('change.select2');
                return;
            }

            const provData = THAI_ADDRESS_DATA.find(p => p.name === selectedProvName);
            if (provData && provData.districts) {
                provData.districts.forEach(d => {
                    $dist.append(new Option(d.name, d.name));
                });
                $dist.prop('disabled', false);
            } else {
                $dist.prop('disabled', true);
            }
            $dist.trigger('change.select2');
            $sub.trigger('change.select2');
        });

        // เมื่อเลือกอำเภอผู้สมัคร
        $('#addr_district').on('change', function() {
            const selectedProvName = $('#addr_province').val();
            const selectedDistName = $(this).val();
            const $sub = $('#addr_subdistrict');
            const $zip = $('#addr_zipcode');

            $sub.empty().append('<option value="">--พิมพ์/เลือกตำบล--</option>');
            $zip.val('');

            if (!selectedDistName) {
                $sub.prop('disabled', true);
                $sub.trigger('change.select2');
                return;
            }

            const provData = THAI_ADDRESS_DATA.find(p => p.name === selectedProvName);
            if (provData) {
                const distData = provData.districts.find(d => d.name === selectedDistName);
                if (distData && distData.sub) {
                    distData.sub.forEach(s => {
                        let opt = new Option(s.name, s.name);
                        opt.setAttribute('data-zip', s.zip || '');
                        $sub.append(opt);
                    });
                    $sub.prop('disabled', false);
                } else {
                    $sub.prop('disabled', true);
                }
            } else {
                $sub.prop('disabled', true);
            }
            $sub.trigger('change.select2');
        });

        // เมื่อเลือกตำบลผู้สมัคร -> ป้อนรหัสไปรษณีย์ให้อัตโนมัติทันที
        $('#addr_subdistrict').on('change', function() {
            const selectedOpt = $(this).find(':selected');
            if (selectedOpt.length && selectedOpt.attr('data-zip')) {
                $('#addr_zipcode').val(selectedOpt.attr('data-zip'));
            } else {
                $('#addr_zipcode').val('');
            }
        });

        // 3.4 ข้อ 4: ที่ตั้งสถานศึกษาเดิม (จังหวัด -> อำเภอ -> ตำบล -> รหัสไปรษณีย์)
        // เมื่อเลือกจังหวัดเดิม
        $('#prev_province').on('change', function() {
            const selectedProvName = $(this).val();
            const $dist = $('#prev_district');
            const $sub = $('#prev_subdistrict');
            const $zip = $('#prev_zipcode');

            $dist.empty().append('<option value="">--พิมพ์/เลือกอำเภอ--</option>');
            $sub.empty().append('<option value="">--พิมพ์/เลือกตำบล--</option>');
            $zip.val('');
            $sub.prop('disabled', true);

            if (!selectedProvName) {
                $dist.prop('disabled', true);
                $dist.trigger('change.select2');
                $sub.trigger('change.select2');
                return;
            }

            const provData = THAI_ADDRESS_DATA.find(p => p.name === selectedProvName);
            if (provData && provData.districts) {
                provData.districts.forEach(d => {
                    $dist.append(new Option(d.name, d.name));
                });
                $dist.prop('disabled', false);
            } else {
                $dist.prop('disabled', true);
            }
            $dist.trigger('change.select2');
            $sub.trigger('change.select2');
        });

        // เมื่อเลือกอำเภอเดิม
        $('#prev_district').on('change', function() {
            const selectedProvName = $('#prev_province').val();
            const selectedDistName = $(this).val();
            const $sub = $('#prev_subdistrict');
            const $zip = $('#prev_zipcode');

            $sub.empty().append('<option value="">--พิมพ์/เลือกตำบล--</option>');
            $zip.val('');

            if (!selectedDistName) {
                $sub.prop('disabled', true);
                $sub.trigger('change.select2');
                return;
            }

            const provData = THAI_ADDRESS_DATA.find(p => p.name === selectedProvName);
            if (provData) {
                const distData = provData.districts.find(d => d.name === selectedDistName);
                if (distData && distData.sub) {
                    distData.sub.forEach(s => {
                        let opt = new Option(s.name, s.name);
                        opt.setAttribute('data-zip', s.zip || '');
                        $sub.append(opt);
                    });
                    $sub.prop('disabled', false);
                } else {
                    $sub.prop('disabled', true);
                }
            } else {
                $sub.prop('disabled', true);
            }
            $sub.trigger('change.select2');
        });

        // เมื่อเลือกตำบลเดิม -> ป้อนรหัสไปรษณีย์ให้อัตโนมัติทันที
        $('#prev_subdistrict').on('change', function() {
            const selectedOpt = $(this).find(':selected');
            if (selectedOpt.length && selectedOpt.attr('data-zip')) {
                $('#prev_zipcode').val(selectedOpt.attr('data-zip'));
            } else {
                $('#prev_zipcode').val('');
            }
        });

        // -------------------------------------------------------------------------
        // 4. เงื่อนไขข้อ 4 และข้อ 5:
        //    - ข้อ 4: ถ้าเลือก ม.3, ม.6, กศน. จะไม่มีสาขาให้เลือก (ล็อกเป็น -ไม่มี-)
        //            ถ้าเลือก ปวช.3 หรือ ปวส.2 ถึงจะมีสาขาให้เลือก
        //    - ข้อ 5: ถ้าข้อ 4 เลือก ม.3 -> ในข้อ 5 เลือก ปวช. อัตโนมัติ
        //            ถ้าข้อ 4 เลือก ม.6 หรือ กศน. -> ในข้อ 5 เลือก ปวส. อัตโนมัติ
        //            ถ้าข้อ 4 เลือก ปวช.3 -> ในข้อ 5 เลือก ปวส. อัตโนมัติ
        //            ถ้าข้อ 4 เลือก ปวส.2 -> ในข้อ 5 เลือก ทล.บ. อัตโนมัติ
        //            ในส่วนข้อ 5 ผู้สมัครคลิกเลือกเองไม่ได้!
        // -------------------------------------------------------------------------
        const prevLevelSelect = document.getElementById('prev_level');
        const prevMajorSelect = document.getElementById('prev_major');
        const hiddenLevelId = document.getElementById('hidden_level_id');
        const majorSelect = document.getElementById('major_id');

        // รายการสาขาสำหรับระดับเดิม ปวช.3 และ ปวส.2
        const majorsPvc3 = [
            '--กรุณาเลือกสาขางานเดิม--',
            'สาขางานช่างยนต์', 'สาขางานช่างไฟฟ้ากำลัง', 'สาขางานช่างอิเล็กทรอนิกส์',
            'สาขางานการบัญชี', 'สาขางานการตลาด', 'สาขางานคอมพิวเตอร์ธุรกิจ/ดิจิทัล',
            'สาขางานธุรกิจค้าปลีก', 'สาขางานการโรงแรม', 'สาขางานอาหารและโภชนาการ',
            'สาขางานคอมพิวเตอร์กราฟิก', 'สาขางานแฟชั่นและเครื่องแต่งกาย', 'สาขางานวิจิตรศิลป์',
            'อื่นๆ'
        ];

        const majorsPvs2 = [
            '--กรุณาเลือกสาขางานเดิม--',
            'สาขางานการบัญชี', 'สาขางานการตลาดดิจิทัล', 'สาขางานเทคโนโลยีธุรกิจดิจิทัล',
            'สาขางานการจัดการธุรกิจค้าปลีก', 'สาขางานการโรงแรมและบริการ', 'สาขางานอาหารและโภชนาการ',
            'สาขางานดิจิทัลกราฟิก', 'สาขางานการจัดการสำนักงาน', 'อื่นๆ'
        ];

        function filterMajorsByLevel(levelId) {
            const options = majorSelect.options;
            majorSelect.value = "";
            let firstMatched = false;

            for (let i = 0; i < options.length; i++) {
                const opt = options[i];
                if (opt.value === "") {
                    opt.style.display = "block";
                    continue;
                }
                if (opt.getAttribute('data-level') == levelId) {
                    opt.style.display = "block";
                    if (!firstMatched) {
                        firstMatched = true;
                    }
                } else {
                    opt.style.display = "none";
                }
            }
        }

        function setTargetEducationLevel(targetLevelId) {
            hiddenLevelId.value = targetLevelId;

            // อัปเดตการเลือก radio
            document.getElementById('radio_level_1').checked = (targetLevelId == 1);
            document.getElementById('radio_level_2').checked = (targetLevelId == 2);
            document.getElementById('radio_level_3').checked = (targetLevelId == 3);

            // ไฮไลต์ label
            for (let i = 1; i <= 3; i++) {
                const lbl = document.getElementById(`label_level_${i}`);
                if (i == targetLevelId) {
                    lbl.classList.add('selected');
                } else {
                    lbl.classList.remove('selected');
                }
            }

            // จัดการแสดง/ซ่อน ตัวเลือก ปวส. (ตามภาพที่ 2 ในข้อ 5)
            const pvsContainer = document.getElementById('pvs_options_container');
            const pvsRadios = document.querySelectorAll('input[name="pvs_type"]');
            if (targetLevelId == 2) {
                pvsContainer.style.display = 'block';
                pvsRadios.forEach(r => r.disabled = false);
            } else {
                pvsContainer.style.display = 'none';
                pvsRadios.forEach(r => {
                    r.checked = false;
                    r.disabled = true;
                    if (r.closest('.pvs-radio-card')) {
                        r.closest('.pvs-radio-card').classList.remove('selected');
                    }
                });
            }

            // กรองสาขาในข้อ 5 ทันที
            filterMajorsByLevel(targetLevelId);
        }

        // เพิ่ม Event สำหรับการคลิกเลือกตัวเลือก ปวส. ให้มีผลไฮไลต์ทันที
        document.querySelectorAll('input[name="pvs_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.pvs-radio-card').forEach(card => card.classList.remove('selected'));
                if (this.checked && this.closest('.pvs-radio-card')) {
                    this.closest('.pvs-radio-card').classList.add('selected');
                }
            });
        });

        prevLevelSelect.addEventListener('change', function() {
            const lvl = this.value;

            // 1. จัดการข้อ 4: สาขางานที่เรียน
            prevMajorSelect.innerHTML = '';

            if (lvl === 'ม.3' || lvl === 'ม.6' || lvl === 'กศน.') {
                // ไม่มีสาขาให้เลือก
                let opt = new Option('-ไม่มี-', '-ไม่มี-');
                prevMajorSelect.add(opt);
                prevMajorSelect.value = '-ไม่มี-';
                prevMajorSelect.disabled = true;
            } else if (lvl === 'ปวช.3') {
                // มีสาขาให้เลือก
                prevMajorSelect.disabled = false;
                majorsPvc3.forEach(m => {
                    prevMajorSelect.add(new Option(m, m.startsWith('--') ? '' : m));
                });
            } else if (lvl === 'ปวส.2') {
                // มีสาขาให้เลือก
                prevMajorSelect.disabled = false;
                majorsPvs2.forEach(m => {
                    prevMajorSelect.add(new Option(m, m.startsWith('--') ? '' : m));
                });
            } else {
                let opt = new Option('--กรุณาเลือกสาขางาน--', '');
                prevMajorSelect.add(opt);
                prevMajorSelect.disabled = false;
            }

            // 2. จัดการข้อ 5: เข้าศึกษาต่อในระดับอัตโนมัติ
            if (lvl === 'ม.3') {
                setTargetEducationLevel(1); // ปวช.
            } else if (lvl === 'ม.6' || lvl === 'กศน.') {
                setTargetEducationLevel(2); // ปวส.
                // แนะนำตัวเลือกเริ่มต้นให้เหมาะสม แต่ผู้สมัครสามารถคลิกเลือกตัวอื่นได้เอง
                const opt = document.getElementById('pvs_type_2');
                if (opt) {
                    opt.checked = true;
                    opt.dispatchEvent(new Event('change'));
                }
            } else if (lvl === 'ปวช.3') {
                setTargetEducationLevel(2); // ปวส.
                // แนะนำตัวเลือกเริ่มต้นให้เหมาะสม แต่ผู้สมัครสามารถคลิกเลือกตัวอื่นได้เอง
                const opt = document.getElementById('pvs_type_1');
                if (opt) {
                    opt.checked = true;
                    opt.dispatchEvent(new Event('change'));
                }
            } else if (lvl === 'ปวส.2') {
                setTargetEducationLevel(3); // ทล.บ.
            } else {
                setTargetEducationLevel(1);
            }
        });

        // กำหนดเริ่มต้น
        setTargetEducationLevel(1);

        // -------------------------------------------------------------------------
        // 5. แสดงชื่อไฟล์ที่เลือก และ Preview รูปถ่ายหน้าตรง 1 นิ้ว
        // -------------------------------------------------------------------------
        function updateFileName(input, textId) {
            const textElement = document.getElementById(textId);
            if (input.files && input.files[0]) {
                textElement.textContent = input.files[0].name;
                textElement.style.color = '#1e293b';
                textElement.style.fontWeight = '500';
            } else {
                textElement.textContent = 'เลือกไฟล์';
                textElement.style.color = '#94a3b8';
            }
        }

        function previewPhoto(input) {
            updateFileName(input, 'file_photo_text');
            const previewImg = document.getElementById('photoPreviewImg');
            const placeholder = document.getElementById('photoPlaceholder');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '';
                previewImg.style.display = 'none';
                placeholder.style.display = 'block';
            }
        }

        function resetFormCustom() {
            setTimeout(() => {
                updateFullIdCard();
                setTargetEducationLevel(1);
                prevMajorSelect.innerHTML = '<option value="-ไม่มี-">-ไม่มี-</option>';
                prevMajorSelect.disabled = true;

                // รีเซ็ต Select2 ของที่อยู่ทั้งข้อ 2 และข้อ 4
                $('#addr_province, #prev_province').val('').trigger('change.select2');
                $('#addr_district, #prev_district').empty().append('<option value="">--พิมพ์/เลือกอำเภอ--</option>').prop('disabled', true).trigger('change.select2');
                $('#addr_subdistrict, #prev_subdistrict').empty().append('<option value="">--พิมพ์/เลือกตำบล--</option>').prop('disabled', true).trigger('change.select2');
                $('#addr_zipcode, #prev_zipcode').val('');

                document.querySelectorAll('.pvs-radio-card').forEach(card => card.classList.remove('selected'));
                document.getElementById('file_idcard_text').textContent = 'เลือกไฟล์';
                document.getElementById('file_transcript_text').textContent = 'เลือกไฟล์';
                document.getElementById('file_photo_text').textContent = 'เลือกไฟล์';
                document.getElementById('photoPreviewImg').style.display = 'none';
                document.getElementById('photoPlaceholder').style.display = 'block';
            }, 50);
        }

        // ตรวจสอบความถูกต้องก่อนส่งฟอร์ม
        document.getElementById('admissionForm').addEventListener('submit', function(e) {
            updateFullIdCard();
            if (hiddenIdCard.value.length !== 13) {
                alert('กรุณากรอกเลขประจำตัวประชาชนให้ครบ 13 หลัก');
                idBoxes[0].focus();
                e.preventDefault();
                return false;
            }

            // ตรวจสอบข้อมูลที่อยู่ (ทั้งข้อ 2 และข้อ 4)
            const requiredAddressSelects = [
                { id: 'addr_province', label: 'จังหวัด (ที่อยู่ผู้สมัคร ในข้อ 2)' },
                { id: 'addr_district', label: 'อำเภอ (ที่อยู่ผู้สมัคร ในข้อ 2)' },
                { id: 'addr_subdistrict', label: 'ตำบล (ที่อยู่ผู้สมัคร ในข้อ 2)' },
                { id: 'prev_province', label: 'จังหวัด (สถานศึกษาเดิม ในข้อ 4)' },
                { id: 'prev_district', label: 'อำเภอ (สถานศึกษาเดิม ในข้อ 4)' },
                { id: 'prev_subdistrict', label: 'ตำบล (สถานศึกษาเดิม ในข้อ 4)' }
            ];
            for (let f of requiredAddressSelects) {
                const val = $('#' + f.id).val();
                if (!val) {
                    alert('กรุณาเลือก ' + f.label);
                    $('#' + f.id).select2('open');
                    e.preventDefault();
                    return false;
                }
            }

            // ถ้าเป็นการเข้าศึกษาต่อระดับ ปวส. ต้องเลือกรูปแบบในภาพที่สองด้วย
            if (hiddenLevelId.value === '2') {
                const selectedPvs = document.querySelector('input[name="pvs_type"]:checked');
                if (!selectedPvs) {
                    alert('กรุณาเลือกรูปแบบการเข้าศึกษาต่อระดับ ปวส. ในข้อ 5');
                    const pvsCont = document.getElementById('pvs_options_container');
                    if (pvsCont) pvsCont.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    e.preventDefault();
                    return false;
                }
            }

            if (!document.getElementById('certifyCheck').checked) {
                alert('กรุณาคลิกรับรองว่าข้อมูลและหลักฐานเป็นความจริง');
                e.preventDefault();
                return false;
            }

            if (!document.getElementById('pdpaCheck').checked) {
                alert('กรุณาคลิกยินยอมตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล (PDPA)');
                e.preventDefault();
                return false;
            }
        });

        // สลับมุมมอง Sidebar (Slide Menu) รองรับทั้งจอมือถือ/แท็บเล็ต และเดสก์ท็อป
        const sidebarMenu = document.getElementById('sidebarMenu');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const mainFormCol = document.getElementById('mainFormCol');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

        function closeMobileSidebar() {
            sidebarMenu.classList.remove('mobile-open');
            sidebarBackdrop.classList.remove('show');
        }

        document.getElementById('sidebarToggle').addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebarMenu.classList.toggle('mobile-open');
                sidebarBackdrop.classList.toggle('show');
            } else {
                sidebarMenu.classList.toggle('d-lg-none');
                if (sidebarMenu.classList.contains('d-lg-none')) {
                    mainFormCol.classList.remove('col-lg-10');
                    mainFormCol.classList.add('col-lg-12');
                } else {
                    mainFormCol.classList.remove('col-lg-12');
                    mainFormCol.classList.add('col-lg-10');
                }
            }
        });

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', closeMobileSidebar);
        }
        if (sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', closeMobileSidebar);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>