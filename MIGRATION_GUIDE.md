# Database Schema Migration Guide

## Overview

The Library Management System has been migrated from a schema using VARCHAR fields for author and publisher names to a normalized schema with separate `authors` and `publishers` tables. This guide helps developers understand the changes and migrate their code.

## Key Changes

### 1. Books Table Schema Change

**BEFORE (Old Schema):**
```sql
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100),          -- String value
    publisher VARCHAR(100),       -- String value
    genre VARCHAR(50),
    isbn VARCHAR(20),
    publication_date DATE,
    description TEXT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    status ENUM('active', 'inactive', 'damaged', 'lost') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**AFTER (New Schema):**
```sql
CREATE TABLE authors (
    author_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    bio TEXT,
    birth_date DATE,
    nationality VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_author (first_name, last_name)
);

CREATE TABLE publishers (
    publisher_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    address VARCHAR(255),
    city VARCHAR(50),
    country VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_publisher (name)
);

CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author_id INT NOT NULL,       -- Foreign key
    publisher_id INT,             -- Foreign key
    genre VARCHAR(50),
    isbn VARCHAR(20),
    publication_year INT,
    description TEXT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    status ENUM('active', 'inactive', 'damaged', 'lost') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(author_id) ON DELETE RESTRICT,
    FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE SET NULL,
    INDEX idx_author (author_id),
    INDEX idx_publisher (publisher_id),
    FULLTEXT INDEX ft_title (title)
);
```

### 2. Sample Data Migration

**BEFORE:**
```sql
INSERT INTO books (title, author, publisher, genre) 
VALUES ('The Great Gatsby', 'F. Scott Fitzgerald', 'Scribner', 'Fiction');
```

**AFTER:**
```sql
-- First insert author
INSERT INTO authors (first_name, last_name, nationality, birth_date) 
VALUES ('F. Scott', 'Fitzgerald', 'American', '1896-09-24');
-- Get the inserted author_id (e.g., 5)

-- Then insert publisher
INSERT INTO publishers (name, address, city, country) 
VALUES ('Scribner', '1230 Avenue of the Americas', 'New York', 'United States');
-- Get the inserted publisher_id (e.g., 3)

-- Finally insert book with foreign keys
INSERT INTO books (title, author_id, publisher_id, genre, publication_year) 
VALUES ('The Great Gatsby', 5, 3, 'Fiction', 1925);
```

## API Code Migration

### Adding a Book - Backend

**BEFORE (Old Code - DEPRECATED):**
```php
// File: backend/api/books.php
if ($action === 'add_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $author = sanitizeInput($_POST['author'] ?? '');      // String
    $publisher = sanitizeInput($_POST['publisher'] ?? ''); // String
    $genre = sanitizeInput($_POST['genre'] ?? '');
    
    $stmt = $conn->prepare(
        "INSERT INTO books (title, author, publisher, genre, total_copies, available_copies) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param("ssssii", $title, $author, $publisher, $genre, $total_copies, $total_copies);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Book added', ['book_id' => $stmt->insert_id]);
    }
}
```

**AFTER (New Code - UPDATED):**
```php
// File: backend/api/books.php
if ($action === 'add_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $author_id = (int)($_POST['author_id'] ?? 0);         // Integer ID
    $publisher_id = (int)($_POST['publisher_id'] ?? 0);   // Integer ID
    $genre = sanitizeInput($_POST['genre'] ?? '');
    
    // Validate author exists
    if (!getAuthorById($author_id)) {
        sendResponse(false, 'Author not found', null, 400);
    }
    
    // Validate publisher exists
    if (!getPublisherById($publisher_id)) {
        sendResponse(false, 'Publisher not found', null, 400);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO books (title, author_id, publisher_id, genre, total_copies, available_copies, status) 
         VALUES (?, ?, ?, ?, ?, ?, 'active')"
    );
    
    $stmt->bind_param("siisii", $title, $author_id, $publisher_id, $genre, $total_copies, $total_copies);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Book added', ['book_id' => $stmt->insert_id]);
    }
}
```

### Listing Books - Backend

**BEFORE (Old Code):**
```php
// Searching by author was problematic with VARCHAR
$query = "SELECT * FROM books WHERE author LIKE '%$search%'";
// This only found exact string matches
```

**AFTER (New Code):**
```php
// Now we can JOIN with authors table for better querying
$query = "SELECT b.*, 
          CONCAT(a.first_name, ' ', a.last_name) as author_name,
          p.name as publisher_name
          FROM books b
          LEFT JOIN authors a ON b.author_id = a.author_id
          LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
          WHERE CONCAT(a.first_name, ' ', a.last_name) LIKE '%$search%'";
```

### Getting Book Details - Backend

**BEFORE (Old Code):**
```php
// File: backend/includes/functions.php
function getBookById($book_id) {
    global $conn;
    $result = $conn->query("SELECT * FROM books WHERE book_id = $book_id");
    return $result->fetch_assoc();
}
// Returns: ['book_id' => 42, 'author' => 'F. Scott Fitzgerald', 'publisher' => 'Scribner', ...]
```

**AFTER (New Code):**
```php
// File: backend/includes/functions.php
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
    return $result->fetch_assoc();
}
// Returns: ['book_id' => 42, 'author_id' => 5, 'author_name' => 'F. Scott Fitzgerald', 
//           'publisher_id' => 3, 'publisher_name' => 'Scribner', ...]
```

## Frontend Code Migration

### Adding a Book - Frontend (JavaScript)

**BEFORE (Old Code):**
```javascript
// File: public/js/app.js
async function addBook(event) {
    event.preventDefault();
    
    const formData = {
        title: document.getElementById('title').value,
        author: document.getElementById('author').value,        // Text input
        publisher: document.getElementById('publisher').value,  // Text input
        genre: document.getElementById('genre').value,
        isbn: document.getElementById('isbn').value,
        total_copies: document.getElementById('total_copies').value,
        user_id: currentUserId
    };
    
    const response = await booksAPI.addBook(formData);
    if (response.success) {
        showMessage('Book added!');
    }
}
```

**HTML Form:**
```html
<!-- BEFORE -->
<form id="bookForm">
    <input type="text" id="title" placeholder="Title" required>
    <input type="text" id="author" placeholder="Author" required>      <!-- Text input -->
    <input type="text" id="publisher" placeholder="Publisher" required> <!-- Text input -->
    <input type="text" id="genre" placeholder="Genre">
    <input type="text" id="isbn" placeholder="ISBN">
    <button type="submit">Add Book</button>
</form>
```

**AFTER (New Code):**
```javascript
// File: public/js/app.js
async function loadBookForm() {
    // Load dropdown data
    const authors = await dropdownsAPI.getAuthors();
    const publishers = await dropdownsAPI.getPublishers();
    
    // Populate dropdowns
    populateSelect('author_id', authors.data);
    populateSelect('publisher_id', publishers.data);
}

async function addBook(event) {
    event.preventDefault();
    
    const formData = {
        title: document.getElementById('title').value,
        author_id: parseInt(document.getElementById('author_id').value),     // Select dropdown
        publisher_id: parseInt(document.getElementById('publisher_id').value), // Select dropdown
        genre: document.getElementById('genre').value,
        isbn: document.getElementById('isbn').value,
        total_copies: document.getElementById('total_copies').value,
        user_id: currentUserId
    };
    
    const response = await booksAPI.addBook(formData);
    if (response.success) {
        showMessage('Book added!');
    }
}

function populateSelect(elementId, data) {
    const select = document.getElementById(elementId);
    select.innerHTML = '<option value="">Select...</option>';
    data.forEach(item => {
        const option = document.createElement('option');
        option.value = item.author_id || item.publisher_id;
        option.textContent = item.name || `${item.name}`;
        select.appendChild(option);
    });
}

// Load form on page load
document.addEventListener('DOMContentLoaded', loadBookForm);
```

**HTML Form:**
```html
<!-- AFTER -->
<form id="bookForm" onsubmit="addBook(event)">
    <input type="text" id="title" placeholder="Title" required>
    
    <!-- Changed from text input to select dropdown -->
    <select id="author_id" required>
        <option value="">Loading authors...</option>
    </select>
    
    <select id="publisher_id" required>
        <option value="">Loading publishers...</option>
    </select>
    
    <input type="text" id="genre" placeholder="Genre">
    <input type="text" id="isbn" placeholder="ISBN">
    <input type="number" id="total_copies" value="1" min="1">
    
    <button type="submit">Add Book</button>
</form>
```

### Displaying Books - Frontend

**BEFORE (Old Code):**
```javascript
// File: public/js/app.js
async function loadBooks() {
    const response = await booksAPI.listBooks();
    
    const html = response.data.map(book => `
        <div class="book-card">
            <h3>${book.title}</h3>
            <p>Author: ${book.author}</p>              <!-- String directly -->
            <p>Publisher: ${book.publisher}</p>        <!-- String directly -->
            <p>Available: ${book.available}/${book.total}</p>
        </div>
    `).join('');
    
    document.getElementById('booksList').innerHTML = html;
}
```

**AFTER (New Code):**
```javascript
// File: public/js/app.js
async function loadBooks() {
    const response = await booksAPI.listBooks();
    
    const html = response.data.map(book => `
        <div class="book-card">
            <h3>${book.title}</h3>
            <p>Author: ${book.author_name}</p>         <!-- From joined authors table -->
            <p>Publisher: ${book.publisher_name}</p>   <!-- From joined publishers table -->
            <p>Available: ${book.available}/${book.total}</p>
        </div>
    `).join('');
    
    document.getElementById('booksList').innerHTML = html;
}
```

### Search Books - Frontend

**BEFORE (Old Code):**
```javascript
// Search only worked on exact text matches
async function searchBooks(query) {
    // Searching by author name was unreliable
    const response = await booksAPI.search(query, 'author');
}
```

**AFTER (New Code):**
```javascript
// Now can search by author first/last name separately or together
async function searchBooks(query) {
    // search_by can now be: 'title', 'author', 'publisher', 'isbn', 'all'
    const response = await booksAPI.search(query, 'author');
    
    // Results include author_name and publisher_name
    response.data.forEach(book => {
        console.log(`${book.title} by ${book.author_name}`);
    });
}
```

## Helper Functions

### New Functions Added to backend/includes/functions.php

```php
// Get author details
function getAuthorById($author_id) {
    global $conn;
    $result = $conn->query(
        "SELECT * FROM authors WHERE author_id = $author_id"
    );
    return $result->fetch_assoc();
}

// Get publisher details
function getPublisherById($publisher_id) {
    global $conn;
    $result = $conn->query(
        "SELECT * FROM publishers WHERE publisher_id = $publisher_id"
    );
    return $result->fetch_assoc();
}

// Search authors by name
function searchAuthors($search_term) {
    global $conn;
    $result = $conn->query(
        "SELECT * FROM authors 
         WHERE CONCAT(first_name, ' ', last_name) LIKE '%$search_term%'
         ORDER BY last_name, first_name ASC"
    );
    
    $authors = [];
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row;
    }
    return $authors;
}

// Search publishers by name
function searchPublishers($search_term) {
    global $conn;
    $result = $conn->query(
        "SELECT * FROM publishers WHERE name LIKE '%$search_term%' ORDER BY name ASC"
    );
    
    $publishers = [];
    while ($row = $result->fetch_assoc()) {
        $publishers[] = $row;
    }
    return $publishers;
}
```

## New API Endpoints

### Authors Management

```
POST   /backend/api/authors_publishers.php?action=add_author
GET    /backend/api/authors_publishers.php?action=list_authors
GET    /backend/api/authors_publishers.php?action=get_author&author_id={id}
POST   /backend/api/authors_publishers.php?action=update_author
POST   /backend/api/authors_publishers.php?action=delete_author
```

### Publishers Management

```
POST   /backend/api/authors_publishers.php?action=add_publisher
GET    /backend/api/authors_publishers.php?action=list_publishers
GET    /backend/api/authors_publishers.php?action=get_publisher&publisher_id={id}
POST   /backend/api/authors_publishers.php?action=update_publisher
POST   /backend/api/authors_publishers.php?action=delete_publisher
```

### Dropdowns

```
GET    /backend/api/dropdowns.php?action=authors
GET    /backend/api/dropdowns.php?action=publishers
GET    /backend/api/dropdowns.php?action=categories
GET    /backend/api/dropdowns.php?action=genres
GET    /backend/api/dropdowns.php?action=members
GET    /backend/api/dropdowns.php?action=book_statuses
GET    /backend/api/dropdowns.php?action=circulation_statuses
GET    /backend/api/dropdowns.php?action=fine_statuses
```

## New JavaScript API Objects

### authorsAPI

```javascript
authorsAPI.addAuthor(data)
authorsAPI.listAuthors(params)
authorsAPI.getAuthor(authorId)
authorsAPI.updateAuthor(data)
authorsAPI.deleteAuthor(authorId, userId)
```

### publishersAPI

```javascript
publishersAPI.addPublisher(data)
publishersAPI.listPublishers(params)
publishersAPI.getPublisher(publisherId)
publishersAPI.updatePublisher(data)
publishersAPI.deletePublisher(publisherId, userId)
```

### dropdownsAPI

```javascript
dropdownsAPI.getAuthors()
dropdownsAPI.getPublishers()
dropdownsAPI.getCategories()
dropdownsAPI.getGenres()
dropdownsAPI.getMembers()
dropdownsAPI.getBookStatuses()
dropdownsAPI.getCirculationStatuses()
dropdownsAPI.getFineStatuses()
```

## Database Views

### book_details
Denormalized view with author and publisher names:
```sql
SELECT b.*, 
       CONCAT(a.first_name, ' ', a.last_name) as author_name,
       p.name as publisher_name
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN publishers p ON b.publisher_id = p.publisher_id;
```

### transaction_history
Circulation with all related information:
```sql
SELECT c.*, 
       CONCAT(u.first_name, ' ', u.last_name) as member_name,
       b.title as book_title,
       CONCAT(a.first_name, ' ', a.last_name) as author_name
FROM circulation c
JOIN users u ON c.user_id = u.user_id
JOIN books b ON c.book_id = b.book_id
LEFT JOIN authors a ON b.author_id = a.author_id;
```

## Migration Checklist

- [ ] Update database schema (create authors and publishers tables)
- [ ] Migrate existing author and publisher data
- [ ] Update books table foreign keys
- [ ] Create database indexes
- [ ] Create database views
- [ ] Add new API endpoints (authors_publishers.php, dropdowns.php)
- [ ] Update books.php API endpoints
- [ ] Update JavaScript API layer (api.js)
- [ ] Update frontend forms to use dropdowns
- [ ] Update frontend display logic
- [ ] Update backend helper functions
- [ ] Test all CRUD operations
- [ ] Test search functionality
- [ ] Update documentation

## Backwards Compatibility

**Note:** The old schema using VARCHAR for author and publisher names is **NOT** supported going forward. All new development should use the new normalized schema.

If you have existing applications using the old schema, you must migrate to the new schema before continuing development.

## Performance Improvements

1. **Better Indexing**: Foreign key indexes on author_id and publisher_id enable faster lookups
2. **Faster Search**: Can now search authors and publishers independently
3. **Data Integrity**: Foreign key constraints prevent orphaned records
4. **Reduced Storage**: No more duplicate author/publisher names across multiple books
5. **Better Querying**: JOINs with normalized tables are more efficient at scale

## Support

For questions about the migration, refer to:
- API_ENDPOINTS_UPDATED.md - Complete API documentation
- This file - Migration guide and code examples
- backend/api/authors_publishers.php - Implementation examples
- public/js/api.js - JavaScript implementation
