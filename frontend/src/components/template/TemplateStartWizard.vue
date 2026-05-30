<template>
  <div class="modal-overlay">
    <div class="modal-dialog modal-dialog--structured modal-dialog--narrow">
      <div class="modal-header">
        <h2>{{ t('components.templateStartWizard.title') }}</h2>
        <button class="modal-close" type="button" @click="$emit('close')">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="wizard-step-indicator">
        <span v-for="n in 3" :key="n" class="wizard-step-dot" :class="{ active: step === n, done: step > n }" />
      </div>

      <div class="modal-body">
        <div v-if="step === 1" class="step-content">
          <p class="wizard-step-intro">{{ t('components.templateStartWizard.step1Intro') }}</p>
          <div class="choice-grid">
            <button
              v-for="choice in kindChoices"
              :key="choice.id"
              type="button"
              class="choice-card"
              :class="{ selected: templateKind === choice.id }"
              @click="templateKind = choice.id"
            >
              <strong>{{ choice.label }}</strong>
              <span>{{ choice.hint }}</span>
            </button>
          </div>
        </div>

        <div v-else-if="step === 2" class="step-content">
          <p class="wizard-step-intro">{{ t('components.templateStartWizard.step2Intro') }}</p>
          <div class="choice-grid">
            <button
              v-for="choice in domainChoices"
              :key="choice.id"
              type="button"
              class="choice-card"
              :class="{ selected: templateDomain === choice.id }"
              @click="templateDomain = choice.id"
            >
              <strong>{{ choice.label }}</strong>
              <span v-if="choice.hint">{{ choice.hint }}</span>
            </button>
          </div>
        </div>

        <div v-else class="step-content">
          <p class="wizard-step-intro">{{ t('components.templateStartWizard.step3Intro') }}</p>
          <div class="form-group">
            <label class="form-label">{{ t('components.templateStartWizard.manufacturerLabel') }}</label>
            <select v-model="manufacturerAddressId" class="form-select" :disabled="loadingOptions">
              <option :value="null">{{ t('components.templateStartWizard.manufacturerMixed') }}</option>
              <option v-for="opt in manufacturerOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
            <p v-if="loadingOptions" class="form-hint">{{ t('components.templateStartWizard.loadingOptions') }}</p>
            <p v-else-if="loadError" class="form-error">{{ loadError }}</p>
          </div>
        </div>
      </div>

      <div class="modal-footer modal-footer--plain">
        <button v-if="step > 1" type="button" class="btn-secondary" @click="step--">{{ t('common.back') }}</button>
        <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('common.cancel') }}</button>
        <button v-if="step < 3" type="button" class="btn-primary" :disabled="!canProceed" @click="step++">{{ t('common.next') }}</button>
        <button v-else type="button" class="btn-primary" :disabled="loadingOptions" @click="finish">{{ t('components.templateStartWizard.continueToEditor') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getTemplateManufacturerOptions,
  type TemplateKind,
  type TemplateDomain,
  type TemplateWizardResult,
  type TemplateManufacturerOption,
} from '@/api/templates'

const props = defineProps<{
  departmentId?: string
  templateScope: 'global' | 'department'
}>()

const emit = defineEmits<{
  close: []
  complete: [result: TemplateWizardResult]
}>()

const { t } = useI18n()
const step = ref(1)
const templateKind = ref<TemplateKind>('combo')
const templateDomain = ref<TemplateDomain>('general')
const manufacturerAddressId = ref<string | null>(null)
const manufacturerOptions = ref<TemplateManufacturerOption[]>([])
const loadingOptions = ref(false)
const loadError = ref('')

const kindChoices = computed(() => [
  { id: 'single_part' as TemplateKind, label: t('components.templateStartWizard.kindSinglePart'), hint: t('components.templateStartWizard.kindSinglePartHint') },
  { id: 'combo' as TemplateKind, label: t('components.templateStartWizard.kindCombo'), hint: t('components.templateStartWizard.kindComboHint') },
  { id: 'configurator' as TemplateKind, label: t('components.templateStartWizard.kindConfigurator'), hint: t('components.templateStartWizard.kindConfiguratorHint') },
])

const domainChoices = computed(() => [
  { id: 'tent' as TemplateDomain, label: t('components.templateStartWizard.domainTent'), hint: t('components.templateStartWizard.domainTentHint') },
  { id: 'kitchen' as TemplateDomain, label: t('components.templateStartWizard.domainKitchen'), hint: '' },
  { id: 'workshop' as TemplateDomain, label: t('components.templateStartWizard.domainWorkshop'), hint: '' },
  { id: 'first_aid' as TemplateDomain, label: t('components.templateStartWizard.domainFirstAid'), hint: '' },
  { id: 'general' as TemplateDomain, label: t('components.templateStartWizard.domainGeneral'), hint: t('components.templateStartWizard.domainGeneralHint') },
])

const canProceed = computed(() => (step.value === 1 ? !!templateKind.value : step.value === 2 ? !!templateDomain.value : true))

function finish() {
  const selected = manufacturerOptions.value.find((o) => o.id === manufacturerAddressId.value)
  emit('complete', {
    template_kind: templateKind.value,
    template_domain: templateDomain.value,
    manufacturer_address_id: manufacturerAddressId.value,
    manufacturer: selected?.label ?? null,
    material_type: templateKind.value === 'configurator' ? 'virtual_combo' : 'physical_combo',
  })
}

async function loadManufacturerOptions() {
  loadingOptions.value = true
  loadError.value = ''
  try {
    manufacturerOptions.value = await getTemplateManufacturerOptions(
      props.templateScope,
      props.templateScope === 'department' ? props.departmentId : undefined,
    )
  } catch (err: unknown) {
    const axiosErr = err as { response?: { data?: { error?: string } } }
    loadError.value = axiosErr.response?.data?.error || t('components.templateStartWizard.loadOptionsFailed')
  } finally {
    loadingOptions.value = false
  }
}

onMounted(() => loadManufacturerOptions())
</script>
