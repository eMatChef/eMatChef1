<template>
  <v-navigation-drawer
    v-model="open"
    location="right"
    temporary
    :width="mdAndUp ? 460 : undefined"
    :class="{ 'drawer--full': !mdAndUp }"
    class="helper-drawer"
  >
    <div v-if="helper" class="drawer">
      <header class="drawer__head">
        <div>
          <h3>{{ fullName(helper) }}</h3>
          <v-chip size="x-small" variant="flat" :color="helper.status === 'archived' ? 'grey' : 'success'">
            {{ t(`grossanlass.helferpool.helperStatus.${helper.status}`) }}
          </v-chip>
        </div>
        <v-btn icon="mdi-close" variant="text" :aria-label="t('common.close')" @click="open = false" />
      </header>

      <div class="drawer__actions">
        <EButton size="small" variant="secondary" @click="emit('edit', helper)"><v-icon icon="mdi-pencil-outline" start size="16" />{{ t('common.edit') }}</EButton>
        <EButton v-if="helper.status === 'active'" size="small" variant="secondary" :loading="busy" @click="setArchived(true)">
          <v-icon icon="mdi-archive-outline" start size="16" />{{ t('grossanlass.helferpool.archive') }}
        </EButton>
        <EButton v-else size="small" variant="secondary" :loading="busy" @click="setArchived(false)">
          <v-icon icon="mdi-archive-arrow-up-outline" start size="16" />{{ t('grossanlass.helferpool.restore') }}
        </EButton>
      </div>

      <section>
        <h4>{{ t('grossanlass.helferpool.contact') }}</h4>
        <p v-if="helper.email"><v-icon icon="mdi-email-outline" size="14" /> {{ helper.email }}</p>
        <p v-if="helper.phone"><v-icon icon="mdi-phone-outline" size="14" /> {{ helper.phone }}</p>
        <p v-if="!helper.email && !helper.phone" class="muted">{{ t('grossanlass.helferpool.noContact') }}</p>
        <p v-if="helper.note" class="drawer__note">„{{ helper.note }}“</p>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.availability') }}</h4>
        <p class="muted">{{ t('grossanlass.helferpool.availabilityHint') }}</p>
        <ul class="list">
          <li v-for="window in availability" :key="window.id">
            <template v-if="editingId === window.id">
              <div class="edit">
                <GrossanlassHelferWindowFields v-model:start="draftStart" v-model:end="draftEnd" />
                <div class="edit__buttons">
                  <EButton size="small" variant="primary" :loading="busy" :disabled="!draftValid" @click="saveWindow(window.id)">{{ t('common.save') }}</EButton>
                  <EButton size="small" variant="text" @click="editingId = ''">{{ t('common.cancel') }}</EButton>
                </div>
              </div>
            </template>
            <template v-else>
              <span class="list__label">{{ windowLabel(window.starts_at, window.ends_at, locale) }}</span>
              <span class="list__buttons">
                <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" :aria-label="t('common.edit')" @click="startEdit(window.id, window.starts_at, window.ends_at)" />
                <v-btn icon="mdi-delete-outline" size="x-small" variant="text" :aria-label="t('common.delete')" @click="removeWindow(window.id)" />
              </span>
            </template>
          </li>
          <li v-if="!availability.length" class="muted">{{ t('grossanlass.helferpool.noAvailability') }}</li>
        </ul>
        <div v-if="helper.status === 'active'" class="edit edit--new">
          <GrossanlassHelferWindowFields v-model:start="newStart" v-model:end="newEnd" />
          <EButton size="small" variant="secondary" :loading="busy" :disabled="!newValid" @click="addWindow">
            <v-icon icon="mdi-plus" start size="16" />{{ t('grossanlass.helferpool.addAvailability') }}
          </EButton>
        </div>
        <p v-if="error" class="drawer__error" role="alert">{{ error }}</p>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.assignments') }}</h4>
        <p v-if="!assignments.length" class="muted">{{ t('grossanlass.helferpool.noAssignments') }}</p>
        <ul class="list">
          <li v-for="entry in assignments" :key="entry.id">
            <span class="list__label">
              <strong>{{ assignmentTitle(entry) }}</strong>
              <small>{{ windowLabel(entry.starts_at, entry.ends_at, locale) }}</small>
              <v-chip v-if="entry.has_override" size="x-small" variant="tonal" color="warning">{{ t('grossanlass.helferpool.override') }}</v-chip>
            </span>
          </li>
        </ul>
      </section>
    </div>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import {
  addGaHelperAvailability,
  archiveGaHelper,
  deleteGaHelperAvailability,
  getGaHelper,
  restoreGaHelper,
  updateGaHelperAvailability,
  type GaHelper,
  type GaHelperAvailability,
  type GaScheduleHelper,
} from '@/api/grossanlassStaffing'
import { EButton } from '@/components/form/base'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import GrossanlassHelferWindowFields from './GrossanlassHelferWindowFields.vue'
import { fullName, windowLabel } from './gaHelferUi'

/** Helfer-Detail: Kontakt, Verfügbarkeitsfenster (erfassen, ändern, löschen), Zuteilungen. Archivieren statt löschen. */
const props = defineProps<{ departmentId: string; helperId: string | null; schedule: GaScheduleHelper | null }>()
const emit = defineEmits<{ edit: [helper: GaHelper]; changed: [] }>()
const open = defineModel<boolean>({ default: false })
const { t, locale } = useI18n()
const { mdAndUp } = useDisplay()

const helper = ref<GaHelper | null>(null)
const availability = ref<GaHelperAvailability[]>([])
const busy = ref(false)
const error = ref('')
const editingId = ref('')
const draftStart = ref('')
const draftEnd = ref('')
const newStart = ref('')
const newEnd = ref('')

const assignments = computed(() => props.schedule?.assignments ?? [])
const draftValid = computed(() => draftStart.value !== '' && draftEnd.value > draftStart.value)
const newValid = computed(() => newStart.value !== '' && newEnd.value > newStart.value)

function assignmentTitle(entry: GaScheduleHelper['assignments'][number]): string {
  return [entry.task_title ?? t('grossanlass.helferpool.needs.projectNeed'), entry.label].filter(Boolean).join(' · ')
}

async function load() {
  if (!props.helperId) return
  error.value = ''
  try {
    const loaded = await getGaHelper(props.departmentId, props.helperId)
    helper.value = loaded
    availability.value = loaded.availability ?? []
  } catch (err) {
    error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.load'))
  }
}

watch(() => [open.value, props.helperId] as const, ([isOpen]) => {
  if (!isOpen) return
  editingId.value = ''
  newStart.value = ''
  newEnd.value = ''
  void load()
}, { immediate: true })

/** Änderungen an Helfer oder Verfügbarkeit: Drawer neu laden und die Elternansicht informieren. */
async function run(action: () => Promise<unknown>) {
  busy.value = true
  error.value = ''
  try {
    await action()
    await load()
    emit('changed')
  } catch (err) {
    error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.save'))
  } finally {
    busy.value = false
  }
}

const setArchived = (archive: boolean) => run(() => (archive ? archiveGaHelper : restoreGaHelper)(props.departmentId, props.helperId!))
const addWindow = () => run(async () => {
  await addGaHelperAvailability(props.departmentId, props.helperId!, newStart.value, newEnd.value)
  newStart.value = ''
  newEnd.value = ''
})
const saveWindow = (id: string) => run(async () => {
  await updateGaHelperAvailability(props.departmentId, id, draftStart.value, draftEnd.value)
  editingId.value = ''
})
const removeWindow = (id: string) => run(() => deleteGaHelperAvailability(props.departmentId, id))

function startEdit(id: string, from: string, to: string) {
  editingId.value = id
  draftStart.value = from
  draftEnd.value = to
}

defineExpose({ reload: load })
</script>

<style scoped>
.drawer {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
}
.drawer__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.drawer__head h3 {
  margin: 0 0 4px;
}
.drawer__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
section h4 {
  margin: 0 0 6px;
  font-size: 0.72rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
section p {
  margin: 0 0 4px;
  font-size: 0.9rem;
}
.drawer__note {
  color: #64748b;
  font-style: italic;
}
.drawer__error {
  color: #b91c1c;
}
.list {
  margin: 0 0 8px;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
  font-size: 0.88rem;
}
.list li {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
}
.list__label {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 8px;
  align-items: baseline;
}
.list small {
  color: #64748b;
}
.list__buttons {
  display: flex;
  flex-shrink: 0;
}
.edit {
  display: grid;
  gap: 8px;
  width: 100%;
}
.edit--new {
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
}
.edit__buttons {
  display: flex;
  gap: 8px;
}
.muted {
  color: #94a3b8;
  font-size: 0.86rem;
}
</style>
