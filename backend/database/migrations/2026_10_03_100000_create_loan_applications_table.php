<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * МИГРАЦИЯ — версия схемы БД в коде.
 *
 * Аналог в Битриксе: модуль sprint.migration или install/db/*.sql у модуля.
 * Здесь это не дополнение, а единственный способ менять схему: руками в БД никто не лезет.
 *
 * Команды:
 *   php artisan migrate            — накатить всё новое
 *   php artisan migrate:rollback   — откатить последнюю пачку (вызовет down())
 *   php artisan migrate:fresh      — снести всё и накатить заново (только локально!)
 *
 * Имя файла начинается с даты — по ней определяется порядок выполнения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // Внешний ключ на users.id. constrained() сам выводит таблицу из имени колонки.
            // cascadeOnDelete — удалили пользователя, удалились его заявки.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('draft');

            // Все поля заявки nullable — это и есть суть черновика:
            // человек заполнил половину формы и ушёл, мы обязаны сохранить как есть.
            // Обязательность проверяется не схемой, а валидацией в момент отправки.
            $table->string('company_name')->nullable();
            $table->string('inn', 12)->nullable();           // 10 цифр у юрлица, 12 у ИП
            $table->unsignedBigInteger('amount')->nullable(); // сумма в рублях, целое
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->text('purpose')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();  // created_at + updated_at, Eloquent заполняет их сам
            $table->softDeletes(); // deleted_at — «мягкое» удаление, см. трейт SoftDeletes в модели

            // Составной индекс под самый частый запрос: «заявки пользователя в таком-то статусе».
            // Это ровно то, что ты проверял бы через EXPLAIN.
            $table->index(['user_id', 'status']);

            // Под проверку дублей по ИНН (стоп-фактор DuplicateApplicationStopFactor).
            $table->index('inn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
