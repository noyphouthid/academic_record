<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ดึงข้อมูลนักศึกษา
$students_query = "SELECT s.student_id, s.firstname, s.lastname, m.major_name 
                   FROM students s 
                   JOIN majors m ON s.major_id = m.major_id 
                   WHERE s.status = 'studying'
                   ORDER BY s.student_id";
$students_result = $conn->query($students_query);
$students = [];
if ($students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// ดึงข้อมูลรายวิชา
$subjects_query = "SELECT * FROM subjects ORDER BY subject_code";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
if ($subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// ฟังก์ชันสำหรับการเพิ่มผลการเรียน
function addGrade($conn, $student_id, $subject_code, $study_year, $semester, $grade) {
    // ตรวจสอบว่านักศึกษาและรายวิชามีอยู่จริง
    $check_student = "SELECT * FROM students WHERE student_id = '$student_id'";
    $check_subject = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
    
    $student_exists = $conn->query($check_student)->num_rows > 0;
    $subject_exists = $conn->query($check_subject)->num_rows > 0;
    
    if (!$student_exists) {
        return "ບໍ່ພົບຂ້ໍມູນນັກສຶກສາ $student_id ໃນລະບົບ";
    }
    
    if (!$subject_exists) {
        return "ບໍ່ພົບຂໍ້ມູນລາຍວິຊາ $subject_code ໃນລະບົບ";
    }
    
    // ตรวจสอบว่ามีข้อมูลผลการเรียนนี้อยู่แล้วหรือไม่
    $check_query = "SELECT * FROM grades 
                   WHERE student_id = '$student_id' 
                   AND subject_code = '$subject_code' 
                   AND study_year = $study_year 
                   AND semester = $semester";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows > 0) {
        // ถ้ามีข้อมูลแล้ว ให้อัพเดทแทน
        $update_query = "UPDATE grades 
                        SET grade = '$grade' 
                        WHERE student_id = '$student_id' 
                        AND subject_code = '$subject_code' 
                        AND study_year = $study_year 
                        AND semester = $semester";
        
        if ($conn->query($update_query) === TRUE) {
            return "ອັບເດດຜົນການຮຽນຮຽບຮ້ອຍແລ້ວ";
        } else {
            return "ເກີດຂໍ້ຜິດພາດໃນການອັບເດດຂໍ້ມູນ: " . $conn->error;
        }
    } else {
        // ถ้ายังไม่มีข้อมูล ให้เพิ่มใหม่
        $insert_query = "INSERT INTO grades (student_id, subject_code, study_year, semester, grade) 
                        VALUES ('$student_id', '$subject_code', $study_year, $semester, '$grade')";
        
        if ($conn->query($insert_query) === TRUE) {
            return "ເພີ່ມຜົນການຮຽນຮຽບຮ້ອຍແລ້ວ";
        } else {
            return "ເກີດຂໍ້ຜິດພາດໃນການອັບເດດຂໍ້ມູນ: " . $conn->error;
        }
    }
}

// ฟังก์ชันสำหรับการแก้ไขผลการเรียน
function updateGrade($conn, $grade_id, $grade) {
    $query = "UPDATE grades SET grade = '$grade' WHERE grade_id = $grade_id";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ฟังก์ชันสำหรับการลบผลการเรียน
function deleteGrade($conn, $grade_id) {
    $query = "DELETE FROM grades WHERE grade_id = $grade_id";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ฟังก์ชันสำหรับการเพิ่มผลการเรียนแบบกลุ่ม (สำหรับรายวิชาเดียวกัน)
function bulkAddGrades($conn, $student_ids, $subject_code, $study_year, $semester, $grades) {
    $success_count = 0;
    $error_messages = [];
    
    for ($i = 0; $i < count($student_ids); $i++) {
        $student_id = $student_ids[$i];
        $grade = $grades[$i];
        
        if (!empty($student_id) && !empty($grade)) {
            $result = addGrade($conn, $student_id, $subject_code, $study_year, $semester, $grade);
            
            if (strpos($result, "ຮຽບຮ້ອຍແລ້ວ") !== false) {
                $success_count++;
            } else {
                $error_messages[] = "ນັກສຶກສາລະຫັດ $student_id: $result";
            }
        }
    }
    
    if (empty($error_messages)) {
        return "ເພີ່ມຜົນການຮຽນຮຽບຮ້ອຍ $success_count ລາຍການ";
    } else {
        return "ເພີ່ມຜົນການຮຽນຮຽບຮ້ອຍ $success_count ລາຍການ ແຕ່ມີບາງລາຍການເກີດຂໍ້ຜິດພາດ: " . implode(", ", $error_messages);
    }
}

// จัดการการส่งฟอร์ม
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$grade_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student_id = isset($_GET['student_id']) ? clean($conn, $_GET['student_id']) : '';
$bulk_mode = isset($_GET['bulk']) && $_GET['bulk'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_grade'])) {
        $student_id = clean($conn, $_POST['student_id']);
        $subject_code = clean($conn, $_POST['subject_code']);
        $study_year = clean($conn, $_POST['study_year']);
        $semester = clean($conn, $_POST['semester']);
        $grade = clean($conn, $_POST['grade']);
        
        $result = addGrade($conn, $student_id, $subject_code, $study_year, $semester, $grade);
        
        if (strpos($result, "ເກິດຂໍ້ຜິດພາດ") !== false) {
            $error = $result;
        } else {
            $message = $result;
        }
    } elseif (isset($_POST['bulk_add_grades'])) {
        $student_ids = $_POST['student_ids'];
        $subject_code = clean($conn, $_POST['subject_code']);
        $study_year = clean($conn, $_POST['study_year']);
        $semester = clean($conn, $_POST['semester']);
        $grades = $_POST['grades'];
        
        $result = bulkAddGrades($conn, $student_ids, $subject_code, $study_year, $semester, $grades);
        
        if (strpos($result, "ເກິດຂໍ້ຜິດພາດ") !== false) {
            $error = $result;
        } else {
            $message = $result;
        }
    } elseif (isset($_POST['edit_grade'])) {
        $grade_id = intval($_POST['grade_id']);
        $grade = clean($conn, $_POST['grade']);
        
        if (updateGrade($conn, $grade_id, $grade)) {
            $message = "ແກ້ໄຂຜົນການຮຽນແລ້ວ";
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການສະແດງຜົນຂໍ້ມູນ: " . $conn->error;
        }
    } elseif (isset($_POST['delete_grade'])) {
        $grade_id = intval($_POST['grade_id']);
        
        if (deleteGrade($conn, $grade_id)) {
            $message = "ລົບຜົນການຮຽນແລ້ວ";
            // รีไดเร็กต์กลับไปหน้ารายการหรือหน้าข้อมูลนักศึกษา
            if (!empty($_POST['return_url'])) {
                header("Location: " . $_POST['return_url'] . "&deleted=1");
                exit();
            } else {
                header("Location: admin_grades.php?deleted=1");
                exit();
            }
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການລົບຜົນການຮຽນ : " . $conn->error;
        }
    }
}

// ดึงข้อมูลผลการเรียนสำหรับการแก้ไข
$edit_data = null;
if ($action === 'edit' && $grade_id > 0) {
    $edit_query = "SELECT g.*, s.subject_name, st.firstname, st.lastname 
                  FROM grades g 
                  JOIN subjects s ON g.subject_code = s.subject_code 
                  JOIN students st ON g.student_id = st.student_id 
                  WHERE g.grade_id = $grade_id";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບຂໍໍາລົບຜົນການຮຽນທີ່ຕ້ອງການແກ້ໄຂ້";
    }
}

// ดึงข้อมูลผลการเรียนของนักศึกษา (ถ้ามีการเลือกนักศึกษา)
$student_data = null;
$student_grades = [];
if (!empty($student_id)) {
    // ดึงข้อมูลนักศึกษา
    $student_query = "SELECT s.*, m.major_name 
                     FROM students s 
                     JOIN majors m ON s.major_id = m.major_id 
                     WHERE s.student_id = '$student_id'";
    $student_result = $conn->query($student_query);
    
    if ($student_result->num_rows > 0) {
        $student_data = $student_result->fetch_assoc();
        
        // ดึงข้อมูลผลการเรียน
        $grades_query = "SELECT g.*, s.subject_name, s.credit 
                        FROM grades g 
                        JOIN subjects s ON g.subject_code = s.subject_code 
                        WHERE g.student_id = '$student_id' 
                        ORDER BY g.study_year DESC, g.semester DESC, s.subject_code";
        $grades_result = $conn->query($grades_query);
        
        if ($grades_result->num_rows > 0) {
            while ($row = $grades_result->fetch_assoc()) {
                $student_grades[] = $row;
            }
        }
    }
}

// ดึงข้อมูลผลการเรียนทั้งหมดสำหรับแสดงในตาราง (ถ้าไม่มีการเลือกนักศึกษา)
$grades = [];
if (empty($student_id) && $action !== 'add' && $action !== 'edit' && !$bulk_mode) {
    $limit = 30; // จำนวนรายการที่แสดง
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    
    // ตัวกรอง
    $filter_sql = "";
    $filter_study_year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : 0;
    $filter_semester = isset($_GET['filter_semester']) ? intval($_GET['filter_semester']) : 0;
    
    if ($filter_study_year > 0) {
        $filter_sql .= " AND g.study_year = $filter_study_year";
    }
    
    if ($filter_semester > 0) {
        $filter_sql .= " AND g.semester = $filter_semester";
    }
    
    // ดึงข้อมูลตามการกรอง
    $grades_query = "SELECT g.*, s.subject_name, s.credit, st.firstname, st.lastname 
                    FROM grades g 
                    JOIN subjects s ON g.subject_code = s.subject_code 
                    JOIN students st ON g.student_id = st.student_id 
                    WHERE 1=1 $filter_sql
                    ORDER BY g.study_year DESC, g.semester DESC, g.grade_id DESC 
                    LIMIT $offset, $limit";
    $grades_result = $conn->query($grades_query);
    
    if ($grades_result && $grades_result->num_rows > 0) {
        while ($row = $grades_result->fetch_assoc()) {
            $grades[] = $row;
        }
    }
    
    // นับจำนวนรายการทั้งหมด
    $count_query = "SELECT COUNT(*) as total FROM grades g WHERE 1=1 $filter_sql";
    $count_result = $conn->query($count_query);
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);
}

// ดึงປີການສຶກສາทั้งหมดที่มีในระบบ
$years_query = "SELECT DISTINCT study_year FROM grades ORDER BY study_year DESC";
$years_result = $conn->query($years_query);
$study_years = [];
if ($years_result && $years_result->num_rows > 0) {
    while ($row = $years_result->fetch_assoc()) {
        $study_years[] = $row['study_year'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຈັດການຜົນການຮຽນ - Polytechnic College</title>
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
 <?php include 'navbar.php'; ?>
   <!-- Main Content -->
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line"></i> ຈັດການຜົນການຮຽນ</h2>
        
        <?php if ($action !== 'add' && $action !== 'edit' && !$bulk_mode): ?>
        <div>
            <div class="btn-group me-2">
                <a href="admin_grades.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> ເພີ່ມຜົນການຮຽນແບບບຸກຄົນ
                </a>
                <a href="admin_grades.php?bulk=1" class="btn btn-success">
                    <i class="fas fa-tasks"></i> ເພີ່ມຜົນການຮຽນແບບກຸ່ມ
                </a>
            </div>
            
            <a href="import_grades.php" class="btn btn-info me-2">
                <i class="fas fa-file-import"></i> ນຳເຂົ້າຂໍ້ມູນຈາກ Excel
            </a>
            
            <?php if (!empty($student_id)): ?>
            <a href="admin_grades.php" class="btn btn-outline-secondary">
                <i class="fas fa-list"></i> ສະແດງທັງໝົດ
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
        
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ລົບຂໍໍາລົບຜົນການຮຽນແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ฟอร์มเพิ่มผลการเรียนรายบุคคล -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> ເພີ່ມຜົນການຮຽນ</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">ເລືອກນັກສຶກສາ *</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">-- ເລືອກນັກສຶກສາ --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" <?php echo (isset($_GET['student_id']) && $student['student_id'] == $_GET['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo $student['student_id'] . ' - ' . $student['firstname'] . ' ' . $student['lastname'] . ' (' . $student['major_name'] . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subject_code" class="form-label">ເລືອກວິຊາ *</label>
                            <select class="form-select" id="subject_code" name="subject_code" required>
                                <option value="">-- ເລືອກວິຊາ --</option>
                                <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_code']; ?>">
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['credit'] . ' ໜ່ວຍກິດ)'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                  <div class="row">
    <div class="col-md-4 mb-3">
        <label for="study_year" class="form-label">ປີການສຶກສາ *</label>
        <select class="form-select" id="study_year" name="study_year" required>
            <option value="1">ປີ 1</option>
            <option value="2">ປີ 2</option>
            <option value="3">ປີ 3</option>
            <option value="4">ປີ 4</option>
        </select>
    </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">ພາກຮຽນ *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="grade" class="form-label">ເກຣດ *</label>
                            <select class="form-select" id="grade" name="grade" required>
                                <option value="A">A</option>
                                <option value="B+">B+</option>
                                <option value="B">B</option>
                                <option value="C+">C+</option>
                                <option value="C">C</option>
                                <option value="D+">D+</option>
                                <option value="D">D</option>
                                <option value="F">F</option>
                                <option value="F">F</option>
                                <option value="W">W </option>
                                <option value="I">I</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo !empty($_GET['student_id']) ? 'admin_grades.php?student_id='.$_GET['student_id'] : 'admin_grades.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> ບັນທຶກ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($bulk_mode): ?>
        <!-- ฟอร์มเพิ่มผลการเรียนแบบกลุ่ม -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> ເພີ່ມຜົນການຮຽນແບບກຸ່ມ</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">ສຳລັບເພີ່ມຜົນການຮຽນວິຊາດຽວກັນ ໃຫ້ກັບນັກສຶກສາຫຼາຍຄົນພ້ອມກັນ</p>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="subject_code" class="form-label">ວິຊາ*</label>
                            <select class="form-select" id="subject_code" name="subject_code" required>
                                <option value="">-- ເລືອກວິຊາ --</option>
                                <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_code']; ?>">
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
        <label for="study_year" class="form-label">ປີການສຶກສາ *</label>
        <select class="form-select" id="study_year" name="study_year" required>
            <option value="1">ປີ 1</option>
            <option value="2">ປີ 2</option>
            <option value="3">ປີ 3</option>
            <option value="4">ປີ 4</option>
        </select>
    </div>
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">ພາກຮຽນ *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="10%">#</th>
                                    <th width="30%">ລະຫັດນັກສຶກສາ</th>
                                    <th width="45%">ຊື່ແລະນາມສະກຸນ </th>
                                    <th width="15%">ເກຣດ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td>
                                        <select class="form-select student-select" name="student_ids[]">
                                            <option value="">-- ເລືອກນັກສຶກສາ --</option>
                                            <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['student_id']; ?>" data-name="<?php echo $student['firstname'] . ' ' . $student['lastname']; ?>">
                                                <?php echo $student['student_id']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="student-name">-</span>
                                    </td>
                                    <td>
                                        <select class="form-select" name="grades[]">
                                            <option value="">--</option>
                                            <option value="A">A</option>
                                            <option value="B+">B+</option>
                                            <option value="B">B</option>
                                            <option value="C+">C+</option>
                                            <option value="C">C</option>
                                            <option value="D+">D+</option>
                                            <option value="D">D</option>
                                            <option value="F">F</option>
                                            <option value="W">W</option>
                                            <option value="I">I</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <a href="admin_grades.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ຍົກເລິກ
                        </a>
                        <button type="submit" name="bulk_add_grades" class="btn btn-success">
                            <i class="fas fa-save"></i> ບັນທຶກຜົນການຮຽນທັງໝົດ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ฟอร์มแก้ไขผลการเรียน -->
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="fas fa-edit"></i> ແກ້ໄຂຜົນການຮຽນ</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>ຂໍ້ມູນຜົນການຮຽນ</h6>
                    <p>
                        <strong>ນັກສຶກສາ:</strong> <?php echo $edit_data['firstname'] . ' ' . $edit_data['lastname']; ?> (<?php echo $edit_data['student_id']; ?>)<br>
                        <strong>ລາຍວິຊາ:</strong> <?php echo $edit_data['subject_code'] . ' - ' . $edit_data['subject_name']; ?><br>
                        <strong>ປີການສຶກສາ:</strong> <?php echo $edit_data['study_year']; ?> ພາກຮຽນທີ <?php echo $edit_data['semester']; ?>
                    </p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="grade_id" value="<?php echo $edit_data['grade_id']; ?>">
                    
                    <div class="mb-3">
                        <label for="grade" class="form-label">ເກຣດ*</label>
                        <select class="form-select" id="grade" name="grade" required>
                            <option value="A" <?php echo ($edit_data['grade'] == 'A') ? 'selected' : ''; ?>>A</option>
                            <option value="B+" <?php echo ($edit_data['grade'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B" <?php echo ($edit_data['grade'] == 'B') ? 'selected' : ''; ?>>B</option>
                            <option value="C+" <?php echo ($edit_data['grade'] == 'C+') ? 'selected' : ''; ?>>C+</option>
                            <option value="C" <?php echo ($edit_data['grade'] == 'C') ? 'selected' : ''; ?>>C</option>
                            <option value="D+" <?php echo ($edit_data['grade'] == 'D+') ? 'selected' : ''; ?>>D+</option>
                            <option value="D" <?php echo ($edit_data['grade'] == 'D') ? 'selected' : ''; ?>>D</option>
                            <option value="F" <?php echo ($edit_data['grade'] == 'F') ? 'selected' : ''; ?>>F</option>
                            <option value="W" <?php echo ($edit_data['grade'] == 'W') ? 'selected' : ''; ?>>W </option>
                            <option value="I" <?php echo ($edit_data['grade'] == 'I') ? 'selected' : ''; ?>>I </option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_grades.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> ບັນທຶກການແກ້ໄຂ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($student_data): ?>
        <!-- แสดงผลการเรียนของนักศึกษา -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-graduate"></i> ຂໍ້ມູນນັກສຶກສາ</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ລະຫັດນັກສຶກສາ:</strong> <?php echo $student_data['student_id']; ?></p>
                        <p><strong>ຊື່ ແລະ ນາມສະກຸນ:</strong> <?php echo $student_data['firstname'] . ' ' . $student_data['lastname']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ພາກວິຊາ:</strong> <?php echo $student_data['major_name']; ?></p>
                        <p><strong>ສະຖານະ:</strong> <?php echo ($student_data['status'] == 'studying') ? 'ກຳລັງສຶກສາ' : (($student_data['status'] == 'graduated') ? 'ຈົບການສຶກສາ' : 'ໝົດສິດໃນການສຶກສາ'); ?></p>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <h5>ຜົນການຮຽນ</h5>
                    <a href="admin_grades.php?action=add&student_id=<?php echo $student_data['student_id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> ເພີ່ມຜົນການຮຽນ
                    </a>
                </div>
                
                <?php if (empty($student_grades)): ?>
                <div class="alert alert-info mt-3">ຍັງບໍ່ມີຜົນກາຮຽນຂອງນັກສຶກສາ</div>
                <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ປີການສຶກສາ</th>
                                <th>ພາກຮຽນ</th>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ຊື່ວິຊາ</th>
                                <th>ໜ່ວຍກິດ</th>
                                <th>ເກຣດ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_year = '';
                            $current_semester = '';
                            $total_credit = 0;
                            $total_grade_point = 0;
                            
                            foreach ($student_grades as $grade):
                                $year_semester = $grade['study_year'] . '-' . $grade['semester'];
                                if ($year_semester != $current_year . '-' . $current_semester):
                                    $current_year = $grade['study_year'];
                                    $current_semester = $grade['semester'];
                            ?>
                            <tr class="academic-year-header">
                                <td colspan="7">ປີການສຶກສາ <?php echo $current_year; ?> ພາກຮຽນที่ <?php echo $current_semester; ?></td>
                            </tr>
                            <?php 
                                endif;
                                // คำนวณเกรดเฉลี่ย
                                if ($grade['grade'] != 'W' && $grade['grade'] != 'I') {
                                    $total_credit += $grade['credit'];
                                    $total_grade_point += ($grade['credit'] * gradeToPoint($grade['grade']));
                                }
                            ?>
                            <tr>
                                <td><?php echo $grade['study_year']; ?></td>
                                <td><?php echo $grade['semester']; ?></td>
                                <td><?php echo $grade['subject_code']; ?></td>
                                <td><?php echo $grade['subject_name']; ?></td>
                                <td><?php echo $grade['credit']; ?></td>
                                <td>
                                    <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                        <?php echo $grade['grade']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="admin_grades.php?action=edit&id=<?php echo $grade['grade_id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $grade['grade_id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Modal ยืนยันการลบ -->
                                    <div class="modal fade" id="deleteModal<?php echo $grade['grade_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ຢືນຢັນການລົບຂໍ້ມູນ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>ທ່ານຕ້ອງການລົບ <strong><?php echo $grade['subject_code'] . ' - ' . $grade['subject_name']; ?></strong> ເກຣດ <strong><?php echo $grade['grade']; ?></strong> ຫຼື ບໍ່?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['grade_id']; ?>">
                                                        <input type="hidden" name="return_url" value="admin_grades.php?student_id=<?php echo $student_data['student_id']; ?>">
                                                        <button type="submit" name="delete_grade" class="btn btn-danger">ຢືນກັນການລົບ</button>
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
                
                <?php
                    // คำนวณ GPA
                    $gpa = ($total_credit > 0) ? number_format($total_grade_point / $total_credit, 2) : 0;
                ?>
                
                <div class="alert alert-info mt-3">
                    <h6>ສະຫລູຸບຜົນການຮຽນ</h6>
                    <p>
                        <strong>ຈຳນວນວິຊາທີ່ລົງທະບຽນ:</strong> <?php echo count($student_grades); ?> ວິຊາ<br>
                        <strong>ໜ່ວຍກິດລວມ:</strong> <?php echo $total_credit; ?> ໜ່ວຍກິດ<br>
                        <strong>ເກຣດສະເລ່ຍສະສົມ (GPA):</strong> <?php echo $gpa; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- แสดงรายการผลการเรียนล่าสุด หรือ ให้เลือกนักศึกษา -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-search"></i> ຄົ້ນຫາຜົນການຮຽນ</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="admin_grades.php" class="d-flex">
                            <select class="form-select me-2" name="student_id" id="student_select">
                                <option value="">-- ເລືອກນັກສຶກ --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo $student['student_id'] . ' - ' . $student['firstname'] . ' ' . $student['lastname']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ຄົ້ນຫາ
                            </button>
                        </form>
                    </div>
                    
                 <div class="col-md-6">
    <form method="GET" action="admin_grades.php" class="filter-form d-flex justify-content-end">
        <div class="me-2">
            <select class="form-select" name="filter_year">
                <option value="0">- ປີການສຶກສາ -</option>
                <option value="1" <?php echo (isset($_GET['filter_year']) && $_GET['filter_year'] == 1) ? 'selected' : ''; ?>>ປີ 1</option>
                <option value="2" <?php echo (isset($_GET['filter_year']) && $_GET['filter_year'] == 2) ? 'selected' : ''; ?>>ປີ 2</option>
                <option value="3" <?php echo (isset($_GET['filter_year']) && $_GET['filter_year'] == 3) ? 'selected' : ''; ?>>ປີ 3</option>
                <option value="4" <?php echo (isset($_GET['filter_year']) && $_GET['filter_year'] == 4) ? 'selected' : ''; ?>>ປີ 4</option>
            </select>
        </div>
                            <div class="me-2">
                                <select class="form-select" name="filter_semester">
                                    <option value="0">- ພາກຮຽນ -</option>
                                    <option value="1" <?php echo (isset($_GET['filter_semester']) && $_GET['filter_semester'] == 1) ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo (isset($_GET['filter_semester']) && $_GET['filter_semester'] == 2) ? 'selected' : ''; ?>>2</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fas fa-filter"></i> ກອງຂໍ້ມູນ
                            </button>
                        </form>
                    </div>
                </div>
                
                <h5 class="mb-3">ລາຍການຜົນການຮຽນລ່າສຸດ</h5>
                
                <?php if (empty($grades)): ?>
                <div class="alert alert-info">ຍັງບໍ່ມີຜົນການຮຽນໃນລະບົບ</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ຊື່ ແລະ ນາມສະກຸນ</th>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ຊື່ວິຊາ</th>
                                <th>ປີການສຶກສາ</th>
                                <th>ພາກຮຽນ</th>
                                <th>ເກຣດ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <a href="admin_grades.php?student_id=<?php echo $grade['student_id']; ?>">
                                        <?php echo $grade['student_id']; ?>
                                    </a>
                                </td>
                                <td><?php echo $grade['firstname'] . ' ' . $grade['lastname']; ?></td>
                                <td><?php echo $grade['subject_code']; ?></td>
                                <td><?php echo $grade['subject_name']; ?></td>
                                <td><?php echo $grade['study_year']; ?></td>
                                <td><?php echo $grade['semester']; ?></td>
                                <td>
                                    <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                        <?php echo $grade['grade']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="admin_grades.php?action=edit&id=<?php echo $grade['grade_id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $grade['grade_id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Modal ยืนยันการลบ -->
                                    <div class="modal fade" id="deleteModal<?php echo $grade['grade_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ຢືນຢັນຂໍເມູນ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>ທ່ານຕ້ອງການລົບ <strong><?php echo $grade['firstname'] . ' ' . $grade['lastname']; ?></strong> วิชา <strong><?php echo $grade['subject_code'] . ' - ' . $grade['subject_name']; ?></strong> ເກຣດ <strong><?php echo $grade['grade']; ?></strong> ຫຼື ບໍ່?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['grade_id']; ?>">
                                                        <button type="submit" name="delete_grade" class="btn btn-danger">ຢືນຢັນການລົບ</button>
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
                
                <!-- Pagination -->
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php 
                                $url_params = $_GET;
                                $url_params['page'] = $i;
                                $query_string = http_build_query($url_params);
                            ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo $query_string; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // เลือกนักศึกษาแบบ Real-time
        document.getElementById('student_select')?.addEventListener('change', function() {
            if (this.value) {
                window.location.href = 'admin_grades.php?student_id=' + this.value;
            }
        });
        
        // อัปเดตชื่อนักศึกษาเมื่อเลือกรหัสในฟอร์มเพิ่มผลการเรียนแบบกลุ่ม
        document.querySelectorAll('.student-select')?.forEach(function(select) {
            select.addEventListener('change', function() {
                const nameElement = this.closest('tr').querySelector('.student-name');
                const selectedOption = this.options[this.selectedIndex];
                
                if (selectedOption.value) {
                    nameElement.textContent = selectedOption.dataset.name;
                } else {
                    nameElement.textContent = '-';
                }
            });
        });
    </script>
</body>
</html>