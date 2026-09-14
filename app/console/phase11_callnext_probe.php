<?php

declare(strict_types=1);

// Phase 11 parallel call-next probe. Spawned twice by phase11_e2e_verify.php.
$_base = $argv[1] ?? 'http://127.0.0.1:8090';
$_token = $argv[2] ?? '';
$_centreId = (int) ($argv[3] ?? 0);
$_date = $argv[4] ?? date('Y-m-d');

function probeCall(string $method, string $url, ?string $body = null, ?string $token = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'ignore_errors' => true,
        'content' => $body,
        'timeout' => 20,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0])) {
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
        $status = is_numeric($m[1] ?? 0) ? (int) $m[1] : 0;
    }
    return ['status' => $status, 'body' => $raw];
}

$r = probeCall('POST', $_base . '/api/v1/operator/queue/call-next', json_encode([
    'centre_id' => $_centreId,
    'date' => $_date,
]), $_token);

$d = json_decode((string) $r['body'], true);
echo json_encode([
    'status' => $r['status'],
    'code' => $d['error']['code'] ?? null,
    'token' => $d['data']['entry']['token'] ?? null,
]);