<?php

declare(strict_types=1);

namespace App\Controllers\Operator;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\QueueService;
use App\Services\RbacService;

class QueueOperatorController
{
    private QueueService $queue;
    private RbacService $rbac;

    public function __construct()
    {
        $this->queue = new QueueService();
        $this->rbac = new RbacService();
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.view', 'You do not have permission to view the queue');

        $result = $this->queue->operatorList($actor, $request->only(['centre_id', 'date', 'status']));

        Response::success($result);
    }

    public function callNext(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.call_next', 'You do not have permission to call the next farmer');

        $entry = $this->queue->callNext($actor, $request->only(['centre_id', 'date']));

        Response::success([
            'entry' => $entry,
            'message' => 'Next farmer called',
        ]);
    }

    public function skip(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.skip', 'You do not have permission to skip queue entries');

        $entry = $this->queue->skip($actor, (int) $request->getParam('entryId'), $request->only(['reason']));

        Response::success([
            'entry' => $entry,
            'message' => 'Queue entry skipped',
        ]);
    }

    public function noShow(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.no_show', 'You do not have permission to mark no-shows');

        $entry = $this->queue->noShow($actor, (int) $request->getParam('entryId'));

        Response::success([
            'entry' => $entry,
            'message' => 'Queue entry marked as no-show',
        ]);
    }

    public function recall(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.call_next', 'You do not have permission to recall queue entries');

        $entry = $this->queue->recall($actor, (int) $request->getParam('entryId'));

        Response::success([
            'entry' => $entry,
            'message' => 'Queue entry recalled',
        ]);
    }

    public function stats(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.stats', 'You do not have permission to view queue statistics');

        $stats = $this->queue->stats($actor, $request->only(['centre_id', 'date']));

        Response::success(['stats' => $stats]);
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