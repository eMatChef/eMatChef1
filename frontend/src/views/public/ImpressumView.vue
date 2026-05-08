<template>
  <div class="public-page public-page--legal">
    <article class="public-card public-card--legal max-w-content">
      <h1>{{ pageTitle }}</h1>

      <template v-if="sections.length">
        <section v-for="(sec, i) in sections" :key="i" class="block">
          <h2>{{ sec.heading }}</h2>
          <div class="imp-html" v-html="sanitizePublicHtml(sec.bodyHtml)" />
        </section>
      </template>
      <template v-else>
        <section class="block">
          <h2>{{ fallbackProviderHeading }}</h2>
          <div class="pre">{{ company }}</div>
          <div class="pre contact">{{ contact }}</div>
        </section>
        <section class="block">
          <h2>{{ fallbackRepresentativeHeading }}</h2>
          <p>{{ representative }}</p>
        </section>
        <section class="block">
          <h2>{{ fallbackLiabilityHeading }}</h2>
          <p>{{ liability }}</p>
        </section>
      </template>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'

const site = useSiteContentStore()
const { locale, t } = useI18n()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('impressum'))
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
const pageTitle = computed(() => String(localized.value.title ?? c.value.title ?? t('publicNav.impressum')))

interface ImpSec {
  heading: string
  bodyHtml: string
}

const sections = computed((): ImpSec[] => {
  const raw = localized.value.sections ?? c.value.sections
  if (!Array.isArray(raw)) return []
  return raw
    .map((row) => {
      if (typeof row !== 'object' || !row) return null
      const o = row as Record<string, unknown>
      const heading = String(o.heading ?? '')
      const bodyHtml = typeof o.bodyHtml === 'string' ? o.bodyHtml : ''
      if (!heading && !bodyHtml.trim()) return null
      return { heading, bodyHtml: bodyHtml || '<p></p>' }
    })
    .filter((x): x is ImpSec => x !== null)
})

const company = computed(() => String(localized.value.company ?? c.value.company ?? '') + '\n' + String(localized.value.address ?? c.value.address ?? ''))
const contact = computed(() => String(localized.value.contact ?? c.value.contact ?? ''))
const representative = computed(() => String(localized.value.representative ?? c.value.representative ?? ''))
const liability = computed(() => String(localized.value.liability ?? c.value.liability ?? ''))
const fallbackProviderHeading = computed(() => String(localized.value.fallbackProviderHeading ?? t('public.impressum.fallbackProviderHeading')))
const fallbackRepresentativeHeading = computed(() => String(localized.value.fallbackRepresentativeHeading ?? t('public.impressum.fallbackRepresentativeHeading')))
const fallbackLiabilityHeading = computed(() => String(localized.value.fallbackLiabilityHeading ?? t('public.impressum.fallbackLiabilityHeading')))
</script>

<style scoped>
.max-w-content {
  max-width: 42rem;
  margin: 0 auto;
}

.block {
  margin-top: 1.75rem;
}

.block h2 {
  font-size: 1.05rem;
  margin-bottom: 0.5rem;
}

.pre {
  white-space: pre-wrap;
  line-height: 1.55;
  color: #334155;
}

.contact {
  margin-top: 0.75rem;
}

p {
  color: #475569;
  line-height: 1.6;
  margin: 0;
}

.imp-html {
  line-height: 1.65;
  color: #334155;
}

.imp-html :deep(p) {
  margin: 0 0 0.65rem;
}

.imp-html :deep(a) {
  color: #059669;
}
</style>
