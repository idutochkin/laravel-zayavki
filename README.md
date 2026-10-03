# Заявки — учебный проект на Laravel

Мини-версия знакомой задачи: заявки на финансирование с **черновиками** и проверкой **стоп-факторов**.
Сделано, чтобы за выходные разобраться в Laravel, опираясь на опыт с Битрикс D7.

- `backend/` — Laravel 13, чистый JSON API
- `frontend/` — отдельное приложение на Vue 3, которое ходит в этот API

Это два независимых приложения: у каждого свои зависимости, свой процесс и свой порт. Общего кода у них нет,
общаются они только по HTTP.

Каждый файл в `backend/app` начинается с комментария: что это за сущность в Laravel и какой у неё аналог в Битриксе.

## Что делает приложение

1. Пользователь регистрируется или входит, получает токен.
2. Создаёт заявку — она сохраняется **черновиком** с любым набором полей, хоть пустая.
3. Нажимает «отправить» — тут срабатывает строгая валидация. Заявка получает статус `submitted`,
   в очередь уходит задача на проверку.
4. Воркер очереди прогоняет заявку через три стоп-фактора (лимит суммы, чёрный список ИНН, дубль по ИНН)
   и ставит `approved` или `rejected`.
5. По расписанию раз в сутки удаляются брошенные черновики.

## Запуск

Нужны PHP 8.3+ и Composer (`brew install php composer`).

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed     # создаст таблицы и демо-данные

php artisan serve              # API на http://localhost:8000
php artisan queue:work         # во втором терминале — воркер очереди
```

Демо-пользователь: `demo@example.com` / `password`.

Тесты: `php artisan test` (28 штук, проходят меньше чем за секунду).

### Проверить руками

```sh
# вход → токен
curl -s localhost:8000/api/login -H 'Accept: application/json' \
  -d email=demo@example.com -d password=password

export T='сюда_токен'

# список своих заявок
curl -s localhost:8000/api/loan-applications -H "Authorization: Bearer $T" -H 'Accept: application/json'

# создать пустой черновик
curl -s -X POST localhost:8000/api/loan-applications -H "Authorization: Bearer $T" -H 'Accept: application/json'

# попытаться отправить недозаполненный (id=1) → 422 со списком незаполненных полей
curl -s -X POST localhost:8000/api/loan-applications/1/submit -H "Authorization: Bearer $T" -H 'Accept: application/json'
```

Если воркер не запущен, отправленная заявка так и останется в статусе `submitted` — это наглядно показывает,
что проверка идёт вне HTTP-запроса. Чтобы проверки выполнялись сразу, поставь в `.env` `QUEUE_CONNECTION=sync`.

Полезные команды: `php artisan route:list`, `php artisan event:list`, `php artisan schedule:list`,
`php artisan tinker` (консоль, где можно выполнять код приложения: `LoanApplication::count()`).

## Фронт

Нужен Node.js 20.19+ или 22.12+. Бэкенд должен быть запущен (`php artisan serve` и `php artisan queue:work`).

```sh
cd frontend
npm install
cp .env.example .env
npm run dev                    # http://localhost:5173
```

Итого три процесса: API на 8000, воркер очереди, фронт на 5173.

### Где проходит граница между фронтом и Laravel

Открой вкладку Network в браузере и пройди сценарий — увидишь всё общение двух приложений.

| Что происходит | Фронт | Laravel |
|----------------|-------|---------|
| Запрос с другого порта | Браузер сначала шлёт `OPTIONS` (preflight) | Middleware `HandleCors` отвечает, что `api/*` доступно с любого origin |
| Авторизация | Токен в `localStorage`, заголовок `Authorization: Bearer` (`src/api.js`) | Middleware `auth:sanctum`, таблица `personal_access_tokens` |
| Токен протух | Ловит 401, выкидывает на экран входа (`src/main.js`) | `AuthenticationException` → 401 |
| Ошибки формы | Раскладывает `errors` из ответа 422 по полям (`ApplicationPage.vue`) | Form Request, ключи `errors` — имена полей из `rules()` |
| Можно ли редактировать | Только прячет кнопки по полю `can_edit` | Решает сам: политика (403) и проверка статуса (409) |
| Список по страницам | Читает `meta.current_page`, `meta.last_page` | `paginate()` + API Resource |
| Ожидание проверки | Раз в 2 секунды перезапрашивает заявку | Job в очереди меняет статус |

Главное: **во фронте нет ни одного правила валидации и ни одной проверки прав**. Он показывает то, что решил сервер.
Если бы правила жили в двух местах, они бы разъехались.

Читать: `src/api.js` → `src/auth.js` → `src/router.js` → `src/pages/ApplicationPage.vue`.

## В каком порядке читать код

Иди по пути одного запроса — так складывается картина. Всё в `backend/`.

| № | Файл | Что там |
|---|------|---------|
| 1 | `routes/api.php` | Маршруты, middleware, группа под токеном |
| 2 | `bootstrap/app.php` | Сборка приложения, жизненный цикл запроса |
| 3 | `app/Http/Controllers/LoanApplicationController.php` | Контроллер: route model binding, eager loading, пагинация, кеш |
| 4 | `app/Http/Requests/SaveDraftRequest.php` | Валидация черновика (всё необязательное) |
| 5 | `app/Http/Requests/SubmitLoanApplicationRequest.php` | Строгая валидация при отправке + проверка прав |
| 6 | `app/Policies/LoanApplicationPolicy.php` | Права: только свои заявки |
| 7 | `app/Models/LoanApplication.php` | Модель Eloquent: fillable, casts, связи, scope, события, soft delete |
| 8 | `database/migrations/2026_10_03_100000_...` | Схема таблицы, индексы |
| 9 | `app/Http/Resources/LoanApplicationResource.php` | Как модель превращается в JSON |
| 10 | `app/Jobs/RunStopFactorChecks.php` | Задача в очереди: ретраи, идемпотентность |
| 11 | `app/Services/StopFactorChecker.php` | Бизнес-логика, транзакция, событие |
| 12 | `app/Providers/AppServiceProvider.php` | Сервис-контейнер: как сервис получает свои зависимости |
| 13 | `app/StopFactors/*` | Интерфейс и три реализации |
| 14 | `app/Events/`, `app/Listeners/` | Событие и слушатель |
| 15 | `app/Console/Commands/PruneStaleDrafts.php`, `routes/console.php` | Консольная команда и расписание |
| 16 | `app/Http/Controllers/AuthController.php` | Токены Sanctum |
| 17 | `database/factories/`, `database/seeders/` | Тестовые данные |
| 18 | `tests/` | Feature- и unit-тесты; заодно это примеры использования API |

## Словарь: Laravel ↔ Битрикс

| Laravel | Битрикс | Где смотреть |
|---------|---------|--------------|
| Route | `urlrewrite.php`, роутинг D7 | `routes/api.php` |
| Controller | `Engine\Controller`, `class.php` компонента | `app/Http/Controllers` |
| Middleware | ActionFilter (prefilters) | `auth:sanctum`, `throttle` в `routes/api.php` |
| Eloquent Model | Таблет `DataManager` | `app/Models` |
| Relations (`hasMany`, `belongsTo`) | `Reference`, `OneToMany` в `getMap()` | `LoanApplication::stopFactorResults()` |
| Eager loading (`with`) | `select` со связями / ручная догрузка | `LoanApplicationController::index` |
| Migration | sprint.migration | `database/migrations` |
| Service Container | `ServiceLocator` | `AppServiceProvider::register` |
| Service Provider | `include.php` модуля, `init.php` | `app/Providers` |
| Event / Listener | `EventManager::addEventHandler` | `app/Events`, `app/Listeners` |
| События модели, Observer | ORM-события `OnBeforeAdd` и т.п. | `LoanApplication::booted` |
| Queue / Job | Kafka-продюсер + консьюмер | `app/Jobs` |
| Scheduler | Агенты на cron | `routes/console.php` |
| Artisan-команда | CLI-скрипт | `app/Console/Commands` |
| Cache | `Data\Cache`, managed cache | `LoanApplicationController::stats` |
| `config/*.php` + `.env` | `.settings.php`, `Option::get` | `config/loans.php` |
| Policy / Gate | `CanDoOperation`, права на сущности | `app/Policies` |
| Blade | `template.php` компонента | будет отдельной страницей на втором этапе |

Чего в Битриксе нет и что нужно просто запомнить:

- **Form Request** — валидация отдельным классом, выполняется до контроллера.
- **API Resource** — слой между моделью и JSON.
- **Facade** — `Cache::get()`, `DB::transaction()`, `Log::info()`: выглядит как статика, на деле вызов объекта из контейнера.
- **Collection** — обёртка над массивом с цепочками `map` / `filter` / `pluck` / `every`.
- **Factory / Seeder** — генерация тестовых данных.
- **Route Model Binding** — `{loan_application}` в URL приходит в метод готовой моделью.
- **Mass assignment** — `create([...])` заполняет только поля из `#[Fillable]`.

### Если на проекте Laravel постарше

Этот проект на Laravel 13. В более ранних версиях (особенно 10 и ниже) то же самое записано иначе — в чужом коде встретишь:

| Здесь | В старых проектах |
|-------|-------------------|
| `#[Fillable([...])]` над классом модели | `protected $fillable = [...];` |
| `#[Scope] protected function withStatus()` | `public function scopeWithStatus()` |
| `protected function casts(): array` | `protected $casts = [...];` |
| `bootstrap/app.php` (middleware, обработка ошибок) | `app/Http/Kernel.php`, `app/Exceptions/Handler.php` |
| Расписание в `routes/console.php` | `app/Console/Kernel.php`, метод `schedule()` |
| Слушатели находятся сами | Список в `EventServiceProvider::$listen` |
| Маршруты подключаются в `bootstrap/app.php` | `RouteServiceProvider` |

## Вопросы, которые задают на собеседованиях

Ответ на каждый есть в коде — найди место и объясни своими словами.

1. **Что такое N+1 и как лечить?** — `with()` в `LoanApplicationController::index`, `whenLoaded` в ресурсе.
2. **Что такое сервис-контейнер и зачем провайдеры?** Чем `register()` отличается от `boot()`? — `AppServiceProvider`.
3. **Что такое фасад?** — комментарий в `LoanApplicationController::stats`.
4. **Что такое mass assignment и как от него защищаются?** — `#[Fillable]` в модели, тест `test_status_and_owner_cannot_be_set_from_the_request`.
5. **Чем middleware отличается от политики?** — `routes/api.php` и `LoanApplicationPolicy`.
6. **Что будет, если job упал?** Что такое `tries`, `backoff`, `failed_jobs`? Почему job должен быть идемпотентным? — `RunStopFactorChecks`.
7. **Как обработать миллион строк?** Чем `chunkById` лучше `chunk`? — `PruneStaleDrafts`.
8. **Как работают транзакции?** — `StopFactorChecker::check`.
9. **Почему `env()` нельзя вызывать вне конфигов?** — `config/loans.php`.
10. **Как работает Sanctum? Чем токен отличается от JWT?** — `AuthController`.
11. **Что такое soft delete и global scope?** — `LoanApplication`, тест `test_draft_is_soft_deleted`.
12. **Чем feature-тест отличается от unit-теста?** Что делают `RefreshDatabase`, `Queue::fake()`? — `tests/`.
13. **`paginate()` против `get()`, `exists()` против `count()`** — контроллер и `DuplicateApplicationStopFactor`.
14. **Жизненный цикл запроса** — `bootstrap/app.php`.

## Упражнения — сделать самому

Читать мало, нужно написать руками. По возрастанию сложности:

1. **Новый стоп-фактор**: срок больше 60 месяцев — отказ. Класс в `app/StopFactors`, одна строка в провайдере, тест.
   Поправь число в `assertSame(3, ...)` в `SubmitLoanApplicationTest`.
2. **Новое поле** `contact_phone`: миграция (`php artisan make:migration add_contact_phone_to_loan_applications_table`),
   `#[Fillable]`, правила в обоих Form Request, ресурс, тест.
3. **Отзыв заявки**: `POST /loan-applications/{id}/withdraw` возвращает заявку из `submitted` в `draft`. Маршрут, метод контроллера, политика, тест.
   Подумай: что случится, если воркер возьмёт job уже после отзыва? (Ответ в `RunStopFactorChecks::handle`.)
4. **Уведомление**: второй слушатель события `LoanApplicationChecked`, который шлёт письмо (`php artisan make:mail`,
   `MAIL_MAILER=log` пишет письма в лог). Сделай его `implements ShouldQueue`.
5. **Фильтр и сортировка** в списке: `?amount_from=...&sort=amount`. Проверь запрос через `->toSql()` или `DB::listen()`.

Генераторы: `php artisan make:model`, `make:controller`, `make:request`, `make:policy`, `make:job`, `make:test` —
создают заготовку класса в правильной папке.
