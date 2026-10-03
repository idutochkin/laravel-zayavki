<?php

namespace App\Http\Resources;

use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API RESOURCE — описывает, как модель превращается в JSON для ответа.
 *
 * Зачем отдельный класс, если модель и так умеет toArray():
 *  - формат ответа API перестаёт зависеть от структуры таблицы (переименовали колонку — API не сломался);
 *  - наружу уходит только то, что перечислено явно;
 *  - можно добавить вычисляемые поля (status_label, can_edit).
 *
 * Внутри ресурса $this->... обращается к полям модели (ресурс проксирует вызовы к ней).
 *
 * Одиночный объект оборачивается в {"data": {...}}, коллекция с пагинацией —
 * в {"data": [...], "links": {...}, "meta": {...}}.
 *
 * @mixin LoanApplication
 */
class LoanApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'can_edit' => $this->status->isEditable(),

            'company_name' => $this->company_name,
            'inn' => $this->inn,
            'amount' => $this->amount,
            'term_months' => $this->term_months,
            'purpose' => $this->purpose,

            // ?-> потому что у черновика этих дат ещё нет
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'checked_at' => $this->checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // whenLoaded: ключ попадёт в ответ, только если связь уже загружена (через with() или load()).
            // Сам ресурс запросов в БД не делает — это страховка от случайного N+1.
            'stop_factors' => StopFactorResultResource::collection($this->whenLoaded('stopFactorResults')),
        ];
    }
}
