<template>
  <header class="w-head">
    <div class="w-head__title">
      <EmcLogoMark v-if="showLogo" size="sm" />
      <div>
        <h1>{{ title }}</h1>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
    </div>
    <time v-if="showClock" class="w-head__clock" :datetime="iso">{{ clock }}</time>
  </header>
</template>

<script setup lang="ts">
import { displayNow } from '@/utils/displayClock'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'

/** Kopfzeile der Anzeige (Logo, Titel, Untertitel, Uhr) wie in den GA-Anzeigebeispielen; rein passiv. */
const props = defineProps<{ config: Record<string, unknown> }>()
const { locale } = useI18n()

const title = computed(() => String(props.config.title ?? ''))
const subtitle = computed(() => String(props.config.subtitle ?? ''))
const showLogo = computed(() => props.config.show_logo !== false)
const showClock = computed(() => props.config.show_clock !== false)

const now = ref(displayNow())
let timer: ReturnType<typeof setInterval> | undefined
const iso = computed(() => now.value.toISOString())
const clock = computed(() => now.value.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit', second: '2-digit' }))
onMounted(() => {
  timer = setInterval(() => {
    now.value = displayNow()
  }, 1000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.w-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1em;
  width: 100%;
  height: 100%;
  padding: 0 0.4em;
  box-sizing: border-box;
  overflow: hidden;
}
.w-head__title {
  display: flex;
  align-items: center;
  gap: 0.9em;
  min-width: 0;
}
.w-head h1 {
  margin: 0;
  font-size: 2.3em;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.w-head p {
  margin: 0.1em 0 0;
  font-size: 0.95em;
  color: #94a3b8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.w-head__clock {
  font-size: 2.2em;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
