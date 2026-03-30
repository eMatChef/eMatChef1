<template>
  <div class="plt-shell">
    <header class="plt-header" role="banner">
      <div class="plt-header-inner">
        <RouterLink to="/" class="plt-brand public-brand" title="eMatChef">
          <EmcLogoMark size="sm" />
          <span class="plt-brand-text public-brand-text">eMatChef</span>
        </RouterLink>
        <nav class="plt-nav" aria-label="Hauptnavigation">
          <RouterLink to="/blog">Blog</RouterLink>
          <RouterLink to="/faq">FAQ</RouterLink>
          <RouterLink to="/tos">Nutzung &amp; Datenschutz</RouterLink>
        </nav>
        <AppLoginLink class="plt-nav-cta btn btn-primary plt-btn-lg">Login</AppLoginLink>
      </div>
    </header>
    <main class="plt-main">
      <RouterView />
    </main>
    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import AppLoginLink from '@/components/public/AppLoginLink.vue'
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue'
</script>
