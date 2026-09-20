<template>
  <section v-if="hasProcurementDelegate && groupItems.length" class="direct-procure">
    <h3>{{ t('grossanlass.beschaffung.direct.title') }}</h3>
    <p class="direct-procure__hint">{{ t('grossanlass.beschaffung.direct.hint') }}</p>

    <form class="direct-procure__form" @submit.prevent="submit">
      <ESelect
        v-model="form.group_id"
        :items="groupItems"
        :label="t('grossanlass.beschaffung.direct.group')"
        hide-details
      />
      <ETextField
        v-model="form.label"
        :label="t('grossanlass.beschaffung.direct.label')"
        hide-details="auto"
      />
      <div class="direct-procure__row">
        <ETextField
          v-model.number="form.quantity"
          type="number"
          min="1"
          :label="t('grossanlass.beschaffung.direct.quantity')"
          hide-details="auto"
        />
        <ETextField
          v-model="form.location"
          :label="t('grossanlass.beschaffung.direct.location')"
          hide-details="auto"
        />
      </div>
      <ETextField
        v-model="form.notes"
        :label="t('grossanlass.beschaffung.direct.notes')"
        hide-details="auto"
      />
      <EButton
        type="submit"
        variant="primary"
        size="small"
        :disabled="!canSubmit"
        :loading="saving"
      >
        {{ t('grossanlass.beschaffung.direct.submit') }}
      </EButton>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { createGrossanlassProcurementLineDirect } from '@/api/grossanlassProcurement'
import { useGrossanlassProcurementScope } from '@/composables/useGrossanlassProcurementScope'
import { flattenTreeWithLevel } from '@/utils/grossanlassGroupHierarchy'

const props = defineProps<{
  departmentId: string
}>()

const emit = defineEmits<{
  created: []
}>()

const { t } = useI18n()
const toast = useToast()

const groups = ref<GrossanlassGroup[]>([])
const groupsRef = computed(() => groups.value)
const { hasProcurementDelegate, userCanProcureInGroup } = useGrossanlassProcurementScope(groupsRef)

const saving = ref(false)
const form = ref({
  group_id: '',
  label: '',
  quantity: 1,
  location: '',
  notes: '',
})

const groupItems = computed(() =>
  flattenTreeWithLevel(groups.value)
    .filter((group) => userCanProcureInGroup(group.id))
    .map((group) => ({
      title: `${'· '.repeat(group._level)}${group.name}`,
      value: group.id,
    })),
)

const canSubmit = computed(() =>
  Boolean(form.value.group_id && form.value.label.trim() && form.value.location.trim() && form.value.quantity >= 1),
)

function resetForm() {
  form.value = {
    group_id: groupItems.value[0]?.value || '',
    label: '',
    quantity: 1,
    location: '',
    notes: '',
  }
}

async function loadGroups() {
  if (!props.departmentId) return
  try {
    groups.value = await getGrossanlassGroups(props.departmentId)
    if (!form.value.group_id && groupItems.value[0]) {
      form.value.group_id = groupItems.value[0].value
    }
  } catch {
    groups.value = []
  }
}

async function submit() {
  if (!props.departmentId || !canSubmit.value || saving.value) return
  saving.value = true
  try {
    await createGrossanlassProcurementLineDirect(props.departmentId, {
      group_id: form.value.group_id,
      label: form.value.label.trim(),
      quantity: Number(form.value.quantity) || 1,
      location: form.value.location.trim(),
      notes: form.value.notes.trim() || null,
    })
    toast.success(t('grossanlass.beschaffung.direct.success'))
    resetForm()
    emit('created')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.direct.error'))
  } finally {
    saving.value = false
  }
}

watch(() => props.departmentId, () => {
  void loadGroups()
})

onMounted(() => {
  void loadGroups()
})
</script>

<style scoped>
.direct-procure {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px 16px;
  background: #fff;
  margin-bottom: 16px;
}
.direct-procure h3 {
  margin: 0 0 4px;
  font-size: 1rem;
}
.direct-procure__hint {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.85rem;
}
.direct-procure__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.direct-procure__row {
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 10px;
}
@media (max-width: 640px) {
  .direct-procure__row {
    grid-template-columns: 1fr;
  }
}
</style>
