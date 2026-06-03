<template>
  <PublicContentPage variant="content">
    <template #hero>
      <p class="plt-subpage-kicker">{{ t('public.tos.kicker') }}</p>
      <h1 class="plt-subpage-title">{{ pageTitle }}</h1>
      <p class="plt-subpage-lead">{{ t('public.tos.lead') }}</p>
    </template>

    <div v-if="sections.length" class="plt-legal-list">
      <article
        v-for="(sec, i) in sections"
        :id="sec.id || undefined"
        :key="i"
        class="plt-legal-item"
      >
        <h2 class="plt-legal-item-title">{{ sec.heading }}</h2>
        <div class="plt-legal-prose plt-legal-item-body" v-html="sectionHtml(sec)" />
      </article>
    </div>
    <EEmptyState v-else :title="t('public.tos.emptyTitle')" :description="t('public.tos.emptyBody')" />
  </PublicContentPage>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicContentPage from '@/components/layout/PublicContentPage.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { localizedPublicContent } from '@/utils/publicSiteLocale'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'
import { plainToP } from '@/utils/siteHtmlMigrate'

const site = useSiteContentStore()
const { locale, t } = useI18n()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('tos'))
const localized = computed(() => localizedPublicContent(c.value, String(locale.value)))
const pageTitle = computed(() => String(localized.value.title ?? c.value.title ?? t('publicNav.tos')))

interface SecRow {
  id?: string
  heading: string
  bodyHtml?: string
  body?: string
}

const sections = computed((): SecRow[] => {
  const raw = localized.value.sections ?? c.value.sections
  if (!Array.isArray(raw)) return []
  return raw
    .map((row) => {
      if (typeof row !== 'object' || !row) return { heading: '', bodyHtml: '' }
      const o = row as Record<string, unknown>
      return {
        id: o.id != null ? String(o.id) : undefined,
        heading: String(o.heading ?? ''),
        bodyHtml: typeof o.bodyHtml === 'string' ? o.bodyHtml : undefined,
        body: typeof o.body === 'string' ? o.body : undefined,
      }
    })
    .filter((sec) => sec.heading.trim() || sec.bodyHtml?.trim() || sec.body?.trim())
})

function sectionHtml(sec: SecRow): string {
  const h = sec.bodyHtml?.trim()
  if (h) return sanitizePublicHtml(h)
  if (sec.body) return sanitizePublicHtml(plainToP(sec.body))
  return ''
}
</script>
