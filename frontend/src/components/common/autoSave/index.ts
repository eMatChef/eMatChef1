/**
 * Auto-Save Formularfelder (debounced save, Loader oben, Diskette nur nach Erfolg).
 *
 * @example Einfaches Textfeld
 * ```vue
 * <AutoSaveField
 *   v-model="form.name"
 *   :baseline="baselines.name"
 *   label="Name"
 *   :save="(v) => api.patch({ name: v })"
 * />
 * ```
 *
 * @example Eigenes Steuerelement (Slot) – onChange für debounced save
 * ```vue
 * <AutoSaveField v-model="form.category_id" :baseline="baselines.category_id" label="Kategorie" :save="saveCategory">
 *   <template #default="{ onFocus, onBlur, onChange }">
 *     <CategoryAutocompleteInput
 *       v-model="form.category_id"
 *       @focus="onFocus"
 *       @blur="onBlur"
 *       @change="onChange"
 *     />
 *   </template>
 * </AutoSaveField>
 * ```
 */
export { default as AutoSaveField } from './AutoSaveField.vue'
export { default as AutoSaveFieldShell } from './AutoSaveFieldShell.vue'
export type {
  AutoSaveFieldSaveFn,
  AutoSaveFieldType,
  AutoSaveFieldValue,
  AutoSaveSelectOption,
  AutoSaveSlotBindings,
  AutoSaveStatus,
} from './types'

export { useAutoSaveField, DEFAULT_AUTO_SAVE_DELAY_MS, AUTO_SAVE_SUCCESS_ICON_MS } from '@/composables/useAutoSaveField'
export { useFormFieldBaselines } from '@/composables/useFormFieldBaselines'
export {
  normalizeAutoSaveValue,
  parseAutoSaveInputValue,
} from '@/utils/autoSaveFieldValue'
