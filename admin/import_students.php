<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ดึงข้อมูลสาขาวิชา
$major_query = "SELECT * FROM majors ORDER BY major_name";
$major_result = $conn->query($major_query);
$majors = [];
if ($major_result && $major_result->num_rows > 0) {
    while ($row = $major_result->fetch_assoc()) {
        $majors[] = $row;
    }
}

// ฟังก์ชันสำหรับนำเข้าข้อมูลนักศึกษาจาก CSV
function importStudentsFromCSV($conn, $file, $has_header = true) {
    $results = [
        'success' => 0,
        'error' => 0,
        'errors' => [],
        'total' => 0
    ];
    
    // อ่านไฟล์ CSV
    $handle = fopen($file, "r");
    if ($handle) {
        // ข้ามบรรทัดแรก (header) ถ้ามี
        if ($has_header) {
            fgetcsv($handle, 1000, ",");
        }
        
        // อ่านข้อมูลทีละบรรทัด
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $results['total']++;
            
            // ตรวจสอบจำนวนคอลัมน์
            if (count($data) < 4) {
                $results['error']++;
                $results['errors'][] = "ແຖວທີ {$results['total']}: ຈຳນວນຖັນບໍ່ຖືກຕ້ອງ";
                continue;
            }
            
            // ดึงข้อมูลจากแต่ละคอลัมน์
            $student_id = clean($conn, trim($data[0]));
            $firstname = clean($conn, trim($data[1]));
            $lastname = clean($conn, trim($data[2]));
            $major_id = clean($conn, trim($data[3]));
            $enrollment_year = isset($data[4]) ? clean($conn, trim($data[4])) : date('Y');
            $status = isset($data[5]) ? clean($conn, trim($data[5])) : 'studying';
            
            // ตรวจสอบว่ามีข้อมูลครบถ้วนหรือไม่
            if (empty($student_id) || empty($firstname) || empty($lastname) || empty($major_id)) {
                $results['error']++;
                $results['errors'][] = "ແຖວທີ {$results['total']}: ຂໍ້ມູນບໍ່ຄົບຖ້ວນ";
                continue;
            }
            
            // ตรวจสอบว่า major_id มีอยู่จริงหรือไม่
            $major_check = "SELECT * FROM majors WHERE major_id = '$major_id'";
            $major_result = $conn->query($major_check);
            if (!$major_result || $major_result->num_rows == 0) {
                $results['error']++;
                $results['errors'][] = "ແຖວທີ {$results['total']}: ບໍ່ພົບສາຂາວິຊາ ID: $major_id";
                continue;
            }
            
            // ตรวจสอบว่านักศึกษามีรหัสซ้ำหรือไม่
            $check_query = "SELECT * FROM students WHERE student_id = '$student_id'";
            $check_result = $conn->query($check_query);
            if ($check_result && $check_result->num_rows > 0) {
                // ถ้ามีข้อมูลแล้ว ให้อัพเดทแทน
                $update_query = "UPDATE students SET 
                                firstname = '$firstname', 
                                lastname = '$lastname', 
                                major_id = '$major_id', 
                                enrollment_year = '$enrollment_year', 
                                status = '$status' 
                                WHERE student_id = '$student_id'";
                
                if ($conn->query($update_query) === TRUE) {
                    $results['success']++;
                } else {
                    $results['error']++;
                    $results['errors'][] = "ແຖວທີ {$results['total']}: ບໍ່ສາມາດອັບເດດຂໍ້ມູນຂອງນັກສຶກສາລະຫັດ $student_id ໄດ້ - " . $conn->error;
                }
            } else {
                // ถ้ายังไม่มีข้อมูล ให้เพิ่มใหม่
                $insert_query = "INSERT INTO students (student_id, firstname, lastname, major_id, enrollment_year, status) 
                               VALUES ('$student_id', '$firstname', '$lastname', '$major_id', '$enrollment_year', '$status')";
                
                if ($conn->query($insert_query) === TRUE) {
                    $results['success']++;
                } else {
                    $results['error']++;
                    $results['errors'][] = "ແຖວທີ {$results['total']}: ບໍ່ສາມາດເພີ່ມຂໍ້ມູນນັກສຶກສາລະຫັດ $student_id ໄດ້ - " . $conn->error;
                }
            }
        }
        fclose($handle);
    } else {
        $results['error']++;
        $results['errors'][] = "ບໍ່ສາມາດເປີດໄຟລ໌ໄດ້";
    }
    
    return $results;
}

// จัดการการส่งฟอร์ม
$message = '';
$error = '';
$import_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        $has_header = isset($_POST['has_header']) ? true : false;
        
        // ตรวจสอบไฟล์
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $file['tmp_name'];
            $file_name = $file['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // ตรวจสอบนามสกุลไฟล์
            if ($file_ext === 'csv') {
                // นำเข้าข้อมูล
                $import_results = importStudentsFromCSV($conn, $file_tmp, $has_header);
                
                if ($import_results['success'] > 0) {
                    $message = "ນຳເຂົ້າຂໍ້ມູນນັກສຶກສາສຳເລັດແລ້ວ {$import_results['success']} ລາຍການ";
                    if ($import_results['error'] > 0) {
                        $message .= " (ມີຂໍ້ຜິດພາດ {$import_results['error']} ລາຍການ)";
                    }
                } else {
                    $error = "ເກີດຂໍ້ຜິດພາດໃນການນຳເຂົ້າຂໍ້ມູນ";
                }
            } else {
                $error = "ກະລຸນາອັບໂຫລດໄຟລ໌ CSV ເທົ່ານັ້ນ";
            }
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການອັບໂຫລດໄຟລ໌: " . $file['error'];
        }
    }
}

// ตัวอย่างข้อมูล CSV
$sample_data = [
    ['ລະຫັດນັກສຶກສາ', 'ຊື່', 'ນາມສະກຸນ', 'ລະຫັດສາຂາ', 'ປີທີ່ເຂົ້າຮຽນ', 'ສະຖານະ'],
    ['IT10001', 'ສົມຊາຍ', 'ໃຈດີ', '1', '2023', 'studying'],
    ['IT10002', 'ສົມຍິງ', 'ຮັກຮຽນ', '1', '2023', 'studying'],
    ['CS10001', 'ວິໄຊ', 'ເກັ່ງກ້າ', '2', '2023', 'studying']
];
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ນຳເຂົ້າຂໍ້ມູນນັກສຶກສາ - ວິທະຍາໄລເຕັກນິກ</title>
    <link rel="stylesheet" href="navbar.css">
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
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
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
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        .card-header i {
            color: #3498db;
            margin-right: 10px;
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
        }
        .sample-table {
            font-size: 14px;
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
        .dropzone-text {
            margin-bottom: 0;
        }
        .error-details {
            max-height: 200px;
            overflow-y: auto;
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
            <h2><i class="fas fa-file-import"></i> ນຳເຂົ້າຂໍ້ມູນນັກສຶກສາຈາກ CSV</h2>
            <a href="admin_students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າຈັດການນັກສຶກສາ
            </a>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($import_results && !empty($import_results['errors'])): ?>
        <div class="alert alert-warning" role="alert">
            <h5>ມີຂໍ້ຜິດພາດໃນການນຳເຂົ້າຂໍ້ມູນ <?php echo count($import_results['errors']); ?> ລາຍການ:</h5>
            <div class="error-details">
                <ul>
                    <?php foreach ($import_results['errors'] as $err): ?>
                    <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload"></i> ອັບໂຫລດໄຟລ໌ CSV</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="dropzone" id="csv-dropzone" onclick="document.getElementById('csv_file').click()">
                                <i class="fas fa-file-csv"></i>
                                <h5>ຄລິກຫຼືລາກໄຟລ໌ CSV ມາວາງໃສ່ນີ້</h5>
                                <p class="dropzone-text">ຮອງຮັບສະເພາະໄຟລ໌ .csv ເທົ່ານັ້ນ</p>
                            </div>
                            
                            <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv" onchange="updateFileInfo(this)">
                            
                            <div id="file-info" class="alert alert-info d-none">
                                <i class="fas fa-file-alt me-2"></i> <span id="file-name"></span>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="has_header" id="has_header" checked>
                                <label class="form-check-label" for="has_header">
                                    ໄຟລ໌ CSV ມີແຖວຫົວຕາຕະລາງ (header)
                                </label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="import_csv" class="btn btn-primary">
                                    <i class="fas fa-file-import"></i> ນຳເຂົ້າຂໍ້ມູນ
                                </button>
                            </div>
                        </form>
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
                            <li><strong>ລະຫັດນັກສຶກສາ</strong> (ຈຳເປັນ)</li>
                            <li><strong>ຊື່</strong> (ຈຳເປັນ)</li>
                            <li><strong>ນາມສະກຸນ</strong> (ຈຳເປັນ)</li>
                            <li><strong>ລະຫັດສາຂາ</strong> (ຈຳເປັນ) - ເບິ່ງລະຫັດສາຂາດ້ານລຸ່ມ</li>
                            <li><strong>ປີທີ່ເຂົ້າຮຽນ</strong> (ຖ້າບໍ່ລະບຸ ຈະໃຊ້ປີປັດຈຸບັນ)</li>
                            <li><strong>ສະຖານະ</strong> (ຖ້າບໍ່ລະບຸ ຈະໃຊ້ຄ່າ 'studying')</li>
                        </ol>
                        
                        <h6 class="mt-3">ລະຫັດສາຂາທີ່ມີໃນລະບົບ:</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($majors as $major): ?>
                            <li class="list-group-item"><?php echo $major['major_id']; ?> - <?php echo $major['major_name']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <h6 class="mt-3">ຕົວຢ່າງຂໍ້ມູນ:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm sample-table">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach ($sample_data[0] as $header): ?>
                                        <th><?php echo $header; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i < count($sample_data); $i++): ?>
                                    <tr>
                                        <?php foreach ($sample_data[$i] as $cell): ?>
                                        <td><?php echo $cell; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <a href="#" class="btn btn-sm btn-outline-primary mt-3" id="download-sample">
                            <i class="fas fa-download"></i> ດາວໂຫລດໄຟລ໌ຕົວຢ່າງ
                        </a>
                    </div>
                </div>
            </div>
        </div>
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
                document.getElementById('csv-dropzone').style.borderColor = '#3498db';
            } else {
                fileInfo.classList.add('d-none');
            }
        }
        
        // สร้างและดาวน์โหลดไฟล์ CSV ตัวอย่าง
        document.getElementById('download-sample').addEventListener('click', function(e) {
            e.preventDefault();
            
            // สร้างข้อมูล CSV
            let csvContent = "ລະຫັດນັກສຶກສາ,ຊື່,ນາມສະກຸນ,ລະຫັດສາຂາ,ປີທີ່ເຂົ້າຮຽນ,ສະຖານະ\n";
            csvContent += "IT10001,ສົມຊາຍ,ໃຈດີ,1,2023,studying\n";
            csvContent += "IT10002,ສົມຍິງ,ຮັກຮຽນ,1,2023,studying\n";
            csvContent += "CS10001,ວິໄຊ,ເກັ່ງກ້າ,2,2023,studying\n";
            
            // สร้าง Blob และสร้าง URL
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            
          // สร้างลิงก์ดาวน์โหลดและคลิก
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'students_sample.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
        
        // รองรับการลากไฟล์มาวาง (drag and drop)
        const dropzone = document.getElementById('csv-dropzone');
        
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
            dropzone.style.borderColor = '#3498db';
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
        
        // Toggle sidebar in responsive mode
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            
            if (toggler && sidebar) {
                toggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html>