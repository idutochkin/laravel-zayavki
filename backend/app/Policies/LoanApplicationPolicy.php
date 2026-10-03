<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;

/**
 * ПОЛИТИКА — правила доступа к конкретной модели: «может ли этот пользователь сделать это с этой заявкой».
 *
 * Аналог в Битриксе: проверки прав через $USER->CanDoOperation() / права на элементы инфоблока,
 * только собранные в один класс рядом с моделью.
 *
 * Laravel находит политику сам по имени: модель LoanApplication → App\Policies\LoanApplicationPolicy.
 *
 * Вызывается так:
 *   Gate::authorize('update', $application);   // в контроллере: бросит 403, если нельзя
 *   $user->can('update', $application);        // где угодно: вернёт bool
 *
 * Первым аргументом всегда приходит текущий пользователь — Laravel подставляет его сам.
 *
 * Здесь проверяем только «чья заявка». Проверку «заявка ещё черновик» держим в контроллере:
 * это не вопрос прав (403), а вопрос состояния (409 Conflict).
 */
class LoanApplicationPolicy
{
    public function view(User $user, LoanApplication $application): bool
    {
        return $this->owns($user, $application);
    }

    public function update(User $user, LoanApplication $application): bool
    {
        return $this->owns($user, $application);
    }

    public function delete(User $user, LoanApplication $application): bool
    {
        return $this->owns($user, $application);
    }

    public function submit(User $user, LoanApplication $application): bool
    {
        return $this->owns($user, $application);
    }

    private function owns(User $user, LoanApplication $application): bool
    {
        return $application->user_id === $user->id;
    }
}
