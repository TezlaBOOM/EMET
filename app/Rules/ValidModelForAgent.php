<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\LlmAccountPool;
use App\Models\LlmModel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidModelForAgent implements ValidationRule
{
    public function __construct(
        public ?int $poolId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $modelKey = (string) $value;

        // 1. Sprawdzenie w tabeli models (jeśli rejestr zawiera jakiekolwiek modele)
        if (LlmModel::count() > 0) {
            $model = LlmModel::where('model_key', $modelKey)->first();

            if (! $model) {
                $fail("Wybrany model '{$modelKey}' nie istnieje w centralnym rejestrze modeli AI.");

                return;
            }

            if (! $model->enabled) {
                $fail("Wybrany model '{$modelKey}' jest obecnie wyłączony w konfiguracji systemu.");

                return;
            }
        }

        // 2. Weryfikacja ograniczeń puli kont
        if ($this->poolId) {
            $pool = LlmAccountPool::find($this->poolId);
            if ($pool && ! empty($pool->allowed_models)) {
                if (! in_array($modelKey, $pool->allowed_models, true)) {
                    $fail("Model '{$modelKey}' nie jest dozwolony w wybranej puli kont '{$pool->name}'.");
                }
            }
        }
    }
}
