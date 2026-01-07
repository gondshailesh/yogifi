# Yogify - Yoga Learning Platform

Yogify is a comprehensive web-based platform for learning yoga online. It provides users with courses, scheduling, progress tracking, and a complete learning management system for yoga enthusiasts of all levels.

## 🧘 Features

### User Features
- **User Registration & Authentication** - Secure signup/login with password reset functionality
- **Course Catalog** - Browse and enroll in various yoga courses
- **Course Player** - Interactive video/content player for course materials
- **Progress Tracking** - Monitor your learning progress and completion status
- **Personal Dashboard** - Central hub for user activities and enrolled courses
- **Profile Management** - Update personal information and profile pictures
- **Class Schedule** - View and manage yoga class schedules
- **Payment Integration** - Secure payment processing for course enrollment

### Admin Features
- **Admin Dashboard** - Overview of platform statistics and activities
- **User Management** - Manage user accounts and permissions
- **Course Management** - Create, update, and delete yoga courses
- **Module Management** - Organize course content into modules
- **Enrollment Management** - Track and manage course enrollments
- **Schedule Management** - Create and manage class schedules
- **Message Center** - Communicate with users

## 🗂️ Project Structure

```
yogify/
├── admin/                    # Admin panel
│   ├── dashboard.php
│   ├── manage_users.php
│   ├── manage_courses.php
│   ├── manage_modules.php
│   ├── manage_schedule.php
│   ├── enrollments.php
│   ├── messages.php
│   └── includes/
│       └── admin-sidebar.php
├── includes/                 # Shared components
│   ├── config.php
│   ├── dbconnect.php
│   ├── functions.php
│   ├── navbar.php
│   ├── footer.php
│   └── profile-sidebar.php
├── css/                      # Stylesheets
│   ├── style.css
│   └── yogify.css
├── DB/                       # Database
│   └── yogify_db.sql
├── images/                   # Static images
├── uploads/                  # User uploads
│   ├── courses/             # Course images
│   ├── profiles/            # Profile pictures
│   └── documents/           # Course documents
└── [Root PHP files]         # Main application pages
```

## 🚀 Installation

### Prerequisites
- XAMPP/WAMP/MAMP or any PHP server environment
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/yogify.git
   ```

2. **Move to web server directory**
   - Copy the `yogify` folder to your server's root directory (e.g., `htdocs` for XAMPP)

3. **Database Setup**
   - Open phpMyAdmin or MySQL client
   - Create a new database named `yogify_db`
   - Import the SQL file: `DB/yogify_db.sql`

4. **Configure Database Connection**
   - Open `includes/config.php`
   - Update database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'yogify_db');
   ```

5. **Configure Upload Directory**
   - Ensure `uploads/` directory has write permissions
   - Create subdirectories if they don't exist:
     - `uploads/courses/`
     - `uploads/profiles/`
     - `uploads/documents/`

6. **Access the Application**
   - Open browser and navigate to: `http://localhost/yogify`
   - For admin panel: `http://localhost/yogify/admin`

### Default Admin Credentials
- **Username**: `admin@gmail.com`
- **Password**: `password` (Change immediately after first login)
- Use `admin/reset_admin_pass.php` for emergency reset if needed

## 🔧 Technical Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Server**: Apache (XAMPP recommended)
- **Additional**: Bootstrap (if used), Font Awesome icons

## 📁 Important Files

### Core Configuration
- `includes/config.php` - Main configuration file
- `includes/dbconnect.php` - Database connection handler
- `includes/functions.php` - Utility functions

### Main Pages
- `index.php` - Homepage
- `courses.php` - Course listing
- `course-details.php` - Course information
- `course-player.php` - Course content player
- `dashboard.php` - User dashboard
- `profile.php` - User profile
- `my-courses.php` - User's enrolled courses
- `my-progress.php` - Learning progress tracking
- `schedule.php` - Class schedule

### Authentication
- `login.php` - User login
- `register.php` - User registration
- `forgot-password.php` - Password recovery
- `reset-password.php` - Password reset
- `logout.php` - Session logout

### Admin Panel
- `admin/dashboard.php` - Admin dashboard
- `admin/manage_users.php` - User management
- `admin/manage_courses.php` - Course management
- `admin/enrollments.php` - Enrollment tracking

## 🗄️ Database Schema

The database includes tables for:
- `users` - User accounts and information
- `courses` - Course details and metadata
- `modules` - Course modules/lessons
- `enrollments` - User course enrollments
- `progress` - User learning progress
- `schedule` - Class schedules
- `payments` - Payment records
- `messages` - User communications

## 🔐 Security Features

- Password hashing using PHP `password_hash()`
- SQL injection prevention
- Session-based authentication
- File upload validation
- XSS protection
- CSRF tokens (if implemented)

## 📦 Dependencies

- PHP extensions: PDO, MySQLi, GD Library (for image processing)
- Optional: Composer for additional PHP packages

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `config.php`
   - Verify MySQL service is running
   - Ensure database `yogify_db` exists

2. **File Upload Issues**
   - Check `uploads/` directory permissions
   - Verify PHP `upload_max_filesize` in php.ini
   - Check `post_max_size` in php.ini

3. **Session Problems**
   - Ensure cookies are enabled in browser
   - Check PHP session configuration

4. **Admin Access Issues**
   - Use `admin/emergency_admin_reset.php` for password reset
   - Verify admin email in database

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support, please:
1. Check the troubleshooting section
2. Review the code documentation
3. Create an issue on GitHub

## 🎯 Future Enhancements

- Mobile application integration
- Live yoga sessions
- Community forums
- Advanced analytics
- Certificate generation
- Multi-language support
- API development for third-party integrations

---
**Happy Yoga Learning!** 🧘‍♀️🧘‍♂️
