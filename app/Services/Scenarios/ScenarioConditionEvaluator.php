<?php

declare(strict_types=1);

namespace App\Services\Scenarios;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class ScenarioConditionEvaluator
{
    protected ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage;
    }

    /**
     * Bezpieczna ewaluacja warunku logicznego z użyciem Symfony ExpressionLanguage.
     *
     * @param  string  $expression  np. "input.status == 'ok' and score >= 80"
     * @param  array<string, mixed>  $variables
     */
    public function evaluate(string $expression, array $variables = []): bool
    {
        $expression = trim($expression);
        if ($expression === '' || $expression === 'true' || $expression === '1') {
            return true;
        }

        if ($expression === 'false' || $expression === '0') {
            return false;
        }

        try {
            $result = $this->expressionLanguage->evaluate($expression, $variables);

            return (bool) $result;
        } catch (Throwable) {
            return false;
        }
    }
}
