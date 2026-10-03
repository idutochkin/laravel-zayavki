<?php

use Illuminate\Support\Facades\Schedule;

/*
 * ПЛАНИРОВЩИК (Scheduler).
 *
 * Аналог: агенты Битрикса, переведённые на cron.
 * На сервере в crontab ставится ОДНА строка:
 *
 *   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
 *
 * Она раз в минуту запускает Laravel, а тот сам решает, каким задачам пора выполниться.
 * Расписание лежит в коде и попадает в git — не нужно лазить по crontab на серверах.
 *
 * Посмотреть расписание:        php artisan schedule:list
 * Локально вместо cron:         php artisan schedule:work
 */

Schedule::command('loan-applications:prune-drafts')
    ->dailyAt('03:00')
    ->withoutOverlapping(); // не запускать, если предыдущий запуск ещё не закончился
