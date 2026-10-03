<?php

namespace App\Http\Requests;

use App\Models\LoanApplication;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация ОТПРАВКИ заявки.
 *
 * Тут два приёма, которые отличают этот класс от обычного Form Request:
 *
 *  1. Проверяем не то, что пришло в запросе (тело запроса пустое), а то, что уже лежит
 *     в черновике в БД — для этого переопределён validationData().
 *  2. Проверку прав делаем прямо здесь, в authorize(), через политику.
 */
class SubmitLoanApplicationRequest extends FormRequest
{
    /**
     * $this->route('loan_application') — параметр маршрута {loan_application}.
     * К этому моменту Laravel уже превратил id из URL в модель (route model binding).
     *
     * Вернём false — клиент получит 403.
     */
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->loanApplication());
    }

    /**
     * Что именно валидировать. По умолчанию это данные запроса, мы подменяем их полями черновика.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->loanApplication()->only([
            'company_name', 'inn', 'amount', 'term_months', 'purpose',
        ]);
    }

    /**
     * Те же поля, что и в SaveDraftRequest, но теперь все обязательны.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'inn' => ['required', 'string', 'regex:/^(\d{10}|\d{12})$/'],
            'amount' => ['required', 'integer', 'min:1'],
            'term_months' => ['required', 'integer', 'between:1,120'],
            'purpose' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Поле «:attribute» нужно заполнить перед отправкой.',
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

    private function loanApplication(): LoanApplication
    {
        return $this->route('loan_application');
    }
}
