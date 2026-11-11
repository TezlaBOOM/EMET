<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Contracts\Integrations\McpHubInterface;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\McpServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class McpHubController extends Controller
{
    public function __construct(
        protected McpHubInterface $mcpHub,
    ) {}

    public function index(): View
    {
        $servers = McpServer::latest()->get();
        $serverManifest = $this->mcpHub->getServerManifest();

        return view('module-integrations::mcp', compact('servers', 'serverManifest'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'transport' => ['required', 'string', 'in:streamable_http,http_sse,stdio'],
            'endpoint_url' => ['nullable', 'string', 'max:255'],
            'command' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = Str::slug($validated['name']);
        if (McpServer::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $server = McpServer::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'transport' => $validated['transport'],
            'endpoint_url' => $validated['endpoint_url'] ?? null,
            'command' => $validated['command'] ?? null,
            'auth_token' => $validated['auth_token'] ?? null,
            'status' => 'active',
        ]);

        // Automatyczne odkrycie i synchronizacja narzędzi do rejestru Skilli
        $syncStats = $this->mcpHub->syncToolsToSkills($server);

        AuditLog::record('mcp_server.created', 'McpServer', (string) $server->id, [
            'name' => $server->name,
            'transport' => $server->transport,
            'synced_skills' => $syncStats['total'],
        ]);

        return back()->with('success', __('integrations.mcp_server_added', [
            'name' => $server->name,
            'count' => $syncStats['total'],
        ]));
    }

    public function sync(McpServer $server): RedirectResponse
    {
        $syncStats = $this->mcpHub->syncToolsToSkills($server);

        AuditLog::record('mcp_server.synced', 'McpServer', (string) $server->id, [
            'name' => $server->name,
            'created' => $syncStats['created'],
            'updated' => $syncStats['updated'],
        ]);

        return back()->with('success', __('integrations.mcp_server_synced', [
            'name' => $server->name,
            'count' => $syncStats['total'],
        ]));
    }

    public function destroy(McpServer $server): RedirectResponse
    {
        $name = $server->name;
        $id = (string) $server->id;
        $server->delete();

        AuditLog::record('mcp_server.deleted', 'McpServer', $id, [
            'name' => $name,
        ]);

        return back()->with('success', __('integrations.mcp_server_deleted'));
    }
}

