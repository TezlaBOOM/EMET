<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Integrations\McpHubInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpServerApiController extends Controller
{
    public function __construct(
        protected McpHubInterface $mcpHub,
    ) {}

    /**
     * Endpoint JSON-RPC 2.0 wbudowanego serwera MCP Projekt-Emet
     */
    public function handleRpc(Request $request): JsonResponse
    {
        $payload = $request->all();
        $response = $this->mcpHub->handleServerRpc($payload);

        return response()->json($response);
    }

    /**
     * Zwraca manifest narzędzi i zasobów serwera MCP Projekt-Emet
     */
    public function manifest(): JsonResponse
    {
        return response()->json($this->mcpHub->getServerManifest());
    }
}

