<?php
/**
 * Admission Rounds Helper
 * ระบบจัดการรอบการรับสมัคร (รอบโควตา และ รอบปกติ/ทั่วไป)
 * วิทยาลัยอาชีวศึกษามหาสารคาม
 */

require_once __DIR__ . '/database.php';

/**
 * ตรวจสอบและอัปเดตโครงสร้างฐานข้อมูล (Auto-Migration)
 * ให้รองรับคอลัมน์ program_type ใน programs และ curriculum_type ใน applicants อัตโนมัติ
 */
function ensureAdmissionDatabaseUpdates(PDO $pdo): void {
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    // 1. ตาราง programs: เพิ่ม program_type
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `programs` LIKE 'program_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `programs` ADD COLUMN `program_type` VARCHAR(50) NOT NULL DEFAULT 'ปกติ' AFTER `education_level`");
        }
    } catch (Throwable $e) {
        error_log("Auto-migration programs.program_type: " . $e->getMessage());
    }

    // 2. ตาราง applicants: เพิ่ม curriculum_type
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `applicants` LIKE 'curriculum_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE `applicants` ADD COLUMN `curriculum_type` VARCHAR(50) NOT NULL DEFAULT 'ระบบปกติ' AFTER `education_level`");
        }
    } catch (Throwable $e) {
        error_log("Auto-migration applicants.curriculum_type: " . $e->getMessage());
    }
}

/**
 * สร้างตาราง admission_rounds หากยังไม่มี และ Seed ข้อมูลรอบเริ่มต้น
 */
function ensureAdmissionRoundsTableExists(PDO $pdo): void {
    ensureAdmissionDatabaseUpdates($pdo);
    static $initialized = false;
    if ($initialized) {
        return;
    }

    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS `admission_rounds` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `round_code` VARCHAR(50) NOT NULL UNIQUE,
                `round_name` VARCHAR(255) NOT NULL,
                `round_type` ENUM('quota', 'normal') NOT NULL DEFAULT 'quota',
                `academic_year` VARCHAR(10) NOT NULL DEFAULT '2570',
                `education_levels` VARCHAR(255) NOT NULL DEFAULT 'ปวช., ปวส., ปริญญาตรี',
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `announcement_date` DATE NULL,
                `registration_date` DATE NULL,
                `quota_seats` INT NOT NULL DEFAULT 0,
                `min_gpa` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
                `status` ENUM('open', 'closed', 'upcoming') NOT NULL DEFAULT 'open',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `description` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);

        // ตรวจสอบว่ามีข้อมูลรอบหรือไม่ ถ้ายังไม่มีให้ Seed ข้อมูลรอบโควตาและรอบปกติเริ่มต้น
        $count = (int)$pdo->query("SELECT COUNT(*) FROM admission_rounds")->fetchColumn();
        if ($count === 0) {
            $insert = $pdo->prepare("
                INSERT INTO admission_rounds 
                (round_code, round_name, round_type, academic_year, education_levels, start_date, end_date, announcement_date, registration_date, quota_seats, min_gpa, status, is_active, description)
                VALUES 
                (:code, :name, :type, :year, :levels, :start, :end, :announce, :reg, :seats, :gpa, :status, :active, :desc)
            ");

            // 1. รอบโควตาเริ่มต้น
            $insert->execute([
                ':code'     => 'QUOTA-2570',
                ':name'     => 'รอบโควตากรณีพิเศษและโควตาสถานศึกษาเดิม',
                ':type'     => 'quota',
                ':year'     => '2570',
                ':levels'   => 'ปวช., ปวส., ปริญญาตรี',
                ':start'    => '2026-10-01',
                ':end'      => '2026-12-31',
                ':announce' => '2027-01-10',
                ':reg'      => '2027-01-20',
                ':seats'    => 250,
                ':gpa'      => 2.00,
                ':status'   => 'open',
                ':active'   => 1,
                ':desc'     => 'สำหรับนักเรียน นักศึกษาโควตากรณีพิเศษ โควตาเรียนดี กิจกรรมเด่น และโควตาสถานศึกษาเดิม (GPA 2.00 ขึ้นไป)',
            ]);

            // 2. รอบปกติ / ทั่วไปเริ่มต้น
            $insert->execute([
                ':code'     => 'NORMAL-2570',
                ':name'     => 'รอบรับสมัครทั่วไป (รอบปกติ)',
                ':type'     => 'normal',
                ':year'     => '2570',
                ':levels'   => 'ปวช., ปวส., ปริญญาตรี',
                ':start'    => '2027-01-05',
                ':end'      => '2027-02-28',
                ':announce' => '2027-03-05',
                ':reg'      => '2027-03-15',
                ':seats'    => 500,
                ':gpa'      => 0.00,
                ':status'   => 'open',
                ':active'   => 1,
                ':desc'     => 'เปิดรับสมัครนักเรียน นักศึกษาใหม่ทั่วไป วุฒิ ม.3, ปวช., ม.6 หรือเทียบเท่า เข้าศึกษาต่อระดับ ปวช., ปวส. และ ป.ตรี',
            ]);
        }

        $initialized = true;
    } catch (PDOException $e) {
        error_log("Failed to ensure admission_rounds table: " . $e->getMessage());
    }
}

/**
 * ดึงรายการรอบการรับสมัครทั้งหมด
 */
function getAllAdmissionRounds(PDO $pdo, ?string $academic_year = null, ?string $type = null): array {
    ensureAdmissionRoundsTableExists($pdo);
    try {
        $sql = "SELECT * FROM admission_rounds WHERE 1=1";
        $params = [];

        if (!empty($academic_year)) {
            $sql .= " AND academic_year = :year";
            $params[':year'] = $academic_year;
        }

        if (!empty($type)) {
            $sql .= " AND round_type = :type";
            $params[':type'] = $type;
        }

        $sql .= " ORDER BY academic_year DESC, round_type ASC, id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getAllAdmissionRounds Error: " . $e->getMessage());
        return [];
    }
}

/**
 * ดึงข้อมูลรอบรับสมัครตาม ID
 */
function getAdmissionRoundById(PDO $pdo, int $id): ?array {
    ensureAdmissionRoundsTableExists($pdo);
    try {
        $stmt = $pdo->prepare("SELECT * FROM admission_rounds WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        error_log("getAdmissionRoundById Error: " . $e->getMessage());
        return null;
    }
}

/**
 * ดึงรอบรับสมัครที่กำลังเปิดรับสมัครอยู่ (Active & Open)
 */
function getActiveAdmissionRounds(PDO $pdo, ?string $academic_year = null): array {
    ensureAdmissionRoundsTableExists($pdo);
    try {
        $sql = "SELECT * FROM admission_rounds WHERE status = 'open' AND is_active = 1";
        $params = [];

        if (!empty($academic_year)) {
            $sql .= " AND academic_year = :year";
            $params[':year'] = $academic_year;
        }

        $sql .= " ORDER BY round_type ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("getActiveAdmissionRounds Error: " . $e->getMessage());
        return [];
    }
}

/**
 * สถิติภาพรวมของรอบการรับสมัคร
 */
function getRoundStats(PDO $pdo, ?string $academic_year = null): array {
    ensureAdmissionRoundsTableExists($pdo);
    $stats = [
        'total'    => 0,
        'quota'    => 0,
        'normal'   => 0,
        'open'     => 0,
        'closed'   => 0,
        'upcoming' => 0,
    ];

    try {
        $sql = "SELECT round_type, status, COUNT(*) as cnt FROM admission_rounds WHERE 1=1";
        $params = [];
        if (!empty($academic_year)) {
            $sql .= " AND academic_year = :year";
            $params[':year'] = $academic_year;
        }
        $sql .= " GROUP BY round_type, status";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $r) {
            $cnt = (int)$r['cnt'];
            $stats['total'] += $cnt;

            if ($r['round_type'] === 'quota') {
                $stats['quota'] += $cnt;
            } elseif ($r['round_type'] === 'normal') {
                $stats['normal'] += $cnt;
            }

            if ($r['status'] === 'open') {
                $stats['open'] += $cnt;
            } elseif ($r['status'] === 'closed') {
                $stats['closed'] += $cnt;
            } elseif ($r['status'] === 'upcoming') {
                $stats['upcoming'] += $cnt;
            }
        }
    } catch (PDOException $e) {
        error_log("getRoundStats Error: " . $e->getMessage());
    }

    return $stats;
}

/**
 * สลับสถานะเปิด/ปิดรับสมัครของรอบ
 */
function toggleAdmissionRoundStatus(PDO $pdo, int $id): array {
    ensureAdmissionRoundsTableExists($pdo);
    $round = getAdmissionRoundById($pdo, $id);
    if (!$round) {
        throw new InvalidArgumentException("ไม่พบรอบการรับสมัครที่ต้องการปรับสถานะ");
    }

    $newStatus = ($round['status'] === 'open') ? 'closed' : 'open';
    $stmt = $pdo->prepare("UPDATE admission_rounds SET status = :st, is_active = :act WHERE id = :id");
    $stmt->execute([
        ':st'  => $newStatus,
        ':act' => ($newStatus === 'open') ? 1 : 0,
        ':id'  => $id,
    ]);

    return [
        'round_name' => $round['round_name'],
        'new_status' => $newStatus,
    ];
}

/**
 * ฟังก์ชันช่วยแปลงข้อมูลวันที่เป็นภาษาไทย
 */
function formatThaiDate(?string $dateStr): string {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($dateStr);
    if (!$ts) return '-';

    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    $d = date('j', $ts);
    $m = $months[(int)date('n', $ts)] ?? '';
    $y = (int)date('Y', $ts) + 543;

    return "{$d} {$m} {$y}";
}

