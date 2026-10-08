<template>
  <v-menu v-if="clockStore.canTravel" v-model="open" :close-on-content-click="false" location="bottom end">
    <template #activator="{ props: activatorProps }">
      <button
        v-bind="activatorProps"
        type="button"
        class="clock-control"
        data-testid="clock-control"
        :title="t('layout.clock.title')"
        :aria-label="t('layout.clock.title')"
      >
        <v-icon icon="mdi-clock-outline" size="18" aria-hidden="true" />
        <span class="clock-control__time">{{ formatted }}</span>
        <span class="clock-control__mode">{{ modeLabel }}</span>
      </button>
    </template>
    <v-card min-width="280" class="clock-control__card">
      <v-card-text class="d-flex flex-column ga-3">
        <v-text-field v-model="dateInput" type="date" :label="t('layout.clock.date')" density="compact" hide-details />
        <v-text-field v-model="timeInput" type="time" :label="t('layout.clock.time')" density="compact" hide-details />
        <v-btn color="primary" :loading="busy" :disabled="!inputValid" @click="applyInput">
          {{ t('layout.clock.apply') }}
        </v-btn>
        <div class="d-flex ga-2">
          <v-btn size="small" variant="tonal" :disabled="busy" @click="shift(-HOUR_MS)">{{ t('layout.clock.minusHour') }}</v-btn>
          <v-btn size="small" variant="tonal" :disabled="busy" @click="shift(HOUR_MS)">{{ t('layout.clock.plusHour') }}</v-btn>
        </div>
        <div class="d-flex ga-2">
          <v-btn size="small" variant="tonal" :disabled="busy" @click="shift(-DAY_MS)">{{ t('layout.clock.minusDay') }}</v-btn>
          <v-btn size="small" variant="tonal" :disabled="busy" @click="shift(DAY_MS)">{{ t('layout.clock.plusDay') }}</v-btn>
        </div>
        <v-btn variant="text" :disabled="busy" @click="reset">{{ t('layout.clock.reset') }}</v-btn>
      </v-card-text>
    </v-card>
  </v-menu>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useBusinessClockStore } from '@/stores/businessClock'
import { useToast } from '@/composables/useToast'

const HOUR_MS = 3_600_000
const DAY_MS = 24 * HOUR_MS

const { t } = useI18n()
const authStore = useAuthStore()
const clockStore = useBusinessClockStore()
const toast = useToast()

const open = ref(false)
const busy = ref(false)
const dateInput = ref('')
const timeInput = ref('')

const pad = (n: number) => String(n).padStart(2, '0')

const formatted = computed(() => {
  const d = clockStore.displayNow
  return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
})

const modeLabel = computed(() =>
  clockStore.mode === 'demo' ? t('layout.clock.modeDemo') : t('layout.clock.modeDev'),
)

const inputValid = computed(() => dateInput.value !== '' && timeInput.value !== '')

function syncInputs() {
  const d = clockStore.displayNow
  dateInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
  timeInput.value = `${pad(d.getHours())}:${pad(d.getMinutes())}`
}

watch(open, (isOpen) => {
  if (isOpen) syncInputs()
})

// Die Department-ID dient nur zum Adressieren; der Server prüft Mitgliedschaft und Zeitreise-Recht.
watch(
  () => authStore.activeDepartmentId,
  (departmentId) => {
    void clockStore.load(departmentId)
  },
  { immediate: true },
)

async function run(action: () => Promise<void>) {
  busy.value = true
  try {
    await action()
    syncInputs()
  } catch {
    toast.error(t('layout.clock.error'))
  } finally {
    busy.value = false
  }
}

function applyInput() {
  const target = new Date(`${dateInput.value}T${timeInput.value}`)
  if (Number.isNaN(target.getTime())) return
  void run(() => clockStore.travelTo(target))
}

function shift(deltaMs: number) {
  void run(() => clockStore.shift(deltaMs))
}

function reset() {
  void run(() => clockStore.reset())
}
</script>

<style scoped>
.clock-control {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.2);
  border-radius: 16px;
  background: none;
  color: inherit;
  font-size: 0.8125rem;
  cursor: pointer;
  white-space: nowrap;
}

.clock-control__mode {
  font-weight: 600;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
  color: rgb(var(--v-theme-primary));
}
</style>
