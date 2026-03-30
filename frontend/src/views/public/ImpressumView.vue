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
          <h2>Angaben gemäß TMG / Schweiz: Anbieterkennzeichnung</h2>
          <div class="pre">{{ company }}</div>
          <div class="pre contact">{{ contact }}</div>
        </section>
        <section class="block">
          <h2>Vertretungsberechtigt</h2>
          <p>{{ representative }}</p>
        </section>
        <section class="block">
          <h2>Haftung für Inhalte</h2>
          <p>{{ liability }}</p>
        </section>
      </template>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'

const site = useSiteContentStore()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('impressum'))
const pageTitle = computed(() => String(c.value.title ?? 'Impressum'))

interface ImpSec {
  heading: string
  bodyHtml: string
}

const sections = computed((): ImpSec[] => {
  const raw = c.value.sections
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

const company = computed(() => String(c.value.company ?? '') + '\n' + String(c.value.address ?? ''))
const contact = computed(() => String(c.value.contact ?? ''))
const representative = computed(() => String(c.value.representative ?? ''))
const liability = computed(() => String(c.value.liability ?? ''))
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
