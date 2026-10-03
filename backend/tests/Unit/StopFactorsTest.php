<?php

namespace Tests\Unit;

use App\Models\LoanApplication;
use App\StopFactors\AmountLimitStopFactor;
use App\StopFactors\BlacklistedInnStopFactor;
use PHPUnit\Framework\TestCase;

/**
 * UNIT-ТЕСТ — проверяет один класс в изоляции.
 *
 * Обрати внимание: наследуемся от PHPUnit\Framework\TestCase, а не от Tests\TestCase.
 * Laravel здесь вообще не загружается: нет контейнера, конфига, БД. Поэтому такие тесты
 * выполняются за миллисекунды. Это возможно, потому что стоп-факторы получают свои
 * настройки через конструктор, а не лезут в config() сами.
 */
class StopFactorsTest extends TestCase
{
    public function test_amount_within_the_limit_passes(): void
    {
        // new Model([...]) — объект в памяти, в БД ничего не пишется
        $application = new LoanApplication(['amount' => 1000]);

        $verdict = (new AmountLimitStopFactor(maxAmount: 1000))->check($application);

        $this->assertTrue($verdict->passed);
        $this->assertNull($verdict->message);
    }

    public function test_amount_over_the_limit_fails(): void
    {
        $application = new LoanApplication(['amount' => 1001]);

        $verdict = (new AmountLimitStopFactor(maxAmount: 1000))->check($application);

        $this->assertFalse($verdict->passed);
        $this->assertSame('Сумма превышает лимит 1 000 ₽', $verdict->message);
    }

    public function test_blacklisted_inn_fails(): void
    {
        $stopFactor = new BlacklistedInnStopFactor(['7700000000']);

        $this->assertFalse($stopFactor->check(new LoanApplication(['inn' => '7700000000']))->passed);
        $this->assertTrue($stopFactor->check(new LoanApplication(['inn' => '7701234567']))->passed);
    }
}
