<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/config/database.php';

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ฟังก์ชันอัปโหลดไฟล์
function uploadApplicantDoc($file_input, $upload_dir, $prefix_name) {
    if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] == 0) {
        $file_info = pathinfo($_FILES[$file_input]['name']);
        $ext = strtolower($file_info['extension'] ?? '');
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if ($_FILES[$file_input]['size'] > 5 * 1024 * 1024) {
            return false;
        }

        if (in_array($ext, $allowed)) {
            $new_name = $prefix_name . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $upload_dir . $new_name)) {
                return $new_name;
            }
        }
    }
    return null;
}

// ฟังก์ชันแปลงเลขไทย
function toThaiNum($num) {
    $thai_digits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $arabic_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($arabic_digits, $thai_digits, (string)$num);
}

// ฟังก์ชันแยกวันเดือนปีเกิดแบบไทย
function getThaiBirthParts($date_str) {
    if (empty($date_str) || $date_str === '0000-00-00') {
        return ['day' => '-', 'month' => '-', 'year' => '-'];
    }
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $time = strtotime($date_str);
    if (!$time) {
        return ['day' => '-', 'month' => '-', 'year' => '-'];
    }
    $d = date('j', $time);
    $m = (int)date('n', $time);
    $y = (int)date('Y', $time) + 543;
    return [
        'day' => $d,
        'month' => $thai_months[$m] ?? '-',
        'year' => $y
    ];
}

// ฟังก์ชันแสดงค่า หากว่างหรือขีดให้ใส่ '-'
function valOrDash($val) {
    if ($val === null) return '-';
    $t = trim((string)$val);
    return ($t === '' || $t === '-') ? '-' : $t;
}

$search_keyword = trim($_GET['search'] ?? $_GET['citizen_id'] ?? $_POST['search'] ?? '');
if (empty($search_keyword) && !empty($_SESSION['applicant_citizen_id'])) {
    $search_keyword = trim($_SESSION['applicant_citizen_id']);
}
$applicant = null;
$search_performed = false;
$msg_success = '';
$msg_error = '';

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// จัดการอัปโหลดไฟล์ในข้อ 6
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_docs') {
    $applicant_id = intval($_POST['applicant_id'] ?? 0);
    $search_keyword = trim($_POST['search_keyword'] ?? '');

    if ($applicant_id > 0) {
        $stmt_check = $pdo->prepare("SELECT * FROM applicants WHERE id = :id");
        $stmt_check->execute([':id' => $applicant_id]);
        $curr = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($curr) {
            $updates = [];
            $params = [':id' => $applicant_id];
            $cid = $curr['citizen_id'];

            // ฟังก์ชันช่วยอัปเดตตาราง applicant_documents
            $upsertDoc = function($doc_type, $file_name) use ($pdo, $applicant_id) {
                $pdo->prepare("DELETE FROM applicant_documents WHERE applicant_id = :aid AND doc_type = :dtype")
                    ->execute([':aid' => $applicant_id, ':dtype' => $doc_type]);
                $pdo->prepare("INSERT INTO applicant_documents (applicant_id, doc_type, file_name, file_path, verified) VALUES (:aid, :dtype, :fname, :fpath, 0)")
                    ->execute([':aid' => $applicant_id, ':dtype' => $doc_type, ':fname' => $file_name, ':fpath' => 'uploads/' . $file_name]);
            };

            // 1. สำเนาบัตรประชาชน
            $new_idcard = uploadApplicantDoc('file_idcard', $upload_dir, 'idcard_' . $cid);
            if ($new_idcard !== null) {
                if ($new_idcard === false) {
                    $msg_error = 'สำเนาบัตรประชาชนขนาดเกิน 5MB หรือประเภทไฟล์ไม่ถูกต้อง';
                } else {
                    $updates[] = "file_idcard = :file_idcard";
                    $params[':file_idcard'] = $new_idcard;
                    $upsertDoc('citizen_card', $new_idcard);
                }
            }

            // 2. รายงานผลการเรียน GPAX
            $new_transcript = uploadApplicantDoc('file_transcript', $upload_dir, 'trans_' . $cid);
            if ($new_transcript !== null) {
                if ($new_transcript === false) {
                    $msg_error = 'รายงานผลการเรียนขนาดเกิน 5MB หรือประเภทไฟล์ไม่ถูกต้อง';
                } else {
                    $updates[] = "file_transcript = :file_transcript";
                    $params[':file_transcript'] = $new_transcript;
                    $upsertDoc('transcript', $new_transcript);
                }
            }

            // 3. รูปถ่ายหน้าตรง
            $new_photo = uploadApplicantDoc('file_photo', $upload_dir, 'photo_' . $cid);
            if ($new_photo !== null) {
                if ($new_photo === false) {
                    $msg_error = 'รูปถ่ายขนาดเกิน 5MB หรือประเภทไฟล์ไม่ถูกต้อง';
                } else {
                    $updates[] = "file_photo = :file_photo";
                    $params[':file_photo'] = $new_photo;
                    $upsertDoc('photo', $new_photo);
                }
            }

            if (empty($msg_error) && count($updates) > 0) {
                $sql_update = "UPDATE applicants SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt_up = $pdo->prepare($sql_update);
                $stmt_up->execute($params);
                $msg_success = 'อัปโหลดไฟล์เอกสารเรียบร้อยแล้ว!';
            } elseif (empty($msg_error) && count($updates) === 0) {
                $msg_error = 'กรุณาเลือกไฟล์เอกสารที่ต้องการอัปโหลดก่อนบันทึก';
            }
        }
    }
}

// ค้นหาผู้สมัครด้วยเลขบัตรประชาชน เบอร์โทรศัพท์ หรือ เลขที่ใบสมัคร
if (!empty($search_keyword)) {
    $search_performed = true;
    $clean_keyword = preg_replace('/[^0-9]/', '', $search_keyword);
    if ($clean_keyword === '') {
        $clean_keyword = '__NO_MATCH__';
    }

    try {
        $sql = "SELECT a.*,
                       a.id AS applicant_id,
                       a.citizen_id AS id_card,
                       a.first_name AS firstname,
                       a.last_name AS lastname,
                       a.birthdate AS birth_date,
                       a.previous_school AS prev_school,
                       a.gpa AS gpax,
                       a.status AS apply_status,
                       a.created_at AS apply_date,
                       p.program_name AS major_name,
                       p.program_code,
                       d.dept_name,
                       CASE a.education_level
                           WHEN 'ปวช.' THEN 1
                           WHEN 'ปวส.' THEN 2
                           WHEN 'ปริญญาตรี' THEN 3
                           ELSE 1
                       END AS level_id,
                       CASE a.education_level
                           WHEN 'ปวช.' THEN 'ประกาศนียบัตรวิชาชีพ (ปวช.)'
                           WHEN 'ปวส.' THEN 'ประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.)'
                           WHEN 'ปริญญาตรี' THEN 'หลักสูตรเทคโนโลยีบัณฑิต (ทล.บ.)'
                           ELSE a.education_level
                       END AS level_name,
                       COALESCE(r.round_name, a.admission_round, 'รอบโควตาและรอบทั่วไป') AS round_name,
                       COALESCE(a.academic_year, r.academic_year, '2570') AS academic_year
                FROM applicants a
                LEFT JOIN programs p ON a.program_id = p.id
                LEFT JOIN departments d ON p.dept_id = d.id
                LEFT JOIN admission_rounds r ON a.round_id = r.id
                WHERE a.citizen_id = :kw1
                   OR a.citizen_id = :clean_kw1
                   OR a.phone = :kw2
                   OR REPLACE(a.phone, '-', '') = :clean_kw2
                   OR a.application_no = :kw3
                ORDER BY a.id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':kw1'       => $search_keyword,
            ':clean_kw1' => $clean_keyword,
            ':kw2'       => $search_keyword,
            ':clean_kw2' => $clean_keyword,
            ':kw3'       => $search_keyword,
        ]);
        $applicant = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $applicant = null;
    }
}

$birth = $applicant ? getThaiBirthParts($applicant['birth_date'] ?? '') : ['day' => '-', 'month' => '-', 'year' => '-'];

$father_name = '-';
if ($applicant) {
    $parts_f = array_filter([$applicant['father_prefix'] ?? '', $applicant['father_firstname'] ?? '', $applicant['father_lastname'] ?? '']);
    $f_str = trim(implode(' ', $parts_f));
    $father_name = (!empty($f_str) && $f_str !== '-') ? $f_str : '-';
}

$mother_name = '-';
if ($applicant) {
    $parts_m = array_filter([$applicant['mother_prefix'] ?? '', $applicant['mother_firstname'] ?? '', $applicant['mother_lastname'] ?? '']);
    $m_str = trim(implode(' ', $parts_m));
    $mother_name = (!empty($m_str) && $m_str !== '-') ? $m_str : '-';
}

$subdistrict_clean = $applicant ? valOrDash(preg_replace('/^(ตำบล|ต\.)\s*/u', '', $applicant['addr_subdistrict'] ?? '')) : '-';
$district_clean = $applicant ? valOrDash(preg_replace('/^(อำเภอ|อ\.)\s*/u', '', $applicant['addr_district'] ?? '')) : '-';
$province_clean = $applicant ? valOrDash(preg_replace('/^(จังหวัด|จ\.)\s*/u', '', $applicant['addr_province'] ?? '')) : '-';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์ใบสมัครและค้นหาประวัติการสมัคร - วิทยาลัยอาชีวศึกษามหาสารคาม</title>
    <!-- Favicon Icon -->
    <link rel="icon" type="image/png" href="img/Logo-W.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts Thai: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --mvc-maroon: #800000;
            --mvc-maroon-dark: #630000;
            --mvc-maroon-light: #9c1c1c;
            --mvc-blue: #004ea2;
            --mvc-bg-body: #f4f6fa;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--mvc-bg-body);
            color: #333333;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Top Navbar */
        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 20px;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }

        .brand-logo-area {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            padding: 2px 0;
        }

        .brand-logo-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: contain;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #eef2f6;
            flex-shrink: 0;
        }

        .brand-title-box {
            display: flex;
            flex-direction: column;
            justify-content: center;
            line-height: 1.25;
            white-space: nowrap;
        }

        .brand-title-th {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--mvc-maroon);
            margin: 0;
            letter-spacing: 0.25px;
            line-height: 1.25;
        }

        .brand-title-en {
            font-size: 0.74rem;
            font-weight: 600;
            color: #64748b;
            margin-top: 2px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .user-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--mvc-maroon);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
        }

        .user-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 1px;
        }

        /* Sidebar */
        .sidebar-container {
            background-color: #ffffff;
            min-height: calc(100vh - 65px);
            border-right: 1px solid #e9ecef;
            padding: 24px 15px;
        }

        .sidebar-profile {
            text-align: center;
            margin-bottom: 25px;
        }

        .sidebar-seal-img {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: contain;
            box-shadow: 0 4px 14px rgba(128, 0, 0, 0.12);
            margin-bottom: 12px;
            padding: 3px;
            background: white;
            border: 2px solid #eef2f6;
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

        /* Content Card */
        .main-content-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid #edf0f5;
            padding: 30px;
            margin-bottom: 40px;
        }

        .search-hero-box {
            background: linear-gradient(135deg, #fff 0%, #fff7f7 100%);
            border: 1.5px solid #f2d6d6;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .btn-mvc {
            background-color: var(--mvc-maroon);
            color: white;
            border: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-mvc:hover {
            background-color: var(--mvc-maroon-dark);
            color: white;
            transform: translateY(-1px);
        }

        .section-title {
            color: var(--mvc-maroon);
            font-size: 1.15rem;
            font-weight: 700;
            border-bottom: 2px solid #f1f3f5;
            padding-bottom: 6px;
            margin-top: 25px;
            margin-bottom: 16px;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            font-size: 0.88rem;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 6px 12px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .doc-item-card {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            background: #ffffff;
            margin-bottom: 15px;
            transition: border-color 0.2s;
        }

        .doc-item-card:hover {
            border-color: #cbd5e1;
        }

        .badge-status-pending {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .badge-status-approved {
            background-color: #d1fae5;
            color: #065f46;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .badge-status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        /* Official Print Sheet Styling */
        .official-print-wrapper {
            background: #e9ecef;
            padding: 25px 15px;
            border-radius: 12px;
            margin-top: 35px;
            border: 1px solid #dee2e6;
        }

        .official-print-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
        }

        .official-print-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 10mm 15mm 10mm 15mm;
            margin: 0 auto;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
            color: #000000;
            font-size: 11pt;
            line-height: 1.55;
        }

        .official-photo-box {
            position: absolute;
            right: 0;
            top: 0;
            width: 110px;
            height: 145px;
            border: 1.5px solid #000;
            background: #fff;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 5;
            box-sizing: border-box;
        }

        .official-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .official-title-block {
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            line-height: 1.45;
            margin-top: 2px;
            margin-bottom: 8px;
            padding-left: 115px;
            padding-right: 115px;
        }

        .print-section-title {
            font-size: 11.5pt;
            font-weight: bold;
            margin-top: 9px;
            margin-bottom: 3px;
            color: #000;
            line-height: 1.35;
        }

        .print-form-row {
            display: flex;
            align-items: baseline;
            font-size: 11pt;
            line-height: 1.9;
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .print-form-row .lbl {
            white-space: nowrap;
            padding-right: 4px;
            flex-shrink: 0;
        }

        .print-form-row .val {
            border-bottom: 1.5px dotted #000;
            padding: 0 6px 0 8px;
            text-align: left;
            font-weight: 500;
            color: #000;
            min-height: 20px;
            white-space: nowrap;
        }

        .circle-box {
            display: inline-block;
            width: 12.5px;
            height: 12.5px;
            border: 1.3px solid #000;
            border-radius: 50%;
            margin-right: 4px;
            margin-left: 2px;
            vertical-align: -1px;
            position: relative;
        }

        .circle-box.checked {
            background-color: #000;
            box-shadow: inset 0 0 0 2px #fff;
        }

        /* 2 Column Table for perfectly aligned checkboxes */
        .option-grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-left: 18px;
            margin-top: 2px;
            margin-bottom: 2px;
            font-size: 11pt;
        }

        .option-grid-table td {
            width: 50%;
            padding: 2px 0;
            vertical-align: middle;
        }

        @media print {
            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: 'Sarabun', 'TH Sarabun New', sans-serif !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .top-navbar, 
            .sidebar-container, 
            .no-print, 
            .search-hero-box, 
            .official-print-header-bar,
            .main-content-card > *:not(.official-print-wrapper),
            footer {
                display: none !important;
            }

            .container-fluid, .row, .main-content-col, .main-content-card {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            .official-print-wrapper {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                border: none !important;
            }

            .official-photo-box {
                right: 0 !important;
                top: 0 !important;
                width: 110px !important;
                height: 145px !important;
            }

            .official-print-sheet {
                box-shadow: none !important;
                width: 100% !important;
                min-height: auto !important;
                max-height: 285mm !important;
                padding: 0 !important;
                margin: 0 !important;
                page-break-after: avoid !important;
                page-break-before: avoid !important;
                page-break-inside: avoid !important;
            }

            @page {
                size: A4 portrait;
                margin: 8mm 12mm 8mm 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- Shared Unified Navbar & Mobile Bottom Navigation -->
    <?php 
      $current_page = 'print';
      require __DIR__ . '/includes/navbar.php'; 
    ?>

    <!-- Main Page Container with Sidebar -->
    <div class="container-fluid">
        <div class="row">
            
            <!-- Left Sidebar -->
            <div class="col-lg-2 col-md-3 sidebar-container no-print" id="sidebarMenu">
                <div class="sidebar-profile">
                    <img src="assets/images/mvc_round_seal.png?v=<?= time() ?>" alt="วิทยาลัยอาชีวศึกษามหาสารคาม" class="sidebar-seal-img">
                    <div class="sidebar-user-name">ระบบผู้สมัคร</div>
                    <div class="sidebar-user-status text-muted">บริการออนไลน์</div>

                    <!-- 4 Quick Action Buttons -->
                    <div class="quick-action-box">
                        <a href="index.php" class="quick-action-btn" title="หน้าแรก">
                            <i class="fa-solid fa-house"></i>
                        </a>
                        <a href="register.php" class="quick-action-btn" title="กรอกใบสมัคร">
                            <i class="fa-regular fa-envelope"></i>
                        </a>
                        <a href="<?= $is_admin ? 'admin.php' : 'login.php' ?>" class="quick-action-btn" title="ระบบเจ้าหน้าที่ / เข้าสู่ระบบ">
                            <i class="fa-regular fa-user"></i>
                        </a>
                        <a href="print_application.php" class="quick-action-btn active" title="พิมพ์ใบสมัคร">
                            <i class="fa-solid fa-print"></i>
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
                        <a href="programs.php" class="sidebar-nav-link">
                            <i class="fa-solid fa-graduation-cap"></i>
                            <span>สาขาวิชาที่เปิดรับ</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="register.php" class="sidebar-nav-link">
                            <i class="fa-solid fa-envelope-open-text"></i>
                            <span>กรอกใบสมัคร</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="status.php<?= !empty($applicant['id_card']) ? '?citizen_id=' . urlencode($applicant['id_card']) : '' ?>" class="sidebar-nav-link">
                            <i class="fa-solid fa-clipboard-check"></i>
                            <span>ตรวจสอบสถานะ</span>
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="print_application.php" class="sidebar-nav-link active">
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

            <!-- Right Main Content -->
            <div class="col-lg-10 col-md-9 py-4 px-lg-4 px-3 main-content-col">
                <div class="main-content-card">
                    
                    <!-- Search Box (Hero) -->
                    <div class="search-hero-box no-print">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-magnifying-glass text-danger fs-5"></i>
                            <h4 class="mb-0 fw-bold" style="color: var(--mvc-maroon);">ค้นหาประวัติการสมัคร / พิมพ์ใบสมัคร</h4>
                        </div>
                        <p class="text-secondary small mb-3">กรุณากรอกเลขประจำตัวประชาชน 13 หลัก หรือ เบอร์โทรศัพท์ที่ใช้ในการสมัครเรียน เพื่อตรวจสอบและพิมพ์ใบสมัคร</p>
                        
                        <form method="GET" action="print_application.php" class="row g-2 align-items-center">
                            <div class="col-md-8 col-sm-8">
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-regular fa-id-card text-muted"></i></span>
                                    <input type="text" name="search" class="form-control form-control-lg fs-6" 
                                           placeholder="เลขบัตรประชาชน 13 หลัก หรือ เบอร์โทรศัพท์..." 
                                           value="<?= htmlspecialchars($search_keyword) ?>" required autofocus>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-4">
                                <button type="submit" class="btn btn-mvc btn-lg w-100 fs-6">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> ค้นหาประวัติ
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($msg_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show no-print mb-4" role="alert">
                            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($msg_success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show no-print mb-4" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($msg_error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Result Display -->
                    <?php if ($search_performed && !$applicant): ?>
                        
                        <!-- Not Found Card -->
                        <div class="text-center py-5 no-print">
                            <div class="mb-3 text-danger">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="fw-bold mb-2" style="color: var(--mvc-maroon);">ไม่มีข้อมูลในระบบ หรือข้อมูลไม่ถูกต้อง</h3>
                            <p class="text-secondary mb-4">
                                ไม่พบข้อมูลการสมัครจากหมายเลข: <strong><?= htmlspecialchars($search_keyword) ?></strong><br>
                                กรุณาตรวจสอบหมายเลขบัตรประจำตัวประชาชน 13 หลัก หรือเบอร์โทรศัพท์ที่ใช้ในการสมัคร
                            </p>
                            <a href="print_application.php" class="btn btn-mvc px-5 py-2 fs-6 shadow-sm">
                                <i class="fa-solid fa-check me-1"></i> ตกลง
                            </a>
                        </div>

                        <!-- Modal Popup แจ้งเตือนเมื่อไม่พบข้อมูล -->
                        <div class="modal fade" id="notFoundModal" tabindex="-1" aria-labelledby="notFoundModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow border-0" style="border-radius: 12px; overflow: hidden;">
                                    <div class="modal-header border-0 bg-danger text-white py-3">
                                        <h5 class="modal-title fw-bold" id="notFoundModalLabel">
                                            <i class="fa-solid fa-circle-exclamation me-2"></i> แจ้งเตือนจากระบบ
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center py-4 px-4">
                                        <div class="mb-3 text-danger">
                                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 3.5rem;"></i>
                                        </div>
                                        <h4 class="fw-bold mb-2" style="color: var(--mvc-maroon);">ไม่มีข้อมูลในระบบ หรือข้อมูลไม่ถูกต้อง</h4>
                                        <p class="text-secondary mb-1">
                                            ไม่พบข้อมูลการสมัครจากหมายเลข <strong><?= htmlspecialchars($search_keyword) ?></strong>
                                        </p>
                                        <p class="text-muted small">
                                            กรุณาตรวจสอบหมายเลขบัตรประชาชน 13 หลัก หรือเบอร์โทรศัพท์ที่ใช้สมัครอีกครั้ง
                                        </p>
                                    </div>
                                    <div class="modal-footer border-0 justify-content-center pb-4">
                                        <button type="button" class="btn btn-mvc px-5 py-2 fs-6 shadow-sm" data-bs-dismiss="modal" onclick="focusSearchInput();">
                                            <i class="fa-solid fa-check me-1"></i> ตกลง
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($applicant): ?>

                        <!-- Web View: Applicant Information Overview (Read-Only) -->
                        <div class="no-print mb-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between p-3 rounded-3 mb-4" style="background-color: #fff5f5; border: 1.5px solid #fecaca;">
                                <div>
                                    <h4 class="fw-bold mb-1" style="color: var(--mvc-maroon);">
                                        <?= htmlspecialchars(($applicant['prefix'] ?? '') . ' ' . $applicant['firstname'] . ' ' . $applicant['lastname']) ?>
                                    </h4>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                        <span class="text-secondary small">เลขบัตรประชาชน: <strong><?= htmlspecialchars($applicant['id_card']) ?></strong></span>
                                        <span class="text-muted">|</span>
                                        <span class="text-secondary small">เบอร์โทร: <strong><?= htmlspecialchars($applicant['phone']) ?></strong></span>
                                        <span class="text-muted">|</span>
                                        <?php if ($applicant['apply_status'] == 'approved'): ?>
                                            <span class="badge-status-approved"><i class="fa-solid fa-circle-check me-1"></i> ผ่านการพิจารณา</span>
                                        <?php elseif ($applicant['apply_status'] == 'rejected'): ?>
                                            <span class="badge-status-rejected"><i class="fa-solid fa-circle-xmark me-1"></i> ไม่ผ่านการพิจารณา</span>
                                        <?php else: ?>
                                            <span class="badge-status-pending"><i class="fa-solid fa-clock me-1"></i> รอการตรวจสอบเอกสาร</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-3 mt-sm-0">
                                    <button type="button" class="btn btn-mvc px-4 py-2 shadow-sm fs-6" onclick="window.print()">
                                        <i class="fa-solid fa-print me-1"></i> พิมพ์ใบสมัคร (Print)
                                    </button>
                                </div>
                            </div>

                            <div class="alert alert-light border small text-secondary mb-4 py-2 px-3">
                                <i class="fa-solid fa-circle-info text-primary me-1"></i> ข้อมูลการสมัครไม่สามารถแก้ไขได้ หากต้องการแนบไฟล์เอกสารเพิ่มเติม สามารถดำเนินการได้ที่ <strong>ข้อที่ 6. เอกสารประกอบการสมัคร</strong> ด้านล่าง
                            </div>

                            <!-- ส่วนที่ 1: ข้อมูลผู้สมัคร -->
                            <div class="section-title">1. ข้อมูลผู้สมัคร</div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-2 col-sm-4">
                                    <label class="info-label">คำนำหน้าชื่อ</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['prefix']) ?></div>
                                </div>
                                <div class="col-md-5 col-sm-8">
                                    <label class="info-label">ชื่อ</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['firstname']) ?></div>
                                </div>
                                <div class="col-md-5 col-sm-12">
                                    <label class="info-label">นามสกุล</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['lastname']) ?></div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <label class="info-label">เลขประจำตัวประชาชน</label>
                                    <div class="info-value fw-bold text-dark"><?= htmlspecialchars($applicant['id_card']) ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">วัน/เดือน/ปีเกิด</label>
                                    <div class="info-value"><?= date('d/m/Y', strtotime($applicant['birth_date'])) ?></div>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <label class="info-label">อายุ</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['age'] ?? '-') ?> ปี</div>
                                </div>
                                <div class="col-md-3 col-sm-8">
                                    <label class="info-label">สัญชาติ / ศาสนา</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['nationality'] ?? 'ไทย') ?> / <?= htmlspecialchars($applicant['religion'] ?? 'พุทธ') ?></div>
                                </div>
                            </div>

                            <!-- ส่วนที่ 2: ที่อยู่ -->
                            <div class="section-title">2. ที่อยู่และการติดต่อ</div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-8 col-sm-12">
                                    <label class="info-label">ที่อยู่ตามทะเบียนบ้าน</label>
                                    <div class="info-value">
                                        เลขที่ <?= htmlspecialchars($applicant['addr_no'] ?? '-') ?>
                                        <?= !empty($applicant['addr_moo']) && $applicant['addr_moo'] !== '-' ? ' หมู่ ' . htmlspecialchars($applicant['addr_moo']) : '' ?>
                                        <?= !empty($applicant['addr_soi']) && $applicant['addr_soi'] !== '-' ? ' ซอย ' . htmlspecialchars($applicant['addr_soi']) : '' ?>
                                        ต.<?= htmlspecialchars($applicant['addr_subdistrict'] ?? '-') ?>
                                        อ.<?= htmlspecialchars($applicant['addr_district'] ?? '-') ?>
                                        จ.<?= htmlspecialchars($applicant['addr_province'] ?? '-') ?>
                                        <?= htmlspecialchars($applicant['addr_zipcode'] ?? '') ?>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-12">
                                    <label class="info-label">เบอร์โทรศัพท์มือถือ</label>
                                    <div class="info-value fw-bold" style="color: var(--mvc-maroon);"><?= htmlspecialchars($applicant['phone']) ?></div>
                                </div>
                            </div>

                            <!-- ส่วนที่ 3: ข้อมูลบิดา-มารดา -->
                            <div class="section-title">3. ข้อมูลบิดาและมารดา</div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border">
                                        <div class="fw-bold mb-2 text-dark"><i class="fa-solid fa-person me-1"></i> ข้อมูลบิดา</div>
                                        <div class="small text-secondary mb-1">ชื่อ-สกุล: <strong class="text-dark"><?= htmlspecialchars($father_name) ?></strong></div>
                                        <div class="small text-secondary mb-1">อาชีพ: <strong class="text-dark"><?= htmlspecialchars(valOrDash($applicant['father_occupation'] ?? '')) ?></strong></div>
                                        <div class="small text-secondary">เบอร์โทร: <strong class="text-dark"><?= htmlspecialchars(valOrDash($applicant['father_phone'] ?? '')) ?></strong></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border">
                                        <div class="fw-bold mb-2 text-dark"><i class="fa-solid fa-person-dress me-1"></i> ข้อมูลมารดา</div>
                                        <div class="small text-secondary mb-1">ชื่อ-สกุล: <strong class="text-dark"><?= htmlspecialchars($mother_name) ?></strong></div>
                                        <div class="small text-secondary mb-1">อาชีพ: <strong class="text-dark"><?= htmlspecialchars(valOrDash($applicant['mother_occupation'] ?? '')) ?></strong></div>
                                        <div class="small text-secondary">เบอร์โทร: <strong class="text-dark"><?= htmlspecialchars(valOrDash($applicant['mother_phone'] ?? '')) ?></strong></div>
                                    </div>
                                </div>
                            </div>

                            <!-- ส่วนที่ 4: ข้อมูลการศึกษา -->
                            <div class="section-title">4. ข้อมูลการศึกษาเดิม</div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">ระดับการศึกษาเดิม</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['prev_level'] ?? '-') ?></div>
                                </div>
                                <div class="col-md-5 col-sm-6">
                                    <label class="info-label">สถานศึกษาเดิม</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['prev_school'] ?? '-') ?></div>
                                </div>
                                <div class="col-md-4 col-sm-12">
                                    <label class="info-label">ที่ตั้งสถานศึกษาเดิม</label>
                                    <div class="info-value">
                                        <?= htmlspecialchars('ต.' . valOrDash($applicant['prev_subdistrict'] ?? '') . ' อ.' . valOrDash($applicant['prev_district'] ?? '') . ' จ.' . valOrDash($applicant['prev_province'] ?? '') . ' ' . valOrDash($applicant['prev_zipcode'] ?? '')) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">เบอร์โทรศัพท์สถานศึกษา</label>
                                    <div class="info-value"><?= htmlspecialchars(valOrDash($applicant['prev_phone'] ?? '')) ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">สาขาวิชา</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['prev_major'] ?? '-') ?></div>
                                </div>
                                <div class="col-md-2 col-sm-6">
                                    <label class="info-label">เกรดเฉลี่ย (GPAX)</label>
                                    <div class="info-value fw-bold text-primary"><?= number_format($applicant['gpax'], 2) ?></div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <label class="info-label">ความสามารถพิเศษ</label>
                                    <div class="info-value"><?= htmlspecialchars(valOrDash($applicant['special_ability'] ?? '')) ?></div>
                                </div>
                            </div>

                            <!-- ส่วนที่ 5: สาขาวิชาที่สมัคร -->
                            <div class="section-title">5. ข้อมูลการสมัครเรียน</div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">รอบการรับสมัคร</label>
                                    <div class="info-value"><?= htmlspecialchars($applicant['round_name'] ?? '-') ?> (ปีการศึกษา <?= htmlspecialchars($applicant['academic_year'] ?? '-') ?>)</div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">ระดับการศึกษาที่สมัคร</label>
                                    <div class="info-value fw-bold" style="color: var(--mvc-maroon);"><?= htmlspecialchars($applicant['level_name'] ?? '-') ?></div>
                                </div>
                                <?php if (!empty($applicant['pvs_type'])): ?>
                                <div class="col-md-3 col-sm-6">
                                    <label class="info-label">รูปแบบการรับสมัคร (ปวส.)</label>
                                    <div class="info-value fw-bold text-success"><?= htmlspecialchars($applicant['pvs_type']) ?></div>
                                </div>
                                <div class="col-md-3 col-sm-12">
                                <?php else: ?>
                                <div class="col-md-6 col-sm-12">
                                <?php endif; ?>
                                    <label class="info-label">สาขาวิชาที่เลือกสมัคร</label>
                                    <div class="info-value fw-bold text-primary"><?= htmlspecialchars($applicant['major_name'] ?? '-') ?></div>
                                </div>
                            </div>

                            <!-- ส่วนที่ 6: แนบไฟล์เอกสารเพิ่มเติม -->
                            <div class="section-title d-flex align-items-center justify-content-between">
                                <span>6. เอกสารประกอบการสมัคร (สามารถอัปโหลดไฟล์เพิ่มเติมได้)</span>
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;">ขนาดไม่เกิน 5MB</span>
                            </div>

                            <form method="POST" action="print_application.php" enctype="multipart/form-data" class="doc-upload-form mt-2">
                                <input type="hidden" name="action" value="upload_docs">
                                <input type="hidden" name="applicant_id" value="<?= $applicant['applicant_id'] ?>">
                                <input type="hidden" name="search_keyword" value="<?= htmlspecialchars($search_keyword) ?>">

                                <div class="row g-3">
                                    <!-- 1. บัตร ปชช. -->
                                    <div class="col-md-4">
                                        <div class="doc-item-card h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="fa-solid fa-address-card text-danger fs-5"></i>
                                                <span class="fw-bold text-dark">สำเนาบัตรประชาชน</span>
                                            </div>
                                            <div class="mb-2">
                                                <?php if (!empty($applicant['file_idcard'])): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> อัปโหลดแล้ว</span>
                                                    <div class="mt-2">
                                                        <a href="uploads/<?= htmlspecialchars($applicant['file_idcard']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa-solid fa-eye me-1"></i> ดูไฟล์เอกสาร
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-xmark me-1"></i> ยังไม่อัปโหลด</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label small text-muted">เลือกไฟล์ใหม่เพื่ออัปโหลด/เปลี่ยน:</label>
                                                <input type="file" name="file_idcard" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. ผลการเรียน -->
                                    <div class="col-md-4">
                                        <div class="doc-item-card h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="fa-solid fa-file-lines text-primary fs-5"></i>
                                                <span class="fw-bold text-dark">รายงานผลการเรียน (GPAX)</span>
                                            </div>
                                            <div class="mb-2">
                                                <?php if (!empty($applicant['file_transcript'])): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> อัปโหลดแล้ว</span>
                                                    <div class="mt-2">
                                                        <a href="uploads/<?= htmlspecialchars($applicant['file_transcript']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa-solid fa-eye me-1"></i> ดูไฟล์เอกสาร
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-xmark me-1"></i> ยังไม่อัปโหลด</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label small text-muted">เลือกไฟล์ใหม่เพื่ออัปโหลด/เปลี่ยน:</label>
                                                <input type="file" name="file_transcript" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3. รูปถ่ายหน้าตรง -->
                                    <div class="col-md-4">
                                        <div class="doc-item-card h-100">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="fa-solid fa-image text-success fs-5"></i>
                                                <span class="fw-bold text-dark">รูปถ่ายหน้าตรง 1 นิ้ว</span>
                                            </div>
                                            <div class="mb-2">
                                                <?php if (!empty($applicant['file_photo'])): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> อัปโหลดแล้ว</span>
                                                    <div class="mt-2">
                                                        <a href="uploads/<?= htmlspecialchars($applicant['file_photo']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa-solid fa-eye me-1"></i> ดูรูปถ่าย
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-xmark me-1"></i> ยังไม่อัปโหลด</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label small text-muted">เลือกไฟล์ใหม่เพื่ออัปโหลด/เปลี่ยน:</label>
                                                <input type="file" name="file_photo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-mvc px-4 py-2">
                                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> บันทึกและอัปโหลดเอกสาร
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Official Printable Sheet Area (Displayed as Preview on Screen & Cleanly on Print) -->
                        <div class="official-print-wrapper">
                            <div class="official-print-header-bar no-print">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-file-contract fs-5" style="color: var(--mvc-maroon);"></i>
                                    <strong style="color: var(--mvc-maroon); font-size: 1.1rem;">แบบฟอร์มใบสมัครสำหรับสั่งพิมพ์ (Official Application Form)</strong>
                                </div>
                                <button type="button" class="btn btn-mvc px-4 py-2 shadow-sm" onclick="window.print()">
                                    <i class="fa-solid fa-print me-1"></i> พิมพ์ใบสมัครนี้ (Print Form)
                                </button>
                            </div>

                            <!-- The Exact Form Matching The Official Document -->
                            <?php
                                $level_id = $applicant['level_id'] ?? 0;
                                $is_pvc = ($level_id == 1 || strpos($applicant['level_name'] ?? '', 'ปวช') !== false);
                                $is_pvs = ($level_id == 2 || strpos($applicant['level_name'] ?? '', 'ปวส') !== false);
                                $pvs_t = $applicant['pvs_type'] ?? '';
                            ?>
                            <div class="official-print-sheet">
                                
                                <!-- Top Header Container (Relative for 100% precise photo alignment) -->
                                <div style="position: relative; width: 100%; min-height: 145px; margin-bottom: 8px;">
                                    
                                    <!-- Top Right: Photo Frame (ขอบขวาตรงกับแนวเส้นตาราง/ข้อความพอดี) -->
                                    <div class="official-photo-box" style="position: absolute; right: 0; top: 0; width: 110px; height: 145px; border: 1.5px solid #000; background: #fff; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center; box-sizing: border-box; z-index: 5;">
                                        <?php if (!empty($applicant['file_photo']) && file_exists('uploads/' . $applicant['file_photo'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($applicant['file_photo']) ?>" alt="รูปถ่ายหน้าตรง" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="font-size: 9.5pt; color: #444; line-height: 1.35;">
                                                ติดรูปถ่าย<br>
                                                <span style="font-size: 8.5pt; color: #777;">๑ หรือ ๑.๕ นิ้ว</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Top Center: Logo & Title (มีระยะกันชนไม่ให้ทับรูปถ่าย) -->
                                    <div style="text-align: center; padding-left: 115px; padding-right: 115px;">
                                        <div style="margin-bottom: 4px;">
                                            <img src="assets/images/mvc_round_seal.png?v=<?= time() ?>" alt="ตราวิทยาลัยอาชีวศึกษามหาสารคาม" style="height: 60px; object-fit: contain;">
                                        </div>
                                        <div class="official-title-block" style="padding: 0; margin: 0; font-weight: bold; font-size: 13pt; line-height: 1.45;">
                                            ใบสมัครเข้าศึกษาต่อระดับประกาศนียบัตรวิชาชีพ <span class="circle-box <?= $is_pvc ? 'checked' : '' ?>"></span> ปวช.<br>
                                            และระดับประกาศนียบัตรวิชาชีพชั้นสูง <span class="circle-box <?= $is_pvs ? 'checked' : '' ?>"></span> ปวส.<br>
                                            โดยวิธีรับตรง (โควตา) ประจำปีงบประมาณศึกษา <?= toThaiNum($applicant['academic_year'] ?? '๒๕๗๐') ?><br>
                                            วิทยาลัยอาชีวศึกษามหาสารคาม
                                        </div>
                                    </div>

                                </div>

                                <!-- 1. ประวัติผู้สมัคร (เติมข้อมูลผู้สมัครครบถ้วน ตรงไหนว่างใส่ '-') -->
                                <div class="print-section-title">ประวัติผู้สมัคร</div>
                                
                                <div class="print-form-row">
                                    <span class="lbl">ชื่อ-สกุล (นาย-นางสาว)</span>
                                    <span class="val flex-grow-1" style="min-width: 170px;"><?= htmlspecialchars(valOrDash(($applicant['prefix'] ?? '') . ' ' . ($applicant['firstname'] ?? '') . ' ' . ($applicant['lastname'] ?? ''))) ?></span>
                                    <span class="lbl">เกิดวันที่</span>
                                    <span class="val" style="min-width: 32px;"><?= htmlspecialchars(valOrDash($birth['day'])) ?></span>
                                    <span class="lbl">เดือน</span>
                                    <span class="val" style="min-width: 85px;"><?= htmlspecialchars(valOrDash($birth['month'])) ?></span>
                                    <span class="lbl">พ.ศ.</span>
                                    <span class="val" style="min-width: 48px;"><?= htmlspecialchars(valOrDash($birth['year'])) ?></span>
                                    <span class="lbl">อายุ</span>
                                    <span class="val" style="min-width: 32px;"><?= htmlspecialchars(valOrDash($applicant['age'] ?? '')) ?></span>
                                    <span class="lbl">ปี</span>
                                </div>

                                <div class="print-form-row">
                                    <span class="lbl">ชื่อ-สกุล บิดา</span>
                                    <span class="val flex-grow-1" style="min-width: 240px;"><?= htmlspecialchars($father_name) ?></span>
                                    <span class="lbl">อาชีพ</span>
                                    <span class="val flex-grow-1" style="min-width: 220px;"><?= htmlspecialchars(valOrDash($applicant['father_occupation'] ?? '')) ?></span>
                                </div>

                                <div class="print-form-row">
                                    <span class="lbl">ชื่อ-สกุล มารดา</span>
                                    <span class="val flex-grow-1" style="min-width: 240px;"><?= htmlspecialchars($mother_name) ?></span>
                                    <span class="lbl">อาชีพ</span>
                                    <span class="val flex-grow-1" style="min-width: 220px;"><?= htmlspecialchars(valOrDash($applicant['mother_occupation'] ?? '')) ?></span>
                                </div>

                                <div class="print-form-row">
                                    <span class="lbl">ภูมิลำเนา บ้านเลขที่</span>
                                    <span class="val" style="min-width: 60px;"><?= htmlspecialchars(valOrDash($applicant['addr_no'] ?? '')) ?></span>
                                    <span class="lbl">หมู่ที่</span>
                                    <span class="val" style="min-width: 40px;"><?= htmlspecialchars(valOrDash($applicant['addr_moo'] ?? '')) ?></span>
                                    <span class="lbl">ตรอก/ซอย</span>
                                    <span class="val" style="min-width: 85px;"><?= htmlspecialchars(valOrDash($applicant['addr_soi'] ?? '')) ?></span>
                                    <span class="lbl">ถนน</span>
                                    <span class="val" style="min-width: 80px;"><?= htmlspecialchars(valOrDash($applicant['addr_road'] ?? '')) ?></span>
                                    <span class="lbl">ตำบล</span>
                                    <span class="val flex-grow-1" style="min-width: 95px;"><?= htmlspecialchars($subdistrict_clean) ?></span>
                                </div>

                                <div class="print-form-row">
                                    <span class="lbl">อำเภอ</span>
                                    <span class="val flex-grow-1" style="min-width: 105px;"><?= htmlspecialchars($district_clean) ?></span>
                                    <span class="lbl">จังหวัด</span>
                                    <span class="val flex-grow-1" style="min-width: 105px;"><?= htmlspecialchars($province_clean) ?></span>
                                    <span class="lbl">รหัสไปรษณีย์</span>
                                    <span class="val" style="min-width: 65px;"><?= htmlspecialchars(valOrDash($applicant['addr_zipcode'] ?? '')) ?></span>
                                    <span class="lbl">โทรศัพท์</span>
                                    <span class="val" style="min-width: 100px;"><?= htmlspecialchars(valOrDash($applicant['phone'] ?? '')) ?></span>
                                </div>

                                <!-- 2. สมัครเข้าศึกษาต่อในระดับ (จัดตาราง 2 คอลัมน์ให้วงกลมตรงกันเป๊ะ) -->
                                <div class="print-section-title">สมัครเข้าศึกษาต่อในระดับ</div>
                                <div style="font-size: 11pt; line-height: 1.8; margin-left: 6px;">
                                    <div>(<?= $is_pvc ? '<b style="font-size: 12pt;">/</b>' : '&nbsp;&nbsp;' ?>) ระดับ ปวช.</div>
                                    <table class="option-grid-table">
                                        <tr>
                                            <td><span class="circle-box <?= $is_pvc ? 'checked' : '' ?>"></span> ระดับ ปวช. จบ ม.๓ ระบบปกติ</td>
                                        </tr>
                                    </table>
                                    <div>(<?= $is_pvs ? '<b style="font-size: 12pt;">/</b>' : '&nbsp;&nbsp;' ?>) ระดับ ปวส.</div>
                                    <table class="option-grid-table">
                                        <tr>
                                            <td><span class="circle-box <?= (strpos($pvs_t, 'จบ ปวช. ระบบปกติ') !== false || strpos($pvs_t, 'จบปวช. ระบบปกติ') !== false) ? 'checked' : '' ?>"></span> ระดับ ปวส. จบ ปวช. ระบบปกติ</td>
                                            <td><span class="circle-box <?= (strpos($pvs_t, 'จบ ม.6 ระบบปกติ') !== false || strpos($pvs_t, 'จบ ม.๖ ระบบปกติ') !== false) ? 'checked' : '' ?>"></span> ระดับ ปวส. จบ ม.๖ ระบบปกติ</td>
                                        </tr>
                                        <tr>
                                            <td><span class="circle-box <?= (strpos($pvs_t, 'จบ ปวช. ระบบทวิภาคี') !== false) ? 'checked' : '' ?>"></span> ระดับ ปวส. จบ ปวช. ระบบทวิภาคี</td>
                                            <td><span class="circle-box <?= (strpos($pvs_t, 'จบ ม.6 ระบบทวิภาคี') !== false || strpos($pvs_t, 'จบ ม.๖ ระบบทวิภาคี') !== false) ? 'checked' : '' ?>"></span> ระดับ ปวส. จบ ม.๖ ระบบทวิภาคี</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="print-form-row" style="margin-top: 6px;">
                                    <span class="lbl">สมัครเข้าศึกษาต่อ สาขาวิชา</span>
                                    <span class="val flex-grow-1" style="min-width: 200px;"><?= htmlspecialchars(valOrDash($applicant['major_name'] ?? '')) ?></span>
                                    <span class="lbl">ระบบการเรียน</span>
                                    <span class="val" style="min-width: 120px; font-weight: bold;"><?= htmlspecialchars(!empty($applicant['curriculum_type']) ? $applicant['curriculum_type'] : ($is_pvs && strpos($pvs_t, 'ทวิภาคี') !== false ? 'ระบบทวิภาคี' : 'ระบบปกติ')) ?></span>
                                </div>

                                <!-- 3. ประวัติการศึกษา -->
                                <div class="print-section-title">ประวัติการศึกษา</div>
                                <div class="print-form-row">
                                    <span class="lbl">กำลังศึกษาอยู่โรงเรียน</span>
                                    <span class="val flex-grow-1" style="min-width: 250px;"><?= htmlspecialchars(valOrDash($applicant['prev_school'] ?? '')) ?></span>
                                    <span class="lbl">ตำบล</span>
                                    <span class="val flex-grow-1" style="min-width: 120px;"><?= htmlspecialchars(valOrDash($applicant['prev_subdistrict'] ?? '')) ?></span>
                                </div>
                                <div class="print-form-row">
                                    <span class="lbl">อำเภอ</span>
                                    <span class="val flex-grow-1" style="min-width: 115px;"><?= htmlspecialchars(valOrDash($applicant['prev_district'] ?? '')) ?></span>
                                    <span class="lbl">จังหวัด</span>
                                    <span class="val flex-grow-1" style="min-width: 115px;"><?= htmlspecialchars(valOrDash($applicant['prev_province'] ?? '')) ?></span>
                                    <span class="lbl">รหัสไปรษณีย์</span>
                                    <span class="val" style="min-width: 70px;"><?= htmlspecialchars(valOrDash($applicant['prev_zipcode'] ?? '')) ?></span>
                                    <span class="lbl">โทรศัพท์</span>
                                    <span class="val" style="min-width: 100px;"><?= htmlspecialchars(valOrDash($applicant['prev_phone'] ?? '')) ?></span>
                                </div>
                                <div class="print-form-row">
                                    <span class="lbl">สาขาวิชา(ถ้ามี)</span>
                                    <span class="val flex-grow-1" style="min-width: 180px;"><?= htmlspecialchars(valOrDash($applicant['prev_major'] ?? '')) ?></span>
                                    <span class="lbl">ผลการเรียนเฉลี่ยสะสม</span>
                                    <span class="val flex-grow-1" style="min-width: 130px;"><?= !empty($applicant['gpax']) ? number_format($applicant['gpax'], 2) : '-' ?></span>
                                </div>
                                <div class="print-form-row">
                                    <span class="lbl">ความสามารถพิเศษ(โปรดระบุ)</span>
                                    <span class="val flex-grow-1" style="min-width: 300px;"><?= htmlspecialchars(valOrDash($applicant['special_ability'] ?? '')) ?></span>
                                </div>

                                <!-- 4. เอกสารหลักฐานที่ส่งมาพร้อมใบสมัคร -->
                                <div class="print-section-title">เอกสารหลักฐานที่ส่งมาพร้อมใบสมัคร</div>
                                <div style="font-size: 11pt; line-height: 1.85; margin-left: 6px;">
                                    <div><span class="circle-box"></span> ใบแสดงผลการเรียน หรือใบรับรองผลการเรีนน จำนวน <span class="val" style="min-width: 60px; display: inline-block;">&nbsp;</span> ฉบับ</div>
                                    <div><span class="circle-box"></span> รูปถ่ายขนาด ๑ นิ้ว หรือ ๑.๕ นิ้วจำนวน ๑ รูป (ติดใบสมัคร)</div>
                                    <div><span class="circle-box"></span> หลักฐานอื่นๆ <span class="val" style="min-width: 60px; display: inline-block;">&nbsp;</span> ฉบับ (โปรดระบุ) <span class="val flex-grow-1" style="min-width: 250px; display: inline-block;">&nbsp;</span></div>
                                </div>

                                <!-- 5. การพิจารณาคัดเลือก -->
                                <div class="print-section-title" style="font-size: 11pt;">
                                    หากการพิจารณาคัดเลือกแล้ว ไม่ได้รับการคัดเลือกตามสาขาวิชาที่สมัครไว้ ผู้สมัคร
                                </div>
                                <div style="font-size: 11pt; line-height: 1.85; margin-left: 6px;">
                                    <div><span class="circle-box"></span> ยินยอม ให้วิทยาลัยอาชีวศึกษามหาสารคามจัดสาขาวิชาอื่นให้ผู้สมัคร</div>
                                    <div><span class="circle-box"></span> ไม่ยินยอม ให้วิทยาลัยอาชีวศึกษามหาสารคามจัดสาขาวิชาอื่นให้ผู้สมัคร</div>
                                </div>

                                <!-- 6. ช่องลงชื่อ (มีจุดไข่ปลาชัดเจน จัดระยะพอดีกับหน้ากระดาษ) -->
                                <div style="margin-top: 26px; margin-right: 25px; text-align: right; font-size: 11pt; line-height: 2.1;">
                                    <div style="display: inline-block; text-align: center;">
                                        ลงชื่อ ...................................................................... ผู้สมัคร<br>
                                        ( ...................................................................... )<br>
                                        วันที่ .......... เดือน ........................................ พ.ศ. ....................
                                    </div>
                                </div>

                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Initial state before search -->
                        <div class="text-center py-5 no-print text-muted">
                            <i class="fa-solid fa-id-card-clip" style="font-size: 3.5rem; color: #cbd5e1;"></i>
                            <h5 class="mt-3 text-secondary">กรุณากรอกเลขบัตรประจำตัวประชาชน หรือเบอร์โทรศัพท์ เพื่อค้นหา</h5>
                            <p class="small text-muted">ระบบจะค้นหาประวัติการสมัคร พร้อมทั้งแสดงแบบฟอร์มใบสมัครสำหรับสั่งพิมพ์</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($search_performed && !$applicant): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('notFoundModal');
            if (modalEl) {
                var notFoundModal = new bootstrap.Modal(modalEl);
                notFoundModal.show();
            }
        });

        function focusSearchInput() {
            var input = document.querySelector('input[name="search"]');
            if (input) {
                input.focus();
                input.select();
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>
