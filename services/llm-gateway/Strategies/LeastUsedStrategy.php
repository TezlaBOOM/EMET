<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Strategies;

use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use Illuminate\Support\Collection;

class LeastUsedStrategy implements RoutingStrategyInterface
{
    public function selectAccount(Collection $availableAccounts, LlmAccountPool $pool): ?LlmAccount
    {
        if ($availableAccounts->isEmpty()) {
            return null;
        }

        return $availableAccounts->sortBy(function (LlmAccount $account) {
            return $account->last_used_at ? $account->last_used_at->getTimestamp() : 0;
        })->first();
    }
}
