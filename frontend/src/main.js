import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from './api'
import { forget } from './auth'
import './style.css'

// Сервер ответил 401 на любой запрос — токен больше не действует: забываем его и ведём на вход.
setUnauthorizedHandler(() => {
  forget()
  router.push({ name: 'login' })
})

createApp(App).use(router).mount('#app')
