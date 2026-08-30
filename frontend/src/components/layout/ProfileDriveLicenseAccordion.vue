<template>
  <details class="profile-accordion" :open="open || undefined">
    <summary class="profile-accordion__summary">{{ t('layout.profileModal.driveSection') }}</summary>
    <div class="profile-accordion__body">
      <p class="drive-hint">{{ t('layout.profileModal.driveHint') }}</p>
      <p v-if="loadError" class="drive-error">{{ loadError }}</p>
      <template v-else>
        <section
          v-for="group in GA_DRIVE_CATEGORY_GROUPS"
          :key="group.id"
          class="drive-group"
        >
          <h4>{{ t(`grossanlass.chain.drive.groups.${group.id}`) }}</h4>
          <label v-for="code in group.codes" :key="code" class="drive-item">
            <input
              type="checkbox"
              :checked="draft.includes(code)"
              @change="toggle(code, ($event.target as HTMLInputElement).checked)"
            >
            <span>{{ t(`grossanlass.chain.drive.classes.${code}`) }}</span>
          </label>
        </section>
        <label class="drive-until">
          <span>{{ t('layout.profileModal.driveValidUntil') }}</span>
          <input v-model="validUntil" type="date">
        </label>
        <div v-if="license?.document" class="drive-doc">
          <a :href="license.document.url" target="_blank" rel="noopener">
            {{ license.document.original_name }}
          </a>
          <EButton variant="text" size="small" :loading="busy" @click="removeProof">
            {{ t('grossanlass.chain.drive.removeScan') }}
          </EButton>
        </div>
        <label class="drive-upload">
          <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="onFile">
          {{ t('grossanlass.chain.drive.uploadScan') }}
        </label>
        <EButton variant="primary" size="small" :loading="busy" class="drive-save" @click="save">
          {{ t('layout.profileModal.driveSave') }}
        </EButton>
      </template>
    </div>
  </details>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  deleteDriveLicenseProof,
  getDriveLicense,
  saveDriveLicense,
  uploadDriveLicenseProof,
  type UserDriveLicense,
} from '@/api/driveLicense'
import { GA_DRIVE_CATEGORY_GROUPS } from '@/views/grossanlass/grossanlassDriveCategories'

const props = defineProps<{ open?: boolean }>()

const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()

const license = ref<UserDriveLicense | null>(null)
const draft = ref<string[]>([])
const validUntil = ref('')
const busy = ref(false)
const loadError = ref('')

const profileId = () => authStore.profileId || authStore.profile?.id || ''

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
  },
  { immediate: true },
)

async function load() {
  const id = profileId()
  if (!id) return
  loadError.value = ''
  try {
    const row = await getDriveLicense(id)
    license.value = row
    draft.value = [...(row.drive_classes ?? [])]
    validUntil.value = row.valid_until || ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    loadError.value = err.response?.data?.error || t('layout.profileModal.driveLoadError')
  }
}

function toggle(code: string, on: boolean) {
  if (on && !draft.value.includes(code)) {
    draft.value = [...draft.value, code]
    return
  }
  if (!on) draft.value = draft.value.filter((item) => item !== code)
}

async function save() {
  const id = profileId()
  if (!id || busy.value) return
  busy.value = true
  try {
    license.value = await saveDriveLicense(id, {
      drive_classes: draft.value,
      valid_until: validUntil.value || null,
    })
    toast.success(t('layout.profileModal.driveSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('layout.profileModal.driveSaveError'))
  } finally {
    busy.value = false
  }
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  const id = profileId()
  if (!file || !id) return
  busy.value = true
  try {
    license.value = await uploadDriveLicenseProof(id, file)
    toast.success(t('layout.profileModal.driveSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('layout.profileModal.driveSaveError'))
  } finally {
    busy.value = false
  }
}

async function removeProof() {
  const id = profileId()
  if (!id) return
  busy.value = true
  try {
    license.value = await deleteDriveLicenseProof(id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('layout.profileModal.driveSaveError'))
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.profile-accordion {
  margin: 0 0 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
  overflow: hidden;
}
.profile-accordion__summary {
  cursor: pointer;
  list-style: none;
  padding: 12px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  user-select: none;
}
.profile-accordion__summary::-webkit-details-marker { display: none; }
.profile-accordion__summary::after {
  content: '▾';
  float: right;
  color: #94a3b8;
}
.profile-accordion[open] > .profile-accordion__summary::after {
  transform: rotate(-180deg);
}
.profile-accordion__body {
  padding: 0 14px 14px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}
.drive-hint {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.82rem;
}
.drive-error { color: #b91c1c; font-size: 0.85rem; }
.drive-group { margin-bottom: 12px; }
.drive-group h4 {
  margin: 0 0 6px;
  font-size: 0.82rem;
  font-weight: 700;
}
.drive-item {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 0.82rem;
  padding: 3px 0;
}
.drive-until {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.82rem;
  margin: 8px 0;
}
.drive-until input {
  max-width: 180px;
  padding: 6px 8px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}
.drive-doc {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 0.82rem;
  margin-bottom: 8px;
}
.drive-upload {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #1d4ed8;
  cursor: pointer;
}
.drive-upload input { font-size: 0.8rem; color: #334155; }
.drive-save { margin-top: 12px; display: block; }
</style>
