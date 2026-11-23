// Brings the top of a paginated list back into view after a page change,
// like search results do. Nothing happens while the list top is still
// visible below the element's scroll margin (e.g. under a sticky header),
// so short lists don't jump.
//
// The jump is deliberately instant: with `preserveScroll` Inertia reads the
// live scroll position right before swapping the page and restores it
// afterwards. A smooth scroll still running at that moment would be cut
// off halfway, which happens whenever the response is fast.
export function scrollListIntoView(element, { win = globalThis.window } = {}) {
  if (!element || !win) return false

  const margin = parseFloat(win.getComputedStyle?.(element).scrollMarginTop) || 0
  if (element.getBoundingClientRect().top >= margin) return false

  element.scrollIntoView({ block: 'start', behavior: 'instant' })

  return true
}

// Moves keyboard focus to the list after a page change, but only when it
// was inside the pagination controls — otherwise the focused button would
// stay at the bottom while the view is at the top.
export function focusListIfPaginating(listElement, paginationElement, { doc = globalThis.document } = {}) {
  if (!listElement || !paginationElement || !doc) return false
  if (!paginationElement.contains(doc.activeElement)) return false

  listElement.focus({ preventScroll: true })

  return true
}
