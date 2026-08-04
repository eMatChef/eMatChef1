<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { VirtualComboSelfProvidedHint } from '@/utils/virtualComboSelfProvidedHints'

const props = defineProps<{
  hints: VirtualComboSelfProvidedHint[]
  /** Am Step issue: Checkbox «Selbst mitgebracht?» (SPEC §17.6) */
  showIssueAck?: boolean
  activityId: string
}>()

const { t } = useI18n()

const issueAckByItemId = ref<Record<string, boolean>>({})

function storageKey(activityId: string): string {
  return `ematchef.journey.selfProvidedIssueAck.${activityId}`
}

function loadAck(activityId: string): Record<string, boolean> {
  try {
    const raw = sessionStorage.getItem(storageKey(activityId))
    if (!raw) return {}
    const parsed = JSON.parse(raw) as unknown
    if (!parsed || typeof parsed !== 'object') return {}
    return parsed as Record<string, boolean>
  } catch {
    return {}
  }
}

function persistAck(activityId: string, map: Record<string, boolean>): void {
  try {
    sessionStorage.setItem(storageKey(activityId), JSON.stringify(map))
  } catch {
    /* ignore quota */
  }
}

watch(
  () => props.activityId,
  (id) => {
    issueAckByItemId.value = loadAck(id)
  },
  { immediate: true },
)

function isIssueAcked(hint: VirtualComboSelfProvidedHint): boolean {
  return Boolean(issueAckByItemId.value[hint.activityItemId])
}

function setIssueAck(hint: VirtualComboSelfProvidedHint, value: boolean): void {
  const next = { ...issueAckByItemId.value, [hint.activityItemId]: value }
  issueAckByItemId.value = next
  persistAck(props.activityId, next)
}

const hasHints = computed(() => props.hints.length > 0)
</script>

<template>
  <div
    v-if="hasHints"
    class="material-journey-self-provided"
    data-testid="material-journey-self-provided"
  >
    <h3 class="material-journey-self-provided__title">
      {{ t('activities.packList.selfProvidedLeaderTitle') }}
    </h3>
    <p class="material-journey-self-provided__intro text-muted">
      {{ t('activities.packList.selfProvidedLeaderIntro') }}
    </p>
    <div
      v-for="hint in hints"
      :key="hint.activityItemId"
      class="material-journey-self-provided__block"
    >
      <strong class="material-journey-self-provided__combo">{{ hint.comboName }}</strong>
      <ul class="material-journey-self-provided__list">
        <li v-for="(item, itemIdx) in hint.items" :key="itemIdx">
          {{ item.total_qty }}× {{ item.name }}
        </li>
      </ul>
      <label
        v-if="showIssueAck"
        class="material-journey-self-provided__ack"
      >
        <input
          type="checkbox"
          :checked="isIssueAcked(hint)"
          @change="setIssueAck(hint, ($event.target as HTMLInputElement).checked)"
        />
        <span>{{ t('activities.materialJourney.selfProvided.issueAck') }}</span>
      </label>
    </div>
  </div>
</template>

<style scoped>
.material-journey-self-provided {
  margin: 0 0 12px;
  padding: 12px 14px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-surface-muted, #f3f4f6) 88%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-border, #d1d5db) 70%, transparent);
}

.material-journey-self-provided__title {
  margin: 0 0 4px;
  font-size: 0.95rem;
  font-weight: 600;
}

.material-journey-self-provided__intro {
  margin: 0 0 10px;
  font-size: 0.85rem;
}

.material-journey-self-provided__block + .material-journey-self-provided__block {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid color-mix(in srgb, var(--color-border, #d1d5db) 60%, transparent);
}

.material-journey-self-provided__combo {
  display: block;
  margin-bottom: 4px;
  font-size: 0.9rem;
}

.material-journey-self-provided__list {
  margin: 0;
  padding-left: 1.2rem;
  font-size: 0.88rem;
}

.material-journey-self-provided__ack {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 8px;
  font-size: 0.88rem;
  cursor: pointer;
}

.material-journey-self-provided__ack input {
  margin-top: 2px;
}
</style>
