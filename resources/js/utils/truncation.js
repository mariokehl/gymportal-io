// Whether text inside an element is cut off with an ellipsis. Looks at the
// element itself and every descendant using Tailwind's `truncate` class: such
// an element is truncated when its content is wider than its visible box.
export function hasTruncatedText(root) {
  if (!root) return false

  const candidates = [
    ...(root.classList?.contains('truncate') ? [root] : []),
    ...(root.querySelectorAll?.('.truncate') ?? []),
  ]

  return candidates.some(element => element.scrollWidth > element.clientWidth)
}
