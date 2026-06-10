<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import PackShellCheckToggle from '@/components/activities/PackShellCheckToggle.vue'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import { shellForwardExpectedQty, shellForwardLineKey } from '@/components/activities/packCrateForwardCheck'

const props = defineProps<{
  open: boolean
  label: string
  sections: PackCrateShellPeekSection[]
  openIssueLabels: string[]
  submitting: boolean
}>()

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()

const { t } = useI18n()
const checkedByKey = ref<Record<string, boolean>>({})

type ChecklistRow = {
  key: string
  materialName: string
  quantity: number
}

const rows = computed((): ChecklistRow[] => {
  const out: ChecklistRow[] = []
  for (const sec of props.sections) {
    const isExtra = sec.subsectionKey === 'extra'
    for (const line of sec.lines) {
      out.push({
        key: shellForwardLineKey(sec.subsectionKey, line.id),
        materialName: line.materialName,
        quantity: shellForwardExpectedQty(isExtra, line.quantity),
      })
    }
  }
  return out
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      checkedByKey.value = {}
      return
    }
    const next: Record<string, boolean> = {}
    for (const row of rows.value) {
      next[row.key] = false
    }
    checkedByKey.value = next
  },
)

const allChecked = computed(
  () => rows.value.length > 0 && rows.value.every((row) => checkedByKey.value[row.key] === true),
)

function toggleRow(key: string): void {
  checkedByKey.value = { ...checkedByKey.value, [key]: !checkedByKey.value[key] }
}
</script>

<template>
  <PackWorkflowModal :open="open" size="md" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.physComboStoreChecklistTitle', { label }) }}</template>
    <template #intro>
      <p class="pack-modal-hint text-muted">{{ t('activities.packList.physComboStoreChecklistIntro') }}</p>
      <div v-if="openIssueLabels.length > 0" class="pack-modal-hint pack-modal-hint--warn" role="status">
        <strong>{{ t('activities.packList.physComboStoreOpenIssuesTitle') }}</strong>
        <ul class="pack-shell-back-ul">
          <li v-for="(issue, idx) in openIssueLabels" :key="'store-issue-' + idx">{{ issue }}</li>
        </ul>
      </div>
    </template>

    <ul v-if="rows.length > 0" class="pack-phys-combo-store-list">
      <li v-for="row in rows" :key="row.key" class="pack-phys-combo-store-row">
        <PackShellCheckToggle
          ok-only
          :ok-active="checkedByKey[row.key] === true"
          :ok-title="t('activities.packList.physComboStoreCheckRowOk')"
          :ok-aria-label="t('activities.packList.physComboStoreCheckRowOk')"
          @ok="toggleRow(row.key)"
        />
        <span class="pack-phys-combo-store-row__name">{{ row.materialName }}</span>
        <span class="pack-phys-combo-store-row__qty text-muted">{{
          t('activities.packList.crateCheckWasSoll', { n: row.quantity })
        }}</span>
      </li>
    </ul>
    <p v-else class="pack-modal-hint text-muted">{{ t('activities.packList.physComboStoreChecklistEmpty') }}</p>

    <template #footer>
      <PackModalFooter
        :cancel-label="t('common.cancel')"
        :submit-label="t('activities.packList.physComboStoreCompleteSet')"
        :submit-disabled="!allChecked || submitting"
        :submitting="submitting"
        @cancel="emit('cancel')"
        @submit="emit('confirm')"
      />
    </template>
  </PackWorkflowModal>
</template>

<style scoped>
.pack-phys-combo-store-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.pack-phys-combo-store-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pack-phys-combo-store-row__name {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
