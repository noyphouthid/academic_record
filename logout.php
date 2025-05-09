<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล เพื่อให้มี session_start()
require_once 'config.php';

// ลบข้อมูลทั้งหมดใน session
$_SESSION = array();

// ลบ session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// ทำลาย session
session_destroy();

// ไปยังหน้าล็อกอิน
header("Location: admin_login.php");
exit();
?>