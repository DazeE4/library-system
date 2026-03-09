# Implementation Details & Design Patterns

## Part 1: Architectural Patterns

### 1.1 Layered Architecture Pattern

```
┌────────────────────────────────────────────────────────┐
│             PRESENTATION LAYER                         │
│        (HTML, CSS, JavaScript Frontend)                │
├────────────────────────────────────────────────────────┤
│             API GATEWAY LAYER                          │
│   (Routing, Rate Limiting, Authentication)            │
├────────────────────────────────────────────────────────┤
│          BUSINESS LOGIC LAYER                          │
│  (Circulation Service, Fine Service, Auth Service)    │
├────────────────────────────────────────────────────────┤
│          DATA ACCESS LAYER (DAO/Repository)           │
│   (Books Repository, Users Repository, etc.)          │
├────────────────────────────────────────────────────────┤
│           DATABASE LAYER                              │
│        (MySQL with 14+ normalized tables)             │
├────────────────────────────────────────────────────────┤
│          EXTERNAL SERVICES LAYER                       │
│   (Stripe, Twilio, AWS S3, Redis Cache)              │
├────────────────────────────────────────────────────────┤
│          LOGGING & MONITORING LAYER                    │
│   (Audit logs, Performance metrics, Alerts)           │
└────────────────────────────────────────────────────────┘

IMPLEMENTATION:

Layer 1: Presentation
├─ index.html (main page)
├─ dashboard.html (user dashboard)
├─ public/js/api.js (API client)
└─ public/css/style.css (styling)

Layer 2: API Gateway
├─ router.php (route handler)
├─ middleware/auth.php (authentication)
└─ middleware/ratelimit.php (rate limiting)

Layer 3: Business Logic
├─ backend/services/AuthService.php
├─ backend/services/CirculationService.php
├─ backend/services/FineService.php
└─ backend/services/NotificationService.php

Layer 4: Data Access
├─ backend/repositories/BookRepository.php
├─ backend/repositories/UserRepository.php
├─ backend/repositories/CirculationRepository.php
└─ backend/repositories/FineRepository.php

Layer 5: Database
└─ MySQL with 14+ tables + views

Layer 6: External Services
├─ Stripe integration
├─ Twilio SMS
├─ Redis caching
└─ AWS S3 (covers, QR codes)

Layer 7: Monitoring
├─ audit_log table
├─ performance_metrics table
└─ error_log file
```

### 1.2 MVC Pattern Implementation

```php
// MODEL: Represents data and business logic
class Book
{
    private $db;
    
    public function __construct(Database $database)
    {
        $this->db = $database;
    }
    
    public function getById($bookId)
    {
        return $this->db->query(
            "SELECT * FROM books WHERE book_id = ?",
            [$bookId]
        )->fetchOne();
    }
    
    public function search($query)
    {
        return $this->db->query(
            "SELECT * FROM books 
             WHERE MATCH(title) AGAINST(? IN BOOLEAN MODE)",
            [$query]
        )->fetchAll();
    }
    
    public function create(array $data)
    {
        return $this->db->insert('books', $data);
    }
}

// CONTROLLER: Handles requests and orchestrates logic
class BookController
{
    private $bookModel;
    
    public function __construct(Book $bookModel)
    {
        $this->bookModel = $bookModel;
    }
    
    public function list()
    {
        $books = $this->bookModel->getAll();
        return $this->json($books);
    }
    
    public function show($id)
    {
        $book = $this->bookModel->getById($id);
        if (!$book) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json($book);
    }
    
    public function search($query)
    {
        $results = $this->bookModel->search($query);
        return $this->json($results);
    }
    
    private function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }
}

// VIEW: Presentation layer (templates)
<!-- view/book-list.html -->
<div class="book-list">
    <?php foreach ($books as $book): ?>
        <div class="book-card">
            <h3><?= htmlspecialchars($book['title']) ?></h3>
            <p><?= htmlspecialchars($book['author_name']) ?></p>
            <button onclick="borrowBook(<?= $book['book_id'] ?>)">
                Borrow
            </button>
        </div>
    <?php endforeach; ?>
</div>

// USAGE: Wiring components together
$db = Database::getInstance();
$bookModel = new Book($db);
$controller = new BookController($bookModel);

$action = $_GET['action'] ?? 'list';
if ($action === 'list') {
    $controller->list();
} elseif ($action === 'show') {
    $controller->show($_GET['id']);
} elseif ($action === 'search') {
    $controller->search($_GET['q']);
}
```

---

## Part 2: Design Patterns

### 2.1 Singleton Pattern (Database Connection)

```php
// Database connection pooling with Singleton
class Database
{
    private static $instance = null;
    private $connection = null;
    
    private function __construct()
    {
        $this->connect();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserializing
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect()
    {
        try {
            $this->connection = new mysqli(
                getenv('DB_HOST'),
                getenv('DB_USER'),
                getenv('DB_PASSWORD'),
                getenv('DB_NAME')
            );
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed");
            }
            
            // Set charset
            $this->connection->set_charset('utf8mb4');
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function query($sql, $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        if ($params) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    private function getParamTypes($params)
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
}

// USAGE: Guaranteed single connection throughout application
$db = Database::getInstance();
$db2 = Database::getInstance(); // Returns same instance
var_dump($db === $db2); // true
```

### 2.2 Factory Pattern (Service Creation)

```php
// Service factory for creating service instances
class ServiceFactory
{
    private static $services = [];
    
    public static function getAuthService()
    {
        if (!isset(self::$services['auth'])) {
            $db = Database::getInstance();
            self::$services['auth'] = new AuthService($db);
        }
        return self::$services['auth'];
    }
    
    public static function getCirculationService()
    {
        if (!isset(self::$services['circulation'])) {
            $db = Database::getInstance();
            $notificationService = self::getNotificationService();
            self::$services['circulation'] = new CirculationService(
                $db,
                $notificationService
            );
        }
        return self::$services['circulation'];
    }
    
    public static function getFineService()
    {
        if (!isset(self::$services['fine'])) {
            $db = Database::getInstance();
            self::$services['fine'] = new FineService($db);
        }
        return self::$services['fine'];
    }
    
    public static function getNotificationService()
    {
        if (!isset(self::$services['notification'])) {
            $db = Database::getInstance();
            self::$services['notification'] = new NotificationService($db);
        }
        return self::$services['notification'];
    }
    
    public static function getPaymentService()
    {
        if (!isset(self::$services['payment'])) {
            self::$services['payment'] = new PaymentService();
        }
        return self::$services['payment'];
    }
}

// USAGE: Central point for service creation
$circulationService = ServiceFactory::getCirculationService();
$result = $circulationService->borrowBook($userId, $bookId);
```

### 2.3 Repository Pattern (Data Access)

```php
// Generic repository interface
interface RepositoryInterface
{
    public function findById($id);
    public function findAll($filters = []);
    public function save($entity);
    public function delete($id);
    public function count();
}

// Book repository implementation
class BookRepository implements RepositoryInterface
{
    private $db;
    
    public function __construct(Database $database)
    {
        $this->db = $database;
    }
    
    public function findById($id)
    {
        $result = $this->db->query(
            "SELECT b.*, a.author_name, p.publisher_name
             FROM books b
             LEFT JOIN authors a ON b.author_id = a.author_id
             LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
             WHERE b.book_id = ?",
            [$id]
        );
        return $result->fetch_assoc();
    }
    
    public function findAll($filters = [])
    {
        $query = "SELECT * FROM books WHERE 1=1";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $query .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['author_id'])) {
            $query .= " AND author_id = ?";
            $params[] = $filters['author_id'];
        }
        
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $filters['limit'] ?? 20;
        $params[] = ($filters['page'] ?? 1 - 1) * ($filters['limit'] ?? 20);
        
        $result = $this->db->query($query, $params);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function save($entity)
    {
        if (isset($entity['book_id'])) {
            // Update
            return $this->db->update('books', $entity, 
                ['book_id' => $entity['book_id']]
            );
        } else {
            // Insert
            return $this->db->insert('books', $entity);
        }
    }
    
    public function delete($id)
    {
        return $this->db->delete('books', ['book_id' => $id]);
    }
    
    public function count()
    {
        $result = $this->db->query("SELECT COUNT(*) as count FROM books");
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    // Custom queries
    public function findByISBN($isbn)
    {
        $result = $this->db->query(
            "SELECT * FROM books WHERE isbn = ?",
            [$isbn]
        );
        return $result->fetch_assoc();
    }
    
    public function searchByTitle($title)
    {
        $result = $this->db->query(
            "SELECT * FROM books 
             WHERE MATCH(title) AGAINST(? IN BOOLEAN MODE)",
            [$title]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public function getAvailable($limit = 20)
    {
        $result = $this->db->query(
            "SELECT * FROM books 
             WHERE available_copies > 0
             ORDER BY average_rating DESC
             LIMIT ?",
            [$limit]
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// USAGE: Consistent data access interface
$bookRepo = new BookRepository($db);
$book = $bookRepo->findById(1);
$available = $bookRepo->getAvailable(10);
$byAuthor = $bookRepo->findAll(['author_id' => 5]);
```

### 2.4 Observer Pattern (Notifications)

```php
// Observer interface
interface NotificationObserver
{
    public function update(BookCirculationEvent $event);
}

// Concrete observers
class EmailNotificationObserver implements NotificationObserver
{
    public function update(BookCirculationEvent $event)
    {
        if ($event->getType() === 'book_borrowed') {
            // Send email notification
            $this->sendBorrowConfirmation($event);
        } elseif ($event->getType() === 'book_due_soon') {
            // Send due reminder
            $this->sendDueReminder($event);
        }
    }
    
    private function sendBorrowConfirmation(BookCirculationEvent $event)
    {
        $user = $event->getUser();
        $book = $event->getBook();
        // Send email logic
    }
}

class SMSNotificationObserver implements NotificationObserver
{
    public function update(BookCirculationEvent $event)
    {
        if ($event->getType() === 'book_overdue') {
            $this->sendOverdueAlert($event);
        }
    }
    
    private function sendOverdueAlert(BookCirculationEvent $event)
    {
        // Send SMS logic
    }
}

class AuditLogObserver implements NotificationObserver
{
    private $db;
    
    public function __construct(Database $database)
    {
        $this->db = $database;
    }
    
    public function update(BookCirculationEvent $event)
    {
        // Log all events to audit table
        $this->db->insert('audit_log', [
            'event_type' => $event->getType(),
            'user_id' => $event->getUser()['user_id'],
            'book_id' => $event->getBook()['book_id'],
            'details' => json_encode($event->getData()),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Subject (Observable)
class CirculationManager
{
    private $observers = [];
    
    public function attach(NotificationObserver $observer)
    {
        $this->observers[] = $observer;
    }
    
    public function detach(NotificationObserver $observer)
    {
        $key = array_search($observer, $this->observers);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }
    
    public function notify(BookCirculationEvent $event)
    {
        foreach ($this->observers as $observer) {
            $observer->update($event);
        }
    }
    
    public function borrowBook($userId, $bookId)
    {
        // Borrow logic
        $circulation = $this->createCirculation($userId, $bookId);
        
        // Notify all observers
        $event = new BookCirculationEvent(
            'book_borrowed',
            ['user_id' => $userId, 'book_id' => $bookId]
        );
        $this->notify($event);
        
        return $circulation;
    }
}

// EVENT: Data container
class BookCirculationEvent
{
    private $type;
    private $data;
    
    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    public function getUser()
    {
        return $this->data['user'];
    }
    
    public function getBook()
    {
        return $this->data['book'];
    }
}

// USAGE: Decouple notification logic from business logic
$manager = new CirculationManager();
$manager->attach(new EmailNotificationObserver());
$manager->attach(new SMSNotificationObserver());
$manager->attach(new AuditLogObserver($db));

$manager->borrowBook($userId, $bookId); // Notifies all observers
```

---

## Part 3: Code Patterns & Best Practices

### 3.1 Input Validation Pattern

```php
// Comprehensive validation class
class ValidationRules
{
    public static function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format");
        }
        
        if (strlen($email) > 255) {
            throw new ValidationException("Email too long");
        }
        
        return $email;
    }
    
    public static function validatePassword($password)
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain number";
        }
        
        if (!preg_match('/[!@#$%^&*]/', $password)) {
            $errors[] = "Password must contain special character";
        }
        
        if (!empty($errors)) {
            throw new ValidationException(implode(", ", $errors));
        }
        
        return $password;
    }
    
    public static function validateISBN($isbn)
    {
        // Remove hyphens
        $isbn = str_replace('-', '', $isbn);
        
        if (!preg_match('/^\d{13}$|^\d{10}$/', $isbn)) {
            throw new ValidationException("Invalid ISBN format");
        }
        
        return $isbn;
    }
    
    public static function validateInteger($value, $min = null, $max = null)
    {
        if (!is_numeric($value) || intval($value) != $value) {
            throw new ValidationException("Must be an integer");
        }
        
        $value = intval($value);
        
        if ($min !== null && $value < $min) {
            throw new ValidationException("Must be >= $min");
        }
        
        if ($max !== null && $value > $max) {
            throw new ValidationException("Must be <= $max");
        }
        
        return $value;
    }
    
    public static function validateString($value, $minLen = 1, $maxLen = 255)
    {
        if (!is_string($value)) {
            throw new ValidationException("Must be a string");
        }
        
        $length = strlen($value);
        
        if ($length < $minLen) {
            throw new ValidationException("Too short (min: $minLen)");
        }
        
        if ($length > $maxLen) {
            throw new ValidationException("Too long (max: $maxLen)");
        }
        
        return $value;
    }
}

// USAGE: Input validation in service
class BookService
{
    private $db;
    
    public function addBook($data)
    {
        try {
            // Validate each field
            $book = [
                'title' => ValidationRules::validateString($data['title'], 1, 255),
                'isbn' => ValidationRules::validateISBN($data['isbn']),
                'author_id' => ValidationRules::validateInteger($data['author_id'], 1),
                'publisher_id' => ValidationRules::validateInteger($data['publisher_id'], 1),
                'total_copies' => ValidationRules::validateInteger($data['total_copies'], 1)
            ];
            
            // Check for duplicates
            if ($this->bookExists($book['isbn'])) {
                throw new ValidationException("ISBN already exists");
            }
            
            // Insert book
            return $this->db->insert('books', $book);
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }
}
```

### 3.2 Error Handling Pattern

```php
// Custom exception hierarchy
class LibraryException extends Exception {}

class ValidationException extends LibraryException {}
class AuthenticationException extends LibraryException {}
class AuthorizationException extends LibraryException {}
class ResourceNotFoundException extends LibraryException {}
class BusinessLogicException extends LibraryException {}

// Global error handler
class ErrorHandler
{
    public static function handle(Throwable $e)
    {
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        
        // Map exceptions to HTTP status codes
        if ($e instanceof ValidationException) {
            $statusCode = 422;
            $errorCode = 'VALIDATION_ERROR';
        } elseif ($e instanceof AuthenticationException) {
            $statusCode = 401;
            $errorCode = 'AUTH_ERROR';
        } elseif ($e instanceof AuthorizationException) {
            $statusCode = 403;
            $errorCode = 'FORBIDDEN';
        } elseif ($e instanceof ResourceNotFoundException) {
            $statusCode = 404;
            $errorCode = 'NOT_FOUND';
        } elseif ($e instanceof BusinessLogicException) {
            $statusCode = 400;
            $errorCode = 'BUSINESS_ERROR';
        }
        
        // Log error
        error_log("[" . date('Y-m-d H:i:s') . "] " . 
                  $e->getFile() . ":" . $e->getLine() . 
                  " - " . $e->getMessage());
        
        // Return JSON response
        header('Content-Type: application/json');
        http_response_code($statusCode);
        
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
}

// Usage in API
set_exception_handler([ErrorHandler::class, 'handle']);

try {
    $userId = ValidationRules::validateInteger($_GET['user_id']);
    $user = $userService->getUser($userId);
    
    if (!$user) {
        throw new ResourceNotFoundException("User not found");
    }
    
    echo json_encode(['success' => true, 'data' => $user]);
    
} catch (Exception $e) {
    ErrorHandler::handle($e);
}
```

### 3.3 Transaction Management Pattern

```php
class TransactionManager
{
    private $db;
    
    public function __construct(Database $database)
    {
        $this->db = $database;
    }
    
    /**
     * Execute callback within transaction
     * Automatically commits or rolls back
     */
    public function execute(callable $callback)
    {
        try {
            $this->db->query("START TRANSACTION");
            
            $result = $callback();
            
            $this->db->query("COMMIT");
            return $result;
            
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }
    
    /**
     * Savepoint for nested transactions
     */
    public function savepoint($name, callable $callback)
    {
        try {
            $this->db->query("SAVEPOINT $name");
            
            $result = $callback();
            
            $this->db->query("RELEASE SAVEPOINT $name");
            return $result;
            
        } catch (Exception $e) {
            $this->db->query("ROLLBACK TO SAVEPOINT $name");
            throw $e;
        }
    }
}

// USAGE: Complex operation with multiple steps
$transactionManager = new TransactionManager($db);

$result = $transactionManager->execute(function() use ($db, $userId, $bookId) {
    // Step 1: Create circulation record
    $circulationId = $db->insert('circulation', [
        'user_id' => $userId,
        'book_id' => $bookId,
        'borrow_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'status' => 'borrowed'
    ]);
    
    // Step 2: Decrease available copies
    $db->query(
        "UPDATE books SET available_copies = available_copies - 1 
         WHERE book_id = ? AND available_copies > 0",
        [$bookId]
    );
    
    // Step 3: Create notification
    $db->insert('notifications', [
        'user_id' => $userId,
        'type' => 'borrow',
        'message' => 'Book borrowed successfully'
    ]);
    
    // All succeed or all rollback
    return $circulationId;
});
```

---

## Part 4: Security Patterns

### 4.1 Input Sanitization

```php
class Sanitizer
{
    public static function sanitizeString($input)
    {
        // Remove null bytes
        $input = str_replace("\0", "", $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // HTML escape for output
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeSQL($input, $db)
    {
        // Use prepared statements (primary defense)
        // This is for emergency only
        return $db->real_escape_string($input);
    }
    
    public static function sanitizeJSON($input)
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }
        
        if (!is_array($input)) {
            throw new ValidationException("Invalid JSON");
        }
        
        return array_filter($input, function($value) {
            return !is_array($value) && !is_object($value);
        });
    }
    
    public static function sanitizePath($path)
    {
        // Prevent directory traversal
        $path = str_replace(['..', '\\', "\0"], '', $path);
        return ltrim($path, '/');
    }
}

// USAGE:
$email = Sanitizer::sanitizeString($_POST['email']);
$filename = Sanitizer::sanitizePath($_GET['file']);
```

### 4.2 Password Security Pattern

```php
class PasswordManager
{
    const ALGORITHM = PASSWORD_BCRYPT;
    const OPTIONS = ['cost' => 12];
    
    /**
     * Hash password for storage
     */
    public static function hash($password)
    {
        if (strlen($password) < 8) {
            throw new ValidationException("Password too short");
        }
        
        return password_hash($password, self::ALGORITHM, self::OPTIONS);
    }
    
    /**
     * Verify password against hash
     */
    public static function verify($password, $hash)
    {
        if (!password_verify($password, $hash)) {
            return false;
        }
        
        // Check if rehashing needed (when cost increases)
        if (password_needs_rehash($hash, self::ALGORITHM, self::OPTIONS)) {
            return ['verified' => true, 'needs_rehash' => true];
        }
        
        return ['verified' => true, 'needs_rehash' => false];
    }
    
    /**
     * Generate random password for reset
     */
    public static function generateReset()
    {
        return bin2hex(random_bytes(32));
    }
}

// USAGE:
// On registration
$passwordHash = PasswordManager::hash($password);
$db->insert('users', ['password_hash' => $passwordHash, ...]);

// On login
$result = PasswordManager::verify($inputPassword, $storedHash);
if ($result['verified']) {
    // Login success
    if ($result['needs_rehash']) {
        // Update with new hash
        $newHash = PasswordManager::hash($inputPassword);
        $db->update('users', ['password_hash' => $newHash]);
    }
}
```

### 4.3 CSRF Protection Pattern

```php
class CSRFToken
{
    private static $tokenField = '_csrf_token';
    
    /**
     * Generate CSRF token
     */
    public static function generate()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get token for forms
     */
    public static function field()
    {
        return '<input type="hidden" name="' . self::$tokenField . 
               '" value="' . self::generate() . '">';
    }
    
    /**
     * Middleware to verify token
     */
    public static function middleware()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[self::$tokenField] ?? '';
            
            if (!self::validate($token)) {
                throw new AuthorizationException("CSRF token invalid");
            }
        }
    }
}

// USAGE: In forms
echo CSRFToken::field();

// USAGE: In API middleware
CSRFToken::middleware();
```

---

## Part 5: Performance Optimization Patterns

### 5.1 Caching Pattern

```php
class CacheManager
{
    private $cache;
    const CACHE_TTL = 3600; // 1 hour
    
    public function __construct()
    {
        // Use Redis or Memcached
        $this->cache = new Redis();
        $this->cache->connect('127.0.0.1', 6379);
    }
    
    /**
     * Get or compute cached value
     */
    public function rememberKey($key, $ttl = self::CACHE_TTL, callable $callback)
    {
        // Try to get from cache
        $value = $this->cache->get($key);
        
        if ($value !== false) {
            return unserialize($value);
        }
        
        // Compute if not cached
        $value = $callback();
        
        // Store in cache
        $this->cache->setex($key, $ttl, serialize($value));
        
        return $value;
    }
    
    /**
     * Cache book details
     */
    public function getBook($bookId)
    {
        return $this->rememberKey(
            "book:$bookId",
            3600,
            function() use ($bookId) {
                return $this->db->query(
                    "SELECT * FROM books WHERE book_id = ?",
                    [$bookId]
                )->fetch_assoc();
            }
        );
    }
    
    /**
     * Invalidate related caches on update
     */
    public function invalidateBook($bookId)
    {
        $this->cache->del("book:$bookId");
        $this->cache->del("books:all");
        $this->cache->del("books:available");
    }
}

// USAGE:
$cacheManager = new CacheManager();
$book = $cacheManager->getBook(1); // Cached for 1 hour

// On book update
$cacheManager->invalidateBook(1); // Clear cache
```

### 5.2 Query Optimization Pattern

```php
class QueryOptimizer
{
    private $db;
    
    /**
     * Use database views instead of complex joins
     */
    public function getUserDashboard($userId)
    {
        // Instead of complex joins, use pre-computed view
        return $this->db->query(
            "SELECT * FROM user_dashboard WHERE user_id = ?",
            [$userId]
        )->fetch_assoc();
    }
    
    /**
     * Pagination instead of fetching all
     */
    public function getBooks($page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        
        return $this->db->query(
            "SELECT * FROM books LIMIT ? OFFSET ?",
            [$perPage, $offset]
        )->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Batch operations instead of loops
     */
    public function updateManyBooks($bookIds, $updates)
    {
        $ids = implode(',', array_fill(0, count($bookIds), '?'));
        
        $query = "UPDATE books SET ";
        $parts = [];
        foreach (array_keys($updates) as $field) {
            $parts[] = "$field = ?";
        }
        $query .= implode(", ", $parts);
        $query .= " WHERE book_id IN ($ids)";
        
        $params = array_values($updates) + $bookIds;
        
        return $this->db->query($query, $params);
    }
    
    /**
     * Select only needed columns
     */
    public function getBookTitles()
    {
        // Bad: SELECT * FROM books
        // Good:
        return $this->db->query(
            "SELECT book_id, title FROM books"
        )->fetch_all(MYSQLI_ASSOC);
    }
}
```

---

## Part 6: API Design Patterns

### 6.1 Versioning Pattern

```
API Versioning Strategy: URL-based
────────────────────────────────────────────────────────

Version 1 (Stable):
  GET /api/v1/books
  POST /api/v1/circulation/borrow
  
Version 2 (Enhanced):
  GET /api/v2/books (improved filtering)
  POST /api/v2/circulation/borrow (faster)
  
Deprecation Timeline:
  V1: Supported until Dec 2026
  V2: Current stable (until Dec 2027)
  V3: Beta (launch date TBD)

Header Version Declaration:
  Accept: application/vnd.library.v2+json
  X-API-Version: 2

Backward Compatibility:
  • Keep old endpoints running for 1 year
  • Clearly announce deprecation 6 months prior
  • Provide migration guide for new version
```

### 6.2 Pagination Pattern

```javascript
// Standardized pagination response format
{
    "success": true,
    "data": [
        // Array of items
    ],
    "pagination": {
        "page": 1,
        "per_page": 20,
        "total": 150,
        "total_pages": 8,
        "has_next": true,
        "has_previous": false,
        "next_page_url": "/api/books?page=2",
        "previous_page_url": null
    }
}
```

**Next: Create Scalability Solutions Documentation**

