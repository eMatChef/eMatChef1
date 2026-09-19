<template>
  <div class="ga-bauprojekt-panel">
    <p v-if="error" class="ga-bauprojekt-panel__error">{{ error }}</p>
    <p v-else-if="loading" class="muted">{{ t('common.loading') }}</p>
    <template v-else-if="briefing">
      <EDateRangeField
        v-if="briefing.can_edit"
        :department-id="departmentId"
        :label="t('grossanlass.planung.ressorts.windowLabel')"
        v-model:start="windowStart"
        v-model:end="windowEnd"
        allow-past
      />
      <p v-else-if="windowText" class="muted">{{ windowText }}</p>
      <EButton
        v-if="briefing.can_edit"
        variant="secondary"
        size="small"
        class="ga-bauprojekt-panel__save-window"
        :loading="savingWindow"
        :disabled="savingWindow"
        @click="saveWindow"
      >
        {{ t('grossanlass.planung.ressorts.windowSave') }}
      </EButton>

      <section class="ga-bauprojekt-panel__block">
        <h4>{{ t('grossanlass.planung.ressorts.placeHeading') }}</h4>
        <p v-if="briefing.place">
          <a :href="briefing.place.qr_url" target="_blank" rel="noopener">
            {{ briefing.place.public_code }}
          </a>
          · {{ briefing.place.name }}
        </p>
        <p v-else class="muted">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
        <RouterLink class="ga-bauprojekt-panel__link" :to="standorteTo">
          {{ t('grossanlass.planung.ressorts.placeOnMap') }}
        </RouterLink>
      </section>

      <section class="ga-bauprojekt-panel__block">
        <h4>{{ t('grossanlass.planung.ressorts.tasksHeading') }}</h4>
        <ul v-if="briefing.tasks.length" class="ga-bauprojekt-panel__list">
          <li v-for="task in briefing.tasks" :key="task.id">
            <span>{{ task.title }}</span>
            <button
              v-if="briefing.can_edit"
              type="button"
              class="action-btn action-btn-danger"
              :title="t('common.delete')"
              @click="removeTask(task.id)"
            >
              <v-icon icon="mdi-close" size="14" />
            </button>
          </li>
        </ul>
        <p v-else class="muted">{{ t('grossanlass.planung.ressorts.tasksEmpty') }}</p>
        <form v-if="briefing.can_edit" class="ga-bauprojekt-panel__add" @submit.prevent="addTask">
          <ETextField
            v-model="taskTitle"
            :placeholder="t('grossanlass.planung.ressorts.taskPlaceholder')"
            hide-details="auto"
          />
          <EButton variant="primary" size="small" type="submit" :disabled="!String(taskTitle || '').trim()" :loading="savingTask">
            {{ t('grossanlass.planung.ressorts.taskAdd') }}
          </EButton>
        </form>
      </section>

      <section class="ga-bauprojekt-panel__block">
        <h4>{{ t('grossanlass.planung.ressorts.materialHeading') }}</h4>
        <p class="muted">{{ t('grossanlass.planung.ressorts.materialHint') }}</p>
        <ul v-if="briefing.material.length" class="ga-bauprojekt-panel__list">
          <li v-for="line in briefing.material" :key="line.id">
            {{ line.quantity }}× {{ line.label }}
          </li>
        </ul>
        <p v-else class="muted">{{ t('grossanlass.planung.ressorts.materialEmpty') }}</p>
        <form v-if="briefing.can_edit" class="ga-bauprojekt-panel__add" @submit.prevent="addMaterial">
          <ETextField
            v-model="materialLabel"
            :placeholder="t('grossanlass.planung.ressorts.materialLabel')"
            hide-details="auto"
          />
          <ETextField
            v-model="materialQty"
            type="number"
            min="1"
            :placeholder="t('grossanlass.planung.ressorts.materialQty')"
            hide-details="auto"
            class="ga-bauprojekt-panel__qty"
          />
          <EButton
            variant="primary"
            size="small"
            type="submit"
            :disabled="!String(materialLabel || '').trim()"
            :loading="savingMaterial"
          >
            {{ t('grossanlass.planung.ressorts.materialAdd') }}
          </EButton>
        </form>
      </section>

      <EButton variant="secondary" size="small" :disabled="!briefing.group" @click="openPrint">
        {{ t('grossanlass.planung.ressorts.printHelper') }}
      </EButton>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { EButton, EDateRangeField, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import {
  addGrossanlassBauprojektMaterial,
  createGrossanlassBauprojektTask,
  deleteGrossanlassBauprojektTask,
  getGrossanlassBauprojekt,
  patchGrossanlassBauprojektWindow,
  type GaBauprojektBriefing,
} from '@/api/grossanlassBauprojekt'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'

const props = defineProps<{
  departmentId: string
  groupId: string
}>()

const { t } = useI18n()
const toast = useToast()
const router = useRouter()

const loading = ref(false)
const error = ref<string | null>(null)
const briefing = ref<GaBauprojektBriefing | null>(null)
const windowStart = ref('')
const windowEnd = ref('')
const savingWindow = ref(false)
const taskTitle = ref('')
const savingTask = ref(false)
const materialLabel = ref('')
const materialQty = ref('1')
const savingMaterial = ref(false)

const windowText = computed(() =>
  formatBauprojektWindow(briefing.value?.window_start, briefing.value?.window_end),
)
const standorteTo = computed(() => `/${props.departmentId}/einstellungen/standorte`)

async function load() {
  if (!props.departmentId || !props.groupId) return
  loading.value = true
  error.value = null
  try {
    briefing.value = await getGrossanlassBauprojekt(props.departmentId, props.groupId)
    windowStart.value = briefing.value.window_start || ''
    windowEnd.value = briefing.value.window_end || ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
  } finally {
    loading.value = false
  }
}

async function saveWindow() {
  if (!briefing.value?.can_edit || savingWindow.value) return
  savingWindow.value = true
  try {
    briefing.value = await patchGrossanlassBauprojektWindow(props.departmentId, props.groupId, {
      window_start: windowStart.value || null,
      window_end: windowEnd.value || null,
    })
    toast.success(t('grossanlass.planung.ressorts.windowSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingWindow.value = false
  }
}

async function addTask() {
  const title = String(taskTitle.value || '').trim()
  if (!title || savingTask.value) return
  savingTask.value = true
  try {
    const task = await createGrossanlassBauprojektTask(props.departmentId, props.groupId, title)
    if (briefing.value) briefing.value.tasks = [...briefing.value.tasks, task]
    taskTitle.value = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingTask.value = false
  }
}

async function removeTask(taskId: string) {
  try {
    await deleteGrossanlassBauprojektTask(props.departmentId, props.groupId, taskId)
    if (briefing.value) {
      briefing.value.tasks = briefing.value.tasks.filter((row) => row.id !== taskId)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  }
}

async function addMaterial() {
  const label = String(materialLabel.value || '').trim()
  if (!label || savingMaterial.value) return
  savingMaterial.value = true
  try {
    const line = await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
      label,
      quantity: Math.max(1, Number(materialQty.value) || 1),
    })
    if (briefing.value) briefing.value.material = [...briefing.value.material, line]
    materialLabel.value = ''
    materialQty.value = '1'
    toast.success(t('grossanlass.planung.ressorts.materialAdded'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingMaterial.value = false
  }
}

function openPrint() {
  void router.push({
    name: 'GrossanlassHelferauftragPrint',
    params: { departmentId: props.departmentId, groupId: props.groupId },
  })
}

watch(() => props.groupId, () => { void load() })
onMounted(() => { void load() })
</script>

<style scoped>
.ga-bauprojekt-panel__error { color: #b91c1c; }
.muted { color: #64748b; font-size: 0.88rem; }
.ga-bauprojekt-panel__save-window { margin: 8px 0 16px; }
.ga-bauprojekt-panel__block { margin: 16px 0; }
.ga-bauprojekt-panel__block h4 { margin: 0 0 8px; font-size: 0.95rem; }
.ga-bauprojekt-panel__list { list-style: none; padding: 0; margin: 0 0 8px; }
.ga-bauprojekt-panel__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px solid #eef2f6;
}
.ga-bauprojekt-panel__add {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-start;
}
.ga-bauprojekt-panel__qty { max-width: 96px; }
.ga-bauprojekt-panel__link { font-size: 0.88rem; }
.action-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
}
.action-btn-danger { color: #b91c1c; }
</style>
