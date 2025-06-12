<?php
// ເຊື່ອມຕໍ່ກັບຖານຂໍ້ມູນ
require_once '../config.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isLoggedIn()) {
    header("Location: admin_login.php");
    exit();
}

// ດຶງຂໍ້ມູນສະຖິຕິພື້ນຖານ
$stats = [];

// ຈຳນວນນັກສຶກສາທັງໝົດ
$student_count_query = "SELECT COUNT(*) as total FROM students";
$student_count_result = $conn->query($student_count_query);
$stats['total_students'] = $student_count_result->fetch_assoc()['total'];

// ຈຳນວນນັກສຶກສາທີ່ກຳລັງສຶກສາ
$active_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'studying'";
$active_students_result = $conn->query($active_students_query);
$stats['active_students'] = $active_students_result->fetch_assoc()['total'];

// ຈຳນວນນັກສຶກສາທີ່ຈົບແລ້ວ
$graduated_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'graduated'";
$graduated_students_result = $conn->query($graduated_students_query);
$stats['graduated_students'] = $graduated_students_result->fetch_assoc()['total'];

// ຈຳນວນວິຊາທັງໝົດ
$subject_count_query = "SELECT COUNT(*) as total FROM subjects";
$subject_count_result = $conn->query($subject_count_query);
$stats['total_subjects'] = $subject_count_result->fetch_assoc()['total'];

// ຈຳນວນສາຂາວິຊາທັງໝົດ
$major_count_query = "SELECT COUNT(*) as total FROM majors";
$major_count_result = $conn->query($major_count_query);
$stats['total_majors'] = $major_count_result->fetch_assoc()['total'];

// ຈຳນວນຜົນການຮຽນທັງໝົດ
$grade_count_query = "SELECT COUNT(*) as total FROM grades";
$grade_count_result = $conn->query($grade_count_query);
$stats['total_grades'] = $grade_count_result->fetch_assoc()['total'];

// ສະຖິຕິນັກສຶກສາຕາມສາຂາວິຊາ
$students_by_major_query = "SELECT m.major_name, m.department, COUNT(s.student_id) as student_count 
                           FROM majors m 
                           LEFT JOIN students s ON m.major_id = s.major_id 
                           GROUP BY m.major_id, m.major_name, m.department 
                           ORDER BY student_count DESC";
$students_by_major_result = $conn->query($students_by_major_query);
$students_by_major = [];
if ($students_by_major_result->num_rows > 0) {
    while ($row = $students_by_major_result->fetch_assoc()) {
        $students_by_major[] = $row;
    }
}

// ສະຖິຕິນັກສຶກສາຕາມປີທີ່ເຂົ້າສຶກສາ
$students_by_year_query = "SELECT enrollment_year, COUNT(*) as student_count 
                          FROM students 
                          GROUP BY enrollment_year 
                          ORDER BY enrollment_year DESC";
$students_by_year_result = $conn->query($students_by_year_query);
$students_by_year = [];
if ($students_by_year_result->num_rows > 0) {
    while ($row = $students_by_year_result->fetch_assoc()) {
        $students_by_year[] = $row;
    }
}

// ສະຖິຕິເກຣດ
$grade_distribution_query = "SELECT grade, COUNT(*) as count 
                            FROM grades 
                            GROUP BY grade 
                            ORDER BY FIELD(grade, 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F', 'W', 'I')";
$grade_distribution_result = $conn->query($grade_distribution_query);
$grade_distribution = [];
if ($grade_distribution_result->num_rows > 0) {
    while ($row = $grade_distribution_result->fetch_assoc()) {
        $grade_distribution[] = $row;
    }
}

// ສະຖິຕິນັກສຶກສາທີ່ມີ GPA ສູງສຸດ
$top_students_query = "SELECT s.student_id, s.firstname, s.lastname, m.major_name,
                       AVG(CASE 
                           WHEN g.grade = 'A' THEN 4.0
                           WHEN g.grade = 'B+' THEN 3.5
                           WHEN g.grade = 'B' THEN 3.0
                           WHEN g.grade = 'C+' THEN 2.5
                           WHEN g.grade = 'C' THEN 2.0
                           WHEN g.grade = 'D+' THEN 1.5
                           WHEN g.grade = 'D' THEN 1.0
                           ELSE 0.0
                       END) as gpa
                       FROM students s
                       JOIN majors m ON s.major_id = m.major_id
                       LEFT JOIN grades g ON s.student_id = g.student_id
                       WHERE s.status = 'studying' AND g.grade IS NOT NULL
                       GROUP BY s.student_id, s.firstname, s.lastname, m.major_name
                       HAVING gpa > 0
                       ORDER BY gpa DESC
                       LIMIT 10";
$top_students_result = $conn->query($top_students_query);
$top_students = [];
if ($top_students_result->num_rows > 0) {
    while ($row = $top_students_result->fetch_assoc()) {
        $top_students[] = $row;
    }
}

// ຈັດການການສົ່ງຟອມສຳລັບສ້າງລາຍງານ
$report_type = isset($_GET['report']) ? $_GET['report'] : '';
$report_data = [];

if ($report_type) {
    switch ($report_type) {
        case 'students_list':
            $filter_major = isset($_GET['major_id']) ? intval($_GET['major_id']) : 0;
            $filter_status = isset($_GET['status']) ? clean($conn, $_GET['status']) : '';
            
            $where_conditions = [];
            if ($filter_major > 0) {
                $where_conditions[] = "s.major_id = $filter_major";
            }
            if (!empty($filter_status)) {
                $where_conditions[] = "s.status = '$filter_status'";
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT s.*, m.major_name, m.department 
                     FROM students s 
                     JOIN majors m ON s.major_id = m.major_id 
                     $where_clause
                     ORDER BY s.student_id";
            $result = $conn->query($query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                }
            }
            break;
            
        case 'grades_summary':
            $filter_year = isset($_GET['study_year']) ? intval($_GET['study_year']) : 0;
            $filter_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
            
            $where_conditions = [];
            if ($filter_year > 0) {
                $where_conditions[] = "g.study_year = $filter_year";
            }
            if ($filter_semester > 0) {
                $where_conditions[] = "g.semester = $filter_semester";
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "SELECT s.student_id, s.firstname, s.lastname, m.major_name,
                     COUNT(g.grade_id) as total_subjects,
                     AVG(CASE 
                         WHEN g.grade = 'A' THEN 4.0
                         WHEN g.grade = 'B+' THEN 3.5
                         WHEN g.grade = 'B' THEN 3.0
                         WHEN g.grade = 'C+' THEN 2.5
                         WHEN g.grade = 'C' THEN 2.0
                         WHEN g.grade = 'D+' THEN 1.5
                         WHEN g.grade = 'D' THEN 1.0
                         ELSE 0.0
                     END) as gpa
                     FROM students s
                     JOIN majors m ON s.major_id = m.major_id
                     LEFT JOIN grades g ON s.student_id = g.student_id
                     $where_clause
                     GROUP BY s.student_id, s.firstname, s.lastname, m.major_name
                     HAVING total_subjects > 0
                     ORDER BY gpa DESC";
            $result = $conn->query($query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $report_data[] = $row;
                }
            }
            break;
    }
}

// ດຶງຂໍ້ມູນສາຂາວິຊາສຳລັບຟິວເຕີ
$majors_query = "SELECT * FROM majors ORDER BY major_name";
$majors_result = $conn->query($majors_query);
$majors = [];
if ($majors_result->num_rows > 0) {
    while ($row = $majors_result->fetch_assoc()) {
        $majors[] = $row;
    }
}

// ຟັງຊັນສຳລັບແປງສະຖານະເປັນພາສາລາວ
function getStatusTextLao($status) {
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
    <title>ລາຍງານ - ວິທະຍາໄລເຕັກນິກ</title>
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
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        
        .card-header i {
            color: #3498db;
            margin-right: 10px;
        }
        
        .stat-card {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .report-filters {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .grade-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
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
        
        .top-student {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .rank-number {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
            margin-right: 15px;
            min-width: 40px;
            text-align: center;
        }
        
        .student-info {
            flex-grow: 1;
        }
        
        .gpa-display {
            font-size: 18px;
            font-weight: bold;
            color: #2ecc71;
        }
        
        @media print {
            .sidebar, .navbar, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
            body {
                padding-top: 0 !important;
            }
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
            <h2><i class="fas fa-file-alt"></i> ລາຍງານແລະສະຖິຕິ</h2>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-secondary me-2">
                    <i class="fas fa-print"></i> ພິມລາຍງານ
                </button>
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> ສົ່ງອອກ Excel
                </button>
            </div>
        </div>
        
        <?php if (!$report_type): ?>
        <!-- ສະຖິຕິລວມ -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(45deg, #3498db, #2980b9);">
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">ນັກສຶກສາທັງໝົດ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(45deg, #2ecc71, #27ae60);">
                    <div class="stat-number"><?php echo $stats['active_students']; ?></div>
                    <div class="stat-label">ກຳລັງສຶກສາ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(45deg, #f39c12, #e67e22);">
                    <div class="stat-number"><?php echo $stats['graduated_students']; ?></div>
                    <div class="stat-label">ຈົບການສຶກສາ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(45deg, #9b59b6, #8e44ad);">
                    <div class="stat-number"><?php echo $stats['total_subjects']; ?></div>
                    <div class="stat-label">ລາຍວິຊາທັງໝົດ</div>
                </div>
            </div>
        </div>
        
        <!-- ປຸ່ມສ້າງລາຍງານ -->
        <div class="row mb-4 no-print">
            <div class="col-md-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> ເລືອກປະເພດລາຍງານ</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h6>ລາຍງານນັກສຶກສາ</h6>
                                        <p class="text-muted">ລາຍການນັກສຶກສາແຍກຕາມສາຂາແລະສະຖານະ</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentsReportModal">
                                            <i class="fas fa-file-alt"></i> ສ້າງລາຍງານ
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                        <h6>ລາຍງານຜົນການຮຽນ</h6>
                                        <p class="text-muted">ສະຫລຸບຜົນການຮຽນແລະເກຣດສະເລ່ຍ</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#gradesReportModal">
                                            <i class="fas fa-file-alt"></i> ສ້າງລາຍງານ
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- ສະຖິຕິນັກສຶກສາຕາມສາຂາ -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> ນັກສຶກສາຕາມສາຂາວິຊາ</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($students_by_major)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ສາຂາວິຊາ</th>
                                        <th>ພາກວິຊາ</th>
                                        <th class="text-end">ຈຳນວນ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_by_major as $major): ?>
                                    <tr>
                                        <td><?php echo $major['major_name']; ?></td>
                                        <td><?php echo $major['department']; ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary"><?php echo $major['student_count']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">ຍັງບໍ່ມີຂໍ້ມູນ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ສະຖິຕິນັກສຶກສາຕາມປີ -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> ນັກສຶກສາຕາມປີທີ່ເຂົ້າສຶກສາ</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($students_by_year)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ປີການສຶກສາ</th>
                                        <th class="text-end">ຈຳນວນນັກສຶກສາ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_by_year as $year): ?>
                                    <tr>
                                        <td><?php echo $year['enrollment_year']; ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-info"><?php echo $year['student_count']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">ຍັງບໍ່ມີຂໍ້ມູນ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- ກະຈາຍຕົວຂອງເກຣດ -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> ການກະຈາຍຕົວຂອງເກຣດ</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($grade_distribution)): ?>
                        <div class="row">
                            <?php foreach ($grade_distribution as $grade): ?>
                            <div class="col-6 col-md-4 mb-3">
                                <div class="text-center">
                                    <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                        <?php echo $grade['grade']; ?>
                                    </span>
                                    <div class="mt-2">
                                        <strong><?php echo $grade['count']; ?></strong>
                                        <small class="text-muted d-block">ລາຍການ</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">ຍັງບໍ່ມີຂໍ້ມູນເກຣດ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- ນັກສຶກສາທີ່ມີຜົນການຮຽນດີເດັ່ນ -->
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy"></i> ນັກສຶກສາຜົນການຮຽນດີເດັ່ນ (Top 10)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_students)): ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($top_students as $student): ?>
                        <div class="top-student">
                            <div class="rank-number">
                                <?php if ($rank == 1): ?>
                                <i class="fas fa-crown text-warning"></i>
                                <?php else: ?>
                                <?php echo $rank; ?>
                                <?php endif; ?>
                            </div>
                            <div class="student-info">
                                <strong><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></strong>
                                <br>
                                <small class="text-muted"><?php echo $student['student_id'] . ' - ' . $student['major_name']; ?></small>
                            </div>
                            <div class="gpa-display">
                                <?php echo number_format($student['gpa'], 2); ?>
                            </div>
                        </div>
                        <?php $rank++; ?>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted">ຍັງບໍ່ມີຂໍ້ມູນຜົນການຮຽນ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ສະແດງຜົນລາຍງານ -->
        <div class="card dashboard-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt"></i> 
                        <?php if ($report_type == 'students_list'): ?>
                        ລາຍງານນັກສຶກສາ
                        <?php elseif ($report_type == 'grades_summary'): ?>
                        ລາຍງານສະຫລຸບຂໍ້ມູນຜົນການຮຽນ
                        <?php endif; ?>
                    </h5>
                    <div class="no-print">
                        <a href="admin_reports.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າຫຼັກ
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($report_type == 'students_list'): ?>
                <!-- ລາຍງານນັກສຶກສາ -->
                <?php if (!empty($report_data)): ?>
                <p class="text-muted mb-4">ວັນທີ່ສ້າງລາຍງານ: <?php echo date('d/m/Y H:i:s'); ?></p>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ຊື່-ນາມສະກຸນ</th>
                                <th>ພາກວິຊາ</th>
                                <th>ສາຂາວິຊາ</th>
                                <th>ປີທີ່ເຂົ້າສຶກສາ</th>
                                <th>ສະຖານະ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $student): ?>
                            <tr>
                                <td><?php echo $student['student_id']; ?></td>
                                <td><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></td>
                                <td><?php echo $student['department']; ?></td>
                                <td><?php echo $student['major_name']; ?></td>
                                <td><?php echo $student['enrollment_year']; ?></td>
                                <td><?php echo getStatusTextLao($student['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3"><strong>ລວມທັງໝົດ: <?php echo count($report_data); ?> ຄົນ</strong></p>
                <?php else: ?>
                <div class="alert alert-info">ບໍ່ພົບຂໍ້ມູນນັກສຶກສາຕາມເງື່ອນໄຂທີ່ກຳນົດ</div>
                <?php endif; ?>
                
                <?php elseif ($report_type == 'grades_summary'): ?>
                <!-- ລາຍງານຜົນການຮຽນ -->
                <?php if (!empty($report_data)): ?>
                <p class="text-muted mb-4">ວັນທີ່ສ້າງລາຍງານ: <?php echo date('d/m/Y H:i:s'); ?></p>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>ລະຫັດນັກສຶກສາ</th>
                                <th>ຊື່-ນາມສະກຸນ</th>
                                <th>ສາຂາວິຊາ</th>
                                <th>ຈຳນວນວິຊາ</th>
                                <th>ເກຣດສະເລ່ຍ (GPA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $student): ?>
                            <tr>
                                <td><?php echo $student['student_id']; ?></td>
                                <td><?php echo $student['firstname'] . ' ' . $student['lastname']; ?></td>
                                <td><?php echo $student['major_name']; ?></td>
                                <td><?php echo $student['total_subjects']; ?></td>
                                <td>
                                    <strong class="<?php echo ($student['gpa'] >= 3.5) ? 'text-success' : (($student['gpa'] >= 2.5) ? 'text-warning' : 'text-danger'); ?>">
                                        <?php echo number_format($student['gpa'], 2); ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3"><strong>ລວມທັງໝົດ: <?php echo count($report_data); ?> ຄົນ</strong></p>
                <?php else: ?>
                <div class="alert alert-info">ບໍ່ພົບຂໍ້ມູນຜົນການຮຽນຕາມເງື່ອນໄຂທີ່ກຳນົດ</div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal ລາຍງານນັກສຶກສາ -->
    <div class="modal fade" id="studentsReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ສ້າງລາຍງານນັກສຶກສາ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin_reports.php" method="GET">
                    <input type="hidden" name="report" value="students_list">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ສາຂາວິຊາ</label>
                            <select name="major_id" class="form-select">
                                <option value="0">ທຸກສາຂາວິຊາ</option>
                                <?php foreach ($majors as $major): ?>
                                <option value="<?php echo $major['major_id']; ?>"><?php echo $major['major_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ສະຖານະ</label>
                            <select name="status" class="form-select">
                                <option value="">ທຸກສະຖານະ</option>
                                <option value="studying">ກຳລັງສຶກສາ</option>
                                <option value="graduated">ຈົບການສຶກສາ</option>
                                <option value="dismissed">ພົ້ນສະພາບ</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-primary">ສ້າງລາຍງານ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal ລາຍງານຜົນການຮຽນ -->
    <div class="modal fade" id="gradesReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ສ້າງລາຍງານຜົນການຮຽນ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin_reports.php" method="GET">
                    <input type="hidden" name="report" value="grades_summary">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ປີການສຶກສາ</label>
                            <select name="study_year" class="form-select">
                                <option value="0">ທຸກປີການສຶກສາ</option>
                                <option value="1">ປີ 1</option>
                                <option value="2">ປີ 2</option>
                                <option value="3">ປີ 3</option>
                                <option value="4">ປີ 4</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ພາກຮຽນ</label>
                            <select name="semester" class="form-select">
                                <option value="0">ທຸກພາກຮຽນ</option>
                                <option value="1">ພາກຮຽນທີ 1</option>
                                <option value="2">ພາກຮຽນທີ 2</option>
                                <option value="3">ພາກຮຽນທີ 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-success">ສ້າງລາຍງານ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // ຟັງຊັນສົ່ງອອກເປັນ Excel
        function exportToExcel() {
            // ສ້າງຕາຕະລາງ Excel ແບບງ່າຍໆ ດ້ວຍ HTML
            const table = document.querySelector('.table-responsive table');
            if (!table) {
                alert('ບໍ່ພົບຕາຕະລາງທີ່ຈະສົ່ງອອກ');
                return;
            }
            
            let html = '<table border="1">';
            html += table.innerHTML;
            html += '</table>';
            
            // ສ້າງ Blob ແລະດາວໂຫຼດ
            const blob = new Blob([html], {
                type: 'application/vnd.ms-excel'
            });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'ລາຍງານ_' + new Date().getTime() + '.xls';
            link.click();
        }
        
        // ຈັດການ Sidebar responsive
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar');
            
            if (navbarToggler && sidebar) {
                navbarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // ປິດ sidebar ເມື່ອຄລິກນອກ sidebar ໃນໂໝດມືຖື
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 992) {
                    if (!sidebar.contains(event.target) && !navbarToggler.contains(event.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // ຈັດການເມື່ອປັບຂະໜາດໜ້າຈໍ
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
        });
        
        // ຟັງຊັນສຳລັບການພິມ
        window.addEventListener('beforeprint', function() {
            document.title = 'ລາຍງານ - ວິທະຍາໄລເຕັກນິກ';
        });
        
        // ເພີ່ມ animation ໃຫ້ກັບສະຖິຕິ
        function animateNumbers() {
            const numbers = document.querySelectorAll('.stat-number');
            numbers.forEach(function(number) {
                const target = parseInt(number.textContent);
                let current = 0;
                const increment = target / 50;
                
                const timer = setInterval(function() {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    number.textContent = Math.floor(current);
                }, 30);
            });
        }
        
        // ເລີ່ມ animation ເມື່ອໂຫຼດໜ້າເສັດ
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', animateNumbers);
        } else {
            animateNumbers();
        }
    </script>
</body>
</html>