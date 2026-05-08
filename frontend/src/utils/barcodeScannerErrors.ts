/**
 * Maps raw getUserMedia / ZXing error strings (often English) to vue-i18n keys.
 * Falls back to `components.barcodeScanner.cameraStartError`.
 */
export function localizedBarcodeScannerError(message: string, t: (key: string) => string): string {
  const generic = t('components.barcodeScanner.cameraStartError')
  const trimmed = (message ?? '').trim()
  if (!trimmed) {
    return generic
  }

  const knownKeys = [
    'components.barcodeScanner.cameraStartError',
    'components.barcodeScanner.errorPermission',
    'components.barcodeScanner.errorNoCamera',
    'components.barcodeScanner.errorAborted',
    'components.barcodeScanner.errorInUse',
  ] as const
  for (const key of knownKeys) {
    if (trimmed === t(key)) return trimmed
  }

  const m = trimmed.toLowerCase()

  if (
    /notallowederror|permission denied|not allowed by the user agent|notallowed|securityerror/.test(m)
  ) {
    return t('components.barcodeScanner.errorPermission')
  }
  if (
    /notfounderror|requested device not found|no camera|no devices found|devicesnotfounderror|could not find starting/.test(
      m
    )
  ) {
    return t('components.barcodeScanner.errorNoCamera')
  }
  if (/aborterror|aborted/.test(m)) {
    return t('components.barcodeScanner.errorAborted')
  }
  if (
    /notreadableerror|could not start video|could not start source|in use|busy|occupied|hardware/.test(m)
  ) {
    return t('components.barcodeScanner.errorInUse')
  }

  return generic
}
