<script setup>
/*
 * Экран одной заявки. Два режима, переключатель — поле can_edit из ответа сервера:
 *   черновик      → форма: «Сохранить черновик», «Отправить на проверку», «Удалить»;
 *   всё остальное → просмотр и результаты стоп-факторов.
 *
 * Обрати внимание, где какая логика:
 *   - можно ли редактировать — решает сервер (can_edit), фронт только прячет кнопки;
 *   - что обязательно для отправки — тоже сервер (SubmitLoanApplicationRequest),
 *     фронт просто показывает ошибки 422 возле полей. Правил валидации во фронте нет вообще.
 */
import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { STOP_FACTOR_LABELS, formatDate, formatMoney } from '../format'
import StatusBadge from '../components/StatusBadge.vue'

const props = defineProps({
  id: { type: String, required: true }, // из адреса /applications/:id
})

const router = useRouter()

const application = ref(null)
const form = reactive({ company_name: '', inn: '', amount: '', term_months: '', purpose: '' })
const fieldErrors = ref({}) // { inn: ['текст ошибки'], ... } — как пришло от Laravel
const message = ref('') // «Черновик сохранён»
const error = ref('') // общая ошибка, не по полям
const loading = ref(true)
const busy = ref(false)

let pollTimer = null
let unmounted = false

/** Кладём ответ сервера в состояние экрана. */
function apply(data) {
  if (unmounted) {
    return // ответ пришёл, когда с экрана уже ушли
  }

  application.value = data

  // null с сервера превращаем в пустую строку для полей ввода
  for (const key of Object.keys(form)) {
    form[key] = data[key] ?? ''
  }

  // Заявка ждёт воркер очереди — перезапрашиваем её раз в две секунды, пока статус не сменится.
  // В настоящем проекте вместо опроса были бы веб-сокеты (в Laravel это broadcasting и Reverb).
  stopPolling()
  if (data.status === 'submitted') {
    pollTimer = setTimeout(refresh, 2000)
  }
}

function stopPolling() {
  clearTimeout(pollTimer)
  pollTimer = null
}

async function refresh() {
  try {
    const { data } = await api.getApplication(props.id)
    apply(data)
  } catch {
    // тихо: следующую попытку сделает пользователь, обновив страницу
  }
}

async function load() {
  loading.value = true
  error.value = ''
  application.value = null

  try {
    const { data } = await api.getApplication(props.id)
    apply(data)
  } catch (e) {
    // 404 — нет такой заявки (или удалена), 403 — чужая: это ответ политики на сервере
    error.value = e.status === 404 ? 'Заявка не найдена.' : e.status === 403 ? 'Это не ваша заявка.' : e.message
  } finally {
    loading.value = false
  }
}

/** Данные формы для отправки: пустые строки → null, числа — числами. */
function payload() {
  const text = (value) => (value === '' ? null : value)
  const number = (value) => (value === '' || value === null ? null : Number(value))

  return {
    company_name: text(form.company_name),
    inn: text(form.inn),
    amount: number(form.amount),
    term_months: number(form.term_months),
    purpose: text(form.purpose),
  }
}

/** Общая обёртка для действий: блокирует кнопки и раскладывает ошибки. */
async function run(action) {
  busy.value = true
  message.value = ''
  error.value = ''
  fieldErrors.value = {}

  try {
    await action()
  } catch (e) {
    if (e.status === 422) {
      fieldErrors.value = e.errors // ошибки валидации — к полям
    } else {
      error.value = e.message // 409 «уже отправлена», 403, сеть
    }
  } finally {
    busy.value = false
  }
}

function save() {
  return run(async () => {
    const { data } = await api.updateApplication(props.id, payload())
    apply(data)
    message.value = 'Черновик сохранён.'
  })
}

function submit() {
  return run(async () => {
    // Сначала сохраняем то, что в форме, потом отправляем: сервер валидирует данные из БД, а не из запроса.
    await api.updateApplication(props.id, payload())
    const { data } = await api.submitApplication(props.id) // 202 Accepted
    apply(data)
  })
}

function remove() {
  return run(async () => {
    await api.deleteApplication(props.id) // 204
    router.push({ name: 'applications' })
  })
}

const firstError = (field) => fieldErrors.value[field]?.[0] ?? ''

// Загружаем при открытии и при переходе на другую заявку без смены компонента
watch(() => props.id, load, { immediate: true })

// Уходим с экрана — останавливаем опрос, иначе таймер продолжит дёргать API
onBeforeUnmount(() => {
  unmounted = true
  stopPolling()
})
</script>

<template>
  <RouterLink :to="{ name: 'applications' }" class="back">← К списку</RouterLink>

  <p v-if="loading" class="empty">Загрузка…</p>
  <p v-else-if="!application" class="alert alert-error">{{ error }}</p>

  <template v-else>
    <div class="page-head">
      <h1>Заявка № {{ application.id }}</h1>
      <StatusBadge :status="application.status" :label="application.status_label" />
    </div>

    <p v-if="error" class="alert alert-error">{{ error }}</p>
    <p v-if="message" class="alert alert-success">{{ message }}</p>

    <!-- ===== Черновик: форма ===== -->
    <section v-if="application.can_edit" class="card">
      <form class="form" @submit.prevent="save">
        <label class="field">
          <span>Название компании</span>
          <input v-model="form.company_name" type="text" :class="{ invalid: firstError('company_name') }" />
          <small v-if="firstError('company_name')" class="field-error">{{ firstError('company_name') }}</small>
        </label>

        <label class="field">
          <span>ИНН</span>
          <input
            v-model="form.inn"
            type="text"
            inputmode="numeric"
            placeholder="10 или 12 цифр"
            :class="{ invalid: firstError('inn') }"
          />
          <small v-if="firstError('inn')" class="field-error">{{ firstError('inn') }}</small>
        </label>

        <div class="field-row">
          <label class="field">
            <span>Сумма, ₽</span>
            <input v-model="form.amount" type="number" min="1" step="1" :class="{ invalid: firstError('amount') }" />
            <small v-if="firstError('amount')" class="field-error">{{ firstError('amount') }}</small>
          </label>

          <label class="field">
            <span>Срок, мес.</span>
            <input
              v-model="form.term_months"
              type="number"
              min="1"
              max="120"
              step="1"
              :class="{ invalid: firstError('term_months') }"
            />
            <small v-if="firstError('term_months')" class="field-error">{{ firstError('term_months') }}</small>
          </label>
        </div>

        <label class="field">
          <span>Цель финансирования</span>
          <textarea v-model="form.purpose" rows="3" :class="{ invalid: firstError('purpose') }"></textarea>
          <small v-if="firstError('purpose')" class="field-error">{{ firstError('purpose') }}</small>
        </label>

        <div class="actions">
          <button type="submit" class="button" :disabled="busy">Сохранить черновик</button>
          <button type="button" class="button button-primary" :disabled="busy" @click="submit">
            Отправить на проверку
          </button>
          <button type="button" class="button button-danger" :disabled="busy" @click="remove">Удалить</button>
        </div>
      </form>

      <p class="hint">
        Черновик можно сохранить с любыми пустыми полями. При отправке сервер потребует заполнить все.
      </p>
    </section>

    <!-- ===== Отправленная заявка: просмотр ===== -->
    <template v-else>
      <section class="card">
        <dl class="details">
          <dt>Компания</dt>
          <dd>{{ application.company_name }}</dd>
          <dt>ИНН</dt>
          <dd>{{ application.inn }}</dd>
          <dt>Сумма</dt>
          <dd>{{ formatMoney(application.amount) }}</dd>
          <dt>Срок</dt>
          <dd>{{ application.term_months }} мес.</dd>
          <dt>Цель</dt>
          <dd>{{ application.purpose }}</dd>
          <dt>Отправлена</dt>
          <dd>{{ formatDate(application.submitted_at) }}</dd>
          <dt>Проверена</dt>
          <dd>{{ formatDate(application.checked_at) }}</dd>
        </dl>
      </section>

      <section class="card">
        <h2>Стоп-факторы</h2>

        <p v-if="application.status === 'submitted'" class="waiting">
          Заявка в очереди на проверку. Страница обновится сама.<br />
          <span class="muted">Если статус не меняется — не запущен воркер: <code>php artisan queue:work</code></span>
        </p>

        <ul v-else class="checks">
          <li v-for="check in application.stop_factors" :key="check.code" :class="check.passed ? 'passed' : 'failed'">
            <span class="check-mark">{{ check.passed ? '✓' : '✕' }}</span>
            <span>
              {{ STOP_FACTOR_LABELS[check.code] ?? check.code }}
              <small v-if="check.message" class="check-message">{{ check.message }}</small>
            </span>
          </li>
        </ul>
      </section>
    </template>
  </template>
</template>
