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

// Process course form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST['id'] ?? 0;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $short_description = trim($_POST['short_description'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $level = $_POST['level'];
    $price = $_POST['price'];
    $discounted_price = $_POST['discounted_price'] ?: null;
    $category = $_POST['category'];
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $instructor_id = $_POST['instructor_id'] ?: null;
    
    // Validate
    if (empty($title)) {
        $message = "Title is required!";
        $message_type = "error";
    } else {
        // Check if course code already exists (if provided)
        if (!empty($code)) {
            $check_sql = "SELECT id FROM courses WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $course_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = "Course code already exists!";
                $message_type = "error";
                $check_stmt->close();
                $check_stmt = null;
            } else {
                $check_stmt->close();
                $check_stmt = null;
            }
        }
        
        if (!$check_stmt) { // Only proceed if no duplicate code error
            if ($course_id > 0) {
                // Update existing course - based on your actual database structure
                $sql = "UPDATE courses SET 
                        title = ?, 
                        description = ?, 
                        instructor_id = ?, 
                        category = ?, 
                        level = ?, 
                        duration = ?, 
                        price = ?, 
                        thumbnail = ?, 
                        is_published = ?, 
                        is_active = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    // Note: We're not updating code since it doesn't exist in your table
                    // We'll use thumbnail field for image path
                    $thumbnail_path = $course['thumbnail'] ?? null;
                    $stmt->bind_param("ssisssdsiii", 
                        $title, $description, $instructor_id, $category, $level, 
                        $duration, $price, $thumbnail_path, $is_published, 
                        $is_active, $course_id
                    );
                    $action_text = "updated";
                } else {
                    $message = "Database error: " . $conn->error;
                    $message_type = "error";
                }
            } else {
                // Add new course - based on your actual database structure
                $sql = "INSERT INTO courses (
                    title, description, instructor_id, category, level, 
                    duration, price, thumbnail, is_published, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $thumbnail_path = null;
                    $stmt->bind_param("ssisssdsii", 
                        $title, $description, $instructor_id, $category, $level, 
                        $duration, $price, $thumbnail_path, $is_published, 
                        $is_active
                    );
                    $action_text = "added";
                } else {
                    $message = "Database error: " . $conn->error;
                    $message_type = "error";
                }
            }
            
            if (isset($stmt) && $stmt) {
                if ($stmt->execute()) {
                    $message = "Course {$action_text} successfully!";
                    $message_type = "success";
                    
                    // If new course, get the ID
                    if ($course_id == 0) {
                        $course_id = $stmt->insert_id;
                    }
                    
                    // Handle course image upload
                    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] == 0) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $file_type = $_FILES['course_image']['type'];
                        
                        if (in_array($file_type, $allowed_types)) {
                            $upload_dir = '../uploads/courses/';
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            
                            $file_extension = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
                            $filename = 'course_' . $course_id . '_' . time() . '.' . $file_extension;
                            $filepath = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['course_image']['tmp_name'], $filepath)) {
                                // Update course with image path (store in thumbnail field)
                                $update_sql = "UPDATE courses SET thumbnail = ? WHERE id = ?";
                                $update_stmt = $conn->prepare($update_sql);
                                $image_path = 'uploads/courses/' . $filename;
                                $update_stmt->bind_param("si", $image_path, $course_id);
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
}

// Delete Course
if ($action == 'delete' && $id > 0) {
    // Check if course has enrollments (you might need to create this table)
    // For now, just delete the course
    $sql = "DELETE FROM courses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Course deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting course: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Toggle Course Status
if ($action == 'toggle_status' && $id > 0) {
    $new_status = $_GET['status'] ?? '';
    if (in_array($new_status, ['active', 'inactive'])) {
        $status_value = $new_status == 'active' ? 1 : 0;
        $sql = "UPDATE courses SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $status_value, $id);
        
        if ($stmt->execute()) {
            $message = "Course status updated!";
            $message_type = "success";
        } else {
            $message = "Error updating status: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Toggle Published Status
if ($action == 'toggle_published' && $id > 0) {
    // Get current published status
    $check_sql = "SELECT is_published FROM courses WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $course = $check_result->fetch_assoc();
        $new_status = $course['is_published'] ? 0 : 1;
        
        $sql = "UPDATE courses SET is_published = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_status, $id);
        
        if ($stmt->execute()) {
            $message = "Course published status updated!";
            $message_type = "success";
        } else {
            $message = "Error updating published status: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Get course for editing
$course = null;
if ($action == 'edit' && $id > 0) {
    $sql = "SELECT c.*, u.full_name as instructor_name 
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get all instructors for dropdown
$instructors = [];
$instructor_sql = "SELECT id, full_name, username FROM users WHERE user_type = 'instructor' AND is_active = 1 ORDER BY full_name";
$instructor_result = $conn->query($instructor_sql);
if ($instructor_result) {
    $instructors = $instructor_result->fetch_all(MYSQLI_ASSOC);
}

// Get search parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';
$status_filter = $_GET['status'] ?? '';
$published_filter = $_GET['published'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clause = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause .= " AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

if (!empty($category_filter) && $category_filter != 'all') {
    $where_clause .= " AND c.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($level_filter) && $level_filter != 'all') {
    $where_clause .= " AND c.level = ?";
    $params[] = $level_filter;
    $types .= 's';
}

if (!empty($status_filter) && $status_filter != 'all') {
    $where_clause .= " AND c.is_active = ?";
    $params[] = ($status_filter == 'active') ? 1 : 0;
    $types .= 'i';
}

if (!empty($published_filter) && $published_filter != 'all') {
    $where_clause .= " AND c.is_published = ?";
    $params[] = ($published_filter == 'published') ? 1 : 0;
    $types .= 'i';
}

// Build ORDER BY
$order_by = "ORDER BY ";
switch ($sort) {
    case 'oldest':
        $order_by .= "c.created_at ASC";
        break;
    case 'title':
        $order_by .= "c.title ASC";
        break;
    case 'price_low':
        $order_by .= "c.price ASC";
        break;
    case 'price_high':
        $order_by .= "c.price DESC";
        break;
    default:
        $order_by .= "c.created_at DESC";
        break;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM courses c
              LEFT JOIN users u ON c.instructor_id = u.id
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_courses = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_courses / $limit);
$count_stmt->close();

// Get courses for current page - UPDATED QUERY
$sql = "SELECT c.*, 
               u.full_name as instructor_name,
               u.username as instructor_username
        FROM courses c
        LEFT JOIN users u ON c.instructor_id = u.id
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
$courses_result = $stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique categories for filter
$categories_sql = "SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                AVG(price) as avg_price
              FROM courses";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Yogify Admin</title>
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
        .stat-icon.published { background: #fef3c7; color: #92400e; }
        .stat-icon.price { background: #dbeafe; color: #1d4ed8; }
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
        
        /* Course Form */
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
            margin-bottom: 10px;
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
            margin-bottom: 10px;
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
            max-width: 300px;
        }
        .file-preview img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .price-input {
            position: relative;
        }
        .price-input .currency {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        .price-input input {
            width: 100%;
            padding: 10px 10px 10px 30px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        /* Courses Grid */
        .courses-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .courses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }
        .courses-header h3 {
            color: var(--dark);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .courses-stats {
            font-size: 13px;
            color: var(--gray);
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        /* Course Card */
        .course-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 1px solid var(--border);
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .course-image {
            height: 160px;
            position: relative;
            overflow: hidden;
        }
        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .course-card:hover .course-image img {
            transform: scale(1.05);
        }
        .course-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(0,0,0,0.7);
            color: white;
        }
        .course-badge.published {
            background: var(--success);
            color: white;
        }
        .course-badge.inactive {
            background: var(--danger);
            color: white;
        }
        .course-badge.draft {
            background: var(--warning);
            color: #92400e;
        }
        .course-content {
            padding: 20px;
        }
        .course-header {
            margin-bottom: 15px;
        }
        .course-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
            line-height: 1.3;
        }
        .course-description {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 13px;
            color: var(--gray);
        }
        .course-duration, .course-level, .course-category {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .course-price {
            margin-bottom: 15px;
        }
        .current-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }
        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        .instructor-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .instructor-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .instructor-name {
            font-size: 13px;
            color: var(--dark);
        }
        .course-actions {
            display: flex;
            gap: 5px;
        }
        .course-action-btn {
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
        .course-action-btn:hover {
            transform: translateY(-2px);
        }
        .course-action-btn.edit { background: var(--warning); }
        .course-action-btn.delete { background: var(--danger); }
        .course-action-btn.view { background: var(--info); }
        .course-action-btn.toggle { background: var(--gray); }
        .course-action-btn.publish { background: var(--success); }
        
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
        .badge-beginner { background: #d1fae5; color: #065f46; }
        .badge-intermediate { background: #fef3c7; color: #92400e; }
        .badge-advanced { background: #fee2e2; color: #991b1b; }
        .badge-hatha { background: #e0e7ff; color: #3730a3; }
        .badge-vinyasa { background: #fce7f3; color: #be185d; }
        .badge-yin { background: #ecfdf5; color: #047857; }
        .badge-meditation { background: #f0f9ff; color: #0369a1; }
        
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
            grid-column: 1 / -1;
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
            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
            .courses-grid {
                grid-template-columns: 1fr;
            }
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            .course-meta {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-spa"></i> Yogify Admin</h2>
            <p>Course Management</p>
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
                <a href="manage_courses.php" class="nav-link active">
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
            <h1><i class="fas fa-book"></i> Manage Courses</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="?action=add" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Add New Course
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
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Total Courses</h3>
                <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="subtext">All courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Active Courses</h3>
                <div class="value"><?php echo $stats['active'] ?? 0; ?></div>
                <div class="subtext">Available for enrollment</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon published">
                    <i class="fas fa-globe"></i>
                </div>
                <h3>Published</h3>
                <div class="value"><?php echo $stats['published'] ?? 0; ?></div>
                <div class="subtext">Publicly visible</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon price">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <h3>Avg. Price</h3>
                <div class="value">₹<?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
                <div class="subtext">Average course price</div>
            </div>
        </div>
        
        <!-- Course Form (Add/Edit) -->
        <?php if ($action == 'add' || $action == 'edit'): ?>
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-book-medical"></i> <?php echo $action == 'add' ? 'Add New Course' : 'Edit Course'; ?></h2>
                <a href="manage_courses.php" class="btn btn-light">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $course['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label class="form-label">Course Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>" 
                           placeholder="Enter course title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Enter detailed course description..." 
                              rows="4"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">Select Category</option>
                                <option value="Hatha Yoga" <?php echo ($course['category'] ?? '') == 'Hatha Yoga' ? 'selected' : ''; ?>>Hatha Yoga</option>
                                <option value="Vinyasa Yoga" <?php echo ($course['category'] ?? '') == 'Vinyasa Yoga' ? 'selected' : ''; ?>>Vinyasa Yoga</option>
                                <option value="Meditation" <?php echo ($course['category'] ?? '') == 'Meditation' ? 'selected' : ''; ?>>Meditation</option>
                                <option value="Yin Yoga" <?php echo ($course['category'] ?? '') == 'Yin Yoga' ? 'selected' : ''; ?>>Yin Yoga</option>
                                <option value="Ashtanga Yoga" <?php echo ($course['category'] ?? '') == 'Ashtanga Yoga' ? 'selected' : ''; ?>>Ashtanga Yoga</option>
                                <option value="Prenatal Yoga" <?php echo ($course['category'] ?? '') == 'Prenatal Yoga' ? 'selected' : ''; ?>>Prenatal Yoga</option>
                                <option value="Power Yoga" <?php echo ($course['category'] ?? '') == 'Power Yoga' ? 'selected' : ''; ?>>Power Yoga</option>
                                <option value="Restorative Yoga" <?php echo ($course['category'] ?? '') == 'Restorative Yoga' ? 'selected' : ''; ?>>Restorative Yoga</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Level</label>
                            <select name="level" class="form-control">
                                <option value="beginner" <?php echo ($course['level'] ?? '') == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo ($course['level'] ?? '') == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo ($course['level'] ?? '') == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                <option value="all-levels" <?php echo ($course['level'] ?? '') == 'all-levels' ? 'selected' : ''; ?>>All Levels</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control" 
                                   value="<?php echo htmlspecialchars($course['duration'] ?? ''); ?>" 
                                   placeholder="e.g., 8 weeks, 30 hours">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label class="form-label">Price (₹)</label>
                            <div class="price-input">
                                <span class="currency">₹</span>
                                <input type="number" name="price" class="form-control" 
                                       value="<?php echo $course['price'] ?? '0'; ?>" 
                                       placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Instructor</label>
                    <select name="instructor_id" class="form-control">
                        <option value="">Select Instructor</option>
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>" 
                                <?php echo ($course['instructor_id'] ?? '') == $instructor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($instructor['full_name'] . ' (@' . $instructor['username'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Course Image</label>
                    <div class="file-upload">
                        <input type="file" name="course_image" class="file-input" accept="image/*" id="courseImage">
                        <label class="file-label" for="courseImage">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose course image...</span>
                        </label>
                    </div>
                    <?php if (!empty($course['thumbnail'])): ?>
                    <div class="file-preview">
                        <img src="../<?php echo htmlspecialchars($course['thumbnail']); ?>" alt="Course Preview" id="courseImagePreview">
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-check">
                            <input type="checkbox" name="is_published" id="is_published" value="1" 
                                   <?php echo ($course['is_published'] ?? 0) == 1 ? 'checked' : ''; ?>>
                            <label for="is_published">Published (Publicly visible)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo ($course['is_active'] ?? 1) == 1 ? 'checked' : ''; ?>>
                            <label for="is_active">Active (Available for enrollment)</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Create Course' : 'Update Course'; ?>
                    </button>
                    <a href="manage_courses.php" class="btn btn-light">
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
                               placeholder="Search courses, descriptions, or instructors..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Category</label>
                        <select name="category" class="filter-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-signal"></i> Level</label>
                        <select name="level" class="filter-select">
                            <option value="all">All Levels</option>
                            <option value="beginner" <?php echo $level_filter == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="intermediate" <?php echo $level_filter == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="advanced" <?php echo $level_filter == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            <option value="all-levels" <?php echo $level_filter == 'all-levels' ? 'selected' : ''; ?>>All Levels</option>
                        </select>
                    </div>
                </div>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <select name="status" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-globe"></i> Published</label>
                        <select name="published" class="filter-select">
                            <option value="all">All Courses</option>
                            <option value="published" <?php echo $published_filter == 'published' ? 'selected' : ''; ?>>Published Only</option>
                            <option value="draft" <?php echo $published_filter == 'draft' ? 'selected' : ''; ?>>Draft Only</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-filter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_courses.php" class="btn btn-light btn-filter">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Courses Grid -->
        <div class="courses-container">
            <div class="courses-header">
                <h3><i class="fas fa-list"></i> Courses List</h3>
                <div class="courses-stats">
                    Showing <?php echo count($courses); ?> of <?php echo $total_courses; ?> courses
                    <?php if ($total_pages > 1): ?> | Page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h4>No courses found</h4>
                <p>Try adjusting your search or filter criteria</p>
                <a href="?action=add" class="btn btn-primary mt-3">
                    <i class="fas fa-plus-circle"></i> Add Your First Course
                </a>
            </div>
            <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $c): 
                    // Generate avatar initials
                    $avatar_initials = '';
                    if (!empty($c['instructor_name'])) {
                        $names = explode(' ', $c['instructor_name']);
                        foreach($names as $n) {
                            $avatar_initials .= strtoupper(substr($n, 0, 1));
                            if (strlen($avatar_initials) >= 2) break;
                        }
                    }
                ?>
                <div class="course-card">
                    <div class="course-image">
                        <?php if (!empty($c['thumbnail'])): ?>
                        <img src="../<?php echo htmlspecialchars($c['thumbnail']); ?>" alt="<?php echo htmlspecialchars($c['title']); ?>">
                        <?php else: ?>
                        <div style="width:100%;height:100%;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);display:flex;align-items:center;justify-content:center;color:white;">
                            <i class="fas fa-spa" style="font-size:48px;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($c['is_published']): ?>
                        <span class="course-badge published">Published</span>
                        <?php elseif (!$c['is_active']): ?>
                        <span class="course-badge inactive">Inactive</span>
                        <?php else: ?>
                        <span class="course-badge draft">Draft</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="course-content">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($c['title']); ?></h3>
                            <div class="course-description">
                                <?php echo htmlspecialchars(substr($c['description'] ?? 'No description available', 0, 100) . '...'); ?>
                            </div>
                        </div>
                        
                        <div class="course-meta">
                            <div class="course-category">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($c['category'] ?? 'Uncategorized'); ?></span>
                            </div>
                            <div class="course-level">
                                <span class="badge badge-<?php echo $c['level'] ?? 'beginner'; ?>">
                                    <?php echo ucfirst($c['level'] ?? 'beginner'); ?>
                                </span>
                            </div>
                            <?php if (!empty($c['duration'])): ?>
                            <div class="course-duration">
                                <i class="far fa-clock"></i>
                                <span><?php echo htmlspecialchars($c['duration']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($c['price']) && $c['price'] > 0): ?>
                        <div class="course-price">
                            <span class="current-price">₹<?php echo number_format($c['price'], 2); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="course-price">
                            <span class="current-price">Free</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="course-footer">
                            <div class="instructor-info">
                                <?php if (!empty($c['instructor_name'])): ?>
                                <div class="instructor-avatar">
                                    <?php echo $avatar_initials ?: 'I'; ?>
                                </div>
                                <div class="instructor-name">
                                    <?php echo htmlspecialchars($c['instructor_name']); ?>
                                </div>
                                <?php else: ?>
                                <div class="instructor-name" style="color:var(--gray);font-size:12px;">
                                    No instructor assigned
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-actions">
                                <a href="?action=edit&id=<?php echo $c['id']; ?>" class="course-action-btn edit" title="Edit Course">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?action=toggle_published&id=<?php echo $c['id']; ?>" class="course-action-btn publish" title="<?php echo $c['is_published'] ? 'Unpublish' : 'Publish'; ?>">
                                    <i class="fas fa-globe"></i>
                                </a>
                                <a href="?action=toggle_status&id=<?php echo $c['id']; ?>&status=<?php echo $c['is_active'] ? 'inactive' : 'active'; ?>" class="course-action-btn toggle" title="<?php echo $c['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <a href="?action=delete&id=<?php echo $c['id']; ?>" class="course-action-btn delete" title="Delete Course" onclick="return confirm('Are you sure you want to delete this course?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Image preview for course image
        const courseImageInput = document.getElementById('courseImage');
        if (courseImageInput) {
            courseImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let previewId = 'courseImagePreview';
                        let preview = document.getElementById(previewId);
                        
                        if (!preview) {
                            const filePreview = document.createElement('div');
                            filePreview.className = 'file-preview';
                            preview = document.createElement('img');
                            preview.id = previewId;
                            preview.alt = 'Course Image Preview';
                            preview.style.width = '100%';
                            preview.style.height = 'auto';
                            preview.style.borderRadius = '8px';
                            preview.style.border = '1px solid var(--border)';
                            filePreview.appendChild(preview);
                            courseImageInput.parentNode.appendChild(filePreview);
                        }
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
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

        // Description character counter
        const descriptionTextarea = document.querySelector('textarea[name="description"]');
        if (descriptionTextarea) {
            descriptionTextarea.addEventListener('input', function() {
                const charCount = this.value.length;
                const counter = document.getElementById('charCounter') || 
                    (() => {
                        const counter = document.createElement('div');
                        counter.id = 'charCounter';
                        counter.style.fontSize = '12px';
                        counter.style.color = 'var(--gray)';
                        counter.style.marginTop = '5px';
                        this.parentNode.appendChild(counter);
                        return counter;
                    })();
                counter.textContent = `${charCount} characters`;
            });
        }
    </script>
</body>
</html>