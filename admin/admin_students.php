<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ດຶງຂໍ້ມູນສາຂາວິຊາຈາກຖານຂໍ້ມູນ ທີ່ຈັດກຸ່ມຕາມພາກວິຊາ
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

// ຟັງຊັນສໍາລັບການເພີ່ມນັກສຶກສາ
function addStudent($conn, $student_id, $firstname, $lastname, $major_id, $enrollment_year) {
    $query = "INSERT INTO students (student_id, firstname, lastname, major_id, enrollment_year, status) 
              VALUES ('$student_id', '$firstname', '$lastname', $major_id, $enrollment_year, 'studying')";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການແກ້ໄຂນັກສຶກສາ
function updateStudent($conn, $student_id, $firstname, $lastname, $major_id, $enrollment_year, $status) {
    $query = "UPDATE students 
              SET firstname = '$firstname', 
                  lastname = '$lastname', 
                  major_id = $major_id, 
                  enrollment_year = $enrollment_year, 
                  status = '$status' 
              WHERE student_id = '$student_id'";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການລຶບນັກສຶກສາ
function deleteStudent($conn, $student_id) {
    // ລຶບຜົນການຮຽນຂອງນັກສຶກສາກ່ອນ
    $delete_grades_query = "DELETE FROM grades WHERE student_id = '$student_id'";
    $conn->query($delete_grades_query);
    
    // ຈາກນັ້ນລຶບຂໍ້ມູນນັກສຶກສາ
    $delete_student_query = "DELETE FROM students WHERE student_id = '$student_id'";
    
    if ($conn->query($delete_student_query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຈັດການການສົ່ງຟອມ
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$student_id = isset($_GET['id']) ? clean($conn, $_GET['id']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $student_id = clean($conn, $_POST['student_id']);
        $firstname = clean($conn, $_POST['firstname']);
        $lastname = clean($conn, $_POST['lastname']);
        $major_id = clean($conn, $_POST['major_id']);
        $enrollment_year = clean($conn, $_POST['enrollment_year']);
        
        // ກວດສອບວ່າລະຫັດນັກສຶກສາຊໍ້າກັນຫຼືບໍ່
        $check_query = "SELECT * FROM students WHERE student_id = '$student_id'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ລະຫັດນັກສຶກສານີ້ມີໃນລະບົບແລ້ວ";
        } else {
            if (addStudent($conn, $student_id, $firstname, $lastname, $major_id, $enrollment_year)) {
                $message = "ເພີ່ມຂໍ້ມູນນັກສຶກສາຮຽບຮ້ອຍແລ້ວ";
                // ຣີເຊັດຟອມ
                $student_id = $firstname = $lastname = $major_id = $enrollment_year = '';
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_student'])) {
        $student_id = clean($conn, $_POST['student_id']);
        $firstname = clean($conn, $_POST['firstname']);
        $lastname = clean($conn, $_POST['lastname']);
        $major_id = clean($conn, $_POST['major_id']);
        $enrollment_year = clean($conn, $_POST['enrollment_year']);
        $status = clean($conn, $_POST['status']);
        
        if (updateStudent($conn, $student_id, $firstname, $lastname, $major_id, $enrollment_year, $status)) {
            $message = "ແກ້ໄຂຂໍ້ມູນນັກສຶກສາຮຽບຮ້ອຍແລ້ວ";
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $conn->error;
        }
    } elseif (isset($_POST['delete_student'])) {
        $student_id = clean($conn, $_POST['student_id']);
        
        if (deleteStudent($conn, $student_id)) {
            $message = "ລຶບຂໍ້ມູນນັກສຶກສາຮຽບຮ້ອຍແລ້ວ";
            // ຣີໄດເຣັກກັບໄປໜ້າລາຍການ
            header("Location: admin_students.php?deleted=1");
            exit();
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $conn->error;
        }
    }
}

// ດຶງຂໍ້ມູນນັກສຶກສາສໍາລັບການແກ້ໄຂ
$edit_data = null;
if ($action === 'edit' && !empty($student_id)) {
    $edit_query = "SELECT s.*, m.major_name, m.department 
                  FROM students s 
                  JOIN majors m ON s.major_id = m.major_id 
                  WHERE s.student_id = '$student_id'";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບຂໍ້ມູນນັກສຶກສາທີ່ຕ້ອງການແກ້ໄຂ";
    }
}

// ດຶງຂໍ້ມູນນັກສຶກສາທັງໝົດສໍາລັບສະແດງໃນຕາຕະລາງ
$students_query = "SELECT s.*, m.major_name, m.department 
                  FROM students s 
                  JOIN majors m ON s.major_id = m.major_id 
                  ORDER BY s.student_id";
$students_result = $conn->query($students_query);
$students = [];
if ($students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// ແປງສະຖານະເປັນພາສາລາວ
function getStatusText($status) {
    switch ($status) {
        case 'studying':
            return 'ກຳລັງສຶກສາ';
        case 'graduated':
            return 'ຈົບການສຶກສາ';
        case 'dismissed':
            return 'ພົ້ນສະພາບ';
        default:
            return $status;
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຈັດການຂໍ້ມູນນັກສຶກສາ - ວິທະຍາໄລເຕັກນິກ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --gray-light: #f8f9fa;
            --gray-medium: #e9ecef;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        body {
            background-color: var(--gray-light);
            padding-top: 60px;
        }
        
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            padding: 20px;
            background-color: var(--primary-color);
            color: white;
            z-index: 100;
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: var(--transition);
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--gray-medium);
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .card-header i {
            color: var(--secondary-color);
            margin-right: 10px;
        }
        
        .table-responsive {
            margin-top: 15px;
        }
        
        .table {
            vertical-align: middle;
        }
        
        .table thead th {
            border-bottom-width: 1px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
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
            background-color: var(--secondary-color);
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-studying {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(46, 204, 113, 0.4);
        }
        
        .status-graduated {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--secondary-color);
            border: 1px solid rgba(52, 152, 219, 0.4);
        }
        
        .status-dismissed {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(231, 76, 60, 0.4);
        }
        
        .pagination {
            margin-top: 20px;
        }
        
        .page-link {
            color: var(--secondary-color);
            border-radius: var(--border-radius);
            margin: 0 3px;
        }
        
        .page-item.active .page-link {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .form-control, .form-select {
            border-radius: var(--border-radius);
            padding: 10px 15px;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .alert {
            border-radius: var(--border-radius);
            border: none;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
        
        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .btn-group .btn {
            border-radius: var(--border-radius);
            margin-right: 5px;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
            border-color: #e67e22;
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
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
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> ໜ້າຫຼັກ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="admin_students.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-graduate text-secondary me-2"></i> ຈັດການຂໍ້ມູນນັກສຶກສາ</h2>
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="admin_students.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> ເພີ່ມນັກສຶກສາໃໝ່
            </a>
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
            <i class="fas fa-check-circle me-2"></i> ລຶບຂໍ້ມູນນັກສຶກສາຮຽບຮ້ອຍແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ຟອມເພີ່ມນັກສຶກສາ -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-primary"></i> ເພີ່ມນັກສຶກສາໃໝ່</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">ລະຫັດນັກສຶກສາ *</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" required>
                            <small class="text-muted">ເຊັ່ນ IT10001</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="enrollment_year" class="form-label">ປີທີ່ເຂົ້າສຶກສາ *</label>
                            <select class="form-select" id="enrollment_year" name="enrollment_year" required>
                                <?php
                                $current_year = date('Y');
                                for ($i = $current_year; $i >= $current_year - 10; $i--) {
                                    echo "<option value=\"$i\">$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstname" class="form-label">ຊື່ *</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lastname" class="form-label">ນາມສະກຸນ *</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="major_id" class="form-label">ສາຂາວິຊາ *</label>
                        <select class="form-select" id="major_id" name="major_id" required>
                            <option value="">ເລືອກສາຂາວິຊາ</option>
                            <?php foreach ($majors_by_department as $department => $department_majors): ?>
                            <optgroup label="<?php echo $department; ?>">
                                <?php foreach ($department_majors as $major): ?>
                                <option value="<?php echo $major['major_id']; ?>"><?php echo $major['major_name']; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_student" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ຟອມແກ້ໄຂນັກສຶກສາ -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit text-warning"></i> ແກ້ໄຂຂໍ້ມູນນັກສຶກສາ</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">ລະຫັດນັກສຶກສາ *</label>
                            <input type="text" class="form-control bg-light" id="student_id" name="student_id" value="<?php echo $edit_data['student_id']; ?>" readonly>
                            <small class="text-muted">ບໍ່ສາມາດແກ້ໄຂລະຫັດນັກສຶກສາໄດ້</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="enrollment_year" class="form-label">ປີທີ່ເຂົ້າສຶກສາ *</label>
                            <select class="form-select" id="enrollment_year" name="enrollment_year" required>
                                <?php
                                $current_year = date('Y');
                                for ($i = $current_year; $i >= $current_year - 10; $i--) {
                                    $selected = ($i == $edit_data['enrollment_year']) ? 'selected' : '';
                                    echo "<option value=\"$i\" $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstname" class="form-label">ຊື່ *</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo $edit_data['firstname']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lastname" class="form-label">ນາມສະກຸນ *</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo $edit_data['lastname']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="major_id" class="form-label">ສາຂາວິຊາ *</label>
                            <select class="form-select" id="major_id" name="major_id" required>
                                <?php foreach ($majors_by_department as $department => $department_majors): ?>
                                <optgroup label="<?php echo $department; ?>">
                                    <?php foreach ($department_majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>" <?php echo ($major['major_id'] == $edit_data['major_id']) ? 'selected' : ''; ?>>
                                        <?php echo $major['major_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">ສະຖານະ *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="studying" <?php echo ($edit_data['status'] == 'studying') ? 'selected' : ''; ?>>ກຳລັງສຶກສາ</option>
                                <option value="graduated" <?php echo ($edit_data['status'] == 'graduated') ? 'selected' : ''; ?>>ຈົບການສຶກສາ</option>
                                <option value="dismissed" <?php echo ($edit_data['status'] == 'dismissed') ? 'selected' : ''; ?>>ພົ້ນສະພາບ</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="admin_students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_student" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກການແກ້ໄຂ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ສະແດງລາຍການນັກສຶກສາ -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list text-primary"></i> ລາຍການນັກສຶກສາທັງໝົດ</h5>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="searchInput" class="form-control" placeholder="ຄົ້ນຫາ...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> ຍັງບໍ່ມີຂໍ້ມູນນັກສຶກສາໃນລະບົບ
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ຊື່-ນາມສະກຸນ</th>
                                <th>ພາກວິຊາ</th>
                                <th>ສາຂາວິຊາ</th>
                                <th>ປີທີ່ເຂົ້າສຶກສາ</th>
                                <th>ສະຖານະ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo $student['student_id']; ?></td>
                                <td><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></td>
                                <td><?php echo $student['department']; ?></td>
                                <td><?php echo $student['major_name']; ?></td>
                                <td><?php echo $student['enrollment_year']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $student['status']; ?>">
                                        <?php echo getStatusText($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="admin_students.php?action=edit&id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> ແກ້ໄຂ
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $student['student_id']; ?>">
                                            <i class="fas fa-trash-alt"></i> ລຶບ
                                        </button>
                                    </div>
                                    
                                    <!-- ໜ້າຕ່າງຢືນຢັນການລຶບ -->
                                    <div class="modal fade" id="deleteModal<?php echo $student['student_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel">ຢືນຢັນການລຶບຂໍ້ມູນ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-3">
                                                        <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                                                        <p>ທ່ານຕ້ອງການລຶບຂໍ້ມູນນັກສຶກສາ "<strong><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></strong>" ລະຫັດ <strong><?php echo $student['student_id']; ?></strong> ແທ້ບໍ່?</p>
                                                        <p class="text-danger"><strong>ຄຳເຕືອນ:</strong> ການລຶບຂໍ້ມູນນັກສຶກສາຈະເຮັດໃຫ້ຜົນການຮຽນທັງໝົດຂອງນັກສຶກສາຄົນນີ້ຖືກລຶບດ້ວຍ</p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                        <button type="submit" name="delete_student" class="btn btn-danger">
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
                
                <!-- ຕົວແບ່ງໜ້າ -->
                <nav aria-label="ການນຳທາງໜ້າ">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">ກ່ອນໜ້າ</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">ຕໍ່ໄປ</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // ສະຄຣິບສຳລັບການຄົ້ນຫາໃນຕາຕະລາງ
        document.addEventListener('DOMContentLoaded', function() {
            // ຄົ້ນຫາໃນຕາຕະລາງ
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
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
            }
            
            // ສະແດງເມນູຫຼັກໃນໂໝດມືຖື
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (navbarToggler) {
                navbarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    
                    if (window.innerWidth < 992) {
                        if (sidebar.classList.contains('show')) {
                            mainContent.style.marginLeft = '250px';
                        } else {
                            mainContent.style.marginLeft = '0';
                        }
                    }
                });
            }
            
            // ຈັດການກັບການປັບຂະໜາດໜ້າຈໍ
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    mainContent.style.marginLeft = '250px';
                } else {
                    if (!sidebar.classList.contains('show')) {
                        mainContent.style.marginLeft = '0';
                    }
                }
            });
        });
    </script>
</body>
</html>