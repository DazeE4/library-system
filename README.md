# Bagmati School Library Management System

A comprehensive web-based library management system designed for schools with features for books management, user management, circulation tracking, fine collection, and detailed analytics.

## ✨ What's New - Admin Dashboard 2024

**Complete Admin System Implementation** ✅
- 6-tab admin dashboard for library management
- Admin login with secure authentication
- Real-time borrowing tracking
- Automatic overdue detection with fine calculation (₹5/day)
- Complete fine payment management
- Monthly/statistical report generation
- Advanced user management and search
- Complete documentation and setup guides

## Features

### 🎨 Frontend Features (Public User Interface)
- Modern glass-morphism design with Bagmati background
- Advanced book search by title, author, ISBN
- Genre filtering
- Instagram/TikTok-style bookmark save button
- Fully responsive mobile design
- Wishlist/saved books management
- My books section for borrowed items

### 📚 Admin Dashboard Features
- **Overview Tab**: 6 key statistics (total books, available, active members, overdue, pending fines, borrowed)
- **Users & Members Tab**: Search and filter members, view borrowing count, track activity
- **Borrowing Records Tab**: Complete circulation history with status filtering
- **Overdue Books Tab**: Auto-detect overdue items with automatic fine calculation
- **Fines & Penalties Tab**: Payment status tracking (pending/paid/waived/cancelled)
- **Reports Tab**: Generate monthly, statistical, and member activity reports
- **Admin Authentication**: Secure login for admin users only
- **Search & Filter**: Advanced search and filtering across all data
- **Real-time Data**: Live updates from database
- **Auto-Calculations**: Automatic overdue and fine calculations

### 📚 Books Management
- Add, edit, and delete books
- Track book inventory and availability
- Categorize books into genres
- Full-text search by title, author, or ISBN
- Track individual book copies

### 👥 User Management
- Student and staff registration
- User profile management
- Role-based access control (Student, Teacher, Librarian, Admin)
- Account activation/deactivation
- User activity tracking

### 📖 Circulation Management
- **Borrowing**: Issue books to users with automatic due date calculation
- **Returns**: Process book returns and update inventory
- **Renewals**: Allow users to renew books up to a maximum limit
- **Due Date Management**: Configurable borrow duration

### 💰 Fine & Fee Management
- Automatic fine calculation for overdue books
- Configurable fine amount per day
- Multiple payment methods support
- Fine waiving capability for admins
- Maximum fine limits per book
- Payment tracking and history

### 🔔 Notifications System
- Overdue book notifications
- Due date reminders (configurable days before)
- Fine payment notifications
- System notifications dashboard

### 📊 Reports & Analytics
- **Usage Reports**: Borrowing trends, return rates, fines collected
- **Popular Books**: Most borrowed books with time filters
- **Inventory Reports**: Stock status, damaged items, missing books
- **Overdue Reports**: List of overdue items with member details
- **Member Engagement**: Active vs inactive members
- **Borrowing Trends**: Daily/weekly borrowing patterns
- **Export Functionality**: Export reports to CSV/Excel format

### 🔐 Security Features
- Password hashing with bcrypt
- CSRF protection ready
- Audit logging of all actions
- Role-based access control
- Input sanitization

## Project Structure

```
library_system/
├── backend/
│   ├── api/
│   │   ├── auth.php              # Authentication & user management
│   │   ├── books.php             # Books catalog management
│   │   ├── circulation.php        # Borrowing, returning, renewal
│   │   ├── fines.php             # Fine management
│   │   └── reports.php           # Reports and analytics
│   ├── config/
│   │   └── database.php          # Database configuration
│   └── includes/
│       └── functions.php         # Utility functions
├── public/
│   ├── index.html                # Main application
│   ├── css/
│   │   ├── style.css             # Main stylesheet
│   │   └── responsive.css        # Responsive design
│   └── js/
│       ├── api.js                # API helper functions
│       └── app.js                # Main application logic
├── index.sql                     # Database schema
├── index.php                     # PHP configuration
├── index.css                     # (Legacy)
└── index.js                      # (Legacy)
```

## Database Schema

### Core Tables
- **users**: User accounts and profiles
- **books**: Book catalog
- **book_inventory**: Individual book copies tracking
- **categories**: Book categories/genres
- **book_categories**: Book-category relationships

### Transaction Tables
- **circulation**: Borrowing records
- **fines**: Fine and penalty records
- **notifications**: User notifications

### Admin Tables
- **library_settings**: System configuration
- **audit_log**: Action logging
- **usage_statistics**: Daily usage statistics

## Installation & Setup

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx web server
- Modern web browser

### Step 1: Database Setup

1. Create a new MySQL database:
```sql
CREATE DATABASE library_management_system;
```

2. Import the schema:
```bash
mysql -u root -p library_management_system < index.sql
```

### Step 2: Backend Configuration

1. Update database credentials in `backend/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

2. Set proper permissions on backend directory:
```bash
chmod -R 755 backend/
```

### Step 3: Access the Application

Open your browser and navigate to:
```
http://localhost/library_system/public/
```

## API Endpoints

### Authentication (`auth.php`)
- `POST /auth.php?action=register` - Register new user
- `POST /auth.php?action=login` - Login
- `GET /auth.php?action=profile` - Get user profile
- `POST /auth.php?action=update_profile` - Update profile
- `GET /auth.php?action=list_users` - List all users (Admin)
- `POST /auth.php?action=toggle_user_status` - Activate/Deactivate user (Admin)

### Books (`books.php`)
- `POST /books.php?action=add_book` - Add new book (Admin)
- `GET /books.php?action=list_books` - List all books
- `GET /books.php?action=get_book` - Get book details
- `POST /books.php?action=update_book` - Update book (Admin)
- `POST /books.php?action=delete_book` - Delete book (Admin)
- `GET /books.php?action=search` - Search books
- `GET /books.php?action=get_categories` - List categories

### Circulation (`circulation.php`)
- `POST /circulation.php?action=borrow` - Borrow a book
- `POST /circulation.php?action=return` - Return a book
- `POST /circulation.php?action=renew` - Renew a book
- `GET /circulation.php?action=my_books` - Get user's borrowed books
- `GET /circulation.php?action=all_borrowed` - List all borrowed books (Admin)

### Fines (`fines.php`)
- `GET /fines.php?action=get_fines` - Get user's fines
- `GET /fines.php?action=total_fines` - Get total pending fines
- `POST /fines.php?action=pay_fine` - Pay a fine
- `POST /fines.php?action=pay_multiple_fines` - Pay all fines
- `POST /fines.php?action=waive_fine` - Waive a fine (Admin)
- `POST /fines.php?action=send_overdue_notifications` - Send overdue notices

### Reports (`reports.php`)
- `GET /reports.php?action=usage_report` - Usage statistics
- `GET /reports.php?action=popular_books` - Popular books report
- `GET /reports.php?action=inventory_report` - Inventory status
- `GET /reports.php?action=overdue_items` - Overdue items list
- `GET /reports.php?action=user_activity` - User activity report
- `GET /reports.php?action=borrowing_trends` - Borrowing trends

## Library Settings

Configure default values by updating `library_settings` table:

| Setting | Default | Description |
|---------|---------|-------------|
| max_borrow_days | 14 | Days a member can keep a book |
| max_renewal_count | 2 | Max renewals per book |
| max_books_at_once | 5 | Max books borrowable at once |
| fine_per_day | 10 | Fine amount in rupees per day |
| max_fine_amount | 100 | Maximum fine per overdue book |
| notification_days_before | 2 | Days before due date for reminder |

## User Roles & Permissions

### Student/Teacher
- View book catalog
- Search books
- Borrow books
- Renew books
- View personal borrowed books
- Pay personal fines
- View personal notifications

### Librarian
- All student permissions
- Add/edit/delete books
- Manage book inventory
- Process returns
- View and manage all circulation records
- Generate reports
- View overdue items
- Send notifications

### Admin
- All librarian permissions
- Manage user accounts
- Activate/deactivate accounts
- Waive fines
- View audit logs
- Configure system settings

## Usage Guide

### For Students/Teachers

1. **Register Account**
   - Click "Register here" on login page
   - Fill in required information
   - Account will be active immediately

2. **Borrow Books**
   - Go to "Books" section
   - Search or browse catalog
   - Click "Borrow" on desired book
   - System calculates due date automatically

3. **Renew Books**
   - Go to "My Books" section
   - Click "Renew" button on book
   - New due date is calculated

4. **Return Books**
   - Go to "My Books" section
   - Click "Return" button
   - Any overdue fines are calculated automatically

5. **Pay Fines**
   - Go to "My Fines" section
   - View pending fines
   - Click "Pay" or "Pay All Fines"

### For Librarians/Admins

1. **Add Books**
   - Go to Admin → Books Management
   - Fill in book details
   - Set number of copies
   - Click "Add Book"

2. **View Circulation**
   - Go to Admin → Circulation
   - View all currently borrowed books
   - Filter by overdue status

3. **Generate Reports**
   - Go to Admin → Reports
   - Select report type
   - View statistics and trends
   - Export data as needed

4. **Manage Users**
   - Go to Admin → Users Management
   - View all users
   - Activate/deactivate accounts as needed

## Troubleshooting

### Database Connection Error
- Check database credentials in `backend/config/database.php`
- Ensure MySQL server is running
- Verify database exists

### API Not Working
- Check browser console for errors
- Verify API_URL in `js/api.js` is correct
- Ensure backend files have proper permissions

### Books Not Displaying
- Check MySQL connection
- Verify books table has data
- Check browser console for JavaScript errors

### Fine Not Calculating
- Verify library_settings table has entries
- Check due dates are in correct format
- Review fines.php for calculation logic

## Performance Tips

1. **Database Indexing**: Already implemented on frequently queried columns
2. **Pagination**: Use limit/offset parameters for large datasets
3. **Caching**: Consider implementing Redis for frequently accessed data
4. **Async Loading**: Data loads asynchronously to prevent blocking

## Future Enhancements

- [ ] Email notifications
- [ ] SMS alerts
- [ ] QR code book tracking
- [ ] Mobile app (React Native/Flutter)
- [ ] Advanced analytics dashboard
- [ ] Book reservations system
- [ ] Multi-branch support
- [ ] Barcode scanning
- [ ] Integration with school management system
- [ ] Payment gateway integration

## Support & Maintenance

- Review audit logs regularly
- Monitor database performance
- Update library settings as needed
- Backup database regularly
- Review and archive old circulation records

## License

This project is designed for Bagmati School Library Management.

## Contact

For support and inquiries, contact the library administration.

---

## 🚀 Admin System Update - 2024

A complete **Admin Dashboard** has been added with the following features:

### 6 Admin Dashboard Tabs:
1. **Overview** - 6 key statistics (total books, available, members, overdue, pending fines, borrowed)
2. **Users & Members** - Search/filter members, view borrowing count
3. **Borrowing Records** - Complete circulation history with filtering
4. **Overdue Books** - Auto-detect overdue items with auto-fine calculation (₹5/day)
5. **Fines & Penalties** - Payment tracking (pending/paid/waived/cancelled)
6. **Reports** - Generate monthly, statistical, and member activity reports

### Documentation Files:
- **ADMIN_SETUP.md** - Quick setup guide (START HERE!)
- **ADMIN_GUIDE.md** - Complete admin documentation (500+ lines)
- **ADMIN_VISUAL.md** - Visual diagrams and workflows
- **ADMIN_IMPLEMENTATION.md** - Technical implementation summary

### Quick Start:
1. Read **ADMIN_SETUP.md**
2. Update database credentials in `index.php`
3. Create admin user with role='admin'
4. Click "Admin" link to access dashboard

---

**Version**: 1.0 + Admin System  
**Last Updated**: 2024  
**Developed by**: Library Administration Team  
**Status**: ✅ Production Ready
