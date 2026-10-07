<template>
  <section v-if="identities.length > 0" class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-external-identities">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.externalIdentities.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.externalIdentities.hint') }}</p>
    <ul class="flex flex-col gap-2">
      <li
        v-for="identity in identities"
        :key="identity.provider"
        class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
      >
        <span class="min-w-0 flex-1 font-medium text-slate-800">{{ identity.label }}</span>
        <span class="text-[0.78rem] text-slate-500">
          {{ t('layout.profileModal.externalIdentities.linkedAt', { date: formatDate(identity.linked_at) }) }}
        </span>
        <EButton
          v-if="identity.provider === 'midata'"
          variant="text"
          size="small"
          :loading="busy"
          :disabled="busy || !identity.can_disconnect"
          :title="identity.can_disconnect ? '' : t('layout.profileModal.externalIdentities.lastMethod')"
          @click="disconnect(identity)"
        >
          {{ t('layout.profileModal.externalIdentities.disconnect') }}
        </EButton>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
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
