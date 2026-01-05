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
    $user_id = $_POST['id'] ?? 0;
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $user_type = $_POST['user_type'];
    $bio = trim($_POST['bio']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate
    if (empty($username) || empty($email)) {
        $message = "Username and Email are required!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
        $message_type = "error";
    } else {
        // Check if username or email already exists (for new users or when changing)
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $username, $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Username or email already exists!";
            $message_type = "error";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            
            if ($user_id > 0) {
                // Update existing user
                $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, user_type = ?, bio = ?, is_active = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $username, $email, $full_name, $user_type, $bio, $is_active, $user_id);
                $action_text = "updated";
            } else {
                // Add new user
                $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
                $sql = "INSERT INTO users (username, email, password, full_name, user_type, bio, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $username, $email, $password, $full_name, $user_type, $bio, $is_active);
                $action_text = "added";
            }
            
            if ($stmt->execute()) {
                $message = "User {$action_text} successfully!";
                $message_type = "success";
                
                // If new user, get the ID
                if ($user_id == 0) {
                    $user_id = $stmt->insert_id;
                }
                
                // Handle profile image upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = $_FILES['profile_image']['type'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        $upload_dir = '../uploads/profiles/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $filepath)) {
                            // Update user with profile image path
                            $update_sql = "UPDATE users SET profile_image = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $image_path = 'uploads/profiles/' . $filename;
                            $update_stmt->bind_param("si", $image_path, $user_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                }
            } else {
                $message = "Error: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Delete User
if ($action == 'delete' && $id > 0) {
    // Don't allow deleting admin users
    $check_sql = "SELECT user_type FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $user = $check_result->fetch_assoc();
        if ($user['user_type'] != 'admin') {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting user: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Cannot delete admin users!";
            $message_type = "error";
        }
    }
    $check_stmt->close();
}

// Toggle User Status (Active/Inactive)
if ($action == 'toggle_status' && $id > 0) {
    // Don't allow deactivating admin users
    $check_sql = "SELECT user_type, is_active FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $user = $check_result->fetch_assoc();
        if ($user['user_type'] != 'admin') {
            $new_status = $user['is_active'] ? 0 : 1;
            $sql = "UPDATE users SET is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_status, $id);
            
            if ($stmt->execute()) {
                $message = "User status updated!";
                $message_type = "success";
            } else {
                $message = "Error updating status: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Cannot deactivate admin users!";
            $message_type = "error";
        }
    }
    $check_stmt->close();
}

// Get user for editing
$user = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

// Exclude admin users from listing (unless specifically requested)
if ($type_filter != 'admin') {
    $where_clause .= " AND user_type != 'admin'";
}

if (!empty($search)) {
    $where_clause .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

if (!empty($type_filter) && $type_filter != 'all') {
    $where_clause .= " AND user_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($status_filter) && $status_filter != 'all') {
    $where_clause .= " AND is_active = ?";
    $params[] = ($status_filter == 'active') ? 1 : 0;
    $types .= 'i';
}

// Build ORDER BY
$order_by = "ORDER BY ";
switch ($sort) {
    case 'oldest':
        $order_by .= "created_at ASC";
        break;
    case 'name':
        $order_by .= "username ASC";
        break;
    case 'email':
        $order_by .= "email ASC";
        break;
    default:
        $order_by .= "created_at DESC";
        break;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);
$count_stmt->close();

// Get users for current page
$sql = "SELECT * FROM users $where_clause $order_by LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
$users = $users_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Yogify Admin</title>
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
        .close-message {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            padding: 0 5px;
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
        
        /* User Form */
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
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .form-check label {
            cursor: pointer;
            font-size: 14px;
        }
        .file-upload {
            position: relative;
        }
        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: var(--light);
            border: 1px dashed var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-label:hover {
            background: #f3f4f6;
        }
        .file-preview {
            margin-top: 10px;
            max-width: 200px;
        }
        .file-preview img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        /* Users Table */
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .table tr:hover {
            background: #fafafa;
        }
        .table tr:last-child td {
            border-bottom: none;
        }
        
        /* User Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
            margin-right: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-details {
            line-height: 1.4;
        }
        .user-name {
            font-weight: 500;
            color: var(--dark);
        }
        .user-username {
            font-size: 12px;
            color: var(--gray);
        }
        .user-email {
            font-size: 12px;
            color: var(--gray);
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
        .badge-student { background: #dbeafe; color: #1d4ed8; }
        .badge-instructor { background: #fef3c7; color: #92400e; }
        .badge-admin { background: #fce7f3; color: #be185d; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        
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
            transition: all 0.3s;
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .action-btn-view { background: var(--info); }
        .action-btn-edit { background: var(--warning); }
        .action-btn-delete { background: var(--danger); }
        .action-btn-toggle { background: var(--gray); }
        .action-btn-reset { background: var(--secondary); }
        
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
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>User Management</p>
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
                <a href="manage_users.php" class="nav-link active">
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
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="?action=add" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add New User
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
        
        <!-- User Form (Add/Edit) -->
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-user-edit"></i> <?php echo $action == 'add' ? 'Add New User' : 'Edit User'; ?></h2>
                <a href="manage_users.php" class="btn btn-light">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $user['id'] ?? ''; ?>">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Username <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" 
                                   placeholder="Enter username" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   placeholder="Enter email address" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                           placeholder="Enter full name">
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">User Type</label>
                            <select name="user_type" class="form-control" required>
                                <option value="student" <?php echo ($user['user_type'] ?? '') == 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="instructor" <?php echo ($user['user_type'] ?? '') == 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Profile Image</label>
                            <div class="file-upload">
                                <input type="file" name="profile_image" class="file-input" accept="image/*" id="profileImage">
                                <label class="file-label" for="profileImage">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose profile image...</span>
                                </label>
                            </div>
                            <?php if (!empty($user['profile_image'])): ?>
                            <div class="file-preview">
                                <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Preview" id="imagePreview">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-control" placeholder="Enter user bio..." 
                              rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" value="1" 
                           <?php echo ($user['is_active'] ?? 1) == 1 ? 'checked' : ''; ?>>
                    <label for="is_active">Active User</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add User' : 'Update User'; ?>
                    </button>
                    <a href="manage_users.php" class="btn btn-light">
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
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by name, username or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> User Type</label>
                        <select name="type" class="filter-select">
                            <option value="all">All Types</option>
                            <option value="student" <?php echo $type_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="instructor" <?php echo $type_filter == 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                            <option value="admin" <?php echo $type_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-user-check"></i> Status</label>
                        <select name="status" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="email" <?php echo $sort == 'email' ? 'selected' : ''; ?>>Email (A-Z)</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-filter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_users.php" class="btn btn-light btn-filter">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Users List</h3>
                <div class="table-stats">
                    Showing <?php echo count($users); ?> of <?php echo $total_users; ?> users
                    <?php if ($total_pages > 1): ?> | Page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?>
                </div>
            </div>
            
            <div class="table-wrapper">
                <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h4>No users found</h4>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            // Generate avatar color based on user type
                            $avatar_color = $u['user_type'] == 'instructor' ? '#f59e0b' : '#3b82f6';
                            $avatar_text = strtoupper(substr($u['username'], 0, 2));
                        ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <?php if (!empty($u['profile_image'])): ?>
                                    <div class="user-avatar" style="background: <?php echo $avatar_color; ?>; overflow: hidden;">
                                        <img src="../<?php echo htmlspecialchars($u['profile_image']); ?>" alt="<?php echo htmlspecialchars($u['username']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <?php else: ?>
                                    <div class="user-avatar" style="background: <?php echo $avatar_color; ?>;">
                                        <?php echo $avatar_text; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></div>
                                        <div class="user-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $u['user_type']; ?>">
                                    <?php echo ucfirst($u['user_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $date = new DateTime($u['created_at']);
                                echo $date->format('M d, Y');
                                ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $u['id']; ?>" class="action-btn action-btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?action=toggle_status&id=<?php echo $u['id']; ?>" class="action-btn action-btn-toggle" title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $u['id']; ?>" class="action-btn action-btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
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
        </div>
    </div>

    <script>
        // Profile image preview
        document.getElementById('profileImage')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('imagePreview');
                    if (!preview) {
                        const filePreview = document.createElement('div');
                        filePreview.className = 'file-preview';
                        preview = document.createElement('img');
                        preview.id = 'imagePreview';
                        preview.alt = 'Profile Preview';
                        preview.style.width = '100%';
                        preview.style.height = 'auto';
                        preview.style.borderRadius = '8px';
                        preview.style.border = '1px solid var(--border)';
                        filePreview.appendChild(preview);
                        e.target.parentNode.parentNode.appendChild(filePreview);
                    }
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message-box');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>