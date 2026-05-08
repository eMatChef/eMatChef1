import type { IncomingMessage } from 'http'
import type { Plugin } from 'vite'

const CANONICAL_LOCAL_MAIN_HOST = 'ematchef.test'

function shouldRedirect(req: IncomingMessage, path: string): boolean {
  const method = (req.method || 'GET').toUpperCase()
  if (method !== 'GET' && method !== 'HEAD') return false
  if (path.startsWith('/api')) return false
  if (path.startsWith('/@')) return false
  if (path.startsWith('/node_modules')) return false
  if (path.startsWith('/__vite')) return false
  if (path.startsWith('/src')) return false
  if (String(req.headers.upgrade || '').toLowerCase() === 'websocket') return false
  return true
}

export function canonicalizeLocalhostMain(): Plugin {
  return {
    name: 'ematchef-canonical-localhost-main',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const rawHost = String(req.headers.host || '')
        const [hostOnly, port] = rawHost.split(':')
        const currentHost = String(hostOnly || '').toLowerCase()
        const requestUrl = req.url || '/'
        const path = requestUrl.split('?')[0] || '/'

        if (!shouldRedirect(req, path)) {
          next()
          return
        }

        if (currentHost !== 'localhost' && currentHost !== '127.0.0.1') {
          next()
          return
        }

        const targetPort = port || String(server.config.server?.port || 5173)
        res.statusCode = 302
        res.setHeader('Location', `http://${CANONICAL_LOCAL_MAIN_HOST}:${targetPort}${requestUrl}`)
        res.setHeader('Cache-Control', 'no-store')
        res.end()
      })
    },
  }
}
