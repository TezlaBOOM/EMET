<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Strategies;

use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use Illuminate\Support\Collection;

interface RoutingStrategyInterface
{
    /**
     * Wybiera konto z listy dostępnych (niebędących w cooldown/błędzie) kont w puli.
     *
     * @param Collection<int, LlmAccount> $availableAccounts
     */
    public function selectAccount(Collection $availableAccounts, LlmAccountPool $pool): ?LlmAccount;
}
