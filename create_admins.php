<?php
/**
 * create_admins.php
 *
 * One-time script to create Super Admin / Finance / Hostel Admin accounts.
 *
 * Usage (from browser):
 *   http://localhost/Hostel_booking%20system/create_admins.php
 *
 * Or run via CLI:
 *   php create_admins.php
 *
 * IMPORTANT:
 *  - This script will NOT create duplicate usernames.
 *  - Replace default credentials below before running in production.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

// Change these defaults to your desired admin credentials
$seedUsers = [
    [
        'full_name' => 'Admin Master',
        'username'  => 'super_admin',
        'email'     => 'admin@system.com',
        'role'      => 'super_admin',
        'password'  => 'Super123',
    ],
    [
        'full_name' => 'mikejohnson',
        'username'  => 'mike_finance',
        'email'     => 'mike@finance.com',
        'role'      => 'finance',
        'password'  => 'mike123',
    ],
    [
        'full_name' => 'lillian kyalisiima',
        'username'  => 'lillian_admin',
        'email'     => 'lillian@hostel.com',
        'role'      => 'hostel_admin',
        'password'  => 'lillian123',
    ],
];

function outLine(string $msg): void {
    echo htmlspecialchars($msg) . "\n";
}

function userExists(PDO $db, string $username, string $email): bool {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    return (bool)$stmt->fetch();
}

$created = 0;
$skipped = 0;
$errors = 0;

outLine('=== Admin Seeding Script (create_admins.php) ===');
outLine('DB: ' . DB_NAME);
outLine('');

foreach ($seedUsers as $u) {
    try {
        $username = $u['username'];
        $email = $u['email'];
        $role = $u['role'];

        if (userExists($db, $username, $email)) {
            $skipped++;
            outLine("SKIP: $role '$username' already exists.");
            continue;
        }

        $hashed = password_hash($u['password'], PASSWORD_BCRYPT);

        $stmt = $db->prepare(
            "INSERT INTO users (full_name, username, email, password, role, status, created_at) "+
            "VALUES (?, ?, ?, ?, ?, 'active', NOW())"
        );

        $ok = $stmt->execute([
            $u['full_name'],
            $u['username'],
            $u['email'],
            $hashed,
            $u['role'],
        ]);

        if ($ok) {
            $created++;
            outLine("CREATE: $role '$username' created successfully.");
        } else {
            $errors++;
            outLine("ERROR: Could not create '$username' ($role)." );
        }
    } catch (Throwable $e) {
        $errors++;
        outLine('ERROR: ' . $e->getMessage());
    }
}

outLine('');
outLine('Done.');
outLine("Created: $created");
outLine("Skipped (already existed): $skipped");
outLine("Errors: $errors");

outLine('');
outLine('Next: You can login using the seeded admin accounts.');

