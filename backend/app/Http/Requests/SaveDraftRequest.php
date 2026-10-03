<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FORM REQUEST — валидация входящих данных, вынесенная из контроллера в отдельный класс.
 *
 * Достаточно указать этот класс типом параметра в методе контроллера — и Laravel выполнит
 * валидацию ДО входа в метод. Если данные невалидны, метод контроллера не вызовется вообще,
 * а клиент получит 422 с JSON вида {"message": "...", "errors": {"inn": ["..."]}}.
 *
 * В Битриксе аналога нет: там валидацию обычно пишут руками в начале экшена контроллера.
 *
 * Этот класс — для СОХРАНЕНИЯ ЧЕРНОВИКА (и создание, и редактирование).
 * Все поля необязательные: проверяем только формат того, что прислали.
 * Строгая проверка — в SubmitLoanApplicationRequest.
 */
class SaveDraftRequest extends FormRequest
{
    /**
     * Можно ли вообще выполнять этот запрос. Права на конкретную заявку проверяет
     * политика в контроллере, поэтому здесь просто true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила. Ключ — имя поля запроса, значение — список правил.
     * nullable: поле можно не присылать или прислать null — тогда остальные правила пропускаются.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'regex:/^(\d{10}|\d{12})$/'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'term_months' => ['nullable', 'integer', 'between:1,120'],
            'purpose' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Свои тексты ошибок. Ключ — «поле.правило» или просто «правило» (тогда для всех полей).
     * :attribute, :min, :max — подстановки, которые Laravel заполнит сам.
     *
     * Здесь тексты заданы точечно. Чтобы перевести ВСЕ стандартные сообщения разом,
     * делают языковой файл lang/ru/validation.php (php artisan lang:publish) и ставят APP_LOCALE=ru.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inn.regex' => 'ИНН должен состоять из 10 или 12 цифр.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
            'amount.min' => 'Сумма должна быть больше нуля.',
            'term_months.between' => 'Срок — от :min до :max месяцев.',
            'max' => 'Поле «:attribute» не должно быть длиннее :max символов.',
        ];
    }

    /**
     * Подписи полей — подставляются в тексты ошибок вместо :attribute.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'Название компании',
            'inn' => 'ИНН',
            'amount' => 'Сумма',
            'term_months' => 'Срок',
            'purpose' => 'Цель финансирования',
        ];
    }
}
