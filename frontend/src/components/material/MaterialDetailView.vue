<template>
  <div class="material-detail-view">
    <!-- Header mit Schließen/Speichern -->
    <header class="detail-header">
      <div class="header-left">
        <button class="back-btn" @click="handleClose">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          {{ t('components.materialDetail.backToList') }}
        </button>
        <div class="header-title">
          <span v-if="material.barcode_tag" class="material-code">{{ material.barcode_tag }}</span>
          <h1>{{ material.name }}</h1>
          <span v-if="material.open_loss_reports > 0" class="loss-report-badge">
            {{ t('components.materialDetail.lossReportBadge', { detail: openLossLabel }) }}
          </span>
        </div>
      </div>
      <div class="header-actions">
        <button
          v-if="showGenerateQrButton"
          class="btn-outline btn-sm"
          :disabled="isGeneratingPublicCode"
          :title="qrGenerateButtonTitle"
          @click="generateMaterialPublicCode"
        >
          {{ qrGenerateButtonLabel }}
        </button>
        <PublicQrTag
          v-if="headerMaterialHasPublicQr"
          class="header-qr-tag"
          :url="material.public_url"
          :code="material.public_code"
          :size="64"
          :clickable="true"
          :image-label="material.name"
          :image-entity-id="material.id"
          @activate="openQrActionModalForMaterial"
        />
        <button
          v-else-if="showHeaderSerialQrShortcut"
          type="button"
          class="btn-outline btn-sm header-qr-serial-shortcut"
          :title="t('components.materialDetail.titleOpenSerialQr')"
          @click="openQrActionModalForAll"
        >
          {{ t('components.materialDetail.qrCodes') }}
        </button>
        <button class="btn-outline" @click="handleClose">{{ t('components.materialDetail.close') }}</button>
        <button class="btn-primary" @click="save" :disabled="!hasChanges || isSaving">
          {{ isSaving ? t('common.saving') : t('common.save') }}
        </button>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-container">
      <div class="spinner"></div>
      <p>{{ t('components.materialDetail.loading') }}</p>
    </div>

    <!-- Content -->
    <div v-else class="detail-content">
      <!-- Tab Navigation -->
      <nav class="tab-nav">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === tab.id }"
          @click="setActiveTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </nav>

      <!-- Main Layout -->
      <div class="content-layout">
        <!-- Main Content (Left) -->
        <main class="content-main">
          <!-- Tab: Daten -->
          <section v-if="activeTab === 'data'" class="tab-content">
            <div class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionMaterial') }}</h2>
              
              <div class="form-grid">
                <div class="form-group span-2">
                  <label>{{ t('components.materialDetail.labelNameDb') }}</label>
                  <input v-model="formData.name" type="text" class="form-input" />
                </div>
                
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelCode') }} <span class="optional">{{ t('components.materialDetail.optionalShort') }}</span></label>
                  <input v-model="formData.barcode_tag" type="text" class="form-input" :placeholder="t('components.materialDetail.codePlaceholder')" />
                </div>
                
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelCategory') }}</label>
                  <CategoryAutocompleteInput
                    v-model="formData.category_id"
                    :categories="categories"
                    :department-id="departmentId"
                    @reload-categories="handleCategoryReloadFromPicker"
                  />
                </div>
                
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelManufacturer') }}</label>
                  <input v-model="formData.manufacturer" type="text" class="form-input" />
                </div>
                
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelModel') }}</label>
                  <input v-model="formData.model" type="text" class="form-input" />
                </div>
              </div>
            </div>

            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionProperties') }}</h2>
                <span class="property-badge">{{ propertyBadgeText }}</span>
              </div>
              
              <div class="properties-grid">
                <div class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.propPhysicalVirtual') }}</span>
                  <span class="property-value">{{ t('components.materialDetail.propPhysicalMaterials') }}</span>
                </div>
                <div class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.propRentalSale') }}</span>
                  <span class="property-value">{{ t('components.materialDetail.propRental') }}</span>
                </div>
                <div
                  v-if="material.tracking_type === 'bulk'"
                  class="property-item property-item--checkbox"
                >
                  <label class="checkbox-label property-checkbox-label">
                    <input type="checkbox" v-model="formData.is_container" />
                    <span>{{ t('components.materialDetail.containerCheckbox') }}</span>
                  </label>
                  <p class="form-hint text-muted mt-1">
                    {{ t('components.materialDetail.containerCheckboxHint') }}
                  </p>
                </div>
                <div class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.labelSource') }}</span>
                  <span class="property-value">{{ formData.is_js_material ? t('components.materialDetail.sourceJs') : t('components.materialDetail.sourceInternal') }}</span>
                </div>
              </div>

              <div v-if="canManageJsMaterial" class="checkbox-group mt-4">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="formData.is_js_material" />
                  <span>{{ t('components.materialDetail.jsMaterialGlobal') }}</span>
                </label>
                <div v-if="formData.is_js_material" class="form-group mt-2">
                  <label>{{ t('components.materialDetail.labelExternalSource') }}</label>
                  <input v-model="formData.external_source" type="text" class="form-input" :placeholder="t('components.materialDetail.externalSourcePlaceholder')" />
                </div>
              </div>
              <div v-else-if="formData.is_js_material" class="form-group mt-4">
                <label>{{ t('components.materialDetail.labelExternalSource') }}</label>
                <input :value="formData.external_source || 'js_ch'" type="text" class="form-input" disabled />
              </div>
              
              <!-- Reservation-Modus (bei Zelt/Kombo-Materialien) -->
              <div v-if="material.is_container || material.material_type === 'physical_combo' || material.material_type === 'virtual_combo'" class="form-grid mt-4">
                <div class="form-group span-2">
                  <label>{{ t('components.materialDetail.labelReservationMode') }}</label>
                  <select v-model="formData.reservation_mode" class="form-select">
                    <option value="">{{ t('components.materialDetail.reservationUnset') }}</option>
                    <option value="complete_only">{{ t('components.materialDetail.reservationComplete') }}</option>
                    <option value="individual">{{ t('components.materialDetail.reservationIndividual') }}</option>
                    <option value="flexible">{{ t('components.materialDetail.reservationFlexible') }}</option>
                  </select>
                  <p class="form-hint" v-if="formData.reservation_mode === 'complete_only'">{{ t('components.materialDetail.hintReservationComplete') }}</p>
                  <p class="form-hint" v-else-if="formData.reservation_mode === 'individual'">{{ t('components.materialDetail.hintReservationIndividual') }}</p>
                  <p class="form-hint" v-else-if="formData.reservation_mode === 'flexible'">{{ t('components.materialDetail.hintReservationFlexible') }}</p>
                </div>
              </div>

              <div
                v-if="material.material_type === 'physical_combo' && material.linked_container_batch"
                class="linked-kiste-banner mt-4"
              >
                <span class="linked-kiste-label">{{ t('components.materialDetail.refKisteLabel') }}</span>
                <p class="linked-kiste-desc">
                  {{ t('components.materialDetail.refKisteDesc') }}
                </p>
                <router-link
                  class="linked-kiste-link"
                  :to="`/${departmentId}/materials/${material.linked_container_batch.material_id}`"
                >
                  {{ material.linked_container_batch.display_label }}
                </router-link>
              </div>
            </div>

            <div class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionDetails') }}</h2>
              
              <div class="form-grid">
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelEan') }}</label>
                  <input v-model="formData.ean" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelWeightKg') }}</label>
                  <input v-model="formData.weight" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelColor') }}</label>
                  <input v-model="formData.color" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelLengthCm') }}</label>
                  <input v-model="formData.size_length" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelWidthCm') }}</label>
                  <input v-model="formData.size_width" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelHeightCm') }}</label>
                  <input v-model="formData.size_height" type="text" class="form-input" />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelWarranty') }}</label>
                  <input v-model="formData.warranty_until" type="date" class="form-input" />
                </div>
              </div>

              <div class="form-group span-full mt-4">
                <label>{{ t('components.materialDetail.labelDescription') }}</label>
                <textarea v-model="formData.description" class="form-textarea" rows="3"></textarea>
              </div>
            </div>

            <!-- Verpackungseinheit (bei Verbrauch/Essen siehe Kosten) -->
            <div v-if="!material.is_consumable && !material.is_food" class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionPackaging') }}</h2>
              <p class="section-hint">{{ t('components.materialDetail.packagingHint') }}</p>
              
              <div class="form-grid">
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelPiecesPerUnit') }}</label>
                  <input 
                    v-model.number="formData.pack_size" 
                    type="number" 
                    min="2"
                    class="form-input"
                    :placeholder="t('components.materialDetail.packSizePlaceholder')"
                  />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelDesignation') }}</label>
                  <div class="pack-unit-select">
                    <select v-model="formData.pack_unit" class="form-select">
                      <option value="">{{ t('components.materialDetail.packUnitNone') }}</option>
                      <option value="Bündel">{{ t('components.materialDetail.packUnitBundle') }}</option>
                      <option value="Kiste">{{ t('components.materialDetail.packUnitKiste') }}</option>
                      <option value="Karton">{{ t('components.materialDetail.packUnitKarton') }}</option>
                      <option value="Sack">{{ t('components.materialDetail.packUnitSack') }}</option>
                      <option value="Rolle">{{ t('components.materialDetail.packUnitRolle') }}</option>
                      <option value="Palette">{{ t('components.materialDetail.packUnitPalette') }}</option>
                      <option value="Set">{{ t('components.materialDetail.packUnitSet') }}</option>
                      <option value="Paket">{{ t('components.materialDetail.packUnitPaket') }}</option>
                    </select>
                    <input
                      v-if="formData.pack_unit && !['Bündel','Kiste','Karton','Sack','Rolle','Palette','Set','Paket',''].includes(formData.pack_unit)"
                      v-model="formData.pack_unit"
                      type="text"
                      class="form-input mt-1"
                      :placeholder="t('components.materialDetail.packUnitCustomPlaceholder')"
                    />
                  </div>
                </div>
              </div>
              <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                {{ t('components.materialDetail.packPreview', { stock: material.total_stock || 80, packs: Math.floor((material.total_stock || 80) / formData.pack_size), unit: formData.pack_unit, per: formData.pack_size }) }}
                <span v-if="(material.total_stock || 80) % formData.pack_size !== 0">{{ t('components.materialDetail.packPreviewRemain', { rem: (material.total_stock || 80) % formData.pack_size }) }}</span>
              </p>
            </div>

            <div v-if="material.is_consumable || material.is_food" class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionCosts') }}</h2>
              <p class="section-hint">{{ t('components.materialDetail.costsHint') }}</p>
              <div v-if="material.is_consumable" class="costs-hint-banner">
                <span>{{ t('components.materialDetail.costsConsumableBanner') }}</span>
              </div>
              <div v-if="material.is_food" class="costs-hint-banner costs-hint-banner--food">
                <span>{{ t('components.materialDetail.costsFoodBanner') }}</span>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelSalePrice') }} <span class="field-required-star">*</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.sale_price"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      placeholder="0.00"
                    />
                  </div>
                  <p class="form-hint">{{ t('components.materialDetail.hintSalePerPiece') }}</p>
                  <div
                    v-if="packSaleDerivedUnitPrice != null"
                    class="pack-sale-to-unit"
                  >
                    <p class="pack-sale-to-unit__text" v-text="packSaleCalcLine" />
                    <button
                      type="button"
                      class="btn-outline btn-sm pack-sale-to-unit__btn"
                      @click="applyPackSaleToUnitSalePrice"
                    >
                      {{ t('components.materialDetail.applyPackToUnit') }}
                    </button>
                  </div>
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelRefPurchase') }} <span class="field-required-star">*</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.reference_purchase_unit_chf"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      placeholder="0.00"
                    />
                  </div>
                  <p class="form-hint">{{ t('components.materialDetail.hintRefPurchase') }}</p>
                </div>
                <div v-if="material.is_consumable" class="form-group">
                  <label>{{ t('components.materialDetail.labelMinStock') }} <span class="optional">{{ t('components.materialDetail.optionalParen') }}</span></label>
                  <input v-model.number="formData.min_stock" type="number" min="0" class="form-input" :placeholder="t('components.materialDetail.packSizePlaceholder')" />
                </div>
              </div>

              <h3 class="subsection-heading-kosten">{{ t('components.materialDetail.sectionPackaging') }}</h3>
              <p class="section-hint">{{ t('components.materialDetail.costsPackagingOptionalHint') }}</p>
              <div class="form-grid">
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelPiecesPerUnit') }}</label>
                  <input
                    v-model.number="formData.pack_size"
                    type="number"
                    min="2"
                    class="form-input"
                    :placeholder="t('components.materialDetail.packSizePlaceholder')"
                  />
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelDesignation') }}</label>
                  <div class="pack-unit-select">
                    <select v-model="formData.pack_unit" class="form-select">
                      <option value="">{{ t('components.materialDetail.packUnitNone') }}</option>
                      <option value="Bündel">{{ t('components.materialDetail.packUnitBundle') }}</option>
                      <option value="Kiste">{{ t('components.materialDetail.packUnitKiste') }}</option>
                      <option value="Karton">{{ t('components.materialDetail.packUnitKarton') }}</option>
                      <option value="Sack">{{ t('components.materialDetail.packUnitSack') }}</option>
                      <option value="Rolle">{{ t('components.materialDetail.packUnitRolle') }}</option>
                      <option value="Palette">{{ t('components.materialDetail.packUnitPalette') }}</option>
                      <option value="Set">{{ t('components.materialDetail.packUnitSet') }}</option>
                      <option value="Paket">{{ t('components.materialDetail.packUnitPaket') }}</option>
                    </select>
                    <input
                      v-if="formData.pack_unit && !['Bündel','Kiste','Karton','Sack','Rolle','Palette','Set','Paket',''].includes(formData.pack_unit)"
                      v-model="formData.pack_unit"
                      type="text"
                      class="form-input mt-1"
                      :placeholder="t('components.materialDetail.packUnitCustomPlaceholder')"
                    />
                  </div>
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelPackSalePerUnit') }} <span class="optional">{{ t('components.materialDetail.optionalParen') }}</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.pack_sale_price_chf"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      placeholder="0.00"
                    />
                  </div>
                  <p
                    v-if="formData.pack_size && formData.pack_size >= 2 && formData.pack_sale_price_chf && formData.pack_sale_price_chf > 0"
                    class="form-hint"
                  >
                    {{ t('components.materialDetail.hintPackEquivPerPiece', { price: (formData.pack_sale_price_chf / formData.pack_size).toFixed(2) }) }}
                  </p>
                </div>
              </div>
              <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                {{ t('components.materialDetail.packPreview', { stock: material.total_stock || 0, packs: Math.floor((material.total_stock || 0) / formData.pack_size), unit: formData.pack_unit, per: formData.pack_size }) }}
                <span v-if="(material.total_stock || 0) % formData.pack_size !== 0">{{ t('components.materialDetail.packPreviewRemain', { rem: (material.total_stock || 0) % formData.pack_size }) }}</span>
              </p>
            </div>
          </section>

          <!-- Tab: Bestand -->
          <section v-else-if="activeTab === 'stock'" class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">Bestand</h2>
                <div class="section-actions">
                  <button
                    v-if="material.tracking_type !== 'serialized'"
                    class="btn-stock-action btn-stock-action-move"
                    :disabled="activeBatches.length === 0"
                    @click="openMoveQuantityFromHeader"
                  >
                    Menge verschieben
                  </button>
                  <button class="btn-stock-action btn-stock-action-add" @click="openAddBatchModal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="12" y1="5" x2="12" y2="19"/>
                      <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Charge hinzufügen
                  </button>
                  <button v-if="splitSourceBatches.length > 0" class="btn-outline-small" @click="openSplitModal">
                    Bulk in Serien splitten
                  </button>
                </div>
              </div>
              
              <div class="stock-summary">
                <div class="stock-stat">
                  <span class="stock-number">{{ material.total_stock }}</span>
                  <span class="stock-label">Gesamt</span>
                  <span v-if="material.pack_size && material.pack_unit" class="stock-pack-info">
                    {{ Math.floor(material.total_stock / material.pack_size) }} {{ material.pack_unit }}
                    <template v-if="material.total_stock % material.pack_size !== 0"> +{{ material.total_stock % material.pack_size }} Stk.</template>
                  </span>
                </div>
                <div class="stock-stat warehouse">
                  <span class="stock-number">{{ material.in_warehouse ?? availableStock }}</span>
                  <span class="stock-label">Im Lager</span>
                </div>
                <div class="stock-stat issued" v-if="material.issued_out > 0">
                  <span class="stock-number">{{ material.issued_out }}</span>
                  <span class="stock-label">Draussen</span>
                </div>
                <div class="stock-stat reserved-stat" v-if="material.reserved > 0">
                  <span class="stock-number">{{ material.reserved }}</span>
                  <span class="stock-label">Reserviert</span>
                </div>
                <div class="stock-stat repair-stat" v-if="material.repair_stock > 0">
                  <span class="stock-number">{{ material.repair_stock }}</span>
                  <span class="stock-label">Reparatur</span>
                </div>
                <div class="stock-stat" v-if="(material.defect_stock || defectStock) > 0">
                  <span class="stock-number defect">{{ material.defect_stock || defectStock }}</span>
                  <span class="stock-label">Defekt</span>
                </div>
                <div class="stock-stat available">
                  <span class="stock-number">{{ material.available ?? availableStock }}</span>
                  <span class="stock-label">Verfügbar</span>
                </div>
              </div>

              <table class="batch-table" v-if="activeBatches.length > 0">
                <thead>
                  <tr>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Einkaufsdatum sortieren" @click="toggleStockSort('acquired_on')">
                        <span>Einkaufsdatum</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'acquired_on' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'acquired_on' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Menge sortieren" @click="toggleStockSort('qty')">
                        <span>Menge</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'qty' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'qty' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Label sortieren" @click="toggleStockSort('label')">
                        <span>Label</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'label' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'label' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Preis sortieren" @click="toggleStockSort('unit_price')">
                        <span>Preis/Stk</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'unit_price' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'unit_price' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Lagerplatz sortieren" @click="toggleStockSort('location')">
                        <span>Lagerplatz</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'location' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'location' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Status sortieren" @click="toggleStockSort('status')">
                        <span>Status</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'status' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'status' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Notiz sortieren" @click="toggleStockSort('notes')">
                        <span>Notiz</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'notes' && stockSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: stockSortKey === 'notes' && stockSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="batch in sortedActiveBatches" :key="batch.id">
                    <td>{{ formatDate(batch.acquired_on) }}</td>
                    <td class="qty-cell">{{ batch.qty }}</td>
                    <td>{{ batch.label || '-' }}</td>
                    <td>{{ batch.unit_price ? `Fr. ${batch.unit_price}` : '-' }}</td>
                    <td class="location-cell">
                      <div class="location-lines">
                        <div
                          v-for="(entry, locationIndex) in buildBatchLocationEntries(batch)"
                          :key="`${batch.id}-loc-${locationIndex}`"
                          class="location-line"
                        >
                          <button
                            v-if="entry.containerMaterialId"
                            class="location-link-text"
                            @click="openContainerMaterial(entry.containerMaterialId, entry.containerBatchId, entry.containerSearchSeed)"
                          >
                            {{ entry.text }}
                          </button>
                          <span v-else>{{ entry.text }}</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge" :class="batch.status">
                        {{ statusLabels[batch.status] }}
                      </span>
                    </td>
                    <td class="notes-cell">{{ batch.notes || '-' }}</td>
                    <td class="actions-cell">
                      <button
                        v-if="material.tracking_type !== 'serialized'"
                        class="icon-btn"
                        title="Menge verschieben"
                        @click="openMoveQuantityModal(batch)"
                      >
                        <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
                        </svg>
                      </button>
                      <button class="icon-btn" title="Charge bearbeiten" @click.stop="openEditBatchModal(batch)">
                        <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              
              <div v-else class="empty-batches">
                <p>Noch keine Chargen vorhanden</p>
                <button class="btn-outline" @click="openAddBatchModal">
                  Erste Charge hinzufügen
                </button>
              </div>
            </div>
          </section>

          <!-- Tab: Gelagert in -->
          <section v-else-if="activeTab === 'stored-in'" class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">Gelagert in</h2>
              </div>
              <StorageTreeView
                :department-id="props.departmentId"
                :material-id="props.materialId"
                :readonly="true"
                :allow-move-actions="true"
                :allow-open-actions="material.material_type === 'physical_combo'"
                :embedded-detail-material-id="props.materialId"
              />
            </div>
          </section>

          <!-- Tab: Zusammensetzung (physische / virtuelle Kombination) -->
          <section v-else-if="activeTab === 'composition'" class="tab-content">
            <div class="section-card composition-tab-card">
              <div class="section-header-row composition-tab-head">
                <div>
                  <h2 class="section-title">Zusammensetzung</h2>
                  <p class="composition-tab-intro">
                    Welche Artikel und Chargen zu dieser Kombination gehören. Den Status der zugewiesenen Komponenten-Charge
                    (z.&nbsp;B. defekt) sehen Sie in der Spalte „Charge-Status“. Werkstatt-Tickets zur Kombination finden Sie
                    unter „Werkstatt“.
                  </p>
                </div>
                <div class="composition-tab-actions">
                  <button type="button" class="btn-primary btn-sm" @click="openAddCompositionModal">
                    Aus Bestand hinzufügen
                  </button>
                  <button type="button" class="btn-outline-small" @click="emitCreateMaterialForComposition">
                    Neues Material anlegen
                  </button>
                  <button type="button" class="btn-outline-small" :disabled="comboComponentsLoading" @click="loadComboComponentsForTab">
                    Aktualisieren
                  </button>
                </div>
              </div>

              <div v-if="comboComponentsLoading" class="loading-container composition-loading">
                <div class="spinner"></div>
                <p>Zusammensetzung wird geladen…</p>
              </div>
              <div v-else-if="comboComponentsList.length === 0" class="empty-used-in">
                <p>Keine Komponenten zugewiesen.</p>
                <p class="text-muted composition-hint">
                  Über „Aus Bestand hinzufügen“ oder „Neues Material anlegen“ können Sie Stücklisten-Einträge ergänzen – auch
                  wenn die Kombination nicht aus einer Vorlage stammt.
                </p>
              </div>
              <div v-else class="composition-table-wrapper">
              <table class="used-in-table composition-table">
                <thead>
                  <tr>
                    <th>Komponente</th>
                    <th>Rolle</th>
                    <th>Menge</th>
                    <th>Seriennummer</th>
                    <th>Charge-Status</th>
                    <th>Zuordnung</th>
                    <th aria-label="Status"></th>
                    <th class="composition-actions-th">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="comp in comboComponentsList" :key="comp.id" class="used-in-row">
                    <td>
                      <button type="button" class="link-btn composition-comp-link" @click="openComponentMaterialDetail(comp.component_material.id)">
                        {{ comp.component_material.name }}
                      </button>
                      <span v-if="comp.is_optional" class="composition-optional-badge">optional</span>
                    </td>
                    <td>{{ comp.component_role || '–' }}</td>
                    <td>{{ comp.qty }}</td>
                    <td>
                      <span v-if="comp.component_batch?.serial_number" class="serial-code">{{ comp.component_batch.serial_number }}</span>
                      <span v-else class="text-muted">–</span>
                    </td>
                    <td>
                      <span
                        v-if="comp.component_batch"
                        class="status-badge"
                        :class="comp.component_batch.status"
                      >
                        {{ statusLabels[comp.component_batch.status] || comp.component_batch.status }}
                      </span>
                      <span v-else class="text-muted">–</span>
                    </td>
                    <td class="composition-assignment-cell">
                      <span
                        class="assignment-badge"
                        :class="comp.assignment_mode === 'fixed' ? 'fix' : comp.assignment_mode"
                        :title="comboAssignmentLabels[comp.assignment_mode] || comp.assignment_mode"
                      >
                        {{ comboAssignmentLabelsShort[comp.assignment_mode] || comp.assignment_mode }}
                      </span>
                    </td>
                    <td class="composition-status-cell">
                      <span v-if="comp.is_awaiting" class="composition-dot composition-dot--await" title="Wartet auf Zuweisung" />
                      <span v-else-if="comp.is_assigned" class="composition-dot composition-dot--ok" title="Zugewiesen" />
                      <span v-else class="composition-dot composition-dot--linked" title="Verknüpft" />
                    </td>
                    <td class="composition-actions-cell">
                      <div class="composition-row-actions">
                        <button
                          type="button"
                          class="icon-btn"
                          title="Bearbeiten"
                          aria-label="Komponente bearbeiten"
                          @click="openEditCompositionModal(comp)"
                        >
                          <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                          </svg>
                        </button>
                        <button
                          type="button"
                          class="icon-btn icon-btn-danger"
                          title="Aus Kombination entfernen"
                          aria-label="Komponente löschen"
                          :disabled="deletingCompositionId === comp.id"
                          @click="confirmDeleteComposition(comp)"
                        >
                          <svg
                            v-if="deletingCompositionId === comp.id"
                            class="table-icon-sm composition-icon-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                          >
                            <path d="M23 4v6h-6M1 20v-6h6" />
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                          </svg>
                          <svg
                            v-else
                            class="table-icon-sm"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                          >
                            <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M10 11v6M14 11v6" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
              </div>
            </div>
          </section>

          <!-- Tab: Inhalt Kiste/Tasche -->
          <section v-else-if="activeTab === 'container-content'" class="tab-content">
            <div class="container-content-layout">
              <div class="section-card container-content-main-card">
                <div class="section-header-row container-content-header-row">
                  <h2 class="section-title">Inhalt Kiste/Tasche</h2>
                  <br class="container-content-header-break" />
                  <div class="detail-inline-filters">
                    <div v-if="storedInContainerOptions.length > 0" class="detail-inline-field">
                      <label class="detail-inline-label">Kiste/Tasche</label>
                      <select v-model="containerContentBatchId" class="detail-inline-select">
                        <option value="">– Kiste/Tasche wählen –</option>
                        <option v-for="option in storedInContainerOptions" :key="option.id" :value="option.id">
                          {{ option.label }}
                        </option>
                      </select>
                    </div>
                    <div v-else class="detail-inline-info-box">
                      Keine Kisten/Taschen für diesen Artikel.
                    </div>
                    <button
                      class="btn-outline-small container-add-btn"
                      :disabled="!containerContentBatchId"
                      @click="openAddToContainerModal"
                    >
                      + Artikel hinzufügen
                    </button>
                  </div>
                </div>

                <div v-if="!containerContentBatchId && storedInContainerOptions.length === 0" class="empty-used-in">
                  <p>Keine Kisten/Taschen für diesen Artikel vorhanden.</p>
                </div>
                <div v-else-if="!containerContentBatchId" class="empty-used-in">
                  <p>Bitte zuerst eine Kiste/Tasche auswählen.</p>
                </div>
                <div v-else-if="isLoadingContainerContentOverview" class="loading-container" style="padding: 32px;">
                  <div class="spinner"></div>
                  <p>Kisteninhalt wird geladen...</p>
                </div>
                <div v-else-if="containerContentRows.length === 0" class="empty-used-in">
                  <p>Keine Artikel in dieser Kiste/Tasche gefunden.</p>
                </div>
                <table v-else class="used-in-table container-content-table">
                  <thead>
                    <tr>
                      <th>Artikel</th>
                      <th>Menge</th>
                      <th class="action-cell">Aktion</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in containerContentRows" :key="row.materialId" class="used-in-row">
                      <td>{{ row.materialName }}</td>
                      <td>{{ row.qty }} Stk.</td>
                      <td class="action-cell">
                        <button class="btn-outline-small" @click="openMaterialById(row.materialId)">
                          Artikel öffnen
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>

              </div>

              <aside class="container-content-sidebar">
                <div class="section-card">
                  <div class="section-header-row">
                    <h2 class="section-title">Details zur Kiste/Tasche</h2>
                  </div>
                  <div v-if="!containerContentBatchId" class="empty-used-in">
                    <p>Keine Kiste/Tasche ausgewählt.</p>
                  </div>
                  <div v-else-if="isLoadingContainerEditor" class="loading-container" style="padding: 24px;">
                    <div class="spinner"></div>
                    <p>Details werden geladen...</p>
                  </div>
                  <div v-else-if="!containerEditorBatchId" class="empty-used-in">
                    <p>Details konnten nicht geladen werden.</p>
                  </div>
                  <div v-else class="form-grid">
                    <div class="form-group span-full">
                      <label>Seriennummer</label>
                      <input v-model="containerEditorForm.serial_number" type="text" class="form-input" />
                    </div>
                    <div class="form-group span-full">
                      <label>Label</label>
                      <input v-model="containerEditorForm.label" type="text" class="form-input" />
                    </div>
                    <div class="form-group span-full">
                      <label>Status</label>
                      <select v-model="containerEditorForm.status" class="form-select">
                        <option value="active">Aktiv</option>
                        <option value="defect">Defekt</option>
                        <option value="repair">Reparatur</option>
                        <option value="lost">Verloren</option>
                        <option value="disposed">Entsorgt</option>
                      </select>
                    </div>
                    <div class="form-group span-full">
                      <label>Notiz</label>
                      <textarea v-model="containerEditorForm.notes" class="form-textarea" rows="3"></textarea>
                    </div>
                    <div class="form-group span-full">
                      <button
                        class="btn-primary"
                        :disabled="isSavingContainerEditor || !containerEditorDirty"
                        @click="saveContainerEditor"
                      >
                        {{ isSavingContainerEditor ? 'Speichern...' : 'Speichern' }}
                      </button>
                    </div>
                  </div>
                </div>
              </aside>
            </div>
          </section>

          <!-- Tab: Seriennummern (nur bei serialisierten Materialien) -->
          <section v-else-if="activeTab === 'serials'" class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">Seriennummern</h2>
                <button class="btn-outline-small" @click="openAddBatchModal">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                  Charge hinzufügen
                </button>
              </div>
              
              <div class="serial-summary">
                <span class="serial-count">{{ serialBatches.length }} Seriennummern registriert</span>
              </div>

              <table class="serials-table" v-if="serialBatches.length > 0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Seriennummer sortieren" @click="toggleSerialSort('serial_number')">
                        <span>Seriennummer</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'serial_number' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'serial_number' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Code sortieren" @click="toggleSerialSort('public_code')">
                        <span>Code</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'public_code' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'public_code' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Label sortieren" @click="toggleSerialSort('label')">
                        <span>Label</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'label' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'label' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell th-serial-behälter">
                      <button type="button" class="detail-th-sort" title="Nach Behälter sortieren" @click="toggleSerialSort('is_container')">
                        <span>Behälter</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'is_container' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'is_container' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Erfassungsdatum sortieren" @click="toggleSerialSort('acquired_on')">
                        <span>Erfasst am</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'acquired_on' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'acquired_on' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Lagerplatz sortieren" @click="toggleSerialSort('location')">
                        <span>Lagerplatz</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'location' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'location' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Status sortieren" @click="toggleSerialSort('status')">
                        <span>Status</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'status' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'status' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th class="th-sort-cell">
                      <button type="button" class="detail-th-sort" title="Nach Notiz sortieren" @click="toggleSerialSort('notes')">
                        <span>Notiz</span>
                        <span class="detail-th-sort-arrows" aria-hidden="true">
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'notes' && serialSortDir === 'asc' }">▲</span>
                          <span class="detail-sort-chev" :class="{ active: serialSortKey === 'notes' && serialSortDir === 'desc' }">▼</span>
                        </span>
                      </button>
                    </th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(batch, index) in sortedSerialBatches" :key="batch.id">
                    <td class="col-num">{{ index + 1 }}</td>
                    <td class="col-serial">
                      <span class="serial-code">{{ batch.serial_number }}</span>
                    </td>
                    <td class="col-qr">
                      <PublicQrTag
                        :url="batch.public_url"
                        :code="batch.public_code"
                        :size="56"
                        :clickable="true"
                        :image-label="material.name"
                        :image-entity-id="batch.id"
                        @activate="openQrActionModalForBatch(batch)"
                      />
                    </td>
                    <td>{{ batch.label || '-' }}</td>
                    <td class="col-serial-container">
                      <label class="checkbox-label serial-behälter-cell">
                        <input
                          type="checkbox"
                          :checked="!!batch.is_container"
                          :disabled="!!serialIsContainerSaving[batch.id]"
                          @change="onSerialBatchIsContainerChange(batch, ($event.target as HTMLInputElement).checked)"
                        />
                      </label>
                    </td>
                    <td>{{ formatDate(batch.acquired_on) }}</td>
                    <td class="location-cell">
                      <div class="location-lines">
                        <div
                          v-for="(entry, locationIndex) in buildBatchLocationEntries(batch)"
                          :key="`${batch.id}-loc-${locationIndex}`"
                          class="location-line"
                        >
                          <button
                            v-if="entry.containerMaterialId"
                            class="location-link-text"
                            @click="openContainerMaterial(entry.containerMaterialId, entry.containerBatchId, entry.containerSearchSeed)"
                          >
                            {{ entry.text }}
                          </button>
                          <span v-else>{{ entry.text }}</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge" :class="batch.status">
                        {{ statusLabels[batch.status] }}
                      </span>
                    </td>
                    <td class="notes-cell">{{ batch.notes || '-' }}</td>
                    <td class="actions-cell">
                      <button class="icon-btn" title="Charge bearbeiten" @click.stop="openEditBatchModal(batch)">
                        <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              
              <div v-else class="empty-serials">
                <p>Noch keine Seriennummern erfasst</p>
                <button class="btn-outline" @click="openAddBatchModal">
                  Erste Charge hinzufügen
                </button>
              </div>
            </div>
          </section>

          <!-- Tab: Vermietung -->
          <section v-else-if="activeTab === 'workshop'" class="tab-content">
            <div class="section-card">
              <h2 class="section-title">Werkstatt &amp; Reparaturen</h2>
              <p class="form-hint" style="margin-top: 0">
                Tickets aus Aktivitäten (Rückgabe defekt, Meldungen) und manuelle Aufträge für dieses Material.
              </p>
              <div v-if="workshopTicketsLoading" class="loading-inline">
                <div class="spinner"></div>
                <span>Tickets werden geladen…</span>
              </div>
              <div v-else-if="workshopTickets.length === 0" class="empty-serials">
                <p>Keine Werkstatt-Tickets für dieses Material.</p>
                <div class="workshop-tab-actions">
                  <router-link
                    class="btn-primary"
                    :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId } }"
                  >
                    Zur Werkstatt (Material gefiltert)
                  </router-link>
                </div>
                <p class="form-hint">Dort kannst du ein neues Ticket erfassen; bei aktivem Material-Filter ist die Vorauswahl gesetzt.</p>
              </div>
              <div v-else>
                <table class="batch-table workshop-tickets-mini">
                  <thead>
                    <tr>
                      <th>Ticket</th>
                      <th>Typ</th>
                      <th>Status</th>
                      <th>Aktivität</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="wt in workshopTickets" :key="wt.id">
                      <td>{{ wt.title }}</td>
                      <td>{{ wt.type_label }}</td>
                      <td>
                        <span class="status-badge">{{ wt.status_label }}</span>
                      </td>
                      <td>
                        <router-link
                          v-if="wt.activity_id"
                          :to="`/${departmentId}/activities/${wt.activity_id}`"
                          class="link-btn"
                        >
                          Öffnen
                        </router-link>
                        <span v-else class="muted">—</span>
                      </td>
                      <td class="actions-cell">
                        <router-link
                          class="btn-outline btn-sm"
                          :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId, ticket: wt.id } }"
                        >
                          In Werkstatt
                        </router-link>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <div class="workshop-tab-actions mt-3">
                  <router-link
                    class="btn-outline btn-sm"
                    :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId } }"
                  >
                    Alle in der Werkstatt
                  </router-link>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'rental'" class="tab-content">
            <div class="section-card">
              <h2 class="section-title">Vermietung</h2>

              <div
                v-if="material.material_type === 'physical_combo' || material.material_type === 'virtual_combo'"
                class="combo-rental-basis-section"
              >
                <h3 class="section-subtitle combo-rental-basis-title">Anschaffung aus Zusammensetzung (ein Set)</h3>
                <p class="form-hint combo-rental-basis-intro">
                  Pro Komponente: Summe (aktive Chargen mit Stückpreis) geteilt durch die Stückzahl dieser Chargen —
                  ergibt den durchschnittlichen Anschaffungspreis pro Stück; multipliziert mit der Menge im Set.
                  Optional: gleiche Logik wie im Amortisationsrechner bei Einzelmaterial.
                </p>
                <div v-if="comboRentalLoading" class="loading-inline combo-rental-loading">
                  <div class="spinner"></div>
                  <span>Komponenten und Chargen werden geladen…</span>
                </div>
                <p v-else-if="comboRentalError" class="form-hint text-warning">{{ comboRentalError }}</p>
                <p v-else-if="comboRentalRows.length === 0" class="empty-serials combo-rental-empty">
                  Keine Einträge in der Stückliste. Unter „Zusammensetzung“ Komponenten hinzufügen, damit eine Basis
                  berechnet werden kann.
                </p>
                <template v-else>
                  <div class="combo-rental-basis-table-wrap">
                    <table class="batch-table combo-rental-basis-table">
                      <thead>
                        <tr>
                          <th>Komponente</th>
                          <th class="combo-rental-col-num">Menge im Set</th>
                          <th class="combo-rental-col-num">Ø Anschaffung / Stk.</th>
                          <th class="combo-rental-col-num">Zeile</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(row, idx) in comboRentalRows" :key="`${row.componentId}-${idx}`">
                          <td>
                            <span>{{ row.name }}</span>
                            <span v-if="row.optional" class="composition-optional-badge">optional</span>
                          </td>
                          <td class="combo-rental-col-num">{{ row.qty }}</td>
                          <td class="combo-rental-col-num">
                            <template v-if="row.perPieceChf != null">Fr. {{ formatChfFiveRappenString(row.perPieceChf) }}</template>
                            <span v-else class="muted">—</span>
                          </td>
                          <td class="combo-rental-col-num">
                            <template v-if="row.lineChf != null">Fr. {{ formatChfFiveRappenString(row.lineChf) }}</template>
                            <span v-else class="muted">—</span>
                          </td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr class="combo-rental-total-row">
                          <th colspan="3">Summe (ein Set)</th>
                          <th class="combo-rental-col-num">
                            <template v-if="rentalAcquisitionBasisChf != null">
                              Fr. {{ formatChfFiveRappenString(rentalAcquisitionBasisChf) }}
                            </template>
                            <span v-else class="muted">—</span>
                          </th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                  <p v-if="comboRentalHasGap" class="form-hint combo-rental-gap-hint">
                    Mindestens eine Zeile ohne verwertbaren Stückpreis (Chargen). Die Summe enthält nur vollständige
                    Zeilen; du kannst die Kalkulationsbasis unten manuell setzen.
                  </p>
                </template>
              </div>

              <RentalPriceAmortizationCalculator
                v-if="showRentalAmortizationCalculator"
                v-model="formData.rental_calc_params"
                :defaults="rentalAmortizationDefaults"
                :historical-basis-chf="rentalAcquisitionBasisChf"
                :piece-count="rentalAcquisitionPieceCount ?? undefined"
                :context="rentalAmortizationContext"
                @apply="onRentalCalculatorApply"
              />
              
              <div class="form-grid">
                <div class="form-group">
                  <label>Tagespreis</label>
                  <div class="input-with-prefix">
                    <span class="prefix">Fr.</span>
                    <input v-model="formData.rental_price_day" type="text" class="form-input" />
                  </div>
                </div>
                <div class="form-group">
                  <label>Wochenpreis</label>
                  <div class="input-with-prefix">
                    <span class="prefix">Fr.</span>
                    <input v-model="formData.rental_price_week" type="text" class="form-input" />
                  </div>
                </div>
                <div class="form-group">
                  <label>Monatspreis</label>
                  <div class="input-with-prefix">
                    <span class="prefix">Fr.</span>
                    <input v-model="formData.rental_price_month" type="text" class="form-input" />
                  </div>
                </div>
                <div class="form-group">
                  <label>Kaution</label>
                  <div class="input-with-prefix">
                    <span class="prefix">Fr.</span>
                    <input v-model="formData.rental_deposit" type="text" class="form-input" />
                  </div>
                </div>
                <div class="form-group">
                  <label>Vorlaufzeit (Tage)</label>
                  <input v-model="formData.rental_lead_days" type="number" class="form-input" />
                </div>
                <div class="form-group">
                  <label>Max. Mietdauer (Tage)</label>
                  <input v-model="formData.rental_max_days" type="number" class="form-input" />
                </div>
              </div>

              <div class="checkbox-group mt-4">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="formData.rental_external_allowed" />
                  <span>Externe Vermietung erlaubt</span>
                </label>
                <label class="checkbox-label">
                  <input type="checkbox" v-model="formData.rental_requires_approval" />
                  <span>Genehmigung erforderlich</span>
                </label>
              </div>

              <div v-if="formData.rental_external_allowed" class="form-group span-full mt-2">
                <label>Externe Vermietung — Reichweite</label>
                <select v-model="formData.rental_scope" class="form-select">
                  <option value="">— nicht festgelegt —</option>
                  <option value="department">Nur eigenes Department</option>
                  <option value="organisation">Ganze Organisation</option>
                  <option value="public">Öffentlich / externe Mieter</option>
                </select>
                <p class="form-hint">Steuert, wer das Material extern mieten darf (sofern die App dies auswertet).</p>
              </div>

              <div class="form-group span-full mt-4">
                <label>Vermietungs-Hinweise</label>
                <textarea v-model="formData.rental_notes" class="form-textarea" rows="3" placeholder="Besondere Bedingungen, Hinweise..."></textarea>
              </div>
            </div>
          </section>
          <!-- Tab: Archiv -->
          <section v-else-if="activeTab === 'archive'" class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">Archiv</h2>
                <span class="archive-info-badge">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                  </svg>
                  Materialien werden nicht gelöscht – sie gehen verloren oder werden entsorgt
                </span>
              </div>

              <table class="batch-table archive-table" v-if="archivedBatches.length > 0">
                <thead>
                  <tr>
                    <th>Einkaufsdatum</th>
                    <th>Menge</th>
                    <th>Preis/Stk</th>
                    <th>Grund</th>
                    <th>Notiz</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="batch in archivedBatches" :key="batch.id" class="archived-row">
                    <td>{{ formatDate(batch.acquired_on) }}</td>
                    <td class="qty-cell">{{ getArchivedBatchDisplayQty(batch) }}</td>
                    <td>{{ batch.unit_price ? `Fr. ${batch.unit_price}` : '-' }}</td>
                    <td>
                      <span class="status-badge" :class="batch.status">
                        {{ statusLabels[batch.status] }}
                      </span>
                    </td>
                    <td class="notes-cell">{{ batch.notes || '-' }}</td>
                    <td class="actions-cell"></td>
                  </tr>
                </tbody>
              </table>
              
              <div v-else class="empty-archive">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <polyline points="20 12 20 22 4 22 4 12"/>
                  <rect x="2" y="7" width="20" height="5"/>
                  <line x1="12" y1="22" x2="12" y2="12"/>
                  <path d="M12 12H7.5a2.5 2.5 0 0 1 0-5C11 7 12 12 12 12z"/>
                  <path d="M12 12h4.5a2.5 2.5 0 0 0 0-5C13 7 12 12 12 12z"/>
                </svg>
                <p>Keine archivierten Chargen</p>
                <span class="empty-archive-hint">Chargen mit Status "Verloren" oder "Entsorgt" erscheinen hier</span>
              </div>
            </div>
          </section>

          <!-- Tab: Verwendet in -->
          <section v-else-if="activeTab === 'used-in'" class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">Verwendet in Kombos</h2>
                <div class="detail-inline-filters">
                  <input
                    v-model="usedInSearch"
                    type="text"
                    class="detail-inline-search"
                    placeholder="Kombos / Rolle / Seriennummer suchen..."
                  />
                </div>
              </div>

              <div v-if="isLoadingUsedIn" class="loading-container" style="padding: 40px;">
                <div class="spinner"></div>
                <p>Wird geladen...</p>
              </div>

              <div v-else-if="usedInEntries.length === 0" class="empty-used-in">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p>Dieses Material wird in keiner Kombo verwendet</p>
              </div>
              <div v-else-if="filteredUsedInEntries.length === 0" class="empty-used-in">
                <p>Keine Treffer für diesen Suchbegriff.</p>
              </div>

              <table v-else class="used-in-table">
                <thead>
                  <tr>
                    <th>Kombo</th>
                    <th>Typ</th>
                    <th>Rolle</th>
                    <th>Seriennummer</th>
                    <th>Menge</th>
                    <th>Zuordnung</th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="entry in filteredUsedInEntries"
                    :key="entry.combo_id + (entry.batch_id || '')"
                    class="used-in-row"
                  >
                    <td>
                      <span class="used-in-link" @click="navigateToCombo(entry.combo_id)">
                        {{ entry.combo_name }}
                      </span>
                    </td>
                    <td>
                      <span class="used-in-type-badge" :class="entry.material_type">
                        {{ entry.material_type === 'physical_combo' ? 'Physisch' : 'Virtuell' }}
                      </span>
                    </td>
                    <td>{{ entry.component_role || '–' }}</td>
                    <td>
                      <span v-if="entry.batch_serial" class="serial-code">{{ entry.batch_serial }}</span>
                      <span v-else class="no-serial">–</span>
                    </td>
                    <td>{{ entry.qty }}</td>
                    <td>
                      <span class="assignment-badge" :class="entry.assignment_mode === 'fixed' ? 'fix' : entry.assignment_mode">
                        {{ usedInAssignmentLabels[entry.assignment_mode] || entry.assignment_mode }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Tab: History Log -->
          <section v-else-if="activeTab === 'history'" class="tab-content">
            <div class="section-card history-card">
              <h2 class="section-title">Änderungshistorie</h2>
              
              <div v-if="isLoadingHistory" class="loading-container" style="padding: 40px;">
                <div class="spinner"></div>
                <p>Historie wird geladen...</p>
              </div>

              <div v-else-if="historyEntries.length === 0" class="empty-history">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
                <p>Noch keine Änderungen protokolliert</p>
              </div>

              <div v-else class="history-layout">
                <!-- Linke Seite: Speicherungen (Liste) -->
                <div class="history-list">
                  <div class="history-list-header">
                    <span class="history-list-title">Speicherungen</span>
                  </div>
                  <div class="history-list-content">
                    <div class="history-list-table">
                      <div class="history-list-th">
                        <span class="th-time">Zeitpunkt</span>
                        <span class="th-user">Erstellt von</span>
                      </div>
                      <div 
                        v-for="entry in historyEntries" 
                        :key="entry.id"
                        class="history-list-row"
                        :class="{ selected: selectedHistoryEntry?.id === entry.id }"
                        @click="selectedHistoryEntry = entry"
                      >
                        <span class="row-time">
                          <span class="time-date">{{ formatHistoryDate(entry.created_at) }}</span>
                          <span class="time-clock">{{ formatHistoryTime(entry.created_at) }}</span>
                        </span>
                        <span class="row-user">{{ entry.user?.name || 'System' }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Rechte Seite: Änderungen -->
                <div class="history-changes">
                  <div class="history-changes-header">
                    <span class="history-changes-title">Änderungen</span>
                    <div v-if="selectedHistoryEntry" class="history-action-badge" :class="selectedHistoryEntry.action">
                      {{ actionLabels[selectedHistoryEntry.action] || selectedHistoryEntry.action }}
                    </div>
                  </div>
                  
                  <div v-if="!selectedHistoryEntry" class="history-empty-selection">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <p>Wähle einen Speichermoment in der linken Liste aus</p>
                  </div>

                  <div v-else-if="selectedHistoryEntry.action === 'created'" class="history-created-info">
                    <div class="created-badge">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                      </svg>
                      Material erstellt
                    </div>
                    <p class="created-hint">Das Material wurde zu diesem Zeitpunkt erstellt.</p>
                  </div>

                  <div v-else-if="selectedHistoryEntry.action === 'batch_added'" class="history-created-info batch-action">
                    <div class="created-badge batch-badge">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                      </svg>
                      Charge hinzugefügt
                    </div>
                    <div v-if="Object.keys(selectedHistoryEntry.changes).length > 0" class="history-changes-list">
                      <div 
                        v-for="(change, fieldName) in selectedHistoryEntry.changes" 
                        :key="fieldName"
                        class="history-change-item"
                      >
                        <div class="change-field">{{ fieldLabels[String(fieldName)] || fieldName }}</div>
                        <div class="change-values">
                          <div class="change-new-only">
                            <span class="change-value">{{ formatChangeValue(change.new) }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else-if="Object.keys(selectedHistoryEntry.changes).length === 0" class="history-no-changes">
                    <p>Keine Feldänderungen bei diesem Speichervorgang</p>
                  </div>

                  <div v-else class="history-changes-list">
                    <div 
                      v-for="(change, fieldName) in selectedHistoryEntry.changes" 
                      :key="fieldName"
                      class="history-change-item"
                    >
                      <div class="change-field">{{ fieldLabels[String(fieldName)] || fieldName }}</div>
                      <div class="change-values">
                        <div class="change-old">
                          <span class="change-label">Vorher</span>
                          <span class="change-value">{{ formatChangeValue(change.old) }}</span>
                        </div>
                        <div class="change-arrow">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                          </svg>
                        </div>
                        <div class="change-new">
                          <span class="change-label">Nachher</span>
                          <span class="change-value">{{ formatChangeValue(change.new) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>

        <!-- Sidebar (Right) -->
        <aside v-if="activeTab === 'data'" class="content-sidebar">
          <!-- Abbildung -->
          <div class="sidebar-card">
            <div class="sidebar-header">
              <h3>Abbildung</h3>
              <button class="link-btn">Mit Google suchen</button>
            </div>
            <div class="image-slot">
              <svg v-if="!material.image_url" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
              <img v-else :src="material.image_url" alt="Material" />
            </div>
            <button v-if="hasAnyQrForPrint" class="btn-outline btn-sm qr-print-btn" @click="openQrActionModalForAll">
              QR-Codes drucken
            </button>
          </div>

          <!-- Bestand Quick View -->
          <div class="sidebar-card">
            <div class="sidebar-header">
              <h3>Bestand</h3>
              <button
                v-if="isComboMaterialView"
                type="button"
                class="link-btn"
                @click="activeTab = 'composition'"
              >
                Zusammensetzung
              </button>
              <button v-else type="button" class="link-btn" @click="activeTab = 'stock'">Ändern</button>
            </div>
            <div class="stock-quick">
              <div class="stock-row stock-row-total">
                <span>Gesamt</span>
                <span class="stock-val">{{ material.total_stock }}</span>
              </div>
              <div v-if="material.pack_size && material.pack_unit" class="stock-row">
                <span>{{ material.pack_unit }}</span>
                <span class="stock-val">{{ packUnitCount }} à {{ material.pack_size }}</span>
              </div>
              <div v-if="material.pack_size && material.pack_unit && packLooseCount > 0" class="stock-row">
                <span>Einzeln</span>
                <span class="stock-val">{{ packLooseCount }}</span>
              </div>
              <div class="stock-row-separator"></div>
              <div class="stock-row">
                <span>Im Lager</span>
                <span class="stock-val warehouse">{{ material.in_warehouse ?? availableStock }}</span>
              </div>
              <div class="stock-row" v-if="material.issued_out > 0">
                <span>Draussen</span>
                <span class="stock-val issued">{{ material.issued_out }}</span>
              </div>
              <div class="stock-row" v-if="material.reserved > 0">
                <span>Reserviert</span>
                <span class="stock-val reserved">{{ material.reserved }}</span>
              </div>
              <div class="stock-row" v-if="material.repair_stock > 0">
                <span>Reparatur</span>
                <span class="stock-val repair">{{ material.repair_stock }}</span>
              </div>
              <div class="stock-row" v-if="material.defect_stock > 0 || defectStock > 0">
                <span>Defekt</span>
                <span class="stock-val defect">{{ material.defect_stock || defectStock }}</span>
              </div>
              <div class="stock-row" v-if="archivedStock > 0">
                <span>Abgeschrieben/Archiv</span>
                <span class="stock-val archived">{{ archivedStock }}</span>
              </div>
              <div class="stock-row stock-row-loss" v-if="material.open_loss_reports > 0">
                <span>Verlust gemeldet</span>
                <span class="stock-val loss">{{ openLossLabel }}</span>
              </div>
              <div class="stock-row-separator"></div>
              <div class="stock-row stock-row-highlight">
                <span>Verfügbar</span>
                <span class="stock-val available">{{ material.available ?? availableStock }}</span>
              </div>
            </div>
          </div>

          <!-- Kategorie Quick View -->
          <div class="sidebar-card" v-if="material.category">
            <div class="sidebar-header">
              <h3>Kategorie</h3>
            </div>
            <div class="category-path">
              {{ getCategoryPath() }}
            </div>
          </div>
        </aside>
      </div>
    </div>

    <!-- Batch Modal (Add / Edit) -->
    <BatchModal
      v-if="showBatchModal && material"
      :key="`${material.id}-${editingBatch?.id ?? 'new'}`"
      :material-id="props.materialId"
      :department-id="props.departmentId"
      :batch="editingBatch"
      :tracking-type="material.tracking_type ?? null"
      :is-serialized="material?.tracking_type === 'serialized'"
      :material-name="material?.name || ''"
      :existing-batches="batches"
      @close="closeBatchModal"
      @saved="handleBatchSaved"
    />

    <SplitModal
      v-model="showSplitModal"
      v-if="material"
      :material-id="props.materialId"
      :department-id="props.departmentId"
      :material-name="material?.name || ''"
      :source-batches="splitSourceBatches"
      :existing-batches="batches"
      @saved="handleSplitSaved"
    />
    <MoveQuantityModal
      v-if="showMoveModal && moveBatch"
      :material-id="materialId"
      :department-id="departmentId"
      :batch="moveBatch"
      @close="showMoveModal = false; moveBatch = null"
      @saved="handleMoveSaved"
    />

    <div v-if="showAddToContainerModal" class="modal-overlay">
      <div class="modal-dialog">
        <h3>Artikel zur Kiste/Tasche hinzufügen</h3>
        <div class="form-group">
          <label>Artikel suchen</label>
          <MaterialLookupInput
            v-model="addToContainerSearch"
            :fetcher="addToContainerMaterialFetcher"
            :min-chars="1"
            :max-suggestions="5"
            placeholder="Material suchen..."
            :loading-text="'Suche...'"
            :empty-text="`Keine Treffer für „${addToContainerSearch || ''}“`"
            :get-result-label="formatAddToContainerLookupLabel"
            :get-result-secondary="formatAddToContainerLookupSecondary"
            @select="handleAddToContainerLookupSelect"
          />
        </div>

        <div v-if="addToContainerSourceMaterial" class="form-group">
          <label>Ausgewählter Artikel</label>
          <div class="selected-source-material">
            <div class="name">{{ addToContainerSourceMaterial.name }}</div>
            <div class="meta">{{ addToContainerSourceMaterial.total_stock || 0 }} Stk. gesamt</div>
            <button type="button" class="btn-outline-small" @click="clearAddToContainerMaterialSelection">Ändern</button>
          </div>
        </div>

        <div v-if="addToContainerSourceMaterialId" class="form-group">
          <label>Charge / Serie</label>
          <select v-model="addToContainerSourceBatchId" class="form-select" @change="handleAddToContainerBatchChange">
            <option value="">– Quelle wählen –</option>
            <option v-for="batch in addToContainerSourceBatches" :key="batch.id" :value="batch.id">
              {{ formatSourceBatchOption(batch) }}
            </option>
          </select>
        </div>

        <div v-if="selectedAddToContainerAllocations.length > 0" class="form-group">
          <label>Von (Quelle / Standort)</label>
          <select v-model="addToContainerSourceAllocationId" class="form-select">
            <option value="">– Quelle wählen –</option>
            <option v-for="alloc in selectedAddToContainerAllocations" :key="alloc.id" :value="alloc.id">
              {{ formatAllocationLocationInline(alloc) }} – {{ alloc.qty }} Stk.
            </option>
          </select>
        </div>

        <div v-if="selectedAddToContainerBatch" class="form-group">
          <label>Menge</label>
          <input v-model.number="addToContainerQty" type="number" min="1" :max="addToContainerMaxQty" class="form-input" />
          <p class="batch-field-hint">Max. {{ addToContainerMaxQty }} Stk.</p>
        </div>

        <p v-if="addToContainerError" class="error-text">{{ addToContainerError }}</p>
        <div class="modal-actions">
          <button class="btn-secondary btn-sm" @click="closeAddToContainerModal">Abbrechen</button>
          <button class="btn-primary btn-sm" :disabled="!canSubmitAddToContainer || isAddingToContainer" @click="submitAddToContainer">
            {{ isAddingToContainer ? 'Hinzufügen...' : 'Hinzufügen' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showAddCompositionModal" class="modal-overlay" @click.self="closeAddCompositionModal">
      <div class="modal-dialog composition-add-modal">
        <h3>Komponente hinzufügen</h3>
        <p class="text-muted composition-add-modal-intro">
          Wählen Sie einen bestehenden Artikel aus Ihrem Materialbestand. Die Zuordnung (z.&nbsp;B. Mengenware) können Sie
          bei Bedarf später anpassen.
        </p>
        <div class="form-group">
          <label>Artikel suchen</label>
          <MaterialLookupInput
            v-model="addCompositionSearch"
            :fetcher="compositionMaterialFetcher"
            :min-chars="1"
            :max-suggestions="8"
            placeholder="Name oder Code…"
            :loading-text="'Suche…'"
            :empty-text="`Keine Treffer für „${addCompositionSearch || ''}“`"
            :get-result-label="compositionLookupLabel"
            :get-result-secondary="formatCompositionLookupSecondary"
            @select="handleCompositionMaterialSelect"
          />
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>Ausgewählter Artikel</label>
          <div class="selected-source-material">
            <div class="name">{{ addCompositionSelected.name }}</div>
            <div class="meta">{{ addCompositionSelected.total_stock ?? 0 }} Stk. gesamt</div>
            <button type="button" class="btn-outline-small" @click="clearAddCompositionSelection">Ändern</button>
          </div>
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>Menge</label>
          <input v-model.number="addCompositionQty" type="number" min="1" class="form-input" />
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>Rolle (optional)</label>
          <input v-model="addCompositionRole" type="text" class="form-input" placeholder="z.&nbsp;B. Aussenzelt, Heringe" />
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label class="checkbox-label">
            <input v-model="addCompositionOptional" type="checkbox" />
            Optional (nicht zwingend für die Kombination)
          </label>
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>Zuordnung</label>
          <select v-model="addCompositionMode" class="form-select">
            <option value="bulk">{{ comboAssignmentLabels.bulk }}</option>
            <option value="fixed">{{ comboAssignmentLabels.fixed }}</option>
            <option value="assigned">{{ comboAssignmentLabels.assigned }}</option>
            <option value="on_issue">{{ comboAssignmentLabels.on_issue }}</option>
          </select>
        </div>
        <p v-if="addCompositionError" class="error-text">{{ addCompositionError }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary btn-sm" @click="closeAddCompositionModal">Abbrechen</button>
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="!canSubmitAddComposition || addCompositionSubmitting"
            @click="submitAddComposition"
          >
            {{ addCompositionSubmitting ? 'Hinzufügen…' : 'Hinzufügen' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showEditCompositionModal && editCompositionComp" class="modal-overlay" @click.self="closeEditCompositionModal">
      <div class="modal-dialog composition-add-modal">
        <h3>Komponente bearbeiten</h3>
        <p class="text-muted composition-add-modal-intro">
          <strong>{{ editCompositionComp.component_material.name }}</strong>
        </p>
        <div class="form-group">
          <label>Menge</label>
          <input v-model.number="editCompositionQty" type="number" min="1" class="form-input" />
        </div>
        <div class="form-group">
          <label>Rolle (optional)</label>
          <input v-model="editCompositionRole" type="text" class="form-input" placeholder="z.&nbsp;B. Aussenzelt, Heringe" />
        </div>
        <div class="form-group">
          <label class="checkbox-label">
            <input v-model="editCompositionOptional" type="checkbox" />
            Optional (nicht zwingend für die Kombination)
          </label>
        </div>
        <div class="form-group">
          <label>Zuordnung</label>
          <select v-model="editCompositionMode" class="form-select">
            <option value="bulk">{{ comboAssignmentLabels.bulk }}</option>
            <option value="fixed">{{ comboAssignmentLabels.fixed }}</option>
            <option value="assigned">{{ comboAssignmentLabels.assigned }}</option>
            <option value="on_issue">{{ comboAssignmentLabels.on_issue }}</option>
          </select>
        </div>
        <div v-if="editCompositionBatchesLoading" class="form-group text-muted">Chargen werden geladen…</div>
        <div v-else-if="editCompositionBatches.length > 0" class="form-group">
          <label>Zugewiesene Charge (optional)</label>
          <select v-model="editCompositionBatchId" class="form-select">
            <option value="">– keine Charge –</option>
            <option v-for="b in editCompositionBatches" :key="b.id" :value="b.id">
              {{ formatCompositionBatchOption(b) }}
            </option>
          </select>
          <p class="batch-field-hint">Nur bei Seriennummern oder fester Zuweisung relevant.</p>
        </div>
        <p v-if="editCompositionError" class="error-text">{{ editCompositionError }}</p>
        <div class="modal-actions">
          <button type="button" class="btn-secondary btn-sm" @click="closeEditCompositionModal">Abbrechen</button>
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="editCompositionSubmitting || (editCompositionQty ?? 0) < 1"
            @click="submitEditComposition"
          >
            {{ editCompositionSubmitting ? 'Speichern…' : 'Speichern' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showQrActionModal" class="modal-overlay" @click.self="closeQrActionModal">
      <div class="modal-dialog">
        <h3>QR-Aktion</h3>
        <p class="qr-modal-text">{{ qrActionLabel }}</p>
        <p v-if="qrActionCode" class="qr-modal-meta">Code: {{ qrActionCode }}</p>
        <p v-else-if="qrActionMode === 'all'" class="qr-modal-meta">
          Druckt alle verfügbaren QR-Codes dieses Materials.
        </p>
        <div class="modal-actions">
          <button class="btn-secondary btn-sm" @click="closeQrActionModal">Abbrechen</button>
          <button class="btn-outline btn-sm" @click="handleQrAddToPrintCart">In Druckkorb</button>
          <button v-if="qrActionUrl" class="btn-outline btn-sm" @click="handleQrOpenLink">QR-Seite öffnen</button>
          <button v-if="qrActionUrl" class="btn-outline btn-sm" @click="handleQrCopyLink">QR-Link kopieren</button>
          <button v-if="qrActionUrl || qrActionMode === 'all'" class="btn-primary btn-sm" @click="handleQrPrint">Drucken</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onDeactivated, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import QRCode from 'qrcode'
import {
  getMaterial,
  getMaterials,
  updateMaterial,
  updateBatch,
  moveBatchQuantity,
  getMaterialHistory,
  getMaterialUsedIn,
  ensureMaterialPublicCode,
  getMaterialStorageLocations,
  getComboComponents,
  addComboComponent,
  updateComboComponent,
  deleteComboComponent,
  type Material,
  type MaterialHistoryEntry,
  type MaterialBatch,
  type BatchStorageAllocation,
  type UsedInEntry,
  type AddBatchMultiResponse,
  type MaterialStorageLocationsResponse,
  type MaterialStorageLocationRow,
  type ComboComponent,
  type UpdateComboComponentRequest,
} from '@/api/materials'
import { addPrintCartItem, addPrintCartItemsBulk } from '@/api/tasks'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { getCategories, type Category } from '@/api/categories'
import { getContainerBatches, getStorageOverview, type ContainerBatch, type StorageOverviewResponse } from '@/api/storageLocations'
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'
import { usePhysicalComboWarningStore } from '@/stores/physicalComboWarning'
import {
  sumAcquisitionBasisFromBatches,
  sumAcquisitionPieceCountFromBatches,
  comboLineAcquisitionChf,
  roundChfToFiveRappen,
  formatChfFiveRappenString,
  type RentalCalcParams,
} from '@/utils/rentalPriceAmortization'
import {
  getRentalAmortizationDefaults,
  DEFAULT_RENTAL_AMORTIZATION,
  type RentalAmortizationDefaults,
} from '@/api/departmentSettings'
import { getWorkshopTickets, type WorkshopTicket } from '@/api/workshop'
import RentalPriceAmortizationCalculator from '@/components/material/RentalPriceAmortizationCalculator.vue'
import SplitModal from '@/components/material/SplitModal.vue'
import { useAuthStore } from '@/stores/auth'
import { usePageHeadStore } from '@/stores/pageHead'
import { useToast } from '@/composables/useToast'
import { useI18n } from 'vue-i18n'
import { printHtmlDocument } from '@/utils/printHtml'
import BatchModal from '@/components/material/BatchModal.vue'
import MoveQuantityModal from '@/components/material/MoveQuantityModal.vue'
import StorageTreeView from '@/components/storage/StorageTreeView.vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import CategoryAutocompleteInput from '@/components/common/CategoryAutocompleteInput.vue'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { unitPriceFromPackSaleChf } from '@/utils/packPricing'

interface Props {
  materialId: string
  departmentId: string
  /** Batch-ID aus URL (z.B. von Lagerübersicht) – öffnet BatchModal zur Slot-Zuordnung */
  initialBatchId?: string
}

const props = defineProps<Props>()
const router = useRouter()
const route = useRoute()
const pageHeadStore = usePageHeadStore()
const authStore = useAuthStore()
const detailTabsStore = useDetailTabsStore()
const toast = useToast()
const { t } = useI18n()
const physicalComboWarningStore = usePhysicalComboWarningStore()

const DETAIL_QUERY_KEYS = {
  tab: 'tab',
  containerBatch: 'containerBatch',
  containerSearch: 'containerSearch',
  legacyStoredInContainerBatch: 'storedInContainerBatch',
  legacyStoredInSearch: 'storedInSearch',
  usedInSearch: 'usedInSearch',
} as const

const emit = defineEmits<{
  close: []
  updated: [material: Material]
  'open-create-for-composition': [{ parentMaterialId: string }]
}>()

const canManageJsMaterial = computed(() => {
  const role = String(authStore.currentDepartmentRole || '').toLowerCase()
  if (role === 'sa' || role === 'superadmin') return true
  return (authStore.userRoles || []).some((r: string) => r.toLowerCase() === 'role_superadmin')
})

// State
const material = ref<Material>({} as Material)
const batches = ref<any[]>([])
const rentalAmortizationDefaults = ref<RentalAmortizationDefaults>({ ...DEFAULT_RENTAL_AMORTIZATION })
const workshopTickets = ref<WorkshopTicket[]>([])
const workshopTicketsLoading = ref(false)

/** Stückliste für Kombos (Tab „Zusammensetzung“) */
const comboComponentsList = ref<ComboComponent[]>([])
const comboComponentsLoading = ref(false)

const showAddCompositionModal = ref(false)
const addCompositionSearch = ref('')
const addCompositionSelected = ref<Material | null>(null)
const addCompositionQty = ref(1)
const addCompositionRole = ref('')
const addCompositionOptional = ref(false)
const addCompositionMode = ref<'fixed' | 'assigned' | 'on_issue' | 'bulk'>('bulk')
const addCompositionError = ref('')
const addCompositionSubmitting = ref(false)

const showEditCompositionModal = ref(false)
const editCompositionComp = ref<ComboComponent | null>(null)
const editCompositionQty = ref(1)
const editCompositionRole = ref('')
const editCompositionOptional = ref(false)
const editCompositionMode = ref<'fixed' | 'assigned' | 'on_issue' | 'bulk'>('bulk')
const editCompositionBatchId = ref('')
const editCompositionBatches = ref<MaterialBatch[]>([])
const editCompositionBatchesLoading = ref(false)
const editCompositionError = ref('')
const editCompositionSubmitting = ref(false)
const deletingCompositionId = ref<string | null>(null)

const canSubmitAddComposition = computed(
  () => !!addCompositionSelected.value && (addCompositionQty.value ?? 0) >= 1
)

const comboAssignmentLabels: Record<string, string> = {
  fixed: 'Fest verbaut',
  assigned: 'Zugewiesen',
  on_issue: 'Bei Ausgabe',
  bulk: 'Mengenware',
}

/** Kurzlabels im Tab Zusammensetzung (voller Text per title) – weniger horizontales Scrollen */
const comboAssignmentLabelsShort: Record<string, string> = {
  fixed: 'Fest',
  assigned: 'Zugew.',
  on_issue: 'Ausgabe',
  bulk: 'Menge',
}

const isComboMaterialView = computed(
  () =>
    material.value?.material_type === 'physical_combo' || material.value?.material_type === 'virtual_combo'
)

/** Zeilen für „Anschaffung aus Zusammensetzung“ (Vermietung-Tab, Kombis) */
const comboRentalRows = ref<
  Array<{
    componentId: string
    name: string
    qty: number
    perPieceChf: number | null
    lineChf: number | null
    optional: boolean
  }>
>([])
const comboRentalLoading = ref(false)
const comboRentalError = ref('')

const acquisitionBasisChf = computed(() => sumAcquisitionBasisFromBatches(batches.value))
const acquisitionPieceCount = computed(() => sumAcquisitionPieceCountFromBatches(batches.value))

const comboRentalHasGap = computed(() => comboRentalRows.value.some((r) => r.lineChf == null))

const rentalAcquisitionBasisChf = computed((): number | null => {
  if (isComboMaterialView.value) {
    let sum = 0
    let has = false
    for (const r of comboRentalRows.value) {
      if (r.lineChf != null) {
        sum += r.lineChf
        has = true
      }
    }
    if (!has) return null
    return roundChfToFiveRappen(sum)
  }
  return acquisitionBasisChf.value
})

const rentalAcquisitionPieceCount = computed(() => {
  if (isComboMaterialView.value) return 1
  return acquisitionPieceCount.value
})

const showRentalAmortizationCalculator = computed(() => {
  const m = material.value
  if (!m?.material_type) return false
  if (m.is_consumable || m.is_food) return false
  if (m.material_type === 'physical') return !!m.tracking_type
  if (m.material_type === 'physical_combo' || m.material_type === 'virtual_combo') return true
  return false
})

const rentalAmortizationContext = computed<'batches' | 'combo'>(() =>
  isComboMaterialView.value ? 'combo' : 'batches'
)

async function loadComboRentalBreakdown() {
  if (!props.materialId) return
  const mt = material.value?.material_type
  if (mt !== 'physical_combo' && mt !== 'virtual_combo') return
  comboRentalLoading.value = true
  comboRentalError.value = ''
  try {
    const list = await getComboComponents(props.materialId)
    const sorted = [...list].sort((a, b) => {
      const o = (a.sort_order ?? 0) - (b.sort_order ?? 0)
      if (o !== 0) return o
      return String(a.component_material?.name || '').localeCompare(
        String(b.component_material?.name || ''),
        'de-CH'
      )
    })
    const ids = [...new Set(sorted.map((c) => c.component_material.id))]
    const materialsById = new Map<string, Material>()
    await Promise.all(
      ids.map(async (id) => {
        try {
          materialsById.set(id, await getMaterial(id))
        } catch {
          /* Zeile ohne Chargen/Preis */
        }
      })
    )
    comboRentalRows.value = sorted.map((comp) => {
      const m = materialsById.get(comp.component_material.id)
      const bs = m?.batches
      const basis = sumAcquisitionBasisFromBatches(bs)
      const pieces = sumAcquisitionPieceCountFromBatches(bs)
      let perPieceChf: number | null = null
      if (basis != null && pieces != null && pieces > 0) {
        const pp = basis / pieces
        perPieceChf = Number.isFinite(pp) && pp >= 0 ? pp : null
      }
      return {
        componentId: comp.component_material.id,
        name: comp.component_material.name,
        qty: comp.qty,
        perPieceChf,
        lineChf: comboLineAcquisitionChf(bs, comp.qty),
        optional: comp.is_optional,
      }
    })
  } catch {
    comboRentalError.value = 'Stückliste konnte nicht geladen werden.'
    comboRentalRows.value = []
  } finally {
    comboRentalLoading.value = false
  }
}

function onRentalCalculatorApply(p: { day: string; week: string; month: string }) {
  const rentalChanged =
    formData.rental_price_day !== p.day ||
    formData.rental_price_week !== p.week ||
    formData.rental_price_month !== p.month
  formData.rental_price_day = p.day
  formData.rental_price_week = p.week
  formData.rental_price_month = p.month
  void nextTick(() => {
    detailTabsStore.setTabDirty(props.materialId, 'material', props.departmentId, hasChanges.value)
    if (rentalChanged) {
      toast.success('Vorschlag übernommen. Speichern nicht vergessen.')
    } else {
      toast.info('Bereits diese Vermietpreise.')
    }
  })
}
const containerBatches = ref<ContainerBatch[]>([])
/** Für Lagerplatz-Spalte: direkte Zeilen + physische Kombi (Elternlager) */
const materialStorageLocations = ref<MaterialStorageLocationsResponse | null>(null)
const categories = ref<Category[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const isGeneratingPublicCode = ref(false)
const activeTab = ref('data')
const containerContentBatchId = ref('')
const containerContentSearch = ref('')
const usedInSearch = ref('')
const syncingFromRoute = ref(false)
const isLoadingContainerBatches = ref(false)
const hasLoadedContainerBatches = ref(false)
const containerContentOverview = ref<StorageOverviewResponse | null>(null)
const isLoadingContainerContentOverview = ref(false)
const isLoadingContainerEditor = ref(false)
const isSavingContainerEditor = ref(false)
const containerEditorMaterialId = ref('')
const containerEditorBatchId = ref('')
const containerEditorOriginal = ref('')
const containerEditorForm = reactive({
  serial_number: '',
  label: '',
  status: 'active',
  notes: '',
})
const showAddToContainerModal = ref(false)
const isLoadingSourceMaterial = ref(false)
const isAddingToContainer = ref(false)
const addToContainerError = ref('')
const addToContainerSearch = ref('')
const addToContainerSourceMaterialId = ref('')
const addToContainerSourceMaterial = ref<Material | null>(null)
const addToContainerSourceBatchId = ref('')
const addToContainerSourceAllocationId = ref('')
const addToContainerQty = ref(1)
const addToContainerMaterialCatalog = ref<Material[] | null>(null)
const isLoadingAddToContainerCatalog = ref(false)

type QrActionMode = 'material' | 'batch' | 'all'
const showQrActionModal = ref(false)
const qrActionMode = ref<QrActionMode>('material')
const qrActionLabel = ref('')
const qrActionCode = ref('')
const qrActionUrl = ref('')
const qrActionEntityId = ref('')

// Batch Modal State
const showBatchModal = ref(false)
const editingBatch = ref<MaterialBatch | null>(null)
const showSplitModal = ref(false)
const showMoveModal = ref(false)
const moveBatch = ref<MaterialBatch | null>(null)

// History State
const historyEntries = ref<MaterialHistoryEntry[]>([])
const selectedHistoryEntry = ref<MaterialHistoryEntry | null>(null)
const isLoadingHistory = ref(false)

// Used-In State
const usedInEntries = ref<UsedInEntry[]>([])
const isLoadingUsedIn = ref(false)

// Form Data
const formData = reactive({
  name: '',
  description: '',
  barcode_tag: '',
  category_id: '',
  storage_address_id: '',
  manufacturer: '',
  model: '',
  ean: '',
  weight: '',
  color: '',
  size_length: '',
  size_width: '',
  size_height: '',
  warranty_until: '',
  rental_price_day: '',
  rental_price_week: '',
  rental_price_month: '',
  rental_deposit: '',
  rental_lead_days: null as number | null,
  rental_max_days: null as number | null,
  rental_external_allowed: false,
  rental_scope: '' as string,
  rental_requires_approval: false,
  rental_notes: '',
  rental_calc_params: null as RentalCalcParams | null,
  pack_size: null as number | null,
  pack_unit: '',
  is_container: false,
  reservation_mode: '' as string,
  is_js_material: false,
  external_source: '',
  sale_price: null as number | null,
  reference_purchase_unit_chf: null as number | null,
  min_stock: null as number | null,
  pack_sale_price_chf: null as number | null,
})

// Original data for change detection
let originalFormData = ''

function normalizeQueryString(value: unknown): string {
  if (Array.isArray(value)) return typeof value[0] === 'string' ? value[0] : ''
  return typeof value === 'string' ? value : ''
}

function mergeAndReplaceQuery(updates: Record<string, string | undefined>) {
  const nextQuery: Record<string, any> = { ...route.query }
  Object.entries(updates).forEach(([key, value]) => {
    if (value && value.trim() !== '') {
      nextQuery[key] = value
    } else {
      delete nextQuery[key]
    }
  })
  const normalizeQueryRecord = (value: Record<string, any>) => {
    const normalized: Record<string, string> = {}
    Object.keys(value).sort().forEach((key) => {
      const str = normalizeQueryString(value[key]).trim()
      if (str) normalized[key] = str
    })
    return normalized
  }
  const currentNormalized = normalizeQueryRecord(route.query as Record<string, any>)
  const nextNormalized = normalizeQueryRecord(nextQuery)
  if (JSON.stringify(currentNormalized) === JSON.stringify(nextNormalized)) return
  router.replace({ path: route.path, query: nextQuery })
}

const storedInContainerOptions = computed(() => {
  const currentMaterialId = String(props.materialId || '')
  const currentMaterialName = String(material.value?.name || '').trim().toLocaleLowerCase('de-CH')
  const filtered = containerBatches.value.filter((batch) => {
    const batchMaterialId = String(batch.material_id || '')
    if (batchMaterialId) return batchMaterialId === currentMaterialId
    if (!currentMaterialName) return false
    return String(batch.material_name || '').trim().toLocaleLowerCase('de-CH') === currentMaterialName
  })

  return filtered
    .map((batch) => ({
      id: batch.id,
      label: formatContainerBatchOptionFullLabel(batch),
    }))
    .sort((a, b) => a.label.localeCompare(b.label, 'de'))
})

/** Tab „Inhalt Kiste/Tasche“ nur bei echten Kisten/Taschen-Artikeln (mind. eine Instanz) oder Deep-Link. */
const showContainerContentTab = computed(() => {
  const hasContainerContext =
    !!normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) ||
    !!normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch])
  if (hasContainerContext) return true
  if (material.value.tracking_type !== 'serialized') return false
  return storedInContainerOptions.value.length > 0
})

// Dynamische Tabs: Kombos fokussiert auf Stückliste & Lager (ohne Bestand/Serien/Verwendet-in-Kette wie Einzelmaterial)
const tabs = computed(() => {
  if (isComboMaterialView.value) {
    const comboTabs = [
      { id: 'data', label: t('components.materialDetail.tabData') },
      { id: 'stored-in', label: t('components.materialDetail.tabStoredIn') },
      { id: 'composition', label: t('components.materialDetail.tabComposition') },
      { id: 'workshop', label: t('components.materialDetail.tabWorkshop') },
    ]
    if (!material.value.is_consumable && !material.value.is_food) {
      comboTabs.push({ id: 'rental', label: t('components.materialDetail.tabRental') })
    }
    const archCount = archivedBatches.value.length
    comboTabs.push(
      {
        id: 'archive',
        label:
          archCount > 0
            ? t('components.materialDetail.tabArchiveCount', { count: archCount })
            : t('components.materialDetail.tabArchive'),
      },
      { id: 'history', label: t('components.materialDetail.tabHistory') }
    )
    return comboTabs
  }

  const baseTabs = [
    { id: 'data', label: t('components.materialDetail.tabData') },
    { id: 'stock', label: t('components.materialDetail.tabStock') },
    { id: 'stored-in', label: t('components.materialDetail.tabStoredIn') },
  ]
  if (material.value.tracking_type === 'serialized') {
    baseTabs.push({ id: 'serials', label: t('components.materialDetail.tabSerials') })
  }
  if (showContainerContentTab.value) {
    baseTabs.push({ id: 'container-content', label: t('components.materialDetail.tabContainerContent') })
  }
  const usedCount = usedInEntries.value.length
  baseTabs.push({
    id: 'used-in',
    label:
      usedCount > 0
        ? t('components.materialDetail.tabUsedInCount', { count: usedCount })
        : t('components.materialDetail.tabUsedIn'),
  })
  baseTabs.push({ id: 'workshop', label: t('components.materialDetail.tabWorkshop') })
  if (!material.value.is_consumable && !material.value.is_food) {
    baseTabs.push({ id: 'rental', label: t('components.materialDetail.tabRental') })
  }
  const archN = archivedBatches.value.length
  baseTabs.push({
    id: 'archive',
    label:
      archN > 0
        ? t('components.materialDetail.tabArchiveCount', { count: archN })
        : t('components.materialDetail.tabArchive'),
  })
  baseTabs.push({ id: 'history', label: t('components.materialDetail.tabHistory') })
  return baseTabs
})

const tabIds = computed(() => tabs.value.map((tab) => tab.id))

function resolveTabId(rawTab: unknown): string {
  const normalized = normalizeQueryString(rawTab)
  return tabIds.value.includes(normalized) ? normalized : 'data'
}

function setActiveTab(tabId: string) {
  if (!tabIds.value.includes(tabId)) return
  activeTab.value = tabId
}

const selectedContainerBatch = computed(() => {
  if (!containerContentBatchId.value) return null
  return containerBatches.value.find((batch) => batch.id === containerContentBatchId.value) || null
})

const containerContentRows = computed(() => {
  const overview = containerContentOverview.value
  const selectedId = containerContentBatchId.value
  if (!overview || !selectedId) return [] as Array<{ materialId: string; materialName: string; qty: number }>
  const grouped = new Map<string, { materialId: string; materialName: string; qty: number }>()
  for (const rack of overview.racks || []) {
    for (const slot of rack.slots || []) {
      for (const item of slot.contents || []) {
        if ((item.container_batch_id || '') !== selectedId) continue
        const existing = grouped.get(item.material_id)
        if (existing) {
          existing.qty += Number(item.qty || 0)
        } else {
          grouped.set(item.material_id, {
            materialId: item.material_id,
            materialName: item.material_name,
            qty: Number(item.qty || 0),
          })
        }
      }
    }
  }
  return Array.from(grouped.values()).sort((a, b) => a.materialName.localeCompare(b.materialName, 'de'))
})

const containerEditorDirty = computed(() => {
  if (!containerEditorBatchId.value) return false
  return JSON.stringify(containerEditorForm) !== containerEditorOriginal.value
})

const addToContainerSourceBatches = computed(() => {
  const batchesList = addToContainerSourceMaterial.value?.batches || []
  return batchesList.filter((batch: any) => (batch.qty || 0) > 0 && batch.status === 'active')
})

const selectedAddToContainerBatch = computed(() => {
  return addToContainerSourceBatches.value.find((batch: any) => String(batch.id) === String(addToContainerSourceBatchId.value)) || null
})

const selectedAddToContainerAllocations = computed((): BatchStorageAllocation[] => {
  const allocs = selectedAddToContainerBatch.value?.allocations
  if (!allocs || !Array.isArray(allocs)) return []
  return allocs
})

const addToContainerMaxQty = computed(() => {
  const allocs = selectedAddToContainerAllocations.value
  if (allocs.length > 0 && addToContainerSourceAllocationId.value) {
    const selected = allocs.find((a) => a.id === addToContainerSourceAllocationId.value)
    if (selected) return Number(selected.qty || 0)
  }
  return Number(selectedAddToContainerBatch.value?.qty || 0)
})

const canSubmitAddToContainer = computed(() => {
  if (!containerContentBatchId.value) return false
  if (!addToContainerSourceMaterialId.value) return false
  if (!selectedAddToContainerBatch.value) return false
  if (selectedAddToContainerAllocations.value.length > 1 && !addToContainerSourceAllocationId.value) return false
  if (addToContainerQty.value < 1) return false
  if (addToContainerQty.value > addToContainerMaxQty.value) return false
  return true
})

// Seriennummern extrahieren (Batches mit is_serialized = true)
const serialBatches = computed(() => {
  return batches.value.filter(b => b.serial_number)
})

const hasAnyQrForPrint = computed(() => {
  if (String(material.value?.public_url || '').trim() !== '') return true
  return (batches.value || []).some((batch: any) => String(batch?.public_url || '').trim() !== '')
})

/** Physische Combo, die mit einer konkreten Kisten-Charge verknüpft ist (QR von der Kiste übernommen). */
const isPhysicalComboFromLinkedContainer = computed(
  () =>
    material.value?.material_type === 'physical_combo' &&
    !!(material.value as { linked_container_batch_id?: string | null }).linked_container_batch_id
)

const qrGenerateButtonLabel = computed(() => {
  if (isGeneratingPublicCode.value) return t('components.materialDetail.qrGenLoading')
  if (isPhysicalComboFromLinkedContainer.value) return t('components.materialDetail.qrGenEnsure')
  return t('components.materialDetail.qrGenCreate')
})

const qrGenerateButtonTitle = computed(() => {
  if (isPhysicalComboFromLinkedContainer.value) {
    return t('components.materialDetail.qrTitleLinkedCombo')
  }
  return ''
})

/** QR im Header: immer wenn Material einen öffentlichen Link hat (Bulk + Serien mit Material-QR). */
const headerMaterialHasPublicQr = computed(
  () => !isLoading.value && String(material.value?.public_url || '').trim() !== ''
)

/**
 * Serienartikel ohne Material-QR: QR liegt auf Chargen — kompakter Einstieg wie „QR-Codes drucken“.
 */
const showHeaderSerialQrShortcut = computed(
  () =>
    !isLoading.value &&
    material.value?.tracking_type === 'serialized' &&
    String(material.value?.public_url || '').trim() === '' &&
    hasAnyQrForPrint.value
)

const showGenerateQrButton = computed(() => {
  if (isLoading.value) return false

  const materialMissing = String(material.value?.public_code || '').trim() === ''
  if (material.value?.tracking_type !== 'serialized') {
    return materialMissing
  }

  const missingSerialCount = serialBatches.value.filter((batch: any) => {
    const serial = String(batch?.serial_number || '').trim()
    if (!serial) return false
    return String(batch?.public_code || '').trim() === ''
  }).length

  /** Nur physische Combo: Anfangsbatch kann ohne Seriennummer sein – dort ein Batch-QR (z. B. Kisten-Referenz). Sonst: serialisiert = nur QR pro Seriennummer. */
  const nonSerialBatchMissingQr =
    material.value?.material_type === 'physical_combo' &&
    (batches.value || []).some((b: any) => {
      if (String(b?.serial_number || '').trim() !== '') return false
      return String(b?.public_code || '').trim() === ''
    })

  return materialMissing || missingSerialCount > 0 || nonSerialBatchMissingQr
})

/** Aus Verkaufspreis pro VE ÷ Stück pro VE (wenn beides gesetzt). */
const packSaleDerivedUnitPrice = computed(() => {
  const pp = formData.pack_sale_price_chf
  const ps = formData.pack_size
  if (pp == null || ps == null) return null
  return unitPriceFromPackSaleChf(Number(pp), Number(ps))
})

const packSaleCalcLine = computed(() => {
  const up = packSaleDerivedUnitPrice.value
  if (up == null) return ''
  const pp = formData.pack_sale_price_chf
  return t('components.materialDetail.packSaleCalcLine', {
    packPrice: pp != null ? Number(pp).toFixed(2) : '—',
    packUnit: formData.pack_unit?.trim() ? formData.pack_unit : t('components.materialDetail.unitGeneric'),
    packSize: formData.pack_size != null ? String(formData.pack_size) : '—',
    unitPrice: up.toFixed(2),
  })
})

function applyPackSaleToUnitSalePrice() {
  const v = packSaleDerivedUnitPrice.value
  if (v == null) return
  formData.sale_price = v
  toast.success(t('components.materialDetail.toastPackSaleApplied'))
}

const statusLabels = computed((): Record<string, string> => ({
  active: t('components.materialDetail.batchStatusActive'),
  defect: t('components.materialDetail.batchStatusDefect'),
  repair: t('components.materialDetail.batchStatusRepair'),
  lost: t('components.materialDetail.batchStatusLost'),
  disposed: t('components.materialDetail.batchStatusDisposed'),
  split_to_serial: t('components.materialDetail.batchStatusSplitToSerial'),
}))

// Archiv-Status: Batches die nicht mehr aktiv im Bestand sind
const archivedStatuses = ['lost', 'disposed', 'split_to_serial']

// Aktive Batches (für Bestand-Tab) – sortiert nach Kaufdatum (neueste zuerst)
// Batches mit qty=0 (z.B. nach Split) ausblenden – bleiben in DB für Historie
const activeBatches = computed(() => {
  return batches.value
    .filter(b => !archivedStatuses.includes(b.status) && (b.qty || 0) > 0)
    .sort((a, b) => (b.acquired_on || '').localeCompare(a.acquired_on || ''))
})

// Archivierte Batches (für Archiv-Tab) – sortiert nach Kaufdatum (neueste zuerst)
const archivedBatches = computed(() => {
  return batches.value
    .filter(b => archivedStatuses.includes(b.status))
    .sort((a, b) => (b.acquired_on || '').localeCompare(a.acquired_on || ''))
})

const splitSourceBatches = computed(() =>
  activeBatches.value.filter((batch) => !batch.serial_number && batch.status === 'active' && (batch.qty || 0) > 0)
)

const stockSortKey = ref<string | null>(null)
const stockSortDir = ref<'asc' | 'desc'>('desc')
const serialSortKey = ref<string | null>(null)
const serialSortDir = ref<'asc' | 'desc'>('desc')
/** batchId → true während PATCH is_container */
const serialIsContainerSaving = reactive<Record<string, boolean>>({})

// Computed
const hasChanges = computed(() => {
  return JSON.stringify(formData) !== originalFormData
})

const propertyBadgeText = computed(() => {
  const m = material.value
  if (!m) return ''

  if (m.is_food) return t('components.materialDetail.badgeFood')
  if (m.is_consumable) return t('components.materialDetail.badgeConsumable')

  const mt = m.material_type || 'physical'

  if (mt === 'virtual_combo') return t('components.materialDetail.badgeVirtualCombo')
  if (mt === 'physical_combo') return t('components.materialDetail.badgePhysicalCombo')

  if (mt === 'physical') {
    const tt = m.tracking_type
    if (tt === 'bulk') return t('components.materialDetail.badgePhysicalBulk')
    if (tt === 'serialized') return t('components.materialDetail.badgePhysicalSerialized')
    return t('components.materialDetail.badgePhysical')
  }

  return t('components.materialDetail.badgeMaterial')
})

const availableStock = computed(() => {
  return batches.value
    .filter(b => b.status === 'active')
    .reduce((sum, b) => sum + b.qty, 0)
})

const defectStock = computed(() => {
  return batches.value
    .filter(b => b.status === 'defect' || b.status === 'repair')
    .reduce((sum, b) => sum + b.qty, 0)
})

const archivedStock = computed(() => {
  return archivedBatches.value.reduce((sum, b) => sum + Math.max(0, b.qty || 0), 0)
})

const packUnitCount = computed(() => {
  const size = material.value.pack_size || 0
  if (!size || size <= 0) return 0
  return Math.floor((material.value.total_stock || 0) / size)
})

const packLooseCount = computed(() => {
  const size = material.value.pack_size || 0
  if (!size || size <= 0) return 0
  return (material.value.total_stock || 0) % size
})

const openLossLabel = computed(() => {
  const reports = material.value.open_loss_reports || 0
  const qty = material.value.open_loss_qty || 0
  return t('components.materialDetail.openLossLine', { reports, qty })
})

// Methods
async function loadComboComponentsForTab() {
  if (!props.materialId || !isComboMaterialView.value) return
  comboComponentsLoading.value = true
  try {
    comboComponentsList.value = await getComboComponents(props.materialId)
  } catch (err) {
    console.error('Zusammensetzung:', err)
    toast.error('Zusammensetzung konnte nicht geladen werden.')
  } finally {
    comboComponentsLoading.value = false
  }
}

async function compositionMaterialFetcher(query: string) {
  const fetcher = createBasicMaterialLookupFetcher(() => props.departmentId)
  const items = await fetcher(query)
  return items.filter((m) => m.id !== props.materialId)
}

function compositionLookupLabel(item: Record<string, unknown>) {
  return String(item?.name ?? '')
}

function formatCompositionLookupSecondary(item: Record<string, unknown>) {
  const t = item?.tracking_type
  const mt = item?.material_type
  if (!t && !mt) return ''
  return [t, mt].filter(Boolean).join(' · ')
}

function openAddCompositionModal() {
  addCompositionSearch.value = ''
  addCompositionSelected.value = null
  addCompositionQty.value = 1
  addCompositionRole.value = ''
  addCompositionOptional.value = false
  addCompositionError.value = ''
  addCompositionMode.value =
    material.value?.material_type === 'virtual_combo' ? 'on_issue' : 'bulk'
  showAddCompositionModal.value = true
}

function closeAddCompositionModal() {
  showAddCompositionModal.value = false
}

function clearAddCompositionSelection() {
  addCompositionSelected.value = null
  addCompositionSearch.value = ''
}

function handleCompositionMaterialSelect(item: Record<string, unknown>) {
  addCompositionSelected.value = item as unknown as Material
}

function emitCreateMaterialForComposition() {
  emit('open-create-for-composition', { parentMaterialId: props.materialId })
}

async function submitAddComposition() {
  if (!addCompositionSelected.value) return
  addCompositionSubmitting.value = true
  addCompositionError.value = ''
  try {
    const maxSort = comboComponentsList.value.reduce(
      (acc, c) => Math.max(acc, c.sort_order ?? 0),
      -1
    )
    await addComboComponent(props.materialId, {
      component_material_id: addCompositionSelected.value.id,
      qty: Math.max(1, addCompositionQty.value || 1),
      component_role: addCompositionRole.value.trim() || undefined,
      is_optional: addCompositionOptional.value,
      assignment_mode: addCompositionMode.value,
      sort_order: maxSort + 1,
    })
    toast.success('Komponente hinzugefügt.')
    showAddCompositionModal.value = false
    await loadComboComponentsForTab()
    await loadMaterial()
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    addCompositionError.value = ax.response?.data?.error || 'Hinzufügen fehlgeschlagen.'
  } finally {
    addCompositionSubmitting.value = false
  }
}

function formatCompositionBatchOption(b: MaterialBatch) {
  const sn = (b.serial_number || '').trim()
  const lb = (b.label || '').trim()
  if (sn && lb) return `${sn} · ${lb}`
  if (sn) return sn
  if (lb) return lb
  return b.id.slice(0, 8) + '…'
}

async function openEditCompositionModal(comp: ComboComponent) {
  editCompositionComp.value = comp
  editCompositionQty.value = comp.qty
  editCompositionRole.value = comp.component_role || ''
  editCompositionOptional.value = comp.is_optional
  editCompositionMode.value = comp.assignment_mode as 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  editCompositionBatchId.value = comp.component_batch?.id || ''
  editCompositionError.value = ''
  editCompositionBatches.value = []
  showEditCompositionModal.value = true
  editCompositionBatchesLoading.value = true
  try {
    const m = await getMaterial(comp.component_material.id)
    editCompositionBatches.value = m.batches || []
  } catch {
    editCompositionBatches.value = []
  } finally {
    editCompositionBatchesLoading.value = false
  }
}

function closeEditCompositionModal() {
  showEditCompositionModal.value = false
  editCompositionComp.value = null
}

async function submitEditComposition() {
  const comp = editCompositionComp.value
  if (!comp) return
  editCompositionSubmitting.value = true
  editCompositionError.value = ''
  try {
    const payload: UpdateComboComponentRequest = {
      qty: Math.max(1, editCompositionQty.value || 1),
      component_role: editCompositionRole.value.trim() || undefined,
      is_optional: editCompositionOptional.value,
      assignment_mode: editCompositionMode.value,
    }
    if (editCompositionBatches.value.length > 0) {
      const bid = editCompositionBatchId.value
      payload.component_batch_id =
        bid && editCompositionBatches.value.some((b) => b.id === bid) ? bid : null
    }
    await updateComboComponent(props.materialId, comp.id, payload)
    toast.success('Komponente gespeichert.')
    closeEditCompositionModal()
    await loadComboComponentsForTab()
    await loadMaterial()
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    editCompositionError.value = ax.response?.data?.error || 'Speichern fehlgeschlagen.'
  } finally {
    editCompositionSubmitting.value = false
  }
}

async function confirmDeleteComposition(comp: ComboComponent) {
  const ok = confirm(
    `Komponente „${comp.component_material.name}“ aus dieser Kombination entfernen?`
  )
  if (!ok) return
  deletingCompositionId.value = comp.id
  try {
    await deleteComboComponent(props.materialId, comp.id)
    toast.success('Komponente entfernt.')
    await loadComboComponentsForTab()
    await loadMaterial()
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    toast.error(ax.response?.data?.error || 'Löschen fehlgeschlagen.')
  } finally {
    deletingCompositionId.value = null
  }
}

function openComponentMaterialDetail(componentMaterialId: string) {
  if (!componentMaterialId) return
  router.push({ path: `/${props.departmentId}/materials/${componentMaterialId}` })
}

async function loadMaterialStorageLocations() {
  if (!props.materialId || !props.departmentId) {
    materialStorageLocations.value = null
    return
  }
  try {
    materialStorageLocations.value = await getMaterialStorageLocations(props.materialId, props.departmentId)
  } catch {
    materialStorageLocations.value = null
  }
}

async function loadMaterial() {
  isLoading.value = true
  try {
    const data = await getMaterial(props.materialId)
    material.value = data
    batches.value = data.batches || []
    if (data.material_type === 'physical_combo' || data.material_type === 'virtual_combo') {
      comboComponentsList.value = data.combo_components ?? []
    } else {
      comboComponentsList.value = []
    }

    populateFormData(data)
    originalFormData = JSON.stringify(formData)

    detailTabsStore.addOrUpdateTab({
      id: props.materialId,
      type: 'material',
      label: data.name || `Material ${props.materialId}`,
      departmentId: props.departmentId,
      path: `/${props.departmentId}/materials/${props.materialId}`,
    })
    await loadMaterialStorageLocations()
    void nextTick(() => {
      if (
        activeTab.value === 'rental' &&
        (data.material_type === 'physical_combo' || data.material_type === 'virtual_combo')
      ) {
        void loadComboRentalBreakdown()
      }
    })
  } catch (err) {
    console.error('Fehler beim Laden:', err)
    materialStorageLocations.value = null
  } finally {
    isLoading.value = false
  }
}

async function generateMaterialPublicCode() {
  if (!props.materialId || isGeneratingPublicCode.value) return
  const linkedKisteCombo = isPhysicalComboFromLinkedContainer.value
  isGeneratingPublicCode.value = true
  try {
    await ensureMaterialPublicCode(props.materialId)
    await loadMaterial()
    emit('updated', material.value)
    if (linkedKisteCombo) {
      toast.success(
        'Öffentlicher Link ist gesetzt: gleicher QR wie an der Referenz-Kiste – es wird kein zweites Etikett erzeugt.'
      )
    } else {
      toast.success('QR-Code wurde erzeugt.')
    }
  } catch (err: any) {
    console.error('Fehler beim öffentlichen QR-Code:', err)
    toast.error(
      err?.response?.data?.error ||
        (linkedKisteCombo ? 'QR-Code konnte nicht übernommen werden.' : 'QR-Code konnte nicht erzeugt werden.')
    )
  } finally {
    isGeneratingPublicCode.value = false
  }
}

async function loadCategories() {
  try {
    categories.value = await getCategories(props.departmentId)
  } catch (err) {
    console.error('Fehler beim Laden der Kategorien:', err)
  }
}

async function handleCategoryReloadFromPicker(saved?: Category) {
  await loadCategories()
  if (saved?.id) {
    formData.category_id = saved.id
  }
}

async function loadContainerBatches() {
  if (isLoadingContainerBatches.value) return
  isLoadingContainerBatches.value = true
  try {
    containerBatches.value = await getContainerBatches(props.departmentId)
    hasLoadedContainerBatches.value = true
  } catch (err) {
    console.error('Fehler beim Laden der Kisten-Batches:', err)
    containerBatches.value = []
    hasLoadedContainerBatches.value = false
  } finally {
    isLoadingContainerBatches.value = false
  }
}

async function ensureContainerBatchesLoaded() {
  if (hasLoadedContainerBatches.value) return
  await loadContainerBatches()
}

async function loadContainerContentOverview() {
  if (!props.departmentId) return
  isLoadingContainerContentOverview.value = true
  try {
    containerContentOverview.value = await getStorageOverview(props.departmentId)
  } catch (err) {
    console.error('Fehler beim Laden der Kisteninhalte:', err)
    containerContentOverview.value = { racks: [] }
  } finally {
    isLoadingContainerContentOverview.value = false
  }
}

function openMaterialById(materialId: string) {
  if (!materialId) return
  router.push({ path: `/${props.departmentId}/materials/${materialId}` })
}

function populateFormData(m: Material) {
  formData.name = m.name || ''
  formData.description = m.description || ''
  formData.barcode_tag = m.barcode_tag || ''
  formData.category_id = m.category?.id || ''
  formData.storage_address_id = m.storage_address?.id || ''
  formData.manufacturer = m.manufacturer || ''
  formData.model = m.model || ''
  formData.ean = m.ean || ''
  formData.weight = m.weight || ''
  formData.color = m.color || ''
  formData.size_length = m.size_length || ''
  formData.size_width = m.size_width || ''
  formData.size_height = m.size_height || ''
  formData.warranty_until = m.warranty_until || ''
  formData.rental_price_day = m.rental_price_day || ''
  formData.rental_price_week = m.rental_price_week || ''
  formData.rental_price_month = m.rental_price_month || ''
  formData.rental_deposit = m.rental_deposit || ''
  formData.rental_lead_days = m.rental_lead_days || null
  formData.rental_max_days = m.rental_max_days || null
  formData.rental_external_allowed = m.rental_external_allowed || false
  formData.rental_scope = m.rental_scope || ''
  formData.rental_requires_approval = m.rental_requires_approval || false
  formData.rental_notes = m.rental_notes || ''
  formData.rental_calc_params = m.rental_calc_params ? { ...m.rental_calc_params } : null
  formData.pack_size = m.pack_size || null
  formData.pack_unit = m.pack_unit || ''
  formData.reservation_mode = m.reservation_mode || ''
  formData.is_container = m.is_container ?? false
  formData.is_js_material = m.is_js_material || false
  formData.external_source = m.external_source || ''
  const parsePriceNum = (v: string | null | undefined) => {
    if (v == null || v === '') return null
    const n = Number(v)
    return Number.isFinite(n) ? n : null
  }
  formData.sale_price = parsePriceNum(m.sale_price)
  formData.reference_purchase_unit_chf = parsePriceNum(m.reference_purchase_unit_chf)
  formData.min_stock = m.min_stock ?? null
  formData.pack_sale_price_chf = parsePriceNum(m.pack_sale_price_chf)
}

function getCategoryPath(): string {
  if (!formData.category_id && !material.value.category?.id) return ''
  return getCategoryPathById(formData.category_id || material.value.category?.id || '')
}

function getCategoryPathById(categoryId: string): string {
  if (!categoryId) return ''
  const mapById = new Map(categories.value.map((c) => [c.id, c]))
  const visited = new Set<string>()
  const parts: string[] = []
  let currentId: string | null = categoryId

  while (currentId) {
    if (visited.has(currentId)) break
    visited.add(currentId)
    const current = mapById.get(currentId)
    if (!current) break
    parts.push(current.name)
    currentId = current.parent_id || null
  }

  return parts.reverse().join(' → ')
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-CH')
}

type BatchLocationEntry = {
  text: string
  containerMaterialId: string | null
  containerBatchId: string | null
  containerSearchSeed: string
}

/** Standort · Gestell (Regal) · Fach – aus API-Feldern; fehlende Teile werden weggelassen. */
function formatAllocationLocationLine(a: {
  storage_address_name?: string | null
  rack?: { name?: string } | null
  slot?: { name?: string } | null
  rack_name?: string
  slot_name?: string
}): string {
  const standort = (a.storage_address_name || '').trim()
  const gestell = (a.rack?.name || a.rack_name || '').trim()
  const fach = (a.slot?.name || a.slot_name || '').trim()
  const parts: string[] = []
  if (standort) parts.push(standort)
  if (gestell) parts.push(gestell)
  if (fach) parts.push(fach)
  return parts.length ? parts.join(' · ') : '-'
}

function formatStorageLocationRow(loc: MaterialStorageLocationRow): string {
  const line = formatAllocationLocationLine({
    storage_address_name: loc.storage_address_name,
    rack: loc.rack_name ? { name: loc.rack_name } : null,
    slot: loc.slot_name ? { name: loc.slot_name } : null,
  })
  if (line !== '-') return line
  const label = (loc.location_label || '').trim()
  if (label) {
    const addr = (loc.storage_address_name || '').trim()
    return addr ? `${addr} · ${label}` : label
  }
  return '-'
}

const buildBatchLocationEntries = (batch: any): BatchLocationEntry[] => {
  const allocations = batch?.allocations
  if (allocations && Array.isArray(allocations) && allocations.length > 0) {
    const resolveContainerBatch = (containerBatchId?: string | null) => {
      if (!containerBatchId) return null
      return containerBatches.value.find((b: any) => b.id === containerBatchId) || batches.value.find((b: any) => b.id === containerBatchId) || null
    }

    return allocations
      .map((a: any) => {
        const loc = formatAllocationLocationLine(a)
        const fallbackContainer = resolveContainerBatch(a.container_batch_id)
        const resolvedContainer = a.container_batch
          ? { ...fallbackContainer, ...a.container_batch }
          : fallbackContainer
        const containerLabel = resolvedContainer?.label || resolvedContainer?.serial_number
        const containerMaterial = resolvedContainer?.material_name
        const containerMaterialId = resolvedContainer?.material_id || null
        const containerBatchId = resolvedContainer?.id || a.container_batch_id || null
        const containerLoc = resolvedContainer
          ? formatAllocationLocationLine({
              storage_address_name: resolvedContainer.storage_address_name,
              rack: resolvedContainer.rack,
              slot: resolvedContainer.slot,
            })
          : ''
        const containerSearchSeed = String(containerLabel || containerMaterial || '').trim()
        if (containerLabel) {
          const materialSuffix = containerMaterial && containerMaterial !== containerLabel ? ` – ${containerMaterial}` : ''
          return {
            text: `${a.qty} in Kiste ${containerLabel}${materialSuffix}${(containerLoc || loc) ? ` (${containerLoc || loc})` : ''}`,
            containerMaterialId,
            containerBatchId,
            containerSearchSeed,
          }
        }
        if (a.container_batch_id) {
          const fallbackName = containerMaterial || 'Kiste'
          return {
            text: `${a.qty} in ${fallbackName}${(containerLoc || loc) ? ` (${containerLoc || loc})` : ''}`,
            containerMaterialId,
            containerBatchId,
            containerSearchSeed,
          }
        }
        return {
          text: `${a.qty} in ${loc}`,
          containerMaterialId: null,
          containerBatchId: null,
          containerSearchSeed: '',
        }
      })
      .filter((entry: BatchLocationEntry | null): entry is BatchLocationEntry => !!entry)
  }

  const ms = materialStorageLocations.value
  const directRows = ms?.direct?.filter((r) => String(r.batch_id) === String(batch?.id)) ?? []
  if (directRows.length > 0) {
    return directRows.map((row) => ({
      text: `${row.qty} in ${formatStorageLocationRow(row)}`,
      containerMaterialId: null,
      containerBatchId: null,
      containerSearchSeed: '',
    }))
  }

  // Nur Felder am Batch (ohne Material-Hauptlager), damit Kombi-Zeilen nicht verdeckt werden
  const fromBatchOnly = formatAllocationLocationLine({
    storage_address_name: batch?.storage_address_name ?? null,
    rack: batch?.rack,
    slot: batch?.slot,
    rack_name: batch?.rack_name,
    slot_name: batch?.slot_name,
  })
  if (fromBatchOnly !== '-') {
    const q = batch?.qty ?? 1
    return [
      {
        text: `${q} in ${fromBatchOnly}`,
        containerMaterialId: null,
        containerBatchId: null,
        containerSearchSeed: '',
      },
    ]
  }

  // Physische Kombi: gleicher realer Ort wie die Kombi-Einheit (API liefert Gestell/Fach).
  // Nur Stücklisten-Zeilen, die dieser Charge zugeordnet sind (component_batch_id), damit jede
  // Seriennummer den passenden Eltern-Kombi-Ort sieht — nicht alle Kombis für alle Zeilen.
  const combos = ms?.via_physical_combo ?? []
  const bid = batch?.id != null ? String(batch.id) : ''
  if (combos.length > 0) {
    const out: BatchLocationEntry[] = []
    for (const block of combos) {
      const linked = block.component_batch_id != null && String(block.component_batch_id).trim() !== ''
      if (linked && String(block.component_batch_id) !== bid) {
        continue
      }
      if (!linked) {
        // Ohne zugewiesene Serien-Charge: keinen Eltern-Lagerplatz jeder Instanz zuschreiben
        continue
      }
      const parentName = (block.parent_name || '').trim() || 'Kombination'
      for (const loc of block.locations || []) {
        const locText = formatStorageLocationRow(loc)
        if (locText === '-' || !locText.trim()) continue
        const q = Number(batch?.qty ?? loc.qty ?? 1) || 1
        out.push({
          text: `${q} in ${locText} (über ${parentName})`,
          containerMaterialId: null,
          containerBatchId: null,
          containerSearchSeed: '',
        })
      }
    }
    if (out.length > 0) {
      return out
    }
  }

  // Fallback: Material-Hauptlagerort + ggf. Batch-Rack
  const fallbackLine = formatAllocationLocationLine({
    storage_address_name: batch?.storage_address_name ?? material.value?.storage_address?.name,
    rack: batch?.rack,
    slot: batch?.slot,
    rack_name: batch?.rack_name,
    slot_name: batch?.slot_name,
  })
  if (fallbackLine !== '-') {
    const q = batch?.qty ?? 1
    return [
      {
        text: `${q} in ${fallbackLine}`,
        containerMaterialId: null,
        containerBatchId: null,
        containerSearchSeed: '',
      },
    ]
  }
  return [{ text: '-', containerMaterialId: null, containerBatchId: null, containerSearchSeed: '' }]
}

function getBatchLocationSortText(batch: any): string {
  const entries = buildBatchLocationEntries(batch)
  return (entries[0]?.text || '').trim()
}

function parseBatchUnitPrice(batch: any): number {
  const v = batch?.unit_price
  if (v == null || v === '') return Number.NaN
  const n = Number(String(v).replace(',', '.'))
  return Number.isFinite(n) ? n : Number.NaN
}

function compareStockBatches(a: any, b: any, key: string): number {
  switch (key) {
    case 'acquired_on':
      return (a.acquired_on || '').localeCompare(b.acquired_on || '')
    case 'qty':
      return (a.qty || 0) - (b.qty || 0)
    case 'label':
      return (a.label || '').localeCompare(b.label || '', 'de-CH', { sensitivity: 'base' })
    case 'unit_price': {
      const pa = parseBatchUnitPrice(a)
      const pb = parseBatchUnitPrice(b)
      const na = Number.isFinite(pa) ? pa : -Infinity
      const nb = Number.isFinite(pb) ? pb : -Infinity
      return na - nb
    }
    case 'location':
      return getBatchLocationSortText(a).localeCompare(getBatchLocationSortText(b), 'de-CH', { sensitivity: 'base' })
    case 'status':
      return (a.status || '').localeCompare(b.status || '')
    case 'notes':
      return (a.notes || '').localeCompare(b.notes || '', 'de-CH', { sensitivity: 'base' })
    default:
      return 0
  }
}

function compareSerialBatches(a: any, b: any, key: string): number {
  switch (key) {
    case 'serial_number':
      return (a.serial_number || '').localeCompare(b.serial_number || '', 'de-CH', { sensitivity: 'base', numeric: true })
    case 'public_code':
      return (a.public_code || '').localeCompare(b.public_code || '', 'de-CH', { sensitivity: 'base' })
    case 'label':
      return (a.label || '').localeCompare(b.label || '', 'de-CH', { sensitivity: 'base' })
    case 'is_container':
      return (a.is_container ? 1 : 0) - (b.is_container ? 1 : 0)
    case 'acquired_on':
      return (a.acquired_on || '').localeCompare(b.acquired_on || '')
    case 'location':
      return getBatchLocationSortText(a).localeCompare(getBatchLocationSortText(b), 'de-CH', { sensitivity: 'base' })
    case 'status':
      return (a.status || '').localeCompare(b.status || '')
    case 'notes':
      return (a.notes || '').localeCompare(b.notes || '', 'de-CH', { sensitivity: 'base' })
    default:
      return 0
  }
}

const sortedActiveBatches = computed(() => {
  const rows = [...activeBatches.value]
  const key = stockSortKey.value
  if (!key) return rows
  const factor = stockSortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => factor * compareStockBatches(a, b, key))
  return rows
})

const sortedSerialBatches = computed(() => {
  const rows = [...serialBatches.value]
  const key = serialSortKey.value
  if (!key) return rows
  const factor = serialSortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => factor * compareSerialBatches(a, b, key))
  return rows
})

function toggleStockSort(key: string) {
  if (stockSortKey.value === key) {
    stockSortDir.value = stockSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    stockSortKey.value = key
    stockSortDir.value = 'desc'
  }
}

function toggleSerialSort(key: string) {
  if (serialSortKey.value === key) {
    serialSortDir.value = serialSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    serialSortKey.value = key
    serialSortDir.value = 'desc'
  }
}

async function onSerialBatchIsContainerChange(batch: MaterialBatch, value: boolean) {
  const id = String(batch.id || '')
  if (!id || serialIsContainerSaving[id]) return
  if (!!batch.is_container === value) return
  serialIsContainerSaving[id] = true
  try {
    await updateBatch(props.materialId, id, { is_container: value })
    await loadMaterial()
    await ensureContainerBatchesLoaded()
    emit('updated', material.value)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Behälter-Markierung konnte nicht gespeichert werden.')
  } finally {
    delete serialIsContainerSaving[id]
  }
}

function openContainerMaterial(materialId: string, containerBatchId?: string | null, containerSearchSeed?: string) {
  if (!materialId) return
  const query: Record<string, string> = {
    [DETAIL_QUERY_KEYS.tab]: 'container-content',
  }
  // Prefer exact container instance selection via dropdown (batch id).
  if (containerBatchId) query[DETAIL_QUERY_KEYS.containerBatch] = containerBatchId
  else if (containerSearchSeed) query[DETAIL_QUERY_KEYS.containerSearch] = containerSearchSeed
  router.push({ path: `/${props.departmentId}/materials/${materialId}`, query })
}

async function loadContainerEditorForSelectedBatch() {
  const selected = selectedContainerBatch.value
  const inferredMaterialId = selected?.material_id
    || (selected?.id && batches.value.some((b: any) => String(b.id) === String(selected.id)) ? props.materialId : '')
  if (!selected?.id || !inferredMaterialId) {
    containerEditorMaterialId.value = ''
    containerEditorBatchId.value = ''
    containerEditorOriginal.value = ''
    containerEditorForm.serial_number = ''
    containerEditorForm.label = ''
    containerEditorForm.status = 'active'
    containerEditorForm.notes = ''
    return
  }
  isLoadingContainerEditor.value = true
  try {
    const m = await getMaterial(inferredMaterialId)
    const batch = (m.batches || []).find((b: any) => String(b.id) === String(selected.id))
    if (!batch) {
      containerEditorMaterialId.value = ''
      containerEditorBatchId.value = ''
      containerEditorOriginal.value = ''
      return
    }
    containerEditorMaterialId.value = inferredMaterialId
    containerEditorBatchId.value = batch.id
    containerEditorForm.serial_number = batch.serial_number || ''
    containerEditorForm.label = batch.label || ''
    containerEditorForm.status = batch.status || 'active'
    containerEditorForm.notes = batch.notes || ''
    containerEditorOriginal.value = JSON.stringify(containerEditorForm)
  } catch (err) {
    console.error('Fehler beim Laden der Kistendetails:', err)
  } finally {
    isLoadingContainerEditor.value = false
  }
}

async function saveContainerEditor() {
  if (!containerEditorMaterialId.value || !containerEditorBatchId.value) return
  if (!containerEditorDirty.value) return
  isSavingContainerEditor.value = true
  try {
    await updateBatch(containerEditorMaterialId.value, containerEditorBatchId.value, {
      serial_number: containerEditorForm.serial_number || null,
      label: containerEditorForm.label || null,
      status: containerEditorForm.status,
      notes: containerEditorForm.notes || null,
    })
    containerEditorOriginal.value = JSON.stringify(containerEditorForm)
    await Promise.all([loadContainerBatches(), loadContainerContentOverview()])
    if (containerEditorMaterialId.value === props.materialId) {
      await loadMaterial()
    }
    toast.success('Kiste/Tasche gespeichert.')
  } catch (err: any) {
    console.error('Fehler beim Speichern der Kiste/Tasche:', err)
    toast.error(err?.response?.data?.error || 'Speichern fehlgeschlagen.')
  } finally {
    isSavingContainerEditor.value = false
  }
}

function resetAddToContainerState() {
  addToContainerError.value = ''
  addToContainerSearch.value = ''
  addToContainerSourceMaterialId.value = ''
  addToContainerSourceMaterial.value = null
  addToContainerSourceBatchId.value = ''
  addToContainerSourceAllocationId.value = ''
  addToContainerQty.value = 1
}

async function ensureAddToContainerMaterialCatalog() {
  if (addToContainerMaterialCatalog.value || isLoadingAddToContainerCatalog.value) return
  isLoadingAddToContainerCatalog.value = true
  try {
    const all = await getMaterials(props.departmentId)
    addToContainerMaterialCatalog.value = all
      .filter((m) => (m.total_stock || 0) > 0)
      .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'de'))
  } catch (err) {
    console.error('Fehler beim Laden der Artikelliste:', err)
    addToContainerMaterialCatalog.value = []
  } finally {
    isLoadingAddToContainerCatalog.value = false
  }
}

async function addToContainerMaterialFetcher(rawQuery: string) {
  const query = String(rawQuery || '').trim().toLocaleLowerCase('de-CH')
  if (!query) return []
  await ensureAddToContainerMaterialCatalog()
  const list = addToContainerMaterialCatalog.value || []
  return list
    .filter((m) => String(m.name || '').toLocaleLowerCase('de-CH').includes(query))
    .slice(0, 5)
}

async function selectAddToContainerMaterial(mat: Material) {
  addToContainerSourceMaterialId.value = mat.id
  addToContainerSearch.value = mat.name || ''
  await loadAddToContainerSourceMaterial()
}

function handleAddToContainerLookupSelect(mat: any) {
  selectAddToContainerMaterial(mat as Material)
}

function formatAddToContainerLookupLabel(item: any) {
  return String(item?.name || '').trim() || 'Unbenannt'
}

function formatAddToContainerLookupSecondary(item: any) {
  const stock = Number(item?.total_stock ?? item?.totalStock ?? 0)
  return `${stock} / ${stock}`
}

function clearAddToContainerMaterialSelection() {
  addToContainerSourceMaterialId.value = ''
  addToContainerSourceMaterial.value = null
  addToContainerSourceBatchId.value = ''
  addToContainerSourceAllocationId.value = ''
  addToContainerQty.value = 1
  addToContainerSearch.value = ''
}

function handleAddToContainerBatchChange() {
  const firstAlloc = selectedAddToContainerAllocations.value[0]
  addToContainerSourceAllocationId.value = firstAlloc?.id || ''
  addToContainerQty.value = 1
}

async function loadAddToContainerSourceMaterial() {
  if (!addToContainerSourceMaterialId.value) {
    addToContainerSourceMaterial.value = null
    addToContainerSourceBatchId.value = ''
    addToContainerSourceAllocationId.value = ''
    addToContainerQty.value = 1
    return
  }
  isLoadingSourceMaterial.value = true
  addToContainerError.value = ''
  try {
    const materialDetails = await getMaterial(addToContainerSourceMaterialId.value)
    addToContainerSourceMaterial.value = materialDetails
    const firstBatch = (materialDetails.batches || []).find((batch: any) => (batch.qty || 0) > 0 && batch.status === 'active')
    addToContainerSourceBatchId.value = firstBatch?.id || ''
    addToContainerSourceAllocationId.value = firstBatch?.allocations?.[0]?.id || ''
    addToContainerQty.value = 1
  } catch (err: any) {
    console.error('Fehler beim Laden des Quellartikels:', err)
    addToContainerError.value = err?.response?.data?.error || 'Quellartikel konnte nicht geladen werden.'
    addToContainerSourceMaterial.value = null
  } finally {
    isLoadingSourceMaterial.value = false
  }
}

function formatAllocationLocationInline(a: BatchStorageAllocation): string {
  const cb = a.container_batch
  const containerLabel = cb?.label || cb?.serial_number
  if (containerLabel) {
    const containerName = cb?.material_name && cb.material_name !== containerLabel ? ` – ${cb.material_name}` : ''
    return `Kiste ${containerLabel}${containerName}`
  }
  const rackName = a.rack?.name || a.rack_id
  const slotName = a.slot?.name || a.slot_id
  return slotName ? `${rackName} / ${slotName}` : String(rackName || '-')
}

function formatSourceBatchOption(batch: any): string {
  const serial = (batch.serial_number || '').trim()
  const label = (batch.label || '').trim()
  const head = label || serial || `Charge ${String(batch.id).slice(-6)}`
  const serialSuffix = serial && serial !== head ? ` · ${serial}` : ''
  return `${head}${serialSuffix} · ${batch.qty} Stk.`
}

async function openAddToContainerModal() {
  if (!containerContentBatchId.value) return
  if (showAddToContainerModal.value) return
  resetAddToContainerState()
  showAddToContainerModal.value = true
}

function closeAddToContainerModal() {
  showAddToContainerModal.value = false
  resetAddToContainerState()
}

function openQrActionModalForMaterial() {
  qrActionMode.value = 'material'
  qrActionEntityId.value = String(material.value?.id || '')
  qrActionLabel.value = material.value?.name || 'Material'
  qrActionCode.value = String(material.value?.public_code || '')
  qrActionUrl.value = String(material.value?.public_url || '')
  showQrActionModal.value = true
}

function openQrActionModalForBatch(batch: any) {
  qrActionMode.value = 'batch'
  qrActionEntityId.value = String(batch?.id || '')
  const serial = String(batch?.serial_number || '').trim()
  const label = String(batch?.label || '').trim()
  qrActionLabel.value = serial || label || `Serie ${String(batch?.id || '').slice(-6)}`
  qrActionCode.value = String(batch?.public_code || '')
  qrActionUrl.value = String(batch?.public_url || '')
  showQrActionModal.value = true
}

function openQrActionModalForAll() {
  qrActionMode.value = 'all'
  qrActionEntityId.value = ''
  qrActionLabel.value = material.value?.name || 'Material'
  qrActionCode.value = ''
  qrActionUrl.value = ''
  showQrActionModal.value = true
}

function closeQrActionModal() {
  showQrActionModal.value = false
}

async function handleQrAddToPrintCart() {
  if (!props.departmentId) {
    toast.error('Kein Department ausgewählt.')
    return
  }

  if (qrActionMode.value === 'all') {
    const payloads: Array<{
      department_id: string
      entity_type: string
      entity_id: string
      label: string
      public_code?: string | null
      public_url: string
    }> = []

    const materialUrl = String(material.value?.public_url || '').trim()
    if (materialUrl) {
      payloads.push({
        department_id: props.departmentId,
        entity_type: 'material',
        entity_id: String(material.value?.id || ''),
        label: String(material.value?.name || 'Material'),
        public_code: String(material.value?.public_code || '') || null,
        public_url: materialUrl,
      })
    }

    for (const batch of serialBatches.value) {
      const url = String(batch?.public_url || '').trim()
      if (!url) continue
      const serial = String(batch?.serial_number || '').trim()
      const label = String(batch?.label || '').trim()
      payloads.push({
        department_id: props.departmentId,
        entity_type: 'batch',
        entity_id: String(batch?.id || ''),
        label: serial || label || `Serie ${String(batch?.id || '').slice(-6)}`,
        public_code: String(batch?.public_code || '') || null,
        public_url: url,
      })
    }

    if (payloads.length === 0) {
      toast.info('Keine QR-Codes zum Hinzufügen vorhanden.')
      return
    }

    try {
      const result = await addPrintCartItemsBulk(props.departmentId, payloads)
      toast.success(`Druckkorb aktualisiert: ${result.created_count} neu, ${result.skipped_count} bereits vorhanden.`)
      closeQrActionModal()
    } catch (err: any) {
      toast.error(err?.response?.data?.error || 'Druckkorb konnte nicht aktualisiert werden.')
    }
    return
  }

  const url = qrActionUrl.value.trim()
  const entityId = qrActionEntityId.value.trim()
  if (!url || !entityId) {
    toast.info('Kein gültiger QR-Link vorhanden.')
    return
  }

  try {
    const result = await addPrintCartItem({
      department_id: props.departmentId,
      entity_type: qrActionMode.value === 'material' ? 'material' : 'batch',
      entity_id: entityId,
      label: qrActionLabel.value || 'QR',
      public_code: qrActionCode.value || null,
      public_url: url,
    })
    toast.success(result.created ? 'Zum Druckkorb hinzugefügt.' : 'Bereits im Druckkorb vorhanden.')
    closeQrActionModal()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || 'Konnte nicht zum Druckkorb hinzufügen.')
  }
}

function handleQrOpenLink() {
  const url = qrActionUrl.value.trim()
  if (!url) {
    toast.info('Kein öffentlicher Link verfügbar.')
    return
  }
  window.open(url, '_blank')
}

async function handleQrCopyLink() {
  const url = qrActionUrl.value.trim()
  if (!url) {
    toast.info('Kein öffentlicher Link verfügbar.')
    return
  }
  await navigator.clipboard.writeText(url)
  toast.success('QR-Link kopiert.')
}

function escapeHtml(raw: string): string {
  return String(raw || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

async function buildPrintRowsForAllQrs(): Promise<Array<{ label: string; code: string; qrDataUrl: string }>> {
  const rows: Array<{ label: string; code: string; qrDataUrl: string }> = []
  const tasks: Array<Promise<void>> = []

  const materialUrl = String(material.value?.public_url || '').trim()
  const materialCode = String(material.value?.public_code || '').trim()
  if (materialUrl) {
    tasks.push((async () => {
      const qrDataUrl = await QRCode.toDataURL(materialUrl, { width: 220, margin: 1 })
      rows.push({
        label: material.value?.name || 'Material',
        code: materialCode,
        qrDataUrl,
      })
    })())
  }

  for (const batch of serialBatches.value) {
    const url = String(batch?.public_url || '').trim()
    if (!url) continue
    const serial = String(batch?.serial_number || '').trim()
    const label = String(batch?.label || '').trim()
    const title = serial || label || `Serie ${String(batch?.id || '').slice(-6)}`
    const code = String(batch?.public_code || '').trim()
    tasks.push((async () => {
      const qrDataUrl = await QRCode.toDataURL(url, { width: 220, margin: 1 })
      rows.push({ label: title, code, qrDataUrl })
    })())
  }

  await Promise.all(tasks)
  return rows
}

async function handleQrPrint() {
  if (qrActionMode.value === 'all') {
    const rows = await buildPrintRowsForAllQrs()
    if (rows.length === 0) {
      toast.info('Keine QR-Codes zum Drucken vorhanden.')
      return
    }
    const cards = rows
      .map((row) => `
        <div class="card">
          <img src="${row.qrDataUrl}" alt="QR" />
          <div class="title">${escapeHtml(row.label)}</div>
          <div class="code">${escapeHtml(row.code || '-')}</div>
        </div>
      `)
      .join('')
    printHtmlDocument(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>QR-Codes - ${escapeHtml(material.value?.name || 'Material')}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 18px; }
    h1 { margin: 0 0 14px; font-size: 18px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
    .card { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px; text-align: center; page-break-inside: avoid; }
    img { width: 160px; height: 160px; object-fit: contain; }
    .title { margin-top: 8px; font-weight: 700; font-size: 13px; }
    .code { margin-top: 4px; font-family: monospace; color: #4b5563; font-size: 12px; }
  </style>
</head>
<body>
  <h1>${escapeHtml(material.value?.name || 'Material')} - QR-Codes</h1>
  <div class="grid">${cards}</div>
</body>
</html>`)
    closeQrActionModal()
    return
  }

  const url = qrActionUrl.value.trim()
  if (!url) {
    toast.info('Kein öffentlicher Link verfügbar.')
    return
  }
  const qrDataUrl = await QRCode.toDataURL(url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>QR-Code - ${escapeHtml(qrActionLabel.value)}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .card { max-width: 360px; border: 1px solid #d1d5db; border-radius: 10px; padding: 14px; text-align: center; }
    img { width: 240px; height: 240px; object-fit: contain; }
    .title { margin-top: 10px; font-weight: 700; font-size: 14px; }
    .code { margin-top: 4px; font-family: monospace; color: #4b5563; font-size: 12px; }
  </style>
</head>
<body>
  <div class="card">
    <img src="${qrDataUrl}" alt="QR" />
    <div class="title">${escapeHtml(qrActionLabel.value)}</div>
    <div class="code">${escapeHtml(qrActionCode.value || '-')}</div>
  </div>
</body>
</html>`)
  closeQrActionModal()
}

async function submitAddToContainer() {
  if (!canSubmitAddToContainer.value || !selectedAddToContainerBatch.value) return
  if (
    containerContentBatchId.value &&
    !(await physicalComboWarningStore.confirmContainerMove([containerContentBatchId.value]))
  ) {
    return
  }
  isAddingToContainer.value = true
  addToContainerError.value = ''
  try {
    await moveBatchQuantity(addToContainerSourceMaterialId.value, selectedAddToContainerBatch.value.id, {
      from_allocation_id: addToContainerSourceAllocationId.value || null,
      to_container_batch_id: containerContentBatchId.value,
      qty: addToContainerQty.value,
    })
    closeAddToContainerModal()
    await loadContainerContentOverview()
    await loadContainerEditorForSelectedBatch()
    await loadContainerBatches()
    await loadMaterial()
    toast.success('Artikel wurde zur Kiste/Tasche hinzugefügt.')
  } catch (err: any) {
    console.error('Fehler beim Hinzufügen zur Kiste:', err)
    addToContainerError.value = err?.response?.data?.error || 'Verschieben fehlgeschlagen.'
  } finally {
    isAddingToContainer.value = false
  }
}

async function save() {
  if (material.value.is_consumable || material.value.is_food) {
    const sp = formData.sale_price
    const rp = formData.reference_purchase_unit_chf
    if (sp == null || Number(sp) <= 0 || rp == null || Number(rp) <= 0) {
      toast.error('Bitte Verkaufs- und Referenz-Einkaufspreis (je Stück, größer 0) eintragen.')
      return
    }
  }
  isSaving.value = true
  try {
    const payload: any = {
      name: formData.name,
      description: formData.description || null,
      barcode_tag: formData.barcode_tag || null,
      category_id: formData.category_id || null,
      storage_address_id: formData.storage_address_id || null,
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
      rental_scope: formData.rental_external_allowed ? (formData.rental_scope || null) : null,
      rental_requires_approval: formData.rental_requires_approval,
      rental_notes: formData.rental_notes || null,
      rental_calc_params: formData.rental_calc_params,
      pack_size: formData.pack_size || null,
      pack_unit: formData.pack_unit || null,
      pack_sale_price_chf:
        formData.pack_sale_price_chf != null && formData.pack_sale_price_chf > 0
          ? String(formData.pack_sale_price_chf)
          : null,
      reservation_mode: formData.reservation_mode || null,
    }
    if (material.value.tracking_type === 'bulk') {
      payload.is_container = formData.is_container
    }

    if (canManageJsMaterial.value) {
      payload.is_js_material = formData.is_js_material
      payload.external_source = formData.is_js_material ? (formData.external_source || 'js_ch') : null
    }

    if (material.value.is_consumable || material.value.is_food) {
      payload.sale_price =
        formData.sale_price != null && formData.sale_price > 0 ? String(formData.sale_price) : null
      payload.reference_purchase_unit_chf =
        formData.reference_purchase_unit_chf != null && formData.reference_purchase_unit_chf > 0
          ? String(formData.reference_purchase_unit_chf)
          : null
    }
    if (material.value.is_consumable) {
      payload.min_stock = formData.min_stock
    }

    const updated = await updateMaterial(props.materialId, payload)
    
    originalFormData = JSON.stringify(formData)
    void nextTick(() => {
      detailTabsStore.setTabDirty(props.materialId, 'material', props.departmentId, false)
    })
    emit('updated', updated)
    
    // History aktualisieren falls Tab aktiv
    if (activeTab.value === 'history') {
      loadHistory()
    } else {
      // History-Cache leeren, damit beim Tab-Wechsel neu geladen wird
      historyEntries.value = []
    }
  } catch (err: any) {
    console.error('Fehler beim Speichern:', err)
    toast.error(err?.response?.data?.error || 'Fehler beim Speichern des Materials')
  } finally {
    isSaving.value = false
  }
}

// History Labels
const actionLabels: Record<string, string> = {
  created: 'Erstellt',
  updated: 'Aktualisiert',
  deleted: 'Gelöscht',
  batch_added: 'Charge hinzugefügt',
  batch_updated: 'Bestand geändert'
}

const fieldLabels: Record<string, string> = {
  name: 'Name',
  description: 'Beschreibung',
  category: 'Kategorie',
  category_id: 'Kategorie (ID)',
  storage_address: 'Lagerort',
  storage_address_id: 'Lagerort (ID)',
  location: 'Standort',
  condition: 'Zustand',
  material_type: 'Material-Typ',
  tracking_type: 'Tracking-Typ',
  is_container: 'Behälter',
  color: 'Farbe',
  size_length: 'Länge',
  size_width: 'Breite',
  size_height: 'Höhe',
  weight: 'Gewicht',
  ean: 'EAN',
  barcode_tag: 'Code / Barcode',
  manufacturer: 'Hersteller',
  model: 'Modell',
  warranty_until: 'Garantie bis',
  rental_external_allowed: 'Externe Vermietung',
  rental_scope: 'Vermietungs-Bereich',
  rental_requires_approval: 'Genehmigung nötig',
  rental_price_day: 'Tagespreis',
  rental_price_week: 'Wochenpreis',
  rental_price_month: 'Monatspreis',
  rental_deposit: 'Kaution',
  rental_lead_days: 'Vorlaufzeit (Tage)',
  rental_max_days: 'Max. Mietdauer (Tage)',
  rental_notes: 'Vermietungs-Hinweise',
  rental_calc_params: 'Amortisationsrechner (Eingaben)',
  pack_size: 'Stück pro Einheit',
  pack_unit: 'Verpackungseinheit',
  is_js_material: 'J&S-Material',
  external_source: 'Externe Quelle',
  // Batch-Felder
  batch_id: 'Chargen-ID',
  'batch.qty': 'Menge',
  'batch.unit_price': 'Stückpreis',
  'batch.status': 'Status',
  'batch.notes': 'Notiz',
  'batch.label': 'Label',
  'batch.serial_number': 'Seriennummer',
  'batch.rack_id': 'Gestell',
  'batch.slot_id': 'Platz',
  'batch.supplier': 'Lieferant',
  acquired_on: 'Kaufdatum',
  qty: 'Menge',
  unit_price: 'Stückpreis'
}

// Used-In Labels
const usedInAssignmentLabels: Record<string, string> = {
  fixed: 'Fest verbaut',
  assigned: 'Zugewiesen',
  on_issue: 'Bei Ausgabe',
  bulk: 'Mengenware'
}

const filteredUsedInEntries = computed(() => {
  const q = usedInSearch.value.trim().toLocaleLowerCase('de-CH')
  if (!q) return usedInEntries.value
  return usedInEntries.value.filter((entry) => {
    const assignmentLabel = (usedInAssignmentLabels[entry.assignment_mode] || entry.assignment_mode || '').toLocaleLowerCase('de-CH')
    const typeLabel = (entry.material_type === 'physical_combo' ? 'physisch' : 'virtuell').toLocaleLowerCase('de-CH')
    const haystack = [
      entry.combo_name,
      entry.component_role || '',
      entry.batch_serial || '',
      assignmentLabel,
      typeLabel,
    ]
      .join(' ')
      .toLocaleLowerCase('de-CH')
    return haystack.includes(q)
  })
})

async function loadUsedIn() {
  isLoadingUsedIn.value = true
  try {
    usedInEntries.value = await getMaterialUsedIn(props.materialId)
  } catch (err) {
    console.error('Fehler beim Laden der Verwendungen:', err)
    usedInEntries.value = []
  } finally {
    isLoadingUsedIn.value = false
  }
}

function navigateToCombo(comboId: string) {
  router.push(`/${props.departmentId}/materials/${comboId}`)
}

async function loadHistory() {
  isLoadingHistory.value = true
  selectedHistoryEntry.value = null
  try {
    historyEntries.value = await getMaterialHistory(props.materialId)
  } catch (err) {
    console.error('Fehler beim Laden der Historie:', err)
    historyEntries.value = []
  } finally {
    isLoadingHistory.value = false
  }
}

function formatHistoryDate(dateStr: string): string {
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatHistoryTime(dateStr: string): string {
  const d = new Date(dateStr)
  return d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function formatChangeValue(val: any): string {
  if (val === null || val === undefined || val === '') return '–'
  if (typeof val === 'boolean') return val ? 'Ja' : 'Nein'
  return String(val)
}

function openAddBatchModal() {
  editingBatch.value = null
  showBatchModal.value = true
}

function getArchivedBatchDisplayQty(batch: any): number {
  if (batch.status === 'split_to_serial' && (batch.qty || 0) === 0) {
    const splitCount = batches.value.filter((b: any) => b.source_batch_id === batch.id).length
    return splitCount > 0 ? splitCount : 0
  }
  return batch.qty ?? 0
}

function openEditBatchModal(batch: any) {
  if (!batch?.id) return
  if (showBatchModal.value && editingBatch.value?.id === batch.id) return
  closeQrActionModal()
  showAddToContainerModal.value = false
  showSplitModal.value = false
  showMoveModal.value = false
  moveBatch.value = null
  editingBatch.value = batch
  showBatchModal.value = true
}

function openMoveQuantityModal(batch: any) {
  moveBatch.value = batch
  showMoveModal.value = true
}

function openMoveQuantityFromHeader() {
  if (material.value.tracking_type === 'serialized') return
  const candidate = activeBatches.value.find((b: any) => (b.qty || 0) > 0)
  if (!candidate) {
    toast.info('Keine verschiebbare Charge vorhanden.')
    return
  }
  openMoveQuantityModal(candidate)
}

function handleMoveSaved(result: { id: string; qty: number; rack_id: string | null; slot_id: string | null; allocations?: any[] }) {
  const idx = batches.value.findIndex((b: any) => b.id === result.id)
  if (idx >= 0) {
    const b = batches.value[idx] as any
    b.qty = result.qty
    b.rack_id = result.rack_id
    b.slot_id = result.slot_id
    if (result.allocations) {
      b.allocations = result.allocations
    }
  }
  loadMaterial()
}

function closeBatchModal() {
  showBatchModal.value = false
  editingBatch.value = null
  // Query-Parameter entfernen, damit Modal nicht erneut öffnet
  if (route.query.batch) {
    const q = { ...route.query }
    delete q.batch
    router.replace({ path: route.path, query: q })
  }
}

function handleClose() {
  // Keine Bestätigung: Tab bleibt offen, Änderungen bleiben erhalten (keep-alive)
  emit('close')
}

function openSplitModal() {
  if (splitSourceBatches.value.length === 0) return
  showSplitModal.value = true
}

async function handleSplitSaved() {
  await loadMaterial()
  if (activeTab.value === 'history') {
    await loadHistory()
  }
  toast.success('Bulk wurde in serialisierte Instanzen aufgeteilt.')
}

async function handleBatchSaved(result: MaterialBatch | AddBatchMultiResponse) {
  if (editingBatch.value) {
    // Update: Batch in lokaler Liste aktualisieren
    const batch = result as MaterialBatch
    const idx = batches.value.findIndex(b => b.id === batch.id)
    if (idx >= 0) {
      batches.value[idx] = { ...batches.value[idx], ...batch }
    }
    if (activeTab.value === 'container-content') {
      await loadContainerContentOverview()
    }
    closeBatchModal()
    return
  }

  // Charge hinzufügen: Liste/Bestand aktualisieren (ohne Full-Reload)
  if ('created_batches' in result) {
    await loadMaterial()
  } else {
    const batch = result as MaterialBatch
    batches.value.push(batch)
    material.value.total_stock = (material.value.total_stock || 0) + batch.qty
  }
  if (activeTab.value === 'container-content') {
    await loadContainerContentOverview()
  }
  toast.success('Charge erfolgreich hinzugefügt')
  closeBatchModal()
}

watch([tabIds, () => route.query[DETAIL_QUERY_KEYS.tab], isLoading], ([ids, routeTab, loading]) => {
  if (loading) {
    const raw = normalizeQueryString(routeTab)
    if (raw && activeTab.value !== raw) {
      syncingFromRoute.value = true
      activeTab.value = raw
      syncingFromRoute.value = false
    }
    return
  }
  const currentRouteTab = normalizeQueryString(routeTab)
  if (!currentRouteTab) {
    if (!ids.includes(activeTab.value)) {
      syncingFromRoute.value = true
      activeTab.value = ids.includes('data') ? 'data' : (ids[0] || 'data')
      syncingFromRoute.value = false
    }
    return
  }
  const resolved = resolveTabId(routeTab)
  if (activeTab.value !== resolved) {
    syncingFromRoute.value = true
    activeTab.value = resolved
    syncingFromRoute.value = false
  }
  if (currentRouteTab && ids.includes(resolved) && currentRouteTab !== resolved) {
    mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: resolved })
  }
}, { immediate: true })

watch(
  () => normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch]),
  (next) => {
  if (containerContentBatchId.value !== next) {
    syncingFromRoute.value = true
    containerContentBatchId.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(
  () => normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerSearch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInSearch]),
  (next) => {
  if (containerContentSearch.value !== next) {
    syncingFromRoute.value = true
    containerContentSearch.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(() => route.query[DETAIL_QUERY_KEYS.usedInSearch], (value) => {
  const next = normalizeQueryString(value)
  if (usedInSearch.value !== next) {
    syncingFromRoute.value = true
    usedInSearch.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(containerContentBatchId, (value) => {
  if (syncingFromRoute.value) return
  const current = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch])
  if (current === value) return
  mergeAndReplaceQuery({
    [DETAIL_QUERY_KEYS.containerBatch]: value || undefined,
    [DETAIL_QUERY_KEYS.legacyStoredInContainerBatch]: undefined,
  })
})

watch(containerContentSearch, (value) => {
  if (syncingFromRoute.value) return
  const current = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerSearch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInSearch])
  if (current === value) return
  mergeAndReplaceQuery({
    [DETAIL_QUERY_KEYS.containerSearch]: value || undefined,
    [DETAIL_QUERY_KEYS.legacyStoredInSearch]: undefined,
  })
})

watch([activeTab, containerContentBatchId], ([tab, batchId]) => {
  if (tab !== 'container-content') return
  if (!batchId) {
    loadContainerEditorForSelectedBatch()
    return
  }
  loadContainerEditorForSelectedBatch()
}, { immediate: true })

watch(selectedContainerBatch, () => {
  if (activeTab.value !== 'container-content') return
  loadContainerEditorForSelectedBatch()
})

watch(usedInSearch, (value) => {
  if (syncingFromRoute.value) return
  if (normalizeQueryString(route.query[DETAIL_QUERY_KEYS.usedInSearch]) === value) return
  mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.usedInSearch]: value || undefined })
})

// Daten laden wenn Tab gewechselt wird
watch(activeTab, (newTab) => {
  const currentRouteTab = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.tab])
  const clearOnLeave: Record<string, string | undefined> = {}
  if (newTab !== 'container-content') {
    clearOnLeave[DETAIL_QUERY_KEYS.containerBatch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.containerSearch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.legacyStoredInSearch] = undefined
  }
  if (newTab !== 'used-in') {
    clearOnLeave[DETAIL_QUERY_KEYS.usedInSearch] = undefined
  }
  if (!syncingFromRoute.value) {
    if (newTab === 'data') {
      if (currentRouteTab) {
        mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: undefined, ...clearOnLeave })
      } else if (Object.keys(clearOnLeave).length > 0) {
        mergeAndReplaceQuery(clearOnLeave)
      }
    } else if (currentRouteTab !== newTab) {
      mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: newTab, ...clearOnLeave })
    } else if (Object.keys(clearOnLeave).length > 0) {
      mergeAndReplaceQuery(clearOnLeave)
    }
  }
  if (newTab === 'history' && historyEntries.value.length === 0) {
    loadHistory()
  }
  if (newTab === 'used-in' && usedInEntries.value.length === 0) {
    loadUsedIn()
  }
  if (newTab === 'composition') {
    void loadComboComponentsForTab()
  }
  if (newTab === 'stock' || newTab === 'serials' || newTab === 'stored-in' || newTab === 'container-content') {
    ensureContainerBatchesLoaded()
  }
  if (newTab === 'container-content') {
    loadContainerContentOverview()
  }
  if (newTab === 'workshop') {
    void loadWorkshopTicketsForMaterial()
  }
  if (newTab === 'rental' && isComboMaterialView.value) {
    void loadComboRentalBreakdown()
  }
}, { immediate: true })

watch(hasChanges, (dirty) => {
  detailTabsStore.setTabDirty(props.materialId, 'material', props.departmentId, dirty)
}, { immediate: true })

watch(
  () => (!isLoading.value && material.value?.name ? String(material.value.name).trim() : ''),
  (name) => {
    if (!name) return
    pageHeadStore.setDynamic(`${name} · eMatChef`, `${name} – Materialdetails in eMatChef.`)
  },
  { immediate: true }
)

// Bei initialBatchId aus Lagerübersicht: BatchModal öffnen zur Slot-Zuordnung
const openedInitialBatchFor = ref<string | null>(null)
watch(
  () => props.initialBatchId,
  (id) => {
    if (!id) openedInitialBatchFor.value = null
  }
)
watch(
  () => ({
    initialBatchId: props.initialBatchId,
    loading: isLoading.value,
    batchIds: batches.value.map((x: any) => x.id).join(','),
  }),
  (state) => {
    const batchId = state.initialBatchId
    if (!batchId || state.loading) return
    if (openedInitialBatchFor.value === String(batchId)) return
    const batch = batches.value.find((x: any) => String(x.id) === String(batchId))
    if (batch) {
      openedInitialBatchFor.value = String(batchId)
      openEditBatchModal(batch)
    }
  },
  { immediate: true }
)

async function loadRentalDefaults() {
  try {
    rentalAmortizationDefaults.value = await getRentalAmortizationDefaults(props.departmentId)
  } catch {
    rentalAmortizationDefaults.value = { ...DEFAULT_RENTAL_AMORTIZATION }
  }
}

async function loadWorkshopTicketsForMaterial() {
  if (!props.materialId || !props.departmentId) return
  workshopTicketsLoading.value = true
  try {
    workshopTickets.value = await getWorkshopTickets(props.departmentId, { material_item_id: props.materialId })
  } catch {
    workshopTickets.value = []
  } finally {
    workshopTicketsLoading.value = false
  }
}

/** keep-alive (AppLayout) + Teleport: Modals hängen sonst im document.body und stapeln sich bei jedem Routenwechsel */
function closeAllDetailModals() {
  showBatchModal.value = false
  showSplitModal.value = false
  showMoveModal.value = false
  showAddToContainerModal.value = false
  showAddCompositionModal.value = false
  showEditCompositionModal.value = false
  showQrActionModal.value = false
  editingBatch.value = null
  moveBatch.value = null
}

onDeactivated(() => {
  closeAllDetailModals()
})

onMounted(() => {
  void Promise.all([loadMaterial(), loadCategories(), loadRentalDefaults()])
})
</script>

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped>
.workshop-tab-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
.workshop-tickets-mini .muted {
  color: #64748b;
}
.field-required-star {
  color: #b91c1c;
  font-weight: 600;
}
.loading-inline {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Sortierbare Tabellenköpfe (Bestand / Seriennummern) */
.th-sort-cell {
  vertical-align: middle;
}

.batch-table .detail-th-sort,
.serials-table .detail-th-sort {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 6px;
  padding: 2px 0;
  margin: 0;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 11px;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6b7280;
  cursor: pointer;
}

.batch-table .detail-th-sort:hover,
.serials-table .detail-th-sort:hover {
  color: #059669;
}

.detail-th-sort > span:first-child {
  min-width: 0;
  text-align: left;
}

.detail-th-sort-arrows {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0;
  line-height: 1;
  flex-shrink: 0;
  margin-left: 2px;
}

.detail-sort-chev {
  font-size: 8px;
  line-height: 1;
  color: #d1d5db;
  transition: color 0.15s ease;
}

.detail-sort-chev.active {
  color: #059669;
}

/* Modal „Komponente hinzufügen“: Such-Dropdown über dem Dialog-Inhalt, nicht vom Scroll abgeschnitten */
.modal-dialog.composition-add-modal {
  overflow: visible;
}
.composition-add-modal :deep(.material-lookup-dropdown) {
  z-index: 3000;
}
</style>
