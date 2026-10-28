<?php
/**
 * ออกจากระบบ (Logout) | ระบบรับสมัครนักเรียน นักศึกษา
 * วิทยาลัยอาชีวศึกษามหาสารคาม
 */
session_start();

// ล้างตัวแปร Session ทั้งหมด
$_SESSION = [];

// ลบคุกกี้ Session หากมี
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// นำทางกลับไปยังหน้า Login พร้อมแจ้งเตือน
header("Location: login.php?msg=" . urlencode("ออกจากระบบเรียบร้อยแล้ว"));
exit;
