<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoanApplicationController;
use Illuminate\Support\Facades\Route;

/*
 * МАРШРУТЫ API.
 *
 * Аналог в Битриксе: urlrewrite.php / файлы роутинга D7 (/local/routes/*.php).
 *
 * Всё, что описано в этом файле:
 *  - автоматически получает префикс /api (см. bootstrap/app.php);
 *  - работает без сессий и кук — состояние между запросами не хранится, только токен.
 *
 * Посмотреть итоговую таблицу маршрутов: php artisan route:list
 */

// --- Публичные маршруты ---

Route::post('/register', [AuthController::class, 'register']);

// throttle:5,1 — MIDDLEWARE с параметрами: не больше 5 запросов в минуту с одного IP,
// дальше 429 Too Many Requests. Защита от перебора паролей.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// --- Маршруты, требующие токен ---
//
// MIDDLEWARE — слой, через который запрос проходит до контроллера (и ответ — после).
// Аналог: prefilters/postfilters (ActionFilter) в контроллерах D7.
// auth:sanctum ищет пользователя по токену из заголовка Authorization. Не нашёл — 401.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Должен стоять ВЫШЕ apiResource: иначе /loan-applications/stats совпадёт
    // с /loan-applications/{loan_application}, и Laravel будет искать заявку с id = "stats".
    Route::get('/loan-applications/stats', [LoanApplicationController::class, 'stats']);

    // apiResource одной строкой регистрирует пять REST-маршрутов:
    //   GET    /loan-applications                     → index
    //   POST   /loan-applications                     → store
    //   GET    /loan-applications/{loan_application}  → show
    //   PUT    /loan-applications/{loan_application}  → update   (и PATCH тоже)
    //   DELETE /loan-applications/{loan_application}  → destroy
    Route::apiResource('loan-applications', LoanApplicationController::class);

    // Действие, которое не ложится в CRUD, — отдельным маршрутом.
    Route::post('/loan-applications/{loan_application}/submit', [LoanApplicationController::class, 'submit']);
});
