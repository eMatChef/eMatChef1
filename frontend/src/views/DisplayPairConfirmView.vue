<template>
  <div class="pair-confirm">
    <header class="pair-confirm-header">
      <EmcLogoMark size="sm" />
      <h1 class="pair-confirm-title">{{ t('display.pair.title') }}</h1>
    </header>

    <ELoadingState v-if="loading" variant="inline" :message="t('display.pair.loading')" />

    <ECard v-else-if="done" variant="outlined" class="pair-confirm-panel">
      <p class="pair-confirm-success">{{ t('display.pair.done') }}</p>
    </ECard>

    <ECard v-else-if="requestError" variant="outlined" class="pair-confirm-panel">
      <p class="pair-confirm-error">{{ requestError }}</p>
      <p class="muted">{{ t('display.pair.retryHint') }}</p>
    </ECard>

    <ECard v-else variant="outlined" class="pair-confirm-panel">
      <p>{{ t('display.pair.verify') }}</p>
      <p class="pair-confirm-code">{{ userCode }}</p>

      <p v-if="!screens.length" class="muted">{{ t('display.pair.noScreens') }}</p>
      <template v-else>
        <ESelect
          v-model="selectedScreenId"
          :items="screenItems"
          :label="t('display.pair.selectScreen')"
          hide-details
        />
        <p v-if="submitError" class="pair-confirm-error">{{ submitError }}</p>
        <EButton
          variant="primary"
          block
          :disabled="!selectedScreenId || submitting"
          :loading="submitting"
          @click="confirm"
        >
          {{ t('display.pair.confirm') }}
        </EButton>
      </template>
    </ECard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ECard, ESelect } from '@/components/form/base'
import {
  approveDisplayPairing,
  getDisplayPairingRequest,
  listPairableDisplayScreens,
  type PairableDisplayScreen,
} from '@/api/displayPairing'

const route = useRoute()
const { t } = useI18n()

const token = computed(() => String(route.params.token || ''))
const loading = ref(true)
const requestError = ref<string | null>(null)
const submitError = ref<string | null>(null)
const submitting = ref(false)
const done = ref(false)
const userCode = ref('')
const screens = ref<PairableDisplayScreen[]>([])
const selectedScreenId = ref<string | null>(null)

const screenItems = computed(() =>
  screens.value.map((s) => ({
    value: s.id,
    title: `${s.department_name} · ${s.name}${s.is_grossanlass ? ` (${t('display.pair.grossanlass')})` : ''}`,
  })),
)

function statusOf(err: unknown): number | undefined {
  return (err as { response?: { status?: number } })?.response?.status
}

async function confirm() {
  if (!selectedScreenId.value) return
  submitting.value = true
  submitError.value = null
  try {
    const selected = screens.value.find((s) => s.id === selectedScreenId.value)
    await approveDisplayPairing(token.value, selectedScreenId.value, selected?.name ?? '')
    done.value = true
  } catch (err) {
    const status = statusOf(err)
    if (status === 403) submitError.value = t('display.pair.forbidden')
    else if (status === 410) requestError.value = t('display.pair.expired')
    else submitError.value = t('display.pair.failed')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    const [info, list] = await Promise.all([getDisplayPairingRequest(token.value), listPairableDisplayScreens()])
    userCode.value = info.user_code
    screens.value = list
    selectedScreenId.value = list.length === 1 ? list[0]!.id : null
  } catch (err) {
    requestError.value = statusOf(err) === 410 ? t('display.pair.expired') : t('display.pair.failed')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.pair-confirm {
  min-height: 100vh;
  padding: 20px 16px 32px;
  background: #f8fafc;
}

.pair-confirm-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.pair-confirm-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 800;
}

.pair-confirm-panel {
  max-width: 440px;
  margin: 0 auto;
  padding: 20px !important;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pair-confirm-code {
  margin: 0;
  text-align: center;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: 0.25em;
}

.pair-confirm-success {
  font-weight: 700;
  margin: 0;
}

.pair-confirm-error {
  color: #b91c1c;
  margin: 0;
}

.muted {
  color: #64748b;
}
</style>
