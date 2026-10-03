<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * ТОЧКА СБОРКИ ПРИЛОЖЕНИЯ.
 *
 * Жизненный цикл запроса:
 *   public/index.php → этот файл (создаётся контейнер, грузятся сервис-провайдеры)
 *   → глобальные middleware → поиск маршрута → middleware маршрута
 *   → контроллер → ответ идёт обратно через те же middleware.
 *
 * В Битриксе на этом месте — prolog_before.php + init.php: ядро поднимается, потом твой код.
 */

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',          // страницы с сессиями, куками и CSRF (Blade)
        api: __DIR__.'/../routes/api.php',          // API: префикс /api, без сессий — мы добавили эту строку
        commands: __DIR__.'/../routes/console.php', // консольные команды и расписание
        health: '/up',                              // готовый healthcheck для балансировщика
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Здесь регистрируют свои глобальные middleware и алиасы. Нам хватает штатных.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Для запросов к /api/* ошибки всегда отдаём в JSON, а не HTML-страницей.
        // Laravel сам превращает исключения в ответы: ValidationException → 422,
        // ModelNotFoundException → 404, AuthenticationException → 401, AuthorizationException → 403.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
