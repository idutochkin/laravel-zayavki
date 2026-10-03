/*
 * Состояние авторизации — один реактивный объект на всё приложение.
 * Для такого маленького проекта отдельное хранилище (Pinia) не нужно:
 * reactive() из Vue делает объект «живым» — компоненты, которые его читают, перерисуются сами.
 */

import { reactive } from 'vue'
import { api, tokenStorage } from './api'

export const auth = reactive({
  user: null,
  // Есть токен — считаем, что вошли. Действителен ли он, выяснится при первом же запросе:
  // если нет, сервер ответит 401 и сработает обработчик из main.js.
  isAuthenticated: Boolean(tokenStorage.get()),
})

function remember({ token, user }) {
  tokenStorage.set(token)
  auth.user = user
  auth.isAuthenticated = true
}

export function forget() {
  tokenStorage.clear()
  auth.user = null
  auth.isAuthenticated = false
}

export async function login(credentials) {
  remember(await api.login(credentials))
}

export async function register(data) {
  remember(await api.register(data))
}

export async function logout() {
  try {
    await api.logout() // сервер удалит токен из personal_access_tokens
  } finally {
    forget() // локально выходим в любом случае, даже если запрос не прошёл
  }
}

/** После перезагрузки страницы токен остался, а данных пользователя нет — запрашиваем. */
export async function restoreUser() {
  if (!auth.isAuthenticated || auth.user) {
    return
  }
  const { data } = await api.me()
  auth.user = data
}
