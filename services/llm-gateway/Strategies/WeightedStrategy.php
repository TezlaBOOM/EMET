<?php

declare(strict_types=1);

namespace App\Services\LlmGateway\Strategies;

use App\Models\LlmAccount;
use App\Models\LlmAccountPool;
use Illuminate\Support\Collection;

class WeightedStrategy implements RoutingStrategyInterface
{
    public function selectAccount(Collection $availableAccounts, LlmAccountPool $pool): ?LlmAccount
    {
        if ($availableAccounts->isEmpty()) {
            return null;
        }

        $totalWeight = 0;
        $weightedAccounts = [];

        foreach ($availableAccounts as $account) {
            $weight = (int) ($account->pivot?->custom_weight ?? $account->weight ?? 1);
            $weight = max(1, $weight);
            $totalWeight += $weight;
            $weightedAccounts[] = [
                'account' => $account,
                'weight' => $weight,
            ];
        }

        $random = mt_rand(1, $totalWeight);
        $current = 0;

        foreach ($weightedAccounts as $item) {
            $current += $item['weight'];
            if ($random <= $current) {
                return $item['account'];
            }
        }

        return $availableAccounts->first();
    }
}
