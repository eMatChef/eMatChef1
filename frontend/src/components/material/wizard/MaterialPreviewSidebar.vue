<template>
  <div class="material-wizard-sidebar">
    <h3>{{ isAddBatchMode ? 'Charge hinzufügen' : 'Mein Material' }}</h3>
    
    <div class="material-preview" :class="{ 'batch-mode': isAddBatchMode }">
      <div class="preview-image">
        <svg v-if="!isAddBatchMode" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.5">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
      </div>
      <div class="preview-info">
        <h4>{{ displayName }}</h4>
        <p v-if="categoryPath" class="preview-category">{{ categoryPath }}</p>
        <span v-if="isAddBatchMode" class="preview-badge batch">
          + {{ initialQty || 0 }} Stück
        </span>
        <span v-else-if="materialType" class="preview-badge" :class="materialType">
          {{ materialTypeLabel }}
        </span>
      </div>
    </div>

    <div v-if="materialType" class="usage-info">
      <div class="usage-header">
        <span v-if="isConsumable">🔥 Verbrauchsmaterial</span>
        <span v-else-if="isFood">🍽️ Esswaren</span>
        <span v-else-if="isJsMaterial">🟡 J&amp;S-Material</span>
        <span v-else>📦 Ausleihmaterial</span>
      </div>
      <p v-if="isConsumable" class="usage-desc">Wird bei Ausgabe sofort vom Bestand abgezogen.</p>
      <p v-else-if="isFood" class="usage-desc">Lebensmittel – wird im Tab "Esswaren" angezeigt.</p>
      <p v-else-if="isJsMaterial" class="usage-desc">Globale externe Quelle fuer department-uebergreifende Planung.</p>
      <p v-else class="usage-desc">Das Material wird nach Abschluss des Projekts ins Lager zurückgebracht.</p>
      <div v-if="isConsumable && salePrice" class="info-row">
        <span class="info-label">Verkaufspreis:</span>
        <span class="info-value">CHF {{ Number(salePrice).toFixed(2) }} / Stk.</span>
      </div>
      <div v-if="isConsumable && minStock" class="info-row">
        <span class="info-label">Mindestbestand:</span>
        <span class="info-value">{{ minStock }} Stk.</span>
      </div>
      <div v-if="packSize && packUnit" class="info-row">
        <span class="info-label">Verpackung:</span>
        <span class="info-value">{{ packUnit }} à {{ packSize }} Stk.</span>
      </div>
      <div v-if="isJsMaterial && externalSource" class="info-row">
        <span class="info-label">Quelle:</span>
        <span class="info-value">{{ externalSource }}</span>
      </div>
    </div>

    <!-- Template-Komponentenübersicht -->
    <div v-if="isFromTemplate && componentInputs.length > 0" class="tracking-info">
      <div class="info-row">
        <span class="info-label">Vorlage:</span>
        <span class="info-value">{{ templateName }}</span>
      </div>
      <div v-if="tentCapacity" class="info-row">
        <span class="info-label">Kapazität:</span>
        <span class="info-value">{{ tentCapacity }} Personen</span>
      </div>
      <div class="info-row">
        <span class="info-label">Komponenten:</span>
        <span class="info-value">{{ componentInputs.length }} Teile</span>
      </div>
      <div v-if="storageAddressWithLocation" class="info-row">
        <span class="info-label">Lagerort:</span>
        <span class="info-value">{{ storageAddressWithLocation }}</span>
      </div>
      <div class="sidebar-components-list">
        <div
          v-for="(ci, idx) in componentInputs"
          :key="idx"
          class="sidebar-comp-item"
          :class="{ 'is-done': isComponentDone(ci) }"
        >
          <span class="sidebar-comp-check">{{ isComponentDone(ci) ? '✓' : '○' }}</span>
          <span class="sidebar-comp-name">{{ ci.name }}</span>
          <span v-if="ci.mode === 'new' && ci.tracking === 'serialized' && ci.serial_number" class="sidebar-comp-sn">SN: {{ ci.serial_number }}</span>
          <span v-else-if="ci.mode === 'new' && ci.tracking === 'bulk'" class="sidebar-comp-qty">{{ ci.qty }}x</span>
          <span v-else-if="ci.mode === 'existing' && ci._selectedMaterial" class="sidebar-comp-sn">{{ ci._selectedMaterial.name }}</span>
        </div>
      </div>
    </div>

    <div v-if="!isFromTemplate && (trackingType || comboArticlesCount > 0)" class="tracking-info">
      <div v-if="trackingType" class="info-row">
        <span class="info-label">Bestandsverfolgung:</span>
        <span class="info-value">{{ trackingTypeLabel }}</span>
      </div>
      <div v-if="initialQty > 0" class="info-row">
        <span class="info-label">Initialer Bestand:</span>
        <span class="info-value">{{ initialQty }} Stück</span>
      </div>
      <div v-if="comboArticlesCount > 0" class="info-row">
        <span class="info-label">Enthält:</span>
        <span class="info-value">{{ comboArticlesCount }} Artikel</span>
      </div>
      <div v-if="storageAddressWithLocation" class="info-row">
        <span class="info-label">Lagerort:</span>
        <span class="info-value">{{ storageAddressWithLocation }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface ComponentInputForPreview {
  name: string
  tracking: 'serialized' | 'bulk'
  mode: 'new' | 'existing'
  serial_number?: string
  qty?: number
  _selectedMaterial?: { name: string }
}

const props = defineProps<{
  isAddBatchMode: boolean
  selectedMaterialName?: string | null
  materialName: string
  categoryPath?: string | null
  materialType?: string | null
  initialQty?: number
  isConsumable?: boolean
  isFood?: boolean
  isJsMaterial?: boolean
  salePrice?: number | string | null
  minStock?: number | null
  packSize?: number | null
  packUnit?: string | null
  externalSource?: string | null
  isFromTemplate?: boolean
  templateName?: string | null
  tentCapacity?: number | null
  componentInputs: ComponentInputForPreview[]
  isComponentDone: (ci: ComponentInputForPreview) => boolean
  storageAddressWithLocation?: string | null
  trackingType?: string | null
  trackingTypeLabel?: string
  comboArticlesCount?: number
  materialTypeLabels?: Record<string, string>
}>()

const displayName = computed(() =>
  props.isAddBatchMode && props.selectedMaterialName
    ? props.selectedMaterialName
    : props.materialName || 'Unbenanntes Material'
)

const materialTypeLabel = computed(() =>
  props.materialType && props.materialTypeLabels
    ? props.materialTypeLabels[props.materialType] ?? props.materialType
    : ''
)
</script>
