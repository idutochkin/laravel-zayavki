<?php

namespace App\Events;

use App\Models\LoanApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * СОБЫТИЕ — «проверка заявки завершена».
 *
 * Аналог в Битриксе: new Event('module', 'OnLoanApplicationChecked', [...]) + $event->send().
 * Отличие: событие здесь — не строка, а класс. Его нельзя вызвать с опечаткой,
 * а IDE покажет всех, кто его бросает и слушает.
 *
 * Сам класс — просто контейнер данных. Бросается так: LoanApplicationChecked::dispatch($application).
 */
class LoanApplicationChecked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LoanApplication $application,
    ) {}
}
