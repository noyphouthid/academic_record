<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ຟັງຊັນສໍາລັບການເພີ່ມວິຊາ
function addSubject($conn, $subject_code, $subject_name, $credit) {
    $query = "INSERT INTO subjects (subject_code, subject_name, credit) 
              VALUES ('$subject_code', '$subject_name', $credit)";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການແກ້ໄຂວິຊາ
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

// ຟັງຊັນສໍາລັບການລຶບວິຊາ
function deleteSubject($conn, $subject_code) {
    // ກວດສອບວ່າມີຜົນການຮຽນຂອງວິຊານີ້ຫຼືບໍ່
    $check_grades_query = "SELECT COUNT(*) as count FROM grades WHERE subject_code = '$subject_code'";
    $check_result = $conn->query($check_grades_query);
    $grade_count = $check_result->fetch_assoc()['count'];
    
    if ($grade_count > 0) {
        return "ບໍ່ສາມາດລຶບວິຊານີ້ໄດ້ເພາະມີຜົນການຮຽນທີ່ກ່ຽວຂ້ອງ $grade_count ລາຍການ";
    }
    
    // ລຶບວິຊາ
    $delete_query = "DELETE FROM subjects WHERE subject_code = '$subject_code'";
    
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
$subject_code = isset($_GET['id']) ? clean($conn, $_GET['id']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        $subject_name = clean($conn, $_POST['subject_name']);
        $credit = intval($_POST['credit']);
        
        // ກວດສອບວ່າລະຫັດວິຊາຊໍ້າກັນຫຼືບໍ່
        $check_query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ລະຫັດວິຊານີ້ມີໃນລະບົບແລ້ວ";
        } else {
            if (addSubject($conn, $subject_code, $subject_name, $credit)) {
                $message = "ເພີ່ມຂໍ້ມູນວິຊາຮຽບຮ້ອຍແລ້ວ";
                // ຣີເຊັດຟອມ
                $subject_code = $subject_name = $credit = '';
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        $subject_name = clean($conn, $_POST['subject_name']);
        $credit = intval($_POST['credit']);
        
        if (updateSubject($conn, $subject_code, $subject_name, $credit)) {
            $message = "ແກ້ໄຂຂໍ້ມູນວິຊາຮຽບຮ້ອຍແລ້ວ";
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $conn->error;
        }
    } elseif (isset($_POST['delete_subject'])) {
        $subject_code = clean($conn, $_POST['subject_code']);
        
        $result = deleteSubject($conn, $subject_code);
        if ($result === true) {
            $message = "ລຶບຂໍ້ມູນວິຊາຮຽບຮ້ອຍແລ້ວ";
            // ຣີໄດເຣັກກັບໄປໜ້າລາຍການ
            header("Location: admin_subjects.php?deleted=1");
            exit();
        } elseif (is_string($result)) {
            $error = $result;
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $conn->error;
        }
    }
}

// ດຶງຂໍ້ມູນວິຊາສໍາລັບການແກ້ໄຂ
$edit_data = null;
if ($action === 'edit' && !empty($subject_code)) {
    $edit_query = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບຂໍ້ມູນວິຊາທີ່ຕ້ອງການແກ້ໄຂ";
    }
}

// ດຶງຂໍ້ມູນວິຊາທັງໝົດສໍາລັບສະແດງໃນຕາຕະລາງ
$search = isset($_GET['search']) ? clean($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE subject_code LIKE '%$search%' OR subject_name LIKE '%$search%'";
}

$subjects_query = "SELECT * FROM subjects $search_condition ORDER BY subject_code";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
if ($subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// ນັບຈຳນວນວິຊາທັງໝົດ
$total_subjects_query = "SELECT COUNT(*) as total FROM subjects";
$total_subjects_result = $conn->query($total_subjects_query);
$total_subjects = $total_subjects_result->fetch_assoc()['total'];

// ນັບຈຳນວນໜ່ວຍກິດທັງໝົດ
$total_credits_query = "SELECT SUM(credit) as total FROM subjects";
$total_credits_result = $conn->query($total_credits_query);
$total_credits = $total_credits_result->fetch_assoc()['total'] ?? 0;
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
            background: linear-gradient(135deg, var(--secondary-color), #5dade2);
            color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
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
        
        .credit-badge {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
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
            <h2><i class="fas fa-book text-primary"></i> ຈັດການລາຍວິຊາ</h2>
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="admin_subjects.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> ເພີ່ມວິຊາໃໝ່
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
            <i class="fas fa-check-circle me-2"></i> ລຶບຂໍ້ມູນວິຊາຮຽບຮ້ອຍແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <!-- ສະຖິຕິພາບລວມ -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h3><?php echo $total_subjects; ?></h3>
                    <p><i class="fas fa-book me-2"></i>ວິຊາທັງໝົດໃນລະບົບ</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #58d68d);">
                    <h3><?php echo $total_credits; ?></h3>
                    <p><i class="fas fa-certificate me-2"></i>ໜ່ວຍກິດທັງໝົດ</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ຟອມເພີ່ມວິຊາ -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle text-primary"></i> ເພີ່ມວິຊາໃໝ່</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">ລະຫັດວິຊາ *</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                                <small class="text-muted">ເຊັ່ນ CS101, MATH201</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="credit" class="form-label">ໜ່ວຍກິດ *</label>
                                <select class="form-select" id="credit" name="credit" required>
                                    <option value="">ເລືອກໜ່ວຍກິດ</option>
                                    <option value="1">1 ໜ່ວຍກິດ</option>
                                    <option value="2">2 ໜ່ວຍກິດ</option>
                                    <option value="3">3 ໜ່ວຍກິດ</option>
                                    <option value="4">4 ໜ່ວຍກິດ</option>
                                    <option value="5">5 ໜ່ວຍກິດ</option>
                                    <option value="6">6 ໜ່ວຍກິດ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="subject_name" class="form-label">ຊື່ວິຊາ *</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                        <small class="text-muted">ຊື່ວິຊາເປັນພາສາລາວ ຫຼື ອັງກິດ</small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_subjects.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_subject" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ຟອມແກ້ໄຂວິຊາ -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit text-warning"></i> ແກ້ໄຂຂໍ້ມູນວິຊາ</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="subject_code" value="<?php echo $edit_data['subject_code']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subject_code_display" class="form-label">ລະຫັດວິຊາ</label>
                                <input type="text" class="form-control bg-light" id="subject_code_display" value="<?php echo $edit_data['subject_code']; ?>" readonly>
                                <small class="text-muted">ບໍ່ສາມາດແກ້ໄຂລະຫັດວິຊາໄດ້</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="credit" class="form-label">ໜ່ວຍກິດ *</label>
                                <select class="form-select" id="credit" name="credit" required>
                                    <option value="">ເລືອກໜ່ວຍກິດ</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($edit_data['credit'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> ໜ່ວຍກິດ
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="subject_name" class="form-label">ຊື່ວິຊາ *</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" value="<?php echo $edit_data['subject_name']; ?>" required>
                        <small class="text-muted">ຊື່ວິຊາເປັນພາສາລາວ ຫຼື ອັງກິດ</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="admin_subjects.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_subject" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                        <button type="submit" name="delete_subject" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> ລຶບວິຊາ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- ຕາຕະລາງວິຊາ -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book text-primary"></i> ລາຍວິຊາ</h5>
            </div>
            <div class="card-body p-4">
                <form method="GET" action="" class="search-box
                    mb-4 d-flex justify-content-between align-items-center">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="ຄົ້ນຫາວິຊາ..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <a href="admin_subjects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> ລີເຊັດ
                    </a>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ຊື່ວິຊາ</th>
                                <th>ໜ່ວຍກິດ</th>
                                <th class="text-center">ເຄື່ອນໄຫວ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($subjects) > 0): ?>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo $subject['subject_code']; ?></td>
                                <td><?php echo $subject['subject_name']; ?></td>
                                <td><?php echo $subject['credit']; ?> ໜ່ວຍກິດ</td>
                                <td class="text-center">
                                    <a href="admin_subjects.php?action=edit&id=<?php echo $subject['subject_code']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> ແກ້ໄຂ
                                    </a>
                                    <?php if ($subject['credit'] > 0): ?>
                                    <span class="badge credit-badge"><?php echo $subject['credit']; ?> ໜ່ວຍກິດ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">ບໍ່ພົບວິຊາໃນລາຍການ</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span class="text-muted
                            ">ຈຳນວນວິຊາ: <?php echo $total_subjects; ?></span>
                        <span class="text-muted">, ໜ່ວຍກິດລວມ: <?php echo $total_credits; ?></span>
                    </div>
                    <div>
                        <a href="admin_subjects.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> ເພີ່ມວິຊາໃໝ່
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
