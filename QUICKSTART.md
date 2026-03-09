# Quick Start Guide - Bagmati Library Management System

## 5-Minute Setup

### 1. Database Creation
```bash
# Connect to MySQL
mysql -u root -p

# Run in MySQL console
CREATE DATABASE library_management_system;
USE library_management_system;
SOURCE /path/to/library_system/index.sql;
```

### 2. Update Configuration
Edit `backend/config/database.php`:
```php
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Access Application
Open in browser:
```
http://localhost/library_system/public/
```

## Test Login Credentials

After running the SQL script, you can create test users through the registration page, or insert directly:

```sql
-- Insert test student
INSERT INTO users (username, email, password_hash, full_name, phone, address, role, is_active) 
VALUES ('student1', 'student@school.com', '$2y$12$...', 'Student Name', '9841234567', 'Address', 'student', 1);

-- Insert test librarian
INSERT INTO users (username, email, password_hash, full_name, phone, address, role, is_active) 
VALUES ('librarian1', 'librarian@school.com', '$2y$12$...', 'Librarian Name', '9841234567', 'Address', 'librarian', 1);

-- Insert test admin
INSERT INTO users (username, email, password_hash, full_name, phone, address, role, is_active) 
VALUES ('admin1', 'admin@school.com', '$2y$12$...', 'Admin Name', '9841234567', 'Address', 'admin', 1);
```

For password hashing, use PHP:
```php
echo password_hash('password123', PASSWORD_BCRYPT);
```

## Adding Sample Books

```sql
INSERT INTO books (title, author, genre, isbn, publication_date, publisher, total_copies, available_copies) 
VALUES 
('Clean Code', 'Robert Martin', 'Technology', '978-0132350884', '2008-08-01', 'Prentice Hall', 3, 3),
('Atomic Habits', 'James Clear', 'Self-Help', '978-0735211292', '2018-10-16', 'Avery', 2, 2),
('The Pragmatic Programmer', 'Andrew Hunt', 'Technology', '978-0201616224', '1999-10-20', 'Addison-Wesley', 2, 2),
('Deep Learning', 'Ian Goodfellow', 'Science', '978-0262035613', '2016-11-18', 'MIT Press', 1, 1),
('Python Crash Course', 'Eric Matthes', 'Technology', '978-1593279288', '2015-12-03', 'No Starch Press', 2, 2);

-- Create inventory for these books
INSERT INTO book_inventory (book_id, copy_number, status) 
VALUES 
(1, 1, 'available'), (1, 2, 'available'), (1, 3, 'available'),
(2, 1, 'available'), (2, 2, 'available'),
(3, 1, 'available'), (3, 2, 'available'),
(4, 1, 'available'),
(5, 1, 'available'), (5, 2, 'available');
```

## Key Features Overview

### 👤 User Registration & Login
1. Click "Register here" on login page
2. Fill in required fields
3. Account is automatically created as 'student'

### 📚 Browse & Search Books
1. Go to "Books" tab after login
2. Use search box to find books
3. Filter by genre
4. View availability status

### 📖 Borrow a Book
1. Click "Borrow" on any available book
2. Automatic due date set (default: 14 days)
3. Notification sent to user

### 🔄 Renew a Book
1. Go to "My Books"
2. Click "Renew" on borrowed book
3. New due date calculated
4. Limited to 2 renewals per book

### 📤 Return a Book
1. Go to "My Books"
2. Click "Return" button
3. If overdue, fine is automatically calculated
4. Book returned to available status

### 💰 Pay Fines
1. Go to "My Fines"
2. View pending fines
3. Click "Pay" for individual fine or "Pay All Fines"
4. Mark as paid

### 📊 Admin Dashboard
1. Login as librarian/admin
2. Click "Admin" in navigation
3. Access different tabs:
   - **Overview**: System statistics
   - **Books Management**: Add/edit/delete books
   - **Users Management**: View and manage users
   - **Circulation**: View all borrowed books
   - **Reports**: Generate various reports

## Customization

### Change Max Borrow Days
```sql
UPDATE library_settings SET setting_value = '21' WHERE setting_key = 'max_borrow_days';
```

### Change Fine Amount Per Day
```sql
UPDATE library_settings SET setting_value = '15' WHERE setting_key = 'fine_per_day';
```

### Change Max Books Allowed
```sql
UPDATE library_settings SET setting_value = '8' WHERE setting_key = 'max_books_at_once';
```

## Common Tasks

### Reset User Password
```sql
-- Generate new hash in PHP first, then:
UPDATE users SET password_hash = 'new_hash_here' WHERE user_id = 5;
```

### Mark Book as Damaged
```sql
UPDATE book_inventory SET status = 'damaged', condition = 'damaged' WHERE inventory_id = 10;
```

### View All Overdue Books
```sql
SELECT c.*, b.title, u.username 
FROM circulation c
JOIN books b ON c.book_id = b.book_id
JOIN users u ON c.user_id = u.user_id
WHERE c.due_date < CURDATE() AND c.status IN ('borrowed', 'renewed');
```

### Calculate Total Fines Outstanding
```sql
SELECT SUM(fine_amount) as total_fines 
FROM fines 
WHERE status = 'pending';
```

## API Testing with cURL

### Login
```bash
curl -X POST http://localhost/library_system/backend/api/auth.php?action=login \
  -d "username=student1&password=password123"
```

### List Books
```bash
curl http://localhost/library_system/backend/api/books.php?action=list_books&limit=10
```

### Search Books
```bash
curl "http://localhost/library_system/backend/api/books.php?action=search&q=python"
```

### Get User's Books
```bash
curl http://localhost/library_system/backend/api/circulation.php?action=my_books&user_id=1
```

### Get User's Fines
```bash
curl http://localhost/library_system/backend/api/fines.php?action=get_fines&user_id=1
```

## Permissions Matrix

| Feature | Student | Teacher | Librarian | Admin |
|---------|---------|---------|-----------|-------|
| Browse Books | ✅ | ✅ | ✅ | ✅ |
| Borrow Books | ✅ | ✅ | ✅ | ✅ |
| Renew Books | ✅ | ✅ | ✅ | ✅ |
| Return Books | ✅ | ✅ | ✅ | ✅ |
| Pay Fines | ✅ | ✅ | ✅ | ✅ |
| Add Books | ❌ | ❌ | ✅ | ✅ |
| Edit Books | ❌ | ❌ | ✅ | ✅ |
| Delete Books | ❌ | ❌ | ✅ | ✅ |
| View Reports | ❌ | ❌ | ✅ | ✅ |
| Manage Users | ❌ | ❌ | ❌ | ✅ |
| Waive Fines | ❌ | ❌ | ✅ | ✅ |
| View Audit Logs | ❌ | ❌ | ❌ | ✅ |

## Troubleshooting Checklist

- [ ] Database connection successful
- [ ] All PHP files have execute permissions (755)
- [ ] Backend directory accessible from web
- [ ] API_URL in js/api.js matches your setup
- [ ] MySQL service running
- [ ] PHP version 7.4 or higher
- [ ] Browser console has no JavaScript errors
- [ ] Database tables created successfully
- [ ] Sample data inserted

## Need Help?

1. Check `README.md` for detailed documentation
2. Review browser console for errors (F12)
3. Check PHP error logs in server logs
4. Verify database connectivity
5. Test API endpoints with cURL

---

**Ready to Go!** Your library management system is now set up and ready to use.
