<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\QueueService;
use App\Services\RbacService;
use App\Validators\QueueValidator;

class QueueController
{
    private QueueService $queue;
    private QueueValidator $validator;
    private RbacService $rbac;

    public function __construct()
    {
        $this->queue = new QueueService();
        $this->validator = new QueueValidator();
        $this->rbac = new RbacService();
    }

    public function my(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.view_own', 'You do not have permission to view the queue');

        $entry = $this->queue->myEntry($actor);

        Response::success(['queue' => $entry]);
    }

    public function live(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.view', 'You do not have permission to view the queue');

        $data = $this->validator->live($request->only(['centre_id', 'date']));
        $date = (string) ($data['date'] ?? date('Y-m-d'));

        $live = $this->queue->live((int) $data['centre_id'], $date);

        Response::success(['live' => $live]);
    }

    public function status(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'queue.view_own', 'You do not have permission to view the queue');

        $entry = $this->queue->statusByToken($actor, (string) $request->getParam('bookingToken'));

        Response::success(['queue' => $entry]);
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