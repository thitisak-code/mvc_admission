<?php
/**
 * Database Configuration & Connection (PDO)
 * ระบบรับสมัครนักเรียน นักศึกษา วิทยาลัยอาชีวศึกษามหาสารคาม
 */

// การตั้งค่าการเชื่อมต่อฐานข้อมูล XAMPP
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mvc_admission');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * ฟังก์ชันเชื่อมต่อฐานข้อมูลผ่าน PDO
 * @return PDO
 */
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
            $options[constant('Pdo\Mysql::ATTR_INIT_COMMAND')] = "SET NAMES " . DB_CHARSET;
        } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[@PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // บันทึก Log และแสดงข้อความที่เป็นมิตร
            error_log("Database Connection Failed: " . $e->getMessage());
            die("
                <div style='font-family: sans-serif; padding: 20px; background: #FFF5F5; border: 1px solid #FEB2B2; border-radius: 8px; margin: 20px auto; max-width: 600px;'>
                    <h3 style='color: #9B1C1C; margin-top: 0;'>⚠️ ไม่สามารถเชื่อมต่อฐานข้อมูลได้</h3>
                    <p style='color: #4A5568;'>กรุณาตรวจสอบว่า Service MySQL ในโปรแกรม XAMPP เปิดทำงานอยู่</p>
                    <small style='color: #718096;'>รายละเอียดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</small>
                </div>
            ");
        }
    }

    return $pdo;
}

// ตั้งค่า Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// สร้างตัวแปร $pdo และ $conn สำหรับเรียกใช้งานทั่วไป (รองรับทั้งสองโมดูล)
$pdo = getDB();
$conn = $pdo;

// ตรวจสอบและอัปเดตโครงสร้างตาราง programs และ applicants อัตโนมัติ (Auto-Migration)
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $col1 = $pdo->query("SHOW COLUMNS FROM `programs` LIKE 'program_type'")->fetchAll();
        if (empty($col1)) {
            $pdo->exec("ALTER TABLE `programs` ADD COLUMN `program_type` VARCHAR(50) NOT NULL DEFAULT 'ปกติ' AFTER `education_level`");
        }
        $col2 = $pdo->query("SHOW COLUMNS FROM `applicants` LIKE 'curriculum_type'")->fetchAll();
        if (empty($col2)) {
            $pdo->exec("ALTER TABLE `applicants` ADD COLUMN `curriculum_type` VARCHAR(50) NOT NULL DEFAULT 'ระบบปกติ' AFTER `education_level`");
        }
    }
} catch (Throwable $e) {
    // ป้องกันการขัดข้องหากฐานข้อมูลถูกจำกัดสิทธิ์ ALTER
}

