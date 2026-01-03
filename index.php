<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/dbconnect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';

// Get featured courses for homepage
$featured_courses = [];
if($conn) {

    // Check if courses table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'courses'");
    if($table_check->num_rows > 0) {
        $query = "SELECT c.*, u.full_name as instructor_name 
                  FROM courses c 
                  LEFT JOIN users u ON c.instructor_id = u.id 
                  WHERE 1=1";
        
        // Check if is_featured column exists
        $col_check = $conn->query("SHOW COLUMNS FROM courses LIKE 'is_featured'");
        if($col_check->num_rows > 0) {
            $query .= " AND (c.is_featured = 1 OR c.is_featured IS NULL)";
        }
        
        // Check if is_active column exists
        $col_check = $conn->query("SHOW COLUMNS FROM courses LIKE 'is_active'");
        if($col_check->num_rows > 0) {
            $query .= " AND c.is_active = 1";
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT 6";
        $result = $conn->query($query);
        if($result) {
            $featured_courses = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Get total counts for statistics
$stats = [
    'total_courses' => 0,
    'total_students' => 0,
    'total_instructors' => 0,
    'success_rate' => 98
];

if($conn) {
    $table_check = $conn->query("SHOW TABLES LIKE 'courses'");
    if($table_check->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) as total FROM courses")->fetch_assoc();
        $stats['total_courses'] = $count['total'] ?? 0;
    }
    
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if($table_check->num_rows > 0) {
        $students = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'student'")->fetch_assoc();
        $instructors = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'instructor'")->fetch_assoc();
        $stats['total_students'] = $students['total'] ?? 0;
        $stats['total_instructors'] = $instructors['total'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo SITE_NAME; ?> - Your Yoga Journey Starts Here</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="css/yogify.css">
    <style>
        /* Custom Styles for Homepage */
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #8BC34A;
            --accent-color: #FF9800;
            --dark-color: #2E7D32;
            --light-color: #F1F8E9;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            padding: 150px 0 100px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.3), rgba(139, 195, 74, 0.3));
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .btn-hero {
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-hero-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }
        
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-hero-outline {
            border: 2px solid white;
            color: white;
            background: transparent;
        }
        
        .btn-hero-outline:hover {
            background: white;
            color: var(--primary-color);
        }
        
        /* Floating Animation */
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: var(--light-color);
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        
        .feature-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        /* Courses Section */
        .courses-section {
            padding: 100px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 15px;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .course-card-home {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            background: white;
        }
        
        .course-card-home:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .course-img-home {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }
        
        .course-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .course-content {
            padding: 25px;
        }
        
        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .course-instructor {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .instructor-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .course-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .price-free {
            color: var(--secondary-color);
        }
        
        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--dark-color), var(--primary-color));
            color: white;
            padding: 80px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: white;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Testimonials */
        .testimonials-section {
            padding: 100px 0;
            background: #f9f9f9;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .testimonial-text {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .author-info h5 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .author-info p {
            margin: 0;
            color: #888;
            font-size: 0.9rem;
        }
        
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Newsletter */
        .newsletter-section {
            padding: 80px 0;
            background: var(--light-color);
        }
        
        .newsletter-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-control-lg {
            padding: 15px 20px;
            border-radius: 50px;
            border: 2px solid #ddd;
        }
        
        .form-control-lg:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include_once 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title animate__animated animate__fadeInUp">
                        Find Your Inner Peace Through <span class="text-warning">Yoga</span>
                    </h1>
                    <p class="hero-subtitle animate__animated animate__fadeInUp animate__delay-1s">
                        Join thousands of students in their yoga journey with expert instructors, 
                        personalized courses, and flexible scheduling from anywhere.
                    </p>
                    <div class="d-flex flex-wrap gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                        <?php if($is_logged_in): ?>
                            <a href="dashboard.php" class="btn btn-hero btn-hero-primary">
                                <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                            </a>
                            <a href="courses.php" class="btn btn-hero btn-hero-outline">
                                <i class="bi bi-play-circle me-2"></i>Browse Courses
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-hero btn-hero-primary">
                                <i class="bi bi-person-plus me-2"></i>Get Started Free
                            </a>
                            <a href="courses.php" class="btn btn-hero btn-hero-outline">
                                <i class="bi bi-play-circle me-2"></i>Explore Courses
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 text-center d-none d-lg-block">
                    <div class="float-animation">
                        <i class="bi bi-flower1" style="font-size: 20rem; color: rgba(255,255,255,0.2);"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_courses']; ?>+</div>
                        <div class="stat-label">Yoga Courses</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_students']; ?>+</div>
                        <div class="stat-label">Happy Students</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_instructors']; ?>+</div>
                        <div class="stat-label">Expert Instructors</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['success_rate']; ?>%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Yogify?</h2>
                <p>Discover the benefits of practicing yoga with our platform</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="feature-title">Expert Instructors</h3>
                        <p class="text-muted">Learn from certified yoga instructors with years of experience in various yoga styles.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-play-circle"></i>
                        </div>
                        <h3 class="feature-title">Flexible Learning</h3>
                        <p class="text-muted">Access courses anytime, anywhere at your own pace with lifetime access to materials.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-heart"></i>
                        </div>
                        <h3 class="feature-title">Holistic Approach</h3>
                        <p class="text-muted">Focus on physical, mental, and spiritual well-being through comprehensive yoga practice.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses -->
    <section class="courses-section">
        <div class="container">
            <div class="section-title">
                <h2>Featured Courses</h2>
                <p>Start your journey with our most popular yoga courses</p>
            </div>
            
            <?php if(!empty($featured_courses)): ?>
                <div class="row g-4">
                    <?php foreach($featured_courses as $course): 
                        $course_image = 'images/course-default.jpg';
                        if(!empty($course['image_url']) && file_exists('uploads/courses/' . $course['image_url'])) {
                            $course_image = 'uploads/courses/' . $course['image_url'];
                        }
                    ?>
                    <div class="col-md-4 animate-on-scroll">
                        <div class="course-card-home">
                            <div class="position-relative">
                                <img src="<?php echo $course_image; ?>" 
                                     class="course-img-home" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>"
                                     onerror="this.src='images/course-default.jpg'">
                                <?php if(($course['price'] ?? 0) == 0): ?>
                                    <span class="course-badge bg-success">Free</span>
                                <?php else: ?>
                                    <span class="course-badge">₹<?php echo number_format($course['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="course-content">
                                <h4 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h4>
                                <div class="course-meta">
                                    <div class="course-instructor">
                                        <i class="bi bi-person"></i>
                                        <span><?php echo htmlspecialchars($course['instructor_name'] ?? 'Expert Instructor'); ?></span>
                                    </div>
                                    <?php if(!empty($course['level'])): ?>
                                        <span class="badge bg-<?php 
                                            echo $course['level'] == 'beginner' ? 'success' : 
                                                 ($course['level'] == 'intermediate' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($course['level']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted mb-3">
                                    <?php 
                                    $desc = strip_tags($course['description'] ?? '');
                                    echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc;
                                    ?>
                                </p>
                                <a href="course-details.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-outline-success w-100">
                                    <i class="bi bi-eye me-1"></i>View Course
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-5">
                    <a href="courses.php" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-arrow-right me-2"></i>View All Courses
                    </a>
                </div>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-flower1" style="font-size: 4rem; color: #ddd;"></i>
                    <h4 class="mt-3">Courses Coming Soon</h4>
                    <p class="text-muted">We're preparing amazing yoga courses for you</p>
                    <?php if(!$is_logged_in): ?>
                        <a href="register.php" class="btn btn-success mt-3">
                            <i class="bi bi-bell me-1"></i>Notify Me
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-title">
                <h2>What Our Students Say</h2>
                <p>Real experiences from our yoga community</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 animate-on-scroll">
                    <div class="testimonial-card">
                        <div class="testimonial-text">
                            "Yogify transformed my life! The beginner courses helped me build a solid foundation, and now I practice daily."
                        </div>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/women/32.jpg" 
                                 class="author-avatar" 
                                 alt="Sarah Johnson">
                            <div class="author-info">
                                <h5>Sarah Johnson</h5>
                                <p>Beginner Yogi</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animate-on-scroll">
                    <div class="testimonial-card">
                        <div class="testimonial-text">
                            "As a busy professional, the flexible schedule and expert guidance helped me maintain consistency in my practice."
                        </div>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/men/54.jpg" 
                                 class="author-avatar" 
                                 alt="Michael Chen">
                            <div class="author-info">
                                <h5>Michael Chen</h5>
                                <p>Software Developer</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animate-on-scroll">
                    <div class="testimonial-card">
                        <div class="testimonial-text">
                            "The meditation courses helped me manage stress effectively. I feel more peaceful and focused every day."
                        </div>
                        <div class="testimonial-author">
                            <img src="https://randomuser.me/api/portraits/women/67.jpg" 
                                 class="author-avatar" 
                                 alt="Priya Sharma">
                            <div class="author-info">
                                <h5>Priya Sharma</h5>
                                <p>Meditation Enthusiast</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Start Your Yoga Journey Today</h2>
            <p class="cta-subtitle">
                Join our community of yoga enthusiasts and experience the transformation 
                in your physical and mental well-being.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <?php if($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-light btn-lg px-5 py-3">
                        <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                    </a>
                    <a href="courses.php" class="btn btn-outline-light btn-lg px-5 py-3">
                        <i class="bi bi-play-circle me-2"></i>Browse Courses
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-light btn-lg px-5 py-3">
                        <i class="bi bi-person-plus me-2"></i>Sign Up Free
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg px-5 py-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="mb-4">Stay Updated</h2>
                    <p class="text-muted mb-4">Subscribe to our newsletter for yoga tips, new courses, and special offers</p>
                    <form class="newsletter-form">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control form-control-lg" 
                                   placeholder="Enter your email address" required>
                            <button class="btn btn-success btn-lg px-4" type="submit">
                                <i class="bi bi-envelope-check"></i>
                            </button>
                        </div>
                        <small class="text-muted">We respect your privacy. Unsubscribe at any time.</small>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include_once 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll animations
        function checkScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }
        
        // Check on scroll and load
        window.addEventListener('scroll', checkScroll);
        window.addEventListener('load', checkScroll);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Newsletter form submission
        document.querySelector('.newsletter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            if(email) {
                alert('Thank you for subscribing to our newsletter!');
                this.reset();
            }
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if(target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>