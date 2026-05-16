<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackIssueQuickActions' })

defineProps<{
  isConsumable: boolean
}>()

const emit = defineEmits<{
  consumed: []
  loss: []
  repair: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="pack-card-issue-quick-row">
    <template v-if="isConsumable">
      <button type="button" class="btn-issue-quick btn-issue-consumed" @click.stop="emit('consumed')">
        {{ t('activities.common.issueConsumed') }}
      </button>
    </template>
    <template v-else>
      <button type="button" class="btn-issue-quick btn-issue-loss" @click.stop="emit('loss')">
        {{ t('activities.common.issueLoss') }}
      </button>
      <button type="button" class="btn-issue-quick btn-issue-repair" @click.stop="emit('repair')">
        {{ t('activities.common.issueRepair') }}
      </button>
    </template>
  </div>
</template>
