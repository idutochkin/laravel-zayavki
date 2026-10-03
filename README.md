# Заявки — учебный проект на Laravel

Мини-версия знакомой задачи: заявки на финансирование с **черновиками** и проверкой **стоп-факторов**.
Сделано, чтобы за выходные разобраться в Laravel, опираясь на опыт с Битрикс D7.

- `backend/` — Laravel 13, чистый JSON API
- `frontend/` — отдельное приложение на Vue 3, которое ходит в этот API

Это два независимых приложения: у каждого свои зависимости, свой процесс и свой порт. Общего кода у них нет,
общаются они только по HTTP.

## Как по этому учиться

1. Запусти проект (раздел «Запуск») и пройди сценарий руками — через фронт или curl.
2. Открывай файлы по списку из раздела «В каком порядке читать код». В каждом файле сверху комментарий:
   что это за сущность и зачем она.
3. Встретил незнакомый термин — ищи его в «Словаре с примерами»: там объяснение простыми словами
   и два куска кода рядом, из Битрикса и из этого проекта.
4. Проверь себя по разделу «Вопросы, которые задают на собеседованиях».
5. Сделай хотя бы два упражнения из последнего раздела — без этого знание останется пассивным.

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

Если менял код, который выполняется в очереди, — перезапусти `queue:work`: воркер держит в памяти старую версию.

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

## Словарь с примерами: Laravel ↔ Битрикс

Для каждого термина: что это простыми словами, как та же задача решается в Битриксе и как — в этом проекте.
Код Битрикса упрощён и написан для той же предметной области (заявки), чтобы сравнивать было удобно.

### 1. Route — маршрут

**По-человечески:** правило «на такой адрес и такой HTTP-метод отвечает вот этот код». Таблица соответствия адресов и обработчиков.

В Битриксе — `urlrewrite.php` (адрес → физический файл) или роутинг D7:

```php
// /local/routes/api.php
return function (RoutingConfigurator $routes) {
    $routes->get('/api/loan-applications/{id}', [LoanApplicationController::class, 'get']);
    $routes->post('/api/loan-applications', [LoanApplicationController::class, 'add']);
};
```

В Laravel — `routes/api.php`:

```php
Route::get('/loan-applications/stats', [LoanApplicationController::class, 'stats']);
Route::apiResource('loan-applications', LoanApplicationController::class); // сразу 5 маршрутов CRUD
```

**Отличие:** почти никакого — роутинг D7 очень похож. Но здесь это единственный способ:
файлов-страниц вроде `/api/index.php` нет вообще, все запросы приходят в один `public/index.php`.

### 2. Controller — контроллер

**По-человечески:** класс, методы которого принимают HTTP-запрос и возвращают ответ. Точка входа в твой код.

В Битриксе:

```php
class LoanApplication extends \Bitrix\Main\Engine\Controller
{
    public function getAction(int $id): ?array
    {
        $row = LoanApplicationTable::getById($id)->fetch();
        if (!$row) {
            $this->addError(new Error('Заявка не найдена'));
            return null;
        }
        return $row; // ядро само обернёт в {"status":"success","data":{...}}
    }
}
```

В Laravel — `app/Http/Controllers/LoanApplicationController.php`:

```php
public function show(LoanApplication $loanApplication): LoanApplicationResource
{
    Gate::authorize('view', $loanApplication);

    return new LoanApplicationResource($loanApplication->load('stopFactorResults'));
}
```

**Отличие:** в Битриксе в метод приходит `$id`, и запись ищешь сам. В Laravel в метод приходит уже найденная
заявка (см. Route Model Binding ниже), а «не найдено» превращается в 404 без твоего участия.

### 3. Middleware — промежуточный слой

**По-человечески:** код, через который запрос проходит до контроллера, а ответ — после. Туда выносят то,
что нужно многим маршрутам сразу: проверить авторизацию, ограничить частоту запросов, добавить заголовки.

В Битриксе — фильтры действий контроллера:

```php
public function configureActions(): array
{
    return [
        'get' => [
            'prefilters' => [
                new ActionFilter\Authentication(),
                new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
            ],
        ],
    ];
}
```

В Laravel — `routes/api.php`:

```php
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 запросов в минуту

Route::middleware('auth:sanctum')->group(function () {
    // всё внутри доступно только с токеном, иначе 401
});
```

**Отличие:** в Битриксе фильтры настраиваются внутри контроллера, в Laravel — снаружи, на маршруте или группе маршрутов.

### 4. Eloquent Model — модель

**По-человечески:** PHP-класс, через который работаешь с одной таблицей БД. Запросы пишешь методами, а не SQL.

В Битриксе — таблет: класс описывает таблицу, строки приходят массивами.

```php
class LoanApplicationTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'loan_applications';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            new IntegerField('USER_ID'),
            new StringField('STATUS'),
            new StringField('INN'),
            new IntegerField('AMOUNT'),
        ];
    }
}

$rows = LoanApplicationTable::getList([
    'filter' => ['=USER_ID' => $userId, '=STATUS' => 'draft'],
    'order' => ['ID' => 'DESC'],
])->fetchAll();

LoanApplicationTable::update($id, ['AMOUNT' => 500000]);
```

В Laravel — `app/Models/LoanApplication.php`: полей в классе нет, они берутся из таблицы.

```php
#[Fillable(['company_name', 'inn', 'amount', 'term_months', 'purpose'])]
class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;
}

$applications = LoanApplication::where('user_id', $userId)
    ->where('status', 'draft')
    ->latest()
    ->get();

$application->update(['amount' => 500000]);
```

**Отличие:** в D7 класс — это таблица, а строка — массив (или отдельный EO-объект). В Eloquent один класс
играет обе роли: `LoanApplication::where(...)` — запрос к таблице, `$application` — одна строка,
у которой есть свои методы (`update`, `delete`, `save`). Этот подход называется Active Record.

### 5. Migration — миграция

**По-человечески:** файл с изменением структуры БД (создать таблицу, добавить колонку). Лежит в git,
накатывается командой. Чтобы у всех разработчиков и на всех серверах схема была одинаковой.

В Битриксе — модуль sprint.migration или установщик своего модуля:

```php
class Version20261003100000 extends \Sprint\Migration\Version
{
    protected $description = 'Поле «Цель» в заявках';

    public function up()
    {
        Application::getConnection()->queryExecute(
            'ALTER TABLE loan_applications ADD purpose TEXT NULL'
        );
    }
}
```

В Laravel — `database/migrations/2026_10_03_100000_create_loan_applications_table.php`:

```php
Schema::create('loan_applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('inn', 12)->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status']);
});
```

Накатить: `php artisan migrate`. Откатить последнюю накатанную пачку: `php artisan migrate:rollback`.

**Отличие:** в Битриксе это сторонний модуль, и таблицу нередко создают руками. В Laravel миграции встроены,
и другого способа менять схему не предполагается.

### 6. Relations и Eager Loading — связи и «жадная» загрузка

**По-человечески:** связь — описание того, как таблицы соотносятся («у заявки много результатов проверок»).
Жадная загрузка — достать связанные записи одним запросом сразу для всего списка. Без неё получается N+1:
один запрос за списком и ещё по одному на каждую строку.

В Битриксе:

```php
// в getMap() таблета заявок
(new OneToMany('STOP_FACTOR_RESULTS', StopFactorResultTable::class, 'LOAN_APPLICATION')),

// выборка сразу со связью
$applications = LoanApplicationTable::query()
    ->setSelect(['*', 'STOP_FACTOR_RESULTS'])
    ->where('USER_ID', $userId)
    ->fetchCollection();
```

В Laravel — связь в `app/Models/LoanApplication.php`, загрузка в `LoanApplicationController::index`:

```php
public function stopFactorResults(): HasMany
{
    return $this->hasMany(StopFactorResult::class);
}

$applications = $user->loanApplications()
    ->with('stopFactorResults') // ← вот это и есть eager loading
    ->paginate(10);
```

**Отличие:** D7 тянет связь JOIN-ом в том же запросе. Eloquent делает два запроса: заявки, потом
`SELECT * FROM stop_factor_results WHERE loan_application_id IN (1, 2, 3, ...)`. Если `with()` забыть,
код продолжит работать, но медленно — поэтому про N+1 спрашивают на каждом собеседовании.

### 7. Form Request — валидация запроса

**По-человечески:** проверка входящих данных, вынесенная в отдельный класс. Срабатывает до контроллера:
если данные плохие, контроллер не вызывается, а клиент получает 422 со списком ошибок по полям.

В Битриксе отдельного механизма нет — проверяешь руками в начале действия:

```php
public function saveAction(array $fields): ?array
{
    if (!preg_match('/^(\d{10}|\d{12})$/', (string)($fields['INN'] ?? ''))) {
        $this->addError(new Error('ИНН должен состоять из 10 или 12 цифр', 'INN'));
        return null;
    }
    // ...и так для каждого поля
}
```

В Laravel — `app/Http/Requests/SaveDraftRequest.php`:

```php
public function rules(): array
{
    return [
        'inn' => ['nullable', 'string', 'regex:/^(\d{10}|\d{12})$/'],
        'amount' => ['nullable', 'integer', 'min:1'],
    ];
}
```

и в контроллере достаточно указать класс типом параметра:

```php
public function store(SaveDraftRequest $request): JsonResponse
{
    $application = $request->user()->loanApplications()->create($request->validated());
}
```

В проекте таких классов два: `SaveDraftRequest` (всё необязательное) и `SubmitLoanApplicationRequest`
(всё обязательное). На этой паре и построена логика черновиков.

### 8. API Resource — ресурс

**По-человечески:** класс, который решает, какие поля модели и в каком виде попадут в JSON-ответ.
Прослойка между таблицей и тем, что видит клиент.

В Битриксе массив для ответа собирают руками в действии:

```php
return [
    'id' => (int)$row['ID'],
    'status' => $row['STATUS'],
    'statusLabel' => self::STATUS_LABELS[$row['STATUS']],
    'amount' => (int)$row['AMOUNT'],
];
```

В Laravel — `app/Http/Resources/LoanApplicationResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status->value,
        'status_label' => $this->status->label(),
        'can_edit' => $this->status->isEditable(),
        'stop_factors' => StopFactorResultResource::collection($this->whenLoaded('stopFactorResults')),
    ];
}
```

**Зачем:** переименовал колонку в БД — API не сломался. И наружу не утечёт поле, которое забыли скрыть.

### 9. Service Container — сервис-контейнер (DI)

**По-человечески:** «фабрика объектов» приложения. Ты не пишешь `new StopFactorChecker(...)`, а просишь
объект у контейнера, и он сам создаёт его со всеми зависимостями. Удобно тем, что зависимости можно подменить
(например, в тестах), не трогая код, который ими пользуется.

В Битриксе — `ServiceLocator`, сервисы описываются в `.settings.php`:

```php
// .settings.php
'services' => [
    'value' => [
        'my.loans.stopFactorChecker' => [
            'className' => \My\Loans\StopFactorChecker::class,
            'constructorParams' => static fn () => [[
                new AmountLimitStopFactor(5000000),
                new BlacklistedInnStopFactor(['7700000000']),
            ]],
        ],
    ],
],

// там, где сервис нужен
$checker = ServiceLocator::getInstance()->get('my.loans.stopFactorChecker');
```

В Laravel — описание в `app/Providers/AppServiceProvider.php`:

```php
$this->app->tag([
    AmountLimitStopFactor::class,
    BlacklistedInnStopFactor::class,
    DuplicateApplicationStopFactor::class,
], 'stop-factors');

$this->app->when(StopFactorChecker::class)
    ->needs('$stopFactors')
    ->giveTagged('stop-factors');
```

а получение — просто типом параметра, например в `app/Jobs/RunStopFactorChecks.php`:

```php
public function handle(StopFactorChecker $checker): void
{
    $checker->check($this->application);
}
```

**Отличие:** в Битриксе сервис достаёшь сам по строковому имени. В Laravel контейнер смотрит на типы параметров
в конструкторе или методе и подставляет объекты сам — это называется autowiring. Большинство классов
вообще не нужно регистрировать: контейнер создаст их по имени класса.

### 10. Service Provider — сервис-провайдер

**По-человечески:** класс, который выполняется при старте приложения и настраивает его: что как создавать,
кто на какие события подписан. Место для кода инициализации.

В Битриксе эту роль играют `init.php` и `include.php` модуля:

```php
// /local/php_interface/init.php
Loader::includeModule('my.loans');

EventManager::getInstance()->addEventHandler(
    'my.loans', 'OnLoanApplicationChecked', [LogCheckResult::class, 'handle']
);
```

В Laravel — `app/Providers/AppServiceProvider.php`, два метода:

```php
public function register(): void
{
    // только описываем, что как создавать (привязки в контейнере)
}

public function boot(): void
{
    // здесь уже можно пользоваться любыми сервисами
}
```

**Почему два метода:** сначала у всех провайдеров вызывается `register()`, и только потом у всех — `boot()`.
Так гарантируется, что к моменту `boot()` всё уже зарегистрировано. Вопрос «чем register отличается от boot»
— классика собеседований.

### 11. Event и Listener — событие и слушатель

**По-человечески:** способ сказать «произошло вот это», не зная, кто и как на это отреагирует.
Один код бросает событие, другой на него подписан. Так новая реакция (письмо, лог, пуш) добавляется
без правки основного кода.

В Битриксе:

```php
// бросить
$event = new Event('my.loans', 'OnLoanApplicationChecked', ['application' => $application]);
$event->send();

// обработать
public static function handle(Event $event): void
{
    $application = $event->getParameter('application');
}
```

В Laravel — `app/Events/LoanApplicationChecked.php` и `app/Listeners/LogCheckResult.php`:

```php
// бросить (в StopFactorChecker)
LoanApplicationChecked::dispatch($application);

// обработать
class LogCheckResult
{
    public function handle(LoanApplicationChecked $event): void
    {
        Log::info('Заявка проверена', ['id' => $event->application->id]);
    }
}
```

**Отличие:** в Битриксе событие — это строка-название, и подписку регистрируешь руками. В Laravel событие —
класс, а слушателя фреймворк находит сам по типу параметра в `handle()`.

### 12. События модели и Observer

**По-человечески:** реакция на то, что запись в БД создали, изменили или удалили. Автоматика вокруг сохранения:
сбросить кеш, записать историю.

В Битриксе — методы таблета:

```php
class LoanApplicationTable extends DataManager
{
    public static function onAfterUpdate(Event $event)
    {
        $id = $event->getParameter('primary')['ID'];
        // сбросить кеш статистики
    }
}
```

В Laravel — `LoanApplication::booted()`:

```php
protected static function booted(): void
{
    static::saved(fn (self $application) => Cache::forget(self::statsCacheKey($application->user_id)));
    static::deleted(fn (self $application) => Cache::forget(self::statsCacheKey($application->user_id)));
}
```

Когда таких обработчиков много, их выносят в отдельный класс — он и называется **Observer**.

### 13. Queue и Job — очередь и задача

**По-человечески:** способ выполнить долгую работу не во время HTTP-запроса, а позже, в фоне.
Запрос кладёт задачу в очередь и сразу отвечает пользователю; отдельный процесс (воркер) задачи разбирает.

В Битриксе встроенной очереди нет. Обходятся одноразовым агентом или внешним брокером (Kafka, RabbitMQ):

```php
// «отложить» проверку через агента
\CAgent::AddAgent("\\My\\Loans\\Agent::checkApplication({$id});", 'my.loans', 'N', 60);

class Agent
{
    public static function checkApplication(int $id): string
    {
        // ...проверка
        return ''; // пустая строка — агент выполнится один раз и удалится
    }
}
```

В Laravel — `app/Jobs/RunStopFactorChecks.php`:

```php
// положить в очередь (в контроллере)
RunStopFactorChecks::dispatch($loanApplication);

// сама задача
class RunStopFactorChecks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;           // три попытки, если упадёт
    public array $backoff = [5, 30]; // паузы между попытками, сек

    public function __construct(public LoanApplication $application) {}

    public function handle(StopFactorChecker $checker): void
    {
        $checker->check($this->application);
    }
}
```

Воркер запускается командой `php artisan queue:work`. Где хранится очередь (таблица в БД, Redis),
задаётся в `.env` — код задачи от этого не зависит.

**Ловушка:** воркер загружает код один раз при старте. Поправил job или сервис — перезапусти `queue:work`,
иначе он продолжит выполнять старую версию. Для разработки есть `php artisan queue:listen`: медленнее,
но перечитывает код на каждой задаче.

### 14. Scheduler — планировщик

**По-человечески:** запуск кода по расписанию: раз в сутки, каждые пять минут.

В Битриксе — агенты:

```php
\CAgent::AddAgent('\My\Loans\Agent::pruneDrafts();', 'my.loans', 'N', 86400);

public static function pruneDrafts(): string
{
    // ...удалить старые черновики
    return '\My\Loans\Agent::pruneDrafts();'; // вернуть строку вызова — агент запустится снова
}
```

В Laravel — `routes/console.php`:

```php
Schedule::command('loan-applications:prune-drafts')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

**Отличие:** агенты хранятся в БД (таблица `b_agent`), их видно в админке. Расписание Laravel лежит в коде
и в git. На сервере в crontab ставится одна строка — `* * * * * php artisan schedule:run`.

### 15. Artisan-команда — консольная команда

**По-человечески:** скрипт, который запускают из терминала или по расписанию, а не через браузер.

В Битриксе — PHP-файл, который сам подключает ядро:

```php
#!/usr/bin/env php
<?php
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// ...логика
```

В Laravel — `app/Console/Commands/PruneStaleDrafts.php`:

```php
class PruneStaleDrafts extends Command
{
    protected $signature = 'loan-applications:prune-drafts {--days=}';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('loans.draft_ttl_days'));
        // ...логика
        return self::SUCCESS;
    }
}
```

Запуск: `php artisan loan-applications:prune-drafts --days=7`. Фреймворк уже загружен, аргументы разобраны.
`artisan` — общая консоль Laravel: через неё же идут миграции, тесты, очередь.

### 16. Cache — кеш

**По-человечески:** запомнить результат дорогого запроса на время, чтобы не считать его заново.

В Битриксе:

```php
$cache = Cache::createInstance();
if ($cache->initCache(600, "loan_stats_{$userId}", '/loans/stats')) {
    $stats = $cache->getVars();
} elseif ($cache->startDataCache()) {
    $stats = self::calculateStats($userId);
    $cache->endDataCache($stats);
}
```

В Laravel — `LoanApplicationController::stats`:

```php
$stats = Cache::remember(
    LoanApplication::statsCacheKey($user->id),
    now()->addMinutes(10),
    function () use ($user) {
        // запрос с GROUP BY status — выполнится, только если в кеше пусто
    },
);

Cache::forget($key); // сброс — в LoanApplication::booted()
```

Где хранится кеш (файлы, БД, Redis) — тоже настройка в `.env`.

### 17. Config и .env — настройки

**По-человечески:** значения, которые отличаются между локальной машиной и продом (доступы к БД, лимиты,
ключи). В код их не зашивают.

В Битриксе:

```php
$maxAmount = (int)Option::get('my.loans', 'max_amount', 5000000);    // настройки модуля, хранятся в БД
$connections = Configuration::getValue('connections');               // .settings.php
```

В Laravel — файл `.env` (в git не попадает) и `config/loans.php`:

```php
// config/loans.php
'stop_factors' => [
    'max_amount' => (int) env('LOAN_MAX_AMOUNT', 5_000_000),
],

// где угодно в коде
config('loans.stop_factors.max_amount');
```

**Правило:** `env()` вызывают только в файлах `config/`, в остальном коде — `config()`.
На проде конфиг кешируется в один файл, и `.env` после этого не читается.

### 18. Policy и Gate — права доступа

**По-человечески:** ответ на вопрос «можно ли этому пользователю сделать это с этой записью».

В Битриксе проверка обычно пишется прямо в действии:

```php
$userId = (int)CurrentUser::get()->getId();
if ((int)$row['USER_ID'] !== $userId) {
    $this->addError(new Error('Доступ запрещён'));
    return null;
}
```

В Laravel — `app/Policies/LoanApplicationPolicy.php`:

```php
public function update(User $user, LoanApplication $application): bool
{
    return $application->user_id === $user->id;
}
```

и одна строка в контроллере:

```php
Gate::authorize('update', $loanApplication); // нельзя — исключение и ответ 403
```

**Policy** — правила для одной модели, собранные в класс. **Gate** — механизм, который эти правила вызывает.

### 19. Транзакции

**По-человечески:** несколько запросов к БД выполняются как один: либо все, либо ни одного.

В Битриксе:

```php
$connection = Application::getConnection();
$connection->startTransaction();
try {
    // ...запросы
    $connection->commitTransaction();
} catch (\Throwable $e) {
    $connection->rollbackTransaction();
    throw $e;
}
```

В Laravel — `StopFactorChecker::check`:

```php
DB::transaction(function () use ($application) {
    // ...запросы. Вылетело исключение — откат произойдёт сам
});
```

### 20. Blade — шаблоны

**По-человечески:** HTML с вставками данных, который сервер собирает и отдаёт готовой страницей.

В Битриксе — `template.php` компонента:

```php
<h1><?= htmlspecialcharsbx($arResult['COMPANY_NAME']) ?></h1>
<?php foreach ($arResult['ITEMS'] as $item): ?>
    <li><?= htmlspecialcharsbx($item['NAME']) ?></li>
<?php endforeach; ?>
```

В Laravel — файлы `resources/views/*.blade.php`:

```blade
<h1>{{ $application->company_name }}</h1>
@foreach ($applications as $application)
    <li>{{ $application->company_name }}</li>
@endforeach
```

`{{ }}` экранирует вывод сам. В этом проекте Blade почти не используется (только стандартная страница-заглушка
на `/`): бэкенд отдаёт JSON, а HTML рисует Vue.

## Чего в Битриксе нет

Эти вещи сравнивать не с чем — их нужно просто понять.

**Facade — фасад.** Короткая запись для обращения к сервису из контейнера. `Cache::get('key')` выглядит как
статический метод, но на деле Laravel достаёт из контейнера объект кеша и вызывает `get()` у него.
Удобно писать и легко подменять в тестах:

```php
Queue::fake();                                   // очередь-заглушка: задачи запоминаются, но не выполняются
// ...действие
Queue::assertPushed(RunStopFactorChecks::class); // проверяем, что задачу положили
```

Фасады в проекте: `Route`, `Cache`, `DB`, `Log`, `Gate`, `Hash`, `Schedule`, `Queue`, `Event`.

**Route Model Binding — привязка модели к маршруту.** В адресе `/loan-applications/5` лежит число,
а в метод контроллера приходит уже загруженная заявка. Laravel сам делает `SELECT ... WHERE id = 5`
и сам отвечает 404, если записи нет.

```php
// маршрут: /loan-applications/{loan_application}
public function show(LoanApplication $loanApplication) // ← объект, а не id
```

**Mass Assignment — массовое заполнение.** `create([...])` и `update([...])` заполняют модель из массива.
Опасность: если передать туда весь запрос, пользователь сможет прислать `status=approved`.
Защита — список разрешённых полей; всё остальное молча отбрасывается.

```php
#[Fillable(['company_name', 'inn', 'amount', 'term_months', 'purpose'])] // status и user_id тут нет

$application->status = ApplicationStatus::Submitted; // такие поля меняются только явным присваиванием
$application->save();
```

**Casts — приведение типов.** Правила превращения значений из БД в типы PHP и обратно:
строка `'draft'` становится enum, дата — объектом, `0/1` — `bool`.

```php
protected function casts(): array
{
    return [
        'status' => ApplicationStatus::class,
        'submitted_at' => 'datetime',
    ];
}
```

**Scope — именованное условие.** Кусок запроса, которому дали имя, чтобы не повторять его по всему коду.

```php
#[Scope]
protected function withStatus(Builder $query, ApplicationStatus $status): void
{
    $query->where('status', $status);
}

LoanApplication::query()->withStatus(ApplicationStatus::Draft)->get();
```

**Soft Delete — мягкое удаление.** `delete()` не удаляет строку, а записывает дату в колонку `deleted_at`.
Во все обычные запросы автоматически добавляется `WHERE deleted_at IS NULL`, так что удалённое не видно,
но его можно восстановить. Включается трейтом `SoftDeletes` в модели.

**Collection — коллекция.** Обёртка над массивом с методами вместо функций `array_*`. Любая выборка
из Eloquent возвращает коллекцию.

```php
$approved = collect($verdicts)->every(fn ($verdict) => $verdict->passed); // все ли проверки пройдены

// Связь БЕЗ скобок — уже загруженная коллекция: where и pluck работают в памяти, запросов нет
$failedCodes = $application->stopFactorResults->where('passed', false)->pluck('code');

// Связь СО скобками — построитель запроса: те же where и pluck уйдут в БД одним SELECT
$failedCodes = $application->stopFactorResults()->where('passed', false)->pluck('code');
```

Разница между `->stopFactorResults` и `->stopFactorResults()` — частый вопрос и частый источник лишних запросов.

**Factory и Seeder — фабрика и сидер.** Фабрика создаёт модель с правдоподобными случайными данными,
сидер наполняет ими базу. На фабриках держатся тесты.

```php
LoanApplication::factory()->for($user)->submitted()->create(['amount' => 100_001]);
```

**Sanctum.** Пакет Laravel для авторизации API. При входе создаёт случайный токен, хеш кладёт в таблицу
`personal_access_tokens`, сам токен отдаёт клиенту. Клиент шлёт его в заголовке `Authorization: Bearer ...`.
Смотри `app/Http/Controllers/AuthController.php`.

**Тесты из коробки.** Feature-тест шлёт HTTP-запрос в приложение прямо внутри PHPUnit и проверяет ответ и базу.
`RefreshDatabase` откатывает БД после каждого теста.

```php
$this->postJson("/api/loan-applications/{$draft->id}/submit")
    ->assertUnprocessable()
    ->assertJsonValidationErrors(['inn', 'amount']);
```

## Если на проекте Laravel постарше

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
