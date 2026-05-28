<template>
  <div class="register-dept-picker">
    <label :for="inputId" class="form-label">{{ t('login.departmentLabel') }}</label>

    <div v-if="selected" class="register-dept-selected">
      <DepartmentPathDisplay v-if="selectedBreadcrumb.length > 0" :segments="selectedBreadcrumb" />
      <template v-else>
        <span>{{ selected.name }}</span>
        <span class="register-dept-selected-org">({{ selected.organisation_name }})</span>
      </template>
      <button type="button" class="inline-link" :disabled="disabled" @click="clearSelection">
        {{ t('login.departmentClear') }}
      </button>
    </div>

    <template v-else>
      <div class="register-dept-search">
        <input
          :id="inputId"
          v-model="query"
          type="text"
          class="form-input"
          :placeholder="t('login.departmentSearchPlaceholder')"
          :disabled="disabled || !organisationId"
          autocomplete="off"
          @focus="dropdownOpen = true"
        />
        <p v-if="!organisationId" class="register-dept-hint">{{ t('login.departmentPickOrgFirst') }}</p>
      </div>

      <div v-if="loading" class="register-dept-hint">{{ t('login.departmentSearchLoading') }}</div>

      <div
        v-else-if="dropdownOpen && query.trim().length >= 2 && (inOrgResults.length > 0 || otherOrgResults.length > 0)"
        class="register-dept-results"
      >
        <template v-if="inOrgResults.length > 0">
          <p class="register-dept-results-label">{{ t('login.departmentResultsInOrg') }}</p>
          <button
            v-for="d in inOrgResults"
            :key="d.id"
            type="button"
            class="register-dept-result-item"
            :disabled="disabled"
            @click="pickDepartment(d)"
          >
            <DepartmentPathDisplay
              v-if="d.breadcrumb?.length"
              :segments="d.breadcrumb"
              compact
            />
            <span v-else>{{ d.name }}</span>
          </button>
        </template>
        <template v-if="otherOrgResults.length > 0">
          <p class="register-dept-results-label">{{ t('login.departmentResultsOtherOrgs') }}</p>
          <button
            v-for="d in otherOrgResults"
            :key="`${d.organisation_id}-${d.id}`"
            type="button"
            class="register-dept-result-item register-dept-result-item--other"
            :disabled="disabled"
            @click="pickDepartment(d)"
          >
            <DepartmentPathDisplay
              v-if="d.breadcrumb?.length"
              :segments="d.breadcrumb"
              compact
            />
            <template v-else>
              <span>{{ d.name }}</span>
              <span class="register-dept-result-org">{{ d.organisation_name }}</span>
            </template>
          </button>
        </template>
      </div>

      <div
        v-else-if="query.trim().length >= 2 && !loading && inOrgResults.length === 0 && otherOrgResults.length === 0"
        class="register-dept-fallback"
      >
        <p class="register-dept-hint">{{ t('login.departmentNoMatch') }}</p>
        <button type="button" class="inline-link" :disabled="disabled" @click="openManualRequest">
          {{ t('login.departmentRequestNew') }}
        </button>
      </div>

      <div v-else-if="query.trim().length >= 2 && !loading && inOrgResults.length === 0 && otherOrgResults.length > 0" class="register-dept-fallback">
        <p class="register-dept-hint">{{ t('login.departmentMaybeOtherOrg') }}</p>
      </div>

      <div v-if="manualRequestOpen" class="register-dept-manual">
        <p class="register-dept-manual-name">
          {{ t('login.departmentRequestAs', { name: query.trim() }) }}
        </p>
        <ParentDepartmentPicker
          :organisation-id="organisationId"
          :disabled="disabled"
          @update:model-value="onParentPicked"
        />
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ParentDepartmentPicker, {
  type ParentDepartmentPickerValue,
} from '@/components/auth/ParentDepartmentPicker.vue'
import DepartmentPathDisplay from '@/components/common/DepartmentPathDisplay.vue'
import {
  getPublicDepartmentBreadcrumb,
  searchPublicDepartmentsGlobal,
  type DepartmentPathSegment,
  type PublicDepartmentSearchResult,
} from '@/api/publicDepartments'

export interface RegisterDepartmentManualRequest {
  departmentName: string
  parentDepartmentId: string
  parentDepartmentName?: string
}

const props = defineProps<{
  organisationId: string
  disabled?: boolean
  initialQuery?: string
}>()

const emit = defineEmits<{
  'update:selected': [value: PublicDepartmentSearchResult | null]
  'update:organisationId': [organisationId: string]
  'update:manual': [value: RegisterDepartmentManualRequest | null]
}>()

const { t } = useI18n()
const inputId = `register-dept-${Math.random().toString(36).slice(2, 9)}`

const query = ref('')
const loading = ref(false)
const dropdownOpen = ref(false)
const selected = ref<PublicDepartmentSearchResult | null>(null)
const inOrgResults = ref<PublicDepartmentSearchResult[]>([])
const otherOrgResults = ref<PublicDepartmentSearchResult[]>([])
const manualRequestOpen = ref(false)
const parentPick = ref<ParentDepartmentPickerValue | null>(null)
const selectedBreadcrumb = ref<DepartmentPathSegment[]>([])

let searchTimer: ReturnType<typeof setTimeout> | null = null

const disabled = computed(() => props.disabled ?? false)

async function loadSelectedBreadcrumb(department: PublicDepartmentSearchResult) {
  if (department.breadcrumb?.length) {
    selectedBreadcrumb.value = department.breadcrumb
    return
  }
  try {
    selectedBreadcrumb.value = await getPublicDepartmentBreadcrumb(
      department.organisation_id,
      department.id,
    )
  } catch {
    selectedBreadcrumb.value = []
  }
}

function emitManualState() {
  if (selected.value) {
    emit('update:manual', null)
    return
  }
  if (manualRequestOpen.value && query.value.trim() !== '') {
    emit('update:manual', {
      departmentName: query.value.trim(),
      parentDepartmentId: parentPick.value?.departmentId ?? '',
      parentDepartmentName: parentPick.value?.departmentName,
    })
  } else {
    emit('update:manual', null)
  }
}

function pickDepartment(department: PublicDepartmentSearchResult) {
  selected.value = department
  query.value = department.name
  inOrgResults.value = []
  otherOrgResults.value = []
  dropdownOpen.value = false
  manualRequestOpen.value = false
  parentPick.value = null
  void loadSelectedBreadcrumb(department)
  if (department.organisation_id !== props.organisationId) {
    emit('update:organisationId', department.organisation_id)
  }
  emit('update:selected', department)
  emitManualState()
}

function clearSelection() {
  selected.value = null
  selectedBreadcrumb.value = []
  query.value = ''
  manualRequestOpen.value = false
  parentPick.value = null
  emit('update:selected', null)
  emitManualState()
}

function onParentPicked(value: ParentDepartmentPickerValue | null) {
  parentPick.value = value
  emitManualState()
}

function openManualRequest() {
  manualRequestOpen.value = true
  dropdownOpen.value = false
  emit('update:selected', null)
  emitManualState()
}

async function runSearch() {
  const q = query.value.trim()
  if (q.length < 2 || !props.organisationId) {
    inOrgResults.value = []
    otherOrgResults.value = []
    return
  }
  loading.value = true
  try {
    const data = await searchPublicDepartmentsGlobal(q, props.organisationId)
    inOrgResults.value = data.in_organisation
    otherOrgResults.value = data.other_organisations
    dropdownOpen.value = true
    if (data.in_organisation.length > 0 || data.other_organisations.length > 0) {
      manualRequestOpen.value = false
    }
  } catch {
    inOrgResults.value = []
    otherOrgResults.value = []
  } finally {
    loading.value = false
    emitManualState()
  }
}

watch(
  () => props.initialQuery,
  (name) => {
    if (name && !selected.value) {
      query.value = name
    }
  },
  { immediate: true },
)

watch(query, (value) => {
  if (selected.value && value.trim() !== selected.value.name) {
    selected.value = null
    emit('update:selected', null)
  }
  manualRequestOpen.value = false
  if (searchTimer) clearTimeout(searchTimer)
  const trimmed = value.trim()
  if (trimmed.length < 2) {
    inOrgResults.value = []
    otherOrgResults.value = []
    emitManualState()
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch()
  }, 300)
})

watch(
  () => props.organisationId,
  (orgId) => {
    if (selected.value && selected.value.organisation_id !== orgId) {
      clearSelection()
    }
    parentPick.value = null
    if (query.value.trim().length >= 2) {
      void runSearch()
    }
  },
  { immediate: true },
)

watch(manualRequestOpen, () => emitManualState())
</script>

<style scoped>
.register-dept-picker {
  margin-bottom: 14px;
}

.register-dept-search {
  position: relative;
}

.register-dept-hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: #6b7280;
}

.register-dept-results {
  margin-top: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.register-dept-results-label {
  margin: 0;
  padding: 8px 12px 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.register-dept-result-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-top: 1px solid #f3f4f6;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.register-dept-result-item:hover:not(:disabled) {
  background: #f3f4f6;
}

.register-dept-result-item--other {
  background: #fffbeb;
}

.register-dept-result-org {
  font-size: 12px;
  color: #6b7280;
}

.register-dept-selected {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
  margin-top: 8px;
  font-size: 14px;
  color: #166534;
}

.register-dept-selected-org {
  color: #6b7280;
  margin-left: 4px;
}

.register-dept-fallback {
  margin-top: 10px;
}

.register-dept-manual {
  margin-top: 12px;
  padding: 12px;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  background: #f9fafb;
}

.register-dept-manual-name {
  margin: 0 0 10px;
  font-size: 14px;
  color: #374151;
}

.inline-link {
  background: none;
  border: none;
  padding: 0;
  color: #2563eb;
  cursor: pointer;
  font-size: inherit;
  text-decoration: underline;
}
</style>
