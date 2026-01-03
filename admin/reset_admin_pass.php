<?php
session_start();
require_once './includes/config.php';
require_once './includes/dbconnect.php';

// Only allow access if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: admin/index.php");
    exit();
}

$message = '';
$message_type = ''; // success, error, warning

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required!";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters long!";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match!";
        $message_type = "error";
    } else {
        // Get current admin data
        $admin_id = $_SESSION['user_id'];
        $sql = "SELECT password FROM users WHERE id = ? AND user_type = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($update_stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_type = "success";
                    
                    // Log this action (you might want to create an audit log table)
                    $log_sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $admin_name = $_SESSION['full_name'];
                    $admin_email = $_SESSION['email'];
                    $subject = "Admin Password Changed";
                    $log_message = "Admin user '{$_SESSION['username']}' changed their password on " . date('Y-m-d H:i:s');
                    $log_stmt->bind_param("ssss", $admin_name, $admin_email, $subject, $log_message);
                    $log_stmt->execute();
                    
                } else {
                    $message = "Error updating password: " . $conn->error;
                    $message_type = "error";
                }
                $update_stmt->close();
            } else {
                $message = "Current password is incorrect!";
                $message_type = "error";
            }
        } else {
            $message = "Admin account not found!";
            $message_type = "error";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Yogify</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
        }
        
        .reset-box {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .reset-header p {
            color: #666;
            font-size: 14px;
        }
        
        .admin-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }