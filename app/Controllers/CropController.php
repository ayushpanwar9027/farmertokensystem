<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Crop;

class CropController
{
    private Crop $crops;

    public function __construct()
    {
        $this->crops = new Crop();
    }

    public function index(Request $request): void
    {
        $rows = $this->crops->activeCatalog();

        $crops = array_map(function (array $row) {
            return [
                'id' => (int) $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'name_hi' => $row['name_hi'],
                'category' => $row['category'],
                'unit' => $row['unit'],
            ];
        }, $rows);

        Response::success(['crops' => $crops]);
    }
}
