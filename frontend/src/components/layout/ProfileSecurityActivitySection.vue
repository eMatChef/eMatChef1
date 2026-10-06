<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-activity">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.activity.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.activity.hint') }}</p>

    <p v-if="loadError" class="text-[0.85rem] text-red-700">{{ loadError }}</p>
    <p v-else-if="loaded && events.length === 0" class="text-[0.82rem] text-slate-500" data-testid="no-events">
      {{ t('layout.profileModal.activity.empty') }}
    </p>
    <ul v-else class="flex flex-col gap-1" data-testid="events">
      <li
        v-for="(e, i) in events"
        :key="i"
        class="flex flex-wrap items-baseline justify-between gap-2 rounded border border-slate-100 px-3 py-1.5 text-[0.82rem]"
      >
        <span class="text-slate-800">{{ eventLabel(e) }}</span>
        <time class="text-[0.75rem] text-slate-500" :datetime="e.created_at">{{ formatDate(e.created_at) }}</time>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getSecurityActivity, type SecurityActivityEvent } from '@/api/profileSecurity'

const props = defineProps<{ open?: boolean }>()

const { t, te, locale } = useI18n()
const authStore = useAuthStore()

const events = ref<SecurityActivityEvent[]>([])
const loadError = ref('')
const loaded = ref(false)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
  },
  { immediate: true },
)

function formatDate(value: string): string {
  return new Date(value).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'short' })
}

function eventLabel(e: SecurityActivityEvent): string {
  const key = `layout.profileModal.activity.events.${e.action}`
  const base = te(key) ? t(key) : e.action
  return e.detail ? `${base} (${e.detail})` : base
}

async function load() {
  const id = authStore.profileId || authStore.profile?.id || ''
  if (!id) return
  loadError.value = ''
  try {
    events.value = await getSecurityActivity(id, 20)
    loaded.value = true
  } catch {
    loadError.value = t('layout.profileModal.activity.loadError')
  }
}
</script>
