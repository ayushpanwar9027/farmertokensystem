<?php

declare(strict_types=1);

namespace App\Controllers\Operator;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\ProcurementService;
use App\Services\RbacService;

class ProcurementController
{
    private ProcurementService $procurements;
    private RbacService $rbac;

    public function __construct()
    {
        $this->procurements = new ProcurementService();
        $this->rbac = new RbacService();
    }

    public function start(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.create', 'You do not have permission to start procurements');

        $result = $this->procurements->start($actor, (int) $request->getParam('entryId'));

        Response::success($result);
    }

    public function capture(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.update', 'You do not have permission to update procurements');

        $procurement = $this->procurements->capture($actor, (int) $request->getParam('id'), $request->only([
            'accepted_weight', 'damaged_qty', 'grade', 'moisture_pct', 'quality_notes', 'operator_note', 'photo_ids',
        ]));

        Response::success(['procurement' => $procurement]);
    }

    public function submit(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.update', 'You do not have permission to submit procurements');

        $procurement = $this->procurements->submit($actor, (int) $request->getParam('id'));

        Response::success(['procurement' => $procurement]);
    }

    public function reject(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.reject', 'You do not have permission to reject procurements');

        $procurement = $this->procurements->reject($actor, (int) $request->getParam('id'), $request->only(['reason']));

        Response::success(['procurement' => $procurement]);
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.view_any', 'You do not have permission to view procurements');

        $result = $this->procurements->operatorList($actor, $request->only(['status', 'date', 'q']));

        Response::success($result);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.view_any', 'You do not have permission to view procurements');

        $procurement = $this->procurements->show($actor, (int) $request->getParam('id'));

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