// Average height of the rows currently rendered in a list container, so
// skeleton rows can take their place without the layout changing height.
// Returns null when the container is missing, hidden (e.g. the table on a
// phone, where cards are shown instead) or empty.
export function averageRowHeight(container, rowCount) {
  if (!container || !rowCount) return null

  const height = container.getBoundingClientRect().height
  if (!height) return null

  return height / rowCount
}
