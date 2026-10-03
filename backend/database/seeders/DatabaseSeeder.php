<?php

namespace Database\Seeders;

use App\Models\LoanApplication;
use App\Models\User;
use App\Services\StopFactorChecker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * СИДЕР — наполняет БД стартовыми/демо-данными.
 *
 * Запуск: php artisan db:seed
 * Или вместе с пересозданием схемы: php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    // Отключает события моделей на время сидинга (у нас это сброс кеша в LoanApplication::booted) —
    // при заливке сотен строк они только тормозят.
    use WithoutModelEvents;

    /**
     * Параметры run() подставляет сервис-контейнер — как в контроллерах и job'ах.
     */
    public function run(StopFactorChecker $checker): void
    {
        // Демо-пользователь. Пароль — "password" (так задано в UserFactory).
        $demo = User::factory()->create([
            'name' => 'Иван Демо',
            'email' => 'demo@example.com',
        ]);

        // for($demo) — привязать создаваемые заявки к этому пользователю (проставит user_id).
        // blank() — состояние из фабрики: все поля пустые.
        LoanApplication::factory()->for($demo)->blank()->create(['company_name' => 'ООО «Недозаполненный»']);
        LoanApplication::factory()->for($demo)->count(2)->create();

        // Две отправленные заявки прогоняем через настоящую проверку — чтобы у них были
        // результаты стоп-факторов. Первая пройдёт, вторая упрётся в лимит суммы.
        $checker->check(LoanApplication::factory()->for($demo)->submitted()->create());
        $checker->check(LoanApplication::factory()->for($demo)->submitted()->create([
            'amount' => config('loans.stop_factors.max_amount') + 1_000_000,
        ]));

        // Ещё один пользователь с заявками — чтобы проверить, что чужое не видно.
        LoanApplication::factory()->count(3)->for(User::factory())->create();
    }
}
