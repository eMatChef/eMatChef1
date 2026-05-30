import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import {
  normalizeAutoSaveValue,
  parseAutoSaveInputValue,
  type AutoSaveFieldType,
  type AutoSaveFieldValue,
} from '@/utils/autoSaveFieldValue'
import type { AutoSaveStatus } from '@/components/common/autoSave/types'

export const DEFAULT_AUTO_SAVE_DELAY_MS = 800
export const AUTO_SAVE_SUCCESS_ICON_MS = 2000

export type UseAutoSaveFieldOptions = {
  modelValue: Ref<AutoSaveFieldValue>
  baseline?: Ref<AutoSaveFieldValue | undefined>
  type?: AutoSaveFieldType
  disabled?: Ref<boolean> | boolean
  /** Wie ecamp3: Auto-Save beim Tippen (debounced). Select/Checkbox speichern sofort. */
  autoSave?: boolean
  autoSaveDelay?: number
  save: (value: AutoSaveFieldValue) => Promise<void>
}

export function useAutoSaveField(options: UseAutoSaveFieldOptions) {
  const type = options.type ?? 'text'
  const autoSave = options.autoSave ?? true
  const autoSaveDelay = options.autoSaveDelay ?? DEFAULT_AUTO_SAVE_DELAY_MS
  const saveImmediately = type === 'select' || type === 'checkbox'

  const isFocused = ref(false)
  const status = ref<AutoSaveStatus>('idle')
  const errorMessage = ref('')
  const isPreSaving = ref(false)
  const savingRequestCount = ref(0)
  const showSuccessIcon = ref(false)
  const internalBaseline = ref(
    normalizeAutoSaveValue(options.baseline?.value ?? options.modelValue.value),
  )
  const baselineValue = ref<AutoSaveFieldValue>(
    options.baseline?.value ?? options.modelValue.value,
  )

  let debounceTimer: ReturnType<typeof setTimeout> | null = null
  let successIconTimer: ReturnType<typeof setTimeout> | null = null
  let saveToken = 0

  const isDisabled = computed(() =>
    typeof options.disabled === 'boolean' ? options.disabled : !!options.disabled?.value,
  )

  const isSaving = computed(() => savingRequestCount.value > 0)

  const isDirty = computed(
    () => normalizeAutoSaveValue(options.modelValue.value) !== internalBaseline.value,
  )

  const hasDisplayValue = computed(() => {
    if (type === 'checkbox') return true
    return normalizeAutoSaveValue(options.modelValue.value) !== ''
  })

  watch(
    () => options.baseline?.value,
    (next) => {
      if (next !== undefined) {
        baselineValue.value = next
        internalBaseline.value = normalizeAutoSaveValue(next)
      }
    },
  )

  function markBaseline(value: AutoSaveFieldValue) {
    baselineValue.value = value
    internalBaseline.value = normalizeAutoSaveValue(value)
  }

  function resetBaselineFromModel() {
    internalBaseline.value = normalizeAutoSaveValue(options.modelValue.value)
    baselineValue.value = options.modelValue.value
  }

  function clearDebounce() {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
  }

  function clearSuccessIconTimer() {
    if (successIconTimer) {
      clearTimeout(successIconTimer)
      successIconTimer = null
    }
  }

  function showSuccessIconBriefly() {
    showSuccessIcon.value = true
    clearSuccessIconTimer()
    successIconTimer = setTimeout(() => {
      showSuccessIcon.value = false
      if (status.value === 'saved') status.value = 'idle'
    }, AUTO_SAVE_SUCCESS_ICON_MS)
  }

  function scheduleDebouncedSave() {
    if (!autoSave || saveImmediately) return
    clearDebounce()
    debounceTimer = setTimeout(() => {
      debounceTimer = null
      void trySave()
    }, autoSaveDelay)
  }

  function handleFocus() {
    isFocused.value = true
    if (showSuccessIcon.value) showSuccessIcon.value = false
    if (status.value === 'saved') status.value = 'idle'
  }

  function handleInput(event: Event, emitUpdate: (value: AutoSaveFieldValue) => void) {
    const el = event.target as HTMLInputElement | HTMLTextAreaElement
    const next = parseAutoSaveInputValue(el.value, type, options.modelValue.value)
    emitUpdate(next)
    isPreSaving.value = true
    errorMessage.value = ''
    if (status.value === 'error') status.value = 'idle'
    showSuccessIcon.value = false
    if (autoSave && !saveImmediately) {
      scheduleDebouncedSave()
    }
  }

  function notifyValueChange() {
    isPreSaving.value = true
    errorMessage.value = ''
    if (status.value === 'error') status.value = 'idle'
    showSuccessIcon.value = false
    if (autoSave && !saveImmediately) {
      scheduleDebouncedSave()
    }
  }

  function handleSelectChange(event: Event, emitUpdate: (value: AutoSaveFieldValue) => void) {
    const el = event.target as HTMLSelectElement
    const next: AutoSaveFieldValue = el.value === '' ? null : el.value
    emitUpdate(next)
    isPreSaving.value = true
    errorMessage.value = ''
    if (status.value === 'error') status.value = 'idle'
    showSuccessIcon.value = false
    void trySave(next)
  }

  function handleCheckboxChange(event: Event, emitUpdate: (value: AutoSaveFieldValue) => void) {
    const el = event.target as HTMLInputElement
    const next = el.checked
    emitUpdate(next)
    isPreSaving.value = true
    errorMessage.value = ''
    if (status.value === 'error') status.value = 'idle'
    showSuccessIcon.value = false
    void trySave(next)
  }

  function revertToBaseline(emitUpdate: (value: AutoSaveFieldValue) => void) {
    emitUpdate(baselineValue.value ?? null)
    errorMessage.value = ''
    status.value = 'idle'
    showSuccessIcon.value = false
    isPreSaving.value = false
  }

  async function handleBlur(emitUpdate: (value: AutoSaveFieldValue) => void) {
    isFocused.value = false

    if (debounceTimer) {
      clearDebounce()
      await trySave()
    }

    if (status.value === 'error') return

    // Select/Checkbox speichern sofort bei change — kein Blur-Revert
    if (saveImmediately) return

    // ecamp3: ohne Eingabe beim Blur auf DB-Stand zurück
    if (!isSaving.value && !isPreSaving.value && autoSave) {
      revertToBaseline(emitUpdate)
    }
  }

  async function trySave(explicitValue?: AutoSaveFieldValue) {
    if (isDisabled.value) {
      isPreSaving.value = false
      return
    }

    const valueToSave = explicitValue !== undefined ? explicitValue : options.modelValue.value
    if (normalizeAutoSaveValue(valueToSave) === internalBaseline.value) {
      isPreSaving.value = false
      return
    }

    const token = ++saveToken
    isPreSaving.value = false
    status.value = 'saving'
    errorMessage.value = ''
    showSuccessIcon.value = false
    savingRequestCount.value++

    try {
      await options.save(valueToSave)
      if (token !== saveToken) return
      internalBaseline.value = normalizeAutoSaveValue(valueToSave)
      baselineValue.value = valueToSave
      status.value = 'saved'
      showSuccessIconBriefly()
    } catch (err: unknown) {
      if (token !== saveToken) return
      status.value = 'error'
      showSuccessIcon.value = false
      const msg =
        (err as { response?: { data?: { error?: string } }; message?: string })?.response?.data
          ?.error ||
        (err as Error)?.message ||
        ''
      errorMessage.value = msg
    } finally {
      savingRequestCount.value = Math.max(0, savingRequestCount.value - 1)
    }
  }

  onBeforeUnmount(() => {
    clearDebounce()
    clearSuccessIconTimer()
  })

  return {
    isFocused,
    status,
    errorMessage,
    isDirty,
    hasDisplayValue,
    isSaving,
    isPreSaving,
    showSuccessIcon,
    markBaseline,
    resetBaselineFromModel,
    handleFocus,
    handleInput,
    handleSelectChange,
    handleCheckboxChange,
    handleBlur,
    notifyValueChange,
    revertToBaseline,
    trySave,
  }
}
