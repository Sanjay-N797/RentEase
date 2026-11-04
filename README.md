# RentEase – Rental Vehicle Management System

A full-stack web application for managing rental vehicle bookings built with **PHP 8 (PDO)**, **MySQL (InnoDB)**, **HTML5**, **CSS3**, and **vanilla JavaScript**. XAMPP-ready.

---

## 🚀 Setup Instructions

### Prerequisites
- **PHP 8.0+** with PDO MySQL extension
- **MySQL 5.7+** / MariaDB 10.3+
- **Apache** with `mod_rewrite` (or any PHP-capable server)
- Alternatively: **XAMPP** (includes all of the above)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Sanjay-N797/RentEase.git
   cd RentEase
   ```

2. **Create the database and import data**
   ```bash
   mysql -u root -e "SOURCE sql/schema.sql"
   mysql -u root -e "SOURCE sql/seed.sql"
   ```
   Or use **phpMyAdmin** → Import `sql/schema.sql`, then Import `sql/seed.sql`.

3. **Configure database credentials**
   Edit `config/db.php` if your MySQL user/password differs from `root` / empty:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'rentease_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Serve the application**
   - **XAMPP**: Copy this folder to `htdocs/rentease`, start Apache + MySQL.
   - **PHP built-in server**: `php -S localhost:8000` from the project root.

5. **Open in browser**
   - XAMPP: `http://localhost/rentease/`
   - Built-in server: `http://localhost:8000/`

6. Ensure `uploads/` directory is writable.

---

## 🔑 Default Login Credentials

| Role     | Email / Licence              | Password       | Login URL               |
|----------|------------------------------|----------------|-------------------------|
| **Admin**    | `admin@rentease.com`         | `Admin@123`    | `/admin/login.php`      |
| Customer | `arun@example.com`           | `Customer@123` | `/customer/login.php`   |
| Customer | `priya@example.com`          | `Customer@123` | `/customer/login.php`   |
| Customer | `rahul@example.com`          | `Customer@123` | `/customer/login.php`   |

> Customers can log in with either **email** or **licence number**.

---

## ✨ Features

### Customer Portal
- Register with licence number, email, and password (≥ 8 chars with letter & number)
- Login by licence **or** email
- Forgot / reset password (token-based, 1-hour expiry)
- Live dashboard with real-time vehicle feed (auto-refreshes every 30s)
- Explore vehicles with search, category chips, fuel type, date filters, and sorting
- Vehicle details + **Book Now** with eligibility checks
- My Bookings: cancel when cancellation policy allows
- Booking history & printable invoices
- Profile management & change password

### Admin Panel
- Dashboard with 5 key statistics
- Create / edit vehicles with validated photo uploads (random filename, size/MIME checks)
- Manage vehicles: search, filter, publish / unpublish / maintenance / close bookings / archive / delete
- Manage bookings: search, filter, mark returned, cancel, CSV export
- Reports: utilisation bars, category & city counts, CSV export

### Booking Engine
- CSRF-protected API endpoints
- Transaction-based with `SELECT … FOR UPDATE` row locking
- Validates: published status, availability window, licence/age eligibility, duplicate booking, date overlap
- Unique booking codes
- **Waitlist system**: auto-promotes waitlisted bookings when confirmed bookings are cancelled

### Security
- `password_hash` / `password_verify` (bcrypt)
- PDO prepared statements (`ATTR_EMULATE_PREPARES = false`)
- Output escaping with `htmlspecialchars`
- CSRF tokens on every POST (forms + fetch headers)
- Role-based access checks on every page/endpoint
- Session ID regeneration on login
- HttpOnly + SameSite=Lax cookies
- 5 failed logins → 5-minute lockout
- Upload validation (size, finfo MIME, `getimagesize`, random filename)
- `uploads/.htaccess` disables script execution
- CSV-injection guard on exports

---

## 📁 Project Structure

```
index.php                          # Landing page
config/db.php                      # Database config & PDO connection
includes/
  ├── auth.php                     # Authentication helpers
  ├── csrf.php                     # CSRF token management
  ├── header.php / footer.php      # Shared layout
  ├── upload.php                   # File upload handler
  └── booking_lib.php              # Booking logic
admin/
  ├── login.php / logout.php       # Admin auth
  ├── dashboard.php                # Admin dashboard
  ├── vehicles.php / vehicle_form.php  # Vehicle CRUD
  ├── bookings.php                 # Booking management
  └── reports.php                  # Reports & analytics
customer/
  ├── login.php / register.php     # Customer auth
  ├── forgot.php / reset.php       # Password recovery
  ├── dashboard.php / explore.php  # Browse vehicles
  ├── details.php                  # Vehicle details
  ├── bookings.php / history.php   # Booking management
  ├── invoice.php                  # Printable invoice
  └── profile.php                  # Profile management
api/
  ├── vehicles_feed.php            # JSON vehicle feed
  ├── book.php                     # Booking API
  └── cancel.php                   # Cancellation API
assets/
  ├── css/style.css                # Stylesheet
  └── js/app.js                    # Client-side JS
sql/
  ├── schema.sql                   # Database schema
  └── seed.sql                     # Sample data
uploads/                           # Vehicle photos
```

---

## 📝 Notes

- Pickup and return time fixed at 10:00 AM; booking duration = date difference in days.
- Back-to-back bookings (return day = next pickup day) are allowed.
- No mail server on localhost: the password reset link is shown on screen while `DEMO_SHOW_RESET_LINK` is `true` in `config/db.php`.
- Seeded vehicles use placeholder images; upload real photos from **Admin → Add/Edit Vehicle**.

---

## 📄 License

This project is for educational purposes.
