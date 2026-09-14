<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\ProcurementService;
use App\Services\RbacService;

class MyProcurementController
{
    private ProcurementService $procurements;
    private RbacService $rbac;

    public function __construct()
    {
        $this->procurements = new ProcurementService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.view_own', 'You do not have permission to view procurements');

        $result = $this->procurements->farmerList($actor, $request->only(['status', 'date', 'q']));

        Response::success($result);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'procurements.view_own', 'You do not have permission to view procurements');

        $procurement = $this->procurements->farmerShow($actor, (int) $request->getParam('id'));

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