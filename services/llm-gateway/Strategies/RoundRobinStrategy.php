<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Strategies;

use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RoundRobinStrategy implements RoutingStrategyInterface
{
    public function selectAccount(Collection $availableAccounts, LlmAccountPool $pool): ?LlmAccount
    {
        $count = $availableAccounts->count();
        if ($count === 0) {
            return null;
        }

        $currentIndex = Cache::increment("llm_pool_rr_{$pool->id}");
        $index = ($currentIndex - 1) % $count;

        return $availableAccounts->values()->get($index);
    }
}
