<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-activity">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.activity.title') }}</h4>
    <p class="mb-1 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.activity.hint') }}</p>
    <p class="mb-3 text-[0.75rem] text-slate-400">{{ t('layout.profileModal.activity.retention', { days: RETENTION_DAYS }) }}</p>

    <p v-if="loadError" class="mb-2 text-[0.85rem] text-red-700" data-testid="load-error">{{ loadError }}</p>
    <p v-if="loaded && events.length === 0 && !loadError" class="text-[0.82rem] text-slate-500" data-testid="no-events">
      {{ t('layout.profileModal.activity.empty') }}
    </p>
    <!-- Genau 5 Zeilen sichtbar (5 × 3rem + 4 × 0.25rem = 16rem), der Rest scrollt in der Liste -->
    <ul
      v-else-if="events.length > 0"
      class="flex max-h-64 flex-col gap-1 overflow-y-auto overscroll-contain pr-1"
      data-testid="events"
      tabindex="0"
      :aria-label="t('layout.profileModal.activity.title')"
      @scroll.passive="onScroll"
    >
      <li
        v-for="e in events"
        :key="e.id"
        class="h-12 shrink-0 overflow-hidden rounded border border-slate-100 px-3 py-1 text-[0.82rem]"
      >
        <div class="flex items-baseline justify-between gap-2">
          <span class="truncate text-slate-800">{{ eventLabel(e) }}</span>
          <time class="shrink-0 text-[0.75rem] text-slate-500" :datetime="e.created_at">{{ formatDate(e.created_at) }}</time>
        </div>
        <p
          v-if="metaParts(e).length"
          class="mt-0.5 truncate text-[0.75rem] text-slate-500"
          :title="metaParts(e).join(' · ')"
          data-testid="event-meta"
        >
          {{ metaParts(e).join(' · ') }}
        </p>
      </li>
    </ul>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <EButton
        v-if="nextCursor"
        variant="text"
        size="small"
        :loading="loading"
        :disabled="loading"
        data-testid="load-more"
        @click="load(true)"
      >
        {{ t('layout.profileModal.activity.loadMore') }}
      </EButton>
      <EButton variant="secondary" size="small" type="button" data-testid="open-full-log" @click="logOpen = true">
        {{ t('layout.profileModal.activity.fullLog') }}
      </EButton>
    </div>

    <ProfileSecurityLogModal v-model="logOpen" />
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import ProfileSecurityLogModal from '@/components/layout/ProfileSecurityLogModal.vue'
import { useSecurityActivityFormat } from '@/components/layout/useSecurityActivityFormat'
import { useAuthStore } from '@/stores/auth'
import { getSecurityActivity, type SecurityActivityEvent } from '@/api/profileSecurity'

const props = defineProps<{ open?: boolean }>()

/** Muss zu SecurityActivityService::CONTEXT_RETENTION_DAYS passen (nur Hinweistext). */
const RETENTION_DAYS = 90
const PAGE_SIZE = 20

const { t } = useI18n()
const { formatDate, eventLabel, metaParts } = useSecurityActivityFormat()
const authStore = useAuthStore()

const events = ref<SecurityActivityEvent[]>([])
const nextCursor = ref<string | null>(null)
const loadError = ref('')
const loaded = ref(false)
const loading = ref(false)
const logOpen = ref(false)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load(false)
  },
  { immediate: true },
)

/** Kurz vor dem Listenende automatisch die nächste Seite holen (Cursor). */
function onScroll(event: Event) {
  const el = event.target as HTMLElement
  if (nextCursor.value && !loading.value && el.scrollTop + el.clientHeight >= el.scrollHeight - 24) {
    void load(true)
  }
}

async function load(more: boolean) {
  const id = authStore.profileId || authStore.profile?.id || ''
  if (!id || loading.value) return
  loadError.value = ''
  loading.value = true
  try {
    const page = await getSecurityActivity(id, PAGE_SIZE, more ? nextCursor.value : null)
    events.value = more ? [...events.value, ...page.events] : page.events
    nextCursor.value = page.next_cursor
    loaded.value = true
  } catch {
    loadError.value = t('layout.profileModal.activity.loadError')
  } finally {
    loading.value = false
  }
}
</script>
