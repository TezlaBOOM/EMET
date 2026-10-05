<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IntegrationInstance;
use App\Models\ProvisioningJob;
use App\Services\IntegrationManager\IntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function index(Request $request): View
    {
        $instances = IntegrationInstance::with('jobs')->latest()->get();

        return view('module-integrations::index', [
            'instances' => $instances,
            'activeSubcategory' => 'all',
            'filterType' => null,
        ]);
    }

    public function hermes(): View
    {
        $instances = IntegrationInstance::where('type', 'hermes')->with('jobs')->latest()->get();

        return view('module-integrations::index', [
            'instances' => $instances,
            'activeSubcategory' => 'hermes',
            'filterType' => 'hermes',
        ]);
    }

    public function openclaw(): View
    {
        $instances = IntegrationInstance::where('type', 'openclaw')->with('jobs')->latest()->get();

        return view('module-integrations::index', [
            'instances' => $instances,
            'activeSubcategory' => 'openclaw',
            'filterType' => 'openclaw',
        ]);
    }

    public function claude(): View
    {
        $instances = IntegrationInstance::where('type', 'claude_code')->with('jobs')->latest()->get();

        return view('module-integrations::index', [
            'instances' => $instances,
            'activeSubcategory' => 'claude',
            'filterType' => 'claude_code',
        ]);
    }

    public function codex(): View
    {
        $instances = IntegrationInstance::where('type', 'codex')->with('jobs')->latest()->get();

        return view('module-integrations::index', [
            'instances' => $instances,
            'activeSubcategory' => 'codex',
            'filterType' => 'codex',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:hermes,openclaw,claude_code,codex'],
            'mode' => ['required', 'string', 'in:systemd,docker'],
            'port' => ['nullable', 'integer', 'min:1024', 'max:65535'],
        ]);

        $instance = IntegrationInstance::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'],
            'mode' => $validated['mode'],
            'port' => $validated['port'] ?? null,
            'status' => 'provisioning',
        ]);

        $job = ProvisioningJob::create([
            'id' => (string) Str::uuid(),
            'instance_id' => $instance->id,
            'action' => 'provision',
            'status' => 'queued',
        ]);

        // Wykonanie provisioningu (w testach synchronicznie, w produkcji w kolejce)
        $this->integrationService->executeProvision($instance, $job);

        AuditLog::record('integration.provisioned', 'IntegrationInstance', (string) $instance->id, [
            'name' => $instance->name,
            'type' => $instance->type,
            'status' => $instance->fresh()->status,
        ]);

        return back()->with('success', __('integrations.provision_queued'));
    }

    public function reconcile(): RedirectResponse
    {
        $changes = $this->integrationService->reconcile();

        AuditLog::record('integrations.reconciled', 'System', null, [
            'changed_count' => count($changes),
        ]);

        return back()->with('success', __('integrations.reconciled_success') . ' Zmian: ' . count($changes));
    }

    public function destroy(IntegrationInstance $instance): RedirectResponse
    {
        $name = $instance->name;
        $id = (string) $instance->id;
        $instance->delete();

        AuditLog::record('integration.deleted', 'IntegrationInstance', $id, ['name' => $name]);

        return back()->with('success', __('integrations.instance_deleted'));
    }
}
