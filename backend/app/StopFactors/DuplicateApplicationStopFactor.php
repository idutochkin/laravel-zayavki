<?php

namespace App\StopFactors;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;

/**
 * Стоп-фактор: по этому ИНН уже есть другая активная заявка (на проверке или одобренная).
 *
 * Здесь пример запроса через Eloquent. В D7 это был бы
 * LoanApplicationTable::getList(['filter' => [...], 'limit' => 1]).
 */
final class DuplicateApplicationStopFactor implements StopFactor
{
    public function code(): string
    {
        return 'duplicate_application';
    }

    public function check(LoanApplication $application): Verdict
    {
        $duplicateExists = LoanApplication::query()
            ->where('inn', $application->inn)
            ->whereKeyNot($application->id) // WHERE id != ? — саму себя не считаем
            ->whereIn('status', [ApplicationStatus::Submitted, ApplicationStatus::Approved])
            ->exists(); // SELECT EXISTS(...) — дешевле, чем count() или first()

        return $duplicateExists
            ? Verdict::fail('По этому ИНН уже есть активная заявка')
            : Verdict::pass();
    }
}
