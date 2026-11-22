import { nextTick, onScopeDispose, watch } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const visibleFocusables = (container) =>
  [...container.querySelectorAll(FOCUSABLE)].filter((el) => el.getClientRects().length > 0)

/**
 * Keeps keyboard focus inside `containerRef` while `active` is true (modal
 * drawers and dialogs). On activation focus moves to the first focusable
 * element unless something inside already has it (e.g. a search input focused
 * synchronously within the opening tap); on deactivation it returns to the
 * element that was focused before.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef
 * @param {import('vue').Ref<boolean>} active
 */
export function useFocusTrap(containerRef, active) {
  let previouslyFocused = null

  function onKeydown(event) {
    if (event.key !== 'Tab' || !containerRef.value) return

    const focusables = visibleFocusables(containerRef.value)
    if (focusables.length === 0) return

    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    const current = document.activeElement

    if (event.shiftKey && (current === first || !containerRef.value.contains(current))) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && (current === last || !containerRef.value.contains(current))) {
      event.preventDefault()
      first.focus()
    }
  }

  watch(active, async (isActive) => {
    if (isActive) {
      previouslyFocused = document.activeElement
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      const container = containerRef.value
      if (container && !container.contains(document.activeElement)) {
        visibleFocusables(container)[0]?.focus()
      }
    } else {
      document.removeEventListener('keydown', onKeydown)
      // Only pull focus back if it is still inside the closed container (or
      // lost); after a link click the browser may already have moved it.
      const container = containerRef.value
      const focusLost = !document.activeElement || document.activeElement === document.body
      if (focusLost || container?.contains(document.activeElement)) {
        previouslyFocused?.focus?.({ preventScroll: true })
      }
      previouslyFocused = null
    }
  })

  onScopeDispose(() => document.removeEventListener('keydown', onKeydown))
}
