<template>
  <div class="public-page public-page--legal">
    <article class="public-card public-card--legal max-w-content">
      <h1>{{ pageTitle }}</h1>
      <dl class="faq-list">
        <template v-for="(item, i) in items" :key="i">
          <dt class="faq-q">{{ item.q }}</dt>
          <dd class="faq-a faq-a-html" v-html="answerHtml(item)" />
        </template>
      </dl>
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

const c = computed(() => site.getContent('faq'))
const pageTitle = computed(() => String(c.value.title ?? 'FAQ'))

interface ItemRow {
  q: string
  aHtml?: string
  a?: string
}

const items = computed((): ItemRow[] => {
  const raw = c.value.items
  if (!Array.isArray(raw)) return []
  return raw.map((row) => {
    if (typeof row !== 'object' || !row) return { q: '' }
    const o = row as Record<string, unknown>
    return {
      q: String(o.q ?? ''),
      aHtml: typeof o.aHtml === 'string' ? o.aHtml : undefined,
      a: typeof o.a === 'string' ? o.a : undefined,
    }
  })
})

function answerHtml(item: ItemRow): string {
  const s = item.aHtml?.trim()
  if (s) return sanitizePublicHtml(s)
  if (item.a) return sanitizePublicHtml(plainToP(item.a))
  return ''
}
</script>

<style scoped>
.max-w-content {
  max-width: 42rem;
  margin: 0 auto;
}

.faq-list {
  margin: 1.5rem 0 0;
}

.faq-q {
  font-weight: 600;
  color: #0f172a;
  margin-top: 1.25rem;
}

.faq-q:first-child {
  margin-top: 0;
}

.faq-a {
  margin: 0.35rem 0 0;
  padding: 0;
  color: #475569;
  line-height: 1.6;
}

.faq-a :deep(p) {
  margin: 0 0 0.5rem;
}

.faq-a :deep(ul),
.faq-a :deep(ol) {
  margin: 0 0 0.5rem;
  padding-left: 1.25rem;
}

.faq-a :deep(a) {
  color: #059669;
}
</style>
