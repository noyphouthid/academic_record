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
function addGrade($conn, $student_id, $subject_code, $academic_year, $semester, $grade) {
    // ตรวจสอบว่านักศึกษาและรายวิชามีอยู่จริง
    $check_student = "SELECT * FROM students WHERE student_id = '$student_id'";
    $check_subject = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
    
    $student_exists = $conn->query($check_student)->num_rows > 0;
    $subject_exists = $conn->query($check_subject)->num_rows > 0;
    
    if (!$student_exists) {
        return "ไม่พบข้อมูลนักศึกษารหัส $student_id ในระบบ";
    }
    
    if (!$subject_exists) {
        return "ไม่พบข้อมูลรายวิชารหัส $subject_code ในระบบ";
    }
    
    // ตรวจสอบว่ามีข้อมูลผลการเรียนนี้อยู่แล้วหรือไม่
    $check_query = "SELECT * FROM grades 
                   WHERE student_id = '$student_id' 
                   AND subject_code = '$subject_code' 
                   AND academic_year = $academic_year 
                   AND semester = $semester";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows > 0) {
        // ถ้ามีข้อมูลแล้ว ให้อัพเดทแทน
        $update_query = "UPDATE grades 
                        SET grade = '$grade' 
                        WHERE student_id = '$student_id' 
                        AND subject_code = '$subject_code' 
                        AND academic_year = $academic_year 
                        AND semester = $semester";
        
        if ($conn->query($update_query) === TRUE) {
            return "อัพเดทผลการเรียนเรียบร้อยแล้ว";
        } else {
            return "เกิดข้อผิดพลาดในการอัพเดทข้อมูล: " . $conn->error;
        }
    } else {
        // ถ้ายังไม่มีข้อมูล ให้เพิ่มใหม่
        $insert_query = "INSERT INTO grades (student_id, subject_code, academic_year, semester, grade) 
                        VALUES ('$student_id', '$subject_code', $academic_year, $semester, '$grade')";
        
        if ($conn->query($insert_query) === TRUE) {
            return "เพิ่มผลการเรียนเรียบร้อยแล้ว";
        } else {
            return "เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . $conn->error;
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
function bulkAddGrades($conn, $student_ids, $subject_code, $academic_year, $semester, $grades) {
    $success_count = 0;
    $error_messages = [];
    
    for ($i = 0; $i < count($student_ids); $i++) {
        $student_id = $student_ids[$i];
        $grade = $grades[$i];
        
        if (!empty($student_id) && !empty($grade)) {
            $result = addGrade($conn, $student_id, $subject_code, $academic_year, $semester, $grade);
            
            if (strpos($result, "เรียบร้อยแล้ว") !== false) {
                $success_count++;
            } else {
                $error_messages[] = "นักศึกษารหัส $student_id: $result";
            }
        }
    }
    
    if (empty($error_messages)) {
        return "เพิ่มผลการเรียนเรียบร้อยแล้ว $success_count รายการ";
    } else {
        return "เพิ่มผลการเรียนเรียบร้อยแล้ว $success_count รายการ แต่มีบางรายการที่มีข้อผิดพลาด: " . implode(", ", $error_messages);
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
        $academic_year = clean($conn, $_POST['academic_year']);
        $semester = clean($conn, $_POST['semester']);
        $grade = clean($conn, $_POST['grade']);
        
        $result = addGrade($conn, $student_id, $subject_code, $academic_year, $semester, $grade);
        
        if (strpos($result, "เกิดข้อผิดพลาด") !== false) {
            $error = $result;
        } else {
            $message = $result;
        }
    } elseif (isset($_POST['bulk_add_grades'])) {
        $student_ids = $_POST['student_ids'];
        $subject_code = clean($conn, $_POST['subject_code']);
        $academic_year = clean($conn, $_POST['academic_year']);
        $semester = clean($conn, $_POST['semester']);
        $grades = $_POST['grades'];
        
        $result = bulkAddGrades($conn, $student_ids, $subject_code, $academic_year, $semester, $grades);
        
        if (strpos($result, "เกิดข้อผิดพลาด") !== false) {
            $error = $result;
        } else {
            $message = $result;
        }
    } elseif (isset($_POST['edit_grade'])) {
        $grade_id = intval($_POST['grade_id']);
        $grade = clean($conn, $_POST['grade']);
        
        if (updateGrade($conn, $grade_id, $grade)) {
            $message = "แก้ไขผลการเรียนเรียบร้อยแล้ว";
        } else {
            $error = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $conn->error;
        }
    } elseif (isset($_POST['delete_grade'])) {
        $grade_id = intval($_POST['grade_id']);
        
        if (deleteGrade($conn, $grade_id)) {
            $message = "ลบผลการเรียนเรียบร้อยแล้ว";
            // รีไดเร็กต์กลับไปหน้ารายการหรือหน้าข้อมูลนักศึกษา
            if (!empty($_POST['return_url'])) {
                header("Location: " . $_POST['return_url'] . "&deleted=1");
                exit();
            } else {
                header("Location: admin_grades.php?deleted=1");
                exit();
            }
        } else {
            $error = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
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
        $error = "ไม่พบข้อมูลผลการเรียนที่ต้องการแก้ไข";
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
                        ORDER BY g.academic_year DESC, g.semester DESC, s.subject_code";
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
    $filter_academic_year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : 0;
    $filter_semester = isset($_GET['filter_semester']) ? intval($_GET['filter_semester']) : 0;
    
    if ($filter_academic_year > 0) {
        $filter_sql .= " AND g.academic_year = $filter_academic_year";
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
                    ORDER BY g.academic_year DESC, g.semester DESC, g.grade_id DESC 
                    LIMIT $offset, $limit";
    $grades_result = $conn->query($grades_query);
    
    if ($grades_result->num_rows > 0) {
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

// ดึงปีการศึกษาทั้งหมดที่มีในระบบ
$years_query = "SELECT DISTINCT academic_year FROM grades ORDER BY academic_year DESC";
$years_result = $conn->query($years_query);
$academic_years = [];
if ($years_result->num_rows > 0) {
    while ($row = $years_result->fetch_assoc()) {
        $academic_years[] = $row['academic_year'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผลการเรียน - Polytechnic College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .grade-badge {
            display: inline-block;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
        }
        .grade-A {
            background-color: #28a745;
            color: white;
        }
        .grade-B\+ {
            background-color: #20c997;
            color: white;
        }
        .grade-B {
            background-color: #17a2b8;
            color: white;
        }
        .grade-C\+ {
            background-color: #6c757d;
            color: white;
        }
        .grade-C {
            background-color: #6c757d;
            color: white;
        }
        .grade-D\+ {
            background-color: #fd7e14;
            color: white;
        }
        .grade-D {
            background-color: #fd7e14;
            color: white;
        }
        .grade-F {
            background-color: #dc3545;
            color: white;
        }
        .grade-W, .grade-I {
            background-color: #6c757d;
            color: white;
        }
        .academic-year-header {
            background-color: #f1f8ff;
            font-weight: bold;
        }
        .pagination {
            margin-top: 20px;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .filter-form .form-select {
            max-width: 150px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                Polytechnic College - ระบบผู้ดูแล
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
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
                <div class="user-role"><?php echo ($_SESSION['role'] === 'admin') ? 'ผู้ดูแลระบบ' : 'อาจารย์'; ?></div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_students.php">
                    <i class="fas fa-user-graduate"></i> จัดการนักศึกษา
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="admin_grades.php">
                    <i class="fas fa-chart-line"></i> จัดการผลการเรียน
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_subjects.php">
                    <i class="fas fa-book"></i> จัดการรายวิชา
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_majors.php">
                    <i class="fas fa-graduation-cap"></i> จัดการสาขาวิชา
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_users.php">
                    <i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_reports.php">
                    <i class="fas fa-file-alt"></i> รายงาน
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="index.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i> ดูหน้าเว็บไซต์
                </a>
            </li>
        </ul>
    </div>
    
   <!-- Main Content -->
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line"></i> จัดการผลการเรียน</h2>
        
        <?php if ($action !== 'add' && $action !== 'edit' && !$bulk_mode): ?>
        <div>
            <div class="btn-group me-2">
                <a href="admin_grades.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> เพิ่มผลการเรียนรายบุคคล
                </a>
                <a href="admin_grades.php?bulk=1" class="btn btn-success">
                    <i class="fas fa-tasks"></i> เพิ่มผลการเรียนแบบกลุ่ม
                </a>
            </div>
            
            <a href="import_grades.php" class="btn btn-info me-2">
                <i class="fas fa-file-import"></i> นำเข้าข้อมูลจาก Excel
            </a>
            
            <?php if (!empty($student_id)): ?>
            <a href="admin_grades.php" class="btn btn-outline-secondary">
                <i class="fas fa-list"></i> แสดงทั้งหมด
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
            ลบข้อมูลผลการเรียนเรียบร้อยแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ฟอร์มเพิ่มผลการเรียนรายบุคคล -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle"></i> เพิ่มผลการเรียนรายบุคคล</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">เลือกนักศึกษา *</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">-- เลือกนักศึกษา --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" <?php echo (isset($_GET['student_id']) && $student['student_id'] == $_GET['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo $student['student_id'] . ' - ' . $student['firstname'] . ' ' . $student['lastname'] . ' (' . $student['major_name'] . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subject_code" class="form-label">เลือกรายวิชา *</label>
                            <select class="form-select" id="subject_code" name="subject_code" required>
                                <option value="">-- เลือกรายวิชา --</option>
                                <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_code']; ?>">
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['credit'] . ' หน่วยกิต)'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="academic_year" class="form-label">ปีการศึกษา *</label>
                            <select class="form-select" id="academic_year" name="academic_year" required>
                                <?php
                                $current_year = date('Y');
                                for ($i = $current_year; $i >= $current_year - 10; $i--) {
                                    echo "<option value=\"$i\">$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">ภาคการศึกษา *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3 (ภาคฤดูร้อน)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="grade" class="form-label">เกรด *</label>
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
                                <option value="W">W (ถอนรายวิชา)</option>
                                <option value="I">I (ไม่สมบูรณ์)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo !empty($_GET['student_id']) ? 'admin_grades.php?student_id='.$_GET['student_id'] : 'admin_grades.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ยกเลิก
                        </a>
                        <button type="submit" name="add_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($bulk_mode): ?>
        <!-- ฟอร์มเพิ่มผลการเรียนแบบกลุ่ม -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> เพิ่มผลการเรียนแบบกลุ่ม</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">สำหรับเพิ่มผลการเรียนรายวิชาเดียวกันให้กับนักศึกษาหลายคนพร้อมกัน</p>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="subject_code" class="form-label">รายวิชา *</label>
                            <select class="form-select" id="subject_code" name="subject_code" required>
                                <option value="">-- เลือกรายวิชา --</option>
                                <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_code']; ?>">
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="academic_year" class="form-label">ปีการศึกษา *</label>
                            <select class="form-select" id="academic_year" name="academic_year" required>
                                <?php
                                $current_year = date('Y');
                                for ($i = $current_year; $i >= $current_year - 10; $i--) {
                                    echo "<option value=\"$i\">$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">ภาคการศึกษา *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3 (ภาคฤดูร้อน)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="10%">#</th>
                                    <th width="30%">รหัสนักศึกษา</th>
                                    <th width="45%">ชื่อ-นามสกุล</th>
                                    <th width="15%">เกรด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td>
                                        <select class="form-select student-select" name="student_ids[]">
                                            <option value="">-- เลือกนักศึกษา --</option>
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
                            <i class="fas fa-arrow-left"></i> ยกเลิก
                        </a>
                        <button type="submit" name="bulk_add_grades" class="btn btn-success">
                            <i class="fas fa-save"></i> บันทึกผลการเรียนทั้งหมด
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ฟอร์มแก้ไขผลการเรียน -->
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="fas fa-edit"></i> แก้ไขผลการเรียน</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>ข้อมูลผลการเรียน</h6>
                    <p>
                        <strong>นักศึกษา:</strong> <?php echo $edit_data['firstname'] . ' ' . $edit_data['lastname']; ?> (<?php echo $edit_data['student_id']; ?>)<br>
                        <strong>รายวิชา:</strong> <?php echo $edit_data['subject_code'] . ' - ' . $edit_data['subject_name']; ?><br>
                        <strong>ปีการศึกษา:</strong> <?php echo $edit_data['academic_year']; ?> ภาคการศึกษาที่ <?php echo $edit_data['semester']; ?>
                    </p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="grade_id" value="<?php echo $edit_data['grade_id']; ?>">
                    
                    <div class="mb-3">
                        <label for="grade" class="form-label">เกรด *</label>
                        <select class="form-select" id="grade" name="grade" required>
                            <option value="A" <?php echo ($edit_data['grade'] == 'A') ? 'selected' : ''; ?>>A</option>
                            <option value="B+" <?php echo ($edit_data['grade'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B" <?php echo ($edit_data['grade'] == 'B') ? 'selected' : ''; ?>>B</option>
                            <option value="C+" <?php echo ($edit_data['grade'] == 'C+') ? 'selected' : ''; ?>>C+</option>
                            <option value="C" <?php echo ($edit_data['grade'] == 'C') ? 'selected' : ''; ?>>C</option>
                            <option value="D+" <?php echo ($edit_data['grade'] == 'D+') ? 'selected' : ''; ?>>D+</option>
                            <option value="D" <?php echo ($edit_data['grade'] == 'D') ? 'selected' : ''; ?>>D</option>
                            <option value="F" <?php echo ($edit_data['grade'] == 'F') ? 'selected' : ''; ?>>F</option>
                            <option value="W" <?php echo ($edit_data['grade'] == 'W') ? 'selected' : ''; ?>>W (ถอนรายวิชา)</option>
                            <option value="I" <?php echo ($edit_data['grade'] == 'I') ? 'selected' : ''; ?>>I (ไม่สมบูรณ์)</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_grades.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ยกเลิก
                        </a>
                        <button type="submit" name="edit_grade" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($student_data): ?>
        <!-- แสดงผลการเรียนของนักศึกษา -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-graduate"></i> ข้อมูลนักศึกษา</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>รหัสนักศึกษา:</strong> <?php echo $student_data['student_id']; ?></p>
                        <p><strong>ชื่อ-นามสกุล:</strong> <?php echo $student_data['firstname'] . ' ' . $student_data['lastname']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>สาขาวิชา:</strong> <?php echo $student_data['major_name']; ?></p>
                        <p><strong>สถานะ:</strong> <?php echo ($student_data['status'] == 'studying') ? 'กำลังศึกษา' : (($student_data['status'] == 'graduated') ? 'จบการศึกษา' : 'พ้นสภาพ'); ?></p>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <h5>ผลการเรียน</h5>
                    <a href="admin_grades.php?action=add&student_id=<?php echo $student_data['student_id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> เพิ่มผลการเรียน
                    </a>
                </div>
                
                <?php if (empty($student_grades)): ?>
                <div class="alert alert-info mt-3">ยังไม่มีข้อมูลผลการเรียนของนักศึกษาคนนี้</div>
                <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ปีการศึกษา</th>
                                <th>ภาคเรียน</th>
                                <th>รหัสวิชา</th>
                                <th>ชื่อวิชา</th>
                                <th>หน่วยกิต</th>
                                <th>เกรด</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_year = '';
                            $current_semester = '';
                            $total_credit = 0;
                            $total_grade_point = 0;
                            
                            foreach ($student_grades as $grade):
                                $year_semester = $grade['academic_year'] . '-' . $grade['semester'];
                                if ($year_semester != $current_year . '-' . $current_semester):
                                    $current_year = $grade['academic_year'];
                                    $current_semester = $grade['semester'];
                            ?>
                            <tr class="academic-year-header">
                                <td colspan="7">ปีการศึกษา <?php echo $current_year; ?> ภาคเรียนที่ <?php echo $current_semester; ?></td>
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
                                <td><?php echo $grade['academic_year']; ?></td>
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
                                                    <h5 class="modal-title">ยืนยันการลบข้อมูล</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>คุณต้องการลบผลการเรียนวิชา <strong><?php echo $grade['subject_code'] . ' - ' . $grade['subject_name']; ?></strong> เกรด <strong><?php echo $grade['grade']; ?></strong> ใช่หรือไม่?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['grade_id']; ?>">
                                                        <input type="hidden" name="return_url" value="admin_grades.php?student_id=<?php echo $student_data['student_id']; ?>">
                                                        <button type="submit" name="delete_grade" class="btn btn-danger">ยืนยันการลบ</button>
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
                    <h6>สรุปผลการเรียน</h6>
                    <p>
                        <strong>จำนวนวิชาที่ลงทะเบียน:</strong> <?php echo count($student_grades); ?> วิชา<br>
                        <strong>หน่วยกิตรวม:</strong> <?php echo $total_credit; ?> หน่วยกิต<br>
                        <strong>เกรดเฉลี่ยสะสม (GPA):</strong> <?php echo $gpa; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- แสดงรายการผลการเรียนล่าสุด หรือ ให้เลือกนักศึกษา -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-search"></i> ค้นหาข้อมูลผลการเรียน</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="admin_grades.php" class="d-flex">
                            <select class="form-select me-2" name="student_id" id="student_select">
                                <option value="">-- เลือกนักศึกษา --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo $student['student_id'] . ' - ' . $student['firstname'] . ' ' . $student['lastname']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-6">
                        <form method="GET" action="admin_grades.php" class="filter-form d-flex justify-content-end">
                            <div class="me-2">
                                <select class="form-select" name="filter_year">
                                    <option value="0">- ปีการศึกษา -</option>
                                    <?php foreach ($academic_years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="me-2">
                                <select class="form-select" name="filter_semester">
                                    <option value="0">- ภาคเรียน -</option>
                                    <option value="1" <?php echo (isset($_GET['filter_semester']) && $_GET['filter_semester'] == 1) ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo (isset($_GET['filter_semester']) && $_GET['filter_semester'] == 2) ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo (isset($_GET['filter_semester']) && $_GET['filter_semester'] == 3) ? 'selected' : ''; ?>>3</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fas fa-filter"></i> กรอง
                            </button>
                        </form>
                    </div>
                </div>
                
                <h5 class="mb-3">รายการผลการเรียนล่าสุด</h5>
                
                <?php if (empty($grades)): ?>
                <div class="alert alert-info">ยังไม่มีข้อมูลผลการเรียนในระบบ</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสนักศึกษา</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>รหัสวิชา</th>
                                <th>ชื่อวิชา</th>
                                <th>ปีการศึกษา</th>
                                <th>ภาคเรียน</th>
                                <th>เกรด</th>
                                <th>การจัดการ</th>
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
                                <td><?php echo $grade['academic_year']; ?></td>
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
                                                    <h5 class="modal-title">ยืนยันการลบข้อมูล</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>คุณต้องการลบผลการเรียนของ <strong><?php echo $grade['firstname'] . ' ' . $grade['lastname']; ?></strong> วิชา <strong><?php echo $grade['subject_code'] . ' - ' . $grade['subject_name']; ?></strong> เกรด <strong><?php echo $grade['grade']; ?></strong> ใช่หรือไม่?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['grade_id']; ?>">
                                                        <button type="submit" name="delete_grade" class="btn btn-danger">ยืนยันการลบ</button>
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