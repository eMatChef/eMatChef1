<template>
  <div class="activity-create-wizard-form">
    <div v-if="layoutMode === 'stepper'" class="activity-stepper-meta">
      <v-chip size="small" variant="tonal" color="primary" class="activity-stepper-count">
        {{
          t('activities.wizard.form.stepCounter', {
            current: wizardStepIndex + 1,
            total: stepKeys.length,
            label: currentStepProgressLabel,
          })
        }}
      </v-chip>
    </div>

    <template v-if="layoutMode === 'single'">
      <section id="activity-create-grunddaten" class="activity-create-section">
        <ActivityOutlinedSection :title="stepTitles.grunddaten" :required="true">
          <ETextField
            id="activity-create-name"
            :model-value="formName"
            :placeholder="t('activities.wizard.form.namePlaceholder')"
            autocomplete="off"
            hide-details
            :aria-label="stepTitles.grunddaten"
            @update:model-value="emit('update:formName', String($event ?? ''))"
          />
        </ActivityOutlinedSection>
        <div
          v-if="showActivityGroupField"
          id="activity-create-group"
          class="activity-create-group-wrap"
        >
          <p v-if="showFixedGroupSelection" class="activity-readonly-value">{{ displayGroupLabel }}</p>
          <ESelect
            v-else
            id="activity-create-group-select"
            :model-value="selectedGroupId ?? ''"
            :items="groupSelectItems"
            :label="groupFieldLabel"
            :placeholder="groupSelectPlaceholder"
            hide-details
            @update:model-value="onGroupSelectChange"
          />
        </div>
      </section>

      <section id="activity-create-zeitraum" class="activity-create-section">
        <p v-if="datesLockedByMaterial" class="activity-dates-locked-hint text-muted">
          <strong>{{ t('activities.wizard.form.datesLockedTitle') }}</strong> {{ t('activities.wizard.form.datesLockedBody') }}
        </p>
        <ActivityZeitraumDatetimeFields
          v-model:usage-day="activityUsageDay"
          v-model:usage-range="rangeUsageDateRange"
          v-model:usage-time-from="usageTimeFromUnified"
          v-model:usage-time-to="usageTimeToUnified"
          v-model:mat-range="matDateRange"
          v-model:mat-start-time="matStartTime"
          v-model:mat-end-time="matEndTime"
          :activity-type="selectedActivityTypeApi"
          :department-id="departmentId"
          :usage-dates-locked="datesLockedByMaterial"
          :material-times-blocked-usage="materialTimesBlockedUsage"
        >
          <template #usage-before>
            <p v-if="isActivityType" class="zeitraum-hint text-muted">
              {{ t('activities.wizard.form.usageHintActivitySingle') }}
            </p>
            <p v-else class="zeitraum-hint text-muted">
              {{ t('activities.wizard.form.usageHintRange') }}
            </p>
          </template>
          <template #planning-before>
            <p v-if="planningUsageConflictMessage" class="activity-planning-usage-warn" role="alert">
              {{ planningUsageConflictMessage }}
            </p>
            <p v-if="defaultsHint && !isActivityType" class="defaults-hint text-muted">{{ defaultsHint }}</p>
            <p v-else-if="isActivityType" class="zeitraum-hint text-muted">
              {{ t('activities.wizard.form.planningPresetFromActivity') }}
            </p>
          </template>
          <template #planning-after>
            <p v-if="defaultsHint && !isActivityType" class="material-times-microhint text-muted">
              {{ t('activities.wizard.form.planningPresetFromUsage') }}
            </p>
            <p v-if="!planningSynced" class="material-times-microhint material-times-microhint--manual">
              <EButton
                variant="text"
                size="small"
                class="btn-material-resync"
                :disabled="!usageStartAt || !usageEndAt"
                @click="emit('resyncPlanning')"
              >
                {{ t('activities.wizard.form.resyncPlanningFromUsage') }}
              </EButton>
            </p>
          </template>
        </ActivityZeitraumDatetimeFields>

        <div
          v-if="showJsMaterialOptionOnGrunddaten && wantsJsMaterial"
          class="form-group activity-js-participant-wrap"
        >
          <label for="activity-js-participant-count-single">{{ t('activities.jsMaterial.participantCountLabel') }}</label>
          <input
            id="activity-js-participant-count-single"
            :value="participantCount ?? ''"
            type="number"
            min="1"
            step="1"
            class="form-input activity-js-participant-input"
            :placeholder="t('activities.jsMaterial.participantCountPlaceholder')"
            @input="onParticipantCountInput"
          />
          <p class="field-hint text-muted">
            {{ t('activities.jsMaterial.participantCountHint') }}
          </p>
        </div>
      </section>

      <section id="activity-create-material" class="activity-create-section">
        <ActivityOutlinedSection :title="t('common.material')" :required="true">
          <ActivityCreateMaterialStep
            :department-id="departmentId"
            :activity-type="selectedActivityType"
            :activity-id="draftActivityId"
            :invited-partner-departments="invitedPartnersForMaterial"
            :planning-start-at="planningStartAt"
            :planning-end-at="planningEndAt"
            :model-value="materialLines"
            :material-search-reset-key="materialSearchResetKey"
            @update:model-value="emit('update:materialLines', $event)"
          />
        </ActivityOutlinedSection>
      </section>
    </template>

    <template v-else>
      <section v-show="currentStepKey === 'grunddaten'" id="activity-create-grunddaten" class="activity-create-section">
        <ActivityOutlinedSection :title="stepTitles.grunddaten" :required="true">
          <ETextField
            id="activity-create-name-s"
            :model-value="formName"
            :placeholder="t('activities.wizard.form.namePlaceholder')"
            autocomplete="off"
            hide-details
            :aria-label="stepTitles.grunddaten"
            @update:model-value="emit('update:formName', String($event ?? ''))"
          />
        </ActivityOutlinedSection>
        <div
          v-if="showGroupOnGrunddatenStep"
          id="activity-create-group-stepper"
          class="activity-create-group-wrap"
        >
          <p v-if="showFixedGroupSelection" class="activity-readonly-value">
            {{ displayGroupLabel }}
          </p>
          <ESelect
            v-else
            id="activity-create-group-select-s"
            :model-value="selectedGroupId ?? ''"
            :items="groupSelectItems"
            :label="groupFieldLabel"
            :placeholder="groupSelectPlaceholder"
            hide-details
            @update:model-value="onGroupSelectChange"
          />
        </div>

        <div
          v-if="showVenueOnGrunddatenStep"
          class="form-group activity-external-address-wrap"
        >
          <label for="activity-venue-address-search">{{ t('activities.wizard.form.venueLabel') }} <span class="req">*</span></label>
          <p class="field-hint text-muted">
            {{ t('activities.wizard.form.venueHint') }}
          </p>
          <DepartmentAddressAutocomplete
            ref="venueAddressAutocompleteRef"
            input-id="activity-venue-address-search"
            :addresses="rentalAddresses"
            :selected-id="venueAddressId"
            primary-type="event"
            :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
            :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
            :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
            inline-create-label-key="addresses.search.createEventVenueInline"
            @update:selected-id="emit('update:venueAddressId', $event)"
            @create="openAddVenueAddressModal"
          />
          <p v-if="venueAddressId" class="selected-address">
            {{ t('activities.wizard.form.selectedPrefix') }}{{ venueAddressSummary }}
            <button type="button" class="clear-selection" :title="t('activities.wizard.form.clearSelectionTitle')" @click="clearVenueAddress">
              ×
            </button>
          </p>
        </div>

        <div
          v-if="showJsMaterialOptionOnGrunddaten"
          class="form-group span-2 activity-js-include-wrap"
        >
          <ECheckbox
            :model-value="wantsJsMaterial"
            :label="t('activities.jsMaterial.includeToggle')"
            hide-details
            @update:model-value="emit('update:wantsJsMaterial', !!$event)"
          />
          <p class="field-hint text-muted activity-js-include-hint">
            {{ t('activities.jsMaterial.includeHint') }}
          </p>
        </div>

        <div
          v-if="selectedActivityType === 'external'"
          class="form-group activity-external-address-wrap"
        >
          <label for="activity-external-address-search">{{ t('activities.wizard.form.externalTenantLabel') }} <span class="req">*</span></label>
          <p class="field-hint text-muted">
            {{ t('activities.wizard.form.externalTenantHint') }}
          </p>
          <div class="activity-address-select-row">
            <div class="autocomplete-wrapper activity-address-autocomplete">
              <input
                id="activity-external-address-search"
                v-model="customerAddressSearch"
                type="text"
                class="form-input"
                :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
                autocomplete="off"
                @input="onCustomerAddressSearchInput"
                @focus="showCustomerAddressDropdown = true"
                @blur="hideCustomerAddressDropdownDelayed"
              />
              <div
                v-if="showCustomerAddressDropdown && filteredRentalAddressesForAutocomplete.length > 0"
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div
                  v-for="a in filteredRentalAddressesForAutocomplete"
                  :key="a.id"
                  class="autocomplete-item activity-address-ac-item"
                  @mousedown.prevent="selectCustomerAddress(a)"
                >
                  <div class="activity-address-ac-main">
                    <span class="item-name">{{ a.name || a.company || a.street_line || t('activities.wizard.form.addressFallbackName') }}</span>
                    <span class="item-address-type-tag" :title="t('activities.wizard.form.addressTypeTitle', { type: a.type_label })">{{
                      a.type_label
                    }}</span>
                  </div>
                  <span class="item-city">{{ a.city_line || a.city || '' }}</span>
                </div>
              </div>
              <div
                v-else-if="
                  showCustomerAddressDropdown &&
                  customerAddressSearchTrimmed.length >= 1 &&
                  rentalAddresses.length > 0 &&
                  filteredRentalAddressesForAutocomplete.length === 0
                "
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div class="autocomplete-item autocomplete-empty">
                  <span class="item-name">{{ t('activities.empty.noMatch') }}</span>
                </div>
              </div>
              <div
                v-else-if="showCustomerAddressDropdown && rentalAddresses.length === 0"
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div class="autocomplete-item autocomplete-empty">
                  <span class="item-name">{{ t('activities.wizard.form.noAddressesWithAdd') }}</span>
                </div>
              </div>
            </div>
            <button
              type="button"
              class="add-inline-btn"
              :title="t('activities.wizard.form.addCustomerAddressTitle')"
              :aria-label="t('activities.wizard.form.addCustomerAddressTitle')"
              @click="openAddCustomerAddressModal"
            >
              +
            </button>
          </div>
          <p v-if="customerAddressId" class="selected-address">
            {{ t('activities.wizard.form.selectedPrefix') }}{{ customerAddressSummary }}
            <button type="button" class="clear-selection" :title="t('activities.wizard.form.clearSelectionTitle')" @click="clearCustomerAddress">
              ×
            </button>
          </p>
        </div>

        <div v-if="showInviteDepartmentsStep" class="form-group activity-invite-departments-wrap">
          <label for="activity-invite-dept-search">{{ t('activities.wizard.form.inviteDepartmentsLabel') }}</label>
          <p class="field-hint text-muted">
            {{ t('activities.wizard.form.inviteDepartmentsHint') }}
          </p>
          <div class="activity-address-select-row">
            <div class="autocomplete-wrapper activity-address-autocomplete">
              <input
                id="activity-invite-dept-search"
                v-model="inviteDeptSearch"
                type="text"
                class="form-input"
                :placeholder="t('activities.wizard.form.inviteDeptPlaceholder')"
                autocomplete="off"
                @focus="showInviteDeptDropdown = true"
                @blur="hideInviteDeptDropdownDelayed"
              />
              <div
                v-if="showInviteDeptDropdown && inviteDeptResults.length > 0"
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div
                  v-for="d in inviteDeptResults"
                  :key="d.id"
                  class="autocomplete-item activity-address-ac-item"
                  @mousedown.prevent="addInvitedDepartment(d)"
                >
                  <div class="activity-address-ac-main">
                    <span class="item-name">{{ d.name }}</span>
                  </div>
                  <span class="item-city">{{ d.organisation_name }}</span>
                </div>
              </div>
              <div
                v-else-if="
                  showInviteDeptDropdown &&
                  inviteDeptSearchTrimmed.length >= 2 &&
                  !inviteDeptLoading &&
                  inviteDeptResults.length === 0
                "
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div class="autocomplete-item autocomplete-empty">
                  <span class="item-name">{{ t('activities.empty.noMatch') }}</span>
                </div>
              </div>
              <div
                v-else-if="showInviteDeptDropdown && inviteDeptLoading"
                class="autocomplete-dropdown activity-address-autocomplete-dropdown"
              >
                <div class="autocomplete-item autocomplete-empty">
                  <span class="item-name">{{ t('activities.wizard.form.inviteSearching') }}</span>
                </div>
              </div>
            </div>
          </div>
          <p v-if="inviteDeptSearchTrimmed.length > 0 && inviteDeptSearchTrimmed.length < 2" class="field-hint text-muted invite-dept-min-hint">
            {{ t('activities.wizard.form.inviteMinChars') }}
          </p>
          <ul v-if="invitedDepartments.length > 0" class="activity-invited-dept-chips" :aria-label="t('activities.wizard.form.invitedDepartmentsAria')">
            <li v-for="row in invitedDepartments" :key="row.id" class="activity-invited-dept-chip">
              <span class="activity-invited-dept-chip-label">{{ row.name }}</span>
              <span v-if="row.organisation_name" class="activity-invited-dept-chip-org">{{ row.organisation_name }}</span>
              <button
                type="button"
                class="activity-invited-dept-chip-remove"
                :title="t('activities.wizard.form.removeInviteTitle', { name: row.name })"
                @click="removeInvitedDepartment(row.id)"
              >
                ×
              </button>
            </li>
          </ul>
        </div>
      </section>

      <section v-show="currentStepKey === 'zeitraum'" id="activity-create-zeitraum" class="activity-create-section">
        <div class="step-header">
          <span class="step-title">{{ stepTitles.zeitraum }}</span>
        </div>
        <p v-if="datesLockedByMaterial" class="activity-dates-locked-hint text-muted">
          <strong>{{ t('activities.wizard.form.datesLockedTitle') }}</strong> {{ t('activities.wizard.form.datesLockedBody') }}
        </p>
        <p class="zeitraum-intro text-muted">
          <strong>{{ t('activities.wizard.form.usageLabelWord') }}</strong> {{ t('activities.wizard.form.zeitraumIntroUsage') }}
          <strong>{{ t('common.material') }}</strong> {{ t('activities.wizard.form.zeitraumIntroMaterial') }}
        </p>

        <ActivityZeitraumDatetimeFields
          v-model:usage-day="activityUsageDay"
          v-model:usage-range="rangeUsageDateRange"
          v-model:usage-time-from="usageTimeFromUnified"
          v-model:usage-time-to="usageTimeToUnified"
          v-model:mat-range="matDateRange"
          v-model:mat-start-time="matStartTime"
          v-model:mat-end-time="matEndTime"
          :activity-type="selectedActivityTypeApi"
          :department-id="departmentId"
          :usage-dates-locked="datesLockedByMaterial"
          :material-times-blocked-usage="materialTimesBlockedUsage"
          usage-block-id="activity-usage-block-s"
          planning-block-id="activity-planning-block-s"
          usage-range-row-label=""
        >
          <template #usage-before>
            <p v-if="isActivityType" class="zeitraum-hint text-muted">
              {{ t('activities.wizard.form.usageHintActivityStepper') }}
            </p>
            <p v-else class="zeitraum-hint text-muted">
              {{ t('activities.wizard.form.usageHintRange') }}
            </p>
          </template>
          <template #planning-before>
            <p v-if="planningUsageConflictMessage" class="activity-planning-usage-warn" role="alert">
              {{ planningUsageConflictMessage }}
            </p>
            <p v-if="defaultsHint" class="defaults-hint text-muted">{{ defaultsHint }}</p>
          </template>
          <template #planning-after>
            <p class="material-times-microhint text-muted">
              {{ t('activities.wizard.form.materialTimesAutoStepper') }}
            </p>
            <p v-if="!planningSynced" class="material-times-microhint material-times-microhint--manual">
              <EButton
                variant="text"
                size="small"
                class="btn-material-resync"
                :disabled="!usageStartAt || !usageEndAt"
                @click="emit('resyncPlanning')"
              >
                {{ t('activities.wizard.form.resyncPlanningFromUsage') }}
              </EButton>
            </p>
          </template>
        </ActivityZeitraumDatetimeFields>

        <div
          v-if="showJsMaterialOptionOnGrunddaten && wantsJsMaterial"
          class="form-group activity-js-participant-wrap"
        >
          <label for="activity-js-participant-count">{{ t('activities.jsMaterial.participantCountLabel') }}</label>
          <input
            id="activity-js-participant-count"
            :value="participantCount ?? ''"
            type="number"
            min="1"
            step="1"
            class="form-input activity-js-participant-input"
            :placeholder="t('activities.jsMaterial.participantCountPlaceholder')"
            @input="onParticipantCountInput"
          />
          <p class="field-hint text-muted">
            {{ t('activities.jsMaterial.participantCountHint') }}
          </p>
        </div>
      </section>

      <section v-show="currentStepKey === 'material'" id="activity-create-material" class="activity-create-section">
        <ActivityOutlinedSection :title="stepTitles.material" :required="true">
          <p v-if="wantsJsMaterial" class="field-hint text-muted activity-js-material-step-hint">
            {{ t('activities.jsMaterial.materialStepHint') }}
          </p>
          <ActivityCreateMaterialStep
            :department-id="departmentId"
            :activity-type="selectedActivityType"
            :activity-id="draftActivityId"
            :invited-partner-departments="invitedPartnersForMaterial"
            :planning-start-at="planningStartAt"
            :planning-end-at="planningEndAt"
            :model-value="materialLines"
            :material-search-reset-key="materialSearchResetKey"
            @update:model-value="emit('update:materialLines', $event)"
          />
        </ActivityOutlinedSection>
      </section>

      <section v-show="currentStepKey === 'uebersicht'" class="activity-create-section">
        <div class="step-header">
          <span class="step-title">{{ stepTitles.uebersicht }}</span>
        </div>
        <dl class="activity-summary-dl">
          <div class="activity-summary-row">
            <dt>{{ t('activities.wizard.form.summaryType') }}</dt>
            <dd>{{ activityTypeLabel(selectedActivityType, t) }}</dd>
          </div>
          <div class="activity-summary-row">
            <dt>{{ t('common.name') }}</dt>
            <dd>{{ formName.trim() || t('activities.wizard.form.summaryEmpty') }}</dd>
          </div>
          <div
            v-if="showVenueOnGrunddatenStep && venueAddressId"
            class="activity-summary-row"
          >
            <dt>{{ t('activities.wizard.form.summaryVenue') }}</dt>
            <dd>{{ venueAddressSummary }}</dd>
          </div>
          <div v-if="selectedActivityType === 'external' && customerAddressId" class="activity-summary-row">
            <dt>{{ t('activities.wizard.form.summaryTenantAddress') }}</dt>
            <dd>{{ customerAddressSummary }}</dd>
          </div>
          <div v-if="showGroupInSummary" class="activity-summary-row">
            <dt>{{ t('common.group') }}</dt>
            <dd>{{ groupSummaryLabel }}</dd>
          </div>
          <div v-if="showJsMaterialOptionOnGrunddaten && wantsJsMaterial" class="activity-summary-row">
            <dt>{{ t('activities.jsMaterial.sectionLabel') }}</dt>
            <dd>{{ t('activities.jsMaterial.badgeIncluded') }}</dd>
          </div>
          <div
            v-if="showJsMaterialOptionOnGrunddaten && wantsJsMaterial && participantCount != null && participantCount >= 1"
            class="activity-summary-row"
          >
            <dt>{{ t('activities.jsMaterial.participantCountLabel') }}</dt>
            <dd>{{ participantCount }}</dd>
          </div>
          <div class="activity-summary-row">
            <dt>{{ summaryUsageLabel }}</dt>
            <dd>{{ formatRange(usageStartAt, usageEndAt) }}</dd>
          </div>
          <div class="activity-summary-row">
            <dt>{{ summaryMaterialLabel }}</dt>
            <dd>{{ formatRange(planningStartAt, planningEndAt) }}</dd>
          </div>
          <div class="activity-summary-row">
            <dt>{{ t('common.material') }}</dt>
            <dd>{{ materialSummaryLabel }}</dd>
          </div>
          <div
            v-if="(selectedActivityType === 'camp' || selectedActivityType === 'event') && invitedDepartmentsSummary"
            class="activity-summary-row"
          >
            <dt>{{ t('activities.wizard.form.summaryInvitedDepts') }}</dt>
            <dd>{{ invitedDepartmentsSummary }}</dd>
          </div>
        </dl>
        <p class="wizard-draft-hint activity-wizard-draft-hint">
          {{ t('activities.wizard.form.wizardDraftHint') }}
        </p>
        <div class="activity-wizard-notes-wrap">
          <ETextarea
            id="activity-create-notes-overview"
            :model-value="activityNotes"
            :label="t('activities.wizard.form.notesLabel')"
            :hint="t('activities.wizard.form.notesOptional')"
            :placeholder="t('activities.wizard.form.notesPlaceholder')"
            :rows="3"
            @update:model-value="emit('update:activityNotes', String($event ?? ''))"
          />
        </div>
      </section>
    </template>

    <AddressModal
      v-if="showAddressModal && addressModalTarget"
      :key="addressModalTarget"
      :department-id="departmentId"
      :default-type="addressModalTarget === 'venue' ? 'event' : 'customer'"
      :default-name="addressModalDefaultName"
      @close="closeAddressModal"
      @saved="onAddressModalSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityApiType } from '@/api/activities'
import { getAddresses, type Address } from '@/api/addresses'
import { searchJoinableDepartments, type DepartmentSearchResult } from '@/api/joinRequests'
import { DepartmentAddressAutocomplete } from '@/components/addresses'
import AddressModal from '@/components/AddressModal.vue'
import ActivityZeitraumDatetimeFields from '@/components/activities/shared/ActivityZeitraumDatetimeFields.vue'
import type { ActivityDefaults } from '@/api/departmentSettings'
import type { Group } from '@/api/groups'
import type {
  ActivityCreateLayoutMode,
  ActivityCreateStepKey,
  ActivityCreateType,
  ActivityMaterialLine,
  InvitedDepartmentDraft,
} from '@/composables/useActivityCreateWizard'
import { formatAddressOption, formatAddressSelectionLabel } from '@/utils/departmentAddressSearch'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import {
  getPlanningUsageViolation,
  isInstantInsideClosedUsage,
  nearestAllowedQuarterOnDayOutsideUsage,
} from '@/utils/activityPlanningUsageConstraint'
import { EButton, ECheckbox, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import {
  expandMaterialLinesForSummary,
  formatMaterialSummaryEntries,
} from '@/utils/virtualComboMaterial'
import {
  flattenGroupsWithLevel,
  pickUserHomeGroupId,
  resolveActivityGroupPickerLabel,
} from '@/utils/groupHierarchy'
import { activityPreviewMaterialLabel, activityPreviewUsageLabel } from './activityPreviewLabels'
import { activityTypeLabel } from './activityTypeLabels'
import ActivityOutlinedSection from './ActivityOutlinedSection.vue'
import ActivityCreateMaterialStep from './ActivityCreateMaterialStep.vue'

const props = withDefaults(
  defineProps<{
    layoutMode: ActivityCreateLayoutMode
    wizardStepIndex: number
    stepKeys: readonly string[]
    currentStepKey: ActivityCreateStepKey | null
    currentStepProgressLabel: string
    stepTitles: Record<ActivityCreateStepKey, string>
    formName: string
    usageStartAt: Date | null
    usageEndAt: Date | null
    planningStartAt: Date | null
    planningEndAt: Date | null
    selectedActivityType: ActivityCreateType
    activityDefaults: ActivityDefaults | null
    planningSynced: boolean
    groups: Group[]
    selectedGroupId: string | null
    /** Bei Typ „extern“: verknüpfte Kunden-/Mieteradresse */
    customerAddressId: string | null
    /** Lager, Event, extern: Eventstandort */
    venueAddressId: string | null
    /** Camp/Event: J+S-Leihmaterial einbeziehen */
    wantsJsMaterial?: boolean
    /** Camp/Event + J+S: Teilnehmerzahl für Dotation */
    participantCount?: number | null
    /** Für Schulferien-Marker (fcal) im Datumsfeld */
    departmentId: string
    /** Oberste Option im Gruppen-Dropdown (Lager/Event) */
    departmentName: string
    materialLines: ActivityMaterialLine[]
    /** Gesetzt nach erstem „Weiter“ (Stepper): Server-Entwurf für Material-API */
    draftActivityId?: string | null
    invitedDepartments?: InvitedDepartmentDraft[]
    /** Stepper Übersicht: optionale Aktivitäts-Notizen */
    activityNotes?: string
  }>(),
  {
    groups: () => [],
    selectedGroupId: null,
    customerAddressId: null,
    venueAddressId: null,
    wantsJsMaterial: false,
    participantCount: null,
    materialLines: () => [],
    draftActivityId: null,
    invitedDepartments: () => [],
    activityNotes: '',
    departmentName: '',
  },
)

const emit = defineEmits<{
  'update:formName': [value: string]
  'update:selectedGroupId': [value: string | null]
  'update:customerAddressId': [value: string | null]
  'update:venueAddressId': [value: string | null]
  'update:wantsJsMaterial': [value: boolean]
  'update:participantCount': [value: number | null]
  'update:usageStartAt': [value: Date | null]
  'update:usageEndAt': [value: Date | null]
  'update:planningStartAt': [value: Date | null]
  'update:planningEndAt': [value: Date | null]
  'update:materialLines': [value: ActivityMaterialLine[]]
  'update:invitedDepartments': [value: InvitedDepartmentDraft[]]
  'update:activityNotes': [value: string]
  resyncPlanning: []
}>()

const toast = useToast()
const { t } = useI18n()
const authStore = useAuthStore()
const { canSelectDepartmentGroupLevel } = useDepartmentMemberRole()

/** Materialsucheingabe leeren + Lookup neu mounten (Schritt Material, Typwechsel Ein-Seiten-Layout) */
const materialSearchResetKey = ref(0)

watch(
  () => props.currentStepKey,
  (key, prevKey) => {
    if (props.layoutMode !== 'stepper') return
    if (key === 'material' && prevKey !== 'material') {
      materialSearchResetKey.value += 1
    }
  },
)

watch(
  () => props.selectedActivityType,
  (typ, oldTyp) => {
    if (oldTyp === undefined || typ === oldTyp) return
    if (props.layoutMode === 'single') {
      materialSearchResetKey.value += 1
    }
  },
)

const isActivityType = computed(() => props.selectedActivityType === 'activity')

const flatGroups = computed(() => flattenGroupsWithLevel(props.groups))
const groupSelectOptions = computed(() => flatGroups.value)

/** Aktivität: immer konkrete Gruppe; Lager/Event: Abteilungsebene nur für Leiter/MW/DC optional. */
const isGroupFieldRequired = computed(() => {
  if (props.selectedActivityType === 'activity') return true
  if (props.selectedActivityType === 'camp') return !canSelectDepartmentGroupLevel.value
  return false
})

const groupFieldLabel = computed(() => {
  let label = t('common.group')
  if (isGroupFieldRequired.value) label += ' *'
  else label += ` (${t('activities.wizard.form.groupOptional')})`
  return label
})

const groupSelectItems = computed(() => {
  const items: { title: string; value: string }[] = []
  const allowDepartmentLevel =
    canSelectDepartmentGroupLevel.value && props.selectedActivityType !== 'activity'
  if (allowDepartmentLevel) {
    items.push({
      value: '',
      title: props.departmentName.trim() || '–',
    })
  }
  for (const g of groupSelectOptions.value) {
    items.push({
      value: g.id,
      title: `${'↳ '.repeat(g._level)}${g.name}`,
    })
  }
  return items
})

const groupSelectPlaceholder = computed(() =>
  isGroupFieldRequired.value && !props.selectedGroupId
    ? t('activities.wizard.form.groupChoose')
    : undefined,
)
const hasMultipleGroupChoices = computed(
  () => canSelectDepartmentGroupLevel.value || groupSelectOptions.value.length > 1,
)
const showFixedGroupSelection = computed(() => {
  // Aktivität: Dropdown auch bei nur einer Gruppe (vorausgefüllt, aber änderbar inkl. Untergruppen)
  if (props.selectedActivityType === 'activity') {
    return groupSelectOptions.value.length === 0
  }
  if (canSelectDepartmentGroupLevel.value) return props.groups.length === 0
  return props.groups.length > 0 && !hasMultipleGroupChoices.value
})
const showActivityGroupField = computed(
  () => isActivityType.value && (props.groups.length > 0 || canSelectDepartmentGroupLevel.value),
)

const displayGroupLabel = computed(() => {
  if (props.selectedGroupId) {
    return resolveActivityGroupPickerLabel(
      props.selectedGroupId,
      props.departmentName,
      props.groups,
    )
  }
  if (canSelectDepartmentGroupLevel.value) {
    return props.departmentName.trim() || '–'
  }
  const homeId = pickUserHomeGroupId(props.groups, authStore.userId)
  if (homeId) {
    return resolveActivityGroupPickerLabel(homeId, props.departmentName, props.groups)
  }
  if (flatGroups.value.length === 1) return flatGroups.value[0].name
  return '–'
})

const invalidUsageOrderLocal = computed(() => {
  if (!props.usageStartAt || !props.usageEndAt) return false
  return props.usageEndAt.getTime() < props.usageStartAt.getTime()
})

const planningUsageConflictMessage = computed(() => {
  if (!props.usageStartAt || !props.usageEndAt || !props.planningStartAt || !props.planningEndAt) return null
  if (invalidUsageOrderLocal.value) return null
  const v = getPlanningUsageViolation(
    props.planningStartAt,
    props.planningEndAt,
    props.usageStartAt,
    props.usageEndAt,
  )
  if (v.pickup && v.return) return t('activities.wizard.form.planningViolationBoth')
  if (v.pickup) return t('activities.wizard.form.planningViolationPickup')
  if (v.return) return t('activities.wizard.form.planningViolationReturn')
  return null
})

/** Nutzungsintervall für gesperrte Viertelstunden in Abhol-/Rückgabe-Zeitauswahl */
const materialTimesBlockedUsage = computed((): { start: Date; end: Date } | null => {
  if (!props.usageStartAt || !props.usageEndAt) return null
  if (props.usageEndAt.getTime() < props.usageStartAt.getTime()) return null
  return { start: props.usageStartAt, end: props.usageEndAt }
})

function resolvePlanningPair(nextStart: Date, nextEnd: Date): { start: Date; end: Date } | null {
  const us = props.usageStartAt
  const ue = props.usageEndAt
  if (!us || !ue || ue.getTime() < us.getTime()) {
    if (nextEnd.getTime() < nextStart.getTime()) return null
    return { start: nextStart, end: nextEnd }
  }
  let s = nextStart
  let e = nextEnd
  if (isInstantInsideClosedUsage(s, us, ue)) {
    const f = nearestAllowedQuarterOnDayOutsideUsage(s, us, ue)
    if (!f) return null
    s = f
  }
  if (isInstantInsideClosedUsage(e, us, ue)) {
    const f = nearestAllowedQuarterOnDayOutsideUsage(e, us, ue)
    if (!f) return null
    e = f
  }
  if (e.getTime() < s.getTime()) return null
  const v = getPlanningUsageViolation(s, e, us, ue)
  if (v.pickup || v.return) return null
  return { start: s, end: e }
}

function emitPlanningPair(nextStart: Date, nextEnd: Date) {
  const resolved = resolvePlanningPair(nextStart, nextEnd)
  if (!resolved) {
    toast.error(t('activities.wizard.form.toastPlanningViolation'))
    return
  }
  emit('update:planningStartAt', resolved.start)
  emit('update:planningEndAt', resolved.end)
}

/** Gruppe in Schritt 1 (Stepper) bei Lager & Event */
const showGroupOnGrunddatenStep = computed(
  () =>
    (props.selectedActivityType === 'camp' || props.selectedActivityType === 'event') &&
    (props.groups.length > 0 || canSelectDepartmentGroupLevel.value),
)

/** Eventstandort in Schritt 1: Lager, Event, extern (nicht „Aktivität“) */
const showVenueOnGrunddatenStep = computed(
  () =>
    props.selectedActivityType === 'camp' ||
    props.selectedActivityType === 'event' ||
    props.selectedActivityType === 'external',
)

const showJsMaterialOptionOnGrunddaten = computed(
  () => props.selectedActivityType === 'camp' || props.selectedActivityType === 'event',
)

function onParticipantCountInput(event: Event) {
  const raw = (event.target as HTMLInputElement).value.trim()
  if (raw === '') {
    emit('update:participantCount', null)
    return
  }
  const num = Number.parseInt(raw, 10)
  emit('update:participantCount', Number.isFinite(num) && num >= 1 ? num : null)
}

const showGroupInSummary = computed(
  () =>
    props.selectedActivityType === 'camp' ||
    props.selectedActivityType === 'event' ||
    (props.selectedActivityType === 'activity' &&
      (props.groups.length > 0 || canSelectDepartmentGroupLevel.value)),
)

const groupSummaryLabel = computed(() => displayGroupLabel.value)

const materialSummaryLabel = computed(() => {
  const lines = props.materialLines
  if (!lines.length) return t('activities.wizard.form.summaryEmpty')
  const entries = expandMaterialLinesForSummary(lines)
  return formatMaterialSummaryEntries(entries, {
    maxItems: 3,
    countLabel: (n) => t('activities.wizard.form.materialLinesCount', { n }),
  })
})

const invitedDepartmentsSummary = computed(() => {
  const list = props.invitedDepartments
  if (!list.length) return ''
  if (list.length > 4) return t('activities.wizard.form.invitedDeptsCount', { n: list.length })
  return list.map((d) => d.name.trim() || d.id).join(', ')
})

const invitedPartnersForMaterial = computed(() =>
  props.invitedDepartments
    .filter((d) => (d.status ?? 'pending') === 'accepted')
    .map((d) => ({ id: d.id, name: d.name.trim() || d.id })),
)

const showInviteDepartmentsStep = computed(
  () =>
    props.layoutMode === 'stepper' &&
    (props.selectedActivityType === 'camp' || props.selectedActivityType === 'event'),
)

const inviteDeptSearch = ref('')
const inviteDeptRawResults = ref<DepartmentSearchResult[]>([])
const inviteDeptLoading = ref(false)
const showInviteDeptDropdown = ref(false)
let inviteDeptSearchTimer: ReturnType<typeof setTimeout> | null = null

const inviteDeptSearchTrimmed = computed(() => inviteDeptSearch.value.trim())

const inviteDeptResults = computed(() => {
  const own = props.departmentId
  const taken = new Set(props.invitedDepartments.map((d) => d.id))
  return inviteDeptRawResults.value.filter((d) => d.id !== own && !taken.has(d.id))
})

watch(inviteDeptSearch, (value) => {
  if (inviteDeptSearchTimer) clearTimeout(inviteDeptSearchTimer)
  const q = value.trim()
  if (q.length < 2) {
    inviteDeptRawResults.value = []
    inviteDeptLoading.value = false
    return
  }
  inviteDeptSearchTimer = setTimeout(async () => {
    inviteDeptLoading.value = true
    try {
      inviteDeptRawResults.value = await searchJoinableDepartments(q)
    } catch {
      inviteDeptRawResults.value = []
    } finally {
      inviteDeptLoading.value = false
    }
  }, 250)
})

onUnmounted(() => {
  if (inviteDeptSearchTimer) clearTimeout(inviteDeptSearchTimer)
})

function hideInviteDeptDropdownDelayed() {
  window.setTimeout(() => {
    showInviteDeptDropdown.value = false
  }, 200)
}

function addInvitedDepartment(d: DepartmentSearchResult) {
  if (d.id === props.departmentId) return
  if (props.invitedDepartments.some((x) => x.id === d.id)) return
  emit('update:invitedDepartments', [
    ...props.invitedDepartments,
    {
      id: d.id,
      name: d.name,
      organisation_name: d.organisation_name || '',
      group_id: d.group_id ?? null,
      group_name: d.group_name ?? null,
      status: 'pending' as const,
    },
  ])
  inviteDeptSearch.value = ''
  inviteDeptRawResults.value = []
  showInviteDeptDropdown.value = false
}

function removeInvitedDepartment(id: string) {
  emit(
    'update:invitedDepartments',
    props.invitedDepartments.filter((x) => x.id !== id),
  )
}

/** v4.01: Mit Materialpositionen sind Nutzungs- und Material-Zeiträume fix, bis alle Zeilen entfernt sind */
const datesLockedByMaterial = computed(() => props.materialLines.length > 0)

const rentalAddresses = ref<Address[]>([])
const showAddressModal = ref(false)
const addressModalTarget = ref<'customer' | 'venue' | null>(null)
const addressModalDefaultName = ref('')
const venueAddressAutocompleteRef = ref<InstanceType<typeof DepartmentAddressAutocomplete> | null>(null)
const customerAddressSearch = ref('')
const showCustomerAddressDropdown = ref(false)

const customerAddressSearchTrimmed = computed(() => customerAddressSearch.value.trim())

const customerAddressSummary = computed(() => {
  if (!props.customerAddressId) return t('activities.wizard.form.summaryEmpty')
  const a = rentalAddresses.value.find((x) => x.id === props.customerAddressId)
  if (!a) return props.customerAddressId
  return (a.full_address && a.full_address.trim()) || formatAddressOption(a)
})

const venueAddressSummary = computed(() => {
  if (!props.venueAddressId) return t('activities.wizard.form.summaryEmpty')
  const a = rentalAddresses.value.find((x) => x.id === props.venueAddressId)
  if (!a) return props.venueAddressId
  return (a.full_address && a.full_address.trim()) || formatAddressOption(a)
})

function addressMatchesQuery(a: Address, q: string): boolean {
  const hay = [
    a.type,
    a.type_label,
    a.name,
    a.company,
    a.street,
    a.street_line,
    a.city,
    a.postal_code,
    a.full_address,
    a.email,
    a.phone,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()
  return hay.includes(q)
}

/** Suche über geladene Department-Adressen (ohne Dropdown-Liste). */
const filteredRentalAddressesForAutocomplete = computed(() => {
  const q = customerAddressSearchTrimmed.value.toLowerCase()
  const list = rentalAddresses.value
  if (!q) return list.slice(0, 20)
  return list.filter((a) => addressMatchesQuery(a, q)).slice(0, 40)
})

async function loadRentalAddresses() {
  if (!props.departmentId) return
  try {
    /** Ohne type-Parameter: API liefert alle Adresstypen des Departments. */
    const { addresses } = await getAddresses(props.departmentId)
    rentalAddresses.value = [...addresses].sort((a, b) =>
      formatAddressOption(a).localeCompare(formatAddressOption(b), 'de'),
    )
  } catch {
    rentalAddresses.value = []
  }
}

watch(
  () => [props.departmentId, props.selectedActivityType] as const,
  ([deptId, typ]) => {
    const needsAddr =
      typ === 'external' || typ === 'camp' || typ === 'event'
    if (needsAddr && deptId) void loadRentalAddresses()
    if (!needsAddr) {
      customerAddressSearch.value = ''
      showCustomerAddressDropdown.value = false
    }
  },
  { immediate: true },
)

/** Nach Auswahl aus Liste oder neuem Anlegen: Suchfeld anzeigen. */
watch(
  () => [props.customerAddressId, rentalAddresses.value] as const,
  () => {
    const id = props.customerAddressId
    if (!id) return
    const a = rentalAddresses.value.find((x) => x.id === id)
    if (a) customerAddressSearch.value = formatAddressSelectionLabel(a)
  },
)

function onCustomerAddressSearchInput() {
  showCustomerAddressDropdown.value = true
  if (props.customerAddressId) {
    emit('update:customerAddressId', null)
  }
}

function hideCustomerAddressDropdownDelayed() {
  window.setTimeout(() => {
    showCustomerAddressDropdown.value = false
  }, 200)
}

function selectCustomerAddress(a: Address) {
  emit('update:customerAddressId', a.id)
  customerAddressSearch.value = formatAddressSelectionLabel(a)
  showCustomerAddressDropdown.value = false
}

function clearCustomerAddress() {
  emit('update:customerAddressId', null)
  customerAddressSearch.value = ''
}

function clearVenueAddress() {
  emit('update:venueAddressId', null)
  venueAddressAutocompleteRef.value?.clearSearch()
}

function closeAddressModal() {
  showAddressModal.value = false
  addressModalTarget.value = null
  addressModalDefaultName.value = ''
}

function openAddCustomerAddressModal() {
  addressModalDefaultName.value = customerAddressSearchTrimmed.value
  addressModalTarget.value = 'customer'
  showAddressModal.value = true
}

function openAddVenueAddressModal(presetName = '') {
  addressModalDefaultName.value = presetName.trim()
  addressModalTarget.value = 'venue'
  showAddressModal.value = true
}

function onAddressModalSaved(addr?: Address) {
  const t = addressModalTarget.value
  showAddressModal.value = false
  addressModalTarget.value = null
  addressModalDefaultName.value = ''
  void loadRentalAddresses().then(() => {
    if (addr?.id) {
      if (t === 'customer') emit('update:customerAddressId', addr.id)
      else if (t === 'venue') emit('update:venueAddressId', addr.id)
    }
  })
}

const summaryUsageLabel = computed(() =>
  props.selectedActivityType ? activityPreviewUsageLabel(props.selectedActivityType, t) : '',
)
const summaryMaterialLabel = computed(() =>
  props.selectedActivityType ? activityPreviewMaterialLabel(props.selectedActivityType, t) : '',
)

function onGroupSelectChange(value: unknown) {
  const raw = value == null ? '' : String(value).trim()
  if (!raw && isGroupFieldRequired.value) return
  emit('update:selectedGroupId', raw || null)
}

/** Schnellauswahl Zeitraum: in ActivityZeitraumDatetimeFields (nur Lager/Event). */

const selectedActivityTypeApi = computed(() => props.selectedActivityType as ActivityApiType)

/** Gemeinsame Uhrzeit-Felder für Typ „Aktivität“ (ein Tag) und Bereich — gleiche API wie die zentrale Zeitraum-Komponente */
const usageTimeFromUnified = computed({
  get: () => props.usageStartAt ?? null,
  set: (v: Date | null) => {
    if (!v) return
    if (isActivityType.value) {
      const day = props.usageStartAt
        ? startOfLocalDay(props.usageStartAt)
        : props.usageEndAt
          ? startOfLocalDay(props.usageEndAt)
          : null
      if (!day) return
      emit('update:usageStartAt', combineDayAndTime(day, v))
    } else {
      if (!props.usageStartAt) return
      emit('update:usageStartAt', combineDayAndTime(startOfLocalDay(props.usageStartAt), v))
    }
  },
})

const usageTimeToUnified = computed({
  get: () => props.usageEndAt ?? null,
  set: (v: Date | null) => {
    if (!v) return
    if (isActivityType.value) {
      const day = props.usageEndAt
        ? startOfLocalDay(props.usageEndAt)
        : props.usageStartAt
          ? startOfLocalDay(props.usageStartAt)
          : null
      if (!day) return
      emit('update:usageEndAt', combineDayAndTime(day, v))
    } else {
      if (!props.usageEndAt) return
      emit('update:usageEndAt', combineDayAndTime(startOfLocalDay(props.usageEndAt), v))
    }
  },
})

const defaultsHint = computed(() => {
  const d = props.activityDefaults
  if (!d) return ''
  if (props.selectedActivityType === 'camp') {
    return t('activities.wizard.defaultsHintCamp', { lead: d.campMaterialLeadDays, lag: d.campMaterialLagDays })
  }
  return t('activities.wizard.defaultsHintStandard', { lead: d.materialLeadMinutes, lag: d.materialLagMinutes })
})

/** Ein Kalendertag für Nutzung (activity): beide Enden gleicher Tag */
const activityUsageDay = computed({
  get: () => (props.usageStartAt ? startOfLocalDay(props.usageStartAt) : null),
  set: (d: Date | null) => {
    if (!d || !props.usageStartAt || !props.usageEndAt) return
    emit('update:usageStartAt', combineDayAndTime(d, props.usageStartAt))
    emit('update:usageEndAt', combineDayAndTime(d, props.usageEndAt))
  },
})

const rangeUsageDateRange = computed({
  get: (): [Date, Date] | null => {
    if (!props.usageStartAt || !props.usageEndAt) return null
    return [startOfLocalDay(props.usageStartAt), startOfLocalDay(props.usageEndAt)]
  },
  set: (v: [Date, Date] | null) => {
    if (!v || v.length < 2) return
    const [dStart, dEnd] = v
    const tStart = props.usageStartAt ?? dStart
    const tEnd = props.usageEndAt ?? dEnd
    emit('update:usageStartAt', combineDayAndTime(dStart, tStart))
    emit('update:usageEndAt', combineDayAndTime(dEnd, tEnd))
  },
})

const matDateRange = computed({
  get: (): [Date, Date] | null => {
    if (!props.planningStartAt || !props.planningEndAt) return null
    return [startOfLocalDay(props.planningStartAt), startOfLocalDay(props.planningEndAt)]
  },
  set: (v: [Date, Date] | null) => {
    if (!v || v.length < 2) return
    const [dStart, dEnd] = v
    const tStart = props.planningStartAt ?? dStart
    const tEnd = props.planningEndAt ?? dEnd
    const nextStart = combineDayAndTime(dStart, tStart)
    const nextEnd = combineDayAndTime(dEnd, tEnd)
    emitPlanningPair(nextStart, nextEnd)
  },
})

const matStartTime = computed({
  get: () => props.planningStartAt,
  set: (v: Date | null) => {
    if (!v || !props.planningStartAt) return
    const next = combineDayAndTime(startOfLocalDay(props.planningStartAt), v)
    if (!props.planningEndAt) {
      emit('update:planningStartAt', next)
      return
    }
    emitPlanningPair(next, props.planningEndAt)
  },
})

const matEndTime = computed({
  get: () => props.planningEndAt,
  set: (v: Date | null) => {
    if (!v || !props.planningEndAt) return
    const next = combineDayAndTime(startOfLocalDay(props.planningEndAt), v)
    if (!props.planningStartAt) {
      emit('update:planningEndAt', next)
      return
    }
    emitPlanningPair(props.planningStartAt, next)
  },
})

function formatRange(a: Date | null, b: Date | null): string {
  if (!a && !b) return '–'
  const opts: Intl.DateTimeFormatOptions = {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }
  const fa = a ? a.toLocaleString('de-CH', opts) : '–'
  const fb = b ? b.toLocaleString('de-CH', opts) : '–'
  return `${fa} – ${fb}`
}
</script>

<style scoped>
.activity-planning-usage-warn {
  margin: 0 0 10px;
  font-size: 13px;
  line-height: 1.45;
  color: #b91c1c;
  font-weight: 500;
}
</style>
