<template>
  <div class="modal-overlay">
    <div class="modal-dialog address-modal-dialog">
      <div class="modal-header">
        <h2>{{ modalTitle }}</h2>
        <button @click="confirmClose" class="modal-close">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      
      <!-- Adress-Suche (AUSSERHALB des scrollbaren Bereichs) -->
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

      <form @submit.prevent="handleSubmit" class="modal-body">
        <!-- Name & Typ -->
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
            <label class="form-label">{{ t('settings.addressModal.typeField') }}</label>
            <select v-model="formData.type" class="form-select" :disabled="isGlobalMode">
              <option v-for="key in visibleAddressTypeKeys" :key="key" :value="key">
                {{ t(`settings.addressForm.types.${key}`) }}
              </option>
            </select>
          </div>
        </div>

        <div v-if="!isGlobalMode && formData.type === 'storage'" class="form-group primary-toggle-group">
          <label class="primary-toggle-label">
            <input v-model="formData.is_primary" type="checkbox" />
            {{ t('settings.addressModal.primaryStorageHint') }}
          </label>
        </div>

        <!-- Firma -->
        <div class="form-group">
          <label class="form-label">{{ t('settings.addressForm.company') }}</label>
          <input
            v-model="formData.company"
            type="text"
            class="form-input"
            :placeholder="t('settings.addressForm.optional')"
          />
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
        <div class="form-row two-cols">
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
        <div class="form-row three-cols">
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
          <div class="map-container-wrapper" style="height: 250px; min-height: 250px;">
            <MapView
              ref="mapRef"
              :latitude="formData.latitude"
              :longitude="formData.longitude"
              :address="fullAddress"
              :editable="true"
              :prefer-swiss-map="isSwissCountry"
              :show-coordinates="true"
              :show-layer-control="true"
              height="250px"
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
        <div class="form-group">
          <label class="form-label">{{ t('settings.addressForm.additionalInfo') }}</label>
          <textarea
            v-model="formData.additional_info"
            class="form-textarea"
            rows="2"
            :placeholder="t('settings.addressForm.additionalInfoPlaceholder')"
          />
        </div>

        <!-- Error -->
        <div v-if="error" class="error-message">{{ error }}</div>

        <!-- Actions -->
        <div class="modal-footer">
          <button type="button" @click="confirmClose" class="btn-secondary">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="isSaving">
            <span v-if="isSaving">{{ t('common.saving') }}</span>
            <span v-else>{{ isEditing ? t('common.save') : t('common.create') }}</span>
          </button>
        </div>
      </form>
    </div>

    <!-- Bestätigungsdialog beim Schliessen -->
    <Teleport to="body">
      <div v-if="showCloseConfirm" class="confirm-overlay">
        <div class="confirm-dialog">
          <div class="confirm-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <h3 class="confirm-title">{{ t('settings.addressModal.closeConfirmTitle') }}</h3>
          <p class="confirm-text">
            {{ t('settings.addressModal.closeConfirmText') }}
          </p>
          <div class="confirm-actions">
            <button class="btn-confirm-cancel" @click="showCloseConfirm = false">
              {{ t('settings.addressModal.backToForm') }}
            </button>
            <button class="btn-confirm-discard" @click="close">{{ t('settings.addressModal.discard') }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import MapView from './MapView.vue'
import { 
  createAddress, 
  updateAddress, 
  setAddressPrimary,
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

interface Props {
  departmentId: string
  address?: Address | null
  editAddressId?: string | null
  defaultType?: string
  defaultName?: string
  apiMode?: 'department' | 'global'
  /** Wenn gesetzt: nur diese Adresstypen im Dropdown (z. B. User-Rolle). */
  allowedTypes?: string[] | null
}

const props = withDefaults(defineProps<Props>(), {
  address: null,
  editAddressId: null,
  defaultType: 'storage',
  defaultName: '',
  apiMode: 'department'
})

const emit = defineEmits<{
  close: []
  saved: [address?: Address]
}>()

const toast = useToast()
const { t } = useI18n()
const mapRef = ref<InstanceType<typeof MapView>>()
const isEditing = computed(() => !!(props.editAddressId || props.address?.id))
const isGlobalMode = computed(() => props.apiMode === 'global')
const isSaving = ref(false)
const error = ref<string | null>(null)

const allAddressTypeKeys = Object.keys(ADDRESS_TYPES) as (keyof typeof ADDRESS_TYPES)[]

const visibleAddressTypeKeys = computed(() => {
  if (props.allowedTypes?.length) {
    return allAddressTypeKeys.filter((key) => props.allowedTypes!.includes(key))
  }
  return allAddressTypeKeys
})

const modalTitle = computed(() => {
  if (isEditing.value) {
    return t('settings.addressModal.editTitle')
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
  is_primary: !isGlobalMode.value && props.defaultType === 'storage',
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
watch(() => props.address, (addr) => {
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
      is_primary: !!addr.is_primary,
    }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
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
    is_primary: !isGlobalMode.value && props.defaultType === 'storage',
  }
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
  emit('close')
}
</script>

<style scoped>
/* Modal overlay/dialog/header/body/footer base uses shared ui/modals.css */
.address-modal-dialog {
  width: min(750px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.address-modal-dialog .modal-header {
  flex-shrink: 0;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}

.modal-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
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

/* Form group/input/select/textarea base uses shared ui/forms.css */

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

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.primary-toggle-group {
  margin-top: -2px;
}

.primary-toggle-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

/* Adress-Suche (eigener Bereich, nicht scrollbar) */
.address-search-section {
  flex-shrink: 0;
  padding: 16px 24px 0;
  overflow: visible;
  position: relative;
  z-index: 10;
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

.error-message {
  padding: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 6px;
  color: #dc2626;
  font-size: 14px;
}


@media (max-width: 640px) {
  .form-row.two-cols,
  .form-row.three-cols {
    grid-template-columns: 1fr;
  }
  .form-row.two-cols-unequal {
    flex-direction: column;
  }
  .form-group[style*="width"] {
    width: 100% !important;
  }
}

/* Bestätigungsdialog */
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.15s ease-out;
}

.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 90%;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: scaleIn 0.15s ease-out;
}

@keyframes scaleIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.confirm-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #fef3c7;
  color: #f59e0b;
  margin-bottom: 12px;
}

.confirm-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 8px 0;
}

.confirm-text {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0 0 20px 0;
  line-height: 1.5;
}

.confirm-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.btn-confirm-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  transition: all 0.15s ease;
}

.btn-confirm-cancel:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.btn-confirm-discard {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  background: #ef4444;
  border: none;
  color: white;
  transition: all 0.15s ease;
}

.btn-confirm-discard:hover {
  background: #dc2626;
}
</style>
