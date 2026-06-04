<template>
  <div class="page-ed">
    <header class="page-ed-head">
      <h1>{{ t('components.siteEditors.landing.title') }}</h1>
      <p v-if="updatedAt" class="meta">{{ t('components.siteEditors.lastSaved') }}: {{ formatDe(updatedAt) }}</p>
    </header>
    <p v-if="error" class="error">{{ error }}</p>

    <div class="locale-tabs" role="tablist" :aria-label="t('publicNav.language')">
      <button
        v-for="loc in LANDING_LOCALES"
        :key="loc"
        type="button"
        class="locale-tab"
        :class="{ active: activeLocale === loc }"
        :aria-selected="activeLocale === loc"
        @click="activeLocale = loc"
      >
        {{ loc.toUpperCase() }}
      </button>
    </div>

    <section class="ed-section">
      <h2 class="h2">{{ t('components.siteEditors.landing.heroSection') }}</h2>
      <label class="lbl" for="landing-kicker">{{ t('components.siteEditors.landing.kickerLabel') }}</label>
      <input id="landing-kicker" v-model="content.kicker" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-hero-title">{{ t('components.siteEditors.landing.heroTitleLabel') }}</label>
      <input id="landing-hero-title" v-model="content.heroTitle" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-hero-sub">{{ t('components.siteEditors.landing.heroSubtitleLabel') }}</label>
      <textarea
        id="landing-hero-sub"
        v-model="content.heroSubtitle"
        class="inp inp-area"
        rows="3"
        :disabled="saving"
      />
      <label class="lbl" for="landing-primary-cta">{{ t('components.siteEditors.landing.primaryCtaLabel') }}</label>
      <input id="landing-primary-cta" v-model="content.primaryCta" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-secondary-cta">{{ t('components.siteEditors.landing.secondaryCtaLabel') }}</label>
      <input id="landing-secondary-cta" v-model="content.secondaryCta" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-secondary-path">{{ t('components.siteEditors.landing.secondaryCtaPathLabel') }}</label>
      <input
        id="landing-secondary-path"
        v-model="content.secondaryCtaPath"
        type="text"
        class="inp"
        placeholder="/faq"
        :disabled="saving"
      />
    </section>

    <section class="ed-section">
      <h2 class="h2">{{ t('components.siteEditors.landing.introSection') }}</h2>
      <label class="lbl" for="landing-intro-title">{{ t('components.siteEditors.landing.introTitleLabel') }}</label>
      <input id="landing-intro-title" v-model="content.introTitle" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-intro-p1">{{ t('components.siteEditors.landing.introParagraph1Label') }}</label>
      <textarea
        id="landing-intro-p1"
        v-model="content.introParagraph1"
        class="inp inp-area"
        rows="4"
        :disabled="saving"
      />
      <label class="lbl" for="landing-intro-p2">{{ t('components.siteEditors.landing.introParagraph2Label') }}</label>
      <textarea
        id="landing-intro-p2"
        v-model="content.introParagraph2"
        class="inp inp-area"
        rows="4"
        :disabled="saving"
      />
    </section>

    <section class="ed-section">
      <div class="block-head">
        <h2 class="h2">{{ t('components.siteEditors.landing.featuresSection') }}</h2>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="saving" @click="addFeature">
          {{ t('components.siteEditors.landing.addFeature') }}
        </button>
      </div>
      <label class="lbl" for="landing-features-title">{{ t('components.siteEditors.landing.featuresTitleLabel') }}</label>
      <input id="landing-features-title" v-model="content.featuresTitle" type="text" class="inp" :disabled="saving" />

      <div v-for="(feat, idx) in content.features" :key="idx" class="feature-item">
        <label class="lbl" :for="'landing-feat-icon-' + idx">{{ t('components.siteEditors.landing.featureIconLabel') }}</label>
        <input
          :id="'landing-feat-icon-' + idx"
          v-model="feat.icon"
          type="text"
          class="inp inp-icon"
          maxlength="48"
          placeholder="mdi-clipboard-check-outline"
          :disabled="saving"
        />
        <label class="lbl" :for="'landing-feat-title-' + idx">{{ t('components.siteEditors.landing.featureTitleLabel') }}</label>
        <input :id="'landing-feat-title-' + idx" v-model="feat.title" type="text" class="inp" :disabled="saving" />
        <label class="lbl" :for="'landing-feat-text-' + idx">{{ t('components.siteEditors.landing.featureTextLabel') }}</label>
        <textarea
          :id="'landing-feat-text-' + idx"
          v-model="feat.text"
          class="inp inp-area"
          rows="2"
          :disabled="saving"
        />
        <button type="button" class="btn-remove" :disabled="saving" @click="removeFeature(idx)">
          {{ t('components.siteEditors.landing.removeFeature') }}
        </button>
      </div>
    </section>

    <section class="ed-section">
      <h2 class="h2">{{ t('components.siteEditors.landing.ctaSection') }}</h2>
      <label class="lbl" for="landing-cta-sr">{{ t('components.siteEditors.landing.ctaTitleSrOnlyLabel') }}</label>
      <input id="landing-cta-sr" v-model="content.ctaTitleSrOnly" type="text" class="inp" :disabled="saving" />
      <label class="lbl" for="landing-cta-text">{{ t('components.siteEditors.landing.ctaTextLabel') }}</label>
      <textarea id="landing-cta-text" v-model="content.ctaText" class="inp inp-area" rows="2" :disabled="saving" />
    </section>

    <div class="actions">
      <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
        {{ saving ? t('components.siteEditors.saving') : t('common.save') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSiteContentStore } from '@/stores/siteContent'
import { getAdminSitePage, putAdminSitePage } from '@/api/sitePages'
import {
  LANDING_LOCALES,
  DEFAULT_FEATURE_ICONS,
  type LandingLocale,
  buildLandingSavePayload,
  normalizeLandingContent,
  type LandingLocaleContent,
} from '@/utils/publicLanding'

const siteContent = useSiteContentStore()
const { t } = useI18n()
const activeLocale = ref<LandingLocale>('de')
const localeContent = ref<Record<LandingLocale, LandingLocaleContent>>(normalizeLandingContent({}))
const updatedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

function formatDe(iso: string): string {
  try {
    return new Intl.DateTimeFormat('de-CH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}

async function load() {
  error.value = null
  try {
    const data = await getAdminSitePage('landing')
    localeContent.value = normalizeLandingContent(data.content as Record<string, unknown>)
    updatedAt.value = data.updatedAt
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.loadFailed')
  }
}

async function save() {
  error.value = null
  saving.value = true
  try {
    const payload = buildLandingSavePayload(localeContent.value)
    const data = await putAdminSitePage('landing', payload)
    localeContent.value = normalizeLandingContent(data.content as Record<string, unknown>)
    updatedAt.value = data.updatedAt
    void siteContent.refresh()
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('components.siteEditors.saveFailed')
  } finally {
    saving.value = false
  }
}

function addFeature() {
  const list = localeContent.value[activeLocale.value].features
  const icon = DEFAULT_FEATURE_ICONS[list.length % DEFAULT_FEATURE_ICONS.length]
  list.push({ icon, title: '', text: '' })
}

function removeFeature(idx: number) {
  const list = localeContent.value[activeLocale.value].features
  if (list.length <= 1) {
    localeContent.value[activeLocale.value].features = [
      { icon: DEFAULT_FEATURE_ICONS[0], title: '', text: '' },
    ]
    return
  }
  list.splice(idx, 1)
}

const content = computed(() => localeContent.value[activeLocale.value])

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page-ed {
  max-width: 52rem;
}

.page-ed-head h1 {
  font-size: 1.35rem;
  margin: 0 0 0.25rem;
}

.meta {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0 0 1rem;
}

.error {
  color: #b91c1c;
  margin-bottom: 0.75rem;
}

.locale-tabs {
  display: inline-flex;
  gap: 0.35rem;
  padding: 0.2rem;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #f8fafc;
  margin-bottom: 0.75rem;
}

.locale-tab {
  border: none;
  border-radius: 999px;
  background: transparent;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 0.28rem 0.62rem;
  cursor: pointer;
}

.locale-tab.active {
  background: #0f172a;
  color: #fff;
}

.ed-section {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px solid #e2e8f0;
}

.ed-section:first-of-type {
  border-top: none;
  padding-top: 0;
  margin-top: 0.5rem;
}

.lbl {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  margin: 0.75rem 0 0.35rem;
}

.inp {
  width: 100%;
  max-width: 40rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  font-family: inherit;
}

.inp-area {
  resize: vertical;
  min-height: 4rem;
}

.inp-icon {
  max-width: 6rem;
  font-size: 1.25rem;
  text-align: center;
}

.block-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.25rem;
}

.h2 {
  font-size: 1.1rem;
  margin: 0;
}

.btn-sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.85rem;
}

.feature-item {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fafafa;
}

.btn-remove {
  margin-top: 0.75rem;
  padding: 0.35rem 0.65rem;
  font-size: 0.85rem;
  border: none;
  background: transparent;
  color: #b91c1c;
  cursor: pointer;
  font-weight: 600;
}

.btn-remove:hover {
  text-decoration: underline;
}

.actions {
  margin-top: 1.5rem;
}
</style>
