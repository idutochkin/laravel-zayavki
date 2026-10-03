<?php

namespace App\StopFactors;

use App\Models\LoanApplication;

/**
 * Контракт одного стоп-фактора.
 *
 * Это обычный PHP-интерфейс, Laravel тут ни при чём. Но именно на интерфейсах держится
 * работа с сервис-контейнером: StopFactorChecker ничего не знает о конкретных проверках,
 * он получает список «чего-то, реализующего StopFactor». Какие именно классы туда попадут —
 * решает AppServiceProvider. Добавить новый стоп-фактор = написать класс + одна строка в провайдере.
 */
interface StopFactor
{
    /** Уникальный код — под ним результат сохраняется в stop_factor_results.code */
    public function code(): string;

    public function check(LoanApplication $application): Verdict;
}
