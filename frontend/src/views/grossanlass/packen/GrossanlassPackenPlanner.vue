<template>
  <div class="packen">
    <p class="packen__intro">
      {{ t('grossanlass.packen.intro') }}
      <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.packen.prototype') }}</v-chip>
    </p>

    <section class="packen__kpis">
      <button
        v-for="kpi in kpis"
        :key="kpi.key"
        type="button"
        class="pk-kpi"
        :class="[`pk-kpi--${kpi.key}`, { 'pk-kpi--active': statusFilter === kpi.key }]"
        @click="statusFilter = statusFilter === kpi.key ? 'all' : kpi.key"
      >
        <strong>{{ kpi.count }}</strong>
        <span>{{ t(`grossanlass.packen.status.${kpi.key}`) }}</span>
      </button>
    </section>

    <EEmptyState
      v-if="!visibleProjects.length"
      variant="generic"
      icon="mdi-package-variant"
      :title="t('grossanlass.packen.emptyTitle')"
      :description="t('grossanlass.packen.emptyText')"
    />

    <section class="packen__projects">
      <article v-for="project in visibleProjects" :key="project.id" class="pk-project" :class="`pk-project--${projectStatus(project)}`">
        <header class="pk-project__head">
          <div>
            <p class="pk-project__origin">{{ project.ressort }} · {{ project.bereich }}</p>
            <h3>{{ project.name }}</h3>
          </div>
          <v-chip size="small" variant="flat" :color="STATUS_COLOR[projectStatus(project)]">
            {{ t(`grossanlass.packen.status.${projectStatus(project)}`) }}
          </v-chip>
        </header>

        <div class="pk-project__progress">
          <v-progress-linear :model-value="projectTotals(project).progress" height="10" rounded color="primary" />
          <span>{{ t('grossanlass.packen.progress', { packed: projectTotals(project).packed, needed: projectTotals(project).needed }) }} · {{ projectTotals(project).progress }} %</span>
        </div>

        <div class="pk-table" role="table">
          <div class="pk-table__row pk-table__row--head" role="row">
            <span>{{ t('grossanlass.packen.col.need') }}</span>
            <span>{{ t('grossanlass.packen.col.needed') }}</span>
            <span>{{ t('grossanlass.packen.col.available') }}</span>
            <span>{{ t('grossanlass.packen.col.packed') }}</span>
            <span>{{ t('grossanlass.packen.col.missing') }}</span>
            <span>{{ t('grossanlass.packen.col.packNow') }}</span>
          </div>
          <div v-for="line in project.lines" :key="line.id" class="pk-table__row" role="row">
            <span class="pk-table__name" data-label="">{{ line.label }}</span>
            <span :data-label="t('grossanlass.packen.col.needed')">{{ line.needed }}</span>
            <span :data-label="t('grossanlass.packen.col.available')">{{ line.available }}</span>
            <span :data-label="t('grossanlass.packen.col.packed')">{{ line.packed }}</span>
            <span :class="{ 'pk-missing': lineMissing(line) > 0 }" :data-label="t('grossanlass.packen.col.missing')">
              {{ lineMissing(line) }}
            </span>
            <span class="pk-table__pick" :data-label="t('grossanlass.packen.col.packNow')">
              <template v-if="linePackableNow(line) > 0">
                <ECheckbox
                  :model-value="isPicked(project.id, line.id)"
                  hide-details
                  @update:model-value="togglePick(project, line, Boolean($event))"
                />
                <input
                  class="pk-qty"
                  type="number"
                  min="1"
                  :max="linePackableNow(line)"
                  :value="pickQty(project.id, line)"
                  :disabled="!isPicked(project.id, line.id)"
                  :aria-label="t('grossanlass.packen.col.packNow')"
                  @input="setQty(project.id, line, ($event.target as HTMLInputElement).value)"
                >
              </template>
              <span v-else class="pk-none">–</span>
            </span>
          </div>
        </div>

        <footer class="pk-project__foot">
          <p v-if="packableLines(project).length" class="pk-now">
            <v-icon icon="mdi-lightbulb-on-outline" size="16" />
            {{ t('grossanlass.packen.nowHint') }}
            <strong>{{ packableLines(project).map((line) => `${linePackableNow(line)}× ${line.label}`).join(', ') }}</strong>
          </p>
          <p v-else-if="projectStatus(project) === 'waiting'" class="pk-wait">
            <v-icon icon="mdi-timer-sand" size="16" /> {{ t('grossanlass.packen.waitingHint') }}
          </p>
          <div v-if="packableLines(project).length" class="pk-project__actions">
            <ESelect
              v-model="targets[project.id]"
              :items="GA_PACK_TARGETS"
              :label="t('grossanlass.packen.target')"
              hide-details
            />
            <EButton variant="secondary" size="large" @click="selectAll(project)">
              {{ t('grossanlass.packen.selectAll') }}
            </EButton>
            <EButton variant="primary" size="large" :disabled="!pickedDraft(project).length" @click="pack(project)">
              <v-icon icon="mdi-package-variant-closed-plus" start size="20" />
              {{ t('grossanlass.packen.packNow') }}
            </EButton>
          </div>
        </footer>
      </article>
    </section>

    <section v-if="palettes.length" class="packen__palettes">
      <h3 class="packen__section">{{ t('grossanlass.packen.palettes') }}</h3>
      <div class="pk-palettes">
        <article v-for="palette in palettes" :key="palette.id" class="pk-palette">
          <header class="pk-palette__head">
            <div>
              <strong>{{ t('grossanlass.packen.paletteNo', { n: palette.number }) }}</strong>
              <span class="pk-palette__code">{{ palette.code }}</span>
            </div>
            <v-chip size="x-small" variant="flat" :color="PALETTE_COLOR[palette.status]">
              {{ t(`grossanlass.packen.paletteStatus.${palette.status}`) }}
            </v-chip>
          </header>
          <h4>{{ palette.title }}</h4>
          <p class="pk-palette__meta">{{ palette.ressort }} · {{ palette.projectName }}</p>
          <p class="pk-palette__meta"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ palette.target }}</p>
          <ul class="pk-palette__lines">
            <li v-for="line in palette.lines" :key="line.label">{{ line.qty }}× {{ line.label }}</li>
          </ul>
          <div class="pk-palette__actions">
            <EButton
              v-if="palette.status === 'packing'"
              variant="primary"
              size="large"
              @click="markPaletteReady(palette)"
            >
              <v-icon icon="mdi-check-circle-outline" start size="20" />
              {{ t('grossanlass.packen.ready') }}
            </EButton>
            <EButton variant="secondary" size="large" @click="openLabel(palette)">
              <v-icon icon="mdi-printer" start size="20" />
              {{ t('grossanlass.packen.printLabel') }}
            </EButton>
            <EButton
              v-if="palette.status === 'ready'"
              variant="primary"
              size="large"
              @click="send(palette)"
            >
              <v-icon icon="mdi-truck-fast-outline" start size="20" />
              {{ t('grossanlass.packen.sendFahrauftrag') }}
            </EButton>
            <EButton
              v-if="palette.status === 'sent' || palette.status === 'delivered'"
              variant="secondary"
              size="large"
              @click="openDriver(palette)"
            >
              <v-icon icon="mdi-cellphone" start size="20" />
              {{ t('grossanlass.packen.driverDemo') }}
            </EButton>
          </div>
          <p v-if="palette.status === 'sent'" class="pk-palette__sent">{{ t('grossanlass.packen.sentNote') }}</p>
        </article>
      </div>
    </section>

    <GrossanlassLabelPrintDialog
      v-model="labelOpen"
      :code="labelPalette?.code ?? ''"
      :title="labelPalette?.title ?? ''"
      :lines="labelPalette ? [`${labelPalette.ressort} · ${labelPalette.projectName}`, labelPalette.target, paletteSummary(labelPalette)] : []"
      @printed="labelPalette && markLabelPrinted(labelPalette)"
    />
    <GrossanlassFahrerDemo v-model="driverOpen" :palette="driverPalette" />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassFahrerDemo from './GrossanlassFahrerDemo.vue'
import GrossanlassLabelPrintDialog from './GrossanlassLabelPrintDialog.vue'
import {
  GA_PACK_TARGETS,
  createPalette,
  lineMissing,
  linePackableNow,
  markLabelPrinted,
  markPaletteReady,
  packStatusCounts,
  packableLines,
  paletteSummary,
  projectStatus,
  projectTotals,
  sendFahrauftrag,
  suggestedPaletteTitle,
  useGaPackenMock,
  type GaPackNeedLine,
  type GaPackProject,
  type GaPackStatus,
  type GaPalette,
  type GaPaletteStatus,
} from './gaPackenMock'

const { t } = useI18n()
const toast = useToast()
const { projects, palettes } = useGaPackenMock()

const STATUS_COLOR: Record<GaPackStatus, string> = {
  packable: 'success',
  partial: 'warning',
  waiting: 'grey',
  done: 'primary',
}
const PALETTE_COLOR: Record<GaPaletteStatus, string> = {
  packing: 'warning',
  ready: 'success',
  sent: 'primary',
  delivered: 'grey',
}

const statusFilter = ref<'all' | GaPackStatus>('all')
const counts = computed(() => packStatusCounts(projects.value))
const kpis = computed(() => (['packable', 'partial', 'waiting', 'done'] as const).map((key) => ({ key, count: counts.value[key] })))
const visibleProjects = computed(() =>
  projects.value.filter((project) => statusFilter.value === 'all' || projectStatus(project) === statusFilter.value),
)

/** Auswahl pro Projekt: lineId → Menge. */
const picks = reactive<Record<string, Record<string, number>>>({})
const targets = reactive<Record<string, string>>({})

function ensureTarget(project: GaPackProject) {
  if (!targets[project.id]) targets[project.id] = project.target
}
projects.value.forEach(ensureTarget)

function isPicked(projectId: string, lineId: string): boolean {
  return picks[projectId]?.[lineId] !== undefined
}
function pickQty(projectId: string, line: GaPackNeedLine): number {
  return picks[projectId]?.[line.id] ?? linePackableNow(line)
}
function togglePick(project: GaPackProject, line: GaPackNeedLine, on: boolean) {
  const entry = (picks[project.id] ??= {})
  if (on) entry[line.id] = linePackableNow(line)
  else delete entry[line.id]
}
function setQty(projectId: string, line: GaPackNeedLine, raw: string) {
  const entry = (picks[projectId] ??= {})
  const value = Math.min(Math.max(1, Math.floor(Number(raw) || 1)), linePackableNow(line))
  entry[line.id] = value
}
function selectAll(project: GaPackProject) {
  const entry = (picks[project.id] ??= {})
  for (const line of packableLines(project)) entry[line.id] = linePackableNow(line)
}
function pickedDraft(project: GaPackProject) {
  return Object.entries(picks[project.id] ?? {}).map(([lineId, qty]) => ({ lineId, qty }))
}

function pack(project: GaPackProject) {
  const draft = pickedDraft(project)
  const lines = project.lines.filter((line) => draft.some((entry) => entry.lineId === line.id))
  const palette = createPalette(project.id, draft, {
    target: targets[project.id],
    title: suggestedPaletteTitle(project, lines),
  })
  picks[project.id] = {}
  if (palette) toast.success(t('grossanlass.packen.packed', { code: palette.code }))
}

const labelOpen = ref(false)
const labelPalette = ref<GaPalette | null>(null)
function openLabel(palette: GaPalette) {
  labelPalette.value = palette
  labelOpen.value = true
}

function send(palette: GaPalette) {
  if (sendFahrauftrag(palette)) toast.success(t('grossanlass.packen.sent', { code: palette.code }))
}

const driverOpen = ref(false)
const driverPalette = ref<GaPalette | null>(null)
function openDriver(palette: GaPalette) {
  driverPalette.value = palette
  driverOpen.value = true
}
</script>

<style scoped>
.packen {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 28px;
}
.packen__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.packen__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
.pk-kpi {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.pk-kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.pk-kpi span {
  font-size: 0.8rem;
  color: #64748b;
}
.pk-kpi--active {
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.18);
}
.pk-kpi--packable strong {
  color: #15803d;
}
.pk-kpi--partial strong {
  color: #b45309;
}
.packen__projects {
  display: grid;
  gap: 14px;
}
.pk-project {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #94a3b8;
  border-radius: 14px;
  background: #fff;
}
.pk-project--packable {
  border-left-color: #16a34a;
}
.pk-project--partial {
  border-left-color: #f59e0b;
}
.pk-project--done {
  border-left-color: #059669;
  opacity: 0.85;
}
.pk-project__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.pk-project__head h3 {
  margin: 0;
  font-size: 1.05rem;
}
.pk-project__origin {
  margin: 0 0 2px;
  font-size: 0.8rem;
  color: #64748b;
}
.pk-project__progress {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.82rem;
  color: #475569;
}
.pk-table {
  display: grid;
  gap: 2px;
}
.pk-table__row {
  display: grid;
  grid-template-columns: minmax(150px, 2fr) repeat(4, minmax(60px, 1fr)) minmax(120px, 1.4fr);
  gap: 8px;
  align-items: center;
  padding: 8px 4px;
  border-bottom: 1px solid #f1f5f9;
}
.pk-table__row--head {
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 600;
}
.pk-table__name {
  font-weight: 600;
}
.pk-table__pick {
  display: flex;
  align-items: center;
  gap: 6px;
}
.pk-qty {
  width: 72px;
  min-height: 40px;
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 1rem;
}
.pk-qty:disabled {
  opacity: 0.45;
}
.pk-missing {
  color: #b91c1c;
  font-weight: 700;
}
.pk-none {
  color: #94a3b8;
}
.pk-project__foot {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pk-now,
.pk-wait {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 0.88rem;
}
.pk-now {
  background: #ecfdf5;
  color: #065f46;
}
.pk-wait {
  background: #f1f5f9;
  color: #475569;
}
.pk-project__actions {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) auto auto;
  gap: 10px;
  align-items: center;
}
.packen__section {
  margin: 0 0 10px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.pk-palettes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}
.pk-palette {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}
.pk-palette__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.pk-palette__code {
  margin-left: 8px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: #475569;
}
.pk-palette h4 {
  margin: 0;
  font-size: 1rem;
}
.pk-palette__meta {
  margin: 0;
  color: #475569;
  font-size: 0.84rem;
}
.pk-palette__lines {
  margin: 0;
  padding-left: 18px;
  font-size: 0.88rem;
}
.pk-palette__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.pk-palette__sent {
  margin: 0;
  color: #1d4ed8;
  font-size: 0.82rem;
}
@media (max-width: 720px) {
  .packen__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pk-table__row--head {
    display: none;
  }
  .pk-table__row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pk-table__name {
    grid-column: 1 / -1;
  }
  .pk-table__row > span[data-label]::before {
    content: attr(data-label) ': ';
    color: #64748b;
    font-size: 0.75rem;
  }
  .pk-table__pick {
    grid-column: 1 / -1;
  }
  .pk-project__actions {
    grid-template-columns: 1fr;
  }
}
</style>
