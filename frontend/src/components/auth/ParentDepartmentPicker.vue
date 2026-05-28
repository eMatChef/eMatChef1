<template>
  <div class="parent-dept-picker">
    <label class="form-label">{{ t('login.parentDepartmentLabel') }}</label>
    <p class="parent-dept-intro">{{ t('login.parentDepartmentIntro') }}</p>

    <div v-if="skipped" class="parent-dept-skipped">
      <span>{{ t('login.parentDepartmentNone') }}</span>
      <button type="button" class="inline-link" :disabled="disabled" @click="resetPicker">
        {{ t('login.parentDepartmentChange') }}
      </button>
    </div>

    <div v-else-if="picked" class="parent-dept-picked">
      <DepartmentPathDisplay v-if="pickedBreadcrumb.length > 0" :segments="pickedBreadcrumb" />
      <span v-else>{{ picked.departmentName }}</span>
      <button type="button" class="inline-link" :disabled="disabled" @click="resetPicker">
        {{ t('login.parentDepartmentChange') }}
      </button>
    </div>

    <template v-else>
      <div class="parent-dept-search">
        <input
          v-model="parentQuery"
          type="text"
          class="form-input"
          :placeholder="t('login.parentDepartmentSearchPlaceholder')"
          :disabled="disabled || !organisationId"
          autocomplete="off"
        />
      </div>

      <button type="button" class="inline-link parent-dept-skip" :disabled="disabled" @click="skipParent">
        {{ t('login.parentDepartmentNone') }}
      </button>

      <div v-if="parentLoading" class="parent-dept-hint">{{ t('login.departmentSearchLoading') }}</div>

      <div v-else-if="parentQuery.trim().length >= 2 && searchResults.length > 0" class="parent-dept-results">
        <button
          v-for="d in searchResults"
          :key="d.id"
          type="button"
          class="parent-dept-result-item"
          :disabled="disabled"
          @click="pickParent(d)"
        >
          <DepartmentPathDisplay
            v-if="d.breadcrumb?.length"
            :segments="d.breadcrumb"
            compact
          />
          <span v-else>{{ d.name }}</span>
        </button>
      </div>

      <div
        v-else-if="parentQuery.trim().length >= 2 && !parentLoading && searchResults.length === 0"
        class="parent-dept-fallback"
      >
        <p class="parent-dept-hint">{{ t('login.parentDepartmentNoMatch') }}</p>
        <button type="button" class="btn-link-action" :disabled="disabled" @click="acceptTypedName">
          {{ t('login.parentDepartmentAcceptTyped', { name: parentQuery.trim() }) }}
        </button>
        <button type="button" class="inline-link" :disabled="disabled" @click="showSpecify = !showSpecify">
          {{ showSpecify ? t('login.parentDepartmentSpecifyHide') : t('login.parentDepartmentSpecifyShow') }}
        </button>
      </div>

      <div v-if="showSpecify" class="parent-dept-specify">
        <p class="parent-dept-hint">{{ t('login.parentDepartmentSpecifyHint') }}</p>
        <div class="parent-dept-levels">
          <label v-for="level in hierarchyLevels" :key="level.value" class="parent-dept-level">
            <input v-model="hierarchyLevel" type="radio" :value="level.value" :disabled="disabled" />
            <span>{{ level.label }}</span>
          </label>
        </div>
        <input
          v-model="hierarchyDetail"
          type="text"
          class="form-input"
          :placeholder="hierarchyPlaceholder"
          :disabled="disabled"
        />
        <button
          type="button"
          class="btn-link-action"
          :disabled="disabled || !hierarchyDetail.trim()"
          @click="applySpecified"
        >
          {{ t('login.parentDepartmentApplySpecified') }}
        </button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DepartmentPathDisplay from '@/components/common/DepartmentPathDisplay.vue'
import {
  getPublicDepartmentBreadcrumb,
  searchPublicDepartments,
  type DepartmentPathSegment,
  type PublicDepartmentSearchResult,
} from '@/api/publicDepartments'

export type ParentDepartmentPickerValue = {
  departmentId: string
  departmentName: string
}

const props = defineProps<{
  organisationId: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: ParentDepartmentPickerValue | null]
}>()

const { t } = useI18n()

const parentQuery = ref('')
const parentLoading = ref(false)
const searchResults = ref<PublicDepartmentSearchResult[]>([])
const picked = ref<ParentDepartmentPickerValue | null>(null)
const pickedBreadcrumb = ref<DepartmentPathSegment[]>([])
const skipped = ref(false)
const showSpecify = ref(false)
const hierarchyLevel = ref<'kanton' | 'region' | 'abteilung'>('kanton')
const hierarchyDetail = ref('')

let searchTimer: ReturnType<typeof setTimeout> | null = null

const disabled = computed(() => props.disabled ?? false)

const hierarchyLevels = computed(() => [
  { value: 'kanton' as const, label: t('login.parentHierarchyKanton') },
  { value: 'region' as const, label: t('login.parentHierarchyRegion') },
  { value: 'abteilung' as const, label: t('login.parentHierarchyAbteilung') },
])

const hierarchyPlaceholder = computed(() => {
  if (hierarchyLevel.value === 'kanton') return t('login.parentHierarchyKantonPlaceholder')
  if (hierarchyLevel.value === 'region') return t('login.parentHierarchyRegionPlaceholder')
  return t('login.parentHierarchyAbteilungPlaceholder')
})

function emitValue() {
  if (skipped.value) {
    emit('update:modelValue', null)
    return
  }
  emit('update:modelValue', picked.value)
}

async function loadPickedBreadcrumb(department: PublicDepartmentSearchResult) {
  if (department.breadcrumb?.length) {
    pickedBreadcrumb.value = department.breadcrumb
    return
  }
  try {
    pickedBreadcrumb.value = await getPublicDepartmentBreadcrumb(props.organisationId, department.id)
  } catch {
    pickedBreadcrumb.value = []
  }
}

function pickParent(department: PublicDepartmentSearchResult) {
  picked.value = { departmentId: department.id, departmentName: department.name }
  parentQuery.value = department.name
  searchResults.value = []
  showSpecify.value = false
  void loadPickedBreadcrumb(department)
  emitValue()
}

function skipParent() {
  skipped.value = true
  picked.value = null
  emitValue()
}

function resetPicker() {
  skipped.value = false
  picked.value = null
  pickedBreadcrumb.value = []
  parentQuery.value = ''
  searchResults.value = []
  showSpecify.value = false
  hierarchyDetail.value = ''
  emitValue()
}

function acceptTypedName() {
  const name = parentQuery.value.trim()
  if (!name) return
  picked.value = { departmentId: '', departmentName: name }
  pickedBreadcrumb.value = []
  showSpecify.value = false
  emitValue()
}

function applySpecified() {
  const detail = hierarchyDetail.value.trim()
  if (!detail) return
  const prefix =
    hierarchyLevel.value === 'kanton'
      ? t('login.parentHierarchyKanton')
      : hierarchyLevel.value === 'region'
        ? t('login.parentHierarchyRegion')
        : t('login.parentHierarchyAbteilung')
  picked.value = { departmentId: '', departmentName: `${prefix}: ${detail}` }
  pickedBreadcrumb.value = []
  parentQuery.value = picked.value.departmentName
  showSpecify.value = false
  emitValue()
}

async function runParentSearch() {
  const q = parentQuery.value.trim()
  if (q.length < 2 || !props.organisationId) {
    searchResults.value = []
    return
  }
  parentLoading.value = true
  try {
    searchResults.value = await searchPublicDepartments(props.organisationId, q)
  } catch {
    searchResults.value = []
  } finally {
    parentLoading.value = false
  }
}

watch(parentQuery, (value) => {
  if (picked.value && value.trim() === picked.value.departmentName) {
    return
  }
  if (picked.value) {
    picked.value = null
    emitValue()
  }
  showSpecify.value = false
  if (searchTimer) clearTimeout(searchTimer)
  if (value.trim().length < 2) {
    searchResults.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runParentSearch()
  }, 300)
})

watch(
  () => props.organisationId,
  () => {
    resetPicker()
  },
)
</script>

<style scoped>
.parent-dept-picker {
  margin-top: 12px;
}

.parent-dept-intro {
  margin: 0 0 8px;
  font-size: 13px;
  color: #6b7280;
}

.parent-dept-search {
  margin-bottom: 6px;
}

.parent-dept-skip {
  display: block;
  margin-bottom: 8px;
}

.parent-dept-hint {
  margin: 8px 0;
  font-size: 13px;
  color: #6b7280;
}

.parent-dept-results {
  margin-top: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.parent-dept-result-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-top: 1px solid #f3f4f6;
  background: #fff;
  text-align: left;
  cursor: pointer;
}

.parent-dept-result-item:hover:not(:disabled) {
  background: #f3f4f6;
}

.parent-dept-result-meta {
  font-size: 11px;
  color: #9ca3af;
}

.parent-dept-fallback {
  margin-top: 8px;
}

.parent-dept-specify {
  margin-top: 12px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.parent-dept-levels {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 16px;
  margin: 10px 0;
}

.parent-dept-level {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  cursor: pointer;
}

.parent-dept-picked,
.parent-dept-skipped {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
  font-size: 14px;
  color: #166534;
}

.btn-link-action {
  display: block;
  width: 100%;
  margin: 8px 0;
  padding: 8px 12px;
  border: 1px dashed #93c5fd;
  border-radius: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 14px;
  text-align: left;
  cursor: pointer;
}

.btn-link-action:hover:not(:disabled) {
  background: #dbeafe;
}

.inline-link {
  background: none;
  border: none;
  padding: 0;
  margin-left: 8px;
  color: #2563eb;
  cursor: pointer;
  font-size: inherit;
  text-decoration: underline;
}
</style>
