<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\LoanApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * МОДЕЛЬ ELOQUENT — заявка на финансирование.
 *
 * Аналог в Битриксе: таблет D7 (class LoanApplicationTable extends DataManager с getMap()).
 * Главное отличие: в D7 таблет описывает таблицу, а строка — это массив или EO-объект.
 * Здесь один и тот же класс — и описание таблицы, и объект конкретной строки (Active Record).
 *
 * Чего тут НЕТ и почему:
 *  - списка полей (getMap) — Eloquent берёт колонки из самой таблицы, схема живёт в миграции;
 *  - имени таблицы — выводится из имени класса: LoanApplication → loan_applications.
 *
 * #[Fillable] — список полей, которые разрешено заполнять массово: create([...]), update([...]).
 * Это защита от mass assignment: если прислать в запросе status=approved или user_id=1,
 * Eloquent молча их проигнорирует, потому что их нет в списке.
 * (В проектах постарше то же самое записано как protected $fillable = [...]; — это одно и то же.)
 */
#[Fillable(['company_name', 'inn', 'amount', 'term_months', 'purpose'])]
class LoanApplication extends Model
{
    /** @use HasFactory<LoanApplicationFactory> */
    use HasFactory;

    // SoftDeletes: delete() не удаляет строку, а ставит deleted_at.
    // Все обычные запросы автоматически получают WHERE deleted_at IS NULL (это «global scope»).
    // Достать удалённые: LoanApplication::withTrashed()->..., восстановить: ->restore().
    use SoftDeletes;

    /** Значения по умолчанию для нового объекта (до сохранения в БД). */
    protected $attributes = [
        'status' => ApplicationStatus::Draft->value,
    ];

    /**
     * CASTS — преобразование типов между БД и PHP.
     * В БД status лежит строкой 'draft', а в коде $application->status — это enum.
     * Даты становятся объектами Carbon (надстройка над DateTime).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'amount' => 'integer',
            'term_months' => 'integer',
            'submitted_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * booted() вызывается один раз при первом обращении к модели.
     * Здесь вешаем обработчики событий модели: creating, created, saving, saved, deleted и т.д.
     *
     * Аналог в Битриксе: ORM-события OnBeforeAdd / OnAfterUpdate в таблете.
     * Если обработчиков много, их выносят в отдельный класс — Observer.
     *
     * Задача: при любом изменении заявки сбросить кеш статистики её владельца
     * (сам кеш — в LoanApplicationController::stats).
     */
    protected static function booted(): void
    {
        $forgetStats = fn (self $application) => Cache::forget(self::statsCacheKey($application->user_id));

        static::saved($forgetStats);   // после INSERT и UPDATE
        static::deleted($forgetStats); // после удаления (в том числе мягкого)
    }

    public static function statsCacheKey(int $userId): string
    {
        return "users:{$userId}:loan-application-stats";
    }

    // ---------------------------------------------------------------------
    // СВЯЗИ. Аналог — Reference / OneToMany в getMap() таблета.
    // Вызов как свойства ($application->user) делает запрос и кеширует результат в объекте.
    // Вызов как метода ($application->stopFactorResults()) возвращает построитель запроса,
    // к которому можно дописать where/orderBy/create.
    // ---------------------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // внешний ключ user_id выводится из имени метода
    }

    /** @return HasMany<StopFactorResult, $this> */
    public function stopFactorResults(): HasMany
    {
        return $this->hasMany(StopFactorResult::class);
    }

    // ---------------------------------------------------------------------
    // SCOPE — именованный кусок запроса, который можно переиспользовать.
    // Использование: LoanApplication::query()->withStatus(ApplicationStatus::Draft)->get()
    // (В старом коде то же самое пишут методом scopeWithStatus() — без атрибута.)
    // ---------------------------------------------------------------------

    #[Scope]
    protected function withStatus(Builder $query, ApplicationStatus $status): void
    {
        $query->where('status', $status);
    }
}
