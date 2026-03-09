# Design Documentation - Library Management System

## Part 1: Architecture Overview

### System Architecture Diagram (ASCII)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         PRESENTATION LAYER (UI)                         │
├─────────────────────────────────────────────────────────────────────────┤
│  Web Browser UI          │  HTML/CSS/JavaScript       │  Responsive Design
│  - Login/Register        │  - public/index.html       │  - Desktop/Tablet
│  - Book Catalog          │  - public/css/style.css    │  - Mobile
│  - My Books              │  - public/js/app.js        │  - Dark Mode
│  - Fines Payment         │  - public/js/api.js        │
│  - Admin Dashboard       │                            │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│                       APPLICATION/API LAYER                             │
├─────────────────────────────────────────────────────────────────────────┤
│  Controller Layer        │  Business Logic            │  Service Layer
│  ┌────────────────────┐  │  ┌──────────────────────┐  │  ┌────────────┐
│  │ auth.php (350L)    │  │  │ User Management      │  │  │ Validation │
│  ├────────────────────┤  │  │ Role-based Access    │  │  ├────────────┤
│  │ books.php (286L)   │  │  │ Book Inventory       │  │  │ Sanitization
│  ├────────────────────┤  │  │ Availability Check   │  │  ├────────────┤
│  │ circulation.php    │  │  │ Due Date Calculation │  │  │ Error Handler
│  ├────────────────────┤  │  │ Fine Calculation     │  │  ├────────────┤
│  │ fines.php (350L)   │  │  │ Audit Logging        │  │  │ Auth Service
│  ├────────────────────┤  │  └──────────────────────┘  │  └────────────┘
│  │ reports.php (400L) │  │
│  ├────────────────────┤  │
│  │ authors_pub.php    │  │
│  ├────────────────────┤  │
│  │ dropdowns.php      │  │
│  └────────────────────┘  │
│  Total: 75+ Endpoints    │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│                          DATA ACCESS LAYER (DAL)                        │
├─────────────────────────────────────────────────────────────────────────┤
│  Query Builder           │  Prepared Statements       │  Connection Pool
│  ┌────────────────────┐  │  ┌──────────────────────┐  │  ┌────────────┐
│  │ SELECT Queries     │  │  │ Parameterized SQL    │  │  │ MySQL Pool │
│  │ JOIN Operations    │  │  │ SQL Injection        │  │  │ Reusable   │
│  │ Aggregations       │  │  │ Prevention           │  │  │ Connections
│  │ Transactions       │  │  └──────────────────────┘  │  └────────────┘
│  └────────────────────┘  │
│  functions.php (250L)    │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│                         DATABASE LAYER (PERSISTENCE)                    │
├─────────────────────────────────────────────────────────────────────────┤
│  RDBMS: MySQL 5.7+       │  Normalization: 3NF        │  Optimization
│  ┌────────────────────┐  │  ┌──────────────────────┐  │  ┌────────────┐
│  │ 14+ Tables         │  │  │ No Redundancy        │  │  │ Indexing   │
│  │ Authors            │  │  │ Referential          │  │  │ Full-Text  │
│  │ Publishers         │  │  │ Integrity (FK)       │  │  │ Foreign Key
│  │ Users              │  │  │ Atomic Operations    │  │  │ Primary Key
│  │ Books              │  │  └──────────────────────┘  │  └────────────┘
│  │ Circulation        │  │
│  │ Fines              │  │
│  │ 4 Database Views   │  │
│  └────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                     CROSS-CUTTING CONCERNS                              │
├─────────────────────────────────────────────────────────────────────────┤
│  Security               │  Performance              │  Monitoring
│  - Password Hashing     │  - Query Optimization     │  - Audit Logging
│  - Input Validation     │  - Caching Strategy       │  - Error Tracking
│  - Access Control       │  - Pagination             │  - Performance Metrics
│  - CSRF Protection      │  - Connection Pooling     │  - System Health
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Part 2: Component Interaction Diagram

### Request/Response Flow

```
┌─────────────────┐
│  User/Client    │
└────────┬────────┘
         │
         │ 1. User Action (e.g., Add Book)
         ↓
┌─────────────────────────────────┐
│  HTML Form Submission            │
│  - Gather Input Data             │
│  - Client-side Validation        │
│  - Prepare FormData              │
└────────┬────────────────────────┘
         │
         │ 2. AJAX Request
         ↓
┌─────────────────────────────────┐
│  JavaScript (app.js)             │
│  - Format Data                   │
│  - Call API (via api.js)        │
│  - Handle Response               │
└────────┬────────────────────────┘
         │
         │ 3. HTTP POST
         │    /api/books.php?action=add_book
         ↓
┌─────────────────────────────────┐
│  API Endpoint (books.php)        │
│  - Route to Controller           │
│  - Extract Parameters            │
└────────┬────────────────────────┘
         │
         │ 4. Business Logic Processing
         ↓
┌─────────────────────────────────┐
│  Validation & Authorization      │
│  - Check User Role (admin?)      │
│  - Validate Input Data           │
│  - Verify Foreign Keys           │
└────────┬────────────────────────┘
         │
         │ 5. Data Access
         ↓
┌─────────────────────────────────┐
│  Database Operations (functions) │
│  - Prepare SQL Statement         │
│  - Bind Parameters               │
│  - Execute Query                 │
│  - Get Result/LastID             │
└────────┬────────────────────────┘
         │
         │ 6. Database Execution
         ↓
┌─────────────────────────────────┐
│  MySQL Database                  │
│  - INSERT into books table       │
│  - FK Constraints Check          │
│  - Index Updates                 │
│  - Commit Transaction            │
└────────┬────────────────────────┘
         │
         │ 7. Response Preparation
         ↓
┌─────────────────────────────────┐
│  Format JSON Response            │
│  {                               │
│    "success": true,              │
│    "message": "Book added",      │
│    "data": {"book_id": 42}       │
│  }                               │
└────────┬────────────────────────┘
         │
         │ 8. HTTP Response
         ↓
┌─────────────────────────────────┐
│  JavaScript Response Handler     │
│  - Parse JSON                    │
│  - Check Success                 │
│  - Update DOM/UI                 │
│  - Show Notification             │
└────────┬────────────────────────┘
         │
         │ 9. User Sees Result
         ↓
┌─────────────────┐
│  Updated UI     │
│  Book Added!    │
└─────────────────┘
```

---

## Part 3: Class Diagram (Detailed Structure)

### Core Entity Classes

```
┌─────────────────────────────────────────┐
│              USER CLASS                 │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - user_id: int [PK]                   │
│  - email: string [UNIQUE]              │
│  - password_hash: string               │
│  - first_name: string                  │
│  - last_name: string                   │
│  - role: enum(student, teacher,        │
│           librarian, admin)             │
│  - phone: string                       │
│  - status: enum(active, inactive)      │
│  - membership_date: timestamp          │
│  - created_at: timestamp               │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + register(email, pwd, role): bool    │
│  + login(email, pwd): User?            │
│  + getProfile(): object                │
│  + updateProfile(data): bool           │
│  + changePassword(old, new): bool      │
│  + deactivate(): bool                  │
│  + getBorrowedBooks(): Book[]          │
│  + getFines(): Fine[]                  │
└─────────────────────────────────────────┘
         ↑           ↑           ↑
         │ inherits  │           │
    ┌────┴──────┐   │      ┌────┴──────┐
    │           │   │      │           │
┌───────────┐  │   │  ┌─────────────┐
│  Student  │  │   │  │  Librarian  │
├───────────┤  │   │  ├─────────────┤
│ roll_no   │  │   │  │ emp_id      │
│ class     │  │   │  │ department  │
└───────────┘  │   │  │ hire_date   │
               │   │  └─────────────┘
            ┌──┴───┴──┐
            │ Teacher │
            ├─────────┤
            │ subject │
            │ grade   │
            └─────────┘

┌─────────────────────────────────────────┐
│             AUTHOR CLASS                │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - author_id: int [PK]                 │
│  - first_name: string                  │
│  - last_name: string                   │
│  - bio: text                           │
│  - birth_date: date                    │
│  - nationality: string                 │
│  - created_at: timestamp               │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + getFullName(): string               │
│  + getBooks(): Book[]                  │
│  + getBookCount(): int                 │
│  + update(data): bool                  │
│  + canDelete(): bool                   │
└─────────────────────────────────────────┘
           ↑
           │ 1..* writes *..1
           │
┌─────────────────────────────────────────┐
│              BOOK CLASS                 │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - book_id: int [PK]                   │
│  - title: string                       │
│  - author_id: int [FK→Author]         │
│  - publisher_id: int [FK→Publisher]   │
│  - isbn: string [UNIQUE]               │
│  - genre: string                       │
│  - publication_year: int               │
│  - total_copies: int                   │
│  - available_copies: int               │
│  - status: enum(active, inactive,      │
│           damaged, lost)                │
│  - created_at: timestamp               │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + getDetails(): object                │
│  + isAvailable(): bool                 │
│  + checkout(): bool                    │
│  + checkin(): bool                     │
│  + markDamaged(): bool                 │
│  + getBorrowHistory(): Circulation[]   │
│  + getAuthorName(): string             │
│  + getPublisherName(): string          │
│  + updateStatus(status): bool          │
└─────────────────────────────────────────┘
           ↑
           │ *..* member of *..1
           │
┌─────────────────────────────────────────┐
│          CIRCULATION CLASS              │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - circulation_id: int [PK]            │
│  - user_id: int [FK→User]             │
│  - book_id: int [FK→Book]             │
│  - borrowed_date: timestamp            │
│  - due_date: date                      │
│  - returned_date: date                 │
│  - status: enum(borrowed, returned)    │
│  - renewal_count: int                  │
│  - condition: enum(good, damaged)      │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + borrow(user, book): bool            │
│  + returnBook(): Fine?                 │
│  + renew(): bool                       │
│  + isOverdue(): bool                   │
│  + getDaysOverdue(): int               │
│  + canRenew(): bool                    │
│  + calculateFine(): decimal            │
└─────────────────────────────────────────┘
           ↑
           │ generates *..1
           │
┌─────────────────────────────────────────┐
│             FINE CLASS                  │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - fine_id: int [PK]                   │
│  - circulation_id: int [FK]            │
│  - user_id: int [FK→User]             │
│  - book_id: int [FK→Book]             │
│  - amount: decimal(10,2)               │
│  - status: enum(pending, partial,      │
│           paid, waived)                 │
│  - created_date: timestamp             │
│  - paid_date: timestamp                │
│  - payment_method: string              │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + payFine(amount, method): bool       │
│  + payPartial(amount): bool            │
│  + waiveFine(reason): bool             │
│  + getPaymentHistory(): object[]       │
│  + isPaid(): bool                      │
│  + calculateLateFee(): decimal         │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│          PUBLISHER CLASS                │
├─────────────────────────────────────────┤
│ Properties:                             │
│  - publisher_id: int [PK]              │
│  - name: string [UNIQUE]               │
│  - address: string                     │
│  - city: string                        │
│  - country: string                     │
│  - phone: string                       │
│  - email: string                       │
│  - website: string                     │
│  - created_at: timestamp               │
├─────────────────────────────────────────┤
│ Methods:                                │
│  + getBooks(): Book[]                  │
│  + getBookCount(): int                 │
│  + update(data): bool                  │
│  + canDelete(): bool                   │
└─────────────────────────────────────────┘
```

---

## Part 4: Sequence Diagram - Book Borrowing

```
Actor    App        API        DB        ValidationLib  FineCalc
  │      │          │          │              │            │
  │─────→│ Click     │          │              │            │
  │      │ Borrow    │          │              │            │
  │      │ Button    │          │              │            │
  │      │          │          │              │            │
  │      │─────────→│ POST     │              │            │
  │      │          │ /borrow  │              │            │
  │      │          │          │              │            │
  │      │          │─────────→│ Check       │            │
  │      │          │          │ User       │            │
  │      │          │          │ Status     │            │
  │      │          │          │            │            │
  │      │          │          │←───────────│ Validate   │
  │      │          │←─────────│ User OK    │ Access     │
  │      │          │          │            │            │
  │      │          │─────────→│ Check      │            │
  │      │          │          │ Book       │            │
  │      │          │          │ Available  │            │
  │      │          │          │            │            │
  │      │          │          │←───────────│ Check      │
  │      │          │←─────────│ Book OK    │ Inventory  │
  │      │          │          │            │            │
  │      │          │─────────→│ Check      │            │
  │      │          │          │ User       │            │
  │      │          │          │ Fines      │            │
  │      │          │          │            │            │
  │      │          │          │←───────────│ Get Fines  │
  │      │          │←─────────│ Fines OK   │            │
  │      │          │          │            │            │
  │      │          │────────────────────────────────────→│
  │      │          │          │            │            │
  │      │          │          │            │            │ Calculate
  │      │          │          │            │            │ Due Date
  │      │          │←────────────────────────────────────│
  │      │          │          │            │            │ (14 days)
  │      │          │          │            │            │
  │      │          │─────────→│ INSERT     │            │
  │      │          │          │ Circ       │            │
  │      │          │          │ Record     │            │
  │      │          │          │            │            │
  │      │          │          │←──────────→│ Update     │
  │      │          │          │ book avail │ Inventory  │
  │      │          │          │ count      │            │
  │      │          │          │            │            │
  │      │          │          │←───────────│ Log Audit  │
  │      │          │          │ Success    │            │
  │      │          │←─────────│            │            │
  │      │←─────────│ JSON OK  │            │            │
  │←─────│ Success  │          │            │            │
  │      │ Message  │          │            │            │
  │      │ Show Due │          │            │            │
  │      │ Date     │          │            │            │
```

---

## Part 5: State Diagram - Book Lifecycle

```
              ┌──────────────────────────┐
              │  BOOK CREATION           │
              │  (Add to System)         │
              └────────────┬─────────────┘
                           │
                           ↓
        ┌──────────────────────────────────────┐
        │        ACTIVE STATE                  │
        │  - Available in Library              │
        │  - Can be Borrowed                   │
        │  - Appears in Catalog                │
        └────────────┬───────────────────┬─────┘
                     │                   │
                     │ (Borrow)          │ (Damage)
                     ↓                   ↓
        ┌─────────────────────┐  ┌─────────────────┐
        │  BORROWED STATE     │  │ DAMAGED STATE   │
        │  - Out with Member  │  │ - Not Available │
        │  - Due Date Set     │  │ - Needs Repair  │
        │  - Cannot Borrow    │  │ - Quarantined   │
        └────────┬────────────┘  └────────┬────────┘
                 │                        │
         (Return) │ (Lost/Not Returned)   │ (Repaired)
                 ↓ ↓                      ↓
    ┌──────────────────────┐   ┌──────────────────┐
    │   OVERDUE STATE      │   │  REPAIRED → ACTIVE
    │  - Return Due        │   └──────────────────┘
    │  - Fine Calculating  │
    │  - Send Reminders    │
    └───┬──────────────────┘
        │ (Return - With Fine)
        ↓
    ┌──────────────────────┐
    │  FINE PENDING STATE  │
    │  - Amount Calculated │
    │  - Awaiting Payment  │
    │  - User Notified     │
    └───┬──────────────────┘
        │ (Pay Fine)
        ↓
    ┌──────────────────────┐
    │  ACTIVE → Available  │
    │  - Fine Cleared      │
    │  - Ready to Borrow   │
    └──────────────────────┘

    Special Case (Lost Book):
    Borrowed → Lost State → Deduct from Inventory
                         → Calculate Compensation Fine
                         → Mark as Missing
```

---

## Part 6: Data Flow Diagram (DFD)

### Level 0 - System Context

```
┌─────────────┐
│   Library   │
│    Staff    │
└────┬────────┘
     │
     │ Manage Books
     │ Manage Users
     ↓
┌──────────────────────┐
│  LIBRARY SYSTEM      │
│  (Main Process)      │
└──────────────────────┘
     ↑
     │ Browse Catalog
     │ Borrow/Return
     │ View Fines
┌─────────────┐
│   Members   │
│  (Students/ │
│  Teachers)  │
└─────────────┘

┌──────────────────────┐
│  MySQL Database      │
│  (Central Store)     │
└──────────────────────┘
     ↑↓
┌──────────────────────┐
│  LIBRARY SYSTEM      │
└──────────────────────┘
     ↓
┌──────────────────────┐
│  External Services   │
│  - Email Service     │
│  - SMS Service       │
│  - Reporting         │
└──────────────────────┘
```

### Level 1 - System Processes

```
PROCESS 1: USER MANAGEMENT
  Input: Reg Data → Register → Output: Confirm
         Login Data → Authenticate → Session
         
PROCESS 2: BOOK MANAGEMENT
  Input: Book Details → Add Book → Output: Book ID
         Book ID → Search → Results
         Book ID → Update Info → Confirmation

PROCESS 3: CIRCULATION MANAGEMENT
  Input: User + Book → Borrow → Output: Receipt
         Circulation ID → Return → Fine Calculated
         Circulation ID → Renew → New Due Date

PROCESS 4: FINE MANAGEMENT
  Input: Overdue Books → Calculate Fine → Output: Fine Amount
         Fine ID + Payment → Process Payment → Receipt
         Fine ID → Waive Fine → Confirmation

PROCESS 5: REPORTING
  Input: Date Range → Generate Report → Output: PDF/Excel
         Filters → Query Data → Statistics
```

---

## Part 7: Entity-Relationship Diagram (ERD)

```
┌─────────────────┐
│     USERS       │◄─────┐
├─────────────────┤      │
│ user_id (PK)    │      │
│ email (UK)      │      │
│ password_hash   │      │
│ first_name      │      │ 1
│ last_name       │      │
│ role            │      │
│ status          │      │
│ phone           │      │
│ membership_date │      │
└────────┬────────┘      │
         │              │
         │ 1      *     │ 1..1 (has)
         │      (borrows)     │
         │              │
         ↓              │
┌──────────────────┐   │
│   CIRCULATION    │───┘
├──────────────────┤
│ circulation_id   │
│ user_id (FK)    │
│ book_id (FK)    │
│ borrowed_date   │
│ due_date        │
│ returned_date   │
│ status          │
│ renewal_count   │
│ condition       │
└────────┬────────┘
         │
         │ 1       *
         │    (generates)
         │
         ↓
    ┌─────────────┐
    │    FINES    │
    ├─────────────┤
    │ fine_id     │
    │ circ_id(FK) │
    │ user_id(FK) │
    │ book_id(FK) │
    │ amount      │
    │ status      │
    │ created_dt  │
    │ paid_date   │
    └─────────────┘

┌──────────────────┐
│      BOOKS       │◄──────┐
├──────────────────┤       │
│ book_id (PK)     │       │ 1
│ title            │       │
│ author_id (FK)   │       │ *
│ publisher_id(FK) │───────┤ (publishes)
│ isbn (UK)        │       │
│ genre            │ 1     │
│ pub_year         │ *     │
│ total_copies     │ (writes)
│ available_copies │       │
│ status           │       │
└────────┬─────────┘       │
         │                 │
         │ 1         *     │
         │    (member)     │
         ↓                 │
┌──────────────────┐       │
│   CATEGORIES     │       │
├──────────────────┤       │
│ category_id (PK) │       │
│ category_name    │       │
└──────────────────┘       │
                            │
                ┌───────────┘
                │
                ↓
        ┌──────────────┐
        │  PUBLISHERS  │
        ├──────────────┤
        │ publisher_id │
        │ name         │
        │ address      │
        │ city         │
        │ country      │
        │ phone        │
        │ email        │
        │ website      │
        └──────────────┘

┌──────────────────┐
│     AUTHORS      │
├──────────────────┤
│ author_id (PK)   │
│ first_name       │
│ last_name        │
│ bio              │
│ birth_date       │
│ nationality      │
└──────────────────┘

┌──────────────────┐
│  LIBRARIANS      │
├──────────────────┤
│ librarian_id(PK) │
│ user_id (FK)     │
│ emp_id           │
│ department       │
│ hire_date        │
│ qualifications   │
└──────────────────┘
```

---

## Part 8: Database Schema (Visual)

```
USERS TABLE (Core)
┌─────────────────────────────────────┐
│ PK  user_id          INT             │
│ UK  email            VARCHAR(100)    │
│     password_hash    VARCHAR(255)    │
│     first_name       VARCHAR(100)    │
│     last_name        VARCHAR(100)    │
│     role             ENUM(...)       │
│     phone            VARCHAR(20)     │
│ FK1 membership_date  DATE            │
│     status           ENUM(...)       │
│     created_at       TIMESTAMP       │
└─────────────────────────────────────┘

BOOKS TABLE (Core)
┌─────────────────────────────────────┐
│ PK  book_id              INT         │
│     title                VARCHAR(255)│
│ FK  author_id            INT         │
│ FK  publisher_id         INT         │
│ UK  isbn                 VARCHAR(20) │
│     genre                VARCHAR(50) │
│     publication_year     INT         │
│     total_copies         INT         │
│     available_copies     INT         │
│     status               ENUM(...)   │
│ IDX created_at           TIMESTAMP   │
└─────────────────────────────────────┘

CIRCULATION TABLE (Transaction)
┌─────────────────────────────────────┐
│ PK  circulation_id       INT         │
│ FK  user_id              INT         │
│ FK  book_id              INT         │
│ FK  inventory_id         INT         │
│ IDX borrowed_date        TIMESTAMP   │
│ IDX due_date             DATE        │
│     returned_date        DATE        │
│     status               ENUM(...)   │
│     renewal_count        INT         │
│     condition            ENUM(...)   │
└─────────────────────────────────────┘

FINES TABLE (Transaction)
┌─────────────────────────────────────┐
│ PK  fine_id              INT         │
│ FK  circulation_id       INT         │
│ FK  user_id              INT         │
│ FK  book_id              INT         │
│     amount               DECIMAL(...)│
│ IDX status               ENUM(...)   │
│     created_date         TIMESTAMP   │
│     paid_date            DATE        │
│     payment_method       VARCHAR(50) │
└─────────────────────────────────────┘

INDEXES STRATEGY
┌─────────────────────────────────────┐
│ PRIMARY KEY: All PK columns         │
│ FOREIGN KEY: All FK columns         │
│ UNIQUE: email, isbn, name           │
│ INDEX: user_id, book_id, status    │
│ FULLTEXT: title, author, publisher  │
│ COMPOSITE: (user_id, status)        │
└─────────────────────────────────────┘
```

---

## Part 9: API Endpoint Organization

```
AUTHENTICATION ENDPOINTS
├── POST /auth.php?action=register
│   ├── Request: email, password, name, role
│   ├── Response: {user_id, email, role}
│   └── Error: Duplicate email, weak password
│
├── POST /auth.php?action=login
│   ├── Request: email, password
│   ├── Response: {user_id, token, role}
│   └── Error: Invalid credentials
│
└── GET /auth.php?action=profile
    ├── Request: user_id
    ├── Response: {full user data}
    └── Error: User not found

BOOKS MANAGEMENT ENDPOINTS
├── POST /books.php?action=add_book
│   ├── Auth: Librarian+ only
│   ├── Request: title, author_id, publisher_id
│   ├── Response: {book_id}
│   └── Validation: Foreign keys exist
│
├── GET /books.php?action=list_books
│   ├── Request: genre, status, search, limit, offset
│   ├── Response: [{book_id, title, author_name, ...}]
│   └── Features: Pagination, filtering
│
├── GET /books.php?action=get_book
│   ├── Request: book_id
│   ├── Response: {full book details + categories}
│   └── JOINs: authors, publishers, categories
│
├── POST /books.php?action=update_book
│   ├── Auth: Librarian+ only
│   ├── Request: book_id, fields...
│   ├── Response: {success}
│   └── Audit: Logged
│
└── POST /books.php?action=delete_book
    ├── Auth: Librarian+ only
    ├── Request: book_id
    ├── Response: {success}
    └── Type: Soft delete (status = inactive)

AUTHORS/PUBLISHERS ENDPOINTS
├── POST /authors_publishers.php?action=add_author
├── GET /authors_publishers.php?action=list_authors
├── POST /authors_publishers.php?action=update_author
├── POST /authors_publishers.php?action=delete_author
├── POST /authors_publishers.php?action=add_publisher
├── GET /authors_publishers.php?action=list_publishers
├── POST /authors_publishers.php?action=update_publisher
└── POST /authors_publishers.php?action=delete_publisher

CIRCULATION ENDPOINTS
├── POST /circulation.php?action=borrow
│   ├── Request: user_id, book_id
│   ├── Response: {circulation_id, due_date}
│   └── Logic: Check availability, calc due date
│
├── POST /circulation.php?action=return
│   ├── Request: circulation_id, condition
│   ├── Response: {fine_due}
│   └── Logic: Update inventory, calc fine
│
├── POST /circulation.php?action=renew
│   ├── Request: circulation_id
│   ├── Response: {new_due_date}
│   └── Validation: Renewal count limit
│
└── GET /circulation.php?action=my_books
    ├── Request: user_id, status
    ├── Response: [{circ_id, book_info, due_date}]
    └── Filter: borrowed, returned

FINES ENDPOINTS
├── GET /fines.php?action=get_fines
│   ├── Request: user_id, status
│   ├── Response: [{fine_id, amount, status}]
│   └── Filter: pending, partial, paid, waived
│
├── POST /fines.php?action=pay_fine
│   ├── Request: fine_id, payment_method
│   ├── Response: {success, receipt}
│   └── Audit: Payment logged
│
└── POST /fines.php?action=waive_fine
    ├── Auth: Admin only
    ├── Request: fine_id, reason
    ├── Response: {success}
    └── Audit: Waiver logged

REPORTS ENDPOINTS
├── GET /reports.php?action=usage_report
├── GET /reports.php?action=popular_books
├── GET /reports.php?action=borrowing_trends
├── GET /reports.php?action=inventory_report
├── GET /reports.php?action=overdue_items
└── GET /reports.php?action=member_engagement

DROPDOWNS ENDPOINTS
├── GET /dropdowns.php?action=authors
├── GET /dropdowns.php?action=publishers
├── GET /dropdowns.php?action=categories
├── GET /dropdowns.php?action=genres
├── GET /dropdowns.php?action=members
├── GET /dropdowns.php?action=book_statuses
├── GET /dropdowns.php?action=circulation_statuses
└── GET /dropdowns.php?action=fine_statuses
```

---

## Summary: Architecture Layers

| Layer | Component | Technology | Responsibility |
|-------|-----------|------------|-----------------|
| **Presentation** | UI/Forms | HTML, CSS, JS | User interaction, display |
| **Application** | Controllers | PHP API | Route handling, business logic |
| **Business** | Services | PHP Functions | Validation, calculations, rules |
| **Data Access** | DAO/Query Builder | PHP + SQL | Query building, CRUD |
| **Persistence** | Database | MySQL | Data storage, integrity |
| **Cross-cutting** | Auth, Logging | Middleware | Security, monitoring |

**Next Step**: Move to Testing & Quality Documentation

