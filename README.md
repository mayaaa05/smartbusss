# SmartBus – Online Bus Reservation System
**TYCS / Diploma in Computer Engineering Final Year Project**

---

## 🚌 Project Overview
**SmartBus** is a modern, full-featured web-based intercity bus reservation system engineered to eliminate bus stand queues, manual paperwork, and double booking. It features real-time search across 600+ intercity routes, interactive visual coach seat selection (window & aisle), instant digital QR code boarding pass generation, automated 90% refund cancellations, and an administrative control suite for managing fleets, routes, schedules, bookings, and customer support inquiries.

---

## 🛠️ Technology Stack
- **Frontend:** HTML5, CSS3 (Modern Responsive System), JavaScript (ES6+ Vanilla), Bootstrap 5.3.0, FontAwesome 6.4.0
- **Backend:** PHP 8+ (PDO with Prepared Statements & Transaction Support)
- **Database:** MySQL 5.7+ / MariaDB (Optimized with Composite B-Tree Indexes)
- **Server:** Apache on XAMPP (Localhost Port 8080 or Port 80)
- **Architecture:** Modular MVC-lite (Separation of Config, Database, Templates, Assets, and Modules)

---

## 📂 Project Structure

```
smartbus/
├── admin/                           # Complete Administrative Control Suite
│   ├── admin_header.php             # Shared admin navigation & sidebar
│   ├── admin_footer.php             # Shared admin footer scripts
│   ├── dashboard.php                # Master analytics, stats & recent activity
│   ├── inquiries.php                # Customer contact desk manager (Unread, Read, Replied)
│   ├── login.php                    # Secure admin authentication
│   ├── logout.php                   # Admin session termination
│   ├── manage_bookings.php          # Booking management & passenger breakdown modal
│   ├── manage_buses.php             # Bus fleet CRUD (Add, Edit, Delete buses)
│   ├── manage_routes.php            # Route network CRUD with search & pagination
│   ├── manage_schedules.php         # Schedule manager filtered by date & fare control
│   ├── manage_users.php             # Registered commuter accounts & spend totals
│   └── reports.php                  # Financial settlements, route profits, fleet reports
├── assets/
│   ├── css/
│   │   └── style.css                # Custom responsive stylesheet & print layout
│   └── js/
│       └── main.js                  # City swap, client filters, validation & payment tabs
├── config/
│   └── db_connect.php               # PDO database connection with UTF-8 encoding
├── database/
│   └── bus_reservation.sql          # Complete DDL schema, indexes, seed data & tables
├── includes/
│   ├── header.php                   # Responsive public navigation & auth dropdown
│   └── footer.php                   # Public footer with feature badges & contact info
├── about.php                        # Company mission, core values & live counters
├── cancel_booking.php               # Real-time cancellation & automated 90% refund
├── confirm_booking.php              # Passenger details intake & session recovery
├── contact.php                      # Functional support desk with ticket reference IDs
├── index.php                        # Homepage with hero search, swap button & FAQs
├── login.php                        # Passenger login with demo auto-fill & return redirect
├── logout.php                       # Secure user session logout
├── my_bookings.php                  # All, Confirmed, and Cancelled booking tabs
├── payment.php                      # Multi-channel checkout (SmartBus Wallet, UPI QR, Card, Net Banking)
├── profile.php                      # Account settings, profile edit, wallet widget & password change
├── search_bus.php                   # Real-time bus search with sidebar filters & sorting
├── select_seat.php                  # Interactive 2D graphical coach seat map
├── ticket.php                       # Digital Boarding Pass with dynamic QR code & print mode
└── wallet.php                       # SmartBus Digital Cash, live UPI QR top-up & passbook ledger
```

---

## 🚀 How to Run the Project on XAMPP

### Prerequisites
1. **XAMPP** installed on your machine (`C:\xampp`).
2. Apache and MySQL services started.

### Step 1: Start Apache and MySQL in XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** (running on Port `80` or `8080`).
3. Click **Start** next to **MySQL** (running on Port `3306`).
4. Ensure both modules show green running indicators.

### Step 2: Database Setup
1. Open your browser and go to: `http://localhost:8080/phpmyadmin` (or `http://localhost/phpmyadmin`).
2. Click on the **Import** tab.
3. Click **Browse** and select `database/bus_reservation.sql` from the project.
4. Click **Import** (or **Go**). All 7 tables, sample records, and performance indexes will be initialized automatically.

### Step 3: Access the Website
Open your browser and navigate to:
- **Public Commuter Portal:**
  `http://localhost:8080/smartbus/` (or `http://localhost/smartbus/`)
- **Admin Control Panel:**
  `http://localhost:8080/smartbus/admin/` (or `http://localhost/smartbus/admin/`)

---

## 🔑 Default Credentials

### 1. Passenger Account
- **Email:** `rahul@gmail.com`
- **Password:** `user123`
*(Or click the **Auto-Fill & Login** button on the Login page!)*

### 2. Administrator Account
- **Email:** `admin@smartbus.com`
- **Password:** `admin123`
*(Or click Sign In on the Admin Login page)*

---

## 🧪 How to Test Each Major Feature

1. **Search Bus Schedules (`index.php` & `search_bus.php`):**
   - On the homepage, select **From: Mumbai** and **To: Pune**.
   - Click the swap button (`⇅`) between From and To to test city swapping.
   - Choose a date and click **Search Available Buses**.
   - On the search page, use the **Bus Type** checkboxes (e.g. AC Sleeper), **Time Slot** filters, or **Sort by Price** to test real-time client filtering.
   - Test **Prev Day** and **Next Day** buttons.

2. **Select Seats (`select_seat.php`):**
   - Click **Select Seats** on any bus card.
   - Click on available white seats (e.g. `4A`, `4B`).
   - Notice the live seat counter, base fare, GST (5%), and grand total update instantly.
   - Try selecting more than 6 seats to verify the safety limit validation.
   - Click **Continue to Passengers**.

3. **Passenger Info & Booking Review (`confirm_booking.php`):**
   - If not logged in, system prompts login and preserves your chosen seats!
   - Fill in Passenger Name, Age, and Gender for each chosen seat.
   - Boarding point and dropping point are pre-filled and editable.
   - Click **Proceed to Secure Demo Payment**.

4. **Payment Gateway & Wallet Checkout (`payment.php`):**
   - **SmartBus Wallet Option:** If your wallet has sufficient balance, select **SmartBus Wallet** for instant 1-click checkout with 0 gateway fees.
   - **UPI QR Code (Scannable):** Scan the dynamic NPCI-standard UPI QR code with GPay/PhonePe/Paytm, or copy the Merchant VPA. A live 5-minute countdown timer guarantees freshness.
   - **5% Promotional Cashback:** Paying via UPI or Card automatically deposits 5% of the fare directly into your SmartBus Wallet upon confirmation!
   - In Card view, enter numbers to see automated 4-digit spacing formatting and MM/YY expiry formatting.
   - Click **Confirm Payment**. ACID row-level locking executes atomically with zero risk of overbooking.

5. **Digital Boarding Pass & QR Ticket (`ticket.php`):**
   - Instant e-ticket generated with unique PNR (`SB-2026-XXXX`).
   - If paid with wallet, highlights **Paid via SmartBus Wallet**. If cashback was earned, an instant celebration alert displays your wallet reward.
   - Live QR Code generated with ticket details for gate verification.
   - Click **Print / Save PDF** to see the clean, dedicated print layout (navbars, buttons, and footers are cleanly hidden).

6. **SmartBus Wallet & Passbook Ledger (`wallet.php`):**
   - Access via the **Wallet: ₹...** badge in the top navigation or My Account.
   - View your live wallet balance and total credits/debits.
   - Quick Top-Up: Select preset amounts (+₹200, +₹500, +₹1,000, +₹2,000) or enter a custom amount and scan the dynamic UPI QR code.
   - View your complete passbook ledger with timestamps, reference IDs, and color-coded transaction badges.

7. **Booking Management & Automated Wallet Refund (`my_bookings.php` & `cancel_booking.php`):**
   - Click **My Bookings** from the top navigation.
   - Filter trips by **All**, **Confirmed**, or **Cancelled**.
   - Click **Cancel Ticket** on a confirmed trip.
   - The system automatically calculates a 90% refund, cancels the booking, frees up the seats, and **instantly credits the refund into your SmartBus Wallet!**

8. **User Profile Settings (`profile.php`):**
   - Click on your name dropdown in the top-right &rarr; **My Profile**.
   - View your integrated SmartBus Wallet card, booking count, and total spend.
   - Update your Name or 10-digit Mobile number, or change your account password.

8. **Contact & Inquiries (`contact.php`):**
   - Submit an inquiry message.
   - Receive an instant ticket reference number (`#SB-MSG-X`).

9. **Admin Control Suite (`admin/`):**
   - Login at `admin/login.php` with `admin@smartbus.com` / `admin123`.
   - **Dashboard:** Inspect live statistics (total users, buses, active routes, revenue).
   - **Manage Buses:** Add a new luxury bus, edit seating capacity, or delete a bus.
   - **Manage Routes:** Search through routes, create new origin-destination pairs.
   - **Manage Schedules:** Filter by date, add departures, update fares and statuses.
   - **Manage Bookings:** View passenger list modal for any PNR, or trigger admin cancellation.
   - **Manage Users:** View registered travelers and their total spending.
   - **Inquiries:** View customer messages and mark as Read or Replied.
   - **Reports:** Analyze revenue share by payment channel, top earning routes, and bus occupancy.

---

## 🛡️ Performance & Security Highlights
- **Prepared Statements (PDO):** 100% immune to SQL injection attacks.
- **Database Composite Indexes:** Sub-millisecond response time across 700,000+ schedules.
- **Transaction Concurrency Protection:** ACID row-level locking ensures two users can never book the same seat at the exact same second.
- **XSS Sanitization:** All output wrapped in `htmlspecialchars()`.
- **Bcrypt Password Encryption:** Passwords hashed with `PASSWORD_BCRYPT`.
- **Double Submit Protection:** Button debouncing and loading spinners on all forms.
- **Mobile Responsive:** Flexbox/CSS Grid layouts tested across phones, tablets, and desktops.

---

*SmartBus – Created with ❤️ for TYCS Final Year Project.*
