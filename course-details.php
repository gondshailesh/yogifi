<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/dbconnect.php';

// Check if course ID is provided
if(!isset($_GET['id'])) {
    header("Location: courses.php");
    exit();
}

$course_id = intval($_GET['id']);
$is_logged_in = isset($_SESSION['user_id']);
$is_enrolled = false;
$error_message = '';
$success_message = '';

// Get course details with safe query
$course_query = "SELECT c.*, u.full_name as instructor_name 
                 FROM courses c 
                 LEFT JOIN users u ON c.instructor_id = u.id 
                 WHERE c.id = ?";
$stmt = $conn->prepare($course_query);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course_result = $stmt->get_result();

if($course_result->num_rows == 0) {
    header("Location: courses.php");
    exit();
}

$course = $course_result->fetch_assoc();

// Check if user is enrolled (only if logged in)
if($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    // Check if enrollments table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'enrollments'");
    if($table_check->num_rows > 0) {
        $check_enroll_query = "SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?";
        $check_stmt = $conn->prepare($check_enroll_query);
        $check_stmt->bind_param("ii", $user_id, $course_id);
        $check_stmt->execute();
        $check_enroll_result = $check_stmt->get_result();
        $is_enrolled = $check_enroll_result->num_rows > 0;
    }
}

// Get course modules if modules table exists
$modules = [];
$total_modules = 0;

$table_check = $conn->query("SHOW TABLES LIKE 'modules'");
if($table_check->num_rows > 0) {
    $modules_query = "SELECT * FROM modules WHERE course_id = ? ORDER BY module_order";
    $modules_stmt = $conn->prepare($modules_query);
    $modules_stmt->bind_param("i", $course_id);
    $modules_stmt->execute();
    $modules_result = $modules_stmt->get_result();
    $modules = $modules_result->fetch_all(MYSQLI_ASSOC);
    $total_modules = count($modules);
}

// Get enrolled students count
$enrolled_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'enrollments'");
if($table_check->num_rows > 0) {
    $enrolled_query = "SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?";
    $enrolled_stmt = $conn->prepare($enrolled_query);
    $enrolled_stmt->bind_param("i", $course_id);
    $enrolled_stmt->execute();
    $enrolled_result = $enrolled_stmt->get_result();
    $enrolled_data = $enrolled_result->fetch_assoc();
    $enrolled_count = $enrolled_data['count'] ?? 0;
}

// Handle enrollment
if($is_logged_in && isset($_POST['enroll']) && !$is_enrolled) {
    // Check if enrollments table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'enrollments'");
    if($table_check->num_rows > 0) {
        $enroll_query = "INSERT INTO enrollments (user_id, course_id) VALUES (?, ?)";
        $enroll_stmt = $conn->prepare($enroll_query);
        $enroll_stmt->bind_param("ii", $user_id, $course_id);
        
        if($enroll_stmt->execute()) {
            $is_enrolled = true;
            $enrolled_count++;
            $success_message = "Successfully enrolled in this course!";
            
            // Redirect to course player after 2 seconds
            header("refresh:2;url=course-player.php?course_id=" . $course_id);
        } else {
            $error_message = "Failed to enroll. Please try again.";
        }
    } else {
        $error_message = "Enrollment system is not available yet.";
    }
}

// Handle wishlist
if($is_logged_in && isset($_POST['add_to_wishlist'])) {
    // Check if wishlist table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'wishlist'");
    if($table_check->num_rows == 0) {
        // Create wishlist table if it doesn't exist
        $create_wishlist = "CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_wishlist (user_id, course_id)
        )";
        $conn->query($create_wishlist);
    }
    
    $wishlist_query = "INSERT IGNORE INTO wishlist (user_id, course_id) VALUES (?, ?)";
    $wishlist_stmt = $conn->prepare($wishlist_query);
    $wishlist_stmt->bind_param("ii", $user_id, $course_id);
    
    if($wishlist_stmt->execute()) {
        if($wishlist_stmt->affected_rows > 0) {
            $success_message = "Course added to wishlist!";
        } else {
            $error_message = "Course is already in your wishlist.";
        }
    } else {
        $error_message = "Failed to add to wishlist.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title'] ?? 'Course Details'); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/yogify.css">
    <style>
        .course-hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), 
                        url('<?php echo !empty($course['image_url']) ? "uploads/courses/" . htmlspecialchars($course['image_url']) : "images/course-bg.jpg"; ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: 40px;
        }
        
        .course-sidebar {
            position: sticky;
            top: 90px;
        }
        
        .module-item {
            border-left: 4px solid #4CAF50;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .instructor-card {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .what-you-learn li {
            margin-bottom: 10px;
        }
        
        .requirements li {
            margin-bottom: 8px;
        }
        
        .course-tag {
            display: inline-block;
            background: #e8f5e9;
            color: #4CAF50;
            padding: 5px 15px;
            border-radius: 20px;
            margin: 5px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include_once 'includes/navbar.php'; ?>
    
    <!-- Course Hero Section -->
    <div class="course-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent p-0 mb-3">
                            <li class="breadcrumb-item"><a href="index.php" class="text-white">Home</a></li>
                            <li class="breadcrumb-item"><a href="courses.php" class="text-white">Courses</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($course['title']); ?></li>
                        </ol>
                    </nav>
                    
                    <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <span class="badge bg-success fs-6"><?php echo ucfirst($course['level'] ?? 'Beginner'); ?></span>
                        <span class="badge bg-info fs-6"><?php echo htmlspecialchars($course['category'] ?? 'Yoga'); ?></span>
                        <?php if(($course['price'] ?? 0) == 0): ?>
                            <span class="badge bg-warning fs-6">Free</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-4 text-white-50">
                        <div>
                            <i class="bi bi-person-fill me-2"></i>
                            <strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor_name'] ?? 'Not Assigned'); ?>
                        </div>
                        <div>
                            <i class="bi bi-people-fill me-2"></i>
                            <strong>Students:</strong> <?php echo $enrolled_count; ?>
                        </div>
                        <div>
                            <i class="bi bi-clock-fill me-2"></i>
                            <strong>Duration:</strong> <?php echo htmlspecialchars($course['duration'] ?? 'Self-paced'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container py-4">
        <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column - Course Content -->
            <div class="col-lg-8 mb-4">
                <!-- About Course -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h3 class="mb-4">About This Course</h3>
                        <div class="mb-4">
                            <?php echo nl2br(htmlspecialchars($course['description'] ?? 'No description available.')); ?>
                        </div>
                        
                        <!-- What You'll Learn -->
                        <?php if(!empty($course['learning_outcomes'])): ?>
                        <div class="mb-4">
                            <h4 class="mb-3">What You'll Learn</h4>
                            <div class="row">
                                <?php 
                                $outcomes = explode("\n", $course['learning_outcomes']);
                                foreach($outcomes as $outcome):
                                    if(trim($outcome)): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-start mb-3">
                                            <i class="bi bi-check-circle-fill text-success me-2 mt-1"></i>
                                            <span><?php echo htmlspecialchars(trim($outcome)); ?></span>
                                        </div>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Course Requirements -->
                        <?php if(!empty($course['requirements'])): ?>
                        <div class="mb-4">
                            <h4 class="mb-3">Requirements</h4>
                            <ul class="list-unstyled requirements">
                                <?php 
                                $requirements = explode("\n", $course['requirements']);
                                foreach($requirements as $req):
                                    if(trim($req)): ?>
                                    <li><i class="bi bi-dot me-2 text-success"></i><?php echo htmlspecialchars(trim($req)); ?></li>
                                <?php endif; endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Course Tags -->
                        <?php if(!empty($course['tags'])): ?>
                        <div>
                            <h4 class="mb-3">Course Tags</h4>
                            <div>
                                <?php 
                                $tags = explode(',', $course['tags']);
                                foreach($tags as $tag):
                                    if(trim($tag)): ?>
                                    <span class="course-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Course Curriculum -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">Course Curriculum</h3>
                        <p class="text-muted mb-0"><?php echo $total_modules; ?> modules</p>
                    </div>
                    <div class="card-body">
                        <?php if($total_modules > 0): ?>
                            <div class="accordion" id="courseAccordion">
                                <?php $module_counter = 1; ?>
                                <?php foreach($modules as $module): ?>
                                <div class="accordion-item mb-2">
                                    <h2 class="accordion-header" id="heading<?php echo $module['id']; ?>">
                                        <button class="accordion-button collapsed" type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $module['id']; ?>"
                                                <?php echo (!$is_enrolled) ? 'disabled' : ''; ?>>
                                            <div class="d-flex justify-content-between w-100 me-3">
                                                <div>
                                                    <span class="badge bg-secondary me-2">Module <?php echo $module_counter; ?></span>
                                                    <?php echo htmlspecialchars($module['title']); ?>
                                                </div>
                                                <div class="text-muted">
                                                    <?php if(!empty($module['duration'])): ?>
                                                        <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($module['duration']); ?>
                                                    <?php endif; ?>
                                                    <?php if($is_enrolled && !empty($module['video_url'])): ?>
                                                        <span class="badge bg-success ms-2">
                                                            <i class="bi bi-play-circle"></i> Watch
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $module['id']; ?>" 
                                         class="accordion-collapse collapse" 
                                         data-bs-parent="#courseAccordion">
                                        <div class="accordion-body">
                                            <p><?php echo nl2br(htmlspecialchars($module['description'] ?? '')); ?></p>
                                            <?php if($is_enrolled && !empty($module['video_url'])): ?>
                                                <a href="course-player.php?module_id=<?php echo $module['id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="bi bi-play-circle me-1"></i>Start Lesson
                                                </a>
                                            <?php elseif(!$is_enrolled): ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-lock me-2"></i>Enroll in the course to access this content
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php $module_counter++; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-book fs-1 text-muted mb-3"></i>
                                <p class="text-muted">Course content will be added soon.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Sidebar -->
            <div class="col-lg-4">
                <div class="course-sidebar">
                    <!-- Course Action Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <?php if(($course['price'] ?? 0) == 0): ?>
                                    <h2 class="text-success">Free</h2>
                                <?php else: ?>
                                    <h2 class="text-success">₹<?php echo number_format($course['price'], 2); ?></h2>
                                    <?php if(!empty($course['discount_price'])): ?>
                                        <del class="text-muted">₹<?php echo number_format($course['discount_price'], 2); ?></del>
                                        <span class="badge bg-danger ms-2">Save ₹<?php echo number_format($course['price'] - $course['discount_price'], 2); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Enrollment Form -->
                            <form method="POST">
                                <?php if($is_enrolled): ?>
                                    <a href="course-player.php?course_id=<?php echo $course_id; ?>" 
                                       class="btn btn-success w-100 btn-lg mb-2">
                                        <i class="bi bi-play-circle me-2"></i>Continue Learning
                                    </a>
                                    <a href="my-courses.php" class="btn btn-outline-success w-100">
                                        <i class="bi bi-list-ul me-2"></i>My Courses
                                    </a>
                                <?php else: ?>
                                    <?php if($is_logged_in): ?>
                                        <button type="submit" name="enroll" class="btn btn-success w-100 btn-lg mb-2">
                                            <?php if(($course['price'] ?? 0) == 0): ?>
                                                <i class="bi bi-arrow-right-circle me-2"></i>Enroll for Free
                                            <?php else: ?>
                                                <i class="bi bi-cart-plus me-2"></i>Enroll Now
                                            <?php endif; ?>
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php?redirect=course-details.php?id=<?php echo $course_id; ?>" 
                                           class="btn btn-success w-100 btn-lg mb-2">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Login to Enroll
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($is_logged_in): ?>
                                        <button type="submit" name="add_to_wishlist" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-heart me-2"></i>Add to Wishlist
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                            
                            <hr class="my-4">
                            
                            <!-- Course Includes -->
                            <h6 class="mb-3">This course includes:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bi bi-play-circle text-success me-2"></i>
                                    <?php echo $total_modules; ?> on-demand modules
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-infinity text-success me-2"></i>
                                    Full lifetime access
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-phone text-success me-2"></i>
                                    Access on mobile and desktop
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-award text-success me-2"></i>
                                    Certificate of completion
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Instructor Card -->
                    <!-- Instructor Card -->
<div class="card border-0 shadow-sm instructor-card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Instructor</h5>
                 </div>
                 <div class="card-body text-center">
                     <div class="mb-3">
                         <?php 
                         $instructor_id = $course['instructor_id'] ?? 0;
                         // FIX: Changed profile_pic to profile_image
                         if($instructor_id > 0) {
                             $instructor_query = "SELECT profile_image, bio FROM users WHERE id = ?";
                             $instructor_stmt = $conn->prepare($instructor_query);
                             $instructor_stmt->bind_param("i", $instructor_id);
                             $instructor_stmt->execute();
                             $instructor_result = $instructor_stmt->get_result();
                             $instructor_data = $instructor_result->fetch_assoc();
                         } else {
                             $instructor_data = null;
                         }
                         ?>
                         <img src="uploads/profiles/<?php echo !empty($instructor_data['profile_image']) ? htmlspecialchars($instructor_data['profile_image']) : 'default-avatar.jpg'; ?>" 
                              class="rounded-circle" 
                              width="100" 
                              height="100" 
                              style="object-fit: cover;"
                              onerror="this.src='uploads/profiles/default-avatar.jpg'">
                     </div>
                     <h5><?php echo htmlspecialchars($course['instructor_name'] ?? 'Not Assigned'); ?></h5>
                     <p class="text-muted">Certified Yoga Instructor</p>
                     <?php if(!empty($instructor_data['bio'])): ?>
                         <p class="small"><?php echo nl2br(htmlspecialchars($instructor_data['bio'])); ?></p>
                     <?php endif; ?>
                 </div>
             </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-expand first module if enrolled
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($is_enrolled && $total_modules > 0): ?>
                const firstModule = document.querySelector('.accordion-button');
                if(firstModule && !firstModule.disabled) {
                    firstModule.click();
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>