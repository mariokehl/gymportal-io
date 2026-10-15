import { onScopeDispose, watch } from 'vue'

export const BOTTOM_BAR_HEIGHT_VAR = '--bottom-bar-height'

/**
 * Publishes the height of a bar pinned to the bottom of the screen as the
 * CSS variable --bottom-bar-height on <html>, so floating elements such as
 * the Chatwoot launcher can move above it instead of covering it.
 *
 * @param {import('vue').Ref<HTMLElement|null>} barRef - Template ref of the bar; null while it is not rendered
 */
export function useBottomBarOffset(barRef) {
  const root = document.documentElement
  let observer = null

  const reset = () => {
    observer?.disconnect()
    observer = null
    root.style.removeProperty(BOTTOM_BAR_HEIGHT_VAR)
  }

  const publish = (el) => {
    root.style.setProperty(BOTTOM_BAR_HEIGHT_VAR, `${el.offsetHeight}px`)
  }

  watch(barRef, (el) => {
    reset()
    if (!el) return

    publish(el)
    // The bar grows with the safe-area inset and wrapping text
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(() => publish(el))
      observer.observe(el)
    }
  }, { immediate: true, flush: 'post' })

  onScopeDispose(reset)
}
