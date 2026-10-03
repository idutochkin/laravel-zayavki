<script setup>
/*
 * Список заявок: счётчики по статусам, фильтр, таблица, пагинация.
 *
 * Фильтр и номер страницы лежат в адресной строке (?status=draft&page=2), а не в переменных:
 * ссылку можно скопировать, кнопка «назад» работает. Компонент просто следит за адресом
 * и при каждом его изменении перезапрашивает данные.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import { STATUSES, formatDate, formatMoney } from '../format'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const router = useRouter()

const applications = ref([])
const meta = ref(null) // блок meta из ответа Laravel: current_page, last_page, total
const stats = ref(null) // { draft: 2, submitted: 0, ... }
const loading = ref(true)
const error = ref('')
const creating = ref(false)

const status = computed(() => route.query.status ?? '')
const page = computed(() => Number(route.query.page ?? 1))

const totalCount = computed(() =>
  stats.value ? Object.values(stats.value).reduce((sum, count) => sum + count, 0) : null,
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    // Два независимых запроса отправляем параллельно.
    // Ответ списка — это LoanApplicationResource::collection() от paginate():
    //   { data: [...], links: {...}, meta: { current_page, last_page, total, ... } }
    const [list, statsResponse] = await Promise.all([
      api.listApplications({ status: status.value, page: page.value }),
      api.stats(),
    ])

    applications.value = list.data
    meta.value = list.meta
    stats.value = statsResponse.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// immediate: true — выполнить сразу при открытии экрана, а потом при каждом изменении фильтра или страницы
watch([status, page], load, { immediate: true })

function setStatus(value) {
  router.push({ query: value ? { status: value } : {} }) // страница сбрасывается на первую
}

function setPage(value) {
  router.push({ query: { ...route.query, page: value } })
}

/*
 * «Новая заявка» сразу создаёт на сервере ПУСТОЙ черновик и открывает его.
 * Так у формы с первой секунды есть id, и любое сохранение — это обычный PUT.
 * Сервер это разрешает: в SaveDraftRequest все поля nullable.
 */
async function createDraft() {
  creating.value = true
  error.value = ''

  try {
    const { data } = await api.createApplication()
    router.push({ name: 'application', params: { id: data.id } })
  } catch (e) {
    error.value = e.message
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="page-head">
    <h1>Мои заявки</h1>
    <button type="button" class="button button-primary" :disabled="creating" @click="createDraft">Новая заявка</button>
  </div>

  <nav class="filters" aria-label="Фильтр по статусу">
    <button type="button" class="chip" :class="{ active: status === '' }" @click="setStatus('')">
      Все <span v-if="totalCount !== null" class="chip-count">{{ totalCount }}</span>
    </button>
    <button
      v-for="item in STATUSES"
      :key="item.value"
      type="button"
      class="chip"
      :class="{ active: status === item.value }"
      @click="setStatus(item.value)"
    >
      {{ item.label }} <span v-if="stats" class="chip-count">{{ stats[item.value] }}</span>
    </button>
  </nav>

  <p v-if="error" class="alert alert-error">{{ error }}</p>

  <section class="card">
    <p v-if="loading && !applications.length" class="empty">Загрузка…</p>

    <p v-else-if="!applications.length" class="empty">
      {{ status ? 'В этом статусе заявок нет.' : 'Заявок пока нет — создайте первую.' }}
    </p>

    <table v-else class="table">
      <thead>
        <tr>
          <th>№</th>
          <th>Компания</th>
          <th>ИНН</th>
          <th class="num">Сумма</th>
          <th>Статус</th>
          <th>Изменена</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="application in applications" :key="application.id">
          <td>{{ application.id }}</td>
          <td>
            <RouterLink :to="{ name: 'application', params: { id: application.id } }">
              {{ application.company_name || 'Без названия' }}
            </RouterLink>
          </td>
          <td>{{ application.inn || '—' }}</td>
          <td class="num">{{ formatMoney(application.amount) }}</td>
          <td><StatusBadge :status="application.status" :label="application.status_label" /></td>
          <td class="muted">{{ formatDate(application.updated_at) }}</td>
        </tr>
      </tbody>
    </table>
  </section>

  <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Страницы">
    <button type="button" class="button" :disabled="meta.current_page <= 1" @click="setPage(meta.current_page - 1)">
      ← Назад
    </button>
    <span class="muted">Страница {{ meta.current_page }} из {{ meta.last_page }}</span>
    <button
      type="button"
      class="button"
      :disabled="meta.current_page >= meta.last_page"
      @click="setPage(meta.current_page + 1)"
    >
      Вперёд →
    </button>
  </nav>
</template>
