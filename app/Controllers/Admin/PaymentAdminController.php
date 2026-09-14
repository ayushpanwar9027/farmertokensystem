<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\PaymentService;
use App\Services\RateService;
use App\Services\RbacService;
use App\Validators\PaymentValidator;

class PaymentAdminController
{
    private PaymentService $payments;
    private RateService $rates;
    private RbacService $rbac;
    private PaymentValidator $validator;

    public function __construct()
    {
        $this->payments = new PaymentService();
        $this->rates = new RateService();
        $this->rbac = new RbacService();
        $this->validator = new PaymentValidator();
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

        $result = $this->payments->adminList($actor, array_filter($filters, fn($v) => $v !== null));
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

    public function cancel(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'payments.cancel');

        $id = (int) $request->getParam('id');
        $data = $request->all();
        $result = $this->payments->cancel($actor, $id, $data);
        Response::success($result);
    }

    public function reverse(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'payments.reverse');
        $this->rbac->assertRoleLevel($actor, 70);

        $id = (int) $request->getParam('id');
        $data = $request->all();
        $result = $this->payments->reverse($actor, $id, $data);
        Response::success($result);
    }

    public function storeRate(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'rates.manage');

        $data = $this->validator->createRate($request->all());
        $result = $this->rates->createRate($actor, $data);
        Response::created($result);
    }

    public function updateRate(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'rates.manage');

        $id = (int) $request->getParam('id');
        $data = $this->validator->updateRate($request->all());
        $result = $this->rates->updateRate($actor, $id, $data);
        Response::success($result);
    }

    public function listRates(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'rates.manage');

        $filters = [];
        if ($request->query('crop_id')) {
            $filters['crop_id'] = (int) $request->query('crop_id');
        }
        if ($request->query('centre_id')) {
            $filters['centre_id'] = (int) $request->query('centre_id');
        }
        if ($request->query('is_active') !== null) {
            $filters['is_active'] = (int) $request->query('is_active');
        }
        if ($request->query('date')) {
            $filters['date'] = $request->query('date');
        }

        $result = $this->rates->listRates($filters);
        Response::success($result['items']);
    }

    public function getCropRates(Request $request): void
    {
        $actor = $this->requireActor($request);

        $cropId = (int) $request->getParam('cropId');
        $centreId = $request->query('centre_id') ? (int) $request->query('centre_id') : null;
        $date = $request->query('date') ?? date('Y-m-d');

        $result = $this->rates->getRatesForCrop($cropId, $centreId, $date);
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
