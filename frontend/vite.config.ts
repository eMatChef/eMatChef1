import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Host: Symfony auf 127.0.0.1:8081. Docker-Frontend-Container: Backend-Service (nicht localhost im Container!)
  const apiProxyTarget = env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8081'

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        '@': resolve(__dirname, 'src'),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      // Mehrere Hosts (app.localhost / qr.localhost): kein fixes origin – Host kommt vom Nginx-Proxy
      allowedHosts: ['app.localhost', 'qr.localhost', 'localhost', '127.0.0.1'],
      hmr: {
        clientPort: Number(process.env.HMR_CLIENT_PORT) || 5173,
      },
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
          secure: false,
        },
      },
    },
  }
})
