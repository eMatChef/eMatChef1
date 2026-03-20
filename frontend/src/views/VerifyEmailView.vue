<template>
  <div class="verify-page">
    <div class="verify-card">
      <h1>E-Mail bestaetigen</h1>
      <p v-if="loading">Verifikationslink wird geprueft...</p>
      <p v-else-if="success" class="success">{{ success }}</p>
      <p v-else class="error">{{ error || 'Verifikation fehlgeschlagen.' }}</p>

      <router-link class="btn" to="/">Zur Anmeldung</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { verifyEmail } from '@/api/auth'

const route = useRoute()
const loading = ref(true)
const success = ref<string | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  const token = String(route.query.token || '')
  if (!token) {
    loading.value = false
    error.value = 'Kein Verifikations-Token vorhanden.'
    return
  }

  try {
    const res = await verifyEmail(token)
    success.value = res.message || 'E-Mail erfolgreich bestaetigt.'
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Verifikationslink ist ungueltig oder abgelaufen.'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.verify-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: #f9fafb; }
.verify-card { max-width: 560px; width: 100%; background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 24px; text-align: center; }
.success { color: #166534; }
.error { color: #b91c1c; }
.btn { margin-top: 16px; display: inline-block; padding: 10px 14px; border-radius: 8px; background: #0b7eea; color: white; text-decoration: none; font-weight: 600; }
</style>
