<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\ApprovalService;
use App\Services\ProcurementService;
use App\Services\RbacService;

class ApprovalController
{
    private ApprovalService $approvals;
    private ProcurementService $procurements;
    private RbacService $rbac;

    public function __construct()
    {
        $this->approvals = new ApprovalService();
        $this->procurements = new ProcurementService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.approve', 'You do not have permission to approve procurements');

        $result = $this->procurements->approvalList($actor, $request->only(['centre_id', 'status']));

        Response::success($result);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.approve', 'You do not have permission to approve procurements');

        $procurement = $this->procurements->approvalShow($actor, (int) $request->getParam('id'));

        Response::success(['procurement' => $procurement]);
    }

    public function approve(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.approve', 'You do not have permission to approve procurements');

        $procurement = $this->approvals->approve($actor, (int) $request->getParam('id'));

        Response::success(['procurement' => $procurement]);
    }

    public function reject(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.approve', 'You do not have permission to reject procurements');

        $procurement = $this->approvals->reject($actor, (int) $request->getParam('id'), $request->only(['reason']));

        Response::success(['procurement' => $procurement]);
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }
        return $user;
    }
}