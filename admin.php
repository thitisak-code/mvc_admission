<?php
/**
 * หน้าผู้ดูแลระบบ (Admin Dashboard) | ระบบรับสมัครนักเรียน นักศึกษา
 * วิทยาลัยอาชีวศึกษามหาสารคาม
 */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/homepage_settings.php';
require_once __DIR__ . '/config/round_helper.php';

// 1. ตรวจสอบสิทธิ์เฉพาะผู้ดูแลระบบ (Admin / Officer)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: login.php?error=" . urlencode("กรุณาเข้าสู่ระบบในฐานะผู้ดูแลระบบก่อนเข้าใช้งาน"));
    exit;
}

$admin_id   = (int)($_SESSION['user_id'] ?? 0);
$admin_name = $_SESSION['user_name'] ?? 'ผู้ดูแลระบบ';
$admin_role = $_SESSION['role'] ?? 'officer';

$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$err = isset($_GET['err']) ? trim($_GET['err']) : '';

$allowed_tabs = ['applicants', 'departments', 'rounds', 'admins', 'homepage'];
$current_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs, true) ? $_GET['tab'] : 'applicants';
$dept_view   = isset($_GET['view']) && in_array($_GET['view'], ['matrix', 'pvc', 'pvs', 'deg'], true) ? $_GET['view'] : 'matrix';

// -------------------------------------------------------------------------
// การจัดการปีการศึกษาภาพรวม (Academic Year Scope & Management)
// -------------------------------------------------------------------------
$available_academic_years = [];
try {
    $available_academic_years = $pdo->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll();
    if (empty($available_academic_years)) {
        $pdo->exec("
            INSERT INTO academic_years (year_name, is_active, description) VALUES
            ('2570', 1, 'ปีการศึกษา 2570 (รอบรับสมัครปัจจุบัน)'),
            ('2569', 0, 'ปีการศึกษา 2569 (ข้อมูลย้อนหลัง)'),
            ('2571', 0, 'ปีการศึกษา 2571 (เตรียมการรับสมัครล่วงหน้า)')
        ");
        $available_academic_years = $pdo->query("SELECT * FROM academic_years ORDER BY year_name DESC")->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Academic Years Table Load Error: " . $e->getMessage());
}

// หาปีการศึกษาที่เปิดรับสมัครหลักของระบบ (Active)
$default_active_year = '2570';
foreach ($available_academic_years as $ay) {
    if (!empty($ay['is_active'])) {
        $default_active_year = $ay['year_name'];
        break;
    }
}

// กำหนดปีการศึกษาที่กำลังเลือกดู / จัดการ (ภาพรวม)
if (isset($_GET['academic_year']) && !empty($_GET['academic_year'])) {
    $selected_year = trim($_GET['academic_year']);
    $_SESSION['admin_academic_year'] = $selected_year;
} elseif (isset($_SESSION['admin_academic_year']) && !empty($_SESSION['admin_academic_year'])) {
    $selected_year = $_SESSION['admin_academic_year'];
} else {
    $selected_year = $default_active_year;
    $_SESSION['admin_academic_year'] = $selected_year;
}

// ตรวจสอบว่าปีที่เลือกมีอยู่จริงหรือไม่ หากไม่มีให้ใช้ default
$year_exists = false;
foreach ($available_academic_years as $ay) {
    if ($ay['year_name'] === $selected_year) {
        $year_exists = true;
        break;
    }
}
if (!$year_exists && !empty($available_academic_years)) {
    $selected_year = $available_academic_years[0]['year_name'];
    $_SESSION['admin_academic_year'] = $selected_year;
}

// =========================================================================
// 2. จัดการคำขอ POST (POST ACTION HANDLERS)
// =========================================================================

// 2.1 จัดการแก้ไขสถานะผู้สมัคร (POST: update_status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);
    $new_status   = trim($_POST['status'] ?? '');
    $status_note  = trim($_POST['status_note'] ?? '');

    $allowed_statuses = ['pending', 'approved', 'document_incomplete', 'paid', 'rejected'];

    if ($applicant_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE applicants 
                SET status = :status, status_note = :status_note, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status'      => $new_status,
                ':status_note' => $status_note,
                ':id'          => $applicant_id
            ]);
            header("Location: admin.php?tab=applicants&msg=" . urlencode("อัปเดตสถานะของผู้สมัครเรียบร้อยแล้ว"));
            exit;
        } catch (PDOException $e) {
            $err = "เกิดข้อผิดพลาดในการอัปเดต: " . $e->getMessage();
        }
    } else {
        $err = "ข้อมูลการเปลี่ยนสถานะไม่ถูกต้อง";
    }
}

// 2.1.1 จัดการลบข้อมูลผู้สมัครเรียน (POST: delete_applicant)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_applicant') {
    $applicant_id = (int)($_POST['applicant_id'] ?? 0);

    if ($applicant_id <= 0) {
        header("Location: admin.php?tab=applicants&err=" . urlencode("รหัสผู้สมัครไม่ถูกต้อง"));
        exit;
    }

    try {
        $cur = $pdo->prepare("SELECT id, application_no, prefix, first_name, last_name FROM applicants WHERE id = :id");
        $cur->execute([':id' => $applicant_id]);
        $target_app = $cur->fetch();

        if (!$target_app) {
            header("Location: admin.php?tab=applicants&err=" . urlencode("ไม่พบข้อมูลผู้สมัครที่ต้องการลบ"));
            exit;
        }

        // ลบเอกสารที่ผูกอยู่และลบข้อมูลผู้สมัคร
        $pdo->prepare("DELETE FROM applicant_documents WHERE applicant_id = :id")->execute([':id' => $applicant_id]);
        $pdo->prepare("DELETE FROM applicants WHERE id = :id")->execute([':id' => $applicant_id]);

        $app_full = $target_app['prefix'] . $target_app['first_name'] . ' ' . $target_app['last_name'];
        header("Location: admin.php?tab=applicants&msg=" . urlencode("ลบข้อมูลผู้สมัคร '{$app_full}' ({$target_app['application_no']}) เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=applicants&err=" . urlencode("เกิดข้อผิดพลาดในการลบผู้สมัคร: " . $e->getMessage()));
        exit;
    }
}

// 2.2 จัดการสลับสถานะเปิด/ปิดรับสมัครสาขาวิชา (POST: toggle_program_status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_program_status') {
    $prog_id = (int)($_POST['program_id'] ?? 0);
    $view    = trim($_POST['view'] ?? 'matrix');

    if ($prog_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id, status, program_name, education_level FROM programs WHERE id = :id");
            $stmt->execute([':id' => $prog_id]);
            $prog = $stmt->fetch();

            if ($prog) {
                $new_status = $prog['status'] === 'open' ? 'closed' : 'open';
                $upd = $pdo->prepare("UPDATE programs SET status = :st WHERE id = :id");
                $upd->execute([':st' => $new_status, ':id' => $prog_id]);

                $st_text = $new_status === 'open' ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร';
                header("Location: admin.php?tab=departments&view={$view}&msg=" . urlencode("ปรับสถานะ '{$prog['program_name']}' ({$prog['education_level']}) เป็น [{$st_text}] เรียบร้อยแล้ว"));
                exit;
            }
        } catch (PDOException $e) {
            header("Location: admin.php?tab=departments&view={$view}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
            exit;
        }
    }
}

// 2.2.1 จัดการเพิ่มปีการศึกษาใหม่ (POST: add_academic_year)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_academic_year') {
    $year_name = trim($_POST['year_name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($year_name)) {
        header("Location: admin.php?tab=departments&err=" . urlencode("กรุณาระบุปีการศึกษา (เช่น 2571)"));
        exit;
    }

    try {
        if ($is_active) {
            $pdo->exec("UPDATE academic_years SET is_active = 0");
        }
        $stmt = $pdo->prepare("
            INSERT INTO academic_years (year_name, is_active, description) 
            VALUES (:yr, :act, :desc) 
            ON DUPLICATE KEY UPDATE description = :desc, is_active = :act
        ");
        $stmt->execute([':yr' => $year_name, ':act' => $is_active, ':desc' => !empty($desc) ? $desc : "ปีการศึกษา {$year_name}"]);
        
        $_SESSION['admin_academic_year'] = $year_name;
        header("Location: admin.php?tab=departments&academic_year={$year_name}&msg=" . urlencode("เพิ่มปีการศึกษา {$year_name} เข้าสู่ระบบและสลับเป็นภาพรวมเรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.2.2 จัดการตั้งปีการศึกษาเปิดรับสมัครหลักของระบบ (POST: set_active_academic_year)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_active_academic_year') {
    $year_name = trim($_POST['year_name'] ?? '');
    if (!empty($year_name)) {
        try {
            $pdo->exec("UPDATE academic_years SET is_active = 0");
            $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 1 WHERE year_name = :yr");
            $stmt->execute([':yr' => $year_name]);
            $_SESSION['admin_academic_year'] = $year_name;
            header("Location: admin.php?tab=departments&academic_year={$year_name}&msg=" . urlencode("ตั้งปีการศึกษา {$year_name} เป็นปีการศึกษาเปิดรับสมัครหลักเรียบร้อยแล้ว"));
            exit;
        } catch (PDOException $e) {
            header("Location: admin.php?tab=departments&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
            exit;
        }
    }
}

// 2.2.3 จัดการติดตั้งแผนกวิชาและสาขาวิชามาตรฐาน MVC (POST: seed_standard_departments)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_standard_departments') {
    $year = trim($_POST['academic_year'] ?? $selected_year);
    if (!empty($year)) {
        try {
            $standard_depts = [
                [
                    'code' => 'DBT',
                    'name' => 'ภาควิชาเทคโนโลยีธุรกิจดิจิทัล',
                    'desc' => 'มุ่งเน้นการพัฒนาทักษะดิจิทัล นวัตกรรมเทคโนโลยี การประยุกต์ธุรกิจดิจิทัล และเทคโนโลยีสารสนเทศสมัยใหม่',
                    'programs' => [
                        ['code' => 'DBT-101', 'name' => 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล', 'level' => 'ปวช.', 'seats' => 45],
                        ['code' => 'DBT-201', 'name' => 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล', 'level' => 'ปวส.', 'seats' => 40],
                        ['code' => 'DBT-301', 'name' => 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล (ทล.บ.)', 'level' => 'ปริญญาตรี', 'seats' => 30],
                    ]
                ],
                [
                    'code' => 'ACC',
                    'name' => 'ภาควิชาการบัญชี',
                    'desc' => 'มุ่งเน้นมาตรฐานการบัญชี การบัญชีภาษีอากร การใช้โปรแกรมคอมพิวเตอร์ในงานบัญชี และการตรวจสอบบัญชี',
                    'programs' => [
                        ['code' => 'ACC-101', 'name' => 'สาขาวิชาการบัญชี', 'level' => 'ปวช.', 'seats' => 50],
                        ['code' => 'ACC-201', 'name' => 'สาขาวิชาการบัญชี', 'level' => 'ปวส.', 'seats' => 45],
                    ]
                ],
                [
                    'code' => 'MKT',
                    'name' => 'ภาควิชาการตลาด',
                    'desc' => 'มุ่งเน้นการวางแผนกลยุทธ์การตลาด การตลาดดิจิทัล การสร้างแบรนด์ และการค้าออนไลน์',
                    'programs' => [
                        ['code' => 'MKT-101', 'name' => 'สาขาวิชาการตลาด', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'MKT-201', 'name' => 'สาขาวิชาการตลาด', 'level' => 'ปวส.', 'seats' => 40],
                        ['code' => 'MKT-301', 'name' => 'สาขาวิชาการตลาด (ทล.บ.)', 'level' => 'ปริญญาตรี', 'seats' => 30],
                    ]
                ],
                [
                    'code' => 'OFC',
                    'name' => 'ภาควิชาการจัดการสำนักงาน',
                    'desc' => 'มุ่งเน้นงานสารบรรณอิเล็กทรอนิกส์ การจัดการสำนักงานดิจิทัล งานเลขานุการ และการประสานงานองค์กร',
                    'programs' => [
                        ['code' => 'OFC-101', 'name' => 'สาขาวิชาการจัดการสำนักงาน', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'OFC-201', 'name' => 'สาขาวิชาการจัดการสำนักงานดิจิทัล', 'level' => 'ปวส.', 'seats' => 40],
                        ['code' => 'OFC-301', 'name' => 'สาขาวิชาการจัดการสำนักงานดิจิทัล (ทล.บ.)', 'level' => 'ปริญญาตรี', 'seats' => 30],
                    ]
                ],
                [
                    'code' => 'RET',
                    'name' => 'ภาควิชาธุรกิจค้าปลีก',
                    'desc' => 'มุ่งเน้นการจัดการธุรกิจค้าปลีกสมัยใหม่ ระบบบริหารร้านสะดวกซื้อ การส่งเสริมการขาย และผู้ประกอบการรุ่นใหม่',
                    'programs' => [
                        ['code' => 'RET-101', 'name' => 'สาขาวิชาธุรกิจค้าปลีก', 'level' => 'ปวช.', 'seats' => 35],
                        ['code' => 'RET-201', 'name' => 'สาขาวิชาการจัดการธุรกิจค้าปลีก', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
                [
                    'code' => 'CG',
                    'name' => 'ภาควิชาคอมพิวเตอร์กราฟิก',
                    'desc' => 'มุ่งเน้นการออกแบบกราฟิก 2D/3D แอนิเมชัน การตัดต่อวิดีโอ สื่อดิจิทัล และวิชวลเอฟเฟกต์',
                    'programs' => [
                        ['code' => 'CG-101', 'name' => 'สาขาวิชาคอมพิวเตอร์กราฟิก', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'CG-201', 'name' => 'สาขาวิชาคอมพิวเตอร์กราฟิก', 'level' => 'ปวส.', 'seats' => 35],
                        ['code' => 'CG-301', 'name' => 'สาขาวิชาคอมพิวเตอร์กราฟิก (ทล.บ.)', 'level' => 'ปริญญาตรี', 'seats' => 30],
                    ]
                ],
                [
                    'code' => 'DES',
                    'name' => 'ภาควิชาการออกแบบ',
                    'desc' => 'มุ่งเน้นการออกแบบผลิตภัณฑ์ การออกแบบตกแต่งภายใน นิเทศศิลป์ และการเขียนแบบสถาปัตย์',
                    'programs' => [
                        ['code' => 'DES-101', 'name' => 'สาขาวิชาการออกแบบ', 'level' => 'ปวช.', 'seats' => 35],
                        ['code' => 'DES-201', 'name' => 'สาขาวิชาการออกแบบ', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
                [
                    'code' => 'ART',
                    'name' => 'ภาควิชาวิจิตรศิลป์',
                    'desc' => 'มุ่งเน้นจิตรกรรมไทย-สากล ประติมากรรม ศิลปะภาพพิมพ์ จิตรกรรมสีน้ำ และงานแกะสลักศิลปกรรม',
                    'programs' => [
                        ['code' => 'ART-101', 'name' => 'สาขาวิชาวิจิตรศิลป์', 'level' => 'ปวช.', 'seats' => 30],
                        ['code' => 'ART-201', 'name' => 'สาขาวิชาวิจิตรศิลป์', 'level' => 'ปวส.', 'seats' => 30],
                    ]
                ],
                [
                    'code' => 'PHT',
                    'name' => 'ภาควิชาการถ่ายภาพและมัลติมีเดีย',
                    'desc' => 'มุ่งเน้นการถ่ายภาพในสตูดิโอ การผลิตสื่อวิดีโอ การจัดระบบไลฟ์สด ครีเอทีฟคอนเทนต์ และมัลติมีเดียโปรดักชัน',
                    'programs' => [
                        ['code' => 'PHT-101', 'name' => 'สาขาวิชาการถ่ายภาพและมัลติมีเดีย', 'level' => 'ปวช.', 'seats' => 35],
                        ['code' => 'PHT-201', 'name' => 'สาขาวิชาการถ่ายภาพและมัลติมีเดีย', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
                [
                    'code' => 'FND',
                    'name' => 'ภาควิชาอาหารและโภชนาการ',
                    'desc' => 'มุ่งเน้นอาหารไทย อาหารนานาชาติ การแต่งหน้าเค้กและเบเกอรี่ ธุรกิจจัดเลี้ยง และโภชนาการเพื่อสุขภาพ',
                    'programs' => [
                        ['code' => 'FND-101', 'name' => 'สาขาวิชาอาหารและโภชนาการ', 'level' => 'ปวช.', 'seats' => 45],
                        ['code' => 'FND-201', 'name' => 'สาขาวิชาอาหารและโภชนาการ', 'level' => 'ปวส.', 'seats' => 40],
                    ]
                ],
                [
                    'code' => 'LOG',
                    'name' => 'ภาควิชาการจัดการโลจิสติกส์และซัพพลายเชน',
                    'desc' => 'มุ่งเน้นการจัดการคลังสินค้า ระบบการขนส่ง การจัดซื้อ-จัดหา และการบริหารจัดการห่วงโซ่อุปทาน',
                    'programs' => [
                        ['code' => 'LOG-101', 'name' => 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'LOG-201', 'name' => 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน', 'level' => 'ปวส.', 'seats' => 40],
                    ]
                ],
                [
                    'code' => 'HTL',
                    'name' => 'ภาควิชาการโรงแรมและบริการ',
                    'desc' => 'มุ่งเน้นการบริการอาหารและเครื่องดื่ม บาร์เทนเดอร์ ศิลปะการชงกาแฟ งานส่วนหน้า และการจัดการโรงแรมมาตรฐาน',
                    'programs' => [
                        ['code' => 'HTL-101', 'name' => 'สาขาวิชาการโรงแรม', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'HTL-201', 'name' => 'สาขาวิชาการโรงแรมและบริการ', 'level' => 'ปวส.', 'seats' => 40],
                    ]
                ],
                [
                    'code' => 'TRV',
                    'name' => 'ภาควิชาการท่องเที่ยว',
                    'desc' => 'มุ่งเน้นวิชาชีพมัคคุเทศก์ ภาษาอังกฤษเพื่อการท่องเที่ยว ธุรกิจนำเที่ยว และการจัดการการท่องเที่ยวสากล',
                    'programs' => [
                        ['code' => 'TRV-101', 'name' => 'สาขาวิชาการท่องเที่ยว', 'level' => 'ปวช.', 'seats' => 40],
                        ['code' => 'TRV-201', 'name' => 'สาขาวิชาการท่องเที่ยว', 'level' => 'ปวส.', 'seats' => 40],
                    ]
                ],
                [
                    'code' => 'FSH',
                    'name' => 'ภาควิชาแฟชั่นและสิ่งทอ',
                    'desc' => 'มุ่งเน้นการออกแบบและตัดเย็บเสื้อผ้า แฟชั่นดีไซเนอร์ ช่างแพทเทิร์น สไตลิสต์ และการจัดแสดงแฟชั่น',
                    'programs' => [
                        ['code' => 'FSH-101', 'name' => 'สาขาวิชาแฟชั่นและสิ่งทอ', 'level' => 'ปวช.', 'seats' => 35],
                        ['code' => 'FSH-201', 'name' => 'สาขาวิชาแฟชั่นและสิ่งทอ', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
                [
                    'code' => 'HEC',
                    'name' => 'ภาควิชาคหกรรมศาสตร์',
                    'desc' => 'มุ่งเน้นศิลปะการจัดดอกไม้ งานใบตองเพื่อธุรกิจ ศิลปะประดิษฐ์ งานแกะสลัก และการจัดตกแต่งสถานที่ครบวงจร',
                    'programs' => [
                        ['code' => 'HEC-101', 'name' => 'สาขาวิชาคหกรรมศาสตร์', 'level' => 'ปวช.', 'seats' => 35],
                        ['code' => 'HEC-201', 'name' => 'สาขาวิชาคหกรรมศาสตร์', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
                [
                    'code' => 'ELD',
                    'name' => 'ภาควิชาการดูแลผู้สูงอายุ',
                    'desc' => 'มุ่งเน้นการบริบาลและฟื้นฟูสุขภาพผู้สูงอายุ ภาษาและวัฒนธรรมญี่ปุ่นเพื่อการทำงาน โอกาสฝึกงานและทำงาน ณ ประเทศญี่ปุ่น',
                    'programs' => [
                        ['code' => 'ELD-201', 'name' => 'สาขาวิชาการดูแลผู้สูงอายุ', 'level' => 'ปวส.', 'seats' => 35],
                    ]
                ],
            ];

            foreach ($standard_depts as $d) {
                $chk = $pdo->prepare("SELECT id FROM departments WHERE dept_code = :c AND academic_year = :y");
                $chk->execute([':c' => $d['code'], ':y' => $year]);
                $dept_id = $chk->fetchColumn();

                if (!$dept_id) {
                    $ins_d = $pdo->prepare("INSERT INTO departments (dept_code, dept_name, description, academic_year) VALUES (:c, :n, :desc, :y)");
                    $ins_d->execute([':c' => $d['code'], ':n' => $d['name'], ':desc' => $d['desc'], ':y' => $year]);
                    $dept_id = (int)$pdo->lastInsertId();
                }

                foreach ($d['programs'] as $p) {
                    $chk_p = $pdo->prepare("SELECT id FROM programs WHERE dept_id = :did AND program_code = :pc AND academic_year = :y");
                    $chk_p->execute([':did' => $dept_id, ':pc' => $p['code'], ':y' => $year]);
                    if (!$chk_p->fetchColumn()) {
                        $ins_p = $pdo->prepare("INSERT INTO programs (dept_id, program_code, program_name, education_level, seats_available, status, academic_year) VALUES (:did, :pc, :pn, :lvl, :st, 'open', :y)");
                        $ins_p->execute([':did' => $dept_id, ':pc' => $p['code'], ':pn' => $p['name'], ':lvl' => $p['level'], ':st' => $p['seats'], ':y' => $year]);
                    }
                }
            }

            header("Location: admin.php?tab=departments&academic_year={$year}&msg=" . urlencode("ติดตั้งแผนกวิชาและสาขาวิชามาตรฐานทางการ MVC ครบ 16 ภาควิชา (35 สาขาวิชา) เข้าสู่ปีการศึกษา {$year} เรียบร้อยแล้ว"));
            exit;
        } catch (PDOException $e) {
            header("Location: admin.php?tab=departments&academic_year={$year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
            exit;
        }
    }
}

// 2.2.4 จัดการคัดลอกโครงสร้างแผนกและสาขาวิชาจากปีอื่น (POST: clone_departments_from_year)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clone_departments_from_year') {
    $src_year    = trim($_POST['source_year'] ?? '');
    $target_year = trim($_POST['target_year'] ?? $selected_year);

    if (empty($src_year) || empty($target_year) || $src_year === $target_year) {
        header("Location: admin.php?tab=departments&academic_year={$target_year}&err=" . urlencode("กรุณาเลือกปีการศึกษาต้นทางและปลายทางที่ถูกต้องและไม่ซ้ำกัน"));
        exit;
    }

    try {
        $src_depts = $pdo->prepare("SELECT * FROM departments WHERE academic_year = :y ORDER BY id ASC");
        $src_depts->execute([':y' => $src_year]);
        $all_src = $src_depts->fetchAll();

        if (empty($all_src)) {
            header("Location: admin.php?tab=departments&academic_year={$target_year}&err=" . urlencode("ไม่พบข้อมูลแผนกวิชาในปีการศึกษาต้นทาง ({$src_year})"));
            exit;
        }

        $count_dept_cloned = 0;
        $count_prog_cloned = 0;

        foreach ($all_src as $sd) {
            $chk = $pdo->prepare("SELECT id FROM departments WHERE dept_code = :c AND academic_year = :y");
            $chk->execute([':c' => $sd['dept_code'], ':y' => $target_year]);
            $new_dept_id = $chk->fetchColumn();

            if (!$new_dept_id) {
                $ins_d = $pdo->prepare("INSERT INTO departments (dept_code, dept_name, description, academic_year) VALUES (:c, :n, :desc, :y)");
                $ins_d->execute([':c' => $sd['dept_code'], ':n' => $sd['dept_name'], ':desc' => $sd['description'], ':y' => $target_year]);
                $new_dept_id = (int)$pdo->lastInsertId();
                $count_dept_cloned++;
            }

            $src_progs = $pdo->prepare("SELECT * FROM programs WHERE dept_id = :did AND academic_year = :y");
            $src_progs->execute([':did' => $sd['id'], ':y' => $src_year]);
            $all_sp = $src_progs->fetchAll();

            foreach ($all_sp as $sp) {
                $chk_p = $pdo->prepare("SELECT id FROM programs WHERE dept_id = :did AND program_code = :pc AND academic_year = :y");
                $chk_p->execute([':did' => $new_dept_id, ':pc' => $sp['program_code'], ':y' => $target_year]);
                if (!$chk_p->fetchColumn()) {
                    $ins_p = $pdo->prepare("INSERT INTO programs (dept_id, program_code, program_name, education_level, seats_available, status, academic_year) VALUES (:did, :pc, :pn, :lvl, :st, :status, :y)");
                    $ins_p->execute([
                        ':did'    => $new_dept_id,
                        ':pc'     => $sp['program_code'],
                        ':pn'     => $sp['program_name'],
                        ':lvl'    => $sp['education_level'],
                        ':st'     => $sp['seats_available'],
                        ':status' => $sp['status'],
                        ':y'      => $target_year
                    ]);
                    $count_prog_cloned++;
                }
            }
        }

        header("Location: admin.php?tab=departments&academic_year={$target_year}&msg=" . urlencode("คัดลอกสำเร็จ: เพิ่ม {$count_dept_cloned} แผนกวิชา และ {$count_prog_cloned} สาขาวิชา จากปี {$src_year} เข้าสู่ปี {$target_year} เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&academic_year={$target_year}&err=" . urlencode("เกิดข้อผิดพลาดในการคัดลอก: " . $e->getMessage()));
        exit;
    }
}

// 2.3 จัดการเพิ่มสาขาวิชาใหม่ / เปิดรับสมัครระดับใหม่ (POST: add_program)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_program') {
    $dept_id       = (int)($_POST['dept_id'] ?? 0);
    $program_code  = trim($_POST['program_code'] ?? '');
    $program_name  = trim($_POST['program_name'] ?? '');
    $edu_level     = trim($_POST['education_level'] ?? '');
    $program_type  = trim($_POST['program_type'] ?? 'ปกติ');
    $seats         = (int)($_POST['seats_available'] ?? 40);
    $status        = trim($_POST['status'] ?? 'open');
    $view          = trim($_POST['view'] ?? 'matrix');
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    $valid_levels = ['ปวช.', 'ปวส.', 'ปริญญาตรี'];
    $valid_types  = ['ปกติ', 'ทวิภาคี', 'ปกติและทวิภาคี'];
    if (!in_array($program_type, $valid_types, true)) {
        $program_type = 'ปกติ';
    }

    if ($dept_id <= 0 || empty($program_code) || empty($program_name) || !in_array($edu_level, $valid_levels, true)) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("กรุณากรอกข้อมูลสาขาวิชาให้ครบถ้วน"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO programs (dept_id, program_code, program_name, education_level, program_type, seats_available, status, academic_year)
            VALUES (:dept_id, :code, :name, :level, :type, :seats, :status, :year)
        ");
        $stmt->execute([
            ':dept_id' => $dept_id,
            ':code'    => strtoupper($program_code),
            ':name'    => $program_name,
            ':level'   => $edu_level,
            ':type'    => $program_type,
            ':seats'   => $seats > 0 ? $seats : 40,
            ':status'  => in_array($status, ['open', 'closed'], true) ? $status : 'open',
            ':year'    => $academic_year,
        ]);
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&msg=" . urlencode("เปิดรับสมัครสาขาวิชา '{$program_name}' ({$edu_level} - {$program_type}) ปีการศึกษา {$academic_year} เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.4 จัดการแก้ไขสาขาวิชา (POST: edit_program)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_program') {
    $prog_id      = (int)($_POST['program_id'] ?? 0);
    $dept_id      = (int)($_POST['dept_id'] ?? 0);
    $program_code = trim($_POST['program_code'] ?? '');
    $program_name = trim($_POST['program_name'] ?? '');
    $edu_level    = trim($_POST['education_level'] ?? '');
    $program_type = trim($_POST['program_type'] ?? 'ปกติ');
    $seats        = (int)($_POST['seats_available'] ?? 40);
    $status       = trim($_POST['status'] ?? 'open');
    $view         = trim($_POST['view'] ?? 'matrix');
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    $valid_levels = ['ปวช.', 'ปวส.', 'ปริญญาตรี'];
    $valid_types  = ['ปกติ', 'ทวิภาคี', 'ปกติและทวิภาคี'];
    if (!in_array($program_type, $valid_types, true)) {
        $program_type = 'ปกติ';
    }

    if ($prog_id <= 0 || $dept_id <= 0 || empty($program_code) || empty($program_name) || !in_array($edu_level, $valid_levels, true)) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("ข้อมูลการแก้ไขสาขาวิชาไม่ถูกต้อง"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE programs 
            SET dept_id = :dept_id, program_code = :code, program_name = :name, education_level = :level, program_type = :type, seats_available = :seats, status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':dept_id' => $dept_id,
            ':code'    => strtoupper($program_code),
            ':name'    => $program_name,
            ':level'   => $edu_level,
            ':type'    => $program_type,
            ':seats'   => $seats > 0 ? $seats : 40,
            ':status'  => in_array($status, ['open', 'closed'], true) ? $status : 'open',
            ':id'      => $prog_id,
        ]);
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&msg=" . urlencode("แก้ไขข้อมูลสาขาวิชา '{$program_name}' ({$program_type}) เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.5 จัดการลบสาขาวิชา (POST: delete_program)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_program') {
    $prog_id = (int)($_POST['program_id'] ?? 0);
    $view    = trim($_POST['view'] ?? 'matrix');
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    if ($prog_id <= 0) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("รหัสสาขาวิชาไม่ถูกต้อง"));
        exit;
    }

    try {
        // ตรวจสอบว่ามีผู้สมัครผูกอยู่หรือไม่
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE program_id = {$prog_id}")->fetchColumn();
        if ($cnt > 0) {
            header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("ไม่สามารถลบสาขานี้ได้เนื่องจากมีผู้สมัครลงทะเบียนอยู่ {$cnt} คน (แนะนำให้กดเลือก 'ปิดรับสมัคร' แทน)"));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = :id");
        $stmt->execute([':id' => $prog_id]);
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&msg=" . urlencode("ลบสาขาวิชาเรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาดในการลบ: " . $e->getMessage()));
        exit;
    }
}

// 2.6 จัดการเพิ่มแผนกวิชาใหม่ (POST: add_department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_department') {
    $dept_code     = trim($_POST['dept_code'] ?? '');
    $dept_name     = trim($_POST['dept_name'] ?? '');
    $desc          = trim($_POST['description'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    if (empty($academic_year)) {
        header("Location: admin.php?tab=departments&err=" . urlencode("กรุณาเลือกปีการศึกษาก่อน แล้วค่อยเพิ่มแผนกวิชา"));
        exit;
    }

    if (empty($dept_code) || empty($dept_name)) {
        header("Location: admin.php?tab=departments&academic_year={$academic_year}&err=" . urlencode("กรุณากรอกรหัสแผนกและชื่อแผนกวิชา"));
        exit;
    }

    try {
        // ตรวจสอบว่ามีรหัสแผนกซ้ำในปีการศึกษานี้หรือไม่
        $chk = $pdo->prepare("SELECT id FROM departments WHERE dept_code = :code AND academic_year = :year");
        $chk->execute([':code' => strtoupper($dept_code), ':year' => $academic_year]);
        if ($chk->fetch()) {
            header("Location: admin.php?tab=departments&academic_year={$academic_year}&err=" . urlencode("รหัสแผนก '{$dept_code}' มีอยู่ในปีการศึกษา {$academic_year} แล้ว"));
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO departments (dept_code, dept_name, description, academic_year) VALUES (:code, :name, :desc, :year)");
        $stmt->execute([
            ':code' => strtoupper($dept_code),
            ':name' => $dept_name,
            ':desc' => !empty($desc) ? $desc : null,
            ':year' => $academic_year,
        ]);
        header("Location: admin.php?tab=departments&academic_year={$academic_year}&msg=" . urlencode("เพิ่มแผนกวิชา '{$dept_name}' ในปีการศึกษา {$academic_year} เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.7 จัดการแก้ไขแผนกวิชา (POST: edit_department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_department') {
    $dept_id   = (int)($_POST['dept_id'] ?? 0);
    $dept_code = trim($_POST['dept_code'] ?? '');
    $dept_name = trim($_POST['dept_name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');

    if ($dept_id <= 0 || empty($dept_code) || empty($dept_name)) {
        header("Location: admin.php?tab=departments&err=" . urlencode("ข้อมูลแผนกวิชาไม่ถูกต้อง"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE departments SET dept_code = :code, dept_name = :name, description = :desc WHERE id = :id");
        $stmt->execute([
            ':code' => strtoupper($dept_code),
            ':name' => $dept_name,
            ':desc' => !empty($desc) ? $desc : null,
            ':id'   => $dept_id
        ]);
        header("Location: admin.php?tab=departments&academic_year={$selected_year}&msg=" . urlencode("แก้ไขแผนกวิชา '{$dept_name}' เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&academic_year={$selected_year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.7.1 จัดการลบแผนกวิชา (POST: delete_department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_department') {
    $dept_id       = (int)($_POST['dept_id'] ?? 0);
    $view          = trim($_POST['view'] ?? 'matrix');
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    if ($dept_id <= 0) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("รหัสแผนกวิชาไม่ถูกต้อง"));
        exit;
    }

    try {
        $cur = $pdo->prepare("SELECT id, dept_code, dept_name, academic_year FROM departments WHERE id = :id");
        $cur->execute([':id' => $dept_id]);
        $target_dept = $cur->fetch();

        if (!$target_dept) {
            header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("ไม่พบแผนกวิชาที่ต้องการลบ"));
            exit;
        }

        // ตรวจสอบว่ามีผู้สมัครเรียนในสาขาวิชาที่สังกัดแผนกนี้หรือไม่
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM applicants a
            JOIN programs p ON a.program_id = p.id
            WHERE p.dept_id = :dept_id
        ");
        $check_stmt->execute([':dept_id' => $dept_id]);
        $applicant_count = (int)$check_stmt->fetchColumn();

        if ($applicant_count > 0) {
            header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("ไม่สามารถลบแผนกวิชา '{$target_dept['dept_name']}' ได้ เนื่องจากมีผู้สมัครเรียนสังกัดสาขาวิชาในแผนกนี้อยู่ {$applicant_count} คน (เพื่อรักษาความถูกต้องของข้อมูล)"));
            exit;
        }

        // ลบสาขาวิชาทั้งหมดในแผนกนี้ก่อน (เนื่องจากไม่มีผู้สมัครแล้ว)
        $pdo->prepare("DELETE FROM programs WHERE dept_id = :dept_id")->execute([':dept_id' => $dept_id]);
        // ลบแผนกวิชา
        $pdo->prepare("DELETE FROM departments WHERE id = :id")->execute([':id' => $dept_id]);

        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&msg=" . urlencode("ลบแผนกวิชา '{$target_dept['dept_name']}' เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=departments&view={$view}&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาดในการลบแผนกวิชา: " . $e->getMessage()));
        exit;
    }
}

// 2.8 จัดการเพิ่มผู้ดูแลระบบใหม่ (POST: add_admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $new_username = trim($_POST['username'] ?? '');
    $new_fullname = trim($_POST['full_name'] ?? '');
    $new_email    = trim($_POST['email'] ?? '');
    $new_role     = trim($_POST['role'] ?? 'officer');
    $new_password = $_POST['password'] ?? '';
    $confirm_pw   = $_POST['confirm_password'] ?? '';

    if (empty($new_username) || empty($new_fullname) || empty($new_password)) {
        header("Location: admin.php?tab=admins&err=" . urlencode("กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (ชื่อผู้ใช้, ชื่อ-สกุล, รหัสผ่าน)"));
        exit;
    } elseif ($new_password !== $confirm_pw) {
        header("Location: admin.php?tab=admins&err=" . urlencode("รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง"));
        exit;
    } elseif (strlen($new_password) < 6) {
        header("Location: admin.php?tab=admins&err=" . urlencode("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร"));
        exit;
    } else {
        try {
            $chk = $pdo->prepare("SELECT id FROM admins WHERE username = :u LIMIT 1");
            $chk->execute([':u' => $new_username]);
            if ($chk->fetch()) {
                header("Location: admin.php?tab=admins&err=" . urlencode("ชื่อผู้ใช้ '{$new_username}' มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น"));
                exit;
            }

            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("
                INSERT INTO admins (username, password, full_name, email, role) 
                VALUES (:u, :p, :fn, :em, :ro)
            ");
            $ins->execute([
                ':u'  => $new_username,
                ':p'  => $hashed,
                ':fn' => $new_fullname,
                ':em' => !empty($new_email) ? $new_email : null,
                ':ro' => in_array($new_role, ['superadmin', 'officer'], true) ? $new_role : 'officer'
            ]);

            header("Location: admin.php?tab=admins&msg=" . urlencode("เพิ่มผู้ดูแลระบบ '{$new_fullname}' สำเร็จเรียบร้อยแล้ว"));
            exit;
        } catch (PDOException $e) {
            header("Location: admin.php?tab=admins&err=" . urlencode("เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . $e->getMessage()));
            exit;
        }
    }
}

// 2.9 จัดการแก้ไขข้อมูลผู้ดูแลระบบ (POST: edit_admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    $target_id     = (int)($_POST['admin_id'] ?? 0);
    $edit_fullname = trim($_POST['full_name'] ?? '');
    $edit_email    = trim($_POST['email'] ?? '');
    $edit_role     = trim($_POST['role'] ?? 'officer');
    $edit_password = $_POST['password'] ?? '';

    if ($target_id <= 0 || empty($edit_fullname)) {
        header("Location: admin.php?tab=admins&err=" . urlencode("ข้อมูลการแก้ไขไม่ถูกต้อง กรุณากรอกชื่อ-สกุลให้ครบถ้วน"));
        exit;
    }

    try {
        $cur = $pdo->prepare("SELECT id, username, role FROM admins WHERE id = :id");
        $cur->execute([':id' => $target_id]);
        $target_admin = $cur->fetch();

        if (!$target_admin) {
            header("Location: admin.php?tab=admins&err=" . urlencode("ไม่พบข้อมูลผู้ดูแลระบบที่ต้องการแก้ไข"));
            exit;
        }

        if ($target_admin['role'] === 'superadmin' && $edit_role !== 'superadmin') {
            $super_cnt = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'")->fetchColumn();
            if ($super_cnt <= 1) {
                header("Location: admin.php?tab=admins&err=" . urlencode("ไม่สามารถลดสิทธิ์ผู้ดูแลระบบสูงสุด (Superadmin) คนสุดท้ายของระบบได้"));
                exit;
            }
        }

        $valid_role = in_array($edit_role, ['superadmin', 'officer'], true) ? $edit_role : 'officer';

        if (!empty($edit_password)) {
            if (strlen($edit_password) < 6) {
                header("Location: admin.php?tab=admins&err=" . urlencode("รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร"));
                exit;
            }
            $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("
                UPDATE admins 
                SET full_name = :fn, email = :em, role = :ro, password = :pw 
                WHERE id = :id
            ");
            $upd->execute([
                ':fn' => $edit_fullname,
                ':em' => !empty($edit_email) ? $edit_email : null,
                ':ro' => $valid_role,
                ':pw' => $hashed,
                ':id' => $target_id
            ]);
        } else {
            $upd = $pdo->prepare("
                UPDATE admins 
                SET full_name = :fn, email = :em, role = :ro 
                WHERE id = :id
            ");
            $upd->execute([
                ':fn' => $edit_fullname,
                ':em' => !empty($edit_email) ? $edit_email : null,
                ':ro' => $valid_role,
                ':id' => $target_id
            ]);
        }

        if ($target_id === $admin_id) {
            $_SESSION['user_name'] = $edit_fullname;
            $_SESSION['role']      = $valid_role;
            $admin_name            = $edit_fullname;
            $admin_role            = $valid_role;
        }

        header("Location: admin.php?tab=admins&msg=" . urlencode("แก้ไขข้อมูลผู้ดูแลระบบ '{$edit_fullname}' เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=admins&err=" . urlencode("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage()));
        exit;
    }
}

// 2.10 จัดการลบผู้ดูแลระบบ (POST: delete_admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    $delete_id = (int)($_POST['admin_id'] ?? 0);

    if ($delete_id <= 0) {
        header("Location: admin.php?tab=admins&err=" . urlencode("รหัสผู้ดูแลระบบไม่ถูกต้อง"));
        exit;
    }

    if ($delete_id === $admin_id) {
        header("Location: admin.php?tab=admins&err=" . urlencode("คุณไม่สามารถลบบัญชีของตนเองที่กำลังเข้าสู่ระบบอยู่ได้"));
        exit;
    }

    try {
        $cur = $pdo->prepare("SELECT id, username, full_name, role FROM admins WHERE id = :id");
        $cur->execute([':id' => $delete_id]);
        $target_admin = $cur->fetch();

        if (!$target_admin) {
            header("Location: admin.php?tab=admins&err=" . urlencode("ไม่พบบัญชีผู้ดูแลระบบที่ต้องการลบ"));
            exit;
        }

        if ($target_admin['role'] === 'superadmin') {
            $super_cnt = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'")->fetchColumn();
            if ($super_cnt <= 1) {
                header("Location: admin.php?tab=admins&err=" . urlencode("ไม่สามารถลบผู้ดูแลระบบสูงสุดคนสุดท้ายของระบบได้"));
                exit;
            }
        }

        $del = $pdo->prepare("DELETE FROM admins WHERE id = :id");
        $del->execute([':id' => $delete_id]);

        header("Location: admin.php?tab=admins&msg=" . urlencode("ลบบัญชีผู้ดูแลระบบ '{$target_admin['full_name']}' ({$target_admin['username']}) เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=admins&err=" . urlencode("เกิดข้อผิดพลาดในการลบ: " . $e->getMessage()));
        exit;
    }
}

// -------------------------------------------------------------------------
// จัดการบันทึกและรีเซ็ตเนื้อหาหน้าแรก (Homepage Settings CMS)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_homepage_settings') {
    try {
        saveHomepageSettings($pdo, $_POST, $_FILES);
        header("Location: admin.php?tab=homepage&msg=" . urlencode("บันทึกการปรับแต่งเนื้อหาและรูปภาพหน้าแรกเรียบร้อยแล้ว"));
        exit;
    } catch (Exception $e) {
        header("Location: admin.php?tab=homepage&err=" . urlencode("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_homepage_settings') {
    try {
        resetHomepageSettings($pdo);
        header("Location: admin.php?tab=homepage&msg=" . urlencode("คืนค่าเริ่มต้นของหน้าแรกทั้งหมดเรียบร้อยแล้ว"));
        exit;
    } catch (Exception $e) {
        header("Location: admin.php?tab=homepage&err=" . urlencode("เกิดข้อผิดพลาดในการรีเซ็ต: " . $e->getMessage()));
        exit;
    }
}

// -------------------------------------------------------------------------
// จัดการรอบการรับสมัคร (POST: Admission Rounds Management)
// -------------------------------------------------------------------------

// 2.3.1 เพิ่มรอบการรับสมัครใหม่ (รอบโควตา หรือ รอบปกติ/ทั่วไป)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admission_round') {
    $round_code     = trim($_POST['round_code'] ?? '');
    $round_name     = trim($_POST['round_name'] ?? '');
    $round_type     = in_array($_POST['round_type'] ?? '', ['quota', 'normal'], true) ? $_POST['round_type'] : 'quota';
    $academic_year  = trim($_POST['academic_year'] ?? $selected_year);
    $levels         = isset($_POST['education_levels']) && is_array($_POST['education_levels']) 
                        ? implode(', ', $_POST['education_levels']) 
                        : trim($_POST['education_levels_str'] ?? 'ปวช., ปวส., ปริญญาตรี');
    $start_date     = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $announce_date  = !empty($_POST['announcement_date']) ? $_POST['announcement_date'] : null;
    $reg_date       = !empty($_POST['registration_date']) ? $_POST['registration_date'] : null;
    $quota_seats    = (int)($_POST['quota_seats'] ?? 0);
    $min_gpa        = (float)($_POST['min_gpa'] ?? 0.00);
    $status         = in_array($_POST['status'] ?? '', ['open', 'closed', 'upcoming'], true) ? $_POST['status'] : 'open';
    $desc           = trim($_POST['description'] ?? '');

    if (empty($round_name)) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("กรุณาระบุชื่อรอบการรับสมัคร"));
        exit;
    }

    if (empty($round_code)) {
        $prefix = ($round_type === 'quota') ? 'QUOTA' : 'NORMAL';
        $round_code = $prefix . '-' . $academic_year . '-' . date('mdHis');
    }

    try {
        ensureAdmissionRoundsTableExists($pdo);

        // ตรวจสอบรหัสรอบซ้ำ
        $chk = $pdo->prepare("SELECT id FROM admission_rounds WHERE round_code = :c");
        $chk->execute([':c' => $round_code]);
        if ($chk->fetch()) {
            $round_code .= '-' . rand(10, 99);
        }

        $stmt = $pdo->prepare("
            INSERT INTO admission_rounds 
            (round_code, round_name, round_type, academic_year, education_levels, start_date, end_date, announcement_date, registration_date, quota_seats, min_gpa, status, is_active, description)
            VALUES 
            (:code, :name, :type, :year, :levels, :start, :end, :announce, :reg, :seats, :gpa, :status, :act, :desc)
        ");
        $stmt->execute([
            ':code'     => $round_code,
            ':name'     => $round_name,
            ':type'     => $round_type,
            ':year'     => $academic_year,
            ':levels'   => $levels,
            ':start'    => $start_date,
            ':end'      => $end_date,
            ':announce' => $announce_date,
            ':reg'      => $reg_date,
            ':seats'    => $quota_seats,
            ':gpa'      => $min_gpa,
            ':status'   => $status,
            ':act'      => ($status === 'open') ? 1 : 0,
            ':desc'     => $desc,
        ]);

        $type_label = ($round_type === 'quota') ? 'รอบโควตา' : 'รอบปกติ/ทั่วไป';
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&msg=" . urlencode("เพิ่ม{$type_label} '{$round_name}' ปีการศึกษา {$academic_year} เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาดในการเพิ่มรอบ: " . $e->getMessage()));
        exit;
    }
}

// 2.3.2 แก้ไขรอบการรับสมัคร (POST: edit_admission_round)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admission_round') {
    $round_id       = (int)($_POST['round_id'] ?? 0);
    $round_code     = trim($_POST['round_code'] ?? '');
    $round_name     = trim($_POST['round_name'] ?? '');
    $round_type     = in_array($_POST['round_type'] ?? '', ['quota', 'normal'], true) ? $_POST['round_type'] : 'quota';
    $academic_year  = trim($_POST['academic_year'] ?? $selected_year);
    $levels         = isset($_POST['education_levels']) && is_array($_POST['education_levels']) 
                        ? implode(', ', $_POST['education_levels']) 
                        : trim($_POST['education_levels_str'] ?? 'ปวช., ปวส., ปริญญาตรี');
    $start_date     = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $announce_date  = !empty($_POST['announcement_date']) ? $_POST['announcement_date'] : null;
    $reg_date       = !empty($_POST['registration_date']) ? $_POST['registration_date'] : null;
    $quota_seats    = (int)($_POST['quota_seats'] ?? 0);
    $min_gpa        = (float)($_POST['min_gpa'] ?? 0.00);
    $status         = in_array($_POST['status'] ?? '', ['open', 'closed', 'upcoming'], true) ? $_POST['status'] : 'open';
    $desc           = trim($_POST['description'] ?? '');

    if ($round_id <= 0 || empty($round_name)) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("ข้อมูลการแก้ไขรอบรับสมัครไม่ถูกต้อง"));
        exit;
    }

    try {
        ensureAdmissionRoundsTableExists($pdo);
        $stmt = $pdo->prepare("
            UPDATE admission_rounds SET
                round_code = :code,
                round_name = :name,
                round_type = :type,
                academic_year = :year,
                education_levels = :levels,
                start_date = :start,
                end_date = :end,
                announcement_date = :announce,
                registration_date = :reg,
                quota_seats = :seats,
                min_gpa = :gpa,
                status = :status,
                is_active = :act,
                description = :desc
            WHERE id = :id
        ");
        $stmt->execute([
            ':code'     => $round_code,
            ':name'     => $round_name,
            ':type'     => $round_type,
            ':year'     => $academic_year,
            ':levels'   => $levels,
            ':start'    => $start_date,
            ':end'      => $end_date,
            ':announce' => $announce_date,
            ':reg'      => $reg_date,
            ':seats'    => $quota_seats,
            ':gpa'      => $min_gpa,
            ':status'   => $status,
            ':act'      => ($status === 'open') ? 1 : 0,
            ':desc'     => $desc,
            ':id'       => $round_id,
        ]);

        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&msg=" . urlencode("แก้ไขข้อมูลรอบการรับสมัคร '{$round_name}' เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาดในการแก้ไขรอบ: " . $e->getMessage()));
        exit;
    }
}

// 2.3.3 สลับสถานะเปิด/ปิดรอบ (POST: toggle_admission_round_status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_admission_round_status') {
    $round_id      = (int)($_POST['round_id'] ?? 0);
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    if ($round_id <= 0) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("รหัสรอบรับสมัครไม่ถูกต้อง"));
        exit;
    }

    try {
        $res = toggleAdmissionRoundStatus($pdo, $round_id);
        $st_text = ($res['new_status'] === 'open') ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร';
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&msg=" . urlencode("ปรับสถานะรอบ '{$res['round_name']}' เป็น [{$st_text}] เรียบร้อยแล้ว"));
        exit;
    } catch (Exception $e) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาด: " . $e->getMessage()));
        exit;
    }
}

// 2.3.4 ลบรอบการรับสมัคร (POST: delete_admission_round)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admission_round') {
    $round_id      = (int)($_POST['round_id'] ?? 0);
    $academic_year = trim($_POST['academic_year'] ?? $selected_year);

    if ($round_id <= 0) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("รหัสรอบรับสมัครไม่ถูกต้อง"));
        exit;
    }

    try {
        $cur = getAdmissionRoundById($pdo, $round_id);
        if (!$cur) {
            header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("ไม่พบรอบรับสมัครที่ต้องการลบ"));
            exit;
        }

        $del = $pdo->prepare("DELETE FROM admission_rounds WHERE id = :id");
        $del->execute([':id' => $round_id]);

        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&msg=" . urlencode("ลบรอบการรับสมัคร '{$cur['round_name']}' เรียบร้อยแล้ว"));
        exit;
    } catch (PDOException $e) {
        header("Location: admin.php?tab=rounds&academic_year={$academic_year}&err=" . urlencode("เกิดข้อผิดพลาดในการลบรอบ: " . $e->getMessage()));
        exit;
    }
}

// =========================================================================
// 3. ดึงข้อมูลและสถิติภาพรวม
// =========================================================================

// 3.0 โหลดการตั้งค่าเนื้อหาและรูปภาพหน้าแรก
$hp_settings = getHomepageSettings($pdo);

// 3.0.1 โหลดข้อมูลรอบการรับสมัคร (Admission Rounds)
$round_filter_type = isset($_GET['round_type']) && in_array($_GET['round_type'], ['quota', 'normal'], true) ? $_GET['round_type'] : null;
$all_rounds = getAllAdmissionRounds($pdo, $selected_year, $round_filter_type);
$round_stats = getRoundStats($pdo, $selected_year);
$active_rounds = getActiveAdmissionRounds($pdo, $selected_year);

// 3.1 สถิติผู้สมัครเรียน
try {
    $stats = [
        'total'               => 0,
        'pending'             => 0,
        'approved'            => 0,
        'document_incomplete' => 0,
        'paid'                => 0,
        'rejected'            => 0,
    ];

    $stat_query = $pdo->query("SELECT status, COUNT(*) AS cnt FROM applicants GROUP BY status");
    while ($row = $stat_query->fetch()) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = (int)$row['cnt'];
        }
        $stats['total'] += (int)$row['cnt'];
    }
} catch (PDOException $e) {
    error_log("Admin Stats Error: " . $e->getMessage());
}

// 3.2 สถิติและรายชื่อผู้ดูแลระบบ
$admin_list = [];
$admin_stats = ['total' => 0, 'superadmin' => 0, 'officer' => 0];
try {
    $admin_stmt = $pdo->query("SELECT id, username, full_name, email, role, created_at FROM admins ORDER BY id ASC");
    $admin_list = $admin_stmt->fetchAll();
    foreach ($admin_list as $a) {
        $admin_stats['total']++;
        if ($a['role'] === 'superadmin') {
            $admin_stats['superadmin']++;
        } else {
            $admin_stats['officer']++;
        }
    }
} catch (PDOException $e) {
    error_log("Load Admins Error: " . $e->getMessage());
}

// 3.3 สถิติและข้อมูลแผนกวิชา + สาขาวิชา
$all_departments = [];
$all_programs    = [];
$program_stats   = [
    'total_depts' => 0,
    'total_progs' => 0,
    'open'        => 0,
    'closed'      => 0,
    'pvc_total'   => 0,
    'pvc_open'    => 0,
    'pvs_total'   => 0,
    'pvs_open'    => 0,
    'deg_total'   => 0,
    'deg_open'    => 0,
];

$dept_matrix = [];
$programs_by_level = [
    'ปวช.'       => [],
    'ปวส.'       => [],
    'ปริญญาตรี' => [],
];

try {
    $stmt_depts = $pdo->prepare("SELECT * FROM departments WHERE academic_year = :yr ORDER BY id ASC");
    $stmt_depts->execute([':yr' => $selected_year]);
    $all_departments = $stmt_depts->fetchAll();
    $program_stats['total_depts'] = count($all_departments);

    foreach ($all_departments as $d) {
        $dept_matrix[$d['id']] = [
            'dept'       => $d,
            'ปวช.'       => [],
            'ปวส.'       => [],
            'ปริญญาตรี' => [],
        ];
    }

    $stmt_progs = $pdo->prepare("
        SELECT 
            p.*,
            d.dept_code,
            d.dept_name,
            COUNT(a.id) AS applicant_count
        FROM programs p
        JOIN departments d ON p.dept_id = d.id
        LEFT JOIN applicants a ON a.program_id = p.id
        WHERE p.academic_year = :yr
        GROUP BY p.id
        ORDER BY d.id ASC, p.education_level ASC, p.id ASC
    ");
    $stmt_progs->execute([':yr' => $selected_year]);
    $all_programs = $stmt_progs->fetchAll();

    $program_stats['total_progs'] = count($all_programs);

    foreach ($all_programs as $p) {
        $lvl = $p['education_level'];
        $st  = $p['status'];

        if ($st === 'open') {
            $program_stats['open']++;
        } else {
            $program_stats['closed']++;
        }

        if ($lvl === 'ปวช.') {
            $program_stats['pvc_total']++;
            if ($st === 'open') $program_stats['pvc_open']++;
        } elseif ($lvl === 'ปวส.') {
            $program_stats['pvs_total']++;
            if ($st === 'open') $program_stats['pvs_open']++;
        } elseif ($lvl === 'ปริญญาตรี') {
            $program_stats['deg_total']++;
            if ($st === 'open') $program_stats['deg_open']++;
        }

        if (isset($dept_matrix[$p['dept_id']][$lvl])) {
            $dept_matrix[$p['dept_id']][$lvl][] = $p;
        }

        if (isset($programs_by_level[$lvl])) {
            $programs_by_level[$lvl][] = $p;
        }
    }
} catch (PDOException $e) {
    error_log("Load Departments/Programs Error: " . $e->getMessage());
}

// 3.4 คัดกรองและค้นหาข้อมูลผู้สมัคร
$search = trim($_GET['search'] ?? '');
$level  = trim($_GET['level'] ?? '');
$status = trim($_GET['status'] ?? '');
$round_filter = trim($_GET['round'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(a.application_no LIKE :s1 OR a.citizen_id LIKE :s2 OR a.first_name LIKE :s3 OR a.last_name LIKE :s4)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
}

if (!empty($level)) {
    $where[] = "a.education_level = :lvl";
    $params[':lvl'] = $level;
}

if (!empty($status)) {
    $where[] = "a.status = :st";
    $params[':st'] = $status;
}

if (!empty($round_filter)) {
    $where[] = "a.admission_round LIKE :rnd";
    $params[':rnd'] = "%$round_filter%";
}

$sql = "
    SELECT 
        a.*, 
        p.program_name, 
        p.program_code,
        d.dept_name
    FROM applicants a
    LEFT JOIN programs p ON a.program_id = p.id
    LEFT JOIN departments d ON p.dept_id = d.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY a.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll();
} catch (PDOException $e) {
    $applicants = [];
    $err = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
}

function getAdminStatusBadge($st) {
    switch ($st) {
        case 'approved':
            return ['text' => 'ผ่านการคัดเลือก', 'badge' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'];
        case 'paid':
            return ['text' => 'มอบตัว/ชำระเงินแล้ว', 'badge' => 'bg-primary text-white', 'icon' => 'bi-award-fill'];
        case 'document_incomplete':
            return ['text' => 'เอกสารไม่สมบูรณ์', 'badge' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-triangle-fill'];
        case 'rejected':
            return ['text' => 'ไม่ผ่านการคัดเลือก', 'badge' => 'bg-danger text-white', 'icon' => 'bi-x-circle-fill'];
        case 'pending':
        default:
            return ['text' => 'รอตรวจสอบเอกสาร', 'badge' => 'bg-secondary text-white', 'icon' => 'bi-hourglass-split'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ระบบจัดการผู้สมัครและแผนกวิชา | วิทยาลัยอาชีวศึกษามหาสารคาม</title>

  <!-- Bootstrap 5.3.3 & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Google Fonts: Sarabun -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" href="img/Logo-W.png">
  <link rel="shortcut icon" href="favicon.ico">

  <style>
    :root {
      --color-primary: #8B1E2B;
      --color-primary-deep: #6F1420;
      --color-primary-hover: #580E18;
      --color-primary-light: #B22B3B;
      --color-primary-tint: #FDF2F2;
      --color-primary-border: #FECACA;
      --color-secondary-deep: #13294B;
      --color-bg: #F8FAFC;
      --color-surface: #FFFFFF;
      --color-text-primary: #0F172A;
      --color-text-secondary: #475569;
      --color-border-light: #E2E8F0;
      --radius-md: 10px;
      --radius-lg: 14px;
    }

    body {
      font-family: 'Sarabun', sans-serif;
      background-color: var(--color-bg);
      color: var(--color-text-primary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .top-header {
      background-color: var(--color-primary-deep);
      color: #FFFFFF;
      border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .navbar-admin {
      background-color: #FFFFFF;
      border-bottom: 1px solid var(--color-border-light);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .brand-logo-img {
      width: 42px;
      height: 42px;
      object-fit: contain;
    }

    /* Main Tab Pills */
    .nav-pills .nav-link {
      color: var(--color-text-secondary);
      font-weight: 600;
      border-radius: 10px;
      padding: 9px 18px;
      transition: all 0.2s ease;
      border: 1px solid transparent;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .nav-pills .nav-link:hover {
      background-color: var(--color-primary-tint);
      color: var(--color-primary-deep);
    }

    .nav-pills .nav-link.active {
      background-color: var(--color-primary);
      color: #FFFFFF;
      box-shadow: 0 4px 12px rgba(139, 30, 43, 0.25);
    }

    /* Sub Tabs (View by level) */
    .sub-nav-tabs .nav-link {
      border-radius: 8px;
      padding: 7px 16px;
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--color-text-secondary);
      background-color: #FFFFFF;
      border: 1px solid var(--color-border-light);
      transition: all 0.2s ease;
    }

    .sub-nav-tabs .nav-link:hover {
      background-color: #F1F5F9;
      color: var(--color-primary);
    }

    .sub-nav-tabs .nav-link.active {
      background-color: var(--color-secondary-deep);
      color: #FFFFFF;
      border-color: var(--color-secondary-deep);
      box-shadow: 0 2px 8px rgba(19, 41, 75, 0.2);
    }

    .stat-card {
      background: #FFFFFF;
      border: 1px solid var(--color-border-light);
      border-radius: var(--radius-lg);
      padding: 18px;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(139, 30, 43, 0.08);
    }

    .stat-card::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
    }

    .stat-card.total::before { background-color: var(--color-primary); }
    .stat-card.pending::before { background-color: #64748B; }
    .stat-card.approved::before { background-color: #10B981; }
    .stat-card.paid::before { background-color: #3B82F6; }
    .stat-card.incomplete::before { background-color: #F59E0B; }
    .stat-card.rejected::before { background-color: #EF4444; }
    .stat-card.superadmin::before { background-color: #DC2626; }
    .stat-card.officer::before { background-color: #2563EB; }
    .stat-card.pvc::before { background-color: #DC2626; }
    .stat-card.pvs::before { background-color: #4F46E5; }
    .stat-card.deg::before { background-color: #D97706; }

    .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
    }

    .table-container {
      background: #FFFFFF;
      border: 1px solid var(--color-border-light);
      border-radius: var(--radius-lg);
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
      overflow: hidden;
    }

    .table thead th {
      background-color: #F1F5F9;
      color: var(--color-secondary-deep);
      font-weight: 700;
      font-size: 0.875rem;
      border-bottom: 2px solid var(--color-border-light);
      padding: 14px 16px;
      white-space: nowrap;
    }

    .table tbody td {
      padding: 14px 16px;
      vertical-align: middle;
      font-size: 0.925rem;
      border-bottom: 1px solid var(--color-border-light);
    }

    .badge-level {
      font-size: 0.775rem;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 20px;
    }

    .badge-level.pvc { background-color: #FEE2E2; color: #991B1B; }
    .badge-level.pvs { background-color: #E0E7FF; color: #3730A3; }
    .badge-level.deg { background-color: #FEF3C7; color: #92400E; }

    .btn-action {
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      border: 1px solid var(--color-primary-border);
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      padding: 6px 14px;
      transition: all 0.2s ease;
    }

    .btn-action:hover {
      background-color: var(--color-primary);
      color: #FFFFFF;
      border-color: var(--color-primary);
    }

    .admin-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary-deep) 100%);
      color: #FFFFFF;
      font-weight: 700;
      font-size: 1rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    /* Program Card in Matrix View */
    .prog-pill-card {
      background: #FFFFFF;
      border: 1px solid var(--color-border-light);
      border-radius: 10px;
      padding: 10px 12px;
      margin-bottom: 8px;
      transition: all 0.2s ease;
    }

    .prog-pill-card:hover {
      border-color: var(--color-primary-border);
      box-shadow: 0 2px 8px rgba(139, 30, 43, 0.08);
    }

    .prog-pill-card.open {
      border-left: 4px solid #10B981;
    }

    .prog-pill-card.closed {
      border-left: 4px solid #94A3B8;
      background-color: #F8FAFC;
    }

    footer {
      margin-top: auto;
      background-color: var(--color-secondary-deep);
      color: #94A3B8;
      padding: 20px 0;
      font-size: 0.85rem;
      border-top: 3px solid var(--color-primary);
    }

    /* Homepage CMS Custom Styling */
    .cms-block-card {
      background-color: var(--color-surface);
      border: 1px solid var(--color-border-light);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      margin-bottom: 24px;
      transition: box-shadow 0.2s ease;
    }
    .cms-block-card:hover {
      box-shadow: var(--shadow-md);
    }
    .cms-block-header {
      background-color: #FAFBFD;
      border-bottom: 1px solid var(--color-border-light);
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-left: 4px solid var(--color-primary);
    }
    .cms-block-body {
      padding: 24px 20px;
    }
    .cms-sticky-bar {
      position: sticky;
      bottom: 0;
      z-index: 1020;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(8px);
      border-top: 2px solid var(--color-primary);
      padding: 14px 24px;
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
      margin: 30px -1.5rem -1.5rem -1.5rem;
      border-bottom-left-radius: 8px;
      border-bottom-right-radius: 8px;
    }
    .image-preview-box {
      background-color: #F8FAFC;
      border: 2px dashed #CBD5E1;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .image-preview-box img {
      max-height: 240px;
      max-width: 100%;
      height: auto;
      width: auto;
      object-fit: contain;
      object-position: center;
      border-radius: 8px;
    }
  </style>
</head>
<body>

  <!-- Top Announcement Bar -->
  <header class="top-header py-1">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
      <div class="small">
        <i class="bi bi-shield-lock-fill text-warning me-1"></i>
        <span>ระบบงานทะเบียนและรับสมัครนักศึกษา (MVC Admission Portal)</span>
      </div>
      <div class="small">
        <i class="bi bi-calendar3 me-1"></i> วันที่: <?= date('d/m/Y') ?>
      </div>
    </div>
  </header>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-admin sticky-top">
    <div class="container-fluid px-4">
      <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
        <img src="img/Logo-W.png" alt="โลโก้" class="brand-logo-img">
        <div>
          <div class="fw-bold" style="color: var(--color-primary-deep); font-size: 1.1rem; line-height: 1.2;">
            ผู้ดูแลระบบรับสมัครนักเรียน นักศึกษา
          </div>
          <small class="text-secondary" style="font-size: 0.775rem;">วิทยาลัยอาชีวศึกษามหาสารคาม</small>
        </div>
      </a>

      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary btn-sm" target="_blank" title="ดูหน้าแรกเว็บไซต์">
          <i class="bi bi-box-arrow-up-right me-1"></i> ดูหน้าเว็บ
        </a>
        <a href="register.php" class="btn btn-outline-danger btn-sm" target="_blank" title="เปิดหน้าฟอร์มรับสมัครเรียน">
          <i class="bi bi-pencil-square me-1"></i> ฟอร์มรับสมัคร
        </a>
        <a href="print_application.php" class="btn btn-outline-dark btn-sm" target="_blank" title="เปิดหน้าค้นหาและพิมพ์ใบสมัคร">
          <i class="bi bi-printer me-1"></i> พิมพ์ใบสมัคร
        </a>

        <!-- Admin Profile dropdown -->
        <div class="dropdown">
          <button class="btn btn-light btn-sm border d-flex align-items-center gap-2 px-3 py-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle fs-5 text-danger"></i>
            <div class="text-start d-none d-md-block">
              <div class="fw-bold text-dark small" style="line-height: 1.1;"><?= htmlspecialchars($admin_name) ?></div>
              <span class="badge bg-danger-subtle text-danger" style="font-size: 0.65rem;">
                <?= $admin_role === 'superadmin' ? 'ผู้ดูแลระบบสูงสุด (Superadmin)' : 'เจ้าหน้าที่ (Officer)' ?>
              </span>
            </div>
            <i class="bi bi-chevron-down text-muted small ms-1"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-1">
            <li>
              <div class="px-3 py-2 border-bottom">
                <small class="text-muted d-block">เข้าสู่ระบบในชื่อ</small>
                <div class="fw-bold text-dark"><?= htmlspecialchars($admin_name) ?></div>
                <small class="text-danger fw-semibold"><?= htmlspecialchars($admin_role) ?></small>
              </div>
            </li>
            <li><a class="dropdown-item py-2" href="admin.php?tab=applicants"><i class="bi bi-people me-2 text-danger"></i> จัดการผู้สมัคร</a></li>
            <li><a class="dropdown-item py-2" href="admin.php?tab=departments"><i class="bi bi-mortarboard me-2 text-warning"></i> จัดการแผนกและสาขาวิชา</a></li>
            <li><a class="dropdown-item py-2" href="admin.php?tab=rounds"><i class="bi bi-calendar-event me-2 text-success"></i> จัดการรอบรับสมัคร</a></li>
            <li><a class="dropdown-item py-2" href="admin.php?tab=admins"><i class="bi bi-shield-person me-2 text-primary"></i> จัดการผู้ดูแลระบบ</a></li>
            <li><a class="dropdown-item py-2 text-danger fw-semibold" href="admin.php?tab=homepage"><i class="bi bi-layout-text-window-reverse me-2"></i> ปรับแต่งหน้าแรก (Index)</a></li>
            <li><a class="dropdown-item py-2" href="index.php" target="_blank"><i class="bi bi-globe me-2 text-secondary"></i> หน้าเว็บรับสมัคร</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li>
              <a class="dropdown-item text-danger py-2 fw-semibold" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="container-fluid px-4 py-4 flex-grow-1">

    <!-- Flash Messages -->
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success alert-dismissible fade show rounded-3 d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
        <div><?= htmlspecialchars($msg) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
      <div class="alert alert-danger alert-dismissible fade show rounded-3 d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
        <div><?= htmlspecialchars($err) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Navigation Tabs & Primary Action Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 border-bottom pb-3">
      <ul class="nav nav-pills gap-2">
        <!-- Tab 1: ผู้สมัครเรียน -->
        <li class="nav-item">
          <a class="nav-link <?= $current_tab === 'applicants' ? 'active' : '' ?>" href="admin.php?tab=applicants">
            <i class="bi bi-people-fill"></i> จัดการผู้สมัครเรียน
            <span class="badge <?= $current_tab === 'applicants' ? 'bg-white text-danger' : 'bg-secondary-subtle text-secondary' ?> rounded-pill ms-1">
              <?= number_format($stats['total']) ?>
            </span>
          </a>
        </li>

        <!-- Tab 2: แผนกวิชาและสาขา -->
        <li class="nav-item">
          <a class="nav-link <?= $current_tab === 'departments' ? 'active' : '' ?>" href="admin.php?tab=departments&academic_year=<?= urlencode($selected_year) ?>">
            <i class="bi bi-mortarboard-fill"></i> จัดการแผนกวิชา & สาขา
            <span class="badge <?= $current_tab === 'departments' ? 'bg-white text-danger' : 'bg-secondary-subtle text-secondary' ?> rounded-pill ms-1">
              <?= number_format($program_stats['total_progs']) ?>
            </span>
          </a>
        </li>

        <!-- Tab 3: จัดการรอบรับสมัคร (ROUNDS) -->
        <li class="nav-item">
          <a class="nav-link <?= $current_tab === 'rounds' ? 'active' : '' ?>" href="admin.php?tab=rounds&academic_year=<?= urlencode($selected_year) ?>">
            <i class="bi bi-calendar-event-fill"></i> จัดการรอบรับสมัคร
            <span class="badge <?= $current_tab === 'rounds' ? 'bg-white text-danger' : 'bg-secondary-subtle text-secondary' ?> rounded-pill ms-1">
              <?= number_format($round_stats['total']) ?>
            </span>
          </a>
        </li>

        <!-- Tab 4: ผู้ดูแลระบบ -->
        <li class="nav-item">
          <a class="nav-link <?= $current_tab === 'admins' ? 'active' : '' ?>" href="admin.php?tab=admins">
            <i class="bi bi-shield-lock-fill"></i> จัดการผู้ดูแลระบบ
            <span class="badge <?= $current_tab === 'admins' ? 'bg-white text-danger' : 'bg-secondary-subtle text-secondary' ?> rounded-pill ms-1">
              <?= number_format($admin_stats['total']) ?>
            </span>
          </a>
        </li>

        <!-- Tab 5: ปรับแต่งหน้าแรก (Index CMS) -->
        <li class="nav-item">
          <a class="nav-link <?= $current_tab === 'homepage' ? 'active' : '' ?>" href="admin.php?tab=homepage">
            <i class="bi bi-layout-text-window-reverse"></i> ปรับแต่งหน้าแรก (Index)
          </a>
        </li>
      </ul>

      <div>
        <?php if ($current_tab === 'departments'): ?>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
              <i class="bi bi-building-add me-1"></i> เพิ่มแผนกวิชา (ปี <?= htmlspecialchars($selected_year) ?>)
            </button>
            <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProgramModal" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-plus-circle-fill"></i>
              <span>เพิ่มสาขา / เปิดรับสมัคร</span>
            </button>
          </div>
        <?php elseif ($current_tab === 'rounds'): ?>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#addQuotaRoundModal">
              <i class="bi bi-award-fill text-danger"></i>
              <span>+ เพิ่มรอบโควตา</span>
            </button>
            <button type="button" class="btn btn-primary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#addNormalRoundModal">
              <i class="bi bi-people-fill"></i>
              <span>+ เพิ่มรอบปกติ (ทั่วไป)</span>
            </button>
          </div>
        <?php elseif ($current_tab === 'admins'): ?>
          <button type="button" class="btn btn-danger px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#addAdminModal" style="background-color: var(--color-primary); border-color: var(--color-primary);">
            <i class="bi bi-person-plus-fill fs-6"></i>
            <span>เพิ่มผู้ดูแลระบบใหม่</span>
          </button>
        <?php elseif ($current_tab === 'homepage'): ?>
          <div class="d-flex gap-2">
            <a href="index.php" target="_blank" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
              <i class="bi bi-box-arrow-up-right"></i>
              <span>ดูหน้าแรกจริง</span>
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#resetHomepageModal">
              <i class="bi bi-arrow-counterclockwise me-1"></i> คืนค่าเริ่มต้น
            </button>
            <button type="submit" form="homepageCMSForm" class="btn btn-danger btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save2-fill"></i>
              <span>บันทึกการเปลี่ยนแปลง</span>
            </button>
          </div>
        <?php else: ?>
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.location.reload();">
            <i class="bi bi-arrow-clockwise me-1"></i> รีเฟรชข้อมูล
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 1: จัดการผู้สมัครเรียน (APPLICANTS)                                  -->
    <!-- ===================================================================== -->
    <?php if ($current_tab === 'applicants'): ?>

      <div class="mb-4">
        <h3 class="fw-bold mb-1" style="color: var(--color-primary-deep);">
          <i class="bi bi-people-fill text-danger me-2"></i>รายชื่อผู้สมัครเรียน
        </h3>
        <div class="text-secondary small">
          ตรวจสอบข้อมูล เอกสารหลักฐาน และปรับปรุงสถานะการพิจารณาคัดเลือกผู้สมัคร
        </div>
      </div>

      <!-- KPI Stats Cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
          <div class="stat-card total h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ผู้สมัครทั้งหมด</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($stats['total']) ?></h3>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <div class="stat-card pending h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">รอตรวจสอบ</div>
                <h3 class="fw-bold text-secondary mt-1 mb-0"><?= number_format($stats['pending']) ?></h3>
              </div>
              <div class="stat-icon bg-secondary-subtle text-secondary">
                <i class="bi bi-hourglass-split"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <div class="stat-card approved h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ผ่านการคัดเลือก</div>
                <h3 class="fw-bold text-success mt-1 mb-0"><?= number_format($stats['approved']) ?></h3>
              </div>
              <div class="stat-icon bg-success-subtle text-success">
                <i class="bi bi-check-circle-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <div class="stat-card paid h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">มอบตัว/ชำระเงิน</div>
                <h3 class="fw-bold text-primary mt-1 mb-0"><?= number_format($stats['paid']) ?></h3>
              </div>
              <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-award-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <div class="stat-card incomplete h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">เอกสารไม่สมบูรณ์</div>
                <h3 class="fw-bold text-warning mt-1 mb-0"><?= number_format($stats['document_incomplete']) ?></h3>
              </div>
              <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <div class="stat-card rejected h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ไม่ผ่านการคัดเลือก</div>
                <h3 class="fw-bold text-danger mt-1 mb-0"><?= number_format($stats['rejected']) ?></h3>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-x-circle-fill"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter & Search Bar -->
      <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form action="admin.php" method="GET" class="row g-2 align-items-center">
          <input type="hidden" name="tab" value="applicants">
          
          <div class="col-md-3">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
              </span>
              <input 
                type="text" 
                name="search" 
                class="form-control border-start-0" 
                placeholder="ค้นหาชื่อ, เลขบัตร 13 หลัก..."
                value="<?= htmlspecialchars($search) ?>"
              >
            </div>
          </div>

          <div class="col-md-2">
            <select name="level" class="form-select">
              <option value="">-- ระดับการศึกษา --</option>
              <option value="ปวช." <?= $level === 'ปวช.' ? 'selected' : '' ?>>ระดับ ปวช.</option>
              <option value="ปวส." <?= $level === 'ปวส.' ? 'selected' : '' ?>>ระดับ ปวส.</option>
              <option value="ปริญญาตรี" <?= $level === 'ปริญญาตรี' ? 'selected' : '' ?>>ระดับ ปริญญาตรี</option>
            </select>
          </div>

          <div class="col-md-2">
            <select name="round" class="form-select">
              <option value="">-- ทุกรอบรับสมัคร --</option>
              <option value="โควตา" <?= $round_filter === 'โควตา' ? 'selected' : '' ?>>รอบโควตา</option>
              <option value="ปกติ" <?= $round_filter === 'ปกติ' ? 'selected' : '' ?>>รอบปกติ / ทั่วไป</option>
              <?php foreach ($all_rounds as $r_opt): ?>
                <option value="<?= htmlspecialchars($r_opt['round_name']) ?>" <?= $round_filter === $r_opt['round_name'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r_opt['round_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <select name="status" class="form-select">
              <option value="">-- ทุกสถานะ --</option>
              <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>รอตรวจสอบเอกสาร</option>
              <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>ผ่านการคัดเลือก</option>
              <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>มอบตัว/ชำระเงินแล้ว</option>
              <option value="document_incomplete" <?= $status === 'document_incomplete' ? 'selected' : '' ?>>เอกสารไม่สมบูรณ์</option>
              <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>ไม่ผ่านการคัดเลือก</option>
            </select>
          </div>

          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-danger w-100 fw-semibold" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-filter"></i> คัดกรอง
            </button>
            <?php if (!empty($search) || !empty($level) || !empty($status) || !empty($round_filter)): ?>
              <a href="admin.php?tab=applicants" class="btn btn-outline-secondary" title="ล้างตัวกรอง">
                <i class="bi bi-x-lg"></i>
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Applicants Table -->
      <div class="table-container mb-4">
        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="fw-bold text-dark">
            พบข้อมูลผู้สมัครทั้งหมด <span class="text-danger"><?= count($applicants) ?></span> คน
          </div>
          <div class="small text-muted">
            คลิกปุ่ม "จัดการ" เพื่อดูรายละเอียดและเปลี่ยนสถานะ
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col" style="width: 140px;">เลขที่ใบสมัคร</th>
                <th scope="col">ชื่อ - สกุล ผู้สมัคร</th>
                <th scope="col">เลขประจำตัวประชาชน</th>
                <th scope="col">ระดับ & สาขาวิชา</th>
                <th scope="col">เกรดเฉลี่ย (GPA)</th>
                <th scope="col">วันที่สมัคร</th>
                <th scope="col" style="width: 170px;">สถานะ</th>
                <th scope="col" class="text-end" style="width: 110px;">การดำเนินการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($applicants)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                    ไม่พบข้อมูลผู้สมัครตามเงื่อนไขที่เลือก
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($applicants as $app): ?>
                  <?php 
                    $stBadge = getAdminStatusBadge($app['status']);
                    $levelClass = 'pvc';
                    if ($app['education_level'] === 'ปวส.') $levelClass = 'pvs';
                    elseif ($app['education_level'] === 'ปริญญาตรี') $levelClass = 'deg';
                  ?>
                  <tr>
                    <td>
                      <span class="badge bg-light text-dark border font-monospace fw-bold">
                        <?= htmlspecialchars($app['application_no']) ?>
                      </span>
                    </td>

                    <td>
                      <div class="fw-bold text-dark">
                        <?= htmlspecialchars($app['prefix'] . $app['first_name'] . ' ' . $app['last_name']) ?>
                      </div>
                      <small class="text-muted">
                        <i class="bi bi-telephone text-secondary me-1"></i> <?= htmlspecialchars($app['phone'] ?? '-') ?>
                      </small>
                    </td>

                    <td>
                      <span class="font-monospace text-secondary">
                        <?= htmlspecialchars($app['citizen_id']) ?>
                      </span>
                    </td>

                    <td>
                      <span class="badge badge-level <?= $levelClass ?> mb-1">
                        <?= htmlspecialchars($app['education_level']) ?>
                      </span>
                      <?php if (!empty($app['curriculum_type'])): ?>
                        <span class="badge <?= $app['curriculum_type'] === 'ระบบทวิภาคี' ? 'bg-warning text-dark' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> mb-1" style="font-size: 0.72rem;">
                          <?= htmlspecialchars($app['curriculum_type']) ?>
                        </span>
                      <?php endif; ?>
                      <div class="small fw-semibold text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($app['program_name'] ?? '') ?>">
                        <?= htmlspecialchars($app['program_name'] ?? 'ไม่ได้ระบุสาขา') ?>
                      </div>
                    </td>

                    <td>
                      <span class="fw-bold text-dark">
                        <?= $app['gpa'] !== null ? number_format((float)$app['gpa'], 2) : '-' ?>
                      </span>
                    </td>

                    <td>
                      <small class="text-muted">
                        <?= date('d/m/Y H:i', strtotime($app['created_at'])) ?>
                      </small>
                    </td>

                    <td>
                      <span class="badge <?= $stBadge['badge'] ?> px-2 py-1 small rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="bi <?= $stBadge['icon'] ?>"></i>
                        <span><?= $stBadge['text'] ?></span>
                      </span>
                    </td>

                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a 
                          href="print_application.php?search=<?= urlencode($app['citizen_id']) ?>" 
                          target="_blank" 
                          class="btn btn-outline-secondary" 
                          title="ดูและพิมพ์ใบสมัครฉบับจริง"
                        >
                          <i class="bi bi-printer"></i>
                        </a>
                        <button 
                          type="button" 
                          class="btn btn-action" 
                          data-bs-toggle="modal" 
                          data-bs-target="#applicantModal"
                          onclick='openApplicantModal(<?= json_encode($app, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="ดูรายละเอียดและจัดการผลการพิจารณา"
                        >
                          <i class="bi bi-pencil-square me-1"></i> จัดการ
                        </button>
                        <button 
                          type="button" 
                          class="btn btn-outline-danger" 
                          onclick='openDeleteApplicantModal(<?= json_encode($app, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="ลบข้อมูลผู้สมัครนี้"
                        >
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ===================================================================== -->
    <!-- TAB 2: จัดการแผนกวิชา & สาขา (DEPARTMENTS & PROGRAMS)                   -->
    <!-- ===================================================================== -->
    <?php elseif ($current_tab === 'departments'): ?>

      <!-- Header Title -->
      <div class="mb-4">
        <h3 class="fw-bold mb-1" style="color: var(--color-primary-deep);">
          <i class="bi bi-mortarboard-fill text-danger me-2"></i>จัดการแผนกวิชาและสาขาวิชาที่เปิดรับสมัคร
        </h3>
        <div class="text-secondary small">
          กำหนดแผนกวิชาที่เปิดรับสมัครในแต่ละระดับชั้น (ปวช., ปวส., ปริญญาตรี) ประจำปีการศึกษาที่เลือก พร้อมระบบสลับเปิด/ปิดรับสมัครได้แบบทันที
        </div>
      </div>

      <!-- แถบควบคุมและเลือกปีการศึกษาภาพรวม (Academic Year Scope Banner) -->
      <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #FFF9F9 0%, #FFFFFF 100%); border: 1px solid var(--color-primary-border) !important; border-left: 6px solid var(--color-primary) !important;">
        <div class="card-body p-3 p-md-4">
          <div class="row align-items-center g-3">
            <div class="col-lg-5 col-md-6">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm flex-shrink-0" style="width: 52px; height: 52px; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-deep) 100%);">
                  <i class="bi bi-calendar3-event fs-4"></i>
                </div>
                <div>
                  <div class="text-uppercase text-secondary fw-bold small" style="font-size: 0.775rem; letter-spacing: 0.5px;">
                    <i class="bi bi-globe-americas me-1 text-danger"></i> ปีการศึกษาภาพรวม (Academic Year Scope)
                  </div>
                  <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2 mt-1">
                    <span>ปีการศึกษา <?= htmlspecialchars($selected_year) ?></span>
                    <?php if ($selected_year === $default_active_year): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 py-1 px-2 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i>รอบรับสมัครหลัก
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary border fs-6 py-1 px-2 fw-semibold">
                        ข้อมูลปีอื่น
                      </span>
                    <?php endif; ?>
                  </h4>
                </div>
              </div>
            </div>

            <!-- ตัวเลือกปีการศึกษา & ปุ่มจัดการ -->
            <div class="col-lg-7 col-md-6">
              <div class="d-flex align-items-center justify-content-md-end gap-2 flex-wrap">
                <div class="d-flex align-items-center gap-2 bg-white px-3 py-1 rounded-3 border shadow-xs">
                  <label for="academic_year_selector" class="form-label mb-0 small fw-bold text-secondary text-nowrap">
                    <i class="bi bi-funnel-fill text-danger me-1"></i> เลือกปีการศึกษา:
                  </label>
                  <select id="academic_year_selector" class="form-select form-select-sm fw-bold border-0 bg-transparent" style="min-width: 175px;" onchange="switchAcademicYear(this.value)">
                    <?php foreach ($available_academic_years as $ay): ?>
                      <option value="<?= htmlspecialchars($ay['year_name']) ?>" <?= $ay['year_name'] === $selected_year ? 'selected' : '' ?>>
                        ปีการศึกษา <?= htmlspecialchars($ay['year_name']) ?> <?= !empty($ay['is_active']) ? '★ (หลัก)' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addAcademicYearModal" title="เพิ่มปีการศึกษาใหม่เข้าสู่ระบบ">
                    <i class="bi bi-calendar-plus me-1"></i> เพิ่มปีการศึกษา
                  </button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cloneYearModal" title="คัดลอกแผนกวิชาและสาขาวิชาจากปีอื่นมายังปีนี้">
                    <i class="bi bi-copy me-1"></i> คัดลอกโครงสร้างปี
                  </button>
                </div>

                <?php if ($selected_year !== $default_active_year): ?>
                  <form action="admin.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="set_active_academic_year">
                    <input type="hidden" name="year_name" value="<?= htmlspecialchars($selected_year) ?>">
                    <button type="submit" class="btn btn-success btn-sm fw-semibold shadow-xs" title="ตั้งปี <?= htmlspecialchars($selected_year) ?> ให้เป็นปีการศึกษาเปิดรับสมัครหลักของวิทยาลัย">
                      <i class="bi bi-check2-circle me-1"></i> ตั้งเป็นปีหลัก
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Stats Cards for Programs -->
      <div class="row g-3 mb-4">
        <!-- Total Departments -->
        <div class="col-6 col-lg-3">
          <div class="stat-card total h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">แผนกวิชาทั้งหมด (ปี <?= htmlspecialchars($selected_year) ?>)</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($program_stats['total_depts']) ?> แผนก</h3>
                <small class="text-muted">วิทยาลัยอาชีวศึกษามหาสารคาม</small>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-building"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Level: ปวช. -->
        <div class="col-6 col-lg-3">
          <div class="stat-card pvc h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ระดับ ปวช. (ปี <?= htmlspecialchars($selected_year) ?>)</div>
                <h3 class="fw-bold text-danger mt-1 mb-0"><?= number_format($program_stats['pvc_total']) ?> สาขา</h3>
                <span class="badge bg-success-subtle text-success small">
                  เปิดรับ <?= $program_stats['pvc_open'] ?> / <?= $program_stats['pvc_total'] ?>
                </span>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-mortarboard"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Level: ปวส. -->
        <div class="col-6 col-lg-3">
          <div class="stat-card pvs h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ระดับ ปวส. (ปี <?= htmlspecialchars($selected_year) ?>)</div>
                <h3 class="fw-bold text-primary mt-1 mb-0"><?= number_format($program_stats['pvs_total']) ?> สาขา</h3>
                <span class="badge bg-success-subtle text-success small">
                  เปิดรับ <?= $program_stats['pvs_open'] ?> / <?= $program_stats['pvs_total'] ?>
                </span>
              </div>
              <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-award"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Level: ปริญญาตรี -->
        <div class="col-6 col-lg-3">
          <div class="stat-card deg h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ระดับ ปริญญาตรี (ปี <?= htmlspecialchars($selected_year) ?>)</div>
                <h3 class="fw-bold text-warning mt-1 mb-0"><?= number_format($program_stats['deg_total']) ?> สาขา</h3>
                <span class="badge bg-success-subtle text-success small">
                  เปิดรับ <?= $program_stats['deg_open'] ?> / <?= $program_stats['deg_total'] ?>
                </span>
              </div>
              <div class="stat-icon bg-warning-subtle text-warning">
                <i class="bi bi-book"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sub-Tabs: มุมมองตามระดับการศึกษา (Level Switcher) -->
      <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <ul class="nav sub-nav-tabs gap-2 mb-0">
            <li class="nav-item">
              <a class="nav-link <?= $dept_view === 'matrix' ? 'active' : '' ?>" href="admin.php?tab=departments&view=matrix&academic_year=<?= urlencode($selected_year) ?>">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> ภาพรวมทุกแผนกวิชา (Matrix)
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $dept_view === 'pvc' ? 'active' : '' ?>" href="admin.php?tab=departments&view=pvc&academic_year=<?= urlencode($selected_year) ?>">
                <i class="bi bi-check-circle-fill text-danger me-1"></i> ระดับ ปวช. (<?= $program_stats['pvc_total'] ?>)
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $dept_view === 'pvs' ? 'active' : '' ?>" href="admin.php?tab=departments&view=pvs&academic_year=<?= urlencode($selected_year) ?>">
                <i class="bi bi-check-circle-fill text-primary me-1"></i> ระดับ ปวส. (<?= $program_stats['pvs_total'] ?>)
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $dept_view === 'deg' ? 'active' : '' ?>" href="admin.php?tab=departments&view=deg&academic_year=<?= urlencode($selected_year) ?>">
                <i class="bi bi-check-circle-fill text-warning me-1"></i> ระดับ ปริญญาตรี (<?= $program_stats['deg_total'] ?>)
              </a>
            </li>
          </ul>

          <div class="small text-muted">
            <i class="bi bi-info-circle me-1"></i> คลิกปุ่ม <strong>"เปิดรับ" / "ปิดรับ"</strong> เพื่อสลับสถานะรับสมัครได้ทันที
          </div>
        </div>
      </div>

      <!-- ----------------------------------------------------------------- -->
      <!-- VIEW 1: MATRIX OVERVIEW (ตารางสรุปทุกแผนก x ระดับชั้น ปวช. ปวส. ปริญญาตรี) -->
      <!-- ----------------------------------------------------------------- -->
      <?php if ($dept_view === 'matrix'): ?>

        <div class="table-container mb-4">
          <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-bold text-dark">
              ตารางภาพรวมแผนกวิชา และระดับการศึกษาที่เปิดรับสมัคร ประจำปีการศึกษา <?= htmlspecialchars($selected_year) ?> (<?= count($all_departments) ?> แผนก)
            </div>
            <div class="small text-secondary">
              สีเขียว = เปิดรับสมัคร | สีเทา = ปิดรับสมัคร
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered align-top mb-0" style="border-color: #E2E8F0;">
              <thead>
                <tr>
                  <th scope="col" style="width: 250px; background-color: #F8FAFC;">แผนกวิชา (Department)</th>
                  <th scope="col" style="width: 320px; background-color: #FFF5F5;">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="text-danger fw-bold"><i class="bi bi-bookmark-fill me-1"></i> ระดับ ปวช.</span>
                      <span class="badge bg-danger-subtle text-danger small">ปวช.</span>
                    </div>
                  </th>
                  <th scope="col" style="width: 320px; background-color: #F5F7FF;">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="text-primary fw-bold"><i class="bi bi-bookmark-fill me-1"></i> ระดับ ปวส.</span>
                      <span class="badge bg-primary-subtle text-primary small">ปวส.</span>
                    </div>
                  </th>
                  <th scope="col" style="width: 320px; background-color: #FFFBF0;">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="text-warning-emphasis fw-bold"><i class="bi bi-bookmark-fill me-1"></i> ระดับ ปริญญาตรี</span>
                      <span class="badge bg-warning-subtle text-warning-emphasis small">ป.ตรี</span>
                    </div>
                  </th>
                  <th scope="col" class="text-end" style="width: 100px; background-color: #F8FAFC;">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($all_departments)): ?>
                  <tr>
                    <td colspan="5" class="py-5 text-center bg-white">
                      <div class="py-4">
                        <i class="bi bi-building text-danger opacity-50" style="font-size: 3.5rem;"></i>
                        <h4 class="fw-bold text-dark mt-3">ยังไม่มีแผนกวิชาใน ปีการศึกษา <?= htmlspecialchars($selected_year) ?></h4>
                        <p class="text-secondary small mx-auto mb-3" style="max-width: 540px;">
                          คุณสามารถเริ่มต้นเพิ่มแผนกวิชาใหม่สำหรับปีการศึกษานี้ทีละแผนก หรือคลิกปุ่มด้านล่างเพื่อติดตั้ง 16 ภาควิชาทางการของวิทยาลัยอาชีวศึกษามหาสารคาม (35 สาขาวิชา) ได้ทันที
                        </p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                          <button type="button" class="btn btn-danger btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            <i class="bi bi-building-add me-1"></i> + เพิ่มแผนกวิชาใหม่
                          </button>
                          <form action="admin.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="seed_standard_departments">
                            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" onclick="return confirm('ต้องการติดตั้งโครงสร้าง 16 ภาควิชาทางการ (35 สาขา) เข้าสู่ปีการศึกษา <?= htmlspecialchars($selected_year) ?> ใช่หรือไม่?')">
                              <i class="bi bi-magic me-1"></i> ติดตั้ง 16 ภาควิชามาตรฐาน MVC (35 สาขา)
                            </button>
                          </form>
                          <button type="button" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#cloneYearModal">
                            <i class="bi bi-copy me-1"></i> คัดลอกจากปีอื่น
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($all_departments as $dept): ?>
                    <?php $dept_id = $dept['id']; ?>
                  <tr>
                    <!-- แผนกวิชา -->
                    <td class="bg-light bg-opacity-50">
                      <div class="fw-bold text-dark fs-6">
                        <?= htmlspecialchars($dept['dept_name']) ?>
                      </div>
                      <div class="small text-secondary mb-1">
                        รหัส: <span class="badge bg-white border text-dark font-monospace"><?= htmlspecialchars($dept['dept_code']) ?></span>
                      </div>
                      <?php if (!empty($dept['description'])): ?>
                        <div class="small text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($dept['description']) ?></div>
                      <?php endif; ?>
                    </td>

                    <!-- คอลัมน์: ระดับ ปวช. -->
                    <td>
                      <?php if (!empty($dept_matrix[$dept_id]['ปวช.'])): ?>
                        <?php foreach ($dept_matrix[$dept_id]['ปวช.'] as $prog): ?>
                          <div class="prog-pill-card <?= $prog['status'] === 'open' ? 'open' : 'closed' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-1">
                              <div>
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">
                                  <?= htmlspecialchars($prog['program_code']) ?>
                                </span>
                                <?php if (($prog['program_type'] ?? '') === 'ทวิภาคี'): ?>
                                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">ทวิภาคี</span>
                                <?php elseif (($prog['program_type'] ?? '') === 'ปกติและทวิภาคี'): ?>
                                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.68rem;">ปกติ/ทวิ</span>
                                <?php else: ?>
                                  <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">ปกติ</span>
                                <?php endif; ?>
                                <div class="fw-bold text-dark small mt-1">
                                  <?= htmlspecialchars($prog['program_name']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.775rem;">
                                  รับ: <strong><?= number_format($prog['seats_available']) ?></strong> ที่นั่ง 
                                  | สมัครแล้ว: <span class="text-danger fw-semibold"><?= number_format($prog['applicant_count']) ?></span> คน
                                </div>
                              </div>

                              <!-- ปุ่ม Toggle และ Edit -->
                              <div class="d-flex flex-column align-items-end gap-1">
                                <form action="admin.php" method="POST" class="d-inline">
                                  <input type="hidden" name="action" value="toggle_program_status">
                                  <input type="hidden" name="program_id" value="<?= $prog['id'] ?>">
                                  <input type="hidden" name="view" value="matrix">
                                  <?php if ($prog['status'] === 'open'): ?>
                                    <button type="submit" class="btn btn-success btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อปิดรับสมัคร">
                                      <i class="bi bi-check-circle-fill me-1"></i>เปิดรับ
                                    </button>
                                  <?php else: ?>
                                    <button type="submit" class="btn btn-secondary btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อเปิดรับสมัคร">
                                      <i class="bi bi-x-circle-fill me-1"></i>ปิดรับ
                                    </button>
                                  <?php endif; ?>
                                </form>
                                <div class="btn-group btn-group-sm">
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-secondary py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openEditProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="แก้ไขสาขาวิชา"
                                  >
                                    <i class="bi bi-pencil"></i>
                                  </button>
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-danger py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openDeleteProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="ลบสาขาวิชา"
                                  >
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="text-muted small py-2 text-center fst-italic">
                          ยังไม่เปิดสอนระดับนี้
                        </div>
                      <?php endif; ?>
                      <div class="text-center mt-2">
                        <button 
                          type="button" 
                          class="btn btn-outline-danger btn-sm py-0 px-2" 
                          style="font-size: 0.75rem;"
                          onclick="openAddProgramForDept(<?= $dept_id ?>, 'ปวช.')"
                        >
                          <i class="bi bi-plus"></i> เพิ่ม ปวช.
                        </button>
                      </div>
                    </td>

                    <!-- คอลัมน์: ระดับ ปวส. -->
                    <td>
                      <?php if (!empty($dept_matrix[$dept_id]['ปวส.'])): ?>
                        <?php foreach ($dept_matrix[$dept_id]['ปวส.'] as $prog): ?>
                          <div class="prog-pill-card <?= $prog['status'] === 'open' ? 'open' : 'closed' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-1">
                              <div>
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">
                                  <?= htmlspecialchars($prog['program_code']) ?>
                                </span>
                                <?php if (($prog['program_type'] ?? '') === 'ทวิภาคี'): ?>
                                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">ทวิภาคี</span>
                                <?php elseif (($prog['program_type'] ?? '') === 'ปกติและทวิภาคี'): ?>
                                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.68rem;">ปกติ/ทวิ</span>
                                <?php else: ?>
                                  <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">ปกติ</span>
                                <?php endif; ?>
                                <div class="fw-bold text-dark small mt-1">
                                  <?= htmlspecialchars($prog['program_name']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.775rem;">
                                  รับ: <strong><?= number_format($prog['seats_available']) ?></strong> ที่นั่ง 
                                  | สมัครแล้ว: <span class="text-primary fw-semibold"><?= number_format($prog['applicant_count']) ?></span> คน
                                </div>
                              </div>

                              <div class="d-flex flex-column align-items-end gap-1">
                                <form action="admin.php" method="POST" class="d-inline">
                                  <input type="hidden" name="action" value="toggle_program_status">
                                  <input type="hidden" name="program_id" value="<?= $prog['id'] ?>">
                                  <input type="hidden" name="view" value="matrix">
                                  <?php if ($prog['status'] === 'open'): ?>
                                    <button type="submit" class="btn btn-success btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อปิดรับสมัคร">
                                      <i class="bi bi-check-circle-fill me-1"></i>เปิดรับ
                                    </button>
                                  <?php else: ?>
                                    <button type="submit" class="btn btn-secondary btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อเปิดรับสมัคร">
                                      <i class="bi bi-x-circle-fill me-1"></i>ปิดรับ
                                    </button>
                                  <?php endif; ?>
                                </form>
                                <div class="btn-group btn-group-sm">
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-secondary py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openEditProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="แก้ไขสาขาวิชา"
                                  >
                                    <i class="bi bi-pencil"></i>
                                  </button>
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-danger py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openDeleteProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="ลบสาขาวิชา"
                                  >
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="text-muted small py-2 text-center fst-italic">
                          ยังไม่เปิดสอนระดับนี้
                        </div>
                      <?php endif; ?>
                      <div class="text-center mt-2">
                        <button 
                          type="button" 
                          class="btn btn-outline-primary btn-sm py-0 px-2" 
                          style="font-size: 0.75rem;"
                          onclick="openAddProgramForDept(<?= $dept_id ?>, 'ปวส.')"
                        >
                          <i class="bi bi-plus"></i> เพิ่ม ปวส.
                        </button>
                      </div>
                    </td>

                    <!-- คอลัมน์: ระดับ ปริญญาตรี -->
                    <td>
                      <?php if (!empty($dept_matrix[$dept_id]['ปริญญาตรี'])): ?>
                        <?php foreach ($dept_matrix[$dept_id]['ปริญญาตรี'] as $prog): ?>
                          <div class="prog-pill-card <?= $prog['status'] === 'open' ? 'open' : 'closed' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-1">
                              <div>
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.7rem;">
                                  <?= htmlspecialchars($prog['program_code']) ?>
                                </span>
                                <?php if (($prog['program_type'] ?? '') === 'ทวิภาคี'): ?>
                                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">ทวิภาคี</span>
                                <?php elseif (($prog['program_type'] ?? '') === 'ปกติและทวิภาคี'): ?>
                                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.68rem;">ปกติ/ทวิ</span>
                                <?php else: ?>
                                  <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">ปกติ</span>
                                <?php endif; ?>
                                <div class="fw-bold text-dark small mt-1">
                                  <?= htmlspecialchars($prog['program_name']) ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.775rem;">
                                  รับ: <strong><?= number_format($prog['seats_available']) ?></strong> ที่นั่ง 
                                  | สมัครแล้ว: <span class="text-warning-emphasis fw-semibold"><?= number_format($prog['applicant_count']) ?></span> คน
                                </div>
                              </div>

                              <div class="d-flex flex-column align-items-end gap-1">
                                <form action="admin.php" method="POST" class="d-inline">
                                  <input type="hidden" name="action" value="toggle_program_status">
                                  <input type="hidden" name="program_id" value="<?= $prog['id'] ?>">
                                  <input type="hidden" name="view" value="matrix">
                                  <?php if ($prog['status'] === 'open'): ?>
                                    <button type="submit" class="btn btn-success btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อปิดรับสมัคร">
                                      <i class="bi bi-check-circle-fill me-1"></i>เปิดรับ
                                    </button>
                                  <?php else: ?>
                                    <button type="submit" class="btn btn-secondary btn-sm py-0 px-2 fw-semibold" style="font-size: 0.725rem;" title="คลิกเพื่อเปิดรับสมัคร">
                                      <i class="bi bi-x-circle-fill me-1"></i>ปิดรับ
                                    </button>
                                  <?php endif; ?>
                                </form>
                                <div class="btn-group btn-group-sm">
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-secondary py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openEditProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="แก้ไขสาขาวิชา"
                                  >
                                    <i class="bi bi-pencil"></i>
                                  </button>
                                  <button 
                                    type="button" 
                                    class="btn btn-outline-danger py-0 px-1" 
                                    style="font-size: 0.7rem;"
                                    onclick='openDeleteProgramModal(<?= json_encode($prog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    title="ลบสาขาวิชา"
                                  >
                                    <i class="bi bi-trash"></i>
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="text-muted small py-2 text-center fst-italic">
                          ยังไม่เปิดสอนระดับนี้
                        </div>
                      <?php endif; ?>
                      <div class="text-center mt-2">
                        <button 
                          type="button" 
                          class="btn btn-outline-warning btn-sm py-0 px-2" 
                          style="font-size: 0.75rem;"
                          onclick="openAddProgramForDept(<?= $dept_id ?>, 'ปริญญาตรี')"
                        >
                          <i class="bi bi-plus"></i> เพิ่ม ป.ตรี
                        </button>
                      </div>
                    </td>

                    <!-- การจัดการแผนก -->
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button 
                          type="button" 
                          class="btn btn-outline-secondary"
                          onclick='openEditDeptModal(<?= json_encode($dept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="แก้ไขชื่อแผนกวิชา"
                        >
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <button 
                          type="button" 
                          class="btn btn-outline-danger"
                          onclick='openDeleteDeptModal(<?= json_encode($dept, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="ลบแผนกวิชานี้"
                        >
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ----------------------------------------------------------------- -->
      <!-- VIEW 2: ปวช. / ปวส. / ปริญญาตรี (ตารางแยกตามระดับการศึกษา)         -->
      <!-- ----------------------------------------------------------------- -->
      <?php else: ?>
        <?php 
          $target_level_name = 'ปวช.';
          $level_badge_class = 'pvc';
          if ($dept_view === 'pvs') {
              $target_level_name = 'ปวส.';
              $level_badge_class = 'pvs';
          } elseif ($dept_view === 'deg') {
              $target_level_name = 'ปริญญาตรี';
              $level_badge_class = 'deg';
          }
          $curr_programs = $programs_by_level[$target_level_name] ?? [];
        ?>

        <div class="table-container mb-4">
          <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <span class="badge badge-level <?= $level_badge_class ?> fs-6 me-2">ระดับ <?= $target_level_name ?></span>
              <strong class="text-dark">สาขาวิชาที่เปิดสอนในระดับ <?= $target_level_name ?> ประจำปีการศึกษา <?= htmlspecialchars($selected_year) ?> (<?= count($curr_programs) ?> สาขา)</strong>
            </div>
            <button 
              type="button" 
              class="btn btn-danger btn-sm"
              onclick="openAddProgramWithLevel('<?= $target_level_name ?>')"
              style="background-color: var(--color-primary); border-color: var(--color-primary);"
            >
              <i class="bi bi-plus-lg me-1"></i> เพิ่มสาขาวิชาระดับ <?= $target_level_name ?> (ปี <?= htmlspecialchars($selected_year) ?>)
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col" style="width: 120px;">รหัสสาขา</th>
                  <th scope="col">ชื่อสาขาวิชา</th>
                  <th scope="col">แผนกวิชา</th>
                  <th scope="col" style="width: 130px;">จำนวนที่รับ</th>
                  <th scope="col" style="width: 130px;">ผู้สมัครปัจจุบัน</th>
                  <th scope="col" style="width: 160px;">สถานะการรับสมัคร</th>
                  <th scope="col" class="text-end" style="width: 140px;">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($curr_programs)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                      <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                      ยังไม่มีสาขาวิชาที่เปิดในระดับ <?= $target_level_name ?> ประจำปีการศึกษา <?= htmlspecialchars($selected_year) ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($curr_programs as $prg): ?>
                    <tr>
                      <td>
                        <span class="badge bg-light text-dark border font-monospace fw-bold">
                          <?= htmlspecialchars($prg['program_code']) ?>
                        </span>
                      </td>

                      <td>
                        <div class="fw-bold text-dark fs-6">
                          <?= htmlspecialchars($prg['program_name']) ?>
                        </div>
                        <div class="mt-1">
                          <?php if (($prg['program_type'] ?? '') === 'ทวิภาคี'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;"><i class="bi bi-briefcase me-1"></i>ทวิภาคี</span>
                          <?php elseif (($prg['program_type'] ?? '') === 'ปกติและทวิภาคี'): ?>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.72rem;"><i class="bi bi-arrow-repeat me-1"></i>ปกติ & ทวิภาคี</span>
                          <?php else: ?>
                            <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;"><i class="bi bi-building me-1"></i>ปกติ</span>
                          <?php endif; ?>
                        </div>
                      </td>

                      <td>
                        <span class="badge bg-secondary-subtle text-secondary border me-1"><?= htmlspecialchars($prg['dept_code']) ?></span>
                        <span class="text-dark small fw-semibold"><?= htmlspecialchars($prg['dept_name']) ?></span>
                      </td>

                      <td>
                        <span class="fw-bold text-dark"><?= number_format($prg['seats_available']) ?></span> ที่นั่ง
                      </td>

                      <td>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">
                          <?= number_format($prg['applicant_count']) ?> คน
                        </span>
                      </td>

                      <!-- ปุ่ม Toggle สลับสถานะเปิด/ปิด -->
                      <td>
                        <form action="admin.php" method="POST" class="d-inline">
                          <input type="hidden" name="action" value="toggle_program_status">
                          <input type="hidden" name="program_id" value="<?= $prg['id'] ?>">
                          <input type="hidden" name="view" value="<?= $dept_view ?>">
                          <?php if ($prg['status'] === 'open'): ?>
                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" title="คลิกเพื่อปิดรับสมัคร">
                              <i class="bi bi-check-circle-fill"></i>
                              <span>เปิดรับสมัคร</span>
                            </button>
                          <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-secondary rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1" title="คลิกเพื่อเปิดรับสมัคร">
                              <i class="bi bi-x-circle-fill"></i>
                              <span>ปิดรับสมัคร</span>
                            </button>
                          <?php endif; ?>
                        </form>
                      </td>

                      <!-- Action Buttons -->
                      <td class="text-end">
                        <div class="btn-group btn-group-sm">
                          <button 
                            type="button" 
                            class="btn btn-outline-primary"
                            onclick='openEditProgramModal(<?= json_encode($prg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            title="แก้ไขข้อมูลสาขา"
                          >
                            <i class="bi bi-pencil-square"></i>
                          </button>
                          <button 
                            type="button" 
                            class="btn btn-outline-danger"
                            onclick='openDeleteProgramModal(<?= json_encode($prg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            title="ลบสาขาวิชา"
                          >
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- TAB 3: จัดการรอบการรับสมัคร (ADMISSION ROUNDS - QUOTA & NORMAL)        -->
    <!-- ===================================================================== -->
    <?php elseif ($current_tab === 'rounds'): ?>

      <!-- Header & Academic Year Switcher -->
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="fw-bold mb-1" style="color: var(--color-primary-deep);">
            <i class="bi bi-calendar-event-fill text-danger me-2"></i>จัดการรอบการรับสมัคร (Admission Rounds)
          </h3>
          <div class="text-secondary small">
            กำหนดรอบโควตา เพิ่มรอบรับสมัครใหม่ เพิ่มรอบปกติ/ทั่วไป และจัดการช่วงเวลารับสมัครประจำปีการศึกษา <?= htmlspecialchars($selected_year) ?>
          </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
          <!-- เลือกปีการศึกษา -->
          <div class="input-group input-group-sm" style="width: auto;">
            <span class="input-group-text bg-white text-secondary fw-semibold">
              <i class="bi bi-calendar3 text-danger me-1"></i> ปีการศึกษา:
            </span>
            <select class="form-select fw-bold text-danger border-start-0" onchange="window.location.href='admin.php?tab=rounds&academic_year=' + encodeURIComponent(this.value)">
              <?php foreach ($available_academic_years as $ay): ?>
                <option value="<?= htmlspecialchars($ay['year_name']) ?>" <?= $ay['year_name'] === $selected_year ? 'selected' : '' ?>>
                  ปี <?= htmlspecialchars($ay['year_name']) ?> <?= !empty($ay['is_active']) ? ' (รอบรับสมัครหลัก)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ปุ่มเพิ่มรอบ -->
          <button type="button" class="btn btn-warning btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#addQuotaRoundModal">
            <i class="bi bi-award-fill text-danger"></i>
            <span>+ เพิ่มรอบโควตา</span>
          </button>
          <button type="button" class="btn btn-primary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#addNormalRoundModal">
            <i class="bi bi-people-fill"></i>
            <span>+ เพิ่มรอบปกติ (ทั่วไป)</span>
          </button>
        </div>
      </div>

      <!-- Round KPI Stats Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="stat-card total h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">รอบทั้งหมด (ปี <?= htmlspecialchars($selected_year) ?>)</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($round_stats['total']) ?> รอบ</h3>
              </div>
              <div class="stat-icon total"><i class="bi bi-calendar-range"></i></div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="stat-card pending h-100 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">รอบโควตา (Quota)</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($round_stats['quota']) ?> รอบ</h3>
              </div>
              <div class="stat-icon" style="background-color: #FEF3C7; color: #D97706;"><i class="bi bi-award-fill"></i></div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="stat-card paid h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">รอบปกติ / ทั่วไป (Normal)</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($round_stats['normal']) ?> รอบ</h3>
              </div>
              <div class="stat-icon" style="background-color: #EBF2FA; color: #1B365D;"><i class="bi bi-people-fill"></i></div>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="stat-card approved h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">กำลังเปิดรับสมัคร (Active)</div>
                <h3 class="fw-bold text-success mt-1 mb-0"><?= number_format($round_stats['open']) ?> รอบ</h3>
              </div>
              <div class="stat-icon approved"><i class="bi bi-check-circle-fill"></i></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Pills Bar -->
      <div class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="small fw-semibold text-secondary me-2"><i class="bi bi-funnel-fill text-danger me-1"></i> กรองแสดงผล:</span>
            <a href="admin.php?tab=rounds&academic_year=<?= urlencode($selected_year) ?>" 
               class="btn btn-sm rounded-pill px-3 <?= empty($round_filter_type) ? 'btn-danger' : 'btn-outline-secondary' ?>">
              ทั้งหมด (<?= number_format($round_stats['total']) ?>)
            </a>
            <a href="admin.php?tab=rounds&round_type=quota&academic_year=<?= urlencode($selected_year) ?>" 
               class="btn btn-sm rounded-pill px-3 <?= $round_filter_type === 'quota' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark' ?>">
              <i class="bi bi-award-fill text-danger me-1"></i> เฉพาะรอบโควตา (<?= number_format($round_stats['quota']) ?>)
            </a>
            <a href="admin.php?tab=rounds&round_type=normal&academic_year=<?= urlencode($selected_year) ?>" 
               class="btn btn-sm rounded-pill px-3 <?= $round_filter_type === 'normal' ? 'btn-primary' : 'btn-outline-primary' ?>">
              <i class="bi bi-people-fill me-1"></i> เฉพาะรอบปกติ / ทั่วไป (<?= number_format($round_stats['normal']) ?>)
            </a>
          </div>

          <div class="small text-muted">
            พบ <?= number_format(count($all_rounds)) ?> รายการ
          </div>
        </div>
      </div>

      <!-- Table of Admission Rounds -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr class="text-secondary small">
                <th class="ps-4 py-3" style="width: 140px;">ประเภทรอบ</th>
                <th class="py-3">ชื่อรอบรับสมัคร & รหัส</th>
                <th class="py-3">ระดับที่เปิดรับ</th>
                <th class="py-3">เงื่อนไข / โควตา</th>
                <th class="py-3">ช่วงเวลารับสมัคร</th>
                <th class="py-3">ประกาศผล / มอบตัว</th>
                <th class="py-3 text-center">สถานะ</th>
                <th class="pe-4 py-3 text-end" style="width: 180px;">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($all_rounds)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    ยังไม่พบข้อมูลรอบการรับสมัครในปีการศึกษา <?= htmlspecialchars($selected_year) ?><br>
                    <div class="mt-3 d-flex justify-content-center gap-2">
                      <button type="button" class="btn btn-warning btn-sm text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#addQuotaRoundModal">
                        <i class="bi bi-award-fill text-danger me-1"></i> เพิ่มรอบโควตา
                      </button>
                      <button type="button" class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addNormalRoundModal">
                        <i class="bi bi-people-fill me-1"></i> เพิ่มรอบปกติ
                      </button>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($all_rounds as $rnd): ?>
                  <tr>
                    <td class="ps-4">
                      <?php if ($rnd['round_type'] === 'quota'): ?>
                        <span class="badge bg-warning text-dark border border-warning px-2 py-1 shadow-xs fw-semibold">
                          <i class="bi bi-award-fill text-danger me-1"></i> รอบโควตา
                        </span>
                      <?php else: ?>
                        <span class="badge bg-primary text-white border border-primary px-2 py-1 shadow-xs fw-semibold">
                          <i class="bi bi-people-fill me-1"></i> รอบปกติ
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="fw-bold text-dark"><?= htmlspecialchars($rnd['round_name']) ?></div>
                      <div class="small text-muted font-monospace">
                        <code><?= htmlspecialchars($rnd['round_code']) ?></code>
                        <span class="ms-1 badge bg-secondary-subtle text-secondary small">ปี <?= htmlspecialchars($rnd['academic_year']) ?></span>
                      </div>
                      <?php if (!empty($rnd['description'])): ?>
                        <div class="small text-secondary mt-1 text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($rnd['description']) ?>">
                          <?= htmlspecialchars($rnd['description']) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex flex-wrap gap-1">
                        <?php 
                          $levels = array_filter(array_map('trim', explode(',', $rnd['education_levels'] ?? '')));
                          foreach ($levels as $lvl):
                        ?>
                          <span class="badge bg-light text-dark border small px-2 py-1">
                            <?= htmlspecialchars($lvl) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    </td>
                    <td>
                      <?php if ($rnd['round_type'] === 'quota'): ?>
                        <div>
                          <small class="text-muted d-block">เกรดเฉลี่ย (GPAX):</small>
                          <strong class="text-danger"><?= !empty($rnd['min_gpa']) && $rnd['min_gpa'] > 0 ? '>= ' . number_format($rnd['min_gpa'], 2) : 'ไม่จำกัด' ?></strong>
                        </div>
                        <?php if (!empty($rnd['quota_seats']) && $rnd['quota_seats'] > 0): ?>
                          <div class="small text-muted mt-1">รับ: <strong><?= number_format($rnd['quota_seats']) ?></strong> ที่นั่ง</div>
                        <?php endif; ?>
                      <?php else: ?>
                        <div>
                          <small class="text-muted d-block">จำนวนรับรวม:</small>
                          <strong><?= !empty($rnd['quota_seats']) && $rnd['quota_seats'] > 0 ? number_format($rnd['quota_seats']) . ' ที่นั่ง' : 'ตามแผนรับ' ?></strong>
                        </div>
                        <div class="small text-muted mt-1">เกรด: <?= !empty($rnd['min_gpa']) && $rnd['min_gpa'] > 0 ? '>= ' . number_format($rnd['min_gpa'], 2) : 'ไม่จำกัด' ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div>
                        <small class="text-muted d-block">เปิด - ปิดรับสมัคร:</small>
                        <span class="fw-semibold text-dark small">
                          <?= formatThaiDate($rnd['start_date']) ?> - <?= formatThaiDate($rnd['end_date']) ?>
                        </span>
                      </div>
                    </td>
                    <td>
                      <div class="small">
                        <div><span class="text-muted">ประกาศผล:</span> <strong class="text-primary"><?= formatThaiDate($rnd['announcement_date']) ?></strong></div>
                        <div><span class="text-muted">รายงานตัว:</span> <strong class="text-success"><?= formatThaiDate($rnd['registration_date']) ?></strong></div>
                      </div>
                    </td>
                    <td class="text-center">
                      <?php if ($rnd['status'] === 'open'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                          <i class="bi bi-check-circle-fill me-1"></i> เปิดรับสมัคร
                        </span>
                      <?php elseif ($rnd['status'] === 'closed'): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 small">
                          <i class="bi bi-x-circle me-1"></i> ปิดรับสมัคร
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 small">
                          <i class="bi bi-clock-history me-1"></i> เร็วๆ นี้
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="pe-4 text-end">
                      <div class="btn-group btn-group-sm">
                        <!-- ปุ่มสลับสถานะเปิด/ปิด -->
                        <form action="admin.php" method="POST" class="d-inline">
                          <input type="hidden" name="action" value="toggle_admission_round_status">
                          <input type="hidden" name="round_id" value="<?= (int)$rnd['id'] ?>">
                          <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">
                          <button 
                            type="submit" 
                            class="btn <?= $rnd['status'] === 'open' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" 
                            title="<?= $rnd['status'] === 'open' ? 'คลิกเพื่อปิดรับสมัครรอบนี้' : 'คลิกเพื่อเปิดรับสมัครรอบนี้' ?>"
                          >
                            <i class="bi <?= $rnd['status'] === 'open' ? 'bi-pause-fill' : 'bi-play-fill' ?>"></i>
                            <?= $rnd['status'] === 'open' ? 'ปิด' : 'เปิด' ?>
                          </button>
                        </form>

                        <!-- ปุ่มแก้ไข -->
                        <button 
                          type="button" 
                          class="btn btn-outline-primary" 
                          onclick='openEditRoundModal(<?= json_encode($rnd, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="แก้ไขข้อมูลรอบ"
                        >
                          <i class="bi bi-pencil-square"></i>
                        </button>

                        <!-- ปุ่มลบ -->
                        <button 
                          type="button" 
                          class="btn btn-outline-danger" 
                          onclick='openDeleteRoundModal(<?= json_encode($rnd, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="ลบรอบนี้"
                        >
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ===================================================================== -->
    <!-- TAB 4: จัดการผู้ดูแลระบบ (ADMINS CRUD)                                   -->
    <!-- ===================================================================== -->
    <?php elseif ($current_tab === 'admins'): ?>

      <div class="mb-4">
        <h3 class="fw-bold mb-1" style="color: var(--color-primary-deep);">
          <i class="bi bi-shield-person text-danger me-2"></i>จัดการรายชื่อผู้ดูแลระบบ (Admin & Staff)
        </h3>
        <div class="text-secondary small">
          ผู้ดูแลระบบสามารถเพิ่มบัญชีใหม่ แก้ไขข้อมูล ปรับเปลี่ยนสิทธิ์ และลบบัญชีผู้ดูแลระบบได้
        </div>
      </div>

      <!-- Admin KPI Stats Cards -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="stat-card total h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ผู้ดูแลระบบทั้งหมด</div>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?= number_format($admin_stats['total']) ?> บัญชี</h3>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-people-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="stat-card superadmin h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">ผู้ดูแลระบบสูงสุด (Superadmin)</div>
                <h3 class="fw-bold text-danger mt-1 mb-0"><?= number_format($admin_stats['superadmin']) ?> บัญชี</h3>
              </div>
              <div class="stat-icon bg-danger-subtle text-danger">
                <i class="bi bi-shield-fill-check"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="stat-card officer h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-secondary small fw-semibold">เจ้าหน้าที่งานทะเบียน (Officer)</div>
                <h3 class="fw-bold text-primary mt-1 mb-0"><?= number_format($admin_stats['officer']) ?> บัญชี</h3>
              </div>
              <div class="stat-icon bg-primary-subtle text-primary">
                <i class="bi bi-person-badge"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Admins Table -->
      <div class="table-container mb-4">
        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="fw-bold text-dark">
            รายชื่อผู้มีสิทธิ์เข้าใช้งานระบบหลังบ้าน (<?= count($admin_list) ?> บัญชี)
          </div>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal" style="background-color: var(--color-primary); border-color: var(--color-primary);">
            <i class="bi bi-plus-lg me-1"></i> เพิ่มผู้ดูแลระบบใหม่
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col" style="width: 70px;">ลำดับ</th>
                <th scope="col">ผู้ดูแลระบบ</th>
                <th scope="col">ชื่อผู้ใช้ (Username)</th>
                <th scope="col">อีเมล</th>
                <th scope="col" style="width: 200px;">บทบาท/สิทธิ์</th>
                <th scope="col">วันที่สร้าง</th>
                <th scope="col" class="text-end" style="width: 170px;">การดำเนินการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admin_list as $idx => $adm): ?>
                <?php 
                  $is_current_user = ($adm['id'] === $admin_id);
                  $initial = mb_substr($adm['full_name'], 0, 1, 'UTF-8');
                ?>
                <tr>
                  <td>
                    <span class="text-muted fw-bold"><?= $idx + 1 ?></span>
                  </td>

                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="admin-avatar"><?= htmlspecialchars($initial) ?></div>
                      <div>
                        <div class="fw-bold text-dark">
                          <?= htmlspecialchars($adm['full_name']) ?>
                          <?php if ($is_current_user): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1 small">
                              <i class="bi bi-person-check-fill"></i> คุณ
                            </span>
                          <?php endif; ?>
                        </div>
                        <small class="text-muted">ID: #<?= $adm['id'] ?></small>
                      </div>
                    </div>
                  </td>

                  <td>
                    <span class="font-monospace fw-semibold text-danger">
                      <?= htmlspecialchars($adm['username']) ?>
                    </span>
                  </td>

                  <td>
                    <?= !empty($adm['email']) ? htmlspecialchars($adm['email']) : '<span class="text-muted">-</span>' ?>
                  </td>

                  <td>
                    <?php if ($adm['role'] === 'superadmin'): ?>
                      <span class="badge bg-danger text-white px-2 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="bi bi-shield-fill-check"></i>
                        <span>ผู้ดูแลระบบสูงสุด (Superadmin)</span>
                      </span>
                    <?php else: ?>
                      <span class="badge bg-primary text-white px-2 py-1 rounded-pill d-inline-flex align-items-center gap-1">
                        <i class="bi bi-person-badge"></i>
                        <span>เจ้าหน้าที่ (Officer)</span>
                      </span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <small class="text-muted">
                      <?= date('d/m/Y H:i', strtotime($adm['created_at'])) ?>
                    </small>
                  </td>

                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button 
                        type="button" 
                        class="btn btn-outline-primary"
                        data-bs-toggle="modal" 
                        data-bs-target="#editAdminModal"
                        onclick='openEditAdminModal(<?= json_encode($adm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                        title="แก้ไขข้อมูล"
                      >
                        <i class="bi bi-pencil-square me-1"></i> แก้ไข
                      </button>

                      <?php if ($is_current_user): ?>
                        <button type="button" class="btn btn-outline-secondary disabled" title="ไม่สามารถลบบัญชีของตนเองได้">
                          <i class="bi bi-trash"></i>
                        </button>
                      <?php elseif ($adm['role'] === 'superadmin' && $admin_stats['superadmin'] <= 1): ?>
                        <button type="button" class="btn btn-outline-secondary disabled" title="ต้องมี Superadmin อย่างน้อย 1 ท่าน">
                          <i class="bi bi-trash"></i>
                        </button>
                      <?php else: ?>
                        <button 
                          type="button" 
                          class="btn btn-outline-danger"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteAdminModal"
                          onclick='openDeleteAdminModal(<?= json_encode($adm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                          title="ลบบัญชีผู้ดูแลระบบ"
                        >
                          <i class="bi bi-trash"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <!-- ===================================================================== -->
    <!-- TAB 4: ปรับแต่งและจัดการเนื้อหาหน้าแรก (HOMEPAGE CMS & BLOGS)          -->
    <!-- ===================================================================== -->
    <?php elseif ($current_tab === 'homepage'): ?>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
          <h3 class="fw-bold mb-1" style="color: var(--color-primary-deep);">
            <i class="bi bi-layout-text-window-reverse text-danger me-2"></i>ปรับแต่งและจัดการเนื้อหาหน้าแรก (Index CMS)
          </h3>
          <div class="text-secondary small">
            แก้ไขข้อความ ปรับแต่งลิงก์ และอัปโหลดเปลี่ยนรูปภาพตามบล็อกต่างๆ ในหน้าแรก (index.php) ได้ตามต้องการ
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="index.php" target="_blank" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>ดูหน้าแรกจริง</span>
          </a>
          <button type="button" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#resetHomepageModal">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>คืนค่าเริ่มต้น</span>
          </button>
          <button type="submit" form="homepageCMSForm" class="btn btn-danger btn-sm px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background-color: var(--color-primary); border-color: var(--color-primary);">
            <i class="bi bi-save2-fill"></i>
            <span>บันทึกการเปลี่ยนแปลงทั้งหมด</span>
          </button>
        </div>
      </div>

      <!-- Shortcut Navigator Pills -->
      <div class="card border-0 shadow-sm rounded-3 mb-4 p-3 bg-white">
        <div class="d-flex align-items-center flex-wrap gap-2">
          <span class="small fw-bold text-secondary me-2"><i class="bi bi-compass-fill text-danger me-1"></i> เลื่อนไปยังบล็อก:</span>
          <a href="#block-topbar" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">1. แถบประกาศบนสุด</a>
          <a href="#block-navbar" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">2. โลโก้ & เมนู</a>
          <a href="#block-hero" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">3. แบนเนอร์หลัก & รูป Hero</a>
          <a href="#block-services" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">4. กล่องบริการ 4 บล็อก</a>
          <a href="#block-notice" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">5. กำหนดการสำคัญ</a>
          <a href="#block-footer" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3">6. ข้อมูลติดต่อ & ท้ายเว็บ</a>
        </div>
      </div>

      <form action="admin.php" method="POST" enctype="multipart/form-data" id="homepageCMSForm">
        <input type="hidden" name="action" value="save_homepage_settings">

        <!-- ================================================================= -->
        <!-- บล็อกที่ 1: แถบประกาศข้อมูลบนสุด (Topbar)                          -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-topbar">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-megaphone text-danger me-2"></i>บล็อกที่ 1: แถบประกาศข้อมูลบนสุด (Topbar Announcement)
              </h5>
              <small class="text-secondary">แถบข้อความสีแดงเลือดหมูเข้มด้านบนสุดของหน้าเว็บ</small>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1">ส่วนหัวบนสุด</span>
          </div>
          <div class="cms-block-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-geo-alt-fill text-warning me-1"></i> ข้อความที่ตั้ง / สังกัดสถานศึกษา:
                </label>
                <input 
                  type="text" 
                  name="topbar_address" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['topbar_address'] ?? '') ?>"
                  placeholder="เช่น วิทยาลัยอาชีวศึกษามหาสารคาม สถาบันการอาชีวศึกษาภาคตะวันออกเฉียงเหนือ 3"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-telephone-fill text-danger me-1"></i> เบอร์โทรศัพท์ติดต่อด่วน:
                </label>
                <input 
                  type="text" 
                  name="topbar_phone" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['topbar_phone'] ?? '') ?>"
                  placeholder="เช่น 043-711-371"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-mortarboard-fill text-primary me-1"></i> ป้ายปีการศึกษา (Badge):
                </label>
                <input 
                  type="text" 
                  name="topbar_badge" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['topbar_badge'] ?? '') ?>"
                  placeholder="เช่น ปีการศึกษา 2570"
                >
              </div>
            </div>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- บล็อกที่ 2: โลโก้และแถบเมนู (Navbar & Logo)                        -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-navbar">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-images text-danger me-2"></i>บล็อกที่ 2: ตราสัญลักษณ์ (Logo) & ชื่อระบบรับสมัคร
              </h5>
              <small class="text-secondary">ตราสัญลักษณ์สถาบันและข้อความแบรนด์บนแถบเมนูนำทาง</small>
            </div>
            <span class="badge bg-secondary-subtle text-secondary px-3 py-1">โลโก้และแบรนด์</span>
          </div>
          <div class="cms-block-body">
            <div class="row g-4 align-items-center">
              <div class="col-md-4 text-center">
                <label class="form-label fw-semibold text-dark small d-block mb-2">ตราสัญลักษณ์ปัจจุบัน:</label>
                <div class="image-preview-box d-flex flex-column align-items-center justify-content-center p-3">
                  <img 
                    src="<?= htmlspecialchars($hp_settings['site_logo'] ?? 'img/Logo-W.png') ?>" 
                    id="preview_site_logo" 
                    alt="Logo Preview" 
                    style="max-height: 90px; width: auto; object-fit: contain;"
                  >
                  <small class="text-muted mt-2 font-monospace d-block text-truncate" style="max-width: 200px;">
                    <?= htmlspecialchars($hp_settings['site_logo'] ?? '') ?>
                  </small>
                </div>
              </div>
              <div class="col-md-8">
                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark small">
                    <i class="bi bi-cloud-arrow-up-fill text-danger me-1"></i> อัปโหลดรูปภาพโลโก้ใหม่:
                  </label>
                  <input 
                    type="file" 
                    name="site_logo" 
                    id="input_site_logo" 
                    class="form-control" 
                    accept="image/png, image/jpeg, image/webp, image/svg+xml"
                    onchange="previewImage(this, 'preview_site_logo')"
                  >
                  <div class="form-text small">รองรับไฟล์ PNG, JPG, WEBP, SVG ขนาดไม่เกิน 8MB (แนะนำพื้นหลังโปร่งใส PNG)</div>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="remove_site_logo" value="1" id="remove_site_logo">
                  <label class="form-check-label small text-secondary" for="remove_site_logo">
                    รีเซ็ตกลับไปใช้โลโก้เริ่มต้นของสถาบัน (img/Logo-W.png)
                  </label>
                </div>
                <div class="row g-2">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-dark small">ชื่อระบบ (Title):</label>
                    <input 
                      type="text" 
                      name="navbar_title" 
                      class="form-control" 
                      value="<?= htmlspecialchars($hp_settings['navbar_title'] ?? '') ?>"
                      placeholder="ระบบรับสมัครนักศึกษา"
                    >
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-dark small">ชื่อสถานศึกษา (Subtitle):</label>
                    <input 
                      type="text" 
                      name="navbar_subtitle" 
                      class="form-control" 
                      value="<?= htmlspecialchars($hp_settings['navbar_subtitle'] ?? '') ?>"
                      placeholder="วิทยาลัยอาชีวศึกษามหาสารคาม"
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- บล็อกที่ 3: แบนเนอร์หลัก & รูปภาพ Hero (Hero Section Banner)       -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-hero">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-card-heading text-danger me-2"></i>บล็อกที่ 3: แบนเนอร์หลัก & รูปภาพประชาสัมพันธ์ (Hero Section)
              </h5>
              <small class="text-secondary">หัวข้อหลัก ข้อความชวนสมัคร ปุ่มดำเนินการ และรูปภาพแบนเนอร์ขนาดใหญ่ด้านขวา</small>
            </div>
            <span class="badge bg-danger text-white px-3 py-1">บล็อกหลัก (Hero)</span>
          </div>
          <div class="cms-block-body">
            <div class="row g-4">
              <!-- คอลัมน์ซ้าย: ข้อความและปุ่ม -->
              <div class="col-lg-7">
                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark small">
                    <i class="bi bi-patch-check-fill text-danger me-1"></i> ข้อความป้ายสถานะรอบรับสมัคร (Status Badge):
                  </label>
                  <input 
                    type="text" 
                    name="hero_badge" 
                    class="form-control" 
                    value="<?= htmlspecialchars($hp_settings['hero_badge'] ?? '') ?>"
                    placeholder="เช่น เปิดรับสมัครรอบโควตาและรอบทั่วไป ประจำปีการศึกษา 2570"
                  >
                </div>

                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-dark small">หัวข้อหลัก บรรทัดที่ 1:</label>
                    <input 
                      type="text" 
                      name="hero_title" 
                      class="form-control" 
                      value="<?= htmlspecialchars($hp_settings['hero_title'] ?? '') ?>"
                      placeholder="เช่น สร้างอนาคตวิชาชีพสู่มืออาชีพ"
                    >
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-danger small">ข้อความไฮไลต์สีแดง บรรทัดที่ 2:</label>
                    <input 
                      type="text" 
                      name="hero_highlight" 
                      class="form-control border-danger" 
                      value="<?= htmlspecialchars($hp_settings['hero_highlight'] ?? '') ?>"
                      placeholder="เช่น รับสมัครนักเรียน นักศึกษาใหม่"
                    >
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark small">หัวข้อย่อยสถานศึกษา (Subtitle):</label>
                  <input 
                    type="text" 
                    name="hero_subtitle" 
                    class="form-control" 
                    value="<?= htmlspecialchars($hp_settings['hero_subtitle'] ?? '') ?>"
                    placeholder="เช่น วิทยาลัยอาชีวศึกษามหาสารคาม (Mahasarakham Vocational College)"
                  >
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark small">รายละเอียดเนื้อหาแนะนำ (Description):</label>
                  <textarea 
                    name="hero_description" 
                    class="form-control" 
                    rows="3"
                    placeholder="คำบรรยายข้อความประชาสัมพันธ์การสมัครเรียน..."
                  ><?= htmlspecialchars($hp_settings['hero_description'] ?? '') ?></textarea>
                </div>

                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-dark small">ปุ่มกดที่ 1 (ปุ่มสีแดงหลัก):</label>
                    <input 
                      type="text" 
                      name="hero_btn1_text" 
                      class="form-control form-control-sm mb-1" 
                      value="<?= htmlspecialchars($hp_settings['hero_btn1_text'] ?? '') ?>"
                      placeholder="ข้อความปุ่ม เช่น สมัครเรียนออนไลน์ทันที"
                    >
                    <input 
                      type="text" 
                      name="hero_btn1_link" 
                      class="form-control form-control-sm text-secondary" 
                      value="<?= htmlspecialchars($hp_settings['hero_btn1_link'] ?? '') ?>"
                      placeholder="ลิงก์ URL เช่น /register"
                    >
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label fw-semibold text-dark small">ปุ่มกดที่ 2 (ปุ่มเส้นขอบแดง):</label>
                    <input 
                      type="text" 
                      name="hero_btn2_text" 
                      class="form-control form-control-sm mb-1" 
                      value="<?= htmlspecialchars($hp_settings['hero_btn2_text'] ?? '') ?>"
                      placeholder="ข้อความปุ่ม เช่น ขั้นตอนการสมัคร"
                    >
                    <input 
                      type="text" 
                      name="hero_btn2_link" 
                      class="form-control form-control-sm text-secondary" 
                      value="<?= htmlspecialchars($hp_settings['hero_btn2_link'] ?? '') ?>"
                      placeholder="ลิงก์ URL เช่น #steps"
                    >
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark small">
                    ป้ายระดับการศึกษา (คั่นแต่ละรายการด้วยเครื่องหมายจุลภาค <code>,</code>):
                  </label>
                  <input 
                    type="text" 
                    name="hero_badges_text" 
                    class="form-control" 
                    value="<?= htmlspecialchars($hp_settings['hero_badges_text'] ?? '') ?>"
                    placeholder="ระดับ ปวช., ระดับ ปวส., ระดับ ปริญญาตรี"
                  >
                </div>

                <div class="p-3 bg-light rounded-3 border">
                  <div class="fw-semibold text-dark small mb-2"><i class="bi bi-card-text text-danger me-1"></i> กล่องลอยบนรูปภาพ (Floating Badge Card):</div>
                  <div class="row g-2">
                    <div class="col-sm-4">
                      <label class="form-label small text-muted mb-1">ไอคอน Bootstrap:</label>
                      <input 
                        type="text" 
                        name="hero_card_icon" 
                        class="form-control form-control-sm font-monospace" 
                        value="<?= htmlspecialchars($hp_settings['hero_card_icon'] ?? 'bi-award-fill') ?>"
                        placeholder="bi-award-fill"
                      >
                    </div>
                    <div class="col-sm-4">
                      <label class="form-label small text-muted mb-1">หัวข้อกล่องลอย:</label>
                      <input 
                        type="text" 
                        name="hero_card_title" 
                        class="form-control form-control-sm" 
                        value="<?= htmlspecialchars($hp_settings['hero_card_title'] ?? '') ?>"
                        placeholder="มาตรฐานอาชีวศึกษาชั้นนำ"
                      >
                    </div>
                    <div class="col-sm-4">
                      <label class="form-label small text-muted mb-1">คำบรรยายกล่องลอย:</label>
                      <input 
                        type="text" 
                        name="hero_card_desc" 
                        class="form-control form-control-sm" 
                        value="<?= htmlspecialchars($hp_settings['hero_card_desc'] ?? '') ?>"
                        placeholder="มุ่งเน้นทักษะจริงสู่ตลาดแรงงานสากล"
                      >
                    </div>
                  </div>
                </div>
              </div>

              <!-- คอลัมน์ขวา: จัดการรูปภาพ Hero Banner -->
              <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold text-dark small mb-0">
                      <i class="bi bi-image text-danger me-1"></i> รูปภาพแบนเนอร์ Hero ปัจจุบัน:
                    </label>
                    <span class="badge bg-danger-subtle text-danger small">ภาพหลักหน้าแรก</span>
                  </div>

                  <div class="image-preview-box mb-3 text-center" style="min-height: 200px;">
                    <div style="width: 100%; aspect-ratio: 16 / 10; max-height: 250px; overflow: hidden; border-radius: 8px; background: #0F172A; display: flex; align-items: center; justify-content: center;">
                      <img 
                        src="<?= htmlspecialchars($hp_settings['hero_image'] ?? 'assets/images/hero.jpg') ?>" 
                        id="preview_hero_image" 
                        alt="Hero Image Preview"
                        style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;"
                        onerror="this.src='https://placehold.co/800x550/8B1E2B/FFFFFF?text=MVC+Admission';"
                      >
                    </div>
                    <small class="text-muted mt-2 d-block font-monospace text-truncate">
                      <?= htmlspecialchars($hp_settings['hero_image'] ?? '') ?>
                    </small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold text-dark small">
                      <i class="bi bi-upload text-danger me-1"></i> เลือกรูปภาพใหม่เพื่อเปลี่ยน (Upload New Hero Image):
                    </label>
                    <input 
                      type="file" 
                      name="hero_image" 
                      id="input_hero_image" 
                      class="form-control" 
                      accept="image/jpeg, image/png, image/webp"
                      onchange="previewImage(this, 'preview_hero_image')"
                    >
                    <div class="form-text small">แนะนำขนาดประมาณ 800 x 550 พิกเซล (อัตราส่วน 16:11 หรือ 16:9) รองรับ JPG, PNG, WEBP สูงสุด 8MB</div>
                  </div>

                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="remove_hero_image" value="1" id="remove_hero_image">
                    <label class="form-check-label small text-secondary" for="remove_hero_image">
                      คืนค่ากลับไปใช้รูปภาพมาตรฐาน (assets/images/hero.jpg)
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- บล็อกที่ 4: กล่องบริการ & บล็อกขั้นตอน 4 กล่อง (Services Cards)       -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-services">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-grid-fill text-danger me-2"></i>บล็อกที่ 4: กล่องบริการและขั้นตอน 4 รายการ (Services & Steps Cards)
              </h5>
              <small class="text-secondary">ปรับแต่งหัวข้อ คำบรรยาย ไอคอน ลิงก์ และสามารถอัปโหลดรูปภาพประจำแต่ละบล็อกได้</small>
            </div>
            <span class="badge bg-primary text-white px-3 py-1">4 บล็อกหลัก</span>
          </div>
          <div class="cms-block-body">
            <!-- Section Header Titles -->
            <div class="p-3 bg-light rounded-3 border mb-4">
              <div class="fw-bold text-dark small mb-2"><i class="bi bi-type-h1 text-danger me-1"></i> หัวข้อส่วนบริการ (Section Header):</div>
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label small text-muted mb-1">หัวข้อย่อยด้านบน (Eyebrow):</label>
                  <input 
                    type="text" 
                    name="services_eyebrow" 
                    class="form-control form-control-sm" 
                    value="<?= htmlspecialchars($hp_settings['services_eyebrow'] ?? '') ?>"
                    placeholder="Online Admission Services"
                  >
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted mb-1">หัวข้อหลักของส่วน (Title):</label>
                  <input 
                    type="text" 
                    name="services_title" 
                    class="form-control form-control-sm" 
                    value="<?= htmlspecialchars($hp_settings['services_title'] ?? '') ?>"
                    placeholder="บริการและขั้นตอนสำหรับผู้สมัคร"
                  >
                </div>
                <div class="col-md-4">
                  <label class="form-label small text-muted mb-1">คำบรรยายสรุป (Description):</label>
                  <input 
                    type="text" 
                    name="services_desc" 
                    class="form-control form-control-sm" 
                    value="<?= htmlspecialchars($hp_settings['services_desc'] ?? '') ?>"
                    placeholder="เข้าถึงทุกข้อมูลที่จำเป็นสำหรับการสมัครเรียนได้อย่างรวดเร็ว ถูกต้องตามระเบียบการ"
                  >
                </div>
              </div>
            </div>

            <!-- The 4 Cards in 2x2 Grid -->
            <div class="row g-4">
              <?php 
                $card_default_names = [
                  1 => 'บล็อก 1: สมัครเรียนออนไลน์ (Featured Card)',
                  2 => 'บล็อก 2: ประกาศรับสมัคร',
                  3 => 'บล็อก 3: เอกสารการสมัคร',
                  4 => 'บล็อก 4: ตรวจสอบสถานะ',
                ];
                for ($i = 1; $i <= 4; $i++): 
                  $c_title    = $hp_settings["card{$i}_title"] ?? '';
                  $c_desc     = $hp_settings["card{$i}_desc"] ?? '';
                  $c_icon     = $hp_settings["card{$i}_icon"] ?? 'bi-app';
                  $c_img      = $hp_settings["card{$i}_image"] ?? '';
                  $c_btn      = $hp_settings["card{$i}_btn_text"] ?? '';
                  $c_link     = $hp_settings["card{$i}_link"] ?? '';
                  $c_featured = !empty($hp_settings["card{$i}_featured"]) && $hp_settings["card{$i}_featured"] == '1';
              ?>
                <div class="col-lg-6">
                  <div class="card h-100 border shadow-sm rounded-4 overflow-hidden <?= $c_featured ? 'border-danger' : '' ?>">
                    <div class="card-header py-3 px-3 d-flex justify-content-between align-items-center <?= $c_featured ? 'bg-danger text-white' : 'bg-light text-dark' ?>">
                      <strong class="small"><?= htmlspecialchars($card_default_names[$i]) ?></strong>
                      <div class="form-check form-switch mb-0">
                        <input 
                          class="form-check-input" 
                          type="checkbox" 
                          name="card<?= $i ?>_featured" 
                          value="1" 
                          id="card<?= $i ?>_featured" 
                          <?= $c_featured ? 'checked' : '' ?>
                        >
                        <label class="form-check-label small <?= $c_featured ? 'text-white' : 'text-secondary' ?>" for="card<?= $i ?>_featured">
                          เน้นแถบแดง
                        </label>
                      </div>
                    </div>
                    <div class="card-body p-3">
                      <div class="row g-2 mb-2">
                        <div class="col-sm-8">
                          <label class="form-label small fw-semibold text-dark mb-1">หัวข้อบล็อก:</label>
                          <input 
                            type="text" 
                            name="card<?= $i ?>_title" 
                            class="form-control form-control-sm" 
                            value="<?= htmlspecialchars($c_title) ?>"
                            placeholder="หัวข้อบล็อก"
                          >
                        </div>
                        <div class="col-sm-4">
                          <label class="form-label small fw-semibold text-dark mb-1">ไอคอน (Icon):</label>
                          <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi <?= htmlspecialchars($c_icon) ?>"></i></span>
                            <input 
                              type="text" 
                              name="card<?= $i ?>_icon" 
                              class="form-control font-monospace" 
                              value="<?= htmlspecialchars($c_icon) ?>"
                              placeholder="bi-pencil-square"
                            >
                          </div>
                        </div>
                      </div>

                      <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">รายละเอียดเนื้อหาบล็อก:</label>
                        <textarea 
                          name="card<?= $i ?>_desc" 
                          class="form-control form-control-sm" 
                          rows="2"
                          placeholder="รายละเอียดของบริการหรือขั้นตอนนี้..."
                        ><?= htmlspecialchars($c_desc) ?></textarea>
                      </div>

                      <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                          <label class="form-label small fw-semibold text-dark mb-1">ข้อความปุ่ม/ลิงก์:</label>
                          <input 
                            type="text" 
                            name="card<?= $i ?>_btn_text" 
                            class="form-control form-control-sm" 
                            value="<?= htmlspecialchars($c_btn) ?>"
                            placeholder="เช่น เริ่มกรอกใบสมัคร"
                          >
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label small fw-semibold text-dark mb-1">ลิงก์ URL ปลายทาง:</label>
                          <input 
                            type="text" 
                            name="card<?= $i ?>_link" 
                            class="form-control form-control-sm text-secondary" 
                            value="<?= htmlspecialchars($c_link) ?>"
                            placeholder="เช่น /register หรือ #steps"
                          >
                        </div>
                      </div>

                      <!-- Image Upload for this Card -->
                      <div class="p-2 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <label class="form-label small fw-bold text-dark mb-0">
                            <i class="bi bi-image text-danger me-1"></i> รูปภาพประจำบล็อก (Card Image):
                          </label>
                          <?php if (!empty($c_img)): ?>
                            <span class="badge bg-success-subtle text-success small">มีการใช้รูปภาพ</span>
                          <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary small">ใช้ไอคอนแสดงผล</span>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($c_img)): ?>
                          <div class="d-flex align-items-center gap-3 my-2 p-2 bg-white rounded border">
                            <div style="width: 80px; height: 50px; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 6px; background: #F1F5F9; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                              <img 
                                src="<?= htmlspecialchars($c_img) ?>" 
                                id="preview_card<?= $i ?>_image" 
                                alt="Card Image" 
                                style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;"
                              >
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                              <small class="text-muted d-block text-truncate font-monospace small"><?= htmlspecialchars($c_img) ?></small>
                              <div class="form-check form-check-inline mt-1 mb-0">
                                <input class="form-check-input" type="checkbox" name="remove_card<?= $i ?>_image" value="1" id="remove_card<?= $i ?>_image">
                                <label class="form-check-label text-danger small fw-semibold" for="remove_card<?= $i ?>_image">
                                  ลบรูปนี้ (กลับไปใช้ไอคอน)
                                </label>
                              </div>
                            </div>
                          </div>
                        <?php else: ?>
                          <div id="preview_card<?= $i ?>_container" class="d-none my-2 p-2 bg-white rounded border text-center">
                            <div style="max-width: 200px; aspect-ratio: 16 / 9; margin: 0 auto; overflow: hidden; border-radius: 6px; background: #F1F5F9; display: flex; align-items: center; justify-content: center;">
                              <img id="preview_card<?= $i ?>_image" src="" alt="New Card Preview" style="width: 100%; height: 100%; object-fit: cover; object-position: center; display: block;">
                            </div>
                            <small class="text-success d-block mt-1">พรีวิวรูปภาพใหม่ที่เลือก (ปรับพอดีกรอบ 16:9 อัตโนมัติ)</small>
                          </div>
                        <?php endif; ?>

                        <input 
                          type="file" 
                          name="card<?= $i ?>_image" 
                          class="form-control form-control-sm mt-1" 
                          accept="image/jpeg, image/png, image/webp"
                          onchange="previewImage(this, 'preview_card<?= $i ?>_image', 'preview_card<?= $i ?>_container')"
                        >
                        <div class="form-text small" style="font-size: 0.75rem;">
                          หากอัปโหลดรูปภาพ ระบบจะแสดงรูปภาพแทนไอคอน (แนะนำสัดส่วนแนวนอน)
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- บล็อกที่ 5: ประกาศกำหนดการสำคัญ (Notice Box)                       -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-notice">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-bell-fill text-danger me-2"></i>บล็อกที่ 5: แถบประกาศกำหนดการสำคัญ (Notice Schedule Box)
              </h5>
              <small class="text-secondary">กล่องแจ้งเตือนกำหนดการยื่นใบสมัครที่อยู่ใต้กล่องบริการ 4 รายการ</small>
            </div>
            <span class="badge bg-warning text-dark px-3 py-1">กำหนดการ</span>
          </div>
          <div class="cms-block-body">
            <div class="row g-3">
              <div class="col-md-2">
                <label class="form-label fw-semibold text-dark small">ป้ายกำกับ (Badge):</label>
                <input 
                  type="text" 
                  name="notice_badge" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['notice_badge'] ?? '') ?>"
                  placeholder="เช่น สำคัญ"
                >
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold text-dark small">หัวข้อกำหนดการ:</label>
                <input 
                  type="text" 
                  name="notice_title" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['notice_title'] ?? '') ?>"
                  placeholder="เช่น กำหนดการยื่นใบสมัครรอบแรก"
                >
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold text-dark small">ปุ่มศูนย์ช่วยเหลือ (ข้อความ + ลิงก์):</label>
                <div class="input-group">
                  <input 
                    type="text" 
                    name="notice_btn_text" 
                    class="form-control" 
                    value="<?= htmlspecialchars($hp_settings['notice_btn_text'] ?? '') ?>"
                    placeholder="ศูนย์ช่วยเหลือผู้สมัคร"
                  >
                  <input 
                    type="text" 
                    name="notice_btn_link" 
                    class="form-control text-secondary" 
                    value="<?= htmlspecialchars($hp_settings['notice_btn_link'] ?? '') ?>"
                    placeholder="#contact"
                  >
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold text-dark small">รายละเอียดข้อความกำหนดการ:</label>
                <textarea 
                  name="notice_desc" 
                  class="form-control" 
                  rows="2"
                  placeholder="เปิดรับสมัครตั้งแต่วันนี้ ถึง 28 กุมภาพันธ์ 2570 กรุณาตรวจสอบความถูกต้องของข้อมูลก่อนยืนยันการสมัคร"
                ><?= htmlspecialchars($hp_settings['notice_desc'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- ================================================================= -->
        <!-- บล็อกที่ 6: ข้อมูลติดต่อและส่วนท้าย (Footer & Contact)              -->
        <!-- ================================================================= -->
        <div class="cms-block-card" id="block-footer">
          <div class="cms-block-header">
            <div>
              <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-telephone-inbound-fill text-danger me-2"></i>บล็อกที่ 6: ข้อมูลติดต่อและส่วนท้าย (Footer & Contact)
              </h5>
              <small class="text-secondary">ข้อมูลชื่อสถานศึกษา ที่อยู่ เบอร์โทรติดต่อ อีเมล เวลาทำการ และข้อความลิขสิทธิ์</small>
            </div>
            <span class="badge bg-secondary text-white px-3 py-1">ส่วนท้ายเว็บ (Footer)</span>
          </div>
          <div class="cms-block-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">ชื่อสถานศึกษา:</label>
                <input 
                  type="text" 
                  name="footer_org_name" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_org_name'] ?? '') ?>"
                  placeholder="วิทยาลัยอาชีวศึกษามหาสารคาม"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">ชื่อภาษาอังกฤษ:</label>
                <input 
                  type="text" 
                  name="footer_org_en" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_org_en'] ?? '') ?>"
                  placeholder="Mahasarakham Vocational College"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">คำบรรยายระบบ:</label>
                <input 
                  type="text" 
                  name="footer_org_desc" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_org_desc'] ?? '') ?>"
                  placeholder="ระบบบริการรับสมัครนักเรียน นักศึกษาใหม่ออนไลน์ ประจำปีการศึกษา 2570"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-geo-alt text-danger me-1"></i> ที่อยู่สถานศึกษา:
                </label>
                <input 
                  type="text" 
                  name="footer_address" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_address'] ?? '') ?>"
                  placeholder="ถนนนครสวรรค์ ตำบลตลาด อำเภอเมือง จังหวัดมหาสารคาม 44000"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-telephone-fill text-danger me-1"></i> เบอร์โทรศัพท์งานทะเบียน:
                </label>
                <input 
                  type="text" 
                  name="footer_phone" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_phone'] ?? '') ?>"
                  placeholder="งานทะเบียน: 043-711-371"
                >
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-envelope-fill text-danger me-1"></i> อีเมลติดต่อ:
                </label>
                <input 
                  type="text" 
                  name="footer_email" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_email'] ?? '') ?>"
                  placeholder="admission@mvc.ac.th"
                >
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">
                  <i class="bi bi-clock-fill text-danger me-1"></i> เวลาทำการ:
                </label>
                <input 
                  type="text" 
                  name="footer_hours" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_hours'] ?? '') ?>"
                  placeholder="วันจันทร์ - ศุกร์: 08:30 - 16:30 น. (เว้นวันหยุดราชการ)"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">ข้อความสงวนลิขสิทธิ์:</label>
                <input 
                  type="text" 
                  name="footer_copyright" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_copyright'] ?? '') ?>"
                  placeholder="© 2570 วิทยาลัยอาชีวศึกษามหาสารคาม. สงวนลิขสิทธิ์ทุกประการ."
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">หน่วยงานผู้ออกแบบและพัฒนา:</label>
                <input 
                  type="text" 
                  name="footer_developer" 
                  class="form-control" 
                  value="<?= htmlspecialchars($hp_settings['footer_developer'] ?? '') ?>"
                  placeholder="งานศูนย์ดิจิทัลวิทยาลัยอาชีวศึกษามหาสารคาม"
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Sticky Bottom Save Bar -->
        <div class="cms-sticky-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger rounded-pill px-3 py-2 small">
              <i class="bi bi-info-circle-fill me-1"></i> พร้อมบันทึก
            </span>
            <span class="small text-secondary d-none d-md-inline">การแก้ไขทั้งหมดจะมีผลที่หน้าแรก (index.php) ทันทีหลังกดบันทึก</span>
          </div>
          <div class="d-flex gap-2">
            <a href="index.php" target="_blank" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold">
              <i class="bi bi-box-arrow-up-right me-1"></i> ดูหน้าแรก
            </a>
            <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2 rounded-3" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save2-fill fs-5"></i>
              <span>บันทึกการปรับแต่งหน้าแรกทั้งหมด</span>
            </button>
          </div>
        </div>

      </form>

    <?php endif; ?>

  </main>

  <!-- ===================================================================== -->
  <!-- MODALS SECTION                                                        -->
  <!-- ===================================================================== -->

  <!-- Modal 1: ดู/แก้ไข สถานะผู้สมัครเรียน (Applicant Modal) -->
  <div class="modal fade" id="applicantModal" tabindex="-1" aria-labelledby="applicantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="applicant_id" id="modal_applicant_id" value="">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <div>
              <h5 class="modal-title fw-bold" id="applicantModalLabel">
                <i class="bi bi-person-badge-fill me-2"></i> ข้อมูลผู้สมัครเรียน
              </h5>
              <small class="opacity-75" id="modal_app_no"></small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <div class="text-muted small">ชื่อ - นามสกุล:</div>
                <div class="fw-bold fs-5 text-dark" id="modal_name">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">เลขประจำตัวประชาชน 13 หลัก:</div>
                <div class="fw-bold fs-5 font-monospace text-danger" id="modal_citizen_id">-</div>
              </div>

              <div class="col-md-4">
                <div class="text-muted small">ระดับการศึกษา:</div>
                <div class="fw-bold text-dark" id="modal_level">-</div>
              </div>
              <div class="col-md-8">
                <div class="text-muted small">สาขาวิชาที่สมัคร:</div>
                <div class="fw-bold text-primary" id="modal_program">-</div>
              </div>

              <div class="col-md-4">
                <div class="text-muted small">เบอร์โทรศัพท์:</div>
                <div class="fw-semibold text-dark" id="modal_phone">-</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">อีเมล:</div>
                <div class="fw-semibold text-dark" id="modal_email">-</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">เกรดเฉลี่ย (GPA):</div>
                <div class="fw-bold text-success fs-5" id="modal_gpa">-</div>
              </div>

              <div class="col-md-6">
                <div class="text-muted small">โรงเรียนเดิม / สถานศึกษาเดิม:</div>
                <div class="fw-semibold text-dark" id="modal_school">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">รอบที่สมัคร:</div>
                <div class="fw-semibold text-dark" id="modal_round">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted small">ที่อยู่ตามทะเบียนบ้าน:</div>
                <div class="fw-semibold text-dark" id="modal_address">-</div>
              </div>

              <div class="col-md-6">
                <div class="text-muted small">ผู้ปกครอง / บิดา-มารดา:</div>
                <div class="fw-semibold text-dark" id="modal_parent">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">เอกสารหลักฐานที่แนบมา:</div>
                <div class="d-flex flex-wrap gap-2 mt-1" id="modal_docs">-</div>
              </div>
            </div>

            <hr class="my-3">

            <h6 class="fw-bold text-dark mb-3">
              <i class="bi bi-shield-check text-danger me-1"></i> ปรับปรุงสถานะการพิจารณาคัดเลือก
            </h6>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="modal_status_select" class="form-label fw-semibold">สถานะผลการพิจารณา <span class="text-danger">*</span></label>
                <select name="status" id="modal_status_select" class="form-select form-select-lg fs-6" required>
                  <option value="pending">รอการตรวจสอบเอกสาร (Pending)</option>
                  <option value="approved">ผ่านการคัดเลือก / รอรายงานตัว (Approved)</option>
                  <option value="paid">ขึ้นทะเบียนนักศึกษา / มอบตัวเรียบร้อย (Paid)</option>
                  <option value="document_incomplete">เอกสารไม่สมบูรณ์ / ให้แก้ไข (Incomplete)</option>
                  <option value="rejected">ไม่ผ่านการคัดเลือก (Rejected)</option>
                </select>
              </div>

              <div class="col-md-6">
                <label for="modal_status_note" class="form-label fw-semibold">ข้อความแจ้งเตือนถึงผู้สมัคร (แสดงในหน้าตรวจสอบสถานะ)</label>
                <textarea 
                  name="status_note" 
                  id="modal_status_note" 
                  rows="3" 
                  class="form-control" 
                  placeholder="เช่น กรุณาอัปโหลดสำเนา ปพ.1 ด้านหลังเพิ่มเติม หรือ รายงานตัววันที่ 15 มีนาคม 2570 เวลา 09.00 น."
                ></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light d-flex justify-content-between flex-wrap gap-2">
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteFromApplicantModal()">
                <i class="bi bi-trash me-1"></i> ลบใบสมัครนี้
              </button>
              <a href="#" id="modal_print_btn" target="_blank" class="btn btn-outline-dark fw-semibold">
                <i class="bi bi-printer-fill me-1"></i> พิมพ์ใบสมัครฉบับจริง
              </a>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-danger fw-semibold" style="background-color: var(--color-primary); border-color: var(--color-primary);">
                <i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 2: เพิ่มสาขาวิชาใหม่ (Add Program Modal) -->
  <div class="modal fade" id="addProgramModal" tabindex="-1" aria-labelledby="addProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="add_program">
          <input type="hidden" name="view" value="<?= htmlspecialchars($dept_view) ?>">
          <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="addProgramModalLabel">
              <i class="bi bi-plus-circle-fill me-2"></i> เปิดรับสมัครสาขาวิชาใหม่ (ปีการศึกษา <?= htmlspecialchars($selected_year) ?>)
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="alert alert-danger-subtle border-danger-subtle d-flex align-items-center justify-content-between p-2 px-3 rounded-3 mb-3">
              <div>
                <span class="text-secondary small fw-semibold d-block">ปีการศึกษาภาพรวม:</span>
                <strong class="text-danger fs-6">ปีการศึกษา <?= htmlspecialchars($selected_year) ?></strong>
              </div>
              <span class="badge bg-danger">
                <i class="bi bi-calendar-check me-1"></i> ภาพรวม
              </span>
            </div>

            <!-- แผนกวิชา -->
            <div class="mb-3">
              <label for="add_prog_dept_id" class="form-label fw-semibold">แผนกวิชา <span class="text-danger">*</span></label>
              <select name="dept_id" id="add_prog_dept_id" class="form-select" required>
                <?php if (empty($all_departments)): ?>
                  <option value="">-- ยังไม่มีแผนกวิชาในปีการศึกษานี้ กรุณาเพิ่มแผนกวิชาก่อน --</option>
                <?php else: ?>
                  <option value="">-- เลือกแผนกวิชา --</option>
                  <?php foreach ($all_departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_name']) ?> (<?= htmlspecialchars($d['dept_code']) ?>)</option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <!-- ระดับการศึกษา -->
            <div class="mb-3">
              <label for="add_prog_edu_level" class="form-label fw-semibold">ระดับชั้นที่เปิดรับสมัคร <span class="text-danger">*</span></label>
              <select name="education_level" id="add_prog_edu_level" class="form-select" required>
                <option value="ปวช." selected>ระดับ ปวช. (ประกาศนียบัตรวิชาชีพ)</option>
                <option value="ปวส.">ระดับ ปวส. (ประกาศนียบัตรวิชาชีพชั้นสูง)</option>
                <option value="ปริญญาตรี">ระดับ ปริญญาตรี (ทล.บ. / สายเทคโนโลยี)</option>
              </select>
            </div>

            <!-- รูปแบบหลักสูตร / ระบบการเรียน -->
            <div class="mb-3">
              <label for="add_prog_type" class="form-label fw-semibold">
                <i class="bi bi-briefcase me-1 text-danger"></i> รูปแบบหลักสูตร / ระบบการเรียน <span class="text-danger">*</span>
              </label>
              <select name="program_type" id="add_prog_type" class="form-select" required>
                <option value="ปกติ" selected>ระบบปกติ (Normal / Regular)</option>
                <option value="ทวิภาคี">ระบบทวิภาคี (Dual Vocational Education - ทวิ)</option>
                <option value="ปกติและทวิภาคี">เปิดทั้งสองระบบ (ปกติ และ ทวิภาคี - ให้ผู้สมัครเลือก)</option>
              </select>
              <div class="form-text small text-muted">กำหนดให้ผู้สมัครสามารถเลือกเรียนแบบระบบปกติ หรือระบบทวิภาคีตามที่เปิดรับ</div>
            </div>

            <!-- รหัสสาขา -->
            <div class="mb-3">
              <label for="add_prog_code" class="form-label fw-semibold">รหัสสาขาวิชา <span class="text-danger">*</span></label>
              <input 
                type="text" 
                class="form-control font-monospace" 
                id="add_prog_code" 
                name="program_code" 
                placeholder="เช่น IT-VC หรือ ACC-HVC" 
                required
              >
            </div>

            <!-- ชื่อสาขาวิชา -->
            <div class="mb-3">
              <label for="add_prog_name" class="form-label fw-semibold">ชื่อสาขาวิชา <span class="text-danger">*</span></label>
              <input 
                type="text" 
                class="form-control" 
                id="add_prog_name" 
                name="program_name" 
                placeholder="เช่น สาขาวิชาเทคโนโลยีสารสนเทศ" 
                required
              >
            </div>

            <div class="row g-3">
              <!-- จำนวนที่รับ -->
              <div class="col-md-6">
                <label for="add_prog_seats" class="form-label fw-semibold">จำนวนที่นั่งเปิดรับ (คน)</label>
                <input 
                  type="number" 
                  class="form-control" 
                  id="add_prog_seats" 
                  name="seats_available" 
                  value="40" 
                  min="1" 
                  max="500" 
                  required
                >
              </div>

              <!-- สถานะ -->
              <div class="col-md-6">
                <label for="add_prog_status" class="form-label fw-semibold">สถานะการรับสมัคร</label>
                <select name="status" id="add_prog_status" class="form-select">
                  <option value="open" selected>เปิดรับสมัคร (Open)</option>
                  <option value="closed">ปิดรับสมัคร (Closed)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save me-1"></i> บันทึกสาขาวิชา
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 3: แก้ไขสาขาวิชา (Edit Program Modal) -->
  <div class="modal fade" id="editProgramModal" tabindex="-1" aria-labelledby="editProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="edit_program">
          <input type="hidden" name="program_id" id="edit_prog_id" value="">
          <input type="hidden" name="view" value="<?= htmlspecialchars($dept_view) ?>">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="editProgramModalLabel">
              <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูลสาขาวิชา
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="edit_prog_dept_id" class="form-label fw-semibold">แผนกวิชา <span class="text-danger">*</span></label>
              <select name="dept_id" id="edit_prog_dept_id" class="form-select" required>
                <?php foreach ($all_departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_name']) ?> (<?= htmlspecialchars($d['dept_code']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="edit_prog_edu_level" class="form-label fw-semibold">ระดับชั้น <span class="text-danger">*</span></label>
              <select name="education_level" id="edit_prog_edu_level" class="form-select" required>
                <option value="ปวช.">ระดับ ปวช. (ประกาศนียบัตรวิชาชีพ)</option>
                <option value="ปวส.">ระดับ ปวส. (ประกาศนียบัตรวิชาชีพชั้นสูง)</option>
                <option value="ปริญญาตรี">ระดับ ปริญญาตรี (ทล.บ.)</option>
              </select>
            </div>

            <!-- รูปแบบหลักสูตร / ระบบการเรียน -->
            <div class="mb-3">
              <label for="edit_prog_type" class="form-label fw-semibold">
                <i class="bi bi-briefcase me-1 text-danger"></i> รูปแบบหลักสูตร / ระบบการเรียน <span class="text-danger">*</span>
              </label>
              <select name="program_type" id="edit_prog_type" class="form-select" required>
                <option value="ปกติ">ระบบปกติ (Normal / Regular)</option>
                <option value="ทวิภาคี">ระบบทวิภาคี (Dual Vocational Education - ทวิ)</option>
                <option value="ปกติและทวิภาคี">เปิดทั้งสองระบบ (ปกติ และ ทวิภาคี - ให้ผู้สมัครเลือก)</option>
              </select>
              <div class="form-text small text-muted">กำหนดให้ผู้สมัครสามารถเลือกเรียนแบบระบบปกติ หรือระบบทวิภาคีตามที่เปิดรับ</div>
            </div>

            <div class="mb-3">
              <label for="edit_prog_code" class="form-label fw-semibold">รหัสสาขาวิชา <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace" id="edit_prog_code" name="program_code" required>
            </div>

            <div class="mb-3">
              <label for="edit_prog_name" class="form-label fw-semibold">ชื่อสาขาวิชา <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_prog_name" name="program_name" required>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="edit_prog_seats" class="form-label fw-semibold">จำนวนที่รับ (คน)</label>
                <input type="number" class="form-control" id="edit_prog_seats" name="seats_available" min="1" max="500" required>
              </div>

              <div class="col-md-6">
                <label for="edit_prog_status" class="form-label fw-semibold">สถานะเปิด/ปิดรับสมัคร</label>
                <select name="status" id="edit_prog_status" class="form-select">
                  <option value="open">เปิดรับสมัคร (Open)</option>
                  <option value="closed">ปิดรับสมัคร (Closed)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 4: ยืนยันการลบสาขาวิชา (Delete Program Modal) -->
  <div class="modal fade" id="deleteProgramModal" tabindex="-1" aria-labelledby="deleteProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="delete_program">
          <input type="hidden" name="program_id" id="delete_prog_id" value="">
          <input type="hidden" name="view" value="<?= htmlspecialchars($dept_view) ?>">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold" id="deleteProgramModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบสาขาวิชา
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-danger">
              <i class="bi bi-trash-fill" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบสาขาวิชานี้?</h5>
            <div class="p-3 bg-light rounded-3 my-3 text-start">
              <div><strong>ชื่อสาขา:</strong> <span id="delete_prog_name" class="fw-bold text-dark">-</span></div>
              <div><strong>ระดับ:</strong> <span id="delete_prog_level" class="badge bg-secondary">-</span></div>
              <div><strong>แผนกวิชา:</strong> <span id="delete_prog_dept">-</span></div>
            </div>
            <p class="text-danger small mb-0">
              * หากมีผู้สมัครลงทะเบียนในสาขานี้แล้ว ระบบจะไม่อนุญาตให้ลบ เพื่อรักษาความปลอดภัยของประวัติผู้สมัคร
            </p>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-trash me-1"></i> ยืนยันลบสาขาวิชา
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 5: เพิ่มแผนกวิชาใหม่ (Add Department Modal) -->
  <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="add_department">
          <input type="hidden" name="view" value="<?= htmlspecialchars($dept_view) ?>">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="addDepartmentModalLabel">
              <i class="bi bi-building-add me-2"></i> เพิ่มแผนกวิชาใหม่ (ปีการศึกษา <?= htmlspecialchars($selected_year) ?>)
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <!-- ปีการศึกษาภาพรวม -->
            <div class="mb-3">
              <label for="add_dept_academic_year" class="form-label fw-semibold">
                <i class="bi bi-calendar-event me-1 text-danger"></i> ปีการศึกษาภาพรวม <span class="text-danger">*</span>
              </label>
              <select name="academic_year" id="add_dept_academic_year" class="form-select fw-bold border-2" required>
                <?php foreach ($available_academic_years as $yr): ?>
                  <option value="<?= htmlspecialchars($yr['year_name']) ?>" <?= $yr['year_name'] === $selected_year ? 'selected' : '' ?>>
                    ปีการศึกษา <?= htmlspecialchars($yr['year_name']) ?> <?= !empty($yr['is_active']) ? '(ปีเปิดรับสมัครหลักปัจจุบัน)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small text-muted">
                <i class="bi bi-info-circle me-1"></i> แผนกวิชาที่สร้างจะผูกกับปีการศึกษาที่เลือกนี้โดยอัตโนมัติ
              </div>
            </div>

            <div class="mb-3">
              <label for="add_dept_code" class="form-label fw-semibold">รหัสแผนกวิชา (Dept Code) <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace text-uppercase" id="add_dept_code" name="dept_code" placeholder="เช่น ART, MKT, ENGR" required>
            </div>
            <div class="mb-3">
              <label for="add_dept_name" class="form-label fw-semibold">ชื่อแผนกวิชา <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="add_dept_name" name="dept_name" placeholder="เช่น แผนกวิชาศิลปกรรมและการออกแบบ" required>
            </div>
            <div class="mb-2">
              <label for="add_dept_desc" class="form-label fw-semibold">คำอธิบายเพิ่มเติม</label>
              <textarea class="form-control" id="add_dept_desc" name="description" rows="3" placeholder="รายละเอียดหรือข้อมูลสังกัดแผนก"></textarea>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save me-1"></i> บันทึกแผนกวิชา
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 5.1: เพิ่มปีการศึกษาใหม่ (Add Academic Year Modal) -->
  <div class="modal fade" id="addAcademicYearModal" tabindex="-1" aria-labelledby="addAcademicYearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="add_academic_year">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="addAcademicYearModalLabel">
              <i class="bi bi-calendar-plus me-2"></i> เพิ่มปีการศึกษาใหม่
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 d-flex align-items-center mb-3 py-2 px-3">
              <i class="bi bi-info-circle-fill text-primary fs-5 me-2 flex-shrink-0"></i>
              <div class="small">
                ปีการศึกษาทำหน้าที่เป็น <strong>ภาพรวมหลัก (Master Overview)</strong> ของแผนกวิชา สาขาวิชา และสถิติการรับสมัครทั้งหมด
              </div>
            </div>

            <div class="mb-3">
              <label for="modal_year_name" class="form-label fw-semibold">
                ระบุปีการศึกษา (พ.ศ.) <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-calendar4-week text-danger"></i>
                </span>
                <input 
                  type="text" 
                  class="form-control border-start-0 ps-1 font-monospace fw-bold" 
                  id="modal_year_name" 
                  name="year_name" 
                  placeholder="เช่น 2571, 2572" 
                  pattern="[0-9]{4}" 
                  maxlength="4" 
                  required
                >
              </div>
              <div class="form-text small">ตัวเลข 4 หลัก เช่น 2571</div>
            </div>

            <div class="mb-3">
              <label for="modal_year_desc" class="form-label fw-semibold">คำอธิบายหรือหมายเหตุ</label>
              <input 
                type="text" 
                class="form-control" 
                id="modal_year_desc" 
                name="description" 
                placeholder="เช่น การรับสมัครนักศึกษาใหม่ ประจำปีการศึกษา 2571"
              >
            </div>

            <div class="form-check form-switch p-3 bg-light rounded-3">
              <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="modal_year_active" name="is_active" value="1">
              <label class="form-check-label fw-semibold" for="modal_year_active">
                ตั้งเป็นปีการศึกษาเปิดรับสมัครหลักของระบบทันที
              </label>
              <div class="text-muted small ps-4">หากเลือก ผู้สมัครทางหน้าบ้านจะเห็นและสมัครในรอบปีการศึกษานี้</div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-check-circle me-1"></i> บันทึกและเปิดใช้งาน
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 5.2: คัดลอกโครงสร้างแผนกและสาขาวิชาจากปีอื่น (Clone Year Modal) -->
  <div class="modal fade" id="cloneYearModal" tabindex="-1" aria-labelledby="cloneYearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="clone_departments_from_year">
          <input type="hidden" name="target_year" value="<?= htmlspecialchars($selected_year) ?>">

          <div class="modal-header text-white" style="background-color: var(--color-secondary);">
            <h5 class="modal-title fw-bold" id="cloneYearModalLabel">
              <i class="bi bi-copy me-2"></i> คัดลอกโครงสร้างแผนกและสาขาวิชา
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="alert alert-info border-0 rounded-3 small mb-3">
              <i class="bi bi-info-circle-fill me-1"></i>
              ระบบจะคัดลอกรายชื่อแผนกวิชาและสาขาวิชาทั้งหมดจาก <strong>ปีต้นทาง</strong> มายัง <strong>ปีการศึกษา <?= htmlspecialchars($selected_year) ?></strong> โดยอัตโนมัติ (ไม่คัดลอกรายชื่อผู้สมัคร)
            </div>

            <div class="mb-3">
              <label for="clone_source_year" class="form-label fw-semibold">เลือกปีการศึกษาต้นทาง <span class="text-danger">*</span></label>
              <select name="source_year" id="clone_source_year" class="form-select fw-semibold" required>
                <option value="">-- เลือกปีการศึกษาต้นทางที่ต้องการคัดลอก --</option>
                <?php foreach ($available_academic_years as $yr): ?>
                  <?php if ($yr['year_name'] !== $selected_year): ?>
                    <option value="<?= htmlspecialchars($yr['year_name']) ?>">
                      ปีการศึกษา <?= htmlspecialchars($yr['year_name']) ?> <?= !empty($yr['is_active']) ? '(ปีรับสมัครหลัก)' : '' ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="p-3 bg-light rounded-3 mb-2">
              <span class="text-secondary small fw-semibold d-block">ปีการศึกษาปลายทาง (ที่จะได้รับข้อมูล):</span>
              <strong class="text-danger fs-5">ปีการศึกษา <?= htmlspecialchars($selected_year) ?></strong>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary fw-semibold px-4" style="background-color: var(--color-secondary); border-color: var(--color-secondary);">
              <i class="bi bi-arrow-repeat me-1"></i> ยืนยันการคัดลอกโครงสร้าง
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 6: แก้ไขแผนกวิชา (Edit Department Modal) -->
  <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="edit_department">
          <input type="hidden" name="dept_id" id="edit_dept_id" value="">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="editDepartmentModalLabel">
              <i class="bi bi-pencil-square me-2"></i> แก้ไขแผนกวิชา
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="edit_dept_code" class="form-label fw-semibold">รหัสแผนกวิชา <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace" id="edit_dept_code" name="dept_code" required>
            </div>
            <div class="mb-3">
              <label for="edit_dept_name" class="form-label fw-semibold">ชื่อแผนกวิชา <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_dept_name" name="dept_name" required>
            </div>
            <div class="mb-2">
              <label for="edit_dept_desc" class="form-label fw-semibold">คำอธิบาย</label>
              <textarea class="form-control" id="edit_dept_desc" name="description" rows="3"></textarea>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 6.1: ยืนยันการลบแผนกวิชา (Delete Department Modal) -->
  <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="delete_department">
          <input type="hidden" name="dept_id" id="delete_dept_id" value="">
          <input type="hidden" name="view" value="<?= htmlspecialchars($dept_view) ?>">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold" id="deleteDepartmentModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบแผนกวิชา
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-danger">
              <i class="bi bi-trash-fill" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบแผนกวิชานี้?</h5>
            <div class="p-3 bg-light rounded-3 my-3 text-start">
              <div><strong>รหัสแผนก:</strong> <span id="delete_dept_code" class="badge bg-secondary font-monospace">-</span></div>
              <div class="mt-1"><strong>ชื่อแผนกวิชา:</strong> <span id="delete_dept_name" class="fw-bold text-dark">-</span></div>
            </div>
            <div class="alert alert-warning text-start small mb-0 py-2">
              <i class="bi bi-info-circle-fill me-1"></i>
              <strong>คำเตือน:</strong> หากลบแผนกวิชานี้ สาขาวิชาทั้งหมดที่สังกัดแผนกนี้จะถูกลบออกไปด้วย (แต่หากมีผู้สมัครเรียนในสาขาวิชาใดๆ ของแผนกนี้ ระบบจะไม่อนุญาตให้ลบ)
            </div>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-trash me-1"></i> ยืนยันลบแผนกวิชา
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 7: เพิ่มผู้ดูแลระบบใหม่ (Add Admin Modal) -->
  <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST" onsubmit="return validateAddAdminForm()">
          <input type="hidden" name="action" value="add_admin">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <h5 class="modal-title fw-bold" id="addAdminModalLabel">
              <i class="bi bi-person-plus-fill me-2"></i> เพิ่มผู้ดูแลระบบใหม่
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="add_username" class="form-label fw-semibold">
                ชื่อผู้ใช้ (Username) สำหรับเข้าสู่ระบบ <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-person-badge text-secondary"></i>
                </span>
                <input 
                  type="text" 
                  class="form-control border-start-0 ps-1" 
                  id="add_username" 
                  name="username" 
                  placeholder="เช่น officer_somchai" 
                  pattern="[a-zA-Z0-9_.-]{3,30}"
                  title="ความยาว 3-30 ตัวอักษร"
                  required
                  autocomplete="off"
                >
              </div>
            </div>

            <div class="mb-3">
              <label for="add_fullname" class="form-label fw-semibold">
                ชื่อ - นามสกุล เจ้าหน้าที่ <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-card-text text-secondary"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-1" id="add_fullname" name="full_name" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="add_email" class="form-label fw-semibold">อีเมลติดต่อ (Email)</label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-envelope text-secondary"></i>
                </span>
                <input type="email" class="form-control border-start-0 ps-1" id="add_email" name="email">
              </div>
            </div>

            <div class="mb-3">
              <label for="add_role" class="form-label fw-semibold">ระดับสิทธิ์ (Role) <span class="text-danger">*</span></label>
              <select name="role" id="add_role" class="form-select" required>
                <option value="officer" selected>เจ้าหน้าที่งานทะเบียน (Officer)</option>
                <option value="superadmin">ผู้ดูแลระบบสูงสุด (Superadmin)</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="add_password" class="form-label fw-semibold">
                รหัสผ่านเริ่มต้น <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-key text-secondary"></i>
                </span>
                <input type="password" class="form-control border-start-0 border-end-0 ps-1" id="add_password" name="password" minlength="6" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('add_password', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>

            <div class="mb-2">
              <label for="add_confirm_password" class="form-label fw-semibold">
                ยืนยันรหัสผ่าน <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-shield-lock text-secondary"></i>
                </span>
                <input type="password" class="form-control border-start-0 border-end-0 ps-1" id="add_confirm_password" name="confirm_password" minlength="6" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('add_confirm_password', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-check-circle me-1"></i> บันทึกผู้ดูแลระบบ
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 8: แก้ไขผู้ดูแลระบบ (Edit Admin Modal) -->
  <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="edit_admin">
          <input type="hidden" name="admin_id" id="edit_admin_id" value="">

          <div class="modal-header text-white" style="background-color: var(--color-primary-deep);">
            <div>
              <h5 class="modal-title fw-bold" id="editAdminModalLabel">
                <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูลผู้ดูแลระบบ
              </h5>
              <small class="opacity-75" id="edit_modal_subtitle"></small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label for="edit_username" class="form-label fw-semibold text-secondary">ชื่อผู้ใช้ (Username)</label>
              <input type="text" class="form-control bg-light font-monospace fw-bold" id="edit_username" readonly tabindex="-1">
            </div>

            <div class="mb-3">
              <label for="edit_fullname" class="form-label fw-semibold">ชื่อ - นามสกุล เจ้าหน้าที่ <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_fullname" name="full_name" required>
            </div>

            <div class="mb-3">
              <label for="edit_email" class="form-label fw-semibold">อีเมลติดต่อ (Email)</label>
              <input type="email" class="form-control" id="edit_email" name="email">
            </div>

            <div class="mb-3">
              <label for="edit_role" class="form-label fw-semibold">ระดับสิทธิ์ (Role) <span class="text-danger">*</span></label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="officer">เจ้าหน้าที่งานทะเบียน (Officer)</option>
                <option value="superadmin">ผู้ดูแลระบบสูงสุด (Superadmin)</option>
              </select>
            </div>

            <div class="mb-2">
              <label for="edit_password" class="form-label fw-semibold">
                เปลี่ยนรหัสผ่านใหม่ <span class="text-muted small fw-normal">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span>
              </label>
              <div class="input-group">
                <input type="password" class="form-control" id="edit_password" name="password" placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)" minlength="6">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('edit_password', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger fw-semibold px-4" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 9: ลบผู้ดูแลระบบ (Delete Admin Modal) -->
  <div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="delete_admin">
          <input type="hidden" name="admin_id" id="delete_admin_id" value="">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold" id="deleteAdminModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบผู้ดูแลระบบ
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-danger">
              <i class="bi bi-trash-fill" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบบัญชีนี้?</h5>
            <div class="p-3 bg-light rounded-3 my-3 text-start">
              <div><strong>ชื่อ-สกุล:</strong> <span id="delete_fullname">-</span></div>
              <div><strong>ชื่อผู้ใช้:</strong> <span class="font-monospace text-danger" id="delete_username">-</span></div>
              <div><strong>บทบาท:</strong> <span id="delete_role">-</span></div>
            </div>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-trash me-1"></i> ยืนยันลบบัญชี
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 10: ยืนยันการลบข้อมูลผู้สมัคร (Delete Applicant Modal) -->
  <div class="modal fade" id="deleteApplicantModal" tabindex="-1" aria-labelledby="deleteApplicantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="delete_applicant">
          <input type="hidden" name="applicant_id" id="delete_applicant_id" value="">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold" id="deleteApplicantModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบข้อมูลผู้สมัคร
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-danger">
              <i class="bi bi-person-x-fill" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลผู้สมัครรายนี้?</h5>
            <div class="p-3 bg-light rounded-3 my-3 text-start">
              <div><strong>เลขที่ใบสมัคร:</strong> <span id="delete_app_no" class="fw-bold text-primary font-monospace">-</span></div>
              <div class="mt-1"><strong>ชื่อ-สกุล:</strong> <span id="delete_app_name" class="fw-bold text-dark">-</span></div>
              <div class="mt-1"><strong>เลขบัตรประชาชน:</strong> <span id="delete_app_citizen" class="font-monospace text-secondary">-</span></div>
              <div class="mt-1"><strong>สาขาวิชา:</strong> <span id="delete_app_prog" class="text-dark">-</span></div>
            </div>
            <p class="text-danger small mb-0">
              * การกระทำนี้ไม่สามารถย้อนกลับได้ ข้อมูลการสมัครและเอกสารแนบทั้งหมดของผู้สมัครรายนี้จะถูกลบออกจากระบบอย่างถาวร
            </p>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-trash me-1"></i> ยืนยันลบผู้สมัคร
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 11: ยืนยันการคืนค่าเริ่มต้นหน้าแรก (Reset Homepage Modal) -->
  <div class="modal fade" id="resetHomepageModal" tabindex="-1" aria-labelledby="resetHomepageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="reset_homepage_settings">

          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title fw-bold" id="resetHomepageModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> ยืนยันการคืนค่าเริ่มต้นหน้าแรก
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-warning">
              <i class="bi bi-arrow-counterclockwise" style="font-size: 3.5rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการคืนค่าเริ่มต้นทั้งหมดของหน้าแรก?</h5>
            <p class="text-secondary small mb-3">
              ระบบจะรีเซ็ตข้อความหัวเรื่อง คำบรรยาย ลิงก์ และกลับไปใช้รูปภาพแบนเนอร์และโลโก้มาตรฐานเดิมของวิทยาลัย
            </p>
            <div class="p-3 bg-light rounded-3 text-start small border">
              <ul class="mb-0 text-muted ps-3">
                <li>ข้อความ Topbar, Hero, Services 4 บล็อก, Notice และ Footer จะกลับเป็นค่าเริ่มต้น</li>
                <li>รูปภาพ Hero จะกลับไปเป็น <code>assets/images/hero.jpg</code></li>
                <li>ตราสัญลักษณ์จะกลับไปเป็น <code>img/Logo-W.png</code></li>
              </ul>
            </div>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-arrow-counterclockwise me-1"></i> ยืนยันคืนค่าเริ่มต้น
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 12: เพิ่มรอบโควตา (Add Quota Round Modal) -->
  <div class="modal fade" id="addQuotaRoundModal" tabindex="-1" aria-labelledby="addQuotaRoundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="add_admission_round">
          <input type="hidden" name="round_type" value="quota">
          <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">

          <div class="modal-header bg-warning text-dark">
            <div>
              <h5 class="modal-title fw-bold" id="addQuotaRoundModalLabel">
                <i class="bi bi-award-fill text-danger me-2"></i> เพิ่มรอบโควตาใหม่ (Add Quota Round)
              </h5>
              <small class="text-dark opacity-75">ปีการศึกษา <?= htmlspecialchars($selected_year) ?></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold text-dark small">ชื่อรอบโควตา: <span class="text-danger">*</span></label>
                <input 
                  type="text" 
                  name="round_name" 
                  class="form-control" 
                  placeholder="เช่น รอบโควตากรณีพิเศษ, โควตาเรียนดี, โควตาสถานศึกษาเดิม" 
                  required
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">รหัสรอบ (Code):</label>
                <input 
                  type="text" 
                  name="round_code" 
                  class="form-control font-monospace" 
                  placeholder="QUOTA-<?= htmlspecialchars($selected_year) ?>-01" 
                  value="QUOTA-<?= htmlspecialchars($selected_year) ?>-<?= date('md') ?>"
                >
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold text-dark small d-block mb-1">ระดับการศึกษาที่เปิดรับในรอบโควตานี้:</label>
                <div class="d-flex flex-wrap gap-3 p-2 bg-light rounded-3 border">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปวช." id="quota_lvl_pvc" checked>
                    <label class="form-check-label small fw-semibold" for="quota_lvl_pvc">ระดับ ปวช.</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปวส." id="quota_lvl_pvs" checked>
                    <label class="form-check-label small fw-semibold" for="quota_lvl_pvs">ระดับ ปวส.</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปริญญาตรี" id="quota_lvl_deg" checked>
                    <label class="form-check-label small fw-semibold" for="quota_lvl_deg">ระดับ ปริญญาตรี</label>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">จำนวนที่นั่งโควตารวม (ที่นั่ง):</label>
                <input 
                  type="number" 
                  name="quota_seats" 
                  class="form-control" 
                  min="0" 
                  value="200" 
                  placeholder="0 = ตามแผนรับ"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">เกรดเฉลี่ยขั้นต่ำ (GPAX):</label>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0" 
                  max="4.00" 
                  name="min_gpa" 
                  class="form-control" 
                  value="2.00" 
                  placeholder="2.00"
                >
                <div class="form-text small" style="font-size: 0.75rem;">ระบุ 0.00 หากไม่กำหนดเกรด</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">สถานะการเปิดรับสมัคร:</label>
                <select name="status" class="form-select">
                  <option value="open" selected>เปิดรับสมัครทันที (Open)</option>
                  <option value="upcoming">เร็วๆ นี้ (Upcoming)</option>
                  <option value="closed">ปิดรับสมัคร (Closed)</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่เริ่มต้นรับสมัคร:</label>
                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่สิ้นสุดรับสมัคร:</label>
                <input type="date" name="end_date" class="form-control" value="<?= date('Y-12-31') ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันประกาศผล / รายชื่อผู้มีสิทธิ์:</label>
                <input type="date" name="announcement_date" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันรายงานตัวและมอบตัว:</label>
                <input type="date" name="registration_date" class="form-control">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold text-dark small">เงื่อนไข / คุณสมบัติเฉพาะรอบโควตานี้:</label>
                <textarea 
                  name="description" 
                  class="form-control" 
                  rows="2" 
                  placeholder="เช่น นักเรียนที่กำลังศึกษาชั้น ม.3 หรือ ปวช.3 ที่มีเกรดเฉลี่ย 2.00 ขึ้นไป หรือมีความสามารถพิเศษด้านกีฬา ดนตรี ศิลปะ"
                ></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-warning px-4 fw-semibold text-dark shadow-sm">
              <i class="bi bi-award-fill text-danger me-1"></i> บันทึกเพิ่มรอบโควตา
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 13: เพิ่มรอบปกติ / ทั่วไป (Add Normal Round Modal) -->
  <div class="modal fade" id="addNormalRoundModal" tabindex="-1" aria-labelledby="addNormalRoundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="add_admission_round">
          <input type="hidden" name="round_type" value="normal">
          <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">

          <div class="modal-header bg-primary text-white">
            <div>
              <h5 class="modal-title fw-bold" id="addNormalRoundModalLabel">
                <i class="bi bi-people-fill me-2"></i> เพิ่มรอบปกติ / ทั่วไป (Add Normal Round)
              </h5>
              <small class="opacity-75">ปีการศึกษา <?= htmlspecialchars($selected_year) ?></small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold text-dark small">ชื่อรอบการรับสมัคร: <span class="text-danger">*</span></label>
                <input 
                  type="text" 
                  name="round_name" 
                  class="form-control" 
                  placeholder="เช่น รอบรับสมัครทั่วไป (รอบปกติ), รอบคัดเลือกทั่วไป รอบที่ 2" 
                  required
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">รหัสรอบ (Code):</label>
                <input 
                  type="text" 
                  name="round_code" 
                  class="form-control font-monospace" 
                  placeholder="NORMAL-<?= htmlspecialchars($selected_year) ?>-01" 
                  value="NORMAL-<?= htmlspecialchars($selected_year) ?>-<?= date('md') ?>"
                >
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold text-dark small d-block mb-1">ระดับการศึกษาที่เปิดรับ:</label>
                <div class="d-flex flex-wrap gap-3 p-2 bg-light rounded-3 border">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปวช." id="normal_lvl_pvc" checked>
                    <label class="form-check-label small fw-semibold" for="normal_lvl_pvc">ระดับ ปวช.</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปวส." id="normal_lvl_pvs" checked>
                    <label class="form-check-label small fw-semibold" for="normal_lvl_pvs">ระดับ ปวส.</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="education_levels[]" value="ระดับ ปริญญาตรี" id="normal_lvl_deg" checked>
                    <label class="form-check-label small fw-semibold" for="normal_lvl_deg">ระดับ ปริญญาตรี</label>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">จำนวนที่นั่งรับรวม (ที่นั่ง):</label>
                <input 
                  type="number" 
                  name="quota_seats" 
                  class="form-control" 
                  min="0" 
                  value="400" 
                  placeholder="0 = ตามแผนรับ"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">เกรดเฉลี่ยขั้นต่ำ (GPAX):</label>
                <input 
                  type="number" 
                  step="0.01" 
                  min="0" 
                  max="4.00" 
                  name="min_gpa" 
                  class="form-control" 
                  value="0.00" 
                  placeholder="0.00"
                >
                <div class="form-text small" style="font-size: 0.75rem;">ระบุ 0.00 หากไม่จำกัดเกรด</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">สถานะการเปิดรับสมัคร:</label>
                <select name="status" class="form-select">
                  <option value="open" selected>เปิดรับสมัครทันที (Open)</option>
                  <option value="upcoming">เร็วๆ นี้ (Upcoming)</option>
                  <option value="closed">ปิดรับสมัคร (Closed)</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่เริ่มต้นรับสมัคร:</label>
                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่สิ้นสุดรับสมัคร:</label>
                <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+60 days')) ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันประกาศผล / รายชื่อผู้ผ่านการคัดเลือก:</label>
                <input type="date" name="announcement_date" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันรายงานตัวและมอบตัว:</label>
                <input type="date" name="registration_date" class="form-control">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold text-dark small">รายละเอียดและคุณสมบัติผู้สมัคร:</label>
                <textarea 
                  name="description" 
                  class="form-control" 
                  rows="2" 
                  placeholder="เช่น เปิดรับสมัครนักเรียน นักศึกษาใหม่ทั่วไป วุฒิ ม.3, ปวช., ม.6 หรือเทียบเท่า เข้าศึกษาต่อทุกแผนกวิชา"
                ></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
              <i class="bi bi-people-fill me-1"></i> บันทึกเพิ่มรอบปกติ
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 14: แก้ไขข้อมูลรอบรับสมัคร (Edit Admission Round Modal) -->
  <div class="modal fade" id="editRoundModal" tabindex="-1" aria-labelledby="editRoundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="edit_admission_round">
          <input type="hidden" name="round_id" id="edit_round_id" value="">

          <div class="modal-header bg-dark text-white">
            <div>
              <h5 class="modal-title fw-bold" id="editRoundModalLabel">
                <i class="bi bi-pencil-square text-warning me-2"></i> แก้ไขข้อมูลรอบการรับสมัคร
              </h5>
              <small class="opacity-75">ปรับปรุงกำหนดการ เงื่อนไข และสถานะของรอบ</small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">ประเภทรอบ: <span class="text-danger">*</span></label>
                <select name="round_type" id="edit_round_type" class="form-select fw-bold">
                  <option value="quota">รอบโควตา (Quota)</option>
                  <option value="normal">รอบปกติ / ทั่วไป (Normal)</option>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold text-dark small">ชื่อรอบรับสมัคร: <span class="text-danger">*</span></label>
                <input type="text" name="round_name" id="edit_round_name" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold text-dark small">รหัสรอบ (Code):</label>
                <input type="text" name="round_code" id="edit_round_code" class="form-control font-monospace" required>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">ปีการศึกษา:</label>
                <select name="academic_year" id="edit_round_academic_year" class="form-select">
                  <?php foreach ($available_academic_years as $ay): ?>
                    <option value="<?= htmlspecialchars($ay['year_name']) ?>"><?= htmlspecialchars($ay['year_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label fw-semibold text-dark small">ระดับที่เปิดรับ (คั่นด้วยจุลภาค <code>,</code>):</label>
                <input type="text" name="education_levels_str" id="edit_round_levels_str" class="form-control" placeholder="ปวช., ปวส., ปริญญาตรี">
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">จำนวนที่นั่งรับรวม:</label>
                <input type="number" name="quota_seats" id="edit_round_seats" class="form-control" min="0">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">เกรดเฉลี่ยขั้นต่ำ (GPAX):</label>
                <input type="number" step="0.01" min="0" max="4.00" name="min_gpa" id="edit_round_min_gpa" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold text-dark small">สถานะรอบ:</label>
                <select name="status" id="edit_round_status" class="form-select">
                  <option value="open">เปิดรับสมัคร (Open)</option>
                  <option value="upcoming">เร็วๆ นี้ (Upcoming)</option>
                  <option value="closed">ปิดรับสมัคร (Closed)</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่เริ่มต้นรับสมัคร:</label>
                <input type="date" name="start_date" id="edit_round_start" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันที่สิ้นสุดรับสมัคร:</label>
                <input type="date" name="end_date" id="edit_round_end" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันประกาศผลคัดเลือก:</label>
                <input type="date" name="announcement_date" id="edit_round_announce" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark small">วันรายงานตัวและมอบตัว:</label>
                <input type="date" name="registration_date" id="edit_round_reg" class="form-control">
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold text-dark small">คำอธิบายและคุณสมบัติ:</label>
                <textarea name="description" id="edit_round_desc" class="form-control" rows="2"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm" style="background-color: var(--color-primary); border-color: var(--color-primary);">
              <i class="bi bi-save2-fill me-1"></i> บันทึกการแก้ไข
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal 15: ยืนยันการลบรอบการรับสมัคร (Delete Admission Round Modal) -->
  <div class="modal fade" id="deleteRoundModal" tabindex="-1" aria-labelledby="deleteRoundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
        <form action="admin.php" method="POST">
          <input type="hidden" name="action" value="delete_admission_round">
          <input type="hidden" name="round_id" id="delete_round_id" value="">
          <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold" id="deleteRoundModalLabel">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> ยืนยันการลบรอบการรับสมัคร
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4 text-center">
            <div class="mb-3 text-danger">
              <i class="bi bi-calendar-x-fill" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบรอบการรับสมัครนี้?</h5>
            <div class="p-3 bg-light rounded-3 my-3 text-start border">
              <div><strong>ประเภทรอบ:</strong> <span id="delete_round_type_badge" class="badge bg-secondary ms-1">-</span></div>
              <div class="mt-1"><strong>ชื่อรอบ:</strong> <span id="delete_round_name" class="fw-bold text-dark">-</span></div>
              <div class="mt-1"><strong>รหัสรอบ:</strong> <span id="delete_round_code" class="font-monospace text-muted">-</span></div>
            </div>
            <p class="text-danger small mb-0">
              * ข้อมูลรอบการรับสมัครนี้จะถูกลบออกจากระบบอย่างถาวร
            </p>
          </div>

          <div class="modal-footer bg-light justify-content-center">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-danger px-4 fw-semibold">
              <i class="bi bi-trash me-1"></i> ยืนยันลบรอบนี้
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    <div class="container text-center">
      <div>&copy; 2570 วิทยาลัยอาชีวศึกษามหาสารคาม. ระบบงานทะเบียนและรับสมัครนักศึกษาออนไลน์.</div>
      <div class="small opacity-75 mt-1">Mahasarakham Vocational College Online Admission Portal</div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let currentApplicantData = null;

    // เปิด Modal ข้อมูลผู้สมัคร
    function openApplicantModal(app) {
      currentApplicantData = app;
      document.getElementById('modal_applicant_id').value = app.id || '';
      document.getElementById('modal_app_no').innerText = 'เลขที่ใบสมัคร: ' + (app.application_no || '-');
      document.getElementById('modal_name').innerText = (app.prefix || '') + (app.first_name || '') + ' ' + (app.last_name || '');
      document.getElementById('modal_citizen_id').innerText = app.citizen_id || '-';
      let levelInfo = app.education_level || '-';
      if (app.curriculum_type) {
        levelInfo += ' [' + app.curriculum_type + ']';
      }
      if (app.pvs_type) {
        levelInfo += ' (' + app.pvs_type + ')';
      }
      document.getElementById('modal_level').innerText = levelInfo;
      document.getElementById('modal_program').innerText = (app.program_name || 'ไม่ระบุ') + (app.dept_name ? ' (' + app.dept_name + ')' : '');
      document.getElementById('modal_phone').innerText = app.phone || '-';
      document.getElementById('modal_email').innerText = app.email || '-';
      document.getElementById('modal_gpa').innerText = app.gpa ? parseFloat(app.gpa).toFixed(2) : '-';
      document.getElementById('modal_school').innerText = (app.previous_school || '-') + (app.prev_level ? ' (' + app.prev_level + ')' : '');
      document.getElementById('modal_round').innerText = (app.admission_round || 'รอบโควตาและรอบทั่วไป') + (app.quota_type ? ' - ' + app.quota_type : '') + ' ปีการศึกษา ' + (app.academic_year || '2570');
      document.getElementById('modal_address').innerText = app.address || '-';

      // ข้อมูลผู้ปกครอง
      let parentStr = app.parent_name || '';
      if (!parentStr && app.father_firstname) {
        parentStr = (app.father_prefix || '') + app.father_firstname + ' ' + (app.father_lastname || '');
      }
      if (app.parent_phone) {
        parentStr += (parentStr ? ' (โทร. ' + app.parent_phone + ')' : app.parent_phone);
      }
      document.getElementById('modal_parent').innerText = parentStr || '-';

      // ลิงก์เอกสารแนบ
      const docsContainer = document.getElementById('modal_docs');
      if (docsContainer) {
        let docsHtml = '';
        if (app.file_photo) {
          docsHtml += `<a href="uploads/${encodeURIComponent(app.file_photo)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-bounding-box me-1"></i>รูปถ่าย</a>`;
        }
        if (app.file_idcard) {
          docsHtml += `<a href="uploads/${encodeURIComponent(app.file_idcard)}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-person-vcard me-1"></i>บัตร ปชช.</a>`;
        }
        if (app.file_transcript) {
          docsHtml += `<a href="uploads/${encodeURIComponent(app.file_transcript)}" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-file-earmark-medical me-1"></i>ปพ.1 (GPAX)</a>`;
        }
        docsContainer.innerHTML = docsHtml || '<span class="text-muted small">ยังไม่ได้แนบไฟล์เอกสาร</span>';
      }

      // ปุ่มพิมพ์ใบสมัคร
      const printBtn = document.getElementById('modal_print_btn');
      if (printBtn) {
        printBtn.href = 'print_application.php?search=' + encodeURIComponent(app.citizen_id || '');
      }
      
      document.getElementById('modal_status_select').value = app.status || 'pending';
      document.getElementById('modal_status_note').value = app.status_note || '';
    }

    // เรียกยืนยันการลบจาก Modal รายละเอียดผู้สมัคร
    function confirmDeleteFromApplicantModal() {
      if (!currentApplicantData) return;
      const appModalEl = document.getElementById('applicantModal');
      const appModal = bootstrap.Modal.getInstance(appModalEl);
      if (appModal) {
        appModal.hide();
      }
      openDeleteApplicantModal(currentApplicantData);
    }

    // เปิด Modal ลบผู้สมัคร
    function openDeleteApplicantModal(app) {
      document.getElementById('delete_applicant_id').value = app.id || '';
      document.getElementById('delete_app_no').innerText = app.application_no || '-';
      document.getElementById('delete_app_name').innerText = (app.prefix || '') + (app.first_name || '') + ' ' + (app.last_name || '');
      document.getElementById('delete_app_citizen').innerText = app.citizen_id || '-';
      document.getElementById('delete_app_prog').innerText = (app.program_name || '-') + (app.education_level ? ' (' + app.education_level + ')' : '');
      const modal = new bootstrap.Modal(document.getElementById('deleteApplicantModal'));
      modal.show();
    }

    // เปิด Modal เพิ่มสาขาโดยระบุแผนกและระดับชั้นล่วงหน้า
    function openAddProgramForDept(deptId, eduLevel) {
      document.getElementById('add_prog_dept_id').value = deptId;
      document.getElementById('add_prog_edu_level').value = eduLevel;
      if (document.getElementById('add_prog_type')) {
        document.getElementById('add_prog_type').value = 'ปกติ';
      }
      document.getElementById('add_prog_code').value = '';
      document.getElementById('add_prog_name').value = '';
      document.getElementById('add_prog_seats').value = 40;
      document.getElementById('add_prog_status').value = 'open';
      const modal = new bootstrap.Modal(document.getElementById('addProgramModal'));
      modal.show();
    }

    // เปิด Modal เพิ่มสาขาโดยระบุระดับชั้นล่วงหน้า
    function openAddProgramWithLevel(eduLevel) {
      document.getElementById('add_prog_edu_level').value = eduLevel;
      if (document.getElementById('add_prog_type')) {
        document.getElementById('add_prog_type').value = 'ปกติ';
      }
      const modal = new bootstrap.Modal(document.getElementById('addProgramModal'));
      modal.show();
    }

    // เปิด Modal แก้ไขสาขาวิชา
    function openEditProgramModal(prg) {
      document.getElementById('edit_prog_id').value = prg.id || '';
      document.getElementById('edit_prog_dept_id').value = prg.dept_id || '';
      document.getElementById('edit_prog_edu_level').value = prg.education_level || 'ปวช.';
      if (document.getElementById('edit_prog_type')) {
        document.getElementById('edit_prog_type').value = prg.program_type || 'ปกติ';
      }
      document.getElementById('edit_prog_code').value = prg.program_code || '';
      document.getElementById('edit_prog_name').value = prg.program_name || '';
      document.getElementById('edit_prog_seats').value = prg.seats_available || 40;
      document.getElementById('edit_prog_status').value = prg.status || 'open';
      const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));
      modal.show();
    }

    // เปิด Modal ลบสาขาวิชา
    function openDeleteProgramModal(prg) {
      document.getElementById('delete_prog_id').value = prg.id || '';
      document.getElementById('delete_prog_name').innerText = prg.program_name || '-';
      document.getElementById('delete_prog_level').innerText = prg.education_level || '-';
      document.getElementById('delete_prog_dept').innerText = prg.dept_name || '-';
      const modal = new bootstrap.Modal(document.getElementById('deleteProgramModal'));
      modal.show();
    }

    // เปิด Modal แก้ไขแผนกวิชา
    function openEditDeptModal(dept) {
      document.getElementById('edit_dept_id').value = dept.id || '';
      document.getElementById('edit_dept_code').value = dept.dept_code || '';
      document.getElementById('edit_dept_name').value = dept.dept_name || '';
      document.getElementById('edit_dept_desc').value = dept.description || '';
      const modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
      modal.show();
    }

    // เปิด Modal ลบแผนกวิชา
    function openDeleteDeptModal(dept) {
      document.getElementById('delete_dept_id').value = dept.id || '';
      document.getElementById('delete_dept_code').innerText = dept.dept_code || '-';
      document.getElementById('delete_dept_name').innerText = dept.dept_name || '-';
      const modal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
      modal.show();
    }

    // เปิด Modal แก้ไขผู้ดูแลระบบ
    function openEditAdminModal(adm) {
      document.getElementById('edit_admin_id').value = adm.id || '';
      document.getElementById('edit_modal_subtitle').innerText = 'ID #' + (adm.id || '-') + ' | ' + (adm.username || '');
      document.getElementById('edit_username').value = adm.username || '';
      document.getElementById('edit_fullname').value = adm.full_name || '';
      document.getElementById('edit_email').value = adm.email || '';
      document.getElementById('edit_role').value = adm.role || 'officer';
      document.getElementById('edit_password').value = '';
    }

    // เปิด Modal ลบผู้ดูแลระบบ
    function openDeleteAdminModal(adm) {
      document.getElementById('delete_admin_id').value = adm.id || '';
      document.getElementById('delete_fullname').innerText = adm.full_name || '-';
      document.getElementById('delete_username').innerText = adm.username || '-';
      document.getElementById('delete_role').innerText = adm.role === 'superadmin' ? 'ผู้ดูแลระบบสูงสุด (Superadmin)' : 'เจ้าหน้าที่ (Officer)';
    }

    // สลับแสดง/ซ่อนรหัสผ่าน
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }

    // สลับปีการศึกษาภาพรวม
    function switchAcademicYear(yr) {
      if (!yr) return;
      window.location.href = 'admin.php?tab=departments&view=<?= htmlspecialchars($dept_view) ?>&academic_year=' + encodeURIComponent(yr);
    }

    // ตรวจสอบแบบฟอร์มเพิ่มแอดมิน
    function validateAddAdminForm() {
      const pw = document.getElementById('add_password').value;
      const cpw = document.getElementById('add_confirm_password').value;
      if (pw !== cpw) {
        alert('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        document.getElementById('add_confirm_password').focus();
        return false;
      }
      return true;
    }

    // พรีวิวรูปภาพแบบ Real-time ทันทีที่ผู้ใช้เลือกไฟล์
    function previewImage(input, previewElementId, containerId) {
      if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 8 * 1024 * 1024) {
          alert('ขนาดไฟล์รูปภาพเกินขีดจำกัด (สูงสุด 8MB) กรุณาเลือกรูปภาพที่มีขนาดเล็กลง');
          input.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          const img = document.getElementById(previewElementId);
          if (img) {
            img.src = e.target.result;
          }
          if (containerId) {
            const container = document.getElementById(containerId);
            if (container) container.classList.remove('d-none');
          }
        };
        reader.readAsDataURL(file);
      }
    }

    // เปิด Modal แก้ไขรอบการรับสมัคร
    function openEditRoundModal(round) {
      if (!round) return;
      document.getElementById('edit_round_id').value = round.id || '';
      document.getElementById('edit_round_code').value = round.round_code || '';
      document.getElementById('edit_round_name').value = round.round_name || '';
      document.getElementById('edit_round_type').value = round.round_type || 'quota';
      document.getElementById('edit_round_academic_year').value = round.academic_year || '<?= htmlspecialchars($selected_year) ?>';
      document.getElementById('edit_round_levels_str').value = round.education_levels || 'ปวช., ปวส., ปริญญาตรี';
      document.getElementById('edit_round_seats').value = round.quota_seats || 0;
      document.getElementById('edit_round_min_gpa').value = round.min_gpa ? parseFloat(round.min_gpa).toFixed(2) : '0.00';
      document.getElementById('edit_round_start').value = round.start_date || '';
      document.getElementById('edit_round_end').value = round.end_date || '';
      document.getElementById('edit_round_announce').value = round.announcement_date || '';
      document.getElementById('edit_round_reg').value = round.registration_date || '';
      document.getElementById('edit_round_status').value = round.status || 'open';
      document.getElementById('edit_round_desc').value = round.description || '';

      const modal = new bootstrap.Modal(document.getElementById('editRoundModal'));
      modal.show();
    }

    // เปิด Modal ลบรอบการรับสมัคร
    function openDeleteRoundModal(round) {
      if (!round) return;
      document.getElementById('delete_round_id').value = round.id || '';
      document.getElementById('delete_round_name').innerText = round.round_name || '-';
      document.getElementById('delete_round_code').innerText = round.round_code || '-';
      
      const badge = document.getElementById('delete_round_type_badge');
      if (round.round_type === 'quota') {
        badge.className = 'badge bg-warning text-dark ms-1';
        badge.innerText = 'รอบโควตา';
      } else {
        badge.className = 'badge bg-primary text-white ms-1';
        badge.innerText = 'รอบปกติ / ทั่วไป';
      }

      const modal = new bootstrap.Modal(document.getElementById('deleteRoundModal'));
      modal.show();
    }
  </script>
</body>
</html>
