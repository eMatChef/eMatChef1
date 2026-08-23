<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.chain.returnIntro') }}</p>

    <table class="data-table">
      <thead>
        <tr>
          <th>{{ t('grossanlass.chain.colItem') }}</th>
          <th>{{ t('grossanlass.chain.colFirm') }}</th>
          <th>{{ t('grossanlass.chain.colDue') }}</th>
          <th>{{ t('grossanlass.chain.colStatus') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in returns" :key="row.id">
          <td>{{ row.name }}</td>
          <td>{{ row.firm }}</td>
          <td>{{ row.due }}</td>
          <td>
            <span class="chip" :class="{ ok: row.returned }">
              {{ row.returned ? t('grossanlass.chain.returned') : t('grossanlass.chain.openReturn') }}
            </span>
          </td>
          <td>
            <EButton
              variant="primary"
              size="small"
              :disabled="row.returned"
              @click="onReturn(row.id)"
            >
              {{ t('grossanlass.chain.markReturned') }}
            </EButton>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton } from '@/components/form/base'
import { listFirmReturns, markReturned } from '@/views/grossanlass/grossanlassChainPreviewStore'

const { t } = useI18n()
const toast = useToast()
const returns = computed(() => listFirmReturns())

function onReturn(id: string) {
  markReturned(id)
  toast.success(t('grossanlass.chain.returnedToast'))
}
</script>

<style scoped>
.ga-preview-page { padding: 4px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; }
.chip { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #ffedd5; color: #c2410c; }
.chip.ok { background: #dcfce7; color: #166534; }
</style>
