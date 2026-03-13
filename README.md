# Bagmati School Library — Digital Hub

A web-based library management system built for Bagmati School, Kathmandu, Nepal. Students can browse books, request to borrow them, and track their loans. Librarians and admins manage everything from one place — approvals, returns, late fees, and member notices.

The whole thing runs as a single HTML file backed by [Supabase](https://supabase.com) for the database and authentication. No build tools, no npm, no server to set up. Just open the file in a browser and it works.

---

## What it does

**For students**
- Browse the full book collection with cover images and availability counts
- Filter books by genre (Fiction, Non-Fiction, Science, Technology, etc.)
- Search by title, author, or genre
- Request to borrow a book — a librarian then approves or rejects it
- See all your pending requests, active loans, and borrow history in one tab
- Get notified when your request is approved/rejected, when a book is due soon, or when it goes overdue
- Save books to a wishlist for later
- View your own profile with your student UID, class, section, and loan count
- Reset your password from any browser, not just the one you signed up on

**For librarians**
- See all pending requests from all students and approve or reject them in one click
- Set a custom borrow duration (1–30 days) when approving
- Monitor all active loans across the library
- See overdue loans at a glance — late fees are calculated automatically
- Mark books as returned
- Search for any student by name, UID, class, or section
- Send a notice to all students or a specific student directly inside the app
- Delete books that are no longer in the collection

**For admins**
- Everything a librarian can do, plus
- Change any user's role (student → librarian → admin) from their profile page
- Full access to the audit trail in the database (every sensitive action is logged)

---

## Folder structure

There are only two files that matter:

```
index.html    — the entire frontend (HTML + CSS + JavaScript in one file)
schema.sql    — the database schema, run this once in Supabase SQL Editor
```

That's it. No node_modules, no webpack, nothing to install.

---

## Setting up from scratch

### 1. Create a Supabase project

Go to [supabase.com](https://supabase.com), create a free account, and start a new project. Pick any region close to Nepal (Singapore is usually the fastest).

Once your project is created, grab two things from **Project Settings → API**:
- **Project URL** — looks like `https://xyzxyz.supabase.co`
- **anon public key** — the long JWT string

### 2. Run the schema

Open the **SQL Editor** inside your Supabase dashboard and paste the entire contents of `schema.sql`. Hit Run. This will create all the tables, set up row-level security policies, and create the database functions the app depends on.

You'll see a message at the bottom — if it says "Success" you're good.

### 3. Set your admin account

At the bottom of `schema.sql` there's this line:

```sql
UPDATE profiles
  SET role = 'admin'
  WHERE email = 'arai27814@gmail.com';
```

Change that email to your own, then run just that line after you've signed up for the first time through the app. You need to sign up and verify your email before this will work, because the profile row needs to exist first.

### 4. Put your Supabase credentials in the HTML

Open `index.html` and find this section near the bottom (around line 1327):

```javascript
const SUPABASE_URL  = 'https://nuvkoluhmiewdegpsrbl.supabase.co';
const SUPABASE_ANON = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ...';
```

Replace both values with your own project URL and anon key from step 1.

### 5. Enable Leaked Password Protection (recommended)

Go to your Supabase dashboard → **Authentication → Providers → Email**, and turn on **Leaked Password Protection**. This checks new passwords against known breached password lists. It takes 10 seconds to enable and is worth doing.

### 6. Open the file

Double-click `index.html` or serve it from any static host. It will load immediately.

If you want to host it online, you can use [Netlify Drop](https://app.netlify.com/drop), [Vercel](https://vercel.com), GitHub Pages, or literally any static file host. Just drag and drop the single HTML file.

---

## How the roles work

There are three roles in the system. Everyone who signs up starts as a **student** by default.

| Role | What they can do |
|------|-----------------|
| `student` | Browse books, request borrows, see own profile and loans, get notifications |
| `librarian` | Everything a student can, plus approve/reject requests, mark returns, search members, send notices, add/delete books |
| `admin` | Everything a librarian can, plus change user roles via the profile page |

To make someone a librarian or admin, log in as admin, go to **Members**, click their name, and use the "Change Role" buttons at the bottom of their profile.

---

## Late fees

Late fees are calculated automatically in the browser using this formula:

- **Rs. 50** flat fee the first day after the due date
- **+ Rs. 50** for every additional week overdue

So if a book is 9 days overdue, the fee is Rs. 50 (base) + Rs. 50 (one extra week) = **Rs. 100**.

The fee shows up on the book card, on the request card in the librarian view, and gets recorded when the book is marked as returned.

---

## How notifications work

The app sends notifications automatically for these events:

- Request approved
- Request rejected
- Book returned (confirmed)
- Book due soon (within the day)
- Book overdue

Librarians and admins can also send **manual notices** from the Members page — either to all students at once, or to a specific student. These show up in the student's notification bell just like automatic ones. Manual notices do not replace or interfere with automatic ones.

Students see the bell icon in the top-right with a red badge showing unread count. Opening the Notifications page marks everything as read.

---

## Resetting all user data (starting over)

If you want to wipe all login/user data and start fresh, run these SQL statements in order in the Supabase SQL Editor:

```sql
-- Step 1: Clear application data
DELETE FROM notifications;
DELETE FROM borrow_requests;
DELETE FROM profiles;

-- Step 2: Delete all auth users
DELETE FROM auth.users;
```

> **Warning:** This is permanent. Books are not deleted by this process — only user accounts, borrow history, and notifications.

After doing this, sign up again through the app, verify your email, then run the admin promotion query again:

```sql
UPDATE profiles SET role = 'admin' WHERE email = 'your@email.com';
```

---

## Security overview

A few things that were deliberately put in place:

**Database level**
- Row-level security (RLS) is enabled on every table — users can only read/write what they're allowed to
- Students can't change their own role — that field is locked at the database level
- Sensitive operations (borrow requests, role changes, notices) go through `SECURITY DEFINER` stored procedures that verify the caller's role before doing anything
- An audit log table records every role change, borrow request, and notice sent
- All functions have a fixed `search_path` set to prevent search path injection attacks
- The `books_with_availability` view uses `security_invoker = true` so it respects the querying user's permissions, not the creator's

**Application level**
- Login attempts are rate-limited: 5 attempts per email per 15 minutes
- All user-supplied text is HTML-escaped before being inserted into the page
- Passwords must be at least 8 characters
- Email format is validated before any auth call is made
- All Supabase calls have a 9-second timeout so the UI never gets stuck loading forever
- Content Security Policy headers block external script injection
- `X-Frame-Options: DENY` prevents the page from being embedded in iframes (clickjacking protection)

---

## Tech used

| Thing | What it is |
|-------|------------|
| HTML/CSS/JS | Everything runs in a single file, no framework |
| [Supabase](https://supabase.com) | Postgres database + authentication + real-time |
| [Supabase JS v2.39.3](https://github.com/supabase/supabase-js) | Client library, loaded from CDN |
| [Font Awesome 6.4](https://fontawesome.com) | Icons |
| [Playfair Display + DM Sans](https://fonts.google.com) | Fonts via Google Fonts |

No React, no Vue, no build step, no package manager. Just a browser.

---

## Known limitations

- The wishlist is saved in `localStorage`, so it's per-device and per-browser. If you clear browser data, the wishlist is gone.
- There's no image upload — book covers are added by pasting a URL. Anything publicly hosted works (Google Books, Open Library, etc.).
- Email delivery for verification and password reset depends on Supabase's email service. Free tier has a limit of 3 emails per hour. For a school deployment, consider setting up a custom SMTP provider in the Supabase dashboard.
- The app doesn't support multiple copies of the same book being tracked individually — it tracks quantity as a number and manages availability based on active loan count vs. quantity.

---

## Frequently asked questions

**A student signed up but can't log in.**
They need to verify their email first. Check their inbox (and spam). The verification link takes them back to the app and activates their account.

**A student's profile shows no data.**
This usually means the database trigger that creates their profile row didn't fire. Go to the SQL Editor and check if a row exists in `profiles` for their user ID. If not, you can insert it manually, or ask them to sign out and back in.

**Members page shows "No members found".**
This only shows students. If no one has signed up as a student yet, it will be empty. Librarians and admins don't appear in this list.

**The password reset link opened in a different browser than expected.**
This is handled. The app detects the recovery token in the URL regardless of which browser opens it and shows the reset password page automatically.

**I need to add a teacher role.**
The current roles are `student`, `librarian`, and `admin`. Adding a teacher would require updating the `CHECK` constraint in the `profiles` table and adding corresponding RLS policies and UI logic. It's doable but wasn't built into this version.

---

## Credits

Built for Bagmati School, Kathmandu, Nepal.

© 2026 Bagmati School Library
