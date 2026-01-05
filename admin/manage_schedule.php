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

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $schedule_id = $_POST['id'] ?? 0;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructor_id = $_POST['instructor_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $zoom_link = trim($_POST['zoom_link']);
    $max_participants = (int)$_POST['max_participants'];
    
    // Validate
    if (empty($title) || empty($start_time) || empty($end_time)) {
        $message = "Title, start time and end time are required!";
        $message_type = "error";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $message = "End time must be after start time!";
        $message_type = "error";
    } else {
        if ($schedule_id > 0) {
            // Update schedule
            $sql = "UPDATE schedule SET title = ?, description = ?, instructor_id = ?, start_time = ?, end_time = ?, zoom_link = ?, max_participants = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssii", $title, $description, $instructor_id, $start_time, $end_time, $zoom_link, $max_participants, $schedule_id);
            $action_text = "updated";
        } else {
            // Add new schedule
            $sql = "INSERT INTO schedule (title, description, instructor_id, start_time, end_time, zoom_link, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssi", $title, $description, $instructor_id, $start_time, $end_time, $zoom_link, $max_participants);
            $action_text = "added";
        }
        
        if ($stmt->execute()) {
            $message = "Schedule {$action_text} successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Delete schedule
if ($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM schedule WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Schedule deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting schedule: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Get schedule for editing
$schedule = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT * FROM schedule WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $schedule = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all instructors
$instructors_sql = "SELECT id, username, full_name FROM users WHERE user_type = 'instructor' ORDER BY full_name";
$instructors_result = $conn->query($instructors_sql);
$instructors = $instructors_result->fetch_all(MYSQLI_ASSOC);

// Get search parameters
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? 'upcoming'; // upcoming, past, all
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause .= " AND (s.title LIKE ? OR s.description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term);
    $types .= 'ss';
}

if (!empty($date_filter)) {
    $where_clause .= " AND DATE(s.start_time) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

// Filter by status
$current_time = date('Y-m-d H:i:s');
if ($status_filter == 'upcoming') {
    $where_clause .= " AND s.start_time >= ?";
    $params[] = $current_time;
    $types .= 's';
} elseif ($status_filter == 'past') {
    $where_clause .= " AND s.start_time < ?";
    $params[] = $current_time;
    $types .= 's';
}
// 'all' shows everything

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM schedule s $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_schedules = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_schedules / $limit);
$count_stmt->close();

// Get schedules with instructor info
$sql = "SELECT s.*, u.username as instructor_username, u.full_name as instructor_name 
        FROM schedule s 
        LEFT JOIN users u ON s.instructor_id = u.id 
        $where_clause 
        ORDER BY s.start_time 
        LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$schedules_result = $stmt->get_result();
$schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get registration counts
$reg_counts = [];
if (count($schedules) > 0) {
    $schedule_ids = array_column($schedules, 'id');
    $ids_placeholder = implode(',', array_fill(0, count($schedule_ids), '?'));
    
    $reg_sql = "SELECT schedule_id, COUNT(*) as count FROM schedule_registrations WHERE schedule_id IN ($ids_placeholder) GROUP BY schedule_id";
    $reg_stmt = $conn->prepare($reg_sql);
    $reg_stmt->bind_param(str_repeat('i', count($schedule_ids)), ...$schedule_ids);
    $reg_stmt->execute();
    $reg_result = $reg_stmt->get_result();
    
    while ($row = $reg_result->fetch_assoc()) {
        $reg_counts[$row['schedule_id']] = $row['count'];
    }
    $reg_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule - Yogify Admin</title>
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
        
        /* Schedule Form */
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
        
        /* Calendar View */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .calendar-month {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-day-header {
            background: var(--light);
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: var(--gray);
            font-size: 12px;
            text-transform: uppercase;
        }
        .calendar-day {
            background: white;
            padding: 12px;
            min-height: 120px;
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }
        .calendar-day:nth-child(7n) {
            border-right: none;
        }
        .day-number {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .day-today {
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .schedule-item {
            background: #dbeafe;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 11px;
            color: #1d4ed8;
            cursor: pointer;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .schedule-item:hover {
            background: #bfdbfe;
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
        
        /* Schedules Table */
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
        
        /* Schedule Info */
        .schedule-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .schedule-time {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        .schedule-instructor {
            font-size: 12px;
            color: var(--gray);
        }
        .schedule-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-upcoming { background: #d1fae5; color: #065f46; }
        .status-ongoing { background: #fef3c7; color: #92400e; }
        .status-past { background: #f3f4f6; color: #6b7280; }
        
        /* Registration Info */
        .registration-info {
            font-size: 12px;
            color: var(--gray);
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: var(--success);
            border-radius: 3px;
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
        .action-btn-registrations { background: var(--success); }
        
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
            <p>Schedule Management</p>
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
                <a href="enrollments.php" class="nav-link">
                    <i class="fas fa-graduation-cap"></i> Enrollments
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_schedule.php" class="nav-link active">
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
            <h1><i class="fas fa-calendar-alt"></i> Manage Schedule</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="?action=add" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Add Session
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
        
        <!-- Schedule Form (Add/Edit) -->
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-edit"></i> <?php echo $action == 'add' ? 'Add New Session' : 'Edit Session'; ?></h2>
                <a href="manage_schedule.php" class="btn btn-light">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="id" value="<?php echo $schedule['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label class="form-label">Session Title <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($schedule['title'] ?? ''); ?>" 
                           placeholder="Enter session title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="Enter session description"><?php echo htmlspecialchars($schedule['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Instructor</label>
                            <select name="instructor_id" class="form-control">
                                <option value="">Select Instructor</option>
                                <?php foreach($instructors as $instructor): ?>
                                <option value="<?php echo $instructor['id']; ?>" 
                                    <?php echo ($schedule['instructor_id'] ?? '') == $instructor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instructor['full_name'] ?: $instructor['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Max Participants</label>
                            <input type="number" name="max_participants" class="form-control" 
                                   value="<?php echo htmlspecialchars($schedule['max_participants'] ?? '50'); ?>" 
                                   min="1" max="500">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Start Time <span style="color: var(--danger);">*</span></label>
                            <input type="datetime-local" name="start_time" class="form-control" 
                                   value="<?php echo isset($schedule['start_time']) ? date('Y-m-d\TH:i', strtotime($schedule['start_time'])) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">End Time <span style="color: var(--danger);">*</span></label>
                            <input type="datetime-local" name="end_time" class="form-control" 
                                   value="<?php echo isset($schedule['end_time']) ? date('Y-m-d\TH:i', strtotime($schedule['end_time'])) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Zoom Link (for online sessions)</label>
                    <input type="url" name="zoom_link" class="form-control" 
                           value="<?php echo htmlspecialchars($schedule['zoom_link'] ?? ''); ?>" 
                           placeholder="https://zoom.us/j/...">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add Session' : 'Update Session'; ?>
                    </button>
                    <a href="manage_schedule.php" class="btn btn-light">
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
                        <label>Search Sessions</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by title or description..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Date</label>
                        <input type="date" name="date" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo $status_filter == 'past' ? 'selected' : ''; ?>>Past</option>
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Sessions</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    
                    <?php if (!empty($search) || !empty($date_filter) || $status_filter != 'upcoming'): ?>
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <a href="manage_schedule.php" class="btn btn-light" style="width: 100%;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Schedules Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Sessions (<?php echo $total_schedules; ?>)</h3>
                <div class="table-stats">
                    <?php 
                    $upcoming_count = 0;
                    $past_count = 0;
                    foreach($schedules as $s) {
                        if (strtotime($s['start_time']) >= time()) {
                            $upcoming_count++;
                        } else {
                            $past_count++;
                        }
                    }
                    ?>
                    <span style="color: var(--success);"><?php echo $upcoming_count; ?> upcoming</span> | 
                    <span style="color: var(--gray);"><?php echo $past_count; ?> past</span>
                </div>
            </div>
            
            <?php if (count($schedules) > 0): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Time</th>
                            <th>Instructor</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($schedules as $schedule_item): 
                            $start_time = strtotime($schedule_item['start_time']);
                            $end_time = strtotime($schedule_item['end_time']);
                            $current_time = time();
                            
                            if ($current_time < $start_time) {
                                $status = 'upcoming';
                                $status_class = 'status-upcoming';
                                $status_text = 'Upcoming';
                            } elseif ($current_time >= $start_time && $current_time <= $end_time) {
                                $status = 'ongoing';
                                $status_class = 'status-ongoing';
                                $status_text = 'Ongoing';
                            } else {
                                $status = 'past';
                                $status_class = 'status-past';
                                $status_text = 'Completed';
                            }
                            
                            $reg_count = $reg_counts[$schedule_item['id']] ?? 0;
                            $max_participants = $schedule_item['max_participants'] ?: 50;
                            $percentage = $max_participants > 0 ? min(100, ($reg_count / $max_participants) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="schedule-title"><?php echo htmlspecialchars($schedule_item['title']); ?></div>
                                <?php if (!empty($schedule_item['description'])): ?>
                                <div style="font-size: 12px; color: var(--gray); margin-top: 3px;">
                                    <?php echo htmlspecialchars(substr($schedule_item['description'], 0, 80)); ?>...
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="schedule-time">
                                    <strong><?php echo date('M d, Y', $start_time); ?></strong><br>
                                    <?php echo date('h:i A', $start_time); ?> - <?php echo date('h:i A', $end_time); ?>
                                </div>
                            </td>
                            <td>
                                <div class="schedule-instructor">
                                    <?php echo htmlspecialchars($schedule_item['instructor_name'] ?? 'Not assigned'); ?>
                                </div>
                            </td>
                            <td style="min-width: 150px;">
                                <div class="registration-info">
                                    <?php echo $reg_count; ?> / <?php echo $max_participants; ?> registered
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="schedule-status <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!empty($schedule_item['zoom_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($schedule_item['zoom_link']); ?>" 
                                       target="_blank" class="action-btn action-btn-view" title="Join Zoom">
                                        <i class="fas fa-video"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?action=edit&id=<?php echo $schedule_item['id']; ?>" 
                                       class="action-btn action-btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_registrations.php?schedule_id=<?php echo $schedule_item['id']; ?>" 
                                       class="action-btn action-btn-registrations" title="View Registrations">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $schedule_item['id']; ?>" 
                                       class="action-btn action-btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this session?')">
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
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>&status=<?php echo $status_filter; ?>" 
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
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_filter) ? '&date=' . urlencode($date_filter) : ''; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div style="padding: 40px; text-align: center; color: var(--gray);">
                <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px; color: #d1d5db;"></i>
                <h4>No Sessions Found</h4>
                <p><?php echo !empty($search) ? 'No sessions match your search criteria.' : 'No sessions have been scheduled yet.'; ?></p>
                <a href="?action=add" class="btn btn-success" style="margin-top: 15px;">
                    <i class="fas fa-plus-circle"></i> Schedule First Session
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Set default datetime to now for new sessions
        <?php if ($action == 'add'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const startTime = document.querySelector('input[name="start_time"]');
            const endTime = document.querySelector('input[name="end_time"]');
            
            // Set start time to next hour
            const nextHour = new Date(now.getTime() + 60 * 60 * 1000);
            nextHour.setMinutes(0, 0, 0);
            
            // Set end time to 1 hour after start
            const endHour = new Date(nextHour.getTime() + 60 * 60 * 1000);
            
            // Format to YYYY-MM-DDTHH:MM
            function formatDateTime(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            }
            
            if (startTime && !startTime.value) {
                startTime.value = formatDateTime(nextHour);
            }
            if (endTime && !endTime.value) {
                endTime.value = formatDateTime(endHour);
            }
        });
        <?php endif; ?>
        
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
                if (!confirm('Are you sure you want to delete this session? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>