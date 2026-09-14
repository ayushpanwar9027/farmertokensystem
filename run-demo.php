<?php

/**
 * run-demo.php — ONE-TIME demo seed for the TESTING subdomain only
 *
 * Usage:  https://testing.deepjyotimicrofinance.com/run-demo.php?confirm=YES
 *
 * Runs the SAME base migrate + seed as run-deploy.php, then additionally the
 * demo/SIH-showcase data (5 centres, slots, 15 farmers, 3 staff, bookings,
 * queue, tokens).
 *
 * TESTING SUBDOMAIN ONLY. NEVER run this on production.
 *
 * AFTER seeing "DONE", DELETE this file (and run-demo.done) via File Manager.
 *
 * SECURITY:
 *   - Requires ?confirm=YES
 *   - Runs only once (run-demo.done marker)
 *   - Checks .env exists
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$marker = __DIR__ . '/run-demo.done';
if (file_exists($marker)) {
    echo "ALREADY RUN (" . file_get_contents($marker) . ")\n";
    echo "Delete run-demo.php AND run-demo.done via File Manager.\n";
    exit(0);
}

$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'YES') {
    echo "Wrong usage. Access this URL:\n";
    echo "?confirm=YES\n";
    exit(1);
}

if (!file_exists(__DIR__ . '/.env')) {
    echo "ERROR: .env not found in the same folder as this file.\n";
    exit(1);
}

echo "=== Farmer Procurement System — Demo Runner (TESTING ONLY) ===\n\n";
echo "WARNING: This creates DEMO data with publicly known passwords.\n";
echo "NEVER run this on the production subdomain.\n\n";

// --- 1. Migrate (idempotent; skips if all applied) ---
$migratePath = __DIR__ . '/app/console/migrate.php';
if (file_exists($migratePath)) {
    echo "--- Migration ---\n";
    $argv = ['migrate.php', 'run'];
    try {
        require $migratePath;
    } catch (\Throwable $e) {
        echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "SKIP: app/console/migrate.php not found.\n";
}

// --- 2. Seed WITH demo data (--demo) ---
echo "\n--- Seed (base + --demo) ---\n";
$seedPath = __DIR__ . '/app/console/seed.php';
if (!file_exists($seedPath)) {
    echo "ERROR: app/console/seed.php not found.\n";
    exit(1);
}

try {
    $argv = ['seed.php', '--demo'];
    require $seedPath;
} catch (\Throwable $e) {
    echo "SEED FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 3. Done ---
file_put_contents($marker, date('Y-m-d H:i:s'));

echo "\n=== DONE ===\n";
echo "\nDEMO LOGINS (publicly known - change/remove before anything production-like):\n";
echo "  STAFF (portal login at /portal/):\n";
echo "    Super Admin : username 'demo_super_admin'  password 'Admin@1234'\n";
echo "    Manager     : username 'demo_manager'      password 'Admin@1234'\n";
echo "    Operator    : username 'demo_operator'     password 'Admin@1234'\n";
echo "  FARMER (mobile app): any of 9000000001..9000000015  password 'Demo@1234'\n";
echo "\nPORTAL TIPS (demo super admin):\n";
echo "  - Change the default password: click your name (top-right) > Change Password\n";
echo "  - Create FARMERS with a password: Administration > Farmers > + Farmer.\n";
echo "    The farmer can then login in the mobile app with that mobile + password.\n";
echo "  - Create staff: Administration > Staff > + Staff.\n";
echo "\nNEXT STEPS (do this NOW):\n";
echo "  1. DELETE run-demo.php and run-demo.done via File Manager\n";
echo "  2. Login at https://testing.deepjyotimicrofinance.com/portal/\n";