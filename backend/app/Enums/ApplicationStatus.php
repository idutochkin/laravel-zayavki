<?php

namespace App\Enums;

/**
 * Статус заявки.
 *
 * Обычный PHP 8.1 backed enum — к Laravel отношения не имеет, но Eloquent умеет
 * автоматически превращать строку из БД в этот enum и обратно (см. casts() в модели).
 *
 * В Битриксе это был бы список констант в классе или свойство-список инфоблока.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';         // черновик: можно сохранять с пустыми полями
    case Submitted = 'submitted'; // отправлена, ждёт проверки стоп-факторов в очереди
    case Approved = 'approved';   // все стоп-факторы пройдены
    case Rejected = 'rejected';   // сработал хотя бы один стоп-фактор

    /** Человекочитаемое название — отдаём на фронт вместе с кодом. */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Submitted => 'На проверке',
            self::Approved => 'Одобрена',
            self::Rejected => 'Отклонена',
        };
    }

    /** Редактировать и удалять можно только черновик. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
