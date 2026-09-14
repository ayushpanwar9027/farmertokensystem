<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\ScopeService;

class ScopeMiddleware implements MiddlewareInterface
{
    private string $mode;

    public function __construct(?string $param = null)
    {
        $this->mode = $param ?? 'auto';
    }

    public function handle(Request $request, callable $next): void
    {
        $user = $request->getUser();
        if ($user === null) {
            $this->deny('Authentication required');
            return;
        }

        $scopeService = new ScopeService();

        try {
            $this->enforce($request, $scopeService);
        } catch (AuthorizationException $e) {
            $this->logDenial($request, $user);
            $this->deny($e->getMessage());
            return;
        }

        $next($request);
    }

    private function enforce(Request $request, ScopeService $scopeService): void
    {
        $centreId = $this->extractId($request, ['centre_id', 'centreId', 'id']);
        $districtId = $this->extractId($request, ['district_id', 'districtId']);

        switch ($this->mode) {
            case 'centre':
                if ($centreId === null) {
                    throw new AuthorizationException('Centre scope could not be determined for this request');
                }
                $scopeService->assertCentreScope($request->getUser(), $centreId);
                return;

            case 'staff_centre':
                // Centre association helper for staff routes: the centre being
                // assigned (from request body) must be within the actor's scope.
                $assignedCentreId = $this->extractId($request, ['centre_id', 'centreId', 'centre']);
                if ($assignedCentreId === null) {
                    return;
                }
                $scopeService->assertCentreScope($request->getUser(), $assignedCentreId);
                return;

            case 'district':
                if ($districtId === null) {
                    throw new AuthorizationException('District scope could not be determined for this request');
                }
                $scopeService->assertUserScope($request->getUser(), $districtId);
                return;

            case 'queue_entry':
                $entryId = $request->getParam('entryId');
                if ($entryId === null || $entryId === '') {
                    throw new AuthorizationException('Queue entry scope could not be determined for this request');
                }
                $resolvedCentre = $this->resolveQueueEntryCentre((int) $entryId);
                if ($resolvedCentre === null) {
                    throw new AuthorizationException('Queue entry not found');
                }
                $scopeService->assertCentreScope($request->getUser(), $resolvedCentre);
                return;

            case 'procurement':
                $procurementId = $request->getParam('id');
                if ($procurementId === null || $procurementId === '') {
                    throw new AuthorizationException('Procurement scope could not be determined for this request');
                }
                $resolvedCentre = $this->resolveProcurementCentre((int) $procurementId);
                if ($resolvedCentre === null) {
                    throw new AuthorizationException('Procurement not found');
                }
                $scopeService->assertCentreScope($request->getUser(), $resolvedCentre);
                return;

            case 'payment':
                $paymentId = $request->getParam('id');
                if ($paymentId === null || $paymentId === '') {
                    throw new AuthorizationException('Payment scope could not be determined for this request');
                }
                $resolvedCentre = $this->resolvePaymentCentre((int) $paymentId);
                if ($resolvedCentre === null) {
                    throw new AuthorizationException('Payment not found');
                }
                $scopeService->assertCentreScope($request->getUser(), $resolvedCentre);
                return;

            case 'auto':
            default:
                if ($centreId !== null) {
                    $scopeService->assertCentreScope($request->getUser(), $centreId);
                    return;
                }
                if ($districtId !== null) {
                    $scopeService->assertUserScope($request->getUser(), $districtId);
                    return;
                }
                return;
        }
    }

    private function resolveQueueEntryCentre(int $entryId): ?int
    {
        $row = Database::selectOne(
            "SELECT centre_id FROM queue_entries WHERE id = ? LIMIT 1",
            [$entryId]
        );

        return $row !== null ? (int) $row['centre_id'] : null;
    }

    private function resolveProcurementCentre(int $procurementId): ?int
    {
        $row = Database::selectOne(
            "SELECT centre_id FROM procurements WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$procurementId]
        );

        return $row !== null ? (int) $row['centre_id'] : null;
    }

    private function resolvePaymentCentre(int $paymentId): ?int
    {
        $row = Database::selectOne(
            "SELECT centre_id FROM payments WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$paymentId]
        );

        return $row !== null ? (int) $row['centre_id'] : null;
    }

    private function extractId(Request $request, array $keys): ?int
    {
        foreach ($keys as $key) {
            $fromParam = $request->getParam($key);
            if ($fromParam !== null && $fromParam !== '') {
                return (int) $fromParam;
            }
            $fromQuery = $request->query($key);
            if ($fromQuery !== null && $fromQuery !== '') {
                return (int) $fromQuery;
            }
            $fromInput = $request->input($key);
            if ($fromInput !== null && $fromInput !== '') {
                return (int) $fromInput;
            }
        }
        return null;
    }

    private function logDenial(Request $request, ?array $user): void
    {
        $logPath = storage_path('logs/security.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(
            $logPath,
            json_encode([
                'timestamp' => gmdate('c'),
                'request_id' => Request::currentRequestId(),
                'level' => 'WARN',
                'type' => 'scope_denied',
                'user_id' => $user['id'] ?? null,
                'role' => $user['role'] ?? null,
                'method' => $request->method(),
                'endpoint' => $request->uri(),
                'params' => $request->getRouteParams(),
                'ip' => $request->ip(),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function deny(string $message): void
    {
        Response::error('SCOPE_DENIED', $message, 403);
    }
}