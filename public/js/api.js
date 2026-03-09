// API Helper Functions
const API_URL = 'http://localhost/library_system/backend/api';

// Auth API calls
const authAPI = {
    register: (data) => fetch(`${API_URL}/auth.php?action=register`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    login: (data) => fetch(`${API_URL}/auth.php?action=login`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    getProfile: (userId) => fetch(`${API_URL}/auth.php?action=profile&user_id=${userId}`).then(r => r.json()),

    updateProfile: (data) => fetch(`${API_URL}/auth.php?action=update_profile`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    listUsers: (params = {}) => {
        let query = `${API_URL}/auth.php?action=list_users`;
        if (params.role) query += `&role=${params.role}`;
        if (params.is_active !== undefined) query += `&is_active=${params.is_active}`;
        return fetch(query).then(r => r.json());
    },

    toggleUserStatus: (userId, isActive) => fetch(`${API_URL}/auth.php?action=toggle_user_status`, {
        method: 'POST',
        body: new URLSearchParams({
            user_id: userId,
            is_active: isActive
        })
    }).then(r => r.json())
};

// Books API calls
const booksAPI = {
    addBook: (data) => fetch(`${API_URL}/books.php?action=add_book`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    listBooks: (params = {}) => {
        let query = `${API_URL}/books.php?action=list_books`;
        if (params.genre) query += `&genre=${params.genre}`;
        if (params.status) query += `&status=${params.status}`;
        if (params.search) query += `&search=${params.search}`;
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    },

    getBook: (bookId) => fetch(`${API_URL}/books.php?action=get_book&book_id=${bookId}`).then(r => r.json()),

    updateBook: (data) => fetch(`${API_URL}/books.php?action=update_book`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    deleteBook: (bookId, userId) => fetch(`${API_URL}/books.php?action=delete_book`, {
        method: 'POST',
        body: new URLSearchParams({
            book_id: bookId,
            user_id: userId
        })
    }).then(r => r.json()),

    search: (query, searchBy = 'all') => fetch(`${API_URL}/books.php?action=search&q=${query}&search_by=${searchBy}`).then(r => r.json()),

    getCategories: () => fetch(`${API_URL}/books.php?action=get_categories`).then(r => r.json()),

    addCategory: (bookId, categoryId) => fetch(`${API_URL}/books.php?action=add_category`, {
        method: 'POST',
        body: new URLSearchParams({
            book_id: bookId,
            category_id: categoryId
        })
    }).then(r => r.json())
};

// Authors API calls
const authorsAPI = {
    addAuthor: (data) => fetch(`${API_URL}/authors_publishers.php?action=add_author`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    listAuthors: (params = {}) => {
        let query = `${API_URL}/authors_publishers.php?action=list_authors`;
        if (params.search) query += `&search=${params.search}`;
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    },

    getAuthor: (authorId) => fetch(`${API_URL}/authors_publishers.php?action=get_author&author_id=${authorId}`).then(r => r.json()),

    updateAuthor: (data) => fetch(`${API_URL}/authors_publishers.php?action=update_author`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    deleteAuthor: (authorId, userId) => fetch(`${API_URL}/authors_publishers.php?action=delete_author`, {
        method: 'POST',
        body: new URLSearchParams({
            author_id: authorId,
            user_id: userId
        })
    }).then(r => r.json())
};

// Publishers API calls
const publishersAPI = {
    addPublisher: (data) => fetch(`${API_URL}/authors_publishers.php?action=add_publisher`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    listPublishers: (params = {}) => {
        let query = `${API_URL}/authors_publishers.php?action=list_publishers`;
        if (params.search) query += `&search=${params.search}`;
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    },

    getPublisher: (publisherId) => fetch(`${API_URL}/authors_publishers.php?action=get_publisher&publisher_id=${publisherId}`).then(r => r.json()),

    updatePublisher: (data) => fetch(`${API_URL}/authors_publishers.php?action=update_publisher`, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.json()),

    deletePublisher: (publisherId, userId) => fetch(`${API_URL}/authors_publishers.php?action=delete_publisher`, {
        method: 'POST',
        body: new URLSearchParams({
            publisher_id: publisherId,
            user_id: userId
        })
    }).then(r => r.json())
};

// Dropdowns API calls
const dropdownsAPI = {
    getAuthors: () => fetch(`${API_URL}/dropdowns.php?action=authors`).then(r => r.json()),

    getPublishers: () => fetch(`${API_URL}/dropdowns.php?action=publishers`).then(r => r.json()),

    getCategories: () => fetch(`${API_URL}/dropdowns.php?action=categories`).then(r => r.json()),

    getGenres: () => fetch(`${API_URL}/dropdowns.php?action=genres`).then(r => r.json()),

    getMembers: () => fetch(`${API_URL}/dropdowns.php?action=members`).then(r => r.json()),

    getBookStatuses: () => fetch(`${API_URL}/dropdowns.php?action=book_statuses`).then(r => r.json()),

    getCirculationStatuses: () => fetch(`${API_URL}/dropdowns.php?action=circulation_statuses`).then(r => r.json()),

    getFineStatuses: () => fetch(`${API_URL}/dropdowns.php?action=fine_statuses`).then(r => r.json())
}

// Circulation API calls
const circulationAPI = {
    borrowBook: (userId, bookId) => fetch(`${API_URL}/circulation.php?action=borrow`, {
        method: 'POST',
        body: new URLSearchParams({
            user_id: userId,
            book_id: bookId
        })
    }).then(r => r.json()),

    returnBook: (circulationId, condition = 'good') => fetch(`${API_URL}/circulation.php?action=return`, {
        method: 'POST',
        body: new URLSearchParams({
            circulation_id: circulationId,
            condition: condition
        })
    }).then(r => r.json()),

    renewBook: (circulationId) => fetch(`${API_URL}/circulation.php?action=renew`, {
        method: 'POST',
        body: new URLSearchParams({
            circulation_id: circulationId
        })
    }).then(r => r.json()),

    getMyBooks: (userId, status = 'borrowed') => fetch(`${API_URL}/circulation.php?action=my_books&user_id=${userId}&status=${status}`).then(r => r.json()),

    getAllBorrowed: (params = {}) => {
        let query = `${API_URL}/circulation.php?action=all_borrowed`;
        if (params.overdueOnly) query += '&overdue_only=1';
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    }
};

// Fines API calls
const finesAPI = {
    getFines: (userId, status = 'all') => fetch(`${API_URL}/fines.php?action=get_fines&user_id=${userId}&status=${status}`).then(r => r.json()),

    getTotalFines: (userId) => fetch(`${API_URL}/fines.php?action=total_fines&user_id=${userId}`).then(r => r.json()),

    payFine: (fineId, paymentMethod = 'cash') => fetch(`${API_URL}/fines.php?action=pay_fine`, {
        method: 'POST',
        body: new URLSearchParams({
            fine_id: fineId,
            payment_method: paymentMethod
        })
    }).then(r => r.json()),

    payMultipleFines: (userId, paymentMethod = 'cash') => fetch(`${API_URL}/fines.php?action=pay_multiple_fines`, {
        method: 'POST',
        body: new URLSearchParams({
            user_id: userId,
            payment_method: paymentMethod
        })
    }).then(r => r.json()),

    waiveFine: (fineId, reason = 'Manual waiver', adminUserId = null) => {
        const data = {
            fine_id: fineId,
            reason: reason
        };
        if (adminUserId) data.admin_user_id = adminUserId;
        return fetch(`${API_URL}/fines.php?action=waive_fine`, {
            method: 'POST',
            body: new URLSearchParams(data)
        }).then(r => r.json());
    },

    getAllPendingFines: (params = {}) => {
        let query = `${API_URL}/fines.php?action=all_pending_fines`;
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    },

    sendOverdueNotifications: () => fetch(`${API_URL}/fines.php?action=send_overdue_notifications`, {
        method: 'POST'
    }).then(r => r.json()),

    sendDueReminders: () => fetch(`${API_URL}/fines.php?action=send_due_reminders`, {
        method: 'POST'
    }).then(r => r.json())
};

// Reports API calls
const reportsAPI = {
    getUsageReport: (startDate = '', endDate = '') => {
        let query = `${API_URL}/reports.php?action=usage_report`;
        if (startDate) query += `&start_date=${startDate}`;
        if (endDate) query += `&end_date=${endDate}`;
        return fetch(query).then(r => r.json());
    },

    getPopularBooks: (limit = 10, startDate = '', endDate = '') => {
        let query = `${API_URL}/reports.php?action=popular_books&limit=${limit}`;
        if (startDate) query += `&start_date=${startDate}`;
        if (endDate) query += `&end_date=${endDate}`;
        return fetch(query).then(r => r.json());
    },

    getBorrowingTrends: (days = 30) => fetch(`${API_URL}/reports.php?action=borrowing_trends&days=${days}`).then(r => r.json()),

    getInventoryReport: () => fetch(`${API_URL}/reports.php?action=inventory_report`).then(r => r.json()),

    getOverdueItems: (params = {}) => {
        let query = `${API_URL}/reports.php?action=overdue_items`;
        if (params.limit) query += `&limit=${params.limit}`;
        if (params.offset) query += `&offset=${params.offset}`;
        return fetch(query).then(r => r.json());
    },

    getUserActivity: (limit = 50) => fetch(`${API_URL}/reports.php?action=user_activity&limit=${limit}`).then(r => r.json()),

    getMemberEngagement: () => fetch(`${API_URL}/reports.php?action=member_engagement`).then(r => r.json()),

    getBookConditionReport: () => fetch(`${API_URL}/reports.php?action=book_condition_report`).then(r => r.json()),

    getExportData: (reportType) => fetch(`${API_URL}/reports.php?action=export&report_type=${reportType}`).then(r => r.json())
};

// Helper function to show messages
function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.container');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Helper function to format currency
function formatCurrency(amount) {
    return `Rs. ${parseFloat(amount).toFixed(2)}`;
}

// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Helper function to format datetime
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Helper function to get days remaining
function getDaysRemaining(dueDate) {
    const today = new Date();
    const due = new Date(dueDate);
    const diff = due - today;
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
    return days;
}

// Helper function to get book availability class
function getAvailabilityClass(available, total) {
    if (available === 0) return 'unavailable';
    if (available <= Math.ceil(total * 0.25)) return 'limited';
    return 'available';
}

// Helper function to get availability text
function getAvailabilityText(available, total) {
    if (available === 0) return 'Unavailable';
    if (available === total) return 'All Available';
    return `${available} of ${total} Available`;
}
