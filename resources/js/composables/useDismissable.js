import { onScopeDispose, watch } from 'vue'

/**
 * Shared close behaviour for popovers, menus and drawers. While `open` is
 * true it closes on
 * - a pointer press outside `rootRef` (the trigger belongs inside the root),
 * - Escape, returning focus to `triggerRef` so keyboard users keep their place,
 * - an Inertia visit, since the persistent shell outlives page changes.
 *
 * @param {import('vue').Ref<HTMLElement|null>} rootRef
 * @param {import('vue').Ref<boolean>} open
 * @param {{ triggerRef?: import('vue').Ref<HTMLElement|null> }} [options]
 */
export function useDismissable(rootRef, open, { triggerRef } = {}) {
  function close({ restoreFocus = false } = {}) {
    if (!open.value) return

    open.value = false
    if (restoreFocus) triggerRef?.value?.focus()
  }

  function onPointerDown(event) {
    if (!rootRef.value?.contains(event.target)) close()
  }

  function onKeydown(event) {
    if (event.key === 'Escape') close({ restoreFocus: true })
  }

  function onNavigate() {
    close()
  }

  function listen() {
    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeydown)
  }

  function unlisten() {
    document.removeEventListener('pointerdown', onPointerDown)
    document.removeEventListener('keydown', onKeydown)
  }

  watch(open, (isOpen) => (isOpen ? listen() : unlisten()), { immediate: true })
  document.addEventListener('inertia:navigate', onNavigate)

  onScopeDispose(() => {
    unlisten()
    document.removeEventListener('inertia:navigate', onNavigate)
  })

  return { close }
}
