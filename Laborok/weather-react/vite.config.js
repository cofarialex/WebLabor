import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // a /api/weather kéréseket az emulátorban futó Function kapja
      '/api/weather': {
        target: 'http://127.0.0.1:5001',
        rewrite: (path) => path.replace('/api/weather', '/weather-app-3e799/us-central1/getWeather'),
      },
    },
  },
})
