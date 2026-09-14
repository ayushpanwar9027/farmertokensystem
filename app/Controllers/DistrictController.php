<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\District;

class DistrictController
{
    public function index(Request $request): void
    {
        $districts = (new District())->active();

        Response::success([
            'districts' => array_map(function (array $row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'state' => $row['state'],
                ];
            }, $districts),
        ]);
    }
}