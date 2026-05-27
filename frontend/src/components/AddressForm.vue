<template>
  <div class="address-form">
    <!-- Typ -->
    <div v-if="showType" class="form-row">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.type') }}</label>
        <select v-model="formData.type" class="form-select">
          <option v-for="key in addressTypeKeys" :key="key" :value="key">
            {{ t(`settings.addressForm.types.${key}`) }}
          </option>
        </select>
      </div>
    </div>

    <!-- Name / Firma -->
    <div class="form-row two-cols">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.designation') }}</label>
        <input
          v-model="formData.name"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.designationPlaceholder')"
        />
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.company') }}</label>
        <input
          v-model="formData.company"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.optional')"
        />
      </div>
    </div>

    <!-- Adresszusatz -->
    <div v-if="showExtended" class="form-row">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.addressExtra') }}</label>
        <input
          v-model="formData.address_line2"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.addressExtraPlaceholder')"
        />
      </div>
    </div>

    <!-- Strasse + Nr -->
    <div class="form-row two-cols-unequal">
      <div class="form-group flex-grow">
        <label class="form-label">{{ t('settings.addressForm.street') }}</label>
        <input
          v-model="formData.street"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.streetPlaceholder')"
        />
      </div>
      <div class="form-group" style="width: 100px">
        <label class="form-label">{{ t('settings.addressForm.streetNumber') }}</label>
        <input
          v-model="formData.street_number"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.streetNumberPlaceholder')"
        />
      </div>
    </div>

    <!-- PLZ + Ort -->
    <div class="form-row two-cols-unequal">
      <div class="form-group" style="width: 120px">
        <label class="form-label">{{ t('settings.addressForm.postalCode') }}</label>
        <input
          v-model="formData.postal_code"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.postalPlaceholder')"
        />
      </div>
      <div class="form-group flex-grow">
        <label class="form-label">{{ t('settings.addressForm.city') }}</label>
        <input
          v-model="formData.city"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.cityPlaceholder')"
        />
      </div>
    </div>

    <!-- Kanton + Land -->
    <div class="form-row two-cols">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.canton') }}</label>
        <select v-model="formData.canton" class="form-select">
          <option value="">{{ t('settings.addressForm.selectPlaceholder') }}</option>
          <option v-for="(name, code) in SWISS_CANTONS" :key="code" :value="code">
            {{ code }} - {{ name }}
          </option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.country') }}</label>
        <input v-model="formData.country" type="text" class="form-input" />
      </div>
    </div>

    <!-- Kontakt: Vorname, Nachname -->
    <div v-if="showExtended" class="form-row two-cols">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.contactFirstName') }}</label>
        <input
          v-model="formData.contact_first_name"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.optional')"
        />
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.contactLastName') }}</label>
        <input
          v-model="formData.contact_last_name"
          type="text"
          class="form-input"
          :placeholder="t('settings.addressForm.optional')"
        />
      </div>
    </div>

    <!-- Kontakt: E-Mail, Telefon, Mobil -->
    <div v-if="showExtended" class="form-row three-cols">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.email') }}</label>
        <input
          v-model="formData.email"
          type="email"
          class="form-input"
          :placeholder="t('settings.addressForm.emailPlaceholder')"
        />
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.phone') }}</label>
        <input
          v-model="formData.phone"
          type="tel"
          class="form-input"
          :placeholder="t('settings.addressForm.phonePlaceholder')"
        />
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.mobile') }}</label>
        <input
          v-model="formData.mobile"
          type="tel"
          class="form-input"
          :placeholder="t('settings.addressForm.mobilePlaceholder')"
        />
      </div>
    </div>

    <!-- Karte -->
    <div v-if="showMap" class="form-row">
      <div class="form-group">
        <label class="form-label">
          {{ t('settings.addressForm.mapLabel') }}
          <button type="button" class="search-coords-btn" @click="searchCoordinates">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path
                d="M7 12A5 5 0 107 2a5 5 0 000 10zM14 14l-3-3"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
              />
            </svg>
            {{ t('settings.addressForm.mapSearch') }}
          </button>
        </label>
        <MapView
          ref="mapRef"
          :latitude="formData.latitude"
          :longitude="formData.longitude"
          :address="fullAddress"
          :editable="true"
          height="250px"
          @update:latitude="formData.latitude = $event"
          @update:longitude="formData.longitude = $event"
        />
        <p class="form-hint">{{ t('settings.addressForm.mapHint') }}</p>
      </div>
    </div>

    <!-- Zusätzliche Infos -->
    <div v-if="showExtended" class="form-row">
      <div class="form-group">
        <label class="form-label">{{ t('settings.addressForm.additionalInfo') }}</label>
        <textarea
          v-model="formData.additional_info"
          class="form-textarea"
          rows="3"
          :placeholder="t('settings.addressForm.additionalInfoPlaceholder')"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import MapView from './MapView.vue'
import { SWISS_CANTONS, ADDRESS_TYPES, type AddressFormData } from '@/api/addresses'

const { t } = useI18n()
const addressTypeKeys = Object.keys(ADDRESS_TYPES) as (keyof typeof ADDRESS_TYPES)[]

interface Props {
  modelValue: AddressFormData
  showType?: boolean
  showExtended?: boolean
  showMap?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showType: false,
  showExtended: true,
  showMap: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: AddressFormData]
}>()

const mapRef = ref<InstanceType<typeof MapView>>()

const formData = ref<AddressFormData>({ ...props.modelValue })

const fullAddress = computed(() => {
  const parts = []
  if (formData.value.street) {
    let street = formData.value.street
    if (formData.value.street_number) {
      street += ' ' + formData.value.street_number
    }
    parts.push(street)
  }
  if (formData.value.postal_code && formData.value.city) {
    parts.push(formData.value.postal_code + ' ' + formData.value.city)
  }
  if (formData.value.country) {
    parts.push(formData.value.country)
  }
  return parts.join(', ')
})

function searchCoordinates() {
  mapRef.value?.searchAddress()
}

watch(
  () => props.modelValue,
  (newVal) => {
    formData.value = { ...newVal }
  },
  { deep: true }
)

watch(
  formData,
  (newVal) => {
    emit('update:modelValue', { ...newVal })
  },
  { deep: true }
)
</script>

<style scoped>
.address-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-row {
  display: flex;
  gap: 16px;
}

.form-row.two-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-row.three-cols {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 16px;
}

.form-row.two-cols-unequal {
  display: flex;
  gap: 16px;
}

.form-group.flex-grow {
  flex: 1;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 8px;
}

.required {
  color: #dc2626;
}

.form-input::placeholder,
.form-textarea::placeholder {
  color: #9ca3af;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin: 4px 0 0 0;
}

.search-coords-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #e5e7eb;
  border: none;
  border-radius: 4px;
  font-size: 12px;
  color: #374151;
  cursor: pointer;
  margin-left: auto;
  transition: background 0.2s;
}

.search-coords-btn:hover {
  background: #d1d5db;
}

@media (max-width: 640px) {
  .form-row.two-cols,
  .form-row.three-cols,
  .form-row.two-cols-unequal {
    grid-template-columns: 1fr;
    flex-direction: column;
  }

  .form-group[style*='width'] {
    width: 100% !important;
  }
}
</style>
