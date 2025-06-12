<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ຕົວແປຮັບຄ່າ
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'student';
$search_value = isset($_GET['search_value']) ? clean($conn, $_GET['search_value']) : '';
$subject_filter = isset($_GET['subject_filter']) ? clean($conn, $_GET['subject_filter']) : '';
$year_filter = isset($_GET['year_filter']) ? intval($_GET['year_filter']) : 0;
$semester_filter = isset($_GET['semester_filter']) ? intval($_GET['semester_filter']) : 0;

// ຕົວແປສຳລັບຜົນລັບ
$message = '';
$error = '';
$grades_data = [];
$student_info = null;

// ຟັງຊັນສຳລັບການອັບເດດເກຣດ
function updateGrade($conn, $grade_id, $new_grade) {
    $grade_id = clean($conn, $grade_id);
    $new_grade = clean($conn, $new_grade);
    
    $query = "UPDATE grades SET grade = '$new_grade' WHERE grade_id = $grade_id";
    
    if ($conn->query($query) === TRUE) {
        return true;
    } else {
        return false;
    }
}

// ຟັງຊັນສຳລັບການອັບເດດເກຣດຫຼາຍລາຍການພ້ອມກັນ
function bulkUpdateGrades($conn, $grade_updates) {
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($grade_updates as $grade_id => $new_grade) {
        if (!empty($new_grade)) {
            if (updateGrade($conn, $grade_id, $new_grade)) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "ບໍ່ສາມາດອັບເດດເກຣດ ID: $grade_id";
            }
        }
    }
    
    return [
        'success' => $success_count,
        'error' => $error_count,
        'errors' => $errors
    ];
}

// ຈັດການການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_single_grade'])) {
        // ອັບເດດເກຣດລາຍການດຽວ
        $grade_id = $_POST['grade_id'];
        $new_grade = $_POST['new_grade'];
        
        if (updateGrade($conn, $grade_id, $new_grade)) {
            $message = "ອັບເດດເກຣດສຳເລັດແລ້ວ";
        } else {
            $error = "ເກີດຂໍ້ຜິດພາດໃນການອັບເດດເກຣດ: " . $conn->error;
        }
    } elseif (isset($_POST['bulk_update_grades'])) {
        // ອັບເດດເກຣດຫຼາຍລາຍການ
        $grade_updates = $_POST['grades'];
        $result = bulkUpdateGrades($conn, $grade_updates);
        
        if ($result['success'] > 0) {
            $message = "ອັບເດດເກຣດສຳເລັດ {$result['success']} ລາຍການ";
            if ($result['error'] > 0) {
                $message .= " (ມີຂໍ້ຜິດພາດ {$result['error']} ລາຍການ)";
            }
        } else {
            $error = "ບໍ່ສາມາດອັບເດດເກຣດໄດ້";
        }
    }
}

// ຄົ້ນຫາຂໍ້ມູນ
if (!empty($search_value)) {
    if ($search_type === 'student') {
        // ຄົ້ນຫາຕາມນັກສຶກສາ
        $student_query = "SELECT s.*, m.major_name, m.department 
                         FROM students s 
                         JOIN majors m ON s.major_id = m.major_id 
                         WHERE s.student_id = '$search_value'";
        $student_result = $conn->query($student_query);
        
        if ($student_result && $student_result->num_rows > 0) {
            $student_info = $student_result->fetch_assoc();
            
            // ສ້າງ WHERE clause ສຳລັບຟິວເຕີ
            $where_conditions = ["g.student_id = '$search_value'"];
            
            if (!empty($subject_filter)) {
                $where_conditions[] = "g.subject_code = '$subject_filter'";
            }
            
            if ($year_filter > 0) {
                $where_conditions[] = "g.study_year = $year_filter";
            }
            
            if ($semester_filter > 0) {
                $where_conditions[] = "g.semester = $semester_filter";
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // ດຶງຂໍ້ມູນເກຣດ
            $grades_query = "SELECT g.*, s.subject_name, s.credit 
                           FROM grades g 
                           JOIN subjects s ON g.subject_code = s.subject_code 
                           WHERE $where_clause
                           ORDER BY g.study_year, g.semester, g.subject_code";
            
            $grades_result = $conn->query($grades_query);
            
            if ($grades_result && $grades_result->num_rows > 0) {
                while ($row = $grades_result->fetch_assoc()) {
                    $grades_data[] = $row;
                }
            }
        } else {
            $error = "ບໍ່ພົບນັກສຶກສາລະຫັດ: $search_value";
        }
    } elseif ($search_type === 'subject') {
        // ຄົ້ນຫາຕາມວິຊາ
        $where_conditions = ["g.subject_code = '$search_value'"];
        
        if ($year_filter > 0) {
            $where_conditions[] = "g.study_year = $year_filter";
        }
        
        if ($semester_filter > 0) {
            $where_conditions[] = "g.semester = $semester_filter";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $grades_query = "SELECT g.*, s.subject_name, s.credit, st.firstname, st.lastname, m.major_name
                        FROM grades g 
                        JOIN subjects s ON g.subject_code = s.subject_code 
                        JOIN students st ON g.student_id = st.student_id
                        JOIN majors m ON st.major_id = m.major_id
                        WHERE $where_clause
                        ORDER BY st.student_id";
        
        $grades_result = $conn->query($grades_query);
        
        if ($grades_result && $grades_result->num_rows > 0) {
            while ($row = $grades_result->fetch_assoc()) {
                $grades_data[] = $row;
            }
        } else {
            $error = "ບໍ່ພົບຂໍ້ມູນເກຣດສຳລັບວິຊາ: $search_value";
        }
    }
}

// ດຶງຂໍ້ມູນວິຊາທັງໝົດສຳລັບ dropdown
$subjects_query = "SELECT * FROM subjects ORDER BY subject_code";
$subjects_result = $conn->query($subjects_query);
$subjects = [];
if ($subjects_result && $subjects_result->num_rows > 0) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// ດຶງຂໍ້ມູນນັກສຶກສາທັງໝົດສຳລັບ suggestions
$students_query = "SELECT student_id, firstname, lastname FROM students WHERE status = 'studying' ORDER BY student_id LIMIT 50";
$students_result = $conn->query($students_query);
$students = [];
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ແກ້ໄຂເກຣດ - ວິທະຍາໄລເຕັກນິກ</title>
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
            transform: translateY(-2px);
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
        
        .search-filters {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        
        .grade-input {
            width: 80px;
            text-align: center;
            font-weight: bold;
        }
        
        .grade-cell {
            position: relative;
        }
        
        .grade-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: inline-block;
            min-width: 35px;
            text-align: center;
        }
        
        .grade-A { background-color: #2ecc71; }
        .grade-B\+ { background-color: #27ae60; }
        .grade-B { background-color: #3498db; }
        .grade-C\+ { background-color: #f39c12; }
        .grade-C { background-color: #e67e22; }
        .grade-D\+ { background-color: #e74c3c; }
        .grade-D { background-color: #c0392b; }
        .grade-F { background-color: #7f8c8d; }
        .grade-W { background-color: #95a5a6; }
        .grade-I { background-color: #9b59b6; }
        
        .student-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .gpa-display {
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
        }
        
        .quick-actions {
            position: sticky;
            top: 20px;
            z-index: 10;
        }
        
        .grade-history {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .bulk-edit-bar {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .bulk-edit-bar.show {
            display: block;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .edit-mode .grade-display {
            display: none;
        }
        
        .edit-mode .grade-input {
            display: inline-block;
        }
        
        .grade-display {
            display: inline-block;
        }
        
        .grade-input {
            display: none;
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
        
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .suggestion-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-input-container {
            position: relative;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> ແກ້ໄຂເກຣດ</h2>
            <div>
                <button id="toggleEditMode" class="btn btn-warning" style="display: none;">
                    <i class="fas fa-edit"></i> ເຂົ້າສູ່ໂໝດແກ້ໄຂ
                </button>
                <a href="admin_grades.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບໄປຈັດການເກຣດ
                </a>
            </div>
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
        
        <!-- ຟອມຄົ້ນຫາ -->
        <div class="search-filters">
            <form method="GET" action="">
                <h5 class="mb-3"><i class="fas fa-search text-primary"></i> ຄົ້ນຫາແລະກັ່ນຕອງ</h5>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">ປະເພດການຄົ້ນຫາ</label>
                        <select name="search_type" class="form-select" onchange="toggleSearchInput()">
                            <option value="student" <?php echo ($search_type == 'student') ? 'selected' : ''; ?>>ຕາມນັກສຶກສາ</option>
                            <option value="subject" <?php echo ($search_type == 'subject') ? 'selected' : ''; ?>>ຕາມວິຊາ</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label" id="search-label">
                            <?php echo ($search_type == 'student') ? 'ລະຫັດນັກສຶກສາ' : 'ລະຫັດວິຊາ'; ?>
                        </label>
                        <div class="search-input-container">
                            <input type="text" name="search_value" class="form-control" 
                                   value="<?php echo $search_value; ?>" 
                                   placeholder="<?php echo ($search_type == 'student') ? 'ປ້ອນລະຫັດນັກສຶກສາ' : 'ປ້ອນລະຫັດວິຊາ'; ?>"
                                   id="searchInput" autocomplete="off">
                            <div id="suggestions" class="suggestions-dropdown"></div>
                        </div>
                    </div>
                    
                    <?php if ($search_type == 'student'): ?>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">ວິຊາ (ເສີມ)</label>
                        <select name="subject_filter" class="form-select">
                            <option value="">ທຸກວິຊາ</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_code']; ?>" 
                                    <?php echo ($subject_filter == $subject['subject_code']) ? 'selected' : ''; ?>>
                                <?php echo $subject['subject_code']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">ປີການສຶກສາ</label>
                        <select name="year_filter" class="form-select">
                            <option value="0">ທຸກປີ</option>
                            <option value="1" <?php echo ($year_filter == 1) ? 'selected' : ''; ?>>ປີ 1</option>
                            <option value="2" <?php echo ($year_filter == 2) ? 'selected' : ''; ?>>ປີ 2</option>
                            <option value="3" <?php echo ($year_filter == 3) ? 'selected' : ''; ?>>ປີ 3</option>
                            <option value="4" <?php echo ($year_filter == 4) ? 'selected' : ''; ?>>ປີ 4</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">ພາກຮຽນ</label>
                        <select name="semester_filter" class="form-select">
                            <option value="0">ທຸກພາກ</option>
                            <option value="1" <?php echo ($semester_filter == 1) ? 'selected' : ''; ?>>ພາກ 1</option>
                            <option value="2" <?php echo ($semester_filter == 2) ? 'selected' : ''; ?>>ພາກ 2</option>
                            <option value="3" <?php echo ($semester_filter == 3) ? 'selected' : ''; ?>>ພາກ 3</option>
                        </select>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> ຄົ້ນຫາ
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($student_info && $search_type == 'student'): ?>
        <!-- ຂໍ້ມູນນັກສຶກສາ -->
        <div class="student-summary">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4><i class="fas fa-user-graduate"></i> <?php echo $student_info['firstname'] . ' ' . $student_info['lastname']; ?></h4>
                    <p class="mb-1">ລະຫັດ: <strong><?php echo $student_info['student_id']; ?></strong></p>
                    <p class="mb-1">ສາຂາ: <strong><?php echo $student_info['major_name']; ?></strong></p>
                    <p class="mb-0">ພາກວິຊາ: <strong><?php echo $student_info['department']; ?></strong></p>
                </div>
                <div class="col-md-4">
                    <?php
                    // ຄຳນວນ GPA
                    $total_credit = 0;
                    $total_point = 0;
                    foreach ($grades_data as $grade) {
                        if ($grade['grade'] != 'W' && $grade['grade'] != 'I') {
                            $total_credit += $grade['credit'];
                            $total_point += ($grade['credit'] * gradeToPoint($grade['grade']));
                        }
                    }
                    $gpa = ($total_credit > 0) ? number_format($total_point / $total_credit, 2) : 0;
                    ?>
                    <div class="gpa-display">
                        <div>GPA</div>
                        <div class="<?php echo ($gpa >= 3.5) ? 'text-success' : (($gpa >= 2.5) ? 'text-warning' : 'text-danger'); ?>">
                            <?php echo $gpa; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($grades_data)): ?>
        <!-- Bulk Edit Bar -->
        <div class="bulk-edit-bar" id="bulkEditBar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-edit text-warning"></i>
                    <strong>ໂໝດແກ້ໄຂແບບກຸ່ມ</strong> - ເລືອກເກຣດທີ່ຕ້ອງການແກ້ໄຂ
                </div>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="saveAllChanges()">
                        <i class="fas fa-save"></i> ບັນທຶກທັງໝົດ
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEditMode()">
                        <i class="fas fa-times"></i> ຍົກເລີກ
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ຕາຕະລາງເກຣດ -->
        <div class="table-container">
            <form id="bulkEditForm" method="POST" action="">
                <input type="hidden" name="bulk_update_grades" value="1">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <?php if ($search_type == 'subject'): ?>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ຊື່-ນາມສະກຸນ</th>
                                <th>ສາຂາ</th>
                                <?php endif; ?>
                                <?php if ($search_type == 'student'): ?>
                                <th>ລະຫັດວິຊາ</th>
                                <th>ຊື່ວິຊາ</th>
                                <th>ໜ່ວຍກິດ</th>
                                <?php endif; ?>
                                <th>ປີ/ພາກ</th>
                                <th>ເກຣດປັດຈຸບັນ</th>
                                <th>ແກ້ໄຂເກຣດ</th>
                                <th>ການຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades_data as $grade): ?>
                            <tr id="row-<?php echo $grade['grade_id']; ?>">
                                <?php if ($search_type == 'subject'): ?>
                                <td>
                                    <strong><?php echo $grade['student_id']; ?></strong>
                                    <div class="grade-history">ເບິ່ງຜົນການຮຽນທັງໝົດ</div>
                                </td>
                                <td><?php echo $grade['firstname'] . ' ' . $grade['lastname']; ?></td>
                                <td><?php echo $grade['major_name']; ?></td>
                                <?php endif; ?>
                                
                                <?php if ($search_type == 'student'): ?>
                                <td>
                                    <strong><?php echo $grade['subject_code']; ?></strong>
                                </td>
                                <td><?php echo $grade['subject_name']; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $grade['credit']; ?></span>
                                </td>
                                <?php endif; ?>
                                
                                <td>
                                    <span class="badge bg-secondary">ປີ <?php echo $grade['study_year']; ?>/<?php echo $grade['semester']; ?></span>
                                </td>
                                
                                <td class="grade-cell">
                                    <span class="grade-display">
                                        <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                            <?php echo $grade['grade']; ?>
                                        </span>
                                    </span>
                                </td>
                                
                                <td class="grade-cell">
                                    <select name="grades[<?php echo $grade['grade_id']; ?>]" 
                                            class="form-select grade-input" 
                                            data-original="<?php echo $grade['grade']; ?>"
                                            onchange="markChanged(this)">
                                        <option value="">-- ເລືອກ --</option>
                                        <option value="A" <?php echo ($grade['grade'] == 'A') ? 'selected' : ''; ?>>A</option>
                                        <option value="B+" <?php echo ($grade['grade'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B" <?php echo ($grade['grade'] == 'B') ? 'selected' : ''; ?>>B</option>
                                        <option value="C+" <?php echo ($grade['grade'] == 'C+') ? 'selected' : ''; ?>>C+</option>
                                        <option value="C" <?php echo ($grade['grade'] == 'C') ? 'selected' : ''; ?>>C</option>
                                        <option value="D+" <?php echo ($grade['grade'] == 'D+') ? 'selected' : ''; ?>>D+</option>
                                        <option value="D" <?php echo ($grade['grade'] == 'D') ? 'selected' : ''; ?>>D</option>
                                        <option value="F" <?php echo ($grade['grade'] == 'F') ? 'selected' : ''; ?>>F</option>
                                        <option value="W" <?php echo ($grade['grade'] == 'W') ? 'selected' : ''; ?>>W</option>
                                        <option value="I" <?php echo ($grade['grade'] == 'I') ? 'selected' : ''; ?>>I</option>
                                    </select>
                                </td>
                                
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning single-edit-btn" 
                                                data-grade-id="<?php echo $grade['grade_id']; ?>"
                                                data-current-grade="<?php echo $grade['grade']; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editGradeModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="admin_grades.php?student_id=<?php echo $grade['student_id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions mt-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <h6><i class="fas fa-bolt text-warning"></i> ການກະທຳດ່ວນ</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="enterEditMode()">
                                <i class="fas fa-edit"></i> ແກ້ໄຂແບບກຸ່ມ
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100 mb-2" onclick="calculateGPA()">
                                <i class="fas fa-calculator"></i> ຄຳນວນ GPA ໃໝ່
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <?php if (!empty($search_value)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> ບໍ່ພົບຂໍ້ມູນເກຣດຕາມການຄົ້ນຫາ
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-search me-2"></i> ກະລຸນາຄົ້ນຫາຂໍ້ມູນນັກສຶກສາຫຼືວິຊາທີ່ຕ້ອງການແກ້ໄຂເກຣດ
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Modal ແກ້ໄຂເກຣດລາຍການດຽວ -->
    <div class="modal fade" id="editGradeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ແກ້ໄຂເກຣດ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="update_single_grade" value="1">
                    <input type="hidden" name="grade_id" id="modalGradeId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ເກຣດປັດຈຸບັນ</label>
                            <div id="currentGradeDisplay" class="alert alert-info"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ເກຣດໃໝ່</label>
                            <select name="new_grade" id="modalNewGrade" class="form-select" required>
                                <option value="">-- ເລືອກເກຣດໃໝ່ --</option>
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
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-primary">ບັນທຶກການແກ້ໄຂ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // ຕົວແປສຳລັບເກັບຂໍ້ມູນ
        let isEditMode = false;
        let changedGrades = new Set();
        
        // ຂໍ້ມູນສຳລັບ autocomplete
        const studentsData = <?php echo json_encode($students); ?>;
        const subjectsData = <?php echo json_encode($subjects); ?>;
        
        // ຟັງຊັນສຳລັບ autocomplete
        function setupAutocomplete() {
            const searchInput = document.getElementById('searchInput');
            const suggestions = document.getElementById('suggestions');
            const searchType = document.querySelector('select[name="search_type"]').value;
            
            searchInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                suggestions.innerHTML = '';
                
                if (value.length < 1) {
                    suggestions.style.display = 'none';
                    return;
                }
                
                let data = searchType === 'student' ? studentsData : subjectsData;
                let matches = [];
                
                if (searchType === 'student') {
                    matches = data.filter(item => 
                        item.student_id.toLowerCase().includes(value) ||
                        item.firstname.toLowerCase().includes(value) ||
                        item.lastname.toLowerCase().includes(value)
                    ).slice(0, 10);
                } else {
                    matches = data.filter(item => 
                        item.subject_code.toLowerCase().includes(value) ||
                        item.subject_name.toLowerCase().includes(value)
                    ).slice(0, 10);
                }
                
                if (matches.length > 0) {
                    matches.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        
                        if (searchType === 'student') {
                            div.innerHTML = `<strong>${item.student_id}</strong> - ${item.firstname} ${item.lastname}`;
                            div.onclick = () => {
                                searchInput.value = item.student_id;
                                suggestions.style.display = 'none';
                            };
                        } else {
                            div.innerHTML = `<strong>${item.subject_code}</strong> - ${item.subject_name}`;
                            div.onclick = () => {
                                searchInput.value = item.subject_code;
                                suggestions.style.display = 'none';
                            };
                        }
                        
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            });
            
            // ເຊື່ອງ suggestions ເມື່ອຄລິກນອກ
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }
        
        // ຟັງຊັນສຳລັບປ່ຽນປະເພດການຄົ້ນຫາ
        function toggleSearchInput() {
            const searchType = document.querySelector('select[name="search_type"]').value;
            const searchInput = document.getElementById('searchInput');
            const searchLabel = document.getElementById('search-label');
            
            if (searchType === 'student') {
                searchLabel.textContent = 'ລະຫັດນັກສຶກສາ';
                searchInput.placeholder = 'ປ້ອນລະຫັດນັກສຶກສາ';
            } else {
                searchLabel.textContent = 'ລະຫັດວິຊາ';
                searchInput.placeholder = 'ປ້ອນລະຫັດວິຊາ';
            }
            
            searchInput.value = '';
            setupAutocomplete();
        }
        
        // ຟັງຊັນເຂົ້າສູ່ໂໝດແກ້ໄຂ
        function enterEditMode() {
            isEditMode = true;
            document.body.classList.add('edit-mode');
            document.getElementById('bulkEditBar').classList.add('show');
            document.getElementById('toggleEditMode').style.display = 'none';
        }
        
        // ຟັງຊັນອອກຈາກໂໝດແກ້ໄຂ
        function cancelEditMode() {
            isEditMode = false;
            document.body.classList.remove('edit-mode');
            document.getElementById('bulkEditBar').classList.remove('show');
            document.getElementById('toggleEditMode').style.display = 'inline-block';
            changedGrades.clear();
            
            // ຣີເຊັດຄ່າເກຣດກັບໄປເປັນຄ່າເກົ່າ
            document.querySelectorAll('.grade-input').forEach(select => {
                select.value = select.dataset.original;
                select.closest('tr').classList.remove('table-warning');
            });
        }
        
        // ຟັງຊັນໝາຍການປ່ຽນແປງ
        function markChanged(selectElement) {
            const gradeId = selectElement.name.match(/\[(\d+)\]/)[1];
            const originalGrade = selectElement.dataset.original;
            const newGrade = selectElement.value;
            
            if (newGrade !== originalGrade && newGrade !== '') {
                changedGrades.add(gradeId);
                selectElement.closest('tr').classList.add('table-warning');
            } else {
                changedGrades.delete(gradeId);
                selectElement.closest('tr').classList.remove('table-warning');
            }
        }
        
        // ຟັງຊັນບັນທຶກການປ່ຽນແປງທັງໝົດ
        function saveAllChanges() {
            if (changedGrades.size === 0) {
                alert('ບໍ່ມີການປ່ຽນແປງໃດໆ');
                return;
            }
            
            if (confirm(`ທ່ານຕ້ອງການບັນທຶກການປ່ຽນແປງ ${changedGrades.size} ລາຍການ ແທ້ບໍ່?`)) {
                document.getElementById('bulkEditForm').submit();
            }
        }
        
        // ຟັງຊັນຄຳນວນ GPA
        function calculateGPA() {
            // ສະແດງຂໍ້ມູນ GPA ໃໝ່ຫຼັງຈາກມີການປ່ຽນແປງ
            alert('ຄຳນວນ GPA ໃໝ່ສຳເລັດ (ຟີເຈີນີ້ຈະໄດ້ຮັບການພັດທະນາເພີ່ມເຕີມ)');
        }
        
        // ຈັດການ Modal ແກ້ໄຂເກຣດ
        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete();
            
            // ຈັດການປຸ່ມແກ້ໄຂລາຍການດຽວ
            document.querySelectorAll('.single-edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const gradeId = this.dataset.gradeId;
                    const currentGrade = this.dataset.currentGrade;
                    
                    document.getElementById('modalGradeId').value = gradeId;
                    document.getElementById('currentGradeDisplay').innerHTML = 
                        `<span class="grade-badge grade-${currentGrade}">${currentGrade}</span>`;
                    document.getElementById('modalNewGrade').value = '';
                });
            });
            
            // ຈັດການ Sidebar responsive
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            
            if (navbarToggler && sidebar) {
                navbarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // ຈັດການເມື່ອປັບຂະໜາດໜ້າຈໍ
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
            
            // ສະແດງປຸ່ມໂໝດແກ້ໄຂຖ້າມີຂໍ້ມູນ
            <?php if (!empty($grades_data)): ?>
            document.getElementById('toggleEditMode').style.display = 'inline-block';
            <?php endif; ?>
        });
        
        // ຟັງຊັນຄົ້ນຫາດ່ວນ
        function quickSearch(type, value) {
            const form = document.querySelector('.search-filters form');
            form.querySelector('select[name="search_type"]').value = type;
            form.querySelector('input[name="search_value"]').value = value;
            form.submit();
        }
        
        // ຟັງຊັນສຳລັບ keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + E = ເຂົ້າສູ່ໂໝດແກ້ໄຂ
            if (e.ctrlKey && e.key === 'e' && !isEditMode) {
                e.preventDefault();
                enterEditMode();
            }
            
            // Escape = ອອກຈາກໂໝດແກ້ໄຂ
            if (e.key === 'Escape' && isEditMode) {
                e.preventDefault();
                cancelEditMode();
            }
            
            // Ctrl + S = ບັນທຶກການປ່ຽນແປງ
            if (e.ctrlKey && e.key === 's' && isEditMode) {
                e.preventDefault();
                saveAllChanges();
            }
        });
    </script>
</body>
</html>