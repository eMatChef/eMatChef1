import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import { readFileSync } from 'fs'
import { execSync } from 'child_process'
import { resolve } from 'path'
import { canonicalizeLocalhostMain } from './vite-plugin-canonical-localhost'
import { siteSeoPlugin } from './vite-plugin-site-seo'

function readPackageVersion(): string {
  try {
    const pkg = JSON.parse(readFileSync(resolve(__dirname, 'package.json'), 'utf-8')) as {
      version?: string
    }
    return (pkg.version || '0.0.0').trim()
  } catch {
    return '0.0.0'
  }
}

function readGitSha(): string {
  try {
    return execSync('git rev-parse --short=7 HEAD', { encoding: 'utf-8' }).trim()
  } catch {
    return ''
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // loadEnv liest nur .env-Dateien — Compose setzt VITE_API_PROXY_TARGET dagegen in process.env (Docker).
  const apiProxyTarget =
    process.env.VITE_API_PROXY_TARGET ||
    env.VITE_API_PROXY_TARGET ||
    'http://127.0.0.1:8081'

  // Hostpoint scripts may set these; otherwise derive from package.json / git.
  const appVersion = process.env.VITE_APP_VERSION || env.VITE_APP_VERSION || readPackageVersion()
  const appGitSha = process.env.VITE_APP_GIT_SHA || env.VITE_APP_GIT_SHA || readGitSha()

  return {
    define: {
      'import.meta.env.VITE_APP_VERSION': JSON.stringify(appVersion),
      'import.meta.env.VITE_APP_GIT_SHA': JSON.stringify(appGitSha),
    },
    plugins: [
      canonicalizeLocalhostMain(),
      siteSeoPlugin(),
      vue(),
      vuetify({ autoImport: true }),
    ],
    resolve: {
      alias: {
        '@': resolve(__dirname, 'src'),
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      // Lokal wie Produktion: Apex + Subdomains auf derselben Basisdomain.
      allowedHosts: [
        'ematchef.test',
        'app.ematchef.test',
        'qr.ematchef.test',
        'devices.ematchef.test',
        'localhost',
        '127.0.0.1',
      ],
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
    test: {
      environment: 'node',
      include: ['src/**/*.spec.ts'],
    },
  }
})
