# Reference Material Documentation

## Part 1: Complete API Specification

### 1.1 Authentication API

```
API MODULE: auth.php
────────────────────────────────────────────────────────

ENDPOINT 1: User Registration
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/auth.php?action=register
Content-Type: application/x-www-form-urlencoded

REQUEST Parameters:
  • email (string, required)
    - Format: Valid email address
    - Constraints: Must be unique, max 255 chars
    - Example: "student@university.edu"
    
  • password (string, required)
    - Format: Min 8 chars, 1 uppercase, 1 number, 1 special char
    - Example: "SecurePass123!"
    
  • first_name (string, required)
    - Constraints: Max 100 chars, alphabetic + spaces
    - Example: "John"
    
  • last_name (string, required)
    - Constraints: Max 100 chars
    - Example: "Doe"
    
  • role (string, required)
    - Allowed values: 'student', 'faculty', 'staff'
    - Default: 'student'
    
  • phone (string, optional)
    - Format: 10 digits
    - Example: "9876543210"

RESPONSE Success (200):
{
    "success": true,
    "user_id": 1,
    "message": "Registration successful",
    "email": "student@university.edu"
}

RESPONSE Errors:
  400 Bad Request:
  {
      "success": false,
      "error": "Email already registered"
  }
  
  422 Unprocessable Entity:
  {
      "success": false,
      "errors": {
          "password": "Password must be at least 8 characters",
          "email": "Invalid email format"
      }
  }

RATE LIMITING: 5 registrations per IP per hour

────────────────────────────────────────────────────────

ENDPOINT 2: User Login
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/auth.php?action=login
Content-Type: application/x-www-form-urlencoded

REQUEST Parameters:
  • email (string, required)
    - Example: "student@university.edu"
    
  • password (string, required)
    - Example: "SecurePass123!"
    
  • remember_me (boolean, optional)
    - Default: false
    - Description: Keep session for 30 days

RESPONSE Success (200):
{
    "success": true,
    "user_id": 1,
    "email": "student@university.edu",
    "first_name": "John",
    "role": "student",
    "session_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 3600
}

RESPONSE Errors:
  401 Unauthorized:
  {
      "success": false,
      "error": "Invalid email or password"
  }
  
  429 Too Many Requests:
  {
      "success": false,
      "error": "Too many login attempts. Try again in 15 minutes."
  }

SESSION DURATION: 1 hour (standard), 30 days (remember_me)

────────────────────────────────────────────────────────

ENDPOINT 3: Update Profile
────────────────────────────────────────────────────────
Method:     PUT
Route:      /backend/api/auth.php?action=update_profile
Content-Type: application/x-www-form-urlencoded
Authorization: Required (Bearer Token)

REQUEST Parameters:
  • user_id (integer, required)
    - Description: User to update
    
  • first_name (string, optional)
    - Constraints: Max 100 chars
    
  • last_name (string, optional)
    - Constraints: Max 100 chars
    
  • phone (string, optional)
    - Format: 10 digits
    
  • address (string, optional)
    - Max 500 chars

RESPONSE Success (200):
{
    "success": true,
    "message": "Profile updated successfully",
    "user": {
        "user_id": 1,
        "email": "student@university.edu",
        "first_name": "John",
        "last_name": "Doe",
        "phone": "9876543210",
        "updated_at": "2026-03-06T10:30:00Z"
    }
}

RESPONSE Errors:
  401 Unauthorized:
  {
      "success": false,
      "error": "Authentication required"
  }
  
  403 Forbidden:
  {
      "success": false,
      "error": "Cannot update other user profiles"
  }

────────────────────────────────────────────────────────

ENDPOINT 4: Change Password
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/auth.php?action=change_password
Content-Type: application/x-www-form-urlencoded
Authorization: Required

REQUEST Parameters:
  • current_password (string, required)
    - Description: User's current password for verification
    
  • new_password (string, required)
    - Format: Min 8 chars, 1 uppercase, 1 number, 1 special char
    - Constraint: Must differ from current password
    
  • confirm_password (string, required)
    - Description: Must match new_password

RESPONSE Success (200):
{
    "success": true,
    "message": "Password changed successfully"
}

RESPONSE Errors:
  400 Bad Request:
  {
      "success": false,
      "error": "Current password is incorrect"
  }
  
  422 Unprocessable Entity:
  {
      "success": false,
      "error": "New password must be different from current password"
  }

────────────────────────────────────────────────────────

ENDPOINT 5: Logout
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/auth.php?action=logout
Authorization: Required

REQUEST Parameters: None

RESPONSE Success (200):
{
    "success": true,
    "message": "Logged out successfully"
}

────────────────────────────────────────────────────────
```

### 1.2 Books API

```
API MODULE: books.php
────────────────────────────────────────────────────────

ENDPOINT 1: List Books
────────────────────────────────────────────────────────
Method:     GET
Route:      /backend/api/books.php?action=list_books
Content-Type: application/json

REQUEST Parameters:
  • page (integer, optional)
    - Default: 1
    - Min: 1
    
  • per_page (integer, optional)
    - Default: 20
    - Min: 1, Max: 100
    
  • category_id (integer, optional)
    - Description: Filter by category
    
  • sort_by (string, optional)
    - Allowed: 'title', 'publication_year', 'average_rating'
    - Default: 'title'
    
  • sort_order (string, optional)
    - Allowed: 'ASC', 'DESC'
    - Default: 'ASC'

RESPONSE Success (200):
{
    "success": true,
    "data": [
        {
            "book_id": 1,
            "title": "The Great Gatsby",
            "author_name": "F. Scott Fitzgerald",
            "publisher_name": "Scribner",
            "isbn": "978-0-7432-7356-5",
            "publication_year": 1925,
            "total_copies": 3,
            "available_copies": 2,
            "cover_image": "/uploads/covers/gatsby.jpg",
            "average_rating": 4.5
        }
    ],
    "pagination": {
        "page": 1,
        "per_page": 20,
        "total_records": 150,
        "total_pages": 8
    }
}

────────────────────────────────────────────────────────

ENDPOINT 2: Get Book Details
────────────────────────────────────────────────────────
Method:     GET
Route:      /backend/api/books.php?action=get_book&book_id={id}

RESPONSE Success (200):
{
    "success": true,
    "book": {
        "book_id": 1,
        "title": "The Great Gatsby",
        "author": {
            "author_id": 5,
            "author_name": "F. Scott Fitzgerald",
            "biography": "..."
        },
        "publisher": {
            "publisher_id": 3,
            "publisher_name": "Scribner"
        },
        "isbn": "978-0-7432-7356-5",
        "publication_year": 1925,
        "description": "A novel of the Jazz Age...",
        "total_copies": 3,
        "available_copies": 2,
        "cover_image": "/uploads/covers/gatsby.jpg",
        "average_rating": 4.5,
        "total_reviews": 25,
        "categories": ["Fiction", "Classics"],
        "created_at": "2025-01-15T10:00:00Z",
        "updated_at": "2026-03-06T08:30:00Z"
    }
}

RESPONSE Errors:
  404 Not Found:
  {
      "success": false,
      "error": "Book not found"
  }

────────────────────────────────────────────────────────

ENDPOINT 3: Search Books
────────────────────────────────────────────────────────
Method:     GET
Route:      /backend/api/books.php?action=search

REQUEST Parameters:
  • query (string, required)
    - Description: Search term (title, author, ISBN)
    - Min length: 2
    
  • search_in (string, optional)
    - Allowed: 'all', 'title', 'author', 'isbn'
    - Default: 'all'
    
  • page (integer, optional)
    - Default: 1

RESPONSE Success (200):
{
    "success": true,
    "query": "Gatsby",
    "results": [
        {
            "book_id": 1,
            "title": "The Great Gatsby",
            "author_name": "F. Scott Fitzgerald",
            "relevance_score": 0.95
        }
    ],
    "total_results": 5,
    "search_time_ms": 125
}

────────────────────────────────────────────────────────

ENDPOINT 4: Add Book
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/books.php?action=add_book
Authorization: Required (librarian/admin only)

REQUEST Parameters:
  • title (string, required)
    - Max 255 chars
    
  • author_id (integer, required)
    - Foreign key to authors table
    
  • publisher_id (integer, required)
    - Foreign key to publishers table
    
  • isbn (string, required)
    - Format: 13-digit ISBN or valid ISBN-10
    - Unique constraint
    
  • publication_year (integer, required)
    - Range: 1000-current year
    
  • description (string, optional)
    - Max 1000 chars
    
  • total_copies (integer, required)
    - Min: 1
    
  • category_id (integer, optional)
    - Foreign key to categories table

RESPONSE Success (201):
{
    "success": true,
    "message": "Book added successfully",
    "book_id": 42,
    "book": {
        "book_id": 42,
        "title": "New Book",
        "isbn": "978-1-234567-89-0",
        "created_at": "2026-03-06T10:30:00Z"
    }
}

RESPONSE Errors:
  400 Bad Request:
  {
      "success": false,
      "error": "ISBN already exists in the system"
  }
  
  403 Forbidden:
  {
      "success": false,
      "error": "Only librarians can add books"
  }

────────────────────────────────────────────────────────

ENDPOINT 5: Update Book
────────────────────────────────────────────────────────
Method:     PUT
Route:      /backend/api/books.php?action=update_book&book_id={id}
Authorization: Required (librarian/admin only)

REQUEST Parameters:
  • title (string, optional)
  • author_id (integer, optional)
  • publisher_id (integer, optional)
  • description (string, optional)
  • total_copies (integer, optional)

RESPONSE Success (200):
{
    "success": true,
    "message": "Book updated successfully",
    "book_id": 42
}

────────────────────────────────────────────────────────

ENDPOINT 6: Delete Book
────────────────────────────────────────────────────────
Method:     DELETE
Route:      /backend/api/books.php?action=delete_book&book_id={id}
Authorization: Required (admin only)

RESPONSE Success (200):
{
    "success": true,
    "message": "Book deleted successfully"
}

RESPONSE Errors:
  403 Forbidden:
  {
      "success": false,
      "error": "Only admins can delete books"
  }
  
  409 Conflict:
  {
      "success": false,
      "error": "Cannot delete book with active borrowings"
  }

────────────────────────────────────────────────────────
```

### 1.3 Circulation API

```
API MODULE: circulation.php
────────────────────────────────────────────────────────

ENDPOINT 1: Borrow Book
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/circulation.php?action=borrow
Authorization: Required
Content-Type: application/x-www-form-urlencoded

REQUEST Parameters:
  • user_id (integer, required)
    - User borrowing the book
    
  • book_id (integer, required)
    - Book to borrow

RESPONSE Success (201):
{
    "success": true,
    "message": "Book borrowed successfully",
    "circulation": {
        "circulation_id": 100,
        "user_id": 1,
        "book_id": 1,
        "borrow_date": "2026-03-06",
        "due_date": "2026-03-20",
        "status": "borrowed",
        "renewal_count": 0
    }
}

RESPONSE Errors:
  400 Bad Request:
  {
      "success": false,
      "error": "Book is not available"
  }
  
  409 Conflict:
  {
      "success": false,
      "error": "User already has 5 borrowed books (max limit)"
  }

BUSINESS RULES:
  • Borrow period: 14 days (can be renewed 3 times)
  • Maximum books per user: 5
  • Fine if overdue: Rs 10/day (max Rs 300)

────────────────────────────────────────────────────────

ENDPOINT 2: Return Book
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/circulation.php?action=return
Authorization: Required

REQUEST Parameters:
  • circulation_id (integer, required)
    - Circulation record to return
    
  • condition (string, optional)
    - Allowed: 'good', 'fair', 'damaged'
    - Default: 'good'

RESPONSE Success (200):
{
    "success": true,
    "message": "Book returned successfully",
    "circulation": {
        "circulation_id": 100,
        "status": "returned",
        "returned_date": "2026-03-18",
        "fine_amount": 0
    }
}

RESPONSE Errors:
  404 Not Found:
  {
      "success": false,
      "error": "Circulation record not found"
  }

────────────────────────────────────────────────────────

ENDPOINT 3: Renew Book
────────────────────────────────────────────────────────
Method:     POST
Route:      /backend/api/circulation.php?action=renew
Authorization: Required

REQUEST Parameters:
  • circulation_id (integer, required)

RESPONSE Success (200):
{
    "success": true,
    "message": "Book renewed successfully",
    "new_due_date": "2026-04-03",
    "renewal_count": 1
}

RESPONSE Errors:
  400 Bad Request:
  {
      "success": false,
      "error": "Maximum renewal limit (3) exceeded"
  }

RENEWAL RULES:
  • Max renewals: 3 times
  • Extension: +14 days per renewal
  • Condition: Not overdue at time of renewal

────────────────────────────────────────────────────────

ENDPOINT 4: Get My Books
────────────────────────────────────────────────────────
Method:     GET
Route:      /backend/api/circulation.php?action=my_books
Authorization: Required

REQUEST Parameters:
  • status (string, optional)
    - Allowed: 'borrowed', 'returned', 'all'
    - Default: 'borrowed'

RESPONSE Success (200):
{
    "success": true,
    "books": [
        {
            "circulation_id": 100,
            "book_id": 1,
            "title": "The Great Gatsby",
            "author_name": "F. Scott Fitzgerald",
            "borrow_date": "2026-03-06",
            "due_date": "2026-03-20",
            "days_left": 14,
            "status": "borrowed",
            "renewal_count": 0,
            "is_overdue": false
        }
    ],
    "summary": {
        "total_borrowed": 3,
        "overdue_count": 0,
        "renewable_count": 2
    }
}

────────────────────────────────────────────────────────
```

---

## Part 2: Data Models & Schemas

### 2.1 User Data Model

```
TABLE: users
────────────────────────────────────────────────────────

Column Name       │ Type          │ Constraints
──────────────────┼───────────────┼──────────────────────
user_id           │ INT(11)       │ PK, AUTO_INCREMENT
email             │ VARCHAR(255)  │ UQ, NOT NULL
password_hash     │ VARCHAR(255)  │ NOT NULL
first_name        │ VARCHAR(100)  │ NOT NULL
last_name         │ VARCHAR(100)  │ NOT NULL
phone             │ VARCHAR(20)   │ UNIQUE, NULLABLE
address           │ TEXT          │ NULLABLE
role              │ ENUM(...)     │ DEFAULT 'student'
created_at        │ TIMESTAMP     │ DEFAULT NOW()
updated_at        │ TIMESTAMP     │ DEFAULT NOW()
last_login        │ DATETIME      │ NULLABLE

INDEXES:
  • PRIMARY KEY (user_id)
  • UNIQUE (email)
  • INDEX (role)
  • INDEX (created_at)

RELATIONSHIPS:
  • circulation (1:many)
  • fines (1:many)
  • notifications (1:many)
  • payments (1:many)

ROLE VALUES:
  • student: Can borrow books, pay fines
  • faculty: Can borrow books, can reserve books
  • staff: Can manage library operations
  • librarian: Can add/edit books, manage circulation
  • admin: Full system access
```

### 2.2 Book Data Model

```
TABLE: books
────────────────────────────────────────────────────────

Column Name       │ Type          │ Constraints
──────────────────┼───────────────┼──────────────────────
book_id           │ INT(11)       │ PK, AUTO_INCREMENT
title             │ VARCHAR(255)  │ NOT NULL
author_id         │ INT(11)       │ FK(authors)
publisher_id      │ INT(11)       │ FK(publishers)
isbn              │ VARCHAR(20)   │ UNIQUE, NOT NULL
publication_year  │ INT(4)        │ NOT NULL
description       │ TEXT          │ NULLABLE
total_copies      │ INT(11)       │ NOT NULL, DEFAULT 1
available_copies  │ INT(11)       │ NOT NULL, DEFAULT 1
cover_image       │ VARCHAR(255)  │ NULLABLE
average_rating    │ DECIMAL(3,2)  │ DEFAULT 0.00
total_reviews     │ INT(11)       │ DEFAULT 0
created_at        │ TIMESTAMP     │ DEFAULT NOW()
updated_at        │ TIMESTAMP     │ DEFAULT NOW()

INDEXES:
  • PRIMARY KEY (book_id)
  • UNIQUE (isbn)
  • INDEX (author_id)
  • INDEX (publisher_id)
  • FULLTEXT (title)
  • INDEX (publication_year)

RELATIONSHIPS:
  • authors (many:1)
  • publishers (many:1)
  • categories (many:many)
  • circulation (1:many)
  • reviews (1:many)
  • fines (1:many)
```

### 2.3 Circulation Data Model

```
TABLE: circulation
────────────────────────────────────────────────────────

Column Name             │ Type        │ Constraints
────────────────────────┼─────────────┼──────────────────
circulation_id          │ INT(11)     │ PK, AUTO_INCREMENT
user_id                 │ INT(11)     │ FK(users), NOT NULL
book_id                 │ INT(11)     │ FK(books), NOT NULL
borrow_date             │ DATE        │ NOT NULL
due_date                │ DATE        │ NOT NULL
returned_date           │ DATE        │ NULLABLE
condition               │ ENUM(...)   │ DEFAULT 'good'
status                  │ ENUM(...)   │ DEFAULT 'borrowed'
renewal_count           │ INT(1)      │ DEFAULT 0
last_overdue_notif      │ DATETIME    │ NULLABLE
created_at              │ TIMESTAMP   │ DEFAULT NOW()
updated_at              │ TIMESTAMP   │ DEFAULT NOW()

INDEXES:
  • PRIMARY KEY (circulation_id)
  • FOREIGN KEY (user_id, book_id)
  • INDEX (status)
  • INDEX (due_date)
  • INDEX (user_id, status)

RELATIONSHIPS:
  • users (many:1)
  • books (many:1)
  • fines (1:1)

STATUS VALUES:
  • borrowed: Book currently with user
  • returned: Book returned to library
  • overdue: Book not returned by due date
  • damaged: Book returned in damaged condition
  • lost: Book not recovered

CONDITION VALUES:
  • good: No damage
  • fair: Minor wear and tear
  • damaged: Requires repair
  • lost: Book missing
```

### 2.4 Fine Data Model

```
TABLE: fines
────────────────────────────────────────────────────────

Column Name       │ Type          │ Constraints
──────────────────┼───────────────┼──────────────────────
fine_id           │ INT(11)       │ PK, AUTO_INCREMENT
user_id           │ INT(11)       │ FK(users), NOT NULL
book_id           │ INT(11)       │ FK(books), NOT NULL
circulation_id    │ INT(11)       │ FK(circulation)
amount            │ DECIMAL(8,2)  │ NOT NULL, DEFAULT 0
status            │ ENUM(...)     │ DEFAULT 'unpaid'
reason            │ VARCHAR(100)  │ DEFAULT 'overdue'
created_at        │ DATE          │ NOT NULL
paid_date         │ DATE          │ NULLABLE
due_date_original │ DATE          │ NOT NULL
waived_by         │ INT(11)       │ FK(users), NULLABLE
waived_reason     │ TEXT          │ NULLABLE

INDEXES:
  • PRIMARY KEY (fine_id)
  • FOREIGN KEY (user_id, book_id, circulation_id)
  • INDEX (status)
  • INDEX (created_at)
  • INDEX (user_id, status)

STATUS VALUES:
  • unpaid: Fine not yet paid
  • paid: Fine payment completed
  • partial: Partial payment made
  • waived: Fine waived by admin
  • forgiven: Fine forgiven

CALCULATION:
  Fine Amount = (Days Overdue) × Rs 10 (max Rs 300)
```

---

## Part 3: Error Codes & Status Messages

### 3.1 HTTP Status Codes

```
SUCCESS RESPONSES
─────────────────────────────────────────────────────

200 OK
  Description: Request successful, response contains result
  Example: Login successful, book list retrieved
  
201 Created
  Description: Resource created successfully
  Example: User registered, book added
  
204 No Content
  Description: Request successful, no response body
  Example: Profile updated, item deleted

REDIRECT RESPONSES
─────────────────────────────────────────────────────

301 Moved Permanently
  Description: Resource moved to new location permanently
  
302 Found
  Description: Resource temporarily at different location
  
304 Not Modified
  Description: Client resource is up to date

CLIENT ERROR RESPONSES
─────────────────────────────────────────────────────

400 Bad Request
  Description: Invalid request format/parameters
  Cause: Validation error, missing required field
  Action: Fix request and retry
  Example:
  {
      "success": false,
      "errors": {
          "email": "Invalid email format",
          "password": "Too short"
      }
  }

401 Unauthorized
  Description: Authentication required or failed
  Cause: Missing/invalid credentials
  Action: Login and provide valid token
  
403 Forbidden
  Description: Access denied (user lacks permission)
  Cause: Insufficient role/privileges
  Action: Request admin access or use appropriate role
  
404 Not Found
  Description: Requested resource not found
  Cause: Invalid ID or deleted resource
  Action: Verify resource ID and retry
  
409 Conflict
  Description: Request conflicts with current state
  Cause: Duplicate entry, constraint violation
  Action: Resolve conflict and retry
  
422 Unprocessable Entity
  Description: Request format correct but logic invalid
  Cause: Business rule violation
  Example: Borrowing limit exceeded
  
429 Too Many Requests
  Description: Rate limit exceeded
  Cause: Too many requests in time window
  Action: Wait before retrying

SERVER ERROR RESPONSES
─────────────────────────────────────────────────────

500 Internal Server Error
  Description: Server error occurred
  Cause: Unhandled exception, database error
  Action: Check logs, retry or contact support
  
503 Service Unavailable
  Description: Server temporarily unavailable
  Cause: Maintenance, overload
  Action: Retry after waiting
```

### 3.2 Application-Specific Error Codes

```
ERROR CODE STRUCTURE
───────────────────────────────────────────────────────

Format: {MODULE}{SEVERITY}{NUMBER}

MODULE PREFIXES:
  • AUTH = Authentication errors (100-199)
  • BOOK = Book management errors (200-299)
  • CIRC = Circulation errors (300-399)
  • FINE = Fine-related errors (400-499)
  • USR  = User-related errors (500-599)
  • PAY  = Payment errors (600-699)
  • SYS  = System errors (900-999)

SEVERITY LEVELS:
  • 0xx = Warning (operation partially failed)
  • 1xx = Error (operation failed)
  • 2xx = Critical (system impact)

AUTHENTICATION ERROR CODES
───────────────────────────────────────────────────────

AUTH_101: Invalid Credentials
  Message: "Invalid email or password"
  HTTP: 401
  Action: Verify email/password and retry

AUTH_102: Account Locked
  Message: "Too many failed login attempts"
  HTTP: 429
  Action: Wait 15 minutes or contact support

AUTH_103: Email Not Verified
  Message: "Please verify your email address"
  HTTP: 403
  Action: Check email and click verification link

AUTH_104: Account Suspended
  Message: "Your account has been suspended"
  HTTP: 403
  Action: Contact library administrator

AUTH_105: Session Expired
  Message: "Your session has expired"
  HTTP: 401
  Action: Login again

BOOK MANAGEMENT ERROR CODES
───────────────────────────────────────────────────────

BOOK_201: ISBN Already Exists
  Message: "A book with this ISBN already exists"
  HTTP: 409
  Action: Use different ISBN or update existing book

BOOK_202: Invalid ISBN Format
  Message: "ISBN must be 13 digits or valid ISBN-10"
  HTTP: 422
  Action: Verify ISBN format

BOOK_203: Book Not Found
  Message: "Book with ID {id} not found"
  HTTP: 404
  Action: Verify book ID

BOOK_204: Author Not Found
  Message: "Author with ID {id} does not exist"
  HTTP: 404
  Action: Select valid author

BOOK_205: Publisher Not Found
  Message: "Publisher with ID {id} does not exist"
  HTTP: 404
  Action: Select valid publisher

CIRCULATION ERROR CODES
───────────────────────────────────────────────────────

CIRC_301: Book Not Available
  Message: "No copies available. Available: 0/5"
  HTTP: 400
  Action: Check back later or reserve

CIRC_302: Borrow Limit Exceeded
  Message: "You already have 5 borrowed books (max limit)"
  HTTP: 409
  Action: Return a book first

CIRC_303: Outstanding Fines
  Message: "Outstanding fine: Rs 150. Clear fines to borrow"
  HTTP: 403
  Action: Pay outstanding fines

CIRC_304: Overdue Books
  Message: "You have 1 overdue book"
  HTTP: 403
  Action: Return overdue books

CIRC_305: Renewal Limit Exceeded
  Message: "Book renewed 3 times. Cannot renew further"
  HTTP: 400
  Action: Return book or contact librarian

FINE MANAGEMENT ERROR CODES
───────────────────────────────────────────────────────

FINE_401: Payment Failed
  Message: "Payment processing failed. Please try again"
  HTTP: 400
  Action: Retry payment or contact support

FINE_402: Invalid Amount
  Message: "Amount must be greater than 0"
  HTTP: 422
  Action: Enter valid amount

FINE_403: Insufficient Fine Balance
  Message: "Payment amount exceeds fine balance"
  HTTP: 422
  Action: Enter amount <= Rs {fine_amount}
```

---

## Part 4: Query Patterns & Examples

### 4.1 Complex Query Examples

```sql
-- QUERY 1: Get user's current books with overdue status
────────────────────────────────────────────────────────

SELECT 
    c.circulation_id,
    c.user_id,
    b.book_id,
    b.title,
    a.author_name,
    c.borrow_date,
    c.due_date,
    DATEDIFF(CURDATE(), c.due_date) as days_overdue,
    CASE 
        WHEN c.due_date < CURDATE() THEN 'overdue'
        WHEN DATEDIFF(c.due_date, CURDATE()) <= 3 THEN 'due_soon'
        ELSE 'on_track'
    END as status,
    f.amount as fine_amount
FROM circulation c
JOIN books b ON c.book_id = b.book_id
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN fines f ON c.circulation_id = f.circulation_id
WHERE c.user_id = ? 
    AND c.status = 'borrowed'
    AND c.returned_date IS NULL
ORDER BY c.due_date ASC;

INDEXES USED:
  • circulation(user_id, status)
  • circulation(returned_date)
  • books(book_id)
  • authors(author_id)
  • fines(circulation_id)

────────────────────────────────────────────────────────

-- QUERY 2: Get popular books (borrowed count in last month)
────────────────────────────────────────────────────────

SELECT 
    b.book_id,
    b.title,
    a.author_name,
    COUNT(c.circulation_id) as borrow_count,
    RANK() OVER (ORDER BY COUNT(c.circulation_id) DESC) as rank,
    b.average_rating,
    b.available_copies
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN circulation c ON b.book_id = c.book_id 
    AND c.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY b.book_id
HAVING borrow_count > 0
ORDER BY borrow_count DESC
LIMIT 20;

WINDOW FUNCTION: RANK() for popularity ranking
INDEXES USED:
  • circulation(borrow_date, book_id)
  • books(book_id)

────────────────────────────────────────────────────────

-- QUERY 3: Calculate statistics for dashboard
────────────────────────────────────────────────────────

SELECT 
    (SELECT COUNT(DISTINCT book_id) FROM circulation WHERE status = 'borrowed') 
        as currently_borrowed,
    (SELECT COUNT(DISTINCT book_id) FROM circulation 
     WHERE DATEDIFF(CURDATE(), due_date) > 0 AND status = 'borrowed')
        as overdue_books,
    (SELECT COUNT(DISTINCT user_id) FROM fines WHERE status = 'unpaid')
        as users_with_fines,
    (SELECT SUM(amount) FROM fines WHERE status = 'unpaid')
        as total_unpaid_fines,
    (SELECT AVG(available_copies/total_copies*100) FROM books)
        as avg_availability_percent;

OPTIMIZATION: Each subquery has dedicated indexes

────────────────────────────────────────────────────────

-- QUERY 4: Advanced search with relevance scoring
────────────────────────────────────────────────────────

SELECT 
    b.book_id,
    b.title,
    a.author_name,
    MATCH(b.title) AGAINST(? IN BOOLEAN MODE) as title_score,
    MATCH(a.author_name) AGAINST(? IN BOOLEAN MODE) as author_score,
    (MATCH(b.title) AGAINST(? IN BOOLEAN MODE) * 2 + 
     MATCH(a.author_name) AGAINST(? IN BOOLEAN MODE)) as relevance,
    b.average_rating,
    b.available_copies
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
WHERE (MATCH(b.title) AGAINST(? IN BOOLEAN MODE) OR
       MATCH(a.author_name) AGAINST(? IN BOOLEAN MODE))
ORDER BY relevance DESC, b.average_rating DESC
LIMIT 20;

FULLTEXT INDEXES:
  • books(title)
  • authors(author_name)

────────────────────────────────────────────────────────

-- QUERY 5: Generate report: Books with no circulation
────────────────────────────────────────────────────────

SELECT 
    b.book_id,
    b.title,
    a.author_name,
    b.publication_year,
    b.total_copies,
    b.average_rating,
    DATEDIFF(CURDATE(), b.created_at) as days_in_library,
    (SELECT COUNT(*) FROM circulation WHERE book_id = b.book_id) 
        as total_borrows
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN circulation c ON b.book_id = c.book_id
WHERE c.circulation_id IS NULL
GROUP BY b.book_id
HAVING DATEDIFF(CURDATE(), b.created_at) > 90
ORDER BY days_in_library DESC;

RESULT: Books in library > 90 days with zero circulation

────────────────────────────────────────────────────────
```

### 4.2 Performance-Optimized Queries

```sql
-- Denormalized view for fast access
────────────────────────────────────────────────────────

CREATE VIEW book_details AS
SELECT 
    b.book_id,
    b.title,
    b.isbn,
    a.author_id,
    a.author_name,
    p.publisher_id,
    p.publisher_name,
    b.publication_year,
    b.total_copies,
    b.available_copies,
    b.average_rating,
    GROUP_CONCAT(c.category_name) as categories,
    b.created_at,
    b.updated_at
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
LEFT JOIN book_categories bc ON b.book_id = bc.book_id
LEFT JOIN categories c ON bc.category_id = c.category_id
GROUP BY b.book_id;

BENEFITS:
  • Single query for complete book info
  • Pre-joined denormalized data
  • Reduced query complexity
  • Better performance for read-heavy operations

────────────────────────────────────────────────────────

-- Cached statistics table (updated periodically)
────────────────────────────────────────────────────────

CREATE TABLE library_statistics (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    stat_date DATE NOT NULL UNIQUE,
    total_books INT,
    available_books INT,
    borrowed_books INT,
    overdue_count INT,
    total_fines_unpaid DECIMAL(10,2),
    total_users INT,
    active_users INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Populate statistics (run daily via cron)
INSERT INTO library_statistics 
SELECT 
    NULL,
    CURDATE(),
    (SELECT COUNT(*) FROM books),
    (SELECT SUM(available_copies) FROM books),
    (SELECT COUNT(*) FROM circulation WHERE status = 'borrowed'),
    (SELECT COUNT(*) FROM circulation 
     WHERE status = 'borrowed' AND due_date < CURDATE()),
    (SELECT SUM(amount) FROM fines WHERE status = 'unpaid'),
    (SELECT COUNT(*) FROM users),
    (SELECT COUNT(DISTINCT user_id) FROM circulation 
     WHERE MONTH(borrow_date) = MONTH(CURDATE())),
    NOW()
ON DUPLICATE KEY UPDATE 
    total_books = VALUES(total_books),
    available_books = VALUES(available_books),
    ...
    updated_at = NOW();

BENEFITS:
  • O(1) access to statistics
  • No complex aggregations at query time
  • Can serve from cache
  • Reduces database load

────────────────────────────────────────────────────────
```

---

## Part 5: Database Indexing Strategy

```
PRIMARY INDEXES (Mandatory)
────────────────────────────────────────────────────────

users:
  • PRIMARY KEY (user_id)
  • UNIQUE (email)
  • INDEX (role) - For role-based queries
  • INDEX (created_at) - For date range queries

books:
  • PRIMARY KEY (book_id)
  • UNIQUE (isbn) - For ISBN lookups
  • INDEX (author_id) - For author filtering
  • INDEX (publisher_id) - For publisher filtering
  • FULLTEXT (title) - For full-text search
  • INDEX (publication_year) - For year filtering

circulation:
  • PRIMARY KEY (circulation_id)
  • INDEX (user_id) - Find user's books
  • INDEX (book_id) - Find book's borrowers
  • INDEX (status) - Filter by status
  • INDEX (due_date) - Find overdue books
  • COMPOSITE (user_id, status) - Common filter
  • COMPOSITE (user_id, returned_date) - Active borrows

fines:
  • PRIMARY KEY (fine_id)
  • INDEX (user_id) - Find user's fines
  • INDEX (status) - Unpaid fines query
  • COMPOSITE (user_id, status) - User's unpaid fines

COMPOSITE INDEX USAGE:
────────────────────────────────────────────────────────

-- Uses index (user_id, status)
SELECT * FROM circulation 
WHERE user_id = 1 AND status = 'borrowed';

-- Uses index (user_id, status, returned_date)
SELECT * FROM circulation 
WHERE user_id = 1 
  AND status = 'borrowed' 
  AND returned_date IS NULL;

INDEX DESIGN PRINCIPLES:
────────────────────────────────────────────────────────

1. SELECTIVITY: High selectivity columns first
   ✓ status > book_id (more selective)

2. QUERY PATTERNS: Follow most frequent queries
   ✓ (user_id, status) for active borrows
   ✓ (due_date, status) for overdue books

3. COVERING INDEXES: Include non-key columns
   -- Regular query
   SELECT user_id, book_id FROM circulation 
   WHERE status = 'borrowed';
   
   -- Covering index (avoiding table access)
   CREATE INDEX idx_circ_status 
   ON circulation(status) 
   INCLUDE (user_id, book_id);

4. AVOID OVER-INDEXING:
   ✗ Too many indexes = slower writes
   ✓ 5-7 indexes per table is optimal
   ✓ Remove unused indexes monthly
```

---

## Part 6: API Rate Limiting

```
RATE LIMITING CONFIGURATION
────────────────────────────────────────────────────────

DEFAULT LIMITS:
  • Public endpoints: 100 requests/hour per IP
  • Authenticated: 1000 requests/hour per user
  • Search: 30 requests/minute per user
  • Payment: 10 requests/minute per user

IMPLEMENTATION:
────────────────────────────────────────────────────────

Use Redis for rate limiting:

$key = "rate_limit:{$endpoint}:{$ip_or_user_id}";
$limit = 100;
$window = 3600; // 1 hour

if (Redis::get($key) >= $limit) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many requests']));
}

Redis::incr($key);
Redis::expire($key, $window);

HEADERS RETURNED:
────────────────────────────────────────────────────────

X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 998
X-RateLimit-Reset: 1646574000

BACKOFF STRATEGY:
────────────────────────────────────────────────────────

Retry-After: 60  (wait 60 seconds)

Exponential backoff:
  • 1st retry: 1 second
  • 2nd retry: 2 seconds
  • 3rd retry: 4 seconds
  • 4th retry: 8 seconds
```

**Next: Create Implementation Details Documentation**

