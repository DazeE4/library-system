# 📚 Bagmati School Library Management System

> A free, fully functional digital library system built for Bagmati School, Nepal.
> No paid services. No credit card. No backend server required.

---

## 🎯 Project Goal

To build a **modern digital library** where:
- Students and teachers can **browse, search, and borrow** books online
- Librarians can **add and manage featured books**
- Everything runs **completely free** using Netlify + Supabase

---

## ✅ What Was Built

A **single HTML file** (`index.html`) that contains all CSS, JavaScript, and HTML in one place. No separate files needed. Just one file uploaded to Netlify and the system is live.

### Pages included:
| Page | What it does |
|------|-------------|
| **Home** | Shows featured books, hero stats, genre filter |
| **Search** | Search books by title, author, or genre |
| **My Books** | View borrowed books and due dates |
| **Wishlist** | Save books to read later |
| **Login** | Sign in with email and password |
| **Signup** | Create a new account (always student role) |

---

## 🛠 Technology Stack

| Layer | Tool | Why |
|-------|------|-----|
| Frontend hosting | **Netlify** | Free, no credit card, drag-and-drop deploy |
| Database | **Supabase (PostgreSQL)** | Free tier, no credit card, built-in auth |
| Authentication | **Supabase Auth** | Email/password login, session management |
| Frontend | **Plain HTML + CSS + JS** | No framework needed, single file |
| Icons | **Font Awesome 6.4** | CDN, free |
| Fonts | **Google Fonts** (Playfair Display + DM Sans) | CDN, free |
| Supabase SDK | **supabase-js v2** | CDN, connects frontend to database |

**Total cost: $0/month** ✅

---

## 🗄 Database Structure (Supabase)

### Table: `profiles`
Stores user profile information linked to Supabase Auth.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Links to `auth.users` |
| `full_name` | text | User's full name |
| `email` | text | User's email |
| `role` | text | `student`, `teacher`, `librarian`, `admin` |

### Table: `featured_books`
Books added by librarians — visible to everyone.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Auto-generated primary key |
| `title` | text | Book title (required) |
| `author` | text | Author name |
| `description` | text | Book summary |
| `genre` | text | Fiction, Science, etc. |
| `cover_url` | text | URL to cover image |
| `added_by` | uuid | Links to librarian's user ID |
| `created_at` | timestamp | When it was added |

### Table: `loans`
Tracks which user borrowed which book.

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Auto-generated primary key |
| `book_id` | uuid | Links to `featured_books` |
| `user_id` | uuid | Links to `auth.users` |
| `due_date` | timestamp | 14 days from borrow date |
| `returned_at` | timestamp | Null if not yet returned |
| `created_at` | timestamp | When borrowed |

---

## 🔐 Security

### Row Level Security (RLS)
All tables have RLS enabled. No one can access data they shouldn't.

| Table | Who can read | Who can write |
|-------|-------------|---------------|
| `profiles` | Own profile only | Own profile only |
| `featured_books` | Everyone (public) | Librarian / Admin only |
| `loans` | Own loans only | Own loans only |

### Role System
| Role | How to get it | What they can do |
|------|--------------|-----------------|
| `student` | Sign up on the site | Browse, borrow, wishlist |
| `teacher` | Admin sets manually in Supabase | Browse, borrow, wishlist |
| `librarian` | Admin sets manually in Supabase | All above + add/delete books |
| `admin` | You set manually via SQL | All above + full Supabase access |

> ⚠️ The signup form does **not** include a role selector. Everyone signs up as `student` by default. Roles are only elevated by running SQL directly in Supabase — no one can give themselves a higher role.

### To promote a user to admin or librarian:
```sql
UPDATE profiles SET role = 'admin' WHERE email = 'your@email.com';
UPDATE profiles SET role = 'librarian' WHERE email = 'librarian@email.com';
```

---

## 🚀 How to Deploy

### Step 1 — Set up Supabase
1. Go to [supabase.com](https://supabase.com) and create a free project
2. Go to **SQL Editor** and run the full schema + RLS script
3. Go to **Settings → API** and copy:
   - Project URL: `https://your-project.supabase.co`
   - Anon (publishable) key

### Step 2 — Configure `index.html`
Open `index.html` and replace at the top of the `<script>` section:
```js
const SUPABASE_URL  = 'https://your-project.supabase.co';
const SUPABASE_ANON = 'your_anon_key_here';
```

### Step 3 — Deploy to Netlify
1. Go to [netlify.com](https://netlify.com)
2. Click **"Add new site → Deploy manually"**
3. Drag and drop `index.html`
4. Done — your site is live ✅

---

## 📁 Project Files

```
bagmati-library/
├── index.html        ← entire app (HTML + CSS + JS in one file)
└── README.md         ← this file
```

> All other files (`index.php`, `index.sql`, `index.css`, `index.js`) from the original version have been **removed** — they were for a PHP/MySQL stack that is no longer used.

---

## 🔄 How Key Features Work

### Featured Books (Librarian only)
1. Librarian logs in → app detects `role = librarian` from `profiles` table
2. **Add Book panel** appears at the top of the home page
3. Librarian fills in title + description → clicks Add
4. Book is inserted into `featured_books` table in Supabase
5. All visitors see the new book instantly (no page refresh needed)
6. Regular users never see the Add Book panel

### Borrow a Book
1. User must be logged in
2. Clicks **Borrow** on any book card
3. A row is inserted into `loans` table with a `due_date` 14 days from now
4. Button changes to **Return**
5. User can see all active loans in **My Books** page

### Wishlist
- Stored in browser `localStorage` (no database needed)
- Survives page refresh but is device-specific
- Tap the bookmark icon on any card to save/unsave

### Authentication Flow
```
User signs up → Supabase Auth creates account
             → profiles row auto-inserted with role = 'student'
             → Email verification sent
User logs in → Supabase Auth validates credentials
             → App loads profile + role from profiles table
             → UI updates based on role (librarian sees extra panel)
```

---

## ⚠️ Known Limitations

- **No borrow limit per user** — a user can borrow unlimited books simultaneously
- **No availability tracking** — books don't go "unavailable" when borrowed (all show as available)
- **Wishlist is local** — saved to browser only, not synced across devices
- **No fine system** — overdue books are flagged visually but no automatic fines
- **No email notifications** — no due date reminders sent by email

These can be added in future versions.

---

## 🌐 Free Tier Limits

| Service | Limit | Enough for school? |
|---------|-------|-------------------|
| Supabase DB | 500 MB | ✅ Yes (text data is tiny) |
| Supabase Auth | 50,000 monthly active users | ✅ Yes |
| Netlify bandwidth | 100 GB/month | ✅ Yes |
| Netlify deploys | 500/month | ✅ Yes |

---

## 👨‍💻 Built With

- No frameworks. No build tools. No npm. No PHP server.
- Just one HTML file, two free cloud services, and a browser.

---

*Bagmati School Library · Nepal · 2026*
