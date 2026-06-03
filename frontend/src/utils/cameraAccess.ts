/** Browser erlauben getUserMedia nur in Secure Contexts (HTTPS, localhost, 127.0.0.1). */
export function isSecureCameraContext(): boolean {
  return typeof window !== 'undefined' && window.isSecureContext === true
}

export function hasGetUserMedia(): boolean {
  return typeof navigator !== 'undefined' && typeof navigator.mediaDevices?.getUserMedia === 'function'
}

export function canRequestCamera(): boolean {
  return isSecureCameraContext() && hasGetUserMedia()
}
