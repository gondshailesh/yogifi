<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/dbconnect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$sql = "SELECT full_name, email, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_info = $result->fetch_assoc();
$stmt->close();

// Get dashboard statistics
$stats = [];

// Total users
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type != 'admin'";
$result = $conn->query($sql);
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total instructors
$sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'instructor'";
$result = $conn->query($sql);
$stats['total_instructors'] = $result->fetch_assoc()['total'];

// Total courses
$sql = "SELECT COUNT(*) as total FROM courses";
$result = $conn->query($sql);
$stats['total_courses'] = $result->fetch_assoc()['total'];

// Total enrollments
$sql = "SELECT COUNT(*) as total FROM enrollments";
$result = $conn->query($sql);
$stats['total_enrollments'] = $result->fetch_assoc()['total'];

// Today's registrations
$sql = "SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()";
$result = $conn->query($sql);
$stats['today_registrations'] = $result->fetch_assoc()['total'];

// Unread messages
$sql = "SELECT COUNT(*) as total FROM contact_messages WHERE is_read = 0";
$result = $conn->query($sql);
$stats['unread_messages'] = $result->fetch_assoc()['total'];

// Recent users
$sql = "SELECT id, username, full_name, email, user_type, created_at 
        FROM users WHERE user_type != 'admin' 
        ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($sql);

// Recent courses
$sql = "SELECT c.*, u.full_name as instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        ORDER BY c.created_at DESC LIMIT 5";
$recent_courses = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yogify Admin - Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: #1a252f;
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: #bdc3c7;
            font-size: 12px;
        }
        
        .admin-info {
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
            font-size: 11px;
            color: #95a5a6;
        }
        
        .nav-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link.active {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border-left: 4px solid #3498db;
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .logout-link {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
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
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d68910;
        }
        
        /* Stats Cards */
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
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .stat-icon.users { background: #e3f2fd; color: #2196f3; }
        .stat-icon.instructors { background: #f3e5f5; color: #9c27b0; }
        .stat-icon.courses { background: #e8f5e9; color: #4caf50; }
        .stat-icon.enrollments { background: #fff3e0; color: #ff9800; }
        .stat-icon.messages { background: #fce4ec; color: #e91e63; }
        .stat-icon.registrations { background: #e1f5fe; color: #00bcd4; }
        
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
        
        .stat-change.positive {
            color: #2ecc71;
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .user-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .user-type.student {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .user-type.instructor {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .user-type.admin {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-badge.published {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-badge.draft {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 12px;
        }
        
        .action-btn.view { background: #3498db; }
        .action-btn.edit { background: #f39c12; }
        .action-btn.delete { background: #e74c3c; }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        
        .action-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .action-card p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .logout-link {
                position: relative;
                bottom: auto;
            }
        }
        
        /* Welcome Message */
        .welcome-message {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .welcome-message h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .welcome-message p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
                <div class="header-actions">
                    <a href="?refresh" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Refresh</a>
                    <a href="../index.php" class="btn btn-success" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
                </div>
            </div>
            
            <!-- Welcome Message -->
            <div class="welcome-message">
                <h2>Welcome back, <?php echo htmlspecialchars($admin_info['full_name']); ?>! 👋</h2>
                <p>Here's what's happening with your platform today.</p>
                <p><small>Last login: <?php echo $_SESSION['last_login'] ?? 'First time login'; ?></small></p>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="manage_users.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add New User</h3>
                    <p>Create student or instructor account</p>
                </a>
                
                <a href="manage_courses.php?action=add" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3>Create Course</h3>
                    <p>Add new yoga course to platform</p>
                </a>
                
                <a href="manage_schedule.php?action=add" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <h3>Schedule Session</h3>
                    <p>Plan live yoga sessions</p>
                </a>
                
                <a href="messages.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Check Messages</h3>
                    <p><?php echo $stats['unread_messages']; ?> unread messages</p>
                </a>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-change positive">+<?php echo $stats['today_registrations']; ?> today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon instructors">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_instructors']; ?></div>
                    <div class="stat-label">Instructors</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon courses">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                    <div class="stat-label">Courses</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon enrollments">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_enrollments']; ?></div>
                    <div class="stat-label">Enrollments</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon messages">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['unread_messages']; ?></div>
                    <div class="stat-label">Unread Messages</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon registrations">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['today_registrations']; ?></div>
                    <div class="stat-label">Today's Registrations</div>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="table-container">
                <h3 class="table-title"><i class="fas fa-user-friends"></i> Recent Users</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_users->num_rows > 0): ?>
                            <?php while($user = $recent_users->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="user-type <?php echo $user['user_type']; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="manage_users.php?action=view&id=<?php echo $user['id']; ?>" class="action-btn view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="manage_users.php?action=edit&id=<?php echo $user['id']; ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="manage_users.php" class="btn btn-primary">View All Users</a>
                </div>
            </div>
            
            <!-- Recent Courses -->
            <div class="table-container">
                <h3 class="table-title"><i class="fas fa-book-open"></i> Recent Courses</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Level</th>
                            <th>Price</th>
                            <th>Created</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_courses->num_rows > 0): ?>
                            <?php while($course = $recent_courses->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $course['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['instructor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo ucfirst($course['level']); ?></td>
                                <td>$<?php echo number_format($course['price'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $course['is_published'] ? 'published' : 'draft'; ?>">
                                        <?php echo $course['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../course-details.php?id=<?php echo $course['id']; ?>" class="action-btn view" target="_blank" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="manage_courses.php?action=edit&id=<?php echo $course['id']; ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No courses found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div style="text-align: right; margin-top: 10px;">
                    <a href="manage_courses.php" class="btn btn-primary">View All Courses</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="text-align: center; margin-top: 30px; padding: 20px; color: #7f8c8d; font-size: 14px;">
                <p>Yogify Admin Panel • <?php echo date('Y'); ?> • v1.0.0</p>
                <p>Server Time: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto refresh every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
        
        // Confirm logout
        document.querySelector('.logout-link').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
        
        // Active nav item highlighting
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>