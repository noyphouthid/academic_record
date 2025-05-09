<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

require_once '../config.php';

// ตรวจสอบว่ามีการล็อกอินอยู่แล้วหรือไม่
if (isLoggedIn()) {
    header("Location:admin_dashboard.php");
    exit();
}

// ตรวจสอบการส่งฟอร์ม
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = clean($conn, $_POST['username']);
        $password = $_POST['password'];
        
        // ตรวจสอบข้อมูลผู้ใช้
        $user_query = "SELECT * FROM users WHERE username = '$username'";
        $user_result = $conn->query($user_query);
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            
            // ตรวจสอบรหัสผ่าน
            if (password_verify($password, $user['password'])) {
                // สร้าง session สำหรับเก็บข้อมูลผู้ใช้
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // ไปยังหน้า Dashboard
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error_message = "ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ";
            }
        } else {
            $error_message = "ຊື່ຜູ້ໃຊ້ບັນຊີບໍ່ຖືກຕ້ອງ";
        }
    } else {
        $error_message = "ກະລຸນາໃສ່ຊື່ຜູ້ໃຊ້ ແລະ ລະຫັດຜ່ານ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ເຂົ້າສູ່ໜ້າຜູ້ດູແລລະບົບ - Polytechnic College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <style>
         * {
            font-family: 'Noto Sans Lao', sans-serif;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .back-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="College Logo" class="logo">
            <h2>Polytechnic College</h2>
            <h4>ຜູ້ດູແລລະບົບ</h4>
        </div>
        
        <div class="card">
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">ລະຫັດຜ່ານ</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">ເຂົ້າສູ່ລະບົບ</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="back-link">
            <a href="../index.php">ກັບໄປຍັງໜ້າຈັດການຜົນການຮຽນ</a>
        </div>
    </div>