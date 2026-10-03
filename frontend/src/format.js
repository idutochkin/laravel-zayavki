// Форматирование для отображения. Сервер отдаёт «сырые» значения (число, дата в ISO 8601),
// как их показать — решает клиент. Мобильное приложение показало бы те же данные по-своему.

const money = new Intl.NumberFormat('ru-RU')
const dateTime = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' })

export function formatMoney(amount) {
  return amount === null || amount === undefined ? '—' : `${money.format(amount)} ₽`
}

export function formatDate(iso) {
  return iso ? dateTime.format(new Date(iso)) : '—'
}

// Названия стоп-факторов. Сервер отдаёт только код (code) — подписи живут на клиенте.
export const STOP_FACTOR_LABELS = {
  amount_limit: 'Лимит суммы',
  blacklisted_inn: 'Стоп-лист ИНН',
  duplicate_application: 'Дубль заявки по ИНН',
}

// Порядок и подписи статусов для фильтра. Значения — те же, что в App\Enums\ApplicationStatus.
export const STATUSES = [
  { value: 'draft', label: 'Черновики' },
  { value: 'submitted', label: 'На проверке' },
  { value: 'approved', label: 'Одобрены' },
  { value: 'rejected', label: 'Отклонены' },
]
