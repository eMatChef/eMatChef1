<template>
  <div class="address-form">
    <!-- Typ -->
    <div v-if="showType" class="form-row">
      <div class="form-group">
        <label class="form-label">Adress-Typ</label>
        <select v-model="formData.type" class="form-select">
          <option v-for="(label, key) in ADDRESS_TYPES" :key="key" :value="key">
            {{ label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Name / Firma -->
    <div class="form-row two-cols">
      <div class="form-group">
        <label class="form-label">Bezeichnung</label>
        <input 
          v-model="formData.name" 
          type="text" 
          class="form-input" 
          placeholder="z.B. Hauptlager, Büro"
        />
      </div>
      <div class="form-group">
        <label class="form-label">Firma/Organisation</label>
        <input 
          v-model="formData.company" 
          type="text" 
          class="form-input" 
          placeholder="Optional"
        />
      </div>
    </div>

    <!-- Adresszusatz -->
    <div v-if="showExtended" class="form-row">
      <div class="form-group">
        <label class="form-label">Adresszusatz</label>
        <input 
          v-model="formData.address_line2" 
          type="text" 
          class="form-input" 
          placeholder="c/o, Postfach, Abteilung..."
        />
      </div>
    </div>

    <!-- Strasse + Nr -->
    <div class="form-row two-cols-unequal">
      <div class="form-group flex-grow">
        <label class="form-label">Strasse</label>
        <input 
          v-model="formData.street" 
          type="text" 
          class="form-input" 
          placeholder="Strassenname"
        />
      </div>
      <div class="form-group" style="width: 100px;">
        <label class="form-label">Nr.</label>
        <input 
          v-model="formData.street_number" 
          type="text" 
          class="form-input" 
          placeholder="123a"
        />
      </div>
    </div>

    <!-- PLZ + Ort -->
    <div class="form-row two-cols-unequal">
      <div class="form-group" style="width: 120px;">
        <label class="form-label">PLZ</label>
        <input 
          v-model="formData.postal_code" 
          type="text" 
          class="form-input" 
          placeholder="8000"
        />
      </div>
      <div class="form-group flex-grow">
        <label class="form-label">Ort</label>
        <input 
          v-model="formData.city" 
          type="text" 
          class="form-input" 
          placeholder="Zürich"
        />
      </div>
    </div>

    <!-- Kanton + Land -->
    <div class="form-row two-cols">
      <div class="form-group">
        <label class="form-label">Kanton</label>
        <select v-model="formData.canton" class="form-select">
          <option value="">-- Auswählen --</option>
          <option v-for="(name, code) in SWISS_CANTONS" :key="code" :value="code">
            {{ code }} - {{ name }}
          </option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Land</label>
        <input 
          v-model="formData.country" 
          type="text" 
          class="form-input"
        />
      </div>
    </div>

    <!-- Kontakt: E-Mail, Telefon, Mobil -->
    <div v-if="showExtended" class="form-row three-cols">
      <div class="form-group">
        <label class="form-label">E-Mail</label>
        <input 
          v-model="formData.email" 
          type="email" 
          class="form-input" 
          placeholder="name@firma.ch"
        />
      </div>
      <div class="form-group">
        <label class="form-label">Telefon</label>
        <input 
          v-model="formData.phone" 
          type="tel" 
          class="form-input" 
          placeholder="+41 44 123 45 67"
        />
      </div>
      <div class="form-group">
        <label class="form-label">Mobil</label>
        <input 
          v-model="formData.mobile" 
          type="tel" 
          class="form-input" 
          placeholder="+41 79 123 45 67"
        />
      </div>
    </div>

    <!-- Karte -->
    <div v-if="showMap" class="form-row">
      <div class="form-group">
        <label class="form-label">
          Standort auf Karte
          <button type="button" @click="searchCoordinates" class="search-coords-btn">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M7 12A5 5 0 107 2a5 5 0 000 10zM14 14l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Suchen
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
        <p class="form-hint">Klicken Sie auf die Karte oder suchen Sie die Adresse um den Standort zu setzen.</p>
      </div>
    </div>

    <!-- Zusätzliche Infos -->
    <div v-if="showExtended" class="form-row">
      <div class="form-group">
        <label class="form-label">Zusätzliche Informationen</label>
        <textarea 
          v-model="formData.additional_info" 
          class="form-textarea" 
          rows="3"
          placeholder="Anfahrt, Besonderheiten..."
        ></textarea>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import MapView from './MapView.vue'
import { SWISS_CANTONS, ADDRESS_TYPES, type AddressFormData } from '@/api/addresses'

interface Props {
  modelValue: AddressFormData
  showType?: boolean
  showExtended?: boolean
  showMap?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showType: false,
  showExtended: true,
  showMap: true
})

const emit = defineEmits<{
  'update:modelValue': [value: AddressFormData]
}>()

const mapRef = ref<InstanceType<typeof MapView>>()

// Lokale Kopie für v-model
const formData = ref<AddressFormData>({ ...props.modelValue })

// Vollständige Adresse für Geocoding
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

// Koordinaten suchen
function searchCoordinates() {
  mapRef.value?.searchAddress()
}

// Watch für externe Änderungen
watch(() => props.modelValue, (newVal) => {
  formData.value = { ...newVal }
}, { deep: true })

// Emit bei Änderungen
watch(formData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })
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

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
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

.form-input,
.form-select,
.form-textarea {
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  color: #1f2937;
  background: white;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
  
  .form-group[style*="width"] {
    width: 100% !important;
  }
}
</style>
