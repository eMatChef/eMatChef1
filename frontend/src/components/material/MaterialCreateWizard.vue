<template>
  <Teleport to="body">
    <div v-if="showDialog" key="material-wizard" class="material-wizard-overlay">
      <div class="material-wizard-modal">
        <!-- Header -->
        <div class="material-wizard-header">
          <div class="material-wizard-header-title">
            <h2>{{ isAddBatchMode ? 'BESTAND HINZUFÜGEN' : 'MATERIAL ERSTELLEN' }}</h2>
            <button class="help-btn" title="Hilfe">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </button>
          </div>
          <button class="close-btn" @click="handleClose">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="material-wizard-body">
          <!-- Content -->
          <div class="material-wizard-content">
            <!-- Left: Form Steps -->
            <div ref="wizardFormRef" class="material-wizard-form">
            
            <!-- Add Batch Mode: Anzeige des ausgewählten Materials -->
            <div v-if="isAddBatchMode && selectedExistingMaterial" class="selected-material-banner">
              <div class="banner-content">
                <div class="banner-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  </svg>
                </div>
                <div class="banner-info">
                  <span class="banner-label">Bestand hinzufügen für:</span>
                  <span class="banner-name">{{ selectedExistingMaterial.name }}</span>
                  <span class="banner-details">
                    {{ selectedExistingMaterial.category?.name || 'Ohne Kategorie' }} • 
                    Aktuell: {{ selectedExistingMaterial.total_stock }} Stk.
                  </span>
                </div>
                <button type="button" class="banner-close" @click="exitAddBatchMode" title="Abbrechen">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- SCHRITT 0: Erstellmodus wählen (inline für zuverlässige Anzeige) -->
            <div v-if="shouldRenderCreationMode" class="wizard-start-block" data-step="creation_mode">
              <div class="step-header">
                <span class="step-title">Was möchtest du erstellen?</span>
              </div>
              <div class="step-content">
                <div class="creation-mode-cards">
                  <div class="creation-mode-card" @click="selectCreationMode('individual')">
                    <div class="mode-card-icon">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <span class="mode-card-title">Einzelartikel erstellen</span>
                      <span class="mode-card-desc">Eigenständiges Material anlegen oder aus Vorlage erstellen.</span>
                    </div>
                  </div>
                  <div class="creation-mode-card" @click="selectCreationMode('physical_combo')">
                    <div class="mode-card-icon physical">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <path d="M3 9h18M9 21V9"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <span class="mode-card-title">Physische Kombination</span>
                      <span class="mode-card-desc">Feste Einheit (mit oder ohne Vorlage, z.B. "Zelt Braun"). Wird als Ganzes gelagert und vermietet.</span>
                    </div>
                  </div>
                  <div class="creation-mode-card" @click="selectCreationMode('virtual_combo')">
                    <div class="mode-card-icon virtual">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <span class="mode-card-title">Virtuelle Kombination</span>
                      <span class="mode-card-desc">Planungsgruppe für Reservationen. Teile bleiben einzeln verfügbar.</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modus-Banner (wenn Modus gewählt) -->
            <SelectedModeBanner
              v-if="!isAddBatchMode && creationMode"
              :creation-mode="creationMode"
              :template-name="selectedTemplate?.name ?? null"
              :template-manufacturer="selectedTemplate?.manufacturer ?? null"
              :rack-name="selectedRackContents?.rack_name ?? null"
              @reset="resetCreationMode"
            />

            <!-- Template-Auswahl (bei allen Modi möglich, wenn noch kein Template gewählt) -->
            <TemplatePickerSection
              v-if="!isAddBatchMode && creationMode && !isFromTemplate && !isFromRackContents"
              :search="templateSearch"
              :show-dropdown="showTemplateDropdown"
              :filtered-templates="filteredTemplateList"
              :show-rack-picker="creationMode === 'physical_combo' || creationMode === 'virtual_combo'"
              :rack-id="rackContentsRackId"
              :storage-racks="storageRacks"
              :is-loading-rack-contents="isLoadingRackContents"
              :selected-rack-contents="selectedRackContents"
              @update:search="templateSearch = $event"
              @focus="showTemplateDropdown = true; searchTemplates()"
              @blur="hideTemplateDropdownDelayed"
              @select="selectTemplate"
              @update:rack-id="onRackIdChange"
              @load-rack-contents="loadRackContents"
            />

            <!-- Step: Allgemeine Informationen (sichtbar wenn Modus gewählt) -->
            <div v-if="!isAddBatchMode && creationMode" class="step-section" data-step="general">
              <div class="step-header step-header--clickable" @click="toggleStep('general')">
                <span class="step-title">{{ creationMode === 'virtual_combo' ? 'Kombination definieren' : 'Allgemeine Informationen' }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('general') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('general')" class="step-content">
                <!-- Virtuelle Kombo: Name + Reservation -->
                <div v-if="creationMode === 'virtual_combo'" class="virtual-combo-fields">
                  <MaterialNameInput
                    :model-value="formData.name"
                    label="Name der virtuellen Kombination *"
                    placeholder="z.B. Phoenix Gruppenzelt 8P, Spatz Set..."
                    :is-checking-name="isCheckingName"
                    :name-exists="nameExists"
                    :show-suggestions="false"
                    :name-suggestions="[]"
                    @update:model-value="formData.name = $event"
                    @input="checkNameDebounced"
                  />
                  <div class="form-group">
                    <label>Reservationsmodus *</label>
                    <div class="reservation-radio-options">
                      <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'complete_only' }">
                        <input type="radio" v-model="tentForm.reservation_mode" value="complete_only" />
                        <span class="radio-label">Nur komplett</span>
                        <span class="radio-desc">Nur als Ganzes reservierbar</span>
                      </label>
                      <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'individual_parts' }">
                        <input type="radio" v-model="tentForm.reservation_mode" value="individual_parts" />
                        <span class="radio-label">Einzelteile</span>
                        <span class="radio-desc">Komponenten einzeln reservierbar</span>
                      </label>
                      <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'flexible' }">
                        <input type="radio" v-model="tentForm.reservation_mode" value="flexible" />
                        <span class="radio-label">Flexibel</span>
                        <span class="radio-desc">Komplett oder Einzelteile möglich</span>
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Einzelartikel-Modus mit Vorlage: Info statt Name-Eingabe -->
                <div v-else-if="isFromTemplate && creationMode === 'individual'" class="individual-mode-info">
                  <div class="info-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"/>
                      <line x1="12" y1="16" x2="12" y2="12"/>
                      <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <p>
                      Die Artikelnamen kommen aus der Vorlage.
                      Existierende Artikel werden automatisch ergänzt.
                    </p>
                  </div>
                </div>

                <!-- Name-Eingabe (bei Kombo-Modi oder normalem Modus) -->
                <MaterialNameInput
                  v-if="creationMode !== 'virtual_combo' && (!isFromTemplate || creationMode !== 'individual')"
                  ref="articleNameInputRef"
                  :model-value="formData.name"
                  :label="isFromTemplate ? 'Name der Kombination *' : 'Artikelname'"
                  :placeholder="isFromTemplate ? 'z.B. Zelt Braun, Phoenix 8P Set...' : 'Name des Materials eingeben...'"
                  :is-checking-name="isCheckingName"
                  :name-exists="nameExists"
                  :show-suggestions="showNameSuggestions"
                  :name-suggestions="nameSuggestions"
                  @update:model-value="formData.name = $event"
                  @input="checkNameDebounced"
                  @focus="handleNameInputFocus"
                  @blur="handleNameInputBlur"
                  @select-suggestion="selectNameSuggestion"
                />

                <!-- Slider-Toggles (nicht bei Vorlage) -->
                <MaterialTypeToggles
                  v-if="!isFromTemplate"
                  :is-consumable="formData.is_consumable"
                  :is-food="formData.is_food"
                  :sale-price="formData.sale_price"
                  :min-stock="formData.min_stock"
                  @update:is-consumable="formData.is_consumable = $event"
                  @update:is-food="formData.is_food = $event"
                  @update:sale-price="formData.sale_price = $event"
                  @update:min-stock="formData.min_stock = $event"
                />
              </div>
            </div>

            <!-- Kategorie (nur bei Einzelartikel ohne Vorlage, wenn Name eingegeben) -->
            <div v-if="!isAddBatchMode && !isFromTemplate && creationMode === 'individual' && formData.name && !nameExists" class="step-section" data-step="category">
              <div class="step-header step-header--clickable" @click="toggleStep('category')">
                <span class="step-title">Kategorie</span>
                <span class="step-chevron" :class="{ open: isStepOpen('category') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('category')" class="step-content">
                <div class="form-group">
                  <label>Kategorie auswählen</label>
                  <div class="autocomplete-wrapper">
                    <input 
                      v-model="categorySearch" 
                      type="text" 
                      class="form-input"
                      placeholder="Kategorie suchen oder auswählen..."
                      @input="searchCategories"
                      @focus="showCategoryDropdown = true; searchCategories()"
                      @blur="hideCategoryDropdownDelayed"
                    />
                    <button type="button" class="add-inline-btn" @click="openAddCategoryModal" title="Neue Kategorie hinzufügen">+</button>
                    <div v-if="showCategoryDropdown && categorySearch.length >= 0" class="autocomplete-dropdown category-dropdown">
                      <div 
                        v-for="cat in filteredCategories" 
                        :key="cat.id"
                        class="autocomplete-item"
                        :class="{ 'is-child': cat.parent_id }"
                        @mousedown="selectCategory(cat)"
                      >
                        <span class="item-name">
                          <span v-if="cat.parent_id" class="cat-indent">└ </span>{{ cat.name }}
                        </span>
                        <span class="item-count">{{ cat.material_count }} Artikel</span>
                      </div>
                      <!-- Keine Ergebnisse → Neu erstellen -->
                      <div 
                        v-if="filteredCategories.length === 0 && categorySearch.length >= 2" 
                        class="autocomplete-item create-new"
                        @mousedown="openAddCategoryModal"
                      >
                        <span class="item-name">+ "{{ categorySearch }}" als Kategorie anlegen</span>
                      </div>
                    </div>
                  </div>
                  <p v-if="selectedCategory" class="selected-address">
                    ✓ {{ getCategoryPath(selectedCategory) }}
                    <button type="button" class="clear-selection" @click="clearCategory">×</button>
                  </p>
                </div>
              </div>
            </div>

            <!-- ========== TEMPLATE-MODUS: Komponenten-Eingabe ========== -->
            <div v-if="((isFromTemplate && selectedTemplate) || (isFromRackContents && selectedRackContents)) && creationMode && (creationMode === 'individual' || (formData.name && !nameExists))" class="step-section" data-step="template_components">
              <div class="step-header step-header--clickable" @click="toggleStep('template_components')">
                <span class="step-title">Komponenten</span>
                <span class="step-badge">{{ componentInputs.length }} Teile</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_components') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_components')" class="step-content">
                <div class="component-inputs-list">
                  <div
                    v-for="(ci, idx) in componentInputs"
                    :key="ci.component_type + idx"
                    class="component-input-card"
                    :class="{ 'is-optional': ci.is_optional }"
                  >
                    <div class="comp-card-header">
                      <div class="comp-card-info">
                        <span class="comp-card-name">{{ ci.name }}</span>
                        <span class="comp-card-meta">
                          <span v-if="ci.tracking === 'serialized'">Seriennummer</span>
                          <span v-else>{{ ci.required_qty }}x Stück</span>
                          <span v-if="ci.is_optional" class="comp-optional-badge">Optional</span>
                        </span>
                      </div>
                      <!-- Virtuelle Kombo: Keine Auswahl nötig, wird bei Ausgabe zugewiesen -->
                      <div v-if="creationMode === 'virtual_combo'" class="comp-card-mode">
                        <span class="comp-mode-info">Bei Ausgabe zuweisen</span>
                      </div>
                      <!-- Andere Modi: Neu/Bestand Toggle -->
                      <div v-else class="comp-card-mode">
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: ci.mode === 'new' }]"
                          @click="ci.mode = 'new'"
                        >Neu kaufen</button>
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: ci.mode === 'existing' }]"
                          @click="ci.mode = 'existing'"
                        >Aus Lager</button>
                      </div>
                    </div>

                    <div class="comp-card-body">

                      <!-- Virtual Combo: Nur Info, keine Eingabe -->
                      <div v-if="creationMode === 'virtual_combo'" class="comp-virtual-info">
                        <span class="comp-virtual-text">Wird bei Ausgabe zugewiesen</span>
                      </div>

                      <!-- ══════ SERIALISIERT ══════ -->

                      <!-- Serialisiert: Neu kaufen → SN + Preis eingeben -->
                      <template v-if="creationMode !== 'virtual_combo' && ci.tracking === 'serialized' && ci.mode === 'new'">
                        <div class="form-row">
                          <div class="form-group">
                            <label>Seriennummer</label>
                            <input
                              v-model="ci.serial_number"
                              type="text"
                              class="form-input"
                              :placeholder="'z.B. ' + ci.component_type.toUpperCase().substring(0, 3) + '-001'"
                            />
                          </div>
                          <div class="form-group">
                            <label>Stückpreis (CHF)</label>
                            <div class="price-input">
                              <span class="currency">Fr.</span>
                              <input
                                v-model="ci.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                placeholder="0.00"
                              />
                            </div>
                          </div>
                        </div>
                      </template>

                      <!-- Serialisiert: Aus Lager → Material suchen + SN wählen -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'serialized' && ci.mode === 'existing'">
                        <div class="comp-existing-search">
                          <div class="form-group">
                            <label>Artikel suchen</label>
                            <div class="autocomplete-wrapper">
                              <input
                                v-model="ci._materialSearch"
                                type="text"
                                class="form-input"
                                :placeholder="'z.B. ' + ci.name + '...'"
                                @input="searchExistingMaterial(ci)"
                                @focus="ci._showDropdown = true"
                                @blur="hideCompDropdownDelayed(ci)"
                              />
                              <div v-if="ci._showDropdown && ci._filteredMaterials?.length > 0" class="autocomplete-dropdown">
                                <div
                                  v-for="mat in ci._filteredMaterials"
                                  :key="mat.id"
                                  class="autocomplete-item"
                                  @mousedown="selectExistingMaterial(ci, mat)"
                                >
                                  <span class="item-name">{{ mat.name }}</span>
                                  <span class="item-count">{{ mat.free_stock ?? mat.total_stock }} frei</span>
                                </div>
                              </div>
                            </div>
                          </div>

                          <!-- Gewähltes Material + SN-Auswahl -->
                          <div v-if="ci.material_id && ci._selectedMaterial" class="comp-selected-material">
                            <div class="comp-selected-header">
                              <span class="comp-selected-check">✓</span>
                              <span class="comp-selected-name">{{ ci._selectedMaterial.name }}</span>
                              <button type="button" class="clear-selection" @click="clearExistingMaterial(ci)">×</button>
                            </div>

                            <div v-if="ci._availableBatches?.length > 0" class="comp-batch-select">
                              <label>Seriennummer wählen</label>
                              <select v-model="ci.batch_id" class="form-select">
                                <option value="">– SN auswählen –</option>
                                <option
                                  v-for="batch in ci._availableBatches"
                                  :key="batch.id"
                                  :value="batch.id"
                                >
                                  SN: {{ batch.serial_number || batch.label || batch.id }}
                                </option>
                              </select>
                            </div>
                            <div v-else class="comp-no-batches">
                              Keine freien Seriennummern im Lager
                            </div>
                          </div>
                        </div>
                      </template>

                      <!-- ══════ BULK (Massenartikel) ══════ -->

                      <!-- Bulk: Neu kaufen → Menge + Preis -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'bulk' && ci.mode === 'new'">
                        <div class="form-row">
                          <div class="form-group">
                            <label>Menge (neu kaufen)</label>
                            <input
                              v-model.number="ci.qty"
                              type="number"
                              min="1"
                              class="form-input"
                            />
                          </div>
                          <div class="form-group">
                            <label>Stückpreis (CHF)</label>
                            <div class="price-input">
                              <span class="currency">Fr.</span>
                              <input
                                v-model="ci.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                placeholder="0.00"
                              />
                            </div>
                          </div>
                        </div>
                        <p class="comp-bulk-info">Neuer Bestand wird zum Lager hinzugefügt und dem Zelt zugewiesen.</p>
                      </template>

                      <!-- Bulk: Aus Lager → Material wählen + Menge, kein Batch nötig -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'bulk' && ci.mode === 'existing'">
                        <div class="comp-bulk-existing">
                          <!-- Material auto-gefunden oder manuell suchen -->
                          <div v-if="!ci._selectedMaterial" class="form-group">
                            <label>Welcher Artikel aus dem Lager?</label>
                            <div class="autocomplete-wrapper">
                              <input
                                v-model="ci._materialSearch"
                                type="text"
                                class="form-input"
                                :placeholder="ci.name + ' suchen...'"
                                @input="searchExistingMaterial(ci)"
                                @focus="ci._showDropdown = true; autoSearchBulk(ci)"
                                @blur="hideCompDropdownDelayed(ci)"
                              />
                              <div v-if="ci._showDropdown && ci._filteredMaterials?.length > 0" class="autocomplete-dropdown">
                                <div
                                  v-for="mat in ci._filteredMaterials"
                                  :key="mat.id"
                                  class="autocomplete-item"
                                  @mousedown="selectBulkMaterial(ci, mat)"
                                >
                                  <span class="item-name">{{ mat.name }}</span>
                                  <span class="item-count">{{ mat.free_stock ?? mat.total_stock }} frei</span>
                                </div>
                              </div>
                            </div>
                          </div>

                          <!-- Material gewählt → Menge + Bestand anzeigen -->
                          <div v-else>
                            <div class="comp-selected-material">
                              <div class="comp-selected-header">
                                <span class="comp-selected-check">✓</span>
                                <span class="comp-selected-name">{{ ci._selectedMaterial.name }}</span>
                                <span class="comp-selected-stock">{{ ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock }} frei</span>
                                <button type="button" class="clear-selection" @click="clearExistingMaterial(ci)">×</button>
                              </div>
                            </div>
                            <div class="form-row" style="margin-top: 8px;">
                              <div class="form-group">
                                <label>Menge zuweisen</label>
                                <input
                                  v-model.number="ci.qty"
                                  type="number"
                                  min="1"
                                  class="form-input"
                                />
                              </div>
                              <div class="form-group comp-stock-info">
                                <label>&nbsp;</label>
                                <div class="comp-stock-display">
                                  <span class="comp-stock-value" :class="{ 'is-low': (ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock) < ci.qty }">
                                    {{ ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock }} frei / {{ ci.qty }} benötigt
                                  </span>
                                </div>
                              </div>
                            </div>
                            <p v-if="(ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock) < ci.qty" class="comp-stock-warning">
                              Nicht genug frei – {{ ci.qty - (ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock) }} Stk. fehlen!
                            </p>
                            <p v-else class="comp-bulk-info">Wird aus dem vorhandenen Lagerbestand dem Zelt zugewiesen.</p>
                          </div>
                        </div>
                      </template>

                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ========== TEMPLATE-MODUS: Zelt-Details (nur bei Kombo-Modi) ========== -->
            <div v-if="isFromTemplate && selectedTemplate && creationMode && creationMode !== 'individual' && formData.name && !nameExists" class="step-section" data-step="template_tent">
              <div class="step-header step-header--clickable" @click="toggleStep('template_tent')">
                <span class="step-title">Zelt-Details</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_tent') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_tent')" class="step-content">
                <div class="form-row">
                  <div class="form-group">
                    <label>Zelt-Typ</label>
                    <select v-model="tentForm.tent_type" class="form-select">
                      <option value="">– wählen –</option>
                      <option value="gruppenzelt">Gruppenzelt</option>
                      <option value="sonstiges">Sonstiges</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Kapazität (Personen)</label>
                    <input
                      v-model.number="tentForm.tent_capacity"
                      type="number"
                      min="1"
                      class="form-input"
                      placeholder="z.B. 6"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label>Reservation</label>
                  <div class="reservation-options">
                    <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'complete_only' }">
                      <input type="radio" v-model="tentForm.reservation_mode" value="complete_only" />
                      <div class="radio-text">
                        <span class="radio-name">Nur komplett</span>
                        <span class="radio-desc">Zelt kann nur als Ganzes reserviert werden</span>
                      </div>
                    </label>
                    <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'individual' }">
                      <input type="radio" v-model="tentForm.reservation_mode" value="individual" />
                      <div class="radio-text">
                        <span class="radio-name">Einzelteile</span>
                        <span class="radio-desc">Komponenten können einzeln reserviert werden</span>
                      </div>
                    </label>
                    <label class="radio-option" :class="{ active: tentForm.reservation_mode === 'flexible' }">
                      <input type="radio" v-model="tentForm.reservation_mode" value="flexible" />
                      <div class="radio-text">
                        <span class="radio-name">Flexibel</span>
                        <span class="radio-desc">Komplett oder Einzelteile, je nach Bedarf</span>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- ========== TEMPLATE-MODUS: Kauf-Details ========== -->
            <div v-if="isFromTemplate && selectedTemplate && creationMode && (creationMode === 'individual' || (formData.name && !nameExists))" class="step-section" data-step="template_purchase">
              <div class="step-header step-header--clickable" @click="toggleStep('template_purchase')">
                <span class="step-title">Kauf &amp; Lagerung</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_purchase') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_purchase')" class="step-content">
                <div class="form-group">
                  <label>Lagerstandort</label>
                  <div class="select-with-add">
                    <select v-model="formData.storage_address_id" class="form-select">
                      <option value="">– Lagerstandort wählen –</option>
                      <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                        {{ addr.name || addr.street_line }}
                      </option>
                    </select>
                    <button type="button" class="add-btn" @click="openAddStorageModal" title="Neuen Lagerort hinzufügen">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                      </svg>
                    </button>
                  </div>
                  <div class="autocomplete-wrapper">
                    <input
                      v-model="formData.location_rack"
                      type="text"
                      class="form-input"
                      placeholder="Gestell (z.B. Holzgestell)"
                      @input="searchRackCategories"
                      @focus="showRackDropdown = true; searchRackCategories()"
                      @blur="hideRackDropdownDelayed"
                    />
                    <button type="button" class="add-inline-btn" @click="addRackCategory" title="Gestell-Kategorie unter Standort hinzufügen">+</button>
                    <div v-if="showRackDropdown" class="autocomplete-dropdown">
                      <div
                        v-for="rack in filteredRackOptions"
                        :key="rack.id"
                        class="autocomplete-item"
                        @mousedown="selectRackCategory(rack)"
                      >
                        <span class="item-name">{{ rack.name }}</span>
                      </div>
                      <div
                        v-if="filteredRackOptions.length === 0 && formData.location_rack.trim().length >= 2"
                        class="autocomplete-item create-new"
                        @mousedown="addRackCategory"
                      >
                        <span class="item-name">+ "{{ formData.location_rack.trim() }}" als Gestell anlegen</span>
                      </div>
                    </div>
                  </div>
                  <input
                    v-model="formData.location_slot"
                    type="text"
                    class="form-input"
                    placeholder="Platz/Fach (z.B. B3)"
                  />
                  <p class="field-hint">Optional: strukturierter Lagerplatz pro Start-Batch (Gestell + Platz/Fach)</p>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label>Kaufdatum</label>
                    <input
                      v-model="formData.purchase_date"
                      type="date"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group">
                    <label>Rechnungsnummer</label>
                    <input
                      v-model="formData.invoice_number"
                      type="text"
                      class="form-input"
                      placeholder="Optional"
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label>Hersteller</label>
                    <div class="autocomplete-wrapper">
                      <input
                        v-model="manufacturerSearch"
                        type="text"
                        class="form-input"
                        placeholder="Hersteller suchen..."
                        @input="searchManufacturers"
                        @focus="showManufacturerDropdown = true"
                        @blur="hideManufacturerDropdownDelayed"
                      />
                      <div v-if="showManufacturerDropdown && manufacturerSearch.length >= 2" class="autocomplete-dropdown">
                        <div
                          v-for="addr in filteredManufacturers"
                          :key="addr.id"
                          class="autocomplete-item"
                          @mousedown="selectManufacturer(addr)"
                        >
                          <span class="item-name">{{ addr.name || addr.company }}</span>
                          <span class="item-city">{{ addr.city }}</span>
                        </div>
                      </div>
                    </div>
                    <p v-if="selectedManufacturer" class="selected-address">
                      {{ selectedManufacturer.name || selectedManufacturer.company }}
                      <button type="button" class="clear-selection" @click="clearManufacturer">×</button>
                    </p>
                  </div>
                  <div class="form-group">
                    <label>Gekauft von</label>
                    <div class="autocomplete-wrapper">
                      <input
                        v-model="supplierSearch"
                        type="text"
                        class="form-input"
                        placeholder="Lieferant suchen..."
                        @input="searchSuppliers"
                        @focus="showSupplierDropdown = true"
                        @blur="hideSupplierDropdownDelayed"
                      />
                      <div v-if="showSupplierDropdown && supplierSearch.length >= 2" class="autocomplete-dropdown">
                        <div
                          v-for="addr in filteredSuppliers"
                          :key="addr.id"
                          class="autocomplete-item"
                          @mousedown="selectSupplier(addr)"
                        >
                          <span class="item-name">{{ addr.name || addr.company }}</span>
                          <span class="item-city">{{ addr.city }}</span>
                        </div>
                      </div>
                    </div>
                    <p v-if="selectedSupplier" class="selected-address">
                      {{ selectedSupplier.name || selectedSupplier.company }}
                      <button type="button" class="clear-selection" @click="clearSupplier">×</button>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Material-Typ (nur bei Einzelartikel ohne Vorlage, wenn Kategorie gewählt) -->
            <div v-if="!isAddBatchMode && !isFromTemplate && creationMode === 'individual' && !formData.is_food && formData.name && !nameExists && formData.category_id" class="step-section" data-step="material_type">
              <div class="step-header step-header--clickable" @click="toggleStep('material_type')">
                <span class="step-title">Was für ein Material ist das?</span>
                <span class="step-chevron" :class="{ open: isStepOpen('material_type') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('material_type')" class="step-content">
                <div class="type-options">
                  <button 
                    :class="['type-option', { active: formData.material_type === 'physical' }]"
                    @click="selectMaterialType('physical')"
                  >
                    <div class="type-icon physical">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                      </svg>
                    </div>
                    <div class="type-text">
                      <span class="type-name">Physischer Artikel</span>
                      <span class="type-desc">Individuelles, eigenständiges Material</span>
                    </div>
                  </button>

                  <button 
                    :class="['type-option', { active: formData.material_type === 'physical_combo' }]"
                    @click="selectMaterialType('physical_combo')"
                  >
                    <div class="type-icon combo">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                      </svg>
                    </div>
                    <div class="type-text">
                      <span class="type-name">Physische Kombination</span>
                      <span class="type-desc">Kiste mit Inhalt, zusammen vermietet</span>
                    </div>
                  </button>

                  <button 
                    :class="['type-option', { active: formData.material_type === 'virtual_combo' }]"
                    @click="selectMaterialType('virtual_combo')"
                  >
                    <div class="type-icon virtual">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                      </svg>
                    </div>
                    <div class="type-text">
                      <span class="type-name">Virtuelle Kombination</span>
                      <span class="type-desc">Für Planung zusammengefasst, nicht physisch gelagert</span>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- Bestandsverfolgung (nur bei physical Einzelartikel ohne Vorlage) -->
            <div v-if="!isAddBatchMode && !isFromTemplate && creationMode === 'individual' && formData.material_type === 'physical' && !formData.is_food" class="step-section" data-step="tracking">
              <div class="step-header step-header--clickable" @click="toggleStep('tracking')">
                <span class="step-title">Wie wird der Lagerbestand verfolgt?</span>
                <span class="step-chevron" :class="{ open: isStepOpen('tracking') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('tracking')" class="step-content">
                <div class="tracking-options">
                  <button 
                    :class="['tracking-option', { active: formData.tracking_type === 'serialized' }]"
                    :disabled="formData.is_food"
                    @click="!formData.is_food && (formData.tracking_type = 'serialized')"
                  >
                    <div class="tracking-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                      </svg>
                    </div>
                    <div class="tracking-text">
                      <span class="tracking-name">Serialisiert</span>
                      <span class="tracking-desc">
                        {{ formData.is_food ? 'Für Esswaren deaktiviert' : 'Einzeln verfolgen (z.B. mit Seriennummern)' }}
                      </span>
                    </div>
                  </button>

                  <button 
                    :class="['tracking-option', { active: formData.tracking_type === 'bulk' }]"
                    @click="formData.tracking_type = 'bulk'"
                  >
                    <div class="tracking-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                      </svg>
                    </div>
                    <div class="tracking-text">
                      <span class="tracking-name">Massenartikel</span>
                      <span class="tracking-desc">Nach Gesamtmenge verfolgen</span>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- Step 4: Kombinations-Artikel (bei physical_combo oder virtual_combo, NICHT im Batch-Modus, NICHT Template) -->
            <div v-if="!isAddBatchMode && !isFromTemplate && (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') && formData.name && !nameExists" class="step-section" data-step="combo_articles">
              <div class="step-header step-header--clickable" @click="toggleStep('combo_articles')">
                <span class="step-title">Welche Artikel enthält diese Kombination?</span>
                <span class="step-chevron" :class="{ open: isStepOpen('combo_articles') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('combo_articles')" class="step-content">
                <p class="step-hint">Mindestens 2 Artikel hinzufügen</p>
                
                <!-- Material-Suche -->
                <div class="combo-search">
                  <input 
                    v-model="comboMaterialSearch" 
                    type="text" 
                    class="form-input"
                    placeholder="Material suchen (min. 3 Zeichen)..."
                    @input="searchComboMaterials"
                  />
                  <div v-if="comboMaterialSearch.length >= 3 && filteredComboMaterials.length > 0" class="combo-dropdown">
                    <div 
                      v-for="mat in filteredComboMaterials" 
                      :key="mat.id"
                      class="combo-item"
                      @click="addComboMaterial(mat)"
                    >
                      <div class="combo-item-info">
                        <span class="combo-item-name">{{ mat.name }}</span>
                        <span class="combo-item-cat">{{ mat.category?.name || 'Ohne Kategorie' }}</span>
                      </div>
                      <span class="combo-item-stock">{{ mat.total_stock }} Stk.</span>
                    </div>
                  </div>
                  <div v-else-if="comboMaterialSearch.length >= 3 && filteredComboMaterials.length === 0" class="combo-dropdown">
                    <div class="combo-empty">Keine Materialien gefunden</div>
                  </div>
                </div>

                <!-- Hinzugefügte Materialien -->
                <div v-if="selectedComboMaterials.length > 0" class="combo-list">
                  <div 
                    v-for="(mat, index) in selectedComboMaterials" 
                    :key="mat.id"
                    class="combo-list-item"
                  >
                    <span class="combo-list-num">{{ index + 1 }}.</span>
                    <div class="combo-list-info">
                      <span class="combo-list-name">{{ mat.name }}</span>
                      <span class="combo-list-cat">{{ mat.category?.name || '' }}</span>
                    </div>
                    <div class="combo-list-qty">
                      <label>Menge:</label>
                      <input 
                        v-model.number="mat.qty" 
                        type="number" 
                        min="1" 
                        class="qty-input"
                      />
                    </div>
                    <button type="button" class="combo-remove" @click="removeComboMaterial(index)">×</button>
                  </div>
                </div>
                <p v-else class="combo-empty-hint">Noch keine Artikel hinzugefügt</p>

                <p v-if="selectedComboMaterials.length > 0 && selectedComboMaterials.length < 2" class="combo-warning">
                  ⚠️ Mindestens 2 Artikel erforderlich
                </p>
              </div>
            </div>

            <!-- Details & Vermietung wird unter Initialer Bestand angezeigt -->

            <!-- Batch Formular: Im Batch-Modus ODER bei physical Einzelartikel ohne Vorlage mit tracking -->
            <div v-if="isAddBatchMode || (!isFromTemplate && creationMode === 'individual' && formData.material_type === 'physical' && formData.tracking_type)" class="step-section" data-step="stock">
              <div class="step-header step-header--clickable" @click="toggleStep('stock')">
                <span class="step-title">{{ isAddBatchMode ? 'Neuer Bestand' : 'Initialer Bestand' }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('stock') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('stock')" class="step-content">
                <div class="batch-form">
                  <div v-if="formData.tracking_type === 'bulk' || isAddBatchMode" class="form-row mb-2">
                    <div class="form-group">
                      <label>Menge</label>
                      <input
                        v-model.number="formData.initial_qty"
                        type="number"
                        min="0"
                        class="form-input"
                        placeholder="0"
                      />
                    </div>
                    <div class="form-group">
                      <label>Kaufdatum <span v-if="!formData.is_food" class="required">*</span></label>
                      <input
                        v-model="formData.purchase_date"
                        type="date"
                        class="form-input"
                        :class="{ 'is-invalid': !formData.is_food && !formData.purchase_date && formData.initial_qty > 0 }"
                        :required="!formData.is_food"
                      />
                    </div>
                    <div v-if="formData.is_food || showExpiryDateForNonFood" class="form-group">
                      <label>Ablaufdatum <span v-if="formData.is_food" class="required">*</span></label>
                      <input
                        v-model="formData.expiry_date"
                        type="date"
                        class="form-input"
                        :class="{ 'is-invalid': formData.is_food && !formData.expiry_date && formData.initial_qty > 0 }"
                      />
                    </div>
                  </div>

                  <div v-if="formData.tracking_type === 'bulk' || isAddBatchMode" class="form-row mb-2">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="formData.split_allocations" class="toggle-input" :disabled="!stockInputReady" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">Auf mehrere Lagerplätze aufteilen</span>
                        <span class="toggle-desc">Menge auf verschiedene Gestelle/Fächer verteilen</span>
                      </span>
                    </label>
                  </div>
                  <p v-if="(formData.tracking_type === 'bulk' || isAddBatchMode) && !stockInputReady" class="field-hint">
                    Zuerst Menge erfassen, danach Lagerplätze zuweisen.
                  </p>

                  <div v-if="formData.tracking_type === 'serialized'" class="form-row mb-2">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="serialLocationSameForAll" class="toggle-input" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">Für alle den gleichen Lagerplatz</span>
                        <span class="toggle-desc">Bei Nein wird der Standort direkt pro Zeile in der Tabelle gewählt</span>
                      </span>
                    </label>
                  </div>

                  <div v-if="formData.tracking_type === 'serialized' || stockInputReady" class="form-group">
                    <template v-if="!(formData.tracking_type === 'serialized' && !serialLocationSameForAll)">
                    <template v-if="!((formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations)">
                      <label>Lagerstandort</label>
                      <div class="select-with-add">
                        <select v-model="formData.storage_address_id" class="form-select">
                          <option value="">– Lagerstandort wählen –</option>
                          <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                            {{ addr.name || addr.street_line }}
                          </option>
                        </select>
                        <button type="button" class="add-btn" @click="openAddStorageModal" title="Neuen Lagerort hinzufügen">
                          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                          </svg>
                        </button>
                      </div>
                    </template>

                  <!-- Allokations-Tabelle (Bulk, wenn Aufteilung aktiv) -->
                  <div v-if="(formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations" class="allocations-section">
                    <div class="allocations-header">
                      <label>Lagerplätze (Summe = {{ formData.initial_qty }} Stk.)</label>
                      <button type="button" class="add-serial-btn" :disabled="!canAddAllocationRow" @click="addAllocationRow">
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
                          <tr v-for="row in initialAllocations" :key="row.id">
                            <td>
                              <input
                                v-model.number="row.qty"
                                type="number"
                                min="1"
                                class="form-input form-input--sm"
                                placeholder="0"
                              />
                            </td>
                            <td>
                              <select v-model="row.mode" class="form-select form-select--sm" @change="row.rack_id = ''; row.slot_id = ''; row.container_batch_id = ''">
                                <option value="slot">Fach</option>
                                <option value="kiste">Kiste</option>
                              </select>
                            </td>
                            <td>
                              <template v-if="row.mode === 'slot'">
                                <label class="form-label-sm">Lagerstandort</label>
                                <select
                                  v-model="row.storage_address_id"
                                  class="form-select form-select--sm"
                                  @change="onAllocationStorageAddressChange(row)"
                                >
                                  <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                                    {{ addr.name || addr.street_line }}
                                  </option>
                                </select>
                                <label class="form-label-sm">Gestell</label>
                                <select
                                  v-model="row.rack_id"
                                  class="form-select form-select--sm"
                                  @change="row.slot_id = ''; loadSlotsForRack(row.rack_id)"
                                  @mouseenter="prefetchRackPreview(row.rack_id)"
                                  :title="getRackPreviewTitle(row.rack_id)"
                                >
                                  <option value="" disabled>– Gestell –</option>
                                  <option
                                    v-for="r in getRacksForAllocationRow(row)"
                                    :key="r.id"
                                    :value="r.id"
                                    :title="getRackPreviewTitle(r.id)"
                                  >
                                    {{ r.name }}
                                  </option>
                                </select>
                                <label class="form-label-sm">Fach</label>
                                <select
                                  v-model="row.slot_id"
                                  class="form-select form-select--sm"
                                  :disabled="!row.rack_id"
                                  @mouseenter="prefetchSlotPreview(row.rack_id, row.slot_id)"
                                  :title="getSlotPreviewTitle(row.rack_id, row.slot_id)"
                                >
                                  <option value="" disabled>– Fach wählen –</option>
                                  <option
                                    v-for="s in (row.rack_id ? (slotsByRackId[row.rack_id] || []) : [])"
                                    :key="s.id"
                                    :value="s.id"
                                    :title="getSlotPreviewTitle(row.rack_id, s.id)"
                                  >
                                    {{ formatSlotOptionLabel(row.rack_id, s) }}
                                  </option>
                                </select>
                              </template>
                              <template v-else>
                                <label class="form-label-sm">Kiste/Tasche</label>
                                <select
                                  v-model="row.container_batch_id"
                                  class="form-select form-select--sm"
                                  @mouseenter="prefetchContainerPreviews()"
                                  :title="getContainerPreviewTitle(row.container_batch_id)"
                                >
                                  <option value="">– Kiste wählen –</option>
                                  <option
                                    v-for="cb in containerBatches"
                                    :key="cb.id"
                                    :value="cb.id"
                                    :title="getContainerPreviewTitle(cb.id)"
                                  >
                                    {{ formatContainerBatchOption(cb) }}
                                  </option>
                                </select>
                              </template>
                            </td>
                            <td>
                              <button type="button" class="remove-row-btn" @click="removeAllocationRow(row.id)" title="Entfernen">×</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p v-if="initialAllocations.length > 0 && !allocationSumValid" class="field-hint is-invalid">
                      Summe muss {{ formData.initial_qty }} Stk. ergeben (aktuell: {{ allocationSum }})
                    </p>
                  </div>

                  <!-- Einzelner Lagerplatz oder Kiste (wenn keine Aufteilung) -->
                  <div v-if="!((formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations)" class="form-group">
                    <div class="stock-location-mode mb-2">
                      <label class="form-label-sm">Hauptlagerplatz</label>
                      <div class="lagerung-switch" role="tablist">
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'slot' }"
                          @click="formData.stock_location_mode = 'slot'; formData.stock_container_batch_id = ''"
                        >
                          Gestell/Fach
                        </button>
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'kiste' }"
                          @click="formData.stock_location_mode = 'kiste'; formData.rack_id = ''; formData.slot_id = ''; formData.location_rack = ''; formData.location_slot = ''"
                        >
                          Kiste/Tasche
                        </button>
                      </div>
                    </div>
                    <template v-if="formData.stock_location_mode === 'slot'">
                      <StorageLocationPicker
                        :rack-id="formData.rack_id"
                        :slot-id="formData.slot_id"
                        :racks="storageRacks"
                        :slots="storageSlots"
                        rack-label="Gestell"
                        slot-label="Fach"
                        rack-placeholder="– Gestell wählen –"
                        slot-placeholder="– Fach wählen –"
                        @update:rackId="formData.rack_id = $event"
                        @rackChange="onStockRackChange"
                        @update:slotId="formData.slot_id = $event"
                      />
                    </template>
                    <template v-else>
                      <select
                        v-model="formData.stock_container_batch_id"
                        class="form-select"
                        @mouseenter="prefetchContainerPreviews()"
                        :title="getContainerPreviewTitle(formData.stock_container_batch_id)"
                      >
                        <option value="">– Kiste wählen –</option>
                        <option
                          v-for="cb in containerBatches"
                          :key="cb.id"
                          :value="cb.id"
                          :title="getContainerPreviewTitle(cb.id)"
                        >
                          {{ formatContainerBatchOption(cb) }}
                        </option>
                      </select>
                    </template>
                  </div>
                    </template>

                  <div v-if="!formData.is_food && formData.tracking_type !== 'serialized'" class="slider-toggle-group pack-toggle-inline">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="showExpiryDateForNonFood" class="toggle-input" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">Ablaufdatum anzeigen</span>
                        <span class="toggle-desc">Optional fuer Nicht-Esswaren (z.B. Haltbarkeit, Prueffrist)</span>
                      </span>
                    </label>
                  </div>

                  <!-- Serialisiert: Seriennummer-Tabelle -->
                  <div v-if="formData.tracking_type === 'serialized'" class="serial-numbers-section">
                    <div class="serial-header">
                      <label>Seriennummern ({{ serializedQty }} Stk.)</label>
                      <div style="display:flex; gap:8px; align-items:center;">
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
                          <input type="checkbox" v-model="serialAutoGenerateEnabled" class="toggle-input" />
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
                    
                    <div class="serial-list" v-if="serialNumbers.length > 0">
                      <div
                        v-for="(entry, index) in serialNumbers"
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
                          <select v-model="entry.location_mode" class="form-select form-select--sm" @change="entry.rack_id=''; entry.slot_id=''; entry.container_batch_id=''">
                            <option value="slot">Gestell/Fach</option>
                            <option value="kiste">Kiste/Tasche</option>
                          </select>
                        </div>

                        <div v-if="!serialLocationSameForAll" class="serial-block serial-block--location">
                          <div class="serial-location-cell">
                            <template v-if="entry.location_mode === 'slot'">
                              <label class="form-label-sm">Lagerstandort</label>
                              <select v-model="entry.storage_address_id" class="form-select form-select--sm" @change="onSerialEntryStorageAddressChange(entry)">
                                <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">{{ addr.name || addr.street_line }}</option>
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
                                  :value="rack.id"
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
                                  v-for="slot in (entry.rack_id ? (slotsByRackId[entry.rack_id] || []) : [])"
                                  :key="slot.id"
                                  :value="slot.id"
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
                                <option value="">– Kiste waehlen –</option>
                                <option
                                  v-for="cb in containerBatches"
                                  :key="cb.id"
                                  :value="cb.id"
                                  :title="getContainerPreviewTitle(cb.id)"
                                >
                                  {{ formatContainerBatchOption(cb) }}
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
                            style="margin-right:6px;"
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
                      <button type="button" class="add-first-btn" @click="addSerialNumber">
                        + Erste Seriennummer hinzufügen
                      </button>
                    </div>
                    <p v-if="!serialLocationSameForAll && hasInvalidSerialLocations" class="field-hint is-invalid">
                      Bitte pro Seriennummer einen gueltigen Standort (Gestell/Fach oder Kiste) waehlen.
                    </p>
                    <p v-if="serialDuplicateHint" class="field-hint is-invalid">
                      {{ serialDuplicateHint }}
                    </p>
                  </div>

                  <!-- Massenartikel: Normale Mengen-Eingabe -->
                  <div v-else>
                    <!-- Verpackungseinheit – direkt bei der Menge -->
                    <div class="slider-toggle-group pack-toggle-inline">
                      <label class="toggle-label">
                        <span class="toggle-wrapper">
                          <input type="checkbox" v-model="packUnitEnabled" class="toggle-input" />
                          <span class="toggle-slider toggle-slider--blue"></span>
                        </span>
                        <span class="toggle-text">
                          <span class="toggle-title">Verpackungseinheit</span>
                          <span class="toggle-desc">In Bündeln, Kisten oder Rollen gelagert</span>
                        </span>
                      </label>
                      <transition name="slide-down">
                        <div v-if="packUnitEnabled" class="slider-details pack-details">
                          <div class="form-row">
                            <div class="form-group">
                              <label>Stück pro Einheit</label>
                              <input v-model.number="formData.pack_size" type="number" min="2" class="form-input" placeholder="z.B. 10" />
                            </div>
                            <div class="form-group">
                              <label>Bezeichnung</label>
                              <select v-model="formData.pack_unit" class="form-input">
                                <option value="">– wählen –</option>
                                <option value="Bündel">Bündel</option>
                                <option value="Kiste">Kiste</option>
                                <option value="Karton">Karton</option>
                                <option value="Sack">Sack</option>
                                <option value="Rolle">Rolle</option>
                                <option value="Palette">Palette</option>
                                <option value="Set">Set</option>
                                <option value="Paket">Paket</option>
                              </select>
                            </div>
                          </div>
                          <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                            Beispiel: {{ formData.initial_qty || 80 }} Stk. = {{ Math.floor((formData.initial_qty || 80) / formData.pack_size) }} {{ formData.pack_unit }} à {{ formData.pack_size }} Stk.
                            <span v-if="(formData.initial_qty || 80) % formData.pack_size !== 0"> + {{ (formData.initial_qty || 80) % formData.pack_size }} Stk.</span>
                          </p>
                        </div>
                      </transition>
                    </div>
                  </div>

                  <!-- Gemeinsame Felder (Kaufdatum bei Serialisiert) -->
                  <div v-if="formData.tracking_type === 'serialized'" class="form-row mt-3">
                    <div class="form-group">
                      <label>Kaufdatum <span v-if="!formData.is_food" class="required">*</span></label>
                      <input 
                        v-model="formData.purchase_date" 
                        type="date" 
                        class="form-input"
                        :class="{ 'is-invalid': !formData.is_food && !formData.purchase_date && serializedQty > 0 }"
                        :required="!formData.is_food"
                      />
                    </div>
                    <div class="form-group">
                      <label>Stückpreis (CHF)</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input 
                          v-model.number="formData.unit_price" 
                          type="number" 
                          step="0.01"
                          min="0"
                          class="form-input"
                          placeholder="0.00"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>Hersteller</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="manufacturerSearch" 
                          type="text" 
                          class="form-input"
                          placeholder="Hersteller suchen..."
                          @input="searchManufacturers"
                          @focus="showManufacturerDropdown = true"
                          @blur="hideManufacturerDropdownDelayed"
                        />
                        <button type="button" class="add-inline-btn" @click="openAddManufacturerModal" title="Neuen Hersteller hinzufügen">+</button>
                        <div v-if="showManufacturerDropdown && manufacturerSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredManufacturers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectManufacturer(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <!-- Keine Ergebnisse → Neu erstellen -->
                          <div 
                            v-if="filteredManufacturers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddManufacturerModal"
                          >
                            <span class="item-name">+ "{{ manufacturerSearch }}" als Hersteller anlegen</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedManufacturer" class="selected-address">
                        ✓ {{ selectedManufacturer.name || selectedManufacturer.company }}
                        <button type="button" class="clear-selection" @click="clearManufacturer">×</button>
                      </p>
                    </div>
                    <div class="form-group">
                      <label>Gekauft von</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="supplierSearch" 
                          type="text" 
                          class="form-input"
                          placeholder="Lieferant suchen..."
                          @input="searchSuppliers"
                          @focus="showSupplierDropdown = true"
                          @blur="hideSupplierDropdownDelayed"
                        />
                        <button type="button" class="add-inline-btn" @click="openAddSupplierModal" title="Neuen Lieferanten hinzufügen">+</button>
                        <div v-if="showSupplierDropdown && supplierSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredSuppliers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectSupplier(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <!-- Keine Ergebnisse → Neu erstellen -->
                          <div 
                            v-if="filteredSuppliers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddSupplierModal"
                          >
                            <span class="item-name">+ "{{ supplierSearch }}" als Lieferant anlegen</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedSupplier" class="selected-address">
                        ✓ {{ selectedSupplier.name || selectedSupplier.company }}
                        <button type="button" class="clear-selection" @click="clearSupplier">×</button>
                      </p>
                    </div>
                  </div>

                  <div class="form-row">
                    <div v-if="formData.tracking_type !== 'serialized'" class="form-group">
                      <label>Stückpreis (CHF)</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input 
                          v-model.number="formData.unit_price" 
                          type="number" 
                          step="0.01"
                          min="0"
                          class="form-input"
                          placeholder="0.00"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Rechnungsnummer</label>
                      <input 
                        v-model="formData.invoice_number" 
                        type="text" 
                        class="form-input"
                        placeholder="Optional"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Details & Vermietung (optional) – wie in MaterialDetailView -->
            <div v-if="!isAddBatchMode && !isFromTemplate && creationMode && ((formData.material_type === 'physical' && formData.tracking_type) || formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo')" class="step-section" data-step="details">
              <div class="step-header step-header--clickable" @click="toggleStep('details')">
                <span class="step-title">Details &amp; Vermietung</span>
                <span class="step-badge optional">Optional</span>
                <span class="step-chevron" :class="{ open: isStepOpen('details') }">▾</span>
              </div>

              <div v-show="isStepOpen('details')" class="step-content">
                <p class="step-hint">Zusätzliche Angaben wie in der Detailansicht – können später ergänzt werden</p>

                <!-- Material-Details -->
                <div class="details-subsection">
                  <h4 class="subsection-title">Material</h4>
                  <div class="form-grid-details">
                    <div class="form-group">
                      <label>Code <span class="optional">(Optional)</span></label>
                      <input v-model="formData.barcode_tag" type="text" class="form-input" placeholder="z.B. Material-Code" />
                    </div>
                    <div class="form-group">
                      <label>EAN / Barcode</label>
                      <input v-model="formData.ean" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Modell</label>
                      <input v-model="formData.model" type="text" class="form-input" />
                    </div>
                  </div>
                </div>

                <!-- Details (Maße, Gewicht, etc.) -->
                <div class="details-subsection">
                  <h4 class="subsection-title">Details</h4>
                  <div class="form-grid-details">
                    <div class="form-group">
                      <label>Gewicht (kg)</label>
                      <input v-model="formData.weight" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Farbe</label>
                      <input v-model="formData.color" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Länge (cm)</label>
                      <input v-model="formData.size_length" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Breite (cm)</label>
                      <input v-model="formData.size_width" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Höhe (cm)</label>
                      <input v-model="formData.size_height" type="text" class="form-input" />
                    </div>
                    <div class="form-group">
                      <label>Garantie bis</label>
                      <input v-model="formData.warranty_until" type="date" class="form-input" />
                    </div>
                  </div>
                  <div class="form-group mt-2">
                    <label>Beschreibung / Notizen</label>
                    <textarea v-model="formData.description" class="form-textarea" rows="3" placeholder="Optionale Beschreibung..."></textarea>
                  </div>
                </div>

                <!-- Vermietung -->
                <div class="details-subsection">
                  <h4 class="subsection-title">Vermietung</h4>
                  <div class="form-grid-details">
                    <div class="form-group">
                      <label>Tagespreis</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input v-model="formData.rental_price_day" type="text" class="form-input" placeholder="0.00" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Wochenpreis</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input v-model="formData.rental_price_week" type="text" class="form-input" placeholder="0.00" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Monatspreis</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input v-model="formData.rental_price_month" type="text" class="form-input" placeholder="0.00" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Kaution</label>
                      <div class="price-input">
                        <span class="currency">Fr.</span>
                        <input v-model="formData.rental_deposit" type="text" class="form-input" placeholder="0.00" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Vorlaufzeit (Tage)</label>
                      <input v-model.number="formData.rental_lead_days" type="number" class="form-input" placeholder="–" />
                    </div>
                    <div class="form-group">
                      <label>Max. Mietdauer (Tage)</label>
                      <input v-model.number="formData.rental_max_days" type="number" class="form-input" placeholder="–" />
                    </div>
                  </div>
                  <div class="checkbox-group mt-2">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="formData.rental_external_allowed" />
                      <span>Externe Vermietung erlaubt</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="formData.rental_requires_approval" />
                      <span>Genehmigung erforderlich</span>
                    </label>
                  </div>
                  <div class="form-group mt-2">
                    <label>Vermietungs-Hinweise</label>
                    <textarea v-model="formData.rental_notes" class="form-textarea" rows="2" placeholder="Besondere Bedingungen, Hinweise..."></textarea>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>
      </div>

        <!-- Right: Sidebar "Mein Material" (Geschwister von material-wizard-content innerhalb body) -->
        <MaterialPreviewSidebar
          :is-add-batch-mode="isAddBatchMode"
          :selected-material-name="selectedExistingMaterial?.name ?? null"
          :material-name="formData.name"
          :category-path="selectedCategory ? getCategoryPath(selectedCategory) : null"
          :material-type="formData.material_type"
          :initial-qty="formData.initial_qty"
          :is-consumable="formData.is_consumable"
          :is-food="formData.is_food"
          :is-js-material="formData.is_js_material"
          :sale-price="formData.sale_price"
          :min-stock="formData.min_stock"
          :pack-size="formData.pack_size"
          :pack-unit="formData.pack_unit"
          :external-source="formData.external_source"
          :is-from-template="isFromTemplate"
          :template-name="selectedTemplate?.name ?? null"
          :tent-capacity="tentForm.tent_capacity"
          :component-inputs="componentInputs"
          :is-component-done="isComponentDone"
          :storage-address-with-location="storageAddressWithLocation"
          :tracking-type="formData.tracking_type"
          :tracking-type-label="formData.tracking_type ? trackingTypeLabels[formData.tracking_type] : ''"
          :combo-articles-count="selectedComboMaterials.length"
          :material-type-labels="materialTypeLabels"
        />
      
        <!-- Ende material-wizard-body -->
      </div>
        <!-- Footer (direkt im Modal, grid-row: 3) -->
        <WizardFooter
          v-model:create-another="createAnother"
          :missing-steps="missingSteps"
          :can-submit="canSubmit"
          :is-submitting="isSubmitting"
          :is-add-batch-mode="isAddBatchMode"
          :is-from-template="isFromTemplate"
          :creation-mode="creationMode"
          @close="handleClose"
          @submit="handleSubmit"
          @jump-to-missing="jumpToMissingStep"
        />
    </div>
    </div>

    <!-- Zentrale Adress-Modal -->
    <AddressModal
      v-if="showAddressModal"
      :department-id="departmentId"
      :default-type="addressModalType === 'manufacturer' ? 'supplier' : addressModalType"
      :default-name="addressModalDefaultName"
      @close="showAddressModal = false"
      @saved="handleAddressSaved"
    />

    <!-- Kategorie-Modal -->
    <CategoryModal
      v-if="showCategoryModal"
      :department-id="departmentId"
      :default-name="categoryModalDefaultName"
      @close="showCategoryModal = false"
      @saved="handleCategorySaved"
    />
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import { createMaterial, getMaterials, getMaterial, addBatch, createComboFromRack, type CreateMaterialRequest, type AddBatchRequest } from '@/api/materials'
import { getAddresses, type Address } from '@/api/addresses'
import { getGlobalAddresses } from '@/api/globalAddresses'
import { createCategory, getCategories, type Category } from '@/api/categories'
import { createStorageRack, createStorageSlot, getStorageRacks, getStorageSlots, getRackContents, getStorageOverview, getContainerBatches, type StorageRack, type StorageSlot, type StorageOverviewResponse } from '@/api/storageLocations'
import { getTemplates, getTemplate, createMaterialFromTemplate, type Template, type TemplateComponent, type CreateMaterialComponentInput } from '@/api/templates'
import { useToast } from '@/composables/useToast'
import AddressModal from '@/components/AddressModal.vue'
import CategoryModal from '@/components/CategoryModal.vue'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import SelectedModeBanner from '@/components/material/wizard/SelectedModeBanner.vue'
import TemplatePickerSection from '@/components/material/wizard/TemplatePickerSection.vue'
import MaterialPreviewSidebar from '@/components/material/wizard/MaterialPreviewSidebar.vue'
import WizardFooter from '@/components/material/wizard/WizardFooter.vue'
import MaterialNameInput from '@/components/material/wizard/MaterialNameInput.vue'
import MaterialTypeToggles from '@/components/material/wizard/MaterialTypeToggles.vue'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import '@/styles/material-wizard.css'

const props = defineProps<{
  departmentId: string
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'created': [material: any]
}>()

const toast = useToast()
const GLOBAL_SUPPLIER_DEPARTMENT_ID = 'GLOBAL000000'
const articleNameInputRef = ref<HTMLInputElement | null>(null)
const wizardFormRef = ref<HTMLElement | null>(null)
let openInitRunId = 0

const showDialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})
const shouldShowCreationMode = computed(() => !isAddBatchMode.value && !creationMode.value)

const storageAddresses = ref<Address[]>([])
const isSubmitting = ref(false)
const createAnother = ref(false)
const isCheckingName = ref(false)
const nameExists = ref(false)
const nameSuggestions = ref<any[]>([])
const showNameSuggestions = ref(false)
const isNameInputFocused = ref(false)
let nameCheckTimeout: ReturnType<typeof setTimeout> | null = null
const materialNameLookupFetcher = createBasicMaterialLookupFetcher(() => props.departmentId)

// Modus: neues Material erstellen oder Batch zu bestehendem hinzufügen
const isAddBatchMode = ref(false)
const selectedExistingMaterial = ref<any>(null)

// Adress-Modal State
const showAddressModal = ref(false)
const addressModalType = ref<'storage' | 'supplier' | 'manufacturer'>('storage')
const addressModalDefaultName = ref('')

// Kategorie State
const showCategoryModal = ref(false)
const categoryModalDefaultName = ref('')
const allCategories = ref<Category[]>([])
const categorySearch = ref('')
const showCategoryDropdown = ref(false)
const filteredCategories = ref<Category[]>([])
const selectedCategory = ref<Category | null>(null)
const showRackDropdown = ref(false)
const filteredRackOptions = ref<StorageRack[]>([])
const allStorageRacks = ref<StorageRack[]>([])
const storageRacks = ref<StorageRack[]>([])
const storageSlots = ref<StorageSlot[]>([])
const rackPreviewTitles = ref<Record<string, string>>({})
const slotPreviewTitles = ref<Record<string, string>>({})
const containerPreviewTitles = ref<Record<string, string>>({})
const storageOverviewCache = ref<StorageOverviewResponse | null>(null)

// Allokationen für mehrere Lagerplätze (Bulk)
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
const initialAllocations = ref<AllocationRow[]>([])
const slotsByRackId = ref<Record<string, StorageSlot[]>>({})
const containerBatches = ref<import('../api/storageLocations').ContainerBatch[]>([])

function normalizeAllocationRowsToTotal() {
  if (!formData.split_allocations) return
  if (formData.initial_qty <= 0) {
    initialAllocations.value = []
    return
  }
  if (initialAllocations.value.length === 0) {
    addAllocationRow()
    return
  }
  const sanitizedRows = initialAllocations.value.map((row) => ({
    ...row,
    qty: Math.max(0, Number(row.qty || 0)),
  }))
  const sumBeforeLast = sanitizedRows
    .slice(0, -1)
    .reduce((sum, row) => sum + row.qty, 0)
  const remainingForLast = Math.max(formData.initial_qty - sumBeforeLast, 0)
  sanitizedRows[sanitizedRows.length - 1].qty = remainingForLast
  initialAllocations.value = sanitizedRows
}

function addAllocationRow() {
  const remainingQty = Math.max(formData.initial_qty - allocationSum.value, 0)
  if (remainingQty <= 0) return
  initialAllocations.value.push({
    id: ++allocationIdCounter,
    mode: 'slot',
    storage_address_id: getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: '',
    qty: remainingQty
  })
}

function removeAllocationRow(id: number) {
  initialAllocations.value = initialAllocations.value.filter((r) => r.id !== id)
  normalizeAllocationRowsToTotal()
}

async function loadSlotsForRack(rackId: string) {
  if (!rackId) return
  if (slotsByRackId.value[rackId]) return
  let slots = await getStorageSlots(rackId).catch(() => [])
  if (slots.length === 0) {
    const created = await createStorageSlot({
      rack_id: rackId,
      name: 'Fach 1',
    }).catch(() => null)
    if (created) {
      slots = await getStorageSlots(rackId).catch(() => [])
    }
  }
  slotsByRackId.value[rackId] = slots
  slotsByRackId.value = { ...slotsByRackId.value }
  await prefetchSlotPreviewsForRack(rackId)
}

function getRacksForAllocationRow(row: AllocationRow): StorageRack[] {
  if (!row.storage_address_id) return allStorageRacks.value
  return allStorageRacks.value.filter((rack) => rack.storage_address_id === row.storage_address_id)
}

function onAllocationStorageAddressChange(row: AllocationRow) {
  row.rack_id = ''
  row.slot_id = ''
}

const allocationSum = computed(() =>
  initialAllocations.value.reduce((sum, r) => sum + (r.qty || 0), 0)
)
const canAddAllocationRow = computed(() =>
  formData.initial_qty > 0 && allocationSum.value < formData.initial_qty
)
const relevantAllocationRows = computed(() =>
  initialAllocations.value.filter((row) => row.qty > 0)
)
const hasInvalidAllocationRows = computed(() =>
  relevantAllocationRows.value.some((row) =>
    row.mode === 'slot' ? (!row.rack_id || !row.slot_id) : !row.container_batch_id
  )
)
const hasRelevantAllocationRows = computed(() => relevantAllocationRows.value.length > 0)
const allocationSumValid = computed(() =>
  formData.initial_qty > 0 && allocationSum.value === formData.initial_qty
)

// Hersteller Autocomplete
const manufacturerSearch = ref('')
const showManufacturerDropdown = ref(false)
const allManufacturers = ref<Address[]>([])
const filteredManufacturers = ref<Address[]>([])
const selectedManufacturer = ref<Address | null>(null)

// Lieferant Autocomplete
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)
const allSuppliers = ref<Address[]>([])
const filteredSuppliers = ref<Address[]>([])
const selectedSupplier = ref<Address | null>(null)

// Kombinations-Materialien
const comboMaterialSearch = ref('')
const allMaterials = ref<any[]>([])
const filteredComboMaterials = ref<any[]>([])
const selectedComboMaterials = ref<Array<{ id: string; name: string; category?: any; total_stock: number; qty: number }>>([])

// ============ Template-Modus ============
const isFromTemplate = ref(false)
const isFromRackContents = ref(false)
const rackContentsRackId = ref('')
const selectedRackContents = ref<{ rack_id: string; rack_name: string; contents: Array<{ material_id: string; material_name: string; tracking_type: string | null; qty: number }> } | null>(null)
const isLoadingRackContents = ref(false)
const selectedTemplate = ref<Template | null>(null)

// Verpackungseinheit Toggle – setzt pack_size/pack_unit zurück wenn deaktiviert
const packUnitEnabled = computed({
  get: () => !!(formData.pack_size || formData.pack_unit),
  set: (val: boolean) => {
    if (!val) {
      formData.pack_size = null
      formData.pack_unit = ''
    } else if (!formData.pack_size) {
      formData.pack_size = 10
    }
  }
})
const templateSearch = ref('')
const availableTemplates = ref<Template[]>([])
const filteredTemplateList = ref<Template[]>([])
const showTemplateDropdown = ref(false)
const templateComponents = ref<TemplateComponent[]>([])

// Benutzereingaben pro Template-Komponente
interface ComponentInput {
  component_type: string
  name: string
  tracking: 'serialized' | 'bulk'
  required_qty: number
  is_optional: boolean
  mode: 'new' | 'existing'
  serial_number: string
  qty: number
  unit_price: string
  material_id: string
  batch_id: string
  assignment_mode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  // UI-Hilfsfelder für "aus Bestand" Modus
  _materialSearch?: string
  _showDropdown?: boolean
  _filteredMaterials?: any[]
  _selectedMaterial?: any
  _availableBatches?: any[]
}
const componentInputs = ref<ComponentInput[]>([])

type StepId =
  | 'creation_mode'
  | 'general'
  | 'category'
  | 'template_components'
  | 'template_tent'
  | 'template_purchase'
  | 'material_type'
  | 'tracking'
  | 'combo_articles'
  | 'details'
  | 'stock'

const activeStep = ref<StepId | ''>('')
const expandAllVisibleSteps = ref(true)

// Erstellungsmodus
const creationMode = ref<'' | 'individual' | 'physical_combo' | 'virtual_combo'>('')
const getTodayIso = () => new Date().toISOString().slice(0, 10)

// Zelt-Details
const tentForm = reactive({
  tent_type: '' as string,
  tent_capacity: null as number | null,
  reservation_mode: 'complete_only' as string
})

const formData = reactive({
  name: '',
  storage_address_id: '',
  location_rack: '',
  location_slot: '',
  rack_id: '',
  slot_id: '',
  category_id: '' as string,
  material_type: '' as '' | 'physical' | 'physical_combo' | 'virtual_combo',
  tracking_type: '' as '' | 'serialized' | 'bulk',
  is_consumable: false,
  is_food: false,
  is_js_material: false,
  external_source: '' as string,
  sale_price: '' as string,
  min_stock: null as number | null,
  pack_size: null as number | null,
  pack_unit: '' as string,
  initial_qty: 0,
  purchase_date: getTodayIso(),
  expiry_date: '',
  manufacturer: '',
  supplier_id: '',
  unit_price: 0,
  invoice_number: '',
  notes: '',
  // Details (wie in MaterialDetailView)
  barcode_tag: '' as string,
  description: '' as string,
  model: '' as string,
  ean: '' as string,
  weight: '' as string,
  color: '' as string,
  size_length: '' as string,
  size_width: '' as string,
  size_height: '' as string,
  warranty_until: '' as string,
  // Vermietung
  rental_price_day: '' as string,
  rental_price_week: '' as string,
  rental_price_month: '' as string,
  rental_deposit: '' as string,
  rental_lead_days: null as number | null,
  rental_max_days: null as number | null,
  rental_external_allowed: false,
  rental_requires_approval: false,
  rental_notes: '' as string,
  split_allocations: false,
  stock_location_mode: 'slot' as 'slot' | 'kiste',
  stock_container_batch_id: '' as string
})

// Seriennummern für serialisierte Artikel
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
const serialNumbers = ref<SerialNumberEntry[]>([])
let serialIdCounter = 0
const serialScannerActive = ref(false)
const serialScannerTargetId = ref<number | null>(null)
const showExpiryDateForNonFood = ref(false)

// Automatisch erzeugen
const autoGenPrefix = ref('')
const autoGenStart = ref(1)
const autoGenPad = ref(3)
const autoGenCount = ref(5)
const serialAutoGenerateEnabled = ref(false)
const serialLocationSameForAll = ref(false)

const suggestedSerialPrefix = computed(() => {
  const name = (formData.name || '').trim()
  return name ? `${name}-` : ''
})

type WizardStockPrefs = {
  storage_address_id?: string
  stock_location_mode?: 'slot' | 'kiste'
  autoGenPrefix?: string
  autoGenStart?: number
  autoGenPad?: number
  autoGenCount?: number
  serialAutoGenerateEnabled?: boolean
  serialLocationSameForAll?: boolean
}

const wizardStockPrefsKey = computed(() => `materialWizard.stockPrefs.${props.departmentId}`)

function loadWizardStockPrefs(): WizardStockPrefs {
  try {
    const raw = localStorage.getItem(wizardStockPrefsKey.value)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as WizardStockPrefs
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function saveWizardStockPrefs() {
  try {
    const payload: WizardStockPrefs = {
      storage_address_id: formData.storage_address_id || '',
      stock_location_mode: formData.stock_location_mode,
      autoGenPrefix: autoGenPrefix.value || '',
      autoGenStart: Math.max(1, autoGenStart.value || 1),
      autoGenPad: Math.max(1, Math.min(6, autoGenPad.value || 3)),
      autoGenCount: Math.max(1, Math.min(100, autoGenCount.value || 1)),
      serialAutoGenerateEnabled: !!serialAutoGenerateEnabled.value,
      serialLocationSameForAll: !!serialLocationSameForAll.value,
    }
    localStorage.setItem(wizardStockPrefsKey.value, JSON.stringify(payload))
  } catch {
    // Ignore localStorage failures (private mode, quota, etc.)
  }
}

function applyWizardStockPrefs() {
  const prefs = loadWizardStockPrefs()
  if (prefs.storage_address_id) formData.storage_address_id = prefs.storage_address_id
  if (prefs.stock_location_mode === 'slot' || prefs.stock_location_mode === 'kiste') {
    formData.stock_location_mode = prefs.stock_location_mode
  }
  if (typeof prefs.autoGenPrefix === 'string') {
    const normalizedPrefix = prefs.autoGenPrefix.trim()
    autoGenPrefix.value = normalizedPrefix.toUpperCase() === 'KISTE-' ? '' : prefs.autoGenPrefix
  }
  if (typeof prefs.autoGenStart === 'number') autoGenStart.value = Math.max(1, prefs.autoGenStart)
  if (typeof prefs.autoGenPad === 'number') autoGenPad.value = Math.max(1, Math.min(6, prefs.autoGenPad))
  if (typeof prefs.autoGenCount === 'number') autoGenCount.value = Math.max(1, Math.min(100, prefs.autoGenCount))
  if (typeof prefs.serialAutoGenerateEnabled === 'boolean') serialAutoGenerateEnabled.value = prefs.serialAutoGenerateEnabled
  if (typeof prefs.serialLocationSameForAll === 'boolean') serialLocationSameForAll.value = prefs.serialLocationSameForAll
}

const autoGenPreview = computed(() => {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value
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

function generateSerialNumbers() {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value
  const start = Math.max(1, autoGenStart.value)
  const pad = Math.max(1, Math.min(6, autoGenPad.value || 3))
  const count = Math.max(1, Math.min(100, autoGenCount.value || 1))
  serialNumbers.value = Array.from({ length: count }, (_, i) => ({
    id: ++serialIdCounter,
    serial_number: prefix + String(start + i).padStart(pad, '0'),
    label: '',
    notes: '',
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  }))
}

function addSerialNumber() {
  serialNumbers.value.push({
    id: ++serialIdCounter,
    serial_number: '',
    label: '',
    notes: '',
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  })
}

function ensureInitialSerialRow() {
  if (serialNumbers.value.length === 0) {
    addSerialNumber()
  }
}

function getRacksForSerialEntry(entry: SerialNumberEntry): StorageRack[] {
  if (!entry.storage_address_id) return allStorageRacks.value
  return allStorageRacks.value.filter((rack) => rack.storage_address_id === entry.storage_address_id)
}

function getPreferredStorageAddressId(): string {
  return formData.storage_address_id || storageAddresses.value[0]?.id || ''
}

function onSerialEntryStorageAddressChange(entry: SerialNumberEntry) {
  entry.rack_id = ''
  entry.slot_id = ''
}

async function onSerialEntryRackChange(entry: SerialNumberEntry) {
  entry.slot_id = ''
  if (entry.rack_id) {
    await loadSlotsForRack(entry.rack_id)
  }
}

const hasInvalidSerialLocations = computed(() =>
  !serialLocationSameForAll.value &&
  serialNumbers.value
    .filter((entry) => entry.serial_number.trim())
    .some((entry) =>
      entry.location_mode === 'kiste'
        ? !entry.container_batch_id
        : (!entry.rack_id || !entry.slot_id)
    )
)

const duplicateSerialNumbers = computed(() => {
  const counts = new Map<string, number>()
  const rawByNormalized = new Map<string, string>()
  for (const entry of serialNumbers.value) {
    const raw = (entry.serial_number || '').trim()
    if (!raw) continue
    const normalized = raw.toLowerCase()
    counts.set(normalized, (counts.get(normalized) || 0) + 1)
    if (!rawByNormalized.has(normalized)) rawByNormalized.set(normalized, raw)
  }
  return Array.from(counts.entries())
    .filter(([, count]) => count > 1)
    .map(([normalized]) => rawByNormalized.get(normalized) || normalized)
})

const hasDuplicateSerialNumbers = computed(() => duplicateSerialNumbers.value.length > 0)

const serialDuplicateHint = computed(() => {
  if (!hasDuplicateSerialNumbers.value) return ''
  const list = duplicateSerialNumbers.value.slice(0, 3).join(', ')
  const rest = duplicateSerialNumbers.value.length > 3 ? ' …' : ''
  return `Doppelte Seriennummern im Formular: ${list}${rest}`
})

function getSerialRowTitle(entry: SerialNumberEntry, index: number): string {
  const sn = (entry.serial_number || '').trim()
  return sn ? `Seriennummer ${index + 1} · ${sn}` : `Seriennummer ${index + 1}`
}

function removeSerialNumber(id: number) {
  serialNumbers.value = serialNumbers.value.filter(s => s.id !== id)
  if (serialScannerTargetId.value === id) {
    serialScannerTargetId.value = null
  }
}

function openSerialScannerFor(id: number) {
  serialScannerTargetId.value = id
  serialScannerActive.value = true
}

function stopSerialScanner() {
  serialScannerActive.value = false
}

function toggleSerialScanner() {
  if (serialScannerActive.value) {
    stopSerialScanner()
    return
  }
  const firstEmpty = serialNumbers.value.find(s => !s.serial_number.trim())
  serialScannerTargetId.value = firstEmpty?.id ?? serialNumbers.value[0]?.id ?? null
  if (!serialScannerTargetId.value) {
    addSerialNumber()
    serialScannerTargetId.value = serialNumbers.value[serialNumbers.value.length - 1]?.id ?? null
  }
  serialScannerActive.value = true
}

function onSerialDetected(payload: { text: string }) {
  const value = payload.text.trim()
  if (!value) return

  let target = serialNumbers.value.find(s => s.id === serialScannerTargetId.value)
  if (!target) {
    target = serialNumbers.value.find(s => !s.serial_number.trim())
  }
  if (!target) {
    addSerialNumber()
    target = serialNumbers.value[serialNumbers.value.length - 1]
  }

  target.serial_number = value

  const nextEmpty = serialNumbers.value.find(s => !s.serial_number.trim())
  serialScannerTargetId.value = nextEmpty?.id ?? null
  if (!nextEmpty) {
    addSerialNumber()
    serialScannerTargetId.value = serialNumbers.value[serialNumbers.value.length - 1]?.id ?? null
  }
}

function onSerialScannerError(message: string) {
  toast.error(message)
}

// Automatisch initial_qty bei Seriennummern aktualisieren
const serializedQty = computed(() => serialNumbers.value.filter(s => s.serial_number.trim()).length)
const stockInputReady = computed(() => {
  if (formData.tracking_type === 'serialized') return serializedQty.value > 0
  return formData.initial_qty > 0
})

watch(
  () => formData.tracking_type,
  (type) => {
    if (type === 'serialized') {
      ensureInitialSerialRow()
    }
  }
)

watch(serialLocationSameForAll, (sameForAll) => {
  if (sameForAll) return
  serialNumbers.value.forEach((entry) => {
    if (!entry.storage_address_id) {
      entry.storage_address_id = formData.storage_address_id || ''
    }
  })
})

const materialTypeLabels: Record<string, string> = {
  physical: 'Physischer Artikel',
  physical_combo: 'Physische Kombination',
  virtual_combo: 'Virtuelle Kombination'
}

const trackingTypeLabels: Record<string, string> = {
  serialized: 'Serialisiert',
  bulk: 'Massenartikel'
}

const storageAddressName = computed(() => {
  if (!formData.storage_address_id) return ''
  const addr = storageAddresses.value.find(a => a.id === formData.storage_address_id)
  return addr ? (addr.name || addr.street_line) : ''
})

const storageAddressWithLocation = computed(() => {
  const addrName = storageAddressName.value
  if (!addrName) return null
  if (!formData.rack_id) return addrName
  const rack = storageRacks.value.find((r) => r.id === formData.rack_id)
  const rackName = rack?.name || ''
  if (!rackName) return addrName
  if (formData.slot_id) {
    const slots = slotsByRackId.value[formData.rack_id] || []
    const slot = slots.find((s) => s.id === formData.slot_id)
    if (slot?.name) return `${addrName} • ${rackName}, ${slot.name}`
  }
  return `${addrName} • ${rackName}`
})

const requiresExpiryDate = computed(() => {
  if (!formData.is_food) return false
  if (formData.tracking_type === 'serialized') return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  return formData.initial_qty > 0
})

const requiresPurchaseDate = computed(() => {
  if (formData.is_food) return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  if (formData.tracking_type === 'serialized') return serializedQty.value > 0
  return formData.initial_qty > 0
})

const visibleStepIds = computed<StepId[]>(() => {
  const steps: StepId[] = []

  if (!isAddBatchMode.value && !creationMode.value) steps.push('creation_mode')
  if (!isAddBatchMode.value && !!creationMode.value) steps.push('general')

  if (!isAddBatchMode.value && !isFromTemplate.value && creationMode.value === 'individual') {
    steps.push('category')
  }

  if ((isFromTemplate.value && selectedTemplate.value) || (isFromRackContents.value && selectedRackContents.value)) {
    if (creationMode.value && (creationMode.value === 'individual' || (formData.name && !nameExists.value))) {
      steps.push('template_components')
    }
  }

  if (isFromTemplate.value && selectedTemplate.value && creationMode.value && creationMode.value !== 'individual' && formData.name && !nameExists.value) {
    steps.push('template_tent')
  }

  if (isFromTemplate.value && selectedTemplate.value && creationMode.value && (creationMode.value === 'individual' || (formData.name && !nameExists.value))) {
    steps.push('template_purchase')
  }

  if (!isAddBatchMode.value && !isFromTemplate.value && creationMode.value === 'individual' && !formData.is_food) {
    steps.push('material_type')
  }

  if (!isAddBatchMode.value && !isFromTemplate.value && creationMode.value === 'individual' && formData.material_type === 'physical' && !formData.is_food) {
    steps.push('tracking')
  }

  if (
    !isAddBatchMode.value &&
    !isFromTemplate.value &&
    !isFromRackContents.value &&
    (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo')
  ) {
    steps.push('combo_articles')
  }

  // Details & Vermietung (optional) – vor Stock, wenn Material-Typ feststeht
  if (
    !isAddBatchMode.value &&
    !isFromTemplate.value &&
    creationMode.value &&
    ((formData.material_type === 'physical' && formData.tracking_type) ||
      formData.material_type === 'physical_combo' ||
      formData.material_type === 'virtual_combo')
  ) {
    steps.push('details')
  }

  if (isAddBatchMode.value || (!isFromTemplate.value && creationMode.value === 'individual' && formData.material_type === 'physical' && formData.tracking_type)) {
    steps.push('stock')
  }

  return steps
})

function isStepOpen(step: StepId): boolean {
  if (expandAllVisibleSteps.value) {
    return visibleStepIds.value.includes(step)
  }
  return activeStep.value === step
}

function toggleStep(step: StepId): void {
  if (expandAllVisibleSteps.value) {
    return
  }
  activeStep.value = activeStep.value === step ? '' : step
}

function mapMissingToStep(message: string): StepId {
  const msg = message.toLowerCase()
  if (msg.includes('erstellmodus')) return 'creation_mode'
  if (msg.includes('name')) return 'general'
  if (msg.includes('kategorie')) return 'category'
  if (msg.includes('bestandsverfolgung')) return 'tracking'
  if (msg.includes('ablaufdatum')) return 'stock'
  if (msg.includes('mindestens 2 artikel')) return 'combo_articles'
  if (msg.includes('kaufdatum') || msg.includes('seriennummer') || msg.includes('mindestens 1 stück')) return 'stock'
  if (msg.includes('sn für') || msg.includes('artikel für') || msg.includes('menge für')) return 'template_components'
  return 'general'
}

async function jumpToMissingStep(message: string): Promise<void> {
  const step = mapMissingToStep(message)
  activeStep.value = step
  await nextTick()
  const el = document.querySelector(`.step-section[data-step="${step}"], [data-step="${step}"]`)
  if (el && 'scrollIntoView' in el) {
    ;(el as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

const canSubmit = computed(() => {
  // Im Batch-Modus: Menge > 0 und Kaufdatum erforderlich
  if (isAddBatchMode.value) {
    if (formData.initial_qty <= 0) return false
    if (requiresPurchaseDate.value && !formData.purchase_date) return false
    if (requiresExpiryDate.value && !formData.expiry_date) return false
    if (formData.split_allocations && (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value)) return false
    return true
  }
  
  // Erstellungsmodus muss gewählt sein
  if (!creationMode.value) return false

  // ── Combo aus Lagerplatz-Inhalt ──
  if (isFromRackContents.value && selectedRackContents.value && rackContentsRackId.value) {
    if (!formData.name.trim()) return false
    if (nameExists.value) return false
    return true
  }

  // ── Virtuelle Kombo (ohne Vorlage): Name + Reservation reicht ──
  if (creationMode.value === 'virtual_combo') {
    if (!formData.name.trim()) return false
    if (nameExists.value) return false
    return true
  }

  // ── Mit Vorlage (Einzelartikel oder Physische Kombo) ──
  if (isFromTemplate.value && selectedTemplate.value) {
    // Physische Kombo: Name ist Pflicht
    if (creationMode.value === 'physical_combo') {
      if (!formData.name.trim()) return false
      if (nameExists.value) return false
    }

    // Pflichtkomponenten müssen ausgefüllt sein
    for (const ci of componentInputs.value) {
      if (ci.is_optional) continue
      if (ci.mode === 'new') {
        if (ci.tracking === 'serialized' && !ci.serial_number.trim()) return false
        if (ci.tracking === 'bulk' && ci.qty < 1) return false
      } else {
        if (ci.tracking === 'serialized') {
          if (!ci.material_id || !ci.batch_id) return false
        } else {
          if (!ci.material_id || ci.qty < 1) return false
        }
      }
    }
    return true
  }

  // ── Einzelartikel ohne Vorlage (manuell) ──
  if (!formData.name.trim()) return false
  if (nameExists.value) return false
  if (!formData.category_id) return false
  if (!formData.material_type) return false
  
  if (formData.material_type === 'physical') {
    if (!formData.tracking_type) return false
    if (requiresPurchaseDate.value && !formData.purchase_date) return false
    if (requiresExpiryDate.value && !formData.expiry_date) return false
    if (formData.tracking_type === 'serialized') {
      if (serializedQty.value < 1) return false
      if (hasDuplicateSerialNumbers.value) return false
    } else {
      if (formData.initial_qty < 1) return false
      if (formData.split_allocations && (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value)) return false
    }
    if (formData.tracking_type === 'serialized') {
      if (!serialLocationSameForAll.value && hasInvalidSerialLocations.value) return false
      if (serialLocationSameForAll.value && formData.stock_location_mode === 'kiste' && serializedQty.value > 0 && !formData.stock_container_batch_id) return false
      if (serialLocationSameForAll.value && formData.stock_location_mode === 'slot' && serializedQty.value > 0 && (!formData.rack_id || !formData.slot_id)) return false
    } else {
      if (!formData.split_allocations && formData.stock_location_mode === 'kiste' && formData.initial_qty > 0 && !formData.stock_container_batch_id) return false
      if (!formData.split_allocations && formData.stock_location_mode === 'slot' && formData.initial_qty > 0 && (!formData.rack_id || !formData.slot_id)) return false
    }
  }

  if (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') {
    if (selectedComboMaterials.value.length < 2) return false
  }
  
  return true
})

// Zeigt an, was noch fehlt
const missingSteps = computed(() => {
  const missing: string[] = []
  
  // Im Batch-Modus
  if (isAddBatchMode.value) {
    if (formData.initial_qty <= 0) {
      missing.push('Menge eingeben')
    }
    if (requiresPurchaseDate.value && !formData.purchase_date) {
      missing.push('Kaufdatum eingeben')
    }
    if (requiresExpiryDate.value && !formData.expiry_date) {
      missing.push('Ablaufdatum eingeben')
    }
    if (formData.split_allocations) {
      if (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value) {
        missing.push('Lagerplätze: Summe muss ' + formData.initial_qty + ' Stk. ergeben')
      }
    }
    return missing
  }
  
  // Erstellmodus muss gewählt sein
  if (!creationMode.value) {
    missing.push('Erstellmodus wählen')
    return missing
  }

  // ── Virtuelle Kombo ──
  if (creationMode.value === 'virtual_combo') {
    if (!formData.name.trim()) missing.push('Name der Kombination eingeben')
    else if (nameExists.value) missing.push('Name existiert bereits')
    return missing
  }

  // ── Mit Vorlage (Einzelartikel oder Physische Kombo) ──
  if (isFromTemplate.value && selectedTemplate.value) {
    if (creationMode.value === 'physical_combo' && !formData.name.trim()) {
      missing.push('Name der Kombination eingeben')
    }
    // Pflichtkomponenten prüfen
    for (const ci of componentInputs.value) {
      if (ci.is_optional) continue
      if (ci.mode === 'new') {
        if (ci.tracking === 'serialized' && !ci.serial_number.trim()) {
          missing.push(`SN für "${ci.name}" eingeben`)
          break
        }
        if (ci.tracking === 'bulk' && ci.qty < 1) {
          missing.push(`Menge für "${ci.name}" eingeben`)
          break
        }
      } else {
        if (ci.tracking === 'serialized') {
          if (!ci.material_id) { missing.push(`Artikel für "${ci.name}" wählen`); break }
          if (!ci.batch_id) { missing.push(`SN für "${ci.name}" wählen`); break }
        } else {
          if (!ci.material_id) { missing.push(`Artikel für "${ci.name}" wählen`); break }
          if (ci.qty < 1) { missing.push(`Menge für "${ci.name}" eingeben`); break }
        }
      }
    }
    return missing
  }

  // ── Einzelartikel ohne Vorlage (manuell) ──
  if (!formData.name.trim()) {
    missing.push('Artikelname eingeben')
  } else if (nameExists.value) {
    missing.push('Name existiert bereits')
  }
  
  if (!formData.category_id) {
    missing.push('Kategorie auswählen')
  }
  
  if (!formData.material_type) {
    missing.push('Material-Typ auswählen')
  }
  
  // Bei physical: Tracking + Menge + Kaufdatum erforderlich
  if (formData.material_type === 'physical') {
    if (!formData.tracking_type) {
      missing.push('Bestandsverfolgung wählen')
    } else {
      if (requiresPurchaseDate.value && !formData.purchase_date) {
        missing.push('Kaufdatum eingeben')
      }
      if (requiresExpiryDate.value && !formData.expiry_date) {
        missing.push('Ablaufdatum eingeben')
      }
      if (formData.tracking_type === 'serialized') {
        if (serializedQty.value < 1) {
          missing.push('Mindestens 1 Seriennummer eingeben')
        }
        if (hasDuplicateSerialNumbers.value) {
          missing.push('Doppelte Seriennummern im Formular entfernen')
        }
      } else if (formData.initial_qty < 1) {
        missing.push('Mindestens 1 Stück eingeben')
      }
      if (formData.tracking_type === 'serialized') {
        if (!serialLocationSameForAll.value) {
          if (hasInvalidSerialLocations.value) {
            missing.push('Pro Seriennummer Standort wählen')
          }
        } else if (serializedQty.value > 0) {
          if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
            missing.push('Kiste wählen')
          }
          if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
            missing.push('Gestell und Fach wählen')
          }
        }
      } else {
        const hasStockInput = formData.initial_qty > 0
        if (hasStockInput && !formData.split_allocations) {
          if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
            missing.push('Kiste wählen')
          }
          if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
            missing.push('Gestell und Fach wählen')
          }
        }
      }
    }
  }
  
  // Bei Kombinationen: 2 Artikel
  if ((formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') 
      && selectedComboMaterials.value.length < 2) {
    missing.push('Mindestens 2 Artikel hinzufügen')
  }
  return missing
})
const shouldRenderCreationMode = computed(() => {
  if (shouldShowCreationMode.value) return true
  return missingSteps.value[0] === 'Erstellmodus wählen'
})

function resetForm() {
  formData.name = ''
  formData.storage_address_id = ''
  formData.location_rack = ''
  formData.location_slot = ''
  formData.rack_id = ''
  formData.slot_id = ''
  formData.split_allocations = false
  initialAllocations.value = []
  slotsByRackId.value = {}
  showRackDropdown.value = false
  filteredRackOptions.value = []
  formData.category_id = ''
  formData.material_type = ''
  formData.tracking_type = ''
  formData.is_consumable = false
  formData.is_food = false
  formData.is_js_material = false
  formData.external_source = ''
  formData.sale_price = ''
  formData.min_stock = null
  formData.pack_size = null
  formData.pack_unit = ''
  formData.initial_qty = 0
  formData.purchase_date = getTodayIso()
  formData.expiry_date = ''
  showExpiryDateForNonFood.value = false
  formData.manufacturer = ''
  formData.supplier_id = ''
  formData.unit_price = 0
  formData.invoice_number = ''
  formData.notes = ''
  formData.barcode_tag = ''
  formData.description = ''
  formData.model = ''
  formData.ean = ''
  formData.weight = ''
  formData.color = ''
  formData.size_length = ''
  formData.size_width = ''
  formData.size_height = ''
  formData.warranty_until = ''
  formData.rental_price_day = ''
  formData.rental_price_week = ''
  formData.rental_price_month = ''
  formData.rental_deposit = ''
  formData.rental_lead_days = null
  formData.rental_max_days = null
  formData.rental_external_allowed = false
  formData.rental_requires_approval = false
  formData.rental_notes = ''
  formData.stock_location_mode = 'slot'
  formData.stock_container_batch_id = ''
  nameExists.value = false
  nameSuggestions.value = []
  showNameSuggestions.value = false
  isAddBatchMode.value = false
  selectedExistingMaterial.value = null
  manufacturerSearch.value = ''
  selectedManufacturer.value = null
  supplierSearch.value = ''
  selectedSupplier.value = null
  comboMaterialSearch.value = ''
  selectedComboMaterials.value = []
  categorySearch.value = ''
  selectedCategory.value = null
  // Seriennummern zurücksetzen
  serialNumbers.value = []
  serialIdCounter = 0
  serialScannerActive.value = false
  serialScannerTargetId.value = null
  serialLocationSameForAll.value = false
  serialAutoGenerateEnabled.value = false
  autoGenPrefix.value = ''
  autoGenStart.value = 1
  autoGenPad.value = 3
  autoGenCount.value = 5
  // Template-Modus zurücksetzen
  isFromTemplate.value = false
  selectedTemplate.value = null
  isFromRackContents.value = false
  rackContentsRackId.value = ''
  selectedRackContents.value = null
  creationMode.value = ''
  templateSearch.value = ''
  templateComponents.value = []
  componentInputs.value = []
  tentForm.tent_type = ''
  tentForm.tent_capacity = null
  tentForm.reservation_mode = 'complete_only'

  // Last-used stock/serial preferences per department
  applyWizardStockPrefs()
}

function handleClose() {
  showDialog.value = false
  resetForm()
}

function selectMaterialType(type: 'physical' | 'physical_combo' | 'virtual_combo') {
  if (formData.is_food && type !== 'physical') {
    return
  }
  formData.material_type = type
  if (type !== 'physical') {
    formData.tracking_type = ''
  }
}

async function checkNameDebounced() {
  if (nameCheckTimeout) clearTimeout(nameCheckTimeout)
  
  if (!formData.name.trim()) {
    nameExists.value = false
    nameSuggestions.value = []
    return
  }
  
  isCheckingName.value = true
  
  nameCheckTimeout = setTimeout(async () => {
    try {
      const query = formData.name.trim().toLowerCase()
      const materials = await materialNameLookupFetcher(formData.name.trim())
      
      // Exakte Übereinstimmung prüfen
      nameExists.value = materials.some(m => 
        m.name.toLowerCase() === query
      )
      
      // Ähnliche Vorschläge (max 5)
      nameSuggestions.value = materials
        .filter(m => m.name.toLowerCase().includes(query))
        .slice(0, 5)
      
      showNameSuggestions.value = nameSuggestions.value.length > 0
    } catch (err) {
      nameExists.value = false
      nameSuggestions.value = []
    } finally {
      isCheckingName.value = false
    }
  }, 400)
}

function hideNameSuggestionsDelayed() {
  setTimeout(() => { showNameSuggestions.value = false }, 200)
}

function handleNameInputFocus() {
  isNameInputFocused.value = true
  showNameSuggestions.value = nameSuggestions.value.length > 0
}

function handleNameInputBlur() {
  isNameInputFocused.value = false
  hideNameSuggestionsDelayed()
}

function selectNameSuggestion(material: any) {
  // Wechsle in "Batch hinzufügen" Modus
  isAddBatchMode.value = true
  selectedExistingMaterial.value = material
  formData.name = material.name
  showNameSuggestions.value = false
  nameExists.value = false // Im Batch-Modus ist das OK
  
  // Setze die Material-Eigenschaften aus dem existierenden Material
  formData.storage_address_id = material.storage_address?.id || ''
  const rawLocation = String(material.location || '').trim()
  if (!rawLocation) {
    formData.location_rack = ''
    formData.location_slot = ''
    formData.rack_id = ''
    formData.slot_id = ''
  } else if (rawLocation.includes('/')) {
    const [rack, slot] = rawLocation.split('/').map((part: string) => part.trim())
    formData.location_rack = rack || ''
    formData.location_slot = slot || ''
    formData.rack_id = ''
    formData.slot_id = ''
  } else {
    formData.location_rack = rawLocation
    formData.location_slot = ''
    formData.rack_id = ''
    formData.slot_id = ''
  }
  formData.is_food = !!material.is_food
  formData.expiry_date = ''
}

function exitAddBatchMode() {
  isAddBatchMode.value = false
  selectedExistingMaterial.value = null
  formData.name = ''
  nameExists.value = false
}

async function loadData() {
  try {
    const storageResult = await getAddresses(props.departmentId, 'storage').catch(() => ({ addresses: [] }))
    storageAddresses.value = storageResult.addresses || []
    
    if (storageAddresses.value.length > 0 && !formData.storage_address_id) {
      const defaultStorage = storageAddresses.value.find(a => 
        a.name?.toLowerCase().includes('standard') || a.is_primary
      ) || storageAddresses.value[0]
      formData.storage_address_id = defaultStorage.id
    }
    const preferredStorageAddressId = getPreferredStorageAddressId()
    initialAllocations.value.forEach((row) => {
      if (!row.storage_address_id) row.storage_address_id = preferredStorageAddressId
    })
    serialNumbers.value.forEach((entry) => {
      if (!entry.storage_address_id) entry.storage_address_id = preferredStorageAddressId
    })

    allStorageRacks.value = await getStorageRacks(props.departmentId).catch(() => [])
    storageRacks.value = (formData.storage_address_id
      ? allStorageRacks.value.filter((rack) => rack.storage_address_id === formData.storage_address_id)
      : allStorageRacks.value)
    await prefetchVisibleRackPreviews(storageRacks.value)
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchContainerPreviews()
    searchRackCategories()
    
    const [supplierResult, globalSupplierResult, globalDepartmentSupplierResult] = await Promise.all([
      getAddresses(props.departmentId, 'supplier').catch(() => ({ addresses: [] })),
      getGlobalAddresses().catch(() => ({ addresses: [] })),
      getAddresses(GLOBAL_SUPPLIER_DEPARTMENT_ID, 'supplier').catch(() => ({ addresses: [] }))
    ])
    const mergedSuppliers = [...(supplierResult.addresses || [])]
    const globalCandidates = [
      ...(globalSupplierResult.addresses || []),
      ...(globalDepartmentSupplierResult.addresses || [])
    ]
    for (const globalAddress of globalCandidates) {
      if (!mergedSuppliers.some(addr => addr.id === globalAddress.id)) {
        mergedSuppliers.push(globalAddress)
      }
    }
    allManufacturers.value = mergedSuppliers
    allSuppliers.value = mergedSuppliers
    
    const materialsResult = await getMaterials(props.departmentId).catch(() => [])
    allMaterials.value = materialsResult || []
    
    // Kategorien laden
    const categoriesResult = await getCategories(props.departmentId).catch(() => [])
    allCategories.value = categoriesResult || []
    applyFoodCategoryIfAvailable()

    // Vorlagen laden
    const templatesResult = await getTemplates(props.departmentId, true).catch(() => [])
    availableTemplates.value = templatesResult || []
  } catch (err) {
    console.error('Fehler beim Laden:', err)
  }
}

function findFoodCategory(): Category | null {
  const categories = allCategories.value || []
  if (!categories.length) return null

  const normalized = (value: string) =>
    value
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim()

  const exactNames = new Set(['esswaren', 'lebensmittel', 'food'])
  const exactMatch = categories.find((cat) => exactNames.has(normalized(cat.name)))
  if (exactMatch) return exactMatch

  return categories.find((cat) => {
    const name = normalized(cat.name)
    return (
      name.includes('esswaren') ||
      name.includes('lebensmittel') ||
      name.includes('getrank') ||
      name.includes('snack') ||
      name.includes('food')
    )
  }) || null
}

function applyFoodCategoryIfAvailable(): void {
  if (!formData.is_food || isAddBatchMode.value) return
  const foodCategory = findFoodCategory()
  if (!foodCategory) return
  selectedCategory.value = foodCategory
  formData.category_id = foodCategory.id
  categorySearch.value = foodCategory.name
}

// Hersteller Suche
function searchManufacturers() {
  const query = manufacturerSearch.value.toLowerCase().trim()
  if (!query) {
    filteredManufacturers.value = allManufacturers.value.slice(0, 10)
    return
  }
  filteredManufacturers.value = allManufacturers.value
    .filter(a => (a.name?.toLowerCase().includes(query)) || (a.company?.toLowerCase().includes(query)))
    .slice(0, 10)
}

function selectManufacturer(addr: Address) {
  selectedManufacturer.value = addr
  manufacturerSearch.value = addr.name || addr.company || ''
  showManufacturerDropdown.value = false
  formData.manufacturer = addr.name || addr.company || ''
}

function clearManufacturer() {
  selectedManufacturer.value = null
  manufacturerSearch.value = ''
  formData.manufacturer = ''
}

function applyTemplateManufacturer(manufacturer: string | null | undefined) {
  const value = (manufacturer || '').trim()
  formData.manufacturer = value
  manufacturerSearch.value = value

  if (!value) {
    selectedManufacturer.value = null
    return
  }

  const valueLower = value.toLowerCase()
  const existing = allManufacturers.value.find(a => {
    const name = (a.name || '').trim().toLowerCase()
    const company = (a.company || '').trim().toLowerCase()
    return name === valueLower || company === valueLower
  }) || null

  selectedManufacturer.value = existing
}

function hideManufacturerDropdownDelayed() {
  setTimeout(() => { showManufacturerDropdown.value = false }, 200)
}

// Kategorie Suche
function searchCategories() {
  const query = categorySearch.value.toLowerCase().trim()
  if (!query) {
    // Zeige hierarchisch sortiert: Hauptkategorien, dann Unterkategorien
    const sorted: Category[] = []
    const mainCats = allCategories.value.filter(c => !c.parent_id)
    mainCats.forEach(main => {
      sorted.push(main)
      const children = allCategories.value.filter(c => c.parent_id === main.id)
      sorted.push(...children)
    })
    filteredCategories.value = sorted.slice(0, 15)
    return
  }
  filteredCategories.value = allCategories.value
    .filter(c => c.name.toLowerCase().includes(query))
    .slice(0, 15)
}

function selectCategory(cat: Category) {
  selectedCategory.value = cat
  categorySearch.value = cat.name
  formData.category_id = cat.id
  showCategoryDropdown.value = false
}

function clearCategory() {
  selectedCategory.value = null
  categorySearch.value = ''
  formData.category_id = ''
}

function hideCategoryDropdownDelayed() {
  setTimeout(() => { showCategoryDropdown.value = false }, 200)
}

function getCategoryPath(cat: Category): string {
  if (!cat.parent_id) return cat.name
  const parent = allCategories.value.find(c => c.id === cat.parent_id)
  return parent ? `${parent.name} › ${cat.name}` : cat.name
}

function normalizeName(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
}

function searchRackCategories() {
  const query = normalizeName(formData.location_rack)
  const racks = storageRacks.value
  if (!query) {
    filteredRackOptions.value = racks.slice(0, 15)
    return
  }
  filteredRackOptions.value = racks
    .filter((rack) => normalizeName(rack.name).includes(query))
    .slice(0, 15)
}

async function selectRackCategory(rack: StorageRack) {
  formData.location_rack = rack.name
  formData.rack_id = rack.id
  await loadSlotsForRack(rack.id)
  storageSlots.value = slotsByRackId.value[rack.id] || []
  const matching = storageSlots.value.find((slot) => normalizeName(slot.name) === normalizeName(formData.location_slot))
  formData.slot_id = matching?.id || ''
  showRackDropdown.value = false
}

function hideRackDropdownDelayed() {
  setTimeout(() => { showRackDropdown.value = false }, 200)
}

function buildContentPreviewTitle(items: Array<{ material_name: string; qty: number }>): string {
  if (!items.length) return 'Leer'
  const lines = items.slice(0, 5).map((item) => `${item.material_name} (${item.qty})`)
  if (items.length > 5) lines.push(`+${items.length - 5} weitere`)
  return lines.join('\n')
}

function formatContainerBatchOption(cb: import('../api/storageLocations').ContainerBatch): string {
  const slotName = (cb.slot?.name || '').trim()
  const rackName = (cb.rack?.name || '').trim()
  const location = slotName
    ? (rackName ? `${rackName} / ${slotName}` : slotName)
    : (rackName || 'Ohne Fach')
  const label = (cb.label || '').trim()
  const name = (cb.material_name || '').trim()
  const serial = (cb.serial_number || '').trim()
  const primary = label || serial || name || 'Kiste'
  const secondary = name && name !== primary ? ` - ${name}` : ''
  return `${location} - ${primary}${secondary}`
}

function getContainerNameFromContent(content: {
  container_label?: string | null
  container_batch_id?: string | null
}): string {
  const label = (content.container_label || '').trim()
  if (label) return label
  const containerId = (content.container_batch_id || '').trim()
  if (!containerId) return ''
  const match = containerBatches.value.find((cb) => cb.id === containerId)
  if (!match) return ''
  return (match.label || match.serial_number || match.material_name || '').trim()
}

function getContainerNamesForSlot(rackId: string, slotId: string): string[] {
  if (!rackId || !slotId || !storageOverviewCache.value) return []
  const rack = storageOverviewCache.value.racks?.find((r) => r.id === rackId)
  const slot = rack?.slots?.find((s) => String(s.id) === String(slotId))
  if (!slot?.contents?.length) return []
  const unique = new Set<string>()
  for (const content of slot.contents) {
    const name = getContainerNameFromContent(content)
    if (name) unique.add(name)
  }
  return Array.from(unique)
}

function formatSlotOptionLabel(rackId: string, slot: StorageSlot): string {
  const labels = getContainerNamesForSlot(rackId, slot.id)
  if (!labels.length) return slot.name
  const preview = labels.slice(0, 2).join(', ')
  const rest = labels.length > 2 ? ` +${labels.length - 2}` : ''
  return `${slot.name} · Kisten: ${preview}${rest}`
}

async function prefetchRackPreview(rackId: string) {
  if (!rackId || rackPreviewTitles.value[rackId]) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const overviewRack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const containerNames = new Set<string>()
  const materialTotals = new Map<string, number>()
  for (const slot of overviewRack?.slots || []) {
    for (const content of slot.contents || []) {
      const containerName = getContainerNameFromContent(content)
      if (containerName) containerNames.add(containerName)
      const materialName = (content.material_name || 'Material').trim()
      materialTotals.set(materialName, (materialTotals.get(materialName) || 0) + Number(content.qty || 0))
    }
  }
  const lines: string[] = []
  const containers = Array.from(containerNames)
  if (containers.length) {
    const preview = containers.slice(0, 3).join(', ')
    const rest = containers.length > 3 ? ` +${containers.length - 3}` : ''
    lines.push(`Kisten: ${preview}${rest}`)
  }
  const materials = Array.from(materialTotals.entries())
    .slice(0, 4)
    .map(([name, qty]) => `${name} (${qty})`)
  if (materials.length) {
    lines.push(...materials)
  }
  if (!lines.length) {
    const data = await getRackContents(rackId).catch(() => null)
    const items = (data?.contents || []).map((c: any) => ({
      material_name: c.material_name || 'Material',
      qty: Number(c.qty || 0),
    }))
    lines.push(buildContentPreviewTitle(items))
  }
  rackPreviewTitles.value = {
    ...rackPreviewTitles.value,
    [rackId]: lines.join('\n'),
  }
}

async function prefetchSlotPreview(rackId: string, slotId: string) {
  if (!rackId || !slotId) return
  const key = `${rackId}:${slotId}`
  if (slotPreviewTitles.value[key]) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const slot = rack?.slots?.find((s) => String(s.id) === String(slotId))
  const items = (slot?.contents || []).map((c) => ({
    material_name: c.material_name || 'Material',
    qty: Number(c.qty || 0),
  }))
  slotPreviewTitles.value = {
    ...slotPreviewTitles.value,
    [key]: buildContentPreviewTitle(items),
  }
}

async function prefetchVisibleRackPreviews(racks: StorageRack[]) {
  const sample = racks.slice(0, 20)
  await Promise.all(sample.map((rack) => prefetchRackPreview(rack.id)))
}

async function prefetchSlotPreviewsForRack(rackId: string) {
  if (!rackId) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  if (!rack?.slots?.length) return
  const next: Record<string, string> = { ...slotPreviewTitles.value }
  for (const slot of rack.slots) {
    const key = `${rackId}:${String(slot.id)}`
    if (next[key]) continue
    const items = (slot.contents || []).map((c) => ({
      material_name: c.material_name || 'Material',
      qty: Number(c.qty || 0),
    }))
    next[key] = buildContentPreviewTitle(items)
  }
  slotPreviewTitles.value = next
}

async function prefetchContainerPreviews() {
  if (!containerBatches.value.length) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const grouped: Record<string, Array<{ material_name: string; qty: number }>> = {}
  for (const rack of storageOverviewCache.value?.racks || []) {
    for (const slot of rack.slots || []) {
      for (const content of slot.contents || []) {
        const containerId = (content.container_batch_id || '').trim()
        if (!containerId) continue
        if (!grouped[containerId]) grouped[containerId] = []
        grouped[containerId].push({
          material_name: content.material_name || 'Material',
          qty: Number(content.qty || 0),
        })
      }
    }
  }
  const next: Record<string, string> = { ...containerPreviewTitles.value }
  for (const cb of containerBatches.value) {
    if (next[cb.id]) continue
    next[cb.id] = buildContentPreviewTitle(grouped[cb.id] || [])
  }
  containerPreviewTitles.value = next
}

function getContainerPreviewTitle(containerBatchId: string): string {
  if (!containerBatchId) return 'Kiste wählen'
  return containerPreviewTitles.value[containerBatchId] || 'Hover lädt Inhalt...'
}

function getRackPreviewTitle(rackId: string): string {
  if (!rackId) return 'Gestell wählen'
  return rackPreviewTitles.value[rackId] || 'Hover lädt Inhalt...'
}

function getSlotPreviewTitle(rackId: string, slotId: string): string {
  if (!slotId) return 'Fach wählen'
  return slotPreviewTitles.value[`${rackId}:${slotId}`] || 'Hover lädt Inhalt...'
}

async function onStockRackChange() {
  formData.slot_id = ''
  if (!formData.rack_id) {
    storageSlots.value = []
    return
  }
  await loadSlotsForRack(formData.rack_id)
  await prefetchSlotPreviewsForRack(formData.rack_id)
  storageSlots.value = slotsByRackId.value[formData.rack_id] || []
}

async function addRackCategory() {
  const rackName = formData.location_rack.trim()
  if (rackName.length < 2) {
    toast.error('Bitte zuerst einen Gestell-Namen eingeben.')
    return
  }
  if (!formData.storage_address_id) {
    toast.error('Bitte zuerst einen Lagerstandort wählen.')
    return
  }

  try {
    const existingRack = storageRacks.value.find((rack) =>
      normalizeName(rack.name) === normalizeName(rackName)
    )
    if (existingRack) {
      formData.location_rack = existingRack.name
      formData.rack_id = existingRack.id
      await loadSlotsForRack(existingRack.id)
      storageSlots.value = slotsByRackId.value[existingRack.id] || []
      formData.slot_id = storageSlots.value[0]?.id || ''
      searchRackCategories()
      toast.success('Gestell ist bereits vorhanden.')
      return
    }

    const createdRack = await createStorageRack({
      department_id: props.departmentId,
      name: rackName,
      storage_address_id: formData.storage_address_id,
    })

    storageRacks.value = await getStorageRacks(props.departmentId, formData.storage_address_id || undefined).catch(() => [])
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchContainerPreviews()
    formData.location_rack = createdRack.name
    formData.rack_id = createdRack.id
    await loadSlotsForRack(createdRack.id)
    storageSlots.value = slotsByRackId.value[createdRack.id] || []
    formData.slot_id = storageSlots.value[0]?.id || ''
    searchRackCategories()
    toast.success('Gestell wurde erstellt.')
  } catch (err: any) {
    toast.error(err?.response?.data?.error || 'Gestell konnte nicht erstellt werden.')
  }
}

function openAddCategoryModal() {
  categoryModalDefaultName.value = categorySearch.value.trim()
  showCategoryModal.value = true
}

async function handleCategorySaved(newCategory: Category) {
  showCategoryModal.value = false
  
  // Kategorien neu laden
  const categoriesResult = await getCategories(props.departmentId).catch(() => [])
  allCategories.value = categoriesResult || []
  searchRackCategories()
  
  // Neue Kategorie auswählen
  selectCategory(newCategory)
  categoryModalDefaultName.value = ''
}

// Lieferant Suche
function searchSuppliers() {
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
  supplierSearch.value = addr.name || addr.company || ''
  showSupplierDropdown.value = false
  formData.supplier_id = addr.id
}

function clearSupplier() {
  selectedSupplier.value = null
  supplierSearch.value = ''
  formData.supplier_id = ''
}

function hideSupplierDropdownDelayed() {
  setTimeout(() => { showSupplierDropdown.value = false }, 200)
}

// Kombinations-Material Suche
function searchComboMaterials() {
  const query = comboMaterialSearch.value.toLowerCase().trim()
  if (query.length < 3) {
    filteredComboMaterials.value = []
    return
  }
  
  const selectedIds = selectedComboMaterials.value.map(m => m.id)
  
  filteredComboMaterials.value = allMaterials.value
    .filter(m => 
      !selectedIds.includes(m.id) &&
      (m.name.toLowerCase().includes(query) || m.category?.name?.toLowerCase().includes(query))
    )
    .slice(0, 10)
}

function addComboMaterial(mat: any) {
  selectedComboMaterials.value.push({
    id: mat.id,
    name: mat.name,
    category: mat.category,
    total_stock: mat.total_stock,
    qty: 1
  })
  comboMaterialSearch.value = ''
  filteredComboMaterials.value = []
}

function removeComboMaterial(index: number) {
  selectedComboMaterials.value.splice(index, 1)
}

// Adress-Modal öffnen
function openAddStorageModal() {
  addressModalType.value = 'storage'
  addressModalDefaultName.value = ''
  showAddressModal.value = true
}

function openAddManufacturerModal() {
  addressModalType.value = 'manufacturer'
  addressModalDefaultName.value = manufacturerSearch.value.trim()
  showAddressModal.value = true
}

function openAddSupplierModal() {
  addressModalType.value = 'supplier'
  addressModalDefaultName.value = supplierSearch.value.trim()
  showAddressModal.value = true
}

async function handleAddressSaved() {
  const savedName = addressModalDefaultName.value.toLowerCase()
  const savedType = addressModalType.value
  
  showAddressModal.value = false
  await loadData()
  
  // Automatisch die neu erstellte Adresse auswählen
  if (savedName && savedType !== 'storage') {
    const newAddress = allSuppliers.value.find(a => 
      (a.name?.toLowerCase() === savedName) || 
      (a.company?.toLowerCase() === savedName)
    )
    
    if (newAddress) {
      if (savedType === 'manufacturer') {
        selectManufacturer(newAddress)
      } else if (savedType === 'supplier') {
        selectSupplier(newAddress)
      }
    }
  }
  
  addressModalDefaultName.value = ''
}

// ============ Template-Funktionen ============
function searchTemplates() {
  const query = templateSearch.value.toLowerCase().trim()
  if (!query) {
    filteredTemplateList.value = availableTemplates.value.slice(0, 10)
    return
  }
  filteredTemplateList.value = availableTemplates.value
    .filter(t => 
      t.name.toLowerCase().includes(query) || 
      (t.manufacturer?.toLowerCase().includes(query)) ||
      (t.model?.toLowerCase().includes(query))
    )
    .slice(0, 10)
}

async function selectTemplate(template: Template) {
  templateSearch.value = template.name
  showTemplateDropdown.value = false

  try {
    // Lade die vollständige Vorlage mit Komponenten
    const fullTemplate = await getTemplate(template.id)
    selectedTemplate.value = fullTemplate
    isFromTemplate.value = true
    templateComponents.value = fullTemplate.components || []

    // Formular vorausfüllen
    formData.material_type = fullTemplate.material_type || 'physical_combo'
    if (fullTemplate.category) {
      formData.category_id = fullTemplate.category.id
      selectedCategory.value = { 
        id: fullTemplate.category.id, 
        name: fullTemplate.category.name, 
        parent_id: null,
        material_count: 0 
      } as Category
      categorySearch.value = fullTemplate.category.name
    }

    // Zelt-Details
    tentForm.tent_type = fullTemplate.tent_type || 'gruppenzelt'
    tentForm.tent_capacity = fullTemplate.capacity || null
    tentForm.reservation_mode = fullTemplate.reservation_mode || 'complete_only'

    // Hersteller aus Vorlage automatisch übernehmen (bleibt manuell änderbar)
    applyTemplateManufacturer(fullTemplate.manufacturer)

    // Komponenten-Eingaben initialisieren
    componentInputs.value = (fullTemplate.components || []).map(comp => {
      const isBulk = comp.tracking === 'bulk'
      const defaultMode = formData.material_type === 'physical_combo' ? 'fixed' : (isBulk ? 'bulk' : 'assigned')

      // Für Bulk: automatisch passendes Material im Lager suchen
      let autoMaterial: any = null
      if (isBulk) {
        autoMaterial = allMaterials.value.find(m =>
          m.material_type === 'physical' &&
          m.name.toLowerCase() === comp.name.toLowerCase()
        )
      }

      return {
        component_type: comp.component_type,
        name: comp.name,
        tracking: comp.tracking,
        required_qty: comp.required_qty,
        is_optional: comp.is_optional,
        mode: 'new',
        serial_number: '',
        qty: comp.required_qty,
        unit_price: '',
        material_id: autoMaterial?.id || '',
        batch_id: '',
        assignment_mode: defaultMode,
        // UI-Hilfsfelder
        _materialSearch: autoMaterial?.name || '',
        _showDropdown: false,
        _filteredMaterials: [],
        _selectedMaterial: autoMaterial || null,
        _availableBatches: [],
      } as ComponentInput
    })

    // Nach Vorlagenwahl immer zuerst Allgemeine Informationen zeigen
    activeStep.value = 'general'
  } catch (err) {
    console.error('Fehler beim Laden der Vorlage:', err)
  }
}

function onRackContentsRackChange() {
  selectedRackContents.value = null
  isFromRackContents.value = false
  componentInputs.value = []
}

function onRackIdChange(v: string) {
  rackContentsRackId.value = v
  onRackContentsRackChange()
}

async function loadRackContents() {
  if (!rackContentsRackId.value) return
  isLoadingRackContents.value = true
  selectedRackContents.value = null
  try {
    const data = await getRackContents(rackContentsRackId.value)
    selectedRackContents.value = data
    isFromRackContents.value = true
    isFromTemplate.value = false
    selectedTemplate.value = null

    const rack = storageRacks.value.find(r => r.id === rackContentsRackId.value)
    formData.name = (rack?.name || data.rack_name) + ' komplett'

    componentInputs.value = data.contents.map((c) => {
      const isBulk = c.tracking_type !== 'serialized'
      const mat = allMaterials.value.find(m => m.id === c.material_id)
      const defaultMode = formData.material_type === 'physical_combo' ? 'fixed' : (isBulk ? 'bulk' : 'assigned')
      return {
        component_type: (c.material_name || 'material').toLowerCase().replace(/\s+/g, '_'),
        name: c.material_name,
        tracking: (c.tracking_type || 'bulk') as 'serialized' | 'bulk',
        required_qty: c.qty,
        is_optional: false,
        mode: 'existing' as const,
        serial_number: '',
        qty: c.qty,
        unit_price: '',
        material_id: c.material_id,
        batch_id: '',
        assignment_mode: defaultMode,
        _materialSearch: mat?.name || c.material_name,
        _showDropdown: false,
        _filteredMaterials: [],
        _selectedMaterial: mat || null,
        _availableBatches: [],
      } as ComponentInput
    })

    activeStep.value = 'general'
  } catch (err) {
    console.error('Fehler beim Laden des Lagerplatz-Inhalts:', err)
    toast.error('Inhalt konnte nicht geladen werden')
  } finally {
    isLoadingRackContents.value = false
  }
}

async function selectCreationMode(mode: 'individual' | 'physical_combo' | 'virtual_combo') {
  creationMode.value = mode
  // Bei Combo-Modus: alle Lagerplätze laden (für "Aus Lagerplatz übernehmen")
  if (mode === 'physical_combo' || mode === 'virtual_combo') {
    storageRacks.value = await getStorageRacks(props.departmentId).catch(() => [])
  }
  // Material-Typ automatisch setzen
  if (mode === 'individual') {
    formData.material_type = 'physical'
    // Vorherige Auswahlreste entfernen, damit der Wizard bei "Allgemeine Informationen" startet
    formData.tracking_type = ''
    formData.category_id = ''
    selectedCategory.value = null
    categorySearch.value = ''
    // Bei Einzelartikel: Name ist nicht erforderlich, setze Platzhalter
    formData.name = ''
  } else if (mode === 'physical_combo') {
    formData.material_type = 'physical_combo'
  } else {
    formData.material_type = 'virtual_combo'
  }

  // Nach Moduswahl zuerst mit den allgemeinen Infos starten
  activeStep.value = 'general'

  // Bei Einzelartikel direkt in das Artikelnamen-Feld springen
  if (mode === 'individual') {
    nextTick(() => {
      articleNameInputRef.value?.focus()
      articleNameInputRef.value?.select()
    })
  }
}

function resetCreationMode() {
  // Gesamten Zustand zurücksetzen wenn Modus geändert wird
  clearTemplate()
  creationMode.value = ''
  formData.material_type = ''
  formData.name = ''
}

function clearTemplate() {
  isFromTemplate.value = false
  selectedTemplate.value = null
  templateSearch.value = ''
  templateComponents.value = []
  componentInputs.value = []
  tentForm.tent_type = ''
  tentForm.tent_capacity = null
  tentForm.reservation_mode = 'complete_only'
  // Reset auch die automatisch gesetzten Felder
  formData.category_id = ''
  selectedCategory.value = null
  categorySearch.value = ''
}

function hideTemplateDropdownDelayed() {
  setTimeout(() => { showTemplateDropdown.value = false }, 200)
}

// ============ "Aus Bestand" Funktionen pro Komponente ============
function searchExistingMaterial(ci: ComponentInput) {
  const query = (ci._materialSearch || '').toLowerCase().trim()
  if (query.length < 2) {
    ci._filteredMaterials = []
    return
  }
  // Suche im bereits geladenen Material-Bestand
  ci._filteredMaterials = allMaterials.value
    .filter(m =>
      m.material_type === 'physical' &&
      (m.total_stock > 0 || m.free_stock > 0) &&
      (m.name.toLowerCase().includes(query) || m.category?.name?.toLowerCase().includes(query))
    )
    .slice(0, 10)
}

async function selectExistingMaterial(ci: ComponentInput, mat: any) {
  ci.material_id = mat.id
  ci._selectedMaterial = mat
  ci._materialSearch = mat.name
  ci._showDropdown = false
  ci._filteredMaterials = []

  // Für serialisierte Teile: Batches laden
  if (ci.tracking === 'serialized') {
    try {
      const fullMaterial = await getMaterial(mat.id)
      // Nur aktive Batches mit SN und Status "ok" anzeigen
      ci._availableBatches = (fullMaterial.batches || []).filter((b: any) =>
        b.status === 'ok' && b.serial_number
      )
    } catch (err) {
      console.error('Fehler beim Laden der Batches:', err)
      ci._availableBatches = []
    }
  }
}

function clearExistingMaterial(ci: ComponentInput) {
  ci.material_id = ''
  ci.batch_id = ''
  ci._selectedMaterial = null
  ci._materialSearch = ''
  ci._availableBatches = []
}

function hideCompDropdownDelayed(ci: ComponentInput) {
  setTimeout(() => { ci._showDropdown = false }, 200)
}

// Automatische Suche beim Fokussieren des Bulk-Suchfelds
function autoSearchBulk(ci: ComponentInput) {
  if (!ci._materialSearch) {
    ci._materialSearch = ci.name
  }
  searchExistingMaterial(ci)
}

// Bulk-Material auswählen (vereinfacht, kein Batch nötig)
function selectBulkMaterial(ci: ComponentInput, mat: any) {
  ci.material_id = mat.id
  ci._selectedMaterial = mat
  ci._materialSearch = mat.name
  ci._showDropdown = false
  ci._filteredMaterials = []
}

function isComponentDone(ci: ComponentInput): boolean {
  if (ci.mode === 'new') {
    return ci.tracking === 'serialized' ? !!ci.serial_number : ci.qty > 0
  }
  // existing
  if (!ci.material_id) return false
  if (ci.tracking === 'serialized') return !!ci.batch_id
  return ci.qty > 0
}

function buildCombinedLocation(): string | null {
  const selectedRack = formData.rack_id ? storageRacks.value.find((entry) => entry.id === formData.rack_id)?.name || '' : ''
  const selectedSlot = formData.slot_id ? storageSlots.value.find((entry) => entry.id === formData.slot_id)?.name || '' : ''
  const rack = (selectedRack || formData.location_rack).trim()
  const slot = (selectedSlot || formData.location_slot).trim()
  if (!rack && !slot) return null
  if (rack && slot) return `${rack} / ${slot}`
  return rack || slot
}

async function ensureStorageSelection(): Promise<void> {
  if (!formData.rack_id) {
    formData.slot_id = ''
    formData.location_rack = ''
    formData.location_slot = ''
    storageSlots.value = []
    return
  }

  storageSlots.value = await getStorageSlots(formData.rack_id).catch(() => [])
  if (formData.slot_id && !storageSlots.value.some((entry) => entry.id === formData.slot_id)) {
    formData.slot_id = ''
  }

  const rackName = storageRacks.value.find((entry) => entry.id === formData.rack_id)?.name || ''
  const slotName = formData.slot_id
    ? storageSlots.value.find((entry) => entry.id === formData.slot_id)?.name || ''
    : ''
  formData.location_rack = rackName
  formData.location_slot = slotName
}

async function handleSubmit() {
  if (!canSubmit.value || isSubmitting.value) return
  
  isSubmitting.value = true
  
  try {
    let successMessage = 'Material erstellt'
    const combinedLocation = buildCombinedLocation()
    const includeExpiryDate = formData.tracking_type !== 'serialized' && (formData.is_food || showExpiryDateForNonFood.value)
    const expiryDatePayload = includeExpiryDate ? (formData.expiry_date || undefined) : undefined
    if (!formData.split_allocations && formData.stock_location_mode === 'slot') {
      await ensureStorageSelection()
    }

    // Im Batch-Modus: Batch zu bestehendem Material hinzufügen
    if (isAddBatchMode.value && selectedExistingMaterial.value) {
      const batchPayload: AddBatchRequest = {
        qty: formData.initial_qty,
        acquired_on: formData.purchase_date || undefined,
        expiry_date: expiryDatePayload || null,
        unit_price: formData.unit_price > 0 ? String(formData.unit_price) : null,
        supplier_id: formData.supplier_id || null,
        ...(formData.split_allocations && hasRelevantAllocationRows.value && allocationSumValid.value && !hasInvalidAllocationRows.value
          ? {
              allocations: initialAllocations.value
                .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                .map((r) =>
                  r.mode === 'kiste'
                    ? { container_batch_id: r.container_batch_id, qty: r.qty }
                    : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                )
            }
          : {
              rack_id: formData.rack_id || null,
              slot_id: formData.slot_id || null
            }),
      }
      
      await addBatch(selectedExistingMaterial.value.id, batchPayload)
      
      // Material mit neuen Daten emittieren
      emit('created', { 
        ...selectedExistingMaterial.value, 
        total_stock: selectedExistingMaterial.value.total_stock + formData.initial_qty 
      })
      successMessage = 'Bestand erfolgreich hinzugefügt'
    } else if (isFromRackContents.value && rackContentsRackId.value) {
      // Combo aus Lagerplatz-Inhalt erstellen
      const result = await createComboFromRack({
        rack_id: rackContentsRackId.value,
        name: formData.name.trim(),
        department_id: props.departmentId,
        material_type: formData.material_type === 'virtual_combo' ? 'virtual_combo' : 'physical_combo',
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        reservation_mode: tentForm.reservation_mode || 'complete_only',
        purchase_date: formData.purchase_date || undefined,
      })
      emit('created', result)
      successMessage = 'Combo aus Lagerplatz erstellt'
    } else if (creationMode.value === 'virtual_combo' && !isFromTemplate.value) {
      // Virtuelle Kombo ohne Vorlage → direkt als Material erstellen
      const payload: CreateMaterialRequest = {
        department_id: props.departmentId,
        name: formData.name.trim(),
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        location: combinedLocation,
        material_type: 'virtual_combo',
        reservation_mode: tentForm.reservation_mode || 'complete_only',
        description: formData.description || null,
        barcode_tag: formData.barcode_tag || null,
        manufacturer: formData.manufacturer || null,
        model: formData.model || null,
        ean: formData.ean || null,
        weight: formData.weight || null,
        color: formData.color || null,
        size_length: formData.size_length || null,
        size_width: formData.size_width || null,
        size_height: formData.size_height || null,
        warranty_until: formData.warranty_until || null,
        rental_price_day: formData.rental_price_day || null,
        rental_price_week: formData.rental_price_week || null,
        rental_price_month: formData.rental_price_month || null,
        rental_deposit: formData.rental_deposit || null,
        rental_lead_days: formData.rental_lead_days,
        rental_max_days: formData.rental_max_days,
        rental_external_allowed: formData.rental_external_allowed,
        rental_requires_approval: formData.rental_requires_approval,
        rental_notes: formData.rental_notes || null,
        is_js_material: formData.is_js_material,
        external_source: formData.is_js_material ? (formData.external_source || 'js_ch') : null,
      }
      const result = await createMaterial(payload)
      emit('created', result)
      successMessage = 'Virtuelle Kombo erstellt'
    } else if (isFromTemplate.value && selectedTemplate.value) {
      // Template-Modus: Material aus Vorlage erstellen
      const mode = creationMode.value as 'individual' | 'physical_combo' | 'virtual_combo'

      // Komponenten-Daten zusammenstellen (bei virtual_combo minimal)
      const components: CreateMaterialComponentInput[] = mode === 'virtual_combo' 
        ? componentInputs.value.map(ci => ({
            component_type: ci.component_type,
            mode: 'new' as const,
            qty: ci.required_qty,
          }))
        : componentInputs.value.map(ci => {
            const comp: CreateMaterialComponentInput = {
              component_type: ci.component_type,
              mode: ci.mode,
              assignment_mode: ci.assignment_mode,
            }
            if (ci.mode === 'new') {
              if (ci.tracking === 'serialized') {
                comp.serial_number = ci.serial_number
              } else {
                comp.qty = ci.qty
              }
              if (ci.unit_price) {
                comp.unit_price = ci.unit_price
              }
            } else {
              if (ci.material_id) comp.material_id = ci.material_id
              if (ci.batch_id) comp.batch_id = ci.batch_id
              if (ci.tracking === 'bulk') comp.qty = ci.qty
            }
            return comp
          })

      const templatePayload: any = {
        department_id: props.departmentId,
        creation_mode: mode,
        storage_address_id: formData.storage_address_id || undefined,
        category_id: formData.category_id || undefined,
        purchase_date: formData.purchase_date || undefined,
        supplier_id: formData.supplier_id || undefined,
        manufacturer: formData.manufacturer || undefined,
        components
      }

      // Name nur bei Kombo-Modi
      if (mode !== 'individual') {
        templatePayload.name = formData.name.trim()
      }

      // Zelt-Details nur bei Kombo-Modi
      if (mode !== 'individual') {
        templatePayload.tent_type = tentForm.tent_type || undefined
        templatePayload.tent_capacity = tentForm.tent_capacity || undefined
      }

      // Reservation-Mode nur bei virtueller Kombo
      if (mode === 'virtual_combo') {
        templatePayload.reservation_mode = tentForm.reservation_mode || undefined
      }

      const result = await createMaterialFromTemplate(selectedTemplate.value.id, templatePayload)
      
      // Bei individual: emittieren wir ein Fake-Material-Objekt
      if (mode === 'individual' && result.articles) {
        emit('created', { id: result.articles[0]?.id, name: 'Einzelartikel erstellt' } as any)
        successMessage = 'Einzelartikel aus Vorlage erstellt'
      } else if (result.material) {
        emit('created', result.material as any)
        successMessage = mode === 'physical_combo'
          ? 'Physische Kombo aus Vorlage erstellt'
          : 'Virtuelle Kombo aus Vorlage erstellt'
      }
    } else {
      // Normaler Modus: Neues Material erstellen
      const payload: CreateMaterialRequest = {
        department_id: props.departmentId,
        name: formData.name.trim(),
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        location: combinedLocation,
        manufacturer: formData.manufacturer || null,
        material_type: formData.material_type || 'physical',
        tracking_type: formData.tracking_type || null,
        is_consumable: formData.is_consumable,
        is_food: formData.is_food,
        is_js_material: formData.is_js_material,
        external_source: formData.is_js_material ? (formData.external_source || 'js_ch') : null,
        sale_price: formData.sale_price ? String(formData.sale_price) : null,
        min_stock: formData.min_stock,
        pack_size: formData.pack_size && formData.pack_size >= 2 ? formData.pack_size : null,
        pack_unit: formData.pack_unit || null,
        initial_acquired_on: formData.purchase_date,
        initial_expiry_date: expiryDatePayload,
        initial_unit_price: formData.unit_price > 0 ? String(formData.unit_price) : undefined,
        initial_supplier_id: formData.supplier_id || undefined,
        ...(formData.split_allocations && hasRelevantAllocationRows.value && allocationSumValid.value && !hasInvalidAllocationRows.value
          ? {
              initial_allocations: initialAllocations.value
                .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                .map((r) =>
                  r.mode === 'kiste'
                    ? { container_batch_id: r.container_batch_id, qty: r.qty }
                    : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                )
            }
          : formData.stock_location_mode === 'kiste' && formData.stock_container_batch_id
            ? { initial_container_batch_id: formData.stock_container_batch_id }
            : {
                initial_rack_id: formData.rack_id || undefined,
                initial_slot_id: formData.slot_id || undefined
              }),
        // Details (wie in MaterialDetailView)
        description: formData.description || null,
        barcode_tag: formData.barcode_tag || null,
        model: formData.model || null,
        ean: formData.ean || null,
        weight: formData.weight || null,
        color: formData.color || null,
        size_length: formData.size_length || null,
        size_width: formData.size_width || null,
        size_height: formData.size_height || null,
        warranty_until: formData.warranty_until || null,
        // Vermietung
        rental_price_day: formData.rental_price_day || null,
        rental_price_week: formData.rental_price_week || null,
        rental_price_month: formData.rental_price_month || null,
        rental_deposit: formData.rental_deposit || null,
        rental_lead_days: formData.rental_lead_days,
        rental_max_days: formData.rental_max_days,
        rental_external_allowed: formData.rental_external_allowed,
        rental_requires_approval: formData.rental_requires_approval,
        rental_notes: formData.rental_notes || null,
      }
      
      // Bei serialisierten Artikeln: Seriennummern mitsenden
      if (formData.tracking_type === 'serialized' && serialNumbers.value.length > 0) {
        payload.serial_numbers = serialNumbers.value
          .filter(s => s.serial_number.trim())
          .map(s => ({
            serial_number: s.serial_number.trim(),
            label: s.label?.trim() || '',
            notes: s.notes || '',
            ...(serialLocationSameForAll.value
              ? {}
              : (s.location_mode === 'kiste'
                ? { container_batch_id: s.container_batch_id || undefined }
                : { rack_id: s.rack_id || undefined, slot_id: s.slot_id || undefined }))
          }))
      } else {
        // Bei Massenartikeln: normale Menge
        payload.initial_qty = formData.initial_qty > 0 ? formData.initial_qty : undefined
      }
      
      const material = await createMaterial(payload)
      emit('created', material)
      successMessage = 'Material erstellt'
    }

    toast.success(successMessage)
    
    if (createAnother.value) {
      resetForm()
      nextTick(() => {
        scrollCreationModeIntoView()
      })
    } else {
      handleClose()
    }
  } catch (err: any) {
    console.error('Fehler beim Erstellen:', err)
    toast.error(err?.response?.data?.error || err?.message || (isAddBatchMode.value ? 'Fehler beim Hinzufügen des Bestands' : 'Fehler beim Erstellen des Materials'))
  } finally {
    isSubmitting.value = false
  }
}

watch(() => props.modelValue, (val) => {
  if (!val) return
  void initializeOnOpen()
})

function scrollCreationModeIntoView(): void {
  const formEl = wizardFormRef.value
  if (!formEl) return

  const stepEl = formEl.querySelector('.step-section[data-step="creation_mode"]') as HTMLElement | null
  if (stepEl) {
    formEl.scrollTop = Math.max(stepEl.offsetTop - 8, 0)
    return
  }
  formEl.scrollTop = 0
}

async function initializeOnOpen(): Promise<void> {
  const runId = ++openInitRunId
  resetForm()
  await nextTick()
  if (runId !== openInitRunId) return
  scrollCreationModeIntoView()

  await loadData()
  if (runId !== openInitRunId) return
  await nextTick()
  if (runId !== openInitRunId) return
  scrollCreationModeIntoView()
}

// Accordion: immer den zuletzt verfügbaren Schritt öffnen
watch(visibleStepIds, (steps) => {
  if (steps.length === 0) {
    activeStep.value = ''
    return
  }

  const lastVisibleStep = steps[steps.length - 1]
  // Während Name-Eingabe/Suche in "Allgemeine Informationen" nicht automatisch springen.
  if (
    activeStep.value === 'general' &&
    steps.includes('general') &&
    (isNameInputFocused.value || showNameSuggestions.value)
  ) {
    return
  }

  // Sonst auf den zuletzt freigeschalteten Schritt springen (Accordion: ein Schritt offen).
  if (!activeStep.value || !steps.includes(activeStep.value as StepId) || activeStep.value !== lastVisibleStep) {
    activeStep.value = lastVisibleStep
  }
}, { immediate: true })

watch(() => formData.is_food, (isFood) => {
  if (!isFood) return
  showExpiryDateForNonFood.value = false
  // Esswaren bleiben ein physischer Einzelartikel.
  formData.material_type = 'physical'
  selectedComboMaterials.value = []
  comboMaterialSearch.value = ''
  // Esswaren dürfen nur als Massenartikel geführt werden.
  formData.tracking_type = 'bulk'
  applyFoodCategoryIfAvailable()
}, { immediate: true })

watch(showExpiryDateForNonFood, (enabled) => {
  if (!enabled && !formData.is_food) {
    formData.expiry_date = ''
  }
})

watch(allCategories, () => {
  applyFoodCategoryIfAvailable()
})

watch(() => formData.split_allocations, (isSplit) => {
  if (isSplit) {
    normalizeAllocationRowsToTotal()
    return
  }
  initialAllocations.value = []
})

watch(() => formData.initial_qty, () => {
  normalizeAllocationRowsToTotal()
})

watch(stockInputReady, (ready) => {
  if (!ready) {
    formData.split_allocations = false
  }
})

watch(() => formData.storage_address_id, async () => {
  storageRacks.value = await getStorageRacks(props.departmentId, formData.storage_address_id || undefined).catch(() => [])
  await prefetchVisibleRackPreviews(storageRacks.value)
  formData.rack_id = ''
  formData.slot_id = ''
  formData.location_rack = ''
  formData.location_slot = ''
  storageSlots.value = []
  slotsByRackId.value = {}
  saveWizardStockPrefs()
})

watch(
  () => [
    formData.stock_location_mode,
    autoGenPrefix.value,
    autoGenStart.value,
    autoGenPad.value,
    autoGenCount.value,
    serialAutoGenerateEnabled.value,
    serialLocationSameForAll.value,
  ] as const,
  () => {
    saveWizardStockPrefs()
  }
)

onMounted(() => {
  if (props.modelValue) {
    void initializeOnOpen()
  }
})
</script>
