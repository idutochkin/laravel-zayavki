<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FEATURE-ТЕСТ — проверяет приложение «снаружи»: шлём HTTP-запрос, смотрим ответ и состояние БД.
 * Настоящего веб-сервера нет: Laravel прогоняет запрос через себя прямо в процессе PHPUnit.
 *
 * БД для тестов — SQLite в памяти (см. phpunit.xml), она живёт только во время прогона.
 *
 * Запуск:  php artisan test                     — всё
 *          php artisan test --filter=AuthTest   — один класс
 */
class AuthTest extends TestCase
{
    // Перед тестами накатывает миграции, а каждый тест оборачивает в транзакцию и откатывает её.
    // Поэтому тесты не видят данные друг друга.
    use RefreshDatabase;

    public function test_user_can_register_and_gets_a_token(): void
    {
        // postJson шлёт запрос с заголовками Accept/Content-Type: application/json
        $response = $this->postJson('/api/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
        ]);

        $response
            ->assertCreated() // 201
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'ivan@example.com');

        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ivan@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Другой Иван',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
        ])
            ->assertUnprocessable() // 422
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_login_and_use_the_token(): void
    {
        // Фабрика ставит всем пароль "password"
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->json('token');

        // Настоящий запрос с настоящим токеном — проверяем всю цепочку Sanctum целиком
        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized(); // 401
        $this->getJson('/api/loan-applications')->assertUnauthorized();
    }
}
