<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Services\Dashboard\DashboardService;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct(DashboardService::class);
    }

    public function getStats(Request $req, Response $res): Response
    {
        return $this->executar($req, $res, function () {
            return $this->servico->getStats();
        });
    }
}
