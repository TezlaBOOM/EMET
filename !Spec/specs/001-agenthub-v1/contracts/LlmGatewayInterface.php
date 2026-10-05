<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

use App\Models\LlmAccount;
use App\Models\LlmAccountPool;

interface LlmGatewayInterface
{
    /**
     * Synchroniczne wywołanie modelu z automatycznym doborem konta z puli i obsługą failover.
     */
    public function complete(CompletionRequest $request, ?LlmAccountPool $pool = null): CompletionResponse;

    /**
     * Strumieniowanie tokenów z modelu (generator / Server-Sent Events / WebSocket).
     */
    public function stream(CompletionRequest $request, ?LlmAccountPool $pool = null): iterable;

    /**
     * Generowanie wektorów embeddingów dla tekstu lub tablicy tekstów.
     */
    public function embed(EmbeddingRequest $request, ?LlmAccount $account = null): EmbeddingResponse;

    /**
     * Sprawdzenie łączności z dostawcą/kontem i pomiar latencji.
     */
    public function testConnection(LlmAccount $account): ConnectionTestResult;
}
