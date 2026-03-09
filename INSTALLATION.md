# Installation Guide - Bagmati Library Management System

## System Requirements

- **Server**: Apache 2.4+ or Nginx 1.10+
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+

## Step-by-Step Installation

### Phase 1: Server Setup

#### 1.1 Install Required Software

**On Linux (Ubuntu/Debian):**
```bash
# Update system
sudo apt-get update
sudo apt-get upgrade -y

# Install Apache
sudo apt-get install -y apache2

# Install PHP and extensions
sudo apt-get install -y php php-mysql php-json php-mbstring php-curl

# Install MySQL
sudo apt-get install -y mysql-server
```

**On macOS:**
```bash
# Using Homebrew
brew install apache2 php mysql
```

**On Windows:**
- Download XAMPP or WAMP from official sites
- Install following wizard instructions

#### 1.2 Enable Apache Modules
```bash
sudo a2enmod rewrite
sudo a2enmod mod_ssl
sudo systemctl restart apache2
```

#### 1.3 Create Virtual Host (Optional)
Edit `/etc/apache2/sites-available/library.conf`:
```apache
<VirtualHost *:80>
    ServerName library.local
    ServerAdmin admin@library.local
    DocumentRoot /var/www/library_system/public
    
    <Directory /var/www/library_system/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/library_error.log
    CustomLog ${APACHE_LOG_DIR}/library_access.log combined
</VirtualHost>
```

Enable it:
```bash
sudo a2ensite library.conf
sudo systemctl restart apache2
```

Add to `/etc/hosts`:
```
127.0.0.1 library.local
```

### Phase 2: Database Setup

#### 2.1 Create Database
```bash
# Connect to MySQL
mysql -u root -p

# In MySQL console:
CREATE DATABASE library_management_system;
CREATE USER 'lib_user'@'localhost' IDENTIFIED BY 'secure_password_123';
GRANT ALL PRIVILEGES ON library_management_system.* TO 'lib_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 2.2 Import Schema
```bash
mysql -u lib_user -p library_management_system < /path/to/library_system/index.sql
```

#### 2.3 Verify Installation
```bash
mysql -u lib_user -p library_management_system
SHOW TABLES;
DESCRIBE users;
EXIT;
```

### Phase 3: Application Deployment

#### 3.1 Copy Files
```bash
# Copy application files to web root
sudo cp -r /path/to/library_system /var/www/html/
sudo chown -R www-data:www-data /var/www/html/library_system/
sudo chmod -R 755 /var/www/html/library_system/
sudo chmod -R 775 /var/www/html/library_system/backend/
```

#### 3.2 Update Configuration

Edit `backend/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'lib_user');
define('DB_PASS', 'secure_password_123');
define('DB_NAME', 'library_management_system');
```

Edit `public/js/api.js`:
```javascript
const API_URL = 'http://localhost/library_system/backend/api';
```

#### 3.3 Create Directories
```bash
mkdir -p /var/www/html/library_system/uploads
chmod 775 /var/www/html/library_system/uploads
```

#### 3.4 Set Permissions
```bash
# Make backend writable
sudo chmod -R 755 /var/www/html/library_system/backend/
sudo chmod 644 /var/www/html/library_system/backend/config/database.php

# Make uploads writable
sudo chmod 777 /var/www/html/library_system/uploads
```

### Phase 4: Verification

#### 4.1 Test Database Connection
Create a test file `backend/test_connection.php`:
```php
<?php
$conn = require_once 'config/database.php';
if ($conn) {
    echo "Database connection successful!";
    // Count tables
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'library_management_system'");
    $row = $result->fetch_assoc();
    echo "Found " . $row['count'] . " tables";
} else {
    echo "Connection failed";
}
?>
```

Access: `http://localhost/library_system/backend/test_connection.php`

#### 4.2 Test API
```bash
curl http://localhost/library_system/backend/api/books.php?action=get_categories
```

#### 4.3 Test Application
- Open: `http://localhost/library_system/public/`
- Should see login page

### Phase 5: Initial Configuration

#### 5.1 Create Initial Users
```sql
-- Create Admin User
INSERT INTO users (username, email, password_hash, full_name, phone, address, role, is_active) 
VALUES ('admin', 'admin@library.local', '$2y$12$G9Yw4JlD3cHO88BLJYy8nuKxOqHsrNz3pQp1K.xvU7BnP8H5W/eem', 'System Administrator', '9841000000', 'Library', 'admin', 1);

-- Create Librarian User
INSERT INTO users (username, email, password_hash, full_name, phone, address, role, is_active) 
VALUES ('librarian', 'librarian@library.local', '$2y$12$G9Yw4JlD3cHO88BLJYy8nuKxOqHsrNz3pQp1K.xvU7BnP8H5W/eem', 'Head Librarian', '9841000001', 'Library', 'librarian', 1);
```

Note: Password hash above is for 'password123'. Generate your own:
```php
echo password_hash('your_password', PASSWORD_BCRYPT);
```

#### 5.2 Add Sample Books
```sql
-- Add some sample books
INSERT INTO books (title, author, genre, isbn, publication_date, publisher, total_copies, available_copies, status) 
VALUES 
('The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', '978-0743273565', '1925-04-10', 'Scribner', 2, 2, 'active'),
('To Kill a Mockingbird', 'Harper Lee', 'Fiction', '978-0061120084', '1960-07-11', 'J.B. Lippincott', 3, 3, 'active'),
('1984', 'George Orwell', 'Fiction', '978-0451524935', '1949-06-08', 'Secker and Warburg', 2, 2, 'active'),
('Pride and Prejudice', 'Jane Austen', 'Fiction', '978-0141439518', '1813-01-28', 'T. Egerton', 3, 3, 'active'),
('The Hobbit', 'J.R.R. Tolkien', 'Fantasy', '978-0547928227', '1937-09-21', 'Allen and Unwin', 2, 2, 'active');

-- Create inventory for sample books
INSERT INTO book_inventory (book_id, copy_number, status) 
VALUES 
(1, 1, 'available'), (1, 2, 'available'),
(2, 1, 'available'), (2, 2, 'available'), (2, 3, 'available'),
(3, 1, 'available'), (3, 2, 'available'),
(4, 1, 'available'), (4, 2, 'available'), (4, 3, 'available'),
(5, 1, 'available'), (5, 2, 'available');
```

### Phase 6: Security Hardening

#### 6.1 Update .htaccess
Create `/var/www/html/library_system/public/.htaccess`:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Prevent direct access to backend
    RewriteRule ^backend/ - [F]
    
    # Add security headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

#### 6.2 Disable Directory Listing
```apache
<Directory /var/www/html/library_system>
    Options -Indexes
</Directory>
```

#### 6.3 Protect Sensitive Files
```apache
<FilesMatch "\.(env|config|sql|log|sh|xml|json)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

#### 6.4 SSL Configuration (Production)
```bash
# Use Let's Encrypt for free SSL
sudo apt-get install certbot python3-certbot-apache
sudo certbot certonly --apache -d your-domain.com
```

### Phase 7: Backup & Maintenance

#### 7.1 Database Backup
```bash
# Daily backup script
mysqldump -u lib_user -p library_management_system > /backup/library_$(date +%Y%m%d).sql

# With compression
mysqldump -u lib_user -p library_management_system | gzip > /backup/library_$(date +%Y%m%d).sql.gz
```

Create cron job:
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * mysqldump -u lib_user -psecure_password_123 library_management_system | gzip > /backup/library_$(date +\%Y\%m\%d).sql.gz
```

#### 7.2 Log Rotation
Create `/etc/logrotate.d/library`:
```
/var/www/html/library_system/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Phase 8: Testing

#### 8.1 Functional Testing Checklist
- [ ] Login with admin account
- [ ] Register new student account
- [ ] Add a book from admin panel
- [ ] Search for book
- [ ] Borrow a book
- [ ] View my books
- [ ] Renew a book
- [ ] Return a book
- [ ] View fines
- [ ] Pay fine
- [ ] View reports
- [ ] Export data

#### 8.2 Browser Compatibility
Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

#### 8.3 Performance Testing
```bash
# Using Apache Bench
ab -n 100 -c 10 http://localhost/library_system/public/

# Using curl for API
time curl http://localhost/library_system/backend/api/books.php?action=list_books
```

## Troubleshooting

### Database Connection Issues
```bash
# Test connection
mysql -h localhost -u lib_user -p library_management_system -e "SELECT 1;"

# Check MySQL service
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql
```

### PHP Module Missing
```bash
# Check installed modules
php -m

# Install missing module
sudo apt-get install php-module-name
```

### Permission Denied
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/html/library_system/
sudo chmod -R 755 /var/www/html/library_system/
sudo chmod 775 /var/www/html/library_system/backend/
```

### SSL Certificate Issues
```bash
# Renew certificate
sudo certbot renew

# Force renewal
sudo certbot renew --force-renewal
```

## Post-Installation

1. **Change default passwords** in database
2. **Configure email settings** for notifications
3. **Set up automated backups**
4. **Enable monitoring and logging**
5. **Train staff on system usage**
6. **Set up regular maintenance schedule**

## Next Steps

1. Review [README.md](README.md) for complete documentation
2. Check [QUICKSTART.md](QUICKSTART.md) for quick usage guide
3. Configure library settings as needed
4. Migrate existing book inventory
5. Start onboarding users

---

**Installation Complete!** Your library management system is now ready for use.

For support, refer to the documentation or contact the development team.
