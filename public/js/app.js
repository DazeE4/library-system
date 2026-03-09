// Bagmati Library Management System - Main Application
let currentUser = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    checkLoginStatus();
    setupEventListeners();
});

// Check if user is logged in
function checkLoginStatus() {
    const user = localStorage.getItem('user');
    if (user) {
        currentUser = JSON.parse(user);
        showPage('dashboard');
        loadDashboard();
        setupAdminPanel();
    } else {
        showPage('login-page');
    }
}

// Setup event listeners
function setupEventListeners() {
    document.getElementById('loginForm')?.addEventListener('submit', handleLogin);
    document.getElementById('registerForm')?.addEventListener('submit', handleRegister);
    document.getElementById('addBookForm')?.addEventListener('submit', handleAddBook);
}

// Authentication handlers
async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await authAPI.login({ username, password });
        
        if (response.success) {
            currentUser = response.data;
            localStorage.setItem('user', JSON.stringify(currentUser));
            showMessage('Login successful!', 'success');
            showPage('dashboard');
            loadDashboard();
            setupAdminPanel();
            document.getElementById('loginForm').reset();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Login failed: ' + error.message, 'danger');
    }
}

async function handleRegister(e) {
    e.preventDefault();
    
    const fullName = document.getElementById('regFullName').value;
    const username = document.getElementById('regUsername').value;
    const email = document.getElementById('regEmail').value;
    const phone = document.getElementById('regPhone').value;
    const password = document.getElementById('regPassword').value;
    
    try {
        const response = await authAPI.register({
            full_name: fullName,
            username: username,
            email: email,
            phone: phone,
            password: password,
            role: 'student'
        });
        
        if (response.success) {
            showMessage('Registration successful! Please login.', 'success');
            showLoginTab('login');
            document.getElementById('registerForm').reset();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Registration failed: ' + error.message, 'danger');
    }
}

// Logout function
function logout() {
    localStorage.removeItem('user');
    currentUser = null;
    showPage('login-page');
    document.getElementById('loginForm').reset();
    document.getElementById('registerForm').reset();
    showMessage('Logged out successfully', 'success');
}

// Page navigation
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => page.classList.add('hidden'));
    const page = document.getElementById(pageId);
    if (page) {
        page.classList.remove('hidden');
    }
    
    // Update admin navigation visibility
    const adminNav = document.getElementById('adminNav');
    if (adminNav) {
        adminNav.style.display = currentUser && isLibrarian(currentUser.role) ? 'block' : 'none';
    }
}

function showLoginTab(tab) {
    if (tab === 'login') {
        document.querySelector('.login-card').classList.remove('hidden');
        document.getElementById('registerCard').classList.add('hidden');
    } else {
        document.querySelector('.login-card').classList.add('hidden');
        document.getElementById('registerCard').classList.remove('hidden');
    }
}

function isLibrarian(role) {
    return role === 'librarian' || role === 'admin';
}

// Dashboard functions
async function loadDashboard() {
    if (!currentUser) return;
    
    try {
        // Load total books
        const booksResp = await booksAPI.listBooks({ limit: 1, offset: 0 });
        if (booksResp.success) {
            document.getElementById('totalBooks').textContent = 'Loading...';
        }
        
        // Load my borrowed books
        const myBooksResp = await circulationAPI.getMyBooks(currentUser.user_id);
        if (myBooksResp.success) {
            document.getElementById('myBorrowedCount').textContent = myBooksResp.data.length;
            
            // Calculate overdue
            let overdueCount = 0;
            const today = new Date();
            myBooksResp.data.forEach(book => {
                const dueDate = new Date(book.due_date);
                if (today > dueDate) overdueCount++;
            });
            document.getElementById('overdueCount').textContent = overdueCount;
        }
        
        // Load pending fines
        const finesResp = await finesAPI.getTotalFines(currentUser.user_id);
        if (finesResp.success) {
            document.getElementById('pendingFines').textContent = formatCurrency(finesResp.data.total_fines || 0);
        }
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

// Books functions
async function searchBooks() {
    const searchText = document.getElementById('bookSearch').value;
    const genre = document.getElementById('genreFilter').value;
    
    try {
        const response = await booksAPI.search(searchText, 'all');
        
        if (response.success) {
            displayBooks(response.data, 'booksGrid');
        } else {
            document.getElementById('booksGrid').innerHTML = '<p>No books found</p>';
        }
    } catch (error) {
        console.error('Error searching books:', error);
    }
}

async function displayBooks(books, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    if (books.length === 0) {
        container.innerHTML = '<p class="text-center">No books found</p>';
        return;
    }
    
    books.forEach(book => {
        const availClass = getAvailabilityClass(book.available || 0, book.total || 0);
        const availText = getAvailabilityText(book.available || 0, book.total || 0);
        
        const card = document.createElement('div');
        card.className = 'book-card';
        card.innerHTML = `
            <div class="book-card-image">📚</div>
            <div class="book-card-content">
                <h4>${book.title}</h4>
                <p class="book-card-author">${book.author}</p>
                <div class="book-card-meta">
                    <span class="availability ${availClass}">${availText}</span>
                </div>
                ${book.genre ? `<p style="font-size: 0.85rem; color: #666;">${book.genre}</p>` : ''}
                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button class="btn btn-small btn-primary" onclick="viewBookDetails(${book.book_id})">View</button>
                    ${(book.available || 0) > 0 ? `<button class="btn btn-small btn-success" onclick="borrowBook(${book.book_id})">Borrow</button>` : ''}
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

async function viewBookDetails(bookId) {
    try {
        const response = await booksAPI.getBook(bookId);
        
        if (response.success) {
            const book = response.data;
            const modal = document.getElementById('bookModal');
            const body = document.getElementById('bookModalBody');
            
            body.innerHTML = `
                <p><strong>Title:</strong> ${book.title}</p>
                <p><strong>Author:</strong> ${book.author}</p>
                ${book.genre ? `<p><strong>Genre:</strong> ${book.genre}</p>` : ''}
                ${book.isbn ? `<p><strong>ISBN:</strong> ${book.isbn}</p>` : ''}
                ${book.publisher ? `<p><strong>Publisher:</strong> ${book.publisher}</p>` : ''}
                ${book.publication_date ? `<p><strong>Publication Date:</strong> ${formatDate(book.publication_date)}</p>` : ''}
                <p><strong>Available Copies:</strong> ${book.available_copies} of ${book.total_copies}</p>
                ${book.description ? `<p><strong>Description:</strong> ${book.description}</p>` : ''}
                <div style="margin-top: 1.5rem;">
                    ${(book.available_copies || 0) > 0 ? `
                        <button class="btn btn-primary" onclick="borrowBook(${book.book_id}); closeModal('bookModal')">Borrow This Book</button>
                    ` : `
                        <p style="color: var(--danger-color);">This book is currently unavailable.</p>
                    `}
                </div>
            `;
            
            modal.classList.add('active');
        }
    } catch (error) {
        console.error('Error loading book details:', error);
    }
}

async function borrowBook(bookId) {
    if (!currentUser) {
        showMessage('Please login first', 'danger');
        return;
    }
    
    try {
        const response = await circulationAPI.borrowBook(currentUser.user_id, bookId);
        
        if (response.success) {
            showMessage(`Book borrowed successfully! Due date: ${formatDate(response.data.due_date)}`, 'success');
            loadDashboard();
            loadMyBooks();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error borrowing book: ' + error.message, 'danger');
    }
}

async function loadMyBooks() {
    if (!currentUser) return;
    
    try {
        const response = await circulationAPI.getMyBooks(currentUser.user_id);
        
        if (response.success) {
            const container = document.getElementById('myBooksGrid');
            container.innerHTML = '';
            
            if (response.data.length === 0) {
                container.innerHTML = '<p class="text-center">You have not borrowed any books</p>';
                return;
            }
            
            response.data.forEach(book => {
                const daysRemaining = getDaysRemaining(book.due_date);
                const isOverdue = daysRemaining < 0;
                
                const card = document.createElement('div');
                card.className = 'book-card';
                card.innerHTML = `
                    <div class="book-card-image">📖</div>
                    <div class="book-card-content">
                        <h4>${book.title}</h4>
                        <p class="book-card-author">${book.author}</p>
                        <p style="font-size: 0.85rem; margin: 0.5rem 0;">
                            <strong>Issued:</strong> ${formatDate(book.issue_date)}
                        </p>
                        <p style="font-size: 0.85rem; margin: 0.5rem 0;">
                            <strong>Due:</strong> <span style="color: ${isOverdue ? 'var(--danger-color)' : 'inherit'}">${formatDate(book.due_date)}</span>
                        </p>
                        <p style="font-size: 0.85rem; margin: 0.5rem 0;">
                            <strong>Days Remaining:</strong> <span style="color: ${isOverdue ? 'var(--danger-color)' : 'var(--success-color)'}">${isOverdue ? 'OVERDUE (' + Math.abs(daysRemaining) + ' days)' : daysRemaining + ' days'}</span>
                        </p>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                            <button class="btn btn-small btn-secondary" onclick="returnBook(${book.circulation_id})">Return</button>
                            <button class="btn btn-small btn-primary" onclick="renewBook(${book.circulation_id})">Renew</button>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }
    } catch (error) {
        console.error('Error loading my books:', error);
    }
}

async function returnBook(circulationId) {
    try {
        const response = await circulationAPI.returnBook(circulationId, 'good');
        
        if (response.success) {
            showMessage('Book returned successfully!' + (response.data.fine_amount > 0 ? ` Fine: ${formatCurrency(response.data.fine_amount)}` : ''), 'success');
            loadMyBooks();
            loadDashboard();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error returning book: ' + error.message, 'danger');
    }
}

async function renewBook(circulationId) {
    try {
        const response = await circulationAPI.renewBook(circulationId);
        
        if (response.success) {
            showMessage(`Book renewed! New due date: ${formatDate(response.data.new_due_date)}`, 'success');
            loadMyBooks();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error renewing book: ' + error.message, 'danger');
    }
}

// Fines functions
async function loadMyFines() {
    if (!currentUser) return;
    
    try {
        const finesResp = await finesAPI.getFines(currentUser.user_id, 'all');
        const totalResp = await finesAPI.getTotalFines(currentUser.user_id);
        
        if (finesResp.success) {
            const tbody = document.getElementById('finesTableBody');
            tbody.innerHTML = '';
            
            if (finesResp.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No fines</td></tr>';
                document.getElementById('payAllBtn').style.display = 'none';
            } else {
                finesResp.data.forEach(fine => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${fine.title}</td>
                        <td>${formatCurrency(fine.fine_amount)}</td>
                        <td>${fine.fine_reason}</td>
                        <td><span class="availability ${fine.status === 'paid' ? 'available' : 'unavailable'}">${fine.status}</span></td>
                        <td>${fine.status === 'pending' ? `<button class="btn btn-small btn-primary" onclick="payFine(${fine.fine_id})">Pay</button>` : '-'}</td>
                    `;
                    tbody.appendChild(row);
                });
                
                const pendingCount = finesResp.data.filter(f => f.status === 'pending').length;
                document.getElementById('payAllBtn').style.display = pendingCount > 0 ? 'block' : 'none';
            }
            
            if (totalResp.success) {
                document.getElementById('totalFinesAmount').textContent = formatCurrency(totalResp.data.total_fines || 0);
            }
        }
    } catch (error) {
        console.error('Error loading fines:', error);
    }
}

async function payFine(fineId) {
    try {
        const response = await finesAPI.payFine(fineId, 'cash');
        
        if (response.success) {
            showMessage('Fine paid successfully!', 'success');
            loadMyFines();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error paying fine: ' + error.message, 'danger');
    }
}

async function payAllFines() {
    try {
        const response = await finesAPI.payMultipleFines(currentUser.user_id, 'cash');
        
        if (response.success) {
            showMessage('All fines paid successfully!', 'success');
            loadMyFines();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error paying fines: ' + error.message, 'danger');
    }
}

// Admin panel functions
function setupAdminPanel() {
    if (!currentUser || !isLibrarian(currentUser.role)) {
        return;
    }
    
    const adminNav = document.getElementById('adminNav');
    if (adminNav) {
        adminNav.style.display = 'block';
    }
}

function switchAdminTab(tabName) {
    document.querySelectorAll('.admin-tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    const tab = document.getElementById(tabName);
    if (tab) {
        tab.classList.remove('hidden');
    }
    
    const btn = event.target;
    if (btn) {
        btn.classList.add('active');
    }
    
    // Load data based on tab
    if (tabName === 'overview') {
        loadAdminOverview();
    } else if (tabName === 'books-mgmt') {
        loadBooksInventory();
    } else if (tabName === 'users-mgmt') {
        loadUsers();
    } else if (tabName === 'circulation') {
        loadCirculation();
    }
}

async function loadAdminOverview() {
    try {
        const inventoryResp = await reportsAPI.getInventoryReport();
        const overdueResp = await reportsAPI.getOverdueItems({ limit: 1, offset: 0 });
        const finesResp = await finesAPI.getAllPendingFines({ limit: 1, offset: 0 });
        const usersResp = await authAPI.listUsers({ is_active: 1 });
        
        if (inventoryResp.success) {
            const inv = inventoryResp.data;
            document.getElementById('adminTotalBooks').textContent = inv.total_books;
            document.getElementById('adminAvailableCopies').textContent = inv.available_copies;
            document.getElementById('adminBorrowedCopies').textContent = inv.borrowed_copies;
        }
        
        if (overdueResp.success) {
            document.getElementById('adminOverdueCount').textContent = overdueResp.data.length;
        }
        
        if (usersResp.success) {
            document.getElementById('adminActiveUsers').textContent = usersResp.data.length;
        }
    } catch (error) {
        console.error('Error loading admin overview:', error);
    }
}

async function handleAddBook(e) {
    e.preventDefault();
    
    if (!currentUser || !isLibrarian(currentUser.role)) {
        showMessage('You do not have permission to add books', 'danger');
        return;
    }
    
    const data = {
        title: document.getElementById('bookTitle').value,
        author: document.getElementById('bookAuthor').value,
        genre: document.getElementById('bookGenre').value,
        isbn: document.getElementById('bookISBN').value,
        publisher: document.getElementById('bookPublisher').value,
        publication_date: document.getElementById('bookPubDate').value,
        description: document.getElementById('bookDescription').value,
        total_copies: document.getElementById('bookCopies').value,
        user_id: currentUser.user_id
    };
    
    try {
        const response = await booksAPI.addBook(data);
        
        if (response.success) {
            showMessage('Book added successfully!', 'success');
            document.getElementById('addBookForm').reset();
            loadBooksInventory();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error adding book: ' + error.message, 'danger');
    }
}

async function loadBooksInventory() {
    try {
        const response = await booksAPI.listBooks({ limit: 100, offset: 0 });
        
        if (response.success) {
            const tbody = document.getElementById('booksInventoryBody');
            tbody.innerHTML = '';
            
            response.data.forEach(book => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${book.title}</td>
                    <td>${book.author}</td>
                    <td>${book.total_copies}</td>
                    <td>${book.available_copies}</td>
                    <td>${(book.total_copies || 0) - (book.available_copies || 0)}</td>
                    <td><span class="availability ${getAvailabilityClass(book.available_copies, book.total_copies)}">${book.status}</span></td>
                    <td>
                        <button class="btn btn-small btn-secondary" onclick="editBook(${book.book_id})">Edit</button>
                        <button class="btn btn-small btn-danger" onclick="deleteBook(${book.book_id})">Delete</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Error loading books inventory:', error);
    }
}

async function loadUsers() {
    try {
        const response = await authAPI.listUsers({});
        
        if (response.success) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            
            response.data.forEach(user => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${user.username}</td>
                    <td>${user.email}</td>
                    <td>${user.full_name}</td>
                    <td>${user.role}</td>
                    <td><span class="availability ${user.is_active ? 'available' : 'unavailable'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>${formatDate(user.registration_date)}</td>
                    <td>
                        <button class="btn btn-small btn-${user.is_active ? 'danger' : 'success'}" onclick="toggleUserStatus(${user.user_id}, ${!user.is_active})">
                            ${user.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

async function loadCirculation() {
    try {
        const response = await circulationAPI.getAllBorrowed({ limit: 100, offset: 0 });
        
        if (response.success) {
            const tbody = document.getElementById('circulationTableBody');
            tbody.innerHTML = '';
            
            response.data.forEach(circ => {
                const daysRemaining = getDaysRemaining(circ.due_date);
                const isOverdue = daysRemaining < 0;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${circ.title}</td>
                    <td>${circ.username}</td>
                    <td>${formatDate(circ.issue_date)}</td>
                    <td>${formatDate(circ.due_date)}</td>
                    <td style="color: ${isOverdue ? 'var(--danger-color)' : 'inherit'}">${isOverdue ? 'OVERDUE (' + Math.abs(daysRemaining) + ' days)' : daysRemaining + ' days'}</td>
                    <td>
                        <button class="btn btn-small btn-secondary" onclick="viewCirculation(${circ.circulation_id})">Details</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Error loading circulation:', error);
    }
}

async function filterOverdue() {
    try {
        const response = await circulationAPI.getAllBorrowed({ overdueOnly: true, limit: 100, offset: 0 });
        
        if (response.success) {
            const tbody = document.getElementById('circulationTableBody');
            tbody.innerHTML = '';
            
            response.data.forEach(circ => {
                const daysRemaining = getDaysRemaining(circ.due_date);
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${circ.title}</td>
                    <td>${circ.username}</td>
                    <td>${formatDate(circ.issue_date)}</td>
                    <td>${formatDate(circ.due_date)}</td>
                    <td style="color: var(--danger-color);">OVERDUE (${Math.abs(daysRemaining)} days)</td>
                    <td>
                        <button class="btn btn-small btn-danger" onclick="sendOverdueNotification(${circ.user_id})">Send Notice</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Error loading overdue items:', error);
    }
}

async function toggleUserStatus(userId, newStatus) {
    try {
        const response = await authAPI.toggleUserStatus(userId, newStatus ? 1 : 0);
        
        if (response.success) {
            showMessage('User status updated', 'success');
            loadUsers();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error updating user: ' + error.message, 'danger');
    }
}

async function deleteBook(bookId) {
    if (!confirm('Are you sure you want to delete this book?')) return;
    
    try {
        const response = await booksAPI.deleteBook(bookId, currentUser.user_id);
        
        if (response.success) {
            showMessage('Book deleted successfully', 'success');
            loadBooksInventory();
        } else {
            showMessage(response.message, 'danger');
        }
    } catch (error) {
        showMessage('Error deleting book: ' + error.message, 'danger');
    }
}

// Reports functions
async function loadReport(reportType) {
    const content = document.getElementById('reportContent');
    content.innerHTML = '<p class="loading">Loading report...</p>';
    
    try {
        let response;
        
        switch (reportType) {
            case 'usage':
                response = await reportsAPI.getUsageReport();
                if (response.success) {
                    const data = response.data;
                    content.innerHTML = `
                        <h4>Usage Report</h4>
                        <p><strong>Period:</strong> ${formatDate(data.period.start)} to ${formatDate(data.period.end)}</p>
                        <table class="admin-table">
                            <tr><td>Total Borrowed</td><td>${data.total_borrowed}</td></tr>
                            <tr><td>Total Returned</td><td>${data.total_returned}</td></tr>
                            <tr><td>Fines Collected</td><td>${formatCurrency(data.fines_collected)}</td></tr>
                            <tr><td>Active Borrowers</td><td>${data.active_borrowers}</td></tr>
                            <tr><td>Overdue Items</td><td><span style="color: var(--danger-color);">${data.overdue_count}</span></td></tr>
                        </table>
                    `;
                }
                break;
                
            case 'popular':
                response = await reportsAPI.getPopularBooks(20);
                if (response.success) {
                    let html = '<h4>Most Popular Books (Last 90 Days)</h4><table class="admin-table"><thead><tr><th>Title</th><th>Author</th><th>Times Borrowed</th></tr></thead><tbody>';
                    response.data.forEach(book => {
                        html += `<tr><td>${book.title}</td><td>${book.author}</td><td>${book.times_borrowed}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                }
                break;
                
            case 'overdue':
                response = await reportsAPI.getOverdueItems({ limit: 50, offset: 0 });
                if (response.success) {
                    let html = '<h4>Overdue Items</h4><table class="admin-table"><thead><tr><th>Book</th><th>Borrower</th><th>Due Date</th><th>Days Overdue</th></tr></thead><tbody>';
                    response.data.forEach(item => {
                        html += `<tr><td>${item.title}</td><td>${item.username}</td><td>${formatDate(item.due_date)}</td><td style="color: var(--danger-color);">${item.days_overdue}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                }
                break;
                
            case 'inventory':
                response = await reportsAPI.getInventoryReport();
                if (response.success) {
                    const data = response.data;
                    let html = '<h4>Inventory Report</h4><table class="admin-table"><tr><td>Total Books</td><td>' + data.total_books + '</td></tr><tr><td>Available Copies</td><td>' + data.available_copies + '</td></tr><tr><td>Borrowed Copies</td><td>' + data.borrowed_copies + '</td></tr><tr><td>Damaged Books</td><td>' + data.damaged_books + '</td></tr><tr><td>Lost Books</td><td>' + data.lost_books + '</td></tr></table>';
                    html += '<h4 style="margin-top: 1.5rem;">Books by Genre</h4><table class="admin-table"><thead><tr><th>Genre</th><th>Count</th></tr></thead><tbody>';
                    data.books_by_genre.forEach(genre => {
                        html += `<tr><td>${genre.genre || 'Unknown'}</td><td>${genre.count}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    content.innerHTML = html;
                }
                break;
                
            case 'engagement':
                response = await reportsAPI.getMemberEngagement();
                if (response.success) {
                    const data = response.data;
                    content.innerHTML = `
                        <h4>Member Engagement Report</h4>
                        <table class="admin-table">
                            <tr><td>Active Members (Last 30 Days)</td><td>${data.active_members_30_days}</td></tr>
                            <tr><td>Inactive Members (Last 90 Days)</td><td>${data.inactive_members_90_days}</td></tr>
                            <tr><td>New Members (Last 30 Days)</td><td>${data.new_members_30_days}</td></tr>
                        </table>
                    `;
                }
                break;
        }
        
        // Update active tab button
        document.querySelectorAll('.report-tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
    } catch (error) {
        content.innerHTML = '<p style="color: var(--danger-color);">Error loading report: ' + error.message + '</p>';
    }
}

// Modal functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('bookModal');
    if (event.target === modal) {
        modal.classList.remove('active');
    }
};

// Load initial data when switching pages
window.addEventListener('click', (e) => {
    // Handle page navigation via nav menu
    if (e.target.tagName === 'A' && e.target.onclick) {
        // Page navigation handled by onclick handlers
        if (e.target.onclick.toString().includes('showPage')) {
            const pageMatch = e.target.onclick.toString().match(/showPage\('([^']+)'\)/);
            if (pageMatch) {
                const pageId = pageMatch[1];
                if (pageId === 'books') {
                    setTimeout(() => {
                        const categories = booksAPI.getCategories();
                        const genreSelect = document.getElementById('genreFilter');
                    }, 100);
                }
                if (pageId === 'mybooks') {
                    setTimeout(() => loadMyBooks(), 100);
                }
                if (pageId === 'fines') {
                    setTimeout(() => loadMyFines(), 100);
                }
            }
        }
    }
});

// Load books when page loads
document.addEventListener('click', (e) => {
    if (e.target.textContent === 'Books') {
        setTimeout(() => searchBooks(), 100);
    }
});
