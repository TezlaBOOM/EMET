<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

use App\Models\LlmAccount;

interface LlmDriverInterface
{
    /**
     * Synchroniczne wywołanie modelu za pomocą danego konta.
     */
    public function complete(CompletionRequest $request, LlmAccount $account): CompletionResponse;

    /**
     * Strumieniowanie tokenów z modelu (generator yield string).
     *
     * @return iterable<string>
     */
    public function stream(CompletionRequest $request, LlmAccount $account): iterable;

    /**
     * Generowanie wektorów embeddingów.
     */
    public function embed(EmbeddingRequest $request, LlmAccount $account): EmbeddingResponse;

    /**
     * Test łączności i latencji z kontem dostawcy.
     */
    public function testConnection(LlmAccount $account): ConnectionTestResult;
}
