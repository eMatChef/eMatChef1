<template>
  <PageShell class="sandbox-view">
    <template #title>{{ t('devSandbox.title') }}</template>
    <template #subtitle>{{ t('devSandbox.lead') }}</template>
    <template #actions>
      <EButton variant="secondary" :to="dashboardLink">
        {{ t('devSandbox.backToApp') }}
      </EButton>
    </template>

    <v-alert type="warning" variant="tonal" density="comfortable" class="mb-6">
      {{ t('devSandbox.devOnlyHint') }}
    </v-alert>

    <!-- Breakpoints (Dev-Hilfe, kein UI-Pattern) -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.breakpoints') }}</v-card-title>
      <v-card-text>
        <v-table density="compact">
          <tbody>
            <tr v-for="row in breakpointRows" :key="row.key">
              <td class="font-weight-medium">{{ row.key }}</td>
              <td>
                <v-chip size="small" :color="row.active ? 'primary' : undefined" variant="tonal">
                  {{ row.active ? t('devSandbox.yes') : t('devSandbox.no') }}
                </v-chip>
              </td>
            </tr>
            <tr>
              <td class="font-weight-medium">{{ t('devSandbox.windowSize') }}</td>
              <td>{{ windowWidth }} × {{ windowHeight }} px</td>
            </tr>
            <tr v-for="row in safeAreaRows" :key="row.key">
              <td class="font-weight-medium">{{ row.key }}</td>
              <td>{{ row.value }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <!-- Filter-Zeile (wie Kontakte) -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.filterRow') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.filterRowNote') }}</v-card-subtitle>
      <v-card-text>
        <EFilterRow>
          <v-col class="e-filter-row__search">
            <ESearchField
              v-model="filterSampleSearch"
              :label="t('devSandbox.samples.searchField')"
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="filterSampleSelect"
              :items="sampleSelectItems"
              :label="t('devSandbox.samples.select')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__actions d-flex align-center">
            <ECheckbox
              v-model="filterSampleCheckbox"
              class="e-filter-row__checkbox"
              density="compact"
              :label="t('devSandbox.samples.checkbox')"
              hide-details
            />
          </v-col>
        </EFilterRow>
      </v-card-text>
    </v-card>

    <!-- E* Formular -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.eComponents') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.eComponentsNote') }}</v-card-subtitle>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="6">
            <ETextField
              v-model="sampleText"
              :label="t('devSandbox.samples.textField')"
            />
          </v-col>
          <v-col cols="12" md="6">
            <ESelect
              v-model="sampleSelect"
              :items="sampleSelectItems"
              :label="t('devSandbox.samples.select')"
            />
          </v-col>
          <v-col cols="12">
            <ETextarea
              v-model="sampleTextarea"
              :label="t('devSandbox.samples.textarea')"
              rows="2"
            />
          </v-col>
          <v-col cols="12" md="6">
            <ECheckbox v-model="sampleCheckbox" :label="t('devSandbox.samples.checkbox')" />
          </v-col>
          <v-col cols="12" md="6">
            <ESwitch v-model="sampleSwitch" :label="t('devSandbox.samples.switch')" />
          </v-col>
        </v-row>

        <p class="text-caption text-medium-emphasis mt-4 mb-2">{{ t('devSandbox.eButtonVariants') }}</p>
        <div class="d-flex flex-wrap ga-3 mb-4">
          <EButton>{{ t('devSandbox.samples.primary') }}</EButton>
          <EButton variant="secondary">{{ t('devSandbox.samples.outlined') }}</EButton>
          <EButton variant="text">{{ t('devSandbox.samples.text') }}</EButton>
          <EButton variant="danger">{{ t('devSandbox.samples.error') }}</EButton>
        </div>

        <ECard variant="outlined" class="pa-4">
          <p class="text-body-2 mb-0">{{ t('devSandbox.eCardSample') }}</p>
        </ECard>
      </v-card-text>
    </v-card>

    <!-- Dialog, Confirm, Prompt -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.dialog') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.dialogNote') }}</v-card-subtitle>
      <v-card-text class="d-flex flex-wrap ga-3">
        <EButton @click="dialogOpen = true">
          {{ t('devSandbox.openDialog') }}
        </EButton>
        <EButton variant="secondary" @click="demoConfirm('warning')">
          {{ t('devSandbox.confirmTriggers.warning') }}
        </EButton>
        <EButton variant="danger" @click="demoConfirm('danger')">
          {{ t('devSandbox.confirmTriggers.danger') }}
        </EButton>
        <EButton variant="text" @click="demoPrompt">
          {{ t('devSandbox.promptTrigger') }}
        </EButton>
      </v-card-text>
    </v-card>

    <!-- Loading states -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.loadingStates') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.loadingStatesNote') }}</v-card-subtitle>
      <v-card-text>
        <p class="text-caption text-medium-emphasis mb-2">{{ t('devSandbox.loadingSamples.page') }}</p>
        <ELoadingState
          variant="page"
          compact
          :message="t('devSandbox.loadingSamples.pageMessage')"
          class="mb-6"
        />

        <p class="text-caption text-medium-emphasis mb-2">{{ t('devSandbox.loadingSamples.table') }}</p>
        <ELoadingState variant="table" :rows="4" class="mb-6" />

        <p class="text-caption text-medium-emphasis mb-2">{{ t('devSandbox.loadingSamples.list') }}</p>
        <ELoadingState variant="list" :rows="3" class="mb-4" />

        <p class="text-caption text-medium-emphasis mb-2">{{ t('devSandbox.loadingSamples.inline') }}</p>
        <ELoadingState variant="inline" :message="t('devSandbox.loadingSamples.inlineMessage')" />
      </v-card-text>
    </v-card>

    <!-- Empty states -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.emptyStates') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.emptyStatesNote') }}</v-card-subtitle>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="6">
            <EEmptyState
              variant="create"
              :title="t('devSandbox.emptySamples.createTitle')"
              :description="t('devSandbox.emptySamples.createText')"
            >
              <template #actions>
                <EButton>{{ t('devSandbox.emptySamples.createAction') }}</EButton>
              </template>
            </EEmptyState>
          </v-col>
          <v-col cols="12" md="6">
            <EEmptyState
              variant="search"
              :title="t('devSandbox.emptySamples.searchTitle')"
              :description="t('devSandbox.emptySamples.searchText')"
            >
              <template #actions>
                <EButton variant="secondary">{{ t('devSandbox.emptySamples.searchAction') }}</EButton>
              </template>
            </EEmptyState>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Material v-data-table (Experiment) -->
    <v-card class="mb-6" variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.materialDataTable') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.materialTableNote') }}</v-card-subtitle>
      <v-card-text>
        <MaterialDataTableSandboxDemo />
      </v-card-text>
    </v-card>

    <!-- Toasts -->
    <v-card variant="outlined">
      <v-card-title>{{ t('devSandbox.sections.toasts') }}</v-card-title>
      <v-card-subtitle>{{ t('devSandbox.toastsNote') }}</v-card-subtitle>
      <v-card-text class="d-flex flex-wrap ga-3">
        <EButton @click="showDemoToast('success')">
          {{ t('devSandbox.toastTriggers.success') }}
        </EButton>
        <EButton variant="danger" @click="showDemoToast('error')">
          {{ t('devSandbox.toastTriggers.error') }}
        </EButton>
        <EButton variant="secondary" @click="showDemoToast('warning')">
          {{ t('devSandbox.toastTriggers.warning') }}
        </EButton>
        <EButton variant="text" @click="showDemoToast('info')">
          {{ t('devSandbox.toastTriggers.info') }}
        </EButton>
        <EButton variant="text" @click="triggerToastStack">
          {{ t('devSandbox.toastTriggers.stack') }}
        </EButton>
        <EButton variant="text" @click="toast.clearAll()">
          {{ t('devSandbox.toastTriggers.clear') }}
        </EButton>
      </v-card-text>
    </v-card>

    <EDialog v-model="dialogOpen" :title="t('devSandbox.dialogTitle')" max-width="480">
      {{ t('devSandbox.dialogBody') }}
      <template #actions>
        <v-spacer />
        <EButton variant="text" @click="dialogOpen = false">{{ t('devSandbox.dialogClose') }}</EButton>
      </template>
    </EDialog>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { usePrompt } from '@/composables/usePrompt'
import PageShell from '@/components/layout/PageShell.vue'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import MaterialDataTableSandboxDemo from '@/views/dev/MaterialDataTableSandboxDemo.vue'
import {
  EButton,
  ECard,
  ECheckbox,
  EDialog,
  ESelect,
  ESearchField,
  ESwitch,
  ETextField,
  ETextarea,
} from '@/components/form/base'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const display = useDisplay()
const toast = useToast()
const confirm = useConfirm()
const prompt = usePrompt()

const sampleText = ref('')
const sampleSelect = ref<string | null>(null)
const sampleTextarea = ref('')
const sampleCheckbox = ref(false)
const sampleSwitch = ref(true)
const filterSampleSearch = ref('')
const filterSampleSelect = ref<string | null>(null)
const filterSampleCheckbox = ref(false)
const dialogOpen = ref(false)
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
const windowHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)

const sampleSelectItems = computed(() => [
  { title: t('devSandbox.samples.optionA'), value: 'a' },
  { title: t('devSandbox.samples.optionB'), value: 'b' },
])

const dashboardLink = computed(() => {
  const id =
    (route.params.departmentId as string) ||
    authStore.activeDepartmentId ||
    authStore.departments.find((d) => d.is_primary)?.department_id ||
    authStore.departments[0]?.department_id
  return id ? `/${id}` : '/dashboard'
})

const breakpointRows = computed(() => [
  { key: 'xs', active: display.xs.value },
  { key: 'sm', active: display.sm.value },
  { key: 'smAndUp', active: display.smAndUp.value },
  { key: 'md', active: display.md.value },
  { key: 'mdAndUp', active: display.mdAndUp.value },
  { key: 'lg', active: display.lg.value },
  { key: 'lgAndUp', active: display.lgAndUp.value },
  { key: 'xl', active: display.xl.value },
  { key: 'mobile', active: display.mobile.value },
])

const safeAreaInsets = ref({ top: '0px', right: '0px', bottom: '0px', left: '0px' })

const safeAreaRows = computed(() => [
  { key: '--emc-safe-top', value: safeAreaInsets.value.top },
  { key: '--emc-safe-bottom', value: safeAreaInsets.value.bottom },
  { key: '--emc-safe-left', value: safeAreaInsets.value.left },
  { key: '--emc-safe-right', value: safeAreaInsets.value.right },
])

function readSafeAreaInsets() {
  const root = getComputedStyle(document.documentElement)
  safeAreaInsets.value = {
    top: root.getPropertyValue('--emc-safe-top').trim() || '0px',
    right: root.getPropertyValue('--emc-safe-right').trim() || '0px',
    bottom: root.getPropertyValue('--emc-safe-bottom').trim() || '0px',
    left: root.getPropertyValue('--emc-safe-left').trim() || '0px',
  }
}

function updateWindowSize() {
  windowWidth.value = window.innerWidth
  windowHeight.value = window.innerHeight
}

const demoToastDuration = 8000

function showDemoToast(type: 'success' | 'error' | 'warning' | 'info') {
  const message = t(`devSandbox.toastSamples.${type}`)
  switch (type) {
    case 'success':
      toast.success(message, demoToastDuration)
      break
    case 'error':
      toast.error(message, demoToastDuration)
      break
    case 'warning':
      toast.warning(message, demoToastDuration)
      break
    default:
      toast.info(message, demoToastDuration)
  }
}

function triggerToastStack() {
  toast.success(t('devSandbox.toastSamples.success'), demoToastDuration)
  toast.info(t('devSandbox.toastSamples.info'), demoToastDuration)
  toast.warning(t('devSandbox.toastSamples.warning'), demoToastDuration)
}

async function demoConfirm(variant: 'warning' | 'danger') {
  const samples = variant === 'danger'
    ? {
        title: t('devSandbox.confirmSamples.dangerTitle'),
        message: t('devSandbox.confirmSamples.dangerMessage'),
      }
    : {
        title: t('devSandbox.confirmSamples.warningTitle'),
        message: t('devSandbox.confirmSamples.warningMessage'),
      }
  const ok = await confirm.confirm({ ...samples, variant })
  if (ok) {
    toast.success(t('devSandbox.confirmSamples.confirmed'), demoToastDuration)
  } else {
    toast.info(t('devSandbox.confirmSamples.cancelled'), demoToastDuration)
  }
}

async function demoPrompt() {
  const value = await prompt.prompt({
    title: t('devSandbox.promptSamples.title'),
    message: t('devSandbox.promptSamples.message'),
    placeholder: t('devSandbox.promptSamples.placeholder'),
    required: true,
  })
  if (value) {
    toast.success(t('devSandbox.promptSamples.entered', { value }), demoToastDuration)
  } else {
    toast.info(t('devSandbox.confirmSamples.cancelled'), demoToastDuration)
  }
}

onMounted(() => {
  window.addEventListener('resize', updateWindowSize)
  updateWindowSize()
  readSafeAreaInsets()
})

onUnmounted(() => {
  window.removeEventListener('resize', updateWindowSize)
})
</script>

<style scoped>
.sandbox-view {
  padding-bottom: 48px;
}
</style>
