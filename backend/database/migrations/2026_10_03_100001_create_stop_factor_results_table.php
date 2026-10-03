<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Результаты проверки стоп-факторов: одна строка на каждый стоп-фактор заявки.
 * Связь «одна заявка — много результатов» (hasMany в модели LoanApplication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stop_factor_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);           // код стоп-фактора, например amount_limit
            $table->boolean('passed');            // true — проверка пройдена
            $table->string('message')->nullable(); // причина, если не пройдена
            $table->timestamps();

            // Один стоп-фактор — один результат на заявку. Защита на уровне БД,
            // а не только кода: если job выполнится дважды, дубля не будет.
            $table->unique(['loan_application_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_factor_results');
    }
};
