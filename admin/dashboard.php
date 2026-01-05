<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/dbconnect.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$sql = "SELECT full_name, email, profile_image FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Get dashboard statistics
$stats = [];

// Total users (excluding admin)
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type != 'admin'";
$result = $conn->query($sql);
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total instructors
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'instructor'";
$result = $conn->query($sql);
$stats['total_instructors'] = $result->fetch_assoc()['total'];

// Total students
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'student'";
$result = $conn->query($sql);
$stats['total_students'] = $result->fetch_assoc()['total'];

// Total courses
$sql = "SELECT COUNT(*) as total FROM courses";
$result = $conn->query($sql);
$stats['total_courses'] = $result->fetch_assoc()['total'];

// Published courses
$sql = "SELECT COUNT(*) as total FROM courses WHERE is_published = 1";
$result = $conn->query($sql);
$stats['published_courses'] = $result->fetch_assoc()['total'];

// Total enrollments
$sql = "SELECT COUNT(*) as total FROM enrollments";
$result = $conn->query($sql);
$stats['total_enrollments'] = $result->fetch_assoc()['total'];

// Today's registrations
$sql = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE() AND user_type != 'admin'";
$result = $conn->query($sql);
$stats['today_registrations'] = $result->fetch_assoc()['total'];

// Unread messages
$sql = "SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0";
$result = $conn->query($sql);
$stats['unread_messages'] = $result->fetch_assoc()['total'];

// Upcoming sessions (next 7 days)
$sql = "SELECT COUNT(*) as total FROM schedule WHERE start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
$result = $conn->query($sql);
$stats['upcoming_sessions'] = $result->fetch_assoc()['total'];

// Recent users (last 5)
$sql = "SELECT id, username, full_name, email, user_type, profile_image, created_at 
        FROM users WHERE user_type != 'admin' 
        ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($sql);

// Recent courses
$sql = "SELECT c.*, u.full_name as instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        ORDER BY c.created_at DESC LIMIT 5";
$recent_courses = $conn->query($sql);

// Recent enrollments with user and course info
$sql = "SELECT e.*, u.username, u.full_name as student_name, c.title as course_title 
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        ORDER BY e.enrolled_at DESC LIMIT 5";
$recent_enrollments = $conn->query($sql);

// Recent messages
$sql = "SELECT id, name, email, subject, created_at, is_read 
        FROM contact_messages 
        ORDER BY created_at DESC LIMIT 5";
$recent_messages = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Yogify Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-header h2 {
            color: white;
            margin-bottom: 5px;
            font-size: 22px;
        }
        .sidebar-header p {
            color: #bdc3c7;
            font-size: 13px;
        }
        .admin-profile {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .admin-details h4 {
            font-size: 14px;
            margin-bottom: 3px;
        }
        .admin-details p {
            font-size: 12px;
            color: #95a5a6;
        }
        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }
        .nav-item {
            margin-bottom: 5px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .nav-badge {
            background: #e74c3c;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        .btn-success:hover {
            background: #27ae60;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
        }
        .users .stat-icon { background: #e3f2fd; color: #2196f3; }
        .instructors .stat-icon { background: #f3e5f5; color: #9c27b0; }
        .students .stat-icon { background: #e8f5e9; color: #4caf50; }
        .courses .stat-icon { background: #fff3e0; color: #ff9800; }
        .enrollments .stat-icon { background: #e1f5fe; color: #00bcd4; }
        .messages .stat-icon { background: #fce4ec; color: #e91e63; }
        .sessions .stat-icon { background: #f3e5f5; color: #673ab7; }
        .registrations .stat-icon { background: #e8f5e9; color: #2e7d32; }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
        }
        .stat-change {
            font-size: 12px;
            margin-top: 5px;
        }
        .positive { color: #2ecc71; }
        .negative { color: #e74c3c; }
        
        /* Tables Section */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .table-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .table-title h3 {
            color: #2c3e50;
            font-size: 18px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            text-align: left;
            padding: 10px;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
        }
        .table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-primary { background: #d1ecf1; color: #0c5460; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .action-card:hover {
            border-color: #3498db;
            transform: translateY(-3px);
        }
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 20px;
        }
        .action-card:nth-child(1) .action-icon { background: #e3f2fd; color: #2196f3; }
        .action-card:nth-child(2) .action-icon { background: #f3e5f5; color: #9c27b0; }
        .action-card:nth-child(3) .action-icon { background: #e8f5e9; color: #4caf50; }
        .action-card:nth-child(4) .action-icon { background: #fff3e0; color: #ff9800; }
        
        .action-card h4 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .action-card p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid, .tables-grid, .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>Administration Panel</p>
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php 
                    $initials = '';
                    if (!empty($admin['full_name'])) {
                        $names = explode(' ', $admin['full_name']);
                        foreach($names as $n) {
                            $initials .= strtoupper(substr($n, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                    }
                    echo $initials ?: 'A';
                    ?>
                </div>
                <div class="admin-details">
                    <h4><?php echo htmlspecialchars($admin['full_name']); ?></h4>
                    <p><?php echo htmlspecialchars($_SESSION['user_type']); ?></p>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_users.php" class="nav-link">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_modules.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Modules
                </a>
            </li>
            <li class="nav-item">
                <a href="enrollments.php" class="nav-link">
                    <i class="fas fa-graduation-cap"></i> Enrollments
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_schedule.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if ($stats['unread_messages'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['unread_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="../reset_admin_password.php" class="nav-link" target="_blank">
                    <i class="fas fa-key"></i> Reset Password
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <div class="header-actions">
                <a href="?refresh" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
                <a href="../index.php" class="btn btn-success" target="_blank">
                    <i class="fas fa-external-link-alt"></i> View Site
                </a>
            </div>
        </div>
        
        <!-- Welcome Message -->
        <div class="table-container" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 30px;">
            <h2 style="color: white; margin-bottom: 10px;">Welcome back, <?php echo htmlspecialchars($admin['full_name']); ?>! 👋</h2>
            <p style="opacity: 0.9; margin-bottom: 5px;">Here's what's happening with your platform today.</p>
            <p style="font-size: 12px; opacity: 0.8;">Last login: <?php echo $_SESSION['last_login'] ?? 'Today'; ?></p>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="manage_users.php?action=add" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h4>Add User</h4>
                <p>Create new student or instructor</p>
            </a>
            <a href="manage_courses.php?action=add" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h4>Create Course</h4>
                <p>Add new yoga course</p>
            </a>
            <a href="manage_schedule.php?action=add" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h4>Schedule Session</h4>
                <p>Plan live yoga class</p>
            </a>
            <a href="messages.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h4>Messages</h4>
                <p><?php echo $stats['unread_messages']; ?> unread</p>
            </a>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change positive">+<?php echo $stats['today_registrations']; ?> today</div>
            </div>
            
            <div class="stat-card instructors">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_instructors']; ?></div>
                <div class="stat-label">Instructors</div>
            </div>
            
            <div class="stat-card students">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Students</div>
            </div>
            
            <div class="stat-card courses">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Total Courses</div>
                <div class="stat-change positive"><?php echo $stats['published_courses']; ?> published</div>
            </div>
            
            <div class="stat-card enrollments">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_enrollments']; ?></div>
                <div class="stat-label">Enrollments</div>
            </div>
            
            <div class="stat-card messages">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unread_messages']; ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>
            
            <div class="stat-card sessions">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-value"><?php echo $stats['upcoming_sessions']; ?></div>
                <div class="stat-label">Upcoming Sessions</div>
            </div>
            
            <div class="stat-card registrations">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['today_registrations']; ?></div>
                <div class="stat-label">Today's Registrations</div>
            </div>
        </div>
        
        <!-- Recent Data Tables -->
        <div class="tables-grid">
            <!-- Recent Users -->
            <div class="table-container">
                <div class="table-title">
                    <h3><i class="fas fa-user-friends"></i> Recent Users</h3>
                    <a href="manage_users.php" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                        View All
                    </a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_users->num_rows > 0): ?>
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                                    <div style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'instructor' ? 'warning' : 'success'); ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #666;">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Courses -->
            <div class="table-container">
                <div class="table-title">
                    <h3><i class="fas fa-book-open"></i> Recent Courses</h3>
                    <a href="manage_courses.php" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                        View All
                    </a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_courses->num_rows > 0): ?>
                            <?php while($course = $recent_courses->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div style="font-size: 11px; color: #666;">by <?php echo htmlspecialchars($course['instructor_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo ucfirst($course['level']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $course['is_published'] ? 'success' : 'warning'; ?>">
                                        <?php echo $course['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #666;">No courses found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Enrollments -->
            <div class="table-container">
                <div class="table-title">
                    <h3><i class="fas fa-graduation-cap"></i> Recent Enrollments</h3>
                    <a href="enrollments.php" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                        View All
                    </a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_enrollments->num_rows > 0): ?>
                            <?php while($enrollment = $recent_enrollments->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($enrollment['student_name'] ?: $enrollment['username']); ?></div>
                                    <div style="font-size: 11px; color: #666;">Enrolled: <?php echo date('M d', strtotime($enrollment['enrolled_at'])); ?></div>
                                </td>
                                <td style="font-size: 12px;"><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $enrollment['completed'] ? 'success' : ($enrollment['progress_percent'] > 50 ? 'warning' : 'info'); ?>">
                                        <?php echo $enrollment['completed'] ? 'Completed' : $enrollment['progress_percent'] . '%'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #666;">No enrollments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Messages -->
            <div class="table-container">
                <div class="table-title">
                    <h3><i class="fas fa-envelope"></i> Recent Messages</h3>
                    <a href="messages.php" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">
                        View All
                    </a>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Subject</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_messages->num_rows > 0): ?>
                            <?php while($message = $recent_messages->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($message['name']); ?></div>
                                    <div style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($message['email']); ?></div>
                                </td>
                                <td style="font-size: 12px;"><?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $message['is_read'] ? 'primary' : 'danger'; ?>">
                                        <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #666;">No messages found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; margin-top: 40px; padding: 20px; color: #7f8c8d; font-size: 13px; border-top: 1px solid #eee;">
            <p>Yogify Admin Panel • <?php echo date('Y'); ?> • v1.0.0</p>
            <p>Server Time: <?php echo date('Y-m-d H:i:s'); ?> | Total Memory: <?php echo round(memory_get_peak_usage()/1024/1024, 2); ?>MB</p>
        </div>
    </div>
    
    <script>
        // Auto refresh every 2 minutes
        setTimeout(() => window.location.reload(), 120000);
        
        // Confirm logout
        document.querySelector('a[href="logout.php"]').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>