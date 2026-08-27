import type { DepartmentPrintPreset, PrintDeviceModel, PrintMedia } from '@/api/printCatalog'

export function mediaCompatibleWithModel(model: PrintDeviceModel, media: PrintMedia): boolean {
  if (model.family !== media.family) return false
  if (!model.compatible_media_keys.length) return true
  if (model.compatible_media_keys.includes(media.catalog_key)) return true
  return media.scope === 'organisation' && media.status === 'published'
}

export function mediaCompatibleWithAnyModel(models: PrintDeviceModel[], media: PrintMedia): boolean {
  return models.some((model) => mediaCompatibleWithModel(model, media))
}

export function uniquePresetsByDevice(presets: DepartmentPrintPreset[]): DepartmentPrintPreset[] {
  const map = new Map<string, DepartmentPrintPreset>()
  for (const preset of presets) {
    const current = map.get(preset.device_model_id)
    if (!current || (preset.is_default && !current.is_default)) {
      map.set(preset.device_model_id, preset)
    }
  }
  return [...map.values()]
}

export function uniqueModelsFromPresets(
  presets: Array<{ device_model: PrintDeviceModel }>,
): PrintDeviceModel[] {
  const map = new Map<string, PrintDeviceModel>()
  for (const preset of presets) {
    map.set(preset.device_model.id, preset.device_model)
  }
  return [...map.values()]
}

export function defaultMediaForModel(model: PrintDeviceModel, media: PrintMedia[]): PrintMedia | null {
  const matching = media.filter((item) => mediaCompatibleWithModel(model, item))
  if (model.family === 'office_a4') {
    return matching.find((item) => item.catalog_key === 'iso_a4') || matching[0] || null
  }
  return matching[0] || null
}
