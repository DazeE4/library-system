# API Endpoints Documentation - Updated Schema Integration

## Overview

The Library Management System has been upgraded to use a normalized database schema with separate tables for Authors and Publishers. This document describes all available API endpoints and their usage.

## Table of Contents

1. [Authentication API](#authentication-api)
2. [Books API (Updated)](#books-api-updated)
3. [Authors & Publishers API (New)](#authors--publishers-api-new)
4. [Circulation API](#circulation-api)
5. [Fines API](#fines-api)
6. [Reports API](#reports-api)
7. [Dropdowns API (New)](#dropdowns-api-new)

---

## Authentication API

### Base URL
```
http://localhost/library_system/backend/api/auth.php
```

### Endpoints

#### 1. Register User
```
POST /auth.php?action=register
```

**Parameters:**
- `email` (string, required): User email
- `password` (string, required): User password (min 6 chars)
- `first_name` (string, required): User first name
- `last_name` (string, required): User last name
- `role` (string, required): One of `student`, `teacher`, `librarian`, `admin`
- `phone` (string, optional): Contact number

**Response:**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user_id": 1
  }
}
```

#### 2. Login
```
POST /auth.php?action=login
```

**Parameters:**
- `email` (string, required): User email
- `password` (string, required): User password

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user_id": 1,
    "email": "user@example.com",
    "role": "student",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

#### 3. Get User Profile
```
GET /auth.php?action=profile&user_id={user_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Profile retrieved",
  "data": {
    "user_id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "role": "student",
    "phone": "1234567890",
    "status": "active",
    "membership_date": "2024-01-15"
  }
}
```

---

## Books API (Updated)

### Base URL
```
http://localhost/library_system/backend/api/books.php
```

### Important Changes

**Old Schema (Deprecated):**
```php
$title = $_POST['title'];
$author = $_POST['author'];  // String value
$publisher = $_POST['publisher'];  // String value
```

**New Schema (Current):**
```php
$title = $_POST['title'];
$author_id = (int)$_POST['author_id'];  // Foreign key to authors table
$publisher_id = (int)$_POST['publisher_id'];  // Foreign key to publishers table
```

### Endpoints

#### 1. Add New Book
```
POST /books.php?action=add_book
```

**Parameters:**
- `title` (string, required): Book title
- `author_id` (integer, required): ID of author (from authors table)
- `publisher_id` (integer, required): ID of publisher (from publishers table)
- `genre` (string, optional): Book genre
- `isbn` (string, optional): ISBN number
- `publication_year` (integer, optional): Publication year
- `description` (string, optional): Book description
- `total_copies` (integer, optional, default 1): Number of copies to add
- `user_id` (integer, optional): User adding the book (for audit log)

**Response:**
```json
{
  "success": true,
  "message": "Book added successfully",
  "data": {
    "book_id": 42
  }
}
```

**Example Usage (JavaScript):**
```javascript
const bookData = {
    title: 'The Great Gatsby',
    author_id: 5,  // Get from Authors dropdown
    publisher_id: 3,  // Get from Publishers dropdown
    genre: 'Fiction',
    isbn: '978-0-7432-7356-5',
    publication_year: 1925,
    description: 'A classic American novel',
    total_copies: 3,
    user_id: currentUserId
};

const response = await booksAPI.addBook(bookData);
```

#### 2. List Books
```
GET /books.php?action=list_books
```

**Query Parameters:**
- `genre` (string, optional): Filter by genre
- `status` (string, optional): Filter by status (active, inactive, damaged, lost)
- `search` (string, optional): Search in title, author name, publisher name, or ISBN
- `limit` (integer, optional, default 50): Number of results
- `offset` (integer, optional, default 0): Pagination offset

**Response:**
```json
{
  "success": true,
  "message": "Books retrieved",
  "data": [
    {
      "book_id": 42,
      "title": "The Great Gatsby",
      "author_id": 5,
      "author_name": "F. Scott Fitzgerald",
      "publisher_id": 3,
      "publisher_name": "Scribner",
      "genre": "Fiction",
      "isbn": "978-0-7432-7356-5",
      "publication_year": 1925,
      "total": 3,
      "available": 2,
      "status": "active"
    }
  ]
}
```

#### 3. Get Book Details
```
GET /books.php?action=get_book&book_id={book_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Book details retrieved",
  "data": {
    "book_id": 42,
    "title": "The Great Gatsby",
    "author_id": 5,
    "author_name": "F. Scott Fitzgerald",
    "publisher_id": 3,
    "publisher_name": "Scribner",
    "genre": "Fiction",
    "isbn": "978-0-7432-7356-5",
    "publication_year": 1925,
    "description": "A classic American novel",
    "categories": [
      {
        "category_id": 1,
        "category_name": "Fiction"
      }
    ]
  }
}
```

#### 4. Update Book
```
POST /books.php?action=update_book
```

**Parameters:**
- `book_id` (integer, required): Book ID to update
- `title` (string, optional): New title
- `author_id` (integer, optional): New author ID
- `publisher_id` (integer, optional): New publisher ID
- `genre` (string, optional): New genre
- `status` (string, optional): New status
- `description` (string, optional): New description
- `user_id` (integer, optional): User making the update

**Response:**
```json
{
  "success": true,
  "message": "Book updated successfully"
}
```

#### 5. Delete Book
```
POST /books.php?action=delete_book
```

**Parameters:**
- `book_id` (integer, required): Book ID
- `user_id` (integer, optional): User deleting the book

**Response:**
```json
{
  "success": true,
  "message": "Book deleted successfully"
}
```

#### 6. Search Books
```
GET /books.php?action=search&q={query}&search_by={field}
```

**Parameters:**
- `q` (string, required): Search query
- `search_by` (string, optional): One of `title`, `author`, `publisher`, `isbn`, `all` (default)

**Example:**
```
GET /books.php?action=search&q=Fitzgerald&search_by=author
```

**Response:**
```json
{
  "success": true,
  "message": "Search results",
  "data": [
    {
      "book_id": 42,
      "title": "The Great Gatsby",
      "author_name": "F. Scott Fitzgerald",
      "publisher_name": "Scribner",
      "total": 3
    }
  ]
}
```

---

## Authors & Publishers API (New)

### Base URL
```
http://localhost/library_system/backend/api/authors_publishers.php
```

### Authors Endpoints

#### 1. Add Author
```
POST /authors_publishers.php?action=add_author
```

**Parameters:**
- `first_name` (string, required): Author first name
- `last_name` (string, required): Author last name
- `bio` (string, optional): Author biography
- `birth_date` (date, optional): Birth date (YYYY-MM-DD)
- `nationality` (string, optional): Nationality
- `user_id` (integer, optional): User adding the author

**Response:**
```json
{
  "success": true,
  "message": "Author added successfully",
  "data": {
    "author_id": 15
  }
}
```

#### 2. List Authors
```
GET /authors_publishers.php?action=list_authors
```

**Query Parameters:**
- `search` (string, optional): Search by name or biography
- `limit` (integer, optional, default 50): Number of results
- `offset` (integer, optional, default 0): Pagination offset

**Response:**
```json
{
  "success": true,
  "message": "Authors retrieved",
  "data": [
    {
      "author_id": 5,
      "first_name": "F. Scott",
      "last_name": "Fitzgerald",
      "bio": "American author of novels and short stories",
      "birth_date": "1896-09-24",
      "nationality": "American"
    }
  ]
}
```

#### 3. Get Author Details
```
GET /authors_publishers.php?action=get_author&author_id={author_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Author details retrieved",
  "data": {
    "author_id": 5,
    "first_name": "F. Scott",
    "last_name": "Fitzgerald",
    "bio": "American author of novels and short stories",
    "birth_date": "1896-09-24",
    "nationality": "American",
    "books": [
      {
        "book_id": 42,
        "title": "The Great Gatsby",
        "isbn": "978-0-7432-7356-5",
        "publication_year": 1925
      }
    ]
  }
}
```

#### 4. Update Author
```
POST /authors_publishers.php?action=update_author
```

**Parameters:**
- `author_id` (integer, required): Author ID
- `first_name` (string, optional): New first name
- `last_name` (string, optional): New last name
- `bio` (string, optional): New biography
- `birth_date` (date, optional): New birth date
- `nationality` (string, optional): New nationality
- `user_id` (integer, optional): User making the update

**Response:**
```json
{
  "success": true,
  "message": "Author updated successfully"
}
```

#### 5. Delete Author
```
POST /authors_publishers.php?action=delete_author
```

**Parameters:**
- `author_id` (integer, required): Author ID to delete
- `user_id` (integer, optional): User deleting the author

**Note:** An author cannot be deleted if books are associated with them.

**Response:**
```json
{
  "success": true,
  "message": "Author deleted successfully"
}
```

### Publishers Endpoints

#### 1. Add Publisher
```
POST /authors_publishers.php?action=add_publisher
```

**Parameters:**
- `name` (string, required): Publisher name
- `address` (string, optional): Street address
- `city` (string, optional): City
- `country` (string, optional): Country
- `phone` (string, optional): Phone number
- `email` (string, optional): Email address
- `website` (string, optional): Website URL
- `user_id` (integer, optional): User adding the publisher

**Response:**
```json
{
  "success": true,
  "message": "Publisher added successfully",
  "data": {
    "publisher_id": 8
  }
}
```

#### 2. List Publishers
```
GET /authors_publishers.php?action=list_publishers
```

**Query Parameters:**
- `search` (string, optional): Search by name, city, or country
- `limit` (integer, optional, default 50): Number of results
- `offset` (integer, optional, default 0): Pagination offset

**Response:**
```json
{
  "success": true,
  "message": "Publishers retrieved",
  "data": [
    {
      "publisher_id": 3,
      "name": "Scribner",
      "address": "1230 Avenue of the Americas",
      "city": "New York",
      "country": "United States",
      "phone": "+1-212-698-7000",
      "email": "info@scribner.com",
      "website": "www.scribner.com"
    }
  ]
}
```

#### 3. Get Publisher Details
```
GET /authors_publishers.php?action=get_publisher&publisher_id={publisher_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Publisher details retrieved",
  "data": {
    "publisher_id": 3,
    "name": "Scribner",
    "address": "1230 Avenue of the Americas",
    "city": "New York",
    "country": "United States",
    "phone": "+1-212-698-7000",
    "email": "info@scribner.com",
    "website": "www.scribner.com",
    "books": [
      {
        "book_id": 42,
        "title": "The Great Gatsby",
        "isbn": "978-0-7432-7356-5",
        "publication_year": 1925,
        "author_name": "F. Scott Fitzgerald"
      }
    ]
  }
}
```

#### 4. Update Publisher
```
POST /authors_publishers.php?action=update_publisher
```

**Parameters:**
- `publisher_id` (integer, required): Publisher ID
- `name` (string, optional): New name
- `address` (string, optional): New address
- `city` (string, optional): New city
- `country` (string, optional): New country
- `phone` (string, optional): New phone
- `email` (string, optional): New email
- `website` (string, optional): New website
- `user_id` (integer, optional): User making the update

**Response:**
```json
{
  "success": true,
  "message": "Publisher updated successfully"
}
```

#### 5. Delete Publisher
```
POST /authors_publishers.php?action=delete_publisher
```

**Parameters:**
- `publisher_id` (integer, required): Publisher ID to delete
- `user_id` (integer, optional): User deleting the publisher

**Note:** A publisher cannot be deleted if books are associated with them.

**Response:**
```json
{
  "success": true,
  "message": "Publisher deleted successfully"
}
```

---

## Circulation API

### Base URL
```
http://localhost/library_system/backend/api/circulation.php
```

### Endpoints

#### 1. Borrow Book
```
POST /circulation.php?action=borrow
```

**Parameters:**
- `user_id` (integer, required): Member ID
- `book_id` (integer, required): Book ID

**Response:**
```json
{
  "success": true,
  "message": "Book borrowed successfully",
  "data": {
    "circulation_id": 120,
    "due_date": "2024-02-15"
  }
}
```

#### 2. Return Book
```
POST /circulation.php?action=return
```

**Parameters:**
- `circulation_id` (integer, required): Circulation record ID
- `condition` (string, optional): Book condition (good, damaged, lost)

**Response:**
```json
{
  "success": true,
  "message": "Book returned successfully",
  "data": {
    "fine_due": 0.00
  }
}
```

#### 3. Renew Book
```
POST /circulation.php?action=renew
```

**Parameters:**
- `circulation_id` (integer, required): Circulation record ID

**Response:**
```json
{
  "success": true,
  "message": "Book renewed successfully",
  "data": {
    "new_due_date": "2024-03-15"
  }
}
```

#### 4. Get My Books
```
GET /circulation.php?action=my_books&user_id={user_id}&status={status}
```

**Parameters:**
- `user_id` (integer, required): Member ID
- `status` (string, optional): Filter by status (borrowed, returned)

**Response:**
```json
{
  "success": true,
  "message": "User books retrieved",
  "data": [
    {
      "circulation_id": 120,
      "book_id": 42,
      "title": "The Great Gatsby",
      "author_name": "F. Scott Fitzgerald",
      "borrowed_date": "2024-01-16",
      "due_date": "2024-02-15",
      "status": "borrowed",
      "days_remaining": 30
    }
  ]
}
```

#### 5. Get All Borrowed Books
```
GET /circulation.php?action=all_borrowed
```

**Query Parameters:**
- `overdue_only` (boolean, optional): Show only overdue books
- `limit` (integer, optional): Number of results
- `offset` (integer, optional): Pagination offset

**Response:**
```json
{
  "success": true,
  "message": "All borrowed books",
  "data": [...]
}
```

---

## Fines API

### Base URL
```
http://localhost/library_system/backend/api/fines.php
```

### Endpoints

#### 1. Get User Fines
```
GET /fines.php?action=get_fines&user_id={user_id}&status={status}
```

**Parameters:**
- `user_id` (integer, required): Member ID
- `status` (string, optional): Filter by status (all, pending, partial, paid, waived)

**Response:**
```json
{
  "success": true,
  "message": "User fines retrieved",
  "data": [
    {
      "fine_id": 50,
      "circulation_id": 120,
      "book_id": 42,
      "title": "The Great Gatsby",
      "amount": 50.00,
      "status": "pending",
      "created_date": "2024-02-15"
    }
  ]
}
```

#### 2. Pay Fine
```
POST /fines.php?action=pay_fine
```

**Parameters:**
- `fine_id` (integer, required): Fine ID
- `payment_method` (string, optional): Payment method (cash, card, online)

**Response:**
```json
{
  "success": true,
  "message": "Fine paid successfully"
}
```

#### 3. Pay Multiple Fines
```
POST /fines.php?action=pay_multiple_fines
```

**Parameters:**
- `user_id` (integer, required): Member ID
- `payment_method` (string, optional): Payment method

**Response:**
```json
{
  "success": true,
  "message": "All fines paid successfully",
  "data": {
    "total_paid": 150.00,
    "fines_count": 3
  }
}
```

#### 4. Waive Fine (Admin Only)
```
POST /fines.php?action=waive_fine
```

**Parameters:**
- `fine_id` (integer, required): Fine ID
- `reason` (string, optional): Reason for waiving
- `admin_user_id` (integer, optional): Admin user ID

**Response:**
```json
{
  "success": true,
  "message": "Fine waived successfully"
}
```

---

## Reports API

### Base URL
```
http://localhost/library_system/backend/api/reports.php
```

### Endpoints (See Reports API documentation for details)

- Usage Statistics
- Popular Books
- Borrowing Trends
- Inventory Report
- Overdue Items
- User Activity
- Member Engagement
- Book Condition Report
- Export Data

---

## Dropdowns API (New)

### Base URL
```
http://localhost/library_system/backend/api/dropdowns.php
```

This API provides data for populating HTML dropdown/select elements in forms.

### Endpoints

#### 1. Get Authors Dropdown
```
GET /dropdowns.php?action=authors
```

**Response:**
```json
{
  "success": true,
  "message": "Authors list",
  "data": [
    {
      "author_id": 5,
      "name": "F. Scott Fitzgerald"
    },
    {
      "author_id": 6,
      "name": "George Orwell"
    }
  ]
}
```

#### 2. Get Publishers Dropdown
```
GET /dropdowns.php?action=publishers
```

**Response:**
```json
{
  "success": true,
  "message": "Publishers list",
  "data": [
    {
      "publisher_id": 3,
      "name": "Scribner"
    },
    {
      "publisher_id": 4,
      "name": "Penguin Books"
    }
  ]
}
```

#### 3. Get Categories Dropdown
```
GET /dropdowns.php?action=categories
```

**Response:**
```json
{
  "success": true,
  "message": "Categories list",
  "data": [
    {
      "category_id": 1,
      "category_name": "Fiction"
    },
    {
      "category_id": 2,
      "category_name": "Non-Fiction"
    }
  ]
}
```

#### 4. Get Genres Dropdown
```
GET /dropdowns.php?action=genres
```

**Response:**
```json
{
  "success": true,
  "message": "Genres list",
  "data": [
    {
      "value": "Fiction",
      "label": "Fiction"
    },
    {
      "value": "Non-Fiction",
      "label": "Non-Fiction"
    }
  ]
}
```

#### 5. Get Members Dropdown
```
GET /dropdowns.php?action=members
```

**Response:**
```json
{
  "success": true,
  "message": "Members list",
  "data": [
    {
      "user_id": 10,
      "name": "John Doe",
      "email": "john@example.com"
    }
  ]
}
```

#### 6. Get Book Statuses Dropdown
```
GET /dropdowns.php?action=book_statuses
```

**Response:**
```json
{
  "success": true,
  "message": "Book statuses",
  "data": [
    {
      "value": "active",
      "label": "Active"
    },
    {
      "value": "inactive",
      "label": "Inactive"
    },
    {
      "value": "damaged",
      "label": "Damaged"
    },
    {
      "value": "lost",
      "label": "Lost"
    }
  ]
}
```

#### 7. Get Circulation Statuses Dropdown
```
GET /dropdowns.php?action=circulation_statuses
```

**Response:**
```json
{
  "success": true,
  "message": "Circulation statuses",
  "data": [
    {
      "value": "borrowed",
      "label": "Borrowed"
    },
    {
      "value": "returned",
      "label": "Returned"
    },
    {
      "value": "lost",
      "label": "Lost"
    },
    {
      "value": "damaged",
      "label": "Damaged"
    }
  ]
}
```

#### 8. Get Fine Statuses Dropdown
```
GET /dropdowns.php?action=fine_statuses
```

**Response:**
```json
{
  "success": true,
  "message": "Fine statuses",
  "data": [
    {
      "value": "pending",
      "label": "Pending"
    },
    {
      "value": "partial",
      "label": "Partial"
    },
    {
      "value": "paid",
      "label": "Paid"
    },
    {
      "value": "waived",
      "label": "Waived"
    }
  ]
}
```

---

## Frontend Integration Example

### Adding a Book with New Schema

```javascript
// 1. Load dropdown data
async function loadBookForm() {
    const authors = await dropdownsAPI.getAuthors();
    const publishers = await dropdownsAPI.getPublishers();
    
    // Populate dropdowns
    populateAuthorDropdown(authors.data);
    populatePublisherDropdown(publishers.data);
}

// 2. Submit book form
async function submitBookForm(event) {
    event.preventDefault();
    
    const formData = {
        title: document.getElementById('title').value,
        author_id: parseInt(document.getElementById('author_id').value),
        publisher_id: parseInt(document.getElementById('publisher_id').value),
        genre: document.getElementById('genre').value,
        isbn: document.getElementById('isbn').value,
        publication_year: parseInt(document.getElementById('publication_year').value),
        description: document.getElementById('description').value,
        total_copies: parseInt(document.getElementById('total_copies').value),
        user_id: currentUserId
    };
    
    const response = await booksAPI.addBook(formData);
    
    if (response.success) {
        showMessage('Book added successfully!', 'success');
        loadBooks();
    } else {
        showMessage(response.message, 'error');
    }
}

// 3. Populate author dropdown
function populateAuthorDropdown(authors) {
    const select = document.getElementById('author_id');
    select.innerHTML = '<option value="">Select Author</option>';
    authors.forEach(author => {
        const option = document.createElement('option');
        option.value = author.author_id;
        option.textContent = author.name;
        select.appendChild(option);
    });
}

// 4. Populate publisher dropdown
function populatePublisherDropdown(publishers) {
    const select = document.getElementById('publisher_id');
    select.innerHTML = '<option value="">Select Publisher</option>';
    publishers.forEach(publisher => {
        const option = document.createElement('option');
        option.value = publisher.publisher_id;
        option.textContent = publisher.name;
        select.appendChild(option);
    });
}
```

### HTML Form Example

```html
<form id="bookForm" onsubmit="submitBookForm(event)">
    <input type="text" id="title" placeholder="Book Title" required>
    
    <select id="author_id" required>
        <option value="">Loading authors...</option>
    </select>
    
    <select id="publisher_id" required>
        <option value="">Loading publishers...</option>
    </select>
    
    <input type="text" id="isbn" placeholder="ISBN">
    <input type="number" id="publication_year" placeholder="Publication Year">
    <textarea id="description" placeholder="Description"></textarea>
    <input type="number" id="total_copies" value="1" min="1">
    
    <button type="submit">Add Book</button>
</form>

<script>
    // Load form on page load
    document.addEventListener('DOMContentLoaded', loadBookForm);
</script>
```

---

## Error Handling

All endpoints return a standardized error response:

```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

**Common HTTP Status Codes:**
- `200`: Success
- `400`: Bad Request (invalid parameters)
- `404`: Not Found (resource doesn't exist)
- `500`: Server Error (database error, etc.)

---

## Migration Guide from Old Schema

### Before (Old Schema)
```javascript
const bookData = {
    title: 'The Great Gatsby',
    author: 'F. Scott Fitzgerald',  // String
    publisher: 'Scribner',  // String
    genre: 'Fiction',
    isbn: '978-0-7432-7356-5'
};
```

### After (New Schema)
```javascript
// First, get author and publisher IDs from dropdowns or search
const authors = await dropdownsAPI.getAuthors();
const publishers = await dropdownsAPI.getPublishers();

// Find author and publisher by name
const author = authors.data.find(a => a.name === 'F. Scott Fitzgerald');
const publisher = publishers.data.find(p => p.name === 'Scribner');

const bookData = {
    title: 'The Great Gatsby',
    author_id: author.author_id,  // Integer ID
    publisher_id: publisher.publisher_id,  // Integer ID
    genre: 'Fiction',
    isbn: '978-0-7432-7356-5'
};

await booksAPI.addBook(bookData);
```

---

## Summary of New Features

✅ **Normalized Database Schema**
- Authors stored in separate table with full details
- Publishers stored in separate table with contact information
- Reduced data redundancy and improved data integrity

✅ **New API Endpoints**
- Authors management (CRUD operations)
- Publishers management (CRUD operations)
- Dropdowns API for form population

✅ **Enhanced Frontend Integration**
- New `authorsAPI` object with 5 methods
- New `publishersAPI` object with 5 methods
- New `dropdownsAPI` object with 8 methods
- Better separation of concerns

✅ **Database Views**
- `book_details`: Denormalized view with author and publisher names
- `transaction_history`: Circulation with all related names
- `members`: Filtered view of members
- `staff`: Filtered view of staff

✅ **Improved Search**
- Search by author name (not just full text)
- Search by publisher name
- More efficient queries with proper indexes

