<template>
  <div class="plt-page">
    <section class="plt-hero" aria-labelledby="landing-title">
      <div class="plt-hero__inner">
        <p class="plt-kicker">{{ t('public.landing.kicker') }}</p>
        <h1 id="landing-title">{{ heroTitle }}</h1>
        <p class="plt-lead">{{ heroSubtitle }}</p>
        <div class="plt-hero__actions">
          <a
            :href="primaryHref"
            :target="openPrimaryInNewTab ? '_blank' : undefined"
            :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
            class="btn btn-primary plt-btn-lg"
          >
            {{ primaryCta }}
          </a>
          <RouterLink to="/faq" class="btn btn-outline plt-btn-lg">{{ secondaryCta }}</RouterLink>
        </div>
      </div>
    </section>

    <section class="plt-section plt-section--alt" aria-labelledby="section-intro">
      <div class="plt-container">
        <h2 id="section-intro">{{ t('public.landing.intro.title') }}</h2>
        <div class="plt-prose">
          <p>{{ t('public.landing.intro.paragraph1') }}</p>
          <p>{{ t('public.landing.intro.paragraph2') }}</p>
        </div>
      </div>
    </section>

    <section class="plt-section" aria-labelledby="section-features">
      <div class="plt-container">
        <h2 id="section-features">{{ t('public.landing.features.title') }}</h2>
        <div class="plt-features">
          <article v-for="(f, i) in features" :key="i" class="plt-feature-card">
            <div class="plt-feature-card__icon" aria-hidden="true">{{ f.icon }}</div>
            <h3>{{ f.title }}</h3>
            <p>{{ f.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="plt-cta" aria-labelledby="section-cta">
      <div class="plt-container">
        <h2 id="section-cta" class="sr-only">{{ t('public.landing.cta.titleSrOnly') }}</h2>
        <p>{{ t('public.landing.cta.text') }}</p>
        <a
          :href="primaryHref"
          :target="openPrimaryInNewTab ? '_blank' : undefined"
          :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
          class="btn btn-primary plt-btn-lg"
        >
          {{ primaryCta }}
        </a>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAppEntryTarget, getAppLoginTarget } from '@/utils/appLoginUrl'
import { useAuthStore } from '@/stores/auth'

const site = useSiteContentStore()
const { t } = useI18n()
const authStore = useAuthStore()

onMounted(() => {
  void site.ensureLoaded()
  void authStore.loadUserSessionFromCookie()
})

const heroTitle = computed(() =>
  String(site.getContent('landing').heroTitle ?? t('public.landing.heroTitle'))
)
const heroSubtitle = computed(() =>
  String(
    site.getContent('landing').heroSubtitle ??
      t('public.landing.heroSubtitle')
  )
)
const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const primaryCta = computed(() =>
  isPublicLoggedIn.value
    ? t('public.lookup.toApp')
    : String(site.getContent('landing').primaryCta ?? t('public.landing.primaryCta'))
)
const primaryHref = computed(() =>
  isPublicLoggedIn.value ? getAppEntryTarget() : getAppLoginTarget()
)
const openPrimaryInNewTab = computed(() => {
  if (typeof window === 'undefined') return false
  try {
    return new URL(primaryHref.value).origin !== window.location.origin
  } catch {
    return false
  }
})
const secondaryCta = computed(() =>
  String(site.getContent('landing').secondaryCta ?? t('public.landing.secondaryCta'))
)

const featureIcons = ['⊙', '⌗', '◎', '⇄', '◇', '○'] as const
const features = computed(() =>
  featureIcons.map((icon, index) => ({
    icon,
    title: t(`public.landing.features.items.${index}.title`),
    text: t(`public.landing.features.items.${index}.text`),
  }))
)
</script>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
