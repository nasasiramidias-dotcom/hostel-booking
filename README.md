# HostelHub — System Overview

## 1) What this system does
HostelHub is a role-based web application for booking hostel rooms and managing the approval + confirmation workflow for payments.

Main actors:
- **Student**: browses hostels/rooms, requests bookings, cancels pending requests, submits payments.
- **Hostel Admin**: manages hostels/rooms and confirms bookings after payment approval.
- **Finance**: reviews pending payments, approves or rejects them, and records confirmation actions.
- **Super Admin**: manages users and views system-wide information.

## 2) High-level architecture
This is a **PHP + MySQL** application using **server-rendered pages** and a set of **JSON endpoints** under `api/`.

### Key directories / files
- `config/database.php`: Database connection wrapper (`Database` class) and activity logging.
- `includes/hostel_functions.php`: Central booking helper logic (availability checks, booking creation, cancellation).
- `dashboards/*.php`: Role dashboards (student/hostel_admin/finance/super_admin).
- `api/*.php`: Backend endpoints for booking, room/hostel management, payments, and payment confirmations.
- `ajax/*.php`: Lightweight AJAX endpoints (e.g., room availability lookups).
- `assets/*`: CSS/JS for UI.
- `uploads/payment_proofs/`: Uploaded payment proof documents.

## 3) Core workflow
### 3.1 Authentication & sessions
1. User logs in via `login.php`.
2. Credentials are verified against the `users` table.
3. Session fields like `$_SESSION['user_id']` and `$_SESSION['role']` are set.
4. User is redirected to the correct dashboard:
   - `student` → `dashboards/student.php`
   - `hostel_admin` → `dashboards/hostel_admin.php`
   - `finance` → `dashboards/finance.php`
   - `super_admin` → `dashboards/super_admin.php`

### 3.2 Browsing hostels and room availability
Students can browse hostels/rooms from the student dashboard.
- Availability is computed by checking overlapping bookings for the requested dates.
- JSON hostels listing is served by endpoints like:
  - `api/get_available_hostels.php`
  - `api/get_rooms.php`

### 3.3 Booking requests
Students submit a booking request to:
- `api/add_booking.php`

The backend:
- Validates the student session + request method.
- Ensures the selected dates are valid (check-in not in the past; check-out after check-in).
- Prevents double-booking by checking for overlapping bookings in `bookings`.
- Creates a record in `bookings` with:
  - `status = 'pending'`
  - `payment_status = 'unpaid'` (or equivalent pending flow)
- Creates a corresponding record in `payments` for tracking.

### 3.4 Payment submission
Students submit payment via:
- `api/make_payment.php`

Depending on payment method:
- **cash**: records intent to pay.
- **bank_transfer**: can require uploading a proof file to `uploads/payment_proofs/`.
- **mobile_money / credit_card**: typically records a transaction reference.

The payment record is updated to `status = 'paid'` along with `payment_method`, `transaction_id` (if provided), and `payment_date`.

### 3.5 Finance approval / rejection
Finance reviews payments using:
- `dashboards/finance.php` UI
- API endpoints:
  - `api/confirm_payment.php` (approve/confirm)
  - `api/reject_payment.php` (reject)

On approval:
- payment status moves toward `confirmed`
- booking status progresses accordingly (pending → confirmed)

On rejection:
- payment status becomes rejected
- booking can be cancelled or moved to rejected depending on business rules.

## 4) Data model (conceptual)
This system revolves around these main entities:
- **users**: authentication + roles (`student`, `hostel_admin`, `finance`, `super_admin`)
- **hostels**: hostel information (name, address/location, status)
- **rooms**: rooms per hostel, room type, capacity, price, and availability fields (e.g., `available_beds`, `status`)
- **bookings**: booking requests/holds with date ranges and statuses
- **payments**: payment attempts tracked per booking (pending/paid/confirmed/rejected)
- **activity_logs**: audit log of actions for administrative visibility

## 5) Availability logic (what “available” means)
The system uses date-range overlap logic:
- A room is considered conflicting if an existing booking overlaps the requested range.
- Overlaps are detected for relevant booking statuses (commonly `pending`/`confirmed` depending on endpoint).

This prevents double-booking for the same dates.

## 6) File upload handling
Payment proof uploads are supported for bank transfers.
- Endpoint: `api/make_payment.php`
- Storage: `uploads/payment_proofs/`
- The implementation checks MIME type and file size before saving.

## 7) Setup notes
A related README for setup exists in the repository:
- `README_ADMIN_SETUP.md` (admin/database/table setup guidance)
- `QUICK_SETUP_GUIDE.md` (quick run instructions)

There is also a separate database README in `../requisition_system/README.md`.

## 8) How to run locally (typical)
1. Ensure **WAMP/Apache** and **MySQL** are running.
2. Create/import the database schema.
3. Configure DB credentials in `config/database.php`.
4. Open the application entry point: `index.php`.

## 9) Admin/audit logging
`config/database.php` provides `logActivity($userId, $action, $details)` which writes to `activity_logs`.
This is used across multiple flows: login, booking request, payment actions, and admin operations.

---

### Quick reference endpoints
- Booking: `api/add_booking.php`
- Cancel booking: `api/cancel_booking.php`
- Payments submission: `api/make_payment.php`
- Finance confirmation: `api/confirm_payment.php`
- Finance rejection: `api/reject_payment.php`

(Other endpoints exist for hostels/rooms/users under `api/`.)

