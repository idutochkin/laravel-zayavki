<script setup>
/*
 * Вход и регистрация — одна форма с переключателем.
 * Сервер в обоих случаях отвечает одинаково: { token, user } (см. AuthController).
 */
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { login, register } from '../auth'

const router = useRouter()

const mode = ref('login') // 'login' | 'register'
const form = reactive({ name: '', email: 'demo@example.com', password: 'password' })
const error = ref(null) // ApiError или null
const loading = ref(false)

async function onSubmit() {
  error.value = null
  loading.value = true

  try {
    if (mode.value === 'login') {
      await login({ email: form.email, password: form.password })
    } else {
      await register({ name: form.name, email: form.email, password: form.password })
    }
    router.push({ name: 'applications' })
  } catch (e) {
    // 422 — ошибки по полям (неверный пароль тоже приходит как ошибка поля email),
    // 429 — сработал throttle на /login, 0 — сервер недоступен.
    error.value = e
  } finally {
    loading.value = false
  }
}

function switchMode(next) {
  mode.value = next
  error.value = null

  // Для входа подставляем демо-доступ, для регистрации — пустую форму
  const demo = next === 'login'
  form.name = ''
  form.email = demo ? 'demo@example.com' : ''
  form.password = demo ? 'password' : ''
}
</script>

<template>
  <section class="card card-narrow">
    <div class="tabs">
      <button type="button" class="tab" :class="{ active: mode === 'login' }" @click="switchMode('login')">Вход</button>
      <button type="button" class="tab" :class="{ active: mode === 'register' }" @click="switchMode('register')">
        Регистрация
      </button>
    </div>

    <form class="form" @submit.prevent="onSubmit">
      <label v-if="mode === 'register'" class="field">
        <span>Имя</span>
        <input v-model="form.name" type="text" autocomplete="name" :class="{ invalid: error?.first('name') }" />
        <small v-if="error?.first('name')" class="field-error">{{ error.first('name') }}</small>
      </label>

      <label class="field">
        <span>Email</span>
        <input v-model="form.email" type="email" autocomplete="email" :class="{ invalid: error?.first('email') }" />
        <small v-if="error?.first('email')" class="field-error">{{ error.first('email') }}</small>
      </label>

      <label class="field">
        <span>Пароль</span>
        <input
          v-model="form.password"
          type="password"
          :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
          :class="{ invalid: error?.first('password') }"
        />
        <small v-if="error?.first('password')" class="field-error">{{ error.first('password') }}</small>
      </label>

      <!-- Ошибки не по полям: сервер недоступен, слишком много попыток -->
      <p v-if="error && error.status !== 422" class="alert alert-error">{{ error.message }}</p>

      <button type="submit" class="button button-primary" :disabled="loading">
        {{ mode === 'login' ? 'Войти' : 'Зарегистрироваться' }}
      </button>
    </form>

    <p v-if="mode === 'login'" class="hint">Демо-доступ из сидера: demo@example.com / password</p>
  </section>
</template>
