<?php
/**
 * Utility Functions
 * Bagmati School Library Management System
 */

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    global $conn;
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return $conn->real_escape_string(trim($data));
}

/**
 * Generate fine for overdue book
 */
function calculateOverdueFine($dueDate, $finePerDay, $maxFine) {
    $today = new DateTime();
    $due = new DateTime($dueDate);
    
    if ($today <= $due) {
        return 0;
    }
    
    $diff = $today->diff($due);
    $daysOverdue = $diff->days;
    $fine = $daysOverdue * $finePerDay;
    
    return min($fine, $maxFine);
}

/**
 * Get library settings
 */
function getLibrarySetting($setting_key, $default = null) {
    global $conn;
    
    $result = $conn->query("SELECT setting_value FROM library_settings WHERE setting_key = '$setting_key'");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Check if user is admin/librarian
 */
function isLibrarian($role) {
    return in_array($role, ['librarian', 'admin']);
}

/**
 * Log audit action
 */
function logAuditAction($action_type, $user_id, $table_name, $record_id, $old_values = null, $new_values = null) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $old_values_json = $old_values ? json_encode($old_values) : null;
    $new_values_json = $new_values ? json_encode($new_values) : null;
    
    $stmt = $conn->prepare(
        "INSERT INTO audit_log (action_type, user_id, table_name, record_id, old_values, new_values, ip_address) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param("sisisss", $action_type, $user_id, $table_name, $record_id, $old_values_json, $new_values_json, $ip_address);
    return $stmt->execute();
}

/**
 * Send notification to user
 */
function sendNotification($user_id, $notification_type, $title, $message) {
    global $conn;
    
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, notification_type, title, message) 
         VALUES (?, ?, ?, ?)"
    );
    
    $stmt->bind_param("isss", $user_id, $notification_type, $title, $message);
    return $stmt->execute();
}

/**
 * Update book availability
 */
function updateBookAvailability($book_id) {
    global $conn;
    
    $result = $conn->query(
        "SELECT COUNT(*) as available FROM book_inventory 
         WHERE book_id = $book_id AND status = 'available'"
    );
    
    $row = $result->fetch_assoc();
    $available = $row['available'];
    
    $conn->query("UPDATE books SET available_copies = $available WHERE book_id = $book_id");
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Get user by ID
 */
function getUserById($user_id) {
    global $conn;
    
    $result = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get book by ID with author and publisher names
 */
function getBookById($book_id) {
    global $conn;
    
    $result = $conn->query(
        "SELECT b.*, 
                CONCAT(a.first_name, ' ', a.last_name) as author_name,
                p.name as publisher_name
         FROM books b
         LEFT JOIN authors a ON b.author_id = a.author_id
         LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
         WHERE b.book_id = $book_id"
    );
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get author by ID
 */
function getAuthorById($author_id) {
    global $conn;
    
    $result = $conn->query("SELECT * FROM authors WHERE author_id = $author_id");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get publisher by ID
 */
function getPublisherById($publisher_id) {
    global $conn;
    
    $result = $conn->query("SELECT * FROM publishers WHERE publisher_id = $publisher_id");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Search authors by name
 */
function searchAuthors($query) {
    global $conn;
    
    $query = $conn->real_escape_string($query);
    $result = $conn->query(
        "SELECT * FROM authors 
         WHERE CONCAT(first_name, ' ', last_name) LIKE '%$query%'
         ORDER BY last_name, first_name"
    );
    
    $authors = [];
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row;
    }
    return $authors;
}

/**
 * Search publishers by name
 */
function searchPublishers($query) {
    global $conn;
    
    $query = $conn->real_escape_string($query);
    $result = $conn->query(
        "SELECT * FROM publishers 
         WHERE name LIKE '%$query%' 
         ORDER BY name"
    );
    
    $publishers = [];
    while ($row = $result->fetch_assoc()) {
        $publishers[] = $row;
    }
    return $publishers;
}

?>
