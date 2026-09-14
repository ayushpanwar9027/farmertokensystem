<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Services\RbacService;
use App\Services\StaffService;

class StaffController
{
    private StaffService $staff;
    private RbacService $rbac;

    public function __construct()
    {
        $this->staff = new StaffService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.view', 'You do not have permission to view staff');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->staff->list(
            $actor,
            $request->only(['q', 'role', 'status', 'centre_id', 'district_id']),
            $page,
            $perPage
        );

        Response::success($result['data'], [
            'pagination' => $result['pagination'],
        ]);
    }

    public function store(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.manage', 'You do not have permission to create staff');

        $staff = $this->staff->create($actor, $request->all());

        Response::created([
            'staff' => $staff,
            'message' => 'Staff account created. Staff will set their own password via the forgot-password flow.',
        ]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.view', 'You do not have permission to view staff');

        $staff = $this->staff->find((int) $request->getParam('id'));

        Response::success([
            'staff' => $this->staff->show($actor, $staff),
        ]);
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.manage', 'You do not have permission to update staff');

        $staff = $this->staff->find((int) $request->getParam('id'));

        $updated = $this->staff->update($actor, $staff, $request->all());

        Response::success([
            'staff' => $updated,
            'message' => 'Staff updated',
        ]);
    }

    public function status(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.manage', 'You do not have permission to change staff status');

        $staff = $this->staff->find((int) $request->getParam('id'));

        $updated = $this->staff->changeStatus($actor, $staff, (string) ($request->input('status') ?? ''));

        Response::success([
            'staff' => $updated,
            'message' => 'Staff status updated',
        ]);
    }

    public function centre(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'staff.manage', 'You do not have permission to assign staff to centres');

        $staff = $this->staff->find((int) $request->getParam('id'));
        $centreId = (int) ($request->input('centre_id') ?? 0);

        if ($centreId <= 0) {
            throw new ValidationException(['centre_id' => ['Centre id is required']]);
        }

        $updated = $this->staff->assignCentre($actor, $staff, $centreId);

        Response::success([
            'staff' => $updated,
            'message' => 'Staff centre assignment updated',
        ]);
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