<?php
// เริ่ม session สำหรับความปลอดภัย
session_start();

// รหัสผ่านที่ต้องการสร้างแฮช
$password = "admin123";  // คุณสามารถเปลี่ยนเป็นรหัสผ่านที่คุณต้องการ

// สร้างรหัสแฮชจากรหัสผ่าน
$hash = password_hash($password, PASSWORD_DEFAULT);

// แสดงผลลัพธ์
echo "<html><head><title>Password Hash Generator</title>";
echo "<style>body{font-family:Arial;margin:20px;} .result{background:#f0f0f0;padding:10px;margin:10px 0;}</style>";
echo "</head><body>";
echo "<h2>Password Hash Generator</h2>";
echo "<p>This page generates a secure hash for your password.</p>";
echo "<div class='result'>";
echo "<strong>Password:</strong> " . $password . "<br>";
echo "<strong>Generated Hash:</strong> " . $hash . "<br>";
echo "</div>";

// สร้างคำสั่ง SQL สำหรับการอัปเดตหรือเพิ่มผู้ใช้
echo "<h3>SQL Commands:</h3>";
echo "<div class='result'>";
echo "<strong>To insert a new user:</strong><br>";
echo "<code>INSERT INTO users (username, password, role, email) <br>VALUES ('admin', '" . $hash . "', 'admin', 'admin@example.com');</code>";
echo "<br><br>";
echo "<strong>To update an existing user:</strong><br>";
echo "<code>UPDATE users SET password = '" . $hash . "' WHERE username = 'admin';</code>";
echo "</div>";

// ทดสอบการตรวจสอบรหัสผ่าน
echo "<h3>Verification Test:</h3>";
echo "<div class='result'>";
$verification_result = password_verify($password, $hash);
echo "Verification result: " . ($verification_result ? "<span style='color:green'>Success</span>" : "<span style='color:red'>Failed</span>");
echo "</div>";
echo "</body></html>";
?>