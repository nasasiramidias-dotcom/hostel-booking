# Admin Setup (create_admins.php)

This project uses user roles to show different dashboards:
- `student` → `dashboards/student.php`
- `hostel_admin` → `dashboards/hostel_admin.php`
- `finance` → `dashboards/finance.php`
- `super_admin` → `dashboards/super_admin.php`

## What `create_admins.php` does
A one-time seeding script that creates default admin accounts in the `users` table:
- Super Admin
- Finance Admin
- Hostel Admin

It **won’t create duplicates** (it checks by `username` or `email`).

## How to run
1. Open `create_admins.php` and update the default credentials to your preferred usernames/emails/passwords.
2. Run the script in your browser:

   `http://localhost/Hostel_booking%20system/create_admins.php`

   (Replace `Hostel_booking system` with your actual folder name if different.)

3. The page will show what accounts were created or skipped.

## Login after seeding
Use the seeded `username` and `password` to login.

- Super Admin: role must be `super_admin`
- Finance Admin: role must be `finance`
- Hostel Admin: role must be `hostel_admin`

## Important security note
After creating the admin accounts, you should remove or disable `create_admins.php` in production.
Leaving it accessible publicly allows anyone to attempt admin seeding.

