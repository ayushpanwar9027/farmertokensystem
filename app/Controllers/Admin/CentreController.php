<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\ProcurementCentre;
use App\Services\CentreService;
use App\Services\RbacService;

class CentreController
{
    private CentreService $centres;
    private RbacService $rbac;

    public function __construct()
    {
        $this->centres = new CentreService();
        $this->rbac = new RbacService();
    }

    public function list(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->centres->list(
            $request->getUser(),
            $request->only(['q', 'district_id', 'status']),
            $page,
            $perPage
        );

        Response::success($result['data'], [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'centres.view', 'You do not have permission to view centres');

        $centreId = (int) $request->getParam('id');
        $this->centreOr404($centreId);

        Response::success([
            'centre' => $this->centres->show($actor, $centreId),
        ]);
    }

    public function store(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'centres.manage', 'You do not have permission to create centres');

        $centre = $this->centres->create($actor, $request->all());

        Response::created([
            'centre' => $centre,
            'message' => 'Centre created',
        ]);
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'centres.manage', 'You do not have permission to update centres');

        $centreId = (int) $request->getParam('id');
        $centre = $this->centreOr404($centreId);

        $updated = $this->centres->update($actor, $centre, $request->all());

        Response::success([
            'centre' => $updated,
            'message' => 'Centre updated',
        ]);
    }

    public function status(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'centres.status.manage', 'You do not have permission to change centre status');

        $centreId = (int) $request->getParam('id');
        $centre = $this->centreOr404($centreId);

        $updated = $this->centres->changeStatus($actor, $centre, (string) ($request->input('status') ?? ''));

        Response::success([
            'centre' => $updated,
            'message' => 'Centre status updated',
        ]);
    }

    private function centreOr404(int $centreId): array
    {
        $centre = (new ProcurementCentre())->find($centreId);
        if ($centre === null) {
            throw new NotFoundException('CENTRE_NOT_FOUND', 'Procurement centre not found');
        }
        return $centre;
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