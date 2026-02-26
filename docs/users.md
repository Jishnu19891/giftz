# Users

**Files:** `users/index.php`, `users/add.php`, `users/profile.php`
**Access:** User list and add: admin only. Profile: all logged-in users.

---

## User List (`users/index.php`)

**Access:** Admin only

Displays all registered user accounts.

### Columns

| Column | Notes |
|---|---|
| Name | |
| Email | Login identifier |
| Role | admin / staff badge |
| Status | active / inactive badge |
| Last Login | Timestamp of most recent login |
| Actions | Edit (links to profile) |

---

## Add User (`users/add.php`)

**Access:** Admin only

### Fields

| Field | Required | Notes |
|---|---|---|
| Name | Yes | Display name |
| Email | Yes | Must be unique; used as login |
| Password | Yes | Stored as bcrypt hash |
| Role | Yes | `admin` or `staff` |
| Status | Yes | `active` or `inactive` |

Inactive users cannot log in (login check validates `status = 'active'`).

---

## User Roles

| Role | Permissions |
|---|---|
| **admin** | Full access to all modules including user management, announcements, and admin-only delete actions |
| **staff** | Access to dashboard, POS, sales, purchases, products, categories, suppliers, customers, expenses (view/add/edit), and reports |

See [Security → Role Capabilities](security.md#role-capabilities) for the full matrix.

---

## Profile Page (`users/profile.php`)

**Access:** All logged-in users (each user sees their own profile)

### Edit Info Tab
- Update name and email
- Session variables (`$_SESSION['user_name']`, `$_SESSION['user_email']`) are updated immediately — no re-login required
- Changes reflected in topbar and sidebar without a new login

### Password Tab
- **Current password** required before accepting a new one
- **Confirm password** field with live match indicator (field turns green/red as user types)
- New password is stored as bcrypt hash via `password_hash()`

### KPI Cards (personal stats)

| Card | Calculation |
|---|---|
| **Total Sales** | COUNT of sales where `created_by = currentUser()` |
| **Lifetime Revenue** | SUM of `sales.total` for completed sales by this user |
| **Today's Sales** | COUNT and SUM for today |
| **Purchase Orders** | COUNT of POs created by this user |

### Recent Sales Table
Last 8 transactions processed by the logged-in user, with invoice links.

---

## Authentication Flow

```
1. User visits /login.php
2. POST email + password
3. attemptLogin($email, $password):
   a. SELECT user WHERE email = ? AND status = 'active'
   b. password_verify($password, $hash)
   c. On success: set $_SESSION vars, UPDATE last_login, redirect to dashboard
4. All admin pages call requireLogin() → redirects to /login.php if no session
```

See [Authentication & Authorization](security.md#authentication--authorization) for full details.

---

## Password Storage

Passwords are hashed with PHP's `password_hash()` using the `PASSWORD_DEFAULT` algorithm (currently bcrypt, cost factor 10+).

Verification uses `password_verify()` — timing-safe comparison, no raw password stored anywhere.

---

## Session Variables

| Variable | Value |
|---|---|
| `$_SESSION['user_id']` | User primary key |
| `$_SESSION['user_name']` | Display name |
| `$_SESSION['user_role']` | `'admin'` or `'staff'` |
| `$_SESSION['user_email']` | Email address |

---

## Default Accounts (Seed Data)

| Email | Password | Role |
|---|---|---|
| admin@giftz.local | Admin@123 | admin |
| staff@giftz.local | Admin@123 | staff |

> Change these passwords immediately in any environment accessible beyond localhost.

---

## Related Pages

- [Security](security.md) — full auth and role documentation
- [Dashboard](dashboard.md) — post-login landing page
