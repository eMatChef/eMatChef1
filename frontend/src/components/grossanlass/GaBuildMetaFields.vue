<template>
  <div class="ga-build-meta-fields">
    <AutoSaveField
      v-if="autosave"
      :model-value="packedWindow"
      :baseline="windowBaseline"
      :label="windowLabel"
      :disabled="disabled"
      span-class="ga-build-meta-fields__window"
      :save="onSaveWindow"
      @update:model-value="onPackedWindow"
    >
      <template #default="{ onChange }">
        <EDateRangeField
          :department-id="departmentId"
          v-model:start="start"
          v-model:end="end"
          allow-past
          show-presets
          preset-mode="fixed-periods"
          @update:start="touchWindow(onChange)"
          @update:end="touchWindow(onChange)"
        />
      </template>
    </AutoSaveField>
    <EDateRangeField
      v-else
      :department-id="departmentId"
      :label="windowLabel"
      v-model:start="start"
      v-model:end="end"
      allow-past
      show-presets
      preset-mode="fixed-periods"
    />
    <p v-if="windowHint" :class="hintClass">{{ windowHint }}</p>
    <p :class="hintClass">
      <span class="ga-build-meta-fields__status-label">{{ t('grossanlass.planung.ressorts.buildStatusLabel') }}:</span>
      {{ derivedStatusLabel }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { AutoSaveField } from '@/components/common/autoSave'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import { EDateRangeField } from '@/components/form/base'
import {
  packBauprojektWindow,
  unpackBauprojektWindow,
} from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
  watchGrossanlassBuildPeriods,
} from '@/utils/grossanlassBuildStatus'

defineOptions({ name: 'GaBuildMetaFields' })

const start = defineModel<string>('start', { default: '' })
const end = defineModel<string>('end', { default: '' })
const status = defineModel<string>('status', { default: '' })

const props = withDefaults(defineProps<{
  departmentId?: string | null
  autosave?: boolean
  disabled?: boolean
  windowLabel: string
  windowHint?: string
  windowBaseline?: string
  statusBaseline?: string
  hintClass?: string
  saveWindow?: (value: AutoSaveFieldValue) => Promise<void>
  saveStatus?: (value: AutoSaveFieldValue) => Promise<void>
  nodeType?: string | null
  reportedStatuses?: Array<string | null | undefined>
}>(), {
  departmentId: null,
  autosave: false,
  disabled: false,
  windowHint: '',
  windowBaseline: undefined,
  statusBaseline: undefined,
  hintClass: 'window-hint',
  nodeType: null,
  reportedStatuses: () => [],
})

const { t } = useI18n()
watchGrossanlassBuildPeriods(toRef(props, 'departmentId'))
const packedWindow = computed(() => packBauprojektWindow(start.value, end.value))
const derivedStatusLabel = computed(() => t(gaBuildStatusI18nKey(resolveBuildStatus({
  node_type: props.nodeType,
  build_status: status.value,
  window_start: start.value,
  window_end: end.value,
}, undefined, props.reportedStatuses))))

function onPackedWindow(value: AutoSaveFieldValue) {
  const next = unpackBauprojektWindow(value)
  start.value = next.start
  end.value = next.end
}

function touchWindow(onChange: () => void) {
  void nextTick(onChange)
}

async function onSaveWindow(value: AutoSaveFieldValue) {
  if (!props.saveWindow) return
  await props.saveWindow(value)
}

</script>

<style scoped>
.ga-build-meta-fields__window :deep(.e-date-range-field.autosave-field) {
  margin: 0;
}
.ga-build-meta-fields__window :deep(.e-date-range-field .autosave-field-frame) {
  border: 0;
  padding: 0;
  box-shadow: none;
  background: transparent;
}
.ga-build-meta-fields__window :deep(.e-date-range-field .autosave-label) {
  display: none;
}
.ga-build-meta-fields__status-label {
  font-weight: 600;
}
</style>
