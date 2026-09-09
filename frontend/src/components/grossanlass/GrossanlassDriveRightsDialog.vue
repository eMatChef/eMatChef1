<template>
  <EDialog
    :model-value="modelValue"
    :title="card ? t('grossanlass.chain.drive.dialogTitle', { name: card.name }) : t('grossanlass.chain.mayDrive')"
    :max-width="760"
    scrollable
    :retain-focus="false"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="hint">{{ t('grossanlass.chain.drive.dialogHint') }}</p>
    <p v-if="profileClasses.length" class="hint profile-license">
      {{ t('grossanlass.chain.drive.profileLicense', { classes: profileClassLabels }) }}
      <EButton variant="text" size="small" @click="copyFromProfile">
        {{ t('grossanlass.chain.drive.copyFromProfile') }}
      </EButton>
    </p>

    <section
      v-for="group in GA_DRIVE_CATEGORY_GROUPS"
      :key="group.id"
      class="cat-group"
    >
      <h3 class="cat-group__title">{{ t(`grossanlass.chain.drive.groups.${group.id}`) }}</h3>
      <p class="cat-group__lead">{{ t(`grossanlass.chain.drive.groupHints.${group.id}`) }}</p>
      <div class="cat-list">
        <label v-for="code in group.codes" :key="code" class="cat-item">
          <input
            type="checkbox"
            :checked="draft.includes(code)"
            @change="toggle(code, ($event.target as HTMLInputElement).checked)"
          >
          <span>
            <strong>{{ t(`grossanlass.chain.drive.classes.${code}`) }}</strong>
            <span class="cat-item__desc">{{ t(`grossanlass.chain.drive.classHints.${code}`) }}</span>
          </span>
        </label>
      </div>
    </section>

    <v-alert
      v-if="hasExtraRegulation"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-3"
    >
      {{ t('grossanlass.chain.drive.craneExtraRules') }}
    </v-alert>

    <section class="proof">
      <h3 class="cat-group__title">{{ t('grossanlass.chain.drive.proofTitle') }}</h3>
      <p class="cat-group__lead">{{ t('grossanlass.chain.drive.proofHint') }}</p>
      <p class="status-line">
        <span class="chip" :class="{ ok: card?.drive_verified }">{{ proofStatus }}</span>
        <span v-if="card?.drive_verified_by_name" class="meta">
          {{ t('grossanlass.chain.drive.verifiedBy', { name: card.drive_verified_by_name }) }}
        </span>
      </p>

      <div v-if="card?.drive_document" class="doc-row">
        <a :href="card.drive_document.url" target="_blank" rel="noopener">
          {{ card.drive_document.original_name }}
        </a>
        <EButton variant="text" size="small" :loading="busy" @click="removeProof">
          {{ t('grossanlass.chain.drive.removeScan') }}
        </EButton>
      </div>

      <label class="upload">
        <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="onFile" >
        {{ t('grossanlass.chain.drive.uploadScan') }}
      </label>
    </section>

    <template #actions>
      <EButton variant="secondary" @click.stop="emit('update:modelValue', false)">{{ t('common.close') }}</EButton>
      <EButton
        v-if="card?.drive_verified"
        variant="secondary"
        :loading="busy"
        @click.stop="revoke"
      >
        {{ t('grossanlass.chain.drive.revoke') }}
      </EButton>
      <EButton variant="secondary" :loading="busy" :disabled="draft.length === 0" @click.stop="saveClasses">
        {{ t('grossanlass.chain.drive.saveClasses') }}
      </EButton>
      <EButton
        variant="primary"
        :loading="busy"
        :disabled="draft.length === 0"
        @click.stop="confirmInPerson"
      >
        {{ t('grossanlass.chain.drive.confirmSeen') }}
      </EButton>
      <EButton
        v-if="card?.drive_document && !card.drive_verified"
        variant="primary"
        :loading="busy"
        :disabled="draft.length === 0"
        @click.stop="confirmDocument"
      >
        {{ t('grossanlass.chain.drive.confirmScan') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, EDialog } from '@/components/form/base'
import {
  deleteGrossanlassUserCardDriveProof,
  updateGrossanlassUserCard,
  uploadGrossanlassUserCardDriveProof,
  type GrossanlassUserCard,
} from '@/api/grossanlassUserCards'
import {
  driveHasExtraRegulation,
  GA_DRIVE_CATEGORY_GROUPS,
} from '@/views/grossanlass/grossanlassDriveCategories'

const props = defineProps<{
  modelValue: boolean
  departmentId: string
  card: GrossanlassUserCard | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: [card: GrossanlassUserCard]
}>()

const { t } = useI18n()
const toast = useToast()
const draft = ref<string[]>([])
const busy = ref(false)

watch(
  () => props.card?.user_id,
  () => {
    draft.value = [...(props.card?.drive_classes ?? [])]
  },
  { immediate: true },
)

watch(
  () => props.modelValue,
  (open) => {
    if (open) draft.value = [...(props.card?.drive_classes ?? [])]
  },
)

const hasExtraRegulation = computed(() => driveHasExtraRegulation(draft.value))
const profileClasses = computed(() => props.card?.profile_license?.drive_classes ?? [])
const profileClassLabels = computed(() =>
  profileClasses.value.map((code) => t(`grossanlass.chain.drive.classes.${code}`)).join(', '),
)

const proofStatus = computed(() => {
  if (!props.card) return ''
  if (props.card.drive_verified) {
    return props.card.drive_proof_kind === 'document'
      ? t('grossanlass.chain.drive.statusScanConfirmed')
      : t('grossanlass.chain.drive.statusSeen')
  }
  if (props.card.drive_document) return t('grossanlass.chain.drive.statusScanPending')
  if (draft.value.length > 0) return t('grossanlass.chain.drive.statusNeedProof')
  return t('grossanlass.chain.drive.statusNone')
})

function copyFromProfile() {
  if (!profileClasses.value.length) return
  draft.value = [...profileClasses.value]
}

function toggle(code: string, on: boolean) {
  if (on && !draft.value.includes(code)) {
    draft.value = [...draft.value, code]
    return
  }
  if (!on) draft.value = draft.value.filter((item) => item !== code)
}

async function run(task: () => Promise<GrossanlassUserCard>) {
  if (!props.departmentId || !props.card || busy.value) return
  busy.value = true
  try {
    const next = await task()
    draft.value = [...next.drive_classes]
    emit('saved', next)
    toast.success(t('grossanlass.chain.drive.saved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.chain.cardsSaveError'))
  } finally {
    busy.value = false
  }
}

function saveClasses() {
  if (!props.card) return
  return run(() =>
    updateGrossanlassUserCard(props.departmentId, props.card!.user_id, {
      drive_classes: draft.value,
    }),
  )
}

function confirmInPerson() {
  if (!props.card) return
  return run(() =>
    updateGrossanlassUserCard(props.departmentId, props.card!.user_id, {
      drive_classes: draft.value,
      verify_in_person: true,
    }),
  )
}

function confirmDocument() {
  if (!props.card) return
  return run(() =>
    updateGrossanlassUserCard(props.departmentId, props.card!.user_id, {
      drive_classes: draft.value,
      verify_document: true,
    }),
  )
}

function revoke() {
  if (!props.card) return
  return run(() =>
    updateGrossanlassUserCard(props.departmentId, props.card!.user_id, {
      revoke_verification: true,
    }),
  )
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !props.card) return
  await run(() => uploadGrossanlassUserCardDriveProof(props.departmentId, props.card!.user_id, file))
}

function removeProof() {
  if (!props.card) return
  return run(() => deleteGrossanlassUserCardDriveProof(props.departmentId, props.card!.user_id))
}
</script>

<style scoped>
.hint, .cat-group__lead, .meta {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.45;
}
.profile-license { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.cat-group { margin-bottom: 16px; }
.cat-group__title {
  margin: 0 0 4px;
  font-size: 0.92rem;
  font-weight: 700;
}
.cat-list { display: flex; flex-direction: column; gap: 6px; }
.cat-item {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 0.84rem;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}
.cat-item__desc {
  display: block;
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 400;
  margin-top: 2px;
}
.proof { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e5e7eb; }
.status-line { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
.chip {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ffedd5;
  color: #c2410c;
}
.chip.ok { background: #dcfce7; color: #166534; }
.doc-row {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
  font-size: 0.85rem;
}
.upload {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #1d4ed8;
  cursor: pointer;
}
.upload input { font-size: 0.8rem; color: #334155; }
</style>
