<?php
/**
 * Dropdowns API
 * Provides data for HTML dropdown/select elements
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$conn = require_once '../config/database.php';
require_once '../includes/functions.php';

$action = $_GET['action'] ?? null;

// Get all authors for dropdown
if ($action === 'authors') {
    $result = $conn->query(
        "SELECT author_id, CONCAT(first_name, ' ', last_name) as name 
         FROM authors 
         ORDER BY last_name, first_name ASC"
    );
    
    $authors = [];
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row;
    }
    
    sendResponse(true, 'Authors list', $authors);
}

// Get all publishers for dropdown
else if ($action === 'publishers') {
    $result = $conn->query(
        "SELECT publisher_id, name 
         FROM publishers 
         ORDER BY name ASC"
    );
    
    $publishers = [];
    while ($row = $result->fetch_assoc()) {
        $publishers[] = $row;
    }
    
    sendResponse(true, 'Publishers list', $publishers);
}

// Get all categories for dropdown
else if ($action === 'categories') {
    $result = $conn->query(
        "SELECT category_id, category_name 
         FROM categories 
         ORDER BY category_name ASC"
    );
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    sendResponse(true, 'Categories list', $categories);
}

// Get all genres for dropdown
else if ($action === 'genres') {
    $result = $conn->query(
        "SELECT DISTINCT genre FROM books WHERE status = 'active' ORDER BY genre ASC"
    );
    
    $genres = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['genre'])) {
            $genres[] = ['value' => $row['genre'], 'label' => $row['genre']];
        }
    }
    
    sendResponse(true, 'Genres list', $genres);
}

// Get all users (members) for dropdown
else if ($action === 'members') {
    $result = $conn->query(
        "SELECT user_id, CONCAT(first_name, ' ', last_name) as name, email 
         FROM users 
         WHERE role IN ('student', 'teacher') AND status = 'active'
         ORDER BY last_name, first_name ASC"
    );
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    sendResponse(true, 'Members list', $members);
}

// Get book statuses for dropdown
else if ($action === 'book_statuses') {
    $statuses = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
        ['value' => 'damaged', 'label' => 'Damaged'],
        ['value' => 'lost', 'label' => 'Lost']
    ];
    
    sendResponse(true, 'Book statuses', $statuses);
}

// Get circulation statuses for dropdown
else if ($action === 'circulation_statuses') {
    $statuses = [
        ['value' => 'borrowed', 'label' => 'Borrowed'],
        ['value' => 'returned', 'label' => 'Returned'],
        ['value' => 'lost', 'label' => 'Lost'],
        ['value' => 'damaged', 'label' => 'Damaged']
    ];
    
    sendResponse(true, 'Circulation statuses', $statuses);
}

// Get fine statuses for dropdown
else if ($action === 'fine_statuses') {
    $statuses = [
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'partial', 'label' => 'Partial'],
        ['value' => 'paid', 'label' => 'Paid'],
        ['value' => 'waived', 'label' => 'Waived']
    ];
    
    sendResponse(true, 'Fine statuses', $statuses);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}

?>
