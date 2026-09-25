<template>
  <div class="ga-bauprojekt-panel">
    <p v-if="error" class="ga-bauprojekt-panel__error">{{ error }}</p>
    <p v-else-if="loading" class="muted">{{ t('common.loading') }}</p>
    <template v-else-if="briefing">
      <v-expansion-panels v-model="openSections" multiple class="e-accordions ga-bauprojekt-panel__accordions">
        <v-expansion-panel value="window">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.windowLabel') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <GaBuildMetaFields
              v-if="briefing.can_edit"
              :department-id="departmentId"
              autosave
              v-model:start="windowStart"
              v-model:end="windowEnd"
              v-model:status="buildStatus"
              :window-label="t('grossanlass.planung.ressorts.windowLabel')"
              :window-baseline="windowBaseline"
              :status-baseline="buildStatusBaseline"
              hint-class="muted"
              :save-window="saveWindowAutosave"
              :save-status="saveStatusAutosave"
            />
            <template v-else>
              <p v-if="windowText" class="muted">{{ windowText }}</p>
              <p v-if="buildStatusText" class="muted">{{ buildStatusText }}</p>
            </template>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="description">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.descriptionHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ETextarea
              v-if="briefing.can_edit"
              v-model="description"
              :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
              rows="4"
              hide-details="auto"
            />
            <p v-else-if="description.trim()" class="ga-bauprojekt-panel__beschrieb">{{ description }}</p>
            <p v-else class="muted">{{ t('grossanlass.planung.ressorts.descriptionEmpty') }}</p>
            <EButton
              v-if="briefing.can_edit"
              variant="secondary"
              size="small"
              class="ga-bauprojekt-panel__save-window"
              :loading="savingWindow"
              :disabled="savingWindow"
              @click="saveDescription"
            >
              {{ t('grossanlass.planung.ressorts.descriptionSave') }}
            </EButton>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="place">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.placeHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text eager>
            <div class="ga-place-head">
              <p v-if="briefing.place">{{ briefing.place.name }}</p>
              <p v-else class="muted">{{ t('grossanlass.planung.ressorts.placeMissing') }}</p>
              <button
                v-if="briefing.can_edit"
                type="button"
                class="action-btn ga-place-gear"
                :title="t('grossanlass.planung.ressorts.placeEdit')"
                @click="placeEditing = !placeEditing"
              >
                <v-icon icon="mdi-cog" size="18" />
              </button>
            </div>
            <div v-if="placeEditing && briefing.can_edit" class="ga-place-edit">
              <ETextField
                v-model="placeName"
                :label="t('grossanlass.planung.ressorts.placeName')"
                hide-details="auto"
              />
              <EButton size="small" variant="primary" :loading="savingPlace" @click="savePlaceName">
                {{ t('common.save') }}
              </EButton>
            </div>
            <p v-if="briefing.can_edit" class="muted">{{ t('grossanlass.planung.ressorts.mapHint') }}</p>
            <GrossanlassPlacePreviewMap
              ref="placeMapRef"
              :latitude="briefing.place?.latitude ?? null"
              :longitude="briefing.place?.longitude ?? null"
              :label="briefing.place?.name || briefing.group?.name || ''"
              :overlay="placeOverlay"
              :active="placeSectionOpen"
              :editable="briefing.can_edit"
              height="280px"
              @pick="onPlacePick"
            />
            <a
              v-if="publicPlaceUrl"
              class="ga-bauprojekt-panel__link"
              :href="publicPlaceUrl"
              target="_blank"
              rel="noopener"
            >
              {{ t('grossanlass.planung.ressorts.placePublicView') }}
            </a>
            <RouterLink v-if="canSeeStandorte" class="ga-bauprojekt-panel__link" :to="standorteTo">
              {{ t('grossanlass.planung.ressorts.placeOnMap') }}
            </RouterLink>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="tasks">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.tasksHeading') }}
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p v-if="!briefing.can_edit && !taskBlocks.length" class="muted">{{ t('grossanlass.planung.ressorts.tasksEmpty') }}</p>
            <draggable
              v-model="taskBlocks"
              item-key="key"
              handle=".ga-block__drag"
              :disabled="!briefing.can_edit"
              @end="onTasksReordered"
            >
              <template #item="{ element: block, index }">
                <div class="ga-block">
                  <button
                    type="button"
                    class="ga-block__drag"
                    :title="t('grossanlass.planung.ressorts.taskBlockMove')"
                    :disabled="!briefing.can_edit || index === 0"
                  >
                    <v-icon icon="mdi-drag-vertical" size="20" />
                  </button>
                  <div class="ga-block__time">
                    <EDateField
                      v-model="block.date"
                      :label="t('grossanlass.planung.ressorts.taskBlockDate')"
                      :department-id="departmentId"
                      allow-past
                      :disabled="!briefing.can_edit"
                    />
                    <ETimeField
                      v-model="block.time"
                      :label="t('grossanlass.planung.ressorts.taskBlockTimeStart')"
                      :disabled="!briefing.can_edit"
                      hide-details
                    />
                    <ETimeField
                      v-model="block.end"
                      :label="t('grossanlass.planung.ressorts.taskBlockTimeEnd')"
                      :disabled="!briefing.can_edit"
                      hide-details
                    />
                  </div>
                  <p v-if="neededLabel(block)" class="ga-block__needed">{{ neededLabel(block) }}</p>
                  <div class="ga-block__body">
                    <ETextField
                      v-model="block.title"
                      class="ga-block__title"
                      :placeholder="t('grossanlass.planung.ressorts.taskBlockName')"
                      :disabled="!briefing.can_edit"
                      hide-details
                    />
                    <ETextarea
                      v-model="block.description"
                      class="ga-block__desc"
                      :placeholder="t('grossanlass.planung.ressorts.taskBlockTitle')"
                      :auto-grow="false"
                      :disabled="!briefing.can_edit"
                      hide-details
                    />
                  </div>
                  <EAutocomplete
                    class="ga-block__assignee"
                    :model-value="block.assigneeId || null"
                    v-model:menu="block.assigneeMenu"
                    v-model:search="block.assigneeSearch"
                    @update:model-value="block.assigneeId = typeof $event === 'string' ? $event : ''"
                    :items="assigneeItems"
                    item-title="title"
                    item-value="value"
                    :no-filter="false"
                    :placeholder="t('grossanlass.planung.ressorts.taskBlockAssignee')"
                    :hint="assigneeHint(block)"
                    :persistent-hint="!!assigneeHint(block)"
                    :disabled="!briefing.can_edit"
                    clearable
                    autocomplete="off"
                    :hide-details="assigneeHint(block) ? false : true"
                    :menu-props="{ zIndex: 10100, minWidth: 280 }"
                  >
                    <template #prepend-item>
                      <div class="ga-assignee-search" @mousedown.stop @click.stop>
                        <v-text-field
                          v-model="block.assigneeSearch"
                          :placeholder="t('grossanlass.planung.ressorts.taskBlockMemberSearch')"
                          prepend-inner-icon="mdi-magnify"
                          density="compact"
                          variant="outlined"
                          hide-details
                          autofocus
                          autocomplete="off"
                        />
                      </div>
                    </template>
                    <template #item="{ item, props: itemProps }">
                      <v-list-item v-bind="itemProps" :title="undefined">
                        <template #title>
                          <span class="ga-assignee-option">
                            <UserAvatarBadge
                              v-if="assigneeMember(item)"
                              :user="assigneeMember(item)!"
                              size="sm"
                              :show-tooltip="false"
                            />
                            <span>{{ item.title }}</span>
                          </span>
                        </template>
                      </v-list-item>
                    </template>
                    <template #selection="{ item }">
                      <span class="ga-assignee-selection">
                        <UserAvatarBadge
                          v-if="assigneeMember(item)"
                          :user="assigneeMember(item)!"
                          size="sm"
                          :show-tooltip="false"
                        />
                        <span class="ga-assignee-selection__name">{{ item.title }}</span>
                      </span>
                    </template>
                  </EAutocomplete>
                  <div class="ga-block__tools">
                    <span
                      v-if="taskSaveHint[block.key]"
                      class="ga-block__save"
                      :class="{ 'is-saved': taskSaveHint[block.key] === 'saved' }"
                      :title="taskSaveHint[block.key] === 'saved'
                        ? t('grossanlass.planung.ressorts.taskBlockSaved')
                        : t('grossanlass.planung.ressorts.taskBlockSaving')"
                    >
                      <v-icon icon="mdi-content-save" size="16" />
                    </span>
                    <button
                      v-if="briefing.can_edit && block.id"
                      type="button"
                      class="action-btn"
                      :title="t('common.delete')"
                      @click="removeTask(block.id)"
                    >
                      <v-icon icon="mdi-delete-outline" size="18" />
                    </button>
                  </div>
                </div>
              </template>
            </draggable>
            <button
              v-if="briefing.can_edit"
              type="button"
              class="ga-block__add"
              @click="addTaskBlock"
            >
              <v-icon icon="mdi-plus" size="22" />
            </button>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="material">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.ressorts.materialHeading') }}
            <span v-if="materialTitleMeta" class="ga-bauprojekt-panel__title-meta">{{ materialTitleMeta }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p v-if="!materialDrafts.length && !briefing.can_edit" class="muted">{{ t('grossanlass.planung.ressorts.materialEmpty') }}</p>
            <div
              v-for="row in materialDrafts"
              :key="row.key"
              class="ga-mat-entry"
              :class="{ 'is-editing': materialEditKey === row.key }"
            >
              <template v-if="materialEditKey === row.key">
                <div class="ga-mat-entry__body">
                  <div class="ga-mat-entry__line">
                    <ETextField
                      v-model="row.quantity"
                      type="number"
                      min="1"
                      :label="t('grossanlass.planung.ressorts.materialQty')"
                      hide-details
                    />
                    <div class="ga-mat-unit" role="group" :aria-label="t('grossanlass.planung.ressorts.materialUnit')">
                      <button
                        v-for="opt in materialUnitItems"
                        :key="opt.value"
                        type="button"
                        class="ga-mat-unit__btn"
                        :class="{ 'is-active': row.unit === opt.value }"
                        @click="row.unit = opt.value"
                      >
                        {{ opt.title }}
                      </button>
                    </div>
                    <ETextField
                      v-model="row.label"
                      :label="t('grossanlass.planung.ressorts.materialLabel')"
                      hide-details
                    />
                  </div>
                  <div class="ga-mat-entry__line ga-mat-entry__line--second">
                    <div class="ga-mat-row__self">
                      <label>
                        <input v-model="row.self" type="radio" :value="false" />
                        {{ t('grossanlass.planung.ressorts.materialModeWish') }}
                      </label>
                      <label>
                        <input v-model="row.self" type="radio" :value="true" />
                        {{ t('grossanlass.planung.ressorts.materialModeSelf') }}
                      </label>
                    </div>
                    <label class="ga-mat-check">
                      <input v-model="row.pickup" type="checkbox" />
                      {{ t('grossanlass.planung.ressorts.materialPickup') }}
                    </label>
                    <label class="ga-mat-check">
                      <input v-model="row.bringBack" type="checkbox" />
                      {{ t('grossanlass.planung.ressorts.materialReturn') }}
                    </label>
                    <ETextField
                      v-if="row.pickup"
                      v-model="row.pickupPlace"
                      :label="t('grossanlass.planung.ressorts.materialPickupPlace')"
                      hide-details
                    />
                  </div>
                </div>
                <div class="ga-mat-entry__side ga-mat-entry__side--row">
                  <span
                    v-if="materialSaveHint[row.key]"
                    class="ga-block__save"
                    :class="{ 'is-saved': materialSaveHint[row.key] === 'saved' }"
                    :title="materialSaveHint[row.key] === 'saved'
                      ? t('grossanlass.planung.ressorts.materialRowSaved')
                      : t('grossanlass.planung.ressorts.taskBlockSaving')"
                  >
                    <v-icon icon="mdi-content-save" size="16" />
                  </span>
                  <button
                    type="button"
                    class="action-btn"
                    :title="t('common.cancel')"
                    :disabled="row.saving"
                    @click="cancelMaterialEdit"
                  >
                    <v-icon icon="mdi-close" size="18" />
                  </button>
                </div>
              </template>
              <template v-else>
                <div
                  class="ga-mat-read"
                  :class="{ 'is-clickable': briefing.can_edit }"
                  @click="briefing.can_edit && beginMaterialEdit(row)"
                >
                  <p class="ga-mat-read__main">{{ row.quantity }} {{ stockUnitLabel(row.unit) }} × {{ row.label }}</p>
                  <p class="muted">{{ materialExtra(row) }}</p>
                </div>
                <div v-if="briefing.can_edit" class="ga-mat-entry__side ga-mat-entry__side--row">
                  <span
                    v-if="materialSaveHint[row.key]"
                    class="ga-block__save"
                    :class="{ 'is-saved': materialSaveHint[row.key] === 'saved' }"
                    :title="materialSaveHint[row.key] === 'saved'
                      ? t('grossanlass.planung.ressorts.materialRowSaved')
                      : t('grossanlass.planung.ressorts.taskBlockSaving')"
                  >
                    <v-icon icon="mdi-content-save" size="16" />
                  </span>
                  <button
                    type="button"
                    class="action-btn"
                    :title="t('common.edit')"
                    @click.stop="beginMaterialEdit(row)"
                  >
                    <v-icon icon="mdi-pencil" size="18" />
                  </button>
                  <button
                    v-if="row.id"
                    type="button"
                    class="action-btn action-btn-danger"
                    :title="t('common.delete')"
                    :disabled="deletingMaterialId === row.id"
                    @click="removeMaterial(row)"
                  >
                    <v-icon icon="mdi-delete-outline" size="18" />
                  </button>
                </div>
              </template>
            </div>
            <div v-if="briefing.can_edit && !materialEditKey" class="ga-mat-entry ga-mat-entry--add">
              <div class="ga-mat-entry__body">
                  <div class="ga-mat-entry__line">
                    <ETextField
                      v-model="materialComposer.quantity"
                      type="number"
                      min="1"
                      :label="t('grossanlass.planung.ressorts.materialQty')"
                      hide-details
                    />
                    <div class="ga-mat-unit" role="group" :aria-label="t('grossanlass.planung.ressorts.materialUnit')">
                      <button
                        v-for="opt in materialUnitItems"
                        :key="opt.value"
                        type="button"
                        class="ga-mat-unit__btn"
                        :class="{ 'is-active': materialComposer.unit === opt.value }"
                        @click="materialComposer.unit = opt.value"
                      >
                        {{ opt.title }}
                      </button>
                    </div>
                    <ETextField
                      v-model="materialComposer.label"
                    :label="t('grossanlass.planung.ressorts.materialLabel')"
                    hide-details
                  />
                </div>
                  <div class="ga-mat-entry__line ga-mat-entry__line--second">
                    <div class="ga-mat-row__self">
                      <label>
                        <input v-model="materialComposer.self" type="radio" :value="false" />
                        {{ t('grossanlass.planung.ressorts.materialModeWish') }}
                      </label>
                      <label>
                        <input v-model="materialComposer.self" type="radio" :value="true" />
                        {{ t('grossanlass.planung.ressorts.materialModeSelf') }}
                      </label>
                    </div>
                    <label class="ga-mat-check">
                      <input v-model="materialComposer.pickup" type="checkbox" />
                      {{ t('grossanlass.planung.ressorts.materialPickup') }}
                    </label>
                    <label class="ga-mat-check">
                      <input v-model="materialComposer.bringBack" type="checkbox" />
                      {{ t('grossanlass.planung.ressorts.materialReturn') }}
                    </label>
                    <ETextField
                      v-if="materialComposer.pickup"
                      v-model="materialComposer.pickupPlace"
                      :label="t('grossanlass.planung.ressorts.materialPickupPlace')"
                      hide-details
                    />
                  </div>
              </div>
              <span
                v-if="materialSaveHint[materialComposer.key]"
                class="ga-block__save"
                :class="{ 'is-saved': materialSaveHint[materialComposer.key] === 'saved' }"
                :title="materialSaveHint[materialComposer.key] === 'saved'
                  ? t('grossanlass.planung.ressorts.materialRowSaved')
                  : t('grossanlass.planung.ressorts.taskBlockSaving')"
              >
                <v-icon icon="mdi-content-save" size="16" />
              </span>
              <button
                type="button"
                class="ga-mat-entry__plus"
                :disabled="materialComposer.saving"
                :title="t('grossanlass.planung.ressorts.materialRowAdd')"
                @click="saveMaterialRow(materialComposer)"
              >
                <v-icon icon="mdi-plus" size="22" />
              </button>
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

      </v-expansion-panels>

      <div class="ga-bauprojekt-panel__footer">
        <a
          v-if="publicPlaceUrl"
          class="ga-bauprojekt-panel__link"
          :href="publicPlaceUrl"
          target="_blank"
          rel="noopener"
        >
          {{ t('grossanlass.planung.ressorts.placePublicView') }}
        </a>
        <EButton
          v-if="canPrintHelper"
          variant="secondary"
          size="small"
          :disabled="!briefing.group"
          @click="openPrint"
        >
          {{ t('grossanlass.planung.ressorts.printHelper') }}
        </EButton>
        <p v-else class="muted">{{ t('grossanlass.planung.ressorts.printOnlyWhenFixed') }}</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { EAutocomplete, EButton, EDateField, ESelect, ETextarea, ETextField, ETimeField } from '@/components/form/base'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import GaBuildMetaFields from '@/components/grossanlass/GaBuildMetaFields.vue'
import GrossanlassPlacePreviewMap from '@/components/grossanlass/GrossanlassPlacePreviewMap.vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { gaCanSeeAnlassOverview, gaCanSeeMaterialUebersicht } from '@/utils/grossanlassAccess'
import { formatUserNicknameFirstNameLastName } from '@/utils/userAvatar'
import { getStockUnitLabel } from '@/utils/materialStockUnit'
import {
  formatBauprojektWindow,
  packBauprojektWindow,
  unpackBauprojektWindow,
} from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
} from '@/utils/grossanlassBuildStatus'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import { gaMapOverlayBounds } from '@/utils/grossanlassGaMap'
import { resolveGaPlacePublicUrl } from '@/utils/publicQrUrl'
import {
  createGrossanlassPlace,
  updateGrossanlassPlace,
  type GaMap,
} from '@/api/grossanlassLogistics'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { getGrossanlassCommitments } from '@/api/grossanlassCommitments'
import { createGrossanlassEinsatz, getGrossanlassSubmitBoard } from '@/api/grossanlassUebersicht'
import { deleteGrossanlassWish } from '@/api/grossanlassWishes'
import { deleteGrossanlassProcurementLine } from '@/api/grossanlassProcurement'
import {
  addGrossanlassBauprojektMaterial,
  updateGrossanlassBauprojektMaterial,
  createGrossanlassBauprojektTask,
  deleteGrossanlassBauprojektTask,
  updateGrossanlassBauprojektTask,
  getGrossanlassBauprojekt,
  patchGrossanlassBauprojektWindow,
  type GaBauprojektBriefing,
} from '@/api/grossanlassBauprojekt'

const props = defineProps<{
  departmentId: string
  groupId: string
}>()
const emit = defineEmits<{
  'meta-saved': [group: NonNullable<GaBauprojektBriefing['group']>]
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const deletingMaterialId = ref<string | null>(null)
const router = useRouter()
const authStore = useAuthStore()

const loading = ref(false)
const error = ref<string | null>(null)
const briefing = ref<GaBauprojektBriefing | null>(null)
const windowStart = ref('')
const windowEnd = ref('')
const buildStatus = ref('')
const windowBaseline = ref('|')
const buildStatusBaseline = ref('')
const description = ref('')
const buildStatusText = computed(() => {
  if (!briefing.value) return ''
  return t(gaBuildStatusI18nKey(resolveBuildStatus({
    build_status: briefing.value.build_status ?? briefing.value.group?.build_status,
    window_start: briefing.value.window_start,
    window_end: briefing.value.window_end,
  })))
})
const savingWindow = ref(false)
const taskTitle = ref('')
const savingTask = ref(false)
const materialLabel = ref('')
const materialQty = ref('1')
const materialNotes = ref('')
const materialMode = ref<'wish' | 'fix' | 'self'>('wish')
const pickupNeed = ref<'' | 'can' | 'must'>('')
const pickupPlace = ref('')
const savingMaterial = ref(false)
const savingPlace = ref(false)
const placeEditing = ref(false)
const placeName = ref('')
const openSections = ref<string[]>(['material', 'tasks'])
const placeMapRef = ref<{ refreshSize: () => void } | null>(null)
const fixMaterialId = ref<string | null>(null)
const fixSearchQuery = ref('')
const fixCatalogLoading = ref(false)
const stockRows = ref<Array<{ id: string; name: string; quantity: number }>>([])
const organizerGroupId = ref('')
const organizerGroups = ref<GrossanlassGroup[]>([])

const canSeeStandorte = computed(() => gaCanSeeAnlassOverview(authStore.currentDepartmentRole))
const anlassStatus = computed(() => {
  const row = authStore.departments.find((d) => d.department_id === props.departmentId)
  return row?.department?.grossanlass_config?.status || 'draft'
})
const canPrintHelper = computed(() => anlassStatus.value !== 'draft')
const windowText = computed(() =>
  formatBauprojektWindow(briefing.value?.window_start, briefing.value?.window_end),
)
const standorteTo = computed(() => `/${props.departmentId}/einstellungen/standorte`)
const publicPlaceUrl = computed(() =>
  resolveGaPlacePublicUrl(briefing.value?.place?.qr_url, briefing.value?.place?.public_code),
)
const placeOverlay = computed(() => {
  const map = briefing.value?.map as GaMap | null | undefined
  const bounds = gaMapOverlayBounds(map)
  if (!map?.image_url || !bounds) return null
  return { url: map.image_url, ...bounds }
})
const placeSectionOpen = computed(() => openSections.value.includes('place'))
function coveredQtyForWish(wishId: string): number {
  return (briefing.value?.einsaetze ?? []).reduce((sum, row) => {
    if (row.wish_line_id !== wishId) return sum
    if (row.kind && row.kind !== 'einsatz') return sum
    return sum + Math.max(0, Number(row.qty) || 0)
  }, 0)
}

function coverageFor(quantity: number, covered: number): { coverage: 'gedeckt' | 'offen'; coverageLabel: string } {
  const open = Math.max(0, quantity - covered)
  if (open < 1) {
    return { coverage: 'gedeckt', coverageLabel: t('grossanlass.planung.ressorts.materialCoverageCovered') }
  }
  if (covered > 0) {
    return {
      coverage: 'offen',
      coverageLabel: t('grossanlass.planung.ressorts.materialCoveragePartial', { covered, open }),
    }
  }
  return { coverage: 'offen', coverageLabel: t('grossanlass.planung.ressorts.materialCoverageOpen') }
}

const materialUnitItems = computed(() => [
  { title: t('workshop.repairPartsList.unitStkShort'), value: 'Stk' as const },
  { title: 'm', value: 'm' as const },
])

function stockUnitLabel(unit?: string | null): string {
  return getStockUnitLabel(unit || 'Stk')
}

function materialUnit(value?: string | null): 'Stk' | 'm' {
  return String(value || '').trim().toLowerCase() === 'm' ? 'm' : 'Stk'
}

function pickupLabel(need?: string | null, place?: string | null): string {
  if (need !== 'can' && need !== 'must') return ''
  const text = t(`grossanlass.planung.ressorts.materialPickup${need === 'must' ? 'Must' : 'Can'}`)
  return place ? `${text} · ${place}` : text
}

const pickupItems = computed(() => [
  { title: t('grossanlass.planung.ressorts.materialPickupNone'), value: '' },
  { title: t('grossanlass.planung.ressorts.materialPickupCan'), value: 'can' },
  { title: t('grossanlass.planung.ressorts.materialPickupMust'), value: 'must' },
])

const materialRows = computed(() => {
  const wishes = (briefing.value?.material ?? []).map((line) => ({
    id: line.id,
    roundId: line.round_id,
    label: line.label,
    quantity: line.quantity,
    notes: line.notes || '',
    mode: 'wish' as const,
    modeLabel: t('grossanlass.planung.ressorts.materialModeWish'),
    pickupNeed: line.pickup_need || '',
    pickupLabel: pickupLabel(line.pickup_need, line.pickup_place),
    ...coverageFor(line.quantity, coveredQtyForWish(line.id)),
  }))
  const direct = (briefing.value?.direct_material ?? []).map((line) => ({
    id: line.id,
    label: line.label,
    quantity: line.quantity,
    notes: line.notes || '',
    mode: 'self' as const,
    modeLabel: t('grossanlass.planung.ressorts.materialModeSelfShort'),
    pickupNeed: line.pickup_need || '',
    pickupLabel: pickupLabel(line.pickup_need, line.pickup_place),
    coverage: line.status === 'erhalten' ? 'gedeckt' as const : 'offen' as const,
    coverageLabel: line.status === 'erhalten'
      ? t('grossanlass.planung.ressorts.materialCoverageCovered')
      : t('grossanlass.planung.ressorts.materialCoverageProcure'),
  }))
  return [...wishes, ...direct]
})

const hasProjectWindow = computed(() => Boolean(windowStart.value && windowEnd.value))

const projectFromIso = computed(() => (windowStart.value ? `${windowStart.value}T00:00:00` : ''))
const projectToIso = computed(() => (windowEnd.value ? `${windowEnd.value}T23:59:59` : ''))

function rangesOverlap(aFrom: string, aTo: string, bFrom: string, bTo: string): boolean {
  return aFrom < bTo && bFrom < aTo
}

function availableForStock(row: { id: string; quantity: number }): number {
  const stock = Math.max(0, Number(row.quantity) || 0)
  if (!projectFromIso.value || !projectToIso.value) return stock
  const used = (briefing.value?.einsaetze ?? []).reduce((sum, einsatz) => {
    if (einsatz.object_id !== row.id) return sum
    if (!rangesOverlap(einsatz.from, einsatz.to, projectFromIso.value, projectToIso.value)) return sum
    return sum + Math.max(0, Number(einsatz.qty) || 0)
  }, 0)
  return Math.max(0, stock - used)
}

type FixStockHit = { id: string; name: string; quantity: number; available: number }

function toFixHit(row: { id: string; name: string; quantity: number }): FixStockHit {
  return {
    id: row.id,
    name: row.name,
    quantity: row.quantity,
    available: availableForStock(row),
  }
}

async function fixMaterialFetcher(query: string): Promise<FixStockHit[]> {
  if (!stockRows.value.length) await loadFixCatalog()
  const needle = query.trim().toLowerCase()
  return stockRows.value
    .map(toFixHit)
    .filter((row) => !needle || row.name.toLowerCase().includes(needle))
    .sort((a, b) => a.name.localeCompare(b.name, 'de'))
}

function fixMaterialSecondary(item: FixStockHit): string {
  return item.available > 0
    ? t('grossanlass.planung.ressorts.materialAvailable', { n: item.available })
    : t('grossanlass.planung.ressorts.materialUnavailable')
}

function onFixMaterialSelect(item: FixStockHit) {
  fixMaterialId.value = item.id
  fixSearchQuery.value = item.name
}

watch(fixSearchQuery, (value) => {
  if (!fixMaterialId.value) return
  const selected = stockRows.value.find((row) => row.id === fixMaterialId.value)
  if (selected && value.trim() !== selected.name) {
    fixMaterialId.value = null
  }
})

const fixSelected = computed(() => {
  const row = stockRows.value.find((item) => item.id === fixMaterialId.value)
  if (!row) return null
  return toFixHit(row)
})

const organizerItems = computed(() => {
  const items = organizerGroups.value.map((group) => ({
    title: group.name,
    value: group.id,
  }))
  if (briefing.value?.group && !items.some((row) => row.value === briefing.value?.group?.id)) {
    items.unshift({ title: briefing.value.group.name, value: briefing.value.group.id })
  }
  return items
})

const canSubmitMaterial = computed(() => {
  if (savingMaterial.value) return false
  if (materialMode.value === 'fix') {
    return Boolean(fixSelected.value && fixSelected.value.available > 0 && hasProjectWindow.value)
  }
  if (materialMode.value === 'self') {
    return Boolean(String(materialLabel.value || '').trim() && organizerGroupId.value)
  }
  return Boolean(String(materialLabel.value || '').trim())
})

const materialSubmitLabel = computed(() => {
  if (materialMode.value === 'fix') return t('grossanlass.planung.ressorts.materialAddFix')
  if (materialMode.value === 'self') return t('grossanlass.planung.ressorts.materialAddSelf')
  return t('grossanlass.planung.ressorts.materialAdd')
})

const materialTitleMeta = computed(() => {
  const wishCount = briefing.value?.material?.length ?? 0
  const selfCount = briefing.value?.direct_material?.length ?? 0
  const parts: string[] = []
  if (wishCount) parts.push(t('grossanlass.meinRessort.wishCount', { n: wishCount }))
  if (selfCount) parts.push(t('grossanlass.meinRessort.selfCount', { n: selfCount }))
  return parts.length ? `· ${parts.join(' · ')}` : ''
})

const einsatzTitleMeta = computed(() => {
  const count = briefing.value?.einsaetze?.length ?? 0
  if (!count) return ''
  return `· ${t('grossanlass.meinRessort.einsatzCount', { n: count })}`
})

async function load(opts?: { silent?: boolean }) {
  if (!props.departmentId || !props.groupId) return
  if (!opts?.silent) {
    loading.value = true
    error.value = null
  }
  try {
    briefing.value = await getGrossanlassBauprojekt(props.departmentId, props.groupId)
    briefing.value.einsaetze ??= []
    briefing.value.direct_material ??= []
    applyMetaFromBriefing(briefing.value)
    syncEditors()
    if (!organizerGroupId.value) {
      organizerGroupId.value = briefing.value.group?.parent_id || briefing.value.group?.id || ''
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    if (!opts?.silent) {
      error.value = err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
    } else {
      toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad'))
    }
  } finally {
    if (!opts?.silent) loading.value = false
  }
}

function dialogScrollTop(): { el: HTMLElement; top: number } | null {
  const el = document.querySelector<HTMLElement>('.v-dialog--active .e-dialog__body')
  if (!el) return null
  return { el, top: el.scrollTop }
}

async function reloadKeepScroll() {
  const scroll = dialogScrollTop()
  await load({ silent: true })
  await nextTick()
  if (scroll) scroll.el.scrollTop = scroll.top
}

async function loadFixCatalog() {
  if (!props.departmentId || fixCatalogLoading.value) return
  fixCatalogLoading.value = true
  try {
    const pool = await getGrossanlassCommitments(props.departmentId).catch(() => [])
    const fromPool = (Array.isArray(pool) ? pool : []).map((row) => ({
      id: row.id,
      name: row.name,
      quantity: Number(row.quantity) || 0,
    }))
    if (fromPool.length) {
      stockRows.value = fromPool
      return
    }
    const board = await getGrossanlassSubmitBoard(props.departmentId)
    stockRows.value = (board.objects ?? []).map((row) => ({
      id: row.id,
      name: row.name,
      quantity: Number(row.qty) || 0,
    }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad'))
    stockRows.value = []
  } finally {
    fixCatalogLoading.value = false
  }
}

async function loadOrganizerGroups() {
  if (!props.departmentId || organizerGroups.value.length) return
  try {
    organizerGroups.value = await getGrossanlassGroups(props.departmentId)
    if (!organizerGroupId.value) {
      organizerGroupId.value = briefing.value?.group?.parent_id
        || briefing.value?.group?.id
        || organizerGroups.value[0]?.id
        || ''
    }
  } catch {
    organizerGroups.value = []
  }
}

function applyMetaFromBriefing(row: GaBauprojektBriefing) {
  windowStart.value = row.window_start || ''
  windowEnd.value = row.window_end || ''
  buildStatus.value = row.build_status || row.group?.build_status || ''
  description.value = row.description || row.group?.description || ''
  placeName.value = row.place?.name || row.group?.name || ''
  windowBaseline.value = packBauprojektWindow(windowStart.value, windowEnd.value)
  buildStatusBaseline.value = buildStatus.value
}

function syncBriefing(next: GaBauprojektBriefing) {
  briefing.value = next
  windowBaseline.value = packBauprojektWindow(next.window_start, next.window_end)
  buildStatusBaseline.value = next.build_status || next.group?.build_status || ''
  if (next.group) emit('meta-saved', next.group)
}

async function patchProjectMeta(extra: { description?: string | null } = {}) {
  const next = await patchGrossanlassBauprojektWindow(props.departmentId, props.groupId, {
    window_start: windowStart.value || null,
    window_end: windowEnd.value || null,
    build_status: buildStatus.value || null,
    ...extra,
  })
  syncBriefing(next)
  return next
}

async function saveWindowAutosave(value: AutoSaveFieldValue) {
  const next = unpackBauprojektWindow(value)
  windowStart.value = next.start
  windowEnd.value = next.end
  await patchProjectMeta()
}

async function saveStatusAutosave(value: AutoSaveFieldValue) {
  buildStatus.value = value == null ? '' : String(value)
  await patchProjectMeta()
}

async function saveDescription() {
  if (!briefing.value?.can_edit || savingWindow.value) return
  savingWindow.value = true
  try {
    await patchProjectMeta({ description: description.value.trim() || null })
    toast.success(t('grossanlass.planung.ressorts.descriptionSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingWindow.value = false
  }
}

async function savePlaceName() {
  if (!briefing.value?.can_edit || savingPlace.value) return
  const name = placeName.value.trim()
  if (!name) return
  savingPlace.value = true
  try {
    if (briefing.value.place) {
      briefing.value.place = await updateGrossanlassPlace(props.departmentId, briefing.value.place.id, { name })
    } else {
      briefing.value.place = await createGrossanlassPlace(props.departmentId, {
        name,
        group_id: props.groupId,
        kind: 'bauprojekt',
      })
    }
    placeEditing.value = false
    toast.success(t('grossanlass.planung.ressorts.placeSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingPlace.value = false
  }
}

async function onPlacePick(lat: number, lng: number) {
  if (!briefing.value?.can_edit || savingPlace.value) return
  savingPlace.value = true
  try {
    const name = placeName.value.trim() || briefing.value.place?.name?.trim() || ''
    if (briefing.value.place) {
      briefing.value.place = await updateGrossanlassPlace(props.departmentId, briefing.value.place.id, {
        latitude: lat,
        longitude: lng,
        ...(name ? { name } : {}),
      })
    } else {
      briefing.value.place = await createGrossanlassPlace(props.departmentId, {
        name: name || briefing.value.group?.name || t('grossanlass.planung.ressorts.kindBauprojekt'),
        group_id: props.groupId,
        kind: 'bauprojekt',
        latitude: lat,
        longitude: lng,
      })
    }
    if ((briefing.value.place?.name || '').trim()) {
      placeName.value = briefing.value.place.name
      placeEditing.value = false
    }
    toast.success(t('grossanlass.planung.ressorts.placeSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingPlace.value = false
  }
}

type TaskBlock = {
  key: string
  id: string | null
  title: string
  description: string
  date: string
  time: string
  end: string
  assigneeId: string
  assigneeMenu: boolean
  assigneeSearch: string
  saving: boolean
}

type MatDraft = {
  key: string
  id: string | null
  roundId?: string
  self: boolean
  label: string
  quantity: string
  unit: 'Stk' | 'm'
  pickup: boolean
  bringBack: boolean
  pickupPlace: string
  saving: boolean
}

function emptyMaterialRow(): MatDraft {
  return {
    key: 'composer',
    id: null,
    self: false,
    label: '',
    quantity: '1',
    unit: 'Stk',
    pickup: false,
    bringBack: false,
    pickupPlace: '',
    saving: false,
  }
}

const taskBlocks = ref<TaskBlock[]>([])
const taskSavedFingerprint = new Map<string, string>()
const taskAutosaveTimers = new Map<string, number>()
const taskSaveHint = ref<Record<string, 'saving' | 'saved'>>({})
const taskSaveHintTimers = new Map<string, number>()
const materialDrafts = ref<MatDraft[]>([])
const materialComposer = ref<MatDraft>(emptyMaterialRow())
const materialEditKey = ref<string | null>(null)
const materialEditSnapshot = ref<MatDraft | null>(null)
const materialSavedFingerprint = new Map<string, string>()
const materialAutosaveTimers = new Map<string, number>()
const materialSaveHint = ref<Record<string, 'saving' | 'saved'>>({})
const materialSaveHintTimers = new Map<string, number>()
const materialSaveBlocked = new Map<string, string>()

function splitStarts(iso?: string | null): { date: string; time: string } {
  if (!iso) return { date: '', time: '' }
  const [date, rest] = iso.split('T')
  return { date: date || '', time: (rest || '').slice(0, 5) }
}

function clockMinutes(value: string): number | null {
  const match = /^(\d{1,2}):(\d{2})$/.exec(value.trim())
  if (!match) return null
  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours > 23 || minutes > 59) return null
  return hours * 60 + minutes
}

function clockFromMinutes(total: number): string {
  const minutes = ((total % (24 * 60)) + 24 * 60) % (24 * 60)
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`
}

function durationMinutes(start: string, end: string): number | null {
  const from = clockMinutes(start)
  const to = clockMinutes(end)
  if (from == null || to == null) return null
  let diff = to - from
  if (diff < 0) diff += 24 * 60
  return diff > 0 ? diff : null
}

function syncEditors() {
  taskAutosaveTimers.forEach((timer) => window.clearTimeout(timer))
  taskAutosaveTimers.clear()
  taskSavedFingerprint.clear()
  taskBlocks.value = (briefing.value?.tasks ?? []).map((task) => {
    const slot = splitStarts(task.starts_at)
    const start = slot.time || '08:00'
    const startMin = clockMinutes(start)
    return {
      key: task.id,
      id: task.id,
      title: task.title,
      description: task.description || '',
      date: slot.date,
      time: start,
      end: task.duration_minutes && startMin != null
        ? clockFromMinutes(startMin + task.duration_minutes)
        : start,
      assigneeId: task.assignee_user_id || '',
      assigneeMenu: false,
      assigneeSearch: '',
      saving: false,
    }
  })
  if (briefing.value?.can_edit && taskBlocks.value.length === 0) {
    taskBlocks.value.push(blankTaskBlock())
  }
  for (const block of taskBlocks.value) {
    taskSavedFingerprint.set(block.key, taskFingerprint(block))
  }
  const wishes = (briefing.value?.material ?? []).map((line) => ({
    key: line.id,
    id: line.id,
    roundId: line.round_id,
    self: false,
    label: line.label,
    quantity: String(line.quantity),
    unit: materialUnit(line.quantity_unit),
    pickup: line.pickup_need === 'can' || line.pickup_need === 'must',
    bringBack: !!line.return_needed,
    pickupPlace: line.pickup_place || '',
    saving: false,
  }))
  const direct = (briefing.value?.direct_material ?? []).map((line) => ({
    key: line.id,
    id: line.id,
    self: true,
    label: line.label,
    quantity: String(line.quantity),
    unit: materialUnit(line.quantity_unit),
    pickup: line.pickup_need === 'can' || line.pickup_need === 'must',
    bringBack: !!line.return_needed,
    pickupPlace: line.pickup_place || '',
    saving: false,
  }))
  materialDrafts.value = [...wishes, ...direct]
  for (const row of materialDrafts.value) {
    materialSavedFingerprint.set(row.key, materialFingerprint(row))
  }
  materialSavedFingerprint.set(materialComposer.value.key, materialFingerprint(materialComposer.value))
}

const assigneeItems = computed(() => {
  const people = new Map<string, GrossanlassGroup['members'][number]>()
  const seen = new Set<string>()
  const walk = (id: string | null | undefined) => {
    if (!id || seen.has(id)) return
    seen.add(id)
    const group = organizerGroups.value.find((row) => row.id === id)
    if (!group) return
    for (const member of group.members || []) {
      if (!people.has(member.user_id)) people.set(member.user_id, member)
    }
    walk(group.parent_id)
  }
  walk(props.groupId)
  return [...people.values()]
    .map((member) => ({
      value: member.user_id,
      title: formatUserNicknameFirstNameLastName(member) || member.name,
      member,
    }))
    .sort((a, b) => a.title.localeCompare(b.title, 'de'))
})

function assigneeMember(item: {
  value?: string
  raw?: { value?: string; member?: GrossanlassGroup['members'][number] }
} | null | undefined) {
  if (item?.raw?.member) return item.raw.member
  const id = item?.raw?.value ?? item?.value
  if (!id) return undefined
  return assigneeItems.value.find((row) => row.value === id)?.member
}

async function onTasksReordered() {
  const saved = taskBlocks.value.filter((block) => block.id)
  await Promise.all(saved.map((block, index) =>
    updateGrossanlassBauprojektTask(props.departmentId, props.groupId, block.id as string, {
      sort_order: index,
    }),
  ))
}

function blankTaskBlock(): TaskBlock {
  return {
    key: `new-${Date.now()}-${taskBlocks.value.length}`,
    id: null,
    title: '',
    description: '',
    date: windowStart.value || '',
    time: '08:00',
    end: '08:00',
    assigneeId: '',
    assigneeMenu: false,
    assigneeSearch: '',
    saving: false,
  }
}

function addTaskBlock() {
  const previous = taskBlocks.value[taskBlocks.value.length - 1]
  const block = blankTaskBlock()
  if (previous) {
    const start = previous.end || previous.time || '08:00'
    block.date = previous.date || block.date
    block.time = start
    block.end = start
  }
  taskBlocks.value.push(block)
  taskSavedFingerprint.set(block.key, taskFingerprint(block))
}

function taskFingerprint(block: TaskBlock): string {
  return [block.title, block.description, block.date, block.time, block.end, block.assigneeId].join('\u0000')
}

function scheduleTaskAutosave(block: TaskBlock) {
  const existing = taskAutosaveTimers.get(block.key)
  if (existing) window.clearTimeout(existing)
  taskAutosaveTimers.set(block.key, window.setTimeout(() => {
    taskAutosaveTimers.delete(block.key)
    void saveTaskBlock(block, true)
  }, 700))
}

watch(taskBlocks, () => {
  if (!briefing.value?.can_edit) return
  for (const block of taskBlocks.value) {
    if (taskSavedFingerprint.get(block.key) === taskFingerprint(block)) continue
    if (!block.title.trim() && !block.description.trim()) continue
    scheduleTaskAutosave(block)
  }
}, { deep: true })

onBeforeUnmount(() => {
  taskAutosaveTimers.forEach((timer) => window.clearTimeout(timer))
  taskAutosaveTimers.clear()
  taskSaveHintTimers.forEach((timer) => window.clearTimeout(timer))
  taskSaveHintTimers.clear()
  materialAutosaveTimers.forEach((timer) => window.clearTimeout(timer))
  materialAutosaveTimers.clear()
  materialSaveHintTimers.forEach((timer) => window.clearTimeout(timer))
  materialSaveHintTimers.clear()
})

function assigneeHint(block: TaskBlock): string {
  if (block.assigneeId || block.assigneeMenu) return ''
  return t('grossanlass.planung.ressorts.taskBlockAssigneeHint')
}

function neededLabel(block: TaskBlock): string {
  const minutes = durationMinutes(block.time, block.end)
  if (!minutes) return ''
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  const label = mins
    ? t('grossanlass.planung.ressorts.taskBlockNeededHm', { hours, minutes: mins })
    : t('grossanlass.planung.ressorts.taskBlockNeededH', { hours })
  return t('grossanlass.planung.ressorts.taskBlockNeeded', { label })
}

function taskPayload(block: TaskBlock) {
  return {
    title: block.title.trim(),
    description: block.description.trim() || null,
    starts_at: block.date ? `${block.date}T${block.time || '00:00'}:00` : null,
    duration_minutes: durationMinutes(block.time, block.end),
    assignee_user_id: block.assigneeId || null,
  }
}

async function saveTaskBlock(block: TaskBlock, silent = false) {
  if (!block.title.trim() && !block.description.trim()) return
  const fp = taskFingerprint(block)
  if (taskSavedFingerprint.get(block.key) === fp) return
  if (block.saving) {
    scheduleTaskAutosave(block)
    return
  }
  block.saving = true
  taskSaveHint.value = { ...taskSaveHint.value, [block.key]: 'saving' }
  try {
    if (block.id) {
      await updateGrossanlassBauprojektTask(props.departmentId, props.groupId, block.id, taskPayload(block))
    } else {
      const created = await createGrossanlassBauprojektTask(props.departmentId, props.groupId, taskPayload(block))
      block.id = created.id
    }
    taskSavedFingerprint.set(block.key, fp)
    taskSaveHint.value = { ...taskSaveHint.value, [block.key]: 'saved' }
    const previous = taskSaveHintTimers.get(block.key)
    if (previous) window.clearTimeout(previous)
    taskSaveHintTimers.set(block.key, window.setTimeout(() => {
      const next = { ...taskSaveHint.value }
      delete next[block.key]
      taskSaveHint.value = next
      taskSaveHintTimers.delete(block.key)
    }, 1400))
    if (!silent) toast.success(t('grossanlass.planung.ressorts.taskBlockSaved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    const next = { ...taskSaveHint.value }
    delete next[block.key]
    taskSaveHint.value = next
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    block.saving = false
  }
}

function beginMaterialEdit(row: MatDraft) {
  if (materialEditKey.value && materialEditKey.value !== row.key) cancelMaterialEdit()
  materialEditSnapshot.value = { ...row }
  materialEditKey.value = row.key
  materialSavedFingerprint.set(row.key, materialFingerprint(row))
}

function cancelMaterialEdit() {
  const snap = materialEditSnapshot.value
  if (snap) {
    const pending = materialAutosaveTimers.get(snap.key)
    if (pending) {
      window.clearTimeout(pending)
      materialAutosaveTimers.delete(snap.key)
    }
    const index = materialDrafts.value.findIndex((row) => row.key === snap.key)
    if (index >= 0) materialDrafts.value[index] = { ...snap }
    materialSavedFingerprint.set(snap.key, materialFingerprint(snap))
  }
  materialEditKey.value = null
  materialEditSnapshot.value = null
}

function addMaterialRow() {
  materialDrafts.value.push({
    key: `new-${Date.now()}`,
    id: null,
    self: false,
    label: '',
    quantity: '1',
    unit: 'Stk',
    pickup: false,
    bringBack: false,
    pickupPlace: '',
    saving: false,
  })
}

function materialExtra(row: MatDraft): string {
  const parts = [
    row.self
      ? t('grossanlass.planung.ressorts.materialModeSelf')
      : t('grossanlass.planung.ressorts.materialModeWish'),
  ]
  if (row.pickup) {
    parts.push(row.pickupPlace.trim()
      ? `${t('grossanlass.planung.ressorts.materialPickup')} · ${row.pickupPlace.trim()}`
      : t('grossanlass.planung.ressorts.materialPickup'))
  }
  if (row.bringBack) parts.push(t('grossanlass.planung.ressorts.materialReturn'))
  return parts.join(' · ')
}

function materialFingerprint(row: MatDraft): string {
  return [
    row.label,
    row.quantity,
    row.unit,
    row.self ? '1' : '0',
    row.pickup ? '1' : '0',
    row.bringBack ? '1' : '0',
    row.pickupPlace,
  ].join('\u0000')
}

function showMaterialSaveHint(key: string, state: 'saving' | 'saved') {
  materialSaveHint.value = { ...materialSaveHint.value, [key]: state }
  if (state !== 'saved') return
  const previous = materialSaveHintTimers.get(key)
  if (previous) window.clearTimeout(previous)
  materialSaveHintTimers.set(key, window.setTimeout(() => {
    const next = { ...materialSaveHint.value }
    delete next[key]
    materialSaveHint.value = next
    materialSaveHintTimers.delete(key)
  }, 1400))
}

function scheduleMaterialAutosave(row: MatDraft) {
  const existing = materialAutosaveTimers.get(row.key)
  if (existing) window.clearTimeout(existing)
  materialAutosaveTimers.set(row.key, window.setTimeout(() => {
    materialAutosaveTimers.delete(row.key)
    void saveMaterialRow(row, true)
  }, 700))
}

watch(materialDrafts, () => {
  if (!briefing.value?.can_edit) return
  for (const row of materialDrafts.value) {
    if (materialEditKey.value !== row.key) continue
    if (materialSavedFingerprint.get(row.key) === materialFingerprint(row)) continue
    if (materialSaveBlocked.get(row.key) === materialFingerprint(row)) continue
    if (!row.label.trim()) continue
    scheduleMaterialAutosave(row)
  }
}, { deep: true })

watch(materialComposer, () => {
  if (!briefing.value?.can_edit || materialEditKey.value) return
  const row = materialComposer.value
  if (!row.label.trim()) return
  if (materialSavedFingerprint.get(row.key) === materialFingerprint(row)) return
  if (materialSaveBlocked.get(row.key) === materialFingerprint(row)) return
  scheduleMaterialAutosave(row)
}, { deep: true })

function materialFlags(row: MatDraft) {
  return {
    pickup_need: row.pickup ? 'must' as const : null,
    pickup_place: row.pickup ? row.pickupPlace.trim() || null : null,
    return_needed: row.bringBack,
    quantity_unit: row.unit || 'Stk',
  }
}

async function saveMaterialRow(row: MatDraft, silent = false) {
  const label = row.label.trim()
  if (!label || row.saving) return
  const fp = materialFingerprint(row)
  if (materialSavedFingerprint.get(row.key) === fp && silent) return
  row.saving = true
  showMaterialSaveHint(row.key, 'saving')
  const flags = materialFlags(row)
  const recreates = !row.id || Boolean(row.self && row.roundId) || Boolean(row.id && !row.self && !row.roundId)
  try {
    if (!row.id) {
      await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
        label,
        quantity: Math.max(1, Number(row.quantity) || 1),
        mode: row.self ? 'direct' : 'wish',
        ...flags,
      })
    } else if (row.self && row.roundId) {
      await deleteGrossanlassWish(props.departmentId, row.roundId, row.id)
      await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
        label,
        quantity: Math.max(1, Number(row.quantity) || 1),
        mode: 'direct',
        ...flags,
      })
    } else if (!row.self && !row.roundId) {
      await deleteGrossanlassProcurementLine(props.departmentId, row.id)
      await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
        label,
        quantity: Math.max(1, Number(row.quantity) || 1),
        mode: 'wish',
        ...flags,
      })
    } else {
      await updateGrossanlassBauprojektMaterial(props.departmentId, props.groupId, row.id, {
        label,
        quantity: Math.max(1, Number(row.quantity) || 1),
        mode: row.roundId ? 'wish' : 'direct',
        ...flags,
      })
    }
    materialSavedFingerprint.set(row.key, fp)
    materialSaveBlocked.delete(row.key)
    materialEditSnapshot.value = materialEditKey.value === row.key ? { ...row } : materialEditSnapshot.value
    showMaterialSaveHint(row.key, 'saved')
    if (!silent) toast.success(t('grossanlass.planung.ressorts.materialRowSaved'))
    if (!silent || recreates) {
      if (!row.id) materialComposer.value = emptyMaterialRow()
      materialEditKey.value = null
      materialEditSnapshot.value = null
      await reloadKeepScroll()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    materialSaveBlocked.set(row.key, fp)
    const next = { ...materialSaveHint.value }
    delete next[row.key]
    materialSaveHint.value = next
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    row.saving = false
  }
}

async function removeTask(taskId: string) {
  try {
    await deleteGrossanlassBauprojektTask(props.departmentId, props.groupId, taskId)
    await reloadKeepScroll()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  }
}

async function addMaterial() {
  if (!canSubmitMaterial.value || savingMaterial.value) return
  savingMaterial.value = true
  try {
    if (materialMode.value === 'fix') {
      const pick = fixSelected.value
      if (!pick || !projectFromIso.value || !projectToIso.value) return
      const qty = Math.min(
        Math.max(1, Number(materialQty.value) || 1),
        pick.available,
      )
      await createGrossanlassEinsatz(props.departmentId, {
        kind: 'einsatz',
        commitment_id: pick.id,
        qty,
        from: projectFromIso.value,
        to: projectToIso.value,
        who: pick.name,
        group_id: props.groupId,
        delivery: 'pickup',
        destination_place_id: briefing.value?.place?.id || null,
      })
      fixMaterialId.value = null
      fixSearchQuery.value = ''
      materialQty.value = '1'
      toast.success(t('grossanlass.planung.ressorts.materialAddedEinsatz'))
    } else {
      const label = String(materialLabel.value || '').trim()
      const organizer = organizerItems.value.find((row) => row.value === organizerGroupId.value)
      const notes = String(materialNotes.value || '').trim()
      const organizerNote = materialMode.value === 'self' && organizer
        ? t('grossanlass.planung.ressorts.materialOrganizerNote', { name: organizer.title })
        : ''
      await addGrossanlassBauprojektMaterial(props.departmentId, props.groupId, {
        label,
        quantity: Math.max(1, Number(materialQty.value) || 1),
        notes: [organizerNote, notes].filter(Boolean).join(' — ') || null,
        mode: materialMode.value === 'self' ? 'direct' : 'wish',
        pickup_need: pickupNeed.value || null,
        pickup_place: pickupNeed.value ? pickupPlace.value.trim() || null : null,
      })
      materialLabel.value = ''
      materialQty.value = '1'
      materialNotes.value = ''
      pickupNeed.value = ''
      pickupPlace.value = ''
      toast.success(
        materialMode.value === 'self'
          ? t('grossanlass.planung.ressorts.materialAddedSelf')
          : t('grossanlass.planung.ressorts.materialAdded'),
      )
    }
    await reloadKeepScroll()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    savingMaterial.value = false
  }
}

function formatEinsatzWindow(from: string, to: string): string {
  return formatBauprojektWindow(from.slice(0, 10), to.slice(0, 10))
}

async function removeMaterial(line: { id: string | null; self: boolean; roundId?: string; label: string }) {
  if (!line.id) {
    materialDrafts.value = materialDrafts.value.filter((row) => row !== line)
    return
  }
  if (!briefing.value?.can_edit || deletingMaterialId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.materialDeleteTitle'),
    message: t('grossanlass.planung.ressorts.materialDeleteMessage', { name: line.label }),
    variant: 'danger',
  })
  if (!ok) return
  deletingMaterialId.value = line.id
  try {
    if (!line.self && line.roundId) {
      if (!line.roundId) return
      await deleteGrossanlassWish(props.departmentId, line.roundId, line.id)
    } else {
      await deleteGrossanlassProcurementLine(props.departmentId, line.id)
    }
    toast.success(t('grossanlass.planung.ressorts.materialDeleted'))
    await reloadKeepScroll()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    deletingMaterialId.value = null
  }
}

function planTrip() {
  void router.push(`/${props.departmentId}/planung/transporte?create=1`)
}

function bookEinsatz(wishId?: string) {
  const query: Record<string, string> = { group: props.groupId }
  const placeId = briefing.value?.place?.id
  if (placeId) query.place = placeId
  if (wishId) query.wish = wishId
  const role = authStore.currentDepartmentRole || ''
  const name = gaCanSeeMaterialUebersicht(role)
    ? 'GrossanlassPlanungBelegung'
    : 'GrossanlassMeinRessort'
  void router.push({ name, params: { departmentId: props.departmentId }, query })
}

function openPrint() {
  if (!canPrintHelper.value) return
  void router.push({
    name: 'GrossanlassHelferauftragPrint',
    params: { departmentId: props.departmentId, groupId: props.groupId },
  })
}

watch(placeSectionOpen, (open) => {
  if (!open) return
  void nextTick(() => {
    placeMapRef.value?.refreshSize()
    setTimeout(() => placeMapRef.value?.refreshSize(), 320)
  })
})

watch(materialMode, (mode) => {
  if (mode === 'fix') void loadFixCatalog()
  if (mode === 'self') void loadOrganizerGroups()
})

watch(() => props.groupId, () => { void load() })
onMounted(() => {
  void load()
  void loadOrganizerGroups()
  void loadFixCatalog()
})
</script>

<style scoped>
.ga-bauprojekt-panel__error { color: #b91c1c; }
.muted { color: #64748b; font-size: 0.88rem; }
.ga-bauprojekt-panel__save-window { margin: 8px 0 8px; }
.ga-bauprojekt-panel__accordions { margin-bottom: 16px; }
.ga-bauprojekt-panel__list { list-style: none; padding: 0; margin: 0 0 8px; }
.ga-bauprojekt-panel__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  border-bottom: 1px solid #eef2f6;
}
.ga-bauprojekt-panel__add {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}
.ga-bauprojekt-panel__add-row {
  display: flex;
  gap: 8px;
  align-items: flex-end;
  width: 100%;
}
.ga-bauprojekt-panel__grow {
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
}
.ga-bauprojekt-panel__grow :deep(.e-form-field),
.ga-bauprojekt-panel__grow :deep(.v-input),
.ga-bauprojekt-panel__qty :deep(.e-form-field),
.ga-bauprojekt-panel__qty :deep(.v-input) {
  width: 100%;
}
.ga-bauprojekt-panel__lookup {
  width: 100%;
}
.ga-bauprojekt-panel__lookup :deep(.material-lookup-input) {
  width: 100%;
}
.ga-bauprojekt-panel__link {
  display: inline-block;
  margin: 10px 12px 0 0;
  font-size: 0.88rem;
}
.ga-bauprojekt-panel__beschrieb { white-space: pre-wrap; margin: 0; }
.ga-bauprojekt-panel__title-meta {
  margin-left: 8px;
  font-weight: 500;
  color: #64748b;
  font-size: 0.82rem;
}
.ga-bauprojekt-panel__modes { display: flex; gap: 4px; width: 100%; flex-wrap: wrap; }
.ga-bauprojekt-panel__mode {
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 0.82rem;
  cursor: pointer;
}
.ga-bauprojekt-panel__mode.is-on { background: #0f766e; color: #fff; border-color: #0f766e; }
.ga-bauprojekt-panel__chip {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.72rem;
  padding: 1px 6px;
  border-radius: 999px;
  background: #e2e8f0;
}
.ga-bauprojekt-panel__chip.direct { background: #dbeafe; }
.ga-bauprojekt-panel__chip.wish { background: #dcfce7; }
.ga-bauprojekt-panel__chip.self { background: #fef3c7; }
.ga-bauprojekt-panel__chip.gedeckt { background: #dcfce7; }
.ga-bauprojekt-panel__chip.offen { background: #ffedd5; }
.ga-place-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.ga-place-head p { margin: 0; }
.ga-place-gear {
  width: 32px;
  height: 32px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ga-place-gear:hover {
  color: var(--color-primary, #059669);
  border-color: var(--color-primary, #059669);
}
.ga-place-edit {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 8px;
  width: 100%;
  margin: 8px 0;
}
.ga-place-edit :deep(.e-form-field) {
  width: 100%;
  min-width: 0;
}
.ga-bauprojekt-panel__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}
.ga-block {
  display: grid;
  grid-template-columns: auto 168px minmax(0, 1fr) 188px auto;
  grid-template-rows: auto auto;
  gap: 8px;
  align-items: stretch;
  padding: 8px 0;
  border-bottom: 1px solid #eef2f6;
}
.ga-block__drag {
  grid-row: 1 / span 2;
  align-self: center;
  border: 0;
  background: transparent;
  color: #94a3b8;
  cursor: grab;
  padding: 8px 0;
}
.ga-block__time {
  grid-column: 2;
  grid-row: 1;
  align-self: start;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ga-block__needed {
  grid-column: 2;
  grid-row: 2;
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
}
.ga-block__body {
  grid-column: 3;
  grid-row: 1 / span 2;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ga-block__title {
  flex: 0 0 auto;
}
.ga-block__desc {
  flex: 1 1 auto;
  min-height: 96px;
  display: flex;
  flex-direction: column;
}
.ga-block__desc :deep(.autosave-control),
.ga-block__desc :deep(.autosave-field-frame),
.ga-block__desc :deep(.v-input),
.ga-block__desc :deep(.v-input__control),
.ga-block__desc :deep(.v-field) {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}
.ga-block__desc :deep(textarea) {
  height: 100% !important;
}
.ga-block__assignee {
  grid-column: 4;
  grid-row: 1 / span 2;
  align-self: start;
  min-width: 0;
}
.ga-block__tools {
  grid-column: 5;
  grid-row: 1 / span 2;
  align-self: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.ga-block__save {
  display: inline-flex;
  color: #16a34a;
}
.ga-block__save.is-saved {
  color: #059669;
}
.ga-assignee-search {
  padding: 8px 8px 4px;
}
.ga-assignee-search :deep(.v-field__prepend-inner .v-icon) {
  color: #64748b;
  opacity: 1;
}
.ga-assignee-option {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.ga-assignee-selection {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  max-width: 100%;
}
.ga-assignee-selection :deep(.user-avatar-badge) {
  flex: 0 0 auto;
}
.ga-assignee-selection__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}
.ga-block__drag:disabled {
  color: #cbd5e1;
  cursor: default;
}
.ga-block__add {
  display: flex;
  justify-content: center;
  width: 100%;
  margin-top: 8px;
  border: 0;
  background: transparent;
  color: #16a34a;
  cursor: pointer;
}
.ga-mat-entry {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: stretch;
  margin-bottom: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  padding: 8px;
}
.ga-mat-entry__body { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.ga-mat-entry__line {
  display: grid;
  grid-template-columns: 88px auto minmax(0, 1fr);
  gap: 8px;
  align-items: end;
}
.ga-mat-unit {
  display: flex;
  align-self: end;
  height: 40px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  overflow: hidden;
}
.ga-mat-unit__btn {
  min-width: 48px;
  border: 0;
  background: #fff;
  color: #334155;
  cursor: pointer;
  font-weight: 600;
  padding: 0 12px;
}
.ga-mat-unit__btn + .ga-mat-unit__btn {
  border-left: 1px solid #d1d5db;
}
.ga-mat-unit__btn.is-active {
  background: var(--color-primary, #059669);
  color: #fff;
}
.ga-mat-entry__line--second {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}
.ga-mat-entry__side {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.ga-mat-entry__side--row {
  flex-direction: row;
  align-self: center;
}
.ga-mat-read.is-clickable { cursor: pointer; }
.ga-mat-read__main { margin: 0; font-weight: 600; }
.ga-mat-read .muted { margin: 2px 0 0; }
.ga-mat-entry__save { color: #16a34a; }
.ga-mat-entry__plus {
  width: 44px;
  border: 0;
  border-radius: 6px;
  background: #16a34a;
  color: #fff;
  cursor: pointer;
}
.ga-mat-entry__plus:disabled { opacity: 0.6; cursor: default; }
.ga-mat-row { display: contents; }
.ga-block__times, .ga-mat-row__extra, .ga-block__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: flex-end;
}
.ga-mat-row__self { display: flex; gap: 16px; font-size: 0.88rem; }
.ga-mat-check {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  font-size: 0.88rem;
}
.ga-mat-row__self label { display: inline-flex; gap: 6px; align-items: center; }
.action-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
}
.action-btn-danger { color: #b91c1c; }
</style>
