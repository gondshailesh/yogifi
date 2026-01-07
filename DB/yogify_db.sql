-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 05, 2026 at 05:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yogify_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `duration` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `thumbnail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `instructor_id`, `category`, `level`, `duration`, `price`, `thumbnail`, `created_at`, `is_published`, `is_active`) VALUES
(1, 'Yoga for Beginners', 'Learn basic yoga poses', NULL, 'Hatha Yoga', 'beginner', '', 0.00, 'uploads/courses/course_1_1767628785.jpg', '2025-12-31 13:50:21', 1, 1),
(2, 'Power Vinyasa Flow', 'Dynamic yoga sequences', NULL, 'Vinyasa Yoga', 'intermediate', NULL, 2999.00, NULL, '2025-12-31 13:50:21', 0, 1),
(3, 'Mindfulness Meditation', 'Meditation techniques', NULL, 'Meditation', 'beginner', NULL, 1999.00, NULL, '2025-12-31 13:50:21', 0, 1),
(4, 'yoga basic', 'basic yoga', 2, 'Yin Yoga', 'beginner', '2 week', 2000.00, 'uploads/courses/course_4_1767628853.jpg', '2026-01-05 15:58:59', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `progress_percent` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrolled_at`, `progress_percent`, `completed`) VALUES
(1, 8, 1, '2025-12-31 15:06:25', 0, 0),
(2, 8, 2, '2025-12-31 15:08:23', 0, 0),
(3, 9, 1, '2026-01-02 09:03:29', 0, 0),
(4, 11, 2, '2026-01-03 06:00:47', 0, 0),
(5, 12, 4, '2026-01-05 16:17:51', 0, 0),
(6, 12, 2, '2026-01-05 16:23:16', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `module_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `course_id`, `title`, `description`, `video_url`, `duration`, `module_order`, `created_at`) VALUES
(1, 1, 'Introduction to Yoga', 'Learn the basics of yoga and its benefits', 'https://www.youtube.com/embed/v7AYKMP6rOE', '15:00', 1, '2025-12-31 14:07:20'),
(2, 1, 'Basic Breathing Techniques', 'Learn Pranayama - the art of yogic breathing', 'https://www.youtube.com/embed/v7AYKMP6rOE', '20:00', 2, '2025-12-31 14:07:20'),
(3, 1, 'Sun Salutation (Surya Namaskar)', 'Complete guide to Sun Salutation sequence', 'https://www.youtube.com/embed/v7AYKMP6rOE', '25:00', 3, '2025-12-31 14:07:20'),
(4, 1, 'Basic Standing Poses', 'Learn foundational standing yoga poses', 'https://www.youtube.com/embed/v7AYKMP6rOE', '30:00', 4, '2025-12-31 14:07:20'),
(5, 1, 'Seated Poses & Flexibility', 'Improve flexibility with seated poses', 'https://www.youtube.com/embed/v7AYKMP6rOE', '20:00', 5, '2025-12-31 14:07:20'),
(6, 2, 'Vinyasa Flow Fundamentals', 'Understanding the flow between poses', 'https://www.youtube.com/embed/v7AYKMP6rOE', '20:00', 1, '2025-12-31 14:07:20'),
(7, 2, 'Building Strength', 'Poses to build core and upper body strength', 'https://www.youtube.com/embed/v7AYKMP6rOE', '25:00', 2, '2025-12-31 14:07:20'),
(8, 2, 'Advanced Transitions', 'Smooth transitions between challenging poses', 'https://www.youtube.com/embed/v7AYKMP6rOE', '30:00', 3, '2025-12-31 14:07:20'),
(9, 3, 'Introduction to Meditation', 'What is meditation and how to start', 'https://www.youtube.com/embed/v7AYKMP6rOE', '15:00', 1, '2025-12-31 14:07:20'),
(10, 3, 'Breathing Meditation', 'Using breath as an anchor for mindfulness', 'https://www.youtube.com/embed/v7AYKMP6rOE', '20:00', 2, '2025-12-31 14:07:20'),
(11, 3, 'Body Scan Meditation', 'Progressive relaxation technique', 'https://www.youtube.com/embed/v7AYKMP6rOE', '25:00', 3, '2025-12-31 14:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `course_id`, `rating`, `comment`, `created_at`) VALUES
(1, 3, 1, 5, 'Excellent for beginners!', '2025-12-31 13:50:21'),
(2, 3, 2, 4, 'Challenging but rewarding', '2025-12-31 13:50:21');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `zoom_link` varchar(500) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`id`, `title`, `description`, `instructor_id`, `start_time`, `end_time`, `zoom_link`, `max_participants`, `created_at`) VALUES
(1, 'Dhyan', 'How can improve spritual health', 2, '2026-01-05 23:01:00', '2026-01-10 23:03:00', 'https://meet.google.com/ams-mger-qfc', 50, '2026-01-05 15:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_registrations`
--

CREATE TABLE `schedule_registrations` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_registrations`
--

INSERT INTO `schedule_registrations` (`id`, `schedule_id`, `user_id`, `registered_at`, `attended`) VALUES
(1, 1, 11, '2026-01-05 15:32:15', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `user_type` enum('student','instructor','admin') DEFAULT 'student',
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `user_type`, `profile_image`, `bio`, `created_at`, `is_active`, `reset_token`, `reset_expiry`, `last_login`) VALUES
(2, 'instructor', 'instructor@yogify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Yoga Master', 'instructor', 'uploads/profiles/profile_2_1767630152.png', '', '2025-12-31 02:37:58', 1, NULL, NULL, NULL),
(3, 'student', 'student@yogify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Student', 'student', NULL, NULL, '2025-12-31 02:37:58', 1, NULL, NULL, NULL),
(4, 'shailesh', 'shailesh@gmail.com', '$2y$10$UiUCvoTyMz5g.EagUh7hu.gykU5OqmAUGLnFQiWcjW6Tlu8toio4m', 'shailesh shivaji gond', 'student', NULL, NULL, '2025-12-31 02:51:28', 1, NULL, NULL, NULL),
(8, 'a', 'a@gmail.com', '$2y$10$p/zT1D0XYQ/4mqK215PLLufQY.Ywg9AY7urMI9qgmqBox7ZHcyRLK', 'a', 'student', NULL, NULL, '2025-12-31 04:02:43', 1, NULL, NULL, '2025-12-31 22:37:18'),
(9, 'b', 'b@gmail.com', '$2y$10$7omPwmfLnVCMSBcpJnuqy.nzIoc96ejeoAg0mDwOLwzyfpJageOy6', 'b', 'instructor', NULL, NULL, '2026-01-01 10:01:53', 1, NULL, NULL, '2026-01-03 22:41:42'),
(10, 'mrunal', 'mrunal@gmail.com', '$2y$10$KsWM2jJtBCpm6CBDL5PGoOssSQ20nfiusY3GJaOMgXkKrX4dmnw3O', 'mrunal', 'student', NULL, NULL, '2026-01-01 10:18:30', 1, NULL, NULL, NULL),
(11, 'vikas', 'vikas@gmail.com', '$2y$10$xCWhFIg3Jd2i17Ou6PiFHuOFAfjE.qlQito47o3Erzm661B6Y1dEW', 'abc', 'student', 'uploads/profiles/profile_11_1767626637.jpg', '', '2026-01-03 05:58:57', 1, NULL, NULL, '2026-01-05 21:01:56'),
(12, 'admin@gmail.com', 'admin@gmail.com', '$2y$10$nM9TL/oPtkpt3RBPqwcDcuodfpabjrndfad3u6ay0QYCkc2hCed6y', 'System Administrator', 'admin', NULL, NULL, '2026-01-05 14:15:10', 1, NULL, NULL, '2026-01-05 21:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_progress`
--

CREATE TABLE `user_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `course_id`, `added_at`) VALUES
(1, 12, 2, '2026-01-05 16:22:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reviews_user` (`user_id`),
  ADD KEY `fk_reviews_course` (`course_id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `schedule_registrations`
--
ALTER TABLE `schedule_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_progress`
--
ALTER TABLE `user_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schedule_registrations`
--
ALTER TABLE `schedule_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_progress`
--
ALTER TABLE `user_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `schedule_registrations`
--
ALTER TABLE `schedule_registrations`
  ADD CONSTRAINT `schedule_registrations_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`id`),
  ADD CONSTRAINT `schedule_registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_progress`
--
ALTER TABLE `user_progress`
  ADD CONSTRAINT `user_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_progress_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`);

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
