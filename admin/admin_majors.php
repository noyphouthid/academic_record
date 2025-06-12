<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ຟັງຊັນສໍາລັບການເພີ່ມສາຂາວິຊາ
function addMajor($conn, $major_name, $department, $description = '') {
    $query = "INSERT INTO majors (major_name, department, description) 
              VALUES ('$major_name', '$department', '$description')";
    
    if ($conn->query($query) === TRUE) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການແກ້ໄຂສາຂາວິຊາ
function updateMajor($conn, $major_id, $major_name, $department, $description = '') {
    $query = "UPDATE majors 
              SET major_name = '$major_name', 
                  department = '$department',
                  description = '$description'
              WHERE major_id = $major_id";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການລຶບສາຂາວິຊາ
function deleteMajor($conn, $major_id) {
    // ກວດສອບວ່າມີນັກສຶກສາໃນສາຂານີ້ຫຼືບໍ່
    $check_students_query = "SELECT COUNT(*) as count FROM students WHERE major_id = $major_id";
    $check_result = $conn->query($check_students_query);
    $student_count = $check_result->fetch_assoc()['count'];
    
    if ($student_count > 0) {
        return "ບໍ່ສາມາດລຶບສາຂາວິຊານີ້ໄດ້ເພາະມີນັກສຶກສາ $student_count ຄົນໃນສາຂານີ້";
    }
    
    // ລຶບສາຂາວິຊາ
    $delete_query = "DELETE FROM majors WHERE major_id = $major_id";
    
    if ($conn->query($delete_query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຈັດການການສົ່ງຟອມ
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$major_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_major'])) {
        $major_name = clean($conn, $_POST['major_name']);
        $department = clean($conn, $_POST['department']);
        $description = clean($conn, $_POST['description']);
        
        // ກວດສອບວ່າຊື່ສາຂາວິຊາຊໍ້າກັນຫຼືບໍ່
        $check_query = "SELECT * FROM majors WHERE major_name = '$major_name' AND department = '$department'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ສາຂາວິຊານີ້ມີໃນພາກວິຊາດັ່ງກ່າວແລ້ວ";
        } else {
            $result = addMajor($conn, $major_name, $department, $description);
            if ($result) {
                $message = "ເພີ່ມຂໍ້ມູນສາຂາວິຊາຮຽບຮ້ອຍແລ້ວ (ID: $result)";
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_major'])) {
        $major_id = intval($_POST['major_id']);
        $major_name = clean($conn, $_POST['major_name']);
        $department = clean($conn, $_POST['department']);
        $description = clean($conn, $_POST['description']);
        
        // ກວດສອບວ່າຊື່ສາຂາວິຊາຊໍ້າກັນຫຼືບໍ່ (ຍົກເວັ້ນໂຕເອງ)
        $check_query = "SELECT * FROM majors WHERE major_name = '$major_name' AND department = '$department' AND major_id != $major_id";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ສາຂາວິຊານີ້ມີໃນພາກວິຊາດັ່ງກ່າວແລ້ວ";
        } else {
            if (updateMajor($conn, $major_id, $major_name, $department, $description)) {
                $message = "ແກ້ໄຂຂໍ້ມູນສາຂາວິຊາຮຽບຮ້ອຍແລ້ວ";
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_major'])) {
        $major_id = intval($_POST['major_id']);
        
        $result = deleteMajor($conn, $major_id);
        if ($result === true) {
            $message = "ລຶບຂໍ້ມູນສາຂາວິຊາຮຽບຮ້ອຍແລ້ວ";
            header("Location: admin_majors.php?deleted=1");
            exit();
        } elseif (is_string($result)) {
            $error = $result;
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $conn->error;
        }
    }
}

// ດຶງຂໍ້ມູນສາຂາວິຊາສໍາລັບການແກ້ໄຂ
$edit_data = null;
if ($action === 'edit' && $major_id > 0) {
    $edit_query = "SELECT * FROM majors WHERE major_id = $major_id";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບຂໍ້ມູນສາຂາວິຊາທີ່ຕ້ອງການແກ້ໄຂ";
    }
}

// ດຶງຂໍ້ມູນສາຂາວິຊາທັງໝົດພ້ອມຈຳນວນນັກສຶກສາ
$search = isset($_GET['search']) ? clean($conn, $_GET['search']) : '';
$department_filter = isset($_GET['department']) ? clean($conn, $_GET['department']) : '';

$search_condition = '';
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(m.major_name LIKE '%$search%' OR m.department LIKE '%$search%')";
}

if (!empty($department_filter)) {
    $conditions[] = "m.department = '$department_filter'";
}

if (!empty($conditions)) {
    $search_condition = "WHERE " . implode(" AND ", $conditions);
}

$majors_query = "SELECT m.*, 
                        COUNT(s.student_id) as student_count,
                        COUNT(CASE WHEN s.status = 'studying' THEN 1 END) as active_students,
                        COUNT(CASE WHEN s.status = 'graduated' THEN 1 END) as graduated_students
                 FROM majors m 
                 LEFT JOIN students s ON m.major_id = s.major_id 
                 $search_condition
                 GROUP BY m.major_id 
                 ORDER BY m.department, m.major_name";

$majors_result = $conn->query($majors_query);
$majors = [];
if ($majors_result->num_rows > 0) {
    while ($row = $majors_result->fetch_assoc()) {
        $majors[] = $row;
    }
}

// ດຶງລາຍຊື່ພາກວິຊາທັງໝົດ
$departments_query = "SELECT DISTINCT department FROM majors ORDER BY department";
$departments_result = $conn->query($departments_query);
$departments = [];
if ($departments_result->num_rows > 0) {
    while ($row = $departments_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// ນັບສະຖິຕິພາບລວມ
$stats_query = "SELECT 
                    COUNT(*) as total_majors,
                    COUNT(DISTINCT department) as total_departments,
                    (SELECT COUNT(*) FROM students) as total_students
                FROM majors";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// ກຸ່ມຂໍ້ມູນຕາມພາກວິຊາ
$majors_by_department = [];
foreach ($majors as $major) {
    $majors_by_department[$major['department']][] = $major;
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຈັດການສາຂາວິຊາ - ວິທະຍາໄລເຕັກນິກ</title>
    <link rel="stylesheet" href="navbar.css">
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
        
        .dashboard-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
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
            background-color: #f8f9fa;
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
        
        .stats-card {
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            color: white;
        }
        
        .stats-card.blue {
            background: linear-gradient(135deg, var(--secondary-color), #5dade2);
        }
        
        .stats-card.green {
            background: linear-gradient(135deg, var(--success-color), #58d68d);
        }
        
        .stats-card.orange {
            background: linear-gradient(135deg, var(--warning-color), #f7dc6f);
        }
        
        .stats-card h3 {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stats-card p {
            margin-bottom: 0;
            opacity: 0.9;
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
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            border-radius: var(--border-radius);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .alert {
            border-radius: var(--border-radius);
            border: none;
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
        
        .search-box {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .department-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .student-count-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .student-count-badge.active {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
        }
        
        .student-count-badge.graduated {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--secondary-color);
        }
        
        .student-count-badge.total {
            background-color: rgba(243, 156, 18, 0.2);
            color: var(--warning-color);
        }
        
        .department-section {
            margin-bottom: 30px;
        }
        
        .department-header {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }
        
        .major-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .major-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
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
  <?php include 'navbar.php'; ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-graduation-cap text-primary"></i> ຈັດການສາຂາວິຊາ</h2>
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="admin_majors.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> ເພີ່ມສາຂາວິຊາໃໝ່
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
            <i class="fas fa-check-circle me-2"></i> ລຶບຂໍ້ມູນສາຂາວິຊາຮຽບຮ້ອຍແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <!-- ສະຖິຕິພາບລວມ -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card blue">
                    <h3><?php echo $stats['total_majors']; ?></h3>
                    <p><i class="fas fa-graduation-cap me-2"></i>ສາຂາວິຊາທັງໝົດ</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card green">
                    <h3><?php echo $stats['total_departments']; ?></h3>
                    <p><i class="fas fa-building me-2"></i>ພາກວິຊາທັງໝົດ</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card orange">
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p><i class="fas fa-users me-2"></i>ນັກສຶກສາທັງໝົດ</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ຟອມເພີ່ມສາຂາວິຊາ -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-primary"></i> ເພີ່ມສາຂາວິຊາໃໝ່</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="major_name" class="form-label">ຊື່ສາຂາວິຊາ *</label>
                                <input type="text" class="form-control" id="major_name" name="major_name" required>
                                <small class="text-muted">ເຊັ່ນ ວິທະຍາສາດຄອມພິວເຕີ</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">ພາກວິຊາ *</label>
                                <input type="text" class="form-control" id="department" name="department" list="departments" required>
                                <datalist id="departments">
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small class="text-muted">ເຊັ່ນ ພາກວິຊາເຕັກໂນໂລຊີ</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label">ລາຍລະອຽດ</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="ອະທິບາຍຫຼັກສູດ ແລະ ຄວາມສາມາດທີ່ຈະໄດ້ຮັບ..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_majors.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_major" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ຟອມແກ້ໄຂສາຂາວິຊາ -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit text-warning"></i> ແກ້ໄຂຂໍ້ມູນສາຂາວິຊາ</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="major_id" value="<?php echo $edit_data['major_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="major_name" class="form-label">ຊື່ສາຂາວິຊາ *</label>
                                <input type="text" class="form-control" id="major_name" name="major_name" value="<?php echo $edit_data['major_name']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">ພາກວິຊາ *</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?php echo $edit_data['department']; ?>" list="departments" required>
                                <datalist id="departments">
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="form-label">ລາຍລະອຽດ</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($edit_data['description']) ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_majors.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_major" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກການແກ້ໄຂ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ສະແດງລາຍການສາຂາວິຊາ -->
        
        <!-- ກ່ອງຄົ້ນຫາ -->
        <div class="search-box">
            <form method="GET" action="admin_majors.php" class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="ຄົ້ນຫາຊື່ສາຂາ ຫຼື ພາກວິຊາ..." value="<?php echo $search; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="department">
                        <option value="">ທຸກພາກວິຊາ</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>" <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                            <?php echo $dept; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> ຄົ້ນຫາ
                        </button>
                        <?php if (!empty($search) || !empty($department_filter)): ?>
                        <a href="admin_majors.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> ລ້າງ
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-info" onclick="toggleView()">
                            <i class="fas fa-th-list" id="viewIcon"></i> <span id="viewText">ມຸມມອງການ໌ດ</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- ມຸມມອງຕາຕະລາງ -->
        <div id="tableView" class="dashboard-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list text-primary"></i> ລາຍການສາຂາວິຊາທັງໝົດ</h5>
                    <span class="badge bg-primary"><?php echo count($majors); ?> ລາຍການ</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($majors)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>ຍັງບໍ່ມີຂໍ້ມູນສາຂາວິຊາໃນລະບົບ</h5>
                    <p>ກົດປຸ່ມ "ເພີ່ມສາຂາວິຊາໃໝ່" ເພື່ອເລີ່ມເພີ່ມຂໍ້ມູນສາຂາວິຊາ</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 5%">ID</th>
                                <th style="width: 25%">
                                    <i class="fas fa-graduation-cap me-1"></i>ຊື່ສາຂາວິຊາ
                                </th>
                                <th style="width: 20%">
                                    <i class="fas fa-building me-1"></i>ພາກວິຊາ
                                </th>
                                <th style="width: 30%">
                                    <i class="fas fa-info-circle me-1"></i>ລາຍລະອຽດ
                                </th>
                                <th style="width: 10%" class="text-center">
                                    <i class="fas fa-users me-1"></i>ນັກສຶກສາ
                                </th>
                                <th style="width: 10%" class="text-center">
                                    <i class="fas fa-cogs me-1"></i>ການຈັດການ
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($majors as $major): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo $major['major_id']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo $major['major_name']; ?></strong>
                                </td>
                                <td>
                                    <span class="department-badge"><?php echo $major['department']; ?></span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $desc = $major['description'] ?? '';
                                        if (strlen($desc) > 50) {
                                            echo substr($desc, 0, 50) . '...';
                                        } elseif (!empty($desc)) {
                                            echo $desc;
                                        } else {
                                            echo '<em class="text-secondary">ບໍ່ມີລາຍລະອຽດ</em>';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column gap-1">
                                        <?php if ($major['student_count'] > 0): ?>
                                        <span class="student-count-badge total">
                                            ທັງໝົດ: <?php echo $major['student_count']; ?>
                                        </span>
                                        <?php if ($major['active_students'] > 0): ?>
                                        <span class="student-count-badge active">
                                            ກຳລັງຮຽນ: <?php echo $major['active_students']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($major['graduated_students'] > 0): ?>
                                        <span class="student-count-badge graduated">
                                            ຈົບແລ້ວ: <?php echo $major['graduated_students']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="admin_majors.php?action=edit&id=<?php echo $major['major_id']; ?>" class="btn btn-sm btn-warning" title="ແກ້ໄຂ">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $major['major_id']; ?>" title="ລຶບ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Modal ຢືນຢັນການລຶບ -->
                                    <div class="modal fade" id="deleteModal<?php echo $major['major_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                                        ຢືນຢັນການລຶບຂໍ້ມູນ
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-3">
                                                        <i class="fas fa-graduation-cap fa-3x text-danger mb-3"></i>
                                                        <h6>ທ່ານຕ້ອງການລຶບສາຂາວິຊານີ້ແທ້ບໍ່?</h6>
                                                        <div class="alert alert-light border">
                                                            <strong>ສາຂາວິຊາ:</strong> <?php echo $major['major_name']; ?><br>
                                                            <strong>ພາກວິຊາ:</strong> <?php echo $major['department']; ?><br>
                                                            <strong>ນັກສຶກສາ:</strong> <?php echo $major['student_count']; ?> ຄົນ
                                                        </div>
                                                        <?php if ($major['student_count'] > 0): ?>
                                                        <p class="text-danger mb-0">
                                                            <i class="fas fa-warning me-1"></i>
                                                            <strong>ບໍ່ສາມາດລຶບໄດ້:</strong> ມີນັກສຶກສາໃນສາຂານີ້
                                                        </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i> ຍົກເລີກ
                                                    </button>
                                                    <?php if ($major['student_count'] == 0): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="major_id" value="<?php echo $major['major_id']; ?>">
                                                        <button type="submit" name="delete_major" class="btn btn-danger">
                                                            <i class="fas fa-trash-alt me-1"></i> ຢືນຢັນການລຶບ
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-danger" disabled>
                                                        <i class="fas fa-ban me-1"></i> ບໍ່ສາມາດລຶບໄດ້
                                                    </button>
                                                    <?php endif; ?>
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
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ມຸມມອງການ໌ດ -->
        <div id="cardView" class="d-none">
            <?php if (!empty($majors_by_department)): ?>
                <?php foreach ($majors_by_department as $department => $dept_majors): ?>
                <div class="department-section">
                    <div class="department-header">
                        <h4 class="mb-0">
                            <i class="fas fa-building me-2"></i><?php echo $department; ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($dept_majors); ?> ສາຂາ</span>
                        </h4>
                    </div>
                    <div class="row">
                        <?php foreach ($dept_majors as $major): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="major-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0"><?php echo $major['major_name']; ?></h6>
                                    <span class="badge bg-primary"><?php echo $major['major_id']; ?></span>
                                </div>
                                
                                <?php if (!empty($major['description'])): ?>
                                <p class="text-muted small mb-3">
                                    <?php 
                                    $desc = $major['description'];
                                    echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc; 
                                    ?>
                                </p>
                                <?php else: ?>
                                <p class="text-secondary small mb-3"><em>ບໍ່ມີລາຍລະອຽດ</em></p>
                                <?php endif; ?>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h6 class="text-warning mb-0"><?php echo $major['student_count']; ?></h6>
                                            <small class="text-muted">ທັງໝົດ</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h6 class="text-success mb-0"><?php echo $major['active_students']; ?></h6>
                                            <small class="text-muted">ກຳລັງຮຽນ</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h6 class="text-info mb-0"><?php echo $major['graduated_students']; ?></h6>
                                        <small class="text-muted">ຈົບແລ້ວ</small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="admin_majors.php?action=edit&id=<?php echo $major['major_id']; ?>" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-edit me-1"></i> ແກ້ໄຂ
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $major['major_id']; ?>">
                                        <i class="fas fa-trash-alt me-1"></i> ລຶບ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus ໃສ່ຊ່ອງຄົ້ນຫາ
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && !searchInput.value) {
                setTimeout(() => searchInput.focus(), 100);
            }
            
            // ການຈັດການ responsive sidebar
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            
            if (navbarToggler && sidebar) {
                navbarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
                
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 992) {
                        sidebar.classList.remove('show');
                    }
                });
            }
            
            // ການ validation ສຳລັບຟອມ
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const majorName = form.querySelector('input[name="major_name"]');
                    const department = form.querySelector('input[name="department"]');
                    
                    if (majorName) {
                        if (majorName.value.trim().length < 2) {
                            e.preventDefault();
                            alert('ຊື່ສາຂາວິຊາຕ້ອງມີຢ່າງໜ້ອຍ 2 ໂຕອັກສອນ');
                            majorName.focus();
                            return;
                        }
                    }
                    
                    if (department) {
                        if (department.value.trim().length < 2) {
                            e.preventDefault();
                            alert('ຊື່ພາກວິຊາຕ້ອງມີຢ່າງໜ້ອຍ 2 ໂຕອັກສອນ');
                            department.focus();
                            return;
                        }
                    }
                });
            });
        });
        
        // ສະຫຼັບລະຫວ່າງມຸມມອງຕາຕະລາງ ແລະ ການ໌ດ
        function toggleView() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');
            
            if (tableView.classList.contains('d-none')) {
                // ສະແດງມຸມມອງຕາຕະລາງ
                tableView.classList.remove('d-none');
                cardView.classList.add('d-none');
                viewIcon.className = 'fas fa-th-large';
                viewText.textContent = 'ມຳມອງການ໌ດ';
            } else {
                // ສະແດງມຸມມອງການ໌ດ
                tableView.classList.add('d-none');
                cardView.classList.remove('d-none');
                viewIcon.className = 'fas fa-th-list';
                viewText.textContent = 'ມຸມມອງຕາຕະລາງ';
            }
        }
        
        // ຟັງຊັນສຳລັບການ export ຂໍ້ມູນ (ຖ້າຕ້ອງການໃນອະນາຄົດ)
        function exportMajors() {
            console.log('Export majors function placeholder');
        }
        
        // ເພີ່ມ animation ເມື່ອ hover ໃສ່ການ໌ດ
        document.addEventListener('DOMContentLoaded', function() {
            const majorCards = document.querySelectorAll('.major-card');
            majorCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderLeft = '4px solid var(--secondary-color)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderLeft = 'none';
                });
            });
        });
    </script>
</body>
</html>