<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AppException;
use App\Exceptions\ConflictException;
use App\Services\AuditService;
use App\Validators\Validator;

class TestController
{
    public function echo(Request $request): void
    {
        $validator = new Validator();
        $data = $validator->validate($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'mobile' => 'required|mobile',
        ]);

        Response::success($data);
    }

    public function throwException(Request $request): void
    {
        throw new ConflictException('Deliberate test exception for error mapping verification');
    }

    public function audit(Request $request): void
    {
        $audit = new AuditService();
        $audit->log([
            'user_id' => null,
            'action' => 'TEST',
            'module' => 'AUDIT',
            'entity_type' => 'test',
            'entity_id' => 1,
            'new_value' => ['note' => 'AuditService verification call'],
        ]);

        Response::success(['audit_logged' => true]);
    }

    public function webMutate(Request $request): void
    {
        Response::success(['web_mutation' => 'ok']);
    }

    public function scopeCheck(Request $request): void
    {
        Response::success([
            'message' => 'Scope check passed',
            'centre_id' => (int) $request->getParam('centre_id'),
        ]);
    }
}