<?php
// reset_user_password.php
session_start();
require_once 'includes/config.php';
require_once 'includes/dbconnect.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: admin/index.php");
    exit();
}

$id = $_GET['id'] ?? 0;
$message = '';
$message_type = '';

if ($id > 0) {
    // Get user info
    $sql = "SELECT username, email, full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $new_password = trim($_POST['new_password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (empty($new_password) || empty($confirm_password)) {
                $message = "Both password fields are required!";
                $message_type = "error";
            } elseif ($new_password !== $confirm_password) {
                $message = "Passwords do not match!";
                $message_type = "error";
            } elseif (strlen($new_password) < 6) {
                $message = "Password must be at least 6 characters long!";
                $message_type = "error";
            } else {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $id);
                
                if ($update_stmt->execute()) {
                    $message = "Password reset successfully for user: " . htmlspecialchars($user['username']);
                    $message_type = "success";
                    
                    // Log the action
                    $log_sql = "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $admin_name = $_SESSION['full_name'];
                    $admin_email = $_SESSION['email'];
                    $subject = "Password Reset by Admin";
                    $log_message = "Admin '{$_SESSION['username']}' reset password for user '{$user['username']}' on " . date('Y-m-d H:i:s');
                    $log_stmt->bind_param("ssss", $admin_name, $admin_email, $subject, $log_message);
                    $log_stmt->execute();
                    $log_stmt->close();
                } else {
                    $message = "Error updating password: " . $conn->error;
                    $message_type = "error";
                }
                $update_stmt->close();
            }
        }
    } else {
        $message = "User not found!";
        $message_type = "error";
    }
    $stmt->close();
} else {
    $message = "Invalid user ID!";
    $message_type = "error";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset User Password - Yogify Admin</title>
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
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h1 {
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .user-info {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
        }
        
        .user-info p {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .user-info strong {
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--gray);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-back {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .message-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
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
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: var(--danger); }
        .strength-medium { color: var(--warning); }
        .strength-strong { color: var(--success); }
        
        @media (max-width: 480px) {
            .reset-box {
                padding: 30px 20px;
            }
            
            .reset-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-box">
            <div class="reset-header">
                <h1><i class="fas fa-key"></i> Reset User Password</h1>
                <p>Reset password for a specific user</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message-box message-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($user)): ?>
            <div class="user-info">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">New Password (minimum 6 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" placeholder="Enter new password" required
                               onkeyup="checkPasswordStrength(this.value)">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" placeholder="Confirm new password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" class="password-strength"></div>
                </div>
                
                <button type="submit" class="btn-reset">
                    <i class="fas fa-sync-alt"></i> Reset Password
                </button>
                
                <a href="admin/manage_users.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to User Management
                </a>
            </form>
            <?php else: ?>
            <div style="text-align: center; padding: 20px;">
                <p>No user selected or user not found.</p>
                <a href="admin/manage_users.php" class="btn-back" style="margin-top: 20px;">
                    <i class="fas fa-arrow-left"></i> Back to User Management
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.parentNode.querySelector('.password-toggle i');
            if (field.type === 'password') {
                field.type = 'text';
                toggle.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                toggle.className = 'fas fa-eye';
            }
        }
        
        function checkPasswordStrength(password) {
            const strengthText = document.getElementById('passwordStrength');
            let strength = '';
            let color = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 6) {
                strength = 'Weak (minimum 6 characters)';
                color = 'strength-weak';
            } else if (password.length < 8) {
                strength = 'Medium';
                color = 'strength-medium';
            } else {
                // Check for complexity
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                const complexity = (hasUpperCase + hasLowerCase + hasNumbers + hasSpecial);
                
                if (complexity >= 3) {
                    strength = 'Strong';
                    color = 'strength-strong';
                } else if (complexity >= 2) {
                    strength = 'Medium';
                    color = 'strength-medium';
                } else {
                    strength = 'Weak (add variety)';
                    color = 'strength-weak';
                }
            }
            
            strengthText.textContent = strength;
            strengthText.className = 'password-strength ' + color;
            
            // Check password match
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.textContent = '';
            } else if (password === confirm) {
                matchText.textContent = '✓ Passwords match';
                matchText.className = 'password-strength strength-strong';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.className = 'password-strength strength-weak';
            }
        }
        
        document.getElementById('confirm_password').addEventListener('keyup', checkPasswordMatch);
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters long!');
                e.preventDefault();
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match!');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Are you sure you want to reset this user\'s password?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Focus on first field
        document.getElementById('new_password')?.focus();
    </script>
</body>
</html>