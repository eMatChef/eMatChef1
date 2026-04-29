import type { IncomingMessage } from 'http'
import type { Plugin } from 'vite'

/**
 * Ohne Subdomain teilen Browser HttpOnly-Cookies mit Domain=.localhost nicht mit „localhost“.
 * Dev: kanonische Public-/Marketing-URL = app.localhost (gleicher Port).
 */
function allowRedirect(req: IncomingMessage, pathname: string): boolean {
  const m = (req.method || 'GET').toUpperCase()
  if (m !== 'GET' && m !== 'HEAD') return false
  if (pathname.startsWith('/api')) return false
  if (pathname.startsWith('/@')) return false
  if (pathname.startsWith('/node_modules')) return false
  if (pathname.startsWith('/__vite')) return false
  if (pathname.startsWith('/src')) return false
  if (String(req.headers.upgrade || '').toLowerCase() === 'websocket') return false
  return true
}

export function redirectLocalhostToAppLocalhost(): Plugin {
  return {
    name: 'ematchef-redirect-localhost-dev',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const rawHost = String(req.headers.host || '')
        const url = req.url || '/'
        let pathname = url
        const q = pathname.indexOf('?')
        if (q !== -1) pathname = pathname.slice(0, q)
        if (!allowRedirect(req, pathname)) {
          next()
          return
        }
        const hostOnly = rawHost.includes(':')
          ? rawHost.slice(0, rawHost.lastIndexOf(':')).toLowerCase()
          : rawHost.toLowerCase()
        if (hostOnly !== 'localhost' && hostOnly !== '127.0.0.1') {
          next()
          return
        }
        const port = rawHost.includes(':')
          ? rawHost.slice(rawHost.lastIndexOf(':') + 1)
          : String(server.config.server?.port ?? 5173)
        res.statusCode = 302
        res.setHeader('Location', `http://app.localhost:${port}${url}`)
        res.setHeader('Cache-Control', 'no-store')
        res.end()
      })
    },
  }
}
