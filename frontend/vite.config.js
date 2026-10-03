import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Vite — сборщик и dev-сервер фронта. К Laravel отношения не имеет:
// это отдельный процесс на отдельном порту (5173), который отдаёт только статику.
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    strictPort: true,
  },
})
