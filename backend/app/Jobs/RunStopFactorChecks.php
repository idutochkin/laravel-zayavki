<?php

namespace App\Jobs;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Services\StopFactorChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * JOB — задача для очереди.
 *
 * Аналог: сообщение в Kafka + консьюмер, которые ты делал для календаря. Только здесь и «сообщение»,
 * и «обработчик» — один класс. Данные лежат в свойствах, логика — в handle().
 *
 * Как это работает:
 *  1. RunStopFactorChecks::dispatch($application) сериализует job и кладёт в очередь
 *     (у нас это таблица jobs в БД, см. QUEUE_CONNECTION в .env; на проде обычно Redis).
 *     HTTP-запрос на этом заканчивается — пользователь не ждёт проверок.
 *  2. Отдельный процесс-воркер (php artisan queue:work) достаёт job и вызывает handle().
 *
 * Интерфейс ShouldQueue — маркер «выполнять в очереди». Без него dispatch() выполнит job сразу.
 */
class RunStopFactorChecks implements ShouldQueue
{
    use Queueable;

    /** Сколько раз пытаться, если handle() бросил исключение. */
    public int $tries = 3;

    /**
     * Пауза перед повторными попытками, сек: перед 2-й — 5, перед 3-й — 30.
     *
     * @var list<int>
     */
    public array $backoff = [5, 30];

    /**
     * В очередь попадает не вся модель, а только её класс и id
     * (это делает трейт SerializesModels — он входит в состав Queueable).
     * Перед handle() воркер заново достаёт заявку из БД — то есть работает со свежими данными.
     * Если строки в БД к этому моменту уже нет, job упадёт с ModelNotFoundException
     * (свойство $deleteWhenMissingModels = true заставит его в таком случае молча удалиться).
     */
    public function __construct(
        public LoanApplication $application,
    ) {}

    /**
     * Параметры handle() подставляет сервис-контейнер — так же, как в конструкторах и контроллерах.
     */
    public function handle(StopFactorChecker $checker): void
    {
        // Идемпотентность: job может выполниться повторно (ретрай, двойной клик, сбой воркера).
        // Если заявка уже проверена — ничего не делаем.
        if ($this->application->status !== ApplicationStatus::Submitted) {
            return;
        }

        $checker->check($this->application);
    }

    /**
     * Вызывается, когда все попытки исчерпаны. Сам job после этого попадает в таблицу failed_jobs,
     * откуда его можно перезапустить: php artisan queue:retry all
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Проверка стоп-факторов не выполнена', [
            'loan_application_id' => $this->application->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
