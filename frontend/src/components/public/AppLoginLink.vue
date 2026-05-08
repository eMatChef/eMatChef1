<template>
  <a
    :href="href"
    class="btn btn-primary"
    :target="useNewTab ? '_blank' : undefined"
    :rel="useNewTab ? 'noopener noreferrer' : undefined"
  >
    <slot>Login</slot>
  </a>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getAppLoginTarget } from '@/utils/appLoginUrl'

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
