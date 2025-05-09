<?php
// Database configuration
$db_host = "localhost";
$db_user = "root"; // แก้ไขตามข้อมูลจริงของคุณ
$db_pass = ""; // แก้ไขตามข้อมูลจริงของคุณ
$db_name = "academic_records";

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// Set character set to utf8
$conn->set_charset("utf8");

// ฟังก์ชันสำหรับแปลงระดับเกรดเป็นค่าตัวเลขสำหรับคำนวณ GPA
function gradeToPoint($grade) {
    switch ($grade) {
        case 'A':  return 4.0;
        case 'B+': return 3.5;
        case 'B':  return 3.0;
        case 'C+': return 2.5;
        case 'C':  return 2.0;
        case 'D+': return 1.5;
        case 'D':  return 1.0;
        case 'F':  return 0.0;
        default:   return 0.0;
    }
}

// ฟังก์ชันสำหรับทำความสะอาดข้อมูลนำเข้า (ป้องกัน SQL Injection)
function clean($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

// ฟังก์ชันสำหรับตรวจสอบการล็อกอินของผู้ใช้ (admin)
function isLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        return true;
    }
    return false;
}

// เริ่ม session สำหรับเก็บข้อมูลการล็อกอิน
session_start();
?>