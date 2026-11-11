<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Contracts\FinOps\WorkspaceFinOpsInterface;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceFinOpsController extends Controller
{
    public function __construct(
        protected WorkspaceFinOpsInterface $finOpsService,
    ) {}

    public function index(): View
    {
        $workspaces = Workspace::orderBy('name')->get();

        return view('module-system::workspaces', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'daily_budget_usd' => ['required', 'numeric', 'min:0.1'],
            'monthly_budget_usd' => ['required', 'numeric', 'min:1'],
            'daily_token_limit' => ['required', 'integer', 'min:1000'],
            'alert_threshold_pct' => ['required', 'integer', 'min:10', 'max:100'],
            'fallback_model' => ['required', 'string', 'max:100'],
        ]);

        Workspace::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'daily_budget_usd' => (float) $validated['daily_budget_usd'],
            'monthly_budget_usd' => (float) $validated['monthly_budget_usd'],
            'daily_token_limit' => (int) $validated['daily_token_limit'],
            'alert_threshold_pct' => (int) $validated['alert_threshold_pct'],
            'fallback_to_local_on_exceed' => $request->boolean('fallback_to_local_on_exceed', true),
            'fallback_model' => $validated['fallback_model'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('system.workspaces.index')
            ->with('status', __('system.workspaces.created'));
    }
}
