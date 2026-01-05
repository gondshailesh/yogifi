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
$course_id = $_GET['course_id'] ?? 0;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $module_id = $_POST['id'] ?? 0;
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $video_url = trim($_POST['video_url']);
    $duration = trim($_POST['duration']);
    $module_order = (int)$_POST['module_order'];
    
    // Validate
    if (empty($title) || empty($course_id)) {
        $message = "Title and Course are required!";
        $message_type = "error";
    } else {
        if ($module_id > 0) {
            // Update module
            $sql = "UPDATE modules SET course_id = ?, title = ?, description = ?, video_url = ?, duration = ?, module_order = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssii", $course_id, $title, $description, $video_url, $duration, $module_order, $module_id);
            $action_text = "updated";
        } else {
            // Add new module
            $sql = "INSERT INTO modules (course_id, title, description, video_url, duration, module_order) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssi", $course_id, $title, $description, $video_url, $duration, $module_order);
            $action_text = "added";
        }
        
        if ($stmt->execute()) {
            $message = "Module {$action_text} successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Delete module
if ($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM modules WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Module deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting module: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Get module for editing
$module = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT * FROM modules WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $module = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get all courses for dropdown
$courses_sql = "SELECT id, title FROM courses ORDER BY title";
$courses_result = $conn->query($courses_sql);
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);

// Build WHERE clause for modules
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term);
    $types .= 'ss';
}

if (!empty($course_filter)) {
    $where_clause .= " AND m.course_id = ?";
    $params[] = $course_filter;
    $types .= 'i';
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM modules m $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_modules = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_modules / $limit);
$count_stmt->close();

// Get modules with course info
$sql = "SELECT m.*, c.title as course_title 
        FROM modules m 
        LEFT JOIN courses c ON m.course_id = c.id 
        $where_clause 
        ORDER BY m.course_id, m.module_order 
        LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$modules_result = $stmt->get_result();
$modules = $modules_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Modules - Yogify Admin</title>
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
        
        /* Sidebar */
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
        
        /* Module Form */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: <?php echo ($action == 'add' || $action == 'edit') ? 'block' : 'none'; ?>;
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
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
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
        }
        .filter-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }
        
        /* Modules Table */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
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
        .table-wrapper {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        .table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            background: var(--light);
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        .table tr:hover {
            background: #fafafa;
        }
        
        /* Module Info */
        .module-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .module-course {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        .module-desc {
            font-size: 13px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 13px;
        }
        .action-btn-view { background: var(--info); }
        .action-btn-edit { background: var(--warning); }
        .action-btn-delete { background: var(--danger); }
        
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
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .filters-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>Module Management</p>
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
                <a href="manage_modules.php" class="nav-link active">
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
            <h1><i class="fas fa-layer-group"></i> Manage Modules</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="?action=add<?php echo $course_id ? '&course_id=' . $course_id : ''; ?>" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Add Module
                </a>
            </div>
        </div>
        
        <!-- Message Box -->
        <?php if (!empty($message)): ?>
        <div class="message-box message-<?php echo $message_type; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button class="close-message" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Module Form (Add/Edit) -->
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-edit"></i> <?php echo $action == 'add' ? 'Add New Module' : 'Edit Module'; ?></h2>
                <a href="manage_modules.php<?php echo $course_id ? '?course=' . $course_id : ''; ?>" class="btn btn-light">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="id" value="<?php echo $module['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label class="form-label">Course <span style="color: var(--danger);">*</span></label>
                    <select name="course_id" class="form-control" required>
                        <option value="">Select Course</option>
                        <?php foreach($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" 
                            <?php echo (($module['course_id'] ?? ($course_id ?? 0)) == $course['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Module Title <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($module['title'] ?? ''); ?>" 
                           placeholder="Enter module title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="Enter module description"><?php echo htmlspecialchars($module['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Video URL</label>
                            <input type="url" name="video_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($module['video_url'] ?? ''); ?>" 
                                   placeholder="https://example.com/video.mp4">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control" 
                                   value="<?php echo htmlspecialchars($module['duration'] ?? ''); ?>" 
                                   placeholder="e.g., 15:30 or 30 minutes">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Module Order</label>
                            <input type="number" name="module_order" class="form-control" 
                                   value="<?php echo htmlspecialchars($module['module_order'] ?? '1'); ?>" 
                                   min="1" max="100">
                            <small style="font-size: 12px; color: var(--gray);">Display order in the course</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Module' : 'Update Module'; ?>
                    </button>
                    <a href="manage_modules.php<?php echo $course_id ? '?course=' . $course_id : ''; ?>" class="btn btn-light">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Search and Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Search Modules</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by title or description..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Filter by Course</label>
                        <select name="course" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" 
                                <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    
                    <?php if (!empty($search) || !empty($course_filter)): ?>
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <a href="manage_modules.php" class="btn btn-light" style="width: 100%;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Modules Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Modules (<?php echo $total_modules; ?>)</h3>
                <div class="table-stats">
                    <?php if ($course_filter && isset($courses[array_search($course_filter, array_column($courses, 'id'))])): 
                        $selected_course = $courses[array_search($course_filter, array_column($courses, 'id'))];
                    ?>
                        Showing modules for: <strong><?php echo htmlspecialchars($selected_course['title']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($modules) > 0): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Course</th>
                            <th>Duration</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($modules as $module_item): ?>
                        <tr>
                            <td>
                                <div class="module-title"><?php echo htmlspecialchars($module_item['title']); ?></div>
                                <?php if (!empty($module_item['description'])): ?>
                                <div class="module-desc"><?php echo htmlspecialchars(substr($module_item['description'], 0, 100)); ?>...</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="module-course"><?php echo htmlspecialchars($module_item['course_title'] ?? 'N/A'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($module_item['duration'] ?? 'N/A'); ?></td>
                            <td><?php echo $module_item['module_order']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!empty($module_item['video_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($module_item['video_url']); ?>" 
                                       target="_blank" class="action-btn action-btn-view" title="View Video">
                                        <i class="fas fa-play"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?action=edit&id=<?php echo $module_item['id']; ?>" 
                                       class="action-btn action-btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $module_item['id']; ?>" 
                                       class="action-btn action-btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this module?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $start + 4);
                if ($end - $start < 4) {
                    $start = max(1, $end - 4);
                }
                
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div style="padding: 40px; text-align: center; color: var(--gray);">
                <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 15px; color: #d1d5db;"></i>
                <h4>No Modules Found</h4>
                <p><?php echo !empty($search) ? 'No modules match your search criteria.' : 'No modules have been added yet.'; ?></p>
                <a href="?action=add" class="btn btn-success" style="margin-top: 15px;">
                    <i class="fas fa-plus-circle"></i> Add First Module
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-close message after 5 seconds
        setTimeout(() => {
            const messageBox = document.querySelector('.message-box');
            if (messageBox) {
                messageBox.style.display = 'none';
            }
        }, 5000);
        
        // Confirm before deleting
        document.querySelectorAll('.action-btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this module? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>