# Quick Start Guide - Using the New Author & Publisher APIs

This guide provides quick examples for common tasks using the new normalized schema.

## Table of Contents
1. [Managing Authors](#managing-authors)
2. [Managing Publishers](#managing-publishers)
3. [Adding Books with New Schema](#adding-books)
4. [Using Dropdowns](#using-dropdowns)
5. [Common Code Snippets](#common-code-snippets)

---

## Managing Authors

### Create New Author

**JavaScript (Frontend):**
```javascript
async function createAuthor() {
    const authorData = {
        first_name: 'J.K.',
        last_name: 'Rowling',
        bio: 'British author, best known for Harry Potter',
        birth_date: '1965-07-31',
        nationality: 'British',
        user_id: currentUserId
    };
    
    const response = await authorsAPI.addAuthor(authorData);
    
    if (response.success) {
        console.log('Author created with ID:', response.data.author_id);
        showMessage('Author added successfully!', 'success');
    } else {
        showMessage(response.message, 'error');
    }
}
```

**PHP (Backend - Direct API Call):**
```php
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost/library_system/backend/api/authors_publishers.php?action=add_author',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'first_name' => 'J.K.',
        'last_name' => 'Rowling',
        'bio' => 'British author, best known for Harry Potter',
        'birth_date' => '1965-07-31',
        'nationality' => 'British',
        'user_id' => $userId
    ])
]);

$response = json_decode(curl_exec($curl));
if ($response->success) {
    $author_id = $response->data->author_id;
}
```

### Search Authors

**JavaScript:**
```javascript
async function searchAuthors(query) {
    const response = await authorsAPI.listAuthors({
        search: query,
        limit: 10
    });
    
    response.data.forEach(author => {
        console.log(`${author.first_name} ${author.last_name}`);
    });
}

// Usage:
await searchAuthors('Rowling');
```

### Get Author with Books

**JavaScript:**
```javascript
async function getAuthorWithBooks(authorId) {
    const response = await authorsAPI.getAuthor(authorId);
    
    const author = response.data;
    console.log(`Author: ${author.first_name} ${author.last_name}`);
    console.log(`Books written:`);
    
    author.books.forEach(book => {
        console.log(`  - ${book.title} (${book.publication_year})`);
    });
}

// Usage:
await getAuthorWithBooks(5);
```

### Update Author

**JavaScript:**
```javascript
async function updateAuthor(authorId, updates) {
    const response = await authorsAPI.updateAuthor({
        author_id: authorId,
        bio: updates.bio,
        nationality: updates.nationality,
        user_id: currentUserId
    });
    
    if (response.success) {
        showMessage('Author updated successfully!', 'success');
    }
}

// Usage:
await updateAuthor(5, {
    bio: 'Updated biography text',
    nationality: 'British'
});
```

### Delete Author

**JavaScript:**
```javascript
async function deleteAuthor(authorId) {
    // First check if author has books
    const authorData = await authorsAPI.getAuthor(authorId);
    
    if (authorData.data.books.length > 0) {
        showMessage('Cannot delete author with associated books', 'error');
        return;
    }
    
    const response = await authorsAPI.deleteAuthor(authorId, currentUserId);
    
    if (response.success) {
        showMessage('Author deleted successfully!', 'success');
    }
}

// Usage:
await deleteAuthor(15);
```

---

## Managing Publishers

### Create New Publisher

**JavaScript:**
```javascript
async function createPublisher() {
    const publisherData = {
        name: 'Bloomsbury Publishing',
        address: '50 Bedford Square',
        city: 'London',
        country: 'United Kingdom',
        phone: '+44-20-7631-5800',
        email: 'info@bloomsbury.com',
        website: 'www.bloomsbury.com',
        user_id: currentUserId
    };
    
    const response = await publishersAPI.addPublisher(publisherData);
    
    if (response.success) {
        console.log('Publisher created with ID:', response.data.publisher_id);
    }
}
```

### List Publishers

**JavaScript:**
```javascript
async function listPublishers() {
    const response = await publishersAPI.listPublishers({
        search: '', // optional
        limit: 20,
        offset: 0
    });
    
    response.data.forEach(pub => {
        console.log(`${pub.name} - ${pub.city}, ${pub.country}`);
    });
}
```

### Get Publisher Details

**JavaScript:**
```javascript
async function getPublisherDetails(publisherId) {
    const response = await publishersAPI.getPublisher(publisherId);
    
    const publisher = response.data;
    console.log(`Publisher: ${publisher.name}`);
    console.log(`Location: ${publisher.city}, ${publisher.country}`);
    console.log(`Books published:`);
    
    publisher.books.forEach(book => {
        console.log(`  - ${book.title} by ${book.author_name}`);
    });
}
```

### Update Publisher

**JavaScript:**
```javascript
async function updatePublisher(publisherId) {
    const response = await publishersAPI.updatePublisher({
        publisher_id: publisherId,
        phone: '+44-20-7631-5900',  // Updated phone
        website: 'www.new-website.com',  // Updated website
        user_id: currentUserId
    });
    
    if (response.success) {
        showMessage('Publisher updated!', 'success');
    }
}
```

---

## Adding Books

### Using Dropdowns

**HTML Form:**
```html
<form id="addBookForm" onsubmit="submitBookForm(event)">
    <div class="form-group">
        <label for="title">Book Title *</label>
        <input type="text" id="title" required>
    </div>
    
    <div class="form-group">
        <label for="author_id">Author *</label>
        <select id="author_id" required>
            <option value="">Select Author...</option>
            <!-- Populated by JavaScript -->
        </select>
    </div>
    
    <div class="form-group">
        <label for="publisher_id">Publisher *</label>
        <select id="publisher_id" required>
            <option value="">Select Publisher...</option>
            <!-- Populated by JavaScript -->
        </select>
    </div>
    
    <div class="form-group">
        <label for="isbn">ISBN</label>
        <input type="text" id="isbn">
    </div>
    
    <div class="form-group">
        <label for="publication_year">Publication Year</label>
        <input type="number" id="publication_year">
    </div>
    
    <div class="form-group">
        <label for="total_copies">Total Copies</label>
        <input type="number" id="total_copies" value="1" min="1">
    </div>
    
    <button type="submit">Add Book</button>
</form>
```

**JavaScript:**
```javascript
// Load form on page load
async function initBookForm() {
    const authors = await dropdownsAPI.getAuthors();
    const publishers = await dropdownsAPI.getPublishers();
    
    populateDropdown('author_id', authors.data, 'name');
    populateDropdown('publisher_id', publishers.data, 'name');
}

function populateDropdown(elementId, data, labelField) {
    const select = document.getElementById(elementId);
    
    data.forEach(item => {
        const option = document.createElement('option');
        option.value = item.author_id || item.publisher_id;
        option.textContent = item[labelField];
        select.appendChild(option);
    });
}

// Submit the form
async function submitBookForm(event) {
    event.preventDefault();
    
    const bookData = {
        title: document.getElementById('title').value,
        author_id: parseInt(document.getElementById('author_id').value),
        publisher_id: parseInt(document.getElementById('publisher_id').value),
        isbn: document.getElementById('isbn').value,
        publication_year: parseInt(document.getElementById('publication_year').value),
        total_copies: parseInt(document.getElementById('total_copies').value),
        user_id: currentUserId
    };
    
    const response = await booksAPI.addBook(bookData);
    
    if (response.success) {
        showMessage('Book added successfully!', 'success');
        document.getElementById('addBookForm').reset();
    } else {
        showMessage(response.message, 'error');
    }
}

// Initialize form when page loads
document.addEventListener('DOMContentLoaded', initBookForm);
```

---

## Using Dropdowns

### Populate Any Dropdown

**JavaScript:**
```javascript
async function populateAll Dropdowns() {
    // Authors
    const authors = await dropdownsAPI.getAuthors();
    populateSelect('author_select', authors.data);
    
    // Publishers
    const publishers = await dropdownsAPI.getPublishers();
    populateSelect('publisher_select', publishers.data);
    
    // Categories
    const categories = await dropdownsAPI.getCategories();
    populateSelect('category_select', categories.data, 'category_name');
    
    // Genres
    const genres = await dropdownsAPI.getGenres();
    populateSelect('genre_select', genres.data, 'label', 'value');
    
    // Members
    const members = await dropdownsAPI.getMembers();
    populateSelect('member_select', members.data);
    
    // Book Statuses
    const bookStatuses = await dropdownsAPI.getBookStatuses();
    populateSelect('status_select', bookStatuses.data, 'label', 'value');
}

function populateSelect(elementId, data, labelField = 'name', valueField = null) {
    const select = document.getElementById(elementId);
    select.innerHTML = '<option value="">Select...</option>';
    
    data.forEach(item => {
        const option = document.createElement('option');
        
        // Determine value
        if (valueField) {
            option.value = item[valueField];
        } else {
            option.value = item.author_id || item.publisher_id || item.category_id || item.user_id;
        }
        
        // Determine label
        option.textContent = item[labelField] || item.name;
        
        select.appendChild(option);
    });
}
```

---

## Common Code Snippets

### Display Books with Author & Publisher Names

**JavaScript:**
```javascript
async function displayBooks() {
    const response = await booksAPI.listBooks({
        limit: 20,
        status: 'active'
    });
    
    const html = response.data.map(book => `
        <div class="book-card">
            <h3>${book.title}</h3>
            <p class="author">by ${book.author_name}</p>
            <p class="publisher">${book.publisher_name}</p>
            <p class="available">${book.available} of ${book.total} available</p>
        </div>
    `).join('');
    
    document.getElementById('booksList').innerHTML = html;
}
```

### Search Books by Author

**JavaScript:**
```javascript
async function searchByAuthor(authorName) {
    const response = await booksAPI.search(authorName, 'author');
    
    console.log(`Found ${response.data.length} books by ${authorName}`);
    response.data.forEach(book => {
        console.log(`- ${book.title}`);
    });
}

// Usage:
await searchByAuthor('Rowling');
```

### Create and Add Book (Complete Flow)

**JavaScript:**
```javascript
async function createAndAddBook() {
    // Step 1: Create author if needed
    const authorResponse = await authorsAPI.addAuthor({
        first_name: 'George',
        last_name: 'Martin',
        nationality: 'American',
        user_id: currentUserId
    });
    
    const author_id = authorResponse.data.author_id;
    
    // Step 2: Create publisher if needed
    const publisherResponse = await publishersAPI.addPublisher({
        name: 'Bantam Books',
        country: 'United States',
        user_id: currentUserId
    });
    
    const publisher_id = publisherResponse.data.publisher_id;
    
    // Step 3: Add book with new author and publisher
    const bookResponse = await booksAPI.addBook({
        title: 'A Game of Thrones',
        author_id: author_id,
        publisher_id: publisher_id,
        isbn: '978-0553103540',
        publication_year: 1996,
        total_copies: 5,
        user_id: currentUserId
    });
    
    if (bookResponse.success) {
        console.log('Book added with ID:', bookResponse.data.book_id);
    }
}
```

### Handle API Errors Gracefully

**JavaScript:**
```javascript
async function safeBookOperation(operation) {
    try {
        const response = await operation();
        
        if (!response.success) {
            showMessage(response.message, 'error');
            return null;
        }
        
        return response.data;
        
    } catch (error) {
        console.error('API Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
        return null;
    }
}

// Usage:
const bookData = await safeBookOperation(() => 
    booksAPI.getBook(42)
);

if (bookData) {
    console.log('Book title:', bookData.title);
}
```

### List Authors with Pagination

**JavaScript:**
```javascript
let currentPage = 1;
const pageSize = 10;

async function listAuthorsPaginated(page) {
    const offset = (page - 1) * pageSize;
    
    const response = await authorsAPI.listAuthors({
        limit: pageSize,
        offset: offset
    });
    
    const html = response.data.map(author => `
        <div class="author-card">
            <h3>${author.first_name} ${author.last_name}</h3>
            <p>${author.nationality || 'Unknown'}</p>
            <p class="bio">${author.bio || 'No biography'}</p>
        </div>
    `).join('');
    
    document.getElementById('authorsList').innerHTML = html;
    currentPage = page;
}

// Usage:
await listAuthorsPaginated(1);  // Load page 1
```

### Create Admin Panel for Authors & Publishers

**HTML:**
```html
<div id="adminPanel">
    <h2>Manage Authors & Publishers</h2>
    
    <div class="admin-tabs">
        <button onclick="switchTab('authors')" class="tab-button active">Authors</button>
        <button onclick="switchTab('publishers')" class="tab-button">Publishers</button>
    </div>
    
    <!-- Authors Tab -->
    <div id="authorsTab" class="tab-content">
        <h3>Authors</h3>
        <form onsubmit="addAuthorForm(event)">
            <input type="text" id="authorFirstName" placeholder="First Name" required>
            <input type="text" id="authorLastName" placeholder="Last Name" required>
            <textarea id="authorBio" placeholder="Biography"></textarea>
            <button type="submit">Add Author</button>
        </form>
        <div id="authorsList"></div>
    </div>
    
    <!-- Publishers Tab -->
    <div id="publishersTab" class="tab-content" style="display:none;">
        <h3>Publishers</h3>
        <form onsubmit="addPublisherForm(event)">
            <input type="text" id="publisherName" placeholder="Name" required>
            <input type="text" id="publisherCity" placeholder="City">
            <button type="submit">Add Publisher</button>
        </form>
        <div id="publishersList"></div>
    </div>
</div>
```

**JavaScript:**
```javascript
async function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    document.getElementById(tabName + 'Tab').style.display = 'block';
    
    if (tabName === 'authors') {
        await loadAuthorsList();
    } else {
        await loadPublishersList();
    }
}

async function loadAuthorsList() {
    const response = await authorsAPI.listAuthors({ limit: 100 });
    
    const html = response.data.map(author => `
        <div class="admin-card">
            <h4>${author.first_name} ${author.last_name}</h4>
            <p>${author.bio || 'No bio'}</p>
            <button onclick="deleteAuthor(${author.author_id})">Delete</button>
        </div>
    `).join('');
    
    document.getElementById('authorsList').innerHTML = html;
}

async function addAuthorForm(event) {
    event.preventDefault();
    
    const response = await authorsAPI.addAuthor({
        first_name: document.getElementById('authorFirstName').value,
        last_name: document.getElementById('authorLastName').value,
        bio: document.getElementById('authorBio').value,
        user_id: currentUserId
    });
    
    if (response.success) {
        event.target.reset();
        await loadAuthorsList();
        showMessage('Author added!', 'success');
    }
}
```

---

## Troubleshooting

### "Author not found" Error

**Problem**: When adding a book, you get "Author not found"

**Solution**: Make sure you're passing the correct `author_id`:
```javascript
// WRONG:
const bookData = {
    author_id: 'John Doe'  // String instead of ID
};

// CORRECT:
const bookData = {
    author_id: 5  // Integer ID from database
};
```

### Dropdowns Not Populating

**Problem**: Dropdown lists are empty

**Solution**: Make sure you're calling `initBookForm()` on page load:
```javascript
// Add this when page loads
document.addEventListener('DOMContentLoaded', initBookForm);
```

### Can't Delete Author/Publisher

**Problem**: Delete operation fails

**Reason**: Authors/publishers with associated books cannot be deleted

**Solution**: Remove associated books first or check if they exist:
```javascript
const author = await authorsAPI.getAuthor(authorId);
if (author.data.books.length > 0) {
    showMessage('Cannot delete - books exist for this author', 'error');
}
```

---

## API Response Examples

### Successful Response
```json
{
  "success": true,
  "message": "Author added successfully",
  "data": {
    "author_id": 42
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Publisher not found",
  "data": null
}
```

---

## Additional Resources

- Full API Reference: `API_ENDPOINTS_UPDATED.md`
- Migration Guide: `MIGRATION_GUIDE.md`
- Integration Summary: `INTEGRATION_SUMMARY.md`

