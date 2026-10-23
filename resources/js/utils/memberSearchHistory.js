// Recently viewed members for the global member search. Only member IDs and
// view timestamps are kept in the browser; names and contact details are
// always loaded fresh from the server (scoped to the current gym).

export const MAX_RECENT = 10

/**
 * One history per user and gym, so switching gyms never mixes members.
 */
export function historyKey(userId, gymId) {
  return `gymportal.memberSearch.recent.${userId}.${gymId}`
}

/**
 * Reads the history defensively: storage may be unavailable (private mode)
 * or contain garbage from older versions.
 */
export function readHistory(storage, key) {
  try {
    const parsed = JSON.parse(storage?.getItem(key) ?? '[]')
    if (!Array.isArray(parsed)) return []

    return parsed
      .filter(entry => Number.isInteger(entry?.id) && entry.id > 0 && Number.isFinite(entry?.viewedAt))
      .slice(0, MAX_RECENT)
  } catch {
    return []
  }
}

/**
 * Moves the member to the top of the history without duplicates.
 */
export function addToHistory(history, id, viewedAt = Date.now(), max = MAX_RECENT) {
  return [{ id, viewedAt }, ...history.filter(entry => entry.id !== id)].slice(0, max)
}

export function writeHistory(storage, key, history) {
  try {
    storage?.setItem(key, JSON.stringify(history))
  } catch {
    // Quota exceeded or storage disabled: the history is a convenience only.
  }
}

/**
 * Short German label for when a member was last viewed, e.g. "vor 5 Min.",
 * "heute, 09:14", "gestern" or "14.10.".
 */
export function formatViewedAt(viewedAt, now = Date.now()) {
  const diffMinutes = Math.floor((now - viewedAt) / 60000)

  if (diffMinutes < 1) return 'gerade eben'
  if (diffMinutes < 60) return `vor ${diffMinutes} Min.`

  const viewed = new Date(viewedAt)
  const today = new Date(now)
  today.setHours(0, 0, 0, 0)

  if (viewed >= today) {
    const time = viewed.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
    return `heute, ${time}`
  }

  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)
  if (viewed >= yesterday) return 'gestern'

  return viewed.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}
