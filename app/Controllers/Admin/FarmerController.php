<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\FarmerService;
use App\Services\RbacService;

class FarmerController
{
    private FarmerService $farmers;
    private RbacService $rbac;

    public function __construct()
    {
        $this->farmers = new FarmerService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'view_farmers', 'You do not have permission to view farmers');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->farmers->list(
            $actor,
            $request->only(['q', 'district_id', 'status', 'verification_status']),
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
        $this->rbac->assertCan($actor, 'manage_farmers', 'You do not have permission to create farmers');

        $result = $this->farmers->create($actor, $request->all());

        Response::created($result);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'view_farmers', 'You do not have permission to view farmers');

        $farmer = $this->farmers->find((int) $request->getParam('id'));

        $this->farmers->assertWithinScope($actor, (int) $farmer['id']);

        Response::success([
            'farmer' => $farmer,
        ]);
    }

    public function resetPassword(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_farmers', 'You do not have permission to manage farmers');

        $result = $this->farmers->resetPassword($actor, (int) $request->getParam('id'), $request->all());

        Response::success($result);
    }

    public function status(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'manage_farmers', 'You do not have permission to manage farmers');

        $status = (string) ($request->input('status') ?? '');

        $result = $this->farmers->changeStatus($actor, (int) $request->getParam('id'), $status);

        Response::success($result);
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