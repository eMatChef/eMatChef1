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
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import { plainToP } from '@/utils/siteHtmlMigrate'

const site = useSiteContentStore()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('tos'))
const pageTitle = computed(() => String(c.value.title ?? 'Nutzungsbedingungen & Datenschutz'))

interface SecRow {
  id?: string
  heading: string
  bodyHtml?: string
  body?: string
}

const sections = computed((): SecRow[] => {
  const raw = c.value.sections
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
