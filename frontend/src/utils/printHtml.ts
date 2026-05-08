/**
 * Druckt ein vollständiges HTML-Dokument ohne Popup.
 *
 * `window.open('', '_blank', 'noopener')` liefert in Chromium/Firefox oft `null`
 * (kein Opener-Handle) → „Popup blockiert“. Ein verstecktes iframe wird nicht blockiert.
 */
export function printHtmlDocument(fullHtml: string): void {
  const iframe = document.createElement('iframe')
  iframe.setAttribute('aria-hidden', 'true')
  iframe.style.cssText =
    'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none'

  document.body.appendChild(iframe)
  const doc = iframe.contentDocument
  const win = iframe.contentWindow
  if (!doc || !win) {
    try {
      document.body.removeChild(iframe)
    } catch {
      /* ignore */
    }
    return
  }

  doc.open()
  doc.write(fullHtml)
  doc.close()

  const cleanup = () => {
    try {
      win.removeEventListener('afterprint', onAfterPrint)
      clearTimeout(fallbackTimer)
      if (iframe.parentNode) iframe.parentNode.removeChild(iframe)
    } catch {
      /* ignore */
    }
  }
  const onAfterPrint = () => cleanup()
  win.addEventListener('afterprint', onAfterPrint)
  const fallbackTimer = window.setTimeout(cleanup, 120000)

  const triggerPrint = () => {
    try {
      win.focus()
      win.print()
    } catch {
      cleanup()
    }
  }

  // Nach doc.write+close ist das Dokument meist sofort bereit; kurz warten für Layout/Bilder
  window.setTimeout(triggerPrint, 0)
}
