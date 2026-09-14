<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\Slot;
use App\Services\RbacService;
use App\Services\SlotGenerator;
use App\Services\SlotService;

class SlotController
{
    private SlotService $slots;
    private SlotGenerator $generator;
    private RbacService $rbac;

    public function __construct()
    {
        $this->slots = new SlotService();
        $this->generator = new SlotGenerator();
        $this->rbac = new RbacService();
    }

    public function bookable(Request $request): void
    {
        $result = $this->slots->bookableList($request->only(['centre_id', 'date', 'from', 'to']));

        Response::success([
            'slots' => $result,
        ]);
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'slots.view', 'You do not have permission to view slots');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $result = $this->slots->list(
            $actor,
            $request->only(['centre_id', 'date_from', 'date_to', 'from', 'to', 'status']),
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
        $this->rbac->assertCan($actor, 'slots.view', 'You do not have permission to view slots');

        $slotId = (int) $request->getParam('id');
        $this->slotOr404($slotId);

        Response::success([
            'slot' => $this->slots->show($actor, $slotId),
        ]);
    }

    public function store(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'slots.manage', 'You do not have permission to create slots');

        $slot = $this->slots->create($actor, $request->only(['centre_id', 'date', 'start_time', 'end_time', 'capacity', 'status']));

        Response::created([
            'slot' => $slot,
            'message' => 'Slot created',
        ]);
    }

    public function update(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'slots.manage', 'You do not have permission to update slots');

        $slotId = (int) $request->getParam('id');
        $slot = $this->slotOr404($slotId);

        $status = (string) ($request->input('status') ?? '');
        if (strtoupper($status) === Slot::STATUS_CANCELLED) {
            $this->rbac->assertCan($actor, 'slots.cancel', 'You do not have permission to cancel slots');
            $updated = $this->slots->cancel($actor, $slot);
        } else {
            $updated = $this->slots->update($actor, $slot, $request->only(['date', 'start_time', 'end_time', 'capacity', 'status']));
        }

        Response::success([
            'slot' => $updated,
            'message' => 'Slot updated',
        ]);
    }

    public function destroy(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'slots.manage', 'You do not have permission to delete slots');

        $slotId = (int) $request->getParam('id');
        $slot = $this->slotOr404($slotId);

        $this->slots->delete($actor, $slot);

        Response::success([
            'message' => 'Slot deleted',
        ]);
    }

    public function generate(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'slots.manage', 'You do not have permission to generate slots');

        $data = $request->only(['date_from', 'date_to', 'centre_id']);
        $summary = $this->generator->generate(
            isset($data['centre_id']) && $data['centre_id'] !== '' ? (int) $data['centre_id'] : null,
            (string) ($data['date_from'] ?? ''),
            (string) ($data['date_to'] ?? '')
        );

        Response::success([
            'summary' => $summary,
            'message' => 'Slots generation completed',
        ]);
    }

    private function slotOr404(int $slotId): array
    {
        $slot = (new Slot())->find($slotId);
        if ($slot === null) {
            throw new NotFoundException('SLOT_NOT_FOUND', 'Slot not found');
        }
        return $slot;
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
