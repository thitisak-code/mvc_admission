<?php
/**
 * Homepage CMS & Blog Settings Helper
 * จัดการข้อมูลเนื้อหา ข้อความ รูปภาพ และบล็อกต่างๆ ในหน้า index.php
 * วิทยาลัยอาชีวศึกษามหาสารคาม
 */

require_once __DIR__ . '/database.php';

/**
 * ค่าเริ่มต้นมาตรฐานสำหรับหน้า index.php (ตรงกับเนื้อหาหลักของระบบ)
 */
function getDefaultHomepageSettings(): array {
    return [
        // ---------------------------------------------------------------------
        // บล็อก 1: แถบประกาศด้านบนสุด (Topbar)
        // ---------------------------------------------------------------------
        'topbar_address'       => 'วิทยาลัยอาชีวศึกษามหาสารคาม สถาบันการอาชีวศึกษาภาคตะวันออกเฉียงเหนือ 3',
        'topbar_phone'         => '043-711-371',
        'topbar_badge'         => 'ปีการศึกษา 2570',

        // ---------------------------------------------------------------------
        // บล็อก 2: โลโก้และแถบเมนู (Navbar)
        // ---------------------------------------------------------------------
        'site_logo'            => 'img/Logo-W.png',
        'navbar_title'         => 'ระบบรับสมัครนักศึกษา',
        'navbar_subtitle'      => 'วิทยาลัยอาชีวศึกษามหาสารคาม',

        // ---------------------------------------------------------------------
        // บล็อก 3: แบนเนอร์หลัก & Hero Section
        // ---------------------------------------------------------------------
        'hero_badge'           => 'เปิดรับสมัครรอบโควตาและรอบทั่วไป ประจำปีการศึกษา 2570',
        'hero_title'           => 'สร้างอนาคตวิชาชีพสู่มืออาชีพ',
        'hero_highlight'       => 'รับสมัครนักเรียน นักศึกษาใหม่',
        'hero_subtitle'        => 'วิทยาลัยอาชีวศึกษามหาสารคาม (Mahasarakham Vocational College)',
        'hero_description'     => 'สมัครเรียนออนไลน์ง่าย สะดวก รวดเร็ว เลือกระดับการศึกษา ปวช., ปวส. หรือ ปริญญาตรี พร้อมส่งเอกสารและติดตามผลการพิจารณาได้ครบจบในระบบเดียว',
        'hero_btn1_text'       => 'สมัครเรียนออนไลน์ทันที',
        'hero_btn1_link'       => 'register.php',
        'hero_btn2_text'       => 'ขั้นตอนการสมัคร',
        'hero_btn2_link'       => '#steps',
        'hero_badges_text'     => 'ระดับ ปวช., ระดับ ปวส., ระดับ ปริญญาตรี',
        'hero_image'           => 'img/hero_20261028_065259_a6a57dcf.png',
        'hero_card_icon'       => 'bi-award-fill',
        'hero_card_title'      => 'มาตรฐานอาชีวศึกษาชั้นนำ',
        'hero_card_desc'       => 'มุ่งเน้นทักษะจริงสู่ตลาดแรงงานสากล',

        // ---------------------------------------------------------------------
        // บล็อก 4: บล็อกบริการและขั้นตอน 4 รายการ (Services & Steps Cards / Blogs)
        // ---------------------------------------------------------------------
        'services_eyebrow'     => 'Online Admission Services',
        'services_title'       => 'บริการและขั้นตอนสำหรับผู้สมัคร',
        'services_desc'        => 'เข้าถึงทุกข้อมูลที่จำเป็นสำหรับการสมัครเรียนได้อย่างรวดเร็ว ถูกต้องตามระเบียบการ',

        // Card 1
        'card1_title'          => 'สมัครเรียนออนไลน์',
        'card1_desc'           => 'กรอกข้อมูลประวัติส่วนตัว เลือกแผนกวิชาที่ต้องการศึกษาต่อ และอัปโหลดรูปถ่าย',
        'card1_icon'           => 'bi-pencil-square',
        'card1_image'          => '',
        'card1_btn_text'       => 'เริ่มกรอกใบสมัคร',
        'card1_link'           => 'register.php',
        'card1_featured'       => '1',

        // Card 2
        'card2_title'          => 'ประกาศรับสมัคร',
        'card2_desc'           => 'ตรวจสอบรอบการรับสมัคร คุณสมบัติเฉพาะสาขา เกณฑ์คะแนน และจำนวนรับแต่ละแผนก',
        'card2_icon'           => 'bi-megaphone-fill',
        'card2_image'          => '',
        'card2_btn_text'       => 'ดูประกาศและคุณสมบัติ',
        'card2_link'           => '#announcements',
        'card2_featured'       => '0',

        // Card 3
        'card3_title'          => 'พิมพ์ใบสมัคร / เอกสาร',
        'card3_desc'           => 'ค้นหาข้อมูลใบสมัคร แนบเอกสารหลักฐานเพิ่มเติม และสั่งพิมพ์ใบสมัครฉบับจริง',
        'card3_icon'           => 'bi-printer-fill',
        'card3_image'          => '',
        'card3_btn_text'       => 'พิมพ์ใบสมัคร / แนบเอกสาร',
        'card3_link'           => 'print_application.php',
        'card3_featured'       => '0',

        // Card 4
        'card4_title'          => 'ตรวจสอบสถานะ',
        'card4_desc'           => 'ติดตามความคืบหน้าการตรวจสอบเอกสาร ผลการคัดเลือก และสถานะการรับสมัคร',
        'card4_icon'           => 'bi-search',
        'card4_image'          => '',
        'card4_btn_text'       => 'ค้นหาด้วยเลขบัตรประชาชน',
        'card4_link'           => 'status.php',
        'card4_featured'       => '0',

        // ---------------------------------------------------------------------
        // บล็อก 5: ประกาศกำหนดการสำคัญ (Notice Box)
        // ---------------------------------------------------------------------
        'notice_badge'         => 'สำคัญ',
        'notice_title'         => 'กำหนดการยื่นใบสมัครรอบแรก',
        'notice_desc'          => 'เปิดรับสมัครตั้งแต่วันนี้ ถึง 28 กุมภาพันธ์ 2570 กรุณาตรวจสอบความถูกต้องของข้อมูลก่อนยืนยันการสมัคร',
        'notice_btn_text'      => 'ศูนย์ช่วยเหลือผู้สมัคร',
        'notice_btn_link'      => '#contact',

        // ---------------------------------------------------------------------
        // บล็อก 6: ข้อมูลติดต่อและส่วนท้าย (Footer & Contact)
        // ---------------------------------------------------------------------
        'footer_org_name'      => 'วิทยาลัยอาชีวศึกษามหาสารคาม',
        'footer_org_en'        => 'Mahasarakham Vocational College',
        'footer_org_desc'      => 'ระบบบริการรับสมัครนักเรียน นักศึกษาใหม่ออนไลน์ ประจำปีการศึกษา 2570',
        'footer_address'       => 'ถนนนครสวรรค์ ตำบลตลาด อำเภอเมือง จังหวัดมหาสารคาม 44000',
        'footer_phone'         => 'งานทะเบียน: 043-711-371',
        'footer_email'         => 'admission@mvc.ac.th',
        'footer_hours'         => 'วันจันทร์ - ศุกร์: 08:30 - 16:30 น. (เว้นวันหยุดราชการ)',
        'footer_copyright'     => '© 2570 วิทยาลัยอาชีวศึกษามหาสารคาม. สงวนลิขสิทธิ์ทุกประการ.',
        'footer_developer'     => 'งานศูนย์ดิจิทัลวิทยาลัยอาชีวศึกษามหาสารคาม',
    ];
}

/**
 * สร้างตาราง homepage_settings หากยังไม่มีในฐานข้อมูล
 */
function ensureHomepageTableExists(PDO $pdo): void {
    static $created = false;
    if ($created) {
        return;
    }

    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS `homepage_settings` (
                `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
                `setting_value` MEDIUMTEXT NULL,
                `setting_group` VARCHAR(50) DEFAULT 'general',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
        $created = true;
    } catch (PDOException $e) {
        error_log("Failed to ensure homepage_settings table: " . $e->getMessage());
    }
}

/**
 * ดึงการตั้งค่าทั้งหมดของหน้าแรก โดยผสานกับค่าเริ่มต้น
 */
function getHomepageSettings(?PDO $pdo = null): array {
    $defaults = getDefaultHomepageSettings();

    if ($pdo === null) {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return $defaults;
        }
    }

    try {
        ensureHomepageTableExists($pdo);

        $stmt = $pdo->query("SELECT setting_key, setting_value FROM homepage_settings");
        $dbRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (empty($dbRows)) {
            // Seed ข้อมูลเริ่มต้นลงฐานข้อมูลครั้งแรก
            $insertStmt = $pdo->prepare("
                INSERT INTO homepage_settings (setting_key, setting_value, setting_group)
                VALUES (:k, :v, :g)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");

            foreach ($defaults as $key => $val) {
                $group = 'general';
                if (str_starts_with($key, 'topbar_')) $group = 'topbar';
                elseif (str_starts_with($key, 'hero_')) $group = 'hero';
                elseif (str_starts_with($key, 'card') || str_starts_with($key, 'services_')) $group = 'services';
                elseif (str_starts_with($key, 'notice_')) $group = 'notice';
                elseif (str_starts_with($key, 'footer_')) $group = 'footer';

                $insertStmt->execute([':k' => $key, ':v' => $val, ':g' => $group]);
            }

            return $defaults;
        }

        // Merge ข้อมูลจากฐานข้อมูลเข้ากับ Defaults
        return array_merge($defaults, $dbRows);
    } catch (PDOException $e) {
        error_log("Error loading homepage settings: " . $e->getMessage());
        return $defaults;
    }
}

/**
 * จัดการอัปโหลดไฟล์รูปภาพอย่างปลอดภัย
 */
function handleHomepageImageUpload(array $file, string $prefix = 'img'): ?string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // กำหนดโฟลเดอร์ปลายทางให้เก็บในโฟลเดอร์ img/
    $uploadDir = __DIR__ . '/../img/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            error_log("Cannot create upload directory: " . $uploadDir);
            return null;
        }
    }

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 8 MB)
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException("ขนาดไฟล์รูปภาพเกินขีดจำกัด (สูงสุด 8MB)");
    }

    // ตรวจสอบ MIME type และนามสกุลไฟล์
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg'    => 'jpg',
        'image/pjpeg'   => 'jpg',
        'image/png'     => 'png',
        'image/x-png'   => 'png',
        'image/webp'    => 'webp',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
    ];

    if (!isset($allowedMimes[$mime])) {
        throw new RuntimeException("รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP, GIF, SVG) เท่านั้น");
    }

    $ext = $allowedMimes[$mime];
    $filename = sprintf('%s_%s_%s.%s', $prefix, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException("ไม่สามารถบันทึกไฟล์รูปภาพไปยังเซิร์ฟเวอร์ได้");
    }

    // ส่งกลับ Relative path สำหรับบันทึกในฐานข้อมูลและแสดงผลบนเว็บ (โฟลเดอร์ img/)
    return 'img/' . $filename;
}

/**
 * บันทึกการตั้งค่าหน้าแรกทั้งหมดจากการส่ง Form ใน Admin
 */
function saveHomepageSettings(PDO $pdo, array $post, array $files): array {
    ensureHomepageTableExists($pdo);
    $current = getHomepageSettings($pdo);
    $defaults = getDefaultHomepageSettings();

    // รายการคีย์ที่เป็นรูปภาพที่สามารถอัปโหลดได้
    $imageFields = [
        'hero_image'  => 'hero',
        'site_logo'   => 'logo',
        'card1_image' => 'card1',
        'card2_image' => 'card2',
        'card3_image' => 'card3',
        'card4_image' => 'card4',
    ];

    $newSettings = [];

    // 1. รับค่าข้อความทั่วไปจาก $_POST
    foreach ($defaults as $key => $defaultVal) {
        if (isset($imageFields[$key])) {
            continue; // จัดการรูปภาพแยกต่างหาก
        }

        if (array_key_exists($key, $post)) {
            $newSettings[$key] = trim((string)$post[$key]);
        }
    }

    // จัดการ Checkbox Featured ของการ์ด
    $newSettings['card1_featured'] = isset($post['card1_featured']) ? '1' : '0';
    $newSettings['card2_featured'] = isset($post['card2_featured']) ? '1' : '0';
    $newSettings['card3_featured'] = isset($post['card3_featured']) ? '1' : '0';
    $newSettings['card4_featured'] = isset($post['card4_featured']) ? '1' : '0';

    // 2. จัดการรูปภาพ (อัปโหลดใหม่ / ลบรูป / ใช้รูปเดิม)
    foreach ($imageFields as $imgKey => $prefix) {
        // หากผู้ใช้ติ๊กเลือกลบรูปภาพ หรือรีเซ็ตเป็นค่าเริ่มต้น
        if (!empty($post['remove_' . $imgKey])) {
            $newSettings[$imgKey] = ($imgKey === 'site_logo' || $imgKey === 'hero_image') ? $defaults[$imgKey] : '';
            continue;
        }

        // ตรวจสอบว่ามีการอัปโหลดไฟล์ใหม่หรือไม่
        if (isset($files[$imgKey]) && is_array($files[$imgKey]) && $files[$imgKey]['error'] === UPLOAD_ERR_OK) {
            $uploadedPath = handleHomepageImageUpload($files[$imgKey], $prefix);
            if ($uploadedPath !== null) {
                $newSettings[$imgKey] = $uploadedPath;
                continue;
            }
        }

        // หากมีการระบุ URL หรือ Path เองในช่องข้อความ
        if (isset($post[$imgKey . '_custom']) && trim((string)$post[$imgKey . '_custom']) !== '') {
            $newSettings[$imgKey] = trim((string)$post[$imgKey . '_custom']);
            continue;
        }

        // ถ้าไม่มีการเปลี่ยน ให้คงค่าเดิมไว้
        $newSettings[$imgKey] = $current[$imgKey] ?? $defaults[$imgKey];
    }

    // 3. บันทึกลงฐานข้อมูล
    $stmt = $pdo->prepare("
        INSERT INTO homepage_settings (setting_key, setting_value, setting_group)
        VALUES (:k, :v, :g)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($newSettings as $k => $v) {
        $group = 'general';
        if (str_starts_with($k, 'topbar_')) $group = 'topbar';
        elseif (str_starts_with($k, 'hero_')) $group = 'hero';
        elseif (str_starts_with($k, 'card') || str_starts_with($k, 'services_')) $group = 'services';
        elseif (str_starts_with($k, 'notice_')) $group = 'notice';
        elseif (str_starts_with($k, 'footer_')) $group = 'footer';

        $stmt->execute([':k' => $k, ':v' => $v, ':g' => $group]);
    }

    return array_merge($current, $newSettings);
}

/**
 * รีเซ็ตการตั้งค่าหน้าแรกทั้งหมดกลับสู่ค่าเริ่มต้นของระบบ
 */
function resetHomepageSettings(PDO $pdo): void {
    ensureHomepageTableExists($pdo);
    $defaults = getDefaultHomepageSettings();

    $stmt = $pdo->prepare("
        INSERT INTO homepage_settings (setting_key, setting_value, setting_group)
        VALUES (:k, :v, :g)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($defaults as $k => $v) {
        $group = 'general';
        if (str_starts_with($k, 'topbar_')) $group = 'topbar';
        elseif (str_starts_with($k, 'hero_')) $group = 'hero';
        elseif (str_starts_with($k, 'card') || str_starts_with($k, 'services_')) $group = 'services';
        elseif (str_starts_with($k, 'notice_')) $group = 'notice';
        elseif (str_starts_with($k, 'footer_')) $group = 'footer';

        $stmt->execute([':k' => $k, ':v' => $v, ':g' => $group]);
    }
}

