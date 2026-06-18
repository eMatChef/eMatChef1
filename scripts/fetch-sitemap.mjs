#!/usr/bin/env node
/**
 * Holt sitemap.xml von der API und schreibt sie ins Hostpoint-Home-Verzeichnis.
 * Fallback: statische sitemap.xml aus dem Vite-Build bleibt unverändert.
 *
 * Usage: node scripts/fetch-sitemap.mjs <out-dir> [api-base]
 */
import { writeFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'

const outDir = process.argv[2]
const apiBase = (process.argv[3] || 'https://api.ematchef.ch').replace(/\/$/, '')

if (!outDir) {
  console.error('Usage: node scripts/fetch-sitemap.mjs <out-dir> [api-base]')
  process.exit(1)
}

const target = resolve(outDir, 'sitemap.xml')
const url = `${apiBase}/api/public/sitemap.xml`

try {
  const res = await fetch(url, { headers: { Accept: 'application/xml' } })
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`)
  }
  const xml = await res.text()
  if (!xml.includes('<urlset')) {
    throw new Error('Ungültige Sitemap-Antwort')
  }
  writeFileSync(target, xml, 'utf8')
  console.log(`sitemap.xml aktualisiert (${url})`)
} catch (err) {
  if (existsSync(target)) {
    console.warn(`Sitemap von API nicht verfügbar (${url}): ${err?.message || err} — statische Version bleibt`)
    process.exit(0)
  }
  console.error(`Sitemap fehlgeschlagen und keine Fallback-Datei: ${err?.message || err}`)
  process.exit(1)
}
