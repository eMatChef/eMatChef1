<template>
  <div class="verify-page">
    <div class="verify-container">
      <ECard class="verify-card" variant="elevated">
        <div class="verify-header">
          <div class="verify-logo">
            <EmcLogoMark size="lg" />
          </div>
          <h1 class="verify-title">{{ t('verifyEmail.title') }}</h1>
        </div>

        <ELoadingState
          v-if="loading"
          variant="inline"
          :message="t('verifyEmail.checking')"
        />

        <template v-else>
          <v-alert
            v-if="success"
            type="success"
            variant="tonal"
            density="compact"
            class="verify-alert"
          >
            {{ success }}
          </v-alert>

          <v-alert
            v-else
            type="error"
            variant="tonal"
            density="compact"
            class="verify-alert"
          >
            {{ error || t('verifyEmail.failedFallback') }}
          </v-alert>

          <EButton variant="primary" to="/" class="verify-action">
            {{ t('verifyEmail.backToLogin') }}
          </EButton>
        </template>
      </ECard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { verifyEmail } from '@/api/auth'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ECard } from '@/components/form/base'

defineOptions({ name: 'VerifyEmailView' })

const route = useRoute()
const { t } = useI18n()
const loading = ref(true)
const success = ref<string | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  const token = String(route.query.token || '')
  if (!token) {
    loading.value = false
    error.value = t('verifyEmail.noToken')
    return
  }

  try {
    const res = await verifyEmail(token)
    success.value = res.message || t('verifyEmail.successFallback')
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    error.value = ax.response?.data?.error || t('verifyEmail.invalidLink')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.verify-page {
  min-height: calc(100dvh - 36px);
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(160deg, #f1f5f9 0%, #e2e8f0 100%);
  padding: 24px;
}

.verify-container {
  width: 100%;
  max-width: 560px;
}

.verify-card {
  border-radius: 16px;
  padding: 32px;
  text-align: center;
}

.verify-card.e-card {
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
}

.verify-header {
  margin-bottom: 24px;
}

.verify-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.verify-title {
  font-size: 28px;
  line-height: 1.2;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.verify-alert {
  text-align: left;
  font-size: 14px;
}

.verify-action {
  margin-top: 20px;
}

@media (max-width: 640px) {
  .verify-card {
    padding: 22px;
  }

  .verify-title {
    font-size: 24px;
  }
}
</style>
