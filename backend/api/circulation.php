<?php
/**
 * Circulation API
 * Handles borrowing, returning, and renewing books
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

// Borrow a book
if ($action === 'borrow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $book_id = (int)($_POST['book_id'] ?? 0);
    
    if ($user_id <= 0 || $book_id <= 0) {
        sendResponse(false, 'Invalid user or book ID', null, 400);
    }
    
    // Get user
    $user = getUserById($user_id);
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    // Check user account status
    if (!$user['is_active']) {
        sendResponse(false, 'Your account is inactive', null, 400);
    }
    
    // Get book
    $book = getBookById($book_id);
    if (!$book || $book['status'] !== 'active') {
        sendResponse(false, 'Book not available', null, 400);
    }
    
    // Get library settings
    $max_books = getLibrarySetting('max_books_at_once', 5);
    $borrow_days = getLibrarySetting('max_borrow_days', 14);
    
    // Check current borrowed count
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM circulation 
         WHERE user_id = $user_id AND status IN ('borrowed', 'renewed')"
    );
    $row = $result->fetch_assoc();
    
    if ($row['count'] >= $max_books) {
        sendResponse(false, "You can only borrow $max_books books at a time", null, 400);
    }
    
    // Check for unpaid fines
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM fines 
         WHERE user_id = $user_id AND status = 'pending'"
    );
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(false, 'You have unpaid fines. Please clear them before borrowing', null, 400);
    }
    
    // Get available copy
    $result = $conn->query(
        "SELECT inventory_id FROM book_inventory 
         WHERE book_id = $book_id AND status = 'available' LIMIT 1"
    );
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'No copies available', null, 400);
    }
    
    $inventory = $result->fetch_assoc();
    $inventory_id = $inventory['inventory_id'];
    
    // Create circulation record
    $issue_date = date('Y-m-d H:i:s');
    $due_date = date('Y-m-d', strtotime("+$borrow_days days"));
    
    $stmt = $conn->prepare(
        "INSERT INTO circulation (user_id, book_id, inventory_id, issue_date, due_date, status) 
         VALUES (?, ?, ?, ?, ?, 'borrowed')"
    );
    
    $stmt->bind_param("iiiis", $user_id, $book_id, $inventory_id, $issue_date, $due_date);
    
    if ($stmt->execute()) {
        // Update inventory status
        $conn->query("UPDATE book_inventory SET status = 'borrowed' WHERE inventory_id = $inventory_id");
        
        // Update book availability
        updateBookAvailability($book_id);
        
        // Log action
        logAuditAction('BOOK_BORROWED', $user_id, 'circulation', $stmt->insert_id);
        
        // Send notification
        sendNotification($user_id, 'system', 'Book Borrowed', 
            "You have borrowed '{$book['title']}'. Due date: $due_date");
        
        sendResponse(true, 'Book borrowed successfully', [
            'circulation_id' => $stmt->insert_id,
            'due_date' => $due_date,
            'max_books' => $max_books
        ]);
    } else {
        sendResponse(false, 'Failed to borrow book: ' . $conn->error, null, 500);
    }
}

// Return a book
else if ($action === 'return' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $circulation_id = (int)($_POST['circulation_id'] ?? 0);
    $condition = sanitizeInput($_POST['condition'] ?? 'good'); // good, fair, damaged
    
    if ($circulation_id <= 0) {
        sendResponse(false, 'Invalid circulation ID', null, 400);
    }
    
    // Get circulation record
    $result = $conn->query(
        "SELECT c.*, b.title FROM circulation c
         JOIN books b ON c.book_id = b.book_id
         WHERE c.circulation_id = $circulation_id"
    );
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'Circulation record not found', null, 404);
    }
    
    $circ = $result->fetch_assoc();
    
    // Check if already returned
    if ($circ['status'] === 'returned') {
        sendResponse(false, 'This book was already returned', null, 400);
    }
    
    $return_date = date('Y-m-d H:i:s');
    $due_date = $circ['due_date'];
    $book_id = $circ['book_id'];
    $inventory_id = $circ['inventory_id'];
    $user_id = $circ['user_id'];
    
    // Calculate fine if overdue
    $fine_amount = 0;
    $today = new DateTime();
    $due = new DateTime($due_date);
    
    if ($today > $due) {
        $diff = $today->diff($due);
        $days_overdue = $diff->days;
        $fine_per_day = (int)getLibrarySetting('fine_per_day', 10);
        $max_fine = (int)getLibrarySetting('max_fine_amount', 100);
        
        $fine_amount = min($days_overdue * $fine_per_day, $max_fine);
    }
    
    // Update circulation record
    $status = 'returned';
    $stmt = $conn->prepare(
        "UPDATE circulation SET return_date = ?, status = ? WHERE circulation_id = ?"
    );
    $stmt->bind_param("ssi", $return_date, $status, $circulation_id);
    $stmt->execute();
    
    // Update inventory
    $inventory_status = ($condition === 'damaged') ? 'damaged' : 'available';
    $conn->query(
        "UPDATE book_inventory SET status = '$inventory_status', condition = '$condition' 
         WHERE inventory_id = $inventory_id"
    );
    
    // Create fine if overdue
    if ($fine_amount > 0) {
        $days_overdue = $diff->days;
        $fine_reason = 'overdue';
        
        $stmt = $conn->prepare(
            "INSERT INTO fines (circulation_id, user_id, book_id, fine_amount, fine_reason, days_overdue, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );
        
        $stmt->bind_param("iiidsi", $circulation_id, $user_id, $book_id, $fine_amount, $fine_reason, $days_overdue);
        $stmt->execute();
        
        // Notify user about fine
        sendNotification($user_id, 'fine_pending', 'Overdue Fine', 
            "You have an outstanding fine of Rs. $fine_amount for '{$circ['title']}'");
    }
    
    // Update book availability
    updateBookAvailability($book_id);
    
    // Log action
    logAuditAction('BOOK_RETURNED', $user_id, 'circulation', $circulation_id);
    
    // Send notification
    $msg = "You have returned '{$circ['title']}'";
    if ($fine_amount > 0) {
        $msg .= " and incurred a fine of Rs. $fine_amount";
    }
    sendNotification($user_id, 'system', 'Book Returned', $msg);
    
    sendResponse(true, 'Book returned successfully', [
        'fine_amount' => $fine_amount
    ]);
}

// Renew a book
else if ($action === 'renew' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $circulation_id = (int)($_POST['circulation_id'] ?? 0);
    
    if ($circulation_id <= 0) {
        sendResponse(false, 'Invalid circulation ID', null, 400);
    }
    
    // Get circulation record
    $result = $conn->query(
        "SELECT c.*, b.title FROM circulation c
         JOIN books b ON c.book_id = b.book_id
         WHERE c.circulation_id = $circulation_id AND c.status IN ('borrowed', 'renewed')"
    );
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'Circulation record not found or already returned', null, 404);
    }
    
    $circ = $result->fetch_assoc();
    
    // Check renewal limit
    $max_renewals = (int)getLibrarySetting('max_renewal_count', 2);
    
    if ($circ['renewal_count'] >= $max_renewals) {
        sendResponse(false, "Maximum renewal limit ($max_renewals) reached", null, 400);
    }
    
    // Check for pending fines
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM fines 
         WHERE circulation_id = $circulation_id AND status = 'pending'"
    );
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(false, 'Cannot renew due to pending fines', null, 400);
    }
    
    // Calculate new due date
    $borrow_days = (int)getLibrarySetting('max_borrow_days', 14);
    $new_due_date = date('Y-m-d', strtotime("+$borrow_days days"));
    $renewal_date = date('Y-m-d H:i:s');
    $new_renewal_count = $circ['renewal_count'] + 1;
    $new_status = 'renewed';
    
    // Update circulation
    $stmt = $conn->prepare(
        "UPDATE circulation SET due_date = ?, renewal_date = ?, renewal_count = ?, status = ? 
         WHERE circulation_id = ?"
    );
    
    $stmt->bind_param("ssiisi", $new_due_date, $renewal_date, $new_renewal_count, $new_status, $circulation_id);
    
    if ($stmt->execute()) {
        logAuditAction('BOOK_RENEWED', $circ['user_id'], 'circulation', $circulation_id);
        
        sendNotification($circ['user_id'], 'system', 'Book Renewed', 
            "'{$circ['title']}' has been renewed. New due date: $new_due_date");
        
        sendResponse(true, 'Book renewed successfully', [
            'new_due_date' => $new_due_date,
            'renewal_count' => $new_renewal_count,
            'max_renewals' => $max_renewals
        ]);
    } else {
        sendResponse(false, 'Renewal failed: ' . $conn->error, null, 500);
    }
}

// Get user's borrowed books
else if ($action === 'my_books' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $status = sanitizeInput($_GET['status'] ?? 'borrowed'); // borrowed, returned, all
    
    if ($user_id <= 0) {
        sendResponse(false, 'Invalid user ID', null, 400);
    }
    
    $query = "SELECT c.*, b.title, b.author FROM circulation c
              JOIN books b ON c.book_id = b.book_id
              WHERE c.user_id = $user_id";
    
    if ($status !== 'all') {
        $query .= " AND c.status IN ('borrowed', 'renewed')";
    }
    
    $query .= " ORDER BY c.issue_date DESC";
    
    $result = $conn->query($query);
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    sendResponse(true, 'Books retrieved', $books);
}

// Get all borrowed books (admin/librarian)
else if ($action === 'all_borrowed' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $overdue_only = isset($_GET['overdue_only']) ? (bool)$_GET['overdue_only'] : false;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT c.*, b.title, b.author, u.username, u.email FROM circulation c
              JOIN books b ON c.book_id = b.book_id
              JOIN users u ON c.user_id = u.user_id
              WHERE c.status IN ('borrowed', 'renewed')";
    
    if ($overdue_only) {
        $today = date('Y-m-d');
        $query .= " AND c.due_date < '$today'";
    }
    
    $query .= " ORDER BY c.due_date ASC LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    sendResponse(true, 'Borrowed books retrieved', $records);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
