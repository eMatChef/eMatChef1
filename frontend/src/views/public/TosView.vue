<template>
  <div class="public-page public-page--legal">
    <article class="public-card public-card--legal max-w-content">
      <h1>{{ pageTitle }}</h1>
      <section
        v-for="(sec, i) in sections"
        :id="sec.id || undefined"
        :key="i"
        class="legal-section"
      >
        <h2>{{ sec.heading }}</h2>
        <div class="legal-body legal-body-html" v-html="sectionHtml(sec)" />
      </section>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import { plainToP } from '@/utils/siteHtmlMigrate'

const site = useSiteContentStore()
const { locale } = useI18n()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('tos'))
type PageLocale = 'de' | 'en' | 'fr'

function preferredLocale(): PageLocale {
  const lc = String(locale.value || 'de').toLowerCase()
  if (lc.startsWith('en')) return 'en'
  if (lc.startsWith('fr')) return 'fr'
  return 'de'
}

function localizedContent(raw: Record<string, unknown>): Record<string, unknown> {
  const localesRaw = raw.locales
  if (!localesRaw || typeof localesRaw !== 'object') return raw
  const locales = localesRaw as Record<string, unknown>
  const order: PageLocale[] = [preferredLocale(), 'de', 'en', 'fr']
  for (const loc of order) {
    const entry = locales[loc]
    if (entry && typeof entry === 'object') {
      return entry as Record<string, unknown>
    }
  }
  return raw
}

const localized = computed(() => localizedContent(c.value))
const pageTitle = computed(() => String(localized.value.title ?? c.value.title ?? 'Nutzungsbedingungen & Datenschutz'))

interface SecRow {
  id?: string
  heading: string
  bodyHtml?: string
  body?: string
}

const sections = computed((): SecRow[] => {
  const raw = localized.value.sections ?? c.value.sections
  if (!Array.isArray(raw)) return []
  return raw.map((row) => {
    if (typeof row !== 'object' || !row) return { heading: '', bodyHtml: '' }
    const o = row as Record<string, unknown>
    return {
      id: o.id != null ? String(o.id) : undefined,
      heading: String(o.heading ?? ''),
      bodyHtml: typeof o.bodyHtml === 'string' ? o.bodyHtml : undefined,
      body: typeof o.body === 'string' ? o.body : undefined,
    }
  })
})

function sectionHtml(sec: SecRow): string {
  const h = sec.bodyHtml?.trim()
  if (h) return sanitizePublicHtml(h)
  if (sec.body) return sanitizePublicHtml(plainToP(sec.body))
  return ''
}
</script>

<style scoped>
.max-w-content {
  max-width: 48rem;
  margin: 0 auto;
}

.legal-section {
  margin-top: 2rem;
}

.legal-section:first-of-type {
  margin-top: 1.25rem;
}

.legal-section h2 {
  font-size: 1.15rem;
  margin-bottom: 0.5rem;
}

.legal-body {
  white-space: pre-wrap;
  color: #475569;
  line-height: 1.65;
}

.legal-body-html :deep(p) {
  margin: 0 0 0.65rem;
  white-space: normal;
}

.legal-body-html :deep(ul),
.legal-body-html :deep(ol) {
  margin: 0 0 0.65rem;
  padding-left: 1.25rem;
}

.legal-body-html :deep(a) {
  color: #059669;
}
</style>
