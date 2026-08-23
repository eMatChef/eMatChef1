import { reactive } from 'vue'
import {
  clonePreviewWishForm,
  createGrossanlassWishFormPreview,
  defaultPreviewFieldsForPurpose,
  type GaPreviewWishForm,
  type GaWishFormPurpose,
} from '@/views/grossanlass/grossanlassWishFormsPreviewData'
import type { GrossanlassRoundStatus } from '@/api/grossanlassRounds'

type Translate = (key: string, values?: Record<string, string | number>) => string

const state = reactive({
  extras: [] as GaPreviewWishForm[],
  overrides: {} as Record<string, GaPreviewWishForm>,
})

function factoryForms(t: Translate): GaPreviewWishForm[] {
  return createGrossanlassWishFormPreview(t)
}

export function listPreviewWishForms(
  t: Translate,
  purpose: Exclude<GaWishFormPurpose, 'material_wish'>,
): GaPreviewWishForm[] {
  return [
    ...factoryForms(t)
      .filter((row) => row.purpose === purpose)
      .map((row) => state.overrides[row.id] ?? row),
    ...state.extras.filter((row) => row.purpose === purpose),
  ]
}

export function addPreviewWishForm(input: {
  name: string
  purpose: Exclude<GaWishFormPurpose, 'material_wish'>
  status?: GrossanlassRoundStatus
  opens_at?: string | null
  closes_at?: string | null
  intro_text?: string
  t: Translate
}): GaPreviewWishForm {
  const form: GaPreviewWishForm = {
    id: `preview-form-${Date.now().toString(36)}`,
    name: input.name,
    purpose: input.purpose,
    status: input.status ?? 'scheduled',
    opens_at: input.opens_at ?? null,
    closes_at: input.closes_at ?? null,
    intro_text: input.intro_text ?? '',
    fields: defaultPreviewFieldsForPurpose(input.t, input.purpose),
    samples: [],
  }
  state.extras.push(form)
  return form
}

export function findPreviewWishForm(t: Translate, id: string): GaPreviewWishForm | undefined {
  return (
    state.extras.find((row) => row.id === id)
    ?? state.overrides[id]
    ?? factoryForms(t).find((row) => row.id === id)
  )
}

export function ensureMutablePreviewForm(t: Translate, id: string): GaPreviewWishForm | undefined {
  const extra = state.extras.find((row) => row.id === id)
  if (extra) return extra
  if (state.overrides[id]) return state.overrides[id]
  const factory = factoryForms(t).find((row) => row.id === id)
  if (!factory) return undefined
  const clone = clonePreviewWishForm(factory)
  state.overrides[id] = clone
  return clone
}

export function updatePreviewWishForm(
  t: Translate,
  id: string,
  patch: Partial<Pick<GaPreviewWishForm, 'name' | 'opens_at' | 'closes_at' | 'status' | 'intro_text'>>,
): GaPreviewWishForm | undefined {
  const form = ensureMutablePreviewForm(t, id)
  if (!form) return undefined
  Object.assign(form, patch)
  return form
}
