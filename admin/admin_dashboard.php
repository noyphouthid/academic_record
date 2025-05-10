<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ดึงข้อมูลสรุป
$stats = [];

// จำนวนนักศึกษาทั้งหมด
$student_count_query = "SELECT COUNT(*) as total FROM students";
$student_count_result = $conn->query($student_count_query);
$stats['total_students'] = $student_count_result->fetch_assoc()['total'];

// จำนวนวิชาทั้งหมด
$subject_count_query = "SELECT COUNT(*) as total FROM subjects";
$subject_count_result = $conn->query($subject_count_query);
$stats['total_subjects'] = $subject_count_result->fetch_assoc()['total'];

// จำนวนผลการเรียนทั้งหมด
$grade_count_query = "SELECT COUNT(*) as total FROM grades";
$grade_count_result = $conn->query($grade_count_query);
$stats['total_grades'] = $grade_count_result->fetch_assoc()['total'];

// จำนวนสาขาวิชาทั้งหมด
$major_count_query = "SELECT COUNT(*) as total FROM majors";
$major_count_result = $conn->query($major_count_query);
$stats['total_majors'] = $major_count_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລະບົບຜູ້ບໍລິຫານ - ວິທະຍາໄລເຕັກນິກ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 60px;
            font-family: -apple-system, BlinkMacSystemFont, 'Noto Sans Lao', 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            padding: 20px;
            background-color: #2c3e50;
            color: white;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background-color: #3498db;
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
            border: none;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3498db;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 10px;
        }
        .user-name {
            font-weight: bold;
        }
        .user-role {
            font-size: 12px;
            opacity: 0.8;
        }
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .navbar-brand {
            font-weight: 600;
        }
        .list-group-item {
            border: none;
            margin-bottom: 5px;
            border-radius: 5px;
            background-color: #f8f9fa;
            transition: all 0.2s ease;
        }
        .list-group-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        .list-group-item i {
            color: #3498db;
            margin-right: 10px;
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        .card-header i {
            color: #3498db;
            margin-right: 10px;
        }
        
        /* Custom Colors for Cards */
        .bg-primary {
            background-color: #3498db !important;
        }
        .bg-success {
            background-color: #2ecc71 !important;
        }
        .bg-warning {
            background-color: #f39c12 !important;
        }
        .bg-danger {
            background-color: #e74c3c !important;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i> ວິທະຍາໄລເຕັກນິກ - ລະບົບຜູ້ບໍລິຫານ
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> ອອກຈາກລະບົບ
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                <div class="user-role"><?php echo ($_SESSION['role'] === 'admin') ? 'ຜູ້ບໍລິຫານລະບົບ' : 'ອາຈານ'; ?></div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> ໜ້າຫຼັກ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_students.php">
                    <i class="fas fa-user-graduate"></i> ຈັດການນັກສຶກສາ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_grades.php">
                    <i class="fas fa-chart-line"></i> ຈັດການຜົນການຮຽນ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_subjects.php">
                    <i class="fas fa-book"></i> ຈັດການລາຍວິຊາ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_majors.php">
                    <i class="fas fa-graduation-cap"></i> ຈັດການສາຂາວິຊາ
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_users.php">
                    <i class="fas fa-users-cog"></i> ຈັດການຜູ້ໃຊ້ລະບົບ
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_reports.php">
                    <i class="fas fa-file-alt"></i> ລາຍງານ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i> ເບິ່ງໜ້າເວັບໄຊຕ໌
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-tachometer-alt text-primary me-2"></i> ໜ້າຫຼັກ</h2>
                <p class="text-muted">ຍິນດີຕ້ອນຮັບເຂົ້າສູ່ລະບົບຈັດການຜົນການຮຽນນັກສຶກສາ</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card dashboard-card bg-primary text-white text-center">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h5 class="card-title">ນັກສຶກສາທັງໝົດ</h5>
                        <h2><?php echo $stats['total_students']; ?></h2>
                        <a href="admin_students.php" class="text-white">ຈັດການນັກສຶກສາ <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card dashboard-card bg-success text-white text-center">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h5 class="card-title">ລາຍວິຊາທັງໝົດ</h5>
                        <h2><?php echo $stats['total_subjects']; ?></h2>
                        <a href="admin_subjects.php" class="text-white">ຈັດການລາຍວິຊາ <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card dashboard-card bg-warning text-white text-center">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h5 class="card-title">ສາຂາວິຊາທັງໝົດ</h5>
                        <h2><?php echo $stats['total_majors']; ?></h2>
                        <a href="admin_majors.php" class="text-white">ຈັດການສາຂາວິຊາ <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card dashboard-card bg-danger text-white text-center">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="card-title">ຜົນການຮຽນທັງໝົດ</h5>
                        <h2><?php echo $stats['total_grades']; ?></h2>
                        <a href="admin_grades.php" class="text-white">ຈັດການຜົນການຮຽນ <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks"></i> ການດຳເນີນການດ່ວນ</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="admin_students.php?action=add" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle"></i> ເພີ່ມນັກສຶກສາໃໝ່
                            </a>
                            <a href="admin_grades.php?action=add" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle"></i> ເພີ່ມຜົນການຮຽນ
                            </a>
                            <a href="admin_subjects.php?action=add" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle"></i> ເພີ່ມລາຍວິຊາໃໝ່
                            </a>
                            <a href="admin_reports.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-file-export"></i> ສ້າງລາຍງານ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> ຂໍ້ມູນລະບົບ</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-server"></i> ເວີຊັນລະບົບ</span>
                                <span class="badge bg-primary rounded-pill">1.0</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-calendar-alt"></i> ປີການສຶກສາປັດຈຸບັນ</span>
                                <span class="badge bg-primary rounded-pill"><?php echo date('Y'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock"></i> ອັບເດດຫຼ້າສຸດ</span>
                                <span><?php echo date('d/m/Y H:i:s'); ?></span>
                            </li>
                            <li class="list-group-item">
                                <span><i class="fas fa-link"></i> ລິ້ງກ໌ສຳລັບນັກສຶກສາ</span><br>
                                <a href="../index.php" target="_blank" class="small">
    http://localhost/academic_records/index.php
</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            navbarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Resize handler
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>