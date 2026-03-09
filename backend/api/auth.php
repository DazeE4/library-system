<?php
/**
 * Authentication API
 * Handles user registration, login, and authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$conn = require_once '../config/database.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Register new user
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        sendResponse(false, 'All required fields must be filled', null, 400);
    }
    
    if (!validateEmail($email)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    
    if (strlen($password) < 6) {
        sendResponse(false, 'Password must be at least 6 characters', null, 400);
    }
    
    // Check if username exists
    $result = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        sendResponse(false, 'Username already exists', null, 400);
    }
    
    // Check if email exists
    $result = $conn->query("SELECT user_id FROM users WHERE email = '$email'");
    if ($result && $result->num_rows > 0) {
        sendResponse(false, 'Email already registered', null, 400);
    }
    
    // Hash password
    $password_hash = hashPassword($password);
    
    // Insert user
    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password_hash, full_name, phone, address, role) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $stmt->bind_param("sssssss", $username, $email, $password_hash, $full_name, $phone, $address, $role);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        logAuditAction('USER_CREATED', $user_id, 'users', $user_id);
        sendResponse(true, 'Registration successful', ['user_id' => $user_id, 'username' => $username]);
    } else {
        sendResponse(false, 'Registration failed: ' . $conn->error, null, 500);
    }
}

// Login user
else if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendResponse(false, 'Username and password required', null, 400);
    }
    
    $result = $conn->query("SELECT * FROM users WHERE username = '$username' AND is_active = TRUE");
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'Invalid username or password', null, 401);
    }
    
    $user = $result->fetch_assoc();
    
    if (!verifyPassword($password, $user['password_hash'])) {
        sendResponse(false, 'Invalid username or password', null, 401);
    }
    
    // Update last login
    $last_login = date('Y-m-d H:i:s');
    $conn->query("UPDATE users SET last_login = '$last_login' WHERE user_id = {$user['user_id']}");
    
    logAuditAction('USER_LOGIN', $user['user_id'], 'users', $user['user_id']);
    
    // Return user data (without password)
    unset($user['password_hash']);
    
    sendResponse(true, 'Login successful', $user);
}

// Get user profile
else if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = sanitizeInput($_GET['user_id'] ?? '');
    
    if (empty($user_id)) {
        sendResponse(false, 'User ID required', null, 400);
    }
    
    $user = getUserById($user_id);
    
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    unset($user['password_hash']);
    sendResponse(true, 'Profile retrieved', $user);
}

// Update user profile
else if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = sanitizeInput($_POST['user_id'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    
    if (empty($user_id)) {
        sendResponse(false, 'User ID required', null, 400);
    }
    
    $user = getUserById($user_id);
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    $stmt = $conn->prepare(
        "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE user_id = ?"
    );
    
    $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);
    
    if ($stmt->execute()) {
        logAuditAction('USER_UPDATED', $user_id, 'users', $user_id);
        sendResponse(true, 'Profile updated successfully');
    } else {
        sendResponse(false, 'Update failed: ' . $conn->error, null, 500);
    }
}

// Get all users (admin only)
else if ($action === 'list_users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $role = sanitizeInput($_GET['role'] ?? '');
    $is_active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
    
    $query = "SELECT user_id, username, email, full_name, phone, role, is_active, registration_date FROM users WHERE 1=1";
    
    if (!empty($role)) {
        $query .= " AND role = '$role'";
    }
    
    if ($is_active !== null) {
        $query .= " AND is_active = $is_active";
    }
    
    $query .= " ORDER BY registration_date DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    sendResponse(true, 'Users retrieved', $users);
}

// Deactivate/activate user
else if ($action === 'toggle_user_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = sanitizeInput($_POST['user_id'] ?? '');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
    
    if (empty($user_id) || $is_active === null) {
        sendResponse(false, 'User ID and status required', null, 400);
    }
    
    $user = getUserById($user_id);
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    $conn->query("UPDATE users SET is_active = $is_active WHERE user_id = $user_id");
    
    $status = $is_active ? 'activated' : 'deactivated';
    logAuditAction('USER_' . strtoupper($status), $user_id, 'users', $user_id);
    
    sendResponse(true, 'User status updated');
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
