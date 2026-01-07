<?php
// Admin sidebar component
?>
<div class="col-md-3 col-lg-2 admin-sidebar p-0">
    <div class="p-4">
        <h4 class="text-white mb-4">
            <i class="bi bi-shield-lock me-2"></i>Admin Panel
        </h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
                   href="index.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>" 
                   href="manage_users.php">
                    <i class="bi bi-people me-2"></i> Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_courses.php' ? 'active' : ''; ?>" 
                   href="manage_courses.php">
                    <i class="bi bi-book me-2"></i> Manage Courses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_modules.php' ? 'active' : ''; ?>" 
                   href="manage_modules.php">
                    <i class="bi bi-collection me-2"></i> Course Modules
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_schedule.php' ? 'active' : ''; ?>" 
                   href="manage_schedule.php">
                    <i class="bi bi-calendar-event me-2"></i> Live Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>" 
                   href="enrollments.php">
                    <i class="bi bi-clipboard-check me-2"></i> Enrollments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" 
                   href="messages.php">
                    <i class="bi bi-envelope me-2"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../settings.php">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link text-warning" href="../dashboard.php">
                    <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                </a>
            </li>
            <li class="nav-item">
                <!-- CORRECTED: Changed from login.php to logout.php -->
                <a href="../logout.php" class="nav-link text-danger" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>