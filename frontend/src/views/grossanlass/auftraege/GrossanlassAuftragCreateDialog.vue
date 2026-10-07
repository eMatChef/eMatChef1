<template>
  <EDialog v-model="open" :title="t('grossanlass.auftraege.create.title')" max-width="760" :retain-focus="false">
    <div class="create">
      <div class="create__types" role="radiogroup" :aria-label="t('grossanlass.auftraege.create.type')">
        <button v-for="type in types" :key="type" type="button" role="radio" :aria-checked="draft.type === type" class="type" :class="{ 'type--on': draft.type === type }" @click="setType(type)">
          <v-icon :icon="TYPE_ICON[type]" size="26" />
          <span><strong>{{ t(`grossanlass.auftraege.type.${type}`) }}</strong><small>{{ t(`grossanlass.auftraege.create.typeText.${type}`) }}</small></span>
        </button>
      </div>

      <ETextField v-model="draft.title" :label="t('grossanlass.auftraege.f.title')" hide-details="auto" />
      <ETextarea v-model="draft.description" :label="t('grossanlass.auftraege.f.description')" rows="2" hide-details />
      <div class="grid">
        <ETextField v-model="draft.ressort" :label="t('grossanlass.auftraege.f.ressort')" hide-details />
        <ETextField v-model="draft.bereich" :label="t('grossanlass.auftraege.f.bereich')" hide-details />
        <ETextField :model-value="toInput(draft.startsAt)" type="datetime-local" :label="t('grossanlass.auftraege.f.from')" hide-details @update:model-value="setDate('startsAt', $event)" />
        <ETextField :model-value="toInput(draft.endsAt)" type="datetime-local" :label="t('grossanlass.auftraege.f.to')" hide-details @update:model-value="setDate('endsAt', $event)" />
        <ETextField v-model="draft.location" :label="t('grossanlass.auftraege.f.location')" hide-details />
        <ESelect v-model="draft.responsible" :items="GA_DEMO_PEOPLE" :label="t('grossanlass.auftraege.f.responsible')" multiple hide-details />
      </div>

      <section class="block">
        <h4><v-icon icon="mdi-clock-outline" size="16" /> {{ t('grossanlass.auftraege.times.title') }}</h4>
        <div class="grid">
          <ETextField :model-value="toInput(draft.earliestStart)" type="datetime-local" :label="t('grossanlass.auftraege.times.earliest')" hide-details @update:model-value="setOptional('earliestStart', $event)" />
          <ETextField :model-value="toInput(draft.deadline)" type="datetime-local" :label="t('grossanlass.auftraege.times.deadline')" hide-details @update:model-value="setOptional('deadline', $event)" />
          <ETextField :model-value="toInput(draft.wished?.from ?? null)" type="datetime-local" :label="t('grossanlass.auftraege.times.wishedFrom')" hide-details @update:model-value="setWished('from', $event)" />
          <ETextField :model-value="toInput(draft.wished?.to ?? null)" type="datetime-local" :label="t('grossanlass.auftraege.times.wishedTo')" hide-details @update:model-value="setWished('to', $event)" />
        </div>
        <p class="hint">{{ t('grossanlass.auftraege.times.plannedHint') }}</p>
      </section>

      <section v-if="draft.type === 'build' && draft.build" class="block block--build">
        <h4><v-icon icon="mdi-hammer-wrench" size="16" /> {{ t('grossanlass.auftraege.create.buildSection') }}</h4>
        <div class="grid">
          <ETextField v-model="draft.build.project" :label="t('grossanlass.auftraege.f.project')" hide-details />
          <ETextField v-model="draft.build.site" :label="t('grossanlass.auftraege.f.site')" hide-details />
          <ETextField :model-value="toInput(draft.build.readyBy)" type="datetime-local" :label="t('grossanlass.auftraege.times.readyBy')" hide-details @update:model-value="setBuildDate('readyBy', $event)" />
          <ETextField :model-value="toInput(draft.build.buildFrom)" type="datetime-local" :label="t('grossanlass.auftraege.f.buildFrom')" hide-details @update:model-value="setBuildDate('buildFrom', $event)" />
          <ETextField :model-value="toInput(draft.build.buildTo)" type="datetime-local" :label="t('grossanlass.auftraege.f.buildTo')" hide-details @update:model-value="setBuildDate('buildTo', $event)" />
          <ETextField v-model="machinesText" :label="t('grossanlass.auftraege.f.machines')" hide-details />
          <ETextField :model-value="toInput(draft.build.teardownFrom)" type="datetime-local" :label="t('grossanlass.auftraege.f.teardownFrom')" hide-details @update:model-value="setBuildDate('teardownFrom', $event)" />
          <ETextField :model-value="toInput(draft.build.teardownTo)" type="datetime-local" :label="t('grossanlass.auftraege.f.teardownTo')" hide-details @update:model-value="setBuildDate('teardownTo', $event)" />
        </div>
        <ETextarea v-model="draft.build.teardownInfo" :label="t('grossanlass.auftraege.f.teardownInfo')" rows="2" hide-details />
        <p class="hint">{{ t('grossanlass.auftraege.create.progressHint') }}</p>
      </section>

      <section class="block">
        <h4>{{ t('grossanlass.auftraege.create.helpers') }}</h4>
        <div v-for="(need, index) in draft.helperNeed" :key="index" class="need-row">
          <ETextField v-model.number="need.count" type="number" min="1" :label="t('grossanlass.auftraege.f.count')" hide-details />
          <ESelect v-model="need.skill" :items="GA_SKILLS" :label="t('grossanlass.auftraege.f.skill')" hide-details />
          <EButton variant="text" size="small" :aria-label="t('common.delete')" @click="draft.helperNeed.splice(index, 1)"><v-icon icon="mdi-close" size="18" /></EButton>
        </div>
        <EButton variant="secondary" size="small" @click="draft.helperNeed.push({ skill: 'Holzbau', count: 2 })">
          <v-icon icon="mdi-plus" start size="16" /> {{ t('grossanlass.auftraege.create.addNeed') }}
        </EButton>
      </section>

      <section class="block">
        <h4>{{ t('grossanlass.auftraege.res.title') }}</h4>
        <div v-for="(res, index) in draft.resources" :key="index" class="res-row">
          <ESelect v-model="res.type" :items="resTypeItems" :label="t('grossanlass.auftraege.res.typeLabel')" hide-details />
          <ETextField v-model="res.label" :label="t('grossanlass.auftraege.res.labelField')" hide-details />
          <ETextField v-model.number="res.durationH" type="number" min="0.5" step="0.5" :label="t('grossanlass.auftraege.res.duration')" hide-details />
          <ETextField v-model.number="res.qty" type="number" min="1" :label="t('grossanlass.auftraege.f.count')" hide-details />
          <ETextField :model-value="toInput(res.earliest)" type="datetime-local" :label="t('grossanlass.auftraege.res.earliest')" hide-details @update:model-value="setResDate(res, 'earliest', $event)" />
          <ETextField :model-value="toInput(res.latest)" type="datetime-local" :label="t('grossanlass.auftraege.res.latest')" hide-details @update:model-value="setResDate(res, 'latest', $event)" />
          <ETextField :model-value="toInput(res.wish?.from ?? null)" type="datetime-local" :label="t('grossanlass.auftraege.res.wishFrom')" hide-details @update:model-value="setResWish(res, 'from', $event)" />
          <ETextField :model-value="toInput(res.wish?.to ?? null)" type="datetime-local" :label="t('grossanlass.auftraege.res.wishTo')" hide-details @update:model-value="setResWish(res, 'to', $event)" />
          <ECheckbox v-model="res.flexible" :label="t('grossanlass.auftraege.res.flexible')" hide-details />
          <ETextField v-model="res.note" :label="t('grossanlass.auftraege.res.note')" hide-details />
          <EButton variant="text" size="small" :aria-label="t('common.delete')" @click="draft.resources.splice(index, 1)"><v-icon icon="mdi-close" size="18" /></EButton>
        </div>
        <EButton variant="secondary" size="small" @click="addResource">
          <v-icon icon="mdi-plus" start size="16" /> {{ t('grossanlass.auftraege.res.add') }}
        </EButton>
      </section>

      <div class="grid">
        <ETextarea v-model="materialText" :label="t('grossanlass.auftraege.f.material')" rows="3" hide-details />
        <ETextarea v-model="toolsText" :label="t('grossanlass.auftraege.f.tools')" rows="3" hide-details />
      </div>

      <section class="block">
        <ECheckbox v-model="withTransport" :label="t('grossanlass.auftraege.f.transport')" hide-details />
        <div v-if="withTransport && draft.transport" class="grid">
          <ETextField v-model="draft.transport.from" :label="t('grossanlass.auftraege.f.transportFrom')" hide-details />
          <ETextField v-model="draft.transport.to" :label="t('grossanlass.auftraege.f.transportTo')" hide-details />
        </div>
      </section>

      <ETextarea v-model="tasksText" :label="t('grossanlass.auftraege.f.tasks')" rows="3" hide-details />
      <p class="hint">{{ t('grossanlass.auftraege.create.tasksHint') }}</p>
      <ESelect v-model="status" :items="statusItems" :label="t('grossanlass.auftraege.f.status')" hide-details />
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :disabled="!valid" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { GA_DEMO_PEOPLE } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { GA_SKILLS } from '@/views/grossanlass/helferpool/gaHelferMock'
import { emptyResourceDraft, GA_RESOURCE_TYPES, type GaResourceDraft } from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { createOrder, emptyDraft, type GaOrderDraft, type GaOrderStatus, type GaOrderType } from './gaAuftraegeMock'
import { TYPE_ICON, fromInput, toInput } from './gaAuftraegeUi'

const props = defineProps<{ modelValue: boolean; initialType: GaOrderType }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; created: [id: string] }>()
const { t } = useI18n()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const types: GaOrderType[] = ['order', 'build']
const draft = reactive<GaOrderDraft>(emptyDraft('order'))
const materialText = ref('')
const toolsText = ref('')
const tasksText = ref('')
const machinesText = ref('')
const withTransport = ref(false)
const status = ref<GaOrderStatus>('draft')
const statusItems = computed(() => (['draft', 'planned', 'active', 'done'] as const).map((value) => ({ value, title: t(`grossanlass.auftraege.status.${value}`) })))
const valid = computed(() => draft.title.trim().length > 0 && draft.ressort.trim().length > 0 && draft.endsAt > draft.startsAt)

function reset(type: GaOrderType) {
  Object.assign(draft, emptyDraft(type))
  materialText.value = ''
  toolsText.value = ''
  tasksText.value = ''
  machinesText.value = ''
  withTransport.value = false
  status.value = 'draft'
}
function setType(type: GaOrderType) {
  const keep = { title: draft.title, description: draft.description, ressort: draft.ressort, bereich: draft.bereich }
  reset(type)
  Object.assign(draft, keep)
}
function setDate(key: 'startsAt' | 'endsAt', value: string | null) {
  const date = fromInput(String(value ?? ''))
  if (date) draft[key] = date
}
function setOptional(key: 'earliestStart' | 'deadline', value: string | null) {
  draft[key] = fromInput(String(value ?? ''))
}
function setWished(key: 'from' | 'to', value: string | null) {
  const date = fromInput(String(value ?? ''))
  const current = draft.wished ?? { from: draft.startsAt, to: draft.endsAt }
  draft.wished = date ? { ...current, [key]: date } : null
}
const resTypeItems = computed(() => GA_RESOURCE_TYPES.map((value) => ({ value, title: t(`grossanlass.auftraege.res.type.${value}`) })))
function addResource() {
  draft.resources.push(emptyResourceDraft(draft.startsAt, draft.endsAt))
}
function setResDate(res: GaResourceDraft, key: 'earliest' | 'latest', value: string | null) {
  const date = fromInput(String(value ?? ''))
  if (date) res[key] = date
}
function setResWish(res: GaResourceDraft, key: 'from' | 'to', value: string | null) {
  const date = fromInput(String(value ?? ''))
  const current = res.wish ?? { from: res.earliest, to: res.latest }
  res.wish = date ? { ...current, [key]: date } : null
}
function setBuildDate(key: 'buildFrom' | 'buildTo' | 'readyBy' | 'teardownFrom' | 'teardownTo', value: string | null) {
  if (draft.build) draft.build[key] = fromInput(String(value ?? ''))!
}

watch(withTransport, (on) => {
  draft.transport = on ? (draft.transport ?? { from: '', to: '', note: '' }) : null
})
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) reset(props.initialType)
})

const lines = (text: string) => text.split('\n').map((line) => line.trim()).filter(Boolean)

function save() {
  if (!valid.value) return
  const created = createOrder({
    ...draft,
    title: draft.title.trim(),
    material: lines(materialText.value),
    tools: lines(toolsText.value),
    taskTitles: lines(tasksText.value),
    helperNeed: draft.helperNeed.filter((need) => need.count > 0),
    resources: draft.resources.filter((res) => res.label.trim()),
    transport: withTransport.value ? draft.transport : null,
    status: status.value,
    build: draft.type === 'build' && draft.build
      ? { ...draft.build, machines: machinesText.value.split(',').map((item) => item.trim()).filter(Boolean) }
      : undefined,
  })
  emit('created', created.id)
  open.value = false
}
</script>

<style scoped>
.create {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.create__types {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.type {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.type small {
  display: block;
  color: #64748b;
}
.type--on {
  border: 2px solid #059669;
  background: #ecfdf5;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 10px;
}
.block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
.block--build {
  border-color: #fdba74;
  background: #fff7ed;
}
.block h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.res-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
  align-items: center;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.need-row {
  display: grid;
  grid-template-columns: 100px minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.hint {
  margin: 0;
  color: #64748b;
  font-size: 0.8rem;
}
@media (max-width: 560px) {
  .create__types {
    grid-template-columns: 1fr;
  }
}
</style>
