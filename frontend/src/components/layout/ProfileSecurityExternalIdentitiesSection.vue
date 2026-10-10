<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-external-identities">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.externalIdentities.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.externalIdentities.hint') }}</p>

    <p v-if="loadError" class="mb-2 text-[0.85rem] text-red-700" data-testid="identities-error">{{ loadError }}</p>
    <p v-if="notice" class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-[0.82rem] text-amber-900" role="status" data-testid="identities-notice">
      {{ notice }}
    </p>

    <p v-if="loaded && identities.length === 0 && !loadError" class="mb-2 text-[0.82rem] text-slate-500" data-testid="no-identities">
      {{ t('layout.profileModal.externalIdentities.empty') }}
    </p>
    <ul v-else-if="identities.length > 0" class="mb-2 flex flex-col gap-2" data-testid="identities">
      <li
        v-for="identity in identities"
        :key="identity.id"
        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        :data-testid="`identity-${identity.id}`"
      >
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-slate-800">{{ identity.label }}</span>
            <span v-if="identity.display_name" class="text-slate-700">{{ identity.display_name }}</span>
          </div>
          <p class="mt-0.5 break-all text-[0.78rem] text-slate-500">
            <span v-if="identity.email">{{ identity.email }} · </span>
            <span>{{ t('layout.profileModal.externalIdentities.accountHint', { hint: identity.external_id_hint }) }}</span>
            · {{ t('layout.profileModal.externalIdentities.linkedAt', { date: formatDate(identity.linked_at) }) }}
          </p>
          <p v-if="!identity.can_disconnect" class="mt-0.5 text-[0.75rem] text-amber-800" data-testid="last-method">
            {{ t('layout.profileModal.externalIdentities.lastMethod') }}
          </p>
        </div>
        <EButton
          variant="text"
          size="small"
          :loading="busyId === identity.id"
          :disabled="busy || !identity.can_disconnect"
          :title="identity.can_disconnect ? '' : t('layout.profileModal.externalIdentities.lastMethod')"
          data-testid="disconnect"
          @click="disconnect(identity)"
        >
          {{ t('layout.profileModal.externalIdentities.disconnect') }}
        </EButton>
      </li>
    </ul>

    <div ref="menuRoot" class="relative inline-block">
      <EButton
        variant="secondary"
        size="small"
        type="button"
        aria-haspopup="menu"
        :aria-expanded="menuOpen"
        :disabled="busy"
        data-testid="connect-toggle"
        @click="menuOpen = !menuOpen"
      >
        {{ t('layout.profileModal.externalIdentities.connectWith') }}
      </EButton>
      <ul
        v-if="menuOpen"
        class="absolute left-0 z-10 mt-1 min-w-[12rem] rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        role="menu"
        data-testid="connect-menu"
        @keydown.esc="menuOpen = false"
      >
        <li v-for="p in providers" :key="p.provider" role="none">
          <button
            type="button"
            role="menuitem"
            class="block w-full px-3 py-1.5 text-left text-[0.85rem] text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400"
            :disabled="!p.configured"
            :title="p.configured ? '' : t('layout.profileModal.externalIdentities.notConfigured')"
            :data-testid="`connect-${p.provider}`"
            @click="connect(p.provider)"
          >
            {{ p.label }}
          </button>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { PROFILE_FROM_PARAM, isProfilePath, rememberProfileFrom } from '@/utils/profileReturn'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  disconnectExternalIdentity,
  getExternalIdentities,
  startExternalIdentityLink,
  type ExternalIdentitySummary,
  type LinkProvider,
} from '@/api/profileSecurity'

const props = defineProps<{ open?: boolean }>()

const { t, te, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const identities = ref<ExternalIdentitySummary[]>([])
const providers = ref<LinkProvider[]>([])
const loaded = ref(false)
const loadError = ref('')
const notice = ref('')
const busy = ref(false)
const busyId = ref<string | null>(null)
const menuOpen = ref(false)
const menuRoot = ref<HTMLElement | null>(null)

const LINK_RESULT_EVENT = 'emc-profile-security-link-result'
const profileId = () => authStore.profileId || authStore.profile?.id || ''

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
    else menuOpen.value = false
  },
  { immediate: true },
)

function onDocumentClick(event: MouseEvent) {
  if (menuOpen.value && menuRoot.value && !menuRoot.value.contains(event.target as Node)) menuOpen.value = false
}
onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  window.addEventListener(LINK_RESULT_EVENT, onLinkResult)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  window.removeEventListener(LINK_RESULT_EVENT, onLinkResult)
})

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(locale.value)
}

type ApiError = { response?: { data?: { error?: string; message?: string } } }

/** Fehlertext zu einem Backend-Code; bei unbekannten Codes der Server-Text bzw. ein allgemeiner Hinweis. */
function errorText(e: unknown, fallbackKey: string): string {
  const data = (e as ApiError).response?.data
  const key = data?.error ? `layout.profileModal.externalIdentities.errors.${data.error}` : ''
  if (key && te(key)) return t(key)
  return data?.message || t(fallbackKey)
}

async function load() {
  const id = profileId()
  if (!id) return
  loadError.value = ''
  try {
    const data = await getExternalIdentities(id)
    identities.value = data.identities
    providers.value = data.providers
    loaded.value = true
  } catch {
    loadError.value = t('layout.profileModal.externalIdentities.loadError')
  }
}

/** Rückweg aus dem OAuth-Link-Flow (TopHeader wertet die URL aus): Fehlergrund anzeigen, Liste ohne Profil-Reload aktualisieren. */
function onLinkResult(event: Event) {
  const detail = (event as CustomEvent<{ status: string; reason?: string | null }>).detail
  const key = `layout.profileModal.externalIdentities.errors.${detail?.reason ?? 'failed'}`
  notice.value = detail?.status === 'error' ? (te(key) ? t(key) : t('layout.profileModal.externalIdentities.errors.failed')) : ''
  void load()
}

async function connect(provider: string) {
  const id = profileId()
  if (!id || busy.value) return
  menuOpen.value = false
  notice.value = ''
  busy.value = true
  try {
    // Zurück in die aktuelle Seite; das Backend hängt profile_security=1 und das Ergebnis an.
    // Seite: `from` aus der URL. Modal (Seite darunter): die aktuelle Seite ist der Rücksprung.
    const here = isProfilePath(window.location.pathname) ? null : window.location.pathname + window.location.search
    rememberProfileFrom(new URLSearchParams(window.location.search).get(PROFILE_FROM_PARAM) ?? here)
    const url = await startExternalIdentityLink(provider, window.location.pathname)
    window.location.assign(url)
  } catch (e: unknown) {
    notice.value = errorText(e, 'layout.profileModal.externalIdentities.connectError')
    busy.value = false
  }
}

async function disconnect(identity: ExternalIdentitySummary) {
  const id = profileId()
  if (!id || busy.value) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.externalIdentities.confirmTitle', { provider: identity.label }),
    message: t('layout.profileModal.externalIdentities.confirmMessage', { provider: identity.label }),
    confirmText: t('layout.profileModal.externalIdentities.disconnect'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  busy.value = true
  busyId.value = identity.id
  notice.value = ''
  try {
    const data = await disconnectExternalIdentity(id, identity.id)
    identities.value = data.identities
    providers.value = data.providers
    toast.success(t('layout.profileModal.externalIdentities.disconnected'))
    window.dispatchEvent(new CustomEvent('emc-profile-identities-changed'))
  } catch (e: unknown) {
    const message = errorText(e, 'layout.profileModal.externalIdentities.error')
    notice.value = message
    toast.error(message)
    await load()
  } finally {
    busy.value = false
    busyId.value = null
  }
}
</script>
