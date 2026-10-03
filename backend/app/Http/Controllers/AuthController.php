<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Регистрация, вход и выход для API.
 *
 * Авторизация — через пакет SANCTUM, токены в заголовке:
 *   Authorization: Bearer 1|AbCdEf...
 *
 * Схема простая: при входе создаём случайный токен, в таблицу personal_access_tokens
 * кладём его SHA-256-хеш, клиенту отдаём сам токен один раз. Дальше клиент шлёт его
 * в каждом запросе, а middleware auth:sanctum по хешу находит пользователя.
 * Это не JWT: токен ничего в себе не несёт и отзывается удалением строки из таблицы.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // Второй способ валидации — прямо в контроллере, без отдельного Form Request.
        // Годится для коротких случаев. validate() вернёт только проверенные поля,
        // а при ошибке сам прервёт выполнение и отдаст 422.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // unique:users,email — правило лезет в БД и проверяет, что такого email ещё нет
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        // Пароль захешируется сам — благодаря 'password' => 'hashed' в User::casts()
        $user = User::create($data);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Одно и то же сообщение для «нет такого email» и «неверный пароль» —
        // чтобы по ответу нельзя было перебирать, какие адреса зарегистрированы.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        // $request->user() — текущий пользователь, которого определил middleware auth:sanctum
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        // Удаляем только тот токен, с которым пришёл запрос. Остальные устройства остаются залогинены.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent(); // 204
    }
}
