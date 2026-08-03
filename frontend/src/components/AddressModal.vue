<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="750"
    :title="modalTitle"
    scrollable
    persistent
    card-class="address-modal-card"
  >
    <form id="address-modal-form" class="address-modal-body" @submit.prevent="handleSubmit">
        <!-- Adress-Suche -->
        <div class="address-search-section">
          <div class="address-search-group">
            <label class="form-label address-search-label">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                <path d="M7 12A5 5 0 107 2a5 5 0 000 10zM14 14l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              {{ t('settings.addressModal.searchLabel') }}
            </label>
            <div class="address-search-row">
              <div class="address-search-wrapper">
                <input
                  v-model="addressSearchQuery"
                  type="text"
                  class="form-input address-search-input"
                  :placeholder="t('settings.addressModal.searchPlaceholder')"
                  @input="onAddressSearchInput"
                  @focus="onSearchFocus"
                  @blur="hideSearchResultsDelayed"
                  @keydown.enter.prevent="onAddressSearchInput"
                />
                <div v-if="isSearching" class="search-spinner"></div>
                <div v-if="showSearchResults && searchResults.length > 0" class="address-search-dropdown">
                  <div
                    v-for="(result, idx) in searchResults"
                    :key="idx"
                    class="address-search-item"
                    @mousedown.prevent="selectSearchResult(result)"
                  >
                    <div class="search-result-main">{{ result.name }}</div>
                    <div v-if="result.detail" class="search-result-detail">{{ result.detail }}</div>
                  </div>
                </div>
                <div v-if="showSearchResults && addressSearchQuery.length >= 3 && !isSearching && searchResults.length === 0" class="address-search-dropdown">
                  <div class="address-search-item search-empty">{{ t('settings.addressModal.searchEmpty') }}</div>
                </div>
              </div>
              <a
                v-if="addressSearchQuery.length >= 2"
                :href="'https://www.google.com/search?q=' + encodeURIComponent(addressSearchQuery + t('settings.addressModal.googleQuerySuffix'))"
                target="_blank"
                class="google-search-btn"
                :title="t('settings.addressModal.googleTitle')"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A10.96 10.96 0 001 12c0 1.78.43 3.46 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                {{ t('settings.addressModal.googleLabel') }}
              </a>
              <button
                type="button"
                class="paste-address-btn"
                @click="pasteAndParseAddress"
                :title="t('settings.addressModal.pasteTitle')"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                  <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/>
                </svg>
                {{ t('settings.addressModal.pasteLabel') }}
              </button>
            </div>
            <div v-if="pasteSuccess" class="paste-success-hint">{{ pasteSuccess }}</div>
            <div v-if="searchError" class="search-error-hint">{{ searchError }}</div>
          </div>
        </div>

        <!-- Name & Typ -->
        <div class="form-row two-cols">
          <ETextField
            v-model="formData.name"
            :label="isEventChildMode ? t('settings.addressModal.eventChildLabel') : t('settings.addressForm.designation')"
            :placeholder="isEventChildMode
              ? t('settings.addressModal.eventChildLabelPlaceholder')
              : t('settings.addressForm.designationPlaceholder')"
            hide-details="auto"
          />
          <ESelect
            v-model="formData.type"
            :items="addressTypeItems"
            :label="t('settings.addressModal.typeField')"
            :disabled="isGlobalMode || (isEventChildMode && visibleAddressTypeKeys.length <= 1)"
            hide-details="auto"
          />
        </div>

        <p v-if="isEventChildMode && !isEditing" class="field-hint text-muted event-child-type-hint">
          {{ t('settings.addressModal.eventChildTypeHint') }}
        </p>

        <ECheckbox
          v-if="!isGlobalMode && formData.type === 'storage'"
          v-model="formData.is_primary"
          :label="t('settings.addressModal.primaryStorageHint')"
          class="primary-toggle-group"
          hide-details
        />

        <div v-if="!isGlobalMode && formData.type === 'event_poi'" class="pin-color-field">
          <span class="form-label">{{ t('settings.addressModal.pinColorLabel') }}</span>
          <div class="pin-color-swatches" role="listbox" :aria-label="t('settings.addressModal.pinColorLabel')">
            <button
              v-for="color in PIN_COLOR_PRESETS"
              :key="color"
              type="button"
              class="pin-color-swatch"
              :class="{ 'is-selected': formData.pin_color === color }"
              :style="{ background: color }"
              :title="color"
              :aria-label="color"
              @click="formData.pin_color = color"
            />
          </div>
        </div>

        <!-- Firma -->
        <ETextField
          v-model="formData.company"
          :label="t('settings.addressForm.company')"
          :placeholder="t('common.optional')"
          hide-details="auto"
        />

        <!-- Strasse + Nr -->
        <div class="form-row street-number-row">
          <ETextField
            v-model="formData.street"
            :label="t('settings.addressForm.street')"
            :placeholder="t('settings.addressForm.streetPlaceholder')"
            hide-details="auto"
          />
          <ETextField
            v-model="formData.street_number"
            class="street-number-field"
            :label="t('settings.addressForm.streetNumber')"
            :placeholder="t('settings.addressForm.streetNumberPlaceholder')"
            hide-details="auto"
          />
        </div>

        <!-- PLZ + Ort -->
        <div class="form-row postal-city-row">
          <ETextField
            v-model="formData.postal_code"
            class="postal-field"
            :label="t('settings.addressForm.postalCode')"
            :placeholder="t('settings.addressForm.postalPlaceholder')"
            hide-details="auto"
          />
          <ETextField
            v-model="formData.city"
            :label="t('settings.addressForm.city')"
            :placeholder="t('settings.addressForm.cityPlaceholder')"
            hide-details="auto"
          />
        </div>

        <!-- Kanton + Land -->
        <div class="form-row two-cols">
          <ESelect
            v-model="formData.canton"
            :items="cantonItems"
            :label="t('settings.addressForm.canton')"
            clearable
            hide-details="auto"
          />
          <ETextField
            v-model="formData.country"
            :label="t('settings.addressForm.country')"
            hide-details="auto"
          />
        </div>

        <!-- Kontakt: Vorname, Nachname -->
        <div class="form-row two-cols">
          <ETextField
            v-model="formData.contact_first_name"
            :label="t('settings.addressForm.contactFirstName')"
            :placeholder="t('common.optional')"
            hide-details="auto"
          />
          <ETextField
            v-model="formData.contact_last_name"
            :label="t('settings.addressForm.contactLastName')"
            :placeholder="t('common.optional')"
            hide-details="auto"
          />
        </div>

        <!-- Kontakt: E-Mail, Telefon, Mobil -->
        <div class="form-row three-cols">
          <ETextField
            v-model="formData.email"
            type="email"
            :label="t('settings.addressForm.email')"
            :placeholder="t('settings.addressForm.emailPlaceholder')"
            hide-details="auto"
          />
          <ETextField
            v-model="formData.phone"
            type="tel"
            :label="t('settings.addressForm.phone')"
            :placeholder="t('settings.addressForm.phonePlaceholder')"
            hide-details="auto"
          />
          <ETextField
            v-model="formData.mobile"
            type="tel"
            :label="t('settings.addressForm.mobile')"
            :placeholder="t('settings.addressForm.mobilePlaceholder')"
            hide-details="auto"
          />
        </div>

        <!-- Karte -->
        <div class="form-group map-section">
          <div class="map-header">
            <label class="form-label map-label">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                <line x1="8" y1="2" x2="8" y2="18"/>
                <line x1="16" y1="6" x2="16" y2="22"/>
              </svg>
              {{ t('settings.addressModal.mapSectionLabel') }}
            </label>
            <button type="button" @click="searchCoordinates" class="search-coords-btn" :disabled="!fullAddress">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                <path d="M7 12A5 5 0 107 2a5 5 0 000 10zM14 14l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              {{ t('settings.addressModal.mapSearchAddress') }}
            </button>
          </div>
          <div class="map-container-wrapper">
            <MapView
              ref="mapRef"
              :latitude="formData.latitude"
              :longitude="formData.longitude"
              :address="fullAddress"
              :editable="true"
              :prefer-swiss-map="isSwissCountry"
              :use-swiss-projection="isSwissCountry"
              :show-coordinates="true"
              :show-layer-control="true"
              :zoom="addressMapZoom"
              height="400px"
              @update:latitude="formData.latitude = $event"
              @update:longitude="formData.longitude = $event"
              @coordinates-changed="onMapCoordinatesChanged"
            />
          </div>
          <p class="map-hint">{{ t('settings.addressModal.mapHint') }}</p>
          <div v-if="formData.latitude && formData.longitude" class="coordinates-info">
            <span class="coord-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
              {{ formData.latitude?.toFixed(6) }}° N, {{ formData.longitude?.toFixed(6) }}° E
            </span>
          </div>
        </div>

        <!-- Zusätzliche Infos -->
        <ETextarea
          v-model="formData.additional_info"
          :label="t('settings.addressForm.additionalInfo')"
          :placeholder="t('settings.addressForm.additionalInfoPlaceholder')"
          rows="2"
          hide-details="auto"
        />

        <v-alert v-if="error" type="error" variant="tonal" class="mt-2" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="confirmClose">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="address-modal-form"
        :loading="isSaving"
        :disabled="isSaving"
      >
        {{ isEditing ? t('common.save') : t('common.create') }}
      </EButton>
    </template>
  </EDialog>

  <EDialog
    v-model="showCloseConfirm"
    :max-width="400"
    :title="t('settings.addressModal.closeConfirmTitle')"
    persistent
  >
    <p>{{ t('settings.addressModal.closeConfirmText') }}</p>
    <template #actions>
      <EButton variant="secondary" size="small" @click="showCloseConfirm = false">
        {{ t('settings.addressModal.backToForm') }}
      </EButton>
      <EButton variant="danger" size="small" @click="close">
        {{ t('settings.addressModal.discard') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import MapView from './MapView.vue'
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { 
  createAddress, 
  updateAddress, 
  setAddressPrimary,
  getAddresses,
  ADDRESS_TYPES,
  SWISS_CANTONS,
  type Address,
  type AddressFormData 
} from '@/api/addresses'
import {
  createGlobalAddress,
  updateGlobalAddress,
  type GlobalAddressFormData
} from '@/api/globalAddresses'

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

interface Props {
  departmentId: string
  address?: Address | null
  editAddressId?: string | null
  defaultType?: string
  defaultName?: string
  parentId?: string | null
  apiMode?: 'department' | 'global'
  /** Wenn gesetzt: nur diese Adresstypen im Dropdown (z. B. User-Rolle). */
  allowedTypes?: string[] | null
}

const props = withDefaults(defineProps<Props>(), {
  address: null,
  editAddressId: null,
  defaultType: 'storage',
  defaultName: '',
  parentId: null,
  apiMode: 'department'
})

const emit = defineEmits<{
  close: []
  saved: [address?: Address]
}>()

const toast = useToast()
const { t } = useI18n()
const dialogOpen = ref(true)
const mapRef = ref<InstanceType<typeof MapView>>()

watch(dialogOpen, (open) => {
  if (!open) close()
})

const addressTypeItems = computed(() =>
  visibleAddressTypeKeys.value.map((key) => ({
    title: t(`settings.addressForm.types.${key}`),
    value: key,
  })),
)

const cantonItems = computed(() => [
  { title: t('settings.addressForm.selectPlaceholder'), value: '' },
  ...Object.entries(SWISS_CANTONS).map(([code, name]) => ({
    title: `${code} - ${name}`,
    value: code,
  })),
])
const isEditing = computed(() => !!(props.editAddressId || props.address?.id))
const isGlobalMode = computed(() => props.apiMode === 'global')
const isEventChildMode = computed(() => {
  if (!props.parentId) return false
  const keys = visibleAddressTypeKeys.value
  return keys.every((k) => k === 'event_delivery' || k === 'event_poi')
})
const isSaving = ref(false)
const error = ref<string | null>(null)

const allAddressTypeKeys = Object.keys(ADDRESS_TYPES) as (keyof typeof ADDRESS_TYPES)[]

const visibleAddressTypeKeys = computed(() => {
  if (props.allowedTypes?.length) {
    return allAddressTypeKeys.filter((key) => props.allowedTypes!.includes(String(key)))
  }
  return allAddressTypeKeys
})

const modalTitle = computed(() => {
  if (isEditing.value) {
    return t('settings.addressModal.editTitle')
  }
  if (isEventChildMode.value) {
    return t('activities.venueLocations.addAddressButton')
  }
  const dk = props.defaultType in ADDRESS_TYPES ? props.defaultType : 'general'
  return t('settings.addressModal.addTitle', { type: t(`settings.addressForm.types.${dk}`) })
})

// Adress-Suche
interface SearchResult {
  name: string
  detail: string
  street?: string
  street_number?: string
  postal_code?: string
  city?: string
  canton?: string
  country?: string
  company?: string
  latitude?: number
  longitude?: number
}

const addressSearchQuery = ref('')
const searchResults = ref<SearchResult[]>([])
const showSearchResults = ref(false)
const isSearching = ref(false)
const searchError = ref('')
const pasteSuccess = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearchFocus() {
  showSearchResults.value = true
  // Bei bestehenden Ergebnissen sofort anzeigen
  if (addressSearchQuery.value.length >= 3 && searchResults.value.length === 0) {
    performAddressSearch(addressSearchQuery.value.trim())
  }
}

function onAddressSearchInput() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchError.value = ''
  
  const query = addressSearchQuery.value.trim()
  if (query.length < 3) {
    searchResults.value = []
    showSearchResults.value = false
    return
  }
  
  showSearchResults.value = true
  isSearching.value = true
  
  searchTimeout = setTimeout(() => {
    performAddressSearch(query)
  }, 400)
}

async function performAddressSearch(query: string) {
  isSearching.value = true
  searchError.value = ''
  
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=6&addressdetails=1`,
      { headers: { 'Accept-Language': 'de' } }
    )
    
    if (!response.ok) {
      searchError.value = t('settings.addressModal.searchFailed')
      searchResults.value = []
      return
    }
    
    const data = await response.json()
    
    searchResults.value = data.map((item: any) => {
      const addr = item.address || {}
      
      // Schönen Namen bauen
      const nameParts = []
      const itemName = item.name || addr.shop || addr.office || addr.craft || addr.company || ''
      if (itemName) nameParts.push(itemName)
      
      const street = addr.road || addr.pedestrian || addr.footway || ''
      const houseNr = addr.house_number || ''
      if (street) nameParts.push(street + (houseNr ? ' ' + houseNr : ''))
      
      const city = addr.city || addr.town || addr.village || addr.municipality || ''
      const postcode = addr.postcode || ''
      if (postcode && city) nameParts.push(postcode + ' ' + city)
      else if (city) nameParts.push(city)
      
      const result: SearchResult = {
        name: nameParts.slice(0, 2).join(', ') || item.display_name?.split(',')[0] || query,
        detail: nameParts.slice(2).join(', ') || (addr.country || ''),
        street: street,
        street_number: houseNr,
        postal_code: postcode,
        city: city,
        canton: mapSwissState(addr.state),
        country: addr.country || '',
        latitude: parseFloat(item.lat),
        longitude: parseFloat(item.lon)
      }
      
      // Firma erkennen
      if (itemName && itemName !== street && itemName !== city) {
        result.company = itemName
      }
      
      return result
    })
    
    showSearchResults.value = true
  } catch (err) {
    console.error('Adress-Suche Fehler:', err)
    searchError.value = t('settings.addressModal.searchNetworkError')
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

// Schweizer Kantone von Nominatim-Antwort zu Kürzel mappen
function mapSwissState(state?: string): string {
  if (!state) return ''
  const cantonMap: Record<string, string> = {
    'Zürich': 'ZH', 'Bern': 'BE', 'Luzern': 'LU', 'Uri': 'UR',
    'Schwyz': 'SZ', 'Obwalden': 'OW', 'Nidwalden': 'NW', 'Glarus': 'GL',
    'Zug': 'ZG', 'Fribourg': 'FR', 'Freiburg': 'FR', 'Solothurn': 'SO',
    'Basel-Stadt': 'BS', 'Basel-Landschaft': 'BL', 'Schaffhausen': 'SH',
    'Appenzell Ausserrhoden': 'AR', 'Appenzell Innerrhoden': 'AI',
    'St. Gallen': 'SG', 'Graubünden': 'GR', 'Aargau': 'AG',
    'Thurgau': 'TG', 'Ticino': 'TI', 'Tessin': 'TI', 'Vaud': 'VD', 'Waadt': 'VD',
    'Valais': 'VS', 'Wallis': 'VS', 'Neuchâtel': 'NE', 'Neuenburg': 'NE',
    'Genève': 'GE', 'Genf': 'GE', 'Jura': 'JU'
  }
  return cantonMap[state] || ''
}

function selectSearchResult(result: SearchResult) {
  showSearchResults.value = false
  addressSearchQuery.value = result.name
  
  // Formfelder ausfüllen
  if (result.street) formData.value.street = result.street
  if (result.street_number) formData.value.street_number = result.street_number
  if (result.postal_code) formData.value.postal_code = result.postal_code
  if (result.city) formData.value.city = result.city
  if (result.canton) formData.value.canton = result.canton
  if (result.country) formData.value.country = result.country
  if (result.company && !formData.value.company) formData.value.company = result.company
  if (result.latitude) formData.value.latitude = result.latitude
  if (result.longitude) formData.value.longitude = result.longitude

  if (result.latitude && result.longitude) {
    nextTick(() => {
      mapRef.value?.setMarker(result.latitude!, result.longitude!, 17)
    })
  }
}

function hideSearchResultsDelayed() {
  setTimeout(() => {
    showSearchResults.value = false
  }, 200)
}

// Adresse aus Zwischenablage einfügen und parsen
async function pasteAndParseAddress() {
  pasteSuccess.value = ''
  searchError.value = ''
  
  try {
    const text = await navigator.clipboard.readText()
    if (!text || !text.trim()) {
      searchError.value = t('settings.addressModal.clipboardEmpty')
      return
    }
    
    const parsed = parseAddressText(text.trim())
    
    // Felder ausfüllen
    if (parsed.company) formData.value.company = parsed.company
    if (parsed.street) formData.value.street = parsed.street
    if (parsed.street_number) formData.value.street_number = parsed.street_number
    if (parsed.postal_code) formData.value.postal_code = parsed.postal_code
    if (parsed.city) formData.value.city = parsed.city
    if (parsed.canton) formData.value.canton = parsed.canton
    if (parsed.country) formData.value.country = parsed.country
    
    // Bezeichnung vorschlagen wenn leer
    if (!formData.value.name && parsed.company) {
      formData.value.name = parsed.company
    }
    
    const filled: string[] = []
    if (parsed.company) filled.push(parsed.company)
    if (parsed.street) filled.push(parsed.street + (parsed.street_number ? ' ' + parsed.street_number : ''))
    if (parsed.postal_code && parsed.city) filled.push(parsed.postal_code + ' ' + parsed.city)
    
    pasteSuccess.value =
      filled.length > 0
        ? t('settings.addressModal.pasteFilled', { list: filled.join(', ') })
        : t('settings.addressModal.pasteCheckFields')
    
    // Erfolgs-Hinweis nach 5 Sek. ausblenden
    setTimeout(() => { pasteSuccess.value = '' }, 5000)
    
  } catch (err) {
    searchError.value = t('settings.addressModal.clipboardDenied')
  }
}

// Adresstext parsen (Swiss/DE-Format)
function parseAddressText(text: string): {
  company?: string, street?: string, street_number?: string,
  postal_code?: string, city?: string, canton?: string, country?: string
} {
  const result: any = {}
  
  // Zeilenumbrüche zu Kommas normalisieren
  let normalized = text.replace(/\n+/g, ', ').replace(/\s*,\s*/g, ', ')
  
  // Teile aufsplitten
  const parts = normalized.split(',').map(p => p.trim()).filter(p => p)
  
  for (let i = 0; i < parts.length; i++) {
    const part = parts[i]
    
    // PLZ + Ort erkennen (4-5 Ziffern + Ort)
    const plzMatch = part.match(/^(\d{4,5})\s+(.+)$/)
    if (plzMatch) {
      result.postal_code = plzMatch[1]
      result.city = plzMatch[2].replace(/\s*\(.*\)$/, '') // "(BE)" etc. entfernen
      
      // Kanton aus Klammer extrahieren
      const cantonMatch = plzMatch[2].match(/\((\w{2})\)/)
      if (cantonMatch) {
        result.canton = cantonMatch[1]
      }
      continue
    }
    
    // Strasse + Hausnummer erkennen
    const streetMatch = part.match(/^([A-Za-zÀ-ÿ\s.-]+?)\s+(\d+\w*)$/)
    if (streetMatch && !result.street) {
      result.street = streetMatch[1].trim()
      result.street_number = streetMatch[2]
      continue
    }
    
    // Nur Strasse (ohne Nummer)
    const streetOnlyMatch = part.match(/^([A-Za-zÀ-ÿ\s.-]+(strasse|str\.|weg|gasse|platz|allee|ring|damm|ufer))$/i)
    if (streetOnlyMatch && !result.street) {
      result.street = part.trim()
      continue
    }
    
    // Land erkennen
    const countries = ['schweiz', 'switzerland', 'suisse', 'deutschland', 'germany', 'österreich', 'austria', 'france', 'frankreich', 'italien', 'italy', 'liechtenstein']
    if (countries.includes(part.toLowerCase())) {
      result.country = part
      continue
    }
    
    // Erster Teil ist oft der Firmenname
    if (i === 0 && !result.company && !plzMatch && !streetMatch) {
      result.company = part
    }
  }
  
  return result
}

function getDefaultCompanyForNewAddress(): string | null {
  if (isGlobalMode.value) return props.defaultName || null
  // Lagerplatz: nur Bezeichnung vorfüllen, Firma/Organisation bleibt leer
  if (props.defaultType === 'storage') return null
  return props.defaultName || null
}

/** Anzahl bestehender Lagerstandorte (null = noch nicht geladen). */
const existingStorageLocationCount = ref<number | null>(null)

async function loadExistingStorageLocationCount() {
  if (isGlobalMode.value || !props.departmentId || props.defaultType !== 'storage') {
    existingStorageLocationCount.value = null
    return
  }
  try {
    const result = await getAddresses(props.departmentId, 'storage')
    existingStorageLocationCount.value = result.addresses?.length ?? 0
  } catch {
    existingStorageLocationCount.value = null
  }
}

/** Primär-Checkbox nur vorauswählen, wenn noch kein Lagerstandort existiert. */
function defaultIsPrimaryForNewStorage(): boolean {
  if (isGlobalMode.value || props.defaultType !== 'storage') return false
  return existingStorageLocationCount.value === 0
}

async function applyPrimaryDefaultForNewStorage() {
  if (isEditing.value || isGlobalMode.value || props.defaultType !== 'storage') return
  await loadExistingStorageLocationCount()
  formData.value.is_primary = defaultIsPrimaryForNewStorage()
}

// Form-Daten
const formData = ref<Partial<AddressFormData>>({
  type: isGlobalMode.value ? 'supplier' : props.defaultType,
  name: props.defaultName || '',
  company: getDefaultCompanyForNewAddress(),
  street: '',
  street_number: null,
  postal_code: '',
  city: '',
  canton: null,
  country: 'Schweiz',
  latitude: null,
  longitude: null,
  contact_first_name: null,
  contact_last_name: null,
  email: null,
  phone: null,
  mobile: null,
  additional_info: null,
  pin_color: '#16a34a',
  is_primary: false,
})

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
  parts.push(formData.value.country || 'Schweiz')
  return parts.join(', ')
})

// Watch für Bearbeitungsmodus
watch(() => props.address, async (addr) => {
  if (addr) {
    formData.value = {
      type: isGlobalMode.value ? 'supplier' : addr.type,
      name: addr.name,
      company: addr.company,
      street: addr.street,
      street_number: addr.street_number,
      postal_code: addr.postal_code,
      city: addr.city,
      canton: addr.canton,
      country: addr.country,
      latitude: addr.latitude,
      longitude: addr.longitude,
      contact_first_name: addr.contact_first_name,
      contact_last_name: addr.contact_last_name,
      email: addr.email,
      phone: addr.phone,
      mobile: addr.mobile,
      additional_info: addr.additional_info,
      pin_color: addr.pin_color || '#16a34a',
      is_primary: !!addr.is_primary,
    }
  } else {
    await resetForm()
  }
}, { immediate: true })

watch(
  () => formData.value.type,
  (type) => {
    if (type === 'event_poi' && !formData.value.pin_color) {
      formData.value.pin_color = '#16a34a'
    }
  },
)

async function resetForm() {
  formData.value = {
    type: isGlobalMode.value ? 'supplier' : props.defaultType,
    name: props.defaultName || '',
    company: getDefaultCompanyForNewAddress(),
    street: '',
    street_number: null,
    postal_code: '',
    city: '',
    canton: null,
    country: 'Schweiz',
    latitude: null,
    longitude: null,
    contact_first_name: null,
    contact_last_name: null,
    email: null,
    phone: null,
    mobile: null,
    additional_info: null,
    pin_color: '#16a34a',
    is_primary: false,
  }
  await applyPrimaryDefaultForNewStorage()
}

// Karte nach Modal-Öffnung invalidieren (Tiles laden)
onMounted(() => {
  // Mehrfach invalidieren, da das Modal-Layout Zeit braucht
  nextTick(() => {
    mapRef.value?.invalidateSize()
  })
  setTimeout(() => {
    mapRef.value?.invalidateSize()
  }, 300)
  setTimeout(() => {
    mapRef.value?.invalidateSize()
  }, 600)
})

// Schweiz-Erkennung anhand Land-Feld
const SWISS_COUNTRY_NAMES = ['schweiz', 'suisse', 'svizzera', 'switzerland', 'ch']
const isSwissCountry = computed(() => {
  const country = (formData.value.country || '').trim().toLowerCase()
  return !country || SWISS_COUNTRY_NAMES.includes(country)
})

/** LV95-Zoom: 15.5 ≈ geo.admin z 1.5 (ganze CH), 21.7 ≈ Detailansicht beim Setzen. */
const SWISS_MAP_OVERVIEW_ZOOM = 15.5
const SWISS_MAP_LOCATION_ZOOM = 21.7

const addressMapZoom = computed(() => {
  if (formData.value.latitude != null && formData.value.longitude != null) {
    return isSwissCountry.value ? SWISS_MAP_LOCATION_ZOOM : 17
  }
  return isSwissCountry.value ? SWISS_MAP_OVERVIEW_ZOOM : 11
})

// Bei Länderwechsel: Layer auf der Karte umschalten
watch(isSwissCountry, (isSwiss) => {
  if (mapRef.value) {
    mapRef.value.setLayer(isSwiss ? 'swisstopo' : 'osm')
  }
})

function searchCoordinates() {
  mapRef.value?.searchAddress()
}

function onMapCoordinatesChanged(lat: number, lng: number) {
  formData.value.latitude = lat
  formData.value.longitude = lng
}

async function handleSubmit() {
  // Mindestens Name, Adresse oder Koordinaten erforderlich
  const hasName = formData.value.name || formData.value.company
  const hasAddress = formData.value.street || formData.value.city
  const hasCoordinates = formData.value.latitude && formData.value.longitude
  
  if (!hasName && !hasAddress && !hasCoordinates) {
    error.value = t('settings.addressModal.validationMinFields')
    return
  }

  isSaving.value = true
  error.value = null

  try {
    let savedAddress: Address | undefined
    if (isGlobalMode.value) {
      const payload: GlobalAddressFormData = {
        name: formData.value.name,
        company: formData.value.company,
        street: formData.value.street || '',
        street_number: formData.value.street_number,
        postal_code: formData.value.postal_code || '',
        city: formData.value.city || '',
        canton: formData.value.canton,
        country: formData.value.country || 'Schweiz',
        contact_first_name: formData.value.contact_first_name,
        contact_last_name: formData.value.contact_last_name,
        email: formData.value.email,
        phone: formData.value.phone,
        mobile: formData.value.mobile,
        additional_info: formData.value.additional_info,
      }

      const targetId = props.editAddressId || props.address?.id
      if (isEditing.value && targetId) {
        await updateGlobalAddress(targetId, payload)
      } else {
        await createGlobalAddress(payload)
      }
    } else {
      const payload: AddressFormData = {
        department_id: props.departmentId,
        type: formData.value.type,
        parent_id: props.parentId ?? undefined,
        name: formData.value.name,
        company: formData.value.company,
        street: formData.value.street || '',
        street_number: formData.value.street_number,
        postal_code: formData.value.postal_code || '',
        city: formData.value.city || '',
        canton: formData.value.canton,
        country: formData.value.country || 'Schweiz',
        latitude: formData.value.latitude,
        longitude: formData.value.longitude,
        contact_first_name: formData.value.contact_first_name,
        contact_last_name: formData.value.contact_last_name,
        email: formData.value.email,
        phone: formData.value.phone,
        mobile: formData.value.mobile,
        additional_info: formData.value.additional_info,
        pin_color: formData.value.type === 'event_poi' ? (formData.value.pin_color || '#16a34a') : null,
        is_primary: !!formData.value.is_primary,
      }

      const targetId = props.editAddressId || props.address?.id
      if (isEditing.value && targetId) {
        const response = await updateAddress(targetId, payload)
        savedAddress = response.address
      } else {
        const response = await createAddress(payload)
        savedAddress = response.address
      }

      if (savedAddress?.id && formData.value.is_primary) {
        const primaryResponse = await setAddressPrimary(savedAddress.id)
        savedAddress = primaryResponse.address
      }
    }

    emit('saved', savedAddress)
  } catch (err: any) {
    const msg = err.response?.data?.error || t('settings.addressModal.saveError')
    error.value = msg
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}

// Prüfen ob das Formular verändert wurde
const hasFormChanges = computed(() => {
  const f = formData.value
  if (isEditing.value && props.address) {
    // Bearbeitungsmodus: Vergleich mit Original-Daten
    const a = props.address
    return (
      f.type !== a.type ||
      f.name !== a.name ||
      f.company !== a.company ||
      f.street !== a.street ||
      f.street_number !== a.street_number ||
      f.postal_code !== a.postal_code ||
      f.city !== a.city ||
      f.canton !== a.canton ||
      f.country !== a.country ||
      f.contact_first_name !== a.contact_first_name ||
      f.contact_last_name !== a.contact_last_name ||
      f.email !== a.email ||
      f.phone !== a.phone ||
      f.mobile !== a.mobile ||
      f.additional_info !== a.additional_info ||
      !!f.is_primary !== !!a.is_primary
    )
  } else {
    // Neu-Erstellung: Prüfen ob irgendein Feld ausgefüllt wurde
    return !!(
      (f.name && f.name !== props.defaultName) ||
      (f.company && f.company !== props.defaultName) ||
      f.street ||
      f.street_number ||
      f.postal_code ||
      f.city ||
      f.contact_first_name ||
      f.contact_last_name ||
      f.email ||
      f.phone ||
      f.mobile ||
      f.additional_info ||
      f.latitude ||
      f.longitude
    )
  }
})

// Schliessen-Bestätigung
const showCloseConfirm = ref(false)

function confirmClose() {
  if (hasFormChanges.value) {
    showCloseConfirm.value = true
  } else {
    close()
  }
}

function close() {
  showCloseConfirm.value = false
  dialogOpen.value = false
  emit('close')
}
</script>

<style scoped>
:deep(.address-modal-card) {
  max-height: calc(100vh - 48px);
}

.address-modal-body {
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
}

.form-row.three-cols {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
}

.form-row.two-cols-unequal {
  display: flex;
}

/** Strasse nimmt Restbreite (wie Firma darüber), Nr. schmal rechts */
.form-row.street-number-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 5.75rem;
  gap: 16px;
  width: 100%;
}

.form-row.street-number-row > * {
  min-width: 0;
}

/* Form group/input/select/textarea base uses shared ui/forms.css */

.flex-grow {
  flex: 1;
  min-width: 0;
}

.street-number-field {
  max-width: 5.75rem;
}

/** PLZ schmal links, Ort Restbreite (wie Firma darüber) */
.form-row.postal-city-row {
  display: grid;
  grid-template-columns: 7rem minmax(0, 1fr);
  gap: 16px;
  width: 100%;
}

.form-row.postal-city-row > * {
  min-width: 0;
}

.postal-field {
  max-width: 7rem;
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

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.primary-toggle-group {
  margin-top: -2px;
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

.event-child-type-hint {
  margin: -4px 0 4px;
  font-size: 13px;
}

.primary-toggle-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

/* Adress-Suche (im scrollbaren Modal-Inhalt) */
.address-search-section {
  overflow: visible;
  position: relative;
  z-index: 2;
}

.address-search-group {
  background: #f0f4ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.address-search-label {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #4338ca !important;
  font-weight: 600 !important;
  font-size: 13px;
  margin: 0;
}

.address-search-row {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.address-search-wrapper {
  position: relative;
  flex: 1;
}

.address-search-input {
  width: 100%;
  padding: 10px 36px 10px 12px !important;
  border: 1px solid #c7d2fe !important;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  box-sizing: border-box;
}

.google-search-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  text-decoration: none;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.15s;
}

.google-search-btn:hover {
  background: #f9fafb;
  border-color: #9ca3af;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.paste-address-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #4338ca;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.15s;
}

.paste-address-btn:hover {
  background: #e0e7ff;
  border-color: #a5b4fc;
}

.paste-success-hint {
  font-size: 12px;
  color: #059669;
  font-weight: 500;
  margin-top: 4px;
  animation: fadeInHint 0.3s ease;
}

@keyframes fadeInHint {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.address-search-input:focus {
  outline: none;
  border-color: #6366f1 !important;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.search-spinner {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  border: 2px solid #e5e7eb;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: addr-spin 0.6s linear infinite;
  pointer-events: none;
}

@keyframes addr-spin {
  to { transform: translateY(-50%) rotate(360deg); }
}

.address-search-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #c7d2fe;
  border-top: none;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  max-height: 240px;
  overflow-y: auto;
  z-index: 1100;
}

.address-search-item {
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.1s;
}

.address-search-item:last-child {
  border-bottom: none;
}

.address-search-item:hover {
  background: #eef2ff;
}

.address-search-item.search-empty {
  color: #9ca3af;
  cursor: default;
  font-style: italic;
  font-size: 13px;
}

.address-search-item.search-empty:hover {
  background: transparent;
}

.search-result-main {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  line-height: 1.3;
}

.search-result-detail {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
  line-height: 1.3;
}

.search-error-hint {
  font-size: 12px;
  color: #dc2626;
  margin-top: 2px;
}

.form-divider {
  height: 1px;
  background: #e5e7eb;
  margin: 4px 0;
}

/* Karten-Bereich */
.map-section {
  margin-top: 8px;
}

.map-container-wrapper {
  width: 100%;
  height: 400px;
  min-height: 400px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.map-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.map-label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 0 !important;
  font-weight: 600;
  color: #1e293b;
}

.search-coords-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.search-coords-btn:hover:not(:disabled) {
  background: #e2e8f0;
  color: #1e293b;
}

.search-coords-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.map-hint {
  margin: 6px 0 0 0;
  font-size: 12px;
  color: #94a3b8;
  font-style: italic;
}

.coordinates-info {
  margin-top: 6px;
}

.coord-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  font-size: 12px;
  font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
  color: #166534;
}

@media (max-width: 640px) {
  .form-row.two-cols,
  .form-row.three-cols {
    grid-template-columns: 1fr;
  }
  .form-row.two-cols-unequal {
    flex-direction: column;
  }
  .form-row.street-number-row,
  .form-row.postal-city-row {
    grid-template-columns: 1fr;
  }
  .street-number-field,
  .postal-field {
    flex: 1 1 100%;
    max-width: none;
  }
}
</style>
