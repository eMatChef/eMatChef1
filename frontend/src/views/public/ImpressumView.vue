<template>
  <PublicContentPage variant="content">
    <template #hero>
      <p class="plt-subpage-kicker">{{ t('public.impressum.kicker') }}</p>
      <h1 class="plt-subpage-title">{{ pageTitle }}</h1>
      <p class="plt-subpage-lead">{{ t('public.impressum.lead') }}</p>
    </template>

    <div v-if="sections.length" class="plt-legal-list">
      <article v-for="(sec, i) in sections" :key="i" class="plt-legal-item">
        <h2 class="plt-legal-item-title">{{ sec.heading }}</h2>
        <div class="plt-legal-prose plt-legal-item-body" v-html="sanitizePublicHtml(sec.bodyHtml)" />
      </article>
    </div>
    <div v-else class="plt-legal-list">
      <article class="plt-legal-item">
        <h2 class="plt-legal-item-title">{{ fallbackProviderHeading }}</h2>
        <div class="plt-legal-prose plt-legal-item-body imp-pre">{{ company }}</div>
        <div class="plt-legal-prose plt-legal-item-body imp-pre">{{ contact }}</div>
      </article>
      <article class="plt-legal-item">
        <h2 class="plt-legal-item-title">{{ fallbackRepresentativeHeading }}</h2>
        <p class="plt-legal-prose plt-legal-item-body">{{ representative }}</p>
      </article>
      <article class="plt-legal-item">
        <h2 class="plt-legal-item-title">{{ fallbackLiabilityHeading }}</h2>
        <p class="plt-legal-prose plt-legal-item-body">{{ liability }}</p>
      </article>
    </div>
  </PublicContentPage>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicContentPage from '@/components/layout/PublicContentPage.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { localizedPublicContent } from '@/utils/publicSiteLocale'
import { sanitizePublicHtml } from '@/utils/sanitizeHtml'

const site = useSiteContentStore()
const { locale, t } = useI18n()
onMounted(() => {
  void site.ensureLoaded()
})

const c = computed(() => site.getContent('impressum'))
const localized = computed(() => localizedPublicContent(c.value, String(locale.value)))
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

const company = computed(
  () =>
    String(localized.value.company ?? c.value.company ?? '') +
    '\n' +
    String(localized.value.address ?? c.value.address ?? ''),
)
const contact = computed(() => String(localized.value.contact ?? c.value.contact ?? ''))
const representative = computed(() => String(localized.value.representative ?? c.value.representative ?? ''))
const liability = computed(() => String(localized.value.liability ?? c.value.liability ?? ''))
const fallbackProviderHeading = computed(() =>
  String(localized.value.fallbackProviderHeading ?? t('public.impressum.fallbackProviderHeading')),
)
const fallbackRepresentativeHeading = computed(() =>
  String(localized.value.fallbackRepresentativeHeading ?? t('public.impressum.fallbackRepresentativeHeading')),
)
const fallbackLiabilityHeading = computed(() =>
  String(localized.value.fallbackLiabilityHeading ?? t('public.impressum.fallbackLiabilityHeading')),
)
</script>

<style scoped>
.imp-pre {
  white-space: pre-wrap;
}
</style>
