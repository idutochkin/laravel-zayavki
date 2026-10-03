<?php

namespace App\StopFactors;

use App\Models\LoanApplication;

/**
 * Стоп-фактор: ИНН в чёрном списке.
 * Список приходит в конструктор из конфига — см. AppServiceProvider::register.
 */
final class BlacklistedInnStopFactor implements StopFactor
{
    /** @param list<string> $blacklist */
    public function __construct(
        private readonly array $blacklist,
    ) {}

    public function code(): string
    {
        return 'blacklisted_inn';
    }

    public function check(LoanApplication $application): Verdict
    {
        return in_array($application->inn, $this->blacklist, true)
            ? Verdict::fail('ИНН находится в стоп-листе')
            : Verdict::pass();
    }
}
