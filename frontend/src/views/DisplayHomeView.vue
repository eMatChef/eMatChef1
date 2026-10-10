<template>
  <div class="display-home">
    <header class="display-home-header">
      <EmcLogoMark size="lg" />
      <h1 class="display-home-title">{{ t('display.home.title') }}</h1>
    </header>

    <main class="display-home-main">
      <div class="display-home-qr" :class="{ 'display-home-qr--loading': !qrDataUrl }">
        <img v-if="qrDataUrl" :src="qrDataUrl" :alt="t('display.home.qrAlt')" width="320" height="320" />
        <span v-else-if="startError" class="display-home-error">{{ startError }}</span>
        <span v-else class="display-home-loading">{{ t('display.home.preparing') }}</span>
      </div>

      <div class="display-home-steps">
        <p class="display-home-lead">{{ t('display.home.lead') }}</p>
        <ol>
          <li>{{ t('display.home.step1') }}</li>
          <li>{{ t('display.home.step2') }}</li>
          <li>{{ t('display.home.step3') }}</li>
        </ol>
        <p v-if="userCode" class="display-home-code">
          {{ t('display.home.verifyCode') }}
          <strong>{{ userCode }}</strong>
        </p>
        <router-link class="display-home-manual" :to="{ name: 'PublicDisplayEntry' }">
          {{ t('display.home.manual') }}
        </router-link>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { pollDisplayPairing, startDisplayPairing, type DisplayPairingStart } from '@/api/displayPairing'

const RETRY_AFTER_ERROR_MS = 10_000

const router = useRouter()
const { t } = useI18n()

const qrDataUrl = ref('')
const userCode = ref('')
const startError = ref<string | null>(null)

let pairing: DisplayPairingStart | null = null
let timer: ReturnType<typeof setTimeout> | null = null
let stopped = false

function schedule(fn: () => void, ms: number) {
  if (stopped) return
  timer = setTimeout(fn, ms)
}

/** Neue Kopplungsanfrage; nach Ablauf oder Abbruch startet der Fernseher selbständig eine neue. */
async function start() {
  if (stopped) return
  pairing = null
  try {
    const next = await startDisplayPairing()
    qrDataUrl.value = await QRCode.toDataURL(next.pair_url, { width: 640, margin: 2 })
    userCode.value = next.user_code
    startError.value = null
    pairing = next
    schedule(poll, next.poll_interval_seconds * 1000)
  } catch {
    startError.value = t('display.home.startError')
    schedule(start, RETRY_AFTER_ERROR_MS)
  }
}

async function poll() {
  if (stopped || !pairing) return
  const current = pairing
  try {
    const result = await pollDisplayPairing(current.request_id, current.poll_secret)
    if (result.status === 'approved') {
      stopped = true
      await router.replace({ name: 'PublicDepartmentDisplay', params: { publicId: result.public_id } })
      return
    }
    if (result.status === 'pending') {
      schedule(poll, current.poll_interval_seconds * 1000)
      return
    }
    // expired / revoked: neuer QR-Code
    qrDataUrl.value = ''
    userCode.value = ''
    void start()
  } catch {
    // Netzwerk- oder Rate-Limit-Fehler: später erneut versuchen, bei Ablauf liefert das Backend 'expired'.
    schedule(poll, RETRY_AFTER_ERROR_MS)
  }
}

onMounted(() => {
  void start()
})

onBeforeUnmount(() => {
  stopped = true
  if (timer) clearTimeout(timer)
})
</script>

<style scoped>
.display-home {
  min-height: 100vh;
  padding: 32px 48px;
  background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
  color: #0f172a;
  display: flex;
  flex-direction: column;
}

.display-home-header {
  display: flex;
  align-items: center;
  gap: 20px;
}

.display-home-title {
  margin: 0;
  font-size: clamp(1.6rem, 3vw, 2.6rem);
  font-weight: 800;
}

.display-home-main {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 64px;
}

.display-home-qr {
  width: 360px;
  height: 360px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(15, 23, 42, 0.12);
}

.display-home-loading,
.display-home-error {
  color: #64748b;
  text-align: center;
  padding: 16px;
}

.display-home-steps {
  max-width: 420px;
  font-size: 1.15rem;
  line-height: 1.5;
}

.display-home-lead {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0 0 12px;
}

.display-home-steps ol {
  padding-left: 1.4rem;
  margin: 0 0 20px;
}

.display-home-code {
  color: #475569;
}

.display-home-code strong {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  letter-spacing: 0.2em;
  font-size: 1.5rem;
  color: #0f172a;
}

.display-home-manual {
  font-size: 0.95rem;
  color: #64748b;
}

@media (max-width: 720px) {
  .display-home {
    padding: 20px;
  }
  .display-home-main {
    gap: 24px;
  }
  .display-home-qr {
    width: 280px;
    height: 280px;
  }
  .display-home-qr img {
    width: 260px;
    height: 260px;
  }
}
</style>
