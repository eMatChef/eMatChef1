import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(resolve(__dirname, 'SidebarNavigation.vue'), 'utf8')

describe('SidebarNavigation styles', () => {
  it('imports the shared sidebar stylesheet before any local rule', () => {
    // Ein @import nach der ersten Regel wird vom Browser verworfen: die ganze Sidebar-Formatierung fehlt.
    const style = source.slice(source.indexOf('<style scoped>') + '<style scoped>'.length, source.lastIndexOf('</style>'))
    expect(style.trim().startsWith("@import '@/styles/sidebar.css';")).toBe(true)
  })

  it('keeps the setup lock wrapper layout-neutral', () => {
    expect(source).toMatch(/\.nav-setup-locked\s*\{\s*display:\s*contents;/)
  })
})
