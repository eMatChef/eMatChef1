<template>
  <div class="helper-form">
    <p class="helper-hint">{{ t('grossanlass.planung.ressorts.helperHint') }}</p>
    <ETextField
      v-model="form.name"
      :label="t('grossanlass.planung.ressorts.helperName')"
      :placeholder="t('grossanlass.planung.ressorts.helperNamePlaceholder')"
      hide-details="auto"
    />
    <ETextField
      v-model="form.email"
      type="email"
      :label="t('grossanlass.planung.ressorts.helperEmail')"
      :placeholder="t('grossanlass.planung.ressorts.helperEmailPlaceholder')"
      hide-details="auto"
    />
    <ESelect
      v-if="groupItems.length > 1 || !fixedGroupId"
      v-model="form.group_id"
      :items="groupItems"
      :label="t('grossanlass.planung.ressorts.helperRessort')"
      hide-details
    />
    <ECheckbox v-model="form.may_drive" :label="t('grossanlass.chain.drive.helperWillDrive')" hide-details />
    <EButton
      variant="primary"
      size="small"
      :disabled="!canSubmit || isSaving"
      :loading="isSaving"
      @click="submit"
    >
      {{ t('grossanlass.planung.ressorts.helperSubmit') }}
    </EButton>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ECheckbox, ESelect, ETextField } from '@/components/form/base'
import {
  createGrossanlassHelper,
  type GrossanlassGroup,
  type GrossanlassHelperResult,
} from '@/api/grossanlassGroups'

const props = defineProps<{
  departmentId: string
  groups: GrossanlassGroup[]
  fixedGroupId?: string | null
}>()

const emit = defineEmits<{
  created: [result: GrossanlassHelperResult]
}>()

const { t } = useI18n()
const toast = useToast()
const isSaving = ref(false)
const form = reactive({
  name: '',
  email: '',
  group_id: '',
  may_drive: false as boolean | null,
})

const groupItems = computed(() =>
  props.groups.map((g) => ({
    title: g.parent_id ? `${g.name}` : g.name,
    value: g.id,
  })),
)

const canSubmit = computed(() => {
  const email = form.email.trim()
  const groupId = form.group_id || props.fixedGroupId || ''
  return email.includes('@') && groupId !== ''
})

watch(
  () => props.fixedGroupId,
  (id) => {
    if (id) form.group_id = id
  },
  { immediate: true },
)

async function submit() {
  const groupId = form.group_id || props.fixedGroupId || ''
  if (!props.departmentId || !groupId || isSaving.value) return
  isSaving.value = true
  try {
    const result = await createGrossanlassHelper(props.departmentId, groupId, {
      email: form.email.trim(),
      name: form.name.trim() || undefined,
      may_drive: !!form.may_drive,
    })
    emit('created', result)
    form.name = ''
    form.email = ''
    form.may_drive = false
    toast.success(t('grossanlass.planung.ressorts.helperCreated', { name: result.name, code: result.card.code }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.helperError'))
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.helper-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.helper-hint {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}
</style>
