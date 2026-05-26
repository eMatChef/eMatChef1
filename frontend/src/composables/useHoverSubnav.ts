import { ref } from 'vue'

/** Untermenü: eingeklappt, bei Hover öffnen, nach Klick wieder zu. */
export function useHoverSubnav() {
  const expanded = ref(false)

  function open() {
    expanded.value = true
  }

  function close() {
    expanded.value = false
  }

  function onNavClick() {
    expanded.value = false
  }

  return { expanded, open, close, onNavClick }
}
