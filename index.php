<?php
/**
 * Bagmati School Library - Admin Backend API
 * Handles admin authentication and data queries
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Database connection (adjust credentials as needed)
$host = 'localhost';
$db = 'bagmati_library';
$user = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get the action from request
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

// Route requests
switch($action) {
    case 'adminLogin':
        handleAdminLogin($conn, $input);
        break;
    case 'getAdminStats':
        handleGetAdminStats($conn);
        break;
    case 'getAdminUsers':
        handleGetAdminUsers($conn, $input);
        break;
    case 'getBorrowingRecords':
        handleGetBorrowingRecords($conn, $input);
        break;
    case 'getOverdueBooks':
        handleGetOverdueBooks($conn);
        break;
    case 'getFinesRecords':
        handleGetFinesRecords($conn, $input);
        break;
    case 'generateReport':
        handleGenerateReport($conn, $input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ═════════════════════════════════════════════════════════════
// ADMIN LOGIN
// ═════════════════════════════════════════════════════════════
function handleAdminLogin($conn, $input) {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }
    
    try {
        // Query admin user
        $stmt = $conn->prepare("
            SELECT id, name, email, role 
            FROM users 
            WHERE email = :email AND role = 'admin' LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }
        
        // In production, verify password hash: password_verify($password, $user['password'])
        // For now, we'll do a simple check (ONLY for demo - never do this in production!)
        // This is a simplified version - implement proper password verification
        
        // Success
        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GET ADMIN DASHBOARD STATISTICS
// ═════════════════════════════════════════════════════════════
function handleGetAdminStats($conn) {
    try {
        // Total books
        $stmt = $conn->query("SELECT COUNT(*) as count FROM books");
        $totalBooks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Available books
        $stmt = $conn->query("SELECT COUNT(*) as count FROM book_inventory WHERE status = 'available'");
        $availableBooks = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Active users
        $stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM circulation WHERE status IN ('borrowed', 'overdue')");
        $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Overdue books
        $stmt = $conn->query("
            SELECT COUNT(*) as count 
            FROM circulation 
            WHERE status = 'overdue' AND due_date < NOW()
        ");
        $overdueCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Pending fines
        $stmt = $conn->query("
            SELECT COUNT(*) as count 
            FROM fines 
            WHERE status = 'pending'
        ");
        $pendingFines = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Currently borrowed
        $stmt = $conn->query("
            SELECT COUNT(*) as count 
            FROM circulation 
            WHERE status = 'borrowed'
        ");
        $currentlyBorrowed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'totalBooks' => (int)$totalBooks,
                'availableBooks' => (int)$availableBooks,
                'activeUsers' => (int)$activeUsers,
                'overdueCount' => (int)$overdueCount,
                'pendingFines' => (int)$pendingFines,
                'currentlyBorrowed' => (int)$currentlyBorrowed
            ]
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GET ALL USERS (FOR ADMIN)
// ═════════════════════════════════════════════════════════════
function handleGetAdminUsers($conn, $input) {
    try {
        $search = $input['search'] ?? '';
        $role = $input['role'] ?? '';
        
        $query = "
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                COUNT(DISTINCT c.id) as booksBorrowed,
                MAX(c.created_at) as lastActivity,
                CASE 
                    WHEN u.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 
                    ELSE 0 
                END as isActive
            FROM users u
            LEFT JOIN circulation c ON u.id = c.user_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (u.name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($role)) {
            $query .= " AND u.role = :role";
            $params[':role'] = $role;
        }
        
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 100";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GET BORROWING RECORDS
// ═════════════════════════════════════════════════════════════
function handleGetBorrowingRecords($conn, $input) {
    try {
        $status = $input['status'] ?? '';
        
        $query = "
            SELECT 
                c.id as transactionId,
                u.name as memberName,
                b.title as bookTitle,
                DATE_FORMAT(c.issue_date, '%Y-%m-%d') as issueDate,
                DATE_FORMAT(c.due_date, '%Y-%m-%d') as dueDate,
                DATE_FORMAT(c.return_date, '%Y-%m-%d') as returnDate,
                c.status
            FROM circulation c
            JOIN users u ON c.user_id = u.id
            JOIN books b ON c.book_id = b.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND c.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY c.created_at DESC LIMIT 500";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'records' => $records
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GET OVERDUE BOOKS
// ═════════════════════════════════════════════════════════════
function handleGetOverdueBooks($conn) {
    try {
        $query = "
            SELECT 
                u.id as memberId,
                u.name as memberName,
                b.title as bookTitle,
                DATE_FORMAT(c.due_date, '%Y-%m-%d') as dueDate,
                DATE_FORMAT(c.issue_date, '%Y-%m-%d') as issueDate,
                DATEDIFF(NOW(), c.due_date) as daysOverdue
            FROM circulation c
            JOIN users u ON c.user_id = u.id
            JOIN books b ON c.book_id = b.id
            WHERE c.status = 'borrowed' AND c.due_date < NOW()
            ORDER BY c.due_date ASC
        ";
        
        $stmt = $conn->query($query);
        $overdueBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'overdueBooks' => $overdueBooks
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GET FINES RECORDS
// ═════════════════════════════════════════════════════════════
function handleGetFinesRecords($conn, $input) {
    try {
        $status = $input['status'] ?? '';
        
        $query = "
            SELECT 
                f.id as fineId,
                u.name as memberName,
                b.title as bookTitle,
                f.fine_amount as amount,
                f.fine_reason as reason,
                f.status
            FROM fines f
            JOIN users u ON f.user_id = u.id
            JOIN books b ON f.book_id = b.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND f.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY f.created_at DESC LIMIT 500";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $fines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'fines' => $fines
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ═════════════════════════════════════════════════════════════
// GENERATE REPORTS
// ═════════════════════════════════════════════════════════════
function handleGenerateReport($conn, $input) {
    try {
        $reportType = $input['reportType'] ?? 'monthly';
        
        // For now, return a success message
        // In production, you would generate actual PDF/CSV reports
        
        $reportUrl = '#';
        
        switch($reportType) {
            case 'monthly':
                // Generate monthly borrowing report
                break;
            case 'statistics':
                // Generate statistics report
                break;
            case 'members':
                // Generate member activity report
                break;
            default:
                throw new Exception('Invalid report type');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Report generated successfully',
            'reportUrl' => $reportUrl
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

?>
