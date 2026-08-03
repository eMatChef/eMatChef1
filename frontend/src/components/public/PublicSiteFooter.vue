<template>
  <footer class="plt-footer" role="contentinfo">
    <p class="plt-footer-copy">
      <span>© {{ copyrightYear }}</span>
      <span class="plt-footer-sep" aria-hidden="true">·</span>
      <a class="plt-footer-status" :href="statusPageUrl" target="_blank" rel="noopener noreferrer">
        {{ t('public.footer.status') }}
      </a>
    </p>
  </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const copyrightYear = computed(() => new Date().getFullYear())

const statusPageUrl = computed(() => {
  const configured = (import.meta.env.VITE_STATUS_PAGE_URL || '').trim()
  return configured || 'https://status.ematchef.ch'
})
</script>
