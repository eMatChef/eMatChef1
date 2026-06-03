<template>
  <EButton
    :href="href"
    variant="primary"
    size="large"
    class="plt-btn-lg plt-nav-cta"
    :target="useNewTab ? '_blank' : undefined"
    :rel="useNewTab ? 'noopener noreferrer' : undefined"
  >
    <slot>{{ t('publicNav.login') }}</slot>
  </EButton>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import { getAppLoginTarget } from '@/utils/appLoginUrl'

const { t } = useI18n()

const props = withDefaults(
  defineProps<{
    /** Neuer Tab nur sinnvoll, wenn App auf anderer Origin liegt. */
    openInNewTab?: boolean
  }>(),
  { openInNewTab: true }
)

const href = computed(() => getAppLoginTarget())

const useNewTab = computed(() => {
  if (!props.openInNewTab || typeof window === 'undefined') return false
  try {
    return new URL(href.value).origin !== window.location.origin
  } catch {
    return props.openInNewTab
  }
})
</script>
