<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\PaymentService;
use App\Services\RbacService;

class MyPaymentController
{
    private PaymentService $payments;
    private RbacService $rbac;

    public function __construct()
    {
        $this->payments = new PaymentService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'view_own_payments', 'You do not have permission to view payments');

        $result = $this->payments->farmerStatement($actor);
        Response::success($result);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'view_own_payments', 'You do not have permission to view payments');

        $payment = $this->payments->farmerShow($actor, (int) $request->getParam('id'));
        Response::success(['payment' => $payment]);
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