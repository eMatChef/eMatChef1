<template>
  <EDialog v-model="open" :max-width="680" :title="isEdit ? t('grossanlass.helferpool.form.editTitle') : t('grossanlass.helferpool.form.createTitle')" scrollable>
    <form class="form" @submit.prevent="save">
      <v-expansion-panels v-model="panels" multiple variant="accordion" data-testid="ga-helper-accordions">
        <!-- 1 Persönliche Angaben -->
        <v-expansion-panel value="personal">
          <v-expansion-panel-title>{{ t('grossanlass.helferpool.form.section.personal') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="form__grid">
              <ESelect
                v-model="form.user_id"
                :items="userItems"
                :label="t('grossanlass.helferpool.form.linkedUser')"
                :disabled="Boolean(linkedUserId)"
                clearable
                hide-details
                data-testid="ga-helper-user"
              />
              <p class="form__hint">{{ linkedHint }}</p>
              <div class="form__row">
                <ETextField v-model="form.first_name" :label="t('grossanlass.helferpool.field.firstName')" :disabled="isLinked" autofocus />
                <ETextField v-model="form.last_name" :label="t('grossanlass.helferpool.field.lastName')" :disabled="isLinked" />
              </div>
              <ETextField v-model="form.email" type="email" :label="t('grossanlass.helferpool.field.email')" :disabled="isLinked" />
              <ETextField v-model="form.phone" :label="t('grossanlass.helferpool.field.phone')" />
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <!-- 2 Berufe & Fähigkeiten -->
        <v-expansion-panel value="skills">
          <v-expansion-panel-title>{{ t('grossanlass.helferpool.form.section.skills') }}<small v-if="form.skills.length" class="form__count">{{ form.skills.length }}</small></v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="form__grid">
              <ETextField v-model="form.occupation" :label="t('grossanlass.helferpool.form.occupation')" />
              <GrossanlassSkillPicker v-model="form.skills" :department-id="departmentId" multiple :label="t('grossanlass.helferpool.form.skills')" :hint="t('grossanlass.helferpool.form.skillsHint')" />
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <!-- 3 Führerausweise & Berechtigungen -->
        <v-expansion-panel value="drive">
          <v-expansion-panel-title>{{ t('grossanlass.helferpool.form.section.drive') }}<small v-if="driveClasses.length" class="form__count">{{ driveClasses.length }}</small></v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="form__hint">{{ isLinked ? t('grossanlass.helferpool.form.driveProfileHint') : t('grossanlass.helferpool.form.driveLocalHint') }}</p>
            <fieldset v-for="group in GA_DRIVE_CATEGORY_GROUPS" :key="group.id" class="drive-group">
              <legend>{{ t(`grossanlass.chain.drive.groups.${group.id}`) }}</legend>
              <label v-for="code in group.codes" :key="code" class="drive-item">
                <input type="checkbox" :checked="driveClasses.includes(code)" :disabled="isLinked" @change="toggleDrive(code)" />
                <span>{{ t(`grossanlass.chain.drive.classes.${code}`) }}</span>
              </label>
            </fieldset>
            <EDateField v-model="driveValidUntilModel" :label="t('grossanlass.helferpool.form.driveValidUntil')" :disabled="isLinked" allow-past />

            <h4 class="form__sub">{{ t('grossanlass.helferpool.form.qualifications') }}</h4>
            <div v-for="(q, index) in form.qualifications" :key="index" class="qual">
              <ETextField v-model="q.label" :label="t('grossanlass.helferpool.form.qualificationLabel')" hide-details />
              <EDateField v-model="q.valid_until" :label="t('grossanlass.helferpool.form.driveValidUntil')" allow-past />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" :aria-label="t('common.delete')" @click="form.qualifications.splice(index, 1)" />
            </div>
            <EButton size="small" variant="secondary" type="button" @click="form.qualifications.push({ label: '', valid_until: '' })">
              <v-icon icon="mdi-plus" start size="16" />{{ t('grossanlass.helferpool.form.addQualification') }}
            </EButton>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <!-- 4 Verfügbarkeit -->
        <v-expansion-panel value="availability">
          <v-expansion-panel-title>{{ t('grossanlass.helferpool.form.section.availability') }}<small v-if="windows.length" class="form__count">{{ windows.length }}</small></v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="form__hint">{{ t('grossanlass.helferpool.availabilityHint') }}</p>
            <div v-for="(w, index) in windows" :key="w.key" class="window-row">
              <GrossanlassHelferWindowFields v-model:start="w.start" v-model:end="w.end" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" :aria-label="t('common.delete')" @click="removeWindow(index)" />
            </div>
            <EButton size="small" variant="secondary" type="button" @click="addWindow">
              <v-icon icon="mdi-plus" start size="16" />{{ t('grossanlass.helferpool.addAvailability') }}
            </EButton>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <!-- 5 Weitere Angaben -->
        <v-expansion-panel value="more">
          <v-expansion-panel-title>{{ t('grossanlass.helferpool.form.section.more') }}</v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="form__grid">
              <ETextField v-model="form.area" :label="t('grossanlass.helferpool.form.area')" />
              <ETextarea v-model="form.note" :label="t('grossanlass.helferpool.field.note')" rows="2" />
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <p class="form__hint"><slot name="hint">{{ t('grossanlass.helferpool.form.hint') }}</slot></p>
      <p v-if="error" class="form__error" role="alert">{{ error }}</p>
    </form>
    <template #actions>
      <EButton variant="text" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :loading="saving" :disabled="!canSave" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  addGaHelperAvailability,
  createGaHelper,
  deleteGaHelperAvailability,
  getGaHelper,
  listGaLinkableUsers,
  updateGaHelper,
  updateGaHelperAvailability,
  type GaHelper,
  type GaHelperAvailability,
  type GaLinkableUser,
} from '@/api/grossanlassStaffing'
import { EButton, EDateField, EDialog, ESelect, ETextarea, ETextField } from '@/components/form/base'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import { GA_DRIVE_CATEGORY_GROUPS } from '../grossanlassDriveCategories'
import GrossanlassHelferWindowFields from './GrossanlassHelferWindowFields.vue'
import GrossanlassSkillPicker from './GrossanlassSkillPicker.vue'

/**
 * Helfer erfassen oder bearbeiten (eine Komponente für beides), gegliedert in Accordions. Verknüpfte Helfer beziehen Name,
 * E-Mail und Führerausweis aus dem zentralen Profil (nur lesend); Fähigkeiten, Qualifikationen und Verfügbarkeit sind GA-lokal.
 */
const props = defineProps<{ departmentId: string; helper: GaHelper | null }>()
const emit = defineEmits<{ saved: [helper: GaHelper] }>()
const open = defineModel<boolean>({ default: false })
const { t } = useI18n()

type Win = { key: number; id: string | null; start: string; end: string }
let keySeq = 0

const form = reactive({
  user_id: null as string | null,
  first_name: '', last_name: '', email: '', phone: '', note: '',
  occupation: '', area: '',
  skills: [] as string[],
  drive_classes: [] as string[],
  drive_valid_until: null as string | null,
  qualifications: [] as { label: string; valid_until: string }[], // Datumsfelder arbeiten mit '' statt null
})
const windows = ref<Win[]>([])
const original = ref<GaHelperAvailability[]>([])
const panels = ref<string[]>(['personal'])
const users = ref<GaLinkableUser[]>([])
const linkedUserId = ref<string | null>(null)
const createdId = ref<string | null>(null)
const saving = ref(false)
const error = ref('')

const helperId = computed(() => props.helper?.id ?? createdId.value)
const isEdit = computed(() => Boolean(helperId.value))
const isLinked = computed(() => Boolean(linkedUserId.value || form.user_id))
const driveClasses = computed(() => form.drive_classes)
const userItems = computed(() => users.value.filter((u) => !u.helper_id || u.helper_id === helperId.value).map((u) => ({ value: u.user_id, title: `${u.name} · ${u.email}` })))
const linkedHint = computed(() => (isLinked.value ? t('grossanlass.helferpool.form.linkedHint') : t('grossanlass.helferpool.form.externalHint')))
const driveValidUntilModel = computed({ get: () => form.drive_valid_until ?? '', set: (v: string) => { form.drive_valid_until = v || null } })
const canSave = computed(() => (isLinked.value || form.first_name.trim() !== '' || form.last_name.trim() !== '') && !saving.value)

function toggleDrive(code: string) {
  form.drive_classes = form.drive_classes.includes(code) ? form.drive_classes.filter((c) => c !== code) : [...form.drive_classes, code]
}
function addWindow() {
  windows.value.push({ key: ++keySeq, id: null, start: '', end: '' })
}
function removeWindow(index: number) {
  windows.value.splice(index, 1)
}

function fill(h: GaHelper | null) {
  linkedUserId.value = h?.user_id ?? null
  form.user_id = h?.user_id ?? null
  form.first_name = h?.first_name ?? ''
  form.last_name = h?.last_name ?? ''
  form.email = h?.email ?? ''
  form.phone = h?.phone ?? ''
  form.note = h?.note ?? ''
  form.occupation = h?.occupation ?? ''
  form.area = h?.area ?? ''
  form.skills = [...(h?.skills ?? [])]
  form.drive_classes = [...(h?.drive_license?.classes ?? [])]
  form.drive_valid_until = h?.drive_license?.valid_until ?? null
  form.qualifications = (h?.qualifications ?? []).map((q) => ({ label: q.label, valid_until: q.valid_until ?? '' }))
  original.value = h?.availability ?? []
  windows.value = original.value.map((a) => ({ key: ++keySeq, id: a.id, start: a.starts_at, end: a.ends_at }))
}

/** Auswahl eines Kontos: Name und E-Mail aus dem Profil als (schreibgeschützte) Vorschau zeigen. */
watch(() => form.user_id, (id) => {
  if (!id || linkedUserId.value) return
  const u = users.value.find((x) => x.user_id === id)
  if (!u) return
  form.first_name = u.first_name
  form.last_name = u.last_name
  form.email = u.email
})

watch(open, async (value) => {
  if (!value) return
  error.value = ''
  createdId.value = null
  panels.value = ['personal']
  fill(props.helper)
  try {
    users.value = await listGaLinkableUsers(props.departmentId)
  } catch {
    users.value = []
  }
  if (props.helper) {
    try {
      fill(await getGaHelper(props.departmentId, props.helper.id)) // gespeicherte Werte inkl. Verfügbarkeit laden
    } catch (err) {
      error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.save'))
    }
  }
})

function payload() {
  const linked = isLinked.value
  return {
    ...(linked ? {} : { first_name: form.first_name.trim(), last_name: form.last_name.trim(), email: form.email.trim(), drive_classes: form.drive_classes, drive_valid_until: form.drive_valid_until }),
    ...(form.user_id && !linkedUserId.value ? { user_id: form.user_id } : {}),
    phone: form.phone.trim(),
    note: form.note.trim(),
    occupation: form.occupation.trim(),
    area: form.area.trim(),
    skills: form.skills,
    qualifications: form.qualifications.filter((q) => q.label.trim() !== '').map((q) => ({ label: q.label.trim(), valid_until: q.valid_until || null })),
  }
}

/** Verfügbarkeit abgleichen: entfernte löschen, geänderte aktualisieren, neue anlegen (Server prüft Überlappung und Zuteilungen). */
async function syncAvailability(id: string) {
  const kept = new Set(windows.value.filter((w) => w.id).map((w) => w.id))
  for (const old of original.value) {
    if (!kept.has(old.id)) await deleteGaHelperAvailability(props.departmentId, old.id)
  }
  for (const w of windows.value) {
    if (!w.start || !w.end) continue
    if (w.id) {
      const old = original.value.find((a) => a.id === w.id)
      if (old && (old.starts_at !== w.start || old.ends_at !== w.end)) await updateGaHelperAvailability(props.departmentId, w.id, w.start, w.end)
    } else {
      w.id = (await addGaHelperAvailability(props.departmentId, id, w.start, w.end)).id
    }
  }
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  error.value = ''
  try {
    const id = helperId.value
    const saved = id ? await updateGaHelper(props.departmentId, id, payload()) : await createGaHelper(props.departmentId, payload())
    createdId.value = saved.id // bei späterem Fehler in der Verfügbarkeit nicht erneut anlegen
    try {
      await syncAvailability(saved.id)
    } catch (err) {
      error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.save'))
      emit('saved', saved)
      panels.value = [...new Set([...panels.value, 'availability'])]
      return
    }
    emit('saved', saved)
    open.value = false
  } catch (err) {
    error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.save'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.form {
  display: grid;
  gap: 10px;
}
.form__grid {
  display: grid;
  gap: 10px;
  padding-top: 6px;
}
.form__row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.form__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.82rem;
}
.form__sub {
  margin: 14px 0 6px;
  font-size: 0.9rem;
}
.form__count {
  margin-left: 8px;
  padding: 0 7px;
  border-radius: 10px;
  background: #e2e8f0;
  color: #334155;
  font-size: 0.75rem;
}
.form__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
.drive-group {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  margin: 8px 0;
  padding: 0;
  border: 0;
}
.drive-group legend {
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
}
.drive-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.88rem;
}
.qual {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 170px auto;
  gap: 8px;
  align-items: start;
  margin-bottom: 8px;
}
.window-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: start;
  margin-bottom: 8px;
}
@media (max-width: 520px) {
  .form__row,
  .qual {
    grid-template-columns: 1fr;
  }
}
</style>
