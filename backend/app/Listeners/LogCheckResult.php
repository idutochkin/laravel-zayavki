<?php

namespace App\Listeners;

use App\Events\LoanApplicationChecked;
use Illuminate\Support\Facades\Log;

/**
 * СЛУШАТЕЛЬ события LoanApplicationChecked.
 *
 * Аналог: функция-обработчик, зарегистрированная через EventManager::addEventHandler().
 *
 * Регистрировать его нигде не нужно: Laravel сканирует папку app/Listeners и по типу параметра
 * в handle() сам понимает, на какое событие подписать класс. Проверить: php artisan event:list
 *
 * В реальном проекте здесь была бы отправка письма или пуша. Тогда слушателю добавляют
 * implements ShouldQueue — и он сам выполняется в очереди, не тормозя основной процесс.
 */
class LogCheckResult
{
    public function handle(LoanApplicationChecked $event): void
    {
        $application = $event->application;

        // Пишет в storage/logs/laravel.log
        Log::info('Заявка проверена', [
            'id' => $application->id,
            'status' => $application->status->value,
            'failed_stop_factors' => $application->stopFactorResults()
                ->where('passed', false)
                ->pluck('code') // достаёт одну колонку, возвращает Collection
                ->all(),
        ]);
    }
}
