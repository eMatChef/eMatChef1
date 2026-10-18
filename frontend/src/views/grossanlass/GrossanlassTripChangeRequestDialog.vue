<template>
  <EDialog v-model="open" :title="t('grossanlass.material.changeRequest.title')" max-width="520" :retain-focus="false">
    <div class="cr" data-testid="trip-change-dialog">
      <p class="cr__hint">{{ t('grossanlass.material.changeRequest.hint') }}</p>
      <v-alert v-if="error" type="warning" variant="tonal" density="compact" data-testid="trip-change-error">{{ error }}</v-alert>
      <label class="cr__field">
        <span>{{ t('grossanlass.material.changeRequest.note') }}</span>
        <textarea v-model="note" rows="3" data-testid="trip-change-note" />
      </label>
      <div class="cr__row">
        <label class="cr__field">
          <span>{{ t('grossanlass.material.changeRequest.from') }}</span>
          <input v-model="from" type="datetime-local" data-testid="trip-change-from" />
        </label>
        <label class="cr__field">
          <span>{{ t('grossanlass.material.changeRequest.to') }}</span>
          <input v-model="to" type="datetime-local" data-testid="trip-change-to" />
        </label>
      </div>
      <ESelect v-model="placeId" :items="placeItems" :label="t('grossanlass.material.changeRequest.place')" clearable hide-details />
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :disabled="busy" data-testid="trip-change-submit" @click="submit">{{ t('grossanlass.material.changeRequest.submit') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import { listGrossanlassPlaces, requestGrossanlassTripChange } from '@/api/grossanlassLogistics'

/** Änderungswunsch eines Ressorts an einem Fahrauftrag; die Disposition bleibt gültig, bis die Logistik entscheidet. */
const props = defineProps<{ modelValue: boolean; departmentId: string; needId: string | null; fromIso?: string; toIso?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; sent: [] }>()
const { t } = useI18n()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const note = ref('')
const from = ref('')
const to = ref('')
const placeId = ref<string | null>(null)
const busy = ref(false)
const error = ref('')
const places = ref<Array<{ id: string; name: string }>>([])
const placeItems = computed(() => places.value.map((place) => ({ title: place.name, value: place.id })))

watch(
  () => props.modelValue,
  async (value) => {
    if (!value) return
    note.value = ''
    from.value = (props.fromIso ?? '').slice(0, 16)
    to.value = (props.toIso ?? '').slice(0, 16)
    placeId.value = null
    error.value = ''
    try {
      places.value = (await listGrossanlassPlaces(props.departmentId)).map((place) => ({ id: place.id, name: place.name }))
    } catch {
      places.value = []
    }
  },
)

async function submit() {
  if (!props.needId) return
  busy.value = true
  error.value = ''
  try {
    const changedWindow = from.value !== (props.fromIso ?? '').slice(0, 16) || to.value !== (props.toIso ?? '').slice(0, 16)
    await requestGrossanlassTripChange(props.departmentId, props.needId, {
      note: note.value,
      starts_at: changedWindow && from.value ? `${from.value}:00` : null,
      ends_at: changedWindow && to.value ? `${to.value}:00` : null,
      destination_place_id: placeId.value,
    })
    emit('sent')
    open.value = false
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { error?: string } } })?.response?.data?.error || t('grossanlass.material.changeRequest.failed')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.cr {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.cr__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.88rem;
}
.cr__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.cr__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.82rem;
  color: #475569;
}
.cr__field textarea,
.cr__field input {
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #0f172a;
}
</style>
