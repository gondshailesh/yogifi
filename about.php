<?php
session_start();
include_once 'includes/dbconnect.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Yogify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .about-hero {
            background: linear-gradient(rgba(76, 175, 80, 0.9), rgba(139, 195, 74, 0.9));
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
        }
        .team-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .team-card:hover {
            transform: translateY(-10px);
        }
        .team-img {
            height: 250px;
            object-fit: cover;
        }
        .value-card {
            text-align: center;
            padding: 30px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }
        .value-icon {
            font-size: 3rem;
            color: #4CAF50;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation (Same as index.php) -->
    <?php include_once 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <div class="about-hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-4">About Yogify</h1>
                    <p class="lead mb-4">Your journey to mindfulness and wellness begins here. We're dedicated to making yoga accessible to everyone.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Our Story -->
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="mb-4">Our Story</h2>
                <p class="lead">Founded in 2023, Yogify was born from a simple idea: to make yoga accessible to everyone, everywhere.</p>
                <p>We believe that yoga is not just about physical postures, but a holistic practice that brings balance to mind, body, and spirit. Our platform brings together expert instructors, comprehensive courses, and a supportive community to help you on your yoga journey.</p>
                <p>Whether you're a complete beginner or an experienced practitioner, Yogify offers something for everyone. Our mission is to empower individuals to lead healthier, more balanced lives through the practice of yoga.</p>
            </div>
            <div class="col-lg-6">
                <img src="images/Team.webp" alt="Yoga Practice" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>

    <!-- Our Values -->
    <div class="bg-light py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <h2 class="display-5 fw-bold mb-3">Our Values</h2>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="value-card">
                        <i class="bi bi-heart value-icon"></i>
                        <h4>Mindfulness</h4>
                        <p>We promote conscious awareness and presence in every practice</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="value-card">
                        <i class="bi bi-people value-icon"></i>
                        <h4>Community</h4>
                        <p>Building a supportive community where everyone feels welcome</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="value-card">
                        <i class="bi bi-award value-icon"></i>
                        <h4>Excellence</h4>
                        <p>Providing high-quality instruction from certified experts</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Section -->
    <div class="container py-5">
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="display-5 fw-bold mb-3">Meet Our Team</h2>
                <p class="lead">Certified yoga instructors with years of experience</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="team-card">
                    <img src="images/team3.png" alt="Instructor" class="img-fluid team-img w-100">
                    <div class="p-4">
                        <h4>Sharma Vinita</h4>
                        <p class="text-muted">Lead Instructor</p>
                        <p>500-hour RYT certified with 10 years of teaching experience</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-card">
                    <img src="images/team2.png" alt="Instructor" class="img-fluid team-img w-100">
                    <div class="p-4">
                        <h4>Kulkarni Shyam</h4>
                        <p class="text-muted">Yoga Therapist</p>
                        <p>Specialized in therapeutic yoga for injury recovery</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="team-card">
                    <img src="images/Team1.png" alt="Instructor" class="img-fluid team-img w-100">
                    <div class="p-4">
                        <h4>Priya Sharma</h4>
                        <p class="text-muted">Meditation Guide</p>
                        <p>Expert in mindfulness and meditation techniques</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>