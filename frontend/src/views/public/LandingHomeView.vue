<template>
  <div class="plt-page">
    <section class="plt-hero" aria-labelledby="landing-title">
      <div class="plt-hero__inner">
        <p v-if="landing.kicker" class="plt-kicker">{{ landing.kicker }}</p>
        <h1 v-if="landing.heroTitle" id="landing-title">{{ landing.heroTitle }}</h1>
        <p v-if="landing.heroSubtitle" class="plt-lead">{{ landing.heroSubtitle }}</p>
        <div v-if="showPrimaryCta || showSecondaryCta" class="plt-hero__actions">
          <a
            v-if="showPrimaryCta"
            :href="primaryHref"
            :target="openPrimaryInNewTab ? '_blank' : undefined"
            :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
            class="btn btn-primary plt-btn-lg"
          >
            {{ primaryCtaLabel }}
          </a>
          <RouterLink
            v-if="showSecondaryCta && secondaryIsInternal"
            :to="landing.secondaryCtaPath"
            class="btn btn-outline plt-btn-lg"
          >
            {{ landing.secondaryCta }}
          </RouterLink>
          <a
            v-else-if="showSecondaryCta"
            :href="landing.secondaryCtaPath"
            class="btn btn-outline plt-btn-lg"
          >
            {{ landing.secondaryCta }}
          </a>
        </div>
      </div>
    </section>

    <section
      v-if="showIntro"
      class="plt-section plt-section--alt"
      aria-labelledby="section-intro"
    >
      <div class="plt-container">
        <h2 v-if="landing.introTitle" id="section-intro">{{ landing.introTitle }}</h2>
        <div class="plt-prose">
          <p v-if="landing.introParagraph1">{{ landing.introParagraph1 }}</p>
          <p v-if="landing.introParagraph2">{{ landing.introParagraph2 }}</p>
        </div>
      </div>
    </section>

    <section
      v-if="landing.features.length"
      class="plt-section"
      aria-labelledby="section-features"
    >
      <div class="plt-container">
        <h2 v-if="landing.featuresTitle" id="section-features">{{ landing.featuresTitle }}</h2>
        <div class="plt-features">
          <article v-for="(f, i) in landing.features" :key="i" class="plt-feature-card">
            <div class="plt-feature-card__icon" aria-hidden="true">{{ f.icon }}</div>
            <h3>{{ f.title }}</h3>
            <p>{{ f.text }}</p>
          </article>
        </div>
      </div>
    </section>

    <section v-if="showCta" class="plt-cta" aria-labelledby="section-cta">
      <div class="plt-container">
        <h2 v-if="landing.ctaTitleSrOnly" id="section-cta" class="sr-only">{{ landing.ctaTitleSrOnly }}</h2>
        <p v-if="landing.ctaText">{{ landing.ctaText }}</p>
        <a
          v-if="showPrimaryCta"
          :href="primaryHref"
          :target="openPrimaryInNewTab ? '_blank' : undefined"
          :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
          class="btn btn-primary plt-btn-lg"
        >
          {{ primaryCtaLabel }}
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
import { resolveLandingDisplay } from '@/utils/publicLanding'

const site = useSiteContentStore()
const { t, locale } = useI18n()
const authStore = useAuthStore()

onMounted(() => {
  void site.ensureLoaded()
  void authStore.loadUserSessionFromCookie()
})

const landing = computed(() =>
  resolveLandingDisplay(site.getContent('landing'), String(locale.value)),
)

const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const primaryCtaLabel = computed(() =>
  isPublicLoggedIn.value ? t('public.lookup.toApp') : landing.value.primaryCta,
)
const showPrimaryCta = computed(
  () => isPublicLoggedIn.value || Boolean(landing.value.primaryCta),
)
const showSecondaryCta = computed(
  () => Boolean(landing.value.secondaryCta && landing.value.secondaryCtaPath),
)
const showIntro = computed(
  () =>
    Boolean(
      landing.value.introTitle ||
        landing.value.introParagraph1 ||
        landing.value.introParagraph2,
    ),
)
const showCta = computed(() => Boolean(landing.value.ctaText || showPrimaryCta.value))
const primaryHref = computed(() =>
  isPublicLoggedIn.value ? getAppEntryTarget() : getAppLoginTarget(),
)
const openPrimaryInNewTab = computed(() => {
  if (typeof window === 'undefined') return false
  try {
    return new URL(primaryHref.value).origin !== window.location.origin
  } catch {
    return false
  }
})

const secondaryIsInternal = computed(() => landing.value.secondaryCtaPath.startsWith('/'))
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
