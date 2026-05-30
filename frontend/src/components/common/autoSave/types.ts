export type {
  AutoSaveFieldType,
  AutoSaveFieldValue,
} from '@/utils/autoSaveFieldValue'

export type AutoSaveStatus = 'idle' | 'saving' | 'saved' | 'error'

export type AutoSaveSelectOption = {
  value: string
  label: string
}

export type AutoSaveFieldSaveFn = (value: import('@/utils/autoSaveFieldValue').AutoSaveFieldValue) => Promise<void>

export type AutoSaveSlotBindings = {
  inputId: string
  disabled: boolean
  inputClass: string
  onFocus: () => void
  onBlur: () => void | Promise<void>
  onInput: (event: Event) => void
  onChange: () => void | Promise<void>
}
