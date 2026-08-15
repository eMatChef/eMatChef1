<template>
  <PublicContentPage variant="content">
    <template #hero>
      <p class="plt-subpage-kicker">{{ t('public.faq.kicker') }}</p>
      <h1 class="plt-subpage-title">{{ pageTitle }}</h1>
      <p class="plt-subpage-lead">{{ t('public.faq.lead') }}</p>
    </template>

    <div v-if="items.length" class="plt-faq-list">
      <article v-for="(item, i) in items" :key="i" class="plt-faq-item">
        <h2 class="plt-faq-question">{{ item.q }}</h2>
        <div class="plt-legal-prose plt-faq-answer" v-html="answerHtml(item)" />
      </article>
    </div>
    <EEmptyState v-else :title="t('public.faq.emptyTitle')" :description="t('public.faq.emptyBody')" />
  </PublicContentPage>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicContentPage from '@/components/layout/PublicContentPage.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { localizedPublicContent } from '@/utils/publicSiteLocale'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import { plainToP } from '@/utils/siteHtmlMigrate'
import { setFaqPageJsonLd } from '@/composables/usePageHead'

const site = useSiteContentStore()
const { t, locale } = useI18n()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('faq'))
const localized = computed(() => localizedPublicContent(c.value, String(locale.value)))
const pageTitle = computed(() => String(localized.value.title ?? c.value.title ?? 'FAQ'))

interface ItemRow {
  q: string
  aHtml?: string
  a?: string
}

const items = computed((): ItemRow[] => {
  const raw = localized.value.items ?? c.value.items
  if (!Array.isArray(raw)) return []
  return raw
    .map((row) => {
      if (typeof row !== 'object' || !row) return { q: '' }
      const o = row as Record<string, unknown>
      return {
        q: String(o.q ?? ''),
        aHtml: typeof o.aHtml === 'string' ? o.aHtml : undefined,
        a: typeof o.a === 'string' ? o.a : undefined,
      }
    })
    .filter((item) => item.q.trim())
})

watch(
  items,
  (rows) => {
    setFaqPageJsonLd(rows)
  },
  { immediate: true },
)

function answerHtml(item: ItemRow): string {
  const s = item.aHtml?.trim()
  if (s) return sanitizePublicHtml(s)
  if (item.a) return sanitizePublicHtml(plainToP(item.a))
  return ''
}
</script>
