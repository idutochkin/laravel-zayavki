<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Результат одного стоп-фактора по одной заявке.
 * Таблица stop_factor_results — имя выведено из имени класса автоматически.
 */
#[Fillable(['code', 'passed', 'message'])]
class StopFactorResult extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean', // в БД tinyint(1), в PHP — настоящий bool
        ];
    }

    /** @return BelongsTo<LoanApplication, $this> */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
