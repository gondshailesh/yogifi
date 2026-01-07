<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/dbconnect.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php");
    exit();
}

$message = '';
$message_type = '';

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Update enrollment status (based on your table structure)
if ($action == 'update_status' && $id > 0) {
    $completed = $_GET['status'] == 'completed' ? 1 : 0;
    
    $sql = "UPDATE enrollments SET completed = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $completed, $id);
    
    if ($stmt->execute()) {
        $message = "Enrollment status updated!";
        $message_type = "success";
    } else {
        $message = "Error updating status: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Delete enrollment
if ($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM enrollments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Enrollment deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting enrollment: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Manual enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manual_enroll'])) {
    $user_id = $_POST['user_id'];
    $course_id = $_POST['course_id'];
    
    // Check if already enrolled
    $check_sql = "SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $course_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "User is already enrolled in this course!";
        $message_type = "error";
    } else {
        $sql = "INSERT INTO enrollments (user_id, course_id, enrolled_at, progress_percent, completed) 
                VALUES (?, ?, NOW(), 0, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $course_id);
        
        if ($stmt->execute()) {
            $message = "User enrolled successfully!";
            $message_type = "success";
        } else {
            $message = "Error enrolling user: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$course_filter = $_GET['course'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR c.title LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
}

if (!empty($status_filter) && $status_filter != 'all') {
    if ($status_filter == 'completed') {
        $where_clause .= " AND e.completed = 1";
    } elseif ($status_filter == 'active') {
        $where_clause .= " AND e.completed = 0";
    }
}

if (!empty($course_filter) && $course_filter != 'all') {
    $where_clause .= " AND e.course_id = ?";
    $params[] = $course_filter;
    $types .= 'i';
}

if (!empty($date_from)) {
    $where_clause .= " AND DATE(e.enrolled_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_clause .= " AND DATE(e.enrolled_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Build ORDER BY
$order_by = "ORDER BY ";
switch ($sort) {
    case 'oldest':
        $order_by .= "e.enrolled_at ASC";
        break;
    case 'name':
        $order_by .= "u.full_name ASC";
        break;
    case 'course':
        $order_by .= "c.title ASC";
        break;
    default:
        $order_by .= "e.enrolled_at DESC";
        break;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM enrollments e
              JOIN users u ON e.user_id = u.id
              JOIN courses c ON e.course_id = c.id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_enrollments = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_enrollments / $limit);
$count_stmt->close();

// Get enrollments for current page
$sql = "SELECT e.*, 
               u.full_name, u.email, u.username,
               c.title as course_title, c.price, c.category, c.level,
               c.instructor_id
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        $where_clause
        $order_by
        LIMIT ? OFFSET ?";

$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$enrollments_result = $stmt->get_result();
$enrollments = $enrollments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all courses for filter and manual enrollment
$courses_sql = "SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title";
$courses_result = $conn->query($courses_sql);
$all_courses = $courses_result->fetch_all(MYSQLI_ASSOC);

// Get all users for manual enrollment
$users_sql = "SELECT id, full_name, email, username FROM users WHERE is_active = 1 AND user_type = 'student' ORDER BY full_name";
$users_result = $conn->query($users_sql);
$all_users = $users_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics based on your actual schema
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as active,
                COUNT(DISTINCT user_id) as unique_students,
                COUNT(DISTINCT course_id) as unique_courses
              FROM enrollments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// For pending and cancelled (not in your schema), set to 0
$stats['pending'] = 0;
$stats['cancelled'] = 0;

// Calculate revenue stats with error handling
$revenue_stats = ['total_revenue' => 0, 'collected_revenue' => 0];
try {
    $revenue_sql = "SELECT 
                    SUM(amount) as total_revenue,
                    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as collected_revenue
                    FROM payments";
    $revenue_result = $conn->query($revenue_sql);
    if ($revenue_result) {
        $revenue_stats = $revenue_result->fetch_assoc() ?: $revenue_stats;
    }
} catch (Exception $e) {
    // Payments table doesn't exist yet - we'll show zero revenue
    $revenue_stats = ['total_revenue' => 0, 'collected_revenue' => 0];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Enrollments - Yogify Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --border: #e5e7eb;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: var(--dark);
        }
        
        /* Sidebar - Use same as manage_courses.php */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, var(--dark) 0%, #374151 100%);
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
            color: #d1d5db;
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
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }
        .nav-item { margin-bottom: 5px; }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(102, 126, 234, 0.2);
            color: white;
        }
        .nav-link i { margin-right: 10px; width: 20px; }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        .header h1 {
            color: var(--dark);
            font-size: 28px;
            display: flex;
            align-items: center;
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
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #0da271; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #2563eb; }
        .btn-light { background: white; color: var(--dark); border: 1px solid var(--border); }
        .btn-light:hover { background: #f9fafb; }
        
        /* Message Box */
        .message-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }
        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }
        .message-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning);
        }
        .message-info {
            background: #dbeafe;
            color: #1d4ed8;
            border-left: 4px solid var(--info);
        }
        .close-message {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            padding: 0 5px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 15px;
        }
        .stat-icon.total { background: #f3e8ff; color: #7c3aed; }
        .stat-icon.active { background: #d1fae5; color: #065f46; }
        .stat-icon.completed { background: #dbeafe; color: #1d4ed8; }
        .stat-icon.revenue { background: #fef3c7; color: #92400e; }
        .stat-card h3 {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .stat-card .subtext {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--dark);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            background: var(--light);
            border-color: var(--primary);
        }
        
        /* Search and Filters */
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .filters-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray);
        }
        .filter-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .filter-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .btn-filter {
            padding: 10px 16px;
            white-space: nowrap;
        }
        
        /* Manual Enrollment Form */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: none; /* Hidden by default, shown via JS */
        }
        .form-container.active {
            display: block;
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        .form-header h2 {
            color: var(--dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-col {
            flex: 1;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 14px;
        }
        .form-label .required {
            color: var(--danger);
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        /* Enrollments Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            overflow-x: auto;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        .table-header h3 {
            color: var(--dark);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .table-stats {
            font-size: 13px;
            color: var(--gray);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th {
            background: var(--light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .table tbody tr:hover {
            background: #f9fafb;
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #dbeafe; color: #1d4ed8; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fef3c7; color: #92400e; }
        .badge-beginner { background: #d1fae5; color: #065f46; }
        .badge-intermediate { background: #fef3c7; color: #92400e; }
        .badge-advanced { background: #fee2e2; color: #991b1b; }
        .badge-progress { background: #e0e7ff; color: #3730a3; }
        
        /* Actions */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.active { background: var(--success); }
        .action-btn.complete { background: var(--info); }
        .action-btn.delete { background: var(--danger); }
        .action-btn.view { background: var(--info); }
        .action-btn.payment { background: var(--warning); }
        
        /* User and Course Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0;
        }
        .user-details {
            line-height: 1.4;
        }
        .user-name {
            font-weight: 500;
            color: var(--dark);
        }
        .user-email {
            font-size: 12px;
            color: var(--gray);
        }
        .course-info {
            max-width: 250px;
        }
        .course-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 3px;
        }
        .course-meta {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Progress Bar */
        .progress-container {
            width: 100px;
            background: var(--light);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: var(--success);
            border-radius: 10px;
            transition: width 0.3s;
        }
        .progress-text {
            font-size: 12px;
            color: var(--gray);
            margin-top: 3px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            padding: 20px;
            border-top: 1px solid var(--border);
        }
        .page-link {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            font-size: 13px;
            transition: all 0.3s;
        }
        .page-link:hover {
            background: var(--light);
            border-color: var(--primary);
        }
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .page-link.disabled {
            color: #9ca3af;
            pointer-events: none;
            background: var(--light);
        }
        
        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--gray);
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #d1d5db;
        }
        .empty-state h4 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
            }
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
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
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .filters-row {
                flex-direction: column;
            }
            .filter-actions {
                width: 100%;
            }
            .btn-filter {
                flex: 1;
            }
            .stats-container {
                grid-template-columns: 1fr;
            }
            .table {
                display: block;
            }
            .table th, .table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>Enrollment Management</p>
            <div class="admin-profile">
                <div class="admin-avatar">
                    <?php 
                    $initials = '';
                    if (isset($_SESSION['full_name'])) {
                        $names = explode(' ', $_SESSION['full_name']);
                        foreach($names as $n) {
                            $initials .= strtoupper(substr($n, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                    }
                    echo $initials ?: 'A';
                    ?>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
                    <div style="font-size: 12px; color: #9ca3af;">Administrator</div>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
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
                <a href="enrollments.php" class="nav-link active">
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
            <h1><i class="fas fa-graduation-cap"></i> Manage Enrollments</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button id="manualEnrollBtn" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Manual Enrollment
                </button>
            </div>
        </div>
        
        <!-- Message Box -->
        <?php if (!empty($message)): ?>
        <div class="message-box message-<?php echo $message_type; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button class="close-message" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="?status=active" class="quick-action-btn">
                <i class="fas fa-user-check"></i> Active Enrollments (<?php echo $stats['active'] ?? 0; ?>)
            </a>
            <a href="?status=completed" class="quick-action-btn">
                <i class="fas fa-trophy"></i> Completed (<?php echo $stats['completed'] ?? 0; ?>)
            </a>
            <a href="?sort=newest" class="quick-action-btn">
                <i class="fas fa-history"></i> Recent Enrollments
            </a>
        </div>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Total Enrollments</h3>
                <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="subtext">All time enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <h3>Active Students</h3>
                <div class="value"><?php echo $stats['active'] ?? 0; ?></div>
                <div class="subtext">Currently active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3>Completed</h3>
                <div class="value"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="subtext">Course completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <h3>Revenue</h3>
                <div class="value">₹<?php echo number_format($revenue_stats['collected_revenue'] ?? 0, 2); ?></div>
                <div class="subtext">Total collected</div>
            </div>
        </div>
        
        <!-- Manual Enrollment Form -->
        <div class="form-container" id="manualEnrollForm">
            <div class="form-header">
                <h2><i class="fas fa-user-plus"></i> Manual Enrollment</h2>
                <button class="btn btn-light" onclick="closeManualEnroll()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="manual_enroll" value="1">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Select Student <span class="required">*</span></label>
                            <select name="user_id" class="form-control" required>
                                <option value="">Choose a student...</option>
                                <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Select Course <span class="required">*</span></label>
                            <select name="course_id" class="form-control" required>
                                <option value="">Choose a course...</option>
                                <?php foreach ($all_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enroll Student
                    </button>
                    <button type="button" class="btn btn-light" onclick="closeManualEnroll()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Search and Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by student name, email, or course..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Status</label>
                        <select name="status" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-book"></i> Course</label>
                        <select name="course" class="filter-select">
                            <option value="all">All Courses</option>
                            <?php foreach ($all_courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" 
                                    <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date From</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date To</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Student Name (A-Z)</option>
                            <option value="course" <?php echo $sort == 'course' ? 'selected' : ''; ?>>Course Title (A-Z)</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-filter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="enrollments.php" class="btn btn-light btn-filter">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Enrollments Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Enrollments List</h3>
                <div class="table-stats">
                    Showing <?php echo count($enrollments); ?> of <?php echo $total_enrollments; ?> enrollments
                    <?php if ($total_pages > 1): ?> | Page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($enrollments)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h4>No enrollments found</h4>
                <p>Try adjusting your search or filter criteria</p>
                <button id="manualEnrollBtn2" class="btn btn-primary mt-3">
                    <i class="fas fa-user-plus"></i> Add First Enrollment
                </button>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Enrolled Date</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enrollment): 
                        // Generate user avatar initials
                        $user_initials = '';
                        if (!empty($enrollment['full_name'])) {
                            $names = explode(' ', $enrollment['full_name']);
                            foreach($names as $n) {
                                $user_initials .= strtoupper(substr($n, 0, 1));
                                if (strlen($user_initials) >= 2) break;
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo $user_initials ?: 'S'; ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($enrollment['full_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($enrollment['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="course-info">
                                <div class="course-title"><?php echo htmlspecialchars($enrollment['course_title']); ?></div>
                                <div class="course-meta">
                                    <?php echo htmlspecialchars($enrollment['category'] ?? 'Uncategorized'); ?>
                                    <?php if (!empty($enrollment['price']) && $enrollment['price'] > 0): ?>
                                    &nbsp;•&nbsp;₹<?php echo number_format($enrollment['price'], 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $enrolled_date = date('M d, Y', strtotime($enrollment['enrolled_at']));
                            $enrolled_time = date('h:i A', strtotime($enrollment['enrolled_at']));
                            echo $enrolled_date . '<br><small style="color: var(--gray); font-size: 12px;">' . $enrolled_time . '</small>';
                            ?>
                        </td>
                        <td>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $enrollment['progress_percent']; ?>%"></div>
                            </div>
                            <div class="progress-text"><?php echo $enrollment['progress_percent']; ?>%</div>
                        </td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            if ($enrollment['completed'] == 1) {
                                $status_class = 'badge-completed';
                                $status_text = 'Completed';
                            } else {
                                $status_class = 'badge-active';
                                $status_text = 'Active';
                            }
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($enrollment['completed'] == 0): ?>
                                <a href="?action=update_status&id=<?php echo $enrollment['id']; ?>&status=completed" 
                                   class="action-btn complete" title="Mark as Completed">
                                    <i class="fas fa-trophy"></i>
                                </a>
                                <?php else: ?>
                                <a href="?action=update_status&id=<?php echo $enrollment['id']; ?>&status=active" 
                                   class="action-btn active" title="Mark as Active">
                                    <i class="fas fa-undo"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="../course-player.php?course_id=<?php echo $enrollment['course_id']; ?>" 
                                   target="_blank" class="action-btn view" title="View Course">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                
                                <a href="?action=delete&id=<?php echo $enrollment['id']; ?>" 
                                   class="action-btn delete" title="Delete Enrollment" 
                                   onclick="return confirm('Are you sure you want to delete this enrollment?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span class="page-link disabled">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Manual enrollment form toggle
        const manualEnrollBtn = document.getElementById('manualEnrollBtn');
        const manualEnrollBtn2 = document.getElementById('manualEnrollBtn2');
        const manualEnrollForm = document.getElementById('manualEnrollForm');
        
        if (manualEnrollBtn) {
            manualEnrollBtn.addEventListener('click', () => {
                manualEnrollForm.classList.add('active');
            });
        }
        
        if (manualEnrollBtn2) {
            manualEnrollBtn2.addEventListener('click', () => {
                manualEnrollForm.classList.add('active');
                manualEnrollForm.scrollIntoView({ behavior: 'smooth' });
            });
        }
        
        function closeManualEnroll() {
            manualEnrollForm.classList.remove('active');
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message-box');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.style.display = 'none', 500);
            });
        }, 5000);
        
        // Date validation
        const dateFrom = document.querySelector('input[name="date_from"]');
        const dateTo = document.querySelector('input[name="date_to"]');
        
        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                dateTo.min = this.value;
            });
            
            dateTo.addEventListener('change', function() {
                dateFrom.max = this.value;
            });
        }
        
        // Initialize tooltips
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                const title = this.getAttribute('title');
                if (title) {
                    // Simple tooltip implementation
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = title;
                    tooltip.style.position = 'absolute';
                    tooltip.style.background = 'rgba(0,0,0,0.8)';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '5px 10px';
                    tooltip.style.borderRadius = '4px';
                    tooltip.style.fontSize = '12px';
                    tooltip.style.zIndex = '10000';
                    tooltip.style.whiteSpace = 'nowrap';
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = (rect.top - 30) + 'px';
                    tooltip.style.left = rect.left + 'px';
                    
                    document.body.appendChild(tooltip);
                    this.tooltip = tooltip;
                }
            });
            
            btn.addEventListener('mouseleave', function() {
                if (this.tooltip) {
                    this.tooltip.remove();
                }
            });
        });
    </script>
</body>
</html>