<?php
// emergency_admin_reset.php - Use only in emergency!
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🚨 EMERGENCY ADMIN PASSWORD RESET</h2>";
echo "<p style='color: red;'>WARNING: Use this only if you cannot login to admin panel!</p>";

require_once '../includes/config.php';
require_once '../includes/dbconnect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $username = 'admin';
    $new_password = 'password'; // Default password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // First, check if admin exists
    $check_sql = "SELECT id FROM users WHERE username = 'admin' AND user_type = 'admin'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        // Update password
        $update_sql = "UPDATE users SET password = ? WHERE username = 'admin' AND user_type = 'admin'";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("s", $hashed_password);
        
        if ($stmt->execute()) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
            echo "<strong>✓ SUCCESS!</strong> Admin password has been reset.<br>";
            echo "Username: <strong>admin</strong><br>";
            echo "Password: <strong>password</strong><br>";
            echo "</div>";
            
            echo "<p><a href='admin/index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
            echo "<strong>✗ ERROR:</strong> " . $conn->error;
            echo "</div>";
        }
        $stmt->close();
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
        echo "Admin user not found. Creating new admin user...<br>";
        
        // Create new admin user
        $create_sql = "INSERT INTO users (username, email, password, full_name, user_type, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $create_stmt = $conn->prepare($create_sql);
        $email = 'admin@yogify.com';
        $full_name = 'System Administrator';
        $user_type = 'admin';
        $is_active = 1;
        $create_stmt->bind_param("sssssi", $username, $email, $hashed_password, $full_name, $user_type, $is_active);
        
        if ($create_stmt->execute()) {
            echo "<strong>✓ Created new admin user!</strong><br>";
            echo "Username: <strong>admin</strong><br>";
            echo "Password: <strong>password</strong><br>";
            echo "Email: <strong>admin@yogify.com</strong><br>";
            
            echo "<p><a href='admin/index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>";
        } else {
            echo "<strong>✗ ERROR creating admin:</strong> " . $conn->error;
        }
        $create_stmt->close();
    }
}

$conn->close();
?>

<hr>

<form method="POST" action="" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
    <h3>Reset Admin Password</h3>
    <p>This will reset the admin password to: <strong>password</strong></p>
    <input type="hidden" name="reset" value="1">
    <button type="submit" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
        🚨 EMERGENCY RESET ADMIN PASSWORD
    </button>
    <p style="font-size: 12px; color: #666; margin-top: 10px;">
        <strong>Note:</strong> After reset, login with:<br>
        Username: <code>admin</code><br>
        Password: <code>password</code>
    </p>
</form>

<hr>

<h3>Direct SQL Commands (for phpMyAdmin):</h3>
<pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; font-size: 12px;">
-- Reset admin password to 'password'
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'admin' AND user_type = 'admin';

-- Check admin users
SELECT id, username, email, full_name, created_at FROM users WHERE user_type = 'admin';

-- Create new admin user (if admin doesn't exist)
INSERT INTO users (username, email, password, full_name, user_type, is_active) VALUES 
('admin', 'admin@yogify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
 'System Administrator', 'admin', 1);
</pre>

<p><a href="index.php" style="color: #007bff; text-decoration: none;">← Back to Main Site</a></p>