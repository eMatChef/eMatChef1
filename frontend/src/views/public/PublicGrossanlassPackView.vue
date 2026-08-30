<template>
  <div class="public-layout">
    <header class="public-header" role="banner">
      <a :href="publicHomeUrl" class="public-brand" :title="t('public.lookup.brandTitle')">
        <EmcLogoMark size="sm" />
        <span class="public-brand-text">eMatChef</span>
      </a>
    </header>

    <main class="public-page">
      <p v-if="loading" class="muted">{{ t('public.lookup.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <template v-else-if="data">
        <p class="hint">{{ t('public.lookup.packScanHint') }}</p>
        <section class="public-card">
          <h1>{{ t('public.lookup.packTitle') }}</h1>
          <p class="code">{{ data.public_code }}</p>
          <PublicQrTag
            v-if="data.qr_url"
            :url="data.qr_url"
            :code="data.public_code"
            :size="160"
            :image-label="data.public_code"
            :image-entity-id="data.id"
          />
          <p v-if="data.warning" class="warn">{{ data.warning }}</p>
          <ul class="lines">
            <li v-for="line in data.lines" :key="line.id">
              {{ line.qty_packed }}/{{ line.qty_needed }} × {{ line.label }}
            </li>
          </ul>
          <p class="status">{{ t(`public.lookup.packStatus.${data.status}`) }}</p>
          <EButton
            v-if="authStore.isLoggedIn && data.department?.id"
            variant="primary"
            :loading="busy"
            @click="startTrip"
          >
            {{ t('public.lookup.packStart') }}
          </EButton>
          <p v-else class="muted">{{ t('public.lookup.packLoginHint') }}</p>
        </section>
      </template>
    </main>
    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { getPublicGaPackByCode } from '@/api/public/publicLookup'
import {
  rememberActivePack,
  scanStartGrossanlassPack,
  type GaLogisticsPack,
} from '@/api/grossanlassLogistics'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<GaLogisticsPack | null>(null)
const busy = ref(false)

const packCode = computed(() => String(route.params.packCode || '').trim())
const publicHomeUrl = computed(() => {
  const host = window.location.hostname.toLowerCase()
  if (host.includes('localhost') || host.includes('127.0.0.1')) return window.location.origin
  return 'https://ematchef.ch'
})

async function load() {
  if (!packCode.value) return
  loading.value = true
  error.value = null
  try {
    data.value = await getPublicGaPackByCode(packCode.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('public.lookup.notFound')
  } finally {
    loading.value = false
  }
}

async function startTrip() {
  const pack = data.value
  const departmentId = pack?.department?.id
  if (!pack || !departmentId || busy.value) return
  busy.value = true
  try {
    const next = await scanStartGrossanlassPack(departmentId, pack.id)
    data.value = { ...pack, ...next }
    rememberActivePack({ packId: pack.id, departmentId, publicCode: pack.public_code })
    toast.success(t('public.lookup.packStarted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('public.lookup.scanError'))
  } finally {
    busy.value = false
  }
}

watch(packCode, () => { void load() })
onMounted(load)
</script>

<style scoped>
.public-layout { min-height: 100vh; display: flex; flex-direction: column; background: #f8fafc; }
.public-header { display: flex; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb; }
.public-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; font-weight: 700; }
.public-page { flex: 1; padding: 24px 16px; max-width: 480px; margin: 0 auto; width: 100%; }
.public-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
h1 { margin: 0 0 8px; font-size: 1.15rem; }
.code, .status, .hint, .muted { color: #64748b; font-size: 0.88rem; }
.warn { color: #b45309; font-size: 0.88rem; }
.error { color: #b91c1c; }
.lines { margin: 12px 0; padding-left: 18px; }
</style>
