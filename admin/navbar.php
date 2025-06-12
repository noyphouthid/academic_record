<?php
// ກວດສອບວ່າມີ session ຫຼືບໍ່
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ດຶງຊື່ໄຟລ໌ປັດຈຸບັນ
$current_file = basename($_SERVER['PHP_SELF']);

// ກຳນົດ path ສຳລັບ logout (ປັບຕາມຕຳແໜ່ງໄຟລ໌)
$logout_path = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../logout.php' : 'logout.php';
$index_path = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../index.php' : 'index.php';
?>

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
                    <a class="nav-link" href="<?php echo $logout_path; ?>">
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
            <div class="user-name"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?></div>
            <div class="user-role"><?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'ຜູ້ບໍລິຫານລະບົບ' : 'ອາຈານ'; ?></div>
        </div>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_dashboard.php') ? 'active' : ''; ?>" href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i> ໜ້າຫຼັກ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_students.php' || $current_file == 'import_students.php') ? 'active' : ''; ?>" href="admin_students.php">
                <i class="fas fa-user-graduate"></i> ຈັດການນັກສຶກສາ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_grades.php' || $current_file == 'import_grades.php') ? 'active' : ''; ?>" href="admin_grades.php">
                <i class="fas fa-chart-line"></i> ຈັດການຜົນການຮຽນ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'edit_grades.php') ? 'active' : ''; ?>" href="edit_grades.php">
                <i class="fas fa-edit"></i> ແກ້ໄຂເກຣດ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_subjects.php') ? 'active' : ''; ?>" href="admin_subjects.php">
                <i class="fas fa-book"></i> ຈັດການລາຍວິຊາ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_majors.php') ? 'active' : ''; ?>" href="admin_majors.php">
                <i class="fas fa-graduation-cap"></i> ຈັດການສາຂາວິຊາ
            </a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_users.php') ? 'active' : ''; ?>" href="admin_users.php">
                <i class="fas fa-users-cog"></i> ຈັດການຜູ້ໃຊ້ລະບົບ
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_file == 'admin_reports.php') ? 'active' : ''; ?>" href="admin_reports.php">
                <i class="fas fa-file-alt"></i> ລາຍງານ
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo $index_path; ?>" target="_blank">
                <i class="fas fa-external-link-alt"></i> ເບິ່ງໜ້າເວັບໄຊຕ໌
            </a>
        </li>
    </ul>
</div>

<script>
// JavaScript สำหรับ Responsive Sidebar
document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (navbarToggler && sidebar) {
        navbarToggler.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // ปิด sidebar เมื่อคลิกนอก sidebar ในโหมดมือถือ
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(event.target) && !navbarToggler.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
        
        // จัดการเมื่อปรับขนาดหน้าจอ
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('show');
            }
        });
    }
});
</script>