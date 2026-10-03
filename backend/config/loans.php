<?php

/*
 * КОНФИГ ПРИЛОЖЕНИЯ.
 *
 * Аналог в Битриксе: .settings.php и Option::get().
 *
 * Правило Laravel: env() вызывается ТОЛЬКО в файлах config/*.php.
 * В остальном коде читаем через config('loans.draft_ttl_days').
 * Причина: на проде конфиг кешируется в один файл (php artisan config:cache),
 * после чего .env вообще не читается и env() в коде вернёт null.
 * На собеседованиях про это спрашивают.
 */

return [

    // Через сколько дней без изменений черновик удаляется командой loan-applications:prune-drafts
    'draft_ttl_days' => (int) env('LOAN_DRAFT_TTL_DAYS', 30),

    'stop_factors' => [

        // Максимальная сумма заявки, руб.
        'max_amount' => (int) env('LOAN_MAX_AMOUNT', 5_000_000),

        // ИНН из «чёрного списка», через запятую. В жизни это был бы внешний сервис или таблица.
        'blacklisted_inns' => array_values(array_filter(
            explode(',', (string) env('LOAN_BLACKLISTED_INNS', '7700000000'))
        )),

    ],

];
