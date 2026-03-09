# Advanced Features Documentation

## Part 1: Feature Overview

```
┌──────────────────────────────────────────────────────┐
│        ADVANCED FEATURES ROADMAP                     │
├──────────────────────────────────────────────────────┤
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ Phase 1: Payment & Notifications            │   │
│  │ • Payment gateway integration               │   │
│  │ • Email notifications                       │   │
│  │ • SMS notifications                         │   │
│  │ • In-app notifications                      │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ Phase 2: Mobile & Scanning                  │   │
│  │ • QR code book identification               │   │
│  │ • Mobile-friendly API                       │   │
│  │ • Barcode scanning                          │   │
│  │ • Native app endpoints                      │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ Phase 3: Search & Discovery                 │   │
│  │ • Advanced search filters                   │   │
│  │ • Book recommendations                      │   │
│  │ • Trending books                            │   │
│  │ • Reading history                           │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────────────────────────────────┐   │
│  │ Phase 4: Analytics & Insights               │   │
│  │ • User behavior analytics                   │   │
│  │ • Collection analytics                      │   │
│  │ • Usage patterns                            │   │
│  │ • Predictive analytics                      │   │
│  └─────────────────────────────────────────────┘   │
│                                                      │
└──────────────────────────────────────────────────────┘
```

---

## Part 2: Payment Gateway Integration

### 2.1 Stripe Integration

```php
// File: backend/api/payments.php

<?php
require_once '../includes/functions.php';

// Load Stripe library
require_once '../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaymentService
{
    private $db;
    private $stripe;
    
    public function __construct($database)
    {
        $this->db = $database;
        Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    }
    
    /**
     * Initialize payment intent for fine payment
     * 
     * @param int $userId User paying the fine
     * @param int $fineId Fine to be paid
     * @param float $amount Amount in rupees
     * @return array Payment intent details
     */
    public function initiatePayment($userId, $fineId, $amount)
    {
        try {
            // Validate amount
            if ($amount <= 0) {
                throw new Exception('Invalid amount');
            }
            
            // Convert INR to paise (Stripe smallest unit)
            $amountInPaise = $amount * 100;
            
            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInPaise,
                'currency' => 'inr',
                'metadata' => [
                    'user_id' => $userId,
                    'fine_id' => $fineId,
                    'library_system' => true
                ],
                'statement_descriptor' => 'Library Fine Payment'
            ]);
            
            // Store payment record
            $this->db->insert('payments', [
                'user_id' => $userId,
                'fine_id' => $fineId,
                'stripe_payment_id' => $paymentIntent->id,
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_id' => $paymentIntent->id,
                'amount' => $amount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Confirm payment and update fine status
     */
    public function confirmPayment($stripePaymentId)
    {
        try {
            // Retrieve payment intent from Stripe
            $paymentIntent = PaymentIntent::retrieve($stripePaymentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                throw new Exception('Payment not succeeded');
            }
            
            // Get payment record
            $payment = $this->db->query(
                "SELECT * FROM payments WHERE stripe_payment_id = ?",
                [$stripePaymentId]
            )->fetchOne();
            
            if (!$payment) {
                throw new Exception('Payment record not found');
            }
            
            // Update fine status
            $this->db->update('fines', 
                ['status' => 'paid', 'paid_date' => date('Y-m-d')],
                ['fine_id' => $payment['fine_id']]
            );
            
            // Update payment status
            $this->db->update('payments',
                ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')],
                ['payment_id' => $payment['payment_id']]
            );
            
            // Send confirmation notification
            $this->sendPaymentConfirmation($payment['user_id'], $payment['amount']);
            
            return [
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'fine_id' => $payment['fine_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle refund request
     */
    public function processRefund($paymentId, $reason = '')
    {
        try {
            $payment = $this->db->query(
                "SELECT * FROM payments WHERE payment_id = ?",
                [$paymentId]
            )->fetchOne();
            
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            // Create refund in Stripe
            $refund = $payment['stripe_payment_id']->createRefund([
                'reason' => $reason ?: 'requested_by_customer'
            ]);
            
            // Update payment status
            $this->db->update('payments',
                ['status' => 'refunded', 'refunded_at' => date('Y-m-d H:i:s')],
                ['payment_id' => $paymentId]
            );
            
            // Revert fine status
            $this->db->update('fines',
                ['status' => 'unpaid'],
                ['fine_id' => $payment['fine_id']]
            );
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100  // Convert back to rupees
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function sendPaymentConfirmation($userId, $amount)
    {
        // Send email/SMS confirmation
        $notificationService = new NotificationService($this->db);
        $notificationService->sendPaymentReceipt($userId, $amount);
    }
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $paymentService = new PaymentService($db);
    
    if ($action === 'initiate') {
        $result = $paymentService->initiatePayment(
            $_POST['user_id'],
            $_POST['fine_id'],
            $_POST['amount']
        );
    } elseif ($action === 'confirm') {
        $result = $paymentService->confirmPayment($_POST['stripe_payment_id']);
    } elseif ($action === 'refund') {
        $result = $paymentService->processRefund($_POST['payment_id']);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
```

### 2.2 Frontend Payment Integration

```javascript
// File: public/js/payments.js

class PaymentService {
    constructor() {
        this.stripe = Stripe(STRIPE_PUBLIC_KEY);
        this.elements = this.stripe.elements();
        this.cardElement = this.elements.create('card');
    }
    
    /**
     * Initialize payment form
     */
    initializePaymentForm(containerId) {
        this.cardElement.mount(`#${containerId}`);
        
        // Handle errors
        this.cardElement.addEventListener('change', (event) => {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    }
    
    /**
     * Process payment
     */
    async processPayment(fineId, amount, clientSecret) {
        try {
            // Confirm card payment
            const result = await this.stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: this.cardElement,
                    billing_details: {
                        name: document.getElementById('cardholderName').value
                    }
                }
            });
            
            if (result.error) {
                return {
                    success: false,
                    error: result.error.message
                };
            }
            
            // Payment succeeded
            if (result.paymentIntent.status === 'succeeded') {
                // Confirm payment on server
                const response = await fetch('backend/api/payments.php?action=confirm', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `stripe_payment_id=${result.paymentIntent.id}`
                });
                
                const data = await response.json();
                return {
                    success: true,
                    message: 'Payment successful'
                };
            }
            
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }
}

// Initialize payment service
const paymentService = new PaymentService();

// Handle payment form submission
document.getElementById('paymentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fineId = document.getElementById('fineId').value;
    const amount = document.getElementById('amount').value;
    
    // Get client secret from server
    const initResponse = await fetch('backend/api/payments.php?action=initiate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `user_id=${getCurrentUserId()}&fine_id=${fineId}&amount=${amount}`
    });
    
    const initData = await initResponse.json();
    
    if (!initData.success) {
        showError(initData.error);
        return;
    }
    
    // Process payment
    const result = await paymentService.processPayment(
        fineId,
        amount,
        initData.client_secret
    );
    
    if (result.success) {
        showSuccess('Payment successful!');
        setTimeout(() => location.reload(), 2000);
    } else {
        showError(result.error);
    }
});
```

---

## Part 3: Notification System

### 3.1 Email Notifications

```php
// File: backend/api/notifications.php

<?php
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

class NotificationService
{
    private $db;
    private $mailer;
    
    public function __construct($database)
    {
        $this->db = $database;
        $this->initializeMailer();
    }
    
    private function initializeMailer()
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = getenv('SMTP_HOST');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('SMTP_USER');
        $this->mailer->Password = getenv('SMTP_PASSWORD');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->setFrom('noreply@library.edu', 'Library System');
    }
    
    /**
     * Send due date reminder
     */
    public function sendDueReminder($circulationId)
    {
        try {
            $circulation = $this->db->query(
                "SELECT c.*, u.email, u.first_name, b.title 
                 FROM circulation c
                 JOIN users u ON c.user_id = u.user_id
                 JOIN books b ON c.book_id = b.book_id
                 WHERE c.circulation_id = ?",
                [$circulationId]
            )->fetchOne();
            
            $daysUntilDue = $this->getDaysUntilDue($circulation['due_date']);
            
            $subject = "📚 Book Due Reminder";
            $body = $this->renderTemplate('due_reminder', [
                'name' => $circulation['first_name'],
                'book_title' => $circulation['title'],
                'due_date' => $circulation['due_date'],
                'days_left' => $daysUntilDue
            ]);
            
            $this->mailer->addAddress($circulation['email']);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true);
            
            if ($this->mailer->send()) {
                $this->logNotification($circulation['user_id'], 'email', 'due_reminder');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send overdue notification
     */
    public function sendOverdueNotification($circulationId)
    {
        try {
            $circulation = $this->db->query(
                "SELECT c.*, u.email, u.first_name, b.title, f.amount 
                 FROM circulation c
                 JOIN users u ON c.user_id = u.user_id
                 JOIN books b ON c.book_id = b.book_id
                 LEFT JOIN fines f ON c.circulation_id = f.circulation_id
                 WHERE c.circulation_id = ?",
                [$circulationId]
            )->fetchOne();
            
            $daysOverdue = $this->getDaysOverdue($circulation['due_date']);
            
            $subject = "⚠️ Book Overdue - Action Required";
            $body = $this->renderTemplate('overdue_notice', [
                'name' => $circulation['first_name'],
                'book_title' => $circulation['title'],
                'days_overdue' => $daysOverdue,
                'fine_amount' => $circulation['amount'] ?? 0
            ]);
            
            $this->mailer->addAddress($circulation['email']);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Overdue notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send borrowing confirmation
     */
    public function sendBorrowingConfirmation($circulationId)
    {
        try {
            $circulation = $this->db->query(
                "SELECT c.*, u.email, u.first_name, b.title, b.isbn
                 FROM circulation c
                 JOIN users u ON c.user_id = u.user_id
                 JOIN books b ON c.book_id = b.book_id
                 WHERE c.circulation_id = ?",
                [$circulationId]
            )->fetchOne();
            
            $subject = "✓ Book Borrowed Successfully";
            $body = $this->renderTemplate('borrow_confirmation', [
                'name' => $circulation['first_name'],
                'book_title' => $circulation['title'],
                'isbn' => $circulation['isbn'],
                'due_date' => $circulation['due_date'],
                'borrow_date' => $circulation['borrow_date']
            ]);
            
            $this->mailer->addAddress($circulation['email']);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Borrow confirmation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment receipt
     */
    public function sendPaymentReceipt($userId, $amount)
    {
        try {
            $user = $this->db->query(
                "SELECT * FROM users WHERE user_id = ?",
                [$userId]
            )->fetchOne();
            
            $subject = "💳 Payment Receipt";
            $body = $this->renderTemplate('payment_receipt', [
                'name' => $user['first_name'],
                'amount' => $amount,
                'date' => date('Y-m-d H:i'),
                'receipt_id' => 'RCP-' . time()
            ]);
            
            $this->mailer->addAddress($user['email']);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(true);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Payment receipt failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function renderTemplate($template, $data)
    {
        ob_start();
        include "templates/email/{$template}.php";
        return ob_get_clean();
    }
    
    private function getDaysUntilDue($dueDate)
    {
        return ceil((strtotime($dueDate) - time()) / 86400);
    }
    
    private function getDaysOverdue($dueDate)
    {
        return ceil((time() - strtotime($dueDate)) / 86400);
    }
    
    private function logNotification($userId, $type, $template)
    {
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'template' => $template,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }
}
?>
```

### 3.2 SMS Notifications (Twilio Integration)

```php
// SMS notification via Twilio
require_once '../vendor/autoload.php';
use Twilio\Rest\Client;

class SMSNotificationService
{
    private $twilioClient;
    
    public function __construct()
    {
        $accountSid = getenv('TWILIO_ACCOUNT_SID');
        $authToken = getenv('TWILIO_AUTH_TOKEN');
        $this->twilioClient = new Client($accountSid, $authToken);
    }
    
    /**
     * Send SMS reminder
     */
    public function sendSMSReminder($phoneNumber, $bookTitle, $daysLeft)
    {
        try {
            $message = $this->twilioClient->messages->create(
                $phoneNumber,
                [
                    "from" => getenv('TWILIO_PHONE_NUMBER'),
                    "body" => "📚 Reminder: '{$bookTitle}' due in {$daysLeft} days. 
                              Return or renew at library.edu"
                ]
            );
            
            return ['success' => true, 'message_id' => $message->sid];
            
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
```

### 3.3 In-App Notifications

```javascript
// File: public/js/notifications.js

class NotificationCenter {
    constructor() {
        this.notifications = [];
        this.initializeWebSocket();
    }
    
    /**
     * Initialize WebSocket for real-time notifications
     */
    initializeWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws/notifications`;
        
        this.ws = new WebSocket(wsUrl);
        
        this.ws.onmessage = (event) => {
            const notification = JSON.parse(event.data);
            this.displayNotification(notification);
            this.store(notification);
        };
    }
    
    /**
     * Display in-app notification
     */
    displayNotification(notification) {
        const container = document.getElementById('notification-container');
        
        const notifElement = document.createElement('div');
        notifElement.className = `notification notification-${notification.type}`;
        notifElement.innerHTML = `
            <div class="notification-content">
                <h4>${notification.title}</h4>
                <p>${notification.message}</p>
                <small>${new Date().toLocaleTimeString()}</small>
            </div>
            <button class="close-btn" onclick="this.parentElement.remove()">×</button>
        `;
        
        container.insertBefore(notifElement, container.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notifElement.parentElement) {
                notifElement.remove();
            }
        }, 5000);
    }
    
    /**
     * Store notification in database
     */
    async store(notification) {
        await fetch('backend/api/notifications.php?action=store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(notification)
        });
    }
}

// Initialize
const notificationCenter = new NotificationCenter();
```

---

## Part 4: QR Code & Barcode System

### 4.1 QR Code Generation

```php
// File: backend/api/qrcodes.php

<?php
require_once '../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Generate QR code for book
     */
    public function generateBookQR($bookId)
    {
        try {
            $book = $this->db->query(
                "SELECT isbn, title FROM books WHERE book_id = ?",
                [$bookId]
            )->fetchOne();
            
            if (!$book) {
                throw new Exception('Book not found');
            }
            
            // QR code data: ISBN + book details
            $qrData = json_encode([
                'type' => 'book',
                'book_id' => $bookId,
                'isbn' => $book['isbn'],
                'title' => $book['title']
            ]);
            
            $qrCode = new QrCode($qrData);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);
            
            $writer = new PngWriter();
            
            // Save QR code
            $filename = "qr_code_book_{$bookId}.png";
            $filepath = "../uploads/qrcodes/{$filename}";
            
            $writer->write($qrCode)->saveToFile($filepath);
            
            // Store QR metadata
            $this->db->insert('qr_codes', [
                'book_id' => $bookId,
                'type' => 'book',
                'filepath' => $filepath,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'qr_path' => $filepath,
                'book_id' => $bookId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate QR code for member card
     */
    public function generateMemberQR($userId)
    {
        try {
            $user = $this->db->query(
                "SELECT user_id, email FROM users WHERE user_id = ?",
                [$userId]
            )->fetchOne();
            
            $qrData = json_encode([
                'type' => 'member',
                'user_id' => $userId,
                'email' => $user['email']
            ]);
            
            $qrCode = new QrCode($qrData);
            $qrCode->setSize(300);
            
            $writer = new PngWriter();
            
            $filename = "member_qr_{$userId}.png";
            $filepath = "../uploads/qrcodes/{$filename}";
            
            $writer->write($qrCode)->saveToFile($filepath);
            
            return [
                'success' => true,
                'qr_path' => $filepath
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// API
if ($_GET['action'] === 'generate_book_qr') {
    $service = new QRCodeService($db);
    $result = $service->generateBookQR($_GET['book_id']);
    echo json_encode($result);
}
?>
```

### 4.2 QR Code Scanning

```javascript
// File: public/js/qrcode-scanner.js

class QRCodeScanner {
    constructor(videoElementId, canvasElementId) {
        this.video = document.getElementById(videoElementId);
        this.canvas = document.getElementById(canvasElementId);
        this.ctx = this.canvas.getContext('2d');
        this.scanning = false;
    }
    
    /**
     * Start camera stream
     */
    async startScanning() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            this.video.srcObject = stream;
            this.scanning = true;
            
            this.detectQR();
            
        } catch (error) {
            console.error('Camera access denied:', error);
            alert('Camera access is required for QR code scanning');
        }
    }
    
    /**
     * Detect QR code in video stream
     */
    detectQR() {
        if (!this.scanning) return;
        
        this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        
        // Using jsQR library for detection
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        
        if (code) {
            this.processQRCode(code.data);
            this.scanning = false;
            return;
        }
        
        requestAnimationFrame(() => this.detectQR());
    }
    
    /**
     * Process scanned QR code
     */
    processQRCode(qrData) {
        try {
            const data = JSON.parse(qrData);
            
            if (data.type === 'book') {
                this.handleBookScan(data);
            } else if (data.type === 'member') {
                this.handleMemberScan(data);
            }
            
        } catch (error) {
            console.error('Invalid QR code:', error);
        }
    }
    
    /**
     * Handle book QR scan (for borrowing)
     */
    handleBookScan(bookData) {
        const userId = getCurrentUserId();
        
        fetch('backend/api/circulation.php?action=quick_borrow', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: userId,
                book_id: bookData.book_id
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(`✓ Book "${bookData.title}" borrowed successfully`);
                this.startScanning(); // Scan next
            } else {
                showError(data.error);
            }
        });
    }
    
    /**
     * Handle member QR scan (for check-in)
     */
    handleMemberScan(memberData) {
        // Automatically populate member field in admin panel
        document.getElementById('member-id').value = memberData.user_id;
        document.getElementById('member-email').value = memberData.email;
        
        showNotification('Member scanned: ' + memberData.email);
    }
    
    stopScanning() {
        this.scanning = false;
        this.video.srcObject.getTracks().forEach(track => track.stop());
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const scanner = new QRCodeScanner('qr-video', 'qr-canvas');
    
    document.getElementById('startScanBtn').addEventListener('click', () => {
        scanner.startScanning();
    });
    
    document.getElementById('stopScanBtn').addEventListener('click', () => {
        scanner.stopScanning();
    });
});
```

---

## Part 5: Mobile API

### 5.1 Mobile-Optimized Endpoints

```php
// File: backend/api/mobile.php

<?php
require_once '../includes/functions.php';

class MobileAPI
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Get user dashboard data (lightweight)
     */
    public function getDashboard($userId)
    {
        $dashboard = [];
        
        // Active borrows
        $dashboard['active_borrows'] = $this->db->query(
            "SELECT c.circulation_id, b.title, b.cover_image, c.due_date,
                    DATEDIFF(c.due_date, CURDATE()) as days_left
             FROM circulation c
             JOIN books b ON c.book_id = b.book_id
             WHERE c.user_id = ? AND c.status = 'borrowed'
             ORDER BY c.due_date ASC",
            [$userId]
        )->fetchAll();
        
        // Pending fines
        $dashboard['fines'] = $this->db->query(
            "SELECT * FROM fines 
             WHERE user_id = ? AND status = 'unpaid'",
            [$userId]
        )->fetchAll();
        
        // Total fines
        $dashboard['total_fines'] = $this->db->query(
            "SELECT SUM(amount) as total FROM fines 
             WHERE user_id = ? AND status = 'unpaid'",
            [$userId]
        )->fetchOne()['total'] ?? 0;
        
        // Quick stats
        $dashboard['stats'] = [
            'books_borrowed' => count($dashboard['active_borrows']),
            'unpaid_fines' => count($dashboard['fines']),
            'overdue_books' => count(array_filter(
                $dashboard['active_borrows'],
                fn($b) => $b['days_left'] < 0
            ))
        ];
        
        return [
            'success' => true,
            'data' => $dashboard
        ];
    }
    
    /**
     * Quick search - simplified for mobile
     */
    public function quickSearch($query)
    {
        $results = $this->db->query(
            "SELECT book_id, title, author_name, cover_image, available_copies
             FROM book_details
             WHERE MATCH(title, author_name) AGAINST(? IN BOOLEAN MODE)
             LIMIT 10",
            [$query]
        )->fetchAll();
        
        return [
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ];
    }
    
    /**
     * Get book preview (minimal data)
     */
    public function getBookPreview($bookId)
    {
        $book = $this->db->query(
            "SELECT b.book_id, b.title, b.cover_image, b.available_copies,
                    a.author_name, p.publisher_name, b.isbn
             FROM books b
             LEFT JOIN authors a ON b.author_id = a.author_id
             LEFT JOIN publishers p ON b.publisher_id = p.publisher_id
             WHERE b.book_id = ?",
            [$bookId]
        )->fetchOne();
        
        return [
            'success' => true,
            'book' => $book
        ];
    }
    
    /**
     * Renew book - optimized for mobile
     */
    public function renewBook($circulationId)
    {
        try {
            $circulation = $this->db->query(
                "SELECT * FROM circulation WHERE circulation_id = ?",
                [$circulationId]
            )->fetchOne();
            
            if (!$circulation) {
                throw new Exception('Record not found');
            }
            
            $newDueDate = date('Y-m-d', strtotime('+14 days'));
            
            $this->db->update('circulation',
                [
                    'due_date' => $newDueDate,
                    'renewal_count' => $circulation['renewal_count'] + 1
                ],
                ['circulation_id' => $circulationId]
            );
            
            return [
                'success' => true,
                'new_due_date' => $newDueDate
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// REST API Handler
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $api = new MobileAPI($db);
    
    if ($action === 'dashboard') {
        $result = $api->getDashboard($_GET['user_id']);
    } elseif ($action === 'search') {
        $result = $api->quickSearch($_GET['q']);
    } elseif ($action === 'book') {
        $result = $api->getBookPreview($_GET['book_id']);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
```

---

## Part 6: Advanced Search & Recommendations

### 6.1 Advanced Search Filters

```php
// File: backend/api/advanced_search.php

<?php
class AdvancedSearchService
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Advanced search with multiple filters
     */
    public function search($filters)
    {
        $query = "SELECT * FROM book_details WHERE 1=1";
        $params = [];
        
        // Title filter
        if (!empty($filters['title'])) {
            $query .= " AND MATCH(title) AGAINST(? IN BOOLEAN MODE)";
            $params[] = $filters['title'];
        }
        
        // Author filter
        if (!empty($filters['author'])) {
            $query .= " AND author_name LIKE ?";
            $params[] = "%{$filters['author']}%";
        }
        
        // Publisher filter
        if (!empty($filters['publisher'])) {
            $query .= " AND publisher_name LIKE ?";
            $params[] = "%{$filters['publisher']}%";
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $query .= " AND category_id = ?";
            $params[] = $filters['category'];
        }
        
        // Year range
        if (!empty($filters['year_from'])) {
            $query .= " AND publication_year >= ?";
            $params[] = $filters['year_from'];
        }
        
        if (!empty($filters['year_to'])) {
            $query .= " AND publication_year <= ?";
            $params[] = $filters['year_to'];
        }
        
        // Availability
        if ($filters['available_only'] ?? false) {
            $query .= " AND available_copies > 0";
        }
        
        // Rating (if implemented)
        if (!empty($filters['min_rating'])) {
            $query .= " AND average_rating >= ?";
            $params[] = $filters['min_rating'];
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'title';
        $sortOrder = $filters['sort_order'] ?? 'ASC';
        $query .= " ORDER BY {$sortBy} {$sortOrder}";
        
        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 20;
        $offset = ($page - 1) * $perPage;
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $results = $this->db->query($query, $params)->fetchAll();
        
        return [
            'success' => true,
            'results' => $results,
            'count' => count($results),
            'page' => $page,
            'per_page' => $perPage
        ];
    }
}
?>
```

### 6.2 Book Recommendation Engine

```php
// File: backend/api/recommendations.php

<?php
class RecommendationEngine
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Collaborative filtering recommendations
     */
    public function getRecommendations($userId)
    {
        // Get user's reading history
        $userBooks = $this->db->query(
            "SELECT DISTINCT b.category_id FROM circulation c
             JOIN books b ON c.book_id = b.book_id
             WHERE c.user_id = ?",
            [$userId]
        )->fetchAll();
        
        $categoryIds = array_column($userBooks, 'category_id');
        
        if (empty($categoryIds)) {
            return $this->getPopularBooks();
        }
        
        // Find books in same categories not yet borrowed
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $query = "SELECT * FROM books b
                  WHERE b.category_id IN ({$placeholders})
                  AND b.book_id NOT IN (
                      SELECT book_id FROM circulation WHERE user_id = ?
                  )
                  ORDER BY average_rating DESC
                  LIMIT 10";
        
        $params = [...$categoryIds, $userId];
        
        $recommendations = $this->db->query($query, $params)->fetchAll();
        
        return [
            'success' => true,
            'recommendations' => $recommendations,
            'reason' => 'Based on your reading history'
        ];
    }
    
    /**
     * Trending books
     */
    public function getTrendingBooks()
    {
        $trending = $this->db->query(
            "SELECT b.*, COUNT(c.circulation_id) as borrow_count
             FROM books b
             JOIN circulation c ON b.book_id = c.book_id
             WHERE c.borrow_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY b.book_id
             ORDER BY borrow_count DESC
             LIMIT 10"
        )->fetchAll();
        
        return [
            'success' => true,
            'trending' => $trending,
            'period' => 'Last 30 days'
        ];
    }
    
    /**
     * Similar books
     */
    public function getSimilarBooks($bookId)
    {
        // Get current book's metadata
        $book = $this->db->query(
            "SELECT * FROM books WHERE book_id = ?",
            [$bookId]
        )->fetchOne();
        
        // Find similar books by author and category
        $similar = $this->db->query(
            "SELECT * FROM books 
             WHERE (author_id = ? OR category_id = ?)
             AND book_id != ?
             ORDER BY average_rating DESC
             LIMIT 5",
            [$book['author_id'], $book['category_id'], $bookId]
        )->fetchAll();
        
        return [
            'success' => true,
            'similar_books' => $similar
        ];
    }
    
    private function getPopularBooks()
    {
        $popular = $this->db->query(
            "SELECT * FROM books 
             ORDER BY average_rating DESC, total_borrows DESC
             LIMIT 10"
        )->fetchAll();
        
        return [
            'success' => true,
            'recommendations' => $popular,
            'reason' => 'Popular books'
        ];
    }
}
?>
```

---

## Part 7: Notification Scheduler

### 7.1 Automated Notifications

```php
// File: backend/cron/notification_scheduler.php

<?php
require_once '../includes/functions.php';

class NotificationScheduler
{
    private $db;
    private $notificationService;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->notificationService = new NotificationService($this->db);
    }
    
    /**
     * Run scheduled tasks (call via cron)
     */
    public function runScheduledTasks()
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled tasks...\n";
        
        // 1. Send due reminders (3 days before)
        $this->sendDueReminders();
        
        // 2. Send overdue notifications
        $this->sendOverdueNotifications();
        
        // 3. Generate fines for overdue books
        $this->generateOverdueFines();
        
        // 4. Send fine payment reminders
        $this->sendFineReminders();
        
        echo "[" . date('Y-m-d H:i:s') . "] Scheduled tasks completed.\n";
    }
    
    private function sendDueReminders()
    {
        // Books due in 3 days
        $books = $this->db->query(
            "SELECT circulation_id FROM circulation
             WHERE status = 'borrowed'
             AND DATEDIFF(due_date, CURDATE()) = 3"
        )->fetchAll();
        
        foreach ($books as $book) {
            $this->notificationService->sendDueReminder($book['circulation_id']);
        }
        
        echo "Sent " . count($books) . " due reminders.\n";
    }
    
    private function sendOverdueNotifications()
    {
        $books = $this->db->query(
            "SELECT circulation_id FROM circulation
             WHERE status = 'borrowed'
             AND due_date < CURDATE()
             AND last_overdue_notification IS NULL
             OR last_overdue_notification < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchAll();
        
        foreach ($books as $book) {
            $this->notificationService->sendOverdueNotification($book['circulation_id']);
            
            $this->db->update('circulation',
                ['last_overdue_notification' => date('Y-m-d H:i:s')],
                ['circulation_id' => $book['circulation_id']]
            );
        }
        
        echo "Sent " . count($books) . " overdue notifications.\n";
    }
    
    private function generateOverdueFines()
    {
        // For overdue books without fines
        $books = $this->db->query(
            "SELECT c.circulation_id, c.user_id, c.book_id, c.due_date
             FROM circulation c
             LEFT JOIN fines f ON c.circulation_id = f.circulation_id
             WHERE c.status = 'borrowed'
             AND c.due_date < CURDATE()
             AND f.fine_id IS NULL"
        )->fetchAll();
        
        foreach ($books as $book) {
            $daysOverdue = (time() - strtotime($book['due_date'])) / 86400;
            $fineAmount = min($daysOverdue * 10, 300); // Rs 10/day, max 300
            
            $this->db->insert('fines', [
                'circulation_id' => $book['circulation_id'],
                'user_id' => $book['user_id'],
                'book_id' => $book['book_id'],
                'amount' => $fineAmount,
                'status' => 'unpaid',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "Generated fines for " . count($books) . " overdue books.\n";
    }
    
    private function sendFineReminders()
    {
        $unpaidFines = $this->db->query(
            "SELECT DISTINCT user_id FROM fines
             WHERE status = 'unpaid'
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchAll();
        
        foreach ($unpaidFines as $fine) {
            // Send reminder email
            $this->notificationService->sendFineReminder($fine['user_id']);
        }
        
        echo "Sent fine reminders to " . count($unpaidFines) . " users.\n";
    }
}

// Execute
$scheduler = new NotificationScheduler();
$scheduler->runScheduledTasks();
?>
```

---

## Part 8: Feature Implementation Roadmap

```
IMPLEMENTATION PHASES
═══════════════════════════════════════════════════════

PHASE 1: Payment Integration (Week 1-2)
├─ Set up Stripe account
├─ Implement payment gateway API
├─ Add payment form to UI
├─ Test payment processing
└─ Deploy to production

PHASE 2: Notifications (Week 3-4)
├─ Configure SMTP server
├─ Implement email templates
├─ Integrate Twilio SMS
├─ Set up WebSocket for real-time
└─ Test all notification channels

PHASE 3: QR Codes (Week 5)
├─ Implement QR generation
├─ Add barcode scanning library
├─ Create scanning UI
├─ Test with physical books
└─ Deploy QR system

PHASE 4: Mobile API (Week 6)
├─ Create mobile endpoints
├─ Optimize for performance
├─ Add mobile-specific features
├─ Test on mobile devices
└─ Deploy mobile API

PHASE 5: Advanced Search (Week 7)
├─ Implement filter system
├─ Add recommendation engine
├─ Create trending books feature
└─ Optimize search performance

PHASE 6: Scheduler & Background Jobs (Week 8)
├─ Set up cron jobs
├─ Implement notification scheduler
├─ Add background job monitoring
└─ Deploy scheduler
```

**Total Implementation Time: 8-10 weeks**

**Next: Create Reference Material Documentation**

