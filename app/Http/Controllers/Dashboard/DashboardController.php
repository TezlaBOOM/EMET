<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Http\Controllers\Controller;
use App\Models\ModelPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected TelemetryCollectorInterface $telemetry
    ) {}

    public function index(Request $request): View
    {
        $period = $request->query('period', '24h');

        $activeAgents = $this->telemetry->getActiveAgentsSummary();
        $tokenMetrics = $this->telemetry->getTokenUsageMetrics($period);
        $performance = $this->telemetry->getPerformanceMetrics($period);
        $costs = $this->telemetry->getCostSummary($period);
        $integrations = $this->telemetry->getIntegrationsHealthSummary();

        return view('module-dashboard::overview', [
            'period' => $period,
            'activeAgents' => $activeAgents,
            'tokenMetrics' => $tokenMetrics,
            'performance' => $performance,
            'costs' => $costs,
            'integrations' => $integrations,
        ]);
    }

    public function tokens(Request $request): View
    {
        $period = $request->query('period', '24h');
        $tokenMetrics = $this->telemetry->getTokenUsageMetrics($period);

        return view('module-dashboard::tokens', [
            'period' => $period,
            'tokenMetrics' => $tokenMetrics,
        ]);
    }

    public function performance(Request $request): View
    {
        $period = $request->query('period', '24h');
        $performance = $this->telemetry->getPerformanceMetrics($period);

        return view('module-dashboard::performance', [
            'period' => $period,
            'performance' => $performance,
        ]);
    }

    public function costs(Request $request): View
    {
        $period = $request->query('period', '24h');
        $costs = $this->telemetry->getCostSummary($period);
        $pricing = ModelPricing::all();

        return view('module-dashboard::costs', [
            'period' => $period,
            'costs' => $costs,
            'pricing' => $pricing,
        ]);
    }

    /**
     * Reaktywny endpoint JSON dla widżetów / odświeżania co 5s
     */
    public function metricsJson(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'active_agents' => $this->telemetry->getActiveAgentsSummary(),
            'token_metrics' => $this->telemetry->getTokenUsageMetrics($period),
            'performance' => $this->telemetry->getPerformanceMetrics($period),
            'costs' => $this->telemetry->getCostSummary($period),
            'integrations' => $this->telemetry->getIntegrationsHealthSummary(),
        ]);
    }
}
