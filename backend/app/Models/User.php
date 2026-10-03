<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь. Модель идёт в составе Laravel, мы добавили две вещи:
 *  - трейт HasApiTokens (пакет Sanctum) — даёт метод createToken() для API-токенов;
 *  - связь loanApplications().
 *
 * #[Hidden] — поля, которые никогда не попадут в JSON при сериализации модели.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // 'hashed' — пароль хешируется автоматически при присваивании,
            // руками вызывать Hash::make() не нужно.
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<LoanApplication, $this> */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
