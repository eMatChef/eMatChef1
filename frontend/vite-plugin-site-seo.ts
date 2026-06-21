import type { Plugin } from 'vite'
import { loadEnv } from 'vite'

const DEFAULT_DESCRIPTION =
  'eMatChef – Materialverwaltung für Vermietungen und Abteilungen: Bestände, Lager, Buchungen und Aktivitäten im Browser.'

function siteOrigin(env: Record<string, string>): string {
  return (env.VITE_MAIN_SITE_ORIGIN || 'https://ematchef.ch').trim().replace(/\/$/, '')
}

function isDevDeploy(env: Record<string, string>): boolean {
  const flag = (env.VITE_SHOW_DEV_BANNER || '').trim().toLowerCase()
  if (flag === '1' || flag === 'true' || flag === 'yes') {
    return true
  }
  const origin = siteOrigin(env).toLowerCase()
  return origin.includes('dev.ematchef.ch') || origin.includes('.ematchef.test')
}

function isMarketingVariant(env: Record<string, string>): boolean {
  return (env.VITE_DEPLOY_VARIANT || 'home').trim().toLowerCase() !== 'app'
}

function shouldIndexMarketing(env: Record<string, string>): boolean {
  return isMarketingVariant(env) && !isDevDeploy(env)
}

function robotsTxt(origin: string, marketing: boolean, indexable: boolean): string {
  if (!marketing || !indexable) {
    return ['User-agent: *', 'Disallow: /', ''].join('\n')
  }
  return [
    'User-agent: *',
    'Allow: /',
    '',
    `Sitemap: ${origin}/sitemap.xml`,
    '',
    '# KI-Crawler (öffentliche Marketing-Seiten)',
    'User-agent: GPTBot',
    'Allow: /',
    '',
    'User-agent: ChatGPT-User',
    'Allow: /',
    '',
    'User-agent: Google-Extended',
    'Allow: /',
    '',
    'User-agent: anthropic-ai',
    'Allow: /',
    '',
    'User-agent: ClaudeBot',
    'Allow: /',
    '',
  ].join('\n')
}

function llmsTxt(origin: string): string {
  return [
    '# eMatChef',
    '',
    '> Materialverwaltung für Vermietungen und Abteilungen – Bestände, Lager, Buchungen und Aktivitäten im Browser.',
    '',
    '## Öffentliche Seiten',
    '',
    `- Startseite: ${origin}/`,
    `- FAQ: ${origin}/faq`,
    `- Blog: ${origin}/blog`,
    `- Nutzung & Datenschutz: ${origin}/tos`,
    `- Impressum: ${origin}/impressum`,
  ].join('\n')
}

function staticSitemapXml(origin: string): string {
  const today = new Date().toISOString().slice(0, 10)
  const urls = ['/', '/faq', '/blog', '/tos', '/impressum']
  const lines = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
  ]
  for (const path of urls) {
    lines.push('  <url>')
    lines.push(`    <loc>${origin}${path === '/' ? '/' : path}</loc>`)
    lines.push(`    <lastmod>${today}</lastmod>`)
    lines.push('  </url>')
  }
  lines.push('</urlset>', '')
  return lines.join('\n')
}

function headInjection(env: Record<string, string>, marketing: boolean, indexable: boolean): string {
  const origin = siteOrigin(env)
  const description = DEFAULT_DESCRIPTION.replace(/"/g, '&quot;')
  if (!marketing || !indexable) {
    return `    <meta name="robots" content="noindex, nofollow">\n`
  }
  return [
    `    <meta name="description" content="${description}">`,
    `    <meta name="robots" content="index, follow">`,
    `    <link rel="canonical" href="${origin}/">`,
    `    <meta property="og:site_name" content="eMatChef">`,
    `    <meta property="og:locale" content="de_CH">`,
    `    <meta property="og:image" content="${origin}/og-image.png">`,
    `    <meta property="og:image:width" content="1200">`,
    `    <meta property="og:image:height" content="630">`,
    `    <meta name="twitter:card" content="summary_large_image">`,
    `    <meta name="twitter:image" content="${origin}/og-image.png">`,
    `    <meta name="theme-color" content="#10b981">`,
    '',
  ].join('\n')
}

export function siteSeoPlugin(): Plugin {
  let env: Record<string, string> = {}

  return {
    name: 'ematchef-site-seo',
    config(_config, { mode }) {
      env = loadEnv(mode, process.cwd(), '')
    },
    transformIndexHtml(html) {
      const marketing = isMarketingVariant(env)
      const indexable = shouldIndexMarketing(env)
      const injection = headInjection(env, marketing, indexable)
      if (html.includes('</head>')) {
        return html.replace('</head>', `${injection}  </head>`)
      }
      return html
    },
    generateBundle() {
      const marketing = isMarketingVariant(env)
      const indexable = shouldIndexMarketing(env)
      const origin = siteOrigin(env)
      this.emitFile({
        type: 'asset',
        fileName: 'robots.txt',
        source: robotsTxt(origin, marketing, indexable),
      })
      if (marketing && indexable) {
        this.emitFile({
          type: 'asset',
          fileName: 'llms.txt',
          source: llmsTxt(origin),
        })
        this.emitFile({
          type: 'asset',
          fileName: 'sitemap.xml',
          source: staticSitemapXml(origin),
        })
      }
    },
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        const path = (req.url || '').split('?')[0]
        const marketing = isMarketingVariant(env)
        const indexable = shouldIndexMarketing(env)
        const origin = siteOrigin(env)
        if (path === '/robots.txt') {
          res.setHeader('Content-Type', 'text/plain; charset=utf-8')
          res.end(robotsTxt(origin, marketing, indexable))
          return
        }
        if (marketing && indexable && path === '/llms.txt') {
          res.setHeader('Content-Type', 'text/plain; charset=utf-8')
          res.end(llmsTxt(origin))
          return
        }
        if (marketing && indexable && path === '/sitemap.xml') {
          res.setHeader('Content-Type', 'application/xml; charset=utf-8')
          res.end(staticSitemapXml(origin))
          return
        }
        next()
      })
    },
  }
}
