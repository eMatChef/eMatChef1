<template>
  <div class="plt-page">
    <section class="plt-hero" aria-labelledby="landing-title">
      <div class="plt-hero__inner">
        <p v-if="landing.kicker" class="plt-kicker">{{ landing.kicker }}</p>
        <h1 v-if="landing.heroTitle" id="landing-title">{{ landing.heroTitle }}</h1>
        <p v-if="landing.heroSubtitle" class="plt-lead">{{ landing.heroSubtitle }}</p>
        <div v-if="showPrimaryCta || showSecondaryCta" class="plt-hero__actions">
          <EButton
            v-if="showPrimaryCta"
            variant="primary"
            size="large"
            class="plt-btn-lg"
            :href="primaryHref"
            :target="openPrimaryInNewTab ? '_blank' : undefined"
            :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
          >
            {{ primaryCtaLabel }}
          </EButton>
          <EButton
            v-if="showSecondaryCta && secondaryIsInternal"
            variant="secondary"
            size="large"
            class="plt-btn-lg"
            :to="landing.secondaryCtaPath"
          >
            {{ landing.secondaryCta }}
          </EButton>
          <EButton
            v-else-if="showSecondaryCta"
            variant="secondary"
            size="large"
            class="plt-btn-lg"
            :href="landing.secondaryCtaPath"
          >
            {{ landing.secondaryCta }}
          </EButton>
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
          <ECard
            v-for="(f, i) in landing.features"
            :key="i"
            variant="outlined"
            class="plt-feature-card"
          >
            <div class="plt-feature-card__body">
              <div class="plt-feature-card__icon" aria-hidden="true">
                <v-icon :icon="featureIcon(f.icon, i)" size="26" />
              </div>
              <h3>{{ f.title }}</h3>
              <p>{{ f.text }}</p>
            </div>
          </ECard>
        </div>
      </div>
    </section>

    <section v-if="showCta" class="plt-cta" aria-labelledby="section-cta">
      <div class="plt-container">
        <h2 v-if="landing.ctaTitleSrOnly" id="section-cta" class="sr-only">{{ landing.ctaTitleSrOnly }}</h2>
        <p v-if="landing.ctaText" class="plt-cta-text">
          <template v-if="ctaTextWithFaqLink">
            {{ ctaTextWithFaqLink.before }}<RouterLink class="plt-cta-faq-link" :to="faqLinkPath">{{
              ctaTextWithFaqLink.link
            }}</RouterLink>{{ ctaTextWithFaqLink.after }}
          </template>
          <template v-else>{{ landing.ctaText }}</template>
        </p>
        <EButton
          v-if="showPrimaryCta"
          variant="primary"
          size="large"
          class="plt-btn-lg"
          :href="primaryHref"
          :target="openPrimaryInNewTab ? '_blank' : undefined"
          :rel="openPrimaryInNewTab ? 'noopener noreferrer' : undefined"
        >
          {{ primaryCtaLabel }}
        </EButton>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import ECard from '@/components/form/base/ECard.vue'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAppEntryTarget, getAppLoginTarget } from '@/utils/appLoginUrl'
import { useAuthStore } from '@/stores/auth'
import { resolveLandingFeatureIcon } from '@/utils/landingFeatureIcons'
import { parseLandingCtaFaqLink, resolveLandingDisplay } from '@/utils/publicLanding'

function featureIcon(icon: string, index: number): string {
  return resolveLandingFeatureIcon(icon, index)
}

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
const faqLinkPath = computed(() => {
  const path = landing.value.secondaryCtaPath.trim()
  return path.startsWith('/') ? path : '/faq'
})
const ctaTextWithFaqLink = computed(() => parseLandingCtaFaqLink(landing.value.ctaText))
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

.plt-feature-card {
  height: 100%;
  border-radius: var(--plt-radius) !important;
  box-shadow: var(--plt-shadow);
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.plt-feature-card:hover {
  box-shadow: var(--plt-shadow-lg);
  border-color: var(--plt-accent-soft) !important;
}

.plt-feature-card__body {
  padding: 1.35rem 1.25rem 1.5rem;
}

.plt-feature-card__body h3 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--plt-text);
  margin: 0 0 0.4rem;
  line-height: 1.3;
}

.plt-feature-card__body p {
  font-size: 0.92rem;
  line-height: 1.55;
  color: var(--plt-text-soft);
  margin: 0;
}
</style>
