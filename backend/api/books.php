<?php
/**
 * Books API
 * Handles book management, searching, and categorization
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$conn = require_once '../config/database.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Add new book (librarian only)
if ($action === 'add_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $author_id = (int)($_POST['author_id'] ?? 0);
    $publisher_id = (int)($_POST['publisher_id'] ?? 0);
    $genre = sanitizeInput($_POST['genre'] ?? '');
    $isbn = sanitizeInput($_POST['isbn'] ?? '');
    $publication_year = (int)($_POST['publication_year'] ?? date('Y'));
    $description = sanitizeInput($_POST['description'] ?? '');
    $total_copies = (int)($_POST['total_copies'] ?? 1);
    
    if (empty($title) || $author_id <= 0 || $publisher_id <= 0) {
        sendResponse(false, 'Title, author ID, and publisher ID are required', null, 400);
    }
    
    // Check if ISBN already exists
    if (!empty($isbn)) {
        $result = $conn->query("SELECT book_id FROM books WHERE isbn = '$isbn'");
        if ($result && $result->num_rows > 0) {
            sendResponse(false, 'Book with this ISBN already exists', null, 400);
        }
    }
    
    // Verify author and publisher exist
    if (!getAuthorById($author_id)) {
        sendResponse(false, 'Author not found', null, 400);
    }
    if (!getPublisherById($publisher_id)) {
        sendResponse(false, 'Publisher not found', null, 400);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO books (title, author_id, publisher_id, genre, isbn, publication_year, description, total_copies, available_copies, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    );
    
    $stmt->bind_param("siisssii", $title, $author_id, $publisher_id, $genre, $isbn, $publication_year, $description, $total_copies, $total_copies);
    
    if ($stmt->execute()) {
        $book_id = $stmt->insert_id;
        
        // Create inventory records for each copy
        for ($i = 1; $i <= $total_copies; $i++) {
            $stmt2 = $conn->prepare(
                "INSERT INTO book_inventory (book_id, copy_number, status) VALUES (?, ?, 'available')"
            );
            $stmt2->bind_param("ii", $book_id, $i);
            $stmt2->execute();
        }
        
        logAuditAction('BOOK_CREATED', $_POST['user_id'] ?? null, 'books', $book_id);
        sendResponse(true, 'Book added successfully', ['book_id' => $book_id]);
    } else {
        sendResponse(false, 'Failed to add book: ' . $conn->error, null, 500);
    }
}

// Get all books
else if ($action === 'list_books' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $genre = sanitizeInput($_GET['genre'] ?? '');
    $status = sanitizeInput($_GET['status'] ?? 'active');
    $search = sanitizeInput($_GET['search'] ?? '');
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT b.*, 
              CONCAT(a.first_name, ' ', a.last_name) as author_name,
              p.name as publisher_name,
              COUNT(bi.inventory_id) as total, 
              SUM(CASE WHEN bi.status = 'available' THEN 1 ELSE 0 END) as available
              FROM books b
              LEFT JOIN authors a ON b.author_id = a.author_id
              LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
              LEFT JOIN book_inventory bi ON b.book_id = bi.book_id
              WHERE 1=1";
    
    if (!empty($genre)) {
        $query .= " AND b.genre = '$genre'";
    }
    
    if (!empty($status)) {
        $query .= " AND b.status = '$status'";
    }
    
    if (!empty($search)) {
        $query .= " AND (b.title LIKE '%$search%' 
                   OR CONCAT(a.first_name, ' ', a.last_name) LIKE '%$search%'
                   OR p.name LIKE '%$search%'
                   OR b.isbn LIKE '%$search%')";
    }
    
    $query .= " GROUP BY b.book_id ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    sendResponse(true, 'Books retrieved', $books);
}

// Get book details
else if ($action === 'get_book' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $book_id = (int)($_GET['book_id'] ?? 0);
    
    if ($book_id <= 0) {
        sendResponse(false, 'Invalid book ID', null, 400);
    }
    
    $book = getBookById($book_id);
    
    if (!$book) {
        sendResponse(false, 'Book not found', null, 404);
    }
    
    // Get categories
    $categories = [];
    $result = $conn->query(
        "SELECT c.* FROM categories c
         JOIN book_categories bc ON c.category_id = bc.category_id
         WHERE bc.book_id = $book_id"
    );
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    $book['categories'] = $categories;
    sendResponse(true, 'Book details retrieved', $book);
}

// Update book
else if ($action === 'update_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $author_id = (int)($_POST['author_id'] ?? 0);
    $publisher_id = (int)($_POST['publisher_id'] ?? 0);
    $genre = sanitizeInput($_POST['genre'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if ($book_id <= 0) {
        sendResponse(false, 'Invalid book ID', null, 400);
    }
    
    $book = getBookById($book_id);
    if (!$book) {
        sendResponse(false, 'Book not found', null, 404);
    }
    
    // Verify author and publisher exist
    if ($author_id > 0 && !getAuthorById($author_id)) {
        sendResponse(false, 'Author not found', null, 400);
    }
    if ($publisher_id > 0 && !getPublisherById($publisher_id)) {
        sendResponse(false, 'Publisher not found', null, 400);
    }
    
    $stmt = $conn->prepare(
        "UPDATE books SET title = ?, author_id = ?, publisher_id = ?, genre = ?, status = ?, description = ? WHERE book_id = ?"
    );
    
    $stmt->bind_param("siisssi", $title, $author_id, $publisher_id, $genre, $status, $description, $book_id);
    
    if ($stmt->execute()) {
        logAuditAction('BOOK_UPDATED', $_POST['user_id'] ?? null, 'books', $book_id);
        sendResponse(true, 'Book updated successfully');
    } else {
        sendResponse(false, 'Update failed: ' . $conn->error, null, 500);
    }
}

// Delete book (soft delete)
else if ($action === 'delete_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    
    if ($book_id <= 0) {
        sendResponse(false, 'Invalid book ID', null, 400);
    }
    
    $book = getBookById($book_id);
    if (!$book) {
        sendResponse(false, 'Book not found', null, 404);
    }
    
    $conn->query("UPDATE books SET status = 'inactive' WHERE book_id = $book_id");
    
    logAuditAction('BOOK_DELETED', $_POST['user_id'] ?? null, 'books', $book_id);
    sendResponse(true, 'Book deleted successfully');
}

// Search books
else if ($action === 'search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $query_text = sanitizeInput($_GET['q'] ?? '');
    $search_by = sanitizeInput($_GET['search_by'] ?? 'all'); // title, author, publisher, isbn, all
    
    if (empty($query_text)) {
        sendResponse(false, 'Search query required', null, 400);
    }
    
    $query = "SELECT b.*, 
              CONCAT(a.first_name, ' ', a.last_name) as author_name,
              p.name as publisher_name,
              COUNT(bi.inventory_id) as total
              FROM books b
              LEFT JOIN authors a ON b.author_id = a.author_id
              LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
              LEFT JOIN book_inventory bi ON b.book_id = bi.book_id
              WHERE b.status = 'active' AND (";
    
    if ($search_by === 'title' || $search_by === 'all') {
        $query .= "b.title LIKE '%$query_text%'";
    }
    
    if ($search_by === 'author' || $search_by === 'all') {
        $query .= ($search_by === 'all' ? " OR " : "") . "CONCAT(a.first_name, ' ', a.last_name) LIKE '%$query_text%'";
    }
    
    if ($search_by === 'publisher' || $search_by === 'all') {
        $query .= ($search_by === 'all' ? " OR " : "") . "p.name LIKE '%$query_text%'";
    }
    
    if ($search_by === 'isbn' || $search_by === 'all') {
        $query .= ($search_by === 'all' ? " OR " : "") . "b.isbn LIKE '%$query_text%'";
    }
    
    $query .= ") GROUP BY b.book_id ORDER BY b.title ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        sendResponse(false, 'Search failed', null, 500);
    }
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    sendResponse(true, 'Search results', $books);
}

// Get categories
else if ($action === 'get_categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    sendResponse(true, 'Categories retrieved', $categories);
}

// Add category to book
else if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    if ($book_id <= 0 || $category_id <= 0) {
        sendResponse(false, 'Invalid book or category ID', null, 400);
    }
    
    // Check if already added
    $result = $conn->query(
        "SELECT * FROM book_categories WHERE book_id = $book_id AND category_id = $category_id"
    );
    
    if ($result && $result->num_rows > 0) {
        sendResponse(false, 'Category already added to this book', null, 400);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)"
    );
    
    $stmt->bind_param("ii", $book_id, $category_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Category added successfully');
    } else {
        sendResponse(false, 'Failed to add category', null, 500);
    }
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
