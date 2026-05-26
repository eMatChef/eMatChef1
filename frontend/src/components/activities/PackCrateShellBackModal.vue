<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'

export interface ShellCrateBackDeviation {
  id: string
  materialName: string
  detail: string
}

const props = defineProps<{
  open: boolean
  shellPackItem: ActivityPackItem
  moveQty: number
  fromStageLabel: string
  toStageLabel: string
  label: string
  peekSections: PackCrateShellPeekSection[]
  deviations: ShellCrateBackDeviation[]
  lastCheckDateLabel: string | null
  acknowledged: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  'update:acknowledged': [value: boolean]
  cancel: []
  confirm: []
}>()

const { t } = useI18n()

const extraLines = computed(() => {
  const sec = props.peekSections.find((s) => s.subsectionKey === 'extra')
  return sec?.lines ?? []
})

const fixedSections = computed(() =>
  props.peekSections.filter((s) => s.subsectionKey !== 'extra' && s.lines.length > 0),
)
</script>

<template>
  <PackWorkflowModal :open="open" size="md" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.shellBackTitle') }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">
        {{
          t('activities.packList.shellBackIntro', {
            label,
            n: moveQty,
            from: fromStageLabel,
            to: toStageLabel,
          })
        }}
      </p>
      <p class="pack-modal-hint text-muted pack-shell-back-retour-hint">
        {{ t('activities.packList.shellBackRetourHint') }}
      </p>
      <p v-if="lastCheckDateLabel" class="pack-modal-hint text-muted">
        {{ t('activities.packList.shellBackLastCheck', { date: lastCheckDateLabel }) }}
      </p>
    </template>

    <div v-if="deviations.length > 0" class="pack-shell-back-block">
      <div class="pack-container-subsection-title">
        {{ t('activities.packList.shellBackDeviationsTitle') }}
      </div>
      <ul class="pack-shell-back-ul">
        <li v-for="d in deviations" :key="d.id" class="pack-shell-back-deviation">
          <strong>{{ d.materialName }}</strong>
          <span class="text-muted"> — {{ d.detail }}</span>
        </li>
      </ul>
    </div>

    <div v-if="extraLines.length > 0" class="pack-shell-back-block">
      <div class="pack-container-subsection-title">{{ t('activities.packList.shellBackExtraTitle') }}</div>
      <ul class="pack-shell-back-ul">
        <li v-for="line in extraLines" :key="'ex-' + line.id" class="pack-shell-back-extra">
          <span>{{ line.materialName }}</span>
          <span class="text-muted"> {{ line.quantity }}×</span>
          <span class="text-muted"> — {{ t('activities.packList.shellBackExtraInCrate') }}</span>
        </li>
      </ul>
    </div>

    <div v-if="fixedSections.length > 0 || peekSections.length > 0" class="pack-shell-back-preview">
      <div class="pack-container-subsection-title">{{ t('activities.packList.shellBackContentsTitle') }}</div>
      <PackCrateShellInlinePanel
        :sections="peekSections"
        :empty-hint="t('activities.packList.cratePeekNoShellYet')"
        separate-section-rows
        default-expanded
      />
    </div>

    <label class="pack-modal-checkbox-row pack-shell-back-confirm-row">
      <input
        :checked="acknowledged"
        type="checkbox"
        @change="emit('update:acknowledged', ($event.target as HTMLInputElement).checked)"
      />
      <span>{{ t('activities.packList.shellBackAcknowledge') }}</span>
    </label>

    <template #footer>
      <PackModalFooter
        :primary-label="t('activities.packList.shellBackSubmit', { to: toStageLabel })"
        :primary-disabled="submitting || !acknowledged"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="emit('confirm')"
      />
    </template>
  </PackWorkflowModal>
</template>

<style src="@/styles/views/activities/detail-workflow.css"></style>
<style scoped>
.pack-shell-back-retour-hint {
  padding: 8px 10px;
  border-radius: 6px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
}

.pack-shell-back-block {
  margin: 12px 0;
}

.pack-shell-back-ul {
  margin: 6px 0 0;
  padding-left: 1.2rem;
  font-size: 13px;
  line-height: 1.45;
}

.pack-shell-back-deviation,
.pack-shell-back-extra {
  margin-bottom: 4px;
}

.pack-shell-back-preview {
  margin: 12px 0;
}

.pack-shell-back-confirm-row {
  margin-top: 12px;
}
</style>
