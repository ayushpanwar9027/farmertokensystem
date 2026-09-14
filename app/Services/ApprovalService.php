<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\BadRequestException;
use App\Exceptions\CentreMismatchException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Procurement;
use App\Validators\ProcurementValidator;

class ApprovalService
{
    private Procurement $procurements;
    private ProcurementValidator $validator;
    private AuditService $audit;
    private ScopeService $scope;
    private PaymentService $payments;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->procurements = new Procurement();
        $this->validator = new ProcurementValidator();
        $this->audit = new AuditService();
        $this->scope = new ScopeService();
        $this->payments = new PaymentService();
        $this->notifications = new NotificationService();
    }

    public function approve(array $actor, int $id): array
    {
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);

        if ((string) $proc['status'] !== Procurement::STATUS_PENDING_APPROVAL) {
            throw new ConflictException('APPROVAL_POLICY_MISMATCH', 'Only pending-approval procurements can be approved');
        }

        $now = date('Y-m-d H:i:s');

        Database::update(
            'procurements',
            [
                'status' => Procurement::STATUS_VERIFIED,
                'approved_by' => (int) $actor['id'],
                'approved_at' => $now,
                'verified_at' => $proc['verified_at'] ?? $now,
                'verified_by' => (int) $actor['id'],
                'updated_at' => $now,
            ],
            'id = ? AND status = ?',
            [$id, Procurement::STATUS_PENDING_APPROVAL]
        );

        $payment = $this->payments->createForProcurement($id);

        $newValue = [
            'status' => Procurement::STATUS_VERIFIED,
            'approved_amount' => null,
        ];

        if ($payment !== null) {
            $newValue['payment'] = [
                'id' => (int) $payment['id'],
                'amount' => $payment['amount'],
                'status' => $payment['status'],
            ];
        }

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PROC_APPROVE',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => (int) $proc['id'],
            'new_value' => $newValue,
        ]);

        try {
            $this->notifications->dispatch('verification_approved', (int) $proc['user_id'], [
                'entity_id' => (int) $proc['id'],
                'entity_type' => 'procurement',
            ]);
        } catch (\Throwable $ignored) {
        }

        return $this->present($this->procurements->byId($id));
    }

    public function reject(array $actor, int $id, array $data): array
    {
        $reason = is_string($data['reason'] ?? null) && trim((string) $data['reason']) !== '' ? (string) $data['reason'] : '';
        if ($reason === '') {
            throw new BadRequestException('REASON_REQUIRED', 'A rejection reason is required');
        }

        $data = $this->validator->reject($data);
        $proc = $this->requireProcurement($id);
        $this->assertCentreScope($actor, (int) $proc['centre_id']);

        if ((string) $proc['status'] !== Procurement::STATUS_PENDING_APPROVAL) {
            throw new ConflictException('APPROVAL_POLICY_MISMATCH', 'Only pending-approval procurements can be rejected');
        }

        $reason = (string) $data['reason'];

        Database::update(
            'procurements',
            [
                'status' => Procurement::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ? AND status = ?',
            [$id, Procurement::STATUS_PENDING_APPROVAL]
        );

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'PROC_APPROVAL_REJECT',
            'module' => 'PROCUREMENT',
            'entity_type' => 'procurement',
            'entity_id' => (int) $proc['id'],
            'new_value' => ['status' => Procurement::STATUS_REJECTED],
            'reason' => $reason,
        ]);

        try {
            $this->notifications->dispatch('verification_rejected', (int) $proc['user_id'], [
                'entity_id' => (int) $proc['id'],
                'entity_type' => 'procurement',
                'reason' => $reason,
            ]);
        } catch (\Throwable $ignored) {
        }

        return $this->present($this->procurements->byId($id));
    }

    private function requireProcurement(int $id): array
    {
        $proc = $this->procurements->find($id);
        if ($proc === null) {
            throw new NotFoundException('PROC_NOT_FOUND', 'Procurement not found');
        }
        return $proc;
    }

    private function assertCentreScope(array $actor, int $centreId): void
    {
        if (!$this->scope->canAccessCentre($actor, $centreId)) {
            throw new CentreMismatchException();
        }
    }

    private function present(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'procurement_number' => $p['procurement_number'] ?? null,
            'booking_id' => (int) $p['booking_id'],
            'booking_crop_id' => isset($p['booking_crop_id']) && $p['booking_crop_id'] !== null ? (int) $p['booking_crop_id'] : null,
            'centre_id' => (int) $p['centre_id'],
            'crop_name' => (string) $p['crop_name'],
            'variety' => $p['variety'] ?? null,
            'quantity_kg' => (float) $p['quantity_kg'],
            'accepted_weight' => isset($p['accepted_weight']) ? (float) $p['accepted_weight'] : 0.0,
            'damaged_qty' => isset($p['damaged_qty']) ? (float) $p['damaged_qty'] : 0.0,
            'grade' => $p['quality_grade'] ?? null,
            'moisture_pct' => $p['moisture_percent'] !== null ? (float) $p['moisture_percent'] : null,
            'status' => (string) $p['status'],
            'rejection_reason' => $p['rejection_reason'] ?? null,
            'approved_amount' => isset($p['approved_amount']) && $p['approved_amount'] !== null ? (float) $p['approved_amount'] : null,
            'approved_by' => isset($p['approved_by']) && $p['approved_by'] !== null ? (int) $p['approved_by'] : null,
            'approved_at' => $p['approved_at'] ?? null,
        ];
    }
}