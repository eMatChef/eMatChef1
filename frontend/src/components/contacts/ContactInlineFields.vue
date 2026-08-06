<script setup lang="ts">
/**
 * Inline-Felder für Kontakt-Abschnitte (Basics / Kommunikation / Adresse / Notizen).
 * Wird in Detail-Bearbeitung und Create-Ansicht wiederverwendet.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ADDRESS_TYPES, SWISS_CANTONS, type AddressFormData } from '@/api/addresses'
import { ECheckbox, ESelect, ETextField, ETextarea } from '@/components/form/base'

export type ContactInlineSection = 'basics' | 'communication' | 'address' | 'notes'

const props = defineProps<{
  section: ContactInlineSection | 'all'
  modelValue: Partial<AddressFormData>
  /** Eingeschränkte Typen (User-Rolle) oder null = alle */
  allowedTypes?: string[] | null
  showPinColor?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<AddressFormData>]
}>()

const { t, te } = useI18n()

const form = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function patch<K extends keyof AddressFormData>(key: K, value: AddressFormData[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function show(section: ContactInlineSection): boolean {
  return props.section === 'all' || props.section === section
}

function typeLabel(key: string): string {
  const path = `settings.addressForm.types.${key}` as const
  return te(path) ? t(path) : (ADDRESS_TYPES[key] || key)
}

const typeItems = computed(() => {
  const keys = props.allowedTypes?.length
    ? Object.keys(ADDRESS_TYPES).filter((k) => props.allowedTypes!.includes(k))
    : Object.keys(ADDRESS_TYPES)
  // event_delivery / event_poi nur als Kind, nicht beim normalen Anlegen
  const filtered = keys.filter((k) => k !== 'event_delivery' && k !== 'event_poi')
  return filtered.map((key) => ({ title: typeLabel(key), value: key }))
})

const cantonItems = computed(() => [
  { title: t('settings.addressForm.selectPlaceholder'), value: '' },
  ...Object.entries(SWISS_CANTONS).map(([code, name]) => ({
    title: `${code} - ${name}`,
    value: code,
  })),
])

const PIN_COLOR_PRESETS = [
  '#16a34a',
  '#0d9488',
  '#2563eb',
  '#7c3aed',
  '#db2777',
  '#ea580c',
  '#ca8a04',
  '#475569',
] as const
</script>

<template>
  <div class="contact-inline-fields">
    <template v-if="show('basics')">
      <div class="form-row two-cols">
        <ETextField
          :model-value="form.name ?? ''"
          :label="t('settings.addressForm.designation')"
          :placeholder="t('settings.addressForm.designationPlaceholder')"
          hide-details="auto"
          @update:model-value="patch('name', String($event ?? '') || null)"
        />
        <ESelect
          :model-value="form.type ?? 'general'"
          :items="typeItems"
          :label="t('settings.addressModal.typeField')"
          hide-details="auto"
          @update:model-value="patch('type', String($event ?? 'general'))"
        />
      </div>
      <ECheckbox
        v-if="form.type === 'storage'"
        :model-value="!!form.is_primary"
        :label="t('settings.addressModal.primaryStorageHint')"
        hide-details
        @update:model-value="patch('is_primary', !!$event)"
      />
      <div v-if="showPinColor && form.type === 'event_poi'" class="pin-color-field">
        <span class="form-label">{{ t('settings.addressModal.pinColorLabel') }}</span>
        <div class="pin-color-swatches">
          <button
            v-for="color in PIN_COLOR_PRESETS"
            :key="color"
            type="button"
            class="pin-color-swatch"
            :class="{ 'is-selected': form.pin_color === color }"
            :style="{ background: color }"
            :title="color"
            @click="patch('pin_color', color)"
          />
        </div>
      </div>
      <ETextField
        :model-value="form.company ?? ''"
        :label="t('settings.addressForm.company')"
        :placeholder="t('common.optional')"
        hide-details="auto"
        @update:model-value="patch('company', String($event ?? '') || null)"
      />
      <div class="form-row two-cols">
        <ETextField
          :model-value="form.contact_first_name ?? ''"
          :label="t('settings.addressForm.contactFirstName')"
          :placeholder="t('common.optional')"
          hide-details="auto"
          @update:model-value="patch('contact_first_name', String($event ?? '') || null)"
        />
        <ETextField
          :model-value="form.contact_last_name ?? ''"
          :label="t('settings.addressForm.contactLastName')"
          :placeholder="t('common.optional')"
          hide-details="auto"
          @update:model-value="patch('contact_last_name', String($event ?? '') || null)"
        />
      </div>
    </template>

    <template v-if="show('communication')">
      <ETextField
        :model-value="form.email ?? ''"
        :label="t('settings.addressForm.email')"
        :placeholder="t('settings.addressForm.emailPlaceholder')"
        hide-details="auto"
        @update:model-value="patch('email', String($event ?? '') || null)"
      />
      <div class="form-row two-cols">
        <ETextField
          :model-value="form.phone ?? ''"
          :label="t('settings.addressForm.phone')"
          :placeholder="t('settings.addressForm.phonePlaceholder')"
          hide-details="auto"
          @update:model-value="patch('phone', String($event ?? '') || null)"
        />
        <ETextField
          :model-value="form.mobile ?? ''"
          :label="t('settings.addressForm.mobile')"
          :placeholder="t('settings.addressForm.mobilePlaceholder')"
          hide-details="auto"
          @update:model-value="patch('mobile', String($event ?? '') || null)"
        />
      </div>
    </template>

    <template v-if="show('address')">
      <ETextField
        :model-value="form.address_line2 ?? ''"
        :label="t('settings.addressForm.addressExtra')"
        :placeholder="t('settings.addressForm.addressExtraPlaceholder')"
        hide-details="auto"
        @update:model-value="patch('address_line2', String($event ?? '') || null)"
      />
      <div class="form-row street-number-row">
        <ETextField
          :model-value="form.street ?? ''"
          :label="t('settings.addressForm.street')"
          :placeholder="t('settings.addressForm.streetPlaceholder')"
          hide-details="auto"
          @update:model-value="patch('street', String($event ?? '') || null)"
        />
        <ETextField
          :model-value="form.street_number ?? ''"
          class="street-number-field"
          :label="t('settings.addressForm.streetNumber')"
          :placeholder="t('settings.addressForm.streetNumberPlaceholder')"
          hide-details="auto"
          @update:model-value="patch('street_number', String($event ?? '') || null)"
        />
      </div>
      <div class="form-row postal-city-row">
        <ETextField
          :model-value="form.postal_code ?? ''"
          class="postal-field"
          :label="t('settings.addressForm.postalCode')"
          :placeholder="t('settings.addressForm.postalPlaceholder')"
          hide-details="auto"
          @update:model-value="patch('postal_code', String($event ?? '') || null)"
        />
        <ETextField
          :model-value="form.city ?? ''"
          :label="t('settings.addressForm.city')"
          :placeholder="t('settings.addressForm.cityPlaceholder')"
          hide-details="auto"
          @update:model-value="patch('city', String($event ?? '') || null)"
        />
      </div>
      <div class="form-row two-cols">
        <ESelect
          :model-value="form.canton ?? ''"
          :items="cantonItems"
          :label="t('settings.addressForm.canton')"
          clearable
          hide-details="auto"
          @update:model-value="patch('canton', ($event ? String($event) : null) as string | null)"
        />
        <ETextField
          :model-value="form.country ?? 'Schweiz'"
          :label="t('settings.addressForm.country')"
          hide-details="auto"
          @update:model-value="patch('country', String($event ?? 'Schweiz'))"
        />
      </div>
    </template>

    <template v-if="show('notes')">
      <ETextarea
        :model-value="form.additional_info ?? ''"
        :label="t('settings.addressForm.additionalInfo')"
        :placeholder="t('settings.addressForm.additionalInfoPlaceholder')"
        rows="3"
        hide-details="auto"
        @update:model-value="patch('additional_info', String($event ?? '') || null)"
      />
    </template>
  </div>
</template>

<style scoped>
.contact-inline-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.form-row {
  display: grid;
  gap: 12px;
}

.form-row.two-cols {
  grid-template-columns: 1fr 1fr;
}

.form-row.street-number-row {
  grid-template-columns: 1fr 100px;
}

.form-row.postal-city-row {
  grid-template-columns: 110px 1fr;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.pin-color-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pin-color-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pin-color-swatch {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #cbd5e1;
  cursor: pointer;
  padding: 0;
}

.pin-color-swatch.is-selected {
  box-shadow: 0 0 0 2px #0f172a;
}

@media (max-width: 640px) {
  .form-row.two-cols,
  .form-row.street-number-row,
  .form-row.postal-city-row {
    grid-template-columns: 1fr;
  }
}
</style>
