<template>
  <EDialog v-model="open" :max-width="560" :title="assignment ? t('grossanlass.helferpool.assign.editTitle') : t('grossanlass.helferpool.assign.title')" scrollable>
    <div class="assign">
      <p v-if="need" class="assign__need">
        <strong>{{ needTitle }}</strong>
        <span v-if="need.dated && need.starts_at && need.ends_at"> · {{ windowLabel(need.starts_at, need.ends_at, locale) }}</span>
      </p>
      <p v-if="need && !need.dated" class="assign__warn">{{ t('grossanlass.helferpool.assign.undated') }}</p>

      <ESelect
        v-model="helperId"
        :items="helperItems"
        :label="t('grossanlass.helferpool.assign.helper')"
        :disabled="Boolean(assignment) || !need?.dated"
        hide-details
      />
      <GrossanlassHelferWindowFields v-model:start="startsAt" v-model:end="endsAt" :disabled="!need?.dated" />
      <p class="assign__hint">{{ t('grossanlass.helferpool.assign.partTimeHint') }}</p>

      <div v-if="conflict" class="assign__conflict" role="alert">
        <p>
          <v-icon icon="mdi-alert-outline" size="16" /> {{ conflictMessage }}
        </p>
        <ul v-if="conflict.conflict.conflicts?.length">
          <li v-for="entry in conflict.conflict.conflicts" :key="entry.assignment_id">{{ windowLabel(entry.starts_at, entry.ends_at, locale) }}</li>
        </ul>
        <ETextarea
          v-if="conflict.conflict.override_possible"
          v-model="overrideReason"
          :label="t('grossanlass.helferpool.assign.overrideReason')"
          rows="2"
          hide-details
        />
        <p v-if="conflict.conflict.override_possible" class="assign__hint">{{ t('grossanlass.helferpool.assign.overrideHint') }}</p>
      </div>
      <p v-else-if="error" class="assign__error" role="alert">{{ error }}</p>
    </div>
    <template #actions>
      <EButton variant="text" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
        {{ conflict?.conflict.override_possible ? t('grossanlass.helferpool.assign.withOverride') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  assignGaHelper,
  staffingConflictOf,
  updateGaAssignment,
  type GaHelper,
  type GaStaffingAssignResult,
  type GaStaffingConflictPayload,
  type GaStaffingNeedRow,
} from '@/api/grossanlassStaffing'
import { EButton, EDialog, ESelect, ETextarea } from '@/components/form/base'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import GrossanlassHelferWindowFields from './GrossanlassHelferWindowFields.vue'
import { conflictMessageKey, fullName, windowLabel } from './gaHelferUi'

/**
 * Helfer einem Personalbedarf zuteilen oder eine Zuteilung ändern (ganz oder teilweise). Konflikte kommen vom Server als 409:
 * Doppelbuchung lässt sich mit Begründung als Ausnahme bestätigen; fehlende Verfügbarkeit nicht.
 */
const props = defineProps<{
  departmentId: string
  need: GaStaffingNeedRow | null
  helpers: GaHelper[]
  /** Bestehende Zuteilung ändern statt neu anlegen. */
  assignment?: { id: string; helper_id: string; starts_at: string; ends_at: string; version: number } | null
  /** Vorbelegter Helfer (z. B. aus der Helferkarte). */
  presetHelperId?: string | null
}>()
const emit = defineEmits<{ done: [result: GaStaffingAssignResult] }>()
const open = defineModel<boolean>({ default: false })
const { t, locale } = useI18n()

const helperId = ref('')
const startsAt = ref('')
const endsAt = ref('')
const overrideReason = ref('')
const saving = ref(false)
const error = ref('')
const conflict = ref<GaStaffingConflictPayload | null>(null)

const needTitle = computed(() => {
  const n = props.need
  if (!n) return ''
  const parts = [n.task_title ?? t('grossanlass.helferpool.needs.projectNeed'), n.label].filter(Boolean)
  return parts.join(' · ')
})
const helperItems = computed(() => props.helpers.filter((h) => h.status === 'active').map((h) => ({ value: h.id, title: fullName(h) })))
const conflictMessage = computed(() => (conflict.value ? t(conflictMessageKey(conflict.value.conflict.kind)) : ''))
const canSubmit = computed(() => {
  if (!props.need?.dated || !helperId.value || !startsAt.value || !endsAt.value || saving.value) return false
  return !(conflict.value?.conflict.override_possible && overrideReason.value.trim() === '')
})

watch(open, (value) => {
  if (!value) return
  conflict.value = null
  error.value = ''
  overrideReason.value = ''
  helperId.value = props.assignment?.helper_id ?? props.presetHelperId ?? ''
  startsAt.value = props.assignment?.starts_at ?? props.need?.starts_at ?? ''
  endsAt.value = props.assignment?.ends_at ?? props.need?.ends_at ?? ''
})

async function submit() {
  if (!props.need || !canSubmit.value) return
  saving.value = true
  error.value = ''
  const reason = conflict.value?.conflict.override_possible ? overrideReason.value.trim() : ''
  try {
    const window = { starts_at: startsAt.value, ends_at: endsAt.value }
    const result = props.assignment
      ? await updateGaAssignment(props.departmentId, props.assignment.id, { ...window, version: props.assignment.version, ...(reason ? { override_reason: reason } : {}) })
      : await assignGaHelper(props.departmentId, { need_id: props.need.id, helper_id: helperId.value, ...window, ...(reason ? { override_reason: reason } : {}) })
    emit('done', result)
    open.value = false
  } catch (err) {
    const known = staffingConflictOf(err)
    if (known) {
      conflict.value = known
      if (!known.conflict.override_possible) error.value = ''
    } else {
      conflict.value = null
      error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.assign'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.assign {
  display: grid;
  gap: 12px;
}
.assign__need {
  margin: 0;
  font-size: 0.92rem;
}
.assign__warn {
  margin: 0;
  color: #92400e;
  font-size: 0.88rem;
}
.assign__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.82rem;
}
.assign__conflict {
  display: grid;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #7f1d1d;
  font-size: 0.9rem;
}
.assign__conflict p {
  margin: 0;
}
.assign__conflict ul {
  margin: 0;
  padding-left: 18px;
}
.assign__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
</style>
