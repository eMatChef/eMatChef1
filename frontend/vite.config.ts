import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'
import { redirectLocalhostToAppLocalhost } from './vite-plugin-redirect-localhost'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // loadEnv liest nur .env-Dateien — Compose setzt VITE_API_PROXY_TARGET dagegen in process.env (Docker).
  const apiProxyTarget =
    process.env.VITE_API_PROXY_TARGET ||
    env.VITE_API_PROXY_TARGET ||
    'http://127.0.0.1:8081'

  return {
    plugins: [redirectLocalhostToAppLocalhost(), vue()],
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
          // PHP Built-in Server (docker-compose: php -S) setzt fälschlich "Host" als
          // *Response*-Header. Das verletzt HTTP; Node kopiert die Header beim Proxy
          // und wirft → 500. Nginx toleriert es, Vite/http-proxy nicht.
          configure: (proxy) => {
            proxy.on('proxyRes', (proxyRes) => {
              delete proxyRes.headers.host
            })
          },
        },
      },
    },
  }
})
