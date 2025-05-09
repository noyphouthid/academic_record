<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config.php';

// ดึงข้อมูลภาควิชาและสาขาวิชาจากฐานข้อมูล
$departments_query = "SELECT DISTINCT department FROM majors ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
if ($departments_result->num_rows > 0) {
    while ($row = $departments_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// ดึงข้อมูลสาขาวิชาทั้งหมด
$majors_query = "SELECT * FROM majors ORDER BY department, major_name";
$majors_result = $conn->query($majors_query);
$majors = [];
$majors_by_department = [];
if ($majors_result->num_rows > 0) {
    while ($row = $majors_result->fetch_assoc()) {
        $majors[] = $row;
        $majors_by_department[$row['department']][] = $row;
    }
}

// ตรวจสอบการส่งฟอร์ม
$error_message = '';
$show_results = false;
$student_data = null;
$grades_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_id']) && isset($_POST['major_id'])) {
        $student_id = clean($conn, $_POST['student_id']);
        $major_id = clean($conn, $_POST['major_id']);
        
        // ตรวจสอบว่ามีนักศึกษาที่มีรหัสและสาขาตรงกันหรือไม่
        $student_query = "SELECT s.*, m.major_name, m.department 
                         FROM students s 
                         JOIN majors m ON s.major_id = m.major_id 
                         WHERE s.student_id = '$student_id' 
                         AND s.major_id = $major_id";
        $student_result = $conn->query($student_query);
        
        if ($student_result->num_rows > 0) {
            $show_results = true;
            $student_data = $student_result->fetch_assoc();
            
            // ดึงข้อมูลผลการเรียนของนักศึกษา
            // เปลี่ยนจาก academic_year เป็น study_year (หากคุณได้เปลี่ยนชื่อคอลัมน์ในฐานข้อมูลแล้ว)
            // ถ้ายังไม่ได้เปลี่ยนชื่อคอลัมน์ ให้ใช้ academic_year แทน study_year
            $grades_query = "SELECT g.*, s.subject_name, s.credit 
                            FROM grades g 
                            JOIN subjects s ON g.subject_code = s.subject_code 
                            WHERE g.student_id = '$student_id' 
                            ORDER BY g.study_year, g.semester";
            $grades_result = $conn->query($grades_query);
            
            if ($grades_result->num_rows > 0) {
                // จัดกลุ่มข้อมูลตามปีการศึกษาและภาคเรียน
                while ($row = $grades_result->fetch_assoc()) {
                    $year_semester = "ປີ " . $row['study_year'] . " - ພາກຮຽນທີ " . $row['semester'];
                    $grades_data[$year_semester][] = $row;
                }
            }
        } else {
            $error_message = "ບໍ່ພົບຂໍ້ມູນນັກສຶກສາ ກະລຸນາກວດສອບລະຫັດນັກສຶກສາ ແລະ ສາຂາວິຊາອີກຄັ້ງ";
        }
    } else {
        $error_message = "ກະລຸນາປ້ອນຂໍ້ມູນໃຫ້ຄົບຖ້ວນ";
    }
}

// คำนวณเกรดเฉลี่ย (GPA)
function calculateGPA($grades) {
    $total_credit = 0;
    $total_point = 0;
    
    foreach ($grades as $semester_grades) {
        foreach ($semester_grades as $grade) {
            $credit = $grade['credit'];
            $grade_point = gradeToPoint($grade['grade']);
            
            $total_credit += $credit;
            $total_point += ($credit * $grade_point);
        }
    }
    
    if ($total_credit > 0) {
        return number_format($total_point / $total_credit, 2);
    } else {
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ເວັບໄຊທ໌ສະແດງຜົນການຮຽນ - Polytechnic College</title>
    <!-- นำเข้าฟอนต์ Noto Sans Lao จาก Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <style>
        /* กำหนดฟอนต์ Noto Sans Lao เป็นฟอนต์หลักสำหรับทุกองค์ประกอบ */
        * {
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        .header {
            text-align: center;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        
        .logo {
            max-width: 120px;
            margin-bottom: 10px;
        }
        
        .container {
            max-width: 900px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .footer {
            background-color: #e3f2fd;
            padding: 15px 0;
            text-align: center;
            font-size: 14px;
            margin-top: 30px;
        }
        
        table th {
            background-color: #f1f8ff;
        }
        
        .gpa-summary {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 20px;
        }
        
        /* เพิ่มเติมเพื่อให้ตัวอักษรลาวแสดงผลสวยงาม */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Noto Sans Lao', sans-serif;
            font-weight: 600;
        }
        
        p, div, span, label, button, input, select, option {
            font-family: 'Noto Sans Lao', sans-serif;
            font-weight: 400;
        }
        
        .btn {
            font-family: 'Noto Sans Lao', sans-serif;
            font-weight: 500;
        }
        
        /* ปรับแต่งปุ่มให้ชัดเจน */
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
            padding: 8px 16px;
            font-size: 16px;
            border-radius: 4px;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        /* ปรับแต่ง select ให้สวยงาม */
        .form-select {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        
        .form-select option {
            padding: 10px;
        }
        
        /* สไตล์สำหรับกลุ่มสาขาวิชา */
        .department-group {
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .major-option {
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="assets/images/logo.png" alt="College Logo" class="logo">
            <h1>Polytechnic College</h1>
            <h2>ເວັບໄຊທ໌ສະແດງຜົນຄະແນນ</h2>
        </div>
        
        <?php if (!$show_results): ?>
        <div class="card">
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">ລະຫັດນັກສຶກສາ</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" placeholder="ເຊັ່ນ: CSIT10001" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="major_id" class="form-label">ສາຂາວິຊາ</label>
                        <select class="form-select" id="major_id" name="major_id" required>
                            <option value="">ກະລຸນາເລືອກສາຂາວິຊາ</option>
                            <?php foreach ($majors_by_department as $department => $department_majors): ?>
                            <optgroup label="<?php echo $department; ?>">
                                <?php foreach ($department_majors as $major): ?>
                                <option value="<?php echo $major['major_id']; ?>"><?php echo $major['major_name']; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <input type="submit" class="btn btn-primary" value="ກວດສອບ">
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>ຂໍ້ມູນນັກສຶກສາ</h3>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">ກັບຄືນຫນ້າຄົ້ນຫາ</a>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ລະຫັດນັກສຶກສາ:</strong> <?php echo $student_data['student_id']; ?></p>
                        <p><strong>ຊື່-ນາມສະກຸນ:</strong> <?php echo $student_data['firstname'] . ' ' . $student_data['lastname']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ພາກວິຊາ:</strong> <?php echo $student_data['department']; ?></p>
                        <p><strong>ສາຂາວິຊາ:</strong> <?php echo $student_data['major_name']; ?></p>
                        <p><strong>ປີທີ່ເຂົ້າສຶກສາ:</strong> <?php echo $student_data['enrollment_year']; ?></p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4>ຜົນການຮຽນ</h4>
                    
                    <?php if (empty($grades_data)): ?>
                    <div class="alert alert-info">ຍັງບໍ່ມີຂໍ້ມູນຜົນການຮຽນ</div>
                    <?php else: ?>
                        <!-- ข้อความแจ้งเตือน -->
                        <div class="alert alert-info">This is invalid without diploma</div>
                        
                        <?php foreach ($grades_data as $year_semester => $grades): ?>
                        <h5 class="mt-3"><?php echo $year_semester; ?></h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ລະຫັດວິຊາ</th>
                                        <th>ຊື່ວິຊາ</th>
                                        <th>ຫນ່ວຍກິດ</th>
                                        <th>ເກຣດ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td><?php echo $grade['subject_code']; ?></td>
                                        <td><?php echo $grade['subject_name']; ?></td>
                                        <td><?php echo $grade['credit']; ?></td>
                                        <td><?php echo $grade['grade']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="gpa-summary">
                            <h5>ຜົນການຮຽນລວມ</h5>
                            <p><strong>ເກຣດສະເລ່ຍສະສົມ (GPA):</strong> <?php echo calculateGPA($grades_data); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Polytechnic College</p>
            <p>ເວັບໄຊທ໌ສະແດງຜົນຄະແນນ (Score display website) Version 1.0</p>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>