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

// Mark as read/unread
if ($action == 'toggle_read' && $id > 0) {
    $sql = "SELECT is_read FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $msg = $result->fetch_assoc();
        $new_status = $msg['is_read'] ? 0 : 1;
        
        $update_sql = "UPDATE contact_messages SET is_read = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_status, $id);
        
        if ($update_stmt->execute()) {
            $message = "Message marked as " . ($new_status ? 'read' : 'unread') . "!";
            $message_type = "success";
        } else {
            $message = "Error updating message: " . $conn->error;
            $message_type = "error";
        }
        $update_stmt->close();
    }
    $stmt->close();
}

// Delete message
if ($action == 'delete' && $id > 0) {
    $sql = "DELETE FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Message deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting message: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Mark all as read
if ($action == 'mark_all_read') {
    $sql = "UPDATE contact_messages SET is_read = 1 WHERE is_read = 0";
    if ($conn->query($sql)) {
        $message = "All messages marked as read!";
        $message_type = "success";
    } else {
        $message = "Error updating messages: " . $conn->error;
        $message_type = "error";
    }
}

// Reply to message (simplified - would need email integration)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reply_message'])) {
    $message_id = $_POST['message_id'];
    $reply_subject = trim($_POST['reply_subject']);
    $reply_content = trim($_POST['reply_content']);
    
    // Get original message
    $sql = "SELECT name, email FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $original = $result->fetch_assoc();
        
        // In a real application, you would send an email here
        // For now, we'll just mark it as replied and log it
        $update_sql = "UPDATE contact_messages SET is_read = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $message_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $message = "Reply sent successfully! (Simulation - email would be sent to " . htmlspecialchars($original['email']) . ")";
        $message_type = "success";
    }
    $stmt->close();
}

// Get message details
$message_detail = null;
if ($action == 'view' && $id > 0) {
    $sql = "SELECT * FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $message_detail = $result->fetch_assoc();
        
        // Mark as read when viewed
        if (!$message_detail['is_read']) {
            $update_sql = "UPDATE contact_messages SET is_read = 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $id);
            $update_stmt->execute();
            $update_stmt->close();
            $message_detail['is_read'] = 1;
        }
    }
    $stmt->close();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'unread'; // unread, read, all
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
}

if ($status_filter == 'unread') {
    $where_clause .= " AND is_read = 0";
} elseif ($status_filter == 'read') {
    $where_clause .= " AND is_read = 1";
}
// 'all' shows everything

if (!empty($date_from)) {
    $where_clause .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_clause .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM contact_messages $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_messages = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_messages / $limit);
$count_stmt->close();

// Get unread count for badge
$unread_sql = "SELECT COUNT(*) as unread FROM contact_messages WHERE is_read = 0";
$unread_result = $conn->query($unread_sql);
$unread_count = $unread_result->fetch_assoc()['unread'];

// Get messages
$sql = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$messages_result = $stmt->get_result();
$messages = $messages_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Yogify Admin</title>
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
        .nav-badge {
            background: var(--danger);
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
        .close-message {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            padding: 0 5px;
        }
        
        /* Message Detail View */
        .message-detail-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: <?php echo ($action == 'view') ? 'block' : 'none'; ?>;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        .message-info h2 {
            color: var(--dark);
            font-size: 22px;
            margin-bottom: 10px;
        }
        .message-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: var(--gray);
        }
        .message-content {
            margin-bottom: 30px;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .reply-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .reply-form h3 {
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
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
            min-height: 150px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        /* Messages Table */
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
        .table-stats {
            font-size: 13px;
            color: var(--gray);
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
        .table tr.unread {
            background: #f0f9ff;
        }
        .table tr.unread:hover {
            background: #e0f2fe;
        }
        
        /* Message Preview */
        .message-preview {
            display: flex;
            align-items: flex-start;
        }
        .message-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }
        .message-details {
            flex: 1;
        }
        .message-sender {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 3px;
        }
        .message-subject {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }
        .message-excerpt {
            font-size: 13px;
            color: var(--gray);
            margin-top: 5px;
            line-height: 1.4;
        }
        .message-meta {
            font-size: 12px;
            color: var(--gray);
        }
        .message-status {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-unread {
            background: var(--danger);
        }
        .status-read {
            background: var(--success);
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
        .action-btn-reply { background: var(--success); }
        .action-btn-toggle { background: var(--warning); }
        .action-btn-delete { background: var(--danger); }
        
        /* Bulk Actions */
        .bulk-actions {
            background: var(--light);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
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
            .filters-row {
                flex-direction: column;
            }
            .message-header {
                flex-direction: column;
                gap: 15px;
            }
            .message-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>Message Center</p>
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
                <a href="manage_schedule.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link active">
                    <i class="fas fa-envelope"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="nav-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
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
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <?php if ($unread_count > 0): ?>
                <a href="?action=mark_all_read" class="btn btn-success">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Message Box -->
        <?php if (!empty($message)): ?>
        <div class="message-box message-<?php echo $message_type; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button class="close-message" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Message Detail View -->
        <?php if ($action == 'view' && $message_detail): ?>
        <div class="message-detail-container">
            <div class="message-header">
                <div class="message-info">
                    <h2><?php echo htmlspecialchars($message_detail['subject'] ?: 'No Subject'); ?></h2>
                    <div class="message-meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($message_detail['name']); ?></span>
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($message_detail['email']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('F j, Y, g:i a', strtotime($message_detail['created_at'])); ?></span>
                    </div>
                </div>
                <div>
                    <a href="messages.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to Inbox
                    </a>
                </div>
            </div>
            
            <div class="message-content">
                <?php echo nl2br(htmlspecialchars($message_detail['message'])); ?>
            </div>
            
            <div class="reply-form">
                <h3><i class="fas fa-reply"></i> Reply to Message</h3>
                <form method="POST" action="">
                    <input type="hidden" name="message_id" value="<?php echo $message_detail['id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($message_detail['name'] . ' <' . $message_detail['email'] . '>'); ?>" 
                               readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="reply_subject" class="form-control" 
                               value="Re: <?php echo htmlspecialchars($message_detail['subject'] ?: 'Your Message'); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="reply_content" class="form-control" rows="8" required placeholder="Type your reply here..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="reply_message" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                        <a href="messages.php" class="btn btn-light">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Search and Filters -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Search Messages</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by name, email, subject, or message..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="unread" <?php echo $status_filter == 'unread' ? 'selected' : ''; ?>>Unread Only (<?php echo $unread_count; ?>)</option>
                            <option value="read" <?php echo $status_filter == 'read' ? 'selected' : ''; ?>>Read Only</option>
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Messages</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    
                    <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || $status_filter != 'unread'): ?>
                    <div class="filter-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <a href="messages.php" class="btn btn-light" style="width: 100%;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Messages Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-inbox"></i> Inbox (<?php echo $total_messages; ?>)</h3>
                <div class="table-stats">
                    <?php echo $unread_count; ?> unread messages
                </div>
            </div>
            
            <?php if (count($messages) > 0): ?>
            <div class="bulk-actions">
                <div class="select-all">
                    <input type="checkbox" id="selectAll">
                    <label for="selectAll">Select All</label>
                </div>
                <button type="button" class="btn btn-light btn-sm" onclick="markSelectedAsRead()">
                    <i class="fas fa-check"></i> Mark as Read
                </button>
                <button type="button" class="btn btn-light btn-sm" onclick="deleteSelected()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" id="selectAllHeader">
                            </th>
                            <th>Message</th>
                            <th style="width: 180px;">Date</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($messages as $msg): 
                            $avatar_initials = strtoupper(substr($msg['name'], 0, 2));
                            $is_unread = !$msg['is_read'];
                        ?>
                        <tr class="<?php echo $is_unread ? 'unread' : ''; ?>">
                            <td>
                                <input type="checkbox" class="message-checkbox" value="<?php echo $msg['id']; ?>">
                            </td>
                            <td>
                                <div class="message-preview">
                                    <div class="message-avatar"><?php echo $avatar_initials; ?></div>
                                    <div class="message-details">
                                        <div class="message-subject">
                                            <span class="message-status <?php echo $is_unread ? 'status-unread' : 'status-read'; ?>"></span>
                                            <?php echo htmlspecialchars($msg['subject'] ?: 'No Subject'); ?>
                                        </div>
                                        <div class="message-sender">
                                            <?php echo htmlspecialchars($msg['name']); ?> &lt;<?php echo htmlspecialchars($msg['email']); ?>&gt;
                                        </div>
                                        <div class="message-excerpt">
                                            <?php echo htmlspecialchars(substr($msg['message'], 0, 150)); ?>...
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="message-meta">
                                    <?php echo date('M j, Y', strtotime($msg['created_at'])); ?><br>
                                    <small><?php echo date('g:i a', strtotime($msg['created_at'])); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=view&id=<?php echo $msg['id']; ?>" class="action-btn action-btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?action=view&id=<?php echo $msg['id']; ?>#reply" class="action-btn action-btn-reply" title="Reply">
                                        <i class="fas fa-reply"></i>
                                    </a>
                                    <a href="?action=toggle_read&id=<?php echo $msg['id']; ?>" class="action-btn action-btn-toggle" title="<?php echo $is_unread ? 'Mark as Read' : 'Mark as Unread'; ?>">
                                        <i class="fas fa-<?php echo $is_unread ? 'envelope-open' : 'envelope'; ?>"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $msg['id']; ?>" class="action-btn action-btn-delete" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this message?')">
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
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&status=<?php echo $status_filter; ?>" 
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
                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&status=<?php echo $status_filter; ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Messages Found</h4>
                <p><?php echo !empty($search) ? 'No messages match your search criteria.' : 'Your inbox is empty.'; ?></p>
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
        
        // Select all functionality
        document.getElementById('selectAllHeader').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('selectAll').checked = this.checked;
        });
        
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('selectAllHeader').checked = this.checked;
        });
        
        // Individual checkbox click
        document.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectAllState();
            });
        });
        
        function updateSelectAllState() {
            const checkboxes = document.querySelectorAll('.message-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const someChecked = Array.from(checkboxes).some(cb => cb.checked);
            
            document.getElementById('selectAllHeader').checked = allChecked;
            document.getElementById('selectAllHeader').indeterminate = someChecked && !allChecked;
            document.getElementById('selectAll').checked = allChecked;
            document.getElementById('selectAll').indeterminate = someChecked && !allChecked;
        }
        
        // Mark selected as read
        function markSelectedAsRead() {
            const selectedIds = getSelectedMessageIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one message.');
                return;
            }
            
            if (confirm(`Mark ${selectedIds.length} message(s) as read?`)) {
                window.location.href = `?action=toggle_read&id=${selectedIds[0]}`;
                // Note: For multiple messages, you would need a bulk action endpoint
            }
        }
        
        // Delete selected
        function deleteSelected() {
            const selectedIds = getSelectedMessageIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one message.');
                return;
            }
            
            if (confirm(`Delete ${selectedIds.length} message(s)? This action cannot be undone.`)) {
                window.location.href = `?action=delete&id=${selectedIds[0]}`;
                // Note: For multiple messages, you would need a bulk action endpoint
            }
        }
        
        function getSelectedMessageIds() {
            const checkboxes = document.querySelectorAll('.message-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        // Confirm before deleting individual messages
        document.querySelectorAll('.action-btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this message?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Auto-focus on search field
        <?php if (empty($action) || $action != 'view'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const searchField = document.querySelector('input[name="search"]');
            if (searchField) {
                searchField.focus();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>