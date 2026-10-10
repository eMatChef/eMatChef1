<template>
  <EDialog
    v-if="isOpen"
    :model-value="true"
    max-width="960"
    content-class="profile-modal-dialog"
    :before-close="confirmClose"
    data-onboarding="profile-modal"
    @update:model-value="onDialogUpdate"
  >
    <template #title>
      <div class="profile-modal__header">
        <div class="profile-modal__title">
          <span>{{ t('profile.page.title') }}</span>
          <span class="profile-modal__actions">
            <EButton variant="text" size="small" data-testid="profile-open-page" @click="openAsPage">
              {{ t('profile.modal.openAsPage') }}
            </EButton>
            <EButton variant="text" size="small" :aria-label="t('layout.profileModal.closeAria')" data-testid="profile-close" @click="requestClose">
              <v-icon icon="mdi-close" size="20" />
            </EButton>
          </span>
        </div>
        <ProfileTabs mode="modal" :tab="tab" @select="tab = $event" />
      </div>
    </template>
    <ProfileContainer mode="modal" :tab="tab" @update:tab="tab = $event" @close="requestClose" @dirty="dirty = $event" />
  </EDialog>
</template>

<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { EButton, EDialog } from '@/components/form/base'
import { useConfirm } from '@/composables/useConfirm'
import { PROFILE_TABS, useProfileModal } from '@/composables/useProfileContext'
import { PROFILE_FROM_PARAM, profileEntryQuery } from '@/utils/profileReturn'
import ProfileContainer from '@/components/profile/ProfileContainer.vue'
import ProfileTabs from '@/components/profile/ProfileTabs.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const { isOpen, tab, dirty, close } = useProfileModal()

async function confirmClose(): Promise<boolean> {
  if (!dirty.value) return true
  return confirm.confirm({
    title: t('layout.confirm.unsavedTitle'),
    message: t('layout.confirm.unsavedMessage'),
    confirmText: t('common.close'),
    cancelText: t('layout.confirm.back'),
    variant: 'warning',
  })
}

async function requestClose() {
  if (await confirmClose()) close()
}

function onDialogUpdate(open: boolean) {
  if (!open) close()
}

/** Canonical URL of the same area, keeping the page the modal was opened over as the return path. */
async function openAsPage() {
  if (!(await confirmClose())) return
  const routeName = PROFILE_TABS.find((entry) => entry.tab === tab.value)?.routeName ?? 'Profile'
  const query = { ...profileEntryQuery(route) }
  dirty.value = false
  close()
  await router.push({ name: routeName, query: query[PROFILE_FROM_PARAM] ? query : undefined })
}

// Navigation while the modal is open (browser back, links, MFA redirect): confirm unsaved input, then close the modal.
let removeGuard: (() => void) | null = null
watch(
  isOpen,
  (open) => {
    removeGuard?.()
    removeGuard = null
    if (!open) return
    removeGuard = router.beforeEach(async () => {
      if (!(await confirmClose())) return false
      close()
      return true
    })
  },
  { immediate: true },
)
onBeforeUnmount(() => removeGuard?.())
</script>

<style scoped>
.profile-modal__header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.profile-modal__title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.profile-modal__actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
</style>

<style>
/* Grosser Dialog: weiter oben und mit mehr nutzbarer Höhe; nur der Inhaltsbereich scrollt (EDialog: scrollable). */
.v-overlay__content.profile-modal-dialog {
  top: 2vh !important;
  margin-top: 0;
  margin-bottom: 0;
  max-height: 96vh;
  max-height: 96dvh;
  width: calc(100% - 24px);
}

.profile-modal-dialog > .v-card {
  max-height: inherit;
}

/* Kein Polster unten: Speichern/Abbrechen kleben direkt am unteren Rand des scrollenden Inhalts */
.profile-modal-dialog .e-dialog__body {
  padding-bottom: 0;
}
</style>
