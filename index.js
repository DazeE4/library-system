// ═══════════════════════════════════════════════════════════════
// ADVANCED LIBRARY MANAGEMENT SYSTEM - CLIENT SIDE
// ═══════════════════════════════════════════════════════════════

// ═══════════════════════ GLOBAL STATE ═══════════════════════
let currentUser = null;
let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
let borrowedBooks = JSON.parse(localStorage.getItem('borrowedBooks')) || [];
let bookCache = {};

// ═══════════════════════ PAGE NAVIGATION ═══════════════════════
/**
 * Navigate to different pages/sections
 * @param {string} pageId - The ID of the page to show
 */
function showPage(pageId) {
    // Hide all sections with fade effect
    document.querySelectorAll('section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const selectedPage = document.getElementById(pageId);
    if (selectedPage) {
        selectedPage.classList.remove('hidden');
        selectedPage.style.animation = 'fadeIn 0.3s ease-in';
        
        // Load page-specific content
        switch(pageId) {
            case 'home':
                loadFeaturedBooks();
                break;
            case 'search':
                document.getElementById('searchInput').focus();
                break;
            case 'mybooks':
                loadMyBooks();
                break;
            case 'saved':
                loadSavedBooks();
                break;
        }
    }
}

// ═══════════════════════ BOOK LOADING & RENDERING ═══════════════════════
/**
 * Load featured books from API
 */
async function loadFeaturedBooks() {
    try {
        const response = await fetch('/api/books.php?action=list&limit=12');
        const data = await response.json();
        
        if (data.status === 'success') {
            const bookGrid = document.getElementById('bookGrid');
            bookGrid.innerHTML = '';
            
            data.books.forEach(book => {
                const bookCard = createBookCard(book);
                bookGrid.appendChild(bookCard);
            });
            
            // Add loading animation
            bookGrid.querySelectorAll('.card').forEach((card, index) => {
                card.style.animation = `fadeIn 0.4s ease-in ${index * 0.05}s`;
                card.style.animationFillMode = 'both';
            });
        }
    } catch (error) {
        console.error('Error loading books:', error);
        document.getElementById('bookGrid').innerHTML = '<p style="color: #d32f2f;">❌ Error loading books</p>';
    }
}

/**
 * Create a book card element with interactive features
 * @param {object} book - Book data object
 * @returns {HTMLElement} Book card element
 */
function createBookCard(book) {
    const card = document.createElement('div');
    card.className = 'card';
    card.dataset.bookId = book.id;
    
    const isInWishlist = wishlist.some(b => b.id === book.id);
    const isBorrowed = borrowedBooks.some(b => b.id === book.id);
    
    const placeholderImage = `https://via.placeholder.com/280x400?text=${encodeURIComponent(book.title.substring(0, 20))}&fontsize=12`;
    
    card.innerHTML = `
        <div class="card-image">
            <img src="${book.cover_image || placeholderImage}" 
                 alt="${book.title}" 
                 onerror="this.src='${placeholderImage}'">
            <!-- Instagram/TikTok Style Save Button -->
            <button class="save-btn ${isInWishlist ? 'saved' : ''}" 
                    onclick="toggleWishlistButton(${book.id}, event)" 
                    title="Save this book">
                <svg class="save-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
            </button>
            <div class="card-overlay">
                <button class="action-btn ${isBorrowed ? 'disabled' : ''}" 
                        onclick="borrowBook(${book.id})" 
                        ${isBorrowed ? 'disabled' : ''}>
                    <i class="fas fa-${isBorrowed ? 'check' : 'plus'}"></i> 
                    ${isBorrowed ? 'Borrowed' : 'Borrow'}
                </button>
            </div>
        </div>
        <div class="card-content">
            <h3 class="card-title" title="${book.title}">${book.title}</h3>
            <p class="card-author">✍️ ${book.author || 'Unknown Author'}</p>
            <div class="card-meta">
                <span class="badge"><i class="fas fa-tag"></i> ${book.genre || 'N/A'}</span>
            </div>
            <div class="card-footer">
                <small><i class="fas fa-book-open"></i> ${book.pages || '0'} pages</small>
                <small><i class="fas fa-star"></i> ${book.rating || '4.5'}/5</small>
            </div>
        </div>
    `;
    
    return card;
}

// ═══════════════════════ SEARCH FUNCTIONALITY ═══════════════════════
/**
 * Search books based on criteria
 */
async function searchBooks() {
    const searchType = document.getElementById('searchType').value;
    const searchInput = document.getElementById('searchInput').value.trim();
    
    if (!searchInput) {
        alert('🔍 Please enter a search term');
        return;
    }
    
    const resultsContainer = document.getElementById('searchResults');
    resultsContainer.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">🔄 Searching...</p>';
    
    try {
        const url = `/api/books.php?action=search&type=${searchType}&query=${encodeURIComponent(searchInput)}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success' && data.books.length > 0) {
            resultsContainer.innerHTML = `
                <p style="margin-bottom: 1rem; font-weight: 500;">
                    <i class="fas fa-check-circle" style="color: var(--success)"></i> 
                    Found ${data.books.length} result(s)
                </p>
            `;
            
            const grid = document.createElement('div');
            grid.className = 'grid';
            
            data.books.forEach((book, index) => {
                const card = createBookCard(book);
                card.style.animation = `fadeIn 0.4s ease-in ${index * 0.05}s`;
                card.style.animationFillMode = 'both';
                grid.appendChild(card);
            });
            
            resultsContainer.appendChild(grid);
        } else {
            resultsContainer.innerHTML = '<p style="color: var(--text-secondary); text-align: center;"><i class="fas fa-search"></i> No books found matching your criteria</p>';
        }
    } catch (error) {
        console.error('Search error:', error);
        resultsContainer.innerHTML = '<p style="color: #d32f2f; text-align: center;">❌ Error performing search</p>';
    }
}

/**
 * Reset search fields
 */
function resetSearch() {
    document.getElementById('searchType').value = 'title';
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('searchInput').focus();
}

// ═══════════════════════ WISHLIST MANAGEMENT ═══════════════════════
/**
 * Toggle book in wishlist
 * @param {number} bookId - Book ID
 * @param {Event} event - Click event
 */
function toggleWishlist(bookId, event) {
    event.stopPropagation();
    
    const index = wishlist.findIndex(b => b.id === bookId);
    const element = event.currentTarget;
    
    if (index > -1) {
        wishlist.splice(index, 1);
        element.classList.remove('active');
        showNotification('📌 Removed from wishlist', 'info');
    } else {
        wishlist.push({ id: bookId, addedAt: new Date().toISOString() });
        element.classList.add('active');
        showNotification('❤️ Added to wishlist', 'success');
    }
    
    localStorage.setItem('wishlist', JSON.stringify(wishlist));
    element.querySelector('i').classList.toggle('filled');
}

/**
 * Toggle wishlist with Instagram/TikTok save button style
 */
function toggleWishlistButton(bookId, event) {
    event.stopPropagation();
    
    const saveBtn = event.currentTarget;
    const isInWishlist = wishlist.some(b => b.id === bookId);
    
    if (isInWishlist) {
        // Remove from wishlist
        wishlist = wishlist.filter(b => b.id !== bookId);
        saveBtn.classList.remove('saved');
        showNotification('Removed from saved', 'info');
    } else {
        // Add to wishlist
        wishlist.push({ id: bookId, addedAt: new Date().toISOString() });
        saveBtn.classList.add('saved');
        showNotification('✓ Saved to collection', 'success');
        
        // Haptic feedback on mobile
        if (navigator.vibrate) {
            navigator.vibrate(50);
        }
    }
    
    // Save to localStorage
    localStorage.setItem('wishlist', JSON.stringify(wishlist));
}

/**
 * Load saved books from wishlist
 */
async function loadSavedBooks() {
    const container = document.getElementById('savedBooks');
    
    if (wishlist.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 2rem;"><i class="fas fa-inbox"></i> Your wishlist is empty</p>';
        return;
    }
    
    container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">⏳ Loading...</p>';
    container.innerHTML = '';
    
    let loadedCount = 0;
    
    for (const item of wishlist) {
        try {
            const response = await fetch(`/api/books.php?action=get&id=${item.id}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const card = createBookCard(data.book);
                card.style.animation = `fadeIn 0.4s ease-in ${loadedCount * 0.05}s`;
                card.style.animationFillMode = 'both';
                container.appendChild(card);
                loadedCount++;
            }
        } catch (error) {
            console.error('Error loading book:', error);
        }
    }
    
    if (loadedCount === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;"><i class="fas fa-inbox"></i> Your wishlist is empty</p>';
    }
}

// ═══════════════════════ BOOK BORROWING ═══════════════════════
/**
 * Borrow a book
 * @param {number} bookId - Book ID to borrow
 */
async function borrowBook(bookId) {
    if (!currentUser) {
        showNotification('🔐 Please log in to borrow books', 'warning');
        setTimeout(() => showPage('login'), 1500);
        return;
    }
    
    try {
        const response = await fetch('/api/circulation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'borrow',
                user_id: currentUser.id,
                book_id: bookId
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            borrowedBooks.push({ 
                id: bookId, 
                borrowDate: new Date().toISOString(),
                dueDate: data.due_date
            });
            localStorage.setItem('borrowedBooks', JSON.stringify(borrowedBooks));
            showNotification('✅ Book borrowed successfully!', 'success');
            
            // Update card visually
            setTimeout(loadFeaturedBooks, 800);
        } else {
            showNotification('❌ ' + (data.message || 'Error borrowing book'), 'error');
        }
    } catch (error) {
        console.error('Error borrowing book:', error);
        showNotification('❌ Error borrowing book', 'error');
    }
}

/**
 * Load user's borrowed books
 */
async function loadMyBooks() {
    const container = document.getElementById('myBooks');
    
    if (borrowedBooks.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 2rem;"><i class="fas fa-inbox"></i> You haven\'t borrowed any books yet</p>';
        return;
    }
    
    container.innerHTML = '';
    
    let loadedCount = 0;
    
    for (const item of borrowedBooks) {
        try {
            const response = await fetch(`/api/books.php?action=get&id=${item.id}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const card = createBookCard(data.book);
                
                const returnBtn = document.createElement('button');
                returnBtn.className = 'action-btn secondary';
                returnBtn.innerHTML = '<i class="fas fa-undo"></i> Return';
                returnBtn.onclick = () => returnBook(item.id);
                
                card.querySelector('.card-overlay').appendChild(returnBtn);
                card.style.animation = `fadeIn 0.4s ease-in ${loadedCount * 0.05}s`;
                card.style.animationFillMode = 'both';
                
                container.appendChild(card);
                loadedCount++;
            }
        } catch (error) {
            console.error('Error loading book:', error);
        }
    }
}

/**
 * Return a borrowed book
 * @param {number} bookId - Book ID to return
 */
async function returnBook(bookId) {
    if (!currentUser) {
        showNotification('🔐 Please log in', 'warning');
        return;
    }
    
    try {
        const response = await fetch('/api/circulation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'return',
                user_id: currentUser.id,
                book_id: bookId
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            const index = borrowedBooks.findIndex(b => b.id === bookId);
            if (index > -1) {
                borrowedBooks.splice(index, 1);
                localStorage.setItem('borrowedBooks', JSON.stringify(borrowedBooks));
            }
            showNotification('✅ Book returned successfully!', 'success');
            setTimeout(loadMyBooks, 800);
        } else {
            showNotification('❌ ' + (data.message || 'Error returning book'), 'error');
        }
    } catch (error) {
        console.error('Error returning book:', error);
        showNotification('❌ Error returning book', 'error');
    }
}

// ═══════════════════════ BOOK FILTERING ═══════════════════════
/**
 * Filter books by status
 * @param {string} filter - Filter type (all, active, returning-soon, overdue)
 */
function filterBooks(filter) {
    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter logic based on book status
    const books = borrowedBooks;
    let filtered = books;
    
    switch(filter) {
        case 'active':
            filtered = books.filter(b => !b.isReturning && !b.isOverdue);
            break;
        case 'returning-soon':
            filtered = books.filter(b => b.isReturning);
            break;
        case 'overdue':
            filtered = books.filter(b => b.isOverdue);
            break;
    }
    
    // Visual feedback
    showNotification(`📚 Showing ${filtered.length} book(s)`, 'info');
}

// ═══════════════════════ AUTHENTICATION ═══════════════════════
/**
 * Handle user login
 */
async function handleLogin() {
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    
    if (!username || !password) {
        showNotification('⚠️ Please enter username and password', 'warning');
        return;
    }
    
    try {
        const response = await fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            currentUser = data.user;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            showNotification('✅ Login successful!', 'success');
            
            // Clear form
            document.getElementById('loginUsername').value = '';
            document.getElementById('loginPassword').value = '';
            
            setTimeout(() => showPage('home'), 1500);
        } else {
            showNotification('❌ ' + (data.message || 'Login failed'), 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showNotification('❌ Error during login', 'error');
    }
}

// ═══════════════════════ UTILITY FUNCTIONS ═══════════════════════
/**
 * Show notification message
 * @param {string} message - Message to display
 * @param {string} type - Type (success, error, warning, info)
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        background: ${
            type === 'success' ? '#4CAF50' :
            type === 'error' ? '#d32f2f' :
            type === 'warning' ? '#FF9800' : '#2196F3'
        };
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Format date to readable format
 * @param {string} dateString - Date string
 * @returns {string} Formatted date
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

/**
 * Check if user is logged in
 * @returns {boolean} True if logged in
 */
function isLoggedIn() {
    return currentUser !== null;
}

/**
 * Logout user
 */
function logout() {
    currentUser = null;
    localStorage.removeItem('currentUser');
    showNotification('👋 Logged out successfully', 'info');
    showPage('home');
}

// ═══════════════════════ INITIALIZATION ═══════════════════════
/**
 * Initialize application on page load
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Advanced Library Management System Loaded');
    console.log('📚 Version: 2.0 (Modern Frontend)');
    
    // Restore user session
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        try {
            currentUser = JSON.parse(savedUser);
            console.log('👤 User session restored:', currentUser.username);
            showNotification(`🎉 Welcome back, ${currentUser.username}!`, 'success');
        } catch (error) {
            console.error('Error restoring user session:', error);
            localStorage.removeItem('currentUser');
        }
    }
    
    // Load featured books on home page
    showPage('home');
    
    // Add fade-in animation to body
    document.body.style.animation = 'fadeIn 0.5s ease-in';
    
    console.log('✅ Initialization complete');
});

// ═══════════════════════ KEYBOARD SHORTCUTS ═══════════════════════
/**
 * Handle keyboard shortcuts
 */
document.addEventListener('keydown', (event) => {
    // Ctrl/Cmd + K for search
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        showPage('search');
    }
    
    // Ctrl/Cmd + H for home
    if ((event.ctrlKey || event.metaKey) && event.key === 'h') {
        event.preventDefault();
        showPage('home');
    }
});

// ═══════════════════════ PERFORMANCE OPTIMIZATION ═══════════════════════
/**
 * Debounce function for search input
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add debounce to search input
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', debounce((e) => {
        if (e.target.value.length > 2) {
            // Optional: auto-search as user types
        }
    }, 300));
}

// ═══════════════════════ GENRE FILTER ═══════════════════════
/**
 * Handle genre filter on home page
 */
const genreFilter = document.getElementById('genreFilter');
if (genreFilter) {
    genreFilter.addEventListener('change', function() {
        const selectedGenre = this.value;
        filterByGenre(selectedGenre);
    });
}

/**
 * Filter books by genre
 * @param {string} genre - Genre to filter by (empty string means 'All')
 */
async function filterByGenre(genre) {
    try {
        let url = '/api/books.php?action=list&limit=12';
        
        if (genre && genre !== '') {
            url = `/api/books.php?action=search&type=genre&query=${encodeURIComponent(genre)}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            const bookGrid = document.getElementById('bookGrid');
            bookGrid.innerHTML = '';
            
            if (data.books && data.books.length > 0) {
                data.books.forEach(book => {
                    const bookCard = createBookCard(book);
                    bookGrid.appendChild(bookCard);
                });
                
                // Add loading animation
                bookGrid.querySelectorAll('.card').forEach((card, index) => {
                    card.style.animation = `fadeIn 0.4s ease-in ${index * 0.05}s`;
                    card.style.animationFillMode = 'both';
                });
            } else {
                bookGrid.innerHTML = '<p style="color: var(--text-secondary); text-align: center; grid-column: 1/-1;">📚 No books found in this genre.</p>';
            }
        }
    } catch (error) {
        console.error('Error filtering by genre:', error);
        document.getElementById('bookGrid').innerHTML = '<p style="color: #d32f2f;">❌ Error loading books</p>';
    }
}
