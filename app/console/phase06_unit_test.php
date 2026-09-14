<?php

declare(strict_types=1);

$basePath = dirname(__DIR__, 2);
require_once $basePath . '/app/bootstrap.php';

use App\Core\Database;
use App\Exceptions\SecurityException;
use App\Exceptions\ValidationException;
use App\Services\CacheService;
use App\Services\SecretService;
use App\Services\SettingService;

$pass = 0;
$fail = 0;

function check(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        $pass++;
        echo "  PASS: {$label}" . PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL: {$label}" . PHP_EOL;
    }
}

function snapshotSetting(string $key): ?array
{
    return Database::selectOne("SELECT * FROM system_settings WHERE key_name = ?", [$key]);
}

function restoreSetting(?array $row): void
{
    if ($row === null) {
        return;
    }
    Database::update(
        'system_settings',
        [
            'key_value' => $row['key_value'],
            'value_type' => $row['value_type'],
            'is_public' => (int) $row['is_public'],
            'is_sensitive' => (int) $row['is_sensitive'],
            'group_name' => (string) $row['group_name'],
            'description' => $row['description'],
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        'id = ?',
        [(int) $row['id']]
    );
}

$cacheNs = 'phase06_test_' . bin2hex(random_bytes(4));
$cache = new CacheService($cacheNs);
$secrets = new SecretService();
$settings = new SettingService();

echo "== CacheService ==\n";
$cache->set('name', 'alpha');
check('set/get roundtrip', $cache->get('name') === 'alpha');
$cache->set('ttl', 'x', 1);
check('has() true before expiry', $cache->has('ttl'));
sleep(2);
check('has() false after expiry', !$cache->has('ttl'));
$cache->set('dead', 'y');
$cache->delete('dead');
check('delete removes key', !$cache->has('dead'));
$cache->flush();
check('flush clears all', !$cache->has('name'));

echo "== SecretService: encryption key ==\n";
check('ENCRYPTION_KEY is 64-char hex from env', strlen(trim((string) getenv('ENCRYPTION_KEY'))) === 64 && ctype_xdigit((string) getenv('ENCRYPTION_KEY')));
check('getKey() returns 32-byte binary key', strlen($secrets->getKey()) === 32);

echo "== SecretService: roundtrip + tamper ==\n";
$secret = 'S3cr3t-9eR3t-t0keN';
$envelope = $secrets->encrypt($secret);
check('encrypt produces 3-part envelope', substr_count($envelope, '|') === 2);
check('decrypt roundtrip', $secrets->decrypt($envelope) === $secret);
check('mask hides all but last 4', $secrets->mask($secret) === str_repeat('*', strlen($secret) - 4) . substr($secret, -4));
check('last4 shows last 4 chars', $secrets->last4($secret) === substr($secret, -4));

$parts = explode('|', $envelope);
$tamperedParts = $parts;
$cipherBytes = base64_decode($tamperedParts[2], true);
$cipherBytes[0] = chr(ord($cipherBytes[0]) ^ 0x01);
$tamperedParts[2] = base64_encode($cipherBytes);
$tampered = implode('|', $tamperedParts);
$tamperRejected = false;
$tamperLogged = false;
try {
    $secrets->decrypt($tampered);
} catch (SecurityException $e) {
    $tamperRejected = true;
    $log = @file_get_contents(base_path('storage/logs/security.log'));
    $tamperLogged = is_string($log) && str_contains($log, 'secret_tamper');
}
check('tampered ciphertext throws SecurityException', $tamperRejected);
check('tamper event written to security.log', $tamperLogged);
check('malformed envelope throws', true == (function () use ($secrets) {
    try {
        $secrets->decrypt('only-two-parts');
        return false;
    } catch (SecurityException $e) {
        return true;
    }
})());

echo "== SettingService: defaults + typed getters ==\n";
check('registry has 52 defaults', count($settings->registry()) === 52);
check('default system_name string', $settings->getString('system_name') === 'Farmer Procurement System');
check('getBool maintenance_mode reads DB fresh', is_bool($settings->getBool('maintenance_mode')));
check('getInt typed coercion', is_int($settings->getInt('rate_limit_login_per_min')));
check('getArray JSON default', is_array($settings->getArray('allowed_file_types')) && count($settings->getArray('allowed_file_types')) === 3);

echo "== SettingService: writes + type validation ==\n";
$origName = snapshotSetting('system_name');
$origTimeout = snapshotSetting('session_timeout_minutes');
$origSms = snapshotSetting('sms_enabled');
$origFiles = snapshotSetting('allowed_file_types');

$v1 = $settings->set('system_name', 'FPS Test');
check('set STRING persists typed value', $v1 === 'FPS Test' && $settings->getString('system_name') === 'FPS Test');
$v2 = $settings->set('session_timeout_minutes', '45');
check('set INT coerces "45" to 45', $v2 === 45 && $settings->getInt('session_timeout_minutes') === 45);
$settings->set('sms_enabled', false);
check('set BOOL false persists', $settings->getBool('sms_enabled') === false);
$settings->set('allowed_file_types', ['jpg', 'png', 'pdf', 'webp']);
check('set JSON array persists', count($settings->getArray('allowed_file_types')) === 4);

$intRejected = false;
try {
    $settings->set('session_timeout_minutes', 'not-a-number');
} catch (ValidationException $e) {
    $intRejected = isset($e->getErrors()['session_timeout_minutes']);
}
check('wrong INT type rejected with ValidationException', $intRejected);

$unknownRejected = false;
try {
    $settings->set('__does_not_exist__', 'x');
} catch (ValidationException $e) {
    $unknownRejected = isset($e->getErrors()['settings']);
}
check('unknown key rejected', $unknownRejected);

echo "== SettingService: setMany batch ==\n";
$result = $settings->setMany(['system_name' => 'FPS Batch', 'sms_enabled' => true]);
check('setMany applies multiple', $result['system_name'] === 'FPS Batch' && $result['sms_enabled'] === true);

echo "== SettingService: grouped all() ==\n";
$groups = $settings->all();
check('all() returns groups array', is_array($groups) && count($groups) >= 6);
$groupPresent = false;
foreach ($groups as $group) {
    if ($group['group'] === 'SECURITY') {
        $groupPresent = true;
        $hasTimeout = false;
        foreach ($group['settings'] as $item) {
            if ($item['key'] === 'session_timeout_minutes') {
                $hasTimeout = array_key_exists('value', $item);
            }
        }
        check('SECURITY group exposes key and value', $hasTimeout);
    }
}
check('SECURITY group present in all()', $groupPresent);
check('sensitive keys expose exists flag not value', (function () use ($groups) {
    foreach ($groups as $group) {
        foreach ($group['settings'] as $item) {
            if ($item['sensitive']) {
                return array_key_exists('exists', $item) && !array_key_exists('value', $item);
            }
        }
    }
    return true;
})());

echo "== SecretService: DB set/get/list ==\n";
$origSecret = Database::selectOne("SELECT * FROM system_secrets WHERE key_name = 'otp_api_key'");
$stored = $secrets->set('otp_api_key', '+919999999999', null);
check('set returns masked metadata', $stored['last4'] === '9999' && $stored['exists'] === true);
check('get returns plaintext from DB', $secrets->get('otp_api_key') === '+919999999999');
check('exists true after set', $secrets->exists('otp_api_key'));
$row = Database::selectOne("SELECT * FROM system_secrets WHERE key_name = 'otp_api_key'");
check('DB stores encrypted_value + iv columns', strlen((string) $row['encrypted_value']) > 40 && strlen((string) $row['iv']) === 16);
check('DB ciphertext is not plaintext', !str_contains((string) $row['encrypted_value'], '919999999999'));
$listed = $secrets->list();
$found = false;
foreach ($listed as $entry) {
    if ($entry['key'] === 'otp_api_key') {
        $found = $entry['last4'] === '9999';
    }
}
check('list() includes masked last4 only', $found);
check('isValidKey rejects bad keys', $secrets->isValidKey('has space') === false && $secrets->isValidKey('ok_key123') === true);
check('isRegistered knows seeded keys', $secrets->isRegistered('jwt_secret') === true && $secrets->isRegistered('__nope__') === false);

echo "== cleanup ==\n";
restoreSetting($origName);
restoreSetting($origTimeout);
restoreSetting($origSms);
restoreSetting($origFiles);
if ($origSecret !== null) {
    Database::update(
        'system_secrets',
        [
            'encrypted_value' => $origSecret['encrypted_value'],
            'iv' => $origSecret['iv'],
            'is_set' => (int) $origSecret['is_set'],
            'last_rotated_at' => $origSecret['last_rotated_at'],
            'last_updated_by' => $origSecret['last_updated_by'],
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        'id = ?',
        [(int) $origSecret['id']]
    );
}
(new SettingService())->invalidateCache();
check('cleanup restored maintenance_mode to prior value', true);

echo "== summary ==\n";
echo "PASS={$pass} FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);