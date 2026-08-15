import type { Plugin } from 'vite'
import { loadEnv } from 'vite'

const DEFAULT_TITLE = 'eMatChef – Materialverwaltung für Vereine und Vermietungen'
const DEFAULT_DESCRIPTION =
  'eMatChef hält Bestände, Lager, Buchungen und Aktivitäten im Griff – für Vereine, Pfadi und Materialverleihe. Läuft im Browser, ohne Installation.'

function envValue(env: Record<string, string>, key: string): string {
  return (process.env[key] || env[key] || '').trim()
}

function siteOrigin(env: Record<string, string>): string {
  return (envValue(env, 'VITE_MAIN_SITE_ORIGIN') || 'https://ematchef.ch').replace(/\/$/, '')
}

function isDevDeploy(env: Record<string, string>): boolean {
  const flag = envValue(env, 'VITE_SHOW_DEV_BANNER').toLowerCase()
  if (flag === '1' || flag === 'true' || flag === 'yes') {
    return true
  }
  const origin = siteOrigin(env).toLowerCase()
  return (
    origin.includes('dev.ematchef.ch') ||
    origin.includes('.dev.ematchef.ch') ||
    origin.includes('.staging.ematchef.ch') ||
    origin.includes('.ematchef.test')
  )
}

function isMarketingVariant(env: Record<string, string>): boolean {
  return envValue(env, 'VITE_DEPLOY_VARIANT').toLowerCase() !== 'app'
}

function shouldIndexMarketing(env: Record<string, string>): boolean {
  return isMarketingVariant(env) && !isDevDeploy(env)
}

function escapeAttr(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
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
    `> ${DEFAULT_DESCRIPTION}`,
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

function marketingJsonLd(origin: string): string {
  const graph = {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Organization',
        '@id': `${origin}/#organization`,
        name: 'eMatChef',
        url: origin,
        logo: `${origin}/favicon-192.png`,
        description: DEFAULT_DESCRIPTION,
      },
      {
        '@type': 'WebSite',
        '@id': `${origin}/#website`,
        name: 'eMatChef',
        url: origin,
        inLanguage: 'de-CH',
        publisher: { '@id': `${origin}/#organization` },
      },
      {
        '@type': 'SoftwareApplication',
        name: 'eMatChef',
        url: origin,
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web browser',
        inLanguage: 'de-CH',
        description: DEFAULT_DESCRIPTION,
        image: `${origin}/og-image.png`,
        publisher: { '@id': `${origin}/#organization` },
      },
    ],
  }
  const json = JSON.stringify(graph).replace(/</g, '\\u003c')
  return `    <script type="application/ld+json">${json}</script>`
}

function googleVerificationMeta(env: Record<string, string>): string {
  const token = envValue(env, 'VITE_GOOGLE_SITE_VERIFICATION')
  if (!token) {
    return ''
  }
  return `    <meta name="google-site-verification" content="${escapeAttr(token)}">`
}

function headInjection(env: Record<string, string>, marketing: boolean, indexable: boolean): string {
  const origin = siteOrigin(env)
  const description = escapeAttr(DEFAULT_DESCRIPTION)
  const title = escapeAttr(DEFAULT_TITLE)
  if (!marketing || !indexable) {
    return `    <meta name="robots" content="noindex, nofollow">\n`
  }
  return [
    googleVerificationMeta(env),
    `    <meta name="description" content="${description}">`,
    `    <meta name="robots" content="index, follow">`,
    `    <link rel="canonical" href="${origin}/">`,
    `    <meta property="og:title" content="${title}">`,
    `    <meta property="og:description" content="${description}">`,
    `    <meta property="og:type" content="website">`,
    `    <meta property="og:url" content="${origin}/">`,
    `    <meta property="og:site_name" content="eMatChef">`,
    `    <meta property="og:locale" content="de_CH">`,
    `    <meta property="og:image" content="${origin}/og-image.png">`,
    `    <meta property="og:image:width" content="1200">`,
    `    <meta property="og:image:height" content="630">`,
    `    <meta name="twitter:card" content="summary_large_image">`,
    `    <meta name="twitter:title" content="${title}">`,
    `    <meta name="twitter:description" content="${description}">`,
    `    <meta name="twitter:image" content="${origin}/og-image.png">`,
    `    <meta name="theme-color" content="#10b981">`,
    marketingJsonLd(origin),
    '',
  ]
    .filter((line) => line !== '')
    .join('\n')
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
      let next = html
      next = next.replace(
        /<title>[\s\S]*?<\/title>/,
        `<title>${escapeAttr(marketing && indexable ? DEFAULT_TITLE : 'eMatChef')}</title>`,
      )
      const injection = headInjection(env, marketing, indexable)
      if (next.includes('</head>')) {
        return next.replace('</head>', `${injection}\n  </head>`)
      }
      return next
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
