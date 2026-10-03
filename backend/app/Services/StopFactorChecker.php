<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Events\LoanApplicationChecked;
use App\Models\LoanApplication;
use App\StopFactors\StopFactor;
use Illuminate\Support\Facades\DB;

/**
 * СЕРВИС — прогоняет заявку через все стоп-факторы и сохраняет итог.
 *
 * В Laravel нет обязательной папки Services и базового класса «сервис» — это просто PHP-класс.
 * Правило хорошего тона: контроллер тонкий (принял запрос, отдал ответ), бизнес-логика — здесь.
 * Тогда её можно вызвать откуда угодно: из job'а в очереди, из консольной команды, из теста.
 *
 * Объект этого класса никто не создаёт через new. Его собирает сервис-контейнер:
 * видит в конструкторе параметр $stopFactors и подставляет то, что описано в AppServiceProvider.
 */
class StopFactorChecker
{
    /** @param iterable<StopFactor> $stopFactors */
    public function __construct(
        private readonly iterable $stopFactors,
    ) {}

    /**
     * @return bool true — заявка одобрена, false — отклонена
     */
    public function check(LoanApplication $application): bool
    {
        // 1. Считаем вердикты. Пока ничего не пишем в БД.
        $verdicts = [];
        foreach ($this->stopFactors as $stopFactor) {
            $verdicts[$stopFactor->code()] = $stopFactor->check($application);
        }

        // collect() оборачивает массив в Collection — у неё цепочки методов вместо array_* функций.
        $approved = collect($verdicts)->every(fn ($verdict) => $verdict->passed);

        // 2. Результаты и новый статус пишем в одной транзакции: либо всё, либо ничего.
        //    Если внутри вылетит исключение — Laravel сам сделает ROLLBACK и пробросит его дальше.
        //    Аналог: $connection->startTransaction() / commitTransaction() в D7.
        DB::transaction(function () use ($application, $verdicts, $approved) {
            // На случай повторной проверки той же заявки — старые результаты убираем.
            $application->stopFactorResults()->delete();

            foreach ($verdicts as $code => $verdict) {
                // create() через связь сам проставит loan_application_id
                $application->stopFactorResults()->create([
                    'code' => $code,
                    'passed' => $verdict->passed,
                    'message' => $verdict->message,
                ]);
            }

            // status и checked_at нет в #[Fillable], поэтому их нельзя передать в update([...]) —
            // только присвоить явно. Так задумано: статус меняет только наш код, а не данные из запроса.
            $application->status = $approved ? ApplicationStatus::Approved : ApplicationStatus::Rejected;
            $application->checked_at = now();
            $application->save();
        });

        // 3. Сообщаем остальному приложению, что проверка завершена.
        //    Кто на это подписан — сервису знать не нужно (см. app/Listeners).
        LoanApplicationChecked::dispatch($application);

        return $approved;
    }
}
