# 🚀 ADMIN SYSTEM - QUICK REFERENCE CARD

## ⚡ 5-Minute Setup

### Step 1: Database
```php
// Edit index.php lines 8-11
$host = 'localhost';
$db = 'bagmati_library';
$user = 'root';
$password = '';
```

### Step 2: Admin Account
```sql
INSERT INTO users (name, email, password, role) 
VALUES ('Admin', 'admin@bagmati.com', 'pass123', 'admin');
```

### Step 3: Login
- Click "Admin" link → Enter email/password → Dashboard loads

---

## 📊 6 Tabs Quick Reference

| Tab | What | How To Use |
|-----|------|-----------|
| **Overview** | 6 statistics | View dashboard |
| **Users** | Members list | Search by name |
| **Borrowing** | Transaction history | Filter by status |
| **Overdue** | Past-due books | ₹5/day auto-fine |
| **Fines** | Payment tracking | Mark as paid |
| **Reports** | Generate reports | Monthly/stats |

---

## 💰 Fine Calculation Cheat Sheet

```
Days Overdue = TODAY - Due Date
Fine = Days Overdue × ₹5

EXAMPLE:
Due: Jan 15, Today: Jan 22 = 7 days
Fine: 7 × ₹5 = ₹35 ✓
```

---

## 🔐 Admin Only!
- Only users with `role='admin'` can see Admin link
- Email-based login
- Password-protected

---

## 📖 Documentation
- **ADMIN_SETUP.md** ← START HERE
- **ADMIN_GUIDE.md** - Full features
- **README.md** - Overview

---

## 🐛 Troubleshooting

| Problem | Fix |
|---------|-----|
| No Admin link | User role must be 'admin' |
| Login fails | Check email in database |
| No data | Check DB connection |
| Wrong fine | Check due_date format |

---

## 📚 5 Most Common Tasks

1. **Check Overdue Books**
   - Click Overdue tab → See days & fine calculated ✓

2. **Search Member**
   - Click Users → Type name → See records ✓

3. **Update Fine Status**
   - Click Fines → Mark "Paid" ✓

4. **See Member History**
   - Click Borrowing → Filter by member ✓

5. **Generate Report**
   - Click Reports → Select type → Generate ✓

---

**Status**: ✅ Ready to Use  
**Version**: 1.0  
**Docs**: 1,900+ lines  
**Code**: 2,547 lines  

Happy administrating! 📚
