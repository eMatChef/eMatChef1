/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_TURNSTILE_SITE_KEY?: string
  /** 1/true = Registrierung ohne Turnstile-Widget (zu TURNSTILE_SKIP_VERIFY im Backend) */
  readonly VITE_TURNSTILE_SKIP?: string
  /** 1/true = gelber Hinweis „Testumgebung“ (zusaetzlich zu bekannten Dev-Hosts). */
  readonly VITE_SHOW_DEV_BANNER?: string
  /** SemVer from package.json / Hostpoint build (e.g. 4.0.1). */
  readonly VITE_APP_VERSION?: string
  /** Short git SHA from Hostpoint build (7 chars). */
  readonly VITE_APP_GIT_SHA?: string
  /** Public Better Stack status page URL (optional). */
  readonly VITE_STATUS_PAGE_URL?: string
  /** Google Search Console HTML-Tag (content=…). Nur Marketing-Build. */
  readonly VITE_GOOGLE_SITE_VERIFICATION?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

interface Window {
  turnstile?: {
    render: (el: HTMLElement, opts: { sitekey: string; theme?: string }) => string
    reset: (widgetId: string) => void
    remove: (widgetId: string) => void
    getResponse: (widgetId: string) => string | undefined
  }
}
