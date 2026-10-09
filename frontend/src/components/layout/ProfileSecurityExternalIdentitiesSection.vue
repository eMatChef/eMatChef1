<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-external-identities">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.externalIdentities.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.externalIdentities.hint') }}</p>
    <ul class="flex flex-col gap-2" data-testid="identities">
      <li
        v-for="row in rows"
        :key="row.provider"
        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        :data-testid="`identity-${row.provider}`"
      >
        <span class="min-w-0 flex-1 font-medium text-slate-800">{{ t(`layout.profileModal.externalIdentities.providers.${row.provider}`) }}</span>
        <span
          class="rounded-full px-2 py-0.5 text-[0.72rem] font-semibold"
          :class="row.identity ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
        >
          {{ row.identity ? t('layout.profileModal.externalIdentities.linked') : t('layout.profileModal.externalIdentities.notLinked') }}
        </span>
        <span v-if="row.identity" class="text-[0.78rem] text-slate-500">
          {{ t('layout.profileModal.externalIdentities.linkedAt', { date: formatDate(row.identity.linked_at) }) }}
        </span>
        <template v-if="row.provider === 'midata'">
          <EButton
            v-if="row.identity"
            variant="text"
            size="small"
            :loading="busy"
            :disabled="busy || !row.identity.can_disconnect"
            :title="row.identity.can_disconnect ? '' : t('layout.profileModal.externalIdentities.lastMethod')"
            data-testid="disconnect-midata"
            @click="disconnect(row.identity)"
          >
            {{ t('layout.profileModal.externalIdentities.disconnect') }}
          </EButton>
          <EButton v-else variant="text" size="small" data-testid="connect-midata" @click="connectMiData">
            {{ t('layout.profileModal.externalIdentities.connect') }}
          </EButton>
        </template>
      </li>
    </ul>
    <p v-if="identityOf('midata') && !identityOf('midata')?.can_disconnect" class="mt-2 text-[0.78rem] text-amber-800" data-testid="last-method">
      {{ t('layout.profileModal.externalIdentities.lastMethod') }}
    </p>
    <p class="mt-2 text-[0.78rem] text-slate-500">{{ t('layout.profileModal.externalIdentities.googleNote') }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { midataLinkStartUrl } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  disconnectExternalIdentity,
  getExternalIdentities,
  type ExternalIdentitySummary,
} from '@/api/profileSecurity'

const props = defineProps<{ open?: boolean }>()

const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const identities = ref<ExternalIdentitySummary[]>([])
const busy = ref(false)

const PROVIDERS = ['google', 'midata'] as const
const rows = computed(() => PROVIDERS.map((provider) => ({ provider, identity: identityOf(provider) })))

function identityOf(provider: string): ExternalIdentitySummary | undefined {
  return identities.value.find((i) => i.provider === provider)
}

const profileId = () => authStore.profileId || authStore.profile?.id || ''

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
  },
  { immediate: true },
)

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(locale.value)
}

async function load() {
  const id = profileId()
  if (!id) return
  try {
    identities.value = await getExternalIdentities(id)
  } catch {
    identities.value = []
  }
}

/** Bestehender MiData-Link-Flow (Session-Cookie); Rückkehr auf die aktuelle Seite. */
function connectMiData() {
  window.location.assign(midataLinkStartUrl(window.location.pathname))
}

async function disconnect(identity: ExternalIdentitySummary) {
  const id = profileId()
  if (!id) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.externalIdentities.confirmTitle'),
    message: t('layout.profileModal.externalIdentities.confirmMessage'),
    confirmText: t('layout.profileModal.externalIdentities.disconnect'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  busy.value = true
  try {
    identities.value = await disconnectExternalIdentity(id, identity.provider)
    toast.success(t('layout.profileModal.externalIdentities.disconnected'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string; message?: string } } }
    toast.error(err.response?.data?.message || err.response?.data?.error || t('layout.profileModal.externalIdentities.error'))
  } finally {
    busy.value = false
  }
}
</script>
