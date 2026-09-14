<?php

/**
 * run-deploy.php — ONE-TIME migrate + seed via browser URL
 *
 * Usage:  https://testing.deepjyotimicrofinance.com/run-deploy.php?confirm=YES
 *
 * AFTER seeing "DONE", DELETE this file immediately via File Manager.
 * If you need to re-run later (new migrations added), re-upload this file.
 *
 * SECURITY:
 *   - Requires ?confirm=YES (bots won't guess this)
 *   - Runs only once — creates run-deploy.done marker after success
 *   - Refuses to run if marker already exists
 *   - Checks .env exists before touching anything
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// --- 1. Marker check (run only once) ---
$marker = __DIR__ . '/run-deploy.done';
if (file_exists($marker)) {
    echo "ALREADY RUN (" . file_get_contents($marker) . ")\n";
    echo "Delete run-deploy.php AND run-deploy.done via File Manager.\n";
    exit(0);
}

// --- 2. Confirm parameter ---
$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'YES') {
    echo "Wrong usage. Access this URL:\n";
    echo "?confirm=YES\n";
    exit(1);
}

// --- 3. .env check ---
if (!file_exists(__DIR__ . '/.env')) {
    echo "ERROR: .env not found in the same folder as this file.\n";
    echo "Create it first from the .env.production template (see README-DEPLOY.txt).\n";
    exit(1);
}

echo "=== Farmer Procurement System — Deploy Runner ===\n\n";

// --- 4. Migrate ---
echo "--- Migration ---\n";
$migratePath = __DIR__ . '/app/console/migrate.php';
if (!file_exists($migratePath)) {
    echo "ERROR: app/console/migrate.php not found. Upload the full zip first.\n";
    exit(1);
}

// Capture migrate output (it calls exit on failure, so we need to handle that)
ob_start();
$exitCode = 0;
try {
    // Set $argv so migrate.php defaults to 'run'
    $argv = ['migrate.php', 'run'];
    require $migratePath;
} catch (\Throwable $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
    $exitCode = 1;
}
$migrateOutput = ob_get_clean();
echo $migrateOutput;

if ($exitCode !== 0) {
    echo "\nMigration failed. Fix the error and re-run this script.\n";
    exit(1);
}

// --- 5. Seed (idempotent, no --demo, no --reset) ---
echo "\n--- Seed ---\n";
$seedPath = __DIR__ . '/app/console/seed.php';
if (!file_exists($seedPath)) {
    echo "ERROR: app/console/seed.php not found.\n";
    exit(1);
}

try {
    $argv = ['seed.php'];  // no --demo, no --reset
    require $seedPath;
} catch (\Throwable $e) {
    echo "SEED FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 6. Done ---
file_put_contents($marker, date('Y-m-d H:i:s'));

echo "\n=== DONE ===\n";
echo "Deploy completed at " . date('Y-m-d H:i:s') . "\n\n";
echo "NEXT STEPS (do this NOW):\n";
echo "  1. DELETE run-deploy.php via File Manager\n";
echo "  2. DELETE run-deploy.done via File Manager\n";
echo "  3. Visit https://testing.deepjyotimicrofinance.com/health\n";
echo "     Expected: {\"status\":\"UP\",...}\n";
echo "  4. Visit https://testing.deepjyotimicrofinance.com/portal/\n";
echo "     Expected: login page loads\n";
echo "  5. Proceed to cron jobs (if needed later)\n";
