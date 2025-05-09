<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ตัวแปรสำหรับข้อความแจ้งเตือน
$message = '';
$error = '';
$preview_data = [];
$has_preview = false;

// ฟังก์ชันสำหรับตรวจสอบว่านักศึกษามีอยู่ในระบบหรือไม่
function checkStudentExists($conn, $student_id) {
    $query = "SELECT * FROM students WHERE student_id = '$student_id'";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// ฟังก์ชันสำหรับตรวจสอบว่ารายวิชามีอยู่ในระบบหรือไม่
function checkSubjectExists($conn, $subject_code) {
    $query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// ฟังก์ชันสำหรับตรวจสอบว่าผลการเรียนซ้ำหรือไม่
function checkGradeExists($conn, $student_id, $subject_code, $study_year, $semester) {
    $query = "SELECT * FROM grades 
              WHERE student_id = '$student_id' 
              AND subject_code = '$subject_code' 
              AND study_year = $study_year 
              AND semester = $semester";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// ฟังก์ชันสำหรับเพิ่มหรืออัพเดทผลการเรียน
function addOrUpdateGrade($conn, $student_id, $subject_code, $study_year, $semester, $grade) {
    // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
    if (checkGradeExists($conn, $student_id, $subject_code, $study_year, $semester)) {
        // อัพเดทผลการเรียน
        $query = "UPDATE grades 
                 SET grade = '$grade' 
                 WHERE student_id = '$student_id' 
                 AND subject_code = '$subject_code' 
                 AND study_year = $study_year 
                 AND semester = $semester";
        return $conn->query($query);
    } else {
        // เพิ่มผลการเรียนใหม่
        $query = "INSERT INTO grades (student_id, subject_code, study_year, semester, grade) 
                 VALUES ('$student_id', '$subject_code', $study_year, $semester, '$grade')";
        return $conn->query($query);
    }
}

// ตรวจสอบการอัพโหลดไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excel_file']) && !empty($_FILES['excel_file']['name'])) {
        // ตรวจสอบนามสกุลไฟล์
        $file_extension = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
        
        if ($file_extension == 'csv') {
            // สำหรับไฟล์ CSV
            $file_tmp = $_FILES['excel_file']['tmp_name'];
            
            // เปิดไฟล์ CSV
            $handle = fopen($file_tmp, "r");
            
            // ตัวแปรสำหรับเก็บข้อมูล
            $row_count = 0;
            $success_count = 0;
            $error_count = 0;
            $preview_data = [];
            
            // อ่านข้อมูลแถวแรก (หัวตาราง)
            $header = fgetcsv($handle);
            
            // ตรวจสอบหัวตาราง
            $required_headers = ['student_id', 'subject_code', 'study_year', 'semester', 'grade'];
            $is_valid_header = true;
            
            foreach ($required_headers as $required) {
                if (!in_array($required, $header)) {
                    $is_valid_header = false;
                    break;
                }
            }
            
            if (!$is_valid_header) {
                $error = "ໄຟລ໌ CSV ຕ້ອງມີຄອລັມນ໌ດັ່ງນີ້: " . implode(", ", $required_headers);
            } else {
                // ดึงตำแหน่งคอลัมน์
                $student_id_index = array_search('student_id', $header);
                $subject_code_index = array_search('subject_code', $header);
                $study_year_index = array_search('study_year', $header);
                $semester_index = array_search('semester', $header);
                $grade_index = array_search('grade', $header);
                
                // ตรวจสอบว่าต้องการนำเข้าข้อมูลหรือเพียงแค่ดูตัวอย่าง
                $is_preview = isset($_POST['preview']) && $_POST['preview'] == 1;
                
                // อ่านข้อมูลแต่ละแถว
                while (($row = fgetcsv($handle)) !== FALSE) {
                    $row_count++;
                    
                    // ตรวจสอบว่ามีข้อมูลครบทุกคอลัมน์
                    if (count($row) >= 5) {
                        $student_id = clean($conn, $row[$student_id_index]);
                        $subject_code = clean($conn, $row[$subject_code_index]);
                        $study_year = intval($row[$study_year_index]);
                        $semester = intval($row[$semester_index]);
                        $grade = clean($conn, $row[$grade_index]);
                        
                        // ตรวจสอบความถูกต้องของข้อมูล
                        $is_valid = true;
                        $error_message = '';
                        
                        if (!checkStudentExists($conn, $student_id)) {
                            $is_valid = false;
                            $error_message = "ບໍ່ພົບລະຫັດນັກສຶກສາ";
                        } elseif (!checkSubjectExists($conn, $subject_code)) {
                            $is_valid = false;
                            $error_message = "ບໍ່ພົບລະຫັດວິຊາ";
                        } elseif ($study_year <= 0 || $study_year > 3) {
                            $is_valid = false;
                            $error_message = "ປີການສຶກສາຕ້ອງເປັນ 1, 2 ຫຼື 3 ເທົ່ານັ້ນ";
                        } elseif ($semester <= 0 || $semester > 3) {
                            $is_valid = false;
                            $error_message = "ພາກຮຽນຕ້ອງເປັນ 1, 2 ຫຼື 3 ເທົ່ານັ້ນ";
                        } elseif (!in_array($grade, ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F', 'W', 'I'])) {
                            $is_valid = false;
                            $error_message = "ເກຣດບໍ່ຖືກຕ້ອງ";
                        }
                        
                        // เพิ่มข้อมูลในตัวอย่าง
                        $preview_row = [
                            'student_id' => $student_id,
                            'subject_code' => $subject_code,
                            'study_year' => $study_year,
                            'semester' => $semester,
                            'grade' => $grade,
                            'is_valid' => $is_valid,
                            'error_message' => $error_message
                        ];
                        
                        $preview_data[] = $preview_row;
                        
                        // ถ้าไม่ใช่การดูตัวอย่างและข้อมูลถูกต้อง ให้นำเข้าข้อมูล
                        if (!$is_preview && $is_valid) {
                            if (addOrUpdateGrade($conn, $student_id, $subject_code, $study_year, $semester, $grade)) {
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        }
                    }
                }
                
                // ปิดไฟล์
                fclose($handle);
                
                // แสดงข้อความผลลัพธ์
                if ($is_preview) {
                    $has_preview = true;
                } else {
                    $message = "ນຳເຂົ້າຂໍ້ມູນຮຽບຮ້ອຍ: $success_count ລາຍການ, ຜິດພາດ: $error_count ລາຍການ, ຈາກທັງໝົດ: $row_count ລາຍການ";
                }
            }
        } elseif ($file_extension == 'xlsx' || $file_extension == 'xls') {
            $error = "ລະບົບຮອງຮັບສະເພາະໄຟລ໌ CSV ເທົ່ານັ້ນ ກະລຸນາບັນທຶກໄຟລ໌ Excel ເປັນຮູບແບບ CSV ກ່ອນອັບໂຫຼດ";
        } else {
            $error = "ໄຟລ໌ບໍ່ຖືກຕ້ອງ ກະລຸນາອັບໂຫຼດໄຟລ໌ CSV";
        }
    } else {
        $error = "ກະລຸນາເລືອກໄຟລ໌";
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ນຳເຂົ້າຂໍ້ມູນຜົນການຮຽນຈາກ Excel - Polytechnic College</title>
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
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            padding: 20px;
            background-color: #343a40;
            color: white;
            z-index: 100;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: #007bff;
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
        }
        
        .table-responsive {
            margin-top: 20px;
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
            background-color: #007bff;
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
        
        .import-steps {
            counter-reset: step-counter;
            margin-bottom: 20px;
        }
        
        .import-step {
            position: relative;
            padding-left: 40px;
            margin-bottom: 15px;
        }
        
        .import-step:before {
            content: counter(step-counter);
            counter-increment: step-counter;
            position: absolute;
            left: 0;
            top: 0;
            width: 30px;
            height: 30px;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .template-link {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .preview-table td.invalid {
            background-color: #ffe6e6;
        }
        
        .preview-table td.valid {
            background-color: #e6ffe6;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                Polytechnic College - ລະບົບຜູ້ດູແລ
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
                <div class="user-role"><?php echo ($_SESSION['role'] === 'admin') ? 'ຜູ້ດູແລລະບົບ' : 'ອາຈານ'; ?></div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> ໜ້າຫຼັກ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_students.php">
                    <i class="fas fa-user-graduate"></i> ຈັດການນັກສຶກສາ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="admin_grades.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-file-import"></i> ນຳເຂົ້າຂໍ້ມູນຜົນການຮຽນຈາກ Excel</h2>
            <a href="admin_grades.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> ກັບໄປຍັງໜ້າຈັດການຜົນການຮຽນ
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
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> ຄຳແນະນຳການນຳເຂົ້າຂໍ້ມູນ</h5>
            </div>
            <div class="card-body">
                <div class="import-steps">
                    <div class="import-step">
                        <h6>ກຽມຂໍ້ມູນໃນ Excel</h6>
                        <p>ສ້າງຂໍ້ມູນຜົນການຮຽນຂອງນັກສຶກສາໃນ Excel ໂດຍມີຄອລັມນ໌: student_id, subject_code, study_year, semester, grade</p>
                        <p><strong>study_year</strong>: ໃສ່ປີທີ່ຮຽນ (1, 2, 3) ບໍ່ແມ່ນປີການສຶກສາ</p>
                    </div>
                    <div class="import-step">
                        <h6>ບັນທຶກເປັນ CSV</h6>
                        <p>ບັນທຶກໄຟລ໌ Excel ເປັນຮູບແບບ CSV (Comma delimited) ເພື່ອນຳເຂົ້າຂໍ້ມູນ</p>
                    </div>
                    <div class="import-step">
                        <h6>ອັບໂຫຼດແລະກວດສອບ</h6>
                        <p>ອັບໂຫຼດໄຟລ໌ CSV ແລະກົດປຸ່ມ "ດູຕົວຢ່າງ" ເພື່ອກວດສອບຂໍ້ມູນກ່ອນການນຳເຂົ້າ</p>
                    </div>
                    <div class="import-step">
                        <h6>ນຳເຂົ້າຂໍ້ມູນ</h6>
                        <p>ຖ້າຂໍ້ມູນຖືກຕ້ອງທັງໝົດ ໃຫ້ກົດປຸ່ມ "ນຳເຂົ້າຂໍ້ມູນ" ເພື່ອນຳເຂົ້າຂໍ້ມູນທັງໝົດ</p>
                    </div>
                </div>
                
                <div class="template-link">
                    <a href="templates/grades_template.csv" download class="btn btn-outline-primary">
                        <i class="fas fa-download"></i> ດາວໂຫຼດແບບຟອມ CSV
                    </a>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">ເລືອກໄຟລ໌ CSV</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".csv">
                        <small class="text-muted">* ຮອງຮັບສະເພາະໄຟລ໌ CSV ເທົ່ານັ້ນ</small>
                    </div>
                    
                    <div class="d-flex">
                        <button type="submit" name="preview" value="1" class="btn btn-secondary me-2">
                            <i class="fas fa-eye"></i> ດູຕົວຢ່າງ
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-import"></i> ນຳເຂົ້າຂໍ້ມູນ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($has_preview && !empty($preview_data)): ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> ຕົວຢ່າງຂໍ້ມູນທີ່ຈະນຳເຂົ້າ</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> ກວດສອບຂໍ້ມູນຂ້າງລຸ່ມນີ້ ຖ້າຖືກຕ້ອງແລ້ວ ໃຫ້ກົດປຸ່ມ "ນຳເຂົ້າຂໍ້ມູນ" ດ້ານເທິງ
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover preview-table">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ປີທີ່ຮຽນ</th>
                                <th>ພາກຮຽນ</th>
                                <th>ເກຣດ</th>
                                <th>ສະຖານະ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $row): ?>
                            <tr>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>"><?php echo $row['student_id']; ?></td>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>"><?php echo $row['subject_code']; ?></td>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>">ປີ <?php echo $row['study_year']; ?></td>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>"><?php echo $row['semester']; ?></td>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>"><?php echo $row['grade']; ?></td>
                                <td class="<?php echo $row['is_valid'] ? 'valid' : 'invalid'; ?>">
                                    <?php if ($row['is_valid']): ?>
                                        <span class="text-success">ຖືກຕ້ອງ</span>
                                    <?php else: ?>
                                        <span class="text-danger"><?php echo $row['error_message']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันสำหรับสร้างไฟล์ตัวอย่าง CSV
        function generateTemplateFile() {
            const header = "student_id,subject_code,study_year,semester,grade\n";
            const rows = [
                "IT10001,CS101,1,1,A",
                "IT10001,MATH101,1,1,B+",
                "IT10002,CS101,1,1,B",
                "IT10002,MATH101,1,1,A"
            ];
            
            const content = header + rows.join('\n');
            const blob = new Blob([content], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'grades_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // สร้างโฟลเดอร์สำหรับเก็บเทมเพลต (ถ้ายังไม่มี)
        document.addEventListener('DOMContentLoaded', function() {
            // แทนที่การใช้ลิงก์ดาวน์โหลดปกติ
            document.querySelector('.template-link a').addEventListener('click', function(e) {
                e.preventDefault();
                generateTemplateFile();
            });
        });
    </script>
</body>
</html>