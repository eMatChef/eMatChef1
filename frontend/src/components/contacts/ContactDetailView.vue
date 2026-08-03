<template>
  <div class="contact-detail-view">
    <header class="detail-header">
      <div class="detail-header-toolbar">
        <EButton variant="secondary" size="small" class="contact-detail-back-btn" @click="goBack">
          <v-icon icon="mdi-arrow-left" start size="20" />
          {{ backLabel }}
        </EButton>
        <div v-if="isCreateMode" class="header-actions contact-detail-header-actions">
          <EButton variant="primary" size="small" :loading="isSaving" :disabled="isSaving" @click="saveCreate">
            {{ t('common.save') }}
          </EButton>
        </div>
        <div v-else-if="contact?.is_deleted && canManageDeletedContacts" class="header-actions contact-detail-header-actions">
          <EButton variant="primary" size="small" :disabled="isRestoring" :loading="isRestoring" @click="handleRestore">
            {{ isRestoring ? t('contacts.detail.loading') : t('contacts.restore') }}
          </EButton>
          <EButton
            variant="danger"
            size="small"
            :disabled="isPermanentDeleting"
            :loading="isPermanentDeleting"
            @click="confirmPermanentDelete"
          >
            {{ isPermanentDeleting ? t('contacts.permanentDeleting') : t('contacts.permanentDelete') }}
          </EButton>
        </div>
        <div v-else-if="contact && !isReadOnly" class="header-actions contact-detail-header-actions">
          <EButton
            variant="danger"
            size="small"
            :disabled="isDeleting"
            @click="confirmDelete"
          >
            <v-icon icon="mdi-delete-outline" start size="18" />
            {{ isDeleting ? t('contacts.detail.deleting') : t('common.delete') }}
          </EButton>
        </div>
      </div>
      <div class="header-title">
        <div class="contact-avatar-lg" :class="headerTypeClass">
          {{ headerInitials }}
        </div>
        <div>
          <h1>{{ headerTitle }}</h1>
          <span v-if="headerSubtitle" class="header-subtitle">{{ headerSubtitle }}</span>
        </div>
      </div>
    </header>

    <div v-if="contact?.is_deleted" class="deleted-banner" role="status">
      {{ t('contacts.detail.deletedBanner') }}
    </div>

    <ELoadingState
      v-if="isLoading && !isCreateMode"
      variant="card"
      :message="t('contacts.detail.loading')"
    />

    <div v-else-if="error && !isCreateMode" class="contact-detail-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadContact">{{ t('common.retry') }}</EButton>
    </div>

    <div v-else-if="isCreateMode || contact" class="detail-content">
      <div class="content-layout">
        <main class="content-main">
          <p v-if="saveError" class="contact-detail-error">{{ saveError }}</p>

          <!-- Kontaktdaten -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                {{ t('contacts.detail.sectionContactData') }}
              </h2>
            </div>

            <div v-if="isCreateMode" class="section-inline-body">
              <ContactInlineFields
                v-model="draft"
                section="basics"
                :allowed-types="editAllowedTypes"
                :show-pin-color="draft.type === 'event_poi'"
              />
            </div>
            <div v-else-if="!isReadOnly" class="form-grid">
              <AutoSaveField
                v-model="formData.name"
                :baseline="baselines.name"
                :label="t('settings.addressForm.designation')"
                :placeholder="t('settings.addressForm.designationPlaceholder')"
                :save="(v) => saveContactField('name', v)"
              />
              <AutoSaveField
                v-if="!isEventContactType"
                v-model="formData.type"
                :baseline="baselines.type"
                type="select"
                :options="typeSelectOptions"
                :label="t('settings.addressForm.type')"
                :disabled="typeFieldLocked"
                :save="(v) => saveContactField('type', v)"
              />
              <div v-else class="info-item form-group">
                <span class="info-label">{{ t('settings.addressForm.type') }}</span>
                <span class="info-value">
                  <span class="address-type-badge event">{{ addressTypeLabel('event') }}</span>
                </span>
              </div>
              <AutoSaveField
                v-if="formData.type === 'storage'"
                v-model="formData.is_primary"
                :baseline="baselines.is_primary"
                type="checkbox"
                :label="t('common.status')"
                :checkbox-label="t('settings.addressModal.primaryStorageHint')"
                span-class="form-group span-2"
                :save="(v) => saveContactField('is_primary', v)"
              />
              <div v-if="formData.type === 'event_poi'" class="form-group span-2 pin-color-field">
                <span class="form-label">{{ t('settings.addressModal.pinColorLabel') }}</span>
                <div class="pin-color-swatches">
                  <button
                    v-for="color in PIN_COLOR_PRESETS"
                    :key="color"
                    type="button"
                    class="pin-color-swatch"
                    :class="{ 'is-selected': formData.pin_color === color }"
                    :style="{ background: color }"
                    :title="color"
                    @click="savePinColor(color)"
                  />
                </div>
              </div>
              <AutoSaveField
                v-model="formData.company"
                :baseline="baselines.company"
                :label="t('settings.addressForm.company')"
                :placeholder="t('common.optional')"
                span-class="form-group span-2"
                :save="(v) => saveContactField('company', v)"
              />
              <AutoSaveField
                v-model="formData.contact_first_name"
                :baseline="baselines.contact_first_name"
                :label="t('settings.addressForm.contactFirstName')"
                :placeholder="t('common.optional')"
                :save="(v) => saveContactField('contact_first_name', v)"
              />
              <AutoSaveField
                v-model="formData.contact_last_name"
                :baseline="baselines.contact_last_name"
                :label="t('settings.addressForm.contactLastName')"
                :placeholder="t('common.optional')"
                :save="(v) => saveContactField('contact_last_name', v)"
              />
            </div>
            <div v-else class="info-grid">
              <div class="info-item">
                <span class="info-label">{{ t('settings.addressForm.designation') }}</span>
                <span class="info-value">{{ contact?.name || '—' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">{{ t('settings.addressForm.company') }}</span>
                <span class="info-value">{{ contact?.company || '—' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">{{ t('settings.addressForm.contactPerson') }}</span>
                <span class="info-value">{{ contact ? (formatContactPerson(contact) || '—') : '—' }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">{{ t('settings.addressForm.type') }}</span>
                <span class="info-value">
                  <span v-if="contact" class="address-type-badge" :class="contact.type">{{ addressTypeLabel(contact.type) }}</span>
                </span>
              </div>
              <div v-if="contact?.is_primary" class="info-item">
                <span class="info-label">{{ t('common.status') }}</span>
                <span class="info-value">
                  <span class="primary-badge">{{ t('contacts.detail.primaryAddress') }}</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Kommunikation -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
                {{ t('contacts.detail.sectionCommunication') }}
              </h2>
            </div>

            <div v-if="isCreateMode" class="section-inline-body">
              <ContactInlineFields v-model="draft" section="communication" />
            </div>
            <div v-else-if="!isReadOnly" class="contact-actions-grid">
              <!-- E-Mail -->
              <div class="comm-field-card" :ref="(el) => setCommCardEl('email', el)">
                <button
                  type="button"
                  class="comm-field-edit-btn"
                  :aria-label="editingCommFields.email ? t('common.close') : t('common.edit')"
                  @click.stop="editingCommFields.email ? closeCommField('email') : (editingCommFields.email = true)"
                >
                  <v-icon :icon="editingCommFields.email ? 'mdi-close' : 'mdi-pencil-outline'" size="16" />
                </button>
                <AutoSaveField
                  v-if="editingCommFields.email"
                  v-model="formData.email"
                  :baseline="baselines.email"
                  :label="t('settings.addressForm.email')"
                  :placeholder="t('settings.addressForm.emailPlaceholder')"
                  span-class="form-group"
                  :save="(v) => saveContactField('email', v)"
                />
                <a
                  v-else-if="contact?.email"
                  :href="'mailto:' + contact.email"
                  class="contact-action-card contact-action-card--in-field"
                >
                  <div class="action-icon email">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="4" width="20" height="16" rx="2"/>
                      <path d="M22 4l-10 8L2 4"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.email') }}</span>
                    <span class="action-value">{{ contact.email }}</span>
                  </div>
                </a>
                <button
                  v-else
                  type="button"
                  class="contact-action-card contact-action-card--empty"
                  @click="editingCommFields.email = true"
                >
                  <div class="action-icon email">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="4" width="20" height="16" rx="2"/>
                      <path d="M22 4l-10 8L2 4"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.email') }}</span>
                    <span class="action-value muted">{{ t('contacts.detail.addEmailCta') }}</span>
                  </div>
                </button>
              </div>

              <!-- Telefon -->
              <div class="comm-field-card" :ref="(el) => setCommCardEl('phone', el)">
                <button
                  type="button"
                  class="comm-field-edit-btn"
                  :aria-label="editingCommFields.phone ? t('common.close') : t('common.edit')"
                  @click.stop="editingCommFields.phone ? closeCommField('phone') : (editingCommFields.phone = true)"
                >
                  <v-icon :icon="editingCommFields.phone ? 'mdi-close' : 'mdi-pencil-outline'" size="16" />
                </button>
                <AutoSaveField
                  v-if="editingCommFields.phone"
                  v-model="formData.phone"
                  :baseline="baselines.phone"
                  :label="t('settings.addressForm.phone')"
                  :placeholder="t('settings.addressForm.phonePlaceholder')"
                  span-class="form-group"
                  :save="(v) => saveContactField('phone', v)"
                />
                <a
                  v-else-if="contact?.phone"
                  :href="'tel:' + contact.phone"
                  class="contact-action-card contact-action-card--in-field"
                >
                  <div class="action-icon phone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.phone') }}</span>
                    <span class="action-value">{{ contact.phone }}</span>
                  </div>
                </a>
                <button
                  v-else
                  type="button"
                  class="contact-action-card contact-action-card--empty"
                  @click="editingCommFields.phone = true"
                >
                  <div class="action-icon phone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.phone') }}</span>
                    <span class="action-value muted">{{ t('contacts.detail.addPhoneCta') }}</span>
                  </div>
                </button>
              </div>

              <!-- Mobile -->
              <div class="comm-field-card" :ref="(el) => setCommCardEl('mobile', el)">
                <button
                  type="button"
                  class="comm-field-edit-btn"
                  :aria-label="editingCommFields.mobile ? t('common.close') : t('common.edit')"
                  @click.stop="editingCommFields.mobile ? closeCommField('mobile') : (editingCommFields.mobile = true)"
                >
                  <v-icon :icon="editingCommFields.mobile ? 'mdi-close' : 'mdi-pencil-outline'" size="16" />
                </button>
                <AutoSaveField
                  v-if="editingCommFields.mobile"
                  v-model="formData.mobile"
                  :baseline="baselines.mobile"
                  :label="t('settings.addressForm.mobile')"
                  :placeholder="t('settings.addressForm.mobilePlaceholder')"
                  span-class="form-group"
                  :save="(v) => saveContactField('mobile', v)"
                />
                <a
                  v-else-if="contact?.mobile"
                  :href="'tel:' + contact.mobile"
                  class="contact-action-card contact-action-card--in-field"
                >
                  <div class="action-icon mobile">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                      <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.mobile') }}</span>
                    <span class="action-value">{{ contact.mobile }}</span>
                  </div>
                </a>
                <button
                  v-else
                  type="button"
                  class="contact-action-card contact-action-card--empty"
                  @click="editingCommFields.mobile = true"
                >
                  <div class="action-icon mobile">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                      <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.mobile') }}</span>
                    <span class="action-value muted">{{ t('contacts.detail.addMobileCta') }}</span>
                  </div>
                </button>
              </div>
            </div>
            <template v-else-if="contact?.email || contact?.phone || contact?.mobile">
              <div class="contact-actions-grid">
                <a v-if="contact.email" :href="'mailto:' + contact.email" class="contact-action-card">
                  <div class="action-icon email">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="4" width="20" height="16" rx="2"/>
                      <path d="M22 4l-10 8L2 4"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.email') }}</span>
                    <span class="action-value">{{ contact.email }}</span>
                  </div>
                </a>
                <a v-if="contact.phone" :href="'tel:' + contact.phone" class="contact-action-card">
                  <div class="action-icon phone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.phone') }}</span>
                    <span class="action-value">{{ contact.phone }}</span>
                  </div>
                </a>
                <a v-if="contact.mobile" :href="'tel:' + contact.mobile" class="contact-action-card">
                  <div class="action-icon mobile">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                      <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                  </div>
                  <div class="action-info">
                    <span class="action-label">{{ t('settings.addressForm.mobile') }}</span>
                    <span class="action-value">{{ contact.mobile }}</span>
                  </div>
                </a>
              </div>
            </template>
            <div v-else class="empty-section">
              <p>{{ t('contacts.detail.noCommunication') }}</p>
            </div>
          </div>

          <!-- Adresse (nicht bei Event — Standort per Karte) -->
          <div v-if="showStreetAddressSection" class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                {{ t('contacts.detail.sectionAddress') }}
              </h2>
            </div>

            <div v-if="isCreateMode" class="section-inline-body">
              <ContactInlineFields v-model="draft" section="address" />
            </div>
            <template v-else-if="!isReadOnly">
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.address_line2"
                  :baseline="baselines.address_line2"
                  :label="t('settings.addressForm.addressExtra')"
                  :placeholder="t('settings.addressForm.addressExtraPlaceholder')"
                  span-class="form-group span-2"
                  :save="(v) => saveContactField('address_line2', v)"
                />
                <AutoSaveField
                  v-model="formData.street"
                  :baseline="baselines.street"
                  :label="t('settings.addressForm.street')"
                  :placeholder="t('settings.addressForm.streetPlaceholder')"
                  :save="(v) => saveContactField('street', v)"
                />
                <AutoSaveField
                  v-model="formData.street_number"
                  :baseline="baselines.street_number"
                  :label="t('settings.addressForm.streetNumber')"
                  :placeholder="t('settings.addressForm.streetNumberPlaceholder')"
                  :save="(v) => saveContactField('street_number', v)"
                />
                <AutoSaveField
                  v-model="formData.postal_code"
                  :baseline="baselines.postal_code"
                  :label="t('settings.addressForm.postalCode')"
                  :placeholder="t('settings.addressForm.postalPlaceholder')"
                  :save="(v) => saveContactField('postal_code', v)"
                />
                <AutoSaveField
                  v-model="formData.city"
                  :baseline="baselines.city"
                  :label="t('settings.addressForm.city')"
                  :placeholder="t('settings.addressForm.cityPlaceholder')"
                  :save="(v) => saveContactField('city', v)"
                />
                <AutoSaveField
                  v-model="formData.canton"
                  :baseline="baselines.canton"
                  type="select"
                  :options="cantonSelectOptions"
                  :label="t('settings.addressForm.canton')"
                  :save="(v) => saveContactField('canton', v)"
                />
                <AutoSaveField
                  v-model="formData.country"
                  :baseline="baselines.country"
                  :label="t('settings.addressForm.country')"
                  :save="(v) => saveContactField('country', v)"
                />
              </div>
              <div v-if="showAddressMapActions" class="address-actions address-actions--below-form">
                <a
                  :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(contact!.full_address)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="map-link"
                >
                  {{ t('contacts.detail.openGoogleMaps') }}
                </a>
                <button type="button" class="copy-btn" @click="copyAddress">
                  {{ copySuccess ? t('contacts.detail.copied') : t('contacts.detail.copyAddress') }}
                </button>
              </div>
            </template>
            <div v-else class="address-display">
              <div class="address-lines">
                <span v-if="contact?.company" class="address-line bold">{{ contact.company }}</span>
                <span v-if="contact?.address_line2" class="address-line">{{ contact.address_line2 }}</span>
                <span class="address-line">{{ contact?.street_line || '—' }}</span>
                <span class="address-line">{{ contact?.city_line || '—' }}</span>
                <span v-if="contact?.canton" class="address-line">{{ SWISS_CANTONS[contact.canton] || contact.canton }}</span>
                <span v-if="contact && contact.country !== 'Schweiz'" class="address-line">{{ contact.country }}</span>
              </div>
              <div v-if="showAddressMapActions" class="address-actions">
                <a
                  :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(contact!.full_address)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="map-link"
                >
                  {{ t('contacts.detail.openGoogleMaps') }}
                </a>
                <button type="button" class="copy-btn" @click="copyAddress">
                  {{ copySuccess ? t('contacts.detail.copied') : t('contacts.detail.copyAddress') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Standort: Karte (nicht bei Event — dort nur Standorte-Karte unten) -->
          <div
            v-if="!isCreateMode && contact?.type !== 'event'"
            ref="locationSectionRef"
            class="section-card section-card--location"
          >
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                  <line x1="8" y1="2" x2="8" y2="18"/>
                  <line x1="16" y1="6" x2="16" y2="22"/>
                </svg>
                {{ t('contacts.detail.sectionLocation') }}
              </h2>
              <div v-if="!isReadOnly" class="location-header-actions">
                <EButton
                  v-if="editingLocation"
                  variant="primary"
                  size="small"
                  :loading="isSavingLocation"
                  :disabled="!canAcceptLocationDraft"
                  @click="acceptLocationDraft"
                >
                  {{ t('contacts.detail.acceptLocation') }}
                </EButton>
                <button
                  v-else
                  type="button"
                  class="comm-field-edit-btn location-edit-btn"
                  :aria-label="t('common.edit')"
                  :disabled="!canStartLocationEdit"
                  @click="startLocationEdit"
                >
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
              </div>
            </div>

            <p v-if="editingLocation" class="location-edit-hint">{{ t('contacts.detail.mapEditHint') }}</p>

            <MapView
              v-if="showLocationMap"
              ref="mapRef"
              :latitude="mapDisplayLat"
              :longitude="mapDisplayLng"
              :address="contact?.full_address || ''"
              :editable="editingLocation"
              :interactive="editingLocation"
              :prefer-swiss-map="isSwiss"
              :show-coordinates="true"
              :show-layer-control="editingLocation"
              :show-external-map-links="!editingLocation"
              height="350px"
              @update:latitude="onLocationDraftLat"
              @update:longitude="onLocationDraftLng"
            />
            <div v-else class="empty-section">
              <p class="map-no-coords">{{ t('contacts.detail.noCoordinates') }}</p>
              <button
                v-if="!isReadOnly && canGeocodeFromAddress"
                type="button"
                class="btn-add-data"
                :disabled="isGeocodingLocation"
                @click="geocodeAndSaveLocation"
              >
                {{ isGeocodingLocation ? t('contacts.detail.searching') : t('contacts.detail.setLocationCta') }}
              </button>
            </div>
          </div>

          <!-- Event: Standorte-Karte + Accordion (Kind-Modal) -->
          <div v-if="!isCreateMode && contact?.type === 'event'" class="section-card section-card--location">
            <EventVenueDetailLocations
              ref="eventLocationsRef"
              :event-address="contact"
              :child-addresses="childAddresses"
              :read-only="isReadOnly"
              @edit-child="openChildEditModal"
              @create-child="openChildCreateModal"
              @venue-updated="handleVenueUpdated"
            />
          </div>

          <!-- Notizen -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="16" x2="12" y2="12"/>
                  <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                {{ t('contacts.detail.sectionNotes') }}
              </h2>
            </div>
            <div v-if="isCreateMode" class="section-inline-body">
              <ContactInlineFields v-model="draft" section="notes" />
            </div>
            <div v-else-if="!isReadOnly" class="form-grid">
              <AutoSaveField
                v-model="formData.additional_info"
                :baseline="baselines.additional_info"
                type="textarea"
                :rows="4"
                :label="t('settings.addressForm.additionalInfo')"
                :placeholder="t('settings.addressForm.additionalInfoPlaceholder')"
                span-class="form-group span-2"
                :save="(v) => saveContactField('additional_info', v)"
              />
            </div>
            <template v-else>
              <p v-if="contact?.additional_info" class="additional-info-text">{{ contact.additional_info }}</p>
              <div v-else class="empty-section">
                <p>{{ t('contacts.detail.noAdditionalInfo') }}</p>
              </div>
            </template>
          </div>

          <div v-if="isCreateMode" class="create-save-bar">
            <EButton variant="secondary" :disabled="isSaving" @click="goBack">{{ t('common.cancel') }}</EButton>
            <EButton variant="primary" :loading="isSaving" @click="saveCreate">{{ t('common.save') }}</EButton>
          </div>
        </main>
      </div>
    </div>

    <!-- Kindadresse: Zustellpunkt / Event-Punkt (Farbe + Bezeichnung) -->
    <AddressModal
      v-if="showChildModal"
      :department-id="departmentId"
      :address="childModalAddress"
      :default-type="childModalDefaultType"
      :parent-id="contact?.id ?? null"
      :default-name="childModalDefaultName"
      :allowed-types="childModalAllowedTypes"
      @close="closeChildModal"
      @saved="handleChildSaved"
    />

    <EDialog
      v-model="showPermanentDeleteConfirm"
      :max-width="440"
      :title="t('contacts.permanentDeleteTitle')"
    >
      <p class="text-muted">
        {{ t('contacts.permanentDeleteMessage', { name: contact?.name || contact?.company || t('contacts.detail.deleteNameFallback') }) }}
      </p>
      <template #actions>
        <EButton variant="secondary" @click="showPermanentDeleteConfirm = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="danger" :disabled="isPermanentDeleting" :loading="isPermanentDeleting" @click="handlePermanentDelete">
          {{ isPermanentDeleting ? t('contacts.permanentDeleting') : t('contacts.permanentDelete') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showDeleteConfirm"
      :max-width="440"
      :title="t('contacts.detail.deleteTitle')"
    >
      <p class="text-muted">
        {{ t('contacts.detail.deleteMessage', { name: contact?.name || contact?.company || t('contacts.detail.deleteNameFallback') }) }}
      </p>
      <template #actions>
        <EButton variant="secondary" @click="showDeleteConfirm = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="danger" :disabled="isDeleting" :loading="isDeleting" @click="handleDelete">
          {{ isDeleting ? t('contacts.detail.deleting') : t('common.delete') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, onBeforeUnmount, watch, nextTick, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import {
  getAddress,
  createAddress,
  updateAddress,
  deleteAddress,
  restoreAddress,
  permanentDeleteAddress,
  setAddressPrimary,
  ADDRESS_TYPES,
  SWISS_CANTONS,
  type Address,
  type AddressFormData,
} from '@/api/addresses'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'
import EventVenueDetailLocations from '@/components/contacts/EventVenueDetailLocations.vue'
import ContactInlineFields from '@/components/contacts/ContactInlineFields.vue'
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import type { AutoSaveFieldValue, AutoSaveSelectOption } from '@/components/common/autoSave/types'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'
import {
  useDepartmentMemberRole,
  canUserManageContactType,
  USER_CONTACT_CREATE_TYPES,
} from '@/composables/useDepartmentMemberRole'

const props = withDefaults(
  defineProps<{
    contactId?: string | null
    departmentId: string
    mode?: 'view' | 'create'
    defaultType?: string
  }>(),
  {
    contactId: null,
    mode: 'view',
    defaultType: 'customer',
  },
)

const router = useRouter()
const { isUserRole, canManageDeletedContacts } = useDepartmentMemberRole()

const emit = defineEmits<{
  close: []
  updated: []
  deleted: []
  created: [address: Address]
}>()

const toast = useToast()
const { t, te } = useI18n()

const isCreateMode = computed(() => props.mode === 'create' || !props.contactId)

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}

const contact = ref<Address | null>(null)
const childAddresses = ref<Address[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)
const saveError = ref<string | null>(null)
const isSaving = ref(false)

const draft = ref<Partial<AddressFormData>>(emptyDraft())

type ContactFormState = {
  name: string | null
  type: string
  company: string | null
  contact_first_name: string | null
  contact_last_name: string | null
  is_primary: boolean
  pin_color: string | null
  email: string | null
  phone: string | null
  mobile: string | null
  address_line2: string | null
  street: string | null
  street_number: string | null
  postal_code: string | null
  city: string | null
  canton: string | null
  country: string
  additional_info: string | null
}

type ContactAutoSaveField = keyof ContactFormState

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

function emptyFormState(type = 'customer'): ContactFormState {
  return {
    name: null,
    type,
    company: null,
    contact_first_name: null,
    contact_last_name: null,
    is_primary: false,
    pin_color: '#16a34a',
    email: null,
    phone: null,
    mobile: null,
    address_line2: null,
    street: null,
    street_number: null,
    postal_code: null,
    city: null,
    canton: null,
    country: 'Schweiz',
    additional_info: null,
  }
}

function formStateFromAddress(a: Address): ContactFormState {
  return {
    name: a.name,
    type: a.type,
    company: a.company,
    contact_first_name: a.contact_first_name,
    contact_last_name: a.contact_last_name,
    is_primary: !!a.is_primary,
    pin_color: a.pin_color || '#16a34a',
    email: a.email,
    phone: a.phone,
    mobile: a.mobile,
    address_line2: a.address_line2,
    street: a.street,
    street_number: a.street_number,
    postal_code: a.postal_code,
    city: a.city,
    canton: a.canton,
    country: a.country || 'Schweiz',
    additional_info: a.additional_info,
  }
}

const formData = reactive<ContactFormState>(emptyFormState())
const baselines = reactive<ContactFormState>(emptyFormState())

function syncFormFromAddress(a: Address) {
  const next = formStateFromAddress(a)
  Object.assign(formData, next)
  Object.assign(baselines, next)
}

const showChildModal = ref(false)
const childModalAddress = ref<Address | null>(null)
const childModalDefaultType = ref<'event_delivery' | 'event_poi'>('event_delivery')
const editingCommFields = reactive({
  email: false,
  phone: false,
  mobile: false,
})

type CommField = 'email' | 'phone' | 'mobile'

const commCardEls: Record<CommField, HTMLElement | null> = {
  email: null,
  phone: null,
  mobile: null,
}

function setCommCardEl(field: CommField, el: Element | ComponentPublicInstance | null) {
  const node = el && '$el' in el ? (el.$el as Element | null) : el
  commCardEls[field] = node instanceof HTMLElement ? node : null
}

function resetEditingCommFields() {
  editingCommFields.email = false
  editingCommFields.phone = false
  editingCommFields.mobile = false
}

const isAnyCommEditing = computed(
  () => editingCommFields.email || editingCommFields.phone || editingCommFields.mobile,
)

async function closeCommField(field: CommField) {
  if (!editingCommFields[field]) return
  const value = formData[field]
  const baseline = baselines[field]
  if (normalizeNullableString(value) !== normalizeNullableString(baseline)) {
    try {
      await saveContactField(field, value)
    } catch {
      return
    }
  }
  editingCommFields[field] = false
}

function onCommOutsidePointerDown(event: Event) {
  if (!isAnyCommEditing.value) return
  const target = event.target as Node | null
  if (!target) return
  ;(['email', 'phone', 'mobile'] as const).forEach((field) => {
    if (!editingCommFields[field]) return
    const el = commCardEls[field]
    if (el && !el.contains(target)) {
      void closeCommField(field)
    }
  })
}

watch(isAnyCommEditing, (active) => {
  if (active) {
    nextTick(() => {
      document.addEventListener('pointerdown', onCommOutsidePointerDown, true)
    })
  } else {
    document.removeEventListener('pointerdown', onCommOutsidePointerDown, true)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onCommOutsidePointerDown, true)
  document.removeEventListener('pointerdown', onLocationOutsidePointerDown, true)
})

const showDeleteConfirm = ref(false)
const showPermanentDeleteConfirm = ref(false)
const isDeleting = ref(false)
const isRestoring = ref(false)
const isPermanentDeleting = ref(false)
const copySuccess = ref(false)
const mapRef = ref<InstanceType<typeof MapView>>()
const eventLocationsRef = ref<InstanceType<typeof EventVenueDetailLocations> | null>(null)
const locationSectionRef = ref<HTMLElement | null>(null)

const editingLocation = ref(false)
const isSavingLocation = ref(false)
const isGeocodingLocation = ref(false)
const draftLat = ref<number | null>(null)
const draftLng = ref<number | null>(null)
const locationBaselineLat = ref<number | null>(null)
const locationBaselineLng = ref<number | null>(null)

function emptyDraft(type = props.defaultType): Partial<AddressFormData> {
  return {
    type,
    name: '',
    company: null,
    address_line2: null,
    street: '',
    street_number: null,
    postal_code: '',
    city: '',
    canton: null,
    country: 'Schweiz',
    contact_first_name: null,
    contact_last_name: null,
    email: null,
    phone: null,
    mobile: null,
    additional_info: null,
    pin_color: '#16a34a',
    is_primary: false,
  }
}

const hasDeliveryChild = computed(() => childAddresses.value.some((a) => a.type === 'event_delivery'))

const childModalAllowedTypes = computed(() => {
  if (childModalAddress.value) return [childModalAddress.value.type]
  return hasDeliveryChild.value ? ['event_poi'] : ['event_delivery', 'event_poi']
})

const childModalDefaultName = computed(() => {
  if (childModalDefaultType.value === 'event_poi') return ''
  const base = contact.value?.name || contact.value?.company || ''
  return base ? `${base} – Zustellung` : ''
})

const isSwiss = computed(() => {
  const country = (isCreateMode.value ? draft.value.country : contact.value?.country)?.toLowerCase() || ''
  return country === 'schweiz' || country === 'switzerland' || country === 'suisse' || country === 'ch' || country === ''
})

const showAddressMapActions = computed(() => {
  const c = contact.value
  if (!c) return false
  return Boolean(c.street?.trim() || c.postal_code?.trim())
})

const currentContactType = computed(() =>
  isCreateMode.value ? (draft.value.type || props.defaultType) : (contact.value?.type || ''),
)

const isEventContactType = computed(() => currentContactType.value === 'event')

const showStreetAddressSection = computed(() => !isEventContactType.value)

const isReadOnly = computed(() => {
  if (isCreateMode.value) return false
  if (!contact.value) return true
  if (contact.value.is_deleted) return true
  return !canUserManageContactType(contact.value.type, isUserRole.value)
})

const canGeocodeFromAddress = computed(() => {
  const c = contact.value
  if (!c) return false
  return Boolean(c.street?.trim() || c.postal_code?.trim() || c.city?.trim() || c.full_address?.trim())
})

const showLocationMap = computed(() => {
  if (editingLocation.value) return true
  return contact.value?.latitude != null && contact.value?.longitude != null
})

const mapDisplayLat = computed(() =>
  editingLocation.value ? draftLat.value : (contact.value?.latitude ?? null),
)
const mapDisplayLng = computed(() =>
  editingLocation.value ? draftLng.value : (contact.value?.longitude ?? null),
)

const canStartLocationEdit = computed(() => {
  if (isReadOnly.value) return false
  return showLocationMap.value || canGeocodeFromAddress.value
})

const canAcceptLocationDraft = computed(() => draftLat.value != null && draftLng.value != null)

function coordsNearlyEqual(a: number | null, b: number | null, c: number | null, d: number | null): boolean {
  if (a == null || b == null || c == null || d == null) return a == null && b == null && c == null && d == null
  return Math.abs(a - c) < 1e-7 && Math.abs(b - d) < 1e-7
}

const editAllowedTypes = computed(() =>
  isUserRole.value ? [...USER_CONTACT_CREATE_TYPES] : null,
)

const typeFieldLocked = computed(() => {
  const type = contact.value?.type
  return !!contact.value?.parent_id || type === 'event_delivery' || type === 'event_poi'
})

const typeSelectOptions = computed<AutoSaveSelectOption[]>(() => {
  const keys = editAllowedTypes.value?.length
    ? Object.keys(ADDRESS_TYPES).filter((k) => editAllowedTypes.value!.includes(k))
    : Object.keys(ADDRESS_TYPES)
  const filtered = keys.filter((k) => k !== 'event_delivery' && k !== 'event_poi')
  // Aktuellen Kind-Typ behalten, auch wenn nicht in Create-Liste
  if (contact.value && (contact.value.type === 'event_delivery' || contact.value.type === 'event_poi')) {
    if (!filtered.includes(contact.value.type)) filtered.unshift(contact.value.type)
  }
  return filtered.map((key) => ({
    value: key,
    label: te(`settings.addressForm.types.${key}`) ? t(`settings.addressForm.types.${key}`) : (ADDRESS_TYPES[key] || key),
  }))
})

const cantonSelectOptions = computed<AutoSaveSelectOption[]>(() => [
  { value: '', label: t('settings.addressForm.selectPlaceholder') },
  ...Object.entries(SWISS_CANTONS).map(([code, name]) => ({
    value: code,
    label: `${code} - ${name}`,
  })),
])

const backLabel = computed(() => {
  if (isCreateMode.value) return t('contacts.detail.backToList')
  if (contact.value?.parent_id) return t('contacts.detail.backToParent')
  return t('contacts.detail.backToList')
})

const headerTypeClass = computed(() =>
  isCreateMode.value ? (draft.value.type || props.defaultType) : (contact.value?.type || 'general'),
)

const headerTitle = computed(() => {
  if (isCreateMode.value) {
    return draft.value.name || draft.value.company || t('contacts.newAddress')
  }
  return contact.value?.name || contact.value?.company || t('contacts.unnamed')
})

const headerSubtitle = computed(() => {
  if (isCreateMode.value) {
    return draft.value.name && draft.value.company ? draft.value.company : ''
  }
  if (contact.value?.company && contact.value?.name) return contact.value.company
  return ''
})

const headerInitials = computed(() => {
  if (isCreateMode.value) {
    const n = (draft.value.name || draft.value.company || '?').trim()
    return n.substring(0, 2).toUpperCase()
  }
  return contact.value ? getInitials(contact.value) : '??'
})

async function loadContact() {
  if (isCreateMode.value || !props.contactId) {
    draft.value = emptyDraft()
    contact.value = null
    isLoading.value = false
    return
  }
  isLoading.value = true
  error.value = null
  try {
    const data = await getAddress(props.contactId)
    contact.value = data.address
    childAddresses.value = data.child_addresses ?? []
    syncFormFromAddress(data.address)
    draft.value = emptyDraft(data.address.type)
    editingLocation.value = false
    await nextTick()
    setTimeout(() => mapRef.value?.invalidateSize(), 600)
    if (
      !data.address.is_deleted
      && data.address.latitude == null
      && data.address.longitude == null
      && (data.address.street?.trim() || data.address.postal_code?.trim() || data.address.city?.trim())
    ) {
      void maybeGeocodeAfterAddressChange()
    }
  } catch (err: any) {
    const msg = err.response?.data?.error || t('contacts.detail.loadError')
    error.value = msg
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}

function formatContactPerson(c: Address): string {
  return [c.contact_first_name, c.contact_last_name].filter(Boolean).join(' ')
}

function getInitials(c: Address): string {
  const contactName = formatContactPerson(c)
  if (contactName) {
    const parts = contactName.trim().split(/\s+/)
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    return contactName.substring(0, 2).toUpperCase()
  }
  if (c.name) return c.name.substring(0, 2).toUpperCase()
  if (c.company) return c.company.substring(0, 2).toUpperCase()
  return '??'
}

function goBack() {
  if (isCreateMode.value) {
    emit('close')
    return
  }
  const parentId = contact.value?.parent_id
  if (parentId) {
    router.push(`/${props.departmentId}/contacts/${parentId}`)
    return
  }
  emit('close')
}

function normalizeNullableString(value: AutoSaveFieldValue): string | null {
  if (value == null) return null
  const s = String(value).trim()
  return s === '' ? null : s
}

async function saveContactField(field: ContactAutoSaveField, value: AutoSaveFieldValue): Promise<void> {
  if (!contact.value || isReadOnly.value) return

  if (field === 'is_primary') {
    const next = !!value
    if (next) {
      const primary = await setAddressPrimary(contact.value.id)
      contact.value = primary.address
    } else {
      const { address } = await updateAddress(contact.value.id, { is_primary: false })
      contact.value = address
    }
    syncFormFromAddress(contact.value)
    emit('updated')
    return
  }

  const payload: Partial<AddressFormData> = {}
  if (field === 'type') {
    payload.type = String(value || 'general')
    if (payload.type !== 'event_poi') payload.pin_color = null
    if (payload.type !== 'storage') payload.is_primary = false
  } else if (field === 'country') {
    payload.country = normalizeNullableString(value) || 'Schweiz'
  } else if (field === 'street' || field === 'postal_code' || field === 'city') {
    payload[field] = normalizeNullableString(value) ?? ''
  } else if (field === 'name') {
    payload.name = normalizeNullableString(value)
  } else if (field === 'company') {
    payload.company = normalizeNullableString(value)
  } else if (field === 'contact_first_name') {
    payload.contact_first_name = normalizeNullableString(value)
  } else if (field === 'contact_last_name') {
    payload.contact_last_name = normalizeNullableString(value)
  } else if (field === 'pin_color') {
    payload.pin_color = normalizeNullableString(value)
  } else if (field === 'email') {
    payload.email = normalizeNullableString(value)
  } else if (field === 'phone') {
    payload.phone = normalizeNullableString(value)
  } else if (field === 'mobile') {
    payload.mobile = normalizeNullableString(value)
  } else if (field === 'address_line2') {
    payload.address_line2 = normalizeNullableString(value)
  } else if (field === 'street_number') {
    payload.street_number = normalizeNullableString(value)
  } else if (field === 'canton') {
    payload.canton = normalizeNullableString(value)
  } else if (field === 'additional_info') {
    payload.additional_info = normalizeNullableString(value)
  }

  const { address } = await updateAddress(contact.value.id, payload)
  contact.value = address
  syncFormFromAddress(address)
  emit('updated')

  if (
    field === 'street'
    || field === 'street_number'
    || field === 'postal_code'
    || field === 'city'
    || field === 'country'
    || field === 'address_line2'
  ) {
    void maybeGeocodeAfterAddressChange()
  }
}

async function savePinColor(color: string): Promise<void> {
  if (!contact.value || isReadOnly.value || formData.pin_color === color) return
  formData.pin_color = color
  try {
    const { address } = await updateAddress(contact.value.id, { pin_color: color })
    contact.value = address
    syncFormFromAddress(address)
    emit('updated')
  } catch (err: any) {
    formData.pin_color = baselines.pin_color
    toast.error(err.response?.data?.error || t('contacts.detail.saveError'))
  }
}

async function saveCreate() {
  isSaving.value = true
  saveError.value = null
  const d = draft.value
  if (!d.name?.trim() && !d.company?.trim() && !d.street?.trim()) {
    saveError.value = t('settings.addressModal.validationMinFields')
    isSaving.value = false
    return
  }
  try {
    const payload: AddressFormData = {
      department_id: props.departmentId,
      type: d.type || props.defaultType,
      name: d.name,
      company: d.company,
      address_line2: d.address_line2,
      street: d.street || '',
      street_number: d.street_number,
      postal_code: d.postal_code || '',
      city: d.city || '',
      canton: d.canton,
      country: d.country || 'Schweiz',
      contact_first_name: d.contact_first_name,
      contact_last_name: d.contact_last_name,
      email: d.email,
      phone: d.phone,
      mobile: d.mobile,
      additional_info: d.additional_info,
      is_primary: !!d.is_primary,
    }
    let { address } = await createAddress(payload)
    if (payload.is_primary && address.id) {
      const primary = await setAddressPrimary(address.id)
      address = primary.address
    }
    toast.success(t('contacts.detail.saveSuccess'))
    emit('created', address)
  } catch (err: any) {
    saveError.value = err.response?.data?.error || t('contacts.detail.saveError')
    toast.error(saveError.value!)
  } finally {
    isSaving.value = false
  }
}

function openChildCreateModal() {
  childModalAddress.value = null
  childModalDefaultType.value = hasDeliveryChild.value ? 'event_poi' : 'event_delivery'
  showChildModal.value = true
}

async function handleVenueUpdated(address: Address) {
  contact.value = address
  syncFormFromAddress(address)
  emit('updated')
  await nextTick()
  eventLocationsRef.value?.refreshMaps()
}

function openChildEditModal(address: Address) {
  router.push(`/${props.departmentId}/contacts/${address.id}`)
}

function closeChildModal() {
  showChildModal.value = false
  childModalAddress.value = null
}

async function handleChildSaved() {
  closeChildModal()
  await loadContact()
  emit('updated')
  await nextTick()
  eventLocationsRef.value?.refreshMaps()
}

function onLocationDraftLat(lat: number) {
  draftLat.value = lat
}

function onLocationDraftLng(lng: number) {
  draftLng.value = lng
}

async function startLocationEdit() {
  if (isReadOnly.value || !contact.value) return
  if (contact.value.latitude == null || contact.value.longitude == null) {
    if (canGeocodeFromAddress.value) {
      await geocodeAndSaveLocation()
    }
  }
  if (!contact.value) return
  draftLat.value = contact.value.latitude
  draftLng.value = contact.value.longitude
  locationBaselineLat.value = contact.value.latitude
  locationBaselineLng.value = contact.value.longitude
  editingLocation.value = true
  await nextTick()
  mapRef.value?.invalidateSize()
  // Ohne Pin: Karte editierbar zum Setzen per Klick
  if (draftLat.value == null && canGeocodeFromAddress.value) {
    await nextTick()
    mapRef.value?.searchAddress()
  }
}

async function acceptLocationDraft() {
  if (!contact.value || draftLat.value == null || draftLng.value == null) {
    editingLocation.value = false
    return
  }
  const unchanged = coordsNearlyEqual(
    draftLat.value,
    draftLng.value,
    locationBaselineLat.value,
    locationBaselineLng.value,
  )
  if (unchanged) {
    editingLocation.value = false
    return
  }
  isSavingLocation.value = true
  try {
    const { address } = await updateAddress(contact.value.id, {
      latitude: draftLat.value,
      longitude: draftLng.value,
    })
    contact.value = address
    syncFormFromAddress(address)
    locationBaselineLat.value = address.latitude
    locationBaselineLng.value = address.longitude
    editingLocation.value = false
    emit('updated')
    await nextTick()
    eventLocationsRef.value?.refreshMaps()
    mapRef.value?.invalidateSize()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.detail.saveError'))
  } finally {
    isSavingLocation.value = false
  }
}

function onLocationOutsidePointerDown(event: Event) {
  if (!editingLocation.value) return
  const target = event.target as Node | null
  const el = locationSectionRef.value
  if (!el || !target || el.contains(target)) return
  void acceptLocationDraft()
}

watch(editingLocation, (active) => {
  if (active) {
    nextTick(() => {
      document.addEventListener('pointerdown', onLocationOutsidePointerDown, true)
    })
  } else {
    document.removeEventListener('pointerdown', onLocationOutsidePointerDown, true)
  }
})

async function geocodeQuery(query: string): Promise<{ lat: number; lng: number } | null> {
  const q = query.trim()
  if (!q) return null
  try {
    const swissResponse = await fetch(
      `https://api3.geo.admin.ch/rest/services/api/SearchServer?searchText=${encodeURIComponent(q)}&type=locations&limit=1`,
    )
    const swissData = await swissResponse.json()
    if (swissData.results?.length > 0) {
      const result = swissData.results[0].attrs
      if (result?.lat != null && result?.lon != null) {
        return { lat: Number(result.lat), lng: Number(result.lon) }
      }
    }
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`,
      { headers: { 'Accept-Language': 'de' } },
    )
    const results = await response.json()
    if (results?.length > 0) {
      return { lat: parseFloat(results[0].lat), lng: parseFloat(results[0].lon) }
    }
  } catch {
    /* ignore */
  }
  return null
}

async function geocodeAndSaveLocation() {
  if (!contact.value || isReadOnly.value || isGeocodingLocation.value) return
  const query = contact.value.full_address?.trim()
    || [contact.value.street, contact.value.street_number, contact.value.postal_code, contact.value.city]
      .filter(Boolean)
      .join(' ')
  if (!query.trim()) return
  isGeocodingLocation.value = true
  try {
    const coords = await geocodeQuery(query)
    if (!coords) {
      toast.error(t('contacts.detail.saveError'))
      return
    }
    const { address } = await updateAddress(contact.value.id, {
      latitude: coords.lat,
      longitude: coords.lng,
    })
    contact.value = address
    syncFormFromAddress(address)
    emit('updated')
    await nextTick()
    eventLocationsRef.value?.refreshMaps()
    mapRef.value?.invalidateSize()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.detail.saveError'))
  } finally {
    isGeocodingLocation.value = false
  }
}

async function maybeGeocodeAfterAddressChange() {
  if (!contact.value || isReadOnly.value) return
  if (contact.value.latitude != null && contact.value.longitude != null) return
  if (!canGeocodeFromAddress.value) return
  await geocodeAndSaveLocation()
}

function confirmDelete() {
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!contact.value) return
  isDeleting.value = true
  try {
    await deleteAddress(contact.value.id)
    showDeleteConfirm.value = false
    toast.success(t('contacts.detail.deleteSuccess'))
    emit('deleted')
  } catch (err: any) {
    const msg = err.response?.data?.error || t('contacts.detail.deleteError')
    error.value = msg
    toast.error(msg)
  } finally {
    isDeleting.value = false
  }
}

function confirmPermanentDelete() {
  showPermanentDeleteConfirm.value = true
}

async function handlePermanentDelete() {
  if (!contact.value) return
  isPermanentDeleting.value = true
  try {
    await permanentDeleteAddress(contact.value.id)
    showPermanentDeleteConfirm.value = false
    emit('deleted')
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.permanentDeleteError'))
  } finally {
    isPermanentDeleting.value = false
  }
}

async function handleRestore() {
  if (!contact.value) return
  isRestoring.value = true
  try {
    const { address } = await restoreAddress(contact.value.id)
    contact.value = address
    syncFormFromAddress(address)
    toast.success(t('contacts.restoreSuccess'))
    emit('updated')
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.restoreError'))
  } finally {
    isRestoring.value = false
  }
}

async function copyAddress() {
  if (!contact.value) return
  try {
    await navigator.clipboard.writeText(contact.value.full_address)
    copySuccess.value = true
    setTimeout(() => { copySuccess.value = false }, 2000)
  } catch {
    /* ignore */
  }
}

watch(
  () => [props.contactId, props.mode] as const,
  () => {
    resetEditingCommFields()
    void loadContact()
  },
)

onMounted(() => {
  void loadContact()
})
</script>

<style scoped>
.contact-detail-view {
  max-width: 900px;
  width: 100%;
  min-width: 0;
  margin: 0 auto;
  box-sizing: border-box;
}

.deleted-banner {
  margin: -16px 0 24px;
  padding: 12px 16px;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
  font-size: 0.9rem;
}

/* Header */
.detail-header {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.detail-header-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: nowrap;
}

.contact-detail-back-btn {
  flex-shrink: 0;
}

.contact-detail-error {
  padding: 24px;
  text-align: center;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.header-title > div:last-child {
  min-width: 0;
}

.header-title h1 {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
  margin: 0;
  overflow-wrap: anywhere;
  word-break: break-word;
}

.header-subtitle {
  font-size: 14px;
  color: #6b7280;
  display: block;
  margin-top: 2px;
}

.contact-avatar-lg {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  flex-shrink: 0;
  text-transform: uppercase;
}

/* Avatar-Farben → styles/components/address-type-badge.css (global) */

.header-actions {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  flex-shrink: 0;
  justify-content: flex-end;
  align-items: center;
  margin-left: auto;
}

.contact-detail-header-actions {
  gap: 8px;
}

/* Loading */
.loading-container {
  text-align: center;
  padding: 80px 20px;
}

.loading-container p {
  color: #6b7280;
  font-size: 15px;
}

.error-container {
  text-align: center;
  padding: 60px 20px;
  color: #dc2626;
}

/* Content */
.detail-content {
  display: flex;
  flex-direction: column;
}

.content-layout {
  display: flex;
  gap: 24px;
}

.content-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Section Card */
.section-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.section-card--location {
  overflow: visible;
}

.section-card--location :deep(.map-wrapper) {
  max-width: 100%;
  height: auto;
  overflow: visible;
}

.section-card--location :deep(.map-container) {
  overflow: hidden;
}

.section-card--location :deep(.external-map-links) {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
  padding-bottom: 4px;
}

.section-card--location :deep(.external-map-links .btn) {
  flex: 1 1 0;
  min-width: min(12rem, 100%);
  width: auto;
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header-row .section-title {
  margin-bottom: 0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px;
}

.section-title svg {
  color: #6b7280;
}

.section-edit-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
}

.section-edit-btn:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  color: #111827;
}

/* Empty Sections */
.empty-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 20px;
  text-align: center;
}

.empty-section p {
  color: #9ca3af;
  font-size: 14px;
  margin: 0;
}

.btn-add-data {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: white;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-data:hover {
  background: #f9fafb;
  border-color: #10b981;
  color: #059669;
}

/* Section Header Actions */
.section-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.save-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  color: #059669;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateX(4px); }
  to { opacity: 1; transform: translateX(0); }
}

/* Map Hint */
.map-no-coords {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  text-align: center;
  padding: 24px 16px;
  background: #f9fafb;
  border: 1px dashed #e5e7eb;
  border-radius: 8px;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.info-value {
  font-size: 15px;
  color: #111827;
  font-weight: 500;
}

/* Type & Primary Badges — Adress-Typ → styles/components/address-type-badge.css (global) */

.primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: #dcfce7;
  color: #166534;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
}

/* Contact Action Cards */
.contact-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.contact-action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
}

.comm-field-card {
  position: relative;
  min-width: 0;
}

.comm-field-edit-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.comm-field-edit-btn:hover {
  background: #f3f4f6;
  color: #111827;
  border-color: #d1d5db;
}

.contact-action-card--in-field {
  width: 100%;
  padding-right: 40px;
  box-sizing: border-box;
}

.contact-action-card--empty {
  width: 100%;
  padding-right: 40px;
  box-sizing: border-box;
  border-style: dashed;
  background: #fff;
  color: inherit;
  font: inherit;
  text-align: left;
}

.contact-action-card--empty:hover {
  border-color: #10b981;
}

.action-value.muted {
  color: #9ca3af;
  font-weight: 500;
}

.contact-action-card:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.action-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-icon.email {
  background: #dbeafe;
  color: #2563eb;
}

.action-icon.phone {
  background: #d1fae5;
  color: #059669;
}

.action-icon.mobile {
  background: #ede9fe;
  color: #7c3aed;
}

.action-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.action-label {
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.action-value {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Address Display */
.address-display {
  display: flex;
  justify-content: space-between;
  gap: 24px;
}

.address-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.address-line {
  font-size: 15px;
  color: #374151;
  line-height: 1.5;
}

.address-line.bold {
  font-weight: 600;
  color: #111827;
}

.address-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex-shrink: 0;
}

.map-link,
.copy-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.map-link {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  text-decoration: none;
}

.map-link:hover {
  background: #dbeafe;
}

.copy-btn {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.copy-btn:hover {
  background: #e5e7eb;
}

/* Additional Info */
.additional-info-text {
  font-size: 14px;
  color: #374151;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

/* Delete Dialog */
.delete-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 20px;
}

.delete-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 100%;
}

.delete-dialog h3 {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 600;
  color: #111827;
}

.delete-dialog p {
  color: #6b7280;
  margin: 0 0 20px;
  font-size: 14px;
  line-height: 1.5;
}

.delete-dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* Delete dialog buttons use shared ui/buttons.css */

/* Responsive */
@media (max-width: 768px) {
  .detail-header-toolbar {
    gap: 8px;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .contact-actions-grid {
    grid-template-columns: 1fr;
  }

  .address-display {
    flex-direction: column;
  }
}

.section-inline-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.address-actions--below-form {
  flex-direction: row;
  flex-wrap: wrap;
  margin-top: 16px;
}

.location-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.location-edit-btn {
  position: static;
}

.location-edit-hint {
  margin: 0 0 12px;
  font-size: 13px;
  color: #6b7280;
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

.create-save-bar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
  padding-top: 8px;
}
</style>
