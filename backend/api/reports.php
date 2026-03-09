<?php
/**
 * Reports and Analytics API
 * Generates usage reports, inventory reports, and analytics
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

// Usage Report
if ($action === 'usage_report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $start_date = sanitizeInput($_GET['start_date'] ?? '');
    $end_date = sanitizeInput($_GET['end_date'] ?? '');
    
    if (empty($start_date)) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }
    if (empty($end_date)) {
        $end_date = date('Y-m-d');
    }
    
    // Total borrowed
    $result = $conn->query(
        "SELECT COUNT(*) as total FROM circulation 
         WHERE DATE(issue_date) BETWEEN '$start_date' AND '$end_date' AND status IN ('borrowed', 'renewed', 'returned')"
    );
    $borrowed = $result->fetch_assoc()['total'];
    
    // Total returned
    $result = $conn->query(
        "SELECT COUNT(*) as total FROM circulation 
         WHERE DATE(return_date) BETWEEN '$start_date' AND '$end_date' AND status = 'returned'"
    );
    $returned = $result->fetch_assoc()['total'];
    
    // Total fines collected
    $result = $conn->query(
        "SELECT SUM(fine_amount) as total FROM fines 
         WHERE DATE(paid_date) BETWEEN '$start_date' AND '$end_date' AND status = 'paid'"
    );
    $fines_collected = $result->fetch_assoc()['total'] ?? 0;
    
    // Active borrowers
    $result = $conn->query(
        "SELECT COUNT(DISTINCT user_id) as total FROM circulation 
         WHERE DATE(issue_date) BETWEEN '$start_date' AND '$end_date'"
    );
    $active_borrowers = $result->fetch_assoc()['total'];
    
    // Overdue count
    $today = date('Y-m-d');
    $result = $conn->query(
        "SELECT COUNT(*) as total FROM circulation 
         WHERE due_date < '$today' AND status IN ('borrowed', 'renewed')"
    );
    $overdue_count = $result->fetch_assoc()['total'];
    
    $report = [
        'period' => ['start' => $start_date, 'end' => $end_date],
        'total_borrowed' => $borrowed,
        'total_returned' => $returned,
        'fines_collected' => (float)$fines_collected,
        'active_borrowers' => $active_borrowers,
        'overdue_count' => $overdue_count
    ];
    
    sendResponse(true, 'Usage report generated', $report);
}

// Popular books report
else if ($action === 'popular_books' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = (int)($_GET['limit'] ?? 10);
    $start_date = sanitizeInput($_GET['start_date'] ?? '');
    $end_date = sanitizeInput($_GET['end_date'] ?? '');
    
    if (empty($start_date)) {
        $start_date = date('Y-m-d', strtotime('-90 days'));
    }
    if (empty($end_date)) {
        $end_date = date('Y-m-d');
    }
    
    $query = "SELECT b.book_id, b.title, b.author, COUNT(c.circulation_id) as times_borrowed
              FROM books b
              LEFT JOIN circulation c ON b.book_id = c.book_id 
              AND DATE(c.issue_date) BETWEEN '$start_date' AND '$end_date'
              GROUP BY b.book_id
              ORDER BY times_borrowed DESC
              LIMIT $limit";
    
    $result = $conn->query($query);
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    sendResponse(true, 'Popular books report generated', $books);
}

// Borrowing trends
else if ($action === 'borrowing_trends' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $days = (int)($_GET['days'] ?? 30);
    
    $query = "SELECT DATE(issue_date) as date, COUNT(*) as count
              FROM circulation
              WHERE issue_date >= DATE_SUB(NOW(), INTERVAL $days DAY)
              GROUP BY DATE(issue_date)
              ORDER BY date ASC";
    
    $result = $conn->query($query);
    
    $trends = [];
    while ($row = $result->fetch_assoc()) {
        $trends[] = $row;
    }
    
    sendResponse(true, 'Borrowing trends retrieved', $trends);
}

// Inventory report
else if ($action === 'inventory_report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Total books
    $result = $conn->query("SELECT COUNT(*) as total FROM books WHERE status = 'active'");
    $total_books = $result->fetch_assoc()['total'];
    
    // Available copies
    $result = $conn->query(
        "SELECT SUM(available_copies) as total FROM books WHERE status = 'active'"
    );
    $available_copies = $result->fetch_assoc()['total'] ?? 0;
    
    // Borrowed copies
    $result = $conn->query(
        "SELECT COUNT(*) as total FROM book_inventory WHERE status = 'borrowed'"
    );
    $borrowed_copies = $result->fetch_assoc()['total'];
    
    // Damaged books
    $result = $conn->query(
        "SELECT COUNT(DISTINCT book_id) as total FROM book_inventory WHERE status = 'damaged'"
    );
    $damaged_books = $result->fetch_assoc()['total'];
    
    // Lost books
    $result = $conn->query(
        "SELECT COUNT(DISTINCT book_id) as total FROM book_inventory WHERE status = 'lost'"
    );
    $lost_books = $result->fetch_assoc()['total'];
    
    // Books by genre
    $genre_query = "SELECT b.genre, COUNT(*) as count
                    FROM books b
                    WHERE b.status = 'active'
                    GROUP BY b.genre
                    ORDER BY count DESC";
    
    $result = $conn->query($genre_query);
    $by_genre = [];
    while ($row = $result->fetch_assoc()) {
        $by_genre[] = $row;
    }
    
    $report = [
        'total_books' => $total_books,
        'available_copies' => $available_copies,
        'borrowed_copies' => $borrowed_copies,
        'damaged_books' => $damaged_books,
        'lost_books' => $lost_books,
        'books_by_genre' => $by_genre
    ];
    
    sendResponse(true, 'Inventory report generated', $report);
}

// Overdue items report
else if ($action === 'overdue_items' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $today = date('Y-m-d');
    
    $query = "SELECT c.circulation_id, c.due_date, b.title, b.author, u.username, u.email,
              DATEDIFF('$today', c.due_date) as days_overdue
              FROM circulation c
              JOIN books b ON c.book_id = b.book_id
              JOIN users u ON c.user_id = u.user_id
              WHERE c.due_date < '$today' AND c.status IN ('borrowed', 'renewed')
              ORDER BY c.due_date ASC
              LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    sendResponse(true, 'Overdue items report generated', $items);
}

// User activity report
else if ($action === 'user_activity' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = (int)($_GET['limit'] ?? 50);
    
    $query = "SELECT u.user_id, u.username, u.email, u.registration_date,
              COUNT(c.circulation_id) as total_borrowed,
              SUM(CASE WHEN c.status = 'returned' THEN 1 ELSE 0 END) as returned,
              SUM(CASE WHEN c.status IN ('borrowed', 'renewed') THEN 1 ELSE 0 END) as currently_holding,
              SUM(f.fine_amount) as total_fines_pending
              FROM users u
              LEFT JOIN circulation c ON u.user_id = c.user_id
              LEFT JOIN fines f ON u.user_id = f.user_id AND f.status = 'pending'
              WHERE u.role IN ('student', 'teacher')
              GROUP BY u.user_id
              ORDER BY total_borrowed DESC
              LIMIT $limit";
    
    $result = $conn->query($query);
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    sendResponse(true, 'User activity report generated', $users);
}

// Member engagement report
else if ($action === 'member_engagement' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Active members (borrowed in last 30 days)
    $result = $conn->query(
        "SELECT COUNT(DISTINCT user_id) as count FROM circulation 
         WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $active_members = $result->fetch_assoc()['count'];
    
    // Inactive members (no activity in 90 days)
    $result = $conn->query(
        "SELECT COUNT(DISTINCT user_id) as count FROM users u
         WHERE u.is_active = TRUE AND NOT EXISTS (
            SELECT 1 FROM circulation c WHERE c.user_id = u.user_id 
            AND c.issue_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
         )"
    );
    $inactive_members = $result->fetch_assoc()['count'];
    
    // New members (registered in last 30 days)
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM users 
         WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $new_members = $result->fetch_assoc()['count'];
    
    $report = [
        'active_members_30_days' => $active_members,
        'inactive_members_90_days' => $inactive_members,
        'new_members_30_days' => $new_members
    ];
    
    sendResponse(true, 'Member engagement report generated', $report);
}

// Book condition report
else if ($action === 'book_condition_report' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT condition, COUNT(*) as count FROM book_inventory 
              WHERE condition IS NOT NULL
              GROUP BY condition
              ORDER BY count DESC";
    
    $result = $conn->query($query);
    
    $conditions = [];
    while ($row = $result->fetch_assoc()) {
        $conditions[] = $row;
    }
    
    sendResponse(true, 'Book condition report generated', $conditions);
}

// Generate export data (for CSV/Excel)
else if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $report_type = sanitizeInput($_GET['report_type'] ?? 'overdue');
    
    if ($report_type === 'overdue') {
        $today = date('Y-m-d');
        
        $query = "SELECT c.circulation_id, c.due_date, b.title, b.author, u.username, u.email, u.phone,
                  DATEDIFF('$today', c.due_date) as days_overdue
                  FROM circulation c
                  JOIN books b ON c.book_id = b.book_id
                  JOIN users u ON c.user_id = u.user_id
                  WHERE c.due_date < '$today' AND c.status IN ('borrowed', 'renewed')
                  ORDER BY c.due_date ASC";
    }
    else if ($report_type === 'pending_fines') {
        $query = "SELECT f.fine_id, f.user_id, u.username, u.email, b.title, f.fine_amount, f.created_at
                  FROM fines f
                  JOIN books b ON f.book_id = b.book_id
                  JOIN users u ON f.user_id = u.user_id
                  WHERE f.status = 'pending'
                  ORDER BY f.created_at DESC";
    }
    else if ($report_type === 'circulation') {
        $query = "SELECT c.circulation_id, b.title, b.author, u.username, c.issue_date, c.due_date, c.return_date, c.status
                  FROM circulation c
                  JOIN books b ON c.book_id = b.book_id
                  JOIN users u ON c.user_id = u.user_id
                  ORDER BY c.issue_date DESC LIMIT 1000";
    }
    else {
        sendResponse(false, 'Invalid report type', null, 400);
    }
    
    $result = $conn->query($query);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    sendResponse(true, 'Export data generated', $data);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
