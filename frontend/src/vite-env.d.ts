/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_TURNSTILE_SITE_KEY?: string
  /** 1/true = Registrierung ohne Turnstile-Widget (zu TURNSTILE_SKIP_VERIFY im Backend) */
  readonly VITE_TURNSTILE_SKIP?: string
  /** 1/true = gelber Hinweis „Testumgebung“ (zusaetzlich zu bekannten Dev-Hosts). */
  readonly VITE_SHOW_DEV_BANNER?: string
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
