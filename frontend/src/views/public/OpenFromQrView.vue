<template>
  <div class="open-from-qr">
    <p class="open-from-qr-text">Weiterleitung…</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getPublicBatchByCode,
  getPublicMaterialByCode,
} from '@/api/public/publicLookup'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

function qrPublicOrigin(): string {
  const host = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim()
  if (!host || typeof window === 'undefined') return ''
  return `${window.location.protocol}//${host}`
}

onMounted(async () => {
  const type = String(route.query.type || '').toLowerCase().trim()
  const code = String(route.query.code || '').trim()
  if ((type !== 'm' && type !== 'b') || !code) {
    await router.replace('/login')
    return
  }

  const qrOrigin = qrPublicOrigin()
  const fallbackToPublicQr = async () => {
    if (qrOrigin) {
      window.location.replace(
        `${qrOrigin}/i/${type}/${encodeURIComponent(code)}?public=1`
      )
      return
    }
    await router.replace({
      path: `/i/${type}/${encodeURIComponent(code)}`,
      query: { public: '1' },
    })
  }

  if (!authStore.isLoggedIn) {
    await fallbackToPublicQr()
    return
  }

  const isSuperAdmin = authStore.userRoles?.includes('ROLE_SUPERADMIN') ?? false

  try {
    const data =
      type === 'b' ? await getPublicBatchByCode(code) : await getPublicMaterialByCode(code)

    const departmentId = data.department.id
    const materialId = data.material.id
    const membership = authStore.departments.find((d) => d.department_id === departmentId)

    if (!isSuperAdmin && !membership) {
      await fallbackToPublicQr()
      return
    }

    const query: Record<string, string> = {}
    if (data.entity_type === 'batch' && 'batch' in data && data.batch?.id) {
      query.batch = data.batch.id
    }

    await router.replace({
      path: `/${departmentId}/materials/${materialId}`,
      query: Object.keys(query).length ? query : undefined,
    })
  } catch {
    await fallbackToPublicQr()
  }
})
</script>

<style scoped>
.open-from-qr {
  min-height: 40vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.open-from-qr-text {
  margin: 0;
  color: var(--color-text-muted, #6b7280);
  font-size: 0.95rem;
}
</style>
