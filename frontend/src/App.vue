<script setup>
/*
 * Корневой компонент: шапка + место, куда роутер подставляет текущий экран.
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { auth, logout, restoreUser } from './auth'

const router = useRouter()

// Страницу перезагрузили: токен в localStorage есть, имени пользователя нет — запросим /api/me.
// Ошибку здесь не показываем: если токен недействителен, сработает общий обработчик 401.
onMounted(() => {
  restoreUser().catch(() => {})
})

async function onLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="topbar">
    <RouterLink :to="{ name: 'applications' }" class="brand">Заявки</RouterLink>

    <div v-if="auth.isAuthenticated" class="topbar-user">
      <span v-if="auth.user" class="muted">{{ auth.user.name }}</span>
      <button type="button" class="button button-ghost" @click="onLogout">Выйти</button>
    </div>
  </header>

  <main class="container">
    <!-- Сюда vue-router вставляет компонент текущего маршрута (см. router.js) -->
    <RouterView />
  </main>
</template>
