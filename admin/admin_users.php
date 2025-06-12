<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ກວດສອບວ່າເປັນ admin ຫຼືບໍ່
if ($_SESSION['role'] !== 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// ຟັງຊັນສໍາລັບການເພີ່ມຜູ້ໃຊ້
function addUser($conn, $username, $email, $password, $role) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO users (username, email, password, role) 
              VALUES ('$username', '$email', '$hashed_password', '$role')";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການແກ້ໄຂຜູ້ໃຊ້
function updateUser($conn, $user_id, $username, $email, $role, $password = null) {
    if ($password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE users 
                  SET username = '$username', 
                      email = '$email', 
                      role = '$role', 
                      password = '$hashed_password'
                  WHERE user_id = $user_id";
    } else {
        $query = "UPDATE users 
                  SET username = '$username', 
                      email = '$email', 
                      role = '$role' 
                  WHERE user_id = $user_id";
    }
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສໍາລັບການລຶບຢູ້ໃຊ້
function deleteUser($conn, $user_id) {
    // ບໍ່ໃຫ້ລຶບຜູ້ໃຊ້ທີ່ກຳລັງເຂົ້າສູ່ລະບົບ
    if ($user_id == $_SESSION['user_id']) {
        return false;
    }
    
    $query = "DELETE FROM users WHERE user_id = $user_id";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຈັດການການສົ່ງຟອມ
$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = clean($conn, $_POST['username']);
        $email = clean($conn, $_POST['email']);
        $password = $_POST['password'];
        $role = clean($conn, $_POST['role']);
        
        // ກວດສອບວ່າຊື່ຜູ້ໃຊ້ຊໍ້າກັນຫຼືບໍ່
        $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ຊື່ຜູ້ໃຊ້ຫຼືອີເມວນີ້ມີໃນລະບົບແລ້ວ";
        } else {
            if (addUser($conn, $username, $email, $password, $role)) {
                $message = "ເພີ່ມຜູ້ໃຊ້ໃໝ່ຮຽບຮ້ອຍແລ້ວ";
                // ຣີເຊັດຟອມ
                $username = $email = $password = $role = '';
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຜູ້ໃຊ້: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        $user_id = intval($_POST['user_id']);
        $username = clean($conn, $_POST['username']);
        $email = clean($conn, $_POST['email']);
        $role = clean($conn, $_POST['role']);
        $password = !empty($_POST['password']) ? $_POST['password'] : null;
        
        // ກວດສອບວ່າຊື່ຜູ້ໃຊ້ຫຼືອີເມວຊໍ້າກັບຄົນອື່ນຫຼືບໍ່
        $check_query = "SELECT * FROM users WHERE (username = '$username' OR email = '$email') AND user_id != $user_id";
        $check_result = $conn->query($check_query);
        
        if ($check_result->num_rows > 0) {
            $error = "ຊື່ຜູ້ໃຊ້ຫຼືອີເມວນີ້ມີຄົນອື່ນໃຊ້ແລ້ວ";
        } else {
            if (updateUser($conn, $user_id, $username, $email, $role, $password)) {
                $message = "ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້ຮຽບຮ້ອຍແລ້ວ";
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        if ($user_id == $_SESSION['user_id']) {
            $error = "ບໍ່ສາມາດລຶບບັນຊີຂອງຕົນເອງໄດ້";
        } else {
            if (deleteUser($conn, $user_id)) {
                $message = "ລຶບຜູ້ໃຊ້ຮຽບຮ້ອຍແລ້ວ";
                // ຣີໄດເຣັກກັບໄປໜ້າລາຍການ
                header("Location: admin_users.php?deleted=1");
                exit();
            } else {
                $error = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຜູ້ໃຊ້: " . $conn->error;
            }
        }
    }
}

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ສໍາລັບການແກ້ໄຂ
$edit_data = null;
if ($action === 'edit' && $user_id > 0) {
    $edit_query = "SELECT * FROM users WHERE user_id = $user_id";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
    } else {
        $error = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້ທີ່ຕ້ອງການແກ້ໄຂ";
    }
}

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດສໍາລັບສະແດງໃນຕາຕະລາງ
$users_query = "SELECT * FROM users ORDER BY user_id DESC";
$users_result = $conn->query($users_query);
$users = [];
if ($users_result->num_rows > 0) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// ແປງບົດບາດເປັນພາສາລາວ
function getRoleText($role) {
    switch ($role) {
        case 'admin':
            return 'ຜູ້ບໍລິຫານລະບົບ';
        case 'teacher':
            return 'ອາຈານ';
        default:
            return $role;
    }
}

// ແປງວັນທີເປັນພາສາລາວ
function formatDateLao($date) {
    if ($date) {
        return date('d/m/Y H:i', strtotime($date));
    }
    return '-';
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ຈັດການຜູ້ໃຊ້ລະບົບ - ວິທະຍາໄລເຕັກນິກ</title>
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
            font-family: 'Noto Sans Lao', sans-serif;
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
        
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .role-admin {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .role-teacher {
            background-color: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
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
        
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #2ecc71; }
        
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
            <h2><i class="fas fa-users-cog"></i> ຈັດການຜູ້ໃຊ້ລະບົບ</h2>
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="admin_users.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> ເພີ່ມຜູ້ໃຊ້ໃໝ່
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
            <i class="fas fa-check-circle me-2"></i> ລຶບຜູ້ໃຊ້ລະບົບຮຽບຮ້ອຍແລ້ວ
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'add'): ?>
        <!-- ຟອມເພີ່ມຜູ້ໃຊ້ -->
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus text-primary"></i> ເພີ່ມຜູ້ໃຊ້ໃໝ່</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້ *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <small class="text-muted">ໃຊ້ພຽງແຕ່ຕົວອັກສອນ ຕົວເລກ ແລະ _ ເທົ່ານັ້ນ</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">ອີເມວ *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">ລະຫັດຜ່ານ *</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6" onkeyup="checkPasswordStrength()">
                            <div id="password-strength" class="password-strength"></div>
                            <small class="text-muted">ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">ບົດບາດ *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">ເລືອກບົດບາດ</option>
                                <option value="admin">ຢູ້ບໍລິຫານລະບົບ</option>
                                <option value="teacher">ອາຈານ</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກຂໍ້ມູນ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'edit' && $edit_data): ?>
        <!-- ຟອມແກ້ໄຂຜູ້ໃຊ້ -->
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-edit text-warning"></i> ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="user_id" value="<?php echo $edit_data['user_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້ *</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo $edit_data['username']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">ອີເມວ *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $edit_data['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">ລະຫັດຜ່ານໃໝ່</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" onkeyup="checkPasswordStrength()">
                            <div id="password-strength" class="password-strength"></div>
                            <small class="text-muted">ຫາກບໍ່ຕ້ອງການປ່ຽນລະຫັດຜ່ານ ໃຫ້ປ່ອຍວ່າງໄວ້</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">ບົດບາດ *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin" <?php echo ($edit_data['role'] == 'admin') ? 'selected' : ''; ?>>ຜູ້ບໍລິຫານລະບົບ</option>
                                <option value="teacher" <?php echo ($edit_data['role'] == 'teacher') ? 'selected' : ''; ?>>ອາຈານ</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> ຍົກເລີກ
                        </a>
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> ບັນທຶກການແກ້ໄຂ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ສະແດງລາຍການຜູ້ໃຊ້ -->
        <div class="card dashboard-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users text-primary"></i> ລາຍການຜູ້ໃຊ້ລະບົບ</h5>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="searchInput" class="form-control" placeholder="ຄົ້ນຫາ...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> ຍັງບໍ່ມີຜູ້ໃຊ້ລະບົບ
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ຊື່ຜູ້ໃຊ້</th>
                                <th>ອີເມວ</th>
                                <th>ບົດບາດ</th>
                                <th>ວັນທີ່ສ້າງ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <strong><?php echo $user['username']; ?></strong>
                                    <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-primary ms-2">ທ່ານ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo getRoleText($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDateLao($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="admin_users.php?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> ແກ້ໄຂ
                                        </a>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['user_id']; ?>">
                                            <i class="fas fa-trash-alt"></i> ລຶບ
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <!-- ໜ້າຕ່າງຢືນຢັນການລຶບ -->
                                    <div class="modal fade" id="deleteModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel">ຢືນຢັນການລຶບຂໍ້ມູນ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="text-center mb-3">
                                                        <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                                                        <p>ທ່ານຕ້ອງການລຶບຜູ້ໃຊ້ "<strong><?php echo $user['username']; ?></strong>" ແທ້ບໍ່?</p>
                                                        <p class="text-danger"><strong>ຄຳເຕືອນ:</strong> ການກະທຳນີ້ບໍ່ສາມາດຍົກເລີກໄດ້</p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-danger">
                                                            <i class="fas fa-trash-alt me-1"></i> ຢືນຢັນການລຶບ
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ສະຖິຕິຜູ້ໃຊ້ -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card dashboard-card bg-primary text-white">
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h5>ຜູ້ບໍລິຫານລະບົບ</h5>
                        <h2><?php echo count(array_filter($users, function($u) { return $u['role'] == 'admin'; })); ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card dashboard-card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h5>ອາຈານ</h5>
                        <h2><?php echo count(array_filter($users, function($u) { return $u['role'] == 'teacher'; })); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ຄຳແນະນຳ -->
        <div class="card dashboard-card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-lightbulb text-warning"></i> ຄຳແນະນຳການຈັດການຜູ້ໃຊ້</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-shield-alt text-primary me-2"></i>ຄວາມປອດໄພ</h6>
                        <ul>
                            <li>ໃຊ້ລະຫັດຜ່ານທີ່ແຂງແຮງ (ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ)</li>
                            <li>ປ່ຽນລະຫັດຜ່ານເປັນປະຈຳ</li>
                            <li>ບໍ່ແບ່ງປັນບັນຊີກັບຄົນອື່ນ</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-user-tag text-success me-2"></i>ບົດບາດ</h6>
                        <ul>
                            <li><strong>ຜູ້ບໍລິຫານລະບົບ:</strong> ສາມາດຈັດການທຸກຢ່າງໃນລະບົບ</li>
                            <li><strong>ອາຈານ:</strong> ສາມາດເບິ່ງແລະແກ້ໄຂຜົນການຮຽນ</li>
                        </ul>
                    </div>
                </div>
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
                });
            }
            
            // ຈັດການກັບການປັບຂະໜາດໜ້າຈໍ
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
        });
        
        // ກວດສອບຄວາມແຂງແຮງຂອງລະຫັດຜ່ານ
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = '';
            
            // ກວດສອບຄວາມຍາວ
            if (password.length >= 8) strength += 1;
            
            // ກວດສອບຕົວອັກສອນນ້ອຍ
            if (/[a-z]/.test(password)) strength += 1;
            
            // ກວດສອບຕົວອັກສອນໃຫຍ່
            if (/[A-Z]/.test(password)) strength += 1;
            
            // ກວດສອບຕົວເລກ
            if (/[0-9]/.test(password)) strength += 1;
            
            // ກວດສອບສັນຍາລັກ
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            switch(strength) {
                case 0:
                case 1:
                case 2:
                    feedback = '<span class="strength-weak">ອ່ອນ</span>';
                    break;
                case 3:
                case 4:
                    feedback = '<span class="strength-medium">ປານກາງ</span>';
                    break;
                case 5:
                    feedback = '<span class="strength-strong">ແຂງແຮງ</span>';
                    break;
            }
            
            strengthDiv.innerHTML = 'ຄວາມແຂງແຮງ: ' + feedback;
        }
        
        // ສະແດງ/ເຊື່ອງລະຫັດຜ່ານ
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>