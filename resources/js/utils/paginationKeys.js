const KEY_DIRECTIONS = { ArrowLeft: 'prev', ArrowRight: 'next' }

const EDITABLE_TAGS = ['INPUT', 'TEXTAREA', 'SELECT']

// Widgets that use the arrow keys themselves.
const ARROW_KEY_WIDGETS = '[role="menu"], [role="menubar"], [role="tablist"], [role="listbox"], [role="radiogroup"], [role="slider"], [role="grid"], [role="tree"]'

// Inertia keeps closed dialogs (e.g. the mobile drawer) in the DOM as inert.
const OPEN_MODAL = '[aria-modal="true"]:not([inert])'

// An ancestor of the focused element that scrolls horizontally right now
// (e.g. the wide member table on a narrow screen); there the arrow keys
// keep scrolling it.
const insideHorizontalScroller = (target, win) => {
  for (let element = target; element && element !== win.document?.body; element = element.parentElement) {
    if (element.scrollWidth > element.clientWidth) {
      const overflowX = win.getComputedStyle(element).overflowX
      if (overflowX === 'auto' || overflowX === 'scroll') return true
    }
  }

  return false
}

// Which page a key press asks for — 'prev', 'next' or null when the key
// belongs to something else: a modifier combination (Alt+← is the
// browser's back), a form field, an arrow-key widget, an open dialog or a
// horizontally scrolling container.
export function paginationDirection(event, { win = globalThis.window } = {}) {
  const direction = KEY_DIRECTIONS[event.key]
  if (!direction || !win) return null
  if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return null

  const target = event.target
  if (target?.isContentEditable || EDITABLE_TAGS.includes(target?.tagName)) return null
  if (target?.closest?.(ARROW_KEY_WIDGETS)) return null
  if (win.document?.querySelector(OPEN_MODAL)) return null
  if (insideHorizontalScroller(target, win)) return null

  return direction
}
