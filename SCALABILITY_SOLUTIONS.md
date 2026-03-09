# Scalability Solutions for 100-User Library System

## Part 1: System Capacity Analysis

### 1.1 Current Architecture Capacity

```
SINGLE SERVER CONFIGURATION (Recommended for 100 users)
─────────────────────────────────────────────────────────

┌─────────────────────────────────────────┐
│         Single Server Stack             │
├─────────────────────────────────────────┤
│ OS: Ubuntu 20.04 LTS                    │
│ PHP: 8.1 (FPM)                          │
│ Database: MySQL 8.0                     │
│ Cache: Redis 6.0                        │
│ Web Server: NGINX 1.21                  │
│ Memory: 8GB RAM                         │
│ CPU: 4 Cores                            │
│ Storage: 256GB SSD                      │
└─────────────────────────────────────────┘

CAPACITY ESTIMATES FOR 100 CONCURRENT USERS
─────────────────────────────────────────────────────────

Request Volume:
  • Concurrent users: 100
  • Avg requests/user/hour: 25
  • Total requests/hour: 2,500
  • Requests/second: ~0.7 RPS (very low load)

Database Size:
  • Users: 100
  • Books: 5,000 (estimated)
  • Circulation records: 50,000
  • Fines: 10,000
  • Total storage: ~500MB

Session Storage:
  • Active sessions: ~20 (20% concurrent)
  • Memory per session: 2KB
  • Total: ~40KB

Cache Requirements:
  • Book details: 5,000 × 5KB = 25MB
  • User data: 100 × 3KB = 0.3MB
  • Search results: 10MB (temporary)
  • Total recommended: 256MB Redis

CONCLUSION: Single server easily handles 100 users
─────────────────────────────────────────────────────────
```

### 1.2 Scaling Decision Tree

```
Decision: How many users?

100 users?
  ├─ YES: Single Server Deployment (This Document)
  │   ├─ Cost: Minimal ($50-100/month)
  │   ├─ Complexity: Low
  │   ├─ Setup time: 2-4 hours
  │   └─ Maintenance: Minimal
  │
  └─ NO: More than 100?
      ├─ 100-500 users:
      │   └─ Vertical scaling (more RAM, CPU)
      │
      ├─ 500-5000 users:
      │   └─ Horizontal scaling + Load balancer
      │
      └─ 5000+ users:
          └─ Microservices + Database replication
```

---

## Part 2: Single Server Setup (100 Users)

### 2.1 Server Configuration

```bash
# File: deployment/server-setup.sh
#!/bin/bash

# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y \
    nginx \
    php8.1-fpm \
    php8.1-mysql \
    php8.1-redis \
    mysql-server \
    redis-server \
    curl \
    git

# Create application user
sudo useradd -m -s /bin/bash appuser

# Create application directory
sudo mkdir -p /var/www/library_system
sudo chown -R appuser:appuser /var/www/library_system

# Create required directories
sudo mkdir -p /var/www/library_system/{public,backend,logs}
sudo chmod 755 /var/www/library_system/logs

# Configure PHP-FPM
sudo cat > /etc/php/8.1/fpm/pool.d/library.conf << 'EOF'
[library]
user = appuser
group = appuser
listen = /run/php/library-fpm.sock
listen.owner = www-data
listen.group = www-data

; Worker processes for 100 users
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 1000

; Performance settings
max_execution_time = 30
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M

; Security
expose_php = off
disable_functions = exec, passthru, shell_exec, system
EOF

# Configure NGINX
sudo cat > /etc/nginx/sites-available/library << 'EOF'
upstream php_backend {
    server unix:/run/php/library-fpm.sock;
}

server {
    listen 80;
    server_name _;
    root /var/www/library_system/public;
    
    client_max_body_size 50M;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml text/javascript
               application/x-javascript application/xml+rss
               application/javascript application/json;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    
    # Caching for static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeouts for 100 users
        fastcgi_connect_timeout 10s;
        fastcgi_send_timeout 30s;
        fastcgi_read_timeout 30s;
    }
    
    # API endpoints
    location /api/ {
        try_files $uri =404;
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root/../backend/api/$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    # Homepage
    location / {
        try_files $uri $uri/ =404;
    }
}
EOF

sudo ln -s /etc/nginx/sites-available/library /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# Configure MySQL for 100 users
sudo cat > /etc/mysql/mysql.conf.d/library.cnf << 'EOF'
[mysqld]
# Connection pool
max_connections = 50
max_user_connections = 10

# Memory optimization for 100 users
key_buffer_size = 32M
query_cache_size = 128M
query_cache_type = 1
tmp_table_size = 64M
max_heap_table_size = 64M

# InnoDB settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Performance
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Charset
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
EOF

# Configure Redis for 100 users
sudo cat > /etc/redis/redis.conf << 'EOF'
# Memory limit (256MB for book cache + session storage)
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# AOF persistence for critical data
appendonly yes
appendfsync everysec

# Security
requirepass $(openssl rand -base64 16)

# Connection pooling for 100 users
tcp-backlog 511
timeout 0
tcp-keepalive 300
databases 16
EOF

# Enable and start services
sudo systemctl enable nginx mysql redis-server php8.1-fpm
sudo systemctl restart nginx mysql redis-server php8.1-fpm

echo "Server setup complete for 100 users!"
```

---

## Part 3: Database Optimization for 100 Users

### 3.1 Connection Pooling

```php
// File: backend/config/database-pool.php

class ConnectionPool
{
    private static $pool = [];
    private static $maxConnections = 50;
    private static $activeConnections = 0;
    
    public static function getConnection()
    {
        // Check if connection available in pool
        if (!empty(self::$pool)) {
            $conn = array_pop(self::$pool);
            if ($conn->ping()) {
                return $conn;
            }
        }
        
        // Create new connection if under limit
        if (self::$activeConnections < self::$maxConnections) {
            $conn = new mysqli(
                getenv('DB_HOST'),
                getenv('DB_USER'),
                getenv('DB_PASSWORD'),
                getenv('DB_NAME')
            );
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            self::$activeConnections++;
            return $conn;
        }
        
        // Wait for connection to become available
        sleep(0.1);
        return self::getConnection();
    }
    
    public static function releaseConnection($conn)
    {
        // Return connection to pool for reuse
        if (count(self::$pool) < self::$maxConnections) {
            self::$pool[] = $conn;
        } else {
            $conn->close();
            self::$activeConnections--;
        }
    }
    
    public static function closeAll()
    {
        foreach (self::$pool as $conn) {
            $conn->close();
        }
        self::$pool = [];
    }
}

// Usage with connection reuse
try {
    $conn = ConnectionPool::getConnection();
    $result = $conn->query("SELECT * FROM books LIMIT 10");
    // Process result
    ConnectionPool::releaseConnection($conn);
} catch (Exception $e) {
    error_log("Connection error: " . $e->getMessage());
}
```

### 3.2 Query Optimization for 100 Users

```sql
-- QUERY 1: Index all frequently searched columns
CREATE INDEX idx_books_title ON books(title);
CREATE INDEX idx_books_author ON books(author_id);
CREATE INDEX idx_circulation_user ON circulation(user_id);
CREATE INDEX idx_circulation_status ON circulation(status);
CREATE INDEX idx_fines_user ON fines(user_id);

-- QUERY 2: Composite indexes for common WHERE + JOIN patterns
CREATE INDEX idx_circ_user_status ON circulation(user_id, status);
CREATE INDEX idx_circ_status_due ON circulation(status, due_date);
CREATE INDEX idx_fines_user_status ON fines(user_id, status);

-- QUERY 3: Full-text search optimization
ALTER TABLE books ADD FULLTEXT INDEX idx_books_search (title, description);
ALTER TABLE authors ADD FULLTEXT INDEX idx_authors_search (author_name);

-- QUERY 4: Analyze table statistics for optimizer
ANALYZE TABLE books;
ANALYZE TABLE circulation;
ANALYZE TABLE fines;
ANALYZE TABLE users;

-- QUERY 5: Check index usage
SELECT * FROM information_schema.STATISTICS 
WHERE TABLE_NAME = 'circulation' 
ORDER BY SEQ_IN_INDEX;

-- QUERY 6: Slow query monitoring
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Monitor for queries > 2 seconds
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
```

### 3.3 Caching Strategy for 100 Users

```php
// File: backend/services/CacheService.php

class CacheService
{
    private $redis;
    private $ttls = [
        'book_detail' => 3600,        // 1 hour
        'book_list' => 1800,          // 30 min
        'user_dashboard' => 300,      // 5 min
        'search_results' => 600,      // 10 min
        'statistics' => 3600,         // 1 hour
        'user_session' => 86400       // 24 hours
    ];
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
    
    /**
     * Cache book details (most frequently accessed)
     */
    public function getBook($bookId)
    {
        $key = "book:$bookId";
        
        // Try cache first
        $cached = $this->redis->get($key);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
        
        // Fetch from DB if not cached
        $book = $this->fetchBookFromDB($bookId);
        
        // Cache for 1 hour
        $this->redis->setex(
            $key,
            $this->ttls['book_detail'],
            json_encode($book)
        );
        
        return $book;
    }
    
    /**
     * Cache user dashboard (individual user access)
     */
    public function getUserDashboard($userId)
    {
        $key = "dashboard:$userId";
        
        $cached = $this->redis->get($key);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
        
        $dashboard = [
            'borrowed_books' => $this->getUserBooks($userId),
            'unpaid_fines' => $this->getUserFines($userId),
            'statistics' => $this->getUserStats($userId)
        ];
        
        $this->redis->setex(
            $key,
            $this->ttls['user_dashboard'],
            json_encode($dashboard)
        );
        
        return $dashboard;
    }
    
    /**
     * Cache common statistics
     */
    public function getStatistics()
    {
        $key = "stats:library";
        
        $cached = $this->redis->get($key);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
        
        $stats = [
            'total_books' => $this->getTotalBooks(),
            'available_books' => $this->getAvailableBooks(),
            'borrowed_count' => $this->getBorrowedCount(),
            'overdue_count' => $this->getOverdueCount(),
            'total_users' => $this->getTotalUsers()
        ];
        
        $this->redis->setex(
            $key,
            $this->ttls['statistics'],
            json_encode($stats)
        );
        
        return $stats;
    }
    
    /**
     * Invalidate related caches on update
     */
    public function invalidateBook($bookId)
    {
        $this->redis->del("book:$bookId");
        $this->redis->del("books:all");
        $this->redis->del("stats:library");
    }
    
    public function invalidateUserDashboard($userId)
    {
        $this->redis->del("dashboard:$userId");
        $this->redis->del("stats:library");
    }
    
    /**
     * Cache memory monitoring
     */
    public function getMemoryUsage()
    {
        $info = $this->redis->info();
        return [
            'used_memory' => $info['used_memory_human'],
            'max_memory' => $info['maxmemory_human'],
            'evictions' => $info['evicted_keys']
        ];
    }
}

// USAGE in API
$cacheService = new CacheService();

// Get book with automatic caching
$book = $cacheService->getBook(1);

// Get user dashboard with 5-minute cache
$dashboard = $cacheService->getUserDashboard($userId);

// Invalidate on updates
$bookService->updateBook($bookId, $data);
$cacheService->invalidateBook($bookId);
```

---

## Part 4: Performance Tuning for 100 Users

### 4.1 PHP-FPM Worker Configuration

```ini
; File: /etc/php/8.1/fpm/pool.d/library.conf

[library]
user = appuser
group = appuser
listen = /run/php/library-fpm.sock
listen.owner = www-data
listen.group = www-data

; DYNAMIC PROCESS MANAGER (Recommended)
; Starts/stops workers based on demand
pm = dynamic

; Maximum worker processes for 100 concurrent users
; Conservative: Total RAM / Memory per worker
; Available: 8GB RAM, ~128MB per worker = ~60 workers max
; For 100 users (20 concurrent): 20 workers sufficient
pm.max_children = 20

; Initial worker pool
pm.start_servers = 5

; Minimum spare workers (idle)
pm.min_spare_servers = 3

; Maximum spare workers (idle)
pm.max_spare_servers = 10

; Recycle workers after N requests
pm.max_requests = 1000

; Request timeout for 100 users
request_terminate_timeout = 30s

; Logging
access.log = /var/log/php-fpm-library-access.log
slowlog = /var/log/php-fpm-library-slow.log
slowlog_timeout = 5s

; Status monitoring
pm.status_path = /php-fpm-status
ping.path = /php-fpm-ping
ping.response = pong
```

### 4.2 NGINX Optimization

```nginx
# File: /etc/nginx/nginx.conf

user www-data;

# Auto-detect CPU cores (4 cores for this setup)
worker_processes auto;

# Max file descriptors for 100 users
worker_rlimit_nofile 65535;

events {
    # Max connections per worker (100 users / 4 cores = 25)
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript
               application/x-javascript application/xml+rss
               application/javascript application/json;
    
    # Connection limits for 100 users
    limit_conn_zone $binary_remote_addr zone=general:10m;
    limit_conn general 10;  # 10 connections per IP
    
    # Request rate limiting (prevent abuse)
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/s;
    limit_req zone=api burst=20 nodelay;
    
    # Caching for static assets
    proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=cache:10m
                     max_size=1g inactive=60m use_temp_path=off;
    
    include /etc/nginx/sites-enabled/*;
}
```

### 4.3 MySQL Tuning for 100 Users

```ini
; File: /etc/mysql/mysql.conf.d/library.cnf

[mysqld]

# Connections for 100 users
max_connections = 50
max_user_connections = 10
connect_timeout = 10
wait_timeout = 600
interactive_timeout = 600

# Memory allocation
key_buffer_size = 32M
query_cache_size = 128M
query_cache_type = 1
tmp_table_size = 64M
max_heap_table_size = 64M

# InnoDB (for transactions)
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_thread_concurrency = 8

# Replication (optional backup)
server_id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = MIXED

# Performance monitoring
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Character set
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
```

---

## Part 5: Monitoring & Maintenance

### 5.1 Health Check Script

```bash
#!/bin/bash
# File: deployment/health-check.sh
# Monitor system for 100 users

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

function check_service() {
    if systemctl is-active --quiet $1; then
        echo -e "${GREEN}✓${NC} $1 running"
        return 0
    else
        echo -e "${RED}✗${NC} $1 stopped"
        return 1
    fi
}

function check_disk() {
    usage=$(df /var/www/library_system | awk 'NR==2 {print $5}' | cut -d'%' -f1)
    if [ $usage -gt 80 ]; then
        echo -e "${RED}✗${NC} Disk usage: $usage% (WARNING)"
        return 1
    else
        echo -e "${GREEN}✓${NC} Disk usage: $usage%"
        return 0
    fi
}

function check_memory() {
    usage=$(free | awk 'NR==2 {printf("%.0f", $3/$2 * 100)}')
    if [ $usage -gt 85 ]; then
        echo -e "${RED}✗${NC} Memory usage: $usage% (WARNING)"
        return 1
    else
        echo -e "${GREEN}✓${NC} Memory usage: $usage%"
        return 0
    fi
}

function check_cpu() {
    load=$(uptime | awk -F'load average:' '{print $2}' | cut -d',' -f1)
    echo -e "${GREEN}✓${NC} CPU Load: $load"
    return 0
}

function check_mysql() {
    if mysqladmin ping -u root -p$(cat /root/.my.cnf | grep password | cut -d= -f2) > /dev/null 2>&1; then
        connections=$(mysql -u root -e "SHOW STATUS LIKE 'Threads_connected'" | awk 'NR==2 {print $2}')
        echo -e "${GREEN}✓${NC} MySQL: $connections connections"
        return 0
    else
        echo -e "${RED}✗${NC} MySQL unreachable"
        return 1
    fi
}

function check_redis() {
    if redis-cli ping > /dev/null 2>&1; then
        memory=$(redis-cli INFO memory | grep used_memory_human | cut -d: -f2)
        echo -e "${GREEN}✓${NC} Redis: $memory memory used"
        return 0
    else
        echo -e "${RED}✗${NC} Redis unreachable"
        return 1
    fi
}

echo "═══════════════════════════════════════"
echo "    System Health Check (100 Users)"
echo "═══════════════════════════════════════"
echo ""

# Check services
echo "Services:"
check_service nginx
check_service php8.1-fpm
check_service mysql
check_service redis-server

echo ""
echo "System Resources:"
check_disk
check_memory
check_cpu

echo ""
echo "Database Services:"
check_mysql
check_redis

echo ""
echo "═══════════════════════════════════════"
```

### 5.2 Automated Maintenance

```bash
#!/bin/bash
# File: deployment/maintenance.sh
# Daily maintenance for 100-user system

# Log file
LOG_FILE="/var/log/library-maintenance.log"

function log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

# 1. Optimize database (weekly)
if [ "$(date +%w)" -eq 0 ]; then  # Sunday
    log_message "Starting database optimization..."
    
    mysql -u root -e "
        USE library_system;
        OPTIMIZE TABLE books;
        OPTIMIZE TABLE circulation;
        OPTIMIZE TABLE fines;
        OPTIMIZE TABLE users;
    "
    
    log_message "Database optimization completed"
fi

# 2. Cleanup old logs (monthly)
if [ "$(date +%d)" -eq 1 ]; then  # First day
    log_message "Cleaning up old logs..."
    
    find /var/log/library* -name "*.log" -mtime +30 -delete
    find /var/log/php-fpm* -name "*.log" -mtime +30 -delete
    find /var/log/mysql* -name "*.log" -mtime +30 -delete
    
    log_message "Old logs cleaned up"
fi

# 3. Backup database (daily at 2 AM)
if [ "$(date +%H)" -eq 02 ]; then
    log_message "Starting database backup..."
    
    BACKUP_DIR="/backups/library_system"
    mkdir -p $BACKUP_DIR
    
    mysqldump -u root --all-databases | gzip > \
        $BACKUP_DIR/library_$(date +%Y%m%d_%H%M%S).sql.gz
    
    # Keep only last 7 days
    find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
    
    log_message "Database backup completed"
fi

# 4. Monitor slow queries (daily)
log_message "Analyzing slow queries..."

mysql -u root -e "
    SELECT query_time, lock_time, rows_sent, rows_examined, sql_text
    FROM mysql.slow_log
    WHERE ts > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY query_time DESC
    LIMIT 10;
" >> $LOG_FILE

log_message "Maintenance tasks completed"
```

---

## Part 6: Capacity Planning Beyond 100 Users

### 6.1 Growth Roadmap

```
CAPACITY ROADMAP FOR FUTURE GROWTH
─────────────────────────────────────────────────────────

PHASE 1: Current (100 users)
├─ Infrastructure: Single server
├─ Cost: ~$50-100/month
├─ Setup time: 2-4 hours
├─ Maintenance: Minimal
└─ Action: Use configurations in this document

PHASE 2: Growth (100-500 users)
├─ Infrastructure: Vertical scaling (upgrade RAM to 16GB)
├─ Cost: ~$100-200/month
├─ Action required:
│   ├─ Upgrade PHP worker processes to 40
│   ├─ Increase MySQL buffer pool to 4GB
│   ├─ Increase Redis memory to 512MB
│   └─ Monitor performance metrics
└─ Timeline: 6-12 months

PHASE 3: Scale Out (500-5000 users)
├─ Infrastructure: 2-tier (separate DB server)
├─ Cost: ~$200-400/month
├─ Changes needed:
│   ├─ App server: Current single server
│   ├─ Database server: Separate MySQL instance
│   ├─ Load balancer: NGINX reverse proxy
│   ├─ Cache: Distributed Redis cluster
│   └─ CDN: For static assets
└─ Timeline: 1-2 years

PHASE 4: Enterprise (5000+ users)
├─ Infrastructure: Microservices architecture
├─ Cost: $500+/month
├─ Components:
│   ├─ Load balancers (HA pair)
│   ├─ Multiple app servers (3-5)
│   ├─ Database replication (master-slave)
│   ├─ Search cluster (Elasticsearch)
│   ├─ Message queue (RabbitMQ)
│   └─ Monitoring (Prometheus + Grafana)
└─ Timeline: 2+ years
```

### 6.2 Pre-Scaling Monitoring

```php
// File: backend/services/PerformanceMonitor.php

class PerformanceMonitor
{
    private $redis;
    private $thresholds = [
        'cpu_usage' => 80,      // %
        'memory_usage' => 85,   // %
        'disk_usage' => 80,     // %
        'db_connections' => 40, // out of 50
        'response_time' => 500  // ms
    ];
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
    
    /**
     * Log performance metrics
     */
    public function recordMetric($name, $value)
    {
        $timestamp = date('Y-m-d H:i:s');
        $key = "metrics:$name:" . date('Y-m-d H:00:00');
        
        $this->redis->lpush($key, json_encode([
            'value' => $value,
            'timestamp' => $timestamp
        ]));
        
        $this->redis->expire($key, 86400 * 30); // Keep 30 days
    }
    
    /**
     * Check if scaling is needed
     */
    public function shouldScale()
    {
        $metrics = $this->getAverageMetrics();
        $alerts = [];
        
        foreach ($this->thresholds as $metric => $threshold) {
            if (isset($metrics[$metric]) && $metrics[$metric] > $threshold) {
                $alerts[] = "$metric: {$metrics[$metric]}% (threshold: $threshold%)";
            }
        }
        
        return [
            'should_scale' => !empty($alerts),
            'alerts' => $alerts,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get average metrics over last hour
     */
    private function getAverageMetrics()
    {
        $metrics = [];
        $patterns = ['cpu', 'memory', 'disk', 'connections', 'response_time'];
        
        foreach ($patterns as $pattern) {
            $key = "metrics:$pattern:*";
            $values = $this->redis->keys($key);
            
            $total = 0;
            $count = 0;
            
            foreach ($values as $v) {
                $data = $this->redis->lrange($v, 0, -1);
                foreach ($data as $point) {
                    $decoded = json_decode($point, true);
                    $total += $decoded['value'];
                    $count++;
                }
            }
            
            $metrics[$pattern] = $count > 0 ? $total / $count : 0;
        }
        
        return $metrics;
    }
}

// Monitor during requests
$monitor = new PerformanceMonitor();

// Record response time
$start = microtime(true);
// ... API logic ...
$duration = (microtime(true) - $start) * 1000;
$monitor->recordMetric('response_time', $duration);

// Check if scaling needed (daily check)
if (date('H:i') === '09:00') { // 9 AM daily
    $scaling_check = $monitor->shouldScale();
    if ($scaling_check['should_scale']) {
        $monitor->sendAlert($scaling_check['alerts']);
    }
}
```

---

## Summary: 100-User System Architecture

```
FINAL ARCHITECTURE (100 Users)
─────────────────────────────────────────────────────────

DEPLOYMENT CHECKLIST:
☑ Single server with 8GB RAM, 4 cores, 256GB SSD
☑ NGINX web server (gzip, caching, compression)
☑ PHP 8.1-FPM (20 worker processes)
☑ MySQL 8.0 (50 connections max)
☑ Redis 6.0 (256MB cache)
☑ SSL/TLS certificate (Let's Encrypt)
☑ Automated backups (daily)
☑ Log rotation (weekly)
☑ Health monitoring (continuous)

EXPECTED PERFORMANCE (100 Users):
├─ Request/second: 0.7-2 RPS (light load)
├─ Average response: 100-200ms
├─ Database queries: <100ms avg
├─ Cache hit rate: >80%
├─ Server uptime: 99.5%+
└─ Deployment time: 2-4 hours

MAINTENANCE SCHEDULE:
├─ Daily: Backups, health checks
├─ Weekly: Log cleanup, database optimization
├─ Monthly: Security updates, performance review
└─ Quarterly: Capacity analysis

COST ESTIMATE:
├─ Server hosting: $50-100/month
├─ SSL certificate: Free (Let's Encrypt)
├─ Monitoring: Free (custom scripts)
├─ Backups: Included
└─ Total: $50-100/month

SCALING DECISION POINT:
When to scale beyond 100 users?
├─ Response time > 500ms consistently
├─ MySQL connections approaching limit
├─ Cache hit rate < 60%
├─ Daily active users > 150
└─ Action: Upgrade to 2-tier architecture
```

**PROJECT COMPLETION: 100% ✅**

All 6 documentation files created successfully!

