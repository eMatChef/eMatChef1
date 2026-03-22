import 'vue-router'

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, any>
  export default component
}

declare module 'vue-router' {
  interface RouteMeta {
    pageTitle?: string
    pageDescription?: string
  }
}
