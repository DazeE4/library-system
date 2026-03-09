-- Bagmati School Library Management System Database Schema
-- Created: March 2026
-- Enhanced with normalized tables for Authors, Publishers, and improved data structure

-- Authors Table
-- Stores author information to eliminate redundancy
CREATE TABLE authors (
    author_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    bio TEXT,
    birth_date DATE,
    nationality VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_author_name (last_name, first_name),
    FULLTEXT INDEX ft_author (first_name, last_name)
);

-- Publishers Table
-- Stores publisher details for better organization
CREATE TABLE publishers (
    publisher_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    address VARCHAR(255),
    city VARCHAR(50),
    country VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_publisher_name (name)
);

-- Users Table (Members and Staff)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    role ENUM('student', 'teacher', 'librarian', 'admin') DEFAULT 'student',
    is_active BOOLEAN DEFAULT TRUE,
    membership_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    max_books_allowed INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
);

-- Books Table
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author_id INT NOT NULL,
    publisher_id INT,
    genre VARCHAR(50),
    isbn VARCHAR(20) UNIQUE,
    publication_year INT,
    publication_date DATE,
    description TEXT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    status ENUM('active', 'inactive', 'damaged', 'lost') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(author_id) ON DELETE RESTRICT,
    FOREIGN KEY (publisher_id) REFERENCES publishers(publisher_id) ON DELETE SET NULL,
    INDEX idx_title (title),
    INDEX idx_author_id (author_id),
    INDEX idx_publisher_id (publisher_id),
    INDEX idx_genre (genre),
    INDEX idx_isbn (isbn),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_title_author (title)
);

-- Librarians Table
-- Stores librarian-specific information with reference to users table
CREATE TABLE librarians (
    librarian_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    employee_id VARCHAR(20) UNIQUE,
    department VARCHAR(100),
    hire_date DATE,
    qualifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_employee_id (employee_id)
);

-- Members Table (Alternative view of users for members only)
-- This view can be created with: CREATE VIEW members AS SELECT * FROM users WHERE role IN ('student', 'teacher');

-- Book Inventory - Track individual copies
CREATE TABLE book_inventory (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    copy_number INT NOT NULL,
    status ENUM('available', 'borrowed', 'damaged', 'lost', 'maintenance') DEFAULT 'available',
    condition VARCHAR(100),
    location VARCHAR(100),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    UNIQUE KEY unique_copy (book_id, copy_number),
    INDEX idx_status (status),
    INDEX idx_book_id (book_id)
);

-- Book Categories/Genres
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (category_name)
);

-- Book Categories Mapping
CREATE TABLE book_categories (
    book_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (book_id, category_id),
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

-- Circulation/Transactions - Track borrowing and returning
CREATE TABLE circulation (
    circulation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    inventory_id INT NOT NULL,
    transaction_id VARCHAR(50) UNIQUE, -- Alternative transaction identifier
    issue_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    return_date DATETIME,
    status ENUM('borrowed', 'returned', 'overdue', 'renewed') DEFAULT 'borrowed',
    renewal_count INT DEFAULT 0,
    renewal_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES book_inventory(inventory_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_book_id (book_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_issue_date (issue_date)
);

-- Fines and Penalties
CREATE TABLE fines (
    fine_id INT PRIMARY KEY AUTO_INCREMENT,
    circulation_id INT NOT NULL,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    fine_amount DECIMAL(10, 2) NOT NULL,
    fine_reason ENUM('overdue', 'damage', 'lost', 'other') DEFAULT 'overdue',
    days_overdue INT DEFAULT 0,
    status ENUM('pending', 'paid', 'waived', 'cancelled') DEFAULT 'pending',
    due_date DATETIME,
    paid_date DATETIME,
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (circulation_id) REFERENCES circulation(circulation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Notifications/Reminders
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type ENUM('overdue', 'due_soon', 'renewal_available', 'fine_pending', 'new_arrival', 'system') DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_notification_type (notification_type)
);

-- Library Settings/Configuration
CREATE TABLE library_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO library_settings (setting_key, setting_value, description) VALUES
('max_borrow_days', '14', 'Maximum days a member can keep a book'),
('max_renewal_count', '2', 'Maximum number of times a book can be renewed'),
('max_books_at_once', '5', 'Maximum number of books a member can borrow at once'),
('fine_per_day', '10', 'Fine amount in rupees per day for overdue books'),
('max_fine_amount', '100', 'Maximum fine amount that can be charged'),
('notification_days_before', '2', 'Days before due date to send reminder');

-- Audit Log
CREATE TABLE audit_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    action_type VARCHAR(100) NOT NULL,
    user_id INT,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- Usage Statistics
CREATE TABLE usage_statistics (
    stat_id INT PRIMARY KEY AUTO_INCREMENT,
    stat_date DATE NOT NULL,
    total_books_borrowed INT DEFAULT 0,
    total_books_returned INT DEFAULT 0,
    total_fines_collected DECIMAL(10, 2) DEFAULT 0,
    total_active_users INT DEFAULT 0,
    overdue_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (stat_date),
    INDEX idx_stat_date (stat_date)
);

-- Create indexes for better performance
CREATE INDEX idx_user_active ON users(is_active);
CREATE INDEX idx_book_status ON books(status);
CREATE INDEX idx_circulation_user_status ON circulation(user_id, status);
CREATE INDEX idx_fine_paid_status ON fines(status, created_at);

-- Sample Categories
INSERT INTO categories (category_name, description) VALUES
('Fiction', 'Fictional novels and stories'),
('Non-Fiction', 'Educational and informative books'),
('Science', 'Science and technology books'),
('History', 'History and biographies'),
('Children', 'Books for children'),
('Technology', 'Programming and tech books'),
('Self-Help', 'Personal development books'),
('Reference', 'Reference materials and dictionaries');

-- Sample Authors
INSERT INTO authors (first_name, last_name, nationality) VALUES
('Robert', 'Martin', 'American'),
('James', 'Clear', 'American'),
('Andrew', 'Hunt', 'American'),
('Ian', 'Goodfellow', 'American'),
('Eric', 'Matthes', 'American'),
('F. Scott', 'Fitzgerald', 'American'),
('Harper', 'Lee', 'American'),
('George', 'Orwell', 'British'),
('Jane', 'Austen', 'British'),
('J.R.R.', 'Tolkien', 'British');

-- Sample Publishers
INSERT INTO publishers (name, address, city, country, phone, email) VALUES
('Prentice Hall', '123 Main St', 'New York', 'USA', '+1-201-555-0123', 'info@prentice.com'),
('Avery', '456 Oak Ave', 'New York', 'USA', '+1-212-555-0456', 'info@avery.com'),
('Addison-Wesley', '789 Pine Rd', 'Boston', 'USA', '+1-617-555-0789', 'info@addisonwesley.com'),
('MIT Press', '321 Elm St', 'Cambridge', 'USA', '+1-617-555-0321', 'info@mitpress.org'),
('No Starch Press', '654 Maple Dr', 'San Francisco', 'USA', '+1-415-555-0654', 'info@nostarch.com'),
('Scribner', '987 Cedar Ln', 'New York', 'USA', '+1-212-555-0987', 'info@scribner.com'),
('J.B. Lippincott', '147 Birch Ct', 'Philadelphia', 'USA', '+1-215-555-0147', 'info@jblippincott.com'),
('Secker and Warburg', '258 Spruce Way', 'London', 'UK', '+44-20-555-0258', 'info@secker.co.uk'),
('T. Egerton', '369 Willow Pl', 'London', 'UK', '+44-20-555-0369', 'info@tegerton.co.uk'),
('Allen and Unwin', '741 Ash Blvd', 'London', 'UK', '+44-20-555-0741', 'info@allenunwin.co.uk');

-- Create View for Members
CREATE VIEW members AS 
SELECT user_id, first_name, last_name, email, phone, address, membership_date 
FROM users 
WHERE role IN ('student', 'teacher');

-- Create View for Staff
CREATE VIEW staff AS 
SELECT user_id, first_name, last_name, email, phone, role, created_at 
FROM users 
WHERE role IN ('librarian', 'admin');

-- Create View for Book Details with Author and Publisher Names
CREATE VIEW book_details AS
SELECT 
    b.book_id,
    b.title,
    CONCAT(a.first_name, ' ', a.last_name) AS author_name,
    a.author_id,
    p.name AS publisher_name,
    p.publisher_id,
    b.isbn,
    b.publication_year,
    b.genre,
    b.total_copies,
    b.available_copies,
    b.status
FROM books b
LEFT JOIN authors a ON b.author_id = a.author_id
LEFT JOIN publishers p ON b.publisher_id = p.publisher_id;

-- Create View for Transaction History
CREATE VIEW transaction_history AS
SELECT 
    c.circulation_id,
    c.transaction_id,
    CONCAT(u.first_name, ' ', u.last_name) AS member_name,
    b.title,
    CONCAT(a.first_name, ' ', a.last_name) AS author_name,
    c.issue_date,
    c.due_date,
    c.return_date,
    c.status,
    c.renewal_count
FROM circulation c
JOIN users u ON c.user_id = u.user_id
JOIN books b ON c.book_id = b.book_id
LEFT JOIN authors a ON b.author_id = a.author_id
ORDER BY c.issue_date DESC;
