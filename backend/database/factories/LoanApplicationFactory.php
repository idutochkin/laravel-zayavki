<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ФАБРИКА — генератор тестовых объектов модели.
 *
 * В Битриксе прямого аналога нет: тестовые данные обычно создают руками или скриптом.
 * Здесь это штатный инструмент, на нём держатся и тесты, и сидеры.
 *
 * Использование:
 *   LoanApplication::factory()->create();                 // одна заявка + автоматически создаст владельца
 *   LoanApplication::factory()->count(5)->for($user)->create(); // пять заявок конкретного пользователя
 *   LoanApplication::factory()->submitted()->make();      // объект в памяти, без записи в БД
 *
 * Фабрика пишет в модель в обход $fillable, поэтому status здесь задавать можно.
 *
 * @extends Factory<LoanApplication>
 */
class LoanApplicationFactory extends Factory
{
    /**
     * Состояние по умолчанию: полностью заполненный черновик.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), // если владельца не передали — создаст нового пользователя
            'status' => ApplicationStatus::Draft,
            'company_name' => fake()->company(),
            'inn' => fake()->numerify('77########'), // 10 цифр, начинается на 77
            'amount' => fake()->numberBetween(100, 3000) * 1000,
            'term_months' => fake()->randomElement([6, 12, 24, 36]),
            'purpose' => fake()->sentence(),
        ];
    }

    // --- STATES: именованные вариации поверх definition() ---

    /** Пустой черновик: человек только открыл форму и нажал «сохранить». */
    public function blank(): static
    {
        return $this->state(fn () => [
            'company_name' => null,
            'inn' => null,
            'amount' => null,
            'term_months' => null,
            'purpose' => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Approved,
            'submitted_at' => now()->subHour(),
            'checked_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Rejected,
            'submitted_at' => now()->subHour(),
            'checked_at' => now(),
        ]);
    }
}
