<template>
  <Teleport to="body">
    <div class="batch-modal-overlay">
      <div class="batch-modal" :class="{ 'batch-modal--wide': !isEditMode }">
        <!-- Header -->
        <div class="batch-modal-header">
          <h2>{{ isEditMode ? 'Charge bearbeiten' : 'Charge hinzufügen' }}</h2>
          <button class="batch-modal-close" @click="$emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="batch-modal-body">
          <!-- Charge hinzufügen serialisiert: gleiches UI wie Material-Erstellwizard -->
          <div v-if="isSerializedAddMode" class="batch-serial-wizard">
              <div class="form-row mb-2">
                <label class="toggle-label">
                  <span class="toggle-wrapper">
                    <input v-model="serialLocationSameForAll" type="checkbox" class="toggle-input" />
                    <span class="toggle-slider toggle-slider--blue"></span>
                  </span>
                  <span class="toggle-text">
                    <span class="toggle-title">Für alle den gleichen Lagerplatz</span>
                    <span class="toggle-desc">Bei Nein wird der Standort direkt pro Zeile in der Tabelle gewählt</span>
                  </span>
                </label>
              </div>

              <div v-if="serialLocationSameForAll" class="form-group">
                <div class="stock-location-mode mb-2">
                  <label class="form-label-sm">Hauptlagerplatz</label>
                  <div class="lagerung-switch" role="tablist">
                    <button
                      type="button"
                      class="lagerung-btn"
                      :class="{ active: stockLocationMode === 'slot' }"
                      @click="setStockLocationMode('slot')"
                    >
                      Gestell/Fach
                    </button>
                    <button
                      type="button"
                      class="lagerung-btn"
                      :class="{ active: stockLocationMode === 'kiste' }"
                      @click="setStockLocationMode('kiste')"
                    >
                      Kiste/Tasche
                    </button>
                  </div>
                </div>
                <template v-if="stockLocationMode === 'slot'">
                  <StorageLocationPicker
                    :show-storage-address="true"
                    :storage-address-id="form.storage_address_id"
                    :storage-address-options="storageAddressOptions"
                    :rack-id="form.rack_id"
                    :slot-id="form.slot_id"
                    :racks="filteredRacks"
                    :slot-list="mainSlots"
                    :rack-label-formatter="formatRackOptionLabel"
                    :rack-option-title-formatter="(r) => rackPreviewTitles[r.id] || ''"
                    :slot-label-formatter="(slot) => formatSlotOptionLabel(form.rack_id, slot)"
                    :slot-option-title-formatter="(s) => slotPreviewTitles[`${String(form.rack_id || '')}:${String(s.id)}`] || ''"
                    storage-address-label="Standort"
                    rack-label="Gestell"
                    slot-label="Fach"
                    storage-address-placeholder="Standort auswaehlen..."
                    rack-placeholder="Gestell auswaehlen..."
                    slot-placeholder="Fach auswaehlen..."
                    @rackListMouseenter="prefetchVisibleRackPreviews(filteredRacks)"
                    @slotListMouseenter="prefetchSlotPreviewsForRack(String(form.rack_id || ''))"
                    @update:storageAddressId="form.storage_address_id = $event"
                    @storageAddressChange="onStorageAddressChange"
                    @update:rackId="onMainRackIdUpdate"
                    @update:slotId="form.slot_id = $event"
                    @slotChange="onSlotChange"
                  />
                </template>
                <template v-else>
                  <label class="form-label-sm">Kiste/Tasche</label>
                  <select
                    v-model="form.container_batch_id"
                    class="batch-form-input form-select--sm"
                    @mouseenter="prefetchContainerPreviews()"
                    :title="getContainerPreviewTitle(form.container_batch_id)"
                  >
                    <option value="">– Kiste wählen –</option>
                    <option
                      v-for="cb in containerBatches"
                      :key="cb.id"
                      :value="cb.id"
                      :title="formatContainerBatchOptionFullLabel(cb)"
                    >
                      {{ formatContainerBatchOptionFullLabel(cb) }}
                    </option>
                  </select>
                </template>
              </div>

              <div class="serial-numbers-section">
                <div class="serial-header">
                  <label>Seriennummern ({{ serializedQty }} Stk.)</label>
                  <div class="serial-header-actions">
                    <button type="button" class="add-serial-btn" @click="addSerialNumber">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                      </svg>
                      Zeile hinzufügen
                    </button>
                    <button type="button" class="add-serial-btn" @click="toggleSerialScanner()">
                      {{ serialScannerActive ? 'Scanner stoppen' : 'Scannen' }}
                    </button>
                  </div>
                </div>
                <div class="slider-toggle-group">
                  <label class="toggle-label">
                    <span class="toggle-wrapper">
                      <input v-model="serialAutoGenerateEnabled" type="checkbox" class="toggle-input" />
                      <span class="toggle-slider toggle-slider--blue"></span>
                    </span>
                    <span class="toggle-text">
                      <span class="toggle-title">Seriennummern automatisch erzeugen</span>
                      <span class="toggle-desc">Prefix, Startnummer und Stellen waehlen statt alles manuell einzugeben</span>
                    </span>
                  </label>
                  <transition name="slide-down">
                    <div v-if="serialAutoGenerateEnabled" class="slider-details">
                      <div class="serial-auto-generate-row">
                        <div class="serial-auto-field serial-auto-field-prefix">
                          <label>Prefix</label>
                          <input
                            v-model="autoGenPrefix"
                            type="text"
                            class="form-input form-input-sm"
                            :placeholder="suggestedSerialPrefix || ''"
                          />
                        </div>
                        <div class="serial-auto-field">
                          <label>Startnummer</label>
                          <input v-model.number="autoGenStart" type="number" min="1" class="form-input form-input-sm" />
                        </div>
                        <div class="serial-auto-field">
                          <label>Stellen</label>
                          <input v-model.number="autoGenPad" type="number" min="1" max="6" class="form-input form-input-sm" />
                        </div>
                        <div class="serial-auto-field">
                          <label>Anzahl</label>
                          <input v-model.number="autoGenCount" type="number" min="1" class="form-input form-input-sm" />
                        </div>
                        <button type="button" class="add-serial-btn add-serial-btn-secondary" @click="generateSerialNumbers">
                          Liste erzeugen
                        </button>
                        <span class="serial-auto-preview">{{ autoGenPreview }}</span>
                      </div>
                    </div>
                  </transition>
                </div>
                <BarcodeScannerPanel
                  v-if="serialScannerActive"
                  :active="serialScannerActive"
                  mode="all"
                  hint="Barcode oder QR auf Seriennummer richten."
                  @detected="onSerialDetected"
                  @error="onSerialScannerError"
                />

                <div v-if="serialRows.length > 0" class="serial-list">
                  <div
                    v-for="(entry, index) in serialRows"
                    :key="entry.id"
                    class="serial-row"
                    :class="{ 'serial-row--shared-location': serialLocationSameForAll }"
                  >
                    <div class="serial-block serial-block--identity">
                      <label class="form-label-sm">{{ getSerialRowTitle(entry, index) }}</label>
                      <input
                        v-model="entry.serial_number"
                        type="text"
                        class="form-input serial-input"
                        placeholder="Seriennummer eingeben..."
                        @keydown.enter.prevent="addSerialNumber"
                      />
                      <label class="form-label-sm">Label (optional)</label>
                      <input
                        v-model="entry.label"
                        type="text"
                        class="form-input notes-input"
                        placeholder="z.B. Kochkiste Bär"
                      />
                    </div>

                    <div v-if="!serialLocationSameForAll" class="serial-block serial-block--art">
                      <label class="form-label-sm">Art</label>
                      <select
                        v-model="entry.location_mode"
                        class="form-select form-select--sm"
                        @change="entry.rack_id = ''; entry.slot_id = ''; entry.container_batch_id = ''"
                      >
                        <option value="slot">Gestell/Fach</option>
                        <option value="kiste">Kiste/Tasche</option>
                      </select>
                    </div>

                    <div v-if="!serialLocationSameForAll" class="serial-block serial-block--location">
                      <div class="serial-location-cell">
                        <template v-if="entry.location_mode === 'slot'">
                          <label class="form-label-sm">Lagerstandort</label>
                          <select
                            v-model="entry.storage_address_id"
                            class="form-select form-select--sm"
                            @change="onSerialEntryStorageAddressChange(entry)"
                          >
                            <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                              {{ addr.name || addr.street_line }}
                            </option>
                          </select>
                          <label class="form-label-sm">Gestell</label>
                          <select
                            v-model="entry.rack_id"
                            class="form-select form-select--sm"
                            @change="onSerialEntryRackChange(entry)"
                            @mouseenter="prefetchRackPreview(entry.rack_id)"
                            :title="getRackPreviewTitle(entry.rack_id)"
                          >
                            <option value="" disabled>– Gestell –</option>
                            <option
                              v-for="rack in getRacksForSerialEntry(entry)"
                              :key="rack.id"
                              :value="String(rack.id)"
                              :title="getRackPreviewTitle(rack.id)"
                            >
                              {{ rack.name }}
                            </option>
                          </select>
                          <label class="form-label-sm">Fach</label>
                          <select
                            v-model="entry.slot_id"
                            class="form-select form-select--sm"
                            :disabled="!entry.rack_id"
                            @mouseenter="prefetchSlotPreview(entry.rack_id, entry.slot_id)"
                            :title="getSlotPreviewTitle(entry.rack_id, entry.slot_id)"
                          >
                            <option value="" disabled>– Fach –</option>
                            <option
                              v-for="slot in (entry.rack_id ? getSlots(entry.rack_id) : [])"
                              :key="slot.id"
                              :value="String(slot.id)"
                              :title="getSlotPreviewTitle(entry.rack_id, slot.id)"
                            >
                              {{ formatSlotOptionLabel(entry.rack_id, slot) }}
                            </option>
                          </select>
                        </template>
                        <template v-else>
                          <label class="form-label-sm">Kiste/Tasche</label>
                          <select
                            v-model="entry.container_batch_id"
                            class="form-select form-select--sm"
                            @mouseenter="prefetchContainerPreviews()"
                            :title="getContainerPreviewTitle(entry.container_batch_id)"
                          >
                            <option value="">– Kiste wählen –</option>
                            <option
                              v-for="cb in containerBatches"
                              :key="cb.id"
                              :value="cb.id"
                              :title="formatContainerBatchOptionFullLabel(cb)"
                            >
                              {{ formatContainerBatchOptionFullLabel(cb) }}
                            </option>
                          </select>
                        </template>
                      </div>
                    </div>

                    <div class="serial-block serial-block--notes">
                      <label class="form-label-sm">Notiz (optional)</label>
                      <input
                        v-model="entry.notes"
                        type="text"
                        class="form-input notes-input"
                        placeholder="Optional"
                      />
                    </div>

                    <div class="serial-block serial-block--actions">
                      <button
                        type="button"
                        class="remove-serial-btn"
                        @click="openSerialScannerFor(entry.id)"
                        title="Seriennummer scannen"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="5" width="18" height="14" rx="2"/>
                          <line x1="7" y1="9" x2="17" y2="9"/>
                          <line x1="7" y1="13" x2="12" y2="13"/>
                        </svg>
                      </button>
                      <button type="button" class="remove-serial-btn" @click="removeSerialNumber(entry.id)" title="Entfernen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="18" y1="6" x2="6" y2="18"/>
                          <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-serials">
                  <p>Noch keine Seriennummern hinzugefügt</p>
                  <button type="button" class="add-first-btn" @click="addSerialNumber">+ Erste Seriennummer hinzufügen</button>
                </div>
                <p v-if="!serialLocationSameForAll && hasInvalidSerialLocations" class="field-hint is-invalid">
                  Bitte pro Seriennummer einen gueltigen Standort (Gestell/Fach oder Kiste) waehlen.
                </p>
                <p v-if="serialDuplicateHint" class="field-hint is-invalid">{{ serialDuplicateHint }}</p>
              </div>

              <div class="form-row mt-3">
                <div class="form-group">
                  <label>Kaufdatum <span class="required">*</span></label>
                  <input
                    v-model="form.acquired_on"
                    type="date"
                    class="batch-form-input"
                    :class="{ 'is-invalid': submitted && !form.acquired_on }"
                    required
                  />
                </div>
                <div class="form-group">
                  <label>Stückpreis (CHF)</label>
                  <div class="batch-price-input">
                    <span class="batch-currency">Fr.</span>
                    <input v-model="form.unit_price" type="text" class="batch-form-input" placeholder="0.00" />
                  </div>
                </div>
              </div>
          </div>

          <div v-else class="batch-wizard-stock">
          <!-- Wie Material-Wizard „Initialer Bestand“: Menge, Kaufdatum, Preis in einer Zeile -->
          <div class="form-row mb-2">
            <div class="form-group">
              <label>Menge <span class="required" v-if="!isEditMode">*</span></label>
              <input
                v-model.number="form.qty"
                type="number"
                min="1"
                class="form-input"
                :class="{ 'is-invalid': submitted && form.qty < 1 }"
                placeholder="1"
              />
            </div>
            <div class="form-group">
              <label>Kaufdatum <span class="required" v-if="!isEditMode">*</span></label>
              <input
                v-if="!isEditMode"
                v-model="form.acquired_on"
                type="date"
                class="form-input"
                :class="{ 'is-invalid': submitted && !form.acquired_on }"
                required
              />
              <div v-else class="batch-readonly-value">
                {{ formatDate(form.acquired_on) }}
                <span class="batch-readonly-hint">Kaufdatum kann nicht geändert werden (in ID eingebettet)</span>
              </div>
            </div>
            <div class="form-group">
              <label>Stückpreis (CHF)</label>
              <div class="price-input">
                <span class="currency">Fr.</span>
                <input v-model="form.unit_price" type="text" class="form-input" placeholder="0.00" />
              </div>
            </div>
          </div>

          <!-- Seriennummer(n) bei serialisierten Materialien (nur Bearbeiten) -->
          <template v-if="isSerializedMaterial && isEditMode">
            <div class="batch-form-row">
              <div class="batch-form-group full-width">
                <label>Seriennummer</label>
                <input 
                  v-model="form.serial_number" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="Seriennummer eingeben..."
                />
              </div>
            </div>
            <div class="batch-form-row">
              <div class="batch-form-group full-width">
                <label>Bezeichnung (optional)</label>
                <input 
                  v-model="form.label" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="z.B. Kochbox, Kochkiste Falk..."
                />
                <p class="batch-field-hint">Anzeigename in der Lagerübersicht – kann jederzeit geändert werden.</p>
              </div>
            </div>
          </template>

          <!-- Auf mehrere Lagerplätze aufteilen (nur bei Bulk) -->
          <div v-if="!isSerializedMaterial" class="form-row mb-2">
            <label class="toggle-label">
              <span class="toggle-wrapper">
                <input v-model="form.split_allocations" type="checkbox" class="toggle-input" />
                <span class="toggle-slider toggle-slider--blue"></span>
              </span>
              <span class="toggle-text">
                <span class="toggle-title">Auf mehrere Lagerplätze aufteilen</span>
                <span class="toggle-desc">Menge auf verschiedene Gestelle/Fächer verteilen</span>
              </span>
            </label>
          </div>

          <!-- Allokations-Tabelle -->
          <div v-if="!isSerializedMaterial && form.split_allocations" class="batch-form-row">
            <div class="batch-form-group full-width">
              <div class="allocations-header">
                <label>Lagerplätze (Summe = {{ form.qty }} Stk.)</label>
                <button type="button" class="add-serial-btn" @click="addAllocationRow">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                  Zeile hinzufügen
                </button>
              </div>
              <div class="allocations-table-wrap">
                <table class="allocations-table">
                  <thead>
                    <tr>
                      <th>Menge</th>
                      <th>Art</th>
                      <th>Lagerort</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in allocationRows" :key="row.id">
                      <td>
                        <input
                          v-model.number="row.qty"
                          type="number"
                          min="1"
                          class="batch-form-input form-input--sm"
                          placeholder="0"
                        />
                      </td>
                      <td>
                        <select v-model="row.mode" class="batch-form-input form-select--sm" @change="row.rack_id = ''; row.slot_id = ''; row.container_batch_id = ''">
                          <option value="slot">Slot</option>
                          <option value="kiste">Kiste</option>
                        </select>
                      </td>
                      <td>
                        <template v-if="row.mode === 'slot'">
                          <StorageLocationPicker
                            variant="compact"
                            :show-storage-address="true"
                            :storage-address-id="row.storage_address_id"
                            :storage-address-options="storageAddressOptions"
                            :rack-id="row.rack_id"
                            :slot-id="row.slot_id"
                            :racks="getAllocationRacks(row)"
                            :slot-list="row.rack_id ? getSlots(row.rack_id) : []"
                            :rack-label-formatter="formatRackOptionLabel"
                            :rack-option-title-formatter="(r) => rackPreviewTitles[r.id] || ''"
                            :slot-label-formatter="(slot) => formatSlotOptionLabel(row.rack_id, slot)"
                            :slot-option-title-formatter="(s) => slotPreviewTitles[`${String(row.rack_id || '')}:${String(s.id)}`] || ''"
                            storage-address-label="Standort"
                            rack-label="Gestell"
                            slot-label="Fach"
                            storage-address-placeholder="– Standort –"
                            rack-placeholder="– Gestell –"
                            slot-placeholder="– optional –"
                            @rackListMouseenter="prefetchVisibleRackPreviews(getAllocationRacks(row))"
                            @slotListMouseenter="prefetchSlotPreviewsForRack(String(row.rack_id || ''))"
                            @update:storageAddressId="row.storage_address_id = $event"
                            @storageAddressChange="onAllocationStorageAddressChange(row)"
                            @update:rackId="onAllocationRackIdUpdate(row, $event)"
                            @update:slotId="row.slot_id = $event"
                          />
                        </template>
                        <select
                          v-else
                          v-model="row.container_batch_id"
                          class="batch-form-input form-select--sm"
                        >
                          <option value="">– Kiste wählen –</option>
                                <option
                                  v-for="cb in containerBatches"
                                  :key="cb.id"
                                  :value="cb.id"
                                  :title="formatContainerBatchOptionFullLabel(cb)"
                                >
                                  {{ formatContainerBatchOptionFullLabel(cb) }}
                                </option>
                        </select>
                      </td>
                      <td>
                        <button type="button" class="remove-row-btn" @click="removeAllocationRow(row.id)" title="Entfernen">×</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-if="allocationRows.length > 0 && !allocationSumValid" class="batch-field-hint is-invalid">
                Summe muss {{ form.qty }} Stk. ergeben (aktuell: {{ allocationSum }})
              </p>
            </div>
          </div>

          <!-- Einzelner Lagerplatz (Bulk ohne Split-Allokationen, oder Charge bearbeiten serialisiert) -->
          <div
            v-if="!isSerializedAddMode && ((!isSerializedMaterial && !form.split_allocations) || (isSerializedMaterial && isEditMode))"
            class="batch-form-row"
          >
            <div class="batch-form-group full-width">
              <StorageLocationPicker
                :show-storage-address="true"
                :storage-address-id="form.storage_address_id"
                :storage-address-options="storageAddressOptions"
                :rack-id="form.rack_id"
                :slot-id="form.slot_id"
                :racks="filteredRacks"
                :slot-list="mainSlots"
                :rack-label-formatter="formatRackOptionLabel"
                :rack-option-title-formatter="(r) => rackPreviewTitles[r.id] || ''"
                :slot-label-formatter="(slot) => formatSlotOptionLabel(form.rack_id, slot)"
                :slot-option-title-formatter="(s) => slotPreviewTitles[`${String(form.rack_id || '')}:${String(s.id)}`] || ''"
                storage-address-label="Standort"
                rack-label="Gestell"
                slot-label="Fach"
                storage-address-placeholder="Standort auswaehlen..."
                rack-placeholder="Gestell auswaehlen..."
                slot-placeholder="Fach auswaehlen..."
                @rackListMouseenter="prefetchVisibleRackPreviews(filteredRacks)"
                @slotListMouseenter="prefetchSlotPreviewsForRack(String(form.rack_id || ''))"
                @update:storageAddressId="form.storage_address_id = $event"
                @storageAddressChange="onStorageAddressChange"
                @update:rackId="onMainRackIdUpdate"
                @update:slotId="form.slot_id = $event"
                @slotChange="onSlotChange"
              />
            </div>
          </div>

          </div>

          <!-- Lieferant (Autocomplete) -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Gekauft von (Lieferant)</label>
              <div class="batch-autocomplete-wrapper">
                <input 
                  v-model="supplierSearch" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="Lieferant suchen..."
                  @input="filterSuppliers"
                  @focus="showSupplierDropdown = true"
                  @blur="hideSupplierDropdownDelayed"
                />
                <button type="button" class="batch-add-inline-btn" @click="openAddSupplierModal" title="Neuen Lieferanten hinzufügen">+</button>
                <div v-if="showSupplierDropdown && supplierSearch.length >= 1" class="batch-autocomplete-dropdown">
                  <div 
                    v-for="addr in filteredSuppliers" 
                    :key="addr.id"
                    class="batch-autocomplete-item"
                    @mousedown="selectSupplier(addr)"
                  >
                    <span class="batch-ac-name">{{ addr.name || addr.company }}</span>
                    <span v-if="addr.city" class="batch-ac-city">{{ addr.city }}</span>
                  </div>
                  <!-- Keine Ergebnisse → Neu erstellen -->
                  <div 
                    v-if="filteredSuppliers.length === 0" 
                    class="batch-autocomplete-item batch-ac-create"
                    @mousedown="openAddSupplierModal"
                  >
                    <span class="batch-ac-name">+ "{{ supplierSearch }}" als Lieferant anlegen</span>
                  </div>
                </div>
              </div>
              <p v-if="selectedSupplier" class="batch-selected-supplier">
                ✓ {{ selectedSupplier.name || selectedSupplier.company }}
                <button type="button" class="batch-clear-btn" @click="clearSupplier">×</button>
              </p>
            </div>
          </div>

          <!-- Notizen -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Notiz</label>
              <textarea 
                v-model="form.notes" 
                class="batch-form-textarea"
                rows="2"
                placeholder="Optionale Notiz zur Charge..."
              ></textarea>
            </div>
          </div>

          <!-- Fehlermeldung -->
          <div v-if="errorMsg" class="batch-error">
            {{ errorMsg }}
          </div>
        </div>

        <!-- Footer -->
        <div class="batch-modal-footer">
          <div v-if="missingFields.length > 0" class="batch-missing">
            <span class="batch-missing-icon">⚠️</span>
            <span>{{ missingFields[0] }}</span>
          </div>
          <div class="batch-footer-actions">
            <button class="btn-secondary btn-sm" @click="$emit('close')">Abbrechen</button>
            <button 
              class="btn-primary btn-sm" 
              @click="handleSubmit"
              :disabled="!canSubmit || isSaving"
            >
              {{ isSaving ? 'Speichern...' : (isEditMode ? 'Speichern' : 'Hinzufügen') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Adress-Modal für neuen Lieferanten -->
    <AddressModal
      v-if="showAddressModal"
      :department-id="departmentId"
      default-type="supplier"
      :default-name="supplierSearch"
      @close="showAddressModal = false"
      @saved="handleAddressSaved"
    />
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { enqueuePendingCostBookingAfterPurchase } from '@/composables/useCostBookingFollowUp'
import { addBatch, updateBatch, type MaterialBatch, type AddBatchRequest, type UpdateBatchRequest, type AddBatchMultiResponse } from '@/api/materials'
import { getAddresses, type Address } from '@/api/addresses'
import {
  getContainerBatches,
  getRackContents,
  getStorageOverview,
  type StorageRack,
  type StorageSlot,
  type StorageOverviewResponse,
} from '@/api/storageLocations'
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'
import { usePhysicalComboWarningStore } from '@/stores/physicalComboWarning'
import {
  formatFachSelectPreviewLine,
  formatRackSlotsDirectPreview,
  summarizeMaterialsForPreview,
} from '@/utils/storageSlotContentPreview'
import AddressModal from '@/components/AddressModal.vue'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import { useStorageStructure } from '@/composables/useStorageStructure'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import '@/styles/material-wizard.css'
import type { ContainerBatch } from '@/api/storageLocations'

interface Props {
  materialId: string
  departmentId: string
  batch?: MaterialBatch | null // null = Add-Modus, batch = Edit-Modus
  initialContainerBatchId?: string
  /** Explizit aus Material.tracking_type setzen – zuverlässiger als nur boolean */
  trackingType?: string | null
  isSerialized?: boolean
  materialName?: string
  existingBatches?: MaterialBatch[]
}

const props = withDefaults(defineProps<Props>(), {
  batch: null,
  initialContainerBatchId: '',
  trackingType: undefined,
  isSerialized: false,
  materialName: '',
  existingBatches: () => []
})

const emit = defineEmits<{
  close: []
  saved: [result: MaterialBatch | AddBatchMultiResponse]
}>()

const toast = useToast()
const headerNotificationsStore = useHeaderNotificationsStore()
const physicalComboWarningStore = usePhysicalComboWarningStore()
const isEditMode = computed(() => !!props.batch)

/** Material ist serialisiert (tracking_type oder Fallback über Chargen mit Seriennummer) */
const isSerializedMaterial = computed(() => {
  const raw = props.trackingType
  if (raw !== undefined && raw !== null && String(raw).trim() !== '') {
    return String(raw).toLowerCase().trim() === 'serialized'
  }
  if (props.isSerialized === true) return true
  if (props.isSerialized === false) return false
  // Fallback: API liefert tracking_type manchmal nicht – aktive Chargen haben Seriennummern
  const active = (props.existingBatches || []).filter((b) => (b.status || 'active') === 'active')
  if (active.length > 0 && active.every((b) => (b.serial_number || '').trim() !== '')) {
    return true
  }
  return false
})

/** Serialisiert + Charge hinzufügen: gleiches UI wie Material-Erstellwizard */
const isSerializedAddMode = computed(() => isSerializedMaterial.value && !isEditMode.value)
const isSaving = ref(false)
const submitted = ref(false)
const errorMsg = ref('')

// Lieferant Autocomplete
const allSuppliers = ref<Address[]>([])
const filteredSuppliers = ref<Address[]>([])
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)
const selectedSupplier = ref<Address | null>(null)
const storageAddresses = ref<Address[]>([])
const {
  racks,
  loadRacks,
  loadSlotsEnsuringDefault: fetchSlotsEnsuringDefault,
  getSlots,
} = useStorageStructure(() => props.departmentId)
const mainSlots = computed<StorageSlot[]>(() => getSlots(form.rack_id))
const rackPreviewTitles = ref<Record<string, string>>({})
const slotPreviewTitles = ref<Record<string, string>>({})
const storageOverviewCache = ref<StorageOverviewResponse | null>(null)

const form = reactive({
  acquired_on: '',
  qty: 1,
  unit_price: '',
  serial_number: '',
  label: '',
  storage_address_id: '',
  rack_id: '',
  slot_id: '',
  container_batch_id: '',
  supplier_id: '',
  notes: '',
  split_allocations: false
})

/** Nur „Charge hinzufügen“ serialisiert: gleiche Logik wie Wizard */
const stockLocationMode = ref<'slot' | 'kiste'>('slot')
const serialLocationSameForAll = ref(true)
const serialAutoGenerateEnabled = ref(false)
const autoGenPrefix = ref('')
const autoGenStart = ref(1)
const autoGenPad = ref(3)
const autoGenCount = ref(5)
const serialScannerActive = ref(false)
const serialScannerTargetId = ref<number | null>(null)

interface SerialNumberEntry {
  id: number
  serial_number: string
  label: string
  notes: string
  location_mode: 'slot' | 'kiste'
  storage_address_id: string
  rack_id: string
  slot_id: string
  container_batch_id: string
}
const serialRows = ref<SerialNumberEntry[]>([])
let serialIdCounter = 0

const filteredRacks = computed(() => {
  if (!form.storage_address_id) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === form.storage_address_id)
})

const storageAddressOptions = computed(() =>
  storageAddresses.value.map((addr) => ({
    id: addr.id,
    label: addr.name || addr.street_line || addr.full_address || addr.id,
  }))
)

const suggestedSerialPrefix = computed(() => {
  const name = (props.materialName || '').trim()
  if (!name) return ''
  return `${name.replace(/[^a-zA-Z0-9äöüÄÖÜß]/g, '').slice(0, 12)}-`
})

const serializedQty = computed(
  () => serialRows.value.filter((e) => (e.serial_number || '').trim().length > 0).length
)

const autoGenPreview = computed(() => {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value || 'SER-'
  const start = Math.max(1, autoGenStart.value)
  const pad = Math.max(1, Math.min(6, autoGenPad.value || 3))
  const count = Math.max(1, Math.min(100, autoGenCount.value || 1))
  if (count <= 2) {
    return Array.from({ length: count }, (_, i) => prefix + String(start + i).padStart(pad, '0')).join(', ')
  }
  const first = prefix + String(start).padStart(pad, '0')
  const last = prefix + String(start + count - 1).padStart(pad, '0')
  return `${first} … ${last} (${count} Stk.)`
})

function getPreferredStorageAddressIdForBatch(): string {
  return form.storage_address_id || storageAddresses.value[0]?.id || ''
}

function createEmptySerialRow(): SerialNumberEntry {
  return {
    id: ++serialIdCounter,
    serial_number: '',
    label: '',
    notes: '',
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressIdForBatch(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  }
}

function addSerialNumber() {
  serialRows.value.push(createEmptySerialRow())
}

function removeSerialNumber(id: number) {
  serialRows.value = serialRows.value.filter((r) => r.id !== id)
  if (serialScannerTargetId.value === id) serialScannerTargetId.value = null
}

function getRacksForSerialEntry(entry: SerialNumberEntry): StorageRack[] {
  if (!entry.storage_address_id) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === entry.storage_address_id)
}

function onSerialEntryStorageAddressChange(entry: SerialNumberEntry) {
  entry.rack_id = ''
  entry.slot_id = ''
}

async function onSerialEntryRackChange(entry: SerialNumberEntry) {
  entry.slot_id = ''
  if (entry.rack_id) {
    await fetchSlotsEnsuringDefault(entry.rack_id)
    await prefetchSlotPreviewsForRack(entry.rack_id)
  }
}

function getSerialRowTitle(entry: SerialNumberEntry, index: number): string {
  const sn = (entry.serial_number || '').trim()
  return sn ? `Seriennummer ${index + 1} · ${sn}` : `Seriennummer ${index + 1}`
}

async function prefetchContainerPreviews() {
  await prefetchStorageOverview()
}

function getContainerPreviewTitle(containerBatchId: string): string {
  if (!containerBatchId) return ''
  const cb = containerBatches.value.find((c) => c.id === containerBatchId)
  return cb ? formatContainerBatchOptionFullLabel(cb) : ''
}

function formatSlotOptionLabel(rackId: string, slot: StorageSlot): string {
  const key = `${rackId}:${String(slot.id)}`
  const preview = (slotPreviewTitles.value[key] || '').trim()
  const base = slot.name
  if (preview) {
    return `${base} · ${preview}`
  }
  return base
}

function formatRackOptionLabel(rack: StorageRack): string {
  const preview = (rackPreviewTitles.value[rack.id] || '').trim()
  if (!preview) return rack.name
  const oneLine = preview.replace(/\n/g, ' · ')
  const short = oneLine.length > 72 ? `${oneLine.slice(0, 69)}…` : oneLine
  return `${rack.name} · ${short}`
}

function setStockLocationMode(mode: 'slot' | 'kiste') {
  stockLocationMode.value = mode
  if (mode === 'slot') {
    form.container_batch_id = ''
  } else {
    form.rack_id = ''
    form.slot_id = ''
  }
}

function toggleSerialScanner() {
  if (serialScannerActive.value) {
    serialScannerActive.value = false
    return
  }
  const firstEmpty = serialRows.value.find((s) => !s.serial_number.trim())
  serialScannerTargetId.value = firstEmpty?.id ?? serialRows.value[0]?.id ?? null
  if (!serialScannerTargetId.value) {
    addSerialNumber()
    serialScannerTargetId.value = serialRows.value[serialRows.value.length - 1]?.id ?? null
  }
  serialScannerActive.value = true
}

function openSerialScannerFor(id: number) {
  serialScannerTargetId.value = id
  serialScannerActive.value = true
}

function onSerialDetected(payload: { text: string }) {
  const value = payload.text.trim()
  if (!value) return
  let target = serialRows.value.find((s) => s.id === serialScannerTargetId.value)
  if (!target) target = serialRows.value.find((s) => !s.serial_number.trim())
  if (!target) {
    addSerialNumber()
    target = serialRows.value[serialRows.value.length - 1]
  }
  target.serial_number = value
  const nextEmpty = serialRows.value.find((s) => !s.serial_number.trim())
  serialScannerTargetId.value = nextEmpty?.id ?? null
}

function onSerialScannerError() {
  // optional
}

function generateSerialNumbers() {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value || 'SER-'
  const start = Math.max(1, autoGenStart.value)
  const pad = Math.max(1, Math.min(6, autoGenPad.value || 3))
  const count = Math.max(1, Math.min(100, autoGenCount.value || 1))
  serialRows.value = Array.from({ length: count }, (_, i) => ({
    id: ++serialIdCounter,
    serial_number: prefix + String(start + i).padStart(pad, '0'),
    label: '',
    notes: '',
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressIdForBatch(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  }))
  form.qty = count
}

const suggestedStartNumber = computed(() => {
  const existing = (props.existingBatches || [])
    .filter((b) => b.serial_number)
    .map((b) => {
      const sn = b.serial_number || ''
      const match = sn.match(/(\d+)$/)
      return match ? parseInt(match[1], 10) : 0
    })
  const max = existing.length ? Math.max(...existing) : 0
  return max + 1
})

const hasInvalidSerialLocations = computed(
  () =>
    isSerializedAddMode.value &&
    !serialLocationSameForAll.value &&
    serialRows.value
      .filter((e) => e.serial_number.trim())
      .some((e) =>
        e.location_mode === 'kiste' ? !e.container_batch_id : !e.rack_id || !e.slot_id
      )
)

const serialDuplicateHint = computed(() => {
  if (!props.isSerialized) return ''
  if (isSerializedAddMode.value) {
    const existing = new Set(
      (props.existingBatches || []).map((b) => (b.serial_number || '').trim()).filter(Boolean)
    )
    const duplicates = serialRows.value
      .map((e) => e.serial_number.trim())
      .filter((sn) => sn && existing.has(sn))
    if (duplicates.length > 0) {
      return `Seriennummer(n) bereits vergeben: ${duplicates.slice(0, 3).join(', ')}${duplicates.length > 3 ? '…' : ''}`
    }
    const seen = new Set<string>()
    for (const e of serialRows.value) {
      const sn = e.serial_number.trim()
      if (sn && seen.has(sn)) return 'Doppelte Seriennummern in der Liste'
      if (sn) seen.add(sn)
    }
    return ''
  }
  return ''
})

// Allokationen für mehrere Lagerplätze
interface AllocationRow {
  id: number
  mode: 'slot' | 'kiste'
  storage_address_id: string
  rack_id: string
  slot_id: string
  container_batch_id: string
  qty: number
}
let allocationIdCounter = 0
const allocationRows = ref<AllocationRow[]>([])
const containerBatches = ref<ContainerBatch[]>([])
const prefilledContainerMode = ref(false)

function getTodayIsoDate(): string {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

/** Haupt-Lagerplatz nach höchster Bestandsmenge (Slot vs. Kiste/Tasche). */
function pickPreferredLocation(): void {
  const slotScores = new Map<string, { qty: number; storageAddressId: string; rackId: string; slotId: string }>()
  const upsertSlot = (rackId?: string | null, slotId?: string | null, qty = 0) => {
    const rid = String(rackId || '').trim()
    if (!rid) return
    const sid = String(slotId || '').trim()
    const rack = racks.value.find((r) => r.id === rid)
    const storageAddressId = rack?.storage_address_id || ''
    const key = `${rid}::${sid}`
    const prev = slotScores.get(key)
    if (prev) {
      prev.qty += Math.max(0, Number(qty || 0))
      return
    }
    slotScores.set(key, {
      qty: Math.max(0, Number(qty || 0)),
      storageAddressId,
      rackId: rid,
      slotId: sid,
    })
  }
  const containerScores = new Map<string, number>()
  const addContainer = (id: string | null | undefined, qty: number) => {
    const cid = String(id || '').trim()
    if (!cid) return
    containerScores.set(cid, (containerScores.get(cid) || 0) + Math.max(0, qty))
  }

  for (const batch of props.existingBatches || []) {
    if ((batch.status || '').toLowerCase() !== 'active') continue
    const batchQty = Math.max(0, Number(batch.qty || 0))
    if (Array.isArray(batch.allocations) && batch.allocations.length > 0) {
      for (const alloc of batch.allocations) {
        const allocQty = Math.max(0, Number(alloc.qty || 0))
        const cb = alloc.container_batch
        if (cb?.id) {
          addContainer(cb.id, allocQty)
          upsertSlot(cb.rack?.id || null, cb.slot?.id || null, allocQty)
        } else {
          upsertSlot(alloc.rack_id, alloc.slot_id, allocQty)
        }
      }
      continue
    }
    upsertSlot(batch.rack_id, batch.slot_id, batchQty)
  }

  let bestSlot: { qty: number; storageAddressId: string; rackId: string; slotId: string } | null = null
  for (const v of slotScores.values()) {
    if (!bestSlot || v.qty > bestSlot.qty) bestSlot = v
  }
  let bestContainer: { id: string; qty: number } | null = null
  for (const [id, qty] of containerScores.entries()) {
    if (!bestContainer || qty > bestContainer.qty) bestContainer = { id, qty }
  }

  const slotScore = bestSlot?.qty ?? 0
  const containerScore = bestContainer?.qty ?? 0

  if (!bestSlot && !bestContainer) return

  if (bestContainer && containerScore > slotScore) {
    stockLocationMode.value = 'kiste'
    form.container_batch_id = bestContainer.id
    form.rack_id = ''
    form.slot_id = ''
    form.storage_address_id = ''
    return
  }
  if (bestSlot?.rackId) {
    stockLocationMode.value = 'slot'
    form.storage_address_id = bestSlot.storageAddressId
    form.rack_id = bestSlot.rackId
    form.slot_id = bestSlot.slotId || ''
    form.container_batch_id = ''
  }
}

function addAllocationRow() {
  allocationRows.value.push({
    id: ++allocationIdCounter,
    mode: 'slot',
    storage_address_id: '',
    rack_id: '',
    slot_id: '',
    container_batch_id: '',
    qty: 0
  })
}

function removeAllocationRow(id: number) {
  allocationRows.value = allocationRows.value.filter((r) => r.id !== id)
}

async function loadSlotsForAllocationRack(rackId: string) {
  if (!rackId) return
  await fetchSlotsEnsuringDefault(rackId)
  await prefetchSlotPreviewsForRack(rackId)
}

function getAllocationRacks(row: AllocationRow): StorageRack[] {
  if (!row.storage_address_id) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === row.storage_address_id)
}

function onAllocationStorageAddressChange(row: AllocationRow) {
  row.rack_id = ''
  row.slot_id = ''
}

function resolveContainerBatchLabel(containerBatchId: string): string {
  const cb = containerBatches.value.find((c) => c.id === containerBatchId)
  if (!cb) return ''
  return (cb.label || cb.serial_number || cb.display_label || cb.material_name || '').trim()
}

async function prefetchStorageOverview() {
  if (storageOverviewCache.value) return
  storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
}

async function prefetchRackPreview(rackId: string) {
  if (!rackId || rackPreviewTitles.value[rackId]) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const resolve = (id: string) => resolveContainerBatchLabel(id)

  let text = ''
  if (rack?.slots?.length) {
    text = formatRackSlotsDirectPreview(rack.slots, resolve).trim()
  }
  if (!text) {
    const data = await getRackContents(rackId).catch(() => null)
    const items = (data?.contents || []).map((c: { material_name: string; qty: number }) => ({
      material_name: c.material_name || 'Material',
      qty: Number(c.qty || 0),
    }))
    text = summarizeMaterialsForPreview(items)
  }
  if (!text.trim()) text = 'Leer'

  rackPreviewTitles.value = {
    ...rackPreviewTitles.value,
    [rackId]: text,
  }
}

async function prefetchSlotPreview(rackId: string, slotId: string) {
  if (!rackId || !slotId) return
  const key = `${rackId}:${slotId}`
  if (slotPreviewTitles.value[key]) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const slot = rack?.slots?.find((s) => String(s.id) === String(slotId))
  const line = formatFachSelectPreviewLine(slot?.contents || [])
  slotPreviewTitles.value = {
    ...slotPreviewTitles.value,
    [key]: line,
  }
}

async function prefetchSlotPreviewsForRack(rackId: string) {
  if (!rackId) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  if (!rack?.slots?.length) return
  const next = { ...slotPreviewTitles.value }
  for (const slot of rack.slots) {
    const key = `${rackId}:${String(slot.id)}`
    if (next[key]) continue
    next[key] = formatFachSelectPreviewLine(slot.contents || [])
  }
  slotPreviewTitles.value = next
}

function getRackPreviewTitle(rackId: string): string {
  if (!rackId) return ''
  return rackPreviewTitles.value[rackId] || 'Inhalt wird geladen...'
}

function getSlotPreviewTitle(rackId: string, slotId: string): string {
  if (!rackId || !slotId) return ''
  return slotPreviewTitles.value[`${rackId}:${slotId}`] || 'Inhalt wird geladen...'
}

async function prefetchVisibleRackPreviews(list: StorageRack[]) {
  const sample = list.slice(0, 20)
  await Promise.all(sample.map((rack) => prefetchRackPreview(rack.id)))
}

const allocationSum = computed(() =>
  allocationRows.value.reduce((sum, r) => sum + (r.qty || 0), 0)
)
const allocationSumValid = computed(() =>
  form.qty > 0 && allocationSum.value === form.qty
)

// Form befüllen
onMounted(async () => {
  // Lieferanten laden
  try {
    const result = await getAddresses(props.departmentId, 'supplier')
    allSuppliers.value = result.addresses || []
  } catch (err) {
    console.error('Fehler beim Laden der Lieferanten:', err)
  }

  try {
    const storageResult = await getAddresses(props.departmentId, 'storage')
    storageAddresses.value = storageResult.addresses || []
  } catch (err) {
    console.error('Fehler beim Laden der Lagerstandorte:', err)
    storageAddresses.value = []
  }

  try {
    await loadRacks()
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchVisibleRackPreviews(racks.value)
  } catch (err) {
    console.error('Fehler beim Laden der Gestelle:', err)
  }

  if (props.batch) {
    // Edit-Modus: Werte aus bestehendem Batch übernehmen
    form.acquired_on = props.batch.acquired_on || ''
    form.qty = props.batch.qty
    form.unit_price = props.batch.unit_price || ''
    form.serial_number = props.batch.serial_number || ''
    form.label = (props.batch as any).label || ''
    form.rack_id = props.batch.rack_id || ''
    form.slot_id = props.batch.slot_id || ''
    form.notes = props.batch.notes || ''
    if (form.rack_id) {
      const selectedRack = racks.value.find((rack) => rack.id === form.rack_id)
      form.storage_address_id = selectedRack?.storage_address_id || ''
    }
    // Lieferant aus Batch vorbelegen (wenn vorhanden)
    if ((props.batch as any).supplier_id) {
      form.supplier_id = (props.batch as any).supplier_id
      const match = allSuppliers.value.find(s => s.id === form.supplier_id)
      if (match) {
        selectedSupplier.value = match
        supplierSearch.value = match.name || match.company || ''
      }
    }
  } else {
    form.acquired_on = getTodayIsoDate()
    pickPreferredLocation()
  }

  if (isSerializedAddMode.value) {
    addSerialNumber()
    serialLocationSameForAll.value = true
    autoGenStart.value = suggestedStartNumber.value
  } else if (!props.isSerialized && props.initialContainerBatchId) {
    form.split_allocations = true
    const initialQty = Math.max(1, form.qty || 1)
    allocationRows.value = [{
      id: ++allocationIdCounter,
      mode: 'kiste',
      storage_address_id: '',
      rack_id: '',
      slot_id: '',
      container_batch_id: props.initialContainerBatchId,
      qty: initialQty,
    }]
    prefilledContainerMode.value = true
  }

  if (form.rack_id) {
    await fetchSlotsEnsuringDefault(form.rack_id)
    await prefetchSlotPreviewsForRack(form.rack_id)
  }
})

watch(serialLocationSameForAll, async (same) => {
  if (same || !isSerializedAddMode.value) return
  const addr = form.storage_address_id
  const rack = form.rack_id
  const slot = form.slot_id
  const cb = form.container_batch_id
  const mode = stockLocationMode.value
  for (const entry of serialRows.value) {
    entry.location_mode = mode === 'kiste' ? 'kiste' : 'slot'
    entry.storage_address_id = addr
    entry.rack_id = mode === 'slot' ? rack : ''
    entry.slot_id = mode === 'slot' ? slot : ''
    entry.container_batch_id = mode === 'kiste' ? cb : ''
    if (entry.rack_id) await fetchSlotsEnsuringDefault(entry.rack_id)
  }
})

watch(() => form.qty, (qty) => {
  if (!prefilledContainerMode.value) return
  if (allocationRows.value.length !== 1) return
  const row = allocationRows.value[0]
  if (row.mode !== 'kiste' || !row.container_batch_id) return
  row.qty = Math.max(1, qty || 1)
})

async function loadSlotsForMainRack(rackId: string) {
  if (!rackId) {
    form.slot_id = ''
    return
  }
  try {
    await fetchSlotsEnsuringDefault(rackId)
    await prefetchSlotPreviewsForRack(rackId)
  } catch (err) {
    console.error('Fehler beim Laden der Slots:', err)
  }
}

async function onMainRackIdUpdate(rackId: string) {
  form.rack_id = rackId
  form.slot_id = ''
  const selectedRack = racks.value.find((r) => String(r.id) === String(rackId))
  form.storage_address_id = selectedRack?.storage_address_id || form.storage_address_id
  await loadSlotsForMainRack(rackId)
}

async function onAllocationRackIdUpdate(row: AllocationRow, rackId: string) {
  row.rack_id = rackId
  row.slot_id = ''
  await loadSlotsForAllocationRack(rackId)
}

function onStorageAddressChange() {
  form.rack_id = ''
  form.slot_id = ''
}

function onSlotChange() {
  // v-model already updates form.slot_id; this hook keeps template API stable.
}

// Lieferant Suche
function filterSuppliers() {
  const query = supplierSearch.value.toLowerCase().trim()
  if (!query) {
    filteredSuppliers.value = allSuppliers.value.slice(0, 10)
    return
  }
  filteredSuppliers.value = allSuppliers.value
    .filter(a => (a.name?.toLowerCase().includes(query)) || (a.company?.toLowerCase().includes(query)))
    .slice(0, 10)
}

function selectSupplier(addr: Address) {
  selectedSupplier.value = addr
  form.supplier_id = addr.id
  supplierSearch.value = addr.name || addr.company || ''
  showSupplierDropdown.value = false
}

function clearSupplier() {
  selectedSupplier.value = null
  form.supplier_id = ''
  supplierSearch.value = ''
}

function hideSupplierDropdownDelayed() {
  setTimeout(() => {
    showSupplierDropdown.value = false
  }, 200)
}

// Adress-Modal
const showAddressModal = ref(false)

function openAddSupplierModal() {
  showSupplierDropdown.value = false
  showAddressModal.value = true
}

async function handleAddressSaved() {
  const savedName = supplierSearch.value.toLowerCase().trim()
  showAddressModal.value = false

  // Lieferanten neu laden
  try {
    const result = await getAddresses(props.departmentId, 'supplier')
    allSuppliers.value = result.addresses || []
  } catch (err) {
    console.error('Fehler beim Neuladen der Lieferanten:', err)
  }

  // Neu erstellten Lieferanten automatisch auswählen
  if (savedName) {
    const newAddr = allSuppliers.value.find(a =>
      (a.name?.toLowerCase() === savedName) ||
      (a.company?.toLowerCase() === savedName)
    )
    if (newAddr) {
      selectSupplier(newAddr)
    }
  }
}

function batchAddUnitPricePositive(): boolean {
  const raw = String(form.unit_price || '').replace(/\s/g, '').replace(',', '.')
  const up = parseFloat(raw)
  return Number.isFinite(up) && up > 0
}

const canSubmit = computed(() => {
  if (isEditMode.value) {
    return form.qty >= 1
  }
  if (!batchAddUnitPricePositive()) return false
  if (!form.acquired_on) return false
  if (isSerializedAddMode.value) {
    if (serializedQty.value < 1) return false
    if (serialDuplicateHint.value) return false
    if (hasInvalidSerialLocations.value) return false
    if (serialLocationSameForAll.value) {
      if (stockLocationMode.value === 'kiste') return !!form.container_batch_id
      return !!(form.rack_id && form.slot_id)
    }
    return true
  }
  if (form.qty < 1) return false
  if (form.split_allocations && (!allocationSumValid.value || allocationRows.value.every((r) => (r.mode === 'slot' ? !r.rack_id : !r.container_batch_id) || r.qty <= 0))) return false
  return true
})

const missingFields = computed(() => {
  const missing: string[] = []
  if (!isEditMode.value && !batchAddUnitPricePositive()) {
    missing.push('Stückpreis eingeben')
  }
  if (!isEditMode.value && !form.acquired_on) {
    missing.push('Kaufdatum eingeben')
  }
  if (isSerializedAddMode.value) {
    if (serializedQty.value < 1) missing.push('Mindestens eine Seriennummer erfassen')
    if (serialDuplicateHint.value) missing.push(serialDuplicateHint.value)
    if (hasInvalidSerialLocations.value) {
      missing.push('Bitte pro Seriennummer einen gültigen Lagerplatz wählen')
    }
    if (serialLocationSameForAll.value) {
      if (stockLocationMode.value === 'kiste' && !form.container_batch_id) missing.push('Kiste/Tasche wählen')
      if (stockLocationMode.value === 'slot' && (!form.rack_id || !form.slot_id)) missing.push('Gestell und Fach wählen')
    }
    return missing
  }
  if (form.qty < 1) {
    missing.push('Menge muss mindestens 1 sein')
  }
  if (form.split_allocations && (!allocationSumValid.value || allocationRows.value.every((r) => (r.mode === 'slot' ? !r.rack_id : !r.container_batch_id) || r.qty <= 0))) {
    missing.push('Lagerplätze: Summe muss ' + form.qty + ' Stk. ergeben')
  }
  return missing
})

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-CH')
}

function computeBatchAddPurchaseTotalChf(): number {
  if (isEditMode.value) return 0
  const raw = String(form.unit_price || '').replace(/\s/g, '').replace(',', '.')
  const up = parseFloat(raw)
  if (!Number.isFinite(up) || up <= 0) return 0
  if (isSerializedAddMode.value) {
    const n = serialRows.value.filter((e) => (e.serial_number || '').trim()).length
    return up * n
  }
  return up * (form.qty || 0)
}

function batchIdFromAddBatchResult(r: MaterialBatch | AddBatchMultiResponse): string | undefined {
  if ('created_batches' in r && Array.isArray(r.created_batches) && r.created_batches.length > 0) {
    return r.created_batches[0].id
  }
  if ('id' in r && typeof (r as MaterialBatch).id === 'string') {
    return (r as MaterialBatch).id
  }
  return undefined
}

/** Kisten-IDs, in die bei diesem „Charge hinzufügen“ eingelagert wird (für Kombi-Warnung). */
function collectContainerBatchIdsForPendingAdd(): string[] {
  const ids: string[] = []
  if (isEditMode.value) return ids
  if (isSerializedAddMode.value) {
    const rows = serialRows.value.filter((e) => (e.serial_number || '').trim())
    const qty = rows.length
    if (qty <= 0) return ids
    if (qty <= 1) {
      const r = rows[0]
      if (serialLocationSameForAll.value) {
        if (stockLocationMode.value === 'kiste' && form.container_batch_id) {
          ids.push(String(form.container_batch_id))
        }
      } else if (r.location_mode === 'kiste' && r.container_batch_id) {
        ids.push(String(r.container_batch_id))
      }
      return ids
    }
    if (serialLocationSameForAll.value) {
      if (stockLocationMode.value === 'kiste' && form.container_batch_id) {
        ids.push(String(form.container_batch_id))
      }
    } else {
      for (const e of rows) {
        if (e.location_mode === 'kiste' && e.container_batch_id) {
          ids.push(String(e.container_batch_id))
        }
      }
    }
    return ids
  }
  if (form.split_allocations && allocationRows.value.length > 0 && allocationSumValid.value) {
    for (const r of allocationRows.value) {
      if (r.qty > 0 && r.mode === 'kiste' && r.container_batch_id) {
        ids.push(String(r.container_batch_id))
      }
    }
  }
  return ids
}

async function handleSubmit() {
  submitted.value = true
  errorMsg.value = ''
  
  if (!canSubmit.value) return

  if (!isEditMode.value) {
    const containerIds = collectContainerBatchIdsForPendingAdd()
    if (!(await physicalComboWarningStore.confirmContainerMove(containerIds))) return
  }
  
  isSaving.value = true
  
  try {
    let result: MaterialBatch | AddBatchMultiResponse

    if (isEditMode.value && props.batch) {
      // Update
      const payload: UpdateBatchRequest = {}
      if (form.qty !== props.batch.qty) payload.qty = form.qty
      if (form.unit_price !== (props.batch.unit_price || '')) payload.unit_price = form.unit_price || null
      if (form.notes !== (props.batch.notes || '')) payload.notes = form.notes || null
      if (form.serial_number !== (props.batch.serial_number || '')) payload.serial_number = form.serial_number || null
      if (form.rack_id !== (props.batch.rack_id || '')) payload.rack_id = form.rack_id || null
      if (form.slot_id !== (props.batch.slot_id || '')) payload.slot_id = form.slot_id || null
      if (form.label !== ((props.batch as any).label || '')) payload.label = form.label.trim() || null
      if (form.supplier_id) payload.supplier_id = form.supplier_id
      
      result = await updateBatch(props.materialId, props.batch.id, payload)
    } else {
      // Add
      if (isSerializedAddMode.value) {
        const rows = serialRows.value.filter((e) => (e.serial_number || '').trim())
        const qty = rows.length
        const base: Pick<AddBatchRequest, 'acquired_on' | 'unit_price' | 'supplier_id' | 'notes'> = {
          acquired_on: form.acquired_on,
          unit_price: form.unit_price || null,
          supplier_id: form.supplier_id || null,
          notes: form.notes || null,
        }
        const serial_entries = rows.map((e) => ({
          serial_number: e.serial_number.trim(),
          label: (e.label || '').trim() || undefined,
        }))

        if (qty <= 1) {
          const r = rows[0]
          const payload: AddBatchRequest = {
            qty: 1,
            ...base,
            serial_numbers: [r.serial_number.trim()],
          }
          const lab = (r.label || '').trim()
          if (lab) payload.label = lab
          if (serialLocationSameForAll.value) {
            if (stockLocationMode.value === 'kiste') {
              payload.allocations = [{ qty: 1, container_batch_id: form.container_batch_id }]
            } else {
              payload.rack_id = form.rack_id || null
              payload.slot_id = form.slot_id || null
            }
          } else {
            if (r.location_mode === 'kiste') {
              payload.allocations = [{ qty: 1, container_batch_id: r.container_batch_id }]
            } else {
              payload.rack_id = r.rack_id || null
              payload.slot_id = r.slot_id || null
            }
          }
          result = await addBatch(props.materialId, payload)
        } else {
          const payload: AddBatchRequest = {
            qty,
            ...base,
            serial_entries,
          }
          if (serialLocationSameForAll.value) {
            if (stockLocationMode.value === 'kiste') {
              payload.container_batch_id = form.container_batch_id
            } else {
              payload.rack_id = form.rack_id || null
              payload.slot_id = form.slot_id || null
            }
          } else {
            payload.serial_allocations = rows.map((e) => ({
              serial_number: e.serial_number.trim(),
              ...(e.location_mode === 'kiste'
                ? { container_batch_id: e.container_batch_id || undefined }
                : { rack_id: e.rack_id || undefined, slot_id: e.slot_id || undefined }),
            }))
          }
          result = await addBatch(props.materialId, payload)
        }
      } else {
        const payload: AddBatchRequest = {
          qty: form.qty,
          acquired_on: form.acquired_on,
          unit_price: form.unit_price || null,
          supplier_id: form.supplier_id || null,
          notes: form.notes || null,
          ...(form.split_allocations && allocationRows.value.length > 0 && allocationSumValid.value
            ? {
                allocations: allocationRows.value
                  .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                  .map((r) =>
                    r.mode === 'kiste'
                      ? { container_batch_id: r.container_batch_id, qty: r.qty }
                      : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                  ),
              }
            : {
                rack_id: form.rack_id || null,
                slot_id: form.slot_id || null,
              }),
        }

        if (props.isSerialized && form.qty === 1 && form.serial_number) {
          payload.serial_numbers = [form.serial_number.trim()]
        }

        result = await addBatch(props.materialId, payload)
      }
    }

    if (!isEditMode.value) {
      const batchId = batchIdFromAddBatchResult(result)
      if (
        await enqueuePendingCostBookingAfterPurchase({
          departmentId: props.departmentId,
          totalChf: computeBatchAddPurchaseTotalChf(),
          purchaseDateIso: form.acquired_on || undefined,
          receiptHint: props.materialName ? `Charge: ${props.materialName}` : undefined,
          materialBatchId: batchId ?? null,
        })
      ) {
        toast.info(
          'Unter Buchhaltung → Buchungen, Tab „Neue Buchung zuordnen“: Kostenstelle und Details erfassen.'
        )
        headerNotificationsStore.requestRefresh()
      }
    }

    emit('saved', result)
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Speichern der Charge'
    errorMsg.value = msg
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.batch-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.15s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.batch-modal {
  background: white;
  border-radius: 12px;
  width: 520px;
  max-width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  animation: slideUp 0.2s ease;
}

.batch-modal--wide {
  width: min(920px, 96vw);
  max-width: 96vw;
}

.batch-serial-wizard .serial-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.batch-serial-wizard .serial-header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.batch-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.batch-modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.batch-modal-close {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
}

.batch-modal-close:hover {
  background: #f3f4f6;
  color: #374151;
}

.batch-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.batch-form-row {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}

.batch-form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.batch-form-group.full-width {
  flex: 1 1 100%;
}

.batch-toggle-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
}

.batch-toggle-input {
  width: 18px;
  height: 18px;
}

.batch-field-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
}

.batch-field-hint.is-invalid {
  color: #ef4444;
}

.allocations-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.allocations-header label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.add-serial-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #10b981;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
}

.add-serial-btn:hover {
  background: #059669;
}

.allocations-table-wrap,
.serial-entries-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: white;
}

.serial-entries-header {
  margin-bottom: 8px;
}

.serial-entries-header label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.serial-entries-table {
  width: 100%;
  border-collapse: collapse;
}

.serial-entries-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.serial-entries-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.serial-entries-table tr:last-child td {
  border-bottom: none;
}

.serial-entries-table .form-input--sm {
  width: 100%;
  min-width: 100px;
  padding: 6px 10px;
  font-size: 13px;
}

.allocations-table {
  width: 100%;
  border-collapse: collapse;
}

.allocations-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.allocations-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.allocations-table tr:last-child td {
  border-bottom: none;
}

.allocations-table .form-input--sm,
.allocations-table .form-select--sm {
  width: 100%;
  min-width: 80px;
  padding: 6px 10px;
  font-size: 13px;
}

.remove-row-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 4px;
}

.remove-row-btn:hover {
  color: #ef4444;
  background: #fef2f2;
}

.batch-form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.required {
  color: #ef4444;
  font-weight: 600;
}

.batch-form-input {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  transition: border-color 0.15s;
}

.batch-form-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-form-input.is-invalid {
  border-color: #ef4444;
  background: #fef2f2;
}

.batch-form-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  cursor: pointer;
}

.batch-form-select:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-form-textarea {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  resize: vertical;
  font-family: inherit;
}

.batch-form-textarea:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-price-input {
  display: flex;
  align-items: center;
  gap: 6px;
}

.batch-currency {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
  white-space: nowrap;
}

.batch-readonly-value {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  padding: 8px 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.batch-readonly-hint {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 400;
  font-style: italic;
}

.batch-error {
  background: #fef2f2;
  color: #991b1b;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  border: 1px solid #fecaca;
  margin-top: 4px;
}

.batch-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}

.batch-missing {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #d97706;
}

.batch-missing-icon {
  font-size: 14px;
}

.batch-footer-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

/* Autocomplete Lieferant */
.batch-autocomplete-wrapper {
  position: relative;
}

.batch-autocomplete-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #d1d5db;
  border-top: none;
  border-radius: 0 0 6px 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  max-height: 180px;
  overflow-y: auto;
  z-index: 50;
}

.batch-autocomplete-item {
  padding: 8px 12px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  transition: background 0.1s;
}

.batch-autocomplete-item:hover {
  background: #f3f4f6;
}

.batch-autocomplete-item.batch-ac-empty {
  color: #9ca3af;
  cursor: default;
  font-style: italic;
}

.batch-autocomplete-item.batch-ac-empty:hover {
  background: transparent;
}

.batch-ac-name {
  color: #111827;
  font-weight: 500;
}

.batch-ac-city {
  color: #9ca3af;
  font-size: 12px;
}

.batch-selected-supplier {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  font-size: 13px;
  color: #059669;
  font-weight: 500;
}

.batch-clear-btn {
  background: none;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  color: #9ca3af;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  padding: 1px 5px;
  transition: all 0.15s;
}

.batch-clear-btn:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #ef4444;
}

/* + Button neben Suchfeld */
.batch-autocomplete-wrapper {
  display: flex;
  gap: 6px;
  align-items: center;
}

.batch-autocomplete-wrapper .batch-form-input {
  flex: 1;
}

.batch-autocomplete-wrapper .batch-autocomplete-dropdown {
  left: 0;
  right: 40px;
}

.batch-add-inline-btn {
  width: 34px;
  height: 34px;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  font-size: 18px;
  font-weight: 600;
  color: #059669;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.15s;
}

.batch-add-inline-btn:hover {
  background: #ecfdf5;
  border-color: #10b981;
}

.batch-autocomplete-item.batch-ac-create {
  color: #059669;
  font-weight: 500;
  cursor: pointer;
}

.batch-autocomplete-item.batch-ac-create:hover {
  background: #ecfdf5;
}

.batch-autocomplete-item.batch-ac-create .batch-ac-name {
  color: #059669;
}
</style>
