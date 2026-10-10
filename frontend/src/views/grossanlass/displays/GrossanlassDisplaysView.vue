<template>
  <PageShell class="displays" :title="t('grossanlass.displays.title')" :subtitle="t('grossanlass.displays.subtitle')">
    <template #actions>
      <EButton variant="primary" @click="openEdit(null)"><v-icon icon="mdi-plus" start size="18" /> {{ t('grossanlass.displays.new') }}</EButton>
    </template>
    <div class="ds">
      <p class="ds__intro">
        {{ t('grossanlass.displays.intro') }}
        <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.displays.prototype') }}</v-chip>
      </p>
      <p class="ds__existing">
        <v-icon icon="mdi-information-outline" size="16" />
        {{ t('grossanlass.displays.existing') }}
        <router-link :to="`/${departmentId}/dept/settings/my-department/display-screens`">{{ t('grossanlass.displays.existingLink') }}</router-link>
      </p>

      <div class="ds__grid">
        <article v-for="display in displays" :key="display.id" class="screen">
          <header>
            <v-chip size="small" variant="flat" :color="TYPE_COLOR[display.type]">{{ t(`grossanlass.displays.type.${display.type}`) }}</v-chip>
            <v-chip v-if="display.rotate" size="x-small" variant="outlined" prepend-icon="mdi-autorenew">{{ t('grossanlass.displays.rotation', { sec: display.rotateSec }) }}</v-chip>
          </header>
          <h3>{{ display.name }}</h3>
          <p class="screen__meta"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ display.location }}</p>
          <p class="screen__meta">
            <v-icon icon="mdi-filter-outline" size="14" />
            {{ [display.filter.ressort, display.filter.bereich, display.filter.project].filter(Boolean).join(' › ') || t('grossanlass.displays.noFilter') }}
          </p>
          <footer>
            <EButton variant="primary" size="small" @click="open(display.id)"><v-icon icon="mdi-television" start size="16" /> {{ t('grossanlass.displays.open') }}</EButton>
            <EButton variant="secondary" size="small" @click="openEdit(display.id)">{{ t('grossanlass.verkauf.edit') }}</EButton>
            <EButton variant="text" size="small" @click="removeDisplay(display.id)">{{ t('common.delete') }}</EButton>
          </footer>
        </article>
      </div>

      <section class="ds__demo">
        <h3>{{ t('grossanlass.displays.simulate.title') }}</h3>
        <p>{{ t('grossanlass.displays.simulate.text') }}</p>
        <div class="ds__actions">
          <EButton variant="secondary" size="small" @click="simulatePack">{{ t('grossanlass.displays.simulate.pack') }}</EButton>
          <EButton variant="secondary" size="small" @click="simulateTrip">{{ t('grossanlass.displays.simulate.trip') }}</EButton>
          <span class="ds__trip">{{ tripLabel }}</span>
        </div>
        <div class="ds__tracking">
          <span>{{ t('grossanlass.displays.tracking.title') }}</span>
          <v-btn-toggle v-model="trackingValue" mandatory density="compact" color="primary" variant="outlined">
            <v-btn value="none" size="small">{{ t('grossanlass.displays.tracking.none') }}</v-btn>
            <v-btn value="eta" size="small">{{ t('grossanlass.displays.tracking.eta') }}</v-btn>
            <v-btn value="gps" size="small" disabled>{{ t('grossanlass.displays.tracking.gps') }}</v-btn>
          </v-btn-toggle>
        </div>
      </section>
    </div>

    <EDialog v-model="editOpen" :title="editId ? t('grossanlass.displays.edit') : t('grossanlass.displays.new')" max-width="560" :retain-focus="false">
      <div class="form">
        <ETextField v-model="form.name" :label="t('grossanlass.displays.f.name')" hide-details="auto" />
        <ESelect v-model="form.type" :items="typeItems" :label="t('grossanlass.displays.f.type')" hide-details />
        <ETextField v-model="form.location" :label="t('grossanlass.displays.f.location')" hide-details />
        <div class="form__grid">
          <ETextField v-model="form.ressort" :label="t('grossanlass.displays.f.ressort')" hide-details />
          <ETextField v-model="form.bereich" :label="t('grossanlass.displays.f.bereich')" hide-details />
          <ETextField v-model="form.project" :label="t('grossanlass.displays.f.project')" hide-details />
        </div>
        <ECheckbox v-model="form.rotate" :label="t('grossanlass.displays.f.rotate')" hide-details />
        <ETextField v-model.number="form.rotateSec" type="number" min="3" :label="t('grossanlass.displays.f.rotateSec')" hide-details />
      </div>
      <template #actions>
        <EButton variant="secondary" @click="editOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!form.name.trim()" @click="save">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import PageShell from '@/components/layout/PageShell.vue'
import { advanceRoute, startTask, taskById } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { GA_FAHRAUFTRAG_STEPS } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { createPalette, markPaletteReady, useGaPackenMock } from '@/views/grossanlass/packen/gaPackenMock'
import {
  displayById,
  removeDisplay,
  saveDisplay,
  setTracking,
  trackingOf,
  useGaLive,
  type GaDisplayType,
  type GaTrackingMode,
} from '@/views/grossanlass/live/gaLiveEvents'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { displays } = useGaLive()
const { palettes } = useGaPackenMock()

const departmentId = computed(() => String(route.params.departmentId || ''))
const TYPE_COLOR: Record<GaDisplayType, string> = { material: 'amber-darken-2', logistik: 'info', projekt: 'success', gesamt: 'primary' }
const typeItems = computed(() => (['material', 'logistik', 'projekt', 'gesamt'] as const).map((value) => ({ value, title: t(`grossanlass.displays.type.${value}`) })))

const editOpen = ref(false)
const editId = ref<string | null>(null)
const form = reactive({ name: '', type: 'gesamt' as GaDisplayType, location: '', ressort: '', bereich: '', project: '', rotate: false, rotateSec: 12 })

function openEdit(id: string | null) {
  editId.value = id
  const display = id ? displayById(id) : undefined
  Object.assign(form, {
    name: display?.name ?? '',
    type: display?.type ?? 'gesamt',
    location: display?.location ?? '',
    ressort: display?.filter.ressort ?? '',
    bereich: display?.filter.bereich ?? '',
    project: display?.filter.project ?? '',
    rotate: display?.rotate ?? false,
    rotateSec: display?.rotateSec ?? 12,
  })
  editOpen.value = true
}
function save() {
  saveDisplay({
    id: editId.value ?? undefined,
    name: form.name.trim(),
    type: form.type,
    location: form.location.trim(),
    filter: { ressort: form.ressort.trim(), bereich: form.bereich.trim(), project: form.project.trim() },
    rotate: form.rotate,
    rotateSec: Math.max(3, Number(form.rotateSec) || 12),
  })
  editOpen.value = false
}
function open(id: string) {
  void router.push(`/display-demo/${id}`)
}

// Demo-Aktionen, damit man die Displays live verändern kann (gleicher State wie die normalen Seiten)
const TRIP_ID = 'ga-demo-logistik-1'
const trackingValue = computed<GaTrackingMode>({
  get: () => trackingOf(TRIP_ID),
  set: (value) => setTracking(TRIP_ID, value),
})
const tripLabel = computed(() => {
  const task = taskById(TRIP_ID)
  const step = task ? GA_FAHRAUFTRAG_STEPS[task.routeStepIndex] : null
  return `${task?.title ?? ''}: ${step ? t(`grossanlass.aufgaben.route.steps.${step}`) : t('grossanlass.displays.logistik.notStarted')}`
})
function simulateTrip() {
  const task = taskById(TRIP_ID)
  if (!task) return
  if (task.status === 'open') startTask(task)
  else advanceRoute(task)
}
function simulatePack() {
  const open = palettes.value.find((row) => row.status === 'packing')
  if (open) {
    markPaletteReady(open)
    return
  }
  const created = createPalette('pp-crew-zelt', [{ lineId: 'pl-cz-schrauben', qty: 100 }]) ?? createPalette('pp-bar-west', [{ lineId: 'pl-bw-holz', qty: 14 }])
  if (created) markPaletteReady(created)
}
</script>

<style scoped>
.ds {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-bottom: 28px;
}
.ds__intro,
.ds__existing {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.ds__existing {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ds__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}
.screen {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.screen header {
  display: flex;
  justify-content: space-between;
  gap: 6px;
}
.screen h3 {
  margin: 0;
}
.screen__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.86rem;
  color: #475569;
}
.screen footer,
.ds__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}
.ds__demo {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border: 1px dashed #94a3b8;
  border-radius: 12px;
  background: #f8fafc;
}
.ds__demo h3,
.ds__demo p {
  margin: 0;
}
.ds__demo p {
  color: #64748b;
  font-size: 0.86rem;
}
.ds__trip {
  font-size: 0.86rem;
  color: #475569;
}
.ds__tracking {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  font-size: 0.86rem;
}
.form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.form__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
}
</style>
