<?php

namespace App\StopFactors;

use App\Models\LoanApplication;

/**
 * Стоп-фактор: сумма заявки превышает лимит.
 *
 * Лимит приходит в конструктор числом. Класс сам в конфиг не лезет — значение ему
 * передаёт контейнер (см. AppServiceProvider::register). Благодаря этому в юнит-тесте
 * достаточно написать new AmountLimitStopFactor(1000), без поднятия фреймворка.
 */
final class AmountLimitStopFactor implements StopFactor
{
    public function __construct(
        private readonly int $maxAmount,
    ) {}

    public function code(): string
    {
        return 'amount_limit';
    }

    public function check(LoanApplication $application): Verdict
    {
        if ($application->amount > $this->maxAmount) {
            return Verdict::fail(sprintf(
                'Сумма превышает лимит %s ₽',
                number_format($this->maxAmount, 0, ',', ' '),
            ));
        }

        return Verdict::pass();
    }
}
