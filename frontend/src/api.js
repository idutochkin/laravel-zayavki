/*
 * КЛИЕНТ API — единственное место во фронте, которое знает про HTTP.
 *
 * Это и есть граница между двумя приложениями: всё общение с Laravel идёт через JSON по HTTP.
 * Фронт ничего не знает про Eloquent, очереди и PHP вообще; Laravel ничего не знает про Vue.
 * Их можно выкатывать отдельно и заменить любой из них, не трогая другой.
 *
 * Три вещи, которые здесь завязаны на поведение Laravel:
 *
 *  1. Заголовок Accept: application/json. По нему Laravel понимает, что ошибки нужно отдавать
 *     в JSON, а не редиректом или HTML-страницей (см. shouldRenderJsonWhen в bootstrap/app.php).
 *
 *  2. Заголовок Authorization: Bearer <токен>. Его читает middleware auth:sanctum.
 *
 *  3. Формат ошибок валидации: статус 422 и тело
 *       { "message": "...", "errors": { "inn": ["текст"], "amount": ["текст"] } }
 *     Ключи в errors — имена полей из rules() в Form Request. Поэтому ошибки можно
 *     разложить по полям формы, ничего не зная о самих правилах.
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

const TOKEN_KEY = 'zayavki_token'

/*
 * Токен храним в localStorage — просто и наглядно, для учебного проекта и мобильных клиентов годится.
 * Минус: его может прочитать любой JS на странице, то есть XSS = украденный токен.
 * Для браузерных SPA на том же домене у Sanctum есть второй режим — авторизация через
 * httpOnly-куку сессии (без токена в JS). На собеседовании могут спросить про эту разницу.
 */
export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

/** Ошибка ответа API: HTTP-статус, сообщение и ошибки по полям (для 422). */
export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }

  /** Первая ошибка по полю или пустая строка. */
  first(field) {
    return this.errors[field]?.[0] ?? ''
  }
}

// Что делать, если сервер ответил 401 (токен отозван или протух). Задаётся в main.js.
let onUnauthorized = () => {}
export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

async function request(method, path, body) {
  const headers = { Accept: 'application/json' }

  const token = tokenStorage.get()
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let response
  try {
    response = await fetch(BASE_URL + path, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    // Сюда попадаем, если сервер не отвечает или браузер заблокировал запрос по CORS.
    throw new ApiError(0, 'Сервер недоступен. Запущен ли php artisan serve?')
  }

  if (response.status === 204) {
    return null // No Content: тела нет (logout, удаление)
  }

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401) {
      onUnauthorized()
    }
    throw new ApiError(response.status, payload?.message ?? `Ошибка ${response.status}`, payload?.errors ?? {})
  }

  return payload
}

/** Собирает строку запроса, пропуская пустые значения: { status: 'draft', page: 2 } → "?status=draft&page=2" */
function query(params) {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, value)
    }
  }
  const string = search.toString()
  return string ? `?${string}` : ''
}

// Каждый метод — один маршрут из routes/api.php.
export const api = {
  register: (data) => request('POST', '/register', data),
  login: (data) => request('POST', '/login', data),
  logout: () => request('POST', '/logout'),
  me: () => request('GET', '/me'),

  stats: () => request('GET', '/loan-applications/stats'),
  listApplications: (params = {}) => request('GET', '/loan-applications' + query(params)),
  getApplication: (id) => request('GET', `/loan-applications/${id}`),
  createApplication: (data = {}) => request('POST', '/loan-applications', data),
  updateApplication: (id, data) => request('PUT', `/loan-applications/${id}`, data),
  deleteApplication: (id) => request('DELETE', `/loan-applications/${id}`),
  submitApplication: (id) => request('POST', `/loan-applications/${id}/submit`),
}
