<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use Illuminate\Console\Command;

/**
 * КОНСОЛЬНАЯ КОМАНДА (artisan) — удаляет брошенные черновики.
 *
 * Запуск руками:  php artisan loan-applications:prune-drafts --days=7
 * По расписанию:  см. routes/console.php
 *
 * Аналог в Битриксе: функция-агент или скрипт, который дёргает cron.
 * Laravel находит команды в app/Console/Commands сам, регистрировать не нужно.
 */
class PruneStaleDrafts extends Command
{
    // Сигнатура: имя команды и её аргументы/опции. {--days=} — необязательная опция со значением.
    protected $signature = 'loan-applications:prune-drafts {--days= : Удалять черновики старше N дней}';

    protected $description = 'Удаляет черновики заявок, которые давно не менялись';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('loans.draft_ttl_days'));
        $deleted = 0;

        LoanApplication::query()
            ->withStatus(ApplicationStatus::Draft)
            ->where('updated_at', '<', now()->subDays($days))
            // chunkById — обработка пачками по 500 строк: WHERE id > последний_id LIMIT 500.
            // Так обрабатывают большие таблицы: в памяти одновременно только одна пачка,
            // и в отличие от OFFSET запрос не замедляется к концу таблицы.
            // Обычный chunk() здесь использовать нельзя: мы удаляем строки, из-за чего
            // OFFSET «съезжает» и часть записей пропускается.
            ->chunkById(500, function ($drafts) use (&$deleted) {
                foreach ($drafts as $draft) {
                    $draft->delete(); // мягкое удаление + событие deleted (сброс кеша статистики)
                    $deleted++;
                }
            });

        $this->info("Удалено черновиков: {$deleted}");

        return self::SUCCESS; // код возврата 0
    }
}
