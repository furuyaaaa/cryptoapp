<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardDataService $dashboardData) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->dashboardData->buildForUser($request->user()));
    }
}
