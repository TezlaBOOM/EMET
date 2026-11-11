<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Contracts\Integrations\ChatOpsChannelInterface;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ExternalChannel;
use App\Models\Scenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternalChannelController extends Controller
{
    public function __construct(
        protected ChatOpsChannelInterface $channelService,
    ) {}

    public function index(): View
    {
        $channels = ExternalChannel::with(['agent', 'scenario'])->latest()->get();
        $agents = Agent::where('is_active', true)->orderBy('name')->get();
        $scenarios = Scenario::orderBy('name')->get();

        return view('module-integrations::channels', [
            'channels' => $channels,
            'agents' => $agents,
            'scenarios' => $scenarios,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'platform' => ['required', 'string', 'in:telegram,slack,discord,teams,email'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'scenario_id' => ['nullable', 'integer', 'exists:scenarios,id'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        ExternalChannel::create([
            'name' => $validated['name'],
            'platform' => $validated['platform'],
            'agent_id' => $validated['agent_id'] ?: null,
            'scenario_id' => $validated['scenario_id'] ?: null,
            'webhook_secret' => $validated['webhook_secret'] ?: null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('integrations.channels.index')
            ->with('status', __('integrations.channels.created'));
    }

    public function destroy(ExternalChannel $channel): RedirectResponse
    {
        $channel->delete();

        return redirect()
            ->route('integrations.channels.index')
            ->with('status', __('integrations.channels.deleted'));
    }

    public function handleWebhook(Request $request, string $slug): JsonResponse
    {
        $channel = ExternalChannel::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return response()->json(['error' => 'Nieznany lub wyłączony kanał ChatOps.'], 404);
        }

        $result = $this->channelService->handleInboundWebhook($channel, $request->all());

        return response()->json($result);
    }
}
