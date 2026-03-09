<?php
/**
 * Library Management System - Configuration
 * This file contains environment-specific configurations
 */

// Application settings
define('APP_NAME', 'Bagmati School Library Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, production

// API Configuration
define('API_DEBUG', true); // Set to false in production
define('API_TIMEOUT', 30); // seconds

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('REMEMBER_ME_DURATION', 7 * 24 * 3600); // 7 days

// Email Configuration (for future use)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-password');
define('MAIL_FROM', 'noreply@bagmatischool.edu.np');
define('MAIL_FROM_NAME', 'Bagmati School Library');

// Security Settings
define('BCRYPT_COST', 12); // Password hashing cost
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 15 * 60); // 15 minutes

// Report Settings
define('REPORTS_PER_PAGE', 50);
define('MAX_REPORT_RANGE', 365); // days

// Notification Settings
define('SEND_EMAIL_NOTIFICATIONS', false); // Set to true when email configured
define('SEND_SMS_NOTIFICATIONS', false); // Set to true when SMS provider configured

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_CRITICAL);
    ini_set('display_errors', 0);
}

// CORS Settings
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8000',
    'http://localhost:3000',
    'http://192.168.1.*',
    // Add your domain here
]);

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // requests
define('RATE_LIMIT_WINDOW', 3600); // seconds (1 hour)

// Cache Settings
define('CACHE_ENABLED', false); // Set to true for production
define('CACHE_DRIVER', 'file'); // file, redis, memcached
define('CACHE_EXPIRE', 3600); // seconds

?>
