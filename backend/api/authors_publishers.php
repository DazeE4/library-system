<?php
/**
 * Authors & Publishers Management API
 * Handles author and publisher data management
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

// ==================== AUTHORS ENDPOINTS ====================

// Add new author
if ($action === 'add_author' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $birth_date = sanitizeInput($_POST['birth_date'] ?? '');
    $nationality = sanitizeInput($_POST['nationality'] ?? '');
    
    if (empty($first_name) || empty($last_name)) {
        sendResponse(false, 'First name and last name are required', null, 400);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO authors (first_name, last_name, bio, birth_date, nationality) 
         VALUES (?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param("sssss", $first_name, $last_name, $bio, $birth_date, $nationality);
    
    if ($stmt->execute()) {
        $author_id = $stmt->insert_id;
        logAuditAction('AUTHOR_CREATED', $_POST['user_id'] ?? null, 'authors', $author_id);
        sendResponse(true, 'Author added successfully', ['author_id' => $author_id]);
    } else {
        sendResponse(false, 'Failed to add author: ' . $conn->error, null, 500);
    }
}

// Get all authors
else if ($action === 'list_authors' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = sanitizeInput($_GET['search'] ?? '');
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT * FROM authors WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (CONCAT(first_name, ' ', last_name) LIKE '%$search%' 
                    OR bio LIKE '%$search%')";
    }
    
    $query .= " ORDER BY last_name, first_name LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $authors = [];
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row;
    }
    
    sendResponse(true, 'Authors retrieved', $authors);
}

// Get author by ID
else if ($action === 'get_author' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $author_id = (int)($_GET['author_id'] ?? 0);
    
    if ($author_id <= 0) {
        sendResponse(false, 'Invalid author ID', null, 400);
    }
    
    $author = getAuthorById($author_id);
    
    if (!$author) {
        sendResponse(false, 'Author not found', null, 404);
    }
    
    // Get books by this author
    $result = $conn->query(
        "SELECT book_id, title, isbn, publication_year FROM books 
         WHERE author_id = $author_id ORDER BY publication_year DESC"
    );
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    $author['books'] = $books;
    sendResponse(true, 'Author details retrieved', $author);
}

// Update author
else if ($action === 'update_author' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $author_id = (int)($_POST['author_id'] ?? 0);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $birth_date = sanitizeInput($_POST['birth_date'] ?? '');
    $nationality = sanitizeInput($_POST['nationality'] ?? '');
    
    if ($author_id <= 0) {
        sendResponse(false, 'Invalid author ID', null, 400);
    }
    
    $author = getAuthorById($author_id);
    if (!$author) {
        sendResponse(false, 'Author not found', null, 404);
    }
    
    $stmt = $conn->prepare(
        "UPDATE authors SET first_name = ?, last_name = ?, bio = ?, birth_date = ?, nationality = ? 
         WHERE author_id = ?"
    );
    
    $stmt->bind_param("sssssi", $first_name, $last_name, $bio, $birth_date, $nationality, $author_id);
    
    if ($stmt->execute()) {
        logAuditAction('AUTHOR_UPDATED', $_POST['user_id'] ?? null, 'authors', $author_id);
        sendResponse(true, 'Author updated successfully');
    } else {
        sendResponse(false, 'Update failed: ' . $conn->error, null, 500);
    }
}

// Delete author
else if ($action === 'delete_author' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $author_id = (int)($_POST['author_id'] ?? 0);
    
    if ($author_id <= 0) {
        sendResponse(false, 'Invalid author ID', null, 400);
    }
    
    $author = getAuthorById($author_id);
    if (!$author) {
        sendResponse(false, 'Author not found', null, 404);
    }
    
    // Check if author has books
    $result = $conn->query("SELECT COUNT(*) as count FROM books WHERE author_id = $author_id");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(false, 'Cannot delete author with associated books', null, 400);
    }
    
    $conn->query("DELETE FROM authors WHERE author_id = $author_id");
    
    logAuditAction('AUTHOR_DELETED', $_POST['user_id'] ?? null, 'authors', $author_id);
    sendResponse(true, 'Author deleted successfully');
}

// ==================== PUBLISHERS ENDPOINTS ====================

// Add new publisher
else if ($action === 'add_publisher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    
    if (empty($name)) {
        sendResponse(false, 'Publisher name is required', null, 400);
    }
    
    // Check if publisher already exists
    $result = $conn->query("SELECT publisher_id FROM publishers WHERE name = '$name'");
    if ($result && $result->num_rows > 0) {
        sendResponse(false, 'Publisher with this name already exists', null, 400);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO publishers (name, address, city, country, phone, email, website) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param("sssssss", $name, $address, $city, $country, $phone, $email, $website);
    
    if ($stmt->execute()) {
        $publisher_id = $stmt->insert_id;
        logAuditAction('PUBLISHER_CREATED', $_POST['user_id'] ?? null, 'publishers', $publisher_id);
        sendResponse(true, 'Publisher added successfully', ['publisher_id' => $publisher_id]);
    } else {
        sendResponse(false, 'Failed to add publisher: ' . $conn->error, null, 500);
    }
}

// Get all publishers
else if ($action === 'list_publishers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = sanitizeInput($_GET['search'] ?? '');
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT * FROM publishers WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (name LIKE '%$search%' 
                    OR city LIKE '%$search%' 
                    OR country LIKE '%$search%')";
    }
    
    $query .= " ORDER BY name LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($query);
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $publishers = [];
    while ($row = $result->fetch_assoc()) {
        $publishers[] = $row;
    }
    
    sendResponse(true, 'Publishers retrieved', $publishers);
}

// Get publisher by ID
else if ($action === 'get_publisher' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $publisher_id = (int)($_GET['publisher_id'] ?? 0);
    
    if ($publisher_id <= 0) {
        sendResponse(false, 'Invalid publisher ID', null, 400);
    }
    
    $publisher = getPublisherById($publisher_id);
    
    if (!$publisher) {
        sendResponse(false, 'Publisher not found', null, 404);
    }
    
    // Get books by this publisher
    $result = $conn->query(
        "SELECT b.book_id, b.title, b.isbn, b.publication_year,
                CONCAT(a.first_name, ' ', a.last_name) as author_name
         FROM books b
         LEFT JOIN authors a ON b.author_id = a.author_id
         WHERE b.publisher_id = $publisher_id 
         ORDER BY b.publication_year DESC"
    );
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    $publisher['books'] = $books;
    sendResponse(true, 'Publisher details retrieved', $publisher);
}

// Update publisher
else if ($action === 'update_publisher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $publisher_id = (int)($_POST['publisher_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    
    if ($publisher_id <= 0) {
        sendResponse(false, 'Invalid publisher ID', null, 400);
    }
    
    $publisher = getPublisherById($publisher_id);
    if (!$publisher) {
        sendResponse(false, 'Publisher not found', null, 404);
    }
    
    $stmt = $conn->prepare(
        "UPDATE publishers SET name = ?, address = ?, city = ?, country = ?, 
         phone = ?, email = ?, website = ? WHERE publisher_id = ?"
    );
    
    $stmt->bind_param("sssssssi", $name, $address, $city, $country, $phone, $email, $website, $publisher_id);
    
    if ($stmt->execute()) {
        logAuditAction('PUBLISHER_UPDATED', $_POST['user_id'] ?? null, 'publishers', $publisher_id);
        sendResponse(true, 'Publisher updated successfully');
    } else {
        sendResponse(false, 'Update failed: ' . $conn->error, null, 500);
    }
}

// Delete publisher
else if ($action === 'delete_publisher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $publisher_id = (int)($_POST['publisher_id'] ?? 0);
    
    if ($publisher_id <= 0) {
        sendResponse(false, 'Invalid publisher ID', null, 400);
    }
    
    $publisher = getPublisherById($publisher_id);
    if (!$publisher) {
        sendResponse(false, 'Publisher not found', null, 404);
    }
    
    // Check if publisher has books
    $result = $conn->query("SELECT COUNT(*) as count FROM books WHERE publisher_id = $publisher_id");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        sendResponse(false, 'Cannot delete publisher with associated books', null, 400);
    }
    
    $conn->query("DELETE FROM publishers WHERE publisher_id = $publisher_id");
    
    logAuditAction('PUBLISHER_DELETED', $_POST['user_id'] ?? null, 'publishers', $publisher_id);
    sendResponse(true, 'Publisher deleted successfully');
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
