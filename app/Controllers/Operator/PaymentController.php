<?php

declare(strict_types=1);

namespace App\Controllers\Operator;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\PaymentService;
use App\Services\RbacService;

class PaymentController
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
        $this->rbac->assertCan($actor, 'payments.view');

        $filters = [
            'status' => $request->query('status'),
            'date' => $request->query('date'),
            'q' => $request->query('q'),
        ];

        $result = $this->payments->operatorList($actor, array_filter($filters, fn($v) => $v !== null));
        Response::success($result['items']);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'payments.view');

        $id = (int) $request->getParam('id');
        $result = $this->payments->show($actor, $id);
        Response::success($result);
    }

    public function release(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'payments.release');

        $id = (int) $request->getParam('id');
        $data = $request->all();
        $result = $this->payments->release($actor, $id, $data);
        Response::success($result);
    }

    private function requireActor(Request $request): array
    {
        $actor = $request->getUser();
        if ($actor === null) {
            throw new AuthorizationException('UNAUTHENTICATED', 'Authentication required');
        }
        return $actor;
    }
}
