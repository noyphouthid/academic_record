<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ฟังก์ชันสำหรับการเพิ่มรายวิชา
function addSubject($conn, $subject_code, $subject_name, $credit) {
    $query = "INSERT INTO subjects (subject_code, subject_name, credit) 
              VALUES ('$subject_code', '$subject_name', $credit)";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ฟังก์ชันสำหรับการแก้ไขรายวิชา
function updateSubject($conn, $subject_code, $subject_name, $credit) {
    $query = "UPDATE subjects 
              SET subject_name = '$subject_name', 
                  credit = $credit 
              WHERE subject_code = '$subject_code'";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ฟังก์ชันสำหรับการลบรายวิชา
function deleteSubject($conn, $subject_code) {
    // ตรวจสอบว่ามีผลการเรียนที่ใช้รายวิชานี้หรือไม่
    $check_grades = "SELECT COUNT(*) as count FROM grades WHERE subject_code = '$subject_code'";
    $check_result = $conn->query($check_grades);
    $grade_count = $check_result->fetch_assoc()['count'];
    
    if ($grade_count > 0) {
        return "ไม่สามารถลบรายวิชานี้ได้ เนื่องจากมีผลการเรียนที่เกี่ยวข้อง";
    }
    
    $query = "DELETE FROM subjects WHERE subject_code = '$subject_code'";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ฟังก์ชันสำหรับนำเข้าข้อมูลจาก CSV
function importSubjectsFromCSV($conn, $file_path) {
    $results = [
        'success' => 0,
        'error' => 0,
        'errors' => [],
        'total' => 0
    ];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // ข้ามบรรทัดแรก (header)
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $results['total']++;
            
            if (count($data) < 3) {
                $results['error']++;
                $results['errors'][] = "แถวที่ {$results['total']}: ข้อมูลไม่ครบถ้วน";
                continue;
            }
            
            $subject_code = clean($conn, trim($data[0]));
            $subject_name = clean($conn, trim($data[1]));
            $credit = intval(trim($data[2]));
            
            if (empty($subject_code) || empty($subject_name) || $credit <= 0) {
                $results['error']++;
                $results['errors'][] = "แถวที่ {$results['total']}: ข้อมูลไม่ถูกต้อง";
                continue;
            }
            
            // ตรวจสอบว่ามีรายวิชาซ้ำหรือไม่
            $check_query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
            $check_result = $conn->query($check_query);
            
            if ($check_result->num_rows > 0) {
                // อัพเดทรายวิชาที่มีอยู่
                if (updateSubject($conn, $subject_code, $subject_name, $credit)) {
                    $results['success']++;
                } else {
                    $results['error']++;
                    $results['errors'][] = "แถวที่ {$results['total']}: ไม่สามารถอัพเดทรายวิชา $subject_code ได้";
                }
            } else {
                // เพิ่มรายวิชาใหม่
                if (addSubject($conn, $subject_code, $subject_name, $credit)) {
                    $results['success']++;
                } else {
                    $results['error']++;
                    $results['errors'][] = "แถวที่ {$results['total']}: ไม่สามารถเพิ่มรายวิชา $subject_code ได้";
                }
            }
        }
        fclose($handle);
    } else {
        $results['errors'][] = "ไม่สามารถเปิดไฟล์ CSV ได้";
        $results['error']++;
    }
    
    return $results;
}

// จัดการการส่งฟอร์ม
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$subject_code = isset($_GET['id']) ? clean($conn, $_GET['id']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        $subject_name = clean($conn, $_POST['subject_name']);
        $credit = clean($conn, $_POST['credit']);
        
        // ตรวจสอบว่ารหัสวิชาซ้ำหรือไม่
        $check_query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ລະຫັດວິຊານີ້ມີໃນລະບົບແລ້ວ";
        } else {
            if (addSubject($conn, $subject_code, $subject_name, $credit)) {
                $message = "ເພີ່ມລາຍວິຊາຮຽບຮ້ອຍແລ້ວ";
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        $subject_name = clean($conn, $_POST['subject_name']);
        $credit = clean($conn, $_POST['credit']);
        
        if (updateSubject($conn, $subject_code, $subject_name, $credit)) {
            $message = "ແກ້ໄຂລາຍວິຊາຮຽບຮ້ອຍແລ້ວ";
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $conn->error;
        }
    } elseif (isset($_POST['delete_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        
        $result = deleteSubject($conn, $subject_code);
        if ($result === true) {
            $message = "ລຶບລາຍວິຊາຮຽບຮ້ອຍແລ້ວ";
            header("Location: admin_subjects.php?deleted=1");
            exit();
        } else {
            $error = (is_string($result)) ? $result : "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $conn->error;
        }
    } elseif (isset($_POST['import_csv'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file_tmp = $_FILES['csv_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'csv') {
                $import_results = importSubjectsFromCSV($conn, $file_tmp);
                
                if ($import_results['success'] > 0) {
                    $message = "ນຳເຂົ້າລາຍວິຊາສຳເລັດ {$import_results['success']} ລາຍການ";
                    if ($import_results['error'] > 0) {
                        $message .= " (ມີຂໍ້ຜິດພາດ {$import_results['error']} ລາຍການ)";
                    }
                }
                
                if (!empty($import_results['errors'])) {
                    $error = "ຂໍ້ຜິດພາດໃນການນຳເຂົ້າ:<br>" . implode("<br>", $import_results['errors']);
                }
            } else {
                $error = "ກະລຸນາອັບໂຫລດໄຟລ໌ CSV ເທົ່ານັ້ນ";
            }
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການອັບໂຫລດໄຟລ໌";
        }
    }
}

// ดึงข้อมูลรายวิชาสำหรับการแก้ไข
$edit_data = null;
if ($action === 'edit' && !empty($subject_code)) {
    $edit_query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບລາຍວິຊາທີ່ຕ້ອງການແກ້ໄຂ";
    }
}

// ดึงข้อมูลรายวิชาทั้งหมด
$subjects_query = "SELECT * FROM subjects ORDER BY subject_code";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
if ($subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຈັດການລາຍວິຊາ - ວິທະຍາໄລເຕັກນິກ</title>
    <link rel="stylesheet" href="navbar.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            padding-top: 60px;
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .card-header i {
            color: #3498db;
            margin-right: 10px;
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
        
        .dropzone {
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .dropzone:hover {
            background-color: #f1f8fe;
        }
        
        .dropzone i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .csv-format {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .csv-format pre {
            margin-bottom: 0;
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
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
  <?php include 'navbar.php'; ?>
   
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-book"></i> ຈັດການລາຍວິຊາ</h2>
            <?php if ($action !== 'add' && $action !== 'edit' && $action !== 'import'): ?>
            <div>
                <a href="admin_subjects.php?action=add" class="btn btn-primary me-2">
                    <i class="fas fa-plus-circle"></i> ເພີ່ມລາຍວິຊາໃໝ່
                </a>
                <a href="admin_subjects.php?action=import" class="btn btn-success">
                    <i class="fas fa-file-import"></i> ນຳເຂົ້າຈາກ CSV
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> ລຶບລາຍວິຊາຮຽບຮ້ອຍແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ฟอร์มเพิ่มรายวิชา -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-primary"></i> ເພີ່ມລາຍວິຊາໃໝ່</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="subject_code" class="form-label">ລະຫັດວິຊາ *</label>
                            <input type="text" class="form-control" id="subject_code" name="subject_code" required placeholder="ເຊັ່ນ: CS101">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subject_name" class="form-label">ຊື່ວິຊາ *</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" required placeholder="ເຊັ່ນ: Introduction to Computer Science">
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label for="credit" class="form-label">ໜ່ວຍກິດ *</label>
                            <input type="number" class="form-control" id="credit" name="credit" min="1" max="6" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_subjects.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_subject" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກລາຍວິຊາ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ฟอร์มแก้ไขรายวิชา -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit text-warning"></i> ແກ້ໄຂລາຍວິຊາ</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="subject_code" class="form-label">ລະຫັດວິຊາ *</label>
                            <input type="text" class="form-control bg-light" id="subject_code" name="subject_code" value="<?php echo $edit_data['subject_code']; ?>" readonly>
                            <small class="text-muted">ບໍ່ສາມາດແກ້ໄຂລະຫັດວິຊາໄດ້</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subject_name" class="form-label">ຊື່ວິຊາ *</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" value="<?php echo $edit_data['subject_name']; ?>" required>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label for="credit" class="form-label">ໜ່ວຍກິດ *</label>
                            <input type="number" class="form-control" id="credit" name="credit" min="1" max="6" value="<?php echo $edit_data['credit']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_subjects.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_subject" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກການແກ້ໄຂ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'import'): ?>
        <!-- ฟอร์มนำเข้า CSV -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-import text-success"></i> ນຳເຂົ້າລາຍວິຊາຈາກ CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="dropzone" id="csv-dropzone" onclick="document.getElementById('csv_file').click()">
                                <i class="fas fa-file-csv"></i>
                                <h5>ຄລິກຫຼືລາກໄຟລ໌ CSV ມາວາງໃສ່ນີ້</h5>
                                <p class="mb-0">ຮອງຮັບສະເພາະໄຟລ໌ .csv ເທົ່ານັ້ນ</p>
                            </div>
                            
                            <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv" onchange="updateFileInfo(this)">
                            
                            <div id="file-info" class="alert alert-info d-none">
                                <i class="fas fa-file-alt me-2"></i> <span id="file-name"></span>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="import_csv" class="btn btn-success">
                                    <i class="fas fa-file-import"></i> ນຳເຂົ້າລາຍວິຊາ
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3">
                            <a href="admin_subjects.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍການ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> ຮູບແບບໄຟລ໌ CSV</h5>
                    </div>
                    <div class="card-body">
                        <p>ໄຟລ໌ CSV ຕ້ອງມີຖັນດັ່ງຕໍ່ໄປນີ້:</p>
                        <ol>
                            <li><strong>subject_code</strong> - ລະຫັດວິຊາ</li>
                            <li><strong>subject_name</strong> - ຊື່ວິຊາ</li>
                            <li><strong>credit</strong> - ໜ່ວຍກິດ</li>
                        </ol>
                        
                        <div class="csv-format">
                            <h6>ຕົວຢ່າງໄຟລ໌ CSV:</h6>
                            <pre>subject_code,subject_name,credit
CS101,Introduction to Computer Science,3
MATH101,Calculus I,4
ENG101,English Communication,2
PHY101,Physics I,3</pre>
                        </div>
                        
                        <a href="#" class="btn btn-sm btn-outline-primary" id="download-sample">
                            <i class="fas fa-download"></i> ດາວໂຫລດໄຟລ໌ຕົວຢ່າງ
                        </a>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>ໝາຍເຫດ:</strong> ຖ້າລະຫັດວິຊາມີໃນລະບົບແລ້ວ ລະບົບຈະອັບເດດຂໍ້ມູນແທນການເພີ່ມໃໝ່
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- แสดงรายการรายวิชา -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list text-primary"></i> ລາຍການວິຊາທັງໝົດ</h5>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="searchInput" class="form-control" placeholder="ຄົ້ນຫາລາຍວິຊາ...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($subjects)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> ຍັງບໍ່ມີລາຍວິຊາໃນລະບົບ
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ຊື່ວິຊາ</th>
                                <th>ໜ່ວຍກິດ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><strong><?php echo $subject['subject_code']; ?></strong></td>
                                <td><?php echo $subject['subject_name']; ?></td>
                                <td><?php echo $subject['credit']; ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="admin_subjects.php?action=edit&id=<?php echo $subject['subject_code']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> ແກ້ໄຂ
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo str_replace(' ', '_', $subject['subject_code']); ?>">
                                            <i class="fas fa-trash-alt"></i> ລຶບ
                                        </button>
                                    </div>
                                    
                                    <!-- Modal ยืนยันการลบ -->
                                    <div class="modal fade" id="deleteModal<?php echo str_replace(' ', '_', $subject['subject_code']); ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ຢືນຢັນການລຶບລາຍວິຊາ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-3">
                                                        <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                                                        <p>ທ່ານຕ້ອງການລຶບລາຍວິຊາ "<strong><?php echo $subject['subject_name']; ?></strong>" ລະຫັດ <strong><?php echo $subject['subject_code']; ?></strong> ແທ້ບໍ່?</p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="subject_code" value="<?php echo $subject['subject_code']; ?>">
                                                        <button type="submit" name="delete_subject" class="btn btn-danger">
                                                            <i class="fas fa-trash-alt me-1"></i> ຢືນຢັນການລຶບ
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <p class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        ທັງໝົດ <?php echo count($subjects); ?> ລາຍວິຊາ
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // แสดงข้อมูลไฟล์ที่เลือก
        function updateFileInfo(input) {
            const fileInfo = document.getElementById('file-info');
            const fileName = document.getElementById('file-name');
            
            if (input.files.length > 0) {
                fileName.textContent = input.files[0].name;
                fileInfo.classList.remove('d-none');
                document.getElementById('csv-dropzone').style.borderColor = '#28a745';
            } else {
                fileInfo.classList.add('d-none');
            }
        }
        
        // สร้างและดาวน์โหลดไฟล์ CSV ตัวอย่าง
        document.getElementById('download-sample')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            // สร้างข้อมูล CSV
            let csvContent = "subject_code,subject_name,credit\n";
            csvContent += "CS101,Introduction to Computer Science,3\n";
            csvContent += "MATH101,Calculus I,4\n";
            csvContent += "ENG101,English Communication,2\n";
            csvContent += "PHY101,Physics I,3\n";
            csvContent += "CS201,Data Structures and Algorithms,3\n";
            csvContent += "MATH201,Calculus II,4\n";
            csvContent += "ENG201,Technical Writing,2\n";
            csvContent += "CS301,Database Systems,3\n";
            
            // สร้าง Blob และสร้าง URL
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            
            // สร้างลิงก์ดาวน์โหลดและคลิก
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'subjects_sample.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // รองรับการลากไฟล์มาวาง (drag and drop)
        const dropzone = document.getElementById('csv-dropzone');
        
        if (dropzone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropzone.style.backgroundColor = '#f1f8fe';
                dropzone.style.borderColor = '#28a745';
            }
            
            function unhighlight() {
                dropzone.style.backgroundColor = '';
                dropzone.style.borderColor = '#3498db';
            }
            
            dropzone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                const fileInput = document.getElementById('csv_file');
                
                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileInfo(fileInput);
                }
            }
        }
        
        // ค้นหาในตาราง
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.querySelector('table');
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
        
        // Toggle sidebar in responsive mode
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            
            if (toggler && sidebar) {
                toggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Resize handler
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar?.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>