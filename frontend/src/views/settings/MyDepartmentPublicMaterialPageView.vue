<template>
  <div class="settings-page">
    <div class="header">
      <h1>Öffentliche Material-Seite</h1>
      <p class="description">Anzeige und Kontaktoptionen für die öffentliche QR-Seite</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <label class="label" for="department-select">Department auswählen:</label>
      <select id="department-select" v-model="selectedDepartmentId" @change="onDepartmentChange" class="input">
        <option v-for="dept in userDepartments" :key="dept.department_id" :value="dept.department_id">
          {{ dept.department?.name || dept.department_id }}
        </option>
      </select>
    </div>

    <div v-if="!canManageJoinCode" class="card">
      <p class="muted">Du hast keine Berechtigung, diese Einstellungen zu ändern.</p>
    </div>

    <div v-else class="card">
      <div class="field">
        <label class="label">Öffentliche Kontakt-E-Mail (optional)</label>
        <input v-model="publicContactEmail" type="email" class="input" placeholder="material@dein-department.ch" />
      </div>
      <div class="field">
        <label class="label">Öffentliche Notiz (optional)</label>
        <textarea v-model="publicContactNote" class="input" rows="3" placeholder="z.B. Bitte Fundgegenstand mit Foto melden." />
      </div>
      <div class="field toggles">
        <label><input v-model="publicShowContactForm" type="checkbox" /> Kontaktformular anzeigen</label>
        <label><input v-model="publicShowContactEmail" type="checkbox" /> Kontakt-E-Mail anzeigen</label>
        <label><input v-model="publicShowContactNote" type="checkbox" /> Öffentliche Notiz anzeigen</label>
      </div>
      <div class="field">
        <label class="label">Hinweise vom QR-Code (Fund / Kontakt)</label>
        <select v-model="publicFoundContactDelivery" class="input">
          <option value="email">Nur per E-Mail</option>
          <option value="in_app">Nur in der Nachrichtenzentrale (App)</option>
          <option value="both">E-Mail und Nachrichtenzentrale</option>
        </select>
      </div>
      <button class="btn" :disabled="isSavingPublicSettings" @click="savePublicSettings">
        {{ isSavingPublicSettings ? 'Speichern...' : 'Speichern' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { getPublicSharingSettings, savePublicSharingSettings, type PublicFoundContactDelivery } from '@/api/departmentSettings'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const selectedDepartmentId = ref<string | null>(null)
const isSavingPublicSettings = ref(false)
const publicContactEmail = ref('')
const publicContactNote = ref('')
const publicShowContactForm = ref(true)
const publicShowContactEmail = ref(true)
const publicShowContactNote = ref(true)
const publicFoundContactDelivery = ref<PublicFoundContactDelivery>('both')

const userDepartments = computed(() => authStore.departments || [])
const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find((d) => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})
const canManageJoinCode = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)
})

async function loadPublicSettings(deptId: string) {
  try {
    const settings = await getPublicSharingSettings(deptId)
    publicContactEmail.value = settings.publicContactEmail
    publicContactNote.value = settings.publicContactNote
    publicShowContactForm.value = settings.publicShowContactForm
    publicShowContactEmail.value = settings.publicShowContactEmail
    publicShowContactNote.value = settings.publicShowContactNote
    publicFoundContactDelivery.value = settings.publicFoundContactDelivery
  } catch {
    publicContactEmail.value = ''
    publicContactNote.value = ''
    publicShowContactForm.value = true
    publicShowContactEmail.value = true
    publicShowContactNote.value = true
    publicFoundContactDelivery.value = 'both'
  }
}

async function savePublicSettings() {
  if (!selectedDepartmentId.value || isSavingPublicSettings.value) return
  isSavingPublicSettings.value = true
  try {
    await savePublicSharingSettings(selectedDepartmentId.value, {
      publicContactEmail: publicContactEmail.value.trim(),
      publicContactNote: publicContactNote.value.trim(),
      publicShowContactForm: publicShowContactForm.value,
      publicShowContactEmail: publicShowContactEmail.value,
      publicShowContactNote: publicShowContactNote.value,
      publicFoundContactDelivery: publicFoundContactDelivery.value,
    })
    toast.success('Öffentliche Einstellungen gespeichert.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Einstellungen konnten nicht gespeichert werden.')
  } finally {
    isSavingPublicSettings.value = false
  }
}

async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  await authStore.setActiveDepartment(newDeptId)
  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  await loadPublicSettings(newDeptId)
}

onMounted(async () => {
  selectedDepartmentId.value = authStore.activeDepartmentId || (userDepartments.value[0]?.department_id ?? null)
  if (selectedDepartmentId.value) await loadPublicSettings(selectedDepartmentId.value)
})
</script>

<style scoped>
.settings-page { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description, .muted { color: #6b7280; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.label { display: block; margin-bottom: 8px; font-size: 13px; color: #6b7280; }
.field { margin-bottom: 12px; }
.input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; background: #fff; font-size: 14px; }
.toggles { display: flex; flex-direction: column; gap: 8px; }
.btn { border: none; border-radius: 8px; background: #2563eb; color: #fff; padding: 10px 14px; cursor: pointer; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
