<?php
/**
 * Fines API
 * Handles fine management, payments, and overdue notifications
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

// Get user fines
if ($action === 'get_fines' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $status = sanitizeInput($_GET['status'] ?? 'all'); // pending, paid, all
    
    if ($user_id <= 0) {
        sendResponse(false, 'Invalid user ID', null, 400);
    }
    
    $query = "SELECT f.*, b.title, b.author, c.due_date, c.return_date FROM fines f
              JOIN books b ON f.book_id = b.book_id
              JOIN circulation c ON f.circulation_id = c.circulation_id
              WHERE f.user_id = $user_id";
    
    if ($status !== 'all') {
        $query .= " AND f.status = '$status'";
    }
    
    $query .= " ORDER BY f.created_at DESC";
    
    $result = $conn->query($query);
    
    $fines = [];
    while ($row = $result->fetch_assoc()) {
        $fines[] = $row;
    }
    
    sendResponse(true, 'Fines retrieved', $fines);
}

// Get total pending fines for user
else if ($action === 'total_fines' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = (int)($_GET['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        sendResponse(false, 'Invalid user ID', null, 400);
    }
    
    $result = $conn->query(
        "SELECT SUM(fine_amount) as total FROM fines 
         WHERE user_id = $user_id AND status = 'pending'"
    );
    
    $row = $result->fetch_assoc();
    $total = $row['total'] ?? 0;
    
    sendResponse(true, 'Total fines retrieved', ['total_fines' => $total]);
}

// Mark fine as paid
else if ($action === 'pay_fine' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fine_id = (int)($_POST['fine_id'] ?? 0);
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    
    if ($fine_id <= 0) {
        sendResponse(false, 'Invalid fine ID', null, 400);
    }
    
    $result = $conn->query("SELECT * FROM fines WHERE fine_id = $fine_id");
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'Fine not found', null, 404);
    }
    
    $fine = $result->fetch_assoc();
    
    if ($fine['status'] === 'paid') {
        sendResponse(false, 'This fine was already paid', null, 400);
    }
    
    $paid_date = date('Y-m-d H:i:s');
    $status = 'paid';
    
    $stmt = $conn->prepare(
        "UPDATE fines SET status = ?, paid_date = ?, payment_method = ? WHERE fine_id = ?"
    );
    
    $stmt->bind_param("sssi", $status, $paid_date, $payment_method, $fine_id);
    
    if ($stmt->execute()) {
        logAuditAction('FINE_PAID', $fine['user_id'], 'fines', $fine_id);
        
        sendNotification($fine['user_id'], 'system', 'Fine Paid', 
            "Your fine of Rs. {$fine['fine_amount']} has been recorded as paid");
        
        sendResponse(true, 'Fine marked as paid', ['fine_id' => $fine_id]);
    } else {
        sendResponse(false, 'Failed to update fine: ' . $conn->error, null, 500);
    }
}

// Pay multiple fines
else if ($action === 'pay_multiple_fines' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $payment_method = sanitizeInput($_POST['payment_method'] ?? 'cash');
    
    if ($user_id <= 0) {
        sendResponse(false, 'Invalid user ID', null, 400);
    }
    
    $paid_date = date('Y-m-d H:i:s');
    $status = 'paid';
    
    // Update all pending fines for user
    $result = $conn->query(
        "UPDATE fines SET status = '$status', paid_date = '$paid_date', payment_method = '$payment_method' 
         WHERE user_id = $user_id AND status = 'pending'"
    );
    
    if ($result) {
        logAuditAction('MULTIPLE_FINES_PAID', $user_id, 'fines', 0);
        sendNotification($user_id, 'system', 'Fines Paid', 'All your pending fines have been marked as paid');
        sendResponse(true, 'All fines marked as paid');
    } else {
        sendResponse(false, 'Failed to update fines: ' . $conn->error, null, 500);
    }
}

// Waive fine (admin/librarian)
else if ($action === 'waive_fine' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fine_id = (int)($_POST['fine_id'] ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? 'Manual waiver');
    
    if ($fine_id <= 0) {
        sendResponse(false, 'Invalid fine ID', null, 400);
    }
    
    $result = $conn->query("SELECT * FROM fines WHERE fine_id = $fine_id");
    
    if (!$result || $result->num_rows === 0) {
        sendResponse(false, 'Fine not found', null, 404);
    }
    
    $fine = $result->fetch_assoc();
    $status = 'waived';
    
    $stmt = $conn->prepare(
        "UPDATE fines SET status = ? WHERE fine_id = ?"
    );
    
    $stmt->bind_param("si", $status, $fine_id);
    
    if ($stmt->execute()) {
        logAuditAction('FINE_WAIVED', $_POST['admin_user_id'] ?? null, 'fines', $fine_id);
        sendNotification($fine['user_id'], 'system', 'Fine Waived', 'A fine of Rs. ' . $fine['fine_amount'] . ' has been waived');
        sendResponse(true, 'Fine waived successfully');
    } else {
        sendResponse(false, 'Failed to waive fine: ' . $conn->error, null, 500);
    }
}

// Get all pending fines (admin/librarian)
else if ($action === 'all_pending_fines' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT f.*, b.title, u.username, u.email FROM fines f
              JOIN books b ON f.book_id = b.book_id
              JOIN users u ON f.user_id = u.user_id
              WHERE f.status = 'pending'
              ORDER BY f.created_at DESC
              LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    $fines = [];
    while ($row = $result->fetch_assoc()) {
        $fines[] = $row;
    }
    
    sendResponse(true, 'Pending fines retrieved', $fines);
}

// Send overdue notifications
else if ($action === 'send_overdue_notifications' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date('Y-m-d');
    
    // Get all overdue books
    $result = $conn->query(
        "SELECT DISTINCT c.user_id, c.circulation_id, b.title, b.author, c.due_date FROM circulation c
         JOIN books b ON c.book_id = b.book_id
         WHERE c.due_date < '$today' AND c.status IN ('borrowed', 'renewed')
         AND NOT EXISTS (
            SELECT 1 FROM notifications 
            WHERE user_id = c.user_id AND circulation_id = c.circulation_id AND notification_type = 'overdue'
         )"
    );
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $title = "Overdue Book Reminder";
        $message = "Your book '{$row['title']}' by {$row['author']} was due on {$row['due_date']}. Please return it immediately.";
        
        sendNotification($row['user_id'], 'overdue', $title, $message);
        $count++;
    }
    
    sendResponse(true, "Sent $count overdue notifications");
}

// Send due date reminders
else if ($action === 'send_due_reminders' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $days_before = (int)getLibrarySetting('notification_days_before', 2);
    $reminder_date = date('Y-m-d', strtotime("+$days_before days"));
    
    // Get books due on specific date
    $result = $conn->query(
        "SELECT DISTINCT c.user_id, c.circulation_id, b.title, b.author, c.due_date FROM circulation c
         JOIN books b ON c.book_id = b.book_id
         WHERE DATE(c.due_date) = '$reminder_date' AND c.status IN ('borrowed', 'renewed')
         AND NOT EXISTS (
            SELECT 1 FROM notifications 
            WHERE user_id = c.user_id AND circulation_id = c.circulation_id AND notification_type = 'due_soon'
         )"
    );
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $title = "Book Due Reminder";
        $message = "Your book '{$row['title']}' is due on {$row['due_date']}. Please remember to return it on time.";
        
        sendNotification($row['user_id'], 'due_soon', $title, $message);
        $count++;
    }
    
    sendResponse(true, "Sent $count due date reminders");
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
