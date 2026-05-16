<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'

export type ReturnCratePartitionView = {
  shellQty: number
  shellIsExtra: boolean
  shellMaterialName: string
  extraLines: ActivityPackContainerItem[]
  standardLines: ActivityPackContainerItem[]
  hasWarehouseTemplate: boolean
}

defineProps<{
  open: boolean
  containerLabel: string
  contentsLoading: boolean
  contentsError: boolean
  noLinkedBatch: boolean
  partition: ReturnCratePartitionView
  notTakenReminders: ActivityIssueReportRow[]
  notTakenLine: (r: ActivityIssueReportRow) => string
  lineRemainingReturn: (ci: ActivityPackContainerItem) => number
  checked: boolean
  submitting: boolean
  submitDisabled: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:checked': [value: boolean]
}>()

const { t } = useI18n()
</script>

<template>
  <PackWorkflowModal :open="open" size="lg" @cancel="emit('cancel')">
    <template #title>{{
      t('activities.packList.returnCrateModalTitle', { label: containerLabel })
    }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">{{ t('activities.packList.returnCrateModalIntro') }}</p>
    </template>

    <div v-if="notTakenReminders.length > 0" class="pack-not-taken-reminder" role="status">
      <div class="pack-not-taken-reminder__title">{{ t('activities.packList.notTakenReminderTitle') }}</div>
      <p class="pack-not-taken-reminder__intro text-muted">
        {{ t('activities.packList.notTakenReminderIntro') }}
      </p>
      <ul class="pack-not-taken-reminder__ul">
        <li v-for="r in notTakenReminders" :key="'ret-nt-' + r.id">{{ notTakenLine(r) }}</li>
      </ul>
    </div>

    <div v-if="contentsLoading" class="pack-modal-loading text-muted">
      {{ t('activities.packList.returnCrateModalLoadingContents') }}
    </div>
    <template v-else>
      <p v-if="contentsError" class="pack-modal-hint pack-modal-hint--warn">
        {{ t('activities.packList.returnCrateModalContentsError') }}
      </p>
      <p v-if="noLinkedBatch" class="pack-modal-hint text-muted">
        {{ t('activities.packList.returnCrateModalNoBatchHint') }}
      </p>
      <p
        v-else-if="partition.hasWarehouseTemplate && partition.extraLines.length > 0"
        class="pack-modal-hint pack-modal-hint--warn"
      >
        {{ t('activities.packList.returnCrateModalExtraHint') }}
      </p>

      <div v-if="partition.shellQty > 0" class="pack-return-crate-block">
        <h4 class="pack-return-crate-subtitle">{{ t('activities.packList.returnCrateModalShellSection') }}</h4>
        <ul class="pack-return-crate-list">
          <li>
            <span class="pack-return-crate-line-name">{{
              partition.shellMaterialName || t('activities.packList.shellMaterialLine')
            }}</span>
            <span class="pack-return-crate-line-qty text-muted">
              {{ t('activities.packList.returnCrateModalStillQty', { n: partition.shellQty }) }}
            </span>
            <span v-if="partition.shellIsExtra" class="pack-return-crate-badge">{{
              t('activities.packList.returnCrateModalBadgeExtra')
            }}</span>
          </li>
        </ul>
      </div>

      <div v-if="partition.extraLines.length > 0" class="pack-return-crate-block">
        <h4 class="pack-return-crate-subtitle">{{ t('activities.packList.returnCrateModalExtraSection') }}</h4>
        <p class="pack-return-crate-note text-muted">{{ t('activities.packList.returnCrateModalExtraNote') }}</p>
        <ul class="pack-return-crate-list">
          <li v-for="ci in partition.extraLines" :key="'ret-extra-' + ci.id">
            <span class="pack-return-crate-line-name">{{
              ci.material_name || t('activities.common.material')
            }}</span>
            <span class="pack-return-crate-line-qty text-muted">
              {{
                t('activities.packList.returnCrateModalLineQty', {
                  still: lineRemainingReturn(ci),
                  issued: ci.quantity_issued ?? 0,
                })
              }}
            </span>
          </li>
        </ul>
      </div>

      <div v-if="partition.standardLines.length > 0" class="pack-return-crate-block">
        <h4 class="pack-return-crate-subtitle">{{ t('activities.packList.returnCrateModalStandardSection') }}</h4>
        <ul class="pack-return-crate-list">
          <li v-for="ci in partition.standardLines" :key="'ret-std-' + ci.id">
            <span class="pack-return-crate-line-name">{{
              ci.material_name || t('activities.common.material')
            }}</span>
            <span class="pack-return-crate-line-qty text-muted">
              {{
                t('activities.packList.returnCrateModalLineQty', {
                  still: lineRemainingReturn(ci),
                  issued: ci.quantity_issued ?? 0,
                })
              }}
            </span>
          </li>
        </ul>
      </div>

      <p
        v-if="
          partition.shellQty < 1 &&
          partition.extraLines.length < 1 &&
          partition.standardLines.length < 1
        "
        class="pack-modal-hint text-muted"
      >
        {{ t('activities.packList.returnCrateModalEmptyLines') }}
      </p>
    </template>

    <label class="pack-return-crate-check">
      <input
        :checked="checked"
        type="checkbox"
        @change="emit('update:checked', ($event.target as HTMLInputElement).checked)"
      />
      <span>{{ t('activities.packList.returnCrateModalConfirmCheckbox') }}</span>
    </label>

    <template #footer>
      <PackModalFooter
        :primary-label="t('activities.packList.returnCrateModalSubmit')"
        :primary-disabled="submitDisabled"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="emit('submit')"
      />
    </template>
  </PackWorkflowModal>
</template>
