<template>
  <div class="plt-shell">
    <header class="plt-header" role="banner">
      <div class="plt-header-inner">
        <RouterLink to="/" class="plt-brand public-brand" :title="t('publicNav.brandTitle')">
          <EmcLogoMark size="sm" />
          <span class="plt-brand-text public-brand-text">eMatChef</span>
        </RouterLink>
        <nav class="plt-nav" :aria-label="t('publicNav.mainAria')">
          <RouterLink to="/blog">{{ t('publicNav.blog') }}</RouterLink>
          <RouterLink to="/faq">{{ t('publicNav.faq') }}</RouterLink>
          <RouterLink to="/tos">{{ t('publicNav.tos') }}</RouterLink>
          <select v-model="publicLocale" class="public-locale-select" :aria-label="t('publicNav.language')">
            <option value="de">DE</option>
            <option value="en">EN</option>
            <option value="fr">FR</option>
          </select>
        </nav>
        <AppLoginLink class="plt-nav-cta btn btn-primary plt-btn-lg">{{ t('publicNav.login') }}</AppLoginLink>
      </div>
    </header>
    <main class="plt-main">
      <RouterView />
    </main>
    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import AppLoginLink from '@/components/public/AppLoginLink.vue'
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue'
import { setLocale } from '@/i18n'

const { t, locale } = useI18n()

function toPublicLocale(value: string): 'de' | 'en' | 'fr' {
  const v = String(value || '').toLowerCase()
  if (v.startsWith('en')) return 'en'
  if (v.startsWith('fr')) return 'fr'
  return 'de'
}

const publicLocale = computed({
  get: () => toPublicLocale(String(locale.value)),
  set: (v: string) => {
    setLocale(toPublicLocale(v))
  },
})
</script>

<style scoped>
.public-locale-select {
  min-width: 4.25rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.3rem 0.45rem;
}
</style>
