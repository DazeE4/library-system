# 📊 Bagmati School Library - Admin System Documentation

## Overview

The admin dashboard provides comprehensive management tools for library administrators to monitor book circulation, track overdue items, manage fines, and generate reports.

---

## 🔐 Admin Authentication

### Login Process

1. **Access Admin Panel**
   - Click the **"Admin"** link in the navigation bar (only visible when an admin user is logged in)
   - Opens the admin login modal

2. **Login Credentials**
   - **Email**: Enter registered admin email
   - **Password**: Enter admin password
   - The system verifies the user has the `admin` role in the database

3. **Session Management**
   - Admin session is stored in browser's LocalStorage
   - Admin link remains visible across page refreshes
   - Click "Logout" to end the admin session

### User Roles

The system supports 4 user roles:
- **student** - Can borrow books, view recommendations
- **teacher** - Can borrow books, get priority in borrowing
- **librarian** - Can manage inventory, check circulation
- **admin** - Full access to all admin features ✓

---

## 📈 Admin Dashboard Features

### 1. **Overview Tab** 📊

Displays key statistics at a glance:

| Metric | Description |
|--------|-------------|
| **Total Books** | All books in the library system |
| **Available Now** | Books currently in stock |
| **Active Members** | Users with active borrowing |
| **Overdue Books** | Items past their due date |
| **Pending Fines** | Unpaid fine charges |
| **Currently Borrowed** | Books issued to members |

**API Endpoint**: `POST /index.php?action=getAdminStats`

**Response**:
```json
{
  "success": true,
  "stats": {
    "totalBooks": 5000,
    "availableBooks": 3200,
    "activeUsers": 1240,
    "overdueCount": 45,
    "pendingFines": ₹3250,
    "currentlyBorrowed": 1800
  }
}
```

---

### 2. **Users & Members Tab** 👥

Manage all library members and their borrowing activity.

**Features**:
- 🔍 **Search** by name or email
- 🏷️ **Filter** by role (student, teacher, librarian)
- 📊 Shows books borrowed count per user
- ✅ Status indicator (Active/Inactive)
- 📅 Last activity timestamp

**Table Columns**:
| Column | Info |
|--------|------|
| User ID | Unique identifier |
| Name | Member's full name |
| Email | Member's email address |
| Role | student/teacher/librarian |
| Books Borrowed | Total books currently borrowed |
| Status | Active/Inactive status |

**API Endpoint**: `POST /index.php?action=getAdminUsers`

**Request Parameters**:
```json
{
  "action": "getAdminUsers",
  "search": "john",      // optional search term
  "role": "student"       // optional role filter
}
```

---

### 3. **Borrowing Records Tab** 📚

Complete history of all book transactions.

**Features**:
- 📋 View all circulation records
- 🔍 Filter by status (borrowed, returned, overdue, renewed)
- 📅 Issue date and due date tracking
- ✅ Return date recording
- 📊 Transaction ID for audit trail

**Table Columns**:
| Column | Info |
|--------|------|
| Transaction ID | Unique circulation record ID |
| Member | Name of borrowing member |
| Book Title | Title of borrowed book |
| Issue Date | When book was issued |
| Due Date | When book should be returned |
| Return Date | Actual return date (if returned) |
| Status | borrowed/returned/overdue/renewed |

**Status Types**:
- 🟦 **borrowed** - Currently with member
- 🟩 **returned** - Book returned on time
- 🟧 **overdue** - Book past due date
- 🟪 **renewed** - Due date extended

**API Endpoint**: `POST /index.php?action=getBorrowingRecords`

**Request Parameters**:
```json
{
  "action": "getBorrowingRecords",
  "status": "overdue"  // optional status filter
}
```

---

### 4. **Overdue Books Tab** ⏰

Track all overdue items and calculate automatic fines.

**Features**:
- 🔴 Shows only overdue books (due_date < NOW())
- 📊 Automatic days overdue calculation
- 💰 Auto-calculated fine amounts (₹5/day default)
- 👤 Member identification for follow-up

**Table Columns**:
| Column | Info |
|--------|------|
| Member ID | ID of member with overdue book |
| Member Name | Name of member |
| Book Title | Title of overdue book |
| Due Date | Original due date |
| Days Overdue | Days past due (auto-calculated) |
| Fine Amount (₹) | Calculated fine: days × ₹5 |
| Status | Always "Overdue" |

**Fine Calculation**:
```
Days Overdue = TODAY - Due Date
Fine Amount = Days Overdue × ₹5/day
```

**Example**:
- Book was due: Jan 15, 2024
- Today: Jan 22, 2024
- Days Overdue: 7 days
- Fine: 7 × ₹5 = **₹35**

**API Endpoint**: `POST /index.php?action=getOverdueBooks`

**Response**:
```json
{
  "success": true,
  "overdueBooks": [
    {
      "memberId": 1001,
      "memberName": "Rajesh Kumar",
      "bookTitle": "Introduction to Python",
      "dueDate": "2024-01-15",
      "daysOverdue": 7,
      "status": "overdue"
    }
  ]
}
```

---

### 5. **Fines & Penalties Tab** 💰

Manage all fines and track payment status.

**Features**:
- 💳 View all generated fines
- 📊 Filter by payment status
- 💾 Reason for fine tracking
- 📅 Fine creation date
- ✅ Payment status verification

**Fine Reasons**:
- 📅 **overdue** - Late return penalty
- 🔨 **damage** - Book damage charge
- ❌ **lost** - Book loss replacement fee
- 📝 **other** - Miscellaneous charges

**Payment Status**:
- 🟩 **paid** - Fine fully paid
- ⏳ **pending** - Payment awaiting
- ⚠️ **waived** - Fine cancelled/waived
- ❌ **cancelled** - Fine withdrawn

**Table Columns**:
| Column | Info |
|--------|------|
| Fine ID | Unique fine identifier |
| Member | Name of member with fine |
| Book Title | Book associated with fine |
| Amount (₹) | Fine amount in rupees |
| Reason | Reason for fine |
| Status | Payment status |

**API Endpoint**: `POST /index.php?action=getFinesRecords`

**Request Parameters**:
```json
{
  "action": "getFinesRecords",
  "status": "pending"  // optional: filter by payment status
}
```

---

### 6. **Reports Tab** 📄

Generate comprehensive reports for analysis and record-keeping.

**Available Reports**:

#### a) **Monthly Report**
- Borrowing activity per month
- Return rate statistics
- Overdue trends
- Popular books analysis

#### b) **Statistics Report**
- Overall library metrics
- Member demographics
- Usage patterns
- Genre popularity

#### c) **Member Activity Report**
- Individual member borrowing history
- Fine summary per member
- Activity timeline
- Recommendation frequency

**Generate Report**:
1. Select report type
2. Click "Generate Report"
3. Report opens in new browser window
4. Save or print as needed

**API Endpoint**: `POST /index.php?action=generateReport`

**Request Parameters**:
```json
{
  "action": "generateReport",
  "reportType": "monthly"  // "monthly" | "statistics" | "members"
}
```

---

## 🔄 Workflow Examples

### Example 1: Process Overdue Book

1. Go to **Overdue Books** tab
2. Find member with overdue book
3. Note "Days Overdue" and "Fine Amount"
4. Contact member for book return
5. Go to **Fines** tab to track fine status
6. Update payment status when paid

### Example 2: Member Borrowing Summary

1. Go to **Users & Members** tab
2. Search for member name
3. View "Books Borrowed" count
4. Go to **Borrowing Records** tab
5. Filter by member to see all transactions

### Example 3: Generate Monthly Report

1. Go to **Reports** tab
2. Click "Generate Monthly Report"
3. Review statistics in new window
4. Download or print as needed
5. Archive for record-keeping

---

## 📊 Database Structure

### Key Tables

#### Users Table
```sql
CREATE TABLE users (
  id INT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100),
  role ENUM('student', 'teacher', 'librarian', 'admin'),
  created_at TIMESTAMP
)
```

#### Circulation Table
```sql
CREATE TABLE circulation (
  id INT PRIMARY KEY,
  user_id INT,
  book_id INT,
  issue_date DATE,
  due_date DATE,
  return_date DATE,
  status ENUM('borrowed', 'returned', 'overdue', 'renewed'),
  renewal_count INT DEFAULT 0,
  created_at TIMESTAMP
)
```

#### Fines Table
```sql
CREATE TABLE fines (
  id INT PRIMARY KEY,
  user_id INT,
  book_id INT,
  fine_amount DECIMAL(10,2),
  fine_reason ENUM('overdue', 'damage', 'lost', 'other'),
  status ENUM('pending', 'paid', 'waived', 'cancelled'),
  days_overdue INT,
  paid_date DATE,
  created_at TIMESTAMP
)
```

#### Books Table
```sql
CREATE TABLE books (
  id INT PRIMARY KEY,
  title VARCHAR(255),
  author_id INT,
  publisher_id INT,
  isbn VARCHAR(13),
  created_at TIMESTAMP
)
```

---

## 🔧 API Reference

### Base URL
```
POST /index.php
```

### Authentication
Admin user must have `role = 'admin'` in users table.

### Response Format
All responses are JSON:
```json
{
  "success": true/false,
  "data": {...} or "message": "error text"
}
```

### Common Endpoints

| Endpoint | Purpose |
|----------|---------|
| `adminLogin` | Authenticate admin user |
| `getAdminStats` | Get dashboard statistics |
| `getAdminUsers` | List all members |
| `getBorrowingRecords` | Get circulation history |
| `getOverdueBooks` | Get overdue items |
| `getFinesRecords` | Get fine records |
| `generateReport` | Generate PDF/CSV report |

---

## 🚀 Setting Up Admin Account

### Step 1: Insert Admin User in Database

```sql
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@bagmati.com', 'hashed_password_here', 'admin');
```

### Step 2: Verify Admin Role

```sql
SELECT id, name, email, role FROM users WHERE role = 'admin';
```

### Step 3: Access Admin Panel

1. Go to library system homepage
2. "Admin" link appears in navigation
3. Click to open login modal
4. Enter admin credentials
5. Dashboard loads

---

## 📋 Checklist for Admins

### Daily Tasks
- ✅ Check overdue books count
- ✅ Monitor pending fines
- ✅ Review new member registrations
- ✅ Process fine payments

### Weekly Tasks
- ✅ Generate weekly activity report
- ✅ Review member borrowing patterns
- ✅ Update book inventory
- ✅ Follow up on long-overdue items

### Monthly Tasks
- ✅ Generate comprehensive monthly report
- ✅ Analyze member demographics
- ✅ Review fine collection rate
- ✅ Plan next month's recommendations

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| Admin link not showing | Make sure user role is 'admin' in database |
| Login fails | Verify email exists in users table with role='admin' |
| No data showing | Check database connection in index.php |
| Overdue calculation wrong | Verify due_date field format is DATE type |
| Fine amount incorrect | Check FINE_PER_DAY constant (₹5/day) |

---

## 📞 Support

For technical issues or questions about the admin system, contact the development team.

---

**Last Updated**: 2024
**Version**: 1.0
**Status**: Production Ready ✓
