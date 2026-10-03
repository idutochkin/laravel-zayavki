<?php

namespace App\Providers;

use App\Services\StopFactorChecker;
use App\StopFactors\AmountLimitStopFactor;
use App\StopFactors\BlacklistedInnStopFactor;
use App\StopFactors\DuplicateApplicationStopFactor;
use Illuminate\Support\ServiceProvider;

/**
 * СЕРВИС-ПРОВАЙДЕР — место, где приложение «собирается»: что и как создавать.
 *
 * Аналог в Битриксе: include.php модуля + init.php + секция services в .settings.php.
 * Сам фреймворк тоже состоит из провайдеров: маршруты, БД, очереди, кеш — каждый регистрирует свой.
 * Список провайдеров приложения — в bootstrap/providers.php.
 *
 * СЕРВИС-КОНТЕЙНЕР ($this->app) — аналог ServiceLocator из D7, но с автосборкой (autowiring):
 * если классу в конструкторе нужен другой класс, контейнер создаст его сам, без регистрации.
 * Регистрировать руками нужно только то, что он угадать не может: интерфейсы, скаляры, списки.
 * У нас как раз такие случаи.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * register() — ТОЛЬКО описываем привязки в контейнере. Ничего не выполняем:
     * в этот момент другие провайдеры могут быть ещё не загружены.
     */
    public function register(): void
    {
        // «Когда создаёшь AmountLimitStopFactor и ему нужен параметр $maxAmount —
        //  возьми значение из config('loans.stop_factors.max_amount')».
        // Это называется contextual binding.
        $this->app->when(AmountLimitStopFactor::class)
            ->needs('$maxAmount')
            ->giveConfig('loans.stop_factors.max_amount');

        $this->app->when(BlacklistedInnStopFactor::class)
            ->needs('$blacklist')
            ->giveConfig('loans.stop_factors.blacklisted_inns');

        // Тег — способ объединить несколько сервисов в группу.
        // Новый стоп-фактор подключается одной строкой здесь, сервис проверки не трогаем.
        $this->app->tag([
            AmountLimitStopFactor::class,
            BlacklistedInnStopFactor::class,
            DuplicateApplicationStopFactor::class,
        ], 'stop-factors');

        // «Когда создаёшь StopFactorChecker — в параметр $stopFactors передай всё с тегом stop-factors».
        $this->app->when(StopFactorChecker::class)
            ->needs('$stopFactors')
            ->giveTagged('stop-factors');
    }

    /**
     * boot() — вызывается, когда ВСЕ провайдеры уже зарегистрированы.
     * Здесь можно пользоваться любыми сервисами: вешать слушателей, настраивать валидатор и т.д.
     * Нам пока нечего: слушатели событий и политики Laravel находит сам по соглашениям об именах.
     */
    public function boot(): void
    {
        //
    }
}
